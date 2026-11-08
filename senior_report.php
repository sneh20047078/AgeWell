<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    setAlert('danger', 'Unauthorized access.');
    redirect('login.php');
}

// Check if senior ID is provided
if (!isset($_GET['id'])) {
    setAlert('danger', 'Senior ID not provided.');
    redirect('senior_citizens.php');
}

$seniorId = (int)$_GET['id'];

// Get senior details
$senior = fetchOne("SELECT * FROM seniorcitizens WHERE id = ?", [$seniorId]);
if (!$senior) {
    setAlert('danger', 'Senior citizen not found.');
    redirect('senior_citizens.php');
}

// Get health records
$healthRecords = fetchAll("SELECT * FROM healthrecords WHERE senior_id = ? ORDER BY recorded_at DESC", [$seniorId]);
$healthRecords = is_array($healthRecords) ? $healthRecords : [];

// Get appointments
$appointments = fetchAll("SELECT * FROM appointments WHERE senior_id = ? ORDER BY appointment_datetime DESC", [$seniorId]);
$appointments = is_array($appointments) ? $appointments : [];

// Get prescriptions
$prescriptions = fetchAll("SELECT * FROM prescriptions WHERE senior_id = ? ORDER BY start_date DESC", [$seniorId]);
$prescriptions = is_array($prescriptions) ? $prescriptions : [];

$pageTitle = 'Senior Report - ' . htmlspecialchars($senior['name']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - AgeWell Senior Care</title>
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

        .table tr:first-child th {
            border-top: 0;
        }

        .text-muted {
            color: #8f8f8f !important;
        }

        /* Section headings */
        h5 {
            color: #45f3ff;
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

        .btn-info {
            background: rgba(69, 243, 255, 0.2);
            border: 1px solid #45f3ff;
            color: #45f3ff;
        }

        .btn-info:hover {
            background: rgba(69, 243, 255, 0.3);
            color: #ffffff;
        }

        .btn-secondary {
            background: transparent;
            border: 1px solid #45f3ff;
            color: #45f3ff;
        }

        .btn-secondary:hover {
            background: rgba(69, 243, 255, 0.1);
            color: #45f3ff;
        }

        /* Print styles */
        @media print {
            body {
                background: white;
                color: black;
            }
            
            .header, .sidebar, .btn, footer {
                display: none !important;
            }
            
            .main-container {
                display: block;
            }
            
            .main-content {
                width: 100% !important;
                padding: 0;
            }
            
            .card {
                border: none !important;
                box-shadow: none !important;
                background: white;
            }
            
            .card-header {
                background-color: white !important;
                color: black !important;
                border-bottom: 1px solid #ddd;
            }
            
            .card-header h6 {
                color: black;
            }
            
            h5, .table thead th {
                color: black;
                border-bottom-color: #ddd;
            }
            
            .table, .table td, .table th {
                color: black;
                border-color: #ddd;
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
                <li><a href="reports.php?type=seniors" class="active"><i class="fas fa-users"></i> Senior Citizens Report</a></li>
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
                <h1><i class="fas fa-file-medical-alt me-2"></i> Senior Report</h1>
                <div>
                    <button onclick="window.print()" class="btn btn-info me-2">
                        <i class="fas fa-print me-1"></i> Print Report
                    </button>
                    <a href="senior_citizens.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Back to List
                    </a>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h6>Report for <?php echo htmlspecialchars($senior['name']); ?></h6>
                </div>
                <div class="card-body">
                    <!-- Personal Information -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h5>Personal Information</h5>
                            <table class="table">
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
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h5>Emergency Contact</h5>
                            <table class="table">
                                <tr>
                                    <th width="30%">Name:</th>
                                    <td><?php echo htmlspecialchars($senior['emergency_contact']); ?></td>
                                </tr>
                                <tr>
                                    <th>Phone:</th>
                                    <td><?php echo htmlspecialchars($senior['emergency_phone']); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- Medical Information -->
                    <div class="mb-4">
                        <h5>Medical Information</h5>
                        <table class="table">
                            <tr>
                                <th width="20%">Medical History:</th>
                                <td><?php echo nl2br(htmlspecialchars($senior['medical_history'] ?: 'None provided')); ?></td>
                            </tr>
                            <tr>
                                <th>Medications:</th>
                                <td><?php echo nl2br(htmlspecialchars($senior['medications'] ?: 'None provided')); ?></td>
                            </tr>
                            <tr>
                                <th>Allergies:</th>
                                <td><?php echo htmlspecialchars($senior['allergies'] ?: 'None'); ?></td>
                            </tr>
                        </table>
                    </div>

                    <!-- Health Records -->
                    <div class="mb-4">
                        <h5>Recent Health Records</h5>
                        <?php if (!empty($healthRecords)): ?>
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Type</th>
                                            <th>Value</th>
                                            <th>Notes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($healthRecords as $record): ?>
                                            <tr>
                                                <td><?php echo date('M d, Y', strtotime($record['recorded_at'])); ?></td>
                                                <td><?php echo str_replace('_', ' ', ucfirst($record['record_type'])); ?></td>
                                                <td><?php echo htmlspecialchars($record['value']); ?></td>
                                                <td><?php echo htmlspecialchars($record['notes'] ?: ''); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">No health records found.</p>
                        <?php endif; ?>
                    </div>

                    <!-- Appointments -->
                    <div class="mb-4">
                        <h5>Recent Appointments</h5>
                        <?php if (!empty($appointments)): ?>
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Doctor</th>
                                            <th>Purpose</th>
                                            <th>Status</th>
                                            <th>Notes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($appointments as $appointment): ?>
                                            <tr>
                                                <td><?php echo date('M d, Y', strtotime($appointment['appointment_datetime'])); ?></td>
                                                <td><?php echo htmlspecialchars($appointment['doctor_name']); ?></td>
                                                <td><?php echo htmlspecialchars($appointment['purpose']); ?></td>
                                                <td><?php echo ucfirst($appointment['status']); ?></td>
                                                <td><?php echo htmlspecialchars($appointment['notes'] ?: ''); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">No appointments found.</p>
                        <?php endif; ?>
                    </div>

                    <!-- Prescriptions -->
                    <?php if (!empty($prescriptions)): ?>
                    <div class="mb-4">
                        <h5>Current Prescriptions</h5>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Medication</th>
                                        <th>Dosage</th>
                                        <th>Start Date</th>
                                        <th>End Date</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($prescriptions as $prescription): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($prescription['medication_name']); ?></td>
                                            <td><?php echo htmlspecialchars($prescription['dosage']); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($prescription['start_date'])); ?></td>
                                            <td><?php echo $prescription['end_date'] ? date('M d, Y', strtotime($prescription['end_date'])) : 'Ongoing'; ?></td>
                                            <td><?php echo htmlspecialchars($prescription['notes'] ?: ''); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                    </div>
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