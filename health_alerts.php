<?php
/**
 * AgeWell Senior Care and Support System
 * Health Alerts Page
 */

// Include configuration and required files
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    setAlert('danger', 'Please login to access this page.');
    redirect('login.php');
}

// Initialize variables
$alerts = [];
$filterSeniorId = isset($_GET['senior_id']) ? (int)$_GET['senior_id'] : 0;
$filterStatus = isset($_GET['status']) ? $_GET['status'] : '';
$filterPriority = isset($_GET['priority']) ? $_GET['priority'] : '';

// Get all seniors for filter dropdown
$seniors = fetchAll("SELECT id, name FROM seniorcitizens ORDER BY name");

// Build query based on filters
$params = [];
$whereClause = [];

if (isCaregiver()) {
    // Caregivers only see their assigned seniors' alerts
    $whereClause[] = "ha.senior_id IN (
        SELECT senior_id FROM senior_caregiver_assignments WHERE caregiver_id = ?
    )";
    $params[] = $_SESSION['user_id'];
}

if ($filterSeniorId > 0) {
    $whereClause[] = "ha.senior_id = ?";
    $params[] = $filterSeniorId;
}

if (!empty($filterStatus)) {
    $whereClause[] = "ha.status = ?";
    $params[] = $filterStatus;
}

if (!empty($filterPriority)) {
    $whereClause[] = "ha.priority = ?";
    $params[] = $filterPriority;
}

$where = !empty($whereClause) ? "WHERE " . implode(" AND ", $whereClause) : "";

// Fetch health alerts with senior details
$sql = "SELECT ha.*, s.name as senior_name 
         FROM health_alerts ha
         LEFT JOIN seniorcitizens s ON ha.senior_id = s.id
         $where
         ORDER BY ha.created_at DESC";

$alerts = fetchAll($sql, $params) ?: [];

// Handle alert status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['alert_id']) && isset($_POST['status'])) {
    $alertId = (int)$_POST['alert_id'];
    $status = $_POST['status'];
    
    // Validate status
    if (in_array($status, ['new', 'acknowledged', 'resolved'])) {
        $where = 'id = ?';
        $whereParams = [$alertId];
        if (isCaregiver()) {
            $where .= ' AND senior_id IN (
                SELECT senior_id FROM senior_caregiver_assignments WHERE caregiver_id = ?
            )';
            $whereParams[] = $_SESSION['user_id'];
        }
        $updated = update('health_alerts', ['status' => $status], $where, $whereParams);
        
        if ($updated) {
            // Log action
            logActivity('health_alert_update', "Updated health alert #$alertId status to $status");
            setAlert('success', 'Health alert status updated successfully.');
            redirect('health_alerts.php');
        } else {
            setAlert('danger', 'Failed to update health alert status.');
        }
    } else {
        setAlert('danger', 'Invalid status value.');
    }
}

$pageTitle = 'Health Alerts';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Health Alerts - AgeWell Senior Care</title>
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

        /* Status badges */
        .badge {
            padding: 5px 8px;
            border-radius: 4px;
            font-weight: 500;
        }

        .badge-new {
            background-color: rgba(0, 123, 255, 0.2) !important;
            color: #0d6efd;
            border: 1px solid rgba(0, 123, 255, 0.5);
        }

        .badge-acknowledged {
            background-color: rgba(255, 193, 7, 0.2) !important;
            color: #ffc107;
            border: 1px solid rgba(255, 193, 7, 0.5);
        }

        .badge-resolved {
            background-color: rgba(40, 167, 69, 0.2) !important;
            color: #28a745;
            border: 1px solid rgba(40, 167, 69, 0.5);
        }

        /* Priority badges */
        .badge-low {
            background-color: rgba(23, 162, 184, 0.2) !important;
            color: #17a2b8;
            border: 1px solid rgba(23, 162, 184, 0.5);
        }

        .badge-medium {
            background-color: rgba(255, 193, 7, 0.2) !important;
            color: #ffc107;
            border: 1px solid rgba(255, 193, 7, 0.5);
        }

        .badge-high {
            background-color: rgba(220, 53, 69, 0.2) !important;
            color: #dc3545;
            border: 1px solid rgba(220, 53, 69, 0.5);
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
            <a href="appointments.php"><i class="fas fa-calendar-check"></i> Appointments</a>
            <a href="health_metrics.php" class="active"><i class="fas fa-heartbeat"></i> Health Metrics</a>
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
                <li><a href="medical_record_add.php"><i class="fas fa-file-medical-alt"></i> Add Medical Record</a></li>
            </ul>
            
            <h6>Health Management</h6>
            <ul>
                <li><a href="health_metrics.php"><i class="fas fa-heartbeat"></i> Health Metrics</a></li>
                <li><a href="medical_records.php"><i class="fas fa-file-medical"></i> Medical Records</a></li>
                <li><a href="health_alerts.php" class="active"><i class="fas fa-bell"></i> Health Alerts</a></li>
            </ul>
            
            <h6>Quick Reports</h6>
            <ul>
                <li><a href="reports.php?type=seniors"><i class="fas fa-users"></i> Senior Citizens Report</a></li>
                <li><a href="reports.php?type=appointments"><i class="fas fa-calendar-check"></i> Appointments Report</a></li>
                <li><a href="reports.php?type=health_metrics"><i class="fas fa-chart-line"></i> Health Metrics Report</a></li>
            </ul>
        </div>
        
        <!-- Main content -->
        <div class="main-content">
            <div class="page-title">
                <h1><i class="fas fa-bell me-2"></i> Health Alerts</h1>
            </div>

            <!-- Display alerts -->
            <?php if (isset($_SESSION['alert_type']) && isset($_SESSION['alert_message'])): ?>
                <div class="alert alert-<?php echo $_SESSION['alert_type']; ?> alert-dismissible fade show" role="alert">
                    <i class="fas <?php echo $_SESSION['alert_type'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> me-2"></i>
                    <?php echo $_SESSION['alert_message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['alert_type'], $_SESSION['alert_message']); ?>
            <?php endif; ?>

            <!-- Filters -->
            <div class="filter-box">
                <form method="GET" action="health_alerts.php" class="row g-3">
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
                    <div class="col-md-3">
                        <label for="status" class="form-label">Filter by Status</label>
                        <select name="status" id="status" class="form-select">
                            <option value="">All Statuses</option>
                            <option value="new" <?php echo $filterStatus === 'new' ? 'selected' : ''; ?>>New</option>
                            <option value="acknowledged" <?php echo $filterStatus === 'acknowledged' ? 'selected' : ''; ?>>Acknowledged</option>
                            <option value="resolved" <?php echo $filterStatus === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="priority" class="form-label">Filter by Priority</label>
                        <select name="priority" id="priority" class="form-select">
                            <option value="">All Priorities</option>
                            <option value="low" <?php echo $filterPriority === 'low' ? 'selected' : ''; ?>>Low</option>
                            <option value="medium" <?php echo $filterPriority === 'medium' ? 'selected' : ''; ?>>Medium</option>
                            <option value="high" <?php echo $filterPriority === 'high' ? 'selected' : ''; ?>>High</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2">Filter</button>
                        <a href="health_alerts.php" class="btn btn-outline-primary">Reset</a>
                    </div>
                </form>
            </div>

            <!-- Alerts List -->
            <div class="card">
                <div class="card-header">
                    <h6>Health Alerts</h6>
                    <span class="badge bg-info"><?php echo count($alerts); ?></span>
                </div>
                <div class="card-body">
                    <?php if (empty($alerts)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i> No health alerts found.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Senior Name</th>
                                        <th>Alert Type</th>
                                        <th>Value</th>
                                        <th>Priority</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($alerts as $alert): ?>
                                        <tr>
                                            <td><?php echo $alert['id']; ?></td>
                                            <td>
                                                <a href="senior_view.php?id=<?php echo $alert['senior_id']; ?>" class="text-info">
                                                    <?php echo htmlspecialchars($alert['senior_name']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $alert['alert_type']))); ?></td>
                                            <td><?php echo htmlspecialchars($alert['value']); ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo $alert['priority']; ?>">
                                                    <?php echo ucfirst($alert['priority']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php echo $alert['status']; ?>">
                                                    <?php echo ucfirst($alert['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M d, Y h:i A', strtotime($alert['created_at'])); ?></td>
                                            <td>
                                                <div class="btn-group">
                                                    <button type="button" class="btn btn-sm btn-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                                        Status
                                                    </button>
                                                    <ul class="dropdown-menu dropdown-menu-dark">
                                                        <li>
                                                            <form method="POST" action="health_alerts.php">
                                                                <input type="hidden" name="alert_id" value="<?php echo $alert['id']; ?>">
                                                                <input type="hidden" name="status" value="new">
                                                                <button type="submit" class="dropdown-item <?php echo $alert['status'] === 'new' ? 'active' : ''; ?>">New</button>
                                                            </form>
                                                        </li>
                                                        <li>
                                                            <form method="POST" action="health_alerts.php">
                                                                <input type="hidden" name="alert_id" value="<?php echo $alert['id']; ?>">
                                                                <input type="hidden" name="status" value="acknowledged">
                                                                <button type="submit" class="dropdown-item <?php echo $alert['status'] === 'acknowledged' ? 'active' : ''; ?>">Acknowledged</button>
                                                            </form>
                                                        </li>
                                                        <li>
                                                            <form method="POST" action="health_alerts.php">
                                                                <input type="hidden" name="alert_id" value="<?php echo $alert['id']; ?>">
                                                                <input type="hidden" name="status" value="resolved">
                                                                <button type="submit" class="dropdown-item <?php echo $alert['status'] === 'resolved' ? 'active' : ''; ?>">Resolved</button>
                                                            </form>
                                                        </li>
                                                    </ul>
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