<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    setAlert('danger', 'Unauthorized access.');
    redirect('login.php');
}

$pageTitle = 'Caregiver Assignments';

// Get all seniors and caregivers with error handling
$seniors = fetchAll("SELECT id, name FROM seniorcitizens ORDER BY name ASC") ?: [];
$caregivers = fetchAll("SELECT id, name FROM userdetails WHERE role = 'caregiver' AND status = 'active' ORDER BY name ASC") ?: [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $seniorId = isset($_POST['senior_id']) ? (int)$_POST['senior_id'] : 0;
    $caregiverId = isset($_POST['caregiver_id']) && $_POST['caregiver_id'] !== '' ? (int)$_POST['caregiver_id'] : null;
    
    if ($seniorId > 0) {
        global $conn;
        $success = false;

        if ($caregiverId !== null) {
            $caregiver = fetchOne(
                "SELECT id FROM userdetails WHERE id = ? AND role = 'caregiver' AND status = 'active'",
                [$caregiverId],
                'i'
            );
            if (!$caregiver) {
                setAlert('danger', 'Please select an active caregiver.');
                redirect('caregiver_assignments_fixed.php');
            }
        }

        $conn->begin_transaction();
        try {
            $removeExisting = $conn->prepare("DELETE FROM senior_caregiver_assignments WHERE senior_id = ?");
            $removeExisting->bind_param("i", $seniorId);
            $removeExisting->execute();
            $removeExisting->close();

            if ($caregiverId !== null) {
                $insertAssignment = $conn->prepare(
                    "INSERT INTO senior_caregiver_assignments (senior_id, caregiver_id, assigned_date) VALUES (?, ?, NOW())"
                );
                $insertAssignment->bind_param("ii", $seniorId, $caregiverId);
                $success = $insertAssignment->execute();
                $insertAssignment->close();
            } else {
                $success = true;
            }

            if ($success) {
                $conn->commit();
            } else {
                $conn->rollback();
            }
        } catch (Exception $e) {
            $conn->rollback();
            error_log('Caregiver assignment update failed: ' . $e->getMessage());
        }

        $affected_rows = $success ? 1 : 0;
        
        if ($success) {
            // Log the assignment
            $logSql = "INSERT INTO activitylogs (user_id, action, details, created_at) 
                      VALUES (?, ?, ?, NOW())";
            $details = "Senior ID: $seniorId assigned to Caregiver ID: " . ($caregiverId ?? 'None');
            query($logSql, [$_SESSION['user_id'], 'caregiver_assignment', $details], 'iss');
            
            if ($affected_rows > 0) {
                setAlert('success', 'Caregiver assignment updated successfully.');
            } else {
                // If no rows affected, it could be that the assignment was already set to that value
                setAlert('info', 'No changes needed. The assignment was already set to the selected value.');
            }
        } else {
            setAlert('danger', 'Failed to update assignment. Please try again. Error: ' . $conn->error);
        }
    } else {
        setAlert('danger', 'Invalid senior ID: ' . $seniorId);
    }
    
    redirect('caregiver_assignments_fixed.php');
}

// Get current assignments with error handling - THIS IS THE KEY QUERY TO FIX
$assignments = fetchAll("
    SELECT s.id, s.name as senior_name, sca.caregiver_id,
           COALESCE(u.name, 'Unassigned') as caregiver_name
    FROM seniorcitizens s
    LEFT JOIN senior_caregiver_assignments sca ON s.id = sca.senior_id
    LEFT JOIN userdetails u ON sca.caregiver_id = u.id
    ORDER BY s.name ASC
") ?? [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Caregiver Assignments - AgeWell Senior Care</title>
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

        /* Card styling */
        .card {
            background: #28292d;
            border: 1px solid rgba(69, 243, 255, 0.3);
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            margin-bottom: 20px;
            overflow: hidden;
        }

        .card-header {
            background: #1c1c1c;
            border-bottom: 1px solid rgba(69, 243, 255, 0.3);
            padding: 15px 20px;
            color: #45f3ff;
            font-weight: 500;
        }

        .card-body {
            padding: 20px;
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

        /* Table styling */
        .assignment-table {
            width: 100%;
            border-collapse: collapse;
        }

        .assignment-table th {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid rgba(69, 243, 255, 0.3);
            color: #45f3ff;
        }

        .assignment-table td {
            padding: 12px 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .assignment-table tr:hover {
            background-color: rgba(69, 243, 255, 0.05);
        }

        /* Form elements */
        .form-select {
            background-color: #1c1c1c;
            border: 1px solid rgba(69, 243, 255, 0.3);
            color: #cdcdcd;
            border-radius: 4px;
            padding: 8px 12px;
        }

        .form-select:focus {
            border-color: #45f3ff;
            box-shadow: 0 0 0 0.25rem rgba(69, 243, 255, 0.25);
        }

        /* Alert styling */
        .alert {
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
        }

        .alert-success {
            background-color: rgba(75, 219, 106, 0.1);
            border-color: rgba(75, 219, 106, 0.3);
            color: #4bdb6a;
        }

        /* Badge styling */
        .badge {
            padding: 5px 8px;
            border-radius: 4px;
            font-weight: 500;
        }

        .bg-success {
            background-color: #4bdb6a !important;
            color: #1c1c1c;
        }

        .bg-danger {
            background-color: #ff3860 !important;
        }
    </style>
</head>
<body>
    <header>
        <nav class="navbar navbar-dark bg-dark">
            <div class="container-fluid">
                <a class="navbar-brand d-flex align-items-center" href="dashboard.php">
                    <i class="fas fa-heartbeat me-2"></i>
                    AgeWell Senior Care
                </a>
                <div class="d-flex">
                    <a href="dashboard.php" class="btn btn-outline-light me-2">Dashboard</a>
                    <a href="senior_citizens.php" class="btn btn-outline-light me-2">Senior Citizens</a>
                    <a href="appointments.php" class="btn btn-outline-light me-2">Appointments</a>
                    <a href="health_metrics.php" class="btn btn-outline-light me-2">Health Metrics</a>
                    <a href="profile.php" class="btn btn-outline-light me-2">Profile</a>
                    <a href="logout.php" class="btn btn-outline-light">Logout</a>
                </div>
            </div>
        </nav>
    </header>

    <div class="container-fluid">
        <div class="row">
            <div class="col-md-3 col-lg-2 bg-dark p-0 sidebar">
                <h6>QUICK ACTIONS</h6>
                <ul>
                    <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li><a href="senior_add.php"><i class="fas fa-user-plus"></i> Add New Senior</a></li>
                </ul>
                
                <h6>QUICK REPORTS</h6>
                <ul>
                    <li><a href="reports.php?type=seniors"><i class="fas fa-users"></i> Senior Citizens Report</a></li>
                    <li><a href="reports.php?type=appointments"><i class="fas fa-calendar-check"></i> Appointments Report</a></li>
                    <li><a href="reports.php?type=health_metrics"><i class="fas fa-heartbeat"></i> Health Metrics Report</a></li>
                </ul>
                
                <h6>ADMIN ACTIONS</h6>
                <ul>
                    <li><a href="user_add.php"><i class="fas fa-user-plus"></i> Add New User</a></li>
                    <li><a href="users.php"><i class="fas fa-users-cog"></i> Manage Users</a></li>
                    <li><a href="caregiver_assignments_fixed.php" class="active"><i class="fas fa-user-nurse"></i> Caregiver Assignments</a></li>
                    <li><a href="activity_logs.php"><i class="fas fa-history"></i> View Activity Logs</a></li>
                </ul>
            </div>
            
            <div class="col-md-9 col-lg-10 main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="text-info">
                        <i class="fas fa-user-nurse"></i> Caregiver Assignments
                    </h1>
                    <button class="btn btn-primary" id="newAssignmentBtn">
                        <i class="fas fa-plus"></i> New Assignment
                    </button>
                </div>
                
                <!-- Alert Messages -->
                <?php if (isset($_SESSION['alert_type']) && isset($_SESSION['alert_message'])): ?>
                    <div class="alert alert-<?php echo $_SESSION['alert_type']; ?> alert-dismissible fade show" role="alert">
                        <i class="fas <?php echo $_SESSION['alert_type'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> me-2"></i>
                        <?php echo $_SESSION['alert_message']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['alert_type'], $_SESSION['alert_message']); ?>
                <?php endif; ?>
                
                <!-- Assignments Table Card -->
                <div class="card">
                    <div class="card-header">
                        Manage Assignments
                    </div>
                    <div class="card-body">
                        <table class="assignment-table">
                            <thead>
                                <tr>
                                    <th>Senior Citizen</th>
                                    <th>Assigned Caregiver</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($assignments as $assignment): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($assignment['senior_name']); ?></td>
                                    <td>
                                        <?php if ($assignment['caregiver_id']): ?>
                                            <span class="badge bg-success">
                                                <i class="fas fa-user-nurse"></i> 
                                                <?php echo htmlspecialchars($assignment['caregiver_name']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Unassigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <form method="post" action="caregiver_assignments_fixed.php" class="d-flex align-items-center">
                                            <input type="hidden" name="senior_id" value="<?php echo $assignment['id']; ?>">
                                            <select name="caregiver_id" class="form-select form-select-sm me-2" style="max-width: 200px;">
                                                <option value="">Unassign</option>
                                                <?php foreach ($caregivers as $caregiver): ?>
                                                <option value="<?php echo $caregiver['id']; ?>" <?php echo ($caregiver['id'] == $assignment['caregiver_id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($caregiver['name']); ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" class="btn btn-sm btn-primary">
                                                <i class="fas fa-save"></i> Save
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <div class="mt-3">
                            <a href="fix_assignments.php" class="btn btn-sm btn-info">
                                <i class="fas fa-wrench"></i> Debug Assignment Tool
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // New Assignment Button
            document.getElementById('newAssignmentBtn').addEventListener('click', function() {
                if (confirm('This will take you to a page to create a new assignment. Continue?')) {
                    window.location.href = 'assign_caregiver.php';
                }
            });
        });
    </script>
</body>
</html> 