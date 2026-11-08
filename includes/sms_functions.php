<?php
require_once __DIR__ . '/config/twilio_config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/ssl_fix.php';

// Add Composer autoloader
$autoloadPaths = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../vendor/autoload.php',
    __DIR__ . '/vendor/autoload.php'
];

$autoloaderFound = false;
foreach ($autoloadPaths as $autoloadPath) {
    if (file_exists($autoloadPath)) {
        require_once $autoloadPath;
        $autoloaderFound = true;
        break;
    }
}

if (!$autoloaderFound) {
    error_log("Twilio autoloader not found. Please run: composer require twilio/sdk");
}

// Set SSL certificate path
putenv('CURL_CA_BUNDLE=' . __DIR__ . '/../cacert.pem');

// Add detailed logging
function debugLog($message) {
    error_log("[SMS Debug] " . $message);
    // Also log to a specific file for easier debugging
    file_put_contents(__DIR__ . '/sms_debug.log', date('Y-m-d H:i:s') . " - " . $message . "\n", FILE_APPEND);
}

// Health Metric Thresholds
define('BP_HIGH_SYSTOLIC', 140);    // mmHg
define('BP_HIGH_DIASTOLIC', 90);    // mmHg
define('BP_CRITICAL_SYSTOLIC', 180); // mmHg
define('BP_CRITICAL_DIASTOLIC', 110); // mmHg

define('BLOOD_SUGAR_HIGH', 180);     // mg/dL
define('BLOOD_SUGAR_CRITICAL', 300); // mg/dL

define('HEART_RATE_LOW', 60);       // bpm
define('HEART_RATE_HIGH', 100);     // bpm
define('HEART_RATE_CRITICAL_LOW', 40); // bpm
define('HEART_RATE_CRITICAL_HIGH', 120); // bpm

define('TEMPERATURE_HIGH', 99.5);    // °F
define('TEMPERATURE_CRITICAL', 103); // °F

// Core SMS functionality
if (!function_exists('sendSMS')) {
    function sendSMS($to, $message) {
        debugLog("Starting SMS send process");
        debugLog("To: $to");
        debugLog("Message: $message");
        
        try {
            // Basic validation
            if (empty($to) || empty($message)) {
                debugLog("Error: Empty phone number or message");
                return false;
            }

            // Create HTTP client with SSL verification disabled
            $httpClient = new Twilio\Http\CurlClient([
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0
            ]);

            // Create Twilio client with custom HTTP client
            $client = new Twilio\Rest\Client(
                TWILIO_ACCOUNT_SID,
                TWILIO_AUTH_TOKEN,
                null,
                null,
                $httpClient
            );
            
            debugLog("Twilio client created successfully");

            // Send message
            $result = $client->messages->create(
                $to,
                [
                    'from' => TWILIO_PHONE_NUMBER,
                    'body' => $message
                ]
            );
            
            debugLog("Message sent successfully. SID: " . $result->sid);
            return true;
            
        } catch (Twilio\Exceptions\TwilioException $e) {
            debugLog("Twilio Error: " . $e->getMessage());
            debugLog("Error Code: " . $e->getCode());
            
            // Handle trial account limitations
            if ($e->getCode() == 21608) {
                // Unverified number in trial account
                debugLog("TRIAL ACCOUNT LIMIT: Number $to is not verified. Please verify this number in your Twilio console.");
                error_log("TWILIO NOTICE: To send SMS to $to, verify this number at https://console.twilio.com/phone-numbers/verified");
            }
            
            return false;
        } catch (Exception $e) {
            debugLog("General Error: " . $e->getMessage());
            debugLog("Error trace: " . $e->getTraceAsString());
            return false;
        }
    }
}

if (!function_exists('formatPhoneNumber')) {
    function formatPhoneNumber($phone) {
        // Remove any non-numeric characters except +
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // If number already starts with +, return as is
        if (substr($phone, 0, 1) === '+') {
            return $phone;
        }
        
        // Add country code if not present (assuming Indian numbers)
        if (strlen($phone) === 10) {
            return '+91' . $phone;
        }
        
        // If number starts with 91, add +
        if (substr($phone, 0, 2) === '91') {
            return '+' . $phone;
        }
        
        // Otherwise, just add + at the start
        return '+' . $phone;
    }
}

if (!function_exists('logNotification')) {
    function logNotification($seniorId, $type, $message, $status) {
        global $conn;
        
        if (!$conn) {
            error_log("Database connection not available in logNotification");
            return false;
        }
        
        try {
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
                
                debugLog("Created notification_logs table");
            }
            
            $stmt = $conn->prepare("INSERT INTO notification_logs (senior_id, type, message, status, created_at) 
                                  VALUES (?, ?, ?, ?, NOW())");
            if (!$stmt) {
                error_log("Failed to prepare statement: " . $conn->error);
                return false;
            }
            
            $stmt->bind_param('isss', $seniorId, $type, $message, $status);
            $result = $stmt->execute();
            $stmt->close();
            return $result;
        } catch (Exception $e) {
            error_log("Error logging notification: " . $e->getMessage());
            return false;
        }
    }
}

function sendNotificationToSenior($seniorId, $type, $message) {
    global $conn;
    
    // Get senior's phone number
    $sql = "SELECT phone, emergency_contact, emergency_phone, name 
            FROM seniorcitizens 
            WHERE id = ?";
    $senior = fetchOne($sql, [$seniorId], 'i');
    
    if (!$senior) {
        return false;
    }

    // Format phone numbers (remove any spaces, dashes, etc.)
    $seniorPhone = preg_replace('/[^0-9]/', '', $senior['phone']);
    $emergencyPhone = preg_replace('/[^0-9]/', '', $senior['emergency_phone']);

    // Add country code if not present (assuming US numbers)
    $seniorPhone = '+1' . $seniorPhone;
    $emergencyPhone = '+1' . $emergencyPhone;

    // Send to senior
    $success = sendSMS($seniorPhone, $message);

    // Log the notification
    logNotification($seniorId, $type, $message, $success ? 'sent' : 'failed');

    return $success;
}

function sendEmergencyNotification($seniorId, $type, $message) {
    global $conn;
    
    // Get emergency contact details
    $sql = "SELECT emergency_contact, emergency_phone, name 
            FROM seniorcitizens 
            WHERE id = ?";
    $senior = fetchOne($sql, [$seniorId], 'i');
    
    if (!$senior) {
        return false;
    }

    // Format emergency contact phone number
    $emergencyPhone = preg_replace('/[^0-9]/', '', $senior['emergency_phone']);
    $emergencyPhone = '+1' . $emergencyPhone;

    // Customize message for emergency contact
    $emergencyMessage = "ALERT for " . $senior['name'] . "'s Emergency Contact: " . $message;

    // Send to emergency contact
    $success = sendSMS($emergencyPhone, $emergencyMessage);

    // Log the emergency notification
    logNotification($seniorId, $type . '_emergency', $emergencyMessage, $success ? 'sent' : 'failed');

    return $success;
}

// Function to check if health metric exceeds thresholds
function checkHealthMetricThresholds($metricType, $value) {
    debugLog("Checking thresholds for $metricType: $value");
    
    switch ($metricType) {
        case 'blood_pressure':
            $parts = explode('/', $value);
            if (count($parts) !== 2) return ['exceeded' => false, 'critical' => false];
            
            $systolic = (int)$parts[0];
            $diastolic = (int)$parts[1];
            
            $exceeded = $systolic >= BP_HIGH_SYSTOLIC || $diastolic >= BP_HIGH_DIASTOLIC;
            $critical = $systolic >= BP_CRITICAL_SYSTOLIC || $diastolic >= BP_CRITICAL_DIASTOLIC;
            
            return [
                'exceeded' => $exceeded,
                'critical' => $critical,
                'message' => $critical ? 
                    "URGENT: Blood pressure is critically high at $value mmHg" :
                    ($exceeded ? "Alert: Blood pressure is elevated at $value mmHg" : null)
            ];
            
        case 'blood_sugar':
            $value = (int)$value;
            $exceeded = $value >= BLOOD_SUGAR_HIGH;
            $critical = $value >= BLOOD_SUGAR_CRITICAL;
            
            return [
                'exceeded' => $exceeded,
                'critical' => $critical,
                'message' => $critical ?
                    "URGENT: Blood sugar is critically high at $value mg/dL" :
                    ($exceeded ? "Alert: Blood sugar is elevated at $value mg/dL" : null)
            ];
            
        case 'heart_rate':
            $value = (int)$value;
            $exceeded = $value <= HEART_RATE_LOW || $value >= HEART_RATE_HIGH;
            $critical = $value <= HEART_RATE_CRITICAL_LOW || $value >= HEART_RATE_CRITICAL_HIGH;
            
            $message = '';
            if ($critical) {
                $message = $value <= HEART_RATE_CRITICAL_LOW ? 
                    "URGENT: Heart rate is critically low at $value bpm" :
                    "URGENT: Heart rate is critically high at $value bpm";
            } elseif ($exceeded) {
                $message = $value <= HEART_RATE_LOW ?
                    "Alert: Heart rate is low at $value bpm" :
                    "Alert: Heart rate is high at $value bpm";
            }
            
            return [
                'exceeded' => $exceeded,
                'critical' => $critical,
                'message' => $message
            ];
            
        case 'temperature':
            $value = (float)$value;
            $exceeded = $value >= TEMPERATURE_HIGH;
            $critical = $value >= TEMPERATURE_CRITICAL;
            
            return [
                'exceeded' => $exceeded,
                'critical' => $critical,
                'message' => $critical ?
                    "URGENT: Temperature is critically high at $value °F" :
                    ($exceeded ? "Alert: Temperature is elevated at $value °F" : null)
            ];
            
        default:
            return ['exceeded' => false, 'critical' => false, 'message' => null];
    }
}

// Function to send health metric alerts
function sendHealthMetricAlert($seniorId, $metricType, $value) {
    debugLog("Processing health metric alert - Type: $metricType, Value: $value, Senior ID: $seniorId");
    
    // Check thresholds
    $thresholdCheck = checkHealthMetricThresholds($metricType, $value);
    debugLog("Threshold check result: " . json_encode($thresholdCheck));
    
    if (!$thresholdCheck['exceeded']) {
        debugLog("Metric within normal range - no alert needed");
        return true;
    }
    
    // Get senior's details
    global $conn;
    $sql = "SELECT name, phone, emergency_contact, emergency_phone 
            FROM seniorcitizens 
            WHERE id = ?";
    $senior = fetchOne($sql, [$seniorId], 'i');
    
    if (!$senior) {
        debugLog("Senior not found for ID: $seniorId");
        return false;
    }
    
    debugLog("Senior details found: " . json_encode($senior));
    
    // Prepare messages
    $seniorMessage = "Hello {$senior['name']}, " . $thresholdCheck['message'] . 
                    ". Please take appropriate action and contact your healthcare provider if needed.";
    
    $emergencyMessage = "Alert for {$senior['name']}'s Emergency Contact ({$senior['emergency_contact']}): " . 
                       $thresholdCheck['message'] . ". Please check on them.";
    
    // Send notifications
    $success = false;
    
    // Send to senior
    if ($senior['phone']) {
        $seniorPhone = formatPhoneNumber($senior['phone']);
        debugLog("Sending alert to senior: $seniorPhone");
        $success = sendSMS($seniorPhone, $seniorMessage);
        logNotification($seniorId, 'health_metric', $seniorMessage, $success ? 'sent' : 'failed');
        debugLog("Senior notification " . ($success ? "sent successfully" : "failed"));
    } else {
        debugLog("No phone number found for senior");
    }
    
    // Send to emergency contact if critical
    if ($thresholdCheck['critical'] && $senior['emergency_phone']) {
        $emergencyPhone = formatPhoneNumber($senior['emergency_phone']);
        debugLog("Sending critical alert to emergency contact: $emergencyPhone");
        $emergencySuccess = sendSMS($emergencyPhone, $emergencyMessage);
        logNotification($seniorId, 'health_metric_emergency', $emergencyMessage, $emergencySuccess ? 'sent' : 'failed');
        debugLog("Emergency contact notification " . ($emergencySuccess ? "sent successfully" : "failed"));
        $success = $success || $emergencySuccess;
    } else if ($thresholdCheck['critical']) {
        debugLog("No emergency contact phone number found");
    }
    
    return $success;
}

// Function to send appointment reminders
function sendAppointmentReminder($seniorId, $appointment) {
    $message = generateAppointmentMessage($appointment);
    sendNotificationToSenior($seniorId, 'appointment', $message);
}

// Function to send medication reminders
function sendMedicationReminder($seniorId, $prescription) {
    $message = generateMedicationMessage($prescription);
    sendNotificationToSenior($seniorId, 'medication', $message);
}

// Helper functions
function generateHealthMetricMessage($metricType, $value, $threshold) {
    switch ($metricType) {
        case 'blood_pressure':
            return "Health Alert: Your blood pressure reading of {$value} mmHg is above normal. Please consult your healthcare provider.";
        case 'blood_sugar':
            return "Health Alert: Your blood sugar level of {$value} mg/dL is above normal. Please check your insulin.";
        case 'heart_rate':
            return "Health Alert: Your heart rate of {$value} bpm requires attention. Please rest and monitor.";
        default:
            return "Health Alert: Your {$metricType} reading of {$value} requires attention.";
    }
}

function generateAppointmentMessage($appointment) {
    $dateTime = date('l, F j \a\t g:i A', strtotime($appointment['appointment_datetime']));
    return "Reminder: You have an appointment with Dr. {$appointment['doctor_name']} on {$dateTime} for {$appointment['purpose']}.";
}

function generateMedicationMessage($prescription) {
    return "Medication Reminder: Time to take {$prescription['medication_name']} ({$prescription['dosage']}). {$prescription['instructions']}";
}

function isMetricCritical($metricType, $value) {
    switch ($metricType) {
        case 'blood_pressure':
            $parts = explode('/', $value);
            $systolic = (int)$parts[0];
            $diastolic = (int)$parts[1];
            return $systolic >= 180 || $diastolic >= 110;
            
        case 'blood_sugar':
            return (int)$value >= 300;
            
        case 'heart_rate':
            return (int)$value >= 120 || (int)$value <= 40;
            
        default:
            return false;
    }
}

if (!function_exists('sendAppointmentNotification')) {
    function sendAppointmentNotification($seniorId, $appointmentDetails) {
        global $conn;
        debugLog("Starting appointment notification for senior ID: $seniorId");
        
        // Get senior's details
        $stmt = $conn->prepare("SELECT name, phone, emergency_contact, emergency_phone 
                              FROM seniorcitizens 
                              WHERE id = ?");
        $stmt->bind_param('i', $seniorId);
        $stmt->execute();
        $result = $stmt->get_result();
        $senior = $result->fetch_assoc();
        $stmt->close();
        
        if (!$senior) {
            debugLog("Senior not found for ID: $seniorId");
            return false;
        }
        
        debugLog("Senior details found: " . json_encode($senior));
        
        // Format appointment date and time
        $appointmentDateTime = new DateTime($appointmentDetails['appointment_datetime']);
        $formattedDateTime = $appointmentDateTime->format('F j, Y \a\t g:i A');
        
        // Message for senior
        $seniorMessage = "Hello {$senior['name']}, your appointment with {$appointmentDetails['doctor_name']} "
                      . "is scheduled for $formattedDateTime at {$appointmentDetails['location']}. "
                      . "Purpose: {$appointmentDetails['purpose']}";
        
        // Message for emergency contact
        $emergencyMessage = "Hello {$senior['emergency_contact']}, this is a notification that {$senior['name']} "
                         . "has an appointment with {$appointmentDetails['doctor_name']} scheduled for "
                         . "$formattedDateTime at {$appointmentDetails['location']}.";
        
        debugLog("Messages prepared");
        
        // Send to senior
        $seniorSuccess = false;
        if ($senior['phone']) {
            $seniorPhone = formatPhoneNumber($senior['phone']);
            debugLog("Sending to senior's phone: $seniorPhone");
            $seniorSuccess = sendSMS($seniorPhone, $seniorMessage);
            logNotification($seniorId, 'appointment', $seniorMessage, $seniorSuccess ? 'sent' : 'failed');
        } else {
            debugLog("No phone number found for senior");
        }
        
        // Send to emergency contact
        $emergencySuccess = false;
        if ($senior['emergency_phone']) {
            $emergencyPhone = formatPhoneNumber($senior['emergency_phone']);
            debugLog("Sending to emergency contact's phone: $emergencyPhone");
            $emergencySuccess = sendSMS($emergencyPhone, $emergencyMessage);
            logNotification($seniorId, 'appointment_emergency', $emergencyMessage, $emergencySuccess ? 'sent' : 'failed');
        } else {
            debugLog("No emergency contact phone number found");
        }
        
        return $seniorSuccess || $emergencySuccess;
    }
} 