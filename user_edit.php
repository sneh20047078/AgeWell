<?php
/**
 * AgeWell Senior Care and Support System
 * Edit User Page
 */

// Include configuration and required files
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Check if user is logged in
if (!isLoggedIn()) {
    setAlert('danger', 'Please login to access this page.');
    redirect('login.php');
}

// Check if user ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    setAlert('danger', 'User ID is missing.');
    redirect(isAdmin() ? 'users.php' : 'dashboard.php');
}

// Get user ID and fetch details
$userId = (int)$_GET['id'];
$user = getUserById($userId);

if (!$user) {
    setAlert('danger', 'User not found.');
    redirect(isAdmin() ? 'users.php' : 'dashboard.php');
}

// Check if the current user has permission to edit this user
if (!isAdmin() && $userId != $_SESSION['user_id']) {
    setAlert('danger', 'You do not have permission to edit this user.');
    redirect('dashboard.php');
}

// Initialize variables with existing data
$name = $user['name'];
$email = $user['email'];
$phone = $user['phone'];
$role = $user['role'];
$errors = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $name = cleanInput($_POST['name'] ?? '');
    $email = cleanInput($_POST['email'] ?? '');
    $phone = cleanInput($_POST['phone'] ?? '');
    
    // Only admin can change role
    if (isAdmin() && $userId != $_SESSION['user_id']) {
        $role = cleanInput($_POST['role'] ?? '');
    }
    
    // Get password data if provided
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validate form data
    if (empty($name)) {
        $errors['name'] = 'Name is required.';
    }
    
    if (empty($email)) {
        $errors['email'] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Invalid email format.';
    } elseif (emailExists($email, $userId)) {
        $errors['email'] = 'Email already exists. Please use a different email.';
    }
    
    if (!empty($phone) && !preg_match('/^[0-9\-\(\)\/\+\s]*$/', $phone)) {
        $errors['phone'] = 'Invalid phone number format.';
    }
    
    if (isAdmin() && $userId != $_SESSION['user_id']) {
        if (empty($role) || !in_array($role, ['admin', 'caregiver'])) {
            $errors['role'] = 'Valid role is required.';
        }
    }
    
    // Validate password change if attempted
    $changePassword = false;
    if (!empty($new_password) || !empty($confirm_password) || !empty($current_password)) {
        if (empty($current_password)) {
            $errors['current_password'] = 'Current password is required to change password.';
        }
        
        if (empty($new_password)) {
            $errors['new_password'] = 'New password is required.';
        } elseif (strlen($new_password) < 6) {
            $errors['new_password'] = 'New password must be at least 6 characters long.';
        }
        
        if ($new_password !== $confirm_password) {
            $errors['confirm_password'] = 'Passwords do not match.';
        }
        
        $changePassword = true;
    }
    
    // If no errors, update user
    if (empty($errors)) {
        // Build data array for update
        $data = [
            'name' => $name,
            'email' => $email,
            'phone' => $phone
        ];
        
        // Add role if admin is changing it
        if (isAdmin() && $userId != $_SESSION['user_id']) {
            $data['role'] = $role;
        }
        
        // Update user profile
        $result = updateUserProfile($userId, $data);
        
        if ($result['success']) {
            // If password change is requested
            if ($changePassword) {
                $passwordResult = changePassword($userId, $current_password, $new_password);
                
                if ($passwordResult['success']) {
                    setAlert('success', 'User profile and password updated successfully.');
                } else {
                    setAlert('warning', 'User profile updated but password change failed: ' . $passwordResult['message']);
                }
            } else {
                setAlert('success', 'User profile updated successfully.');
            }
            
            if (isAdmin() && $userId != $_SESSION['user_id']) {
                redirect('users.php');
            } else {
                redirect('dashboard.php');
            }
        } else {
            setAlert('danger', $result['message']);
        }
    }
}

// Set page title
$pageTitle = 'Edit User';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User - AgeWell Senior Care</title>
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

        .form-label {
            color: #45f3ff;
            font-weight: 500;
            margin-bottom: 5px;
        }

        .form-control, .form-select {
            background: #1c1c1c;
            border: 1px solid rgba(69, 243, 255, 0.3);
            color: #cdcdcd;
            border-radius: 4px;
            padding: 8px 12px;
            transition: border-color 0.3s;
        }

        .form-control:focus, .form-select:focus {
            border-color: #45f3ff;
            box-shadow: 0 0 5px rgba(69, 243, 255, 0.5);
            background: #1c1c1c;
            color: #cdcdcd;
        }

        .form-control.is-invalid, .form-select.is-invalid {
            border-color: #ff3860;
        }

        .invalid-feedback {
            color: #ff3860;
            font-size: 12px;
        }

        .input-group .btn-outline-secondary {
            border: 1px solid rgba(69, 243, 255, 0.3);
            color: #45f3ff;
            background: #1c1c1c;
            transition: all 0.3s;
        }

        .input-group .btn-outline-secondary:hover {
            background: rgba(69, 243, 255, 0.1);
            color: #45f3ff;
        }

        .form-text {
            color: #8f8f8f;
            font-size: 12px;
        }

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
            background: #8f8f8f;
            border-color: #8f8f8f;
            color: #1c1c1c;
        }

        .btn-secondary:hover {
            background: #6f6f6f;
            border-color: #6f6f6f;
            color: #1c1c1c;
        }

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
                <li><a href="senior_add.php"><i class="fas fa-user-plus"></i> Add New Senior</a></li>
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
                <h1>Edit User</h1>
                <div class="quick-access">
                    <?php if (isAdmin()): ?>
                        <a href="users.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to List
                        </a>
                    <?php else: ?>
                        <a href="dashboard.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Edit User Form -->
            <div class="card">
                <div class="card-header">
                    <h6>User Information</h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="user_edit.php?id=<?php echo $userId; ?>" class="needs-validation" novalidate>
                        <div class="row mb-3">
                            <!-- Name -->
                            <div class="col-md-6 mb-3">
                                <label for="name" class="form-label">Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control <?php echo isset($errors['name']) ? 'is-invalid' : ''; ?>" 
                                       id="name" name="name" value="<?php echo htmlspecialchars($name); ?>" required>
                                <?php if (isset($errors['name'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['name']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Email -->
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" 
                                       id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                                <?php if (isset($errors['email'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['email']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Phone -->
                            <div class="col-md-6 mb-3">
                                <label for="phone" class="form-label">Phone Number</label>
                                <input type="text" class="form-control <?php echo isset($errors['phone']) ? 'is-invalid' : ''; ?>" 
                                       id="phone" name="phone" value="<?php echo htmlspecialchars($phone); ?>"
                                       placeholder="e.g., 123-456-7890">
                                <?php if (isset($errors['phone'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['phone']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Role (Admin only) -->
                            <?php if (isAdmin() && $userId != $_SESSION['user_id']): ?>
                                <div class="col-md-6 mb-3">
                                    <label for="role" class="form-label">Role <span class="text-danger">*</span></label>
                                    <select class="form-select <?php echo isset($errors['role']) ? 'is-invalid' : ''; ?>" 
                                            id="role" name="role" required>
                                        <option value="admin" <?php echo $role === 'admin' ? 'selected' : ''; ?>>Administrator</option>
                                        <option value="caregiver" <?php echo $role === 'caregiver' ? 'selected' : ''; ?>>Caregiver</option>
                                    </select>
                                    <?php if (isset($errors['role'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['role']; ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-12 mb-3">
                                <h5 class="form-label">Change Password</h5>
                                <p class="form-text">Leave password fields empty if you don't want to change the password.</p>
                            </div>
                            
                            <!-- Current Password -->
                            <div class="col-md-4 mb-3">
                                <label for="current_password" class="form-label">Current Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control <?php echo isset($errors['current_password']) ? 'is-invalid' : ''; ?>" 
                                           id="current_password" name="current_password">
                                    <button class="btn btn-outline-secondary" type="button" id="toggleCurrentPassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <?php if (isset($errors['current_password'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['current_password']; ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- New Password -->
                            <div class="col-md-4 mb-3">
                                <label for="new_password" class="form-label">New Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control <?php echo isset($errors['new_password']) ? 'is-invalid' : ''; ?>" 
                                           id="new_password" name="new_password">
                                    <button class="btn btn-outline-secondary" type="button" id="toggleNewPassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <?php if (isset($errors['new_password'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['new_password']; ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="form-text">Password must be at least 6 characters long.</div>
                            </div>
                            
                            <!-- Confirm Password -->
                            <div class="col-md-4 mb-3">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control <?php echo isset($errors['confirm_password']) ? 'is-invalid' : ''; ?>" 
                                           id="confirm_password" name="confirm_password">
                                    <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <?php if (isset($errors['confirm_password'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['confirm_password']; ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i> Update User
                                </button>
                                <?php if (isAdmin()): ?>
                                    <a href="users.php" class="btn btn-secondary">Cancel</a>
                                <?php else: ?>
                                    <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                                <?php endif; ?>
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
            // Toggle password visibility
            const toggles = [
                { buttonId: 'toggleCurrentPassword', inputId: 'current_password' },
                { buttonId: 'toggleNewPassword', inputId: 'new_password' },
                { buttonId: 'toggleConfirmPassword', inputId: 'confirm_password' }
            ];
            
            toggles.forEach(function(toggle) {
                const button = document.getElementById(toggle.buttonId);
                const input = document.getElementById(toggle.inputId);
                
                if (button && input) {
                    button.addEventListener('click', function() {
                        const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                        input.setAttribute('type', type);
                        
                        const icon = this.querySelector('i');
                        if (icon) {
                            icon.classList.toggle('fa-eye');
                            icon.classList.toggle('fa-eye-slash');
                        }
                    });
                }
            });

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