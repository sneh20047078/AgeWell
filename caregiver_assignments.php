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
                redirect('caregiver_assignments.php');
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
    
    redirect('caregiver_assignments.php');
}

// Get current assignments with error handling
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

        /* Form controls */
        .form-label {
            color: #45f3ff;
            font-weight: 500;
            margin-bottom: 8px;
        }

        .form-control, .form-select {
            background-color: #1c1c1c;
            border: 1px solid rgba(69, 243, 255, 0.3);
            color: #cdcdcd;
            border-radius: 4px;
            padding: 10px 15px;
            transition: all 0.3s;
        }

        .form-control:focus, .form-select:focus {
            background-color: #1c1c1c;
            border-color: #45f3ff;
            box-shadow: 0 0 0 0.25rem rgba(69, 243, 255, 0.25);
            color: #fff;
        }

        .form-select-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }

        .form-select option {
            background-color: #1c1c1c;
            color: #cdcdcd;
        }

        /* Table styling */
        .table {
            color: #cdcdcd;
            background: transparent;
            border-collapse: separate;
            border-spacing: 0;
        }

        .table-bordered {
            border: 1px solid rgba(69, 243, 255, 0.2);
        }

        .table-bordered td, 
        .table-bordered th {
            border: 1px solid rgba(69, 243, 255, 0.2);
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

        .btn-sm {
            padding: 5px 10px;
            font-size: 0.875rem;
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

        .dataTables_wrapper .dataTables_paginate .paginate_button {
            color: #cdcdcd !important;
            border: 1px solid rgba(69, 243, 255, 0.3);
            background: #1c1c1c;
            border-radius: 4px;
            margin: 0 3px;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.current, 
        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            color: #1c1c1c !important;
            background: #45f3ff;
            border-color: #45f3ff;
        }

        /* Gap utility */
        .gap-2 {
            gap: 0.5rem;
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
                <li><a href="caregiver_assignments.php" class="active"><i class="fas fa-user-nurse"></i> Caregiver Assignments</a></li>
                <li><a href="activity_logs.php"><i class="fas fa-history"></i> View Activity Logs</a></li>
            </ul>
        </div>
        
        <!-- Main content -->
        <div class="main-content">
            <div class="page-title">
                <h1><i class="fas fa-user-nurse me-2"></i> Caregiver Assignments</h1>
                <button type="button" class="btn btn-primary" onclick="toggleNewAssignment()">
                    <i class="fas fa-plus me-1"></i> New Assignment
                </button>
            </div>

            <!-- New Assignment Section -->
            <div id="newAssignmentSection" class="card" style="display: none;">
                <div class="card-header">
                    <h6>Create New Assignment</h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="caregiver_assignments.php">
                        <div class="row">
                            <div class="col-md-5">
                                <div class="mb-3">
                                    <label class="form-label">Select Senior</label>
                                    <select name="senior_id" class="form-select" required>
                                        <option value="">Choose...</option>
                                        <?php foreach ($seniors as $senior): ?>
                                            <option value="<?php echo $senior['id']; ?>">
                                                <?php echo htmlspecialchars($senior['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-5">
                                <div class="mb-3">
                                    <label class="form-label">Select Caregiver</label>
                                    <select name="caregiver_id" class="form-select" required>
                                        <option value="">Choose...</option>
                                        <?php foreach ($caregivers as $caregiver): ?>
                                            <option value="<?php echo $caregiver['id']; ?>">
                                                <?php echo htmlspecialchars($caregiver['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <div class="mb-3">
                                    <button type="submit" class="btn btn-primary">Create Assignment</button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
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

            <!-- Assignments Table -->
            <div class="card">
                <div class="card-header">
                    <h6>Manage Assignments</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($seniors) || empty($caregivers)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> 
                            <strong>No assignments found.</strong> To manage caregiver assignments, you need both seniors and caregivers registered in the system.
                            
                            <div class="mt-3">
                                <?php if (empty($seniors)): ?>
                                    <p><i class="fas fa-exclamation-triangle"></i> There are no senior citizens registered.</p>
                                    <a href="add_senior.php" class="btn btn-sm btn-primary">
                                        <i class="fas fa-plus"></i> Add Senior Citizen
                                    </a>
                                <?php endif; ?>
                                
                                <?php if (empty($caregivers)): ?>
                                    <p class="mt-2"><i class="fas fa-exclamation-triangle"></i> There are no caregivers registered.</p>
                                    <a href="add_user.php" class="btn btn-sm btn-primary">
                                        <i class="fas fa-plus"></i> Add Caregiver
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php elseif (empty($assignments)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> No assignments found. Please make sure both seniors and caregivers are registered in the system.
                        </div>
                        
                        <p>You can create assignments using the form below:</p>
                        
                        <form method="post" action="caregiver_assignments.php" class="mb-4">
                            <div class="row">
                                <div class="col-md-5">
                                    <div class="form-group">
                                        <label for="senior_id">Senior Citizen:</label>
                                        <select id="senior_id" name="senior_id" class="form-control" required>
                                            <option value="">Select Senior Citizen</option>
                                            <?php foreach ($seniors as $senior): ?>
                                                <option value="<?php echo $senior['id']; ?>"><?php echo htmlspecialchars($senior['name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-5">
                                    <div class="form-group">
                                        <label for="caregiver_id">Caregiver:</label>
                                        <select id="caregiver_id" name="caregiver_id" class="form-control">
                                            <option value="">Unassign</option>
                                            <?php foreach ($caregivers as $caregiver): ?>
                                                <option value="<?php echo $caregiver['id']; ?>"><?php echo htmlspecialchars($caregiver['name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fas fa-save"></i> Save
                                    </button>
                                </div>
                            </div>
                        </form>
                    <?php else: ?>
                        <table class="table table-striped table-hover">
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
                                            <td style="color: #fff;"><?php echo htmlspecialchars($assignment['senior_name']); ?></td>
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
                                            <form method="post" action="caregiver_assignments.php" class="d-inline">
                                                    <input type="hidden" name="senior_id" value="<?php echo $assignment['id']; ?>">
                                                <select name="caregiver_id" class="form-select form-select-sm d-inline-block w-auto">
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
document.addEventListener('DOMContentLoaded', function() {
            // Initialize DataTables with custom styling
            $('#assignmentsTable').DataTable({
        pageLength: 10,
                order: [[0, "asc"]],
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "Search assignments...",
                    lengthMenu: "Show _MENU_ assignments",
                    info: "Showing _START_ to _END_ of _TOTAL_ assignments",
                    infoEmpty: "No assignments found",
                    infoFiltered: "(filtered from _MAX_ total assignments)"
                },
                initComplete: function () {
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

function toggleNewAssignment() {
    const section = document.getElementById('newAssignmentSection');
    if (section.style.display === 'none') {
        section.style.display = 'block';
    } else {
        section.style.display = 'none';
    }
}
</script>
</body>
</html>