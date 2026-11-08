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

$pageTitle = 'Assign Caregiver';

// Get senior details
$seniorId = isset($_GET['senior_id']) ? (int)$_GET['senior_id'] : 0;
$senior = fetchOne("SELECT * FROM seniorcitizens WHERE id = ?", [$seniorId]);

if (!$senior) {
    setAlert('danger', 'Senior citizen not found.');
    redirect('admin_dashboard.php');
}

// Get available caregivers
$caregivers = fetchAll("SELECT * FROM userdetails WHERE role = 'caregiver' AND status = 'active'");

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $caregiverId = isset($_POST['caregiver_id']) ? (int)$_POST['caregiver_id'] : 0;
    $caregiver = fetchOne(
        "SELECT id FROM userdetails WHERE id = ? AND role = 'caregiver' AND status = 'active'",
        [$caregiverId],
        'i'
    );

    if (!$caregiver) {
        setAlert('danger', 'Please select an active caregiver.');
    } else {
        $existing = fetchOne(
            "SELECT caregiver_id FROM senior_caregiver_assignments WHERE senior_id = ? LIMIT 1",
            [$seniorId],
            'i'
        );

        if ($existing && (int)$existing['caregiver_id'] === $caregiverId) {
            setAlert('warning', 'This senior is already assigned to the selected caregiver.');
        } else {
            global $conn;
            $conn->begin_transaction();
            try {
                $removeExisting = $conn->prepare("DELETE FROM senior_caregiver_assignments WHERE senior_id = ?");
                $removeExisting->bind_param('i', $seniorId);
                $removeExisting->execute();
                $removeExisting->close();

                $insertAssignment = $conn->prepare(
                    "INSERT INTO senior_caregiver_assignments (senior_id, caregiver_id, assigned_date) VALUES (?, ?, NOW())"
                );
                $insertAssignment->bind_param('ii', $seniorId, $caregiverId);
                $result = $insertAssignment->execute();
                $insertAssignment->close();

                if (!$result) {
                    throw new RuntimeException('Unable to save caregiver assignment.');
                }
                $conn->commit();
            } catch (Exception $e) {
                $conn->rollback();
                error_log('Caregiver assignment failed: ' . $e->getMessage());
                setAlert('danger', 'Failed to assign caregiver.');
                redirect('assign_caregiver.php?senior_id=' . $seniorId);
            }

            // Notify caregiver
            if (function_exists('createNotification')) {
                // Check which version of createNotification is available
                $reflection = new ReflectionFunction('createNotification');
                $params = $reflection->getParameters();
                
                if (count($params) >= 5) {
                    // Using functions.php version
                    createNotification(
                        'New Senior Assignment',
                        'You have been assigned a new senior citizen to care for.',
                        $caregiverId,
                        'senior_assignment',
                        false
                    );
                } else {
                    // Using notification_handler.php version
            createNotification(
                $caregiverId,
                'You have been assigned a new senior citizen to care for.',
                'senior_assignment'
            );
                }
            }
            
            setAlert('success', 'Caregiver assigned successfully.');
            redirect('admin_dashboard.php');
        }
    }
}

// Don't include the default header since we're using a custom one
// require_once 'includes/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Caregiver - AgeWell Senior Care</title>
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

        /* Form styling */
        .form-group {
            margin-bottom: 20px;
        }

        .form-control, .form-select {
            background-color: #1c1c1c;
            border: 1px solid rgba(69, 243, 255, 0.3);
            color: #fff;
            padding: 10px 15px;
            border-radius: 4px;
            transition: border-color 0.3s;
        }

        .form-control:focus, .form-select:focus {
            border-color: #45f3ff;
            box-shadow: 0 0 0 0.25rem rgba(69, 243, 255, 0.25);
            background-color: #1c1c1c;
            color: #fff;
        }

        .form-select option {
            background-color: #1c1c1c;
            color: #fff;
        }

        /* Label styling */
        label {
            display: block;
            margin-bottom: 8px;
            color: #45f3ff;
            font-weight: 500;
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

        .btn-secondary {
            background: transparent;
            border-color: #8f8f8f;
            color: #8f8f8f;
        }

        .btn-secondary:hover {
            background: rgba(143, 143, 143, 0.1);
            color: #fff;
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
                <h1>Assign Caregiver</h1>
                <div class="quick-access">
                    <a href="admin_dashboard.php" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>

            <!-- Assign Caregiver Card -->
            <div class="card glow-effect">
                <div class="card-header">
                    <h6>Assign Caregiver to <?php echo htmlspecialchars($senior['name']); ?></h6>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="form-group mb-3">
                            <label for="caregiver_id">Select Caregiver</label>
                            <select class="form-select" id="caregiver_id" name="caregiver_id" required>
                                <option value="">Choose a caregiver...</option>
                                <?php foreach ($caregivers as $caregiver): ?>
                                    <option value="<?php echo $caregiver['id']; ?>">
                                        <?php echo htmlspecialchars($caregiver['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="d-flex gap-2 mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-user-plus me-1"></i> Assign Caregiver
                            </button>
                            <a href="admin_dashboard.php" class="btn btn-secondary">
                                <i class="fas fa-times me-1"></i> Cancel
                            </a>
                        </div>
                    </form>
            </div>
        </div>
    </div>
</div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>