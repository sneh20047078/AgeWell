<?php
/**
 * AgeWell Senior Care and Support System
 * Logout Page
 */

// Include configuration and required files
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Log out the user
logoutUser();

// Set alert and redirect to login page
setAlert('success', 'You have been successfully logged out.');
redirect('login.php');
?>
