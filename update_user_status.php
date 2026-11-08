<?php
require_once 'includes/config.php';
require_once 'includes/db.php';

// Check if the action was confirmed
$confirmed = isset($_GET['confirm']) && $_GET['confirm'] === 'yes';

if ($confirmed) {
    // Update the users
    $result = $GLOBALS['conn']->query("
        UPDATE userdetails 
        SET status = 'pending' 
        WHERE status = 'inactive'
    ");
    
    if ($result) {
        $affectedRows = $GLOBALS['conn']->affected_rows;
        echo "<div style='background: #d4edda; color: #155724; padding: 15px; margin-bottom: 20px;'>
              Successfully updated $affectedRows users from 'inactive' to 'pending'
              </div>";
    } else {
        echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; margin-bottom: 20px;'>
              Error updating users: " . $GLOBALS['conn']->error . "
              </div>";
    }
}

// Count users with inactive status
$countResult = $GLOBALS['conn']->query("SELECT COUNT(*) as count FROM userdetails WHERE status = 'inactive'");
$countRow = $countResult->fetch_assoc();
$inactiveCount = $countRow['count'];

// Count users with pending status
$pendingResult = $GLOBALS['conn']->query("SELECT COUNT(*) as count FROM userdetails WHERE status = 'pending'");
$pendingRow = $pendingResult->fetch_assoc();
$pendingCount = $pendingRow['count'];

echo "<h1>User Status Management</h1>";

echo "<p>Users with 'inactive' status: <strong>$inactiveCount</strong></p>";
echo "<p>Users with 'pending' status: <strong>$pendingCount</strong></p>";

if (!$confirmed && $inactiveCount > 0) {
    echo "<p>Would you like to update all 'inactive' users to 'pending' status?</p>";
    echo "<a href='update_user_status.php?confirm=yes' style='display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 4px;'>Yes, Update All Users</a>";
    echo " <a href='check_users.php' style='display: inline-block; padding: 10px 20px; background: #6c757d; color: white; text-decoration: none; border-radius: 4px;'>Cancel</a>";
} elseif ($confirmed) {
    echo "<p><a href='admin_dashboard.php' style='display: inline-block; padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 4px;'>Go to Admin Dashboard</a></p>";
} else {
    echo "<p>No inactive users found. No update needed.</p>";
    echo "<p><a href='admin_dashboard.php' style='display: inline-block; padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 4px;'>Go to Admin Dashboard</a></p>";
}
?> 