<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// If user is already logged in, redirect to dashboard
if (isLoggedIn()) {
    redirect('dashboard.php');
}

$error = '';
$success = '';
$name = '';
$email = '';
$phone = '';
$role = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = cleanInput($_POST['name'] ?? '');
    $email = cleanInput($_POST['email'] ?? '');
    $phone = cleanInput($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = cleanInput($_POST['role'] ?? ''); // Get selected role

    // Validate input
    if (empty($name) || empty($email) || empty($password) || empty($confirm_password) || empty($role)) {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } else {
        // Check if email already exists
        $checkEmail = $GLOBALS['conn']->prepare("SELECT id FROM userdetails WHERE email = ?");
        $checkEmail->bind_param("s", $email);
        $checkEmail->execute();
        $result = $checkEmail->get_result();
        
        if ($result->num_rows > 0) {
            $error = 'Email already registered.';
            $checkEmail->close();
        } else {
            $checkEmail->close();
            
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Set created and updated timestamps
            $now = date('Y-m-d H:i:s');
            
            // Insert new user with pending status using prepared statement
            $stmt = $GLOBALS['conn']->prepare("
                INSERT INTO userdetails (name, email, phone, password, role, status, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, 'inactive', ?, ?)
            ");
            
            if ($stmt) {
                $stmt->bind_param("sssssss", $name, $email, $phone, $hashed_password, $role, $now, $now);
                $insertResult = $stmt->execute();
                
                if ($insertResult) {
                    $success = 'Registration successful! Please wait for administrator approval before logging in.';
                    
                    // Log the registration
                    $new_user_id = $GLOBALS['conn']->insert_id;
                    
                    // Insert into activity logs directly
                    $action = 'Registration';
                    $details = "New user registered: $name ($email)";
                    $logStmt = $GLOBALS['conn']->prepare("
                        INSERT INTO activitylogs (user_id, action, details, created_at) 
                        VALUES (?, ?, ?, ?)
                    ");
                    
                    if ($logStmt) {
                        $logStmt->bind_param("isss", $new_user_id, $action, $details, $now);
                        $logStmt->execute();
                        $logStmt->close();
                    }
                    
                    // Reset form values on success
                    $name = $email = $phone = $role = '';
                } else {
                    $error = 'Registration failed. Please try again. Error: ' . $stmt->error;
                }
                
                $stmt->close();
            } else {
                $error = 'Registration failed. Please try again. Error: ' . $GLOBALS['conn']->error;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - AgeWell Senior Care</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: #1E1E1E;
        }

        .container {
            width: 400px;
            padding: 40px;
            background: #2A2A2A;
            border-radius: 10px;
            border: 1px solid #00F7FF;
        }

        h2 {
            color: #00F7FF;
            text-align: center;
            margin-bottom: 8px;
            font-size: 24px;
        }

        .subtitle {
            color: #666;
            text-align: center;
            margin-bottom: 30px;
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 20px;
            position: relative;
        }

        .form-group input {
            width: 100%;
            padding: 10px;
            padding-left: 35px;
            background: transparent;
            border: none;
            border-bottom: 1px solid #444;
            color: #fff;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .form-group .icon {
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .form-group input:focus {
            outline: none;
            border-bottom-color: #00F7FF;
        }

        .form-group input:focus + .icon {
            color: #00F7FF;
        }

        .form-group input:focus::placeholder {
            color: #00F7FF;
        }

        button {
            width: 100%;
            padding: 12px;
            background: #00F7FF;
            color: #000;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            cursor: pointer;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }

        button:hover {
            background: #00D4FF;
            transform: translateY(-2px);
        }

        .back-link {
            display: block;
            text-align: center;
            color: #666;
            text-decoration: none;
            font-size: 14px;
            padding: 12px;
            background: #1c1d2b;
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        .back-link:hover {
            background: #252736;
            color: #00F7FF;
        }

        .alert {
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 14px;
            background: rgba(255, 0, 76, 0.1);
            color: #ff004c;
            border: 1px solid rgba(255, 0, 76, 0.2);
        }

        .success {
            background: rgba(0, 247, 255, 0.1);
            color: #00F7FF;
            border: 1px solid rgba(0, 247, 255, 0.2);
        }

        /* Password strength indicator */
        .password-strength {
            margin-top: 5px;
            font-size: 12px;
            color: #666;
        }

        .strength-weak { color: #ff004c; }
        .strength-medium { color: #ffd700; }
        .strength-strong { color: #00ff00; }

        /* Show/Hide Password */
        .toggle-password {
            position: absolute;
            right: 0;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .toggle-password:hover {
            color: #00F7FF;
        }

        /* Role selection styling */
        .role-select {
            width: 100%;
            padding: 10px;
            padding-left: 35px;
            background: transparent;
            border: none;
            border-bottom: 1px solid #444;
            color: #fff;
            font-size: 14px;
            margin-bottom: 20px;
        }

        .role-select option {
            background: #2A2A2A;
            color: #fff;
        }

        .role-select:focus {
            outline: none;
            border-bottom-color: #00F7FF;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Create Account</h2>
        <p class="subtitle">Join AgeWell Senior Care Services</p>

        <?php if (!empty($error)): ?>
            <div class="alert"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert success"><?php echo $success; ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <input type="text" name="name" id="name" placeholder="Full Name" value="<?php echo htmlspecialchars($name ?? ''); ?>" required>
                <i class="icon fas fa-user"></i>
            </div>

            <div class="form-group">
                <input type="email" name="email" id="email" placeholder="Email Address" value="<?php echo htmlspecialchars($email ?? ''); ?>" required>
                <i class="icon fas fa-envelope"></i>
            </div>

            <div class="form-group">
                <input type="tel" name="phone" id="phone" placeholder="Phone Number" value="<?php echo htmlspecialchars($phone ?? ''); ?>">
                <i class="icon fas fa-phone"></i>
            </div>

            <div class="form-group">
                <input type="password" name="password" id="password" placeholder="Password" required>
                <i class="icon fas fa-lock"></i>
                <i class="toggle-password fas fa-eye" onclick="togglePassword('password')"></i>
                <div class="password-strength" id="password-strength"></div>
            </div>

            <div class="form-group">
                <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm Password" required>
                <i class="icon fas fa-lock"></i>
                <i class="toggle-password fas fa-eye" onclick="togglePassword('confirm_password')"></i>
            </div>

            <div class="form-group">
                <select name="role" id="role" class="role-select" required>
                    <option value="">Select Role</option>
                    <option value="caregiver" <?php echo ($role ?? '') === 'caregiver' ? 'selected' : ''; ?>>Caregiver</option>
                    <option value="admin" <?php echo ($role ?? '') === 'admin' ? 'selected' : ''; ?>>Admin</option>
                </select>
                <i class="icon fas fa-user-shield"></i>
            </div>

            <button type="submit">Register</button>
            <a href="login.php" class="back-link">Already have an account? Login</a>
        </form>
    </div>

    <script>
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = input.nextElementSibling.nextElementSibling;
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // Password strength checker
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthDiv = document.getElementById('password-strength');
            let strength = 0;
            let message = '';

            if (password.match(/[a-z]/)) strength++;
            if (password.match(/[A-Z]/)) strength++;
            if (password.match(/[0-9]/)) strength++;
            if (password.match(/[^a-zA-Z0-9]/)) strength++;
            if (password.length >= 8) strength++;

            switch(strength) {
                case 0:
                case 1:
                    message = '<span class="strength-weak">Weak Password</span>';
                    break;
                case 2:
                case 3:
                    message = '<span class="strength-medium">Medium Password</span>';
                    break;
                case 4:
                case 5:
                    message = '<span class="strength-strong">Strong Password</span>';
                    break;
            }

            strengthDiv.innerHTML = message;
        });
    </script>
</body>
</html>
