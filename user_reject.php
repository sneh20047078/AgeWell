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

// Check for foreign key constraints before deleting
$hasRelations = false;
$relationTables = [];

// Check healthrecords table
$healthRecordsCount = fetchOne("SELECT COUNT(*) as count FROM healthrecords WHERE caregiver_id = ?", [$userId]);
if ($healthRecordsCount && $healthRecordsCount['count'] > 0) {
    $hasRelations = true;
    $relationTables[] = 'healthrecords';
}

// Check appointments table
$appointmentsCount = fetchOne("SELECT COUNT(*) as count FROM appointments WHERE caregiver_id = ?", [$userId]);
if ($appointmentsCount && $appointmentsCount['count'] > 0) {
    $hasRelations = true;
    $relationTables[] = 'appointments';
}

// Check other potential tables with foreign key constraints
$potentialTables = [
    ['table' => 'activitylogs', 'column' => 'user_id'],
    ['table' => 'prescriptions', 'column' => 'caregiver_id'],
    ['table' => 'seniorcitizens', 'column' => 'assigned_caregiver_id']
];

foreach ($potentialTables as $tableInfo) {
    // Check if table exists
    $tableExists = fetchOne("SHOW TABLES LIKE '{$tableInfo['table']}'");
    if ($tableExists) {
        // Check if column exists
        $columnExists = fetchOne("SHOW COLUMNS FROM `{$tableInfo['table']}` LIKE '{$tableInfo['column']}'");
        if ($columnExists) {
            // Check if user has relations in this table
            $count = fetchOne("SELECT COUNT(*) as count FROM `{$tableInfo['table']}` WHERE `{$tableInfo['column']}` = ?", [$userId]);
            if ($count && $count['count'] > 0) {
                $hasRelations = true;
                $relationTables[] = $tableInfo['table'];
            }
        }
    }
}

if ($hasRelations) {
    // User has relations, we'll deactivate instead of deleting
    $result = executeQuery("UPDATE userdetails SET status = 'inactive' WHERE id = ?", [$userId]);
    
    if ($result) {
        // Log activity
        logActivity($_SESSION['user_id'], 'Rejected user account and deactivated: ' . $user['name'] . ' (has relations in: ' . implode(', ', $relationTables) . ')');
        
        setAlert('warning', 'User has been rejected and deactivated (not deleted) because they have associated records in: ' . implode(', ', $relationTables));
    } else {
        setAlert('danger', 'Failed to deactivate user.');
    }
} else {
    // No relations, safe to delete
    // Try with foreign key checks disabled to ensure deletion
    global $conn;
    
    try {
        // Begin transaction
        $conn->begin_transaction();
        
        // Temporarily disable foreign key checks
        $conn->query("SET FOREIGN_KEY_CHECKS=0");
        
        // Delete the user
        $stmt = $conn->prepare("DELETE FROM userdetails WHERE id = ?");
        $stmt->bind_param('i', $userId);
        $success = $stmt->execute();
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        
        // Re-enable foreign key checks
        $conn->query("SET FOREIGN_KEY_CHECKS=1");
        
        if ($success && $affectedRows > 0) {
            // Commit the transaction
            $conn->commit();
            
            // Log activity
            logActivity($_SESSION['user_id'], 'Rejected and removed user account: ' . $user['name']);
            
            setAlert('success', 'User rejected and removed successfully.');
        } else {
            // Rollback the transaction
            $conn->rollback();
            setAlert('danger', 'Failed to delete user. No rows affected.');
        }
    } catch (Exception $e) {
        // Rollback the transaction on error
        $conn->rollback();
        
        // Re-enable foreign key checks
        $conn->query("SET FOREIGN_KEY_CHECKS=1");
        
        setAlert('danger', 'Error rejecting user: ' . $e->getMessage());
        // Log the error
        error_log('Error in user_reject.php: ' . $e->getMessage());
    }
}

redirect('admin_dashboard.php');
?> 