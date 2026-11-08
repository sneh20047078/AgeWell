<?php
/**
 * AgeWell Senior Care and Support System
 * Email Reply Sending Script
 */

require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    setAlert('danger', 'Unauthorized access.');
    redirect('login.php');
}

// If not a POST request, redirect
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setAlert('danger', 'Invalid request method.');
    redirect('contact_messages.php');
}

// Get form data
$message_id = isset($_POST['message_id']) ? (int)$_POST['message_id'] : 0;
$to = cleanInput($_POST['to'] ?? '');
$recipient_name = cleanInput($_POST['recipient_name'] ?? '');
$subject = cleanInput($_POST['subject'] ?? '');
$message = cleanInput($_POST['message'] ?? '');
$mark_as_read = isset($_POST['mark_as_read']) ? true : false;

// Validate data
if (empty($message_id) || empty($to) || empty($subject) || empty($message)) {
    setAlert('danger', 'All fields are required.');
    redirect('contact_messages.php');
}

// Verify the contact message exists
$contact_message = fetchOne("SELECT * FROM contact_messages WHERE id = ?", [$message_id], 'i');
if (!$contact_message) {
    setAlert('danger', 'Contact message not found.');
    redirect('contact_messages.php');
}

// Fix duplicate salutation issue in the message content
$message = fixEmailContent($message);

// Get admin info
$admin_name = $_SESSION['user_name'] ?? 'AgeWell Admin';
$admin_id = $_SESSION['user_id'] ?? 1;
$admin_email = ADMIN_EMAIL ?? 'admin@agewell.care';

// Prepare HTML message
$html_message = '
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
        a {
            color: #007bff;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>AgeWell Senior Care</h2>
        </div>
        <div class="content">
            ' . nl2br(htmlspecialchars($message)) . '
        </div>
        <div class="footer">
            <p>AgeWell Senior Care and Support System</p>
            <p>This is an email response to your inquiry. If you have further questions, please reply to this email.</p>
            <p>&copy; ' . date('Y') . ' AgeWell Senior Care. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
';

// Plain text version of the message
$text_message = $message . "\n\n" .
                "AgeWell Senior Care and Support System\n" .
                "This is an email response to your inquiry. If you have further questions, please reply to this email.\n" .
                "© " . date('Y') . " AgeWell Senior Care. All rights reserved.";

// Function to fix common email formatting issues
function fixEmailContent($content) {
    // Remove duplicate salutations (e.g., "Dear Sneha, Dear Sneha")
    $content = preg_replace('/Dear\s+([^,\n]+),\s*\n*\s*Dear\s+([^,\n]+),/i', 'Dear $1,', $content);
    
    // Check if we need to add the salutation (if it's missing)
    if (!preg_match('/^Dear\s+/i', trim($content))) {
        global $recipient_name;
        $content = "Dear " . $recipient_name . ",\n\n" . $content;
    }
    
    return $content;
}

// Try to send email using enhanced function with fallback
try {
    // Use the enhanced email sending function
    if (function_exists('sendEmailWithFallback')) {
        $emailResult = sendEmailWithFallback(
            $to,
            $subject,
            $html_message,
            $text_message,
            [
                'reply_to' => $admin_email,
                'reply_to_name' => $admin_name
            ]
        );
        
        // Email was sent successfully by at least one method
        $email_sent = $emailResult['success'];
        $email_method = $emailResult['method'] ?? 'unknown';
    } else {
        // Fallback to basic PHPMailer if the enhanced function isn't available
        $mail = new PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_ENCRYPTION;
        $mail->Port = SMTP_PORT;
        
        // Recipients
        $mail->setFrom(ADMIN_EMAIL, 'AgeWell Senior Care');
        $mail->addAddress($to, $recipient_name);
        $mail->addReplyTo($admin_email, $admin_name);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $html_message;
        $mail->AltBody = $text_message;
        
        // Send the email
        $mail->send();
        
        $email_sent = true;
        $email_method = 'smtp';
    }
    
    // Log the reply in message_replies table
    $reply_data = [
        'contact_message_id' => $message_id,
        'admin_id' => $admin_id,
        'admin_name' => $admin_name,
        'recipient_email' => $to,
        'subject' => $subject,
        'message' => $message,
        'status' => $email_sent ? 'sent' : 'failed',
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    // Insert reply log into database
    try {
        $reply_id = insert('message_replies', $reply_data);
        
        // Mark original message as read if requested
        if ($mark_as_read && $email_sent) {
            update('contact_messages', ['status' => 'read'], 'id = ?', [$message_id], 'i');
        }
        
        // Create notification for admin
        $notification_message = $email_sent 
            ? "Reply sent successfully to {$recipient_name} ({$to}) using {$email_method}" 
            : "Failed to send reply to {$recipient_name} ({$to})";
        
        $notification_type = $email_sent ? 'success' : 'danger';
        createAdminNotification('message_reply', $notification_message);
        
        // Log admin activity
        logActivity(
            'send_reply', 
            "Admin {$admin_name} " . ($email_sent ? 'sent' : 'attempted to send') . " reply to {$to}"
        );
        
        // Set appropriate alert
        if ($email_sent) {
            setAlert('success', 'Reply sent successfully to ' . $to);
        } else {
            setAlert('danger', 'Failed to send email reply: ' . ($emailResult['message'] ?? 'Unknown error'));
        }
    } catch (Exception $e) {
        // Log the error
        error_log("Error saving reply: " . $e->getMessage());
        setAlert('danger', 'An error occurred while processing your reply. Please try again.');
    }
} catch (Exception $e) {
    // Log error
    error_log("Email Error: " . $e->getMessage());
    setAlert('danger', 'Failed to send email. ' . $e->getMessage());
}

// Redirect back to contact messages
redirect('contact_messages.php'); 