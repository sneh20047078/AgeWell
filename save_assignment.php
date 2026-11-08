<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $seniorId = isset($_POST['senior_id']) ? (int)$_POST['senior_id'] : 0;
    $caregiverId = isset($_POST['caregiver_id']) ? (int)$_POST['caregiver_id'] : 0;
    
    // Validate inputs
    if ($seniorId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid senior ID']);
        exit;
    }

    // Check if senior exists
    $senior = fetchOne("SELECT id FROM seniorcitizens WHERE id = ?", [$seniorId]);
    if (!$senior) {
        echo json_encode(['success' => false, 'message' => 'Senior not found']);
        exit;
    }

    // Check if caregiver exists
    if ($caregiverId > 0) {
        $caregiver = fetchOne("SELECT id FROM userdetails WHERE id = ? AND role = 'caregiver' AND status = 'active'", [$caregiverId]);
        if (!$caregiver) {
            echo json_encode(['success' => false, 'message' => 'Invalid caregiver selected']);
            exit;
        }
    }

    try {
        // Perform the update
        $sql = "UPDATE seniorcitizens SET caregiver_id = ? WHERE id = ?";
        $result = query($sql, [$caregiverId ?: null, $seniorId]);
        
        if ($result !== false) {
            echo json_encode(['success' => true, 'message' => 'Assignment updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update assignment']);
        }
    } catch (Exception $e) {
        error_log("Assignment update error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}