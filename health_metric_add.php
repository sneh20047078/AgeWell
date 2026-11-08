<?php
/**
 * AgeWell Senior Care and Support System
 * Add Health Metric Page
 */

// This page is essentially the same as medical_record_add.php since health metrics
// are stored in the healthrecords table. We'll redirect to medical_record_add.php
// to avoid code duplication.

// Include configuration and functions
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Get senior ID from query parameter if available
$seniorId = isset($_GET['senior_id']) ? (int)$_GET['senior_id'] : null;

// Redirect to medical_record_add.php
$url = 'medical_record_add.php';
if ($seniorId) {
    $url .= "?senior_id=$seniorId";
}

redirect($url);
?>
