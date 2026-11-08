<?php
/**
 * AgeWell Senior Care and Support System
 * Users Management Page
 */

// Include configuration and required files
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Check if user is logged in and has admin privileges
if (!isLoggedIn()) {
    setAlert('danger', 'Please login to access this page.');
    redirect('login.php');
} elseif (!isAdmin()) {
    setAlert('danger', 'You do not have permission to access this page.');
    redirect('dashboard.php');
}

// Handle status toggle
if (isset($_GET['action']) && $_GET['action'] === 'toggle_status' && isset($_GET['user_id']) && isset($_GET['new_status'])) {
    $userId = (int)$_GET['user_id'];
    $newStatus = $_GET['new_status'] === 'active' ? 'active' : 'inactive';
    
    if ($userId != $_SESSION['user_id']) {
        $sql = "UPDATE userdetails SET status = ? WHERE id = ?";
        if (query($sql, [$newStatus, $userId], 'si')) {
            setAlert('success', 'User status updated successfully.');
        } else {
            setAlert('danger', 'Failed to update user status.');
        }
    }
    redirect('users.php');
}

// Handle delete confirmation request
if (isset($_GET['action']) && $_GET['action'] === 'delete_confirm' && isset($_GET['user_id'])) {
    $userId = (int)$_GET['user_id'];
    $userSql = "SELECT name FROM userdetails WHERE id = ? AND deleted_at IS NULL";
    $user = fetchOne($userSql, [$userId], 'i');
    
    if (!$user) {
        setAlert('danger', 'User not found or has already been deleted.');
        redirect('users.php');
    }
    
    if ($userId == $_SESSION['user_id']) {
        setAlert('danger', 'You cannot delete your own account.');
        redirect('users.php');
    }
    
    // Set variables for confirmation page
    $confirmAction = true;
    $confirmUserId = $userId;
    $confirmUserName = $user['name'];
}

// Handle user deletion - now using POST method
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user']) && isset($_POST['user_id'])) {
    $userId = (int)$_POST['user_id'];
    
    // Prevent deleting yourself
    if ($userId != $_SESSION['user_id']) {
        // Check for related records in all relevant tables
        $hasRelations = false;
        $errorMessage = "User cannot be deleted because they have associated records in: ";
        $relatedTables = [];
        
        // Check appointments
        $checkAppointmentsSql = "SELECT COUNT(*) as count FROM appointments WHERE caregiver_id = ?";
        $appointmentsCount = fetchOne($checkAppointmentsSql, [$userId], 'i');
        if ($appointmentsCount && $appointmentsCount['count'] > 0) {
            $hasRelations = true;
            $relatedTables[] = "appointments";
        }
        
        // Check health records
        $checkHealthRecordsSql = "SELECT COUNT(*) as count FROM healthrecords WHERE caregiver_id = ?";
        $healthRecordsCount = fetchOne($checkHealthRecordsSql, [$userId], 'i');
        if ($healthRecordsCount && $healthRecordsCount['count'] > 0) {
            $hasRelations = true;
            $relatedTables[] = "health records";
        }
        
        if ($hasRelations) {
            // If user has related records, deactivate instead of deleting
            $errorMessage .= implode(", ", $relatedTables);
            $sql = "UPDATE userdetails SET status = 'inactive' WHERE id = ?";
            if (query($sql, [$userId], 'i')) {
                setAlert('warning', $errorMessage . '. User has been deactivated instead.');
                // Log activity
                logActivity('user_deactivate', 'Deactivated user ID: ' . $userId . ' instead of deleting due to related records');
            } else {
                setAlert('danger', 'Failed to deactivate user.');
            }
        } else {
            // Use direct mysqli connection for soft deletion
            global $conn;
            
            // Start transaction to ensure data consistency
            $conn->begin_transaction();
            
            try {
                // Soft delete the user by setting deleted_at timestamp
                $stmt = $conn->prepare("UPDATE userdetails SET deleted_at = NOW() WHERE id = ?");
                $stmt->bind_param('i', $userId);
                
                // Execute the statement
                if ($stmt->execute()) {
                    // Check if any rows were affected
                    if ($stmt->affected_rows > 0) {
                        // Log activity
                        logActivity('user_delete', 'Deleted user ID: ' . $userId);
                        setAlert('success', 'User deleted successfully.');
                        $conn->commit();
                    } else {
                        setAlert('warning', 'No rows were affected. The user may not exist or could not be deleted.');
                        $conn->rollback();
                    }
                } else {
                    throw new Exception($stmt->error);
                }
                
                $stmt->close();
            } catch (Exception $e) {
                // Rollback transaction on error
                $conn->rollback();
                setAlert('danger', 'Error deleting user: ' . $e->getMessage());
                error_log('Delete user error: ' . $e->getMessage());
            }
        }
    } else {
        setAlert('danger', 'You cannot delete your own account.');
    }
    
    // Always refresh the page to show updated data
    header("Location: users.php?t=" . time() . "&r=" . mt_rand(1000, 9999));
    exit;
}

// Set page title
$pageTitle = 'User Management';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : DEFAULT_PAGINATION_LIMIT;
$offset = ($page - 1) * $perPage;

// Filters
$role = isset($_GET['role']) ? cleanInput($_GET['role']) : null;
$status = isset($_GET['status']) ? cleanInput($_GET['status']) : null;
$search = isset($_GET['search']) ? cleanInput($_GET['search']) : '';

// Build query conditions
$whereConditions = [];
$params = [];
$types = '';

if ($role && in_array($role, ['admin', 'caregiver'])) {
    $whereConditions[] = 'role = ?';
    $params[] = $role;
    $types .= 's';
}

if ($status && in_array($status, ['active', 'inactive'])) {
    $whereConditions[] = 'status = ?';
    $params[] = $status;
    $types .= 's';
}

if (!empty($search)) {
    $whereConditions[] = '(name LIKE ? OR email LIKE ? OR phone LIKE ?)';
    $searchTerm = '%' . $search . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= 'sss';
}

// Combine conditions
$whereClause = !empty($whereConditions) ? implode(' AND ', $whereConditions) : '';

// Get total number of users for pagination
$countSql = "SELECT SQL_NO_CACHE COUNT(*) as total FROM userdetails WHERE deleted_at IS NULL";
if (!empty($whereClause)) {
    $countSql .= " AND " . $whereClause;
}

$totalUsers = 0;

try {
    if (!empty($params)) {
        $types = str_repeat('s', count($params));
        $countResult = fetchOne($countSql, $params, $types);
    } else {
        $countResult = fetchOne($countSql);
    }
    
    $totalUsers = $countResult ? (int)$countResult['total'] : 0;
    error_log("Total users found: $totalUsers");
} catch (Exception $e) {
    error_log("Error in count query: " . $e->getMessage());
}

$totalPages = ceil($totalUsers / $perPage);

// Get users data with question mark placeholders
$sql = "SELECT 
        u.*,
        COALESCE(
            (SELECT COUNT(*) FROM appointments a WHERE a.caregiver_id = u.id AND a.status = 'completed'),
            0
        ) as completed_appointments
        FROM userdetails u 
        WHERE u.deleted_at IS NULL";

// Add additional conditions if they exist
if (!empty($whereClause)) {
    $sql .= " AND " . $whereClause;
}

// Add ordering and limits
$sql .= " ORDER BY u.name ASC LIMIT ?, ?";

// Prepare params array for use with fetchAll
$limitParams = $params;
$limitParams[] = $offset;
$limitParams[] = $perPage;

// Build types string for parameters
$types = '';
if (!empty($params)) {
    $types = str_repeat('s', count($params));
}
$types .= 'ii'; // Add integer types for LIMIT parameters

// Execute query with error handling
try {
    error_log("SQL Query: " . $sql);
    error_log("Parameters: " . print_r($limitParams, true));
    error_log("Types: " . $types);
    
    // Use a prepared query with bound pagination values.
    global $conn;
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Unable to prepare the user list query: ' . $conn->error);
    }
    
    // Bind parameters
    if (!empty($limitParams)) {
        $bindParams = array_map(function($param) {
            return $param;
        }, $limitParams);
        
        $stmt->bind_param($types, ...$bindParams);
    }
    
    // Execute and get results
    $stmt->execute();
    $result = $stmt->get_result();
    $users = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    error_log("Direct query found " . count($users) . " users");
} catch (Exception $e) {
    error_log("Database Error: " . $e->getMessage());
    $users = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Prevent browser caching -->
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>User Management - AgeWell Senior Care (<?php echo time(); ?>)</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
    <style>
        /* Same CSS as admin_dashboard.php */
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

        .alert-info {
            background-color: rgba(69, 243, 255, 0.1);
            border-color: rgba(69, 243, 255, 0.3);
            color: #45f3ff;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
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

        .btn-success {
            background: #4bdb6a;
            border-color: #4bdb6a;
        }

        .btn-danger {
            background: #ff3860;
            border-color: #ff3860;
        }

        .btn-warning {
            background: #ffd166;
            border-color: #ffd166;
            color: #1c1c1c;
        }

        .badge {
            padding: 5px 8px;
            border-radius: 4px;
            font-weight: 500;
        }

        .bg-danger {
            background-color: #ff3860 !important;
        }

        .bg-info {
            background-color: #45f3ff !important;
            color: #1c1c1c;
        }

        .bg-success {
            background-color: #4bdb6a !important;
            color: #1c1c1c;
        }

        .bg-secondary {
            background-color: #8f8f8f !important;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
        }

        .action-buttons .btn {
            padding: 5px 10px;
            font-size: 12px;
        }

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

        .modal-content {
            background: #28292d;
            color: #cdcdcd;
            border: 1px solid rgba(69, 243, 255, 0.3);
        }

        .modal-header {
            background: #1c1c1c;
            border-bottom: 1px solid rgba(69, 243, 255, 0.3);
        }

        .modal-title {
            color: #45f3ff;
        }

        .modal-footer {
            border-top: 1px solid rgba(69, 243, 255, 0.3);
        }

        .btn-close {
            filter: invert(1) grayscale(100%) brightness(200%);
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
                <li><a href="users.php" class="active"><i class="fas fa-users-cog"></i> Manage Users</a></li>
                <li><a href="activity_logs.php"><i class="fas fa-history"></i> View Activity Logs</a></li>
            </ul>
        </div>
        
        <!-- Main content -->
        <div class="main-content">
            <div class="page-title">
                <h1>User Management</h1>
                <div class="quick-access">
                    <a href="user_add.php" class="btn btn-outline-primary">
                        <i class="fas fa-user-plus"></i> Add New User
                    </a>
                </div>
            </div>

            <?php if (isset($confirmAction) && $confirmAction): ?>
            <!-- Delete Confirmation -->
            <div class="card mb-4">
                <div class="card-header bg-danger text-white">
                    <h6>Confirm Delete User</h6>
                </div>
                <div class="card-body">
                    <p>Are you sure you want to delete user <strong><?php echo htmlspecialchars($confirmUserName); ?></strong>?</p>
                    <p class="text-danger mb-4"><strong>Warning:</strong> This action cannot be undone.</p>
                    
                    <div class="d-flex">
                        <a href="users.php" class="btn btn-secondary me-2">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <form method="POST" action="users.php" style="display: inline;">
                            <input type="hidden" name="user_id" value="<?php echo $confirmUserId; ?>">
                            <button type="submit" name="delete_user" class="btn btn-danger">
                                <i class="fas fa-trash"></i> Yes, Delete User
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Users List -->
            <div class="card">
                <div class="card-header">
                    <h6>Users</h6>
                    <span class="badge bg-info"><?php echo $totalUsers; ?></span>
                </div>
                <div class="card-body">
                    <?php if (!empty($users)): ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                        <th>Completed Appointments</th>
                                        <th>Last Login</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td>
                                                <?php echo htmlspecialchars($user['name']); ?>
                                                <?php if ($user['id'] == $_SESSION['user_id']): ?>
                                                    <span class="badge bg-info">You</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td><?php echo htmlspecialchars($user['phone']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $user['role'] === 'admin' ? 'danger' : 'info'; ?>">
                                                    <?php echo ucfirst($user['role']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $user['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                    <?php echo ucfirst($user['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($user['role'] === 'caregiver'): ?>
                                                    <?php echo number_format($user['completed_appointments']); ?>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo $user['last_login'] ? date('M d, Y g:i A', strtotime($user['last_login'])) : 'Never'; ?></td>
                                            <td>
                                                <div class="action-buttons">
                                                    <a href="user_edit.php?id=<?php echo $user['id']; ?>" class="btn btn-primary">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                        <a href="users.php?action=toggle_status&user_id=<?php echo $user['id']; ?>&new_status=<?php echo $user['status'] === 'active' ? 'inactive' : 'active'; ?>" 
                                                           class="btn btn-<?php echo $user['status'] === 'active' ? 'warning' : 'success'; ?>"
                                                           title="<?php echo $user['status'] === 'active' ? 'Deactivate' : 'Activate'; ?> user">
                                                            <i class="fas fa-<?php echo $user['status'] === 'active' ? 'ban' : 'check'; ?>"></i>
                                                        </a>
                                                        <a href="users.php?action=delete_confirm&user_id=<?php echo $user['id']; ?>" class="btn btn-danger" title="Delete user">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i> No users found.
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
                if (link.getAttribute('href') === currentPage) {
                    link.classList.add('active');
                }
            });
        });
    </script>
</body>
</html>