<?php
/**
 * AgeWell Senior Care and Support System
 * Notifications Page
 */

// Include configuration and required files
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/notification_handler.php';

// Check if user is logged in
if (!isLoggedIn()) {
    setAlert('danger', 'Please login to access this page.');
    redirect('login.php');
}

// AJAX actions for marking notifications as read
if (isset($_GET['action'])) {
    $action = cleanInput($_GET['action']);
    $userId = $_SESSION['user_id'];
    
    // Response array
    $response = ['success' => false, 'message' => '', 'unreadCount' => 0];
    
    // Handle marking a single notification as read
    if ($action === 'mark_read' && isset($_GET['id'])) {
        $notificationId = (int)$_GET['id'];
        $marked = markNotificationAsRead($notificationId, $userId);
        
        if ($marked) {
            $response['success'] = true;
            $response['message'] = 'Notification marked as read successfully.';
            $response['unreadCount'] = getUnreadNotificationCount($userId);
        } else {
            $response['message'] = 'Failed to mark notification as read.';
        }
        
        // Return JSON response
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
    
    // Handle marking all notifications as read
    if ($action === 'mark_all_read') {
        $marked = markAllNotificationsAsRead($userId);
        
        if ($marked) {
            $response['success'] = true;
            $response['message'] = 'All notifications marked as read successfully.';
            $response['unreadCount'] = 0;
        } else {
            $response['message'] = 'Failed to mark all notifications as read.';
        }
        
        // Return JSON response
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
}

// Set page title
$pageTitle = 'Notifications';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : DEFAULT_PAGINATION_LIMIT;
$offset = ($page - 1) * $perPage;

// Filters
$type = isset($_GET['type']) ? cleanInput($_GET['type']) : null;
$isRead = isset($_GET['is_read']) ? ($_GET['is_read'] === '1' ? true : false) : null;
$isEmergency = isset($_GET['is_emergency']) ? ($_GET['is_emergency'] === '1' ? true : false) : null;

// Current user ID
$userId = $_SESSION['user_id'];

// Build query conditions
$whereConditions = ["(for_user_id = ? OR for_user_id IS NULL)"]; // User-specific or system-wide notifications
$params = [$userId];
$types = 'i';

if ($type && in_array($type, ['info', 'warning', 'success', 'danger'])) {
    $whereConditions[] = 'type = ?';
    $params[] = $type;
    $types .= 's';
}

if ($isRead !== null) {
    $whereConditions[] = 'is_read = ?';
    $params[] = $isRead;
    $types .= 'i';
}

if ($isEmergency !== null) {
    $whereConditions[] = 'is_emergency = ?';
    $params[] = $isEmergency;
    $types .= 'i';
}

// Combine conditions
$whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

// Get total number of notifications for pagination
$countSql = "SELECT COUNT(*) as total FROM notifications $whereClause";
$countResult = fetchOne($countSql, $params, $types);
$totalNotifications = $countResult ? $countResult['total'] : 0;
$totalPages = ceil($totalNotifications / $perPage);

// Get notifications data
$sql = "SELECT * FROM notifications $whereClause ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;
$types .= 'ii';

$notifications = fetchAll($sql, $params, $types);

// Include header
require_once 'includes/header.php';
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
                <h1 class="h2"><i class="fas fa-bell me-2"></i> Notifications</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <?php if ($totalNotifications > 0): ?>
                        <button id="markAllNotificationsRead" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-check-double me-1"></i> Mark All as Read
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <form method="GET" action="notifications.php" class="row g-3">
                        <!-- Type filter -->
                        <div class="col-md-3">
                            <select class="form-select" id="type" name="type" onchange="this.form.submit()">
                                <option value="">All Types</option>
                                <option value="info" <?php echo $type === 'info' ? 'selected' : ''; ?>>Info</option>
                                <option value="warning" <?php echo $type === 'warning' ? 'selected' : ''; ?>>Warning</option>
                                <option value="success" <?php echo $type === 'success' ? 'selected' : ''; ?>>Success</option>
                                <option value="danger" <?php echo $type === 'danger' ? 'selected' : ''; ?>>Danger</option>
                            </select>
                        </div>
                        
                        <!-- Read status filter -->
                        <div class="col-md-3">
                            <select class="form-select" id="is_read" name="is_read" onchange="this.form.submit()">
                                <option value="">All Statuses</option>
                                <option value="0" <?php echo $isRead === false ? 'selected' : ''; ?>>Unread</option>
                                <option value="1" <?php echo $isRead === true ? 'selected' : ''; ?>>Read</option>
                            </select>
                        </div>
                        
                        <!-- Emergency filter -->
                        <div class="col-md-3">
                            <select class="form-select" id="is_emergency" name="is_emergency" onchange="this.form.submit()">
                                <option value="">All Priorities</option>
                                <option value="1" <?php echo $isEmergency === true ? 'selected' : ''; ?>>Emergency</option>
                                <option value="0" <?php echo $isEmergency === false ? 'selected' : ''; ?>>Normal</option>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <a href="notifications.php" class="btn btn-secondary w-100">
                                <i class="fas fa-times me-1"></i> Clear Filters
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Notifications List -->
            <div class="card shadow-sm">
                <div class="card-body">
                    <?php if ($notifications && count($notifications) > 0): ?>
                        <div class="notification-list">
                            <?php foreach ($notifications as $notification): ?>
                                <div class="notification-item <?php echo $notification['is_read'] ? '' : 'unread'; ?> <?php echo $notification['type']; ?>">
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
                                        <?php if (!$notification['is_read']): ?>
                                            <button class="btn btn-sm btn-outline-primary mark-notification-read" 
                                                    data-notification-id="<?php echo $notification['id']; ?>">
                                                <i class="fas fa-check me-1"></i> Mark as Read
                                            </button>
                                        <?php else: ?>
                                            <span class="text-muted"><i class="fas fa-check me-1"></i> Read</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Pagination -->
                        <div class="d-flex justify-content-between align-items-center mt-4">
                            <p>Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $perPage, $totalNotifications); ?> of <?php echo $totalNotifications; ?> notifications</p>
                            
                            <?php 
                            $queryParams = [];
                            if ($type) $queryParams['type'] = $type;
                            if ($isRead !== null) $queryParams['is_read'] = $isRead ? '1' : '0';
                            if ($isEmergency !== null) $queryParams['is_emergency'] = $isEmergency ? '1' : '0';
                            if ($perPage != DEFAULT_PAGINATION_LIMIT) $queryParams['per_page'] = $perPage;
                            
                            $queryString = !empty($queryParams) ? '&' . http_build_query($queryParams) : '';
                            
                            echo generatePagination($page, $totalPages, "notifications.php?$queryString");
                            ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <p>No notifications found.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Mark individual notification as read
        const markReadButtons = document.querySelectorAll('.mark-notification-read');
        markReadButtons.forEach(button => {
            button.addEventListener('click', function() {
                const notificationId = this.getAttribute('data-notification-id');
                
                fetch('notifications.php?action=mark_read&id=' + notificationId, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update button
                        this.disabled = true;
                        this.innerHTML = '<i class="fas fa-check"></i> Read';
                        
                        // Update notification item
                        const notificationItem = this.closest('.notification-item');
                        if (notificationItem) {
                            notificationItem.classList.remove('unread');
                        }
                        
                        // Update notification count in navbar
                        const countBadge = document.querySelector('.badge.bg-danger');
                        if (countBadge && data.unreadCount > 0) {
                            countBadge.textContent = data.unreadCount;
                        } else if (countBadge) {
                            countBadge.style.display = 'none';
                        }
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                });
            });
        });
        
        // Mark all notifications as read
        const markAllButton = document.getElementById('markAllNotificationsRead');
        if (markAllButton) {
            markAllButton.addEventListener('click', function() {
                if (confirm('Are you sure you want to mark all notifications as read?')) {
                    fetch('notifications.php?action=mark_all_read', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Reload the page to show updated list
                            window.location.reload();
                        } else {
                            alert('Error: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                    });
                }
            });
        }
    });
</script>

<?php
// Include footer
require_once 'includes/footer.php';
?>
