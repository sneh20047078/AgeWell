<?php
require_once 'includes/config.php';
require_once 'includes/db.php';

// Check if deleted_at column exists
$checkColumn = fetchOne("SHOW COLUMNS FROM userdetails LIKE 'deleted_at'");

if (!$checkColumn) {
    // Add deleted_at column
    $sql = "ALTER TABLE userdetails ADD COLUMN deleted_at DATETIME DEFAULT NULL";
    if (query($sql)) {
        echo "Successfully added deleted_at column to userdetails table.";
    } else {
        echo "Error adding deleted_at column: " . mysqli_error($conn);
    }
} else {
    echo "deleted_at column already exists in userdetails table.";
}
?> 