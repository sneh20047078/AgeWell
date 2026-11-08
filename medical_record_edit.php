<?php
/**
 * AgeWell Senior Care and Support System
 * Edit Medical Record Page
 */

require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    setAlert('danger', 'Please login to access this page.');
    redirect('login.php');
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    setAlert('danger', 'Record ID is missing.');
    redirect('medical_records.php');
}

$recordId = (int)$_GET['id'];
$record = fetchOne(
    "SELECT h.*, s.name as senior_name FROM healthrecords h 
     JOIN seniorcitizens s ON h.senior_id = s.id 
     WHERE h.id = ?", 
    [$recordId], 
    'i'
);

if (!$record) {
    setAlert('danger', 'Medical record not found.');
    redirect('medical_records.php');
}

$senior_id = $record['senior_id'];
$record_type = $record['record_type'];
$value = $record['value'];
$notes = $record['notes'];
$recorded_at = $record['recorded_at'];
$errors = [];

$seniors = fetchAll("SELECT id, name FROM seniorcitizens ORDER BY name ASC");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $senior_id = isset($_POST['senior_id']) ? (int)$_POST['senior_id'] : null;
    $record_type = cleanInput($_POST['record_type'] ?? '');
    $value = cleanInput($_POST['value'] ?? '');
    $notes = cleanInput($_POST['notes'] ?? '');
    $recorded_at = cleanInput($_POST['recorded_at'] ?? date('Y-m-d H:i:s'));
    
    if (empty($senior_id)) {
        $errors['senior_id'] = 'Senior citizen is required.';
    } else {
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
    
    if (empty($errors)) {
        $data = [
            'senior_id' => $senior_id,
            'record_type' => $record_type,
            'value' => $value,
            'notes' => $notes,
            'recorded_at' => $recorded_at
        ];
        
        $updated = update('healthrecords', $data, 'id = ?', [$recordId], 'i');
        
        if ($updated) {
            $seniorName = $senior['name'];
            logActivity('medical_record_update', "Updated medical record for senior: $seniorName");
            
            // Check if this is a health metric that needs monitoring for alerts
            if (in_array($record_type, ['blood_pressure', 'heart_rate', 'blood_sugar', 'temperature'])) {
                // Include SMS functions for notifications
                require_once 'includes/sms_functions.php';
                
                // Send SMS alert for critical values
                try {
                    sendHealthMetricAlert($senior_id, $record_type, $value);
                    error_log("SMS alert sent for updated health metric - Senior: $seniorName, Type: $record_type, Value: $value");
                } catch (Exception $e) {
                    error_log("Failed to send SMS alert for updated health metric: " . $e->getMessage());
                }
            }
            
            setAlert('success', 'Medical record updated successfully.');
            redirect('medical_records.php');
        } else {
            setAlert('danger', 'Failed to update medical record. Please try again.');
        }
    }
}

$pageTitle = 'Edit Medical Record';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Medical Record - AgeWell Senior Care</title>
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

        .header .nav-links a {
            color: #8f8f8f;
            text-decoration: none;
            padding: 0 15px;
            transition: color 0.3s;
        }

        .header .nav-links a:hover {
            color: #45f3ff;
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

        .main-container {
            display: flex;
            height: calc(100vh - 60px);
        }

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

        .sidebar ul li a {
            display: flex;
            align-items: center;
            color: #8f8f8f;
            text-decoration:Beer;
            padding: 10px 20px;
            transition: all 0.3s;
        }

        .sidebar ul li a:hover {
            background: rgba(69, 243, 255, 0.1);
            color: #45f3ff;
        }

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

        .card {
            background: #28292d;
            border: 1px solid rgba(69, 243, 255, 0.3);
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            margin-bottom: 20px;
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.3);
            border-color: rgba(69, 243, 255, 0.6);
        }

        .card-body {
            padding: 20px;
        }

        .form-label {
            color: #45f3ff;
            font-weight: 500;
            margin-bottom: 8px;
        }

        .form-control, .form-select {
            background: #1c1c1c;
            border: 1px solid rgba(69, 243, 255, 0.3);
            color: #cdcdcd;
            border-radius: 4px;
            padding: 8px 12px;
        }

        .form-control:focus, .form-select:focus {
            border-color: #45f3ff;
            box-shadow: 0 0 5px rgba(69, 243, 255, 0.5);
            background: #1c1c1c;
            color: #cdcdcd;
        }

        .form-text {
            color: #8f8f8f;
        }

        .invalid-feedback {
            color: #ff3860;
        }

        .btn-primary {
            background: #45f3ff;
            border-color: #45f3ff;
            color: #1c1c1c;
            padding: 8px 16px;
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
            padding: 8px 16px;
        }

        .btn-secondary:hover {
            background: rgba(69, 243, 255, 0.1);
            color: #45f3ff;
            border-color: #45f3ff;
        }

        .text-danger {
            color: #ff3860 !important;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">
            <i class="fas fa-heartbeat"></i>
            <span>AgeWell Senior Care</span>
        </div>
        <div class="nav-links">
            <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="senior_citizens.php"><i class="fas fa-users"></i> Senior Citizens</a>
            <a href="appointments.php"><i class="fas fa-calendar-check"></i> Appointments</a>
            <a href="health_metrics.php"><i class="fas fa-heartbeat"></i> Health Metrics</a>
        </div>
        <div class="user-menu">
            <a href="profile.php"><i class="fas fa-user-circle"></i> Profile</a>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <div class="main-container">
        <div class="sidebar">
            <h6>Quick Actions</h6>
            <ul>
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="senior_add.php"><i class="fas fa-user-plus"></i> Add New Senior</a></li>
            </ul>
            
            <h6>Quick Reports</h6>
            <ul>
                <li><a href="reports.php?type=seniors"><i class="fas fa-users"></i> Senior Citizens Report</a></li>
                <li><a href="reports.php?type=appointments"><i class="fas fa-calendar-check"></i> Appointments Report</a></li>
                <li><a href="reports.php?type=health_metrics"><i class="fas fa-chart-line"></i> Health Metrics Report</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="page-title">
                <h1><i class="fas fa-file-medical-alt me-2"></i> Edit Medical Record</h1>
                <a href="medical_records.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Back to List
                </a>
            </div>

            <div class="card">
                <div class="card-body">
                    <form method="POST" action="medical_record_edit.php?id=<?php echo $recordId; ?>" class="needs-validation" novalidate>
                        <div class="row mb-3">
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
                            
                            <div class="col-md-6 mb-3">
                                <label for="value" class="form-label">Value <span class="text-danger">*</span></label>
                                <input type="text" class="form-control <?php echo isset($errors['value']) ? 'is-invalid' : ''; ?>" 
                                       id="value" name="value" value="<?php echo htmlspecialchars($value); ?>" required>
                                <div class="form-text" id="valueHelp"></div>
                                <?php if (isset($errors['value'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['value']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="recorded_at" class="form-label">Recorded At <span class="text-danger">*</span></label>
                                <?php $formatted_datetime = str_replace(' ', 'T', $recorded_at); ?>
                                <input type="datetime-local" class="form-control <?php echo isset($errors['recorded_at']) ? 'is-invalid' : ''; ?>" 
                                       id="recorded_at" name="recorded_at" value="<?php echo htmlspecialchars($formatted_datetime); ?>" required>
                                <?php if (isset($errors['recorded_at'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['recorded_at']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-12 mb-3">
                                <label for="notes" class="form-label">Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo htmlspecialchars($notes); ?></textarea>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i> Update Medical Record
                                </button>
                                <a href="medical_records.php" class="btn btn-secondary">Cancel</a>
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
            updateValueHelp();
        });
    </script>
</body>
</html>