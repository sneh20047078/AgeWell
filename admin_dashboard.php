<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/notification_handler.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    setAlert('danger', 'Unauthorized access.');
    redirect('login.php');
}

$pageTitle = 'Admin Dashboard';
$userId = $_SESSION['user_id'];

// Get admin-specific data
$pendingUsers = fetchAll("SELECT * FROM userdetails WHERE status = 'inactive' ORDER BY created_at DESC");
$pendingUsers = is_array($pendingUsers) ? $pendingUsers : [];

$totalSeniors = fetchOne("SELECT COUNT(*) as count FROM seniorcitizens");
$totalSeniors = $totalSeniors ? $totalSeniors['count'] : 0;

$totalCaregivers = fetchOne("SELECT COUNT(*) as count FROM userdetails WHERE role = 'caregiver'");
$totalCaregivers = $totalCaregivers ? $totalCaregivers['count'] : 0;

// Get unassigned seniors
$unassignedSeniors = fetchAll("SELECT s.* FROM seniorcitizens s
                      LEFT JOIN senior_caregiver_assignments sca ON s.id = sca.senior_id
                      WHERE sca.id IS NULL");

// Ensure $unassignedSeniors is an array
$unassignedSeniors = is_array($unassignedSeniors) ? $unassignedSeniors : [];

// Get recent registrations
$recentRegistrations = fetchAll("SELECT * FROM userdetails ORDER BY created_at DESC LIMIT 5");
$recentRegistrations = is_array($recentRegistrations) ? $recentRegistrations : [];

// Don't include the default header since we're using a custom one
// require_once 'includes/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - AgeWell Senior Care</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
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

        /* Stats cards */
        .stats-card {
            background: #1c1c1c;
            border-radius: 8px;
            padding: 20px;
            transition: all 0.3s;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            border: 1px solid rgba(69, 243, 255, 0.1);
        }

        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(69, 243, 255, 0.2);
        }

        .stats-card .title {
            color: #45f3ff;
            font-size: 14px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 10px;
        }

        .stats-card .value {
            font-size: 32px;
            font-weight: 600;
            color: #ffffff;
            margin-bottom: 15px;
        }

        .stats-card .icon {
            font-size: 28px;
            color: rgba(69, 243, 255, 0.7);
            margin-bottom: 10px;
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

        .alert-success {
            background-color: rgba(75, 219, 106, 0.1);
            border-color: rgba(75, 219, 106, 0.3);
            color: #4bdb6a;
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
        }

        .btn-danger {
            background: #ff3860;
            border-color: #ff3860;
        }

        /* Badge styling */
        .badge {
            padding: 5px 8px;
            border-radius: 4px;
            font-weight: 500;
        }

        .bg-warning {
            background-color: #ffd166 !important;
            color: #1c1c1c;
        }

        .bg-success {
            background-color: #4bdb6a !important;
            color: #1c1c1c;
        }

        .bg-danger {
            background-color: #ff3860 !important;
        }

        .bg-info {
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

        /* Glowing effects */
        @keyframes glow {
            0% {
                box-shadow: 0 0 5px rgba(69, 243, 255, 0.3);
            }
            50% {
                box-shadow: 0 0 15px rgba(69, 243, 255, 0.5);
            }
            100% {
                box-shadow: 0 0 5px rgba(69, 243, 255, 0.3);
            }
        }

        .glow-effect {
            animation: glow 2s infinite;
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
            <a href="dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="senior_citizens.php"><i class="fas fa-users"></i> Senior Citizens</a>
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
                <h1>Admin Dashboard</h1>
                <div class="quick-access">
                    <a href="senior_add.php" class="btn btn-outline-primary">
                        <i class="fas fa-user-plus"></i> Add Senior Citizen
                    </a>
                    <a href="view_seniors.php" class="btn btn-outline-primary">
                        <i class="fas fa-users"></i> View Seniors
                    </a>
                    <a href="caregiver_assignments.php" class="btn btn-outline-primary">
                        <i class="fas fa-user-nurse"></i> Manage Assignments
                    </a>
                </div>
            </div>

            <!-- Dashboard Stats -->
            <div class="row">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="stats-card glow-effect">
                        <div class="icon"><i class="fas fa-users"></i></div>
                        <div class="title">Total Seniors</div>
                        <div class="value"><?php echo $totalSeniors; ?></div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="stats-card glow-effect">
                        <div class="icon"><i class="fas fa-user-nurse"></i></div>
                        <div class="title">Active Caregivers</div>
                        <div class="value"><?php echo $totalCaregivers; ?></div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="stats-card glow-effect">
                        <div class="icon"><i class="fas fa-clock"></i></div>
                        <div class="title">Pending Approvals</div>
                        <div class="value"><?php echo count($pendingUsers); ?></div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="stats-card glow-effect">
                        <div class="icon"><i class="fas fa-user-minus"></i></div>
                        <div class="title">Unassigned Seniors</div>
                        <div class="value"><?php echo count($unassignedSeniors); ?></div>
                    </div>
                </div>
            </div>

            <!-- Pending Approvals Section -->
            <div class="card">
                <div class="card-header">
                    <h6>Pending Approvals</h6>
                    <span class="badge bg-warning"><?php echo count($pendingUsers); ?></span>
                </div>
                <div class="card-body">
                    <?php if (!empty($pendingUsers)): ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Registration Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pendingUsers as $user): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($user['name']); ?></td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td><span class="badge bg-info"><?php echo ucfirst(htmlspecialchars($user['role'])); ?></span></td>
                                            <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                            <td>
                                                <div class="action-buttons">
                                                    <a href="user_approve.php?id=<?php echo $user['id']; ?>" class="btn btn-success btn-sm">
                                                        <i class="fas fa-check"></i>
                                                    </a>
                                                    <a href="user_reject.php?id=<?php echo $user['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to reject this user?');">
                                                        <i class="fas fa-times"></i>
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
                            <i class="fas fa-info-circle me-2"></i> No pending approvals at this time.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Unassigned Seniors Section -->
            <div class="card">
                <div class="card-header">
                    <h6>Unassigned Seniors</h6>
                    <span class="badge bg-danger"><?php echo count($unassignedSeniors); ?></span>
                </div>
                <div class="card-body">
                    <?php if (!empty($unassignedSeniors)): ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Age</th>
                                        <th>Contact</th>
                                        <th>Emergency Contact</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($unassignedSeniors as $senior): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($senior['name']); ?></td>
                                            <td><?php echo htmlspecialchars($senior['age']); ?></td>
                                            <td><?php echo htmlspecialchars($senior['phone']); ?></td>
                                            <td><?php echo htmlspecialchars($senior['emergency_contact']); ?></td>
                                            <td>
                                                <a href="assign_caregiver.php?senior_id=<?php echo $senior['id']; ?>" 
                                                   class="btn btn-sm btn-primary">
                                                    <i class="fas fa-user-plus me-1"></i> Assign
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i> All seniors are currently assigned to caregivers.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Messages Section -->
            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-header">
                            <h6>Recent Contact Messages</h6>
                            <span class="badge bg-warning" id="unread-count"></span>
                        </div>
                        <div class="card-body">
                            <?php
                            $contactMessages = fetchAll(
                                "SELECT * FROM contact_messages ORDER BY created_at DESC LIMIT 5"
                            ) ?? [];
                            ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Subject</th>
                                            <th>Date</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($contactMessages)): ?>
                                            <?php foreach ($contactMessages as $message): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($message['name']); ?></td>
                                                    <td><?php echo htmlspecialchars($message['subject']); ?></td>
                                                    <td><?php echo date('M d, Y', strtotime($message['created_at'])); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo ($message['status'] ?? 'unread') === 'unread' ? 'warning' : 'success'; ?>">
                                                            <?php echo ucfirst($message['status'] ?? 'unread'); ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="4" class="text-center">No messages found</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="text-center mt-3">
                                <a href="contact_messages.php" class="btn btn-primary btn-sm">
                                    <i class="fas fa-envelope me-1"></i> View All Messages
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add any JavaScript for interactivity here
        document.addEventListener('DOMContentLoaded', function() {
            // Example: Count unread messages
            const unreadCount = document.querySelectorAll('.badge.bg-warning').length - 1;
            document.getElementById('unread-count').textContent = unreadCount > 0 ? unreadCount : '';
            
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