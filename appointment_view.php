<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    setAlert('danger', 'Please login to access this page.');
    redirect('login.php');
}

$pageTitle = 'View Appointment';

// Get appointment ID from URL
$appointmentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch appointment details with related information
$appointment = fetchOne("SELECT 
    a.*,
    s.name as senior_name,
    s.phone as senior_phone,
    s.emergency_contact as emergency_contact,
    s.emergency_phone as emergency_phone,
    COALESCE(u.name, 'Unassigned') as caregiver_name
    FROM appointments a
    INNER JOIN seniorcitizens s ON a.senior_id = s.id
    LEFT JOIN userdetails u ON a.caregiver_id = u.id
    WHERE a.id = ?", [$appointmentId], 'i');

if (!$appointment) {
    setAlert('danger', 'Appointment not found.');
    redirect('appointments.php');
}
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

        .card-header h5 {
            color: #45f3ff;
            margin: 0;
            font-weight: 500;
            font-size: 16px;
        }

        .card-body {
            padding: 20px;
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

        .badge {
            padding: 5px 8px;
            border-radius: 4px;
            font-weight: 500;
        }

        .bg-primary { background-color: #45f3ff !important; color: #1c1c1c; }
        .bg-success { background-color: #4bdb6a !important; color: #1c1c1c; }
        .bg-warning { background-color: #ffd166 !important; color: #1c1c1c; }
        .bg-danger { background-color: #ff3860 !important; }
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
            <a href="appointments.php" class="active"><i class="fas fa-calendar-check"></i> Appointments</a>
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
                <li><a href="appointment_add.php"><i class="fas fa-calendar-plus"></i> Add Appointment</a></li>
            </ul>
            
            <h6>Navigation</h6>
            <ul>
                <li><a href="appointments.php"><i class="fas fa-calendar-check"></i> All Appointments</a></li>
                <li><a href="senior_citizens.php"><i class="fas fa-users"></i> Senior Citizens</a></li>
            </ul>
        </div>
        
        <!-- Main content -->
        <div class="main-content">
            <div class="page-title">
                <h1>View Appointment</h1>
                <div class="btn-toolbar">
                    <a href="appointments.php" class="btn btn-secondary me-2">
                        <i class="fas fa-arrow-left"></i> Back to List
                    </a>
                    <a href="appointment_edit.php?id=<?php echo $appointmentId; ?>" class="btn btn-primary">
                        <i class="fas fa-edit"></i> Edit Appointment
                    </a>
                </div>
            </div>

            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5>Appointment Details</h5>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <h6 style="color: #45f3ff;">Date & Time</h6>
                                    <p><?php echo date('F d, Y h:i A', strtotime($appointment['appointment_datetime'])); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <h6 style="color: #45f3ff;">Status</h6>
                                    <span class="badge bg-<?php echo getStatusColor($appointment['status']); ?>">
                                        <?php echo ucfirst($appointment['status']); ?>
                                    </span>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <h6 style="color: #45f3ff;">Doctor</h6>
                                    <p><?php echo htmlspecialchars($appointment['doctor_name']); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <h6 style="color: #45f3ff;">Location</h6>
                                    <p><?php echo htmlspecialchars($appointment['location']); ?></p>
                                </div>
                            </div>

                            <div class="mb-3">
                                <h6 style="color: #45f3ff;">Purpose</h6>
                                <p><?php echo htmlspecialchars($appointment['purpose']); ?></p>
                            </div>

                            <div class="mb-3">
                                <h6 style="color: #45f3ff;">Notes</h6>
                                <p><?php echo nl2br(htmlspecialchars($appointment['notes'] ?? 'No notes available')); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5>Senior Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <h6 style="color: #45f3ff;">Name</h6>
                                <p><?php echo htmlspecialchars($appointment['senior_name']); ?></p>
                            </div>
                            <div class="mb-3">
                                <h6 style="color: #45f3ff;">Contact Number</h6>
                                <p><?php echo htmlspecialchars($appointment['senior_phone']); ?></p>
                            </div>
                            <div class="mb-3">
                                <h6 style="color: #45f3ff;">Emergency Contact</h6>
                                <p><?php echo htmlspecialchars($appointment['emergency_contact']); ?></p>
                            </div>
                            <div class="mb-3">
                                <h6 style="color: #45f3ff;">Emergency Phone</h6>
                                <p><?php echo htmlspecialchars($appointment['emergency_phone']); ?></p>
                            </div>
                            <div class="mb-3">
                                <h6 style="color: #45f3ff;">Assigned Caregiver</h6>
                                <p><?php echo htmlspecialchars($appointment['caregiver_name']); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
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