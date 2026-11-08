<?php
// Common functions for all admin pages
if (!function_exists('isLoggedIn') || !isLoggedIn() || !isAdmin()) {
    setAlert('danger', 'Unauthorized access.');
    redirect('login.php');
}

// Set default page title if not set
if (!isset($pageTitle)) {
    $pageTitle = 'Admin Panel';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - AgeWell Senior Care</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    
    <style>
        /* Core Layout */
        body {
            background: #23242a;
            color: #fff;
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 0;
        }

        .main-wrapper {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Styling */
        .sidebar {
            width: 250px;
            height: 100vh;
            background: #1c1c1c;
            position: fixed;
            left: 0;
            top: 56px; /* Match your navbar height */
            bottom: 0;
            border-right: 1px solid rgba(69, 243, 255, 0.1);
            z-index: 1000;
            overflow-y: auto;
            display: block !important;
        }

        /* Main Content Area */
        .main-content {
            flex: 1;
            margin-left: 250px;
            padding-top: 30px;
            min-height: calc(100vh - 56px);
        }

        /* Dashboard Container */
        .dashboard-container, .content-container {
            width: 100%;
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* Page Title */
        .page-title, .dashboard-title {
            color: #45f3ff;
            font-size: 2rem;
            margin-bottom: 20px;
            position: relative;
            display: inline-block;
        }

        .page-title::after, .dashboard-title::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 100%;
            height: 2px;
            background: linear-gradient(90deg, #45f3ff, transparent);
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: rgba(28, 28, 28, 0.8);
            border: 1px solid rgba(69, 243, 255, 0.1);
            border-radius: 10px;
            padding: 20px;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(69, 243, 255, 0.1);
        }

        .stat-title {
            color: #45f3ff;
            font-size: 0.9rem;
            text-transform: uppercase;
            margin-bottom: 10px;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 600;
            color: #fff;
        }

        .stat-icon {
            float: right;
            font-size: 2.5rem;
            color: rgba(69, 243, 255, 0.3);
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 15px;
            margin: 20px 0;
        }

        .action-button, .btn-primary, .btn-success, .btn-danger, .btn-info, .btn-warning {
            background: transparent;
            border: 1px solid #45f3ff;
            color: #45f3ff;
            padding: 8px 20px;
            border-radius: 5px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .action-button:hover, .btn-primary:hover {
            background: #45f3ff;
            color: #1c1c1c;
            transform: translateY(-2px);
        }

        .btn-danger {
            border-color: #ff4757;
            color: #ff4757;
        }

        .btn-danger:hover {
            background: #ff4757;
            color: #fff;
        }

        .btn-success {
            border-color: #28a745;
            color: #28a745;
        }

        .btn-success:hover {
            background: #28a745;
            color: #fff;
        }

        /* Cards */
        .info-card, .content-card, .card {
            background: rgba(28, 28, 28, 0.8);
            border: 1px solid rgba(69, 243, 255, 0.1);
            border-radius: 10px;
            margin-bottom: 20px;
            overflow: hidden;
        }

        .info-card-header, .content-card-header, .card-header {
            background: rgba(69, 243, 255, 0.1);
            padding: 15px 20px;
            border-bottom: 1px solid rgba(69, 243, 255, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .info-card-title, .content-card-title, .card-title {
            color: #45f3ff;
            margin: 0;
            font-size: 1.1rem;
        }

        .info-card-body, .content-card-body, .card-body {
            padding: 20px;
        }

        /* Table Styling */
        .table-responsive {
            margin: 0;
            padding: 0;
            width: 100%;
            background: rgba(28, 28, 28, 0.6);
            border-radius: 10px;
            overflow: hidden;
        }

        .table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            color: #fff;
            margin-bottom: 0;
        }

        .table th {
            background: rgba(69, 243, 255, 0.1);
            color: #45f3ff;
            font-weight: 500;
            padding: 15px;
            text-align: left;
            border: none;
        }

        .table td {
            padding: 15px;
            border-bottom: 1px solid rgba(69, 243, 255, 0.1);
            color: #fff;
        }

        .table tr:last-child td {
            border-bottom: none;
        }

        .table tbody tr:hover {
            background: rgba(69, 243, 255, 0.05);
        }

        /* Form Controls */
        .form-control, .form-select {
            background-color: rgba(28, 28, 28, 0.8);
            border: 1px solid rgba(69, 243, 255, 0.1);
            color: #fff;
            border-radius: 5px;
            padding: 10px 15px;
        }

        .form-control:focus, .form-select:focus {
            background-color: rgba(28, 28, 28, 0.9);
            border-color: #45f3ff;
            box-shadow: 0 0 0 0.25rem rgba(69, 243, 255, 0.25);
            color: #fff;
        }

        .form-label {
            color: #45f3ff;
            font-weight: 500;
        }

        /* Badge Styles */
        .badge {
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .badge-primary, .bg-primary { background: #45f3ff; color: #1c1c1c; }
        .badge-warning, .bg-warning { background: #ffd700; color: #1c1c1c; }
        .badge-danger, .bg-danger { background: #ff4757; color: #fff; }
        .badge-success, .bg-success { background: #28a745; color: #fff; }
        .badge-info, .bg-info { background: #17a2b8; color: #fff; }

        /* Navbar Styling */
        .navbar {
            background: rgba(28, 28, 28, 0.95) !important;
            border-bottom: 1px solid rgba(69, 243, 255, 0.1);
            backdrop-filter: blur(10px);
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1001;
            padding: 0.5rem 1rem;
        }

        /* Brand/Logo */
        .navbar-brand {
            color: #45f3ff !important;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .navbar-brand i {
            color: #45f3ff;
        }

        /* Navigation Links */
        .navbar .nav-link {
            color: #fff !important;
            padding: 0.5rem 1rem;
            transition: all 0.3s ease;
            position: relative;
        }

        .navbar .nav-link:hover,
        .navbar .nav-link.active {
            color: #45f3ff !important;
        }

        .navbar .nav-link::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            width: 0;
            height: 2px;
            background: #45f3ff;
            transition: all 0.3s ease;
            transform: translateX(-50%);
        }

        .navbar .nav-link:hover::after,
        .navbar .nav-link.active::after {
            width: 80%;
        }

        /* Footer Styling */
        .dashboard-footer {
            background: rgba(28, 28, 28, 0.95);
            backdrop-filter: blur(10px);
            border-top: 1px solid rgba(69, 243, 255, 0.1);
            color: #fff;
            padding: 15px 0;
            margin-top: 40px;
            width: 100%;
            text-align: center;
        }

        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .footer-copyright p {
            margin: 0;
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.7);
        }

        /* Alerts */
        .alert {
            background: rgba(69, 243, 255, 0.05);
            border: 1px solid rgba(69, 243, 255, 0.1);
            color: #fff;
        }

        .alert-success {
            background: rgba(40, 167, 69, 0.1);
            border-color: rgba(40, 167, 69, 0.3);
        }

        .alert-danger {
            background: rgba(255, 71, 87, 0.1);
            border-color: rgba(255, 71, 87, 0.3);
        }

        .alert-warning {
            background: rgba(255, 215, 0, 0.1);
            border-color: rgba(255, 215, 0, 0.3);
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
            
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="admin_dashboard.php">
                <i class="fas fa-heart"></i> AgeWell Senior Care
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link <?php echo $pageTitle == 'Admin Dashboard' ? 'active' : ''; ?>" href="admin_dashboard.php">Dashboard</a>
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
                <div class="d-flex">
                    <a href="export_pdf.php" class="action-button me-2">
                        <i class="fas fa-file-export"></i> Export PDF
                    </a>
                    <a href="logout.php" class="action-button" style="border-color: #ff4757; color: #ff4757;">
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
                <!-- Page specific content will be here -->
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Mobile Sidebar Toggle -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toggleSidebar = document.querySelector('.sidebar-toggle');
            if (toggleSidebar) {
                toggleSidebar.addEventListener('click', function() {
                    document.querySelector('.sidebar').classList.toggle('show');
                });
            }
        });
    </script>
</body>
</html> 