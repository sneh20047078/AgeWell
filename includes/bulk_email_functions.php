<?php
/**
 * Bulk Email Functions for AgeWell Senior Care System
 * This file contains functions for sending common/bulk emails to multiple recipients
 */

// Include required files
require_once __DIR__ . '/config/email_config.php';
require_once __DIR__ . '/functions.php';

/**
 * Send a common email to multiple recipients
 * 
 * @param array $recipients Array of email addresses or associative array with 'email' and 'name' keys
 * @param string $subject Email subject
 * @param string $htmlBody HTML email body
 * @param string $textBody Plain text email body (optional)
 * @param array $options Additional options (attachments, etc.)
 * @return array Result array with success status and details
 */
function sendBulkEmail($recipients, $subject, $htmlBody, $textBody = '', $options = []) {
    // Initialize result array
    $result = [
        'success' => false,
        'message' => '',
        'total_recipients' => count($recipients),
        'sent_count' => 0,
        'failed_count' => 0,
        'failed_recipients' => []
    ];
    
    if (empty($recipients)) {
        $result['message'] = 'No recipients specified';
        return $result;
    }
    
    try {
        // Get configured mailer
        $mail = getConfiguredMailer();
        
        // Set content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->AltBody = $textBody ?: strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody));
        
        // Add attachments if provided
        if (!empty($options['attachments'])) {
            foreach ($options['attachments'] as $attachment) {
                if (isset($attachment['path'])) {
                    $name = isset($attachment['name']) ? $attachment['name'] : basename($attachment['path']);
                    $mail->addAttachment($attachment['path'], $name);
                }
            }
        }
        
        // BCC method (all recipients hidden from each other)
        if (!empty($options['use_bcc']) && $options['use_bcc'] === true) {
            // Add a dummy recipient to To field
            $mail->addAddress(EMAIL_FROM_ADDRESS, EMAIL_FROM_NAME);
            
            // Add all recipients as BCC
            foreach ($recipients as $recipient) {
                if (is_array($recipient)) {
                    $email = $recipient['email'];
                    $name = $recipient['name'] ?? '';
                } else {
                    $email = $recipient;
                    $name = '';
                }
                
                try {
                    $mail->addBCC($email, $name);
                    $result['sent_count']++;
                } catch (Exception $e) {
                    $result['failed_count']++;
                    $result['failed_recipients'][] = $email;
                    if (EMAIL_LOG_ERRORS) {
                        error_log("Failed to add BCC recipient $email: " . $e->getMessage());
                    }
                }
            }
            
            // Send the email once to all BCC recipients
            $mail->send();
            $result['success'] = true;
            $result['message'] = 'Bulk email sent successfully using BCC method';
            
        } else {
            // Individual emails method (separate email to each recipient)
            foreach ($recipients as $recipient) {
                // Clear previous recipients
                $mail->clearAddresses();
                $mail->clearCCs();
                $mail->clearBCCs();
                
                if (is_array($recipient)) {
                    $email = $recipient['email'];
                    $name = $recipient['name'] ?? '';
                } else {
                    $email = $recipient;
                    $name = '';
                }
                
                try {
                    $mail->addAddress($email, $name);
                    $mail->send();
                    $result['sent_count']++;
                } catch (Exception $e) {
                    $result['failed_count']++;
                    $result['failed_recipients'][] = $email;
                    if (EMAIL_LOG_ERRORS) {
                        error_log("Failed to send email to $email: " . $e->getMessage());
                    }
                }
            }
            
            $result['success'] = ($result['sent_count'] > 0);
            $result['message'] = "Bulk email processed: {$result['sent_count']} sent, {$result['failed_count']} failed";
        }
        
    } catch (Exception $e) {
        $result['message'] = "Error sending bulk email: " . $e->getMessage();
        if (EMAIL_LOG_ERRORS) {
            error_log($result['message']);
        }
    }
    
    return $result;
}

/**
 * Send a common email to clients in specific categories
 * 
 * @param array $filters Filters to select clients (age, care_type, etc.)
 * @param string $subject Email subject
 * @param string $htmlBody HTML email body
 * @param array $options Additional options
 * @return array Result with success status and details
 */
function sendEmailToFilteredClients($filters, $subject, $htmlBody, $options = []) {
    global $conn;
    
    // Start building the query to get client emails
    $sql = "SELECT id, first_name, last_name, email FROM clients WHERE email IS NOT NULL AND email != ''";
    $params = [];
    $types = "";
    
    // Add filters
    if (!empty($filters['care_type'])) {
        $sql .= " AND care_type = ?";
        $params[] = $filters['care_type'];
        $types .= "s";
    }
    
    if (!empty($filters['min_age'])) {
        $sql .= " AND TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) >= ?";
        $params[] = $filters['min_age'];
        $types .= "i";
    }
    
    if (!empty($filters['max_age'])) {
        $sql .= " AND TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) <= ?";
        $params[] = $filters['max_age'];
        $types .= "i";
    }
    
    if (!empty($filters['status'])) {
        $sql .= " AND status = ?";
        $params[] = $filters['status'];
        $types .= "s";
    }
    
    if (!empty($filters['location_id'])) {
        $sql .= " AND location_id = ?";
        $params[] = $filters['location_id'];
        $types .= "i";
    }
    
    // Execute the query
    $clients = [];
    if (empty($params)) {
        $clients = fetchAll($sql);
    } else {
        $clients = fetchAll($sql, $params, $types);
    }
    
    if (empty($clients)) {
        return [
            'success' => false,
            'message' => 'No clients match the specified filters',
            'total_recipients' => 0,
            'sent_count' => 0,
            'failed_count' => 0
        ];
    }
    
    // Format recipient list with names
    $recipients = [];
    foreach ($clients as $client) {
        $recipients[] = [
            'email' => $client['email'],
            'name' => $client['first_name'] . ' ' . $client['last_name']
        ];
    }
    
    // Send the bulk email
    $result = sendBulkEmail($recipients, $subject, $htmlBody, '', $options);
    
    // Log the bulk email action
    $admin_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
    $log_sql = "INSERT INTO activity_log (user_id, action, details, created_at) VALUES (?, ?, ?, NOW())";
    $log_params = [
        $admin_id,
        'bulk_email_sent',
        json_encode([
            'subject' => $subject,
            'recipients' => count($recipients),
            'filters' => $filters,
            'success' => $result['success'],
            'sent_count' => $result['sent_count'],
            'failed_count' => $result['failed_count']
        ])
    ];
    query($log_sql, $log_params, "iss");
    
    return $result;
}

/**
 * Get list of predefined email templates
 * 
 * @return array List of email templates
 */
function getEmailTemplates() {
    return [
        [
            'id' => 'welcome',
            'name' => 'Welcome to AgeWell',
            'subject' => 'Welcome to AgeWell Senior Care',
            'body' => '<p>Dear {client_name},</p>
                <p>Welcome to AgeWell Senior Care! We\'re delighted to have you join our community.</p>
                <p>At AgeWell, we are committed to providing the highest quality care and support to help you live your best life. Our team of dedicated professionals is here to assist you with all your needs.</p>
                <p>If you have any questions or concerns, please don\'t hesitate to contact us.</p>
                <p>Best regards,<br>The AgeWell Team</p>'
        ],
        [
            'id' => 'monthly_newsletter',
            'name' => 'Monthly Newsletter',
            'subject' => 'AgeWell Senior Care - Monthly Newsletter',
            'body' => '<p>Dear {client_name},</p>
                <p>We hope this newsletter finds you well. Here are some updates and activities happening at AgeWell this month:</p>
                <ul>
                    <li>Upcoming community events</li>
                    <li>Health tips for seniors</li>
                    <li>New services available</li>
                    <li>Staff spotlights</li>
                </ul>
                <p>We look forward to seeing you at our upcoming events!</p>
                <p>Warm regards,<br>The AgeWell Team</p>'
        ],
        [
            'id' => 'appointment_reminder',
            'name' => 'Appointment Reminder',
            'subject' => 'Reminder: Your Upcoming Appointment with AgeWell',
            'body' => '<p>Dear {client_name},</p>
                <p>This is a friendly reminder of your upcoming appointment with AgeWell Senior Care.</p>
                <p>If you need to reschedule or have any questions, please contact us at your earliest convenience.</p>
                <p>Thank you,<br>AgeWell Senior Care Team</p>'
        ],
        [
            'id' => 'holiday_greetings',
            'name' => 'Holiday Greetings',
            'subject' => 'Season\'s Greetings from AgeWell Senior Care',
            'body' => '<p>Dear {client_name},</p>
                <p>The team at AgeWell Senior Care would like to wish you and your loved ones a wonderful holiday season filled with joy, peace, and good health.</p>
                <p>Please note our holiday hours and emergency contact information:</p>
                <p>[Holiday Hours]</p>
                <p>Warm wishes,<br>The AgeWell Team</p>'
        ]
    ];
}

/**
 * Replace template variables with actual values
 * 
 * @param string $template The template text with variables
 * @param array $data The data to replace variables with
 * @return string The processed template
 */
function processEmailTemplate($template, $data) {
    foreach ($data as $key => $value) {
        $template = str_replace('{' . $key . '}', $value, $template);
    }
    return $template;
} 