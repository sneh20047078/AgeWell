<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Check if user is logged in
if (!isLoggedIn()) {
    setAlert('danger', 'Please login to access this page.');
    redirect('login.php');
}

// Clear any unauthorized access messages that might be present
if (isset($_SESSION['alert_type']) && $_SESSION['alert_type'] === 'danger' && $_SESSION['alert_message'] === 'Unauthorized access.') {
    unset($_SESSION['alert_type'], $_SESSION['alert_message']);
}

// Initialize variables
$appointments = [];
$seniors = [];
$filterSeniorId = isset($_GET['senior_id']) ? (int)$_GET['senior_id'] : 0;
$filterStatus = isset($_GET['status']) ? $_GET['status'] : '';

// Get all seniors for filter dropdown
$seniors = fetchAll("SELECT id, name FROM seniorcitizens ORDER BY name");

// Build query based on filters
$params = [];
$whereClause = [];

if (isCaregiver()) {
    // Caregivers only see their assigned appointments
    $whereClause[] = "a.caregiver_id = ?";
    $params[] = $_SESSION['user_id'];
}

if ($filterSeniorId > 0) {
    $whereClause[] = "senior_id = ?";
    $params[] = $filterSeniorId;
}

if (!empty($filterStatus)) {
    $whereClause[] = "status = ?";
    $params[] = $filterStatus;
}

$where = !empty($whereClause) ? "WHERE " . implode(" AND ", $whereClause) : "";

// Fetch appointments with senior details using JOIN
$sql = "SELECT a.*, s.name as senior_name 
         FROM appointments a
         LEFT JOIN seniorcitizens s ON a.senior_id = s.id
         $where
         ORDER BY a.appointment_datetime DESC";

$appointments = fetchAll($sql, $params);

// Handle appointment status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['appointment_id']) && isset($_POST['status'])) {
    $appointmentId = (int)$_POST['appointment_id'];
    $status = $_POST['status'];
    
    // Validate status
    if (in_array($status, ['scheduled', 'completed', 'cancelled', 'no_show'])) {
        $updated = update('appointments', ['status' => $status], 'id = ?', [$appointmentId]);
        
        if ($updated) {
            // Get appointment details for log
            $appointment = fetchOne("SELECT a.*, s.name as senior_name 
                                     FROM appointments a 
                                     LEFT JOIN seniorcitizens s ON a.senior_id = s.id 
                                     WHERE a.id = ?", 
                                    [$appointmentId]);
            
            logActivity('appointment_status_update', "Updated appointment status to $status for {$appointment['senior_name']}");
            setAlert('success', 'Appointment status updated successfully.');
            redirect('appointments.php');
        } else {
            setAlert('danger', 'Failed to update appointment status.');
        }
    } else {
        setAlert('danger', 'Invalid status value.');
    }
}

$pageTitle = 'Appointments';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointments - AgeWell Senior Care</title>
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

        .header .nav-links a:hover,
        .header .nav-links a.active {
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

        .sidebar ul li a:hover,
        .sidebar ul li a.active {
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
            background: #1c1c1c !important;
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

        .table thead th {
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

        .table tbody td {
            padding: 12px 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        /* Form styling */
        .form-label {
            color: #8f8f8f;
            font-weight: 500;
            margin-bottom: 8px;
        }

        .form-control, .form-select {
            background-color: #1c1c1c;
            border: 1px solid rgba(69, 243, 255, 0.3);
            border-radius: 4px;
            color: #cdcdcd;
            padding: 10px 12px;
            transition: all 0.3s;
        }

        .form-control:focus, .form-select:focus {
            border-color: #45f3ff;
            box-shadow: 0 0 0 0.25rem rgba(69, 243, 255, 0.25);
            background-color: #1c1c1c;
            color: #ffffff;
        }

        .form-control::placeholder {
            color: #6c757d;
        }

        /* Status badges */
        .badge {
            padding: 5px 8px;
            border-radius: 4px;
            font-weight: 500;
        }

        .bg-scheduled, .badge-scheduled {
            background-color: rgba(0, 123, 255, 0.2) !important;
            color: #0d6efd;
            border: 1px solid rgba(0, 123, 255, 0.5);
        }

        .bg-completed, .badge-completed {
            background-color: rgba(40, 167, 69, 0.2) !important;
            color: #28a745;
            border: 1px solid rgba(40, 167, 69, 0.5);
        }

        .bg-cancelled, .badge-cancelled {
            background-color: rgba(220, 53, 69, 0.2) !important;
            color: #dc3545;
            border: 1px solid rgba(220, 53, 69, 0.5);
        }

        .bg-no_show, .badge-no_show {
            background-color: rgba(255, 193, 7, 0.2) !important;
            color: #ffc107;
            border: 1px solid rgba(255, 193, 7, 0.5);
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

        .btn-secondary {
            background: transparent;
            border-color: #45f3ff;
            color: #45f3ff;
        }

        .btn-secondary:hover {
            background: rgba(69, 243, 255, 0.1);
            color: #45f3ff;
        }

        .btn-info {
            background: rgba(69, 243, 255, 0.2);
            border: 1px solid #45f3ff;
            color: #45f3ff;
        }

        .btn-info:hover {
            background: rgba(69, 243, 255, 0.3);
            color: #ffffff;
        }

        /* Filter section */
        .filter-box {
            background: #1c1c1c;
            border: 1px solid rgba(69, 243, 255, 0.3);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .filter-box:hover {
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }

        .filter-box label {
            color: #8f8f8f;
            margin-bottom: 5px;
        }

        .filter-box select,
        .filter-box input {
            background: #28292d;
            border: 1px solid rgba(69, 243, 255, 0.3);
            border-radius: 4px;
            color: #cdcdcd;
            padding: 8px 12px;
        }

        .filter-box select:focus,
        .filter-box input:focus {
            border-color: #45f3ff;
            outline: none;
            box-shadow: 0 0 0 2px rgba(69, 243, 255, 0.2);
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

        /* Action buttons */
        .action-buttons {
            display: flex;
            gap: 5px;
        }

        .action-btn {
            padding: 5px 10px;
            font-size: 12px;
            margin-right: 5px;
        }

        .dropdown-menu-dark {
            background-color: #1c1c1c;
            border: 1px solid rgba(69, 243, 255, 0.3);
        }

        .dropdown-item {
            color: #cdcdcd;
        }

        .dropdown-item:hover, .dropdown-item:focus {
            background-color: rgba(69, 243, 255, 0.1);
            color: #45f3ff;
        }

        .dropdown-item.active {
            background-color: rgba(69, 243, 255, 0.2);
            color: #45f3ff;
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

        .text-info {
            color: #45f3ff !important;
            text-decoration: none;
        }

        .text-info:hover {
            text-decoration: underline;
        }

        .alert-info {
            background-color: rgba(69, 243, 255, 0.1);
            border-color: rgba(69, 243, 255, 0.3);
            color: #45f3ff;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                position: fixed;
                z-index: 999;
                left: -100%;
                transition: all 0.3s;
            }

            .main-content {
                width: 100%;
            }
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
            <a href="senior_citizens.php"><i class="fas fa-users"></i> Senior Citizens</a>
            <a href="appointments.php" class="active"><i class="fas fa-calendar-check"></i> Appointments</a>
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
                <li><a href="appointment_add.php"><i class="fas fa-plus-circle"></i> Add New Appointment</a></li>
            </ul>
            
            <h6>Quick Reports</h6>
            <ul>
                <li><a href="reports.php?type=seniors"><i class="fas fa-users"></i> Senior Citizens Report</a></li>
                <li><a href="reports.php?type=appointments" class="active"><i class="fas fa-calendar-check"></i> Appointments Report</a></li>
                <li><a href="reports.php?type=health_metrics"><i class="fas fa-chart-line"></i> Health Metrics Report</a></li>
            </ul>
            
            <?php if (isAdmin()): ?>
            <h6>Admin Actions</h6>
            <ul>
                <li><a href="user_add.php"><i class="fas fa-user-plus"></i> Add New User</a></li>
                <li><a href="users.php"><i class="fas fa-users-cog"></i> Manage Users</a></li>
                <li><a href="activity_logs.php"><i class="fas fa-history"></i> View Activity Logs</a></li>
            </ul>
            <?php endif; ?>
        </div>
        
        <!-- Main content -->
        <div class="main-content">
            <div class="page-title">
                <h1><i class="fas fa-calendar-check me-2"></i> Appointments</h1>
                
                <div class="quick-access">
                    <!-- Removed Add New Appointment button -->
                </div>
            </div>

            <!-- Display alerts -->
            <?php displayAlert(); ?>

            <!-- Filters -->
            <div class="filter-box">
                <form method="GET" action="appointments.php" class="row g-3">
                    <div class="col-md-4">
                        <label for="senior_id" class="form-label">Filter by Senior</label>
                        <select name="senior_id" id="senior_id" class="form-select">
                            <option value="0">All Seniors</option>
                            <?php foreach ($seniors as $senior): ?>
                                <option value="<?php echo $senior['id']; ?>" <?php echo $filterSeniorId == $senior['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($senior['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="status" class="form-label">Filter by Status</label>
                        <select name="status" id="status" class="form-select">
                            <option value="">All Statuses</option>
                            <option value="scheduled" <?php echo $filterStatus === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                            <option value="completed" <?php echo $filterStatus === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo $filterStatus === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            <option value="no_show" <?php echo $filterStatus === 'no_show' ? 'selected' : ''; ?>>No Show</option>
                        </select>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2">Filter</button>
                        <a href="appointments.php" class="btn btn-secondary">Reset</a>
                    </div>
                </form>
            </div>

            <!-- Appointments List -->
            <div class="card glow-effect">
                <div class="card-header">
                    <h6>Appointments List</h6>
                    <span class="badge bg-info"><?php echo count($appointments); ?></span>
                </div>
                <div class="card-body">
                    <?php if (empty($appointments)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i> No appointments found.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Senior Name</th>
                                        <th>Doctor</th>
                                        <th>Date & Time</th>
                                        <th>Purpose</th>
                                        <th>Location</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($appointments as $appointment): ?>
                                        <tr>
                                            <td>
                                                <a href="senior_view.php?id=<?php echo $appointment['senior_id']; ?>" class="text-info">
                                                    <?php echo htmlspecialchars($appointment['senior_name']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo htmlspecialchars($appointment['doctor_name']); ?></td>
                                            <td><?php echo date('M d, Y h:i A', strtotime($appointment['appointment_datetime'])); ?></td>
                                            <td><?php echo htmlspecialchars($appointment['purpose']); ?></td>
                                            <td><?php echo htmlspecialchars($appointment['location'] ?: 'N/A'); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $appointment['status']; ?>">
                                                    <?php echo ucfirst($appointment['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <div class="btn-group">
                                                        <button type="button" class="btn btn-sm btn-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                                            Status
                                                        </button>
                                                        <ul class="dropdown-menu dropdown-menu-dark">
                                                            <li>
                                                                <form method="POST" action="appointments.php">
                                                                    <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                                                    <input type="hidden" name="status" value="scheduled">
                                                                    <button type="submit" class="dropdown-item <?php echo $appointment['status'] === 'scheduled' ? 'active' : ''; ?>">Scheduled</button>
                                                                </form>
                                                            </li>
                                                            <li>
                                                                <form method="POST" action="appointments.php">
                                                                    <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                                                    <input type="hidden" name="status" value="completed">
                                                                    <button type="submit" class="dropdown-item <?php echo $appointment['status'] === 'completed' ? 'active' : ''; ?>">Completed</button>
                                                                </form>
                                                            </li>
                                                            <li>
                                                                <form method="POST" action="appointments.php">
                                                                    <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                                                    <input type="hidden" name="status" value="cancelled">
                                                                    <button type="submit" class="dropdown-item <?php echo $appointment['status'] === 'cancelled' ? 'active' : ''; ?>">Cancelled</button>
                                                                </form>
                                                            </li>
                                                            <li>
                                                                <form method="POST" action="appointments.php">
                                                                    <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                                                    <input type="hidden" name="status" value="no_show">
                                                                    <button type="submit" class="dropdown-item <?php echo $appointment['status'] === 'no_show' ? 'active' : ''; ?>">No Show</button>
                                                                </form>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                    <a href="appointment_edit.php?id=<?php echo $appointment['id']; ?>" class="btn btn-sm btn-info">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="appointment_view.php?id=<?php echo $appointment['id']; ?>" class="btn btn-sm btn-primary">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add active class to current page in sidebar
            const currentPage = window.location.pathname.split('/').pop();
            document.querySelectorAll('.sidebar a').forEach(link => {
                if (link.getAttribute('href').includes(currentPage)) {
                    link.classList.add('active');
                }
            });
        });
    </script>
</body>
</html> 