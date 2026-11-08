<?php
/**
 * AgeWell Senior Care and Support System
 * Add New Medical Record Page
 */

// Include configuration and required files
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    setAlert('danger', 'Please login to access this page.');
    redirect('login.php');
}

// Restrict admin users from accessing this page
if (isAdmin()) {
    setAlert('danger', 'Adding health metrics is only available to caregivers.');
    redirect('health_metrics.php');
}

// Get senior ID from query parameter if available
$preselected_senior_id = isset($_GET['senior_id']) ? (int)$_GET['senior_id'] : null;

// Initialize variables for form
$senior_id = $preselected_senior_id;
$record_type = '';
$value = '';
$notes = '';
$recorded_at = date('Y-m-d H:i:s'); // Default to current time
$errors = [];

// Get all seniors for dropdown
$seniors = fetchAll("SELECT id, name FROM seniorcitizens ORDER BY name ASC");

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $senior_id = isset($_POST['senior_id']) ? (int)$_POST['senior_id'] : null;
    $record_type = cleanInput($_POST['record_type'] ?? '');
    $value = cleanInput($_POST['value'] ?? '');
    $notes = cleanInput($_POST['notes'] ?? '');
    $recorded_at = cleanInput($_POST['recorded_at'] ?? date('Y-m-d H:i:s'));
    
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
    
    if (empty($record_type)) {
        $errors['record_type'] = 'Record type is required.';
    } elseif (!in_array($record_type, ['blood_pressure', 'heart_rate', 'blood_sugar', 'temperature', 'weight', 'other'])) {
        $errors['record_type'] = 'Invalid record type.';
    }
    
    if (empty($value)) {
        $errors['value'] = 'Value is required.';
    } else {
        // Validate based on record type
        switch ($record_type) {
            case 'blood_pressure':
                if (!preg_match('/^\d{1,3}\/\d{1,3}$/', $value)) {
                    $errors['value'] = 'Blood pressure must be in format systolic/diastolic (e.g., 120/80).';
                }
                break;
            case 'heart_rate':
            case 'blood_sugar':
            case 'weight':
                if (!is_numeric($value)) {
                    $errors['value'] = 'Value must be a number.';
                }
                break;
            case 'temperature':
                if (!is_numeric($value) || $value < 90 || $value > 110) {
                    $errors['value'] = 'Temperature must be a number between 90 and 110 °F.';
                }
                break;
        }
    }
    
    if (empty($recorded_at)) {
        $errors['recorded_at'] = 'Recorded time is required.';
    } elseif (strtotime($recorded_at) > time()) {
        $errors['recorded_at'] = 'Recorded time cannot be in the future.';
    }
    
    // If no errors, insert into database
    if (empty($errors)) {
        // Get the current user ID
        $caregiver_id = $_SESSION['user_id'];
        
        $data = [
            'senior_id' => $senior_id,
            'caregiver_id' => $caregiver_id,
            'record_type' => $record_type,
            'value' => $value,
            'notes' => $notes,
            'recorded_at' => $recorded_at,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $recordId = insert('healthrecords', $data);
        
        if ($recordId) {
            // Log activity
            $seniorName = $senior['name'];
            logActivity('medical_record_add', "Added new medical record for senior: $seniorName");
            
            // Check if this is a health metric that needs monitoring for alerts
            if (in_array($record_type, ['blood_pressure', 'heart_rate', 'blood_sugar', 'temperature'])) {
                // Define thresholds for different metrics
                $thresholds = [
                    'blood_pressure' => [
                        'systolic_high' => 140,
                        'systolic_high_emergency' => 180,
                        'systolic_low' => 90,
                        'systolic_low_emergency' => 70,
                        'diastolic_high' => 90,
                        'diastolic_high_emergency' => 120,
                        'diastolic_low' => 60,
                        'diastolic_low_emergency' => 40
                    ],
                    'heart_rate' => [
                        'high' => 100,
                        'high_emergency' => 120,
                        'low' => 50,
                        'low_emergency' => 40
                    ],
                    'blood_sugar' => [
                        'high' => 140,
                        'high_emergency' => 300,
                        'low' => 70,
                        'low_emergency' => 50
                    ],
                    'temperature' => [
                        'high' => 99.5,
                        'high_emergency' => 103,
                        'low' => 97,
                        'low_emergency' => 95
                    ]
                ];
                
                // Create health alert if needed
                if (isset($thresholds[$record_type])) {
                    require_once 'includes/notification_handler.php';
                    createHealthAlert($senior_id, $record_type, $value, $thresholds[$record_type]);
                    
                    // Also send SMS notifications directly
                    require_once 'includes/sms_functions.php';
                    try {
                        sendHealthMetricAlert($senior_id, $record_type, $value);
                        // Log the SMS attempt
                        error_log("SMS health alert attempted for senior ID: $senior_id, metric: $record_type, value: $value");
                    } catch (Exception $e) {
                        error_log("Failed to send direct SMS health alert: " . $e->getMessage());
                    }
                }
            }
            
            setAlert('success', 'Medical record added successfully.');
            redirect('medical_records.php');
        } else {
            setAlert('danger', 'Failed to add medical record. Please try again.');
        }
    }
}

// Set page title
$pageTitle = 'Add Medical Record';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Medical Record - AgeWell Senior Care</title>
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

        .form-text {
            color: #45f3ff;
            opacity: 0.8;
            font-size: 0.85rem;
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
            <a href="appointments.php"><i class="fas fa-calendar-check"></i> Appointments</a>
            <a href="health_metrics.php" class="active"><i class="fas fa-heartbeat"></i> Health Metrics</a>
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
                <li><a href="medical_record_add.php" class="active"><i class="fas fa-file-medical-alt"></i> Add Medical Record</a></li>
            </ul>
            
            <h6>Health Management</h6>
            <ul>
                <li><a href="health_metrics.php"><i class="fas fa-heartbeat"></i> Health Metrics</a></li>
                <li><a href="medical_records.php"><i class="fas fa-file-medical"></i> Medical Records</a></li>
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
                <h1><i class="fas fa-file-medical-alt me-2"></i> Add Medical Record</h1>
                <div class="quick-access">
                    <a href="medical_records.php" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left"></i> Back to Records
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
                    <h6><i class="fas fa-file-medical me-2"></i> Medical Record Details</h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="medical_record_add.php<?php echo $preselected_senior_id ? "?senior_id=$preselected_senior_id" : ''; ?>" class="needs-validation" novalidate>
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
                            
                            <!-- Record Type -->
                            <div class="col-md-6 mb-3">
                                <label for="record_type" class="form-label">Record Type <span class="text-danger">*</span></label>
                                <select class="form-select <?php echo isset($errors['record_type']) ? 'is-invalid' : ''; ?>" 
                                        id="record_type" name="record_type" required>
                                    <option value="">Select Record Type</option>
                                    <option value="blood_pressure" <?php echo $record_type === 'blood_pressure' ? 'selected' : ''; ?>>Blood Pressure</option>
                                    <option value="heart_rate" <?php echo $record_type === 'heart_rate' ? 'selected' : ''; ?>>Heart Rate</option>
                                    <option value="blood_sugar" <?php echo $record_type === 'blood_sugar' ? 'selected' : ''; ?>>Blood Sugar</option>
                                    <option value="temperature" <?php echo $record_type === 'temperature' ? 'selected' : ''; ?>>Temperature</option>
                                    <option value="weight" <?php echo $record_type === 'weight' ? 'selected' : ''; ?>>Weight</option>
                                    <option value="other" <?php echo $record_type === 'other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                                <?php if (isset($errors['record_type'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['record_type']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Value -->
                            <div class="col-md-6 mb-3">
                                <label for="value" class="form-label">Value <span class="text-danger">*</span></label>
                                <input type="text" class="form-control <?php echo isset($errors['value']) ? 'is-invalid' : ''; ?>" 
                                       id="value" name="value" value="<?php echo htmlspecialchars($value); ?>" required>
                                <div class="form-text" id="valueHelp"></div>
                                <?php if (isset($errors['value'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['value']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Recorded At -->
                            <div class="col-md-6 mb-3">
                                <label for="recorded_at" class="form-label">Recorded At <span class="text-danger">*</span></label>
                                <?php $formatted_datetime = str_replace(' ', 'T', $recorded_at); ?>
                                <input type="datetime-local" class="form-control <?php echo isset($errors['recorded_at']) ? 'is-invalid' : ''; ?>" 
                                       id="recorded_at" name="recorded_at" value="<?php echo htmlspecialchars($formatted_datetime); ?>" required>
                                <?php if (isset($errors['recorded_at'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['recorded_at']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Notes -->
                            <div class="col-md-12 mb-3">
                                <label for="notes" class="form-label">Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo htmlspecialchars($notes); ?></textarea>
                            </div>
                        </div>
                        
                        <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i> Save Medical Record
                                </button>
                            <a href="medical_records.php" class="btn btn-secondary">
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
    // Show help text based on selected record type
    document.addEventListener('DOMContentLoaded', function() {
        const recordTypeSelect = document.getElementById('record_type');
        const valueHelp = document.getElementById('valueHelp');
        
        function updateValueHelp() {
            const selectedType = recordTypeSelect.value;
            switch (selectedType) {
                case 'blood_pressure':
                    valueHelp.textContent = 'Enter in format: systolic/diastolic (e.g., 120/80)';
                    break;
                case 'heart_rate':
                    valueHelp.textContent = 'Enter beats per minute (e.g., 72)';
                    break;
                case 'blood_sugar':
                    valueHelp.textContent = 'Enter in mg/dL (e.g., 120)';
                    break;
                case 'temperature':
                    valueHelp.textContent = 'Enter in °F (e.g., 98.6)';
                    break;
                case 'weight':
                    valueHelp.textContent = 'Enter in kg (e.g., 70.5)';
                    break;
                default:
                    valueHelp.textContent = '';
            }
        }
        
        recordTypeSelect.addEventListener('change', updateValueHelp);
        
        // Initial update
        updateValueHelp();
            
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
