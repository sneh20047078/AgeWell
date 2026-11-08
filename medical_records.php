<?php
/**
 * AgeWell Senior Care and Support System
 * Medical Records List Page
 */

// Include configuration and required files
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    setAlert('danger', 'Please login to access this page.');
    redirect('login.php');
}

// Set page title
$pageTitle = 'Medical Records';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : DEFAULT_PAGINATION_LIMIT;
$offset = ($page - 1) * $perPage;

// Filters
$senior_id = isset($_GET['senior_id']) ? (int)$_GET['senior_id'] : null;
$from_date = isset($_GET['from_date']) ? cleanInput($_GET['from_date']) : null;
$to_date = isset($_GET['to_date']) ? cleanInput($_GET['to_date']) : null;
$search = isset($_GET['search']) ? cleanInput($_GET['search']) : '';

// Build query conditions
$whereConditions = [];
$params = [];
$types = '';

if ($senior_id) {
    $whereConditions[] = 'h.senior_id = ?';
    $params[] = $senior_id;
    $types .= 'i';
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

if (!empty($search)) {
    $whereConditions[] = '(s.name LIKE ? OR h.notes LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= 'ss';
}

// Combine conditions
$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get total number of records for pagination
$countSql = "SELECT COUNT(*) as total FROM healthrecords h 
             JOIN seniorcitizens s ON h.senior_id = s.id 
             $whereClause";
$countResult = fetchOne($countSql, $params, $types);
$totalRecords = $countResult ? $countResult['total'] : 0;
$totalPages = ceil($totalRecords / $perPage);

// Get medical records data
$sql = "SELECT h.*, s.name as senior_name, u.name as caregiver_name 
        FROM healthrecords h 
        JOIN seniorcitizens s ON h.senior_id = s.id 
        JOIN userdetails u ON h.caregiver_id = u.id 
        $whereClause 
        ORDER BY h.recorded_at DESC 
        LIMIT ?, ?";
$params[] = $offset;
$params[] = $perPage;
$types .= 'ii';

$medicalRecords = fetchAll($sql, $params, $types);

// Get seniors for filter dropdown
$seniors = fetchAll("SELECT id, name FROM seniorcitizens ORDER BY name ASC");

// Handle delete request
if (isset($_POST['delete']) && isset($_POST['id'])) {
    $recordId = (int)$_POST['id'];
    
    // Get record details for logging
    $record = fetchOne(
        "SELECT h.*, s.name as senior_name FROM healthrecords h 
         JOIN seniorcitizens s ON h.senior_id = s.id 
         WHERE h.id = ?", 
        [$recordId], 
        'i'
    );
    
    if (!$record) {
        setAlert('danger', 'Medical record not found.');
        redirect('medical_records.php');
        exit;
    }
    
    // Delete the record
    $deleted = delete('healthrecords', 'id = ?', [$recordId], 'i');
    
    if ($deleted) {
        // Log the activity
        logActivity('medical_record_delete', "Deleted medical record for {$record['senior_name']} recorded on " . date('M d, Y', strtotime($record['recorded_at'])));
        setAlert('success', 'Medical record deleted successfully.');
    } else {
        setAlert('danger', 'Failed to delete medical record. Please try again.');
    }
    
    // Redirect to refresh the page
    redirect('medical_records.php');
    exit;
}

// Don't include the default header since we're using a custom one
// require_once 'includes/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical Records - AgeWell Senior Care</title>
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

        .card-header h5, .card-header h6 {
            color: #45f3ff;
            margin: 0;
            font-weight: 500;
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

        .table tbody tr td {
            border-top: none;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding: 12px 15px;
        }

        /* Form styling */
        .form-control, .form-select {
            background-color: #1c1c1c;
            border: 1px solid rgba(69, 243, 255, 0.3);
            border-radius: 4px;
            color: #cdcdcd;
            padding: 10px 12px;
            transition: all 0.3s;
        }

        .form-control:focus, .form-select:focus {
            border-color: #45f3ff;
            box-shadow: 0 0 0 0.25rem rgba(69, 243, 255, 0.25);
            background-color: #1c1c1c;
            color: #ffffff;
        }

        .form-control::placeholder {
            color: #6c757d;
        }

        .input-group .btn {
            background-color: #45f3ff;
            border-color: #45f3ff;
            color: #1c1c1c;
        }

        .input-group .btn:hover {
            background-color: #28d7e5;
            border-color: #28d7e5;
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

        .btn-secondary {
            background: transparent;
            border-color: #45f3ff;
            color: #45f3ff;
        }

        .btn-secondary:hover {
            background: rgba(69, 243, 255, 0.1);
            color: #45f3ff;
        }

        .btn-danger {
            background: #ff3860;
            border-color: #ff3860;
        }

        .btn-danger:hover {
            background: #e8264e;
            border-color: #e8264e;
        }

        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
        }

        /* Pagination styling */
        .pagination {
            margin-top: 20px;
        }

        .pagination .page-item .page-link {
            background-color: #1c1c1c;
            border-color: rgba(69, 243, 255, 0.3);
            color: #45f3ff;
        }

        .pagination .page-item.active .page-link {
            background-color: #45f3ff;
            border-color: #45f3ff;
            color: #1c1c1c;
        }

        .pagination .page-item .page-link:hover {
            background-color: rgba(69, 243, 255, 0.1);
            color: #45f3ff;
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

        .alert-danger {
            background-color: rgba(255, 56, 96, 0.1);
            border-color: rgba(255, 56, 96, 0.3);
            color: #ff3860;
        }

        /* Modal styling */
        .modal-content {
            background-color: #28292d;
            border: 1px solid rgba(69, 243, 255, 0.3);
        }

        .modal-header {
            border-bottom: 1px solid rgba(69, 243, 255, 0.3);
        }

        .modal-footer {
            border-top: 1px solid rgba(69, 243, 255, 0.3);
        }

        .modal-title {
            color: #ffffff;
        }

        /* Action buttons */
        .action-buttons {
            display: flex;
            gap: 5px;
        }

        .action-buttons .btn {
            padding: 5px 10px;
            font-size: 12px;
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

        /* Badge styling */
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

        /* Glowing effects */
        @keyframes glow {
            0% {
                box-shadow: 0 0 5px rgba(69, 243, 255, 0.3);
            }
            50% {
                box-shadow: 0 0 15px rgba(69, 243, 255, 0.5);
            }
            100% {
                box-shadow: 0 0 5px rgba(69, 243, 255, 0.3);
            }
        }

        .glow-effect {
            animation: glow 2s infinite;
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
            <a href="health_metrics.php" class="active"><i class="fas fa-heartbeat"></i> Health Metrics</a>
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
            <?php require_once 'includes/sidebar.php'; ?>
        </div>
        
        <!-- Main content -->
        <div class="main-content">
            <div class="page-title">
                <h1><i class="fas fa-notes-medical me-2"></i> Medical Records</h1>
                <div class="quick-access">
                    <a href="medical_record_add.php" class="btn btn-outline-primary">
                        <i class="fas fa-plus"></i> Add Medical Record
                    </a>
                    <a href="health_metrics.php" class="btn btn-outline-primary">
                        <i class="fas fa-chart-line"></i> View Health Metrics
                    </a>
                </div>
            </div>

            <!-- Display alerts -->
            <?php if (isset($_SESSION['alert'])): ?>
                <div class="alert alert-<?php echo $_SESSION['alert']['type']; ?>">
                    <?php echo $_SESSION['alert']['message']; ?>
                </div>
                <?php unset($_SESSION['alert']); ?>
            <?php endif; ?>
            
            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6>Search & Filter</h6>
                </div>
                <div class="card-body">
                    <form method="GET" action="medical_records.php" class="row g-3">
                        <!-- Search input -->
                        <div class="col-md-4">
                            <div class="input-group">
                                <input type="text" class="form-control" id="search" name="search" 
                                       placeholder="Search by senior name or notes" 
                                       value="<?php echo htmlspecialchars($search); ?>">
                                <button class="btn btn-primary" type="submit">
                                    <i class="fas fa-search"></i> Search
                                </button>
                            </div>
                        </div>
                        
                        <!-- Senior filter -->
                        <div class="col-md-3">
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
                        
                        <!-- Date range filter -->
                        <div class="col-md-2">
                            <input type="date" class="form-control" id="from_date" name="from_date" 
                                   placeholder="From Date" value="<?php echo $from_date; ?>">
                        </div>
                        
                        <div class="col-md-2">
                            <input type="date" class="form-control" id="to_date" name="to_date" 
                                   placeholder="To Date" value="<?php echo $to_date; ?>">
                        </div>
                        
                        <div class="col-md-1">
                            <div class="d-grid">
                                <a href="medical_records.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i>
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Medical Records List -->
            <div class="card">
                <div class="card-header">
                    <h6>Medical Records</h6>
                    <span class="badge bg-info"><?php echo $totalRecords; ?> Records</span>
                </div>
                <div class="card-body">
                    <?php if ($medicalRecords && count($medicalRecords) > 0): ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Senior</th>
                                        <th>Record Type</th>
                                        <th>Value</th>
                                        <th>Recorded At</th>
                                        <th>Caregiver</th>
                                        <th>Notes</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($medicalRecords as $record): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($record['senior_name']); ?></td>
                                            <td>
                                                <?php 
                                                $recordType = str_replace('_', ' ', $record['record_type']);
                                                echo ucwords($recordType); 
                                                ?>
                                            </td>
                                            <td>
                                                <?php 
                                                $value = $record['value'];
                                                switch ($record['record_type']) {
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
                                            <td><?php echo date('M d, Y g:i A', strtotime($record['recorded_at'])); ?></td>
                                            <td><?php echo htmlspecialchars($record['caregiver_name']); ?></td>
                                            <td><?php echo htmlspecialchars($record['notes']); ?></td>
                                            <td>
                                                <div class="action-buttons">
                                                    <a href="medical_record_edit.php?id=<?php echo $record['id']; ?>" class="btn btn-primary btn-sm">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" 
                                                            data-bs-target="#deleteModal<?php echo $record['id']; ?>">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                                
                                                <!-- Delete Confirmation Modal -->
                                                <div class="modal fade" id="deleteModal<?php echo $record['id']; ?>" tabindex="-1" 
                                                     aria-labelledby="deleteModalLabel<?php echo $record['id']; ?>" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header bg-danger text-white">
                                                                <h5 class="modal-title" id="deleteModalLabel<?php echo $record['id']; ?>">
                                                                    Confirm Delete
                                                                </h5>
                                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p>Are you sure you want to delete this medical record for 
                                                                <strong><?php echo htmlspecialchars($record['senior_name']); ?></strong>?</p>
                                                                <p class="text-danger"><strong>Warning:</strong> This action cannot be undone.</p>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                <form method="POST" action="medical_records.php">
                                                                    <input type="hidden" name="id" value="<?php echo $record['id']; ?>">
                                                                    <button type="submit" name="delete" class="btn btn-danger">Delete</button>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <div class="d-flex justify-content-between align-items-center mt-4">
                            <p>Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $perPage, $totalRecords); ?> of <?php echo $totalRecords; ?> entries</p>
                            
                            <?php 
                            $queryParams = [];
                            if ($senior_id) $queryParams['senior_id'] = $senior_id;
                            if ($from_date) $queryParams['from_date'] = $from_date;
                            if ($to_date) $queryParams['to_date'] = $to_date;
                            if (!empty($search)) $queryParams['search'] = $search;
                            if ($perPage != DEFAULT_PAGINATION_LIMIT) $queryParams['per_page'] = $perPage;
                            
                            $queryString = !empty($queryParams) ? '&' . http_build_query($queryParams) : '';
                            
                            echo generatePagination($page, $totalPages, "medical_records.php?$queryString");
                            ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <?php if (!empty($whereClause)): ?>
                                <p><i class="fas fa-info-circle me-2"></i> No medical records found matching your filter criteria.</p>
                                <p><a href="medical_records.php" class="alert-link">Clear filters</a> to see all medical records.</p>
                            <?php else: ?>
                                <p><i class="fas fa-info-circle me-2"></i> No medical records found in the system.</p>
                                <p><a href="medical_record_add.php" class="alert-link">Add a new medical record</a> to get started.</p>
                            <?php endif; ?>
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
                if (link.getAttribute('href') === currentPage) {
                    link.classList.add('active');
                }
            });
        });
    </script>
</body>
</html>
