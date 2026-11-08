<?php
// Show all errors during development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/config/email_config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$error = '';
$success = '';
$token = isset($_GET['token']) ? trim($_GET['token']) : '';
$debug = []; // You can keep this, but don't add items to it

// Now clean the token
$cleanedToken = cleanInput($token);
if ($token !== $cleanedToken) {
    // $debug[] = "WARNING: Token was changed by cleanInput()!";
    // $debug[] = "Before: " . substr($token, 0, 20) . "...";
    // $debug[] = "After: " . substr($cleanedToken, 0, 20) . "...";
}

// Use the raw token for validation to avoid cleaning issues
$token = $cleanedToken;
// $debug[] = "Final token being used: " . substr($token, 0, 20) . "...";

if (empty($token)) {
    $error = 'Invalid or missing token. Please request a new password reset link.';
    // $debug[] = "Token is empty";
} else {
    // First check if token exists at all
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE token = ?");
        $stmt->execute([$token]);
        $basicCheck = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($basicCheck) {
            // $debug[] = "✅ Found token in database with ID: " . $basicCheck['id'];
            
            // Now check if it's valid (not used and not expired)
            if ($basicCheck['used'] == 1) {
                $error = 'This reset token has already been used. Please request a new password reset.';
                // $debug[] = "❌ Token has been marked as used";
            } elseif (strtotime($basicCheck['expiry']) < time()) {
                $error = 'This reset token has expired. Please request a new password reset.';
                // $debug[] = "❌ Token expired on: " . $basicCheck['expiry'] . " (current time: " . date('Y-m-d H:i:s') . ")";
            } else {
                // Token is valid, get the associated user
                $stmt = $pdo->prepare("SELECT * FROM userdetails WHERE id = ?");
                $stmt->execute([$basicCheck['user_id']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user) {
                    // $debug[] = "✅ Found associated user: " . $user['name'] . " (ID: " . $user['id'] . ")";
                    // Set reset data that will be used later
                    $reset = array_merge($basicCheck, [
                        'name' => $user['name'],
                        'email' => $user['email']
                    ]);
                } else {
                    $error = 'Could not find the user associated with this token.';
                    // $debug[] = "❌ Could not find user with ID: " . $basicCheck['user_id'];
                }
            }
        } else {
            $error = 'Invalid reset token. Please request a new password reset link.';
            // $debug[] = "❌ Token not found in database at all";
        }
    } catch (Exception $e) {
        $error = 'Database error. Please try again later.';
        // $debug[] = "Exception: " . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($reset)) {
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    
    if (empty($password)) {
        $error = 'Password is required.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        // Update password and mark token as used
        $hash = password_hash($password, PASSWORD_DEFAULT);
        try {
            $sql = "UPDATE userdetails SET password = ? WHERE id = ?";
            if (query($sql, [$hash, $reset['user_id']], 'si')) {
                query("UPDATE password_resets SET used = 1 WHERE id = ?", [$reset['id']], 'i');
                $success = 'Password has been reset successfully. You can now login with your new password.';
                // $debug[] = "Password updated successfully for user ID: " . $reset['user_id'];
                
                // Send confirmation email
                try {
                    // Create email body
                    $mailBody = '
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <style>
                            body {
                                font-family: Arial, sans-serif;
                                line-height: 1.6;
                                color: #333;
                                background-color: #f9f9f9;
                                margin: 0;
                                padding: 0;
                            }
                            .container {
                                max-width: 600px;
                                margin: 0 auto;
                                padding: 20px;
                                background-color: #ffffff;
                                border-radius: 5px;
                                box-shadow: 0 0 10px rgba(0,0,0,0.1);
                            }
                            .header {
                                background-color: #05172e;
                                color: #fff;
                                padding: 15px 20px;
                                text-align: center;
                                border-radius: 5px 5px 0 0;
                            }
                            .content {
                                padding: 20px;
                                background-color: #ffffff;
                            }
                            .footer {
                                text-align: center;
                                margin-top: 20px;
                                padding: 15px;
                                font-size: 12px;
                                color: #777;
                                border-top: 1px solid #ddd;
                            }
                        </style>
                    </head>
                    <body>
                        <div class="container">
                            <div class="header">
                                <h2>AgeWell Senior Care</h2>
                            </div>
                            <div class="content">
                                <p>Hello ' . htmlspecialchars($reset['name']) . ',</p>
                                <p>Your password has been successfully reset.</p>
                                <p>If you did not make this change, please contact us immediately.</p>
                                <p>You can now log in with your new password.</p>
                                <p>Thank you,<br>The AgeWell Team</p>
                            </div>
                            <div class="footer">
                                <p>&copy; ' . date('Y') . ' AgeWell Senior Care. All rights reserved.</p>
                                <p>This is an automated message, please do not reply to this email.</p>
                            </div>
                        </div>
                    </body>
                    </html>';
                    
                    // Text-only version
                    $textBody = "Hello {$reset['name']},\n\n" .
                                "Your password has been successfully reset.\n\n" .
                                "If you did not make this change, please contact us immediately.\n\n" .
                                "You can now log in with your new password.\n\n" .
                                "Thank you,\nThe AgeWell Team";
                    
                    // Send email with fallback
                    $emailResult = sendEmailWithFallback(
                        $reset['email'],
                        "Your Password Has Been Reset - " . SITE_NAME,
                        $mailBody,
                        $textBody
                    );
                    
                    if ($emailResult['success']) {
                        error_log("Password reset confirmation email sent to {$reset['email']} using {$emailResult['method']}");
                    } else {
                        error_log("Failed to send password reset confirmation email: {$emailResult['message']}");
                    }
                } catch (Exception $e) {
                    error_log("Failed to send password reset confirmation email: " . $e->getMessage());
                    // Don't show this error to the user, as the password was still reset successfully
                }
                
                echo '<script>setTimeout(function(){ window.location.href = "login.php"; }, 3000);</script>';
            } else {
                $error = 'Failed to reset password. Please try again.';
                // $debug[] = "Failed to update password in database";
            }
        } catch (Exception $e) {
            $error = 'Database error updating password.';
            // $debug[] = "Exception updating password: " . $e->getMessage();
        }
    }
}

// Only show debug info during development - remove or hide this in production
$showDebug = false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - AgeWell Senior Care</title>
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
        }

        .form-group input {
            width: 100%;
            padding: 10px;
            background: transparent;
            border: none;
            border-bottom: 1px solid #444;
            color: #888;
            font-size: 14px;
        }

        .form-group input:focus {
            outline: none;
            border-bottom-color: #00F7FF;
            color: #fff;
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
        }

        .alert {
            padding: 8px;
            border-radius: 4px;
            margin-bottom: 15px;
            text-align: center;
            font-size: 14px;
            background: rgba(255, 0, 76, 0.1);
            color: #ff004c;
        }
        
        .success {
            padding: 8px;
            border-radius: 4px;
            margin-bottom: 15px;
            text-align: center;
            font-size: 14px;
            background: rgba(0, 247, 255, 0.1);
            color: #00F7FF;
        }
        
        /* Debug panel style */
        .debug-panel {
            margin-top: 20px;
            padding: 10px;
            background: #333;
            color: #00ff00;
            font-family: monospace;
            font-size: 12px;
            border-radius: 5px;
            text-align: left;
        }
        .debug-panel h3 {
            color: #fff;
            margin-bottom: 10px;
        }
        .debug-item {
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Reset Password</h2>
        <p class="subtitle">Enter your new password</p>

            <?php if (!empty($error)): ?>
                <div class="alert"><?php echo $error; ?></div>
            <?php if (empty($token) || !isset($reset) || !$reset): ?>
                <a href="forgot_password.php" class="back-link" id="requestNewLink">Request New Reset Link</a>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="success"><?php echo $success; ?></div>
            <p class="subtitle">Redirecting to login page in 3 seconds...</p>
            <a href="login.php" class="back-link">Login Now</a>
        <?php endif; ?>

        <?php if (empty($success) && isset($reset) && $reset): ?>
            <form method="POST">
                <div class="form-group">
                    <input type="password" name="password" placeholder="New Password" required>
                </div>
                <div class="form-group">
                    <input type="password" name="confirm_password" placeholder="Confirm Password" required>
                </div>
                <button type="submit">Update Password</button>
                <a href="login.php" class="back-link">Back to Login</a>
            </form>
        <?php endif; ?>
        
        <?php if ($showDebug && !empty($debug)): ?>
            <div class="debug-panel">
                <h3>Debug Information</h3>
                <?php foreach ($debug as $item): ?>
                    <div class="debug-item"><?php echo htmlspecialchars($item); ?></div>
                <?php endforeach; ?>
                
                <h3>Server Information</h3>
                <div class="debug-item">Server Time: <?php echo date('Y-m-d H:i:s'); ?></div>
                <div class="debug-item">PHP Version: <?php echo phpversion(); ?></div>
        </div>
        <?php endif; ?>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Add click event listener to the "Request New Reset Link" button
        const resetLinkBtn = document.getElementById('requestNewLink');
        if (resetLinkBtn) {
            resetLinkBtn.addEventListener('click', function(e) {
                // You could add analytics or additional functionality here
                console.log('User requested new reset link');
            });
        }
    });
    </script>
</body>
</html>