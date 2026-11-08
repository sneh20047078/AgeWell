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

// Include header
require_once 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-3 col-lg-2">
            <?php require_once 'includes/sidebar.php'; ?>
        </div>
        
        <!-- Main Content -->
        <div class="col-md-9 col-lg-10">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="fas fa-file-medical-alt me-2"></i> Add Medical Record</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="medical_records.php" class="btn btn-sm btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Back to List
                    </a>
                </div>
            </div>
            
            <!-- Add Medical Record Form -->
            <div class="card shadow-sm">
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
                        
                        <div class="row">
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i> Save Medical Record
                                </button>
                                <a href="medical_records.php" class="btn btn-secondary">Cancel</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

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
    });
</script>

<?php
// Include footer
require_once 'includes/footer.php';
?>
