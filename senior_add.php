<?php
/**
 * AgeWell Senior Care and Support System
 * Add New Senior Citizen Page
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

// Initialize variables for form
$name = '';
$age = '';
$gender = '';
$phone = '';
$emergency_contact = '';
$emergency_phone = '';
$address = '';
$medical_history = '';
$medications = '';
$allergies = '';
$errors = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $name = cleanInput($_POST['name'] ?? '');
    $age = cleanInput($_POST['age'] ?? '');
    $gender = cleanInput($_POST['gender'] ?? '');
    $phone = cleanInput($_POST['phone'] ?? '');
    $emergency_contact = cleanInput($_POST['emergency_contact'] ?? '');
    $emergency_phone = cleanInput($_POST['emergency_phone'] ?? '');
    $address = cleanInput($_POST['address'] ?? '');
    $medical_history = cleanInput($_POST['medical_history'] ?? '');
    $medications = cleanInput($_POST['medications'] ?? '');
    $allergies = cleanInput($_POST['allergies'] ?? '');
    
    // Validate form data
    if (empty($name)) {
        $errors['name'] = 'Name is required.';
    }
    
    if (empty($age)) {
        $errors['age'] = 'Age is required.';
    } elseif (!is_numeric($age) || $age < 0 || $age > 150) {
        $errors['age'] = 'Age must be a valid number between 0 and 150.';
    }
    
    if (empty($gender)) {
        $errors['gender'] = 'Gender is required.';
    } elseif (!in_array($gender, ['male', 'female', 'other'])) {
        $errors['gender'] = 'Gender must be male, female, or other.';
    }
    
    if (!empty($phone) && !preg_match('/^[0-9\-\(\)\/\+\s]*$/', $phone)) {
        $errors['phone'] = 'Invalid phone number format.';
    }
    
    if (empty($emergency_contact)) {
        $errors['emergency_contact'] = 'Emergency contact name is required.';
    }
    
    if (empty($emergency_phone)) {
        $errors['emergency_phone'] = 'Emergency contact phone is required.';
    } elseif (!preg_match('/^[0-9\-\(\)\/\+\s]*$/', $emergency_phone)) {
        $errors['emergency_phone'] = 'Invalid emergency phone number format.';
    }
    
    // If no errors, insert into database
    if (empty($errors)) {
        $data = [
            'name' => $name,
            'age' => $age,
            'gender' => $gender,
            'phone' => $phone,
            'emergency_contact' => $emergency_contact,
            'emergency_phone' => $emergency_phone,
            'address' => $address,
            'medical_history' => $medical_history,
            'medications' => $medications,
            'allergies' => $allergies,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $seniorId = insert('seniorcitizens', $data);
        
        if ($seniorId) {
            // Log activity
            logActivity('senior_add', "Added new senior citizen: $name");
            
            setAlert('success', 'Senior citizen added successfully.');
            redirect('senior_profile.php?id=' . $seniorId);
        } else {
            setAlert('danger', 'Failed to add senior citizen. Please try again.');
        }
    }
}

// Set page title
$pageTitle = 'Add New Senior';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Senior - AgeWell Senior Care</title>
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

        /* Form controls */
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

        .form-control::placeholder {
            color: #6c757d;
        }

        .form-select option {
            background-color: #1c1c1c;
            color: #cdcdcd;
        }

        .card-title {
            color: #45f3ff;
            border-bottom: 1px solid rgba(69, 243, 255, 0.3);
            padding-bottom: 10px;
            margin-bottom: 20px;
        }

        .text-danger {
            color: #ff3860 !important;
        }

        .invalid-feedback {
            color: #ff3860;
            font-size: 0.875em;
            margin-top: 0.25rem;
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
            background: transparent;
            border: 1px solid #45f3ff;
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
            <a href="senior_citizens.php" class="active"><i class="fas fa-users"></i> Senior Citizens</a>
            <a href="appointments.php"><i class="fas fa-calendar-check"></i> Appointments</a>
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
                <li><a href="senior_add.php" class="active"><i class="fas fa-user-plus"></i> Add New Senior</a></li>
            </ul>
            
            <h6>Quick Reports</h6>
            <ul>
                <li><a href="reports.php?type=seniors"><i class="fas fa-users"></i> Senior Citizens Report</a></li>
                <li><a href="reports.php?type=appointments"><i class="fas fa-calendar-check"></i> Appointments Report</a></li>
                <li><a href="reports.php?type=health_metrics"><i class="fas fa-chart-line"></i> Health Metrics Report</a></li>
            </ul>
            
            <h6>Admin Actions</h6>
            <ul>
                <li><a href="user_add.php"><i class="fas fa-user-plus"></i> Add New User</a></li>
                <li><a href="users.php"><i class="fas fa-users-cog"></i> Manage Users</a></li>
                <li><a href="activity_logs.php"><i class="fas fa-history"></i> View Activity Logs</a></li>
            </ul>
        </div>
        
        <!-- Main content -->
        <div class="main-content">
            <div class="page-title">
                <h1><i class="fas fa-user-plus me-2"></i> Add New Senior</h1>
                <a href="senior_citizens.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Back to List
                    </a>
            </div>
            
            <!-- Add Senior Form -->
            <div class="card">
                <div class="card-header">
                    <h6>Senior Citizen Information</h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="senior_add.php" class="needs-validation" novalidate>
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <h5 class="card-title">Basic Information</h5>
                            </div>
                            
                            <!-- Name -->
                            <div class="col-md-6 mb-3">
                                <label for="name" class="form-label">Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control <?php echo isset($errors['name']) ? 'is-invalid' : ''; ?>" 
                                       id="name" name="name" value="<?php echo htmlspecialchars($name); ?>" required>
                                <?php if (isset($errors['name'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['name']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Age -->
                            <div class="col-md-2 mb-3">
                                <label for="age" class="form-label">Age <span class="text-danger">*</span></label>
                                <input type="number" class="form-control <?php echo isset($errors['age']) ? 'is-invalid' : ''; ?>" 
                                       id="age" name="age" value="<?php echo htmlspecialchars($age); ?>" min="0" max="150" required>
                                <?php if (isset($errors['age'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['age']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Gender -->
                            <div class="col-md-4 mb-3">
                                <label for="gender" class="form-label">Gender <span class="text-danger">*</span></label>
                                <select class="form-select <?php echo isset($errors['gender']) ? 'is-invalid' : ''; ?>" 
                                        id="gender" name="gender" required>
                                    <option value="" <?php echo empty($gender) ? 'selected' : ''; ?>>Select Gender</option>
                                    <option value="male" <?php echo $gender === 'male' ? 'selected' : ''; ?>>Male</option>
                                    <option value="female" <?php echo $gender === 'female' ? 'selected' : ''; ?>>Female</option>
                                    <option value="other" <?php echo $gender === 'other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                                <?php if (isset($errors['gender'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['gender']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Phone -->
                            <div class="col-md-6 mb-3">
                                <label for="phone" class="form-label">Phone Number</label>
                                <input type="text" class="form-control phone-input <?php echo isset($errors['phone']) ? 'is-invalid' : ''; ?>" 
                                       id="phone" name="phone" value="<?php echo htmlspecialchars($phone); ?>"
                                       placeholder="e.g., 123-456-7890">
                                <?php if (isset($errors['phone'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['phone']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Address -->
                            <div class="col-md-6 mb-3">
                                <label for="address" class="form-label">Address</label>
                                <textarea class="form-control" id="address" name="address" rows="2"><?php echo htmlspecialchars($address); ?></textarea>
                            </div>
                            
                            <!-- Emergency Contact Name -->
                            <div class="col-md-6 mb-3">
                                <label for="emergency_contact" class="form-label">Emergency Contact Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control <?php echo isset($errors['emergency_contact']) ? 'is-invalid' : ''; ?>" 
                                       id="emergency_contact" name="emergency_contact" 
                                       value="<?php echo htmlspecialchars($emergency_contact); ?>" required>
                                <?php if (isset($errors['emergency_contact'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['emergency_contact']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Emergency Contact Phone -->
                            <div class="col-md-6 mb-3">
                                <label for="emergency_phone" class="form-label">Emergency Contact Phone <span class="text-danger">*</span></label>
                                <input type="text" class="form-control phone-input <?php echo isset($errors['emergency_phone']) ? 'is-invalid' : ''; ?>" 
                                       id="emergency_phone" name="emergency_phone" 
                                       value="<?php echo htmlspecialchars($emergency_phone); ?>" 
                                       placeholder="e.g., 123-456-7890" required>
                                <?php if (isset($errors['emergency_phone'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['emergency_phone']; ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <h5 class="card-title">Medical Information</h5>
                            </div>
                            
                            <!-- Medical History -->
                            <div class="col-md-12 mb-3">
                                <label for="medical_history" class="form-label">Medical History</label>
                                <textarea class="form-control" id="medical_history" name="medical_history" 
                                          rows="3" placeholder="Enter any chronic conditions, surgeries, or significant medical events"><?php echo htmlspecialchars($medical_history); ?></textarea>
                            </div>
                            
                            <!-- Current Medications -->
                            <div class="col-md-6 mb-3">
                                <label for="medications" class="form-label">Current Medications</label>
                                <textarea class="form-control" id="medications" name="medications" 
                                          rows="3" placeholder="List current medications and dosages"><?php echo htmlspecialchars($medications); ?></textarea>
                            </div>
                            
                            <!-- Allergies -->
                            <div class="col-md-6 mb-3">
                                <label for="allergies" class="form-label">Allergies</label>
                                <textarea class="form-control" id="allergies" name="allergies" 
                                          rows="3" placeholder="List any known allergies"><?php echo htmlspecialchars($allergies); ?></textarea>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary glow-effect">
                                    <i class="fas fa-save me-1"></i> Save Senior Citizen
                                </button>
                                <a href="senior_citizens.php" class="btn btn-secondary">Cancel</a>
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
                if (link.getAttribute('href') === currentPage) {
                    link.classList.add('active');
                }
            });
        });
    </script>
</body>
</html>
