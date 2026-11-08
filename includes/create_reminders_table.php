<?php
/**
 * Create reminders table if it doesn't exist
 */
require_once 'db.php';

global $conn;

// Check if table exists
$tableExists = $conn->query("SHOW TABLES LIKE 'reminders'");
if ($tableExists->num_rows == 0) {
    // Table doesn't exist, create it
    $sql = "CREATE TABLE `reminders` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `appointment_id` int(11) NOT NULL,
        `reminder_time` datetime NOT NULL,
        `message` text NOT NULL,
        `is_sent` tinyint(1) NOT NULL DEFAULT '0',
        `sent_at` datetime DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `appointment_id` (`appointment_id`),
        KEY `reminder_time` (`reminder_time`),
        KEY `is_sent` (`is_sent`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if ($conn->query($sql) === TRUE) {
        echo "Reminders table created successfully";
    } else {
        echo "Error creating reminders table: " . $conn->error;
    }
} else {
    echo "Reminders table already exists";
} 