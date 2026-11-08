<?php
/**
 * AgeWell Senior Care and Support System
 * Reports Page
 */
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    setAlert('danger', 'Please login to access this page.');
    redirect('login.php');
}

// Certain reports might be restricted to admin only
$isReportAdmin = isAdmin() || isset($_GET['type']);

// Set page title
$pageTitle = 'Reports';

// Get report type
$type = isset($_GET['type']) ? cleanInput($_GET['type']) : '';
$validTypes = ['seniors', 'appointments', 'health_metrics', 'activities', 'users'];

// Get date range filters
$startDate = isset($_GET['start_date']) ? cleanInput($_GET['start_date']) : date('Y-m-d', strtotime('-30 days'));
$endDate = isset($_GET['end_date']) ? cleanInput($_GET['end_date']) : date('Y-m-d');
$seniorId = isset($_GET['senior_id']) ? (int)$_GET['senior_id'] : null;

// Handle export requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_format']) && isset($_POST['export_type'])) {
    $exportFormat = cleanInput($_POST['export_format']);
    $exportType = cleanInput($_POST['export_type']);
    
    $exportStartDate = isset($_POST['start_date']) ? cleanInput($_POST['start_date']) : date('Y-m-d', strtotime('-30 days'));
    $exportEndDate = isset($_POST['end_date']) ? cleanInput($_POST['end_date']) : date('Y-m-d');
    
    if ($exportFormat === 'csv') {
        generateReportExport($exportType, $exportStartDate, $exportEndDate, $seniorId, 'csv');
    } elseif ($exportFormat === 'pdf') {
        generatePDFReport($exportType, $exportStartDate, $exportEndDate, $seniorId);
    }
}

// Get all seniors for dropdown
$seniors = fetchAll("SELECT id, name FROM seniorcitizens ORDER BY name ASC");

// Function to get report data based on type
function getReportData($type, $startDate, $endDate, $seniorId = null) {
    $dateCondition = "";
    $params = [];
    $types = "";
    
    switch ($type) {
        case 'seniors':
            $sql = "SELECT s.*, 
                   (SELECT COUNT(*) FROM appointments a WHERE a.senior_id = s.id) as appointment_count,
                   (SELECT COUNT(*) FROM healthrecords h WHERE h.senior_id = s.id) as health_record_count
                   FROM seniorcitizens s";
            if ($seniorId) {
                $sql .= " WHERE s.id = ?";
                $params[] = $seniorId;
                $types .= "i";
            }
            $sql .= " ORDER BY s.name ASC";
            break;
            
        case 'appointments':
            $dateCondition = " WHERE DATE(a.appointment_datetime) BETWEEN ? AND ?";
            $params = [$startDate, $endDate];
            $types = "ss";
            $sql = "SELECT a.*, s.name as senior_name, u.name as caregiver_name 
                    FROM appointments a 
                    JOIN seniorcitizens s ON a.senior_id = s.id 
                    LEFT JOIN userdetails u ON a.caregiver_id = u.id" . $dateCondition;
            if ($seniorId) {
                $sql .= " AND a.senior_id = ?";
                $params[] = $seniorId;
                $types .= "i";
            }
            $sql .= " ORDER BY a.appointment_datetime DESC";
            break;
            
        case 'health_metrics':
            $dateCondition = " WHERE DATE(h.recorded_at) BETWEEN ? AND ?";
            $params = [$startDate, $endDate];
            $types = "ss";
            $sql = "SELECT h.*, s.name as senior_name, u.name as caregiver_name 
                    FROM healthrecords h 
                    JOIN seniorcitizens s ON h.senior_id = s.id 
                    JOIN userdetails u ON h.caregiver_id = u.id" . $dateCondition;
            if ($seniorId) {
                $sql .= " AND h.senior_id = ?";
                $params[] = $seniorId;
                $types .= "i";
            }
            $sql .= " ORDER BY h.recorded_at DESC";
            break;
            
        case 'activities':
            if (!isAdmin()) return false;
            $dateCondition = " WHERE DATE(a.created_at) BETWEEN ? AND ?";
            $params = [$startDate, $endDate];
            $types = "ss";
            $sql = "SELECT a.*, u.name as user_name 
                    FROM activitylogs a 
                    LEFT JOIN userdetails u ON a.user_id = u.id" . $dateCondition;
            $sql .= " ORDER BY a.created_at DESC";
            break;
            
        case 'users':
            if (!isAdmin()) return false;
            $sql = "SELECT u.*, 
                   (SELECT COUNT(*) FROM appointments a WHERE a.caregiver_id = u.id) as appointment_count,
                   (SELECT COUNT(*) FROM healthrecords h WHERE h.caregiver_id = u.id) as health_record_count
                   FROM userdetails u 
                   ORDER BY u.name ASC";
            break;
            
        default:
            return false;
    }
    
    return fetchAll($sql, $params, $types);
}

// Function to generate export file
function generateReportExport($type, $startDate, $endDate, $seniorId, $format) {
    $data = getReportData($type, $startDate, $endDate, $seniorId);
    if (!$data) {
        setAlert('danger', 'No data available for export.');
        return false;
    }
    
    $headers = [];
    $csvData = [];
    
    switch ($type) {
        case 'seniors':
            $headers = ['ID', 'Name', 'Age', 'Gender', 'Phone', 'Emergency Contact', 'Emergency Phone', 'Address', 'Medical History', 'Medications', 'Allergies', 'Appointment Count', 'Health Record Count'];
            foreach ($data as $row) {
                $csvData[] = [
                    $row['id'], $row['name'], $row['age'], $row['gender'], $row['phone'],
                    $row['emergency_contact'], $row['emergency_phone'], $row['address'],
                    $row['medical_history'], $row['medications'], $row['allergies'],
                    $row['appointment_count'], $row['health_record_count']
                ];
            }
            break;
            
        case 'appointments':
            $headers = ['ID', 'Senior Name', 'Caregiver Name', 'Doctor Name', 'Date & Time', 'Purpose', 'Location', 'Status', 'Notes'];
            foreach ($data as $row) {
                $csvData[] = [
                    $row['id'], $row['senior_name'], $row['caregiver_name'] ?? 'Not Assigned',
                    $row['doctor_name'], $row['appointment_datetime'], $row['purpose'],
                    $row['location'], $row['status'], $row['notes']
                ];
            }
            break;
            
        case 'health_metrics':
            $headers = ['ID', 'Senior Name', 'Caregiver Name', 'Record Type', 'Value', 'Recorded At', 'Notes'];
            foreach ($data as $row) {
                $csvData[] = [
                    $row['id'], $row['senior_name'], $row['caregiver_name'],
                    $row['record_type'], $row['value'], $row['recorded_at'], $row['notes']
                ];
            }
            break;
            
        case 'activities':
            $headers = ['ID', 'User', 'Action', 'Details', 'Created At'];
            foreach ($data as $row) {
                $csvData[] = [
                    $row['id'], $row['user_name'] ?? 'System', $row['action'],
                    $row['details'], $row['created_at']
                ];
            }
            break;
            
        case 'users':
            $headers = ['ID', 'Name', 'Email', 'Phone', 'Role', 'Status', 'Last Login', 'Appointment Count', 'Health Record Count'];
            foreach ($data as $row) {
                $csvData[] = [
                    $row['id'], $row['name'], $row['email'], $row['phone'], $row['role'],
                    $row['status'], $row['last_login'], $row['appointment_count'], $row['health_record_count']
                ];
            }
            break;
    }
    
    $filename = $type . '_report_' . date('Y-m-d') . '.csv';
    generateCSV($csvData, $headers, $filename);
    return true;
}

// Function to generate PDF report
function generatePDFReport($type, $startDate, $endDate, $seniorId = null) {
    $tcpdfPath = __DIR__ . '/vendor/tecnickcom/tcpdf/tcpdf.php';
    if (!file_exists($tcpdfPath)) {
        $tcpdfPath = __DIR__ . '/vendor/tecnickcom/tcpdf/TCPDF.php';
    }
    
    if (!file_exists($tcpdfPath)) {
        setAlert('danger', 'PDF generation library not found. Please contact system administrator.');
        return false;
    }
    
    require_once($tcpdfPath);
    $data = getReportData($type, $startDate, $endDate, $seniorId);
    if (!$data) {
        setAlert('danger', 'No data available for PDF export.');
        return false;
    }
    
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    $pdf->SetCreator('AgeWell Senior Care System');
    $pdf->SetAuthor('AgeWell Admin');
    $pdf->SetTitle(ucfirst($type) . ' Report');
    $pdf->SetMargins(15, 15, 15);
    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 11);
    
    $title = ucfirst($type) . ' Report';
    if ($startDate && $endDate) {
        $title .= ' (' . date('M d, Y', strtotime($startDate)) . ' - ' . date('M d, Y', strtotime($endDate)) . ')';
    }
    $pdf->Cell(0, 10, $title, 0, 1, 'C');
    $pdf->Ln(5);
    
    switch ($type) {
        case 'seniors':
            $headers = ['Name', 'Age', 'Gender', 'Phone', 'Emergency Contact'];
            $widths = [45, 20, 30, 35, 50];
            $pdf->SetFont('helvetica', 'B', 10);
            foreach ($headers as $i => $header) {
                $pdf->Cell($widths[$i], 7, $header, 1);
            }
            $pdf->Ln();
            $pdf->SetFont('helvetica', '', 10);
            foreach ($data as $row) {
                $pdf->Cell($widths[0], 6, $row['name'], 1);
                $pdf->Cell($widths[1], 6, $row['age'], 1);
                $pdf->Cell($widths[2], 6, ucfirst($row['gender']), 1);
                $pdf->Cell($widths[3], 6, $row['phone'], 1);
                $pdf->Cell($widths[4], 6, $row['emergency_contact'], 1);
                $pdf->Ln();
            }
            break;
            
        case 'appointments':
            $headers = ['Senior Name', 'Doctor', 'Date & Time', 'Purpose', 'Status'];
            $widths = [45, 45, 45, 35, 20];
            $pdf->SetFont('helvetica', 'B', 10);
            foreach ($headers as $i => $header) {
                $pdf->Cell($widths[$i], 7, $header, 1);
            }
            $pdf->Ln();
            $pdf->SetFont('helvetica', '', 10);
            foreach ($data as $row) {
                $pdf->Cell($widths[0], 6, $row['senior_name'], 1);
                $pdf->Cell($widths[1], 6, $row['doctor_name'], 1);
                $pdf->Cell($widths[2], 6, date('M d, Y g:i A', strtotime($row['appointment_datetime'])), 1);
                $pdf->Cell($widths[3], 6, $row['purpose'], 1);
                $pdf->Cell($widths[4], 6, ucfirst($row['status']), 1);
                $pdf->Ln();
            }
            break;
            
        case 'health_metrics':
            $headers = ['Senior Name', 'Record Type', 'Value', 'Recorded At', 'Caregiver'];
            $widths = [40, 35, 25, 45, 40];
            $pdf->SetFont('helvetica', 'B', 10);
            foreach ($headers as $i => $header) {
                $pdf->Cell($widths[$i], 7, $header, 1);
            }
            $pdf->Ln();
            $pdf->SetFont('helvetica', '', 10);
            foreach ($data as $row) {
                $recordType = ucwords(str_replace('_', ' ', $row['record_type']));
                $value = $row['value'];
                switch ($row['record_type']) {
                    case 'blood_pressure': $value .= ' mmHg'; break;
                    case 'heart_rate': $value .= ' bpm'; break;
                    case 'blood_sugar': $value .= ' mg/dL'; break;
                    case 'temperature': $value .= ' °F'; break;
                    case 'weight': $value .= ' kg'; break;
                }
                $pdf->Cell($widths[0], 6, $row['senior_name'], 1);
                $pdf->Cell($widths[1], 6, $recordType, 1);
                $pdf->Cell($widths[2], 6, $value, 1);
                $pdf->Cell($widths[3], 6, date('M d, Y g:i A', strtotime($row['recorded_at'])), 1);
                $pdf->Cell($widths[4], 6, $row['caregiver_name'], 1);
                $pdf->Ln();
            }
            break;
            
        case 'activities':
            $headers = ['User', 'Action', 'Details', 'Created At'];
            $widths = [40, 40, 65, 45];
            $pdf->SetFont('helvetica', 'B', 10);
            foreach ($headers as $i => $header) {
                $pdf->Cell($widths[$i], 7, $header, 1);
            }
            $pdf->Ln();
            $pdf->SetFont('helvetica', '', 10);
            foreach ($data as $row) {
                $pdf->Cell($widths[0], 6, $row['user_name'] ?? 'System', 1);
                $pdf->Cell($widths[1], 6, $row['action'], 1);
                $pdf->Cell($widths[2], 6, $row['details'], 1);
                $pdf->Cell($widths[3], 6, date('M d, Y g:i A', strtotime($row['created_at'])), 1);
                $pdf->Ln();
            }
            break;
            
        case 'users':
            $headers = ['Name', 'Email', 'Role', 'Status', 'Last Login'];
            $widths = [40, 50, 25, 25, 45];
            $pdf->SetFont('helvetica', 'B', 10);
            foreach ($headers as $i => $header) {
                $pdf->Cell($widths[$i], 7, $header, 1);
            }
            $pdf->Ln();
            $pdf->SetFont('helvetica', '', 10);
            foreach ($data as $row) {
                $pdf->Cell($widths[0], 6, $row['name'], 1);
                $pdf->Cell($widths[1], 6, $row['email'], 1);
                $pdf->Cell($widths[2], 6, ucfirst($row['role']), 1);
                $pdf->Cell($widths[3], 6, ucfirst($row['status']), 1);
                $pdf->Cell($widths[4], 6, $row['last_login'] ? date('M d, Y', strtotime($row['last_login'])) : 'Never', 1);
                $pdf->Ln();
            }
            break;
    }
    
    $filename = $type . '_report_' . date('Y-m-d') . '.pdf';
    $pdf->Output($filename, 'D');
    exit;
}

// Get report data if type is specified
$reportData = $type ? getReportData($type, $startDate, $endDate, $seniorId) : null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - AgeWell Senior Care</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
    <?php if ($type === 'health_metrics'): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <?php endif; ?>
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
            color: #1c1c1c;
        }

        .btn-success:hover {
            background: #3cb058;
            border-color: #3cb058;
            color: #1c1c1c;
        }

        .btn-warning {
            background: #ffd166;
            border-color: #ffd166;
            color: #1c1c1c;
        }

        .btn-danger {
            background: #ff3860;
            border-color: #ff3860;
            color: #ffffff;
        }

        .badge {
            padding: 5px 8px;
            border-radius: 4px;
            font-weight: 500;
        }

        .bg-primary {
            background-color: #45f3ff !important;
            color: #1c1c1c;
        }

        .bg-success {
            background-color: #4bdb6a !important;
            color: #1c1c1c;
        }

        .bg-warning {
            background-color: #ffd166 !important;
            color: #1c1c1c;
        }

        .bg-danger {
            background-color: #ff3860 !important;
            color: #ffffff;
        }

        .bg-info {
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

        .form-control, .form-select {
            background: #28292d;
            border: 1px solid rgba(69, 243, 255, 0.3);
            color: #cdcdcd;
            border-radius: 4px;
            padding: 8px 12px;
        }

        .form-control:focus, .form-select:focus {
            background: #28292d;
            border-color: #45f3ff;
            color: #cdcdcd;
            box-shadow: 0 0 5px rgba(69, 243, 255, 0.5);
        }

        .form-label {
            color: #45f3ff;
            font-weight: 500;
        }

        .chart-container {
            position: relative;
            margin-bottom: 20px;
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
                <?php if (isAdmin()): ?>
                <li><a href="senior_add.php"><i class="fas fa-user-plus"></i> Add New Senior</a></li>
                <?php endif; ?>
            </ul>
            <h6>Quick Reports</h6>
            <ul>
                <li><a href="reports.php?type=seniors"><i class="fas fa-users"></i> Senior Citizens Report</a></li>
                <li><a href="reports.php?type=appointments"><i class="fas fa-calendar-check"></i> Appointments Report</a></li>
                <li><a href="reports.php?type=health_metrics"><i class="fas fa-chart-line"></i> Health Metrics Report</a></li>
            </ul>
            <?php if (isAdmin()): ?>
            <h6>Admin Actions</h6>
            <ul>
                <li><a href="user_add.php"><i class="fas fa-user-plus"></i> Add New User</a></li>
                <li><a href="users.php"><i class="fas fa-users-cog"></i> Manage Users</a></li>
                <li><a href="activity_logs.php"><i class="fas fa-history"></i> View Activity Logs</a></li>
            </ul>
            <?php endif; ?>
        </div>
        
        <!-- Main content -->
        <div class="main-content">
            <div class="page-title">
                <h1><i class="fas fa-chart-bar me-2"></i> Reports</h1>
                <?php if ($type && $reportData): ?>
                    <div class="quick-access">
                        <button class="btn btn-outline-primary btn-export" data-format="csv" data-type="<?php echo $type; ?>">
                                <i class="fas fa-file-csv me-1"></i> Export CSV
                            </button>
                        <button class="btn btn-outline-primary btn-export" data-format="pdf" data-type="<?php echo $type; ?>">
                                <i class="fas fa-file-pdf me-1"></i> Export PDF
                            </button>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Report Selection Form -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="reports.php" class="row g-3">
                        <div class="col-md-3">
                            <label for="type" class="form-label">Report Type</label>
                            <select class="form-select" id="type" name="type" required>
                                <option value="" <?php echo empty($type) ? 'selected' : ''; ?>>Select Report Type</option>
                                <option value="seniors" <?php echo $type === 'seniors' ? 'selected' : ''; ?>>Senior Citizens</option>
                                <option value="appointments" <?php echo $type === 'appointments' ? 'selected' : ''; ?>>Appointments</option>
                                <option value="health_metrics" <?php echo $type === 'health_metrics' ? 'selected' : ''; ?>>Health Metrics</option>
                                <?php if (isAdmin()): ?>
                                    <option value="activities" <?php echo $type === 'activities' ? 'selected' : ''; ?>>Activity Logs</option>
                                    <option value="users" <?php echo $type === 'users' ? 'selected' : ''; ?>>Users</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="start_date" class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $startDate; ?>">
                        </div>
                        <div class="col-md-2">
                            <label for="end_date" class="form-label">End Date</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $endDate; ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="senior_id" class="form-label">Senior Citizen</label>
                            <select class="form-select" id="senior_id" name="senior_id">
                                <option value="">All Seniors</option>
                                <?php foreach ($seniors as $senior): ?>
                                    <option value="<?php echo $senior['id']; ?>" <?php echo $seniorId == $senior['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($senior['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search me-1"></i> Generate Report
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <?php if (empty($type)): ?>
                <!-- Report Types Display -->
                <div class="row">
                    <div class="col-md-4 mb-4">
                        <div class="card">
                            <div class="card-body">
                                <h5><i class="fas fa-users me-2"></i> Senior Citizens Report</h5>
                                <p>View comprehensive information about senior citizens including their medical history, appointments, and health records.</p>
                                <a href="reports.php?type=seniors" class="btn btn-primary w-100">Generate Report</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-4">
                        <div class="card">
                            <div class="card-body">
                                <h5><i class="fas fa-calendar-check me-2"></i> Appointments Report</h5>
                                <p>Track appointments over time, filtered by date range, status, or specific senior citizens.</p>
                                <a href="reports.php?type=appointments" class="btn btn-primary w-100">Generate Report</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-4">
                        <div class="card">
                            <div class="card-body">
                                <h5><i class="fas fa-heartbeat me-2"></i> Health Metrics Report</h5>
                                <p>Analyze health metrics data including blood pressure, heart rate, blood sugar, and more.</p>
                                <a href="reports.php?type=health_metrics" class="btn btn-primary w-100">Generate Report</a>
                            </div>
                        </div>
                    </div>
                    <?php if (isAdmin()): ?>
                        <div class="col-md-4 mb-4">
                            <div class="card">
                                <div class="card-body">
                                    <h5><i class="fas fa-history me-2"></i> Activity Logs Report</h5>
                                    <p>Review system activity logs to track user actions and system events.</p>
                                    <a href="reports.php?type=activities" class="btn btn-primary w-100">Generate Report</a>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-4">
                            <div class="card">
                                <div class="card-body">
                                    <h5><i class="fas fa-users-cog me-2"></i> Users Report</h5>
                                    <p>View information about system users, their roles, and activities.</p>
                                    <a href="reports.php?type=users" class="btn btn-primary w-100">Generate Report</a>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php elseif ($type && $reportData): ?>
                <!-- Report Display -->
                <div class="card">
                    <div class="card-header">
                        <h6>
                            <?php
                            $reportTitle = '';
                            switch ($type) {
                                case 'seniors': $reportTitle = 'Senior Citizens Report'; break;
                                case 'appointments':
                                    $reportTitle = 'Appointments Report';
                                    if ($seniorId) {
                                        $senior = getSeniorById($seniorId);
                                        if ($senior) $reportTitle .= ' for ' . $senior['name'];
                                    }
                                    $reportTitle .= ' (' . date('M d, Y', strtotime($startDate)) . ' - ' . date('M d, Y', strtotime($endDate)) . ')';
                                    break;
                                case 'health_metrics':
                                    $reportTitle = 'Health Metrics Report';
                                    if ($seniorId) {
                                        $senior = getSeniorById($seniorId);
                                        if ($senior) $reportTitle .= ' for ' . $senior['name'];
                                    }
                                    $reportTitle .= ' (' . date('M d, Y', strtotime($startDate)) . ' - ' . date('M d, Y', strtotime($endDate)) . ')';
                                    break;
                                case 'activities':
                                    $reportTitle = 'Activity Logs Report (' . date('M d, Y', strtotime($startDate)) . ' - ' . date('M d, Y', strtotime($endDate)) . ')';
                                    break;
                                case 'users': $reportTitle = 'Users Report'; break;
                            }
                            echo $reportTitle;
                            ?>
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php if ($type === 'health_metrics' && $seniorId): ?>
                            <div class="chart-container" style="height:300px;">
                                <canvas id="healthMetricsChart"></canvas>
                            </div>
                        <?php endif; ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <?php
                                    switch ($type) {
                                        case 'seniors':
                                            echo '<tr>
                                                <th>Name</th>
                                                <th>Age</th>
                                                <th>Gender</th>
                                                <th>Phone</th>
                                                <th>Emergency Contact</th>
                                                <th>Appointments</th>
                                                <th>Health Records</th>
                                                <th>Actions</th>
                                            </tr>';
                                            break;
                                        case 'appointments':
                                            echo '<tr>
                                                <th>Senior Name</th>
                                                <th>Doctor</th>
                                                <th>Date & Time</th>
                                                <th>Purpose</th>
                                                <th>Status</th>
                                                <th>Caregiver</th>
                                            </tr>';
                                            break;
                                        case 'health_metrics':
                                            echo '<tr>
                                                <th>Senior Name</th>
                                                <th>Record Type</th>
                                                <th>Value</th>
                                                <th>Recorded At</th>
                                                <th>Caregiver</th>
                                                <th>Notes</th>
                                            </tr>';
                                            break;
                                        case 'activities':
                                            echo '<tr>
                                                <th>User</th>
                                                <th>Action</th>
                                                <th>Details</th>
                                                <th>Created At</th>
                                            </tr>';
                                            break;
                                        case 'users':
                                            echo '<tr>
                                                <th>Name</th>
                                                <th>Email</th>
                                                <th>Role</th>
                                                <th>Status</th>
                                                <th>Last Login</th>
                                                <th>Appointments</th>
                                                <th>Health Records</th>
                                            </tr>';
                                            break;
                                    }
                                    ?>
                                </thead>
                                <tbody>
                                    <?php
                                    switch ($type) {
                                        case 'seniors':
                                            foreach ($reportData as $senior) {
                                                echo '<tr>
                                                    <td>' . htmlspecialchars($senior['name']) . '</td>
                                                    <td>' . htmlspecialchars($senior['age']) . '</td>
                                                    <td>' . ucfirst(htmlspecialchars($senior['gender'])) . '</td>
                                                    <td>' . htmlspecialchars($senior['phone']) . '</td>
                                                    <td>' . htmlspecialchars($senior['emergency_contact']) . ' (' . htmlspecialchars($senior['emergency_phone']) . ')</td>
                                                    <td>' . $senior['appointment_count'] . '</td>
                                                    <td>' . $senior['health_record_count'] . '</td>
                                                    <td>
                                                        <div class="action-buttons">
                                                            <a href="senior_profile.php?id=' . $senior['id'] . '" class="btn btn-primary">
                                                                <i class="fas fa-eye"></i>
                                                        </a>
                                                        </div>
                                                    </td>
                                                </tr>';
                                            }
                                            break;
                                        case 'appointments':
                                            foreach ($reportData as $appointment) {
                                                $statusClass = $appointment['status'] === 'scheduled' ? 'bg-primary' :
                                                              ($appointment['status'] === 'completed' ? 'bg-success' :
                                                              ($appointment['status'] === 'cancelled' ? 'bg-warning' : 'bg-danger'));
                                                echo '<tr>
                                                    <td>' . htmlspecialchars($appointment['senior_name']) . '</td>
                                                    <td>' . htmlspecialchars($appointment['doctor_name']) . '</td>
                                                    <td>' . date('M d, Y g:i A', strtotime($appointment['appointment_datetime'])) . '</td>
                                                    <td>' . htmlspecialchars($appointment['purpose']) . '</td>
                                                    <td><span class="badge ' . $statusClass . '">' . ucfirst($appointment['status']) . '</span></td>
                                                    <td>' . htmlspecialchars($appointment['caregiver_name'] ?? 'Not Assigned') . '</td>
                                                </tr>';
                                            }
                                            break;
                                        case 'health_metrics':
                                            foreach ($reportData as $metric) {
                                                $recordType = ucwords(str_replace('_', ' ', $metric['record_type']));
                                                $value = $metric['value'];
                                                switch ($metric['record_type']) {
                                                    case 'blood_pressure': $value .= ' mmHg'; break;
                                                    case 'heart_rate': $value .= ' bpm'; break;
                                                    case 'blood_sugar': $value .= ' mg/dL'; break;
                                                    case 'temperature': $value .= ' °F'; break;
                                                    case 'weight': $value .= ' kg'; break;
                                                }
                                                echo '<tr>
                                                    <td>' . htmlspecialchars($metric['senior_name']) . '</td>
                                                    <td>' . $recordType . '</td>
                                                    <td>' . $value . '</td>
                                                    <td>' . date('M d, Y g:i A', strtotime($metric['recorded_at'])) . '</td>
                                                    <td>' . htmlspecialchars($metric['caregiver_name']) . '</td>
                                                    <td>' . htmlspecialchars($metric['notes']) . '</td>
                                                </tr>';
                                            }
                                            break;
                                        case 'activities':
                                            foreach ($reportData as $activity) {
                                                echo '<tr>
                                                    <td>' . htmlspecialchars($activity['user_name'] ?? 'System') . '</td>
                                                    <td>' . htmlspecialchars($activity['action']) . '</td>
                                                    <td>' . htmlspecialchars($activity['details']) . '</td>
                                                    <td>' . date('M d, Y g:i A', strtotime($activity['created_at'])) . '</td>
                                                </tr>';
                                            }
                                            break;
                                        case 'users':
                                            foreach ($reportData as $user) {
                                                echo '<tr>
                                                    <td>' . htmlspecialchars($user['name']) . '</td>
                                                    <td>' . htmlspecialchars($user['email']) . '</td>
                                                    <td><span class="badge bg-' . ($user['role'] === 'admin' ? 'danger' : 'info') . '">' . ucfirst($user['role']) . '</span></td>
                                                    <td><span class="badge bg-' . ($user['status'] === 'active' ? 'success' : 'secondary') . '">' . ucfirst($user['status']) . '</span></td>
                                                    <td>' . ($user['last_login'] ? date('M d, Y g:i A', strtotime($user['last_login'])) : 'Never') . '</td>
                                                    <td>' . $user['appointment_count'] . '</td>
                                                    <td>' . $user['health_record_count'] . '</td>
                                                </tr>';
                                            }
                                            break;
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php elseif ($type): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i> No data available for the selected report criteria.
                </div>
            <?php endif; ?>
    </div>
</div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const currentPage = window.location.pathname.split('/').pop();
        document.querySelectorAll('.sidebar a').forEach(link => {
            const href = link.getAttribute('href');
            if (href === currentPage || href.includes('reports.php?type=' + '<?php echo $type; ?>')) {
                link.classList.add('active');
            }
        });

        const exportButtons = document.querySelectorAll('.btn-export');
        exportButtons.forEach(button => {
            button.addEventListener('click', function() {
                const format = this.dataset.format;
                const type = this.dataset.type;
                
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'reports.php';
                
                const formatInput = document.createElement('input');
                formatInput.type = 'hidden';
                formatInput.name = 'export_format';
                formatInput.value = format;
                form.appendChild(formatInput);
                
                const typeInput = document.createElement('input');
                typeInput.type = 'hidden';
                typeInput.name = 'export_type';
                typeInput.value = type;
                form.appendChild(typeInput);
                
                const startDate = document.getElementById('start_date').value;
                const endDate = document.getElementById('end_date').value;
                if (startDate) {
                    const startInput = document.createElement('input');
                    startInput.type = 'hidden';
                    startInput.name = 'start_date';
                    startInput.value = startDate;
                    form.appendChild(startInput);
                }
                if (endDate) {
                    const endInput = document.createElement('input');
                    endInput.type = 'hidden';
                    endInput.name = 'end_date';
                    endInput.value = endDate;
                    form.appendChild(endInput);
                }
                
                document.body.appendChild(form);
                form.submit();
            });
        });

        <?php if ($type === 'health_metrics' && $seniorId && $reportData): ?>
        const chartData = {
            bloodPressure: { dates: [], systolic: [], diastolic: [] },
            heartRate: { dates: [], values: [] },
            bloodSugar: { dates: [], values: [] },
            temperature: { dates: [], values: [] },
            weight: { dates: [], values: [] }
        };
        
        <?php foreach ($reportData as $metric): ?>
            <?php
            $date = "'" . date('M d', strtotime($metric['recorded_at'])) . "'";
            switch ($metric['record_type']) {
                case 'blood_pressure':
                    $bpParts = explode('/', $metric['value']);
                    $systolic = isset($bpParts[0]) ? trim($bpParts[0]) : null;
                    $diastolic = isset($bpParts[1]) ? trim($bpParts[1]) : null;
                    ?>
                    chartData.bloodPressure.dates.push(<?php echo $date; ?>);
                    chartData.bloodPressure.systolic.push(<?php echo $systolic ?? 'null'; ?>);
                    chartData.bloodPressure.diastolic.push(<?php echo $diastolic ?? 'null'; ?>);
                        <?php break;
                case 'heart_rate':
                    ?>
                    chartData.heartRate.dates.push(<?php echo $date; ?>);
                    chartData.heartRate.values.push(<?php echo $metric['value']; ?>);
                        <?php break;
                case 'blood_sugar':
                    ?>
                    chartData.bloodSugar.dates.push(<?php echo $date; ?>);
                    chartData.bloodSugar.values.push(<?php echo $metric['value']; ?>);
                        <?php break;
                case 'temperature':
                    ?>
                    chartData.temperature.dates.push(<?php echo $date; ?>);
                    chartData.temperature.values.push(<?php echo $metric['value']; ?>);
                        <?php break;
                case 'weight':
                    ?>
                    chartData.weight.dates.push(<?php echo $date; ?>);
                    chartData.weight.values.push(<?php echo $metric['value']; ?>);
                        <?php break;
            }
            ?>
        <?php endforeach; ?>
        
            const ctx = document.getElementById('healthMetricsChart').getContext('2d');
        if (chartData.bloodPressure.dates.length > 0) {
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: chartData.bloodPressure.dates,
                        datasets: [
                            { label: 'Systolic', data: chartData.bloodPressure.systolic, borderColor: '#45f3ff', fill: false },
                            { label: 'Diastolic', data: chartData.bloodPressure.diastolic, borderColor: '#4bdb6a', fill: false }
                        ]
                    },
                    options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: false } } }
                });
        } else if (chartData.heartRate.dates.length > 0) {
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: chartData.heartRate.dates,
                        datasets: [{ label: 'Heart Rate', data: chartData.heartRate.values, borderColor: '#45f3ff', fill: false }]
                    },
                    options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: false } } }
                });
        } else if (chartData.bloodSugar.dates.length > 0) {
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: chartData.bloodSugar.dates,
                        datasets: [{ label: 'Blood Sugar', data: chartData.bloodSugar.values, borderColor: '#45f3ff', fill: false }]
                    },
                    options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: false } } }
                });
        } else if (chartData.temperature.dates.length > 0) {
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: chartData.temperature.dates,
                        datasets: [{ label: 'Temperature', data: chartData.temperature.values, borderColor: '#45f3ff', fill: false }]
                    },
                    options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: false } } }
                });
        } else if (chartData.weight.dates.length > 0) {
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: chartData.weight.dates,
                        datasets: [{ label: 'Weight', data: chartData.weight.values, borderColor: '#45f3ff', fill: false }]
                    },
                    options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: false } } }
                });
            }
        <?php endif; ?>
});
</script>
</body>
</html>