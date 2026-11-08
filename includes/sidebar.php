<h6>Quick Actions</h6>
<ul>
    <li>
        <a href="dashboard.php">
            <i class="fas fa-tachometer-alt"></i>
            Dashboard
        </a>
    </li>
</ul>

<h6>Quick Reports</h6>
<ul>
    <li>
        <a href="reports.php?type=seniors">
            <i class="fas fa-users"></i>
            Senior Citizens Report
        </a>
    </li>
    <li>
        <a href="reports.php?type=appointments">
            <i class="fas fa-calendar-check"></i>
            Appointments Report
        </a>
    </li>
    <li>
        <a href="reports.php?type=health_metrics">
            <i class="fas fa-chart-line"></i>
            Health Metrics Report
        </a>
    </li>
</ul>

<?php if (isAdmin()): ?>
<h6>Admin Actions</h6>
<ul>
    <li>
        <a href="user_add.php">
            <i class="fas fa-user-plus"></i>
            Add New User
        </a>
    </li>
    <li>
        <a href="users.php">
            <i class="fas fa-users-cog"></i>
            Manage Users
        </a>
    </li>
    <li>
        <a href="activity_logs.php">
            <i class="fas fa-history"></i>
            View Activity Logs
        </a>
    </li>
    <li>
        <a href="contact_messages.php">
            <i class="fas fa-envelope"></i>
            Contact Messages
        </a>
    </li>
    <li>
        <a href="message_replies.php">
            <i class="fas fa-reply-all"></i>
            Message Replies
        </a>
    </li>
</ul>
<?php endif; ?>
