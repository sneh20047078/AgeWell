<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/notification_handler.php';

// Check if user is logged in and is caregiver
if (!isLoggedIn() || $_SESSION['user_role'] !== 'caregiver') {
    setAlert('danger', 'Unauthorized access.');
    redirect('login.php');
}

$pageTitle = 'Caregiver Dashboard';
$userId = $_SESSION['user_id'];

// Get assigned seniors with their details
$assignedSeniors = fetchAll("SELECT s.*, 
                                  sca.assigned_date,
                                  (SELECT COUNT(*) FROM appointments a WHERE a.senior_id = s.id AND a.appointment_datetime >= NOW()) as upcoming_appointments,
                                  (SELECT COUNT(*) FROM healthrecords h WHERE h.senior_id = s.id) as health_records_count
                           FROM senior_caregiver_assignments sca
                           JOIN seniorcitizens s ON sca.senior_id = s.id
                           WHERE sca.caregiver_id = ?
                           ORDER BY s.name ASC", [$userId]);

// Get recent health metrics for assigned seniors
$recentHealthMetrics = fetchAll("SELECT h.*, s.name as senior_name 
                               FROM healthrecords h 
                               JOIN seniorcitizens s ON h.senior_id = s.id
                               JOIN senior_caregiver_assignments sca ON s.id = sca.senior_id
                               WHERE sca.caregiver_id = ?
                               ORDER BY h.recorded_at DESC LIMIT 5", [$userId]);

// Get upcoming appointments for assigned seniors
$upcomingAppointments = fetchAll("SELECT a.*, s.name as senior_name 
                                FROM appointments a 
                                JOIN seniorcitizens s ON a.senior_id = s.id
                                JOIN senior_caregiver_assignments sca ON s.id = sca.senior_id
                                WHERE sca.caregiver_id = ? 
                                AND a.appointment_datetime >= NOW()
                                ORDER BY a.appointment_datetime ASC LIMIT 5", [$userId]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Caregiver Dashboard - AgeWell Senior Care</title>
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

        .stats-card {
            background: #1c1c1c;
            border-radius: 8px;
            padding: 20px;
            transition: all 0.3s;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            border: 1px solid rgba(69, 243, 255, 0.1);
        }

        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(69, 243, 255, 0.2);
        }

        .stats-card .title {
            color: #45f3ff;
            font-size: 14px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 10px;
        }

        .stats-card .value {
            font-size: 32px;
            font-weight: 600;
            color: #ffffff;
            margin-bottom: 15px;
        }

        .stats-card .icon {
            font-size: 28px;
            color: rgba(69, 243, 255, 0.7);
            margin-bottom: 10px;
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

        .badge {
            padding: 5px 8px;
            border-radius: 4px;
            font-weight: 500;
        }

        .bg-warning {
            background-color: #ffd166 !important;
            color: #1c1c1c;
        }

        .bg-success {
            background-color: #4bdb6a !important;
            color: #1c1c1c;
        }

        .bg-danger {
            background-color: #ff3860 !important;
        }

        .bg-info {
            background-color: #45f3ff !important;
            color: #1c1c1c;
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
    <div class="header">
        <div class="logo">
            <i class="fas fa-heartbeat"></i>
            <span>AgeWell Senior Care</span>
        </div>
        <div class="nav-links">
            <a href="dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="senior_citizens.php"><i class="fas fa-users"></i> Senior Citizens</a>
            <a href="appointments.php"><i class="fas fa-calendar-check"></i> Appointments</a>
            <a href="health_metrics.php"><i class="fas fa-heartbeat"></i> Health Metrics</a>
        </div>
        <div class="user-menu">
            <a href="profile.php"><i class="fas fa-user-circle"></i> Profile</a>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <div class="main-container">
        <div class="sidebar">
            <h6>Quick Actions</h6>
            <ul>
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            </ul>
            
            <h6>Quick Reports</h6>
            <ul>
                <li><a href="reports.php?type=seniors"><i class="fas fa-users"></i> Senior Citizens Report</a></li>
                <li><a href="reports.php?type=appointments"><i class="fas fa-calendar-check"></i> Appointments Report</a></li>
                <li><a href="reports.php?type=health_metrics"><i class="fas fa-chart-line"></i> Health Metrics Report</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="page-title">
                <h1>Caregiver Dashboard</h1>
                <div class="quick-access">
                    <a href="health_metric_add.php" class="btn btn-outline-primary">
                        <i class="fas fa-heartbeat"></i> Record Health Metric
                    </a>
                    <a href="appointment_add.php" class="btn btn-outline-primary">
                        <i class="fas fa-calendar-plus"></i> Schedule Appointment
                    </a>
                </div>
            </div>

            <div class="row">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="stats-card glow-effect">
                        <div class="icon"><i class="fas fa-users"></i></div>
                        <div class="title">Assigned Seniors</div>
                        <div class="value"><?php echo count($assignedSeniors); ?></div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="stats-card glow-effect">
                        <div class="icon"><i class="fas fa-calendar-check"></i></div>
                        <div class="title">Today's Appointments</div>
                        <div class="value">
                            <?php 
                            $todayAppointments = array_filter($upcomingAppointments, function($apt) {
                                return date('Y-m-d', strtotime($apt['appointment_datetime'])) === date('Y-m-d');
                            });
                            echo count($todayAppointments);
                            ?>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="stats-card glow-effect">
                        <div class="icon"><i class="fas fa-heartbeat"></i></div>
                        <div class="title">Recent Health Records</div>
                        <div class="value"><?php echo count($recentHealthMetrics); ?></div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="stats-card glow-effect">
                        <div class="icon"><i class="fas fa-tasks"></i></div>
                        <div class="title">Pending Tasks</div>
                        <div class="value"><?php echo count($upcomingAppointments); ?></div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h6>My Assigned Seniors</h6>
                    <span class="badge bg-primary"><?php echo count($assignedSeniors); ?></span>
                </div>
                <div class="card-body">
                    <?php if ($assignedSeniors): ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Age</th>
                                        <th>Contact</th>
                                        <th>Upcoming Appointments</th>
                                        <th>Health Records</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($assignedSeniors as $senior): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($senior['name']); ?></td>
                                            <td><?php echo $senior['age']; ?></td>
                                            <td><?php echo htmlspecialchars($senior['phone']); ?></td>
                                            <td><?php echo $senior['upcoming_appointments']; ?></td>
                                            <td><?php echo $senior['health_records_count']; ?></td>
                                            <td>
                                                <div class="action-buttons">
                                                    <a href="senior_view.php?id=<?php echo $senior['id']; ?>" class="btn btn-primary btn-sm">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="senior_edit.php?id=<?php echo $senior['id']; ?>" class="btn btn-primary btn-sm">
                                                        <i class="fas fa-edit"></i>
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
                            <i class="fas fa-info-circle me-2"></i> No seniors assigned yet.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h6>Recent Health Metrics</h6>
                            <a href="health_metrics.php" class="btn btn-sm btn-primary">
                                <i class="fas fa-list"></i> View All
                            </a>
                        </div>
                        <div class="card-body">
                            <?php if ($recentHealthMetrics): ?>
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Senior</th>
                                                <th>Metric Type</th>
                                                <th>Value</th>
                                                <th>Recorded At</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recentHealthMetrics as $metric): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($metric['senior_name']); ?></td>
                                                    <td><?php echo ucwords(str_replace('_', ' ', $metric['record_type'])); ?></td>
                                                    <td>
                                                        <?php 
                                                        $value = $metric['value'];
                                                        switch ($metric['record_type']) {
                                                            case 'blood_pressure':
                                                                echo $value . ' mmHg';
                                                                break;
                                                            case 'heart_rate':
                                                                echo $value . ' bpm';
                                                                break;
                                                            case 'blood_sugar':
                                                                echo $value . ' mg/dL';
                                                                break;
                                                            case 'temperature':
                                                                echo $value . ' °F';
                                                                break;
                                                            case 'weight':
                                                                echo $value . ' kg';
                                                                break;
                                                            default:
                                                                echo $value;
                                                        }
                                                        ?>
                                                    </td>
                                                    <td><?php echo date('M d, Y g:i A', strtotime($metric['recorded_at'])); ?></td>
                                                    <td>
                                                        <div class="action-buttons">
                                                            <a href="health_metric_view.php?id=<?php echo $metric['id']; ?>" class="btn btn-primary btn-sm">
                                                                <i class="fas fa-eye"></i>
                                                            </a>
                                                            <a href="health_metric_edit.php?id=<?php echo $metric['id']; ?>" class="btn btn-primary btn-sm">
                                                                <i class="fas fa-edit"></i>
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
                                    <i class="fas fa-info-circle me-2"></i> No recent health metrics recorded.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h6>Upcoming Appointments</h6>
                            <a href="appointments.php" class="btn btn-sm btn-primary">
                                <i class="fas fa-list"></i> View All
                            </a>
                        </div>
                        <div class="card-body">
                            <?php if ($upcomingAppointments): ?>
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Senior</th>
                                                <th>Date & Time</th>
                                                <th>Purpose</th>
                                                <th>Location</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($upcomingAppointments as $appointment): ?>
                                                <tr class="<?php echo (date('Y-m-d') === date('Y-m-d', strtotime($appointment['appointment_datetime']))) ? 'table-warning' : ''; ?>">
                                                    <td><?php echo htmlspecialchars($appointment['senior_name']); ?></td>
                                                    <td><?php echo date('M d, Y g:i A', strtotime($appointment['appointment_datetime'])); ?></td>
                                                    <td><?php echo htmlspecialchars($appointment['purpose']); ?></td>
                                                    <td><?php echo htmlspecialchars($appointment['location']); ?></td>
                                                    <td>
                                                        <div class="action-buttons">
                                                            <a href="appointment_view.php?id=<?php echo $appointment['id']; ?>" class="btn btn-primary btn-sm">
                                                                <i class="fas fa-eye"></i>
                                                            </a>
                                                            <a href="appointment_edit.php?id=<?php echo $appointment['id']; ?>" class="btn btn-primary btn-sm">
                                                                <i class="fas fa-edit"></i>
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
                                    <i class="fas fa-info-circle me-2"></i> No upcoming appointments scheduled.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
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

<?php
function getStatusColor($status) {
    switch ($status) {
        case 'scheduled':
            return 'primary';
        case 'completed':
            return 'success';
        case 'cancelled':
            return 'warning';
        case 'no_show':
            return 'danger';
        default:
            return 'secondary';
    }
}
?>