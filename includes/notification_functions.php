<?php
/**
 * Notification Functions for AgeWell Senior Care System
 */

require_once __DIR__ . '/config/twilio_config.php';
require_once __DIR__ . '/sms_functions.php';
require_once __DIR__ . '/db.php'; // Make sure this path is correct

if (!function_exists('getHealthMetricAlerts')) {
    function getHealthMetricAlerts($seniorId) {
        global $conn;
        
        if (!$conn) {
            error_log("Database connection not available in getHealthMetricAlerts");
            return [];
        }

        $alerts = [];
        
        try {
            // Blood Pressure Alert
            $stmt = $conn->prepare("SELECT * FROM healthrecords 
                                  WHERE senior_id = ? AND record_type = 'blood_pressure' 
                                  ORDER BY recorded_at DESC LIMIT 1");
            $stmt->bind_param('i', $seniorId);
            $stmt->execute();
            $result = $stmt->get_result();
            $latestBP = $result->fetch_assoc();
            $stmt->close();

            if ($latestBP) {
                $bpParts = explode('/', $latestBP['value']);
                $systolic = isset($bpParts[0]) ? (int)trim($bpParts[0]) : 0;
                $diastolic = isset($bpParts[1]) ? (int)trim($bpParts[1]) : 0;
                
                if ($systolic >= 140 || $diastolic >= 90) {
                    $alerts[] = [
                        'type' => 'warning',
                        'message' => 'High blood pressure detected: ' . $latestBP['value'] . ' mmHg',
                        'date' => $latestBP['recorded_at']
                    ];
                    
                    sendHealthMetricAlert($seniorId, 'blood_pressure', $latestBP['value'], '140/90');
                }
            }
            
            // Blood Sugar Alert
            $latestBS = fetchOne("SELECT * FROM healthrecords 
                                 WHERE senior_id = ? AND record_type = 'blood_sugar' 
                                 ORDER BY recorded_at DESC LIMIT 1", [$seniorId], 'i');
            if ($latestBS) {
                $bloodSugar = (int)$latestBS['value'];
                if ($bloodSugar > 200) {
                    $alerts[] = [
                        'type' => 'warning',
                        'message' => 'High blood sugar detected: ' . $bloodSugar . ' mg/dL',
                        'date' => $latestBS['recorded_at']
                    ];
                    
                    // Send SMS alert
                    sendHealthMetricAlert($seniorId, 'blood_sugar', $bloodSugar, '200');
                }
            }
            
            return $alerts;
        } catch (Exception $e) {
            error_log("Error in getHealthMetricAlerts: " . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('sendNotificationToSenior')) {
    function sendNotificationToSenior($seniorId, $type, $message) {
        // Get senior's phone number
        $sql = "SELECT phone, name FROM seniorcitizens WHERE id = ?";
        $senior = fetchOne($sql, [$seniorId], 'i');
        
        if (!$senior) {
            return false;
        }

        // Format phone number
        $seniorPhone = formatPhoneNumber($senior['phone']);
        
        // Send SMS
        $success = sendSMS($seniorPhone, $message);

        // Log notification
        logNotification($seniorId, $type, $message, $success ? 'sent' : 'failed');

        return $success;
    }
}

if (!function_exists('sendEmergencyNotification')) {
    function sendEmergencyNotification($seniorId, $type, $message) {
        // Get emergency contact details
        $sql = "SELECT emergency_contact, emergency_phone, name FROM seniorcitizens WHERE id = ?";
        $senior = fetchOne($sql, [$seniorId], 'i');
        
        if (!$senior) {
            return false;
        }

        // Format emergency contact phone number
        $emergencyPhone = formatPhoneNumber($senior['emergency_phone']);
        
        // Customize message for emergency contact
        $emergencyMessage = "ALERT for " . $senior['name'] . "'s Emergency Contact: " . $message;

        // Send SMS
        $success = sendSMS($emergencyPhone, $emergencyMessage);

        // Log notification
        logNotification($seniorId, $type . '_emergency', $emergencyMessage, $success ? 'sent' : 'failed');

        return $success;
    }
}

if (!function_exists('logNotification')) {
    function logNotification($seniorId, $type, $message, $status) {
        global $conn;
        
        // Check if notification_logs table exists
        $result = $conn->query("SHOW TABLES LIKE 'notification_logs'");
        if ($result->num_rows == 0) {
            // Table doesn't exist, create it
            $createTableSQL = "CREATE TABLE notification_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                senior_id INT NOT NULL,
                type VARCHAR(50) NOT NULL,
                message TEXT NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'pending',
                created_at DATETIME NOT NULL,
                FOREIGN KEY (senior_id) REFERENCES seniorcitizens(id) ON DELETE CASCADE
            )";
            
            if (!$conn->query($createTableSQL)) {
                error_log("Failed to create notification_logs table: " . $conn->error);
                return false;
            }
        }
        
        // Now insert the notification log
        $sql = "INSERT INTO notification_logs (senior_id, type, message, status, created_at) 
                VALUES (?, ?, ?, ?, NOW())";
        return execute($sql, [$seniorId, $type, $message, $status], 'isss');
    }
}

if (!function_exists('getUpcomingAppointmentNotifications')) {
    function getUpcomingAppointmentNotifications($seniorId) {
        $sql = "SELECT * FROM appointments 
                WHERE senior_id = ? 
                AND appointment_datetime > NOW() 
                AND appointment_datetime <= DATE_ADD(NOW(), INTERVAL 7 DAY)
                ORDER BY appointment_datetime ASC";
        return fetchAll($sql, [$seniorId], 'i');
    }
}

if (!function_exists('getMedicationReminders')) {
    function getMedicationReminders($seniorId) {
        $sql = "SELECT * FROM prescriptions 
                WHERE senior_id = ? 
                AND (end_date IS NULL OR end_date >= CURDATE())
                ORDER BY medication_name ASC";
        return fetchAll($sql, [$seniorId], 'i');
    }
}

if (!function_exists('generateHealthMetricMessage')) {
    function generateHealthMetricMessage($metricType, $value, $threshold) {
        switch ($metricType) {
            case 'blood_pressure':
                return "Health Alert: Your blood pressure reading of {$value} mmHg is above normal. Please consult your healthcare provider.";
            case 'blood_sugar':
                return "Health Alert: Your blood sugar level of {$value} mg/dL is above normal. Please check your insulin.";
            default:
                return "Health Alert: Your {$metricType} reading of {$value} requires attention.";
        }
    }
}

if (!function_exists('sendHealthMetricAlert')) {
    function sendHealthMetricAlert($seniorId, $metricType, $value, $threshold) {
        $message = generateHealthMetricMessage($metricType, $value, $threshold);
        
        // Debug mode - just return without sending actual SMS
        // Comment this out when you want to enable real SMS notifications
        return;
        
        // Send to senior
        sendNotificationToSenior($seniorId, 'health_metric', $message);
        
        // Send to emergency contact if value is critically high
        if (isMetricCritical($metricType, $value)) {
            sendEmergencyNotification($seniorId, 'health_metric', $message);
        }
    }
}

if (!function_exists('isMetricCritical')) {
    function isMetricCritical($metricType, $value) {
        switch ($metricType) {
            case 'blood_pressure':
                $parts = explode('/', $value);
                $systolic = (int)$parts[0];
                $diastolic = (int)$parts[1];
                return $systolic >= 180 || $diastolic >= 110;
                
            case 'blood_sugar':
                return (int)$value >= 300;
                
            default:
                return false;
        }
    }
}

if (!function_exists('sendAppointmentReminders')) {
    function sendAppointmentReminders() {
        global $conn;
        
        // Get appointments happening in the next 24 hours
        $sql = "SELECT a.*, s.name as senior_name, s.phone, s.emergency_contact, s.emergency_phone 
                FROM appointments a 
                JOIN seniorcitizens s ON a.senior_id = s.id 
                WHERE a.appointment_datetime BETWEEN NOW() 
                AND DATE_ADD(NOW(), INTERVAL 24 HOUR) 
                AND a.status = 'scheduled'";
                
        $result = $conn->query($sql);
        
        while ($appointment = $result->fetch_assoc()) {
            // Format appointment time
            $appointmentTime = new DateTime($appointment['appointment_datetime']);
            $formattedTime = $appointmentTime->format('F j, Y \a\t g:i A');
            
            // Prepare messages
            $seniorMessage = "Reminder: Hello {$appointment['senior_name']}, you have an appointment with "
                         . "{$appointment['doctor_name']} tomorrow at {$formattedTime}. "
                         . "Location: {$appointment['location']}";
            
            $emergencyMessage = "Reminder: {$appointment['senior_name']} has an appointment with "
                             . "{$appointment['doctor_name']} tomorrow at {$formattedTime}. "
                             . "Location: {$appointment['location']}";
            
            // Send to senior
            if ($appointment['phone']) {
                $seniorPhone = formatPhoneNumber($appointment['phone']);
                $success = sendSMS($seniorPhone, $seniorMessage);
                logNotification($appointment['senior_id'], 'appointment_reminder', $seniorMessage, 
                              $success ? 'sent' : 'failed');
            }
            
            // Send to emergency contact
            if ($appointment['emergency_phone']) {
                $emergencyPhone = formatPhoneNumber($appointment['emergency_phone']);
                $success = sendSMS($emergencyPhone, $emergencyMessage);
                logNotification($appointment['senior_id'], 'appointment_reminder_emergency', 
                              $emergencyMessage, $success ? 'sent' : 'failed');
            }
        }
    }
}

// Helper Functions
function formatPhoneNumber($phone) {
    // Remove non-numeric characters
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Add country code if not present (assuming US numbers)
    if (strlen($phone) === 10) {
        $phone = '+1' . $phone;
    } elseif (strlen($phone) > 10 && !str_starts_with($phone, '+')) {
        $phone = '+' . $phone;
    }
    
    return $phone;
} 