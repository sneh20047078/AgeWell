<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    setAlert('danger', 'Please login to access this page.');
    redirect('login.php');
}

// Set page title
$pageTitle = 'Health Records';

// Get filters and pagination parameters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Get total records for pagination
$totalResult = fetchOne("SELECT COUNT(*) as total FROM healthrecords");
$totalRecords = $totalResult ? $totalResult['total'] : 0;
$totalPages = ceil($totalRecords / $perPage);

// Get health records with pagination
$records = [];
if ($totalRecords > 0) {
    $sql = "SELECT h.*, s.name as senior_name, u.name as caregiver_name
            FROM healthrecords h
            JOIN seniorcitizens s ON h.senior_id = s.id 
            JOIN userdetails u ON h.caregiver_id = u.id 
            ORDER BY h.recorded_at DESC 
            LIMIT ?, ?";

    $records = fetchAll($sql, [$offset, $perPage], 'ii');
}

// Include header
require_once 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-3 col-lg-2">
            <?php require_once 'includes/sidebar.php'; ?>
        </div>
        
        <!-- Main Content -->
        <div class="col-md-9 col-lg-10">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="fas fa-heartbeat me-2"></i> Health Records</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="health_record_add.php" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus me-1"></i> Add Health Record
                    </a>
                </div>
            </div>

            <?php if (empty($records)): ?>
                <div class="alert alert-info">
                    <p>No health records found.</p>
                    <p><a href="health_record_add.php" class="alert-link">Add a new health record</a> to get started.</p>
                </div>
            <?php endif; ?>
            
            <!-- Rest of your display code -->
        </div>
    </div>
</div>