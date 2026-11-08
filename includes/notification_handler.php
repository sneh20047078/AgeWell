<?php
/**
 * Notification Handler Functions
 */

// Only declare functions if they haven't been defined yet
if (!function_exists('getUnreadNotificationCount')) {
    function getUnreadNotificationCount($userId) {
        return fetchOne(
            "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0",
            [$userId],
            'i'
        )['count'] ?? 0;
    }
}

if (!function_exists('getNotificationsForUser')) {
    function getNotificationsForUser($userId, $limit = 5) {
        return fetchAll(
            "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?",
            [$userId, $limit],
            'ii'
        );
    }
}

if (!function_exists('getNotificationIcon')) {
    function getNotificationIcon($type) {
        $icons = [
            'info' => 'info-circle',
            'success' => 'check-circle',
            'warning' => 'exclamation-triangle',
            'danger' => 'exclamation-circle'
        ];
        
        return $icons[$type] ?? 'bell';
    }
}

if (!function_exists('createNotification')) {
    function createNotification($title, $message, $forUserId = null, $type = 'info', $isEmergency = false) {
        return executeQuery(
            "INSERT INTO notifications (title, message, for_user_id, type, is_emergency, is_read, created_at)
             VALUES (?, ?, ?, ?, ?, 0, NOW())",
            [$title, $message, $forUserId, $type, $isEmergency ? 1 : 0],
            'ssisi'
        );
    }
}

// Keep only one declaration of getUserNotifications()
/**
 * Get notifications for a specific user
 * 
 * @param int $userId User ID
 * @param int $limit Number of notifications to retrieve (0 for all)
 * @param bool $unreadOnly Whether to retrieve only unread notifications
 * @return array Notifications
 */
function getUserNotifications($userId, $limit = 0, $unreadOnly = false) {
    $sql = "SELECT * FROM notifications 
            WHERE (for_user_id = ? OR for_user_id IS NULL)";
    
    $params = [$userId];
    $types = 'i';
    
    if ($unreadOnly) {
        $sql .= " AND is_read = false";
    }
    
    $sql .= " ORDER BY created_at DESC";
    
    if ($limit > 0) {
        $sql .= " LIMIT ?";
        $params[] = $limit;
        $types .= 'i';
    }
    
    return fetchAll($sql, $params, $types);
}

// Remove the second markNotificationAsRead function (around line 422)
// Delete or comment out this entire block:
/*
function markNotificationAsRead($notificationId) {
    global $conn;
    
    $sql = "UPDATE notifications SET is_read = 1 WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    
    if ($stmt) {
        $stmt->bind_param('i', $notificationId);
        return $stmt->execute();
    }
    
    return false;
}
*/

/**
 * Get count of unread notifications for a user
 */

/**
 * Create an appointment reminder notification
 * 
 * @param int $appointmentId The ID of the appointment
 * @param int $hoursBefore Number of hours before the appointment to send reminder
 * @return bool Success status
 */
if (!function_exists('createAppointmentReminder')) {
    function createAppointmentReminder($appointmentId, $hoursBefore = 24) {
        global $conn;
        
        try {
            // Get appointment details
            $appointment = fetchOne(
                "SELECT a.*, s.name as senior_name, u.id as caregiver_user_id 
                FROM appointments a 
                LEFT JOIN seniorcitizens s ON a.senior_id = s.id 
                LEFT JOIN userdetails u ON a.caregiver_id = u.id 
                WHERE a.id = ?",
                [$appointmentId],
                'i'
            );
            
            if (!$appointment) {
                return false;
            }
            
            // Calculate reminder time
            $appointmentTime = new DateTime($appointment['appointment_datetime']);
            $reminderTime = clone $appointmentTime;
            $reminderTime->sub(new DateInterval("PT{$hoursBefore}H"));
            
            // Format times for display
            $appointmentFormatted = $appointmentTime->format('F j, Y \a\t g:i A');
            $reminderFormatted = $reminderTime->format('Y-m-d H:i:s');
            
            // Create message based on appointment details
            $message = "Reminder: Appointment with {$appointment['doctor_name']} for {$appointment['senior_name']} at {$appointmentFormatted}";
            
            // Add reminder to database
            $sql = "INSERT INTO reminders (appointment_id, reminder_time, message, is_sent) 
                   VALUES (?, ?, ?, 0)";
                   
            $result = executeQuery($sql, [
                $appointmentId, 
                $reminderFormatted, 
                $message
            ], 'iss');
            
            // Create notification for caregiver if assigned
            if (!empty($appointment['caregiver_user_id'])) {
                createNotification(
                    'Appointment Reminder',
                    $message,
                    (int)$appointment['caregiver_user_id'],
                    'info'
                );
            }
            
            return $result;
        } catch (Exception $e) {
            // Log error but don't expose to user
            error_log("Error creating appointment reminder: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * Create a health alert when a metric exceeds normal thresholds
 * 
 * @param int $seniorId The ID of the senior
 * @param string $metricType The type of health metric (blood_pressure, heart_rate, etc.)
 * @param string $value The recorded value
 * @param array $thresholds The threshold values for this metric type
 * @return bool Success status
 */
function createHealthAlert($seniorId, $metricType, $value, $thresholds) {
    global $conn;
    
    if (!$conn) {
        error_log("Database connection not available in createHealthAlert");
        return false;
    }
    
    try {
        // Determine alert level based on thresholds
        $alertLevel = 'normal';
        $alertMessage = '';
        
        switch ($metricType) {
            case 'blood_pressure':
                $parts = explode('/', $value);
                $systolic = isset($parts[0]) ? (int)trim($parts[0]) : 0;
                $diastolic = isset($parts[1]) ? (int)trim($parts[1]) : 0;
                
                if ($systolic >= $thresholds['systolic_high_emergency'] || 
                    $diastolic >= $thresholds['diastolic_high_emergency'] ||
                    $systolic <= $thresholds['systolic_low_emergency'] || 
                    $diastolic <= $thresholds['diastolic_low_emergency']) {
                    $alertLevel = 'critical';
                    $alertMessage = "Blood pressure reading of {$value} mmHg is at a CRITICAL level and requires immediate attention";
                } elseif ($systolic >= $thresholds['systolic_high'] || 
                         $diastolic >= $thresholds['diastolic_high'] ||
                         $systolic <= $thresholds['systolic_low'] || 
                         $diastolic <= $thresholds['diastolic_low']) {
                    $alertLevel = 'warning';
                    $alertMessage = "Blood pressure reading of {$value} mmHg is above/below normal range";
                }
                break;
                
            case 'heart_rate':
                $value = (int)$value;
                if ($value >= $thresholds['high_emergency'] || $value <= $thresholds['low_emergency']) {
                    $alertLevel = 'critical';
                    $alertMessage = "Heart rate of {$value} bpm is at a CRITICAL level and requires immediate attention";
                } elseif ($value >= $thresholds['high'] || $value <= $thresholds['low']) {
                    $alertLevel = 'warning';
                    $alertMessage = "Heart rate of {$value} bpm is outside normal range";
                }
                break;
                
            case 'blood_sugar':
                $value = (int)$value;
                if ($value >= $thresholds['high_emergency'] || $value <= $thresholds['low_emergency']) {
                    $alertLevel = 'critical';
                    $alertMessage = "Blood sugar level of {$value} mg/dL is at a CRITICAL level and requires immediate attention";
                } elseif ($value >= $thresholds['high'] || $value <= $thresholds['low']) {
                    $alertLevel = 'warning';
                    $alertMessage = "Blood sugar level of {$value} mg/dL is outside normal range";
                }
                break;
                
            case 'temperature':
                $value = (float)$value;
                if ($value >= $thresholds['high_emergency'] || $value <= $thresholds['low_emergency']) {
                    $alertLevel = 'critical';
                    $alertMessage = "Body temperature of {$value} °F is at a CRITICAL level and requires immediate attention";
                } elseif ($value >= $thresholds['high'] || $value <= $thresholds['low']) {
                    $alertLevel = 'warning';
                    $alertMessage = "Body temperature of {$value} °F is outside normal range";
                }
                break;
        }
        
        // If there's no alert needed, just return
        if ($alertLevel === 'normal') {
            return true;
        }
        
        // Get senior details
        $senior = fetchOne("SELECT name FROM seniorcitizens WHERE id = ?", [$seniorId], 'i');
        if (!$senior) {
            error_log("Senior not found for ID: $seniorId in createHealthAlert");
            return false;
        }
        
        // Get assigned caregivers
        $caregivers = fetchAll(
            "SELECT u.id 
             FROM userdetails u 
             JOIN senior_caregiver_assignments sca ON u.id = sca.caregiver_id
             WHERE sca.senior_id = ?",
            [$seniorId], 
            'i'
        );
        
        // Insert health alert into database
        $priority = $alertLevel === 'critical' ? 'high' : 'medium';
        $sql = "INSERT INTO health_alerts
                (senior_id, alert_type, value, priority, message, created_at, status)
                VALUES (?, ?, ?, ?, ?, NOW(), 'new')";

        $success = executeQuery(
            $sql,
            [$seniorId, $metricType, (string)$value, $priority, $alertMessage],
            'issss'
        );
        
        if (!$success) {
            error_log("Failed to insert health alert in database");
            return false;
        }
        
        // Get the inserted alert ID
        $alertId = $conn->insert_id;
        
        // Create notifications for caregivers
        foreach ($caregivers as $caregiver) {
            $notificationMessage = "HEALTH ALERT for {$senior['name']}: {$alertMessage}";
            $notificationType = ($alertLevel === 'critical') ? 'danger' : 'warning';
            
            createNotification(
                'Health Alert',
                $notificationMessage,
                (int)$caregiver['id'],
                $notificationType,
                $alertLevel === 'critical'
            );
        }
        
        // Log the alert creation
        logActivity('health_alert_created', "Created {$alertLevel} health alert for {$senior['name']}: {$alertMessage}");
        
        return true;
    } catch (Exception $e) {
        error_log("Error creating health alert: " . $e->getMessage());
        return false;
    }
}