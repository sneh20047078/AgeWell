<?php
/**
 * General utility functions for the AgeWell Senior Care and Support System
 */

require_once 'config.php';
require_once 'db.php';

/**
 * Redirect to a specified URL
 * 
 * @param string $url URL to redirect to
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * Check if user is logged in
 * 
 * @return bool True if logged in, false otherwise
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Check if the current user has admin role
 * 
 * @return bool True if admin, false otherwise
 */
function isAdmin() {
    return isLoggedIn() && $_SESSION['user_role'] === ROLE_ADMIN;
}

/**
 * Check if the current user has caregiver role
 * 
 * @return bool True if caregiver, false otherwise
 */
function isCaregiver() {
    return isLoggedIn() && $_SESSION['user_role'] === ROLE_CAREGIVER;
}

/**
 * Verify user has permission to access a page
 * 
 * @param array $allowedRoles Array of roles allowed to access
 * @return bool True if access granted, redirects to login if not logged in, dashboard if unauthorized
 */
function checkPermission($allowedRoles = []) {
    if (!isLoggedIn()) {
        setAlert('danger', 'Please login to access this page.');
        redirect('login.php');
        return false;
    }
    
    if (empty($allowedRoles) || in_array($_SESSION['user_role'], $allowedRoles)) {
        return true;
    }
    
    setAlert('danger', 'You do not have permission to access this page.');
    redirect('dashboard.php');
    return false;
}

/**
 * Set alert message in session
 * 
 * @param string $type Alert type (success, danger, warning, info)
 * @param string $message Alert message
 */
function setAlert($type, $message) {
    $_SESSION['alert_type'] = $type;
    $_SESSION['alert_message'] = $message;
}

/**
 * Display alert message if exists and clear it
 */
function displayAlert() {
    if (isset($_SESSION['alert_message']) && isset($_SESSION['alert_type'])) {
        $type = $_SESSION['alert_type'];
        $message = $_SESSION['alert_message'];
        
        echo "<div class='alert alert-$type alert-dismissible fade show' role='alert'>
                $message
                <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
              </div>";
        
        unset($_SESSION['alert_type']);
        unset($_SESSION['alert_message']);
    }
}

/**
 * Format date from database to user-friendly format
 * 
 * @param string $date Date string from database
 * @param string $format Format to use (default: Y-m-d H:i:s)
 * @return string Formatted date string
 */
function formatDate($date, $format = 'Y-m-d H:i:s') {
    if (empty($date)) return '';
    $datetime = new DateTime($date);
    return $datetime->format($format);
}

/**
 * Clean and sanitize user input
 * @param string $input The input to be cleaned
 * @return string Sanitized input
 */
function cleanInput($input) {
    $input = trim($input);
    $input = strip_tags($input);
    $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    return $input;
}

/**
 * Log an activity in the system
 * 
 * @param string $action Action performed
 * @param string $details Details of the action
 * @param int $userId User ID who performed the action (default: current user)
 * @return bool True on success, false on failure
 */
function logActivity($action, $details, $userId = null) {
    if ($userId === null && isset($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id'];
    }
    
    $data = [
        'user_id' => $userId,
        'action' => $action,
        'details' => $details,
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    return insert('activitylogs', $data) !== false;
}

/**
 * Get user details by ID
 * 
 * @param int $userId User ID
 * @return array|false User details or false if not found
 */
function getUserById($userId) {
    return fetchOne("SELECT * FROM userdetails WHERE id = ?", [$userId]);
}

/**
 * Get senior citizen details by ID
 * 
 * @param int $seniorId Senior ID
 * @return array|false Senior details or false if not found
 */
function getSeniorById($seniorId) {
    return fetchOne("SELECT * FROM seniorcitizens WHERE id = ?", [$seniorId]);
}

/**
 * Create a notification for users
 * 
 * @param string $title Notification title
 * @param string $message Notification message
 * @param int $forUserId User ID for whom the notification is intended (NULL for all)
 * @param string $type Notification type (info, warning, success, danger)
 * @param bool $isEmergency Whether it's an emergency notification
 * @return int|false Notification ID or false on failure
 */
function createNotification($title, $message, $forUserId = null, $type = 'info', $isEmergency = false) {
    $data = [
        'title' => $title,
        'message' => $message,
        'for_user_id' => $forUserId,
        'type' => $type,
        'is_emergency' => $isEmergency ? true : false,
        'is_read' => false,
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    return insert('notifications', $data);
}

/**
 * Get count of unread notifications for a user
 * 
 * @param int $userId User ID
 * @return int Count of unread notifications
 */
function getUnreadNotificationCount($userId) {
    $result = fetchOne(
        "SELECT COUNT(*) as count FROM notifications 
         WHERE (for_user_id = ? OR for_user_id IS NULL) AND is_read = false",
        [$userId]
    );
    
    return $result ? (int)$result['count'] : 0;
}

/**
 * Generate pagination links
 * 
 * @param int $currentPage Current page number
 * @param int $totalPages Total number of pages
 * @param string $url Base URL for pagination links
 * @return string HTML for pagination links
 */
function generatePagination($currentPage, $totalPages, $url) {
    if ($totalPages <= 1) {
        return '';
    }
    
    $html = '<nav aria-label="Page navigation"><ul class="pagination justify-content-center">';
    
    // Previous button
    if ($currentPage > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $url . 'page=' . ($currentPage - 1) . '">&laquo; Previous</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><a class="page-link" href="#">&laquo; Previous</a></li>';
    }
    
    // Page numbers
    $startPage = max(1, $currentPage - 2);
    $endPage = min($totalPages, $currentPage + 2);
    
    if ($startPage > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $url . 'page=1">1</a></li>';
        if ($startPage > 2) {
            $html .= '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
        }
    }
    
    for ($i = $startPage; $i <= $endPage; $i++) {
        if ($i == $currentPage) {
            $html .= '<li class="page-item active"><a class="page-link" href="#">' . $i . '</a></li>';
        } else {
            $html .= '<li class="page-item"><a class="page-link" href="' . $url . 'page=' . $i . '">' . $i . '</a></li>';
        }
    }
    
    if ($endPage < $totalPages) {
        if ($endPage < $totalPages - 1) {
            $html .= '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
        }
        $html .= '<li class="page-item"><a class="page-link" href="' . $url . 'page=' . $totalPages . '">' . $totalPages . '</a></li>';
    }
    
    // Next button
    if ($currentPage < $totalPages) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $url . 'page=' . ($currentPage + 1) . '">Next &raquo;</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><a class="page-link" href="#">Next &raquo;</a></li>';
    }
    
    $html .= '</ul></nav>';
    
    return $html;
}

/**
 * Get count of all seniors in the system
 * 
 * @return int Count of all seniors
 */
function getTotalSeniorsCount() {
    $result = fetchOne("SELECT COUNT(*) as count FROM seniorcitizens");
    return $result ? (int)$result['count'] : 0;
}

/**
 * Get count of all caregivers in the system
 * 
 * @return int Count of all caregivers
 */
function getTotalCaregiversCount() {
    $result = fetchOne("SELECT COUNT(*) as count FROM userdetails WHERE role = ?", [ROLE_CAREGIVER]);
    return $result ? (int)$result['count'] : 0;
}

/**
 * Get count of all appointments in the system
 * 
 * @return int Count of all appointments
 */
function getTotalAppointmentsCount() {
    $result = fetchOne("SELECT COUNT(*) as count FROM appointments");
    return $result ? (int)$result['count'] : 0;
}

/**
 * Get count of all upcoming appointments in the system
 * 
 * @return int Count of upcoming appointments
 */
function getUpcomingAppointmentsCount() {
    $currentDate = date('Y-m-d H:i:s');
    $result = fetchOne(
        "SELECT COUNT(*) as count FROM appointments WHERE appointment_datetime > ?",
        [$currentDate]
    );
    return $result ? (int)$result['count'] : 0;
}

/**
 * Check if email already exists in the system
 * 
 * @param string $email Email to check
 * @param int $excludeUserId User ID to exclude from check (for updates)
 * @return bool True if email exists, false otherwise
 */
function emailExists($email, $excludeUserId = null) {
    if ($excludeUserId) {
        $result = fetchOne(
            "SELECT id FROM userdetails WHERE email = ? AND id != ?",
            [$email, $excludeUserId]
        );
    } else {
        $result = fetchOne(
            "SELECT id FROM userdetails WHERE email = ?",
            [$email]
        );
    }
    
    return $result !== false;
}

/**
 * Generate a CSV file for download
 * 
 * @param array $data Array of data rows
 * @param array $headers Array of column headers
 * @param string $filename Filename for download
 */
function generateCSV($data, $headers, $filename) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    fputcsv($output, $headers);
    
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit;
}

/**
 * Generate a PDF report
 * 
 * Note: This is a placeholder. In a real application, you would use a library like FPDF or TCPDF
 * 
 * @param array $data Array of data rows
 * @param array $headers Array of column headers
 * @param string $title Report title
 * @param string $filename Filename for download
 */
function generatePDF($data, $headers, $title, $filename) {
    echo "<div class='alert alert-warning'>PDF generation would be implemented here using a library like FPDF or TCPDF.</div>";
}

/**
 * Get admin notifications
 * 
 * @param int $limit Limit number of notifications to return
 * @return array Array of admin notifications
 */
function getAdminNotifications($limit = 10) {
    return fetchAll(
        "SELECT * FROM admin_notifications 
         WHERE is_read = 0 
         ORDER BY created_at DESC 
         LIMIT ?",
        [$limit]
    );
}

/**
 * Mark admin notification as read
 * 
 * @param int $notificationId Notification ID
 * @return bool True on success, false on failure
 */
function markAdminNotificationAsRead($notificationId) {
    return update(
        'admin_notifications',
        ['is_read' => 1],
        'id = ?',
        [$notificationId]
    );
}

/**
 * Get count of unread admin notifications
 * 
 * @return int Count of unread admin notifications
 */
function getUnreadAdminNotificationCount() {
    $result = fetchOne(
        "SELECT COUNT(*) as count FROM admin_notifications WHERE is_read = 0"
    );
    return $result ? (int)$result['count'] : 0;
}

/**
 * Create admin notification
 * 
 * @param string $type Notification type
 * @param string $message Notification message
 * @param string $link Optional link
 * @return bool True on success, false on failure
 */
function createAdminNotification($type, $message, $link = '') {
    $data = [
        'type' => $type,
        'message' => $message,
        'link' => $link,
        'is_read' => 0,
        'created_at' => date('Y-m-d H:i:s')
    ];
    return insert('admin_notifications', $data) !== false;
}

// Add these debugging functions at the end of the file

/**
 * Debug database query
 * 
 * @param string $sql SQL query
 * @param array $params Query parameters
 * @return void
 */
function debugQuery($sql, $params = []) {
    error_log("Debug SQL: " . $sql);
    error_log("Debug Params: " . print_r($params, true));
    
    // Test database connection
    try {
        $conn = getConnection();
        error_log("Database connection successful");
        
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            error_log("Query preparation failed");
            return;
        }
        
        if (!empty($params)) {
            if ($stmt->execute($params)) {
                error_log("Query executed successfully");
                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                error_log("Results: " . print_r($result, true));
            } else {
                error_log("Query execution failed");
                error_log("Error info: " . print_r($stmt->errorInfo(), true));
            }
        }
    } catch (Exception $e) {
        error_log("Database error: " . $e->getMessage());
    }
}

/**
 * Test database connection and basic queries
 * 
 * @return bool True if all tests pass, false otherwise
 */
function testDatabaseConnection() {
    try {
        $conn = getConnection();
        error_log("Database connection test: Success");
        
        // Test simple query
        $result = fetchOne("SELECT 1 as test");
        if ($result && isset($result['test'])) {
            error_log("Basic query test: Success");
            return true;
        } else {
            error_log("Basic query test: Failed");
            return false;
        }
    } catch (Exception $e) {
        error_log("Database connection test failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Fetch all rows from a query with support for named parameters
 * @param string $sql
 * @param array $params
 * @param string $types
 * @return array|false
 */
function fetchAllWithNamed($sql, $params = array(), $types = null) {
    global $conn;
    
    try {
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            error_log("Prepare failed: " . $conn->error);
            return false;
        }
        
        if (!empty($params)) {
            // Check if using named parameters or question marks
            $firstKey = array_key_first($params);
            $isNamedParams = is_string($firstKey) && $firstKey[0] === ':';
            
            if ($isNamedParams) {
                // Named parameters - replace with question marks and reorder params
                $questionMarkSql = $sql;
                $orderedParams = [];
                
                // Extract parameter names from SQL in order
                preg_match_all('/:([a-zA-Z0-9_]+)/', $sql, $matches);
                
                if (empty($matches[1])) {
                    error_log("No named parameters found in query: $sql");
                    return false;
                }
                
                // Build ordered params array
                foreach ($matches[1] as $paramName) {
                    $paramKey = ":$paramName";
                    if (isset($params[$paramKey])) {
                        $orderedParams[] = $params[$paramKey];
                    } else {
                        error_log("Missing parameter: $paramKey");
                        return false;
                    }
                }
                
                // Replace named parameters with question marks
                $questionMarkSql = preg_replace('/:([a-zA-Z0-9_]+)/', '?', $sql);
                
                // Execute with ordered params
                $types = $types ?? str_repeat('s', count($orderedParams));
                $stmt = $conn->prepare($questionMarkSql);
                $stmt->bind_param($types, ...$orderedParams);
            } else {
                // Regular question mark parameters
                if ($types === null) {
                    $types = str_repeat('s', count($params));
                }
                $stmt->bind_param($types, ...$params);
            }
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        if (!$result) {
            error_log("Get result failed: " . $stmt->error);
            return false;
        }
        
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        
        return $rows;
    } catch (Exception $e) {
        error_log("Database Error in fetchAllWithNamed: " . $e->getMessage());
        return false;
    }
}

/**
 * Fetch a single row from a query with support for named parameters
 * @param string $sql
 * @param array $params
 * @param string $types
 * @return array|false
 */
function fetchOneWithNamed($sql, $params = array(), $types = null) {
    $rows = fetchAllWithNamed($sql, $params, $types);
    return $rows ? $rows[0] : false;
}
?>
