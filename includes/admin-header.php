<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle : 'Admin'; ?> - AgeWell Senior Care</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    
    <!-- Admin Styles -->
    <link rel="stylesheet" href="assets/css/admin-styles.css">
    
    <?php if (isset($extraCSS)): ?>
        <link rel="stylesheet" href="<?php echo $extraCSS; ?>">
    <?php endif; ?>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-heart"></i> AgeWell Senior Care
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link <?php echo $pageTitle == 'Admin Dashboard' ? 'active' : ''; ?>" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $pageTitle == 'Senior Citizens' ? 'active' : ''; ?>" href="senior_citizens.php">Senior Citizens</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $pageTitle == 'Appointments' ? 'active' : ''; ?>" href="appointments.php">Appointments</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $pageTitle == 'Health Metrics' ? 'active' : ''; ?>" href="health_metrics.php">Health Metrics</a>
                    </li>
                </ul>
                
                <div class="ms-auto d-flex">
                    <a href="export_pdf.php" class="btn-export">
                        <i class="fas fa-file-export"></i> Export PDF
                    </a>
                    <a href="logout.php" class="btn-logout">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>
    
    <div class="main-wrapper">
        <?php require_once 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <div class="content-container">
                <!-- Page content will be here --> 