<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    setAlert('danger', 'Unauthorized access.');
    redirect('login.php');
}

$pageTitle = 'Contact Messages';

if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $action = $_GET['action'];
    
    if ($action === 'mark-read') {
        query("UPDATE contact_messages SET status = 'read' WHERE id = ?", [$id], 'i');
        setAlert('success', 'Message marked as read.');
    } elseif ($action === 'mark-unread') {
        query("UPDATE contact_messages SET status = 'unread' WHERE id = ?", [$id], 'i');
        setAlert('success', 'Message marked as unread.');
    } elseif ($action === 'delete') {
        query("DELETE FROM contact_messages WHERE id = ?", [$id], 'i');
        setAlert('success', 'Message deleted successfully.');
    }
    
    redirect('contact_messages.php');
}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$totalMessages = fetchOne("SELECT COUNT(*) as count FROM contact_messages");
$totalPages = ceil($totalMessages['count'] / $limit);

$messages = fetchAll(
    "SELECT * FROM contact_messages ORDER BY created_at DESC LIMIT ? OFFSET ?",
    [$limit, $offset],
    'ii'
) ?? [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Messages - AgeWell Senior Care</title>
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

        .table-active {
            background-color: rgba(69, 243, 255, 0.1);
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

        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
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
            background: #3db857;
            border-color: #3db857;
            color: #1c1c1c;
        }

        .btn-danger {
            background: #ff3860;
            border-color: #ff3860;
        }

        .btn-danger:hover {
            background: #e63055;
            border-color: #e63055;
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
            background: #ffc107;
            border-color: #ffc107;
            color: #1c1c1c;
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

        .action-buttons {
            display: flex;
            gap: 5px;
        }

        .message-details {
            background: #28292d;
            border: 1px solid rgba(69, 243, 255, 0.3);
            border-radius: 8px;
            padding: 15px;
        }

        .message-details h5 {
            color: #45f3ff;
            font-size: 16px;
        }

        .form-control, .form-check-input {
            background: #28292d;
            border: 1px solid rgba(69, 243, 255, 0.3);
            color: #cdcdcd;
        }

        .form-control:focus {
            border-color: #45f3ff;
            box-shadow: 0 0 5px rgba(69, 243, 255, 0.5);
            background: #28292d;
            color: #cdcdcd;
        }

        .form-label {
            color: #45f3ff;
            font-weight: 500;
        }

        .pagination .page-item .page-link {
            background: #28292d;
            border: 1px solid rgba(69, 243, 255, 0.3);
            color: #cdcdcd;
            margin: 0 2px;
        }

        .pagination .page-item.active .page-link {
            background: #45f3ff;
            border-color: #45f3ff;
            color: #1c1c1c;
        }

        .pagination .page-item .page-link:hover {
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
                <li><a href="contact_messages.php" class="active"><i class="fas fa-envelope"></i> Contact Messages</a></li>
            </ul>
            <h6>Admin Actions</h6>
            <ul>
                <li><a href="users.php"><i class="fas fa-users-cog"></i> Manage Users</a></li>
                <li><a href="activity_logs.php"><i class="fas fa-history"></i> View Activity Logs</a></li>
            </ul>
        </div>

        <div class="main-content">
            <div class="page-title">
                <h1>Contact Messages</h1>
            </div>

            <div class="card">
                <div class="card-body">
                    <?php if (!empty($messages)): ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Subject</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($messages as $message): ?>
                                        <tr class="<?php echo $message['status'] === 'unread' ? 'table-active' : ''; ?>">
                                            <td><?php echo htmlspecialchars($message['name']); ?></td>
                                            <td><?php echo htmlspecialchars($message['email']); ?></td>
                                            <td><?php echo htmlspecialchars($message['phone'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($message['subject']); ?></td>
                                            <td><?php echo date('M d, Y H:i', strtotime($message['created_at'])); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $message['status'] === 'unread' ? 'warning' : 'success'; ?>">
                                                    <?php echo ucfirst($message['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <?php if ($message['status'] === 'unread'): ?>
                                                        <a href="?action=mark-read&id=<?php echo $message['id']; ?>" 
                                                           class="btn btn-sm btn-success" title="Mark as Read">
                                                            <i class="fas fa-check"></i>
                                                        </a>
                                                    <?php else: ?>
                                                        <a href="?action=mark-unread&id=<?php echo $message['id']; ?>" 
                                                           class="btn btn-sm btn-warning" title="Mark as Unread">
                                                            <i class="fas fa-undo"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    <a href="?action=delete&id=<?php echo $message['id']; ?>" 
                                                       class="btn btn-sm btn-danger" 
                                                       onclick="return confirm('Are you sure you want to delete this message?')"
                                                       title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-sm btn-info toggle-message" 
                                                            data-message-id="<?php echo $message['id']; ?>"
                                                            title="View Message">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr id="message-content-<?php echo $message['id']; ?>" class="message-content-row" style="display: none;">
                                            <td colspan="7">
                                                <div class="message-details">
                                                    <h5><i class="fas fa-envelope me-2"></i>Message Details</h5>
                                                    <p><strong>From:</strong> <?php echo htmlspecialchars($message['name']); ?> (<?php echo htmlspecialchars($message['email']); ?>)</p>
                                                    <p><strong>Subject:</strong> <?php echo htmlspecialchars($message['subject']); ?></p>
                                                    <p><strong>Date:</strong> <?php echo date('M d, Y H:i', strtotime($message['created_at'])); ?></p>
                                                    <hr>
                                                    <div class="message-text">
                                                        <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                                                    </div>
                                                </div>
                                                <div class="reply-form mt-4">
                                                    <h5><i class="fas fa-reply me-2"></i>Reply to this message</h5>
                                                    <form action="send_reply.php" method="post">
                                                        <input type="hidden" name="message_id" value="<?php echo $message['id']; ?>">
                                                        <input type="hidden" name="to" value="<?php echo htmlspecialchars($message['email']); ?>">
                                                        <input type="hidden" name="recipient_name" value="<?php echo htmlspecialchars($message['name']); ?>">
                                                        <div class="mb-3">
                                                            <label for="subject-<?php echo $message['id']; ?>" class="form-label">Subject:</label>
                                                            <input type="text" class="form-control" id="subject-<?php echo $message['id']; ?>" 
                                                                   name="subject" value="Re: <?php echo htmlspecialchars($message['subject']); ?>">
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="message-<?php echo $message['id']; ?>" class="form-label">Message:</label>
                                                            <textarea class="form-control" id="message-<?php echo $message['id']; ?>" 
                                                                      name="message" rows="5" required>Dear <?php echo htmlspecialchars($message['name']); ?>,

Thank you for contacting AgeWell Senior Care.


Best regards,
<?php echo $_SESSION['user_name'] ?? 'AgeWell Admin'; ?></textarea>
                                                        </div>
                                                        <div class="form-check mb-3">
                                                            <input class="form-check-input" type="checkbox" 
                                                                   id="mark-read-<?php echo $message['id']; ?>" 
                                                                   name="mark_as_read" checked>
                                                            <label class="form-check-label" for="mark-read-<?php echo $message['id']; ?>">
                                                                Mark message as read after sending reply
                                                            </label>
                                                        </div>
                                                        <div class="d-flex justify-content-end">
                                                            <button type="button" class="btn btn-secondary me-2 toggle-message" 
                                                                    data-message-id="<?php echo $message['id']; ?>">Cancel</button>
                                                            <button type="submit" class="btn btn-primary">
                                                                <i class="fas fa-paper-plane me-1"></i> Send Reply
                                                            </button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if ($totalPages > 1): ?>
                            <nav class="mt-4">
                                <ul class="pagination justify-content-center">
                                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                        <li class="page-item <?php echo $page === $i ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i> No messages found.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const toggleButtons = document.querySelectorAll('.toggle-message');
        toggleButtons.forEach(button => {
            button.addEventListener('click', function() {
                const messageId = this.getAttribute('data-message-id');
                const contentRow = document.getElementById('message-content-' + messageId);
                
                document.querySelectorAll('.message-content-row').forEach(row => {
                    if (row !== contentRow) {
                        row.style.display = 'none';
                    }
                });
                
                contentRow.style.display = contentRow.style.display === 'none' ? 'table-row' : 'none';
            });
        });
    });
    </script>
</body>
</html>