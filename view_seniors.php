<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    setAlert('danger', 'Unauthorized access.');
    redirect('login.php');
}

$pageTitle = 'View Senior Citizens';

// Get all seniors with basic information and caregiver names
$seniors = fetchAll("
    SELECT s.*,
           COALESCE(
               (SELECT GROUP_CONCAT(DISTINCT u.name ORDER BY u.name SEPARATOR ', ')
                FROM senior_caregiver_assignments sca
                JOIN userdetails u ON sca.caregiver_id = u.id
                WHERE sca.senior_id = s.id),
               'Unassigned'
           ) as caregiver_name
    FROM seniorcitizens s
    ORDER BY s.name ASC
");

// Error checking
if ($seniors === false) {
    error_log("Database error: Unable to fetch seniors");
} elseif (empty($seniors)) {
    error_log("No records found in the seniorcitizens table");
}

// Ensure $seniors is an array even if the query fails
$seniors = is_array($seniors) ? $seniors : [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Senior Citizens - AgeWell Senior Care</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background: #23242a;
            color: #cdcdcd;
            min-height: 100vh;
        }

        /* Header styling */
        .header {
            background: #1c1c1c;
            border-bottom: 1px solid #45f3ff;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 20px;
            position: sticky;
            top: 0;
            z-index: 1030;
        }

        .header .logo {
            color: #45f3ff;
            font-weight: 500;
            display: flex;
            align-items: center;
        }

        .header .logo i {
            margin-right: 8px;
        }

        .header .nav-links {
            display: flex;
        }

        .header .nav-links a {
            color: #8f8f8f;
            text-decoration: none;
            padding: 0 15px;
            transition: color 0.3s;
        }

        .header .nav-links a:hover {
            color: #45f3ff;
        }

        .header .user-menu {
            display: flex;
            align-items: center;
        }

        .header .user-menu a {
            color: #8f8f8f;
            text-decoration: none;
            margin-left: 15px;
            transition: color 0.3s;
        }

        .header .user-menu a:hover {
            color: #45f3ff;
        }

        /* Main container */
        .main-container {
            display: flex;
            height: calc(100vh - 60px);
        }

        /* Sidebar */
        .sidebar {
            width: 250px;
            background: #1c1c1c;
            border-right: 1px solid rgba(69, 243, 255, 0.2);
            padding: 20px 0;
            overflow-y: auto;
        }

        .sidebar h6 {
            color: #45f3ff;
            padding: 15px 20px 10px;
            font-size: 14px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .sidebar ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .sidebar ul li a {
            display: flex;
            align-items: center;
            color: #8f8f8f;
            text-decoration: none;
            padding: 10px 20px;
            transition: all 0.3s;
        }

        .sidebar ul li a:hover {
            background: rgba(69, 243, 255, 0.1);
            color: #45f3ff;
        }

        .sidebar ul li a i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        /* Main content */
        .main-content {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
        }

        .page-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(69, 243, 255, 0.3);
        }

        .page-title h1 {
            color: #45f3ff;
            font-size: 28px;
            font-weight: 500;
        }

        /* Card styling */
        .card {
            background: #28292d;
            border: 1px solid rgba(69, 243, 255, 0.3);
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            margin-bottom: 20px;
            overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.3);
            border-color: rgba(69, 243, 255, 0.6);
        }

        .card-header {
            background: #1c1c1c;
            border-bottom: 1px solid rgba(69, 243, 255, 0.3);
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-header h6 {
            color: #45f3ff;
            margin: 0;
            font-weight: 500;
            font-size: 16px;
        }

        .card-body {
            padding: 20px;
        }

        /* Table styling */
        .table {
            color: #cdcdcd;
            background: transparent;
            border-collapse: separate;
            border-spacing: 0;
        }

        .table thead tr th {
            color: #45f3ff;
            font-weight: 500;
            border-bottom: 1px solid rgba(69, 243, 255, 0.3);
            padding: 12px 15px;
        }

        .table tbody tr {
            transition: background-color 0.3s;
        }

        .table tbody tr:hover {
            background-color: rgba(69, 243, 255, 0.05);
        }

        .table tbody tr td {
            border-top: none;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding: 12px 15px;
        }

        /* Alert styling */
        .alert {
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
        }

        .alert-info {
            background-color: rgba(69, 243, 255, 0.1);
            border-color: rgba(69, 243, 255, 0.3);
            color: #45f3ff;
        }

        /* Button styling */
        .btn {
            padding: 8px 16px;
            border-radius: 4px;
            transition: all 0.3s;
            font-weight: 500;
        }

        .btn-primary {
            background: #45f3ff;
            border-color: #45f3ff;
            color: #1c1c1c;
        }

        .btn-primary:hover {
            background: #28d7e5;
            border-color: #28d7e5;
            color: #1c1c1c;
        }

        .btn-outline-primary {
            background: transparent;
            border-color: #45f3ff;
            color: #45f3ff;
        }

        .btn-outline-primary:hover {
            background: rgba(69, 243, 255, 0.1);
            color: #45f3ff;
        }

        .btn-success {
            background: #4bdb6a;
            border-color: #4bdb6a;
            color: #1c1c1c;
        }

        .btn-success:hover {
            background: #3cb058;
            border-color: #3cb058;
            color: #1c1c1c;
        }

        .btn-info {
            background: #45f3ff;
            border-color: #45f3ff;
            color: #1c1c1c;
        }

        .btn-info:hover {
            background: #28d7e5;
            border-color: #28d7e5;
            color: #1c1c1c;
        }

        /* Badge styling */
        .badge {
            padding: 5px 8px;
            border-radius: 4px;
            font-weight: 500;
        }

        .bg-primary {
            background-color: #45f3ff !important;
            color: #1c1c1c;
        }

        /* Action buttons */
        .action-buttons {
            display: flex;
            gap: 5px;
        }

        .action-buttons .btn {
            padding: 5px 10px;
            font-size: 12px;
        }

        /* Quick access buttons */
        .quick-access {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .quick-access .btn {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 12px;
            font-weight: 500;
        }

        .quick-access .btn i {
            margin-right: 8px;
        }

        /* DataTables styling */
        .dataTables_wrapper .dataTables_length, 
        .dataTables_wrapper .dataTables_filter, 
        .dataTables_wrapper .dataTables_info, 
        .dataTables_wrapper .dataTables_processing, 
        .dataTables_wrapper .dataTables_paginate {
            color: #cdcdcd;
            margin-bottom: 15px;
        }

        .dataTables_wrapper .dataTables_length select,
        .dataTables_wrapper .dataTables_filter input {
            background-color: #1c1c1c;
            border: 1px solid rgba(69, 243, 255, 0.3);
            color: #cdcdcd;
            border-radius: 4px;
            padding: 5px 10px;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="logo">
            <i class="fas fa-heartbeat"></i>
            <span>AgeWell Senior Care</span>
        </div>
        <div class="nav-links">
            <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="senior_citizens.php" class="active"><i class="fas fa-users"></i> Senior Citizens</a>
            <a href="appointments.php"><i class="fas fa-calendar-check"></i> Appointments</a>
            <a href="health_metrics.php"><i class="fas fa-heartbeat"></i> Health Metrics</a>
        </div>
        <div class="user-menu">
            <a href="profile.php"><i class="fas fa-user-circle"></i> Profile</a>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <!-- Main container -->
    <div class="main-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <h6>Quick Actions</h6>
            <ul>
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="senior_add.php"><i class="fas fa-user-plus"></i> Add New Senior</a></li>
            </ul>
            
            <h6>Quick Reports</h6>
            <ul>
                <li><a href="reports.php?type=seniors"><i class="fas fa-users"></i> Senior Citizens Report</a></li>
                <li><a href="reports.php?type=appointments"><i class="fas fa-calendar-check"></i> Appointments Report</a></li>
                <li><a href="reports.php?type=health_metrics"><i class="fas fa-chart-line"></i> Health Metrics Report</a></li>
            </ul>
            
            <h6>Admin Actions</h6>
            <ul>
                <li><a href="user_add.php"><i class="fas fa-user-plus"></i> Add New User</a></li>
                <li><a href="users.php"><i class="fas fa-users-cog"></i> Manage Users</a></li>
                <li><a href="activity_logs.php"><i class="fas fa-history"></i> View Activity Logs</a></li>
            </ul>
        </div>
        
        <!-- Main content -->
        <div class="main-content">
            <div class="page-title">
                <h1>Senior Citizens List</h1>
                <div class="quick-access">
                    <a href="admin_dashboard.php" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                    <a href="senior_add.php" class="btn btn-outline-primary">
                        <i class="fas fa-plus"></i> Add New Senior
                    </a>
                    <a href="caregiver_assignments.php" class="btn btn-outline-primary">
                        <i class="fas fa-user-nurse"></i> Manage Assignments
                    </a>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h6>Senior Citizens</h6>
                    <span class="badge bg-primary"><?php echo count($seniors); ?></span>
                </div>
                <div class="card-body">
                    <?php if (!empty($seniors)): ?>
                        <div class="table-responsive">
                            <table class="table" id="seniorsTable">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Age</th>
                                        <th>Contact</th>
                                        <th>Assigned Caregiver</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($seniors as $senior): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($senior['name']); ?></td>
                                            <td><?php echo htmlspecialchars($senior['age']); ?></td>
                                            <td><?php echo htmlspecialchars($senior['phone']); ?></td>
                                            <td>
                                                <?php if ($senior['caregiver_name'] === 'Unassigned'): ?>
                                                    <span class="badge bg-secondary">Unassigned</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success"><?php echo htmlspecialchars($senior['caregiver_name']); ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <a href="senior_view.php?id=<?php echo $senior['id']; ?>" 
                                                       class="btn btn-info" title="View">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="senior_edit.php?id=<?php echo $senior['id']; ?>" 
                                                       class="btn btn-primary" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="senior_report.php?id=<?php echo $senior['id']; ?>" 
                                                       class="btn btn-success" title="Report">
                                                        <i class="fas fa-file-medical"></i>
                                                    </a>
                                                    <a href="caregiver_assignments.php?senior_id=<?php echo $senior['id']; ?>" 
                                                       class="btn btn-warning" title="Assign Caregiver">
                                                        <i class="fas fa-user-nurse"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i> No senior citizens found.
                        </div>
                    <?php endif; ?>
            </div>
        </div>
    </div>
</div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function() {
    $('#seniorsTable').DataTable({
        "order": [[0, "asc"]],
                "pageLength": 10,
                "language": {
                    "search": "_INPUT_",
                    "searchPlaceholder": "Search seniors...",
                    "lengthMenu": "Show _MENU_ seniors",
                    "info": "Showing _START_ to _END_ of _TOTAL_ seniors",
                    "infoEmpty": "No seniors found",
                    "infoFiltered": "(filtered from _MAX_ total seniors)"
                },
                "initComplete": function () {
                    // Style the DataTables elements after initialization
                    $('.dataTables_wrapper .dataTables_filter input').css({
                        'background-color': '#1c1c1c',
                        'border': '1px solid rgba(69, 243, 255, 0.3)',
                        'color': '#cdcdcd',
                        'border-radius': '4px',
                        'padding': '5px 10px'
                    });
                    
                    $('.dataTables_wrapper .dataTables_length select').css({
                        'background-color': '#1c1c1c',
                        'border': '1px solid rgba(69, 243, 255, 0.3)',
                        'color': '#cdcdcd',
                        'border-radius': '4px',
                        'padding': '5px 10px'
                    });
                }
            });
            
            // Add active class to current page in sidebar
            const currentPage = window.location.pathname.split('/').pop();
            document.querySelectorAll('.sidebar a').forEach(link => {
                if (link.getAttribute('href') === currentPage) {
                    link.classList.add('active');
                }
    });
});
</script>
</body>
</html>