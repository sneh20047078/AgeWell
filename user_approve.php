<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/notification_handler.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    setAlert('danger', 'Unauthorized access.');
    redirect('login.php');
}

// Check if id is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    setAlert('danger', 'Invalid user ID.');
    redirect('admin_dashboard.php');
}

$userId = $_GET['id'];

// Get user details
$user = fetchOne("SELECT * FROM userdetails WHERE id = ?", [$userId]);

if (!$user) {
    setAlert('danger', 'User not found.');
    redirect('admin_dashboard.php');
}

// Update user status to active
$result = executeQuery("UPDATE userdetails SET status = 'active' WHERE id = ?", [$userId]);

if ($result) {
    // Log activity
    logActivity($_SESSION['user_id'], 'Approved user account: ' . $user['name']);
    
    // Send notification to user
    $message = "Your account has been approved. You can now login to the system.";
    $notificationData = [
        'user_id' => $userId,
        'message' => $message,
        'type' => 'account_approval'
    ];
    
    // Add notification (assuming this function exists in notification_handler.php)
    if (function_exists('addNotification')) {
        addNotification($notificationData);
    }

    // Send email notification if possible
    if (function_exists('sendEmail')) {
        $emailData = [
            'to' => $user['email'],
            'subject' => 'AgeWell Senior Care - Account Approved',
            'message' => "
                <p>Hello " . htmlspecialchars($user['name']) . ",</p>
                <p>Your account has been approved by the administrator. You can now login to the system using your email and password.</p>
                <p>Click <a href='" . SITE_URL . "/login.php'>here</a> to login.</p>
                <p>Thank you,<br>AgeWell Senior Care Team</p>"
        ];
        sendEmail($emailData);
    }
    
    setAlert('success', 'User approved successfully.');
} else {
    setAlert('danger', 'Failed to approve user.');
}

redirect('admin_dashboard.php');
?> 