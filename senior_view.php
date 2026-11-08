<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Check if user is logged in and is admin or caregiver
if (!isLoggedIn() || (!isAdmin() && !isCaregiver())) {
    setAlert('danger', 'Unauthorized access.');
    redirect('login.php');
}

// Check if senior ID is provided
if (!isset($_GET['id'])) {
    setAlert('danger', 'Senior ID not provided.');
    redirect('dashboard.php');
}

$seniorId = (int)$_GET['id'];

// Get senior details
$senior = fetchOne("SELECT * FROM seniorcitizens WHERE id = ?", [$seniorId]);
if (!$senior) {
    setAlert('danger', 'Senior citizen not found.');
    redirect('dashboard.php');
}

$pageTitle = 'View Senior - ' . htmlspecialchars($senior['name']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Senior Details - AgeWell Senior Care</title>
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

        /* Table styling */
        .table {
            color: #cdcdcd;
            background: transparent;
            border-collapse: separate;
            border-spacing: 0;
        }

        .table tbody tr {
            transition: background-color 0.3s;
        }

        .table tbody tr:hover {
            background-color: rgba(69, 243, 255, 0.05);
        }

        .table tbody tr th,
        .table tbody tr td {
            border-top: none;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding: 12px 15px;
        }

        .table tbody tr th {
            color: #45f3ff;
            font-weight: 500;
        }

        /* Section styling */
        .info-section {
            margin-bottom: 30px;
        }

        .info-section h5 {
            color: #45f3ff;
            font-size: 18px;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 1px solid rgba(69, 243, 255, 0.3);
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
                <h1>Senior Details</h1>
                <div class="quick-access">
                    <a href="view_seniors.php" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left"></i> Back to List
                    </a>
                    <a href="senior_edit.php?id=<?php echo $senior['id']; ?>" class="btn btn-outline-primary">
                        <i class="fas fa-edit"></i> Edit Senior
                    </a>
                    <a href="senior_report.php?id=<?php echo $senior['id']; ?>" class="btn btn-outline-primary">
                        <i class="fas fa-file-medical"></i> Generate Report
                    </a>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h6>
                        <i class="fas fa-user me-2"></i>
                        <?php echo htmlspecialchars($senior['name']); ?>
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-section">
                                <h5><i class="fas fa-user me-2"></i>Personal Information</h5>
                            <table class="table">
                                    <tbody>
                                <tr>
                                    <th width="30%">Name:</th>
                                    <td><?php echo htmlspecialchars($senior['name']); ?></td>
                                </tr>
                                <tr>
                                    <th>Age:</th>
                                    <td><?php echo $senior['age']; ?></td>
                                </tr>
                                <tr>
                                    <th>Gender:</th>
                                    <td><?php echo ucfirst($senior['gender']); ?></td>
                                </tr>
                                <tr>
                                    <th>Phone:</th>
                                    <td><?php echo htmlspecialchars($senior['phone']); ?></td>
                                </tr>
                                <tr>
                                    <th>Address:</th>
                                    <td><?php echo htmlspecialchars($senior['address']); ?></td>
                                </tr>
                                    </tbody>
                            </table>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-section">
                                <h5><i class="fas fa-phone-alt me-2"></i>Emergency Contact</h5>
                            <table class="table">
                                    <tbody>
                                <tr>
                                    <th width="30%">Name:</th>
                                    <td><?php echo htmlspecialchars($senior['emergency_contact']); ?></td>
                                </tr>
                                <tr>
                                    <th>Phone:</th>
                                    <td><?php echo htmlspecialchars($senior['emergency_phone']); ?></td>
                                </tr>
                                    </tbody>
                            </table>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-12">
                            <div class="info-section">
                                <h5><i class="fas fa-notes-medical me-2"></i>Medical Information</h5>
                            <table class="table">
                                    <tbody>
                                <tr>
                                    <th width="20%">Medical History:</th>
                                    <td><?php echo nl2br(htmlspecialchars($senior['medical_history'])); ?></td>
                                </tr>
                                <tr>
                                    <th>Medications:</th>
                                    <td><?php echo nl2br(htmlspecialchars($senior['medications'])); ?></td>
                                </tr>
                                <tr>
                                    <th>Allergies:</th>
                                    <td><?php echo htmlspecialchars($senior['allergies'] ?: 'None'); ?></td>
                                </tr>
                                    </tbody>
                            </table>
                        </div>
                    </div>
                </div>
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
                if (link.getAttribute('href') === currentPage || 
                    (currentPage.includes('senior_view.php') && link.getAttribute('href').includes('senior_citizens.php'))) {
                    link.classList.add('active');
                }
            });
        });
    </script>
</body>
</html>