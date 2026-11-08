<?php
require_once 'includes/db.php';

// Check if the notification_logs table already exists
$result = $conn->query("SHOW TABLES LIKE 'notification_logs'");
if ($result->num_rows > 0) {
    echo "The notification_logs table already exists. No action taken.";
} else {
    // Create the notification_logs table
    $sql = "CREATE TABLE notification_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        senior_id INT NOT NULL,
        type VARCHAR(50) NOT NULL,
        message TEXT NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        created_at DATETIME NOT NULL,
        FOREIGN KEY (senior_id) REFERENCES seniorcitizens(id) ON DELETE CASCADE
    )";
    
    if ($conn->query($sql) === TRUE) {
        echo "The notification_logs table was created successfully.";
    } else {
        echo "Error creating notification_logs table: " . $conn->error;
    }
}
?> 