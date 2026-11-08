<?php
/**
 * AgeWell Senior Care and Support System
 * Dashboard Page
 */

// Include configuration and required files
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/notification_handler.php';

// Check if user is logged in
if (!isLoggedIn()) {
    setAlert('danger', 'Please login to access the dashboard.');
    redirect('login.php');
}

// Redirect to appropriate dashboard based on role
if ($_SESSION['user_role'] === 'admin') {
    redirect('admin_dashboard.php');
} else if ($_SESSION['user_role'] === 'caregiver') {
    redirect('caregiver_dashboard.php');
} else {
    setAlert('danger', 'Invalid user role.');
    redirect('login.php');
}

// Set page title
$pageTitle = 'Dashboard';

// Get user-specific data for dashboard
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'];

// Get count data for dashboard cards
$totalSeniorsCount = getTotalSeniorsCount();
$totalCaregiversCount = getTotalCaregiversCount();
$totalAppointmentsCount = getTotalAppointmentsCount();
$upcomingAppointmentsCount = getUpcomingAppointmentsCount();

// Get recent appointments
$sql = "SELECT a.*, s.name as senior_name 
        FROM appointments a 
        JOIN seniorcitizens s ON a.senior_id = s.id 
        WHERE a.appointment_datetime >= NOW() 
        ORDER BY a.appointment_datetime ASC 
        LIMIT 5";
$recentAppointments = fetchAll($sql);

// Get recent health metrics
$sql = "SELECT h.*, s.name as senior_name 
        FROM healthrecords h 
        JOIN seniorcitizens s ON h.senior_id = s.id 
        ORDER BY h.recorded_at DESC 
        LIMIT 5";
$recentHealthMetrics = fetchAll($sql);

// Get unread notifications
$unreadNotifications = getUserNotifications($userId, 5, true);

// Get information for charts
// Last 7 days of appointments
$currentDate = date('Y-m-d');
$pastWeekDate = date('Y-m-d', strtotime('-6 days'));

$sql = "SELECT DATE(appointment_datetime) as date, COUNT(*) as count 
        FROM appointments 
        WHERE DATE(appointment_datetime) BETWEEN ? AND ? 
        GROUP BY DATE(appointment_datetime) 
        ORDER BY date ASC";
$appointmentStats = fetchAll($sql, [$pastWeekDate, $currentDate], 'ss');

// Format data for chart.js
$appointmentDates = [];
$appointmentCounts = [];

// Initialize all dates for the past week
for ($i = 0; $i < 7; $i++) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $appointmentDates[6-$i] = date('M d', strtotime($date));
    $appointmentCounts[6-$i] = 0;
}

// Fill in actual data
if ($appointmentStats) {
    foreach ($appointmentStats as $stat) {
        $dayIndex = 6 - (strtotime($currentDate) - strtotime($stat['date'])) / 86400;
        $appointmentCounts[$dayIndex] = (int)$stat['count'];
    }
}

// For Pie chart: Senior Citizens by Age
$sql = "SELECT 
            CASE 
                WHEN age < 70 THEN '60-69'
                WHEN age BETWEEN 70 AND 79 THEN '70-79'
                WHEN age BETWEEN 80 AND 89 THEN '80-89'
                ELSE '90+' 
            END AS age_group,
            COUNT(*) as count
        FROM seniorcitizens
        GROUP BY age_group
        ORDER BY age_group";
$seniorAgeGroups = fetchAll($sql);

// Include header
require_once 'includes/header.php';

// Add Chart.js to extraJS
$extraJS = '
<script src="assets/js/charts.js"></script>
';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-3 col-lg-2">
            <?php require_once 'includes/sidebar.php'; ?>
        </div>
        
        <!-- Main Content -->
        <div class="col-md-9 col-lg-10">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="fas fa-tachometer-alt me-2"></i> Dashboard</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <a href="health_metric_add.php" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-heartbeat me-1"></i> Record Health Metric
                        </a>
                        <a href="appointment_add.php" class="btn btn-sm btn-outline-success">
                            <i class="fas fa-calendar-plus me-1"></i> Schedule Appointment
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Dashboard Cards -->
            <div class="row">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card dashboard-card card-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col me-2">
                                    <h5 class="text-uppercase mb-1">Seniors</h5>
                                    <h2 class="mb-0"><?php echo $totalSeniorsCount; ?></h2>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-users fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if (isAdmin()): ?>
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card dashboard-card card-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col me-2">
                                    <h5 class="text-uppercase mb-1">Caregivers</h5>
                                    <h2 class="mb-0"><?php echo $totalCaregiversCount; ?></h2>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-user-nurse fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card dashboard-card card-info shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col me-2">
                                    <h5 class="text-uppercase mb-1">Total Appointments</h5>
                                    <h2 class="mb-0"><?php echo $totalAppointmentsCount; ?></h2>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-calendar-check fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card dashboard-card card-warning shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col me-2">
                                    <h5 class="text-uppercase mb-1">Upcoming Appointments</h5>
                                    <h2 class="mb-0"><?php echo $upcomingAppointmentsCount; ?></h2>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-calendar-day fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Main Dashboard Content -->
            <div class="row">
                <!-- Left Column -->
                <div class="col-lg-8">
                    <!-- Appointment Chart -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                            <h6 class="m-0 font-weight-bold text-primary">Appointments (Last 7 Days)</h6>
                        </div>
                        <div class="card-body">
                            <div class="chart-container" style="position: relative; height:300px;">
                                <canvas id="appointmentChart"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recent Appointments -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                            <h6 class="m-0 font-weight-bold text-primary">Upcoming Appointments</h6>
                            <a href="appointments.php" class="btn btn-sm btn-primary">
                                View All
                            </a>
                        </div>
                        <div class="card-body">
                            <?php if ($recentAppointments && count($recentAppointments) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Senior</th>
                                                <th>Doctor</th>
                                                <th>Date & Time</th>
                                                <th>Purpose</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recentAppointments as $appointment): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($appointment['senior_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($appointment['doctor_name']); ?></td>
                                                    <td><?php echo date('M d, Y g:i A', strtotime($appointment['appointment_datetime'])); ?></td>
                                                    <td><?php echo htmlspecialchars($appointment['purpose']); ?></td>
                                                    <td>
                                                        <a href="appointment_edit.php?id=<?php echo $appointment['id']; ?>" class="btn btn-sm btn-outline-primary">
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
                    
                    <!-- Recent Health Metrics -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                            <h6 class="m-0 font-weight-bold text-primary">Recent Health Metrics</h6>
                            <a href="health_metrics.php" class="btn btn-sm btn-primary">
                                View All
                            </a>
                        </div>
                        <div class="card-body">
                            <?php if ($recentHealthMetrics && count($recentHealthMetrics) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Senior</th>
                                                <th>Metric</th>
                                                <th>Value</th>
                                                <th>Recorded At</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recentHealthMetrics as $metric): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($metric['senior_name']); ?></td>
                                                    <td>
                                                        <?php 
                                                        $metricType = str_replace('_', ' ', $metric['record_type']);
                                                        echo ucwords($metricType); 
                                                        ?>
                                                    </td>
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
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-center">No recent health metrics found.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Right Column -->
                <div class="col-lg-4">
                    <!-- Unread Notifications -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                            <h6 class="m-0 font-weight-bold text-primary">Unread Notifications</h6>
                            <a href="notifications.php" class="btn btn-sm btn-primary">
                                View All
                            </a>
                        </div>
                        <div class="card-body">
                            <?php if ($unreadNotifications && count($unreadNotifications) > 0): ?>
                                <div class="notification-list">
                                    <?php foreach ($unreadNotifications as $notification): ?>
                                        <div class="notification-item unread <?php echo $notification['type']; ?>">
                                            <div class="title">
                                                <?php if ($notification['is_emergency']): ?>
                                                    <span class="badge bg-danger">EMERGENCY</span>
                                                <?php endif; ?>
                                                <?php echo htmlspecialchars($notification['title']); ?>
                                            </div>
                                            <div class="message">
                                                <?php echo htmlspecialchars($notification['message']); ?>
                                            </div>
                                            <div class="meta">
                                                <span><?php echo date('M d, Y g:i A', strtotime($notification['created_at'])); ?></span>
                                                <button class="btn btn-sm btn-outline-primary mark-notification-read" 
                                                        data-notification-id="<?php echo $notification['id']; ?>">
                                                    <i class="fas fa-check me-1"></i> Mark as Read
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-center">No unread notifications.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Seniors by Age Group -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Seniors by Age Group</h6>
                        </div>
                        <div class="card-body">
                            <div class="chart-container" style="position: relative; height:250px;">
                                <canvas id="seniorAgeChart"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Quick Links -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Quick Links</h6>
                        </div>
                        <div class="card-body">
                            <div class="list-group">
                                <a href="appointment_add.php" class="list-group-item list-group-item-action">
                                    <i class="fas fa-calendar-plus me-2"></i> Schedule Appointment
                                </a>
                                <a href="health_metric_add.php" class="list-group-item list-group-item-action">
                                    <i class="fas fa-heartbeat me-2"></i> Record Health Metric
                                </a>
                                <?php if (isAdmin()): ?>
                                <a href="reports.php" class="list-group-item list-group-item-action">
                                    <i class="fas fa-chart-bar me-2"></i> Generate Reports
                                </a>
                                <a href="user_add.php" class="list-group-item list-group-item-action">
                                    <i class="fas fa-user-plus me-2"></i> Add New User
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Appointment Chart
        const appointmentChartCtx = document.getElementById('appointmentChart').getContext('2d');
        new Chart(appointmentChartCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($appointmentDates); ?>,
                datasets: [{
                    label: 'Appointments',
                    data: <?php echo json_encode($appointmentCounts); ?>,
                    backgroundColor: 'rgba(78, 115, 223, 0.2)',
                    borderColor: 'rgba(78, 115, 223, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });
        
        // Senior Age Group Chart
        <?php if ($seniorAgeGroups && count($seniorAgeGroups) > 0): ?>
            const ageLabels = [];
            const ageCounts = [];
            
            <?php foreach ($seniorAgeGroups as $group): ?>
                ageLabels.push('<?php echo $group['age_group']; ?>');
                ageCounts.push(<?php echo $group['count']; ?>);
            <?php endforeach; ?>
            
            const seniorAgeChartCtx = document.getElementById('seniorAgeChart').getContext('2d');
            createPieChart(
                'seniorAgeChart', 
                ageLabels, 
                ageCounts, 
                ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e'],
                'Seniors by Age Group'
            );
        <?php endif; ?>
    });
</script>

<?php
// Include footer
require_once 'includes/footer.php';
?>
