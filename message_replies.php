<?php
/**
 * AgeWell Senior Care and Support System
 * Message Replies History Page
 */

require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    setAlert('danger', 'Unauthorized access.');
    redirect('login.php');
}

$pageTitle = 'Message Reply History';

// Pagination setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = DEFAULT_PAGINATION_LIMIT;
$offset = ($page - 1) * $limit;

// Get total replies
$totalReplies = fetchOne("SELECT COUNT(*) as count FROM message_replies");
$totalPages = ceil($totalReplies['count'] / $limit);

// Get all message replies with pagination
$replies = fetchAll(
    "SELECT r.*, c.subject as original_subject, c.message as original_message 
     FROM message_replies r 
     LEFT JOIN contact_messages c ON r.contact_message_id = c.id 
     ORDER BY r.created_at DESC 
     LIMIT ? OFFSET ?",
    [$limit, $offset],
    'ii'
) ?? [];

require_once 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-3 col-lg-2">
            <?php require_once 'includes/sidebar.php'; ?>
        </div>
        
        <div class="col-md-9 col-lg-10">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Message Reply History</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="contact_messages.php" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-envelope me-1"></i> Contact Messages
                    </a>
                </div>
            </div>

            <div class="card shadow mb-4">
                <div class="card-body">
                    <?php if (!empty($replies)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Admin</th>
                                        <th>Recipient</th>
                                        <th>Subject</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($replies as $reply): ?>
                                        <tr>
                                            <td><?php echo date('M d, Y H:i', strtotime($reply['created_at'])); ?></td>
                                            <td><?php echo htmlspecialchars($reply['admin_name']); ?></td>
                                            <td><?php echo htmlspecialchars($reply['recipient_email']); ?></td>
                                            <td><?php echo htmlspecialchars($reply['subject']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $reply['status'] === 'sent' ? 'success' : 'danger'; ?>">
                                                    <?php echo ucfirst($reply['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-info view-reply" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#viewReplyModal" 
                                                        data-reply-id="<?php echo $reply['id']; ?>"
                                                        data-recipient="<?php echo htmlspecialchars($reply['recipient_email']); ?>"
                                                        data-subject="<?php echo htmlspecialchars($reply['subject']); ?>"
                                                        data-message="<?php echo htmlspecialchars($reply['message']); ?>"
                                                        data-original-subject="<?php echo htmlspecialchars($reply['original_subject'] ?? ''); ?>"
                                                        data-original-message="<?php echo htmlspecialchars($reply['original_message'] ?? ''); ?>">
                                                    <i class="fas fa-eye"></i> View
                                                </button>
                                                <a href="mailto:<?php echo htmlspecialchars($reply['recipient_email']); ?>" 
                                                   class="btn btn-sm btn-primary">
                                                    <i class="fas fa-reply"></i> Send New
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
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
                            <i class="fas fa-info-circle me-2"></i> No email replies have been sent yet.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- View Reply Modal -->
<div class="modal fade" id="viewReplyModal" tabindex="-1" aria-labelledby="viewReplyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewReplyModalLabel">View Email Reply</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <h6>Original Message</h6>
                    <div class="card mb-3">
                        <div class="card-body">
                            <p><strong>Subject:</strong> <span id="original-subject"></span></p>
                            <p><strong>Message:</strong></p>
                            <p id="original-message" class="border-top pt-2"></p>
                        </div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <h6>Reply Details</h6>
                    <p><strong>To:</strong> <span id="view-recipient"></span></p>
                    <p><strong>Subject:</strong> <span id="view-subject"></span></p>
                    <div class="border p-3 bg-light">
                        <p id="view-message"></p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // View reply modal
    const viewReplyModal = document.getElementById('viewReplyModal');
    if (viewReplyModal) {
        viewReplyModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const recipient = button.getAttribute('data-recipient');
            const subject = button.getAttribute('data-subject');
            const message = button.getAttribute('data-message');
            const originalSubject = button.getAttribute('data-original-subject');
            const originalMessage = button.getAttribute('data-original-message');
            
            document.getElementById('view-recipient').textContent = recipient;
            document.getElementById('view-subject').textContent = subject;
            document.getElementById('view-message').innerHTML = message.replace(/\n/g, '<br>');
            document.getElementById('original-subject').textContent = originalSubject;
            document.getElementById('original-message').innerHTML = originalMessage.replace(/\n/g, '<br>');
        });
    }
});
</script> 