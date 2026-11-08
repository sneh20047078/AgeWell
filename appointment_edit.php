<?php
/**
 * AgeWell Senior Care and Support System
 * Edit Appointment Page
 */

// Include configuration and required files
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/notification_handler.php';
require_once 'includes/sms_functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    setAlert('danger', 'Please login to access this page.');
    redirect('login.php');
}

// Check if appointment ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    setAlert('danger', 'Appointment ID is missing.');
    redirect('appointments.php');
}

// Get appointment ID and fetch details
$appointmentId = (int)$_GET['id'];
$appointment = fetchOne(
    "SELECT * FROM appointments WHERE id = ?", 
    [$appointmentId], 
    'i'
);

if (!$appointment) {
    setAlert('danger', 'Appointment not found.');
    redirect('appointments.php');
}

// Get senior details
$senior = getSeniorById($appointment['senior_id']);
if (!$senior) {
    setAlert('danger', 'Senior citizen not found.');
    redirect('appointments.php');
}

// Initialize variables with existing data
$senior_id = $appointment['senior_id'];
$caregiver_id = $appointment['caregiver_id'];
$doctor_name = $appointment['doctor_name'];
$appointment_datetime = $appointment['appointment_datetime'];
$purpose = $appointment['purpose'];
$location = $appointment['location'];
$status = $appointment['status'];
$notes = $appointment['notes'];
$errors = [];

// Get all seniors for dropdown
$seniors = fetchAll("SELECT id, name FROM seniorcitizens ORDER BY name ASC");

// Get all caregivers for dropdown
$caregivers = fetchAll("SELECT id, name FROM userdetails WHERE role = ? ORDER BY name ASC", [ROLE_CAREGIVER], 's');

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $senior_id = isset($_POST['senior_id']) ? (int)$_POST['senior_id'] : null;
    $caregiver_id = isset($_POST['caregiver_id']) ? (int)$_POST['caregiver_id'] : null;
    $doctor_name = cleanInput($_POST['doctor_name'] ?? '');
    $appointment_datetime = cleanInput($_POST['appointment_datetime'] ?? '');
    $purpose = cleanInput($_POST['purpose'] ?? '');
    $location = cleanInput($_POST['location'] ?? '');
    $status = cleanInput($_POST['status'] ?? '');
    $notes = cleanInput($_POST['notes'] ?? '');
    
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
    }
    
    if (empty($purpose)) {
        $errors['purpose'] = 'Purpose is required.';
    }
    
    if (empty($status) || !in_array($status, ['scheduled', 'completed', 'cancelled', 'no_show'])) {
        $errors['status'] = 'Valid status is required.';
    }
    
    // If no errors, update database
    if (empty($errors)) {
        $data = [
            'senior_id' => $senior_id,
            'caregiver_id' => $caregiver_id,
            'doctor_name' => $doctor_name,
            'appointment_datetime' => $appointment_datetime,
            'purpose' => $purpose,
            'location' => $location,
            'status' => $status,
            'notes' => $notes,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        $updated = update('appointments', $data, 'id = ?', [$appointmentId], 'i');
        
        if ($updated) {
            // Log activity
            $seniorName = $senior['name'];
            logActivity('appointment_update', "Updated appointment for senior: $seniorName");
            
            // If appointment was rescheduled, create a new notification
            if ($appointment_datetime != $appointment['appointment_datetime']) {
                createAppointmentReminder($appointmentId, 24);
            }
            
            // Get senior's details for notification
            $seniorSql = "SELECT name, phone, emergency_contact, emergency_phone 
                          FROM seniorcitizens 
                          WHERE id = ?";
            $seniorStmt = $conn->prepare($seniorSql);
            $seniorStmt->bind_param("i", $senior_id);
            $seniorStmt->execute();
            $seniorResult = $seniorStmt->get_result();
            $senior = $seniorResult->fetch_assoc();

            // Format appointment date and time
            $appointmentDate = new DateTime($appointment_datetime);
            $formattedDateTime = $appointmentDate->format('F j, Y \a\t g:i A');

            // Prepare messages
            $seniorMessage = "Hello {$senior['name']}, your appointment with {$doctor_name} has been updated. "
                          . "New schedule: {$formattedDateTime} at {$location}. "
                          . "Purpose: {$purpose}";

            $emergencyMessage = "Hello {$senior['emergency_contact']}, {$senior['name']}'s appointment "
                            . "with {$doctor_name} has been updated to {$formattedDateTime} at {$location}.";

            // Send to senior
            if ($senior['phone']) {
                $seniorPhone = formatPhoneNumber($senior['phone']);
                sendSMS($seniorPhone, $seniorMessage);
            }

            // Send to emergency contact
            if ($senior['emergency_phone']) {
                $emergencyPhone = formatPhoneNumber($senior['emergency_phone']);
                sendSMS($emergencyPhone, $emergencyMessage);
            }
            
            setAlert('success', 'Appointment updated successfully. Notifications have been sent.');
            redirect('appointments.php');
        } else {
            setAlert('danger', 'Failed to update appointment. Please try again.');
        }
    }
}

// Set page title
$pageTitle = 'Edit Appointment';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Appointment - AgeWell Senior Care</title>
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

        .header .nav-links a:hover,
        .header .nav-links a.active {
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

        .sidebar ul li a:hover,
        .sidebar ul li a.active {
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
            background: #1c1c1c !important;
            border-bottom: 1px solid rgba(69, 243, 255, 0.3);
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-header h5 {
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
            color: #8f8f8f;
            font-weight: 500;
            margin-bottom: 8px;
        }

        .form-control, .form-select {
            background-color: #1c1c1c;
            border: 1px solid rgba(69, 243, 255, 0.3);
            border-radius: 4px;
            color: #cdcdcd;
            padding: 10px 12px;
            transition: all 0.3s;
        }

        .form-control:focus, .form-select:focus {
            border-color: #45f3ff;
            box-shadow: 0 0 0 0.25rem rgba(69, 243, 255, 0.25);
            background-color: #1c1c1c;
            color: #ffffff;
        }

        .form-control::placeholder {
            color: #6c757d;
        }

        .text-danger {
            color: #ff3860 !important;
        }

        .invalid-feedback {
            color: #ff3860;
            font-size: 0.875em;
        }

        .is-invalid {
            border-color: #ff3860 !important;
        }

        .is-invalid:focus {
            box-shadow: 0 0 0 0.25rem rgba(255, 56, 96, 0.25) !important;
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
            background: transparent;
            border-color: #45f3ff;
            color: #45f3ff;
        }

        .btn-secondary:hover {
            background: rgba(69, 243, 255, 0.1);
            color: #45f3ff;
        }

        /* Glowing effects */
        @keyframes glow {
            0% {
                box-shadow: 0 0 5px rgba(69, 243, 255, 0.3);
            }
            50% {
                box-shadow: 0 0 15px rgba(69, 243, 255, 0.5);
            }
            100% {
                box-shadow: 0 0 5px rgba(69, 243, 255, 0.3);
            }
        }

        .glow-effect {
            animation: glow 2s infinite;
        }

        /* Badge styling */
        .badge {
            padding: 5px 8px;
            border-radius: 4px;
            font-weight: 500;
        }

        .bg-success {
            background-color: #4bdb6a !important;
            color: #1c1c1c;
        }

        .bg-danger {
            background-color: #ff3860 !important;
        }

        .bg-info {
            background-color: #45f3ff !important;
            color: #1c1c1c;
        }

        .bg-warning {
            background-color: #ffd166 !important;
            color: #1c1c1c;
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
        
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                position: fixed;
                z-index: 999;
                left: -100%;
                transition: all 0.3s;
            }

            .main-content {
                width: 100%;
            }
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
                <li><a href="appointment_add.php"><i class="fas fa-plus-circle"></i> Add New Appointment</a></li>
            </ul>
            
            <h6>Quick Reports</h6>
            <ul>
                <li><a href="reports.php?type=seniors"><i class="fas fa-users"></i> Senior Citizens Report</a></li>
                <li><a href="reports.php?type=appointments" class="active"><i class="fas fa-calendar-check"></i> Appointments Report</a></li>
                <li><a href="reports.php?type=health_metrics"><i class="fas fa-chart-line"></i> Health Metrics Report</a></li>
            </ul>
            
            <?php if (isAdmin()): ?>
            <h6>Admin Actions</h6>
            <ul>
                <li><a href="user_add.php"><i class="fas fa-user-plus"></i> Add New User</a></li>
                <li><a href="users.php"><i class="fas fa-users-cog"></i> Manage Users</a></li>
                <li><a href="activity_logs.php"><i class="fas fa-history"></i> View Activity Logs</a></li>
            </ul>
            <?php endif; ?>
        </div>
        
        <!-- Main content -->
        <div class="main-content">
            <div class="page-title">
                <h1><i class="fas fa-calendar-alt me-2"></i> Edit Appointment</h1>
                <div class="quick-access">
                    <a href="appointments.php" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left"></i> Back to Appointments
                    </a>
                    <a href="appointment_view.php?id=<?php echo $appointmentId; ?>" class="btn btn-outline-primary">
                        <i class="fas fa-eye"></i> View Appointment
                    </a>
                </div>
            </div>

            <!-- Display alerts -->
            <?php displayAlert(); ?>
            
            <!-- Edit Appointment Form -->
            <div class="card glow-effect">
                <div class="card-header">
                    <h6>Edit Appointment Details</h6>
                    <span class="badge bg-info">ID: <?php echo $appointmentId; ?></span>
                </div>
                <div class="card-body">
                    <form method="POST" action="appointment_edit.php?id=<?php echo $appointmentId; ?>" class="needs-validation" novalidate>
                        <div class="row mb-3">
                            <!-- Senior Selection -->
                            <div class="col-md-6 mb-3">
                                <label for="senior_id" class="form-label">Senior Citizen <span class="text-danger">*</span></label>
                                <select class="form-select <?php echo isset($errors['senior_id']) ? 'is-invalid' : ''; ?>" 
                                        id="senior_id" name="senior_id" required>
                                    <option value="">Select Senior Citizen</option>
                                    <?php foreach ($seniors as $seniorOption): ?>
                                        <option value="<?php echo $seniorOption['id']; ?>" 
                                                <?php echo $senior_id == $seniorOption['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($seniorOption['name']); ?>
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
                            
                            <!-- Status Selection -->
                            <div class="col-md-6 mb-3">
                                <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                                <select class="form-select <?php echo isset($errors['status']) ? 'is-invalid' : ''; ?>" 
                                        id="status" name="status" required>
                                    <option value="scheduled" <?php echo $status == 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                                    <option value="completed" <?php echo $status == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                    <option value="cancelled" <?php echo $status == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                    <option value="no_show" <?php echo $status == 'no_show' ? 'selected' : ''; ?>>No Show</option>
                                </select>
                                <?php if (isset($errors['status'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['status']; ?></div>
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
                                <?php $formatted_datetime = str_replace(' ', 'T', $appointment_datetime); ?>
                                <input type="datetime-local" class="form-control <?php echo isset($errors['appointment_datetime']) ? 'is-invalid' : ''; ?>" 
                                       id="appointment_datetime" name="appointment_datetime" value="<?php echo htmlspecialchars($formatted_datetime); ?>" required>
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
                            <div class="col-md-12 mb-3">
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
                        
                        <div class="row">
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i> Update Appointment
                                </button>
                                <a href="appointments.php" class="btn btn-secondary">Cancel</a>
                            </div>
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
                if (link.getAttribute('href').includes(currentPage)) {
                    link.classList.add('active');
                }
            });
        });
    </script>
</body>
</html>
