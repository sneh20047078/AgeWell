<?php
/**
 * AgeWell Senior Care and Support System
 * Senior Citizen Profile Page
 */

// Include configuration and required files
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/notification_functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    setAlert('danger', 'Please login to access this page.');
    redirect('login.php');
}

// Set page title
$pageTitle = 'Senior Profile';

// Check if senior ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    setAlert('danger', 'Senior ID is missing.');
    redirect('seniors.php');
}

// Get senior ID and fetch details
$seniorId = (int)$_GET['id'];
$senior = getSeniorById($seniorId);

if (!$senior) {
    setAlert('danger', 'Senior citizen not found.');
    redirect('seniors.php');
}

// Get latest health records
$sql = "SELECT * FROM healthrecords WHERE senior_id = ? ORDER BY recorded_at DESC LIMIT 10";
$healthRecords = fetchAll($sql, [$seniorId], 'i');

// Get upcoming appointments
$sql = "SELECT * FROM appointments WHERE senior_id = ? AND appointment_datetime >= NOW() ORDER BY appointment_datetime ASC LIMIT 5";
$upcomingAppointments = fetchAll($sql, [$seniorId], 'i');

// Get current prescriptions
$sql = "SELECT * FROM prescriptions WHERE senior_id = ? AND (end_date IS NULL OR end_date >= CURDATE()) ORDER BY medication_name ASC";
$currentPrescriptions = fetchAll($sql, [$seniorId], 'i');

// Get health metrics for charts (last 30 days)
$thirtyDaysAgo = date('Y-m-d', strtotime('-30 days'));

// Blood Pressure
$bpSql = "SELECT * FROM healthrecords 
          WHERE senior_id = ? AND record_type = 'blood_pressure' AND DATE(recorded_at) >= ? 
          ORDER BY recorded_at ASC";
$bloodPressureRecords = fetchAll($bpSql, [$seniorId, $thirtyDaysAgo], 'is');

// Heart Rate
$hrSql = "SELECT * FROM healthrecords 
          WHERE senior_id = ? AND record_type = 'heart_rate' AND DATE(recorded_at) >= ? 
          ORDER BY recorded_at ASC";
$heartRateRecords = fetchAll($hrSql, [$seniorId, $thirtyDaysAgo], 'is');

// Blood Sugar
$bsSql = "SELECT * FROM healthrecords 
          WHERE senior_id = ? AND record_type = 'blood_sugar' AND DATE(recorded_at) >= ? 
          ORDER BY recorded_at ASC";
$bloodSugarRecords = fetchAll($bsSql, [$seniorId, $thirtyDaysAgo], 'is');

// Fetch notifications
$appointmentNotifications = getUpcomingAppointmentNotifications($seniorId);
$healthAlerts = getHealthMetricAlerts($seniorId);
$medicationReminders = getMedicationReminders($seniorId);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Senior Profile - AgeWell Senior Care</title>
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

        .table tbody td {
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding: 12px 15px;
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

        .btn-success {
            background: #4bdb6a;
            border-color: #4bdb6a;
            color: #1c1c1c;
        }

        .btn-success:hover {
            background: #3ac258;
            border-color: #3ac258;
            color: #1c1c1c;
        }

        .btn-info {
            background: #45f3ff;
            border-color: #45f3ff;
            color: #1c1c1c;
        }

        .btn-info:hover {
            background: #28d7e5;
            border-color: #28d7e5;
            color: #1c1c1c;
        }

        .btn-warning {
            background: #ffd166;
            border-color: #ffd166;
            color: #1c1c1c;
        }

        .btn-warning:hover {
            background: #e6b954;
            border-color: #e6b954;
            color: #1c1c1c;
        }

        .btn-secondary {
            background: #8f8f8f;
            border-color: #8f8f8f;
            color: #1c1c1c;
        }

        .btn-secondary:hover {
            background: #6f6f6f;
            border-color: #6f6f6f;
            color: #1c1c1c;
        }

        /* Chart container */
        .chart-container {
            position: relative;
            height: 250px;
        }

        /* Profile info item */
        .profile-info-item h6 {
            color: #45f3ff;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 5px;
        }

        .profile-info-item p {
            color: #cdcdcd;
            font-size: 16px;
            margin-bottom: 0;
        }

        .profile-info-item small {
            color: #8f8f8f;
            font-size: 12px;
        }

        /* Glowing effects */
        @keyframes glow {
            0% { box-shadow: 0 0 5px rgba(69, 243, 255, 0.3); }
            50% { box-shadow: 0 0 15px rgba(69, 243, 255, 0.5); }
            100% { box-shadow: 0 0 5px rgba(69, 243, 255, 0.3); }
        }

        .glow-effect {
            animation: glow 2s infinite;
        }

        /* Add these to your existing styles */
        .notification-list {
            max-height: 300px;
            overflow-y: auto;
        }

        .notification-item {
            margin-bottom: 10px;
            padding: 12px 15px;
            border-radius: 6px;
            transition: all 0.3s;
        }

        .notification-item:hover {
            transform: translateX(5px);
        }

        .notification-item:last-child {
            margin-bottom: 0;
        }

        .alert-info {
            background-color: rgba(69, 243, 255, 0.1);
            border-color: rgba(69, 243, 255, 0.3);
            color: #45f3ff;
        }

        .alert-warning {
            background-color: rgba(255, 209, 102, 0.1);
            border-color: rgba(255, 209, 102, 0.3);
            color: #ffd166;
        }

        .alert-success {
            background-color: rgba(75, 219, 106, 0.1);
            border-color: rgba(75, 219, 106, 0.3);
            color: #4bdb6a;
        }

        .notification-item small {
            opacity: 0.8;
        }

        .notification-item i {
            width: 20px;
            text-align: center;
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
            <a href="seniors.php" class="active"><i class="fas fa-users"></i> Senior Citizens</a>
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
                <li><a href="senior_edit.php?id=<?php echo $seniorId; ?>"><i class="fas fa-edit"></i> Edit Profile</a></li>
            </ul>
        </div>
        
        <!-- Main content -->
        <div class="main-content">
            <div class="page-title">
                <h1><i class="fas fa-user me-2"></i> Senior Profile</h1>
                <div class="btn-group">
                    <a href="generate_pdf.php?id=<?php echo $seniorId; ?>" class="btn btn-secondary" target="_blank">
                            <i class="fas fa-file-pdf me-1"></i> Download PDF
                        </a>
                    <a href="seniors.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Back to List
                    </a>
                </div>
            </div>

            <!-- Add Notifications Section -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h6><i class="fas fa-bell me-2"></i>Notifications</h6>
                        </div>
                        <div class="card-body">
                            <?php if (empty($appointmentNotifications) && empty($healthAlerts) && empty($medicationReminders)): ?>
                                <p class="text-center">No active notifications</p>
                            <?php else: ?>
                                <div class="notification-list">
                                    <!-- Appointment Notifications -->
                                    <?php foreach ($appointmentNotifications as $notification): ?>
                                        <div class="notification-item alert alert-info">
                                            <i class="fas fa-calendar-check me-2"></i>
                                            Upcoming Appointment: <?php echo date('M d, Y g:i A', strtotime($notification['appointment_datetime'])); ?>
                                            with Dr. <?php echo htmlspecialchars($notification['doctor_name']); ?>
                                            <small class="ms-2">
                                                (<?php echo htmlspecialchars($notification['purpose']); ?>)
                                            </small>
                                        </div>
                                    <?php endforeach; ?>

                                    <!-- Health Metric Alerts -->
                                    <?php foreach ($healthAlerts as $alert): ?>
                                        <div class="notification-item alert alert-warning">
                                            <i class="fas fa-exclamation-triangle me-2"></i>
                                            <?php echo htmlspecialchars($alert['message']); ?>
                                            <small class="ms-2">
                                                (Recorded: <?php echo date('M d, Y', strtotime($alert['date'])); ?>)
                                            </small>
                                        </div>
                                    <?php endforeach; ?>

                                    <!-- Medication Reminders -->
                                    <?php foreach ($medicationReminders as $reminder): ?>
                                        <div class="notification-item alert alert-success">
                                            <i class="fas fa-pills me-2"></i>
                                            Medication Reminder: <?php echo htmlspecialchars($reminder['medication_name']); ?>
                                            (<?php echo htmlspecialchars($reminder['dosage']); ?>)
                                            <small class="ms-2">
                                                <?php echo htmlspecialchars($reminder['frequency']); ?>
                                            </small>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Add this after your notifications section -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h6><i class="fas fa-history me-2"></i>Notification History</h6>
                        </div>
                        <div class="card-body">
                            <?php
                            $notificationHistory = fetchAll(
                                "SELECT * FROM notification_logs 
                                 WHERE senior_id = ? 
                                 ORDER BY created_at DESC 
                                 LIMIT 10",
                                [$seniorId],
                                'i'
                            );
                            
                            if ($notificationHistory): ?>
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Type</th>
                                                <th>Message</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($notificationHistory as $notification): ?>
                                                <tr>
                                                    <td><?php echo date('M d, Y g:i A', strtotime($notification['created_at'])); ?></td>
                                                    <td><?php echo ucfirst($notification['type']); ?></td>
                                                    <td><?php echo htmlspecialchars($notification['message']); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $notification['status'] === 'sent' ? 'success' : 'danger'; ?>">
                                                            <?php echo ucfirst($notification['status']); ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-center">No notification history available</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Senior Profile Overview -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card glow-effect">
                        <div class="card-header">
                            <h6>Basic Information</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <div class="profile-info-item">
                                        <h6>Full Name</h6>
                                        <p><?php echo htmlspecialchars($senior['name']); ?></p>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="profile-info-item">
                                        <h6>Age</h6>
                                        <p><?php echo htmlspecialchars($senior['age']); ?></p>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="profile-info-item">
                                        <h6>Gender</h6>
                                        <p><?php echo ucfirst(htmlspecialchars($senior['gender'])); ?></p>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="profile-info-item">
                                        <h6>Phone Number</h6>
                                        <p><?php echo htmlspecialchars($senior['phone']); ?></p>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="profile-info-item">
                                        <h6>Emergency Contact</h6>
                                        <p><?php echo htmlspecialchars($senior['emergency_contact']); ?> (<?php echo htmlspecialchars($senior['emergency_phone']); ?>)</p>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="profile-info-item">
                                        <h6>Address</h6>
                                        <p><?php echo htmlspecialchars($senior['address']); ?></p>
                                    </div>
                                </div>
                                <div class="col-md-12 mb-3">
                                    <div class="profile-info-item">
                                        <h6>Medical History</h6>
                                        <p><?php echo nl2br(htmlspecialchars($senior['medical_history'])); ?></p>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="profile-info-item">
                                        <h6>Current Medications</h6>
                                        <p><?php echo nl2br(htmlspecialchars($senior['medications'])); ?></p>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="profile-info-item">
                                        <h6>Allergies</h6>
                                        <p><?php echo nl2br(htmlspecialchars($senior['allergies'])); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Health Metrics Charts -->
            <div class="row mb-4">
                <div class="col-md-6 mb-4">
                    <div class="card glow-effect">
                        <div class="card-header">
                            <h6>Blood Pressure (Last 30 Days)</h6>
                            <a href="health_metrics.php?senior_id=<?php echo $seniorId; ?>&type=blood_pressure" class="btn btn-primary btn-sm">View All</a>
                        </div>
                        <div class="card-body">
                            <?php if ($bloodPressureRecords && count($bloodPressureRecords) > 0): ?>
                                <div class="chart-container">
                                    <canvas id="bloodPressureChart"></canvas>
                                </div>
                            <?php else: ?>
                                <p class="text-center">No blood pressure data available for the last 30 days.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 mb-4">
                    <div class="card glow-effect">
                        <div class="card-header">
                            <h6>Heart Rate (Last 30 Days)</h6>
                            <a href="health_metrics.php?senior_id=<?php echo $seniorId; ?>&type=heart_rate" class="btn btn-primary btn-sm">View All</a>
                        </div>
                        <div class="card-body">
                            <?php if ($heartRateRecords && count($heartRateRecords) > 0): ?>
                                <div class="chart-container">
                                    <canvas id="heartRateChart"></canvas>
                                </div>
                            <?php else: ?>
                                <p class="text-center">No heart rate data available for the last 30 days.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 mb-4">
                    <div class="card glow-effect">
                        <div class="card-header">
                            <h6>Blood Sugar (Last 30 Days)</h6>
                            <a href="health_metrics.php?senior_id=<?php echo $seniorId; ?>&type=blood_sugar" class="btn btn-primary btn-sm">View All</a>
                        </div>
                        <div class="card-body">
                            <?php if ($bloodSugarRecords && count($bloodSugarRecords) > 0): ?>
                                <div class="chart-container">
                                    <canvas id="bloodSugarChart"></canvas>
                                </div>
                            <?php else: ?>
                                <p class="text-center">No blood sugar data available for the last 30 days.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 mb-4">
                    <div class="card glow-effect">
                        <div class="card-header">
                            <h6>Latest Health Metrics</h6>
                        </div>
                        <div class="card-body">
                            <?php
                            $latestBP = fetchOne("SELECT * FROM healthrecords WHERE senior_id = ? AND record_type = 'blood_pressure' ORDER BY recorded_at DESC LIMIT 1", [$seniorId], 'i');
                            $latestHR = fetchOne("SELECT * FROM healthrecords WHERE senior_id = ? AND record_type = 'heart_rate' ORDER BY recorded_at DESC LIMIT 1", [$seniorId], 'i');
                            $latestBS = fetchOne("SELECT * FROM healthrecords WHERE senior_id = ? AND record_type = 'blood_sugar' ORDER BY recorded_at DESC LIMIT 1", [$seniorId], 'i');
                            $latestTemp = fetchOne("SELECT * FROM healthrecords WHERE senior_id = ? AND record_type = 'temperature' ORDER BY recorded_at DESC LIMIT 1", [$seniorId], 'i');
                            ?>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <div class="profile-info-item">
                                        <h6>Blood Pressure</h6>
                                        <p><?php echo $latestBP ? $latestBP['value'] . ' mmHg' : 'N/A'; ?></p>
                                        <small><?php echo $latestBP ? 'Recorded: ' . date('M d, Y', strtotime($latestBP['recorded_at'])) : ''; ?></small>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="profile-info-item">
                                        <h6>Heart Rate</h6>
                                        <p><?php echo $latestHR ? $latestHR['value'] . ' bpm' : 'N/A'; ?></p>
                                        <small><?php echo $latestHR ? 'Recorded: ' . date('M d, Y', strtotime($latestHR['recorded_at'])) : ''; ?></small>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="profile-info-item">
                                        <h6>Blood Sugar</h6>
                                        <p><?php echo $latestBS ? $latestBS['value'] . ' mg/dL' : 'N/A'; ?></p>
                                        <small><?php echo $latestBS ? 'Recorded: ' . date('M d, Y', strtotime($latestBS['recorded_at'])) : ''; ?></small>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="profile-info-item">
                                        <h6>Temperature</h6>
                                        <p><?php echo $latestTemp ? $latestTemp['value'] . ' °F' : 'N/A'; ?></p>
                                        <small><?php echo $latestTemp ? 'Recorded: ' . date('M d, Y', strtotime($latestTemp['recorded_at'])) : ''; ?></small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Upcoming Appointments and Current Prescriptions -->
            <div class="row">
                <div class="col-md-6 mb-4">
                    <div class="card glow-effect">
                        <div class="card-header">
                            <h6>Upcoming Appointments</h6>
                            <a href="appointments.php?senior_id=<?php echo $seniorId; ?>" class="btn btn-primary btn-sm">View All</a>
                        </div>
                        <div class="card-body">
                            <?php if ($upcomingAppointments && count($upcomingAppointments) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Date & Time</th>
                                                <th>Doctor</th>
                                                <th>Purpose</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($upcomingAppointments as $appointment): ?>
                                                <tr>
                                                    <td><?php echo date('M d, Y g:i A', strtotime($appointment['appointment_datetime'])); ?></td>
                                                    <td><?php echo htmlspecialchars($appointment['doctor_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($appointment['purpose']); ?></td>
                                                    <td>
                                                        <a href="appointment_edit.php?id=<?php echo $appointment['id']; ?>" class="btn btn-primary btn-sm">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-center">No upcoming appointments found.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 mb-4">
                    <div class="card glow-effect">
                        <div class="card-header">
                            <h6>Current Prescriptions</h6>
                        </div>
                        <div class="card-body">
                            <?php if ($currentPrescriptions && count($currentPrescriptions) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Medication</th>
                                                <th>Dosage</th>
                                                <th>Frequency</th>
                                                <th>Prescribed By</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($currentPrescriptions as $prescription): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($prescription['medication_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($prescription['dosage']); ?></td>
                                                    <td><?php echo htmlspecialchars($prescription['frequency']); ?></td>
                                                    <td><?php echo htmlspecialchars($prescription['doctor_name']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-center">No current prescriptions found.</p>
                            <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/charts.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Blood Pressure Chart
        <?php if ($bloodPressureRecords && count($bloodPressureRecords) > 0): ?>
            const bpDates = [];
            const systolicData = [];
            const diastolicData = [];
            <?php foreach ($bloodPressureRecords as $record): ?>
                bpDates.push('<?php echo date('M d', strtotime($record['recorded_at'])); ?>');
                <?php
                $bpParts = explode('/', $record['value']);
                $systolic = isset($bpParts[0]) ? trim($bpParts[0]) : null;
                $diastolic = isset($bpParts[1]) ? trim($bpParts[1]) : null;
                ?>
                systolicData.push(<?php echo $systolic ?? 'null'; ?>);
                diastolicData.push(<?php echo $diastolic ?? 'null'; ?>);
            <?php endforeach; ?>
            createBloodPressureChart('bloodPressureChart', bpDates, systolicData, diastolicData);
        <?php endif; ?>
        
        // Heart Rate Chart
        <?php if ($heartRateRecords && count($heartRateRecords) > 0): ?>
            const hrDates = [];
            const heartRateData = [];
            <?php foreach ($heartRateRecords as $record): ?>
                hrDates.push('<?php echo date('M d', strtotime($record['recorded_at'])); ?>');
                heartRateData.push(<?php echo $record['value']; ?>);
            <?php endforeach; ?>
            createHeartRateChart('heartRateChart', hrDates, heartRateData);
        <?php endif; ?>
        
        // Blood Sugar Chart
        <?php if ($bloodSugarRecords && count($bloodSugarRecords) > 0): ?>
            const bsDates = [];
            const bloodSugarData = [];
            <?php foreach ($bloodSugarRecords as $record): ?>
                bsDates.push('<?php echo date('M d', strtotime($record['recorded_at'])); ?>');
                bloodSugarData.push(<?php echo $record['value']; ?>);
            <?php endforeach; ?>
            createBloodSugarChart('bloodSugarChart', bsDates, bloodSugarData);
        <?php endif; ?>

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