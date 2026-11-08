<?php
session_start();
require_once 'config.php';
require_once 'db.php';
require_once 'functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = cleanInput($_POST['name'] ?? '');
    $email = cleanInput($_POST['email'] ?? '');
    $phone = cleanInput($_POST['phone'] ?? '');
    $subject = cleanInput($_POST['subject'] ?? '');
    $message = cleanInput($_POST['message'] ?? '');

    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $_SESSION['error_message'] = 'Please fill in all required fields.';
        redirect('../index.php#contact');
    }

    $inserted = query(
        "INSERT INTO contact_messages (name, email, phone, subject, message, created_at) 
         VALUES (?, ?, ?, ?, ?, NOW())",
        [$name, $email, $phone, $subject, $message],
        'sssss'
    );

    if ($inserted) {
        // Create notification for admin
        query(
            "INSERT INTO notifications (title, message, type, created_at) 
             VALUES ('New Contact Message', ?, 'info', NOW())",
            ["New contact message received from $name regarding $subject"],
            's'
        );

        $_SESSION['success_message'] = 'Your message has been sent successfully. We will contact you soon.';
    } else {
        $_SESSION['error_message'] = 'Failed to send message. Please try again.';
    }
}

redirect('../index.php#contact');
?>