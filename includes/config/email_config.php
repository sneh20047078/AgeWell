<?php
/**
 * Email Configuration for AgeWell Senior Care System
 * This file contains configuration settings for sending emails via SMTP (Gmail)
 */

// Import PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Make sure autoloader is included
require_once __DIR__ . '/../../vendor/autoload.php';

// Email SMTP configuration
define('EMAIL_USE_SMTP', getenv('AGEWELL_EMAIL_SMTP_ENABLED') === '1');
define('EMAIL_SMTP_HOST', 'smtp.gmail.com');
define('EMAIL_SMTP_PORT', 465); // Changed to SSL port
define('EMAIL_SMTP_SECURE', 'ssl'); // Changed to SSL
define('EMAIL_SMTP_AUTH', true);
define('EMAIL_SMTP_USERNAME', getenv('AGEWELL_EMAIL_SMTP_USERNAME') ?: '');
define('EMAIL_SMTP_PASSWORD', getenv('AGEWELL_EMAIL_SMTP_PASSWORD') ?: '');

// Fallback options if SMTP fails - DISABLED to avoid Outlook integration issues
define('EMAIL_USE_FALLBACK', false); // Disable fallback to PHP mail()

// Sender information
define('EMAIL_FROM_ADDRESS', getenv('AGEWELL_EMAIL_FROM_ADDRESS') ?: '');
define('EMAIL_FROM_NAME', getenv('AGEWELL_EMAIL_FROM_NAME') ?: 'AgeWell Senior Care');

// Debug and logging
define('EMAIL_DEBUG', 0); // Set to 2 for detailed debugging, 0 for production
define('EMAIL_LOG_ERRORS', true); // Log all email errors

/**
 * Get configured PHPMailer instance ready to use
 * 
 * @return PHPMailer\PHPMailer\PHPMailer
 */
function getConfiguredMailer() {
    // Create a new PHPMailer instance
    $mail = new PHPMailer(true);
    
    // Server settings
    if (EMAIL_USE_SMTP) {
        $mail->isSMTP();
        $mail->Host = EMAIL_SMTP_HOST;
        $mail->Port = EMAIL_SMTP_PORT;
        
        if (EMAIL_SMTP_SECURE) {
            $mail->SMTPSecure = EMAIL_SMTP_SECURE;
        }
        
        if (EMAIL_SMTP_AUTH) {
            $mail->SMTPAuth = true;
            $mail->Username = EMAIL_SMTP_USERNAME;
            $mail->Password = EMAIL_SMTP_PASSWORD;
        }
        
        // Add SSL options to help with connection issues
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
    }
    
    // Set default sender
    $mail->setFrom(EMAIL_FROM_ADDRESS, EMAIL_FROM_NAME);
    
    // Set debug level
    $mail->SMTPDebug = EMAIL_DEBUG;
    
    // Set charset
    $mail->CharSet = 'UTF-8';
    
    return $mail;
}

/**
 * Enhanced email sending function with fallback options
 * 
 * @param string $to Recipient email
 * @param string $subject Email subject
 * @param string $htmlBody HTML email body
 * @param string $textBody Plain text email body
 * @param array $options Additional options
 * @return array Result with status and message
 */
function sendEmailWithFallback($to, $subject, $htmlBody, $textBody = '', $options = []) {
    $result = [
        'success' => false,
        'message' => '',
        'method' => ''
    ];
    
    // Try SMTP first if enabled
    if (EMAIL_USE_SMTP) {
        try {
            $mail = getConfiguredMailer();
            
            // Add recipient
            $mail->addAddress($to);
            
            // Add CC recipients if provided
            if (!empty($options['cc'])) {
                foreach ((array)$options['cc'] as $cc) {
                    $mail->addCC($cc);
                }
            }
            
            // Add BCC recipients if provided
            if (!empty($options['bcc'])) {
                foreach ((array)$options['bcc'] as $bcc) {
                    $mail->addBCC($bcc);
                }
            }
            
            // Add reply-to if provided
            if (!empty($options['reply_to'])) {
                $mail->addReplyTo($options['reply_to'], $options['reply_to_name'] ?? '');
            }
            
            // Set content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = $textBody ?: strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody));
            
            // Send the email
            $mail->send();
            
            $result['success'] = true;
            $result['message'] = 'Email sent successfully via SMTP';
            $result['method'] = 'smtp';
            
            return $result;
            
        } catch (Exception $e) {
            $errorMessage = "SMTP Email Error: " . $mail->ErrorInfo . " - " . $e->getMessage();
            
            if (EMAIL_LOG_ERRORS) {
                error_log($errorMessage);
            }
            
            // If fallback is not enabled, throw the exception
            if (!EMAIL_USE_FALLBACK) {
                // Instead of throwing exception, return error in result
                $result['success'] = false;
                $result['message'] = $errorMessage;
                $result['method'] = 'smtp_failed';
                return $result;
            }
            
            // Otherwise, continue to fallback method
            $result['message'] = $errorMessage;
        }
    }
    
    // Fallback to PHP mail() if SMTP failed and fallback is enabled
    if (EMAIL_USE_FALLBACK && function_exists('mail')) {
        try {
            // Prepare headers
            $headers = 'From: ' . EMAIL_FROM_NAME . ' <' . EMAIL_FROM_ADDRESS . '>' . "\r\n";
            
            if (!empty($options['reply_to'])) {
                $headers .= 'Reply-To: ' . $options['reply_to'] . "\r\n";
            }
            
            if (!empty($options['cc'])) {
                $headers .= 'Cc: ' . (is_array($options['cc']) ? implode(',', $options['cc']) : $options['cc']) . "\r\n";
            }
            
            if (!empty($options['bcc'])) {
                $headers .= 'Bcc: ' . (is_array($options['bcc']) ? implode(',', $options['bcc']) : $options['bcc']) . "\r\n";
            }
            
            $headers .= 'MIME-Version: 1.0' . "\r\n";
            $headers .= 'Content-type: text/html; charset=UTF-8' . "\r\n";
            $headers .= 'X-Mailer: PHP/' . phpversion() . "\r\n";
            
            // Send mail
            $mailSuccess = mail($to, $subject, $htmlBody, $headers);
            
            if ($mailSuccess) {
                $result['success'] = true;
                $result['message'] = 'Email sent successfully via PHP mail() function';
                $result['method'] = 'mail';
            } else {
                $result['message'] = 'Failed to send email via PHP mail() function';
                
                if (EMAIL_LOG_ERRORS) {
                    error_log($result['message']);
                }
            }
            
        } catch (Exception $e) {
            $result['message'] = "PHP mail() Error: " . $e->getMessage();
            
            if (EMAIL_LOG_ERRORS) {
                error_log($result['message']);
            }
        }
    }
    
    return $result;
} 
