<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    setAlert('danger', 'Unauthorized access.');
    redirect('login.php');
}

// Handle delete confirmation request
if (isset($_GET['action']) && $_GET['action'] === 'delete_confirm' && isset($_GET['id'])) {
    $seniorId = (int)$_GET['id'];
    $seniorSql = "SELECT name FROM seniorcitizens WHERE id = ?";
    $senior = fetchOne($seniorSql, [$seniorId], 'i');
    
    if (!$senior) {
        setAlert('danger', 'Senior citizen not found.');
        redirect('senior_citizens.php');
    }
    
    // Set variables for confirmation page
    $confirmAction = true;
    $confirmSeniorId = $seniorId;
    $confirmSeniorName = $senior['name'];
}

// Handle senior deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_senior']) && isset($_POST['senior_id'])) {
    $seniorId = (int)$_POST['senior_id'];
    
    // Check for related records in relevant tables
    $hasRelations = false;
    $errorMessage = "Senior cannot be deleted because they have associated records in: ";
    $relatedTables = [];
    
    // Check health records
    $checkHealthRecordsSql = "SELECT COUNT(*) as count FROM healthrecords WHERE senior_id = ?";
    $healthRecordsCount = fetchOne($checkHealthRecordsSql, [$seniorId], 'i');
    if ($healthRecordsCount && $healthRecordsCount['count'] > 0) {
        $hasRelations = true;
        $relatedTables[] = "health records";
    }
    
    // Check appointments
    $checkAppointmentsSql = "SELECT COUNT(*) as count FROM appointments WHERE senior_id = ?";
    $appointmentsCount = fetchOne($checkAppointmentsSql, [$seniorId], 'i');
    if ($appointmentsCount && $appointmentsCount['count'] > 0) {
        $hasRelations = true;
        $relatedTables[] = "appointments";
    }
    
    if ($hasRelations) {
        // If senior has related records, set status to inactive instead of deleting
        $errorMessage .= implode(", ", $relatedTables);
        $sql = "UPDATE seniorcitizens SET status = 'inactive' WHERE id = ?";
        if (query($sql, [$seniorId], 'i')) {
            setAlert('warning', $errorMessage . '. Senior has been set to inactive instead.');
            // Log activity
            logActivity('senior_deactivate', 'Deactivated senior ID: ' . $seniorId . ' instead of deleting due to related records');
        } else {
            setAlert('danger', 'Failed to deactivate senior.');
        }
    } else {
        // Use direct mysqli connection for deletion
        global $conn;
        
        // Start transaction to ensure data consistency
        $conn->begin_transaction();
        
        try {
            // Delete the senior
            $stmt = $conn->prepare("DELETE FROM seniorcitizens WHERE id = ?");
            $stmt->bind_param('i', $seniorId);
            
            // Execute the statement
            if ($stmt->execute()) {
                // Check if any rows were affected
                if ($stmt->affected_rows > 0) {
                    // Log activity
                    logActivity('senior_delete', 'Deleted senior ID: ' . $seniorId);
                    setAlert('success', 'Senior citizen deleted successfully.');
                    $conn->commit();
                } else {
                    setAlert('warning', 'No rows were affected. The senior may not exist or could not be deleted.');
                    $conn->rollback();
                }
            } else {
                throw new Exception($stmt->error);
            }
            
            $stmt->close();
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            setAlert('danger', 'Error deleting senior: ' . $e->getMessage());
            error_log('Delete senior error: ' . $e->getMessage());
        }
    }
    
    // Always refresh the page to show updated data
    header("Location: senior_citizens.php?t=" . time() . "&r=" . mt_rand(1000, 9999));
    exit;
}

$pageTitle = 'Senior Citizens';

// Your page specific code here
$seniors = fetchAll("SELECT * FROM seniorcitizens ORDER BY name");
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

        .badge {
            padding: 5px 8px;
            border-radius: 4px;
            font-weight: 500;
        }

        .bg-primary {
            background-color: #45f3ff !important;
            color: #1c1c1c;
        }

        .bg-secondary {
            background-color: #818896 !important;
            color: #1c1c1c;
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
                <h1>Senior Citizens</h1>
                <div class="quick-access">
                    <a href="senior_add.php" class="btn btn-outline-primary">
                        <i class="fas fa-user-plus"></i> Add New Senior
                    </a>
                </div>
            </div>

            <?php if (isset($confirmAction) && $confirmAction): ?>
            <!-- Delete Confirmation -->
            <div class="card mb-4">
                <div class="card-header bg-danger text-white">
                    <h6>Confirm Delete Senior Citizen</h6>
                </div>
                <div class="card-body">
                    <p>Are you sure you want to delete senior citizen <strong><?php echo htmlspecialchars($confirmSeniorName); ?></strong>?</p>
                    <p class="text-danger mb-4"><strong>Warning:</strong> This action cannot be undone.</p>
                    
                    <div class="d-flex">
                        <a href="senior_citizens.php" class="btn btn-secondary me-2">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <form method="POST" action="senior_citizens.php" style="display: inline;">
                            <input type="hidden" name="senior_id" value="<?php echo $confirmSeniorId; ?>">
                            <button type="submit" name="delete_senior" class="btn btn-danger">
                                <i class="fas fa-trash"></i> Yes, Delete Senior
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h6>All Senior Citizens</h6>
                    <span class="badge bg-primary"><?php echo count($seniors); ?></span>
                </div>
                <div class="card-body">
                    <?php if (!empty($seniors)): ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Age</th>
                                        <th>Phone</th>
                                        <th>Address</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($seniors as $senior): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($senior['name']); ?></td>
                                            <td><?php echo htmlspecialchars($senior['age']); ?></td>
                                            <td><?php echo htmlspecialchars($senior['phone']); ?></td>
                                            <td><?php echo htmlspecialchars($senior['address']); ?></td>
                                            <td>
                                                <span class="badge bg-secondary">
                                                    <?php echo isset($senior['status']) ? ucfirst(htmlspecialchars($senior['status'])) : 'Active'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <a href="senior_view.php?id=<?php echo $senior['id']; ?>" 
                                                       class="btn btn-primary" title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="senior_edit.php?id=<?php echo $senior['id']; ?>" 
                                                       class="btn btn-primary" title="Edit Senior">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="senior_citizens.php?action=delete_confirm&id=<?php echo $senior['id']; ?>" 
                                                       class="btn btn-danger" title="Delete Senior">
                                                        <i class="fas fa-trash"></i>
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
    <script>
        document.addEventListener('DOMContentLoaded', function() {
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