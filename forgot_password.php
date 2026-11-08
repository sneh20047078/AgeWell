<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// PHPMailer already required in email_config.php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

$error = '';
$success = '';
$debugInfo = ''; // Add this to store debug information

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = cleanInput($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error = 'Email is required.';
    } else {
        // Check if user exists
        $sql = "SELECT id, name FROM userdetails WHERE email = ? AND status = 'active' LIMIT 1";
        $user = fetchOne($sql, [$email], 's');
        
        if ($user) {
            // Generate reset token
            $token = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Log token generation
            error_log("Generated token: " . $token);
            error_log("Expiry time: " . $expiry);
            
            // Save token in database with verification
            $sql = "INSERT INTO password_resets (user_id, token, expiry) VALUES (?, ?, ?)";
            if (query($sql, [$user['id'], $token, $expiry], 'iss')) {
                // Verify the token was saved
                $verify_sql = "SELECT * FROM password_resets WHERE token = ? LIMIT 1";
                $saved_token = fetchOne($verify_sql, [$token], 's');
                
                if ($saved_token) {
                    error_log("Token successfully saved to database with ID: " . $saved_token['id']);
                } else {
                    error_log("WARNING: Token may not have been saved correctly");
                }
                
                // Send reset email
                $resetLink = SITE_URL . "/reset_password.php?token=" . $token;
                
                // Log the reset link to error log for debugging
                error_log("Reset link generated: " . $resetLink);
                
                // Create HTML email body with improved design
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
                        .button {
                            display: inline-block;
                            padding: 10px 20px;
                            background-color: #0dcaf0;
                            color: #05172e;
                            text-decoration: none;
                            border-radius: 5px;
                            font-weight: bold;
                            margin: 20px 0;
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
                            <p>Hello ' . htmlspecialchars($user['name']) . ',</p>
                            <p>We received a request to reset your password. Click the button below to create a new password:</p>
                            <p style="text-align: center;">
                                <a href="' . $resetLink . '" class="button">Reset Password</a>
                            </p>
                            <p>If the button doesn\'t work, you can also copy and paste the following link into your browser:</p>
                            <p style="text-align: center;"><a href="' . $resetLink . '">' . $resetLink . '</a></p>
                            <p>This link will expire in 1 hour.</p>
                            <p>If you didn\'t request a password reset, please ignore this email or contact support if you have concerns.</p>
                            <p>Thank you,<br>The AgeWell Team</p>
                        </div>
                        <div class="footer">
                            <p>&copy; ' . date('Y') . ' AgeWell Senior Care. All rights reserved.</p>
                            <p>This is an automated message, please do not reply to this email.</p>
                        </div>
                    </div>
                </body>
                </html>';
                
                // Text-only version of the email
                $textBody = "Hello {$user['name']},\n\n" .
                            "We received a request to reset your password. Please click the following link to reset your password:\n" .
                            $resetLink . "\n\n" .
                            "This link will expire in 1 hour.\n\n" .
                            "If you didn't request a password reset, please ignore this email.\n\n" .
                            "Thank you,\nThe AgeWell Team";
                
                try {
                    // Use the enhanced email sending function with fallback
                    $emailResult = sendEmailWithFallback(
                        $email, 
                        "Password Reset Request - " . SITE_NAME,
                        $mailBody,
                        $textBody,
                        [
                            'reply_to' => EMAIL_FROM_ADDRESS,
                            'reply_to_name' => EMAIL_FROM_NAME
                        ]
                    );
                    
                    if ($emailResult['success']) {
                    $success = 'Password reset instructions have been sent to your email.';
                        error_log("Password reset email sent successfully to {$email} using {$emailResult['method']}");
                    } else {
                        $error = "Failed to send reset email. Please try again later.";
                        error_log("Failed to send reset email to {$email}: {$emailResult['message']}");
                    }
                } catch (Exception $e) {
                    $error = "Failed to send reset email. Please try again later.";
                    error_log("Exception when sending reset email to {$email}: " . $e->getMessage());
                }
            } else {
                error_log("FAILED to save token to database for user ID {$user['id']}");
                $error = 'Failed to process reset request. Please try again.';
            }
        } else {
            // Don't reveal if email exists
            $success = 'If your email exists in our system, you will receive reset instructions.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - AgeWell Senior Care</title>
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
            color: #45f3ff;
            text-align: center;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Forgot Password?</h2>
        <p class="subtitle">Enter your registered email to reset the password</p>

        <?php if (!empty($error)): ?>
            <div class="alert"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert" style="background: rgba(0, 247, 255, 0.1); color: #00F7FF;">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <input type="email" name="email" placeholder="Email" required>
            </div>

            <button type="submit">Reset Password</button>
            <a href="login.php" class="back-link">Back to Login</a>
        </form>
    </div>
</body>
</html>