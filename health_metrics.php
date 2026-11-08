<?php
/**
 * AgeWell Senior Care and Support System
 * Health Metrics Page
 */

require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    setAlert('danger', 'Please login to access this page.');
    redirect('login.php');
}

$pageTitle = 'Health Metrics';

$senior = null;
$senior_id = isset($_GET['senior_id']) ? (int)$_GET['senior_id'] : null;
$metric_type = isset($_GET['type']) ? cleanInput($_GET['type']) : null;
$from_date = isset($_GET['from_date']) ? cleanInput($_GET['from_date']) : date('Y-m-d', strtotime('-30 days'));
$to_date = isset($_GET['to_date']) ? cleanInput($_GET['to_date']) : date('Y-m-d');

if ($senior_id) {
    $senior = getSeniorById($senior_id);
    if (!$senior) {
        setAlert('danger', 'Senior citizen not found.');
        redirect('health_metrics.php');
    }
}

$whereConditions = [];
$params = [];
$types = '';

if ($senior_id) {
    $whereConditions[] = 'h.senior_id = ?';
    $params[] = $senior_id;
    $types .= 'i';
}

if ($metric_type) {
    $whereConditions[] = 'h.record_type = ?';
    $params[] = $metric_type;
    $types .= 's';
}

if ($from_date) {
    $whereConditions[] = 'DATE(h.recorded_at) >= ?';
    $params[] = $from_date;
    $types .= 's';
}

if ($to_date) {
    $whereConditions[] = 'DATE(h.recorded_at) <= ?';
    $params[] = $to_date;
    $types .= 's';
}

$validMetricTypes = ['blood_pressure', 'heart_rate', 'blood_sugar', 'temperature', 'weight'];
if (!empty($metric_type) && !in_array($metric_type, $validMetricTypes)) {
    $metric_type = null;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

$sql = "SELECT h.*, s.name as senior_name 
        FROM healthrecords h 
        JOIN seniorcitizens s ON h.senior_id = s.id 
        $whereClause 
        ORDER BY h.recorded_at DESC";

$metrics = fetchAll($sql, $params, $types);
$seniors = fetchAll("SELECT id, name FROM seniorcitizens ORDER BY name ASC");

$chartLabels = [];
$chartData = [];
$systolicData = [];
$diastolicData = [];

if ($metrics && count($metrics) > 0) {
    foreach ($metrics as $metric) {
        $chartLabels[] = date('M d, Y', strtotime($metric['recorded_at']));
        if ($metric['record_type'] === 'blood_pressure') {
            $bpParts = explode('/', $metric['value']);
            $systolic = isset($bpParts[0]) ? trim($bpParts[0]) : null;
            $diastolic = isset($bpParts[1]) ? trim($bpParts[1]) : null;
            if ($systolic !== null) $systolicData[] = $systolic;
            if ($diastolic !== null) $diastolicData[] = $diastolic;
        } else {
            $chartData[] = $metric['value'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Health Metrics - AgeWell Senior Care</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="assets/js/charts.js"></script>
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

        .form-select, .form-control {
            background: #28292d;
            border: 1px solid rgba(69, 243, 255, 0.3);
            color: #cdcdcd;
            padding: 8px;
            border-radius: 4px;
        }

        .form-select:focus, .form-control:focus {
            border-color: #45f3ff;
            box-shadow: 0 0 5px rgba(69, 243, 255, 0.5);
            background: #28292d;
            color: #cdcdcd;
        }

        .form-label {
            color: #45f3ff;
            font-size: 14px;
            font-weight: 500;
        }

        .chart-container {
            position: relative;
            height: 400px;
        }

        .dropdown-menu {
            background: #28292d;
            border: 1px solid rgba(69, 243, 255, 0.3);
        }

        .dropdown-item {
            color: #cdcdcd;
        }

        .dropdown-item:hover {
            background: rgba(69, 243, 255, 0.1);
            color: #45f3ff;
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
            <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="senior_citizens.php"><i class="fas fa-users"></i> Senior Citizens</a>
            <a href="appointments.php"><i class="fas fa-calendar-check"></i> Appointments</a>
            <a href="health_metrics.php" class="active"><i class="fas fa-heartbeat"></i> Health Metrics</a>
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
                <?php if (!isAdmin()): // Only show for caregivers, not admins ?>
                <li><a href="health_metric_add.php"><i class="fas fa-plus"></i> Add Health Metric</a></li>
                <?php endif; ?>
            </ul>
            <h6>Navigation</h6>
            <ul>
                <li><a href="senior_citizens.php"><i class="fas fa-users"></i> Senior Citizens</a></li>
                <li><a href="reports.php?type=health_metrics"><i class="fas fa-chart-line"></i> Health Reports</a></li>
            </ul>
        </div>

        <div class="main-content">
            <div class="page-title">
                <h1>
                    <i class="fas fa-heartbeat me-2"></i> Health Metrics
                    <?php if ($senior): ?> for <?php echo htmlspecialchars($senior['name']); ?><?php endif; ?>
                </h1>
                <div class="btn-toolbar">
                    <?php if (!isAdmin()): // Only show for caregivers, not admins ?>
                    <a href="health_metric_add.php" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i> Add Health Metric
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <form method="GET" action="health_metrics.php" class="row g-3">
                        <div class="col-md-3">
                            <label for="senior_id" class="form-label">Senior Citizen</label>
                            <select class="form-select" id="senior_id" name="senior_id">
                                <option value="">All Seniors</option>
                                <?php foreach ($seniors as $seniorOption): ?>
                                    <option value="<?php echo $seniorOption['id']; ?>" 
                                            <?php echo $senior_id == $seniorOption['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($seniorOption['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="type" class="form-label">Metric Type</label>
                            <select class="form-select" id="type" name="type">
                                <option value="">All Types</option>
                                <option value="blood_pressure" <?php echo $metric_type === 'blood_pressure' ? 'selected' : ''; ?>>Blood Pressure</option>
                                <option value="heart_rate" <?php echo $metric_type === 'heart_rate' ? 'selected' : ''; ?>>Heart Rate</option>
                                <option value="blood_sugar" <?php echo $metric_type === 'blood_sugar' ? 'selected' : ''; ?>>Blood Sugar</option>
                                <option value="temperature" <?php echo $metric_type === 'temperature' ? 'selected' : ''; ?>>Temperature</option>
                                <option value="weight" <?php echo $metric_type === 'weight' ? 'selected' : ''; ?>>Weight</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="from_date" class="form-label">From Date</label>
                            <input type="date" class="form-control" id="from_date" name="from_date" 
                                   value="<?php echo $from_date; ?>">
                        </div>
                        <div class="col-md-2">
                            <label for="to_date" class="form-label">To Date</label>
                            <input type="date" class="form-control" id="to_date" name="to_date" 
                                   value="<?php echo $to_date; ?>">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-filter me-1"></i> Apply
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h6>
                        <?php
                        $chartTitle = "Health Metrics";
                        if ($metric_type) {
                            $metricName = str_replace('_', ' ', $metric_type);
                            $chartTitle = ucwords($metricName) . " Trend";
                        }
                        if ($senior) {
                            $chartTitle .= " for " . $senior['name'];
                        }
                        echo $chartTitle;
                        ?>
                    </h6>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" 
                                id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                            <li><a class="dropdown-item" href="#" id="downloadChartBtn">Download Chart</a></li>
                            <li><a class="dropdown-item" href="reports.php?type=health_metrics<?php echo $senior_id ? "&senior_id=$senior_id" : ""; ?>">Generate Report</a></li>
                        </ul>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (($metrics && count($metrics) > 0) && ($metric_type || $senior_id)): ?>
                        <div class="chart-container">
                            <canvas id="healthMetricChart"></canvas>
                        </div>
                    <?php elseif ($metrics && count($metrics) > 0): ?>
                        <div class="alert alert-info">
                            <p>Please select a specific metric type or senior citizen to display a chart.</p>
                        </div>
                        <div class="table-responsive mt-3">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Senior Name</th>
                                        <th>Metric Type</th>
                                        <th>Value</th>
                                        <th>Recorded At</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($metrics as $metric): ?>
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
                                                <a href="medical_record_edit.php?id=<?php echo $metric['id']; ?>" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-edit"></i> Edit
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <p>No health metrics found for the selected filters.</p>
                            <?php if (!isAdmin()): // Only show for caregivers, not admins ?>
                            <p><a href="health_metric_add.php" class="alert-link">Add a new health metric</a> to get started.</p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (($metrics && count($metrics) > 0) && ($metric_type || $senior_id)): ?>
                <div class="card">
                    <div class="card-header">
                        <h6>Detailed Metrics Data</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <?php if (!$senior_id): ?><th>Senior Name</th><?php endif; ?>
                                        <?php if (!$metric_type): ?><th>Metric Type</th><?php endif; ?>
                                        <th>Value</th>
                                        <th>Recorded At</th>
                                        <th>Notes</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($metrics as $metric): ?>
                                        <tr>
                                            <?php if (!$senior_id): ?>
                                                <td><?php echo htmlspecialchars($metric['senior_name']); ?></td>
                                            <?php endif; ?>
                                            <?php if (!$metric_type): ?>
                                                <td><?php echo ucwords(str_replace('_', ' ', $metric['record_type'])); ?></td>
                                            <?php endif; ?>
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
                                            <td><?php echo htmlspecialchars($metric['notes']); ?></td>
                                            <td>
                                                <a href="medical_record_edit.php?id=<?php echo $metric['id']; ?>" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-edit"></i> Edit
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        <?php if ($metrics && count($metrics) > 0 && ($metric_type || $senior_id)): ?>
            let chart;
            const ctx = document.getElementById('healthMetricChart').getContext('2d');
            
            <?php if ($metric_type === 'blood_pressure' && !empty($systolicData) && !empty($diastolicData)): ?>
                chart = createBloodPressureChart(
                    'healthMetricChart',
                    <?php echo json_encode($chartLabels); ?>,
                    <?php echo json_encode($systolicData); ?>,
                    <?php echo json_encode($diastolicData); ?>
                );
            <?php elseif ($metric_type === 'heart_rate' && !empty($chartData)): ?>
                chart = createHeartRateChart(
                    'healthMetricChart',
                    <?php echo json_encode($chartLabels); ?>,
                    <?php echo json_encode($chartData); ?>
                );
            <?php elseif ($metric_type === 'blood_sugar' && !empty($chartData)): ?>
                chart = createBloodSugarChart(
                    'healthMetricChart',
                    <?php echo json_encode($chartLabels); ?>,
                    <?php echo json_encode($chartData); ?>
                );
            <?php elseif ($metric_type === 'temperature' && !empty($chartData)): ?>
                chart = createTemperatureChart(
                    'healthMetricChart',
                    <?php echo json_encode($chartLabels); ?>,
                    <?php echo json_encode($chartData); ?>
                );
            <?php elseif ($metric_type === 'weight' && !empty($chartData)): ?>
                chart = createWeightChart(
                    'healthMetricChart',
                    <?php echo json_encode($chartLabels); ?>,
                    <?php echo json_encode($chartData); ?>
                );
            <?php else: ?>
                chart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: <?php echo json_encode($chartLabels); ?>,
                        datasets: [{
                            label: <?php echo json_encode(ucwords(str_replace('_', ' ', $metric_type ?? 'Health Metrics'))); ?>,
                            data: <?php echo json_encode($chartData); ?>,
                            backgroundColor: 'rgba(69, 243, 255, 0.2)',
                            borderColor: '#45f3ff',
                            borderWidth: 1,
                            tension: 0.1,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: false
                            }
                        }
                    }
                });
            <?php endif; ?>
            
            document.getElementById('downloadChartBtn').addEventListener('click', function() {
                const canvas = document.getElementById('healthMetricChart');
                // Set the scale to improve image quality
                const scale = 2;
                const scaledCanvas = document.createElement('canvas');
                scaledCanvas.width = canvas.width * scale;
                scaledCanvas.height = canvas.height * scale;
                const scaledContext = scaledCanvas.getContext('2d');
                scaledContext.scale(scale, scale);
                scaledContext.drawImage(canvas, 0, 0);
                
                // Create a temporary link and trigger download
                const link = document.createElement('a');
                link.download = 'health-metrics-chart.png';
                link.href = scaledCanvas.toDataURL('image/png');
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            });
        <?php endif; ?>
    });
    </script>
</body>
</html>