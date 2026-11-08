<?php
/**
 * AgeWell Senior Care and Support System
 * Add New Appointment Page
 */

// Include configuration and required files
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/notification_handler.php';
require_once 'includes/sms_functions.php';

/**
 * Get senior citizen's name by ID
 * @param int $seniorId The ID of the senior citizen
 * @return string The name of the senior citizen or 'Unknown Senior' if not found
 */
function getSeniorName($seniorId) {
    $senior = fetchOne("SELECT name FROM seniorcitizens WHERE id = ?", [$seniorId], 'i');
    return $senior ? $senior['name'] : 'Unknown Senior';
}

// Check if user is logged in
if (!isLoggedIn()) {
    setAlert('danger', 'Please login to access this page.');
    redirect('login.php');
}

// Get senior ID from query parameter if available
$preselected_senior_id = isset($_GET['senior_id']) ? (int)$_GET['senior_id'] : null;

// Initialize variables for form
$senior_id = $preselected_senior_id;
$caregiver_id = $_SESSION['user_id']; // Default to current user if caregiver
$doctor_name = '';
$appointment_datetime = '';
$purpose = '';
$location = '';
$notes = '';
$errors = [];

// Get all seniors for dropdown
$seniors = fetchAll("SELECT id, name FROM seniorcitizens ORDER BY name ASC");

// Get all caregivers for dropdown
$caregivers = fetchAll("SELECT id, name FROM userdetails WHERE role = ? ORDER BY name ASC", [ROLE_CAREGIVER], 's');

// Add debug logging
error_log("Starting appointment creation process");

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $senior_id = isset($_POST['senior_id']) ? (int)$_POST['senior_id'] : null;
    $caregiver_id = isset($_POST['caregiver_id']) ? (int)$_POST['caregiver_id'] : null;
    $doctor_name = cleanInput($_POST['doctor_name'] ?? '');
    $appointment_datetime = cleanInput($_POST['appointment_datetime'] ?? '');
    $purpose = cleanInput($_POST['purpose'] ?? '');
    $location = cleanInput($_POST['location'] ?? '');
    $notes = cleanInput($_POST['notes'] ?? '');
    
    error_log("Appointment details received: " . json_encode($_POST));
    
    // Validate form data
    if (empty($senior_id)) {
        $errors['senior_id'] = 'Senior citizen is required.';
    } else {
        // Check if senior exists
        $senior = getSeniorById($senior_id);
        if (!$senior) {
            $errors['senior_id'] = 'Selected senior citizen does not exist.';
        }
    }
    
    if (!empty($caregiver_id)) {
        // Check if caregiver exists
        $caregiver = fetchOne("SELECT id FROM userdetails WHERE id = ? AND role = ?", [$caregiver_id, ROLE_CAREGIVER], 'is');
        if (!$caregiver) {
            $errors['caregiver_id'] = 'Selected caregiver does not exist.';
        }
    }
    
    if (empty($doctor_name)) {
        $errors['doctor_name'] = 'Doctor name is required.';
    }
    
    if (empty($appointment_datetime)) {
        $errors['appointment_datetime'] = 'Appointment date and time is required.';
    } elseif (strtotime($appointment_datetime) < time()) {
        $errors['appointment_datetime'] = 'Appointment date and time cannot be in the past.';
    }
    
    if (empty($purpose)) {
        $errors['purpose'] = 'Purpose is required.';
    }
    
    // If no errors, insert into database
    if (empty($errors)) {
        $data = [
            'senior_id' => $senior_id,
            'caregiver_id' => $caregiver_id,
            'doctor_name' => $doctor_name,
            'appointment_datetime' => $appointment_datetime,
            'purpose' => $purpose,
            'location' => $location,
            'notes' => $notes,
            'status' => 'scheduled',
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $stmt = $conn->prepare("INSERT INTO appointments (senior_id, caregiver_id, doctor_name, appointment_datetime, purpose, location, notes, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iisssssss", $data['senior_id'], $data['caregiver_id'], $data['doctor_name'], $data['appointment_datetime'], $data['purpose'], $data['location'], $data['notes'], $data['status'], $data['created_at']);
        
        if ($stmt->execute()) {
            $appointmentId = $conn->insert_id;
            error_log("Appointment created successfully with ID: $appointmentId");
            
            // Get senior's details for notification
            $seniorSql = "SELECT name, phone, emergency_contact, emergency_phone 
                          FROM seniorcitizens 
                          WHERE id = ?";
            $seniorStmt = $conn->prepare($seniorSql);
            $seniorStmt->bind_param("i", $senior_id);
            $seniorStmt->execute();
            $seniorResult = $seniorStmt->get_result();
            $senior = $seniorResult->fetch_assoc();

            error_log("Senior details retrieved: " . json_encode($senior));

            // Format appointment date and time
            $appointmentDate = new DateTime($appointment_datetime);
            $formattedDateTime = $appointmentDate->format('F j, Y \a\t g:i A');

            // Prepare messages
            $seniorMessage = "Hello {$senior['name']}, your appointment with {$doctor_name} is scheduled for {$formattedDateTime} at {$location}. Purpose: {$purpose}";
            $emergencyMessage = "Hello {$senior['emergency_contact']}, this is a notification that {$senior['name']} has an appointment with {$doctor_name} scheduled for {$formattedDateTime} at {$location}.";

            // Send to senior
            if ($senior['phone']) {
                $seniorPhone = formatPhoneNumber($senior['phone']);
                error_log("Attempting to send SMS to senior at: $seniorPhone");
                $seniorSent = sendSMS($seniorPhone, $seniorMessage);
                error_log("Senior notification " . ($seniorSent ? "sent successfully" : "failed"));
            }

            // Send to emergency contact
            if ($senior['emergency_phone']) {
                $emergencyPhone = formatPhoneNumber($senior['emergency_phone']);
                error_log("Attempting to send SMS to emergency contact at: $emergencyPhone");
                $emergencySent = sendSMS($emergencyPhone, $emergencyMessage);
                error_log("Emergency contact notification " . ($emergencySent ? "sent successfully" : "failed"));
            }

            // Log the activity
            logActivity('appointment_add', "Added new appointment for senior: " . getSeniorName($senior_id));
            
            $_SESSION['success'] = "Appointment scheduled successfully. Notifications have been sent.";
        } else {
            error_log("Error creating appointment: " . $conn->error);
            $_SESSION['error'] = "Error scheduling appointment: " . $conn->error;
        }

        header("Location: appointments.php");
        exit();
    }
}

// Set page title
$pageTitle = 'Schedule Appointment';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule Appointment - AgeWell Senior Care</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background: #23242a;
            color: #cdcdcd;
            min-height: 100vh;
        }

        /* Header styling */
        .header {
            background: #1c1c1c;
            border-bottom: 1px solid #45f3ff;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 20px;
            position: sticky;
            top: 0;
            z-index: 1030;
        }

        .header .logo {
            color: #45f3ff;
            font-weight: 500;
            display: flex;
            align-items: center;
        }

        .header .logo i {
            margin-right: 8px;
        }

        .header .nav-links {
            display: flex;
        }

        .header .nav-links a {
            color: #8f8f8f;
            text-decoration: none;
            padding: 0 15px;
            transition: color 0.3s;
        }

        .header .nav-links a:hover {
            color: #45f3ff;
        }

        .header .user-menu {
            display: flex;
            align-items: center;
        }

        .header .user-menu a {
            color: #8f8f8f;
            text-decoration: none;
            margin-left: 15px;
            transition: color 0.3s;
        }

        .header .user-menu a:hover {
            color: #45f3ff;
        }

        /* Main container */
        .main-container {
            display: flex;
            height: calc(100vh - 60px);
        }

        /* Sidebar */
        .sidebar {
            width: 250px;
            background: #1c1c1c;
            border-right: 1px solid rgba(69, 243, 255, 0.2);
            padding: 20px 0;
            overflow-y: auto;
        }

        .sidebar h6 {
            color: #45f3ff;
            padding: 15px 20px 10px;
            font-size: 14px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .sidebar ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .sidebar ul li a {
            display: flex;
            align-items: center;
            color: #8f8f8f;
            text-decoration: none;
            padding: 10px 20px;
            transition: all 0.3s;
        }

        .sidebar ul li a:hover {
            background: rgba(69, 243, 255, 0.1);
            color: #45f3ff;
        }

        .sidebar ul li a i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        /* Main content */
        .main-content {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
        }

        .page-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(69, 243, 255, 0.3);
        }

        .page-title h1 {
            color: #45f3ff;
            font-size: 28px;
            font-weight: 500;
        }

        /* Card styling */
        .card {
            background: #28292d;
            border: 1px solid rgba(69, 243, 255, 0.3);
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            margin-bottom: 20px;
            overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.3);
            border-color: rgba(69, 243, 255, 0.6);
        }

        .card-header {
            background: #1c1c1c;
            border-bottom: 1px solid rgba(69, 243, 255, 0.3);
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-header h6 {
            color: #45f3ff;
            margin: 0;
            font-weight: 500;
            font-size: 16px;
        }

        .card-body {
            padding: 20px;
        }

        /* Form styling */
        .form-label {
            color: #45f3ff;
            font-weight: 500;
            margin-bottom: 8px;
        }

        .form-control, .form-select {
            background-color: #1c1c1c;
            border: 1px solid rgba(69, 243, 255, 0.3);
            color: #cdcdcd;
            border-radius: 4px;
            padding: 10px 15px;
            transition: all 0.3s;
        }

        .form-control:focus, .form-select:focus {
            background-color: #1c1c1c;
            border-color: #45f3ff;
            box-shadow: 0 0 0 0.25rem rgba(69, 243, 255, 0.25);
            color: #fff;
        }

        .form-select option {
            background-color: #1c1c1c;
            color: #cdcdcd;
        }

        .text-danger {
            color: #ff3860 !important;
        }

        .invalid-feedback {
            color: #ff3860;
        }

        .is-invalid {
            border-color: #ff3860 !important;
        }

        /* Button styling */
        .btn {
            padding: 8px 16px;
            border-radius: 4px;
            transition: all 0.3s;
            font-weight: 500;
        }

        .btn-primary {
            background: #45f3ff;
            border-color: #45f3ff;
            color: #1c1c1c;
        }

        .btn-primary:hover {
            background: #28d7e5;
            border-color: #28d7e5;
            color: #1c1c1c;
        }

        .btn-secondary {
            background: #2c2c2c;
            border-color: #2c2c2c;
            color: #cdcdcd;
        }

        .btn-secondary:hover {
            background: #3a3a3a;
            border-color: #3a3a3a;
            color: #fff;
        }

        .btn-outline-primary {
            background: transparent;
            border-color: #45f3ff;
            color: #45f3ff;
        }

        .btn-outline-primary:hover {
            background: rgba(69, 243, 255, 0.1);
            color: #45f3ff;
        }

        /* Alert styling */
        .alert {
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
        }

        .alert-success {
            background-color: rgba(75, 219, 106, 0.1);
            border-color: rgba(75, 219, 106, 0.3);
            color: #4bdb6a;
        }

        .alert-danger {
            background-color: rgba(255, 56, 96, 0.1);
            border-color: rgba(255, 56, 96, 0.3);
            color: #ff3860;
        }

        /* Quick access buttons */
        .quick-access {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .quick-access .btn {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 12px;
            font-weight: 500;
        }

        .quick-access .btn i {
            margin-right: 8px;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="logo">
            <i class="fas fa-heartbeat"></i>
            <span>AgeWell Senior Care</span>
        </div>
        <div class="nav-links">
            <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="senior_citizens.php"><i class="fas fa-users"></i> Senior Citizens</a>
            <a href="appointments.php" class="active"><i class="fas fa-calendar-check"></i> Appointments</a>
            <a href="health_metrics.php"><i class="fas fa-heartbeat"></i> Health Metrics</a>
        </div>
        <div class="user-menu">
            <a href="profile.php"><i class="fas fa-user-circle"></i> Profile</a>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <!-- Main container -->
    <div class="main-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <h6>Quick Actions</h6>
            <ul>
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="appointment_add.php"><i class="fas fa-calendar-plus"></i> Schedule Appointment</a></li>
            </ul>
            
            <h6>Appointment Management</h6>
            <ul>
                <li><a href="appointments.php"><i class="fas fa-calendar"></i> All Appointments</a></li>
                <li><a href="appointments.php?status=scheduled"><i class="fas fa-clock"></i> Upcoming Appointments</a></li>
                <li><a href="appointments.php?status=completed"><i class="fas fa-check-circle"></i> Completed Appointments</a></li>
            </ul>
            
            <h6>Quick Reports</h6>
            <ul>
                <li><a href="reports.php?type=seniors"><i class="fas fa-users"></i> Senior Citizens Report</a></li>
                <li><a href="reports.php?type=appointments"><i class="fas fa-calendar-check"></i> Appointments Report</a></li>
                <li><a href="reports.php?type=health_metrics"><i class="fas fa-chart-line"></i> Health Metrics Report</a></li>
            </ul>
        </div>
        
        <!-- Main content -->
        <div class="main-content">
            <div class="page-title">
                <h1><i class="fas fa-calendar-plus me-2"></i> Schedule Appointment</h1>
                <div class="quick-access">
                    <a href="appointments.php" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left"></i> Back to Appointments
                    </a>
                </div>
            </div>
            
            <?php if (isset($_SESSION['alert_type']) && isset($_SESSION['alert_message'])): ?>
                <div class="alert alert-<?php echo $_SESSION['alert_type']; ?> alert-dismissible fade show" role="alert">
                    <i class="fas <?php echo $_SESSION['alert_type'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> me-2"></i>
                    <?php echo $_SESSION['alert_message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['alert_type'], $_SESSION['alert_message']); ?>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h6><i class="fas fa-calendar-alt me-2"></i> Appointment Details</h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="appointment_add.php<?php echo $preselected_senior_id ? "?senior_id=$preselected_senior_id" : ''; ?>" class="needs-validation" novalidate>
                        <div class="row mb-3">
                            <!-- Senior Selection -->
                            <div class="col-md-6 mb-3">
                                <label for="senior_id" class="form-label">Senior Citizen <span class="text-danger">*</span></label>
                                <select class="form-select <?php echo isset($errors['senior_id']) ? 'is-invalid' : ''; ?>" 
                                        id="senior_id" name="senior_id" required>
                                    <option value="">Select Senior Citizen</option>
                                    <?php foreach ($seniors as $senior): ?>
                                        <option value="<?php echo $senior['id']; ?>" 
                                                <?php echo $senior_id == $senior['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($senior['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (isset($errors['senior_id'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['senior_id']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Caregiver Selection -->
                            <div class="col-md-6 mb-3">
                                <label for="caregiver_id" class="form-label">Assigned Caregiver</label>
                                <select class="form-select <?php echo isset($errors['caregiver_id']) ? 'is-invalid' : ''; ?>" 
                                        id="caregiver_id" name="caregiver_id">
                                    <option value="">Select Caregiver (Optional)</option>
                                    <?php foreach ($caregivers as $caregiver): ?>
                                        <option value="<?php echo $caregiver['id']; ?>" 
                                                <?php echo $caregiver_id == $caregiver['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($caregiver['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (isset($errors['caregiver_id'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['caregiver_id']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Doctor Name -->
                            <div class="col-md-6 mb-3">
                                <label for="doctor_name" class="form-label">Doctor Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control <?php echo isset($errors['doctor_name']) ? 'is-invalid' : ''; ?>" 
                                       id="doctor_name" name="doctor_name" value="<?php echo htmlspecialchars($doctor_name); ?>" required>
                                <?php if (isset($errors['doctor_name'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['doctor_name']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Appointment Date and Time -->
                            <div class="col-md-6 mb-3">
                                <label for="appointment_datetime" class="form-label">Date and Time <span class="text-danger">*</span></label>
                                <input type="datetime-local" class="form-control <?php echo isset($errors['appointment_datetime']) ? 'is-invalid' : ''; ?>" 
                                       id="appointment_datetime" name="appointment_datetime" value="<?php echo htmlspecialchars($appointment_datetime); ?>" required>
                                <?php if (isset($errors['appointment_datetime'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['appointment_datetime']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Purpose -->
                            <div class="col-md-6 mb-3">
                                <label for="purpose" class="form-label">Purpose <span class="text-danger">*</span></label>
                                <input type="text" class="form-control <?php echo isset($errors['purpose']) ? 'is-invalid' : ''; ?>" 
                                       id="purpose" name="purpose" value="<?php echo htmlspecialchars($purpose); ?>" required>
                                <?php if (isset($errors['purpose'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['purpose']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Location -->
                            <div class="col-md-6 mb-3">
                                <label for="location" class="form-label">Location</label>
                                <input type="text" class="form-control" id="location" name="location" 
                                       value="<?php echo htmlspecialchars($location); ?>">
                            </div>
                            
                            <!-- Notes -->
                            <div class="col-md-12 mb-3">
                                <label for="notes" class="form-label">Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo htmlspecialchars($notes); ?></textarea>
                            </div>
                        </div>
                        
                        <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-calendar-check me-1"></i> Schedule Appointment
                                </button>
                            <a href="appointments.php" class="btn btn-secondary">
                                <i class="fas fa-times me-1"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add active class to current page in sidebar
            const currentPage = window.location.pathname.split('/').pop();
            document.querySelectorAll('.sidebar a').forEach(link => {
                if (link.getAttribute('href') === currentPage) {
                    link.classList.add('active');
                    link.style.color = '#45f3ff';
                    link.style.background = 'rgba(69, 243, 255, 0.1)';
                }
            });
            
            // Form validation
            const forms = document.querySelectorAll('.needs-validation');
            Array.from(forms).forEach(form => {
                form.addEventListener('submit', event => {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                }, false);
            });
        });
    </script>
</body>
</html>
