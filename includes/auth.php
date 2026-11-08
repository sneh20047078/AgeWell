<?php
/**
 * Authentication functions for the AgeWell Senior Care and Support System
 */

require_once 'config.php';
require_once 'db.php';
require_once 'functions.php';

/**
 * Register a new user
 * 
 * @param string $name User's full name
 * @param string $email User's email
 * @param string $password User's password (plain text)
 * @param string $phone User's phone number
 * @param string $role User's role (admin or caregiver)
 * @return array Result with success status and message
 */
function registerUser($name, $email, $password, $phone, $role) {
    // Check if email already exists
    if (emailExists($email)) {
        return [
            'success' => false,
            'message' => 'Email already exists. Please use a different email.'
        ];
    }
    
    // Hash the password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Prepare data for insertion
    $data = [
        'name' => $name,
        'email' => $email,
        'password' => $hashedPassword,
        'phone' => $phone,
        'role' => $role,
        'status' => 'active',
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    // Insert user into database
    $userId = insert('userdetails', $data);
    
    if ($userId === false) {
        return [
            'success' => false,
            'message' => 'Failed to register user. Please try again.'
        ];
    }
    
    // Log activity
    logActivity('user_register', "New user registered: $name ($email)", $userId);
    
    return [
        'success' => true,
        'message' => 'User registered successfully.',
        'user_id' => $userId
    ];
}

/**
 * Authenticate a user
 * 
 * @param string $email User's email
 * @param string $password User's password (plain text)
 * @return array Result with success status and message
 */
function loginUser($email, $password) {
    $user = fetchOne("SELECT * FROM userdetails WHERE email = ?", [$email]);
    
    if (!$user) {
        return ['success' => false, 'message' => 'Invalid email or password.'];
    }
    
    if (!password_verify($password, $user['password'])) {
        return ['success' => false, 'message' => 'Invalid email or password.'];
    }
    
    // Check user status
    if ($user['status'] === 'pending') {
        return ['success' => false, 'message' => 'Your account is pending approval. Please wait for administrator review.'];
    }
    
    // Login successful
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    
    return ['success' => true, 'message' => 'Login successful.'];
}

/**
 * Log out the current user
 */
function logoutUser() {
    // Log activity before clearing session
    if (isset($_SESSION['user_id'])) {
        $userName = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : '';
        logActivity('user_logout', "User logged out: {$userName}", $_SESSION['user_id']);
    }
    
    // Clear all session variables
    $_SESSION = array();
    
    // Destroy the session
    session_destroy();
}

/**
 * Change a user's password
 * 
 * @param int $userId User ID
 * @param string $currentPassword Current password (plain text)
 * @param string $newPassword New password (plain text)
 * @return array Result with success status and message
 */
function changePassword($userId, $currentPassword, $newPassword) {
    // Get user by ID
    $user = getUserById($userId);
    
    if (!$user) {
        return [
            'success' => false,
            'message' => 'User not found.'
        ];
    }
    
    // Verify current password
    if (!password_verify($currentPassword, $user['password'])) {
        return [
            'success' => false,
            'message' => 'Current password is incorrect.'
        ];
    }
    
    // Hash the new password
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    
    // Update password in database
    $updated = update('userdetails', ['password' => $hashedPassword], 'id = ?', [$userId]);
    
    if (!$updated) {
        return [
            'success' => false,
            'message' => 'Failed to change password. Please try again.'
        ];
    }
    
    // Log activity
    logActivity('password_change', "Password changed for user: {$user['name']} ({$user['email']})", $userId);
    
    return [
        'success' => true,
        'message' => 'Password changed successfully.'
    ];
}

/**
 * Update the status of a user
 * 
 * @param int $userId User ID
 * @param string $status New status (active or inactive)
 * @return array Result with success status and message
 */
function updateUserStatus($userId, $status) {
    // Check if status is valid
    if (!in_array($status, ['active', 'inactive'])) {
        return [
            'success' => false,
            'message' => 'Invalid status.'
        ];
    }
    
    // Get user by ID
    $user = getUserById($userId);
    
    if (!$user) {
        return [
            'success' => false,
            'message' => 'User not found.'
        ];
    }
    
    // Update status in database
    $updated = update('userdetails', ['status' => $status], 'id = ?', [$userId]);
    
    if (!$updated) {
        return [
            'success' => false,
            'message' => 'Failed to update user status. Please try again.'
        ];
    }
    
    // Log activity
    $statusAction = $status === 'active' ? 'activated' : 'deactivated';
    logActivity('user_status_change', "User $statusAction: {$user['name']} ({$user['email']})");
    
    return [
        'success' => true,
        'message' => "User {$statusAction} successfully."
    ];
}

/**
 * Update user profile information
 * 
 * @param int $userId User ID
 * @param array $data User data to update
 * @return array Result with success status and message
 */
function updateUserProfile($userId, $data) {
    // Get user by ID
    $user = getUserById($userId);
    
    if (!$user) {
        return [
            'success' => false,
            'message' => 'User not found.'
        ];
    }
    
    // Check if email is being changed and if it already exists
    if (isset($data['email']) && $data['email'] !== $user['email'] && emailExists($data['email'], $userId)) {
        return [
            'success' => false,
            'message' => 'Email already exists. Please use a different email.'
        ];
    }
    
    // Remove password from data if it exists
    if (isset($data['password'])) {
        unset($data['password']);
    }
    
    // Update user in database
    $updated = update('userdetails', $data, 'id = ?', [$userId]);
    
    if (!$updated) {
        return [
            'success' => false,
            'message' => 'Failed to update user profile. Please try again.'
        ];
    }
    
    // Log activity
    logActivity('user_profile_update', "User profile updated for: {$user['name']} ({$user['email']})");
    
    // Update session information if the current user is being updated
    if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $userId) {
        if (isset($data['name'])) $_SESSION['user_name'] = $data['name'];
        if (isset($data['email'])) $_SESSION['user_email'] = $data['email'];
        if (isset($data['role'])) $_SESSION['user_role'] = $data['role'];
    }
    
    return [
        'success' => true,
        'message' => 'User profile updated successfully.'
    ];
}
?>
