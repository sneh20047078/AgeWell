<?php
/**
 * AgeWell Senior Care and Support System
 * Seniors List Page
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
$pageTitle = 'Senior Citizens';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : DEFAULT_PAGINATION_LIMIT;
$offset = ($page - 1) * $perPage;

// Search functionality
$search = isset($_GET['search']) ? cleanInput($_GET['search']) : '';
$whereClause = '';
$params = [];
$types = '';

if (!empty($search)) {
    $whereClause = "WHERE s.name LIKE ? OR s.phone LIKE ?";
    $params = ["%$search%", "%$search%"];
    $types = 'ss';
}

// Main query
$sql = "SELECT s.*, 
        COALESCE(u.name, 'Unassigned') as caregiver_name 
        FROM seniorcitizens s 
        LEFT JOIN userdetails u ON s.caregiver_id = u.id 
        $whereClause 
        ORDER BY s.name ASC LIMIT ? , ?";

// Add pagination parameters
$queryParams = $params;
$queryParams[] = (int)$offset;  // Convert to integer
$queryParams[] = (int)$perPage; // Convert to integer
$queryTypes = $types . 'ii';

// Remove duplicate query section and debug logs
error_log("Final SQL: " . $sql);
error_log("Parameters: " . print_r($queryParams, true));
error_log("Types: " . $queryTypes);

// Debug information
error_log("Search term: " . $search);
error_log("Where clause: " . $whereClause);
error_log("Parameters: " . print_r($params, true));

// Get total number of seniors for pagination
$countSql = "SELECT COUNT(*) as total FROM seniorcitizens s $whereClause";
$countResult = fetchOne($countSql, $params, $types);
$totalSeniors = $countResult ? $countResult['total'] : 0;
$totalPages = ceil($totalSeniors / $perPage);

// Main query
$sql = "SELECT s.*, 
        COALESCE(u.name, 'Unassigned') as caregiver_name 
        FROM seniorcitizens s 
        LEFT JOIN userdetails u ON s.caregiver_id = u.id 
        $whereClause 
        ORDER BY s.name ASC LIMIT ?, ?";

// Add pagination parameters after the search parameters
$queryParams = $params;
$queryParams[] = $offset;
$queryParams[] = $perPage;
$queryTypes = $types . 'ii';

$seniors = fetchAll($sql, $queryParams, $queryTypes) ?: [];

// If no results, check for database errors
if (empty($seniors)) {
    error_log("No seniors found or database error occurred");
}

// Handle delete request
if (isset($_POST['delete']) && isset($_POST['id'])) {
    $seniorId = (int)$_POST['id'];
    
    // Check if user has permission to delete
    if (!isAdmin()) {
        setAlert('danger', 'You do not have permission to delete senior citizens.');
        redirect('seniors.php');
        exit;
    }
    
    // Get senior details for logging
    $senior = getSeniorById($seniorId);
    
    if (!$senior) {
        setAlert('danger', 'Senior citizen not found.');
        redirect('seniors.php');
        exit;
    }
    
    // Delete the senior
    $deleted = delete('seniorcitizens', 'id = ?', [$seniorId], 'i');
    
    if ($deleted) {
        // Log the activity
        logActivity('senior_delete', "Deleted senior citizen: {$senior['name']}");
        setAlert('success', 'Senior citizen deleted successfully.');
    } else {
        setAlert('danger', 'Failed to delete senior citizen. Please try again.');
    }
    
    // Redirect to refresh the page
    redirect('seniors.php');
    exit;
}

// Include header
require_once 'includes/header.php';
?>

<!-- Now start the HTML section -->
<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-3 col-lg-2">
            <?php require_once 'includes/sidebar.php'; ?>
        </div>
        
        <!-- Main Content -->
        <div class="col-md-9 col-lg-10">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="fas fa-user-friends me-2"></i> Senior Citizens</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="senior_add.php" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus me-1"></i> Add New Senior
                    </a>
                </div>
            </div>
            
            <!-- Search Bar -->
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <form method="GET" action="seniors.php" class="row g-3">
                        <div class="col-md-6">
                            <div class="input-group">
                                <input type="text" class="form-control" id="search" name="search" 
                                       placeholder="Search by name, emergency contact, or phone" 
                                       value="<?php echo htmlspecialchars($search); ?>">
                                <button class="btn btn-primary" type="submit">
                                    <i class="fas fa-search"></i> Search
                                </button>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="input-group">
                                <label class="input-group-text" for="per_page">Show</label>
                                <select class="form-select" id="per_page" name="per_page" onchange="this.form.submit()">
                                    <option value="10" <?php echo $perPage == 10 ? 'selected' : ''; ?>>10</option>
                                    <option value="25" <?php echo $perPage == 25 ? 'selected' : ''; ?>>25</option>
                                    <option value="50" <?php echo $perPage == 50 ? 'selected' : ''; ?>>50</option>
                                    <option value="100" <?php echo $perPage == 100 ? 'selected' : ''; ?>>100</option>
                                </select>
                                <span class="input-group-text">entries</span>
                            </div>
                        </div>
                        
                        <?php if (!empty($search)): ?>
                            <div class="col-md-2">
                                <a href="seniors.php" class="btn btn-secondary w-100">
                                    <i class="fas fa-times me-1"></i> Clear
                                </a>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
            
            <!-- Seniors List -->
            <div class="card shadow-sm">
                <div class="card-body">
                    <?php if ($seniors && count($seniors) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-striped">
                                <thead class="table-light">
                                    <tr>
                                        <th>Name</th>
                                        <th>Age</th>
                                        <th>Gender</th>
                                        <th>Phone</th>
                                        <th>Address</th>
                                        <th>Emergency Contact</th>
                                        <th>Medical History</th>
                                        <th>Caregiver</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($seniors as $senior): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($senior['name']); ?></td>
                                            <td><?php echo htmlspecialchars($senior['age']); ?></td>
                                            <td><?php echo ucfirst(htmlspecialchars($senior['gender'])); ?></td>
                                            <td><?php echo htmlspecialchars($senior['phone']); ?></td>
                                            <td><?php echo htmlspecialchars($senior['address']); ?></td>
                                            <td>
                                                <?php echo htmlspecialchars($senior['emergency_contact']); ?>
                                                <br>
                                                <small class="text-muted"><?php echo htmlspecialchars($senior['emergency_phone']); ?></small>
                                            </td>
                                            <td>
                                                <?php 
                                                $history = htmlspecialchars($senior['medical_history']);
                                                echo strlen($history) > 50 ? substr($history, 0, 50) . '...' : $history; 
                                                ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($senior['caregiver_name'] ?? 'Unassigned'); ?></td>
                                            <td>
                                                <!-- Existing action buttons remain the same -->
                                                <div class="btn-group btn-group-sm">
                                                    <a href="senior_profile.php?id=<?php echo $senior['id']; ?>" class="btn btn-info">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="senior_edit.php?id=<?php echo $senior['id']; ?>" class="btn btn-primary">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <?php if (isAdmin()): ?>
                                                        <button type="button" class="btn btn-danger" data-bs-toggle="modal" 
                                                                data-bs-target="#deleteModal<?php echo $senior['id']; ?>">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <!-- Delete Confirmation Modal -->
                                                <?php if (isAdmin()): ?>
                                                    <div class="modal fade" id="deleteModal<?php echo $senior['id']; ?>" tabindex="-1" 
                                                         aria-labelledby="deleteModalLabel<?php echo $senior['id']; ?>" aria-hidden="true">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <div class="modal-header bg-danger text-white">
                                                                    <h5 class="modal-title" id="deleteModalLabel<?php echo $senior['id']; ?>">
                                                                        Confirm Delete
                                                                    </h5>
                                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    <p>Are you sure you want to delete senior citizen <strong><?php echo htmlspecialchars($senior['name']); ?></strong>?</p>
                                                                    <p class="text-danger"><strong>Warning:</strong> This action cannot be undone and will delete all associated records including appointments, health records, and prescriptions.</p>
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                    <form method="POST" action="seniors.php">
                                                                        <input type="hidden" name="id" value="<?php echo $senior['id']; ?>">
                                                                        <button type="submit" name="delete" class="btn btn-danger">Delete</button>
                                                                    </form>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <div class="d-flex justify-content-between align-items-center mt-4">
                            <p>Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $perPage, $totalSeniors); ?> of <?php echo $totalSeniors; ?> entries</p>
                            
                            <?php 
                            $queryParams = [];
                            if (!empty($search)) $queryParams['search'] = $search;
                            if ($perPage != DEFAULT_PAGINATION_LIMIT) $queryParams['per_page'] = $perPage;
                            
                            $queryString = !empty($queryParams) ? '&' . http_build_query($queryParams) : '';
                            
                            echo generatePagination($page, $totalPages, "seniors.php?$queryString");
                            ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <?php if (!empty($search)): ?>
                                <p>No senior citizens found matching your search criteria.</p>
                                <p><a href="seniors.php" class="alert-link">Clear search</a> to see all senior citizens.</p>
                            <?php else: ?>
                                <p>No senior citizens found in the system.</p>
                                <p><a href="senior_add.php" class="alert-link">Add a new senior citizen</a> to get started.</p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
require_once 'includes/footer.php';
?>
