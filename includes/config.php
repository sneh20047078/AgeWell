<?php
/**
 * Configuration settings for the AgeWell Senior Care and Support System
 */

// Ensure no output before session_start
ob_start();
session_start();

// Error reporting settings (turn off in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Database configuration
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'agewell');
define('DB_PORT', '3306');
define('DB_TYPE', 'mysql');        // Changed to MySQL

// Site configuration
define('SITE_NAME', 'AgeWell Senior Care');

// Define site URL based on environment
if (PHP_SAPI === 'cli') {
    // When running from command line (CLI)
    define('SITE_URL', 'http://localhost/AW/SCS');
} else {
    // When running in a web server
    define('SITE_URL', 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/AW/SCS');
}

// User roles
define('ROLE_ADMIN', 'admin');
define('ROLE_CAREGIVER', 'caregiver');

// Timezone setting
date_default_timezone_set('UTC');

// Default pagination limit
define('DEFAULT_PAGINATION_LIMIT', 10);

// Admin configuration
define('ADMIN_EMAIL', 'admin@agewell.care');

// Include email configuration
require_once __DIR__ . '/config/email_config.php';

// Session configuration
