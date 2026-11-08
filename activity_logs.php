<?php
/**
 * AgeWell Senior Care and Support System
 * Activity Logs Page
 */

// Include configuration and required files
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Check if user is logged in and has admin privileges
if (!isLoggedIn()) {
    setAlert('danger', 'Please login to access this page.');
    redirect('login.php');
} elseif (!isAdmin()) {
    setAlert('danger', 'You do not have permission to access this page.');
    redirect('dashboard.php');
}

// Set page title
$pageTitle = 'Activity Logs';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : DEFAULT_PAGINATION_LIMIT;
$offset = ($page - 1) * $perPage;

// Filters
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
$action = isset($_GET['action']) ? cleanInput($_GET['action']) : null;
$from_date = isset($_GET['from_date']) ? cleanInput($_GET['from_date']) : null;
$to_date = isset($_GET['to_date']) ? cleanInput($_GET['to_date']) : null;
$search = isset($_GET['search']) ? cleanInput($_GET['search']) : '';

// Build query conditions
$whereConditions = [];
$params = [];
$types = '';

if ($user_id) {
    $whereConditions[] = 'a.user_id = ?';
    $params[] = $user_id;
    $types .= 'i';
}

if ($action) {
    $whereConditions[] = 'a.action = ?';
    $params[] = $action;
    $types .= 's';
}

if ($from_date) {
    $whereConditions[] = 'DATE(a.created_at) >= ?';
    $params[] = $from_date;
    $types .= 's';
}

if ($to_date) {
    $whereConditions[] = 'DATE(a.created_at) <= ?';
    $params[] = $to_date;
    $types .= 's';
}

if (!empty($search)) {
    $whereConditions[] = '(a.action LIKE ? OR a.details LIKE ? OR u.name LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= 'sss';
}

// Combine conditions
$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get total number of logs for pagination
$countSql = "SELECT COUNT(*) as total FROM activitylogs a 
             LEFT JOIN userdetails u ON a.user_id = u.id 
             $whereClause";
$countResult = fetchOne($countSql, $params, $types);
$totalLogs = $countResult ? $countResult['total'] : 0;
$totalPages = ceil($totalLogs / $perPage);

// Get activity logs data with proper parameter handling
$sql = "SELECT a.*, u.name as user_name 
        FROM activitylogs a 
        LEFT JOIN userdetails u ON a.user_id = u.id 
        $whereClause 
        ORDER BY a.created_at DESC 
        LIMIT $offset, $perPage";

$queryParams = $params;
$queryTypes = $types;

$logs = fetchAll($sql, $queryParams, $queryTypes) ?: [];

// Get users for filter dropdown
$users = fetchAll("SELECT id, name FROM userdetails ORDER BY name ASC");

// Get unique action types for filter dropdown
$actions = fetchAll("SELECT DISTINCT action FROM activitylogs ORDER BY action ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs - AgeWell Senior Care</title>
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

        .header .nav-links a {
            color: #8f8f8f;
            text-decoration: none;
            padding: 0 15px;
            transition: color 0.3s;
        }

        .header .nav-links a:hover {
            color: #45f3ff;
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

        .main-container {
            display: flex;
            height: calc(100vh - 60px);
        }

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

        .table tbody tr:hover {
            background-color: rgba(69, 243, 255, 0.05);
        }

        .table tbody tr td {
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding: 12px 15px;
        }

        .alert-info {
            background-color: rgba(69, 243, 255, 0.1);
            border-color: rgba(69, 243, 255, 0.3);
            color: #45f3ff;
            border-radius: 8px;
            padding: 15px;
        }

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
            background: #8f8f8f;
            border-color: #8f8f8f;
            color: #1c1c1c;
        }

        .btn-secondary:hover {
            background: #6f6f6f;
            border-color: #6f6f6f;
        }

        .badge {
            padding: 5px 8px;
            border-radius: 4px;
            font-weight: 500;
        }

        .bg-success { background-color: #4bdb6a !important; color: #1c1c1c; }
        .bg-primary { background-color: #45f3ff !important; color: #1c1c1c; }
        .bg-danger { background-color: #ff3860 !important; color: #fff; }
        .bg-info { background-color: #45f3ff !important; color: #1c1c1c; }
        .bg-secondary { background-color: #8f8f8f !important; color: #1c1c1c; }

        .quick-access .btn {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 12px;
        }

        .quick-access .btn i {
            margin-right: 8px;
        }

        @keyframes glow {
            0% { box-shadow: 0 0 5px rgba(69, 243, 255, 0.3); }
            50% { box-shadow: 0 0 15px rgba(69, 243, 255, 0.5); }
            100% { box-shadow: 0 0 5px rgba(69, 243, 255, 0.3); }
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
            <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
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
                <h1><i class="fas fa-history me-2"></i> Activity Logs</h1>
                <div class="quick-access">
                    <a href="reports.php?type=activities" class="btn btn-outline-primary">
                        <i class="fas fa-file-export me-1"></i> Generate Report
                    </a>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="card">
                <div class="card-header">
                    <h6>Filter Logs</h6>
                </div>
                <div class="card-body">
                    <form method="GET" action="activity_logs.php" class="row g-3">
                        <div class="col-md-4">
                            <div class="input-group">
                                <input type="text" class="form-control" id="search" name="search" 
                                       placeholder="Search by action, details, or user" 
                                       value="<?php echo htmlspecialchars($search); ?>">
                                <button class="btn btn-primary" type="submit">
                                    <i class="fas fa-search"></i> Search
                                </button>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <select class="form-select" id="user_id" name="user_id">
                                <option value="">All Users</option>
                                <?php foreach ($users as $userOption): ?>
                                    <option value="<?php echo $userOption['id']; ?>" 
                                            <?php echo $user_id == $userOption['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($userOption['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select class="form-select" id="action" name="action">
                                <option value="">All Actions</option>
                                <?php foreach ($actions as $actionOption): ?>
                                    <option value="<?php echo $actionOption['action']; ?>" 
                                            <?php echo $action === $actionOption['action'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($actionOption['action']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <input type="date" class="form-control" id="from_date" name="from_date" 
                                   value="<?php echo $from_date; ?>">
                        </div>
                        <div class="col-md-2">
                            <a href="activity_logs.php" class="btn btn-secondary w-100">
                                    <i class="fas fa-times me-1"></i> Clear
                                </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Activity Logs List -->
            <div class="card">
                <div class="card-header">
                    <h6>Activity Logs</h6>
                    <span class="badge bg-info"><?php echo $totalLogs; ?></span>
                </div>
                <div class="card-body">
                    <?php if ($logs && count($logs) > 0): ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th width="5%">#</th>
                                        <th width="15%">Timestamp</th>
                                        <th width="15%">User</th>
                                        <th width="15%">Action</th>
                                        <th width="50%">Details</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($logs as $index => $log): ?>
                                        <tr>
                                            <td><?php echo $offset + $index + 1; ?></td>
                                            <td><?php echo date('M d, Y g:i A', strtotime($log['created_at'])); ?></td>
                                            <td>
                                                <?php if ($log['user_id']): ?>
                                                    <a href="user_edit.php?id=<?php echo $log['user_id']; ?>">
                                                        <?php echo htmlspecialchars($log['user_name']); ?>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted">System</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php
                                                $actionClass = '';
                                                $actionIcon = '';
                                                if (strpos($log['action'], 'add') !== false || strpos($log['action'], 'create') !== false) {
                                                    $actionClass = 'bg-success';
                                                    $actionIcon = 'fa-plus';
                                                } elseif (strpos($log['action'], 'update') !== false || strpos($log['action'], 'edit') !== false) {
                                                    $actionClass = 'bg-primary';
                                                    $actionIcon = 'fa-edit';
                                                } elseif (strpos($log['action'], 'delete') !== false || strpos($log['action'], 'remove') !== false) {
                                                    $actionClass = 'bg-danger';
                                                    $actionIcon = 'fa-trash';
                                                } elseif (strpos($log['action'], 'login') !== false) {
                                                    $actionClass = 'bg-info';
                                                    $actionIcon = 'fa-sign-in-alt';
                                                } elseif (strpos($log['action'], 'logout') !== false) {
                                                    $actionClass = 'bg-secondary';
                                                    $actionIcon = 'fa-sign-out-alt';
                                                } else {
                                                    $actionClass = 'bg-info';
                                                    $actionIcon = 'fa-info-circle';
                                                }
                                                $actionDisplay = str_replace('_', ' ', $log['action']);
                                                $actionDisplay = ucwords($actionDisplay);
                                                ?>
                                                <span class="badge <?php echo $actionClass; ?>">
                                                    <i class="fas <?php echo $actionIcon; ?> me-1"></i>
                                                    <?php echo $actionDisplay; ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($log['details']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-4">
                            <p>Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $perPage, $totalLogs); ?> of <?php echo $totalLogs; ?> logs</p>
                            <?php 
                            $queryParams = [];
                            if ($user_id) $queryParams['user_id'] = $user_id;
                            if ($action) $queryParams['action'] = $action;
                            if ($from_date) $queryParams['from_date'] = $from_date;
                            if ($to_date) $queryParams['to_date'] = $to_date;
                            if (!empty($search)) $queryParams['search'] = $search;
                            if ($perPage != DEFAULT_PAGINATION_LIMIT) $queryParams['per_page'] = $perPage;
                            $queryString = !empty($queryParams) ? '&' . http_build_query($queryParams) : '';
                            echo generatePagination($page, $totalPages, "activity_logs.php?$queryString");
                            ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <?php if (!empty($whereClause)): ?>
                                <p>No activity logs found matching your filter criteria.</p>
                                <p><a href="activity_logs.php" class="alert-link">Clear filters</a> to see all activity logs.</p>
                            <?php else: ?>
                                <p>No activity logs found in the system.</p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Activity Insights Card -->
            <?php if ($logs && count($logs) > 0): ?>
                <div class="card">
                    <div class="card-header">
                        <h6>Activity Insights</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <!-- Most Active Users -->
                            <div class="col-md-6 mb-4">
                                <h6 class="font-weight-bold text-primary">Most Active Users</h6>
                                <?php
                                $activeUsersSql = "SELECT u.id, u.name, COUNT(*) as action_count 
                                                 FROM activitylogs a 
                                                 JOIN userdetails u ON a.user_id = u.id 
                                                 GROUP BY a.user_id 
                                                 ORDER BY action_count DESC 
                                                 LIMIT 5";
                                $activeUsers = fetchAll($activeUsersSql);
                                if ($activeUsers && count($activeUsers) > 0):
                                ?>
                                    <div class="list-group">
                                        <?php foreach ($activeUsers as $user): ?>
                                            <a href="activity_logs.php?user_id=<?php echo $user['id']; ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                                <?php echo htmlspecialchars($user['name']); ?>
                                                <span class="badge bg-primary"><?php echo $user['action_count']; ?> actions</span>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted">No user activity data available.</p>
                                <?php endif; ?>
                            </div>
                            <!-- Most Common Actions -->
                            <div class="col-md-6 mb-4">
                                <h6 class="font-weight-bold text-primary">Most Common Actions</h6>
                                <?php
                                $commonActionsSql = "SELECT action, COUNT(*) as action_count 
                                                   FROM activitylogs 
                                                   GROUP BY action 
                                                   ORDER BY action_count DESC 
                                                   LIMIT 5";
                                $commonActions = fetchAll($commonActionsSql);
                                if ($commonActions && count($commonActions) > 0):
                                ?>
                                    <div class="list-group">
                                        <?php foreach ($commonActions as $actionItem): ?>
                                            <a href="activity_logs.php?action=<?php echo $actionItem['action']; ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                                <?php 
                                                $actionDisplay = str_replace('_', ' ', $actionItem['action']);
                                                echo ucwords($actionDisplay); 
                                                ?>
                                                <span class="badge bg-primary"><?php echo $actionItem['action_count']; ?></span>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted">No action data available.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
    </div>
</div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const currentPage = window.location.pathname.split('/').pop();
            document.querySelectorAll('.sidebar a').forEach(link => {
                if (link.getAttribute('href') === currentPage) {
                    link.classList.add('active');
                    link.style.color = '#45f3ff';
                }
            });
        });
    </script>
</body>
</html>