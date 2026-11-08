<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    setAlert('danger', 'Unauthorized access.');
    redirect('login.php');
}

$pageTitle = 'Schedule View';

// Get all caregivers
$caregivers = fetchAll("SELECT id, name FROM userdetails WHERE role = 'caregiver' AND status = 'active'");

// Get selected caregiver's schedule
$selectedCaregiver = $_GET['caregiver_id'] ?? null;
$schedules = [];

if ($selectedCaregiver) {
    $schedules = fetchAll(
        "SELECT s.*, sc.name as senior_name 
         FROM schedules s 
         JOIN seniorcitizens sc ON s.senior_id = sc.id 
         WHERE s.caregiver_id = ? 
         ORDER BY s.schedule_date, s.start_time",
        [$selectedCaregiver],
        'i'
    );
}

require_once 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-3 col-lg-2">
            <?php require_once 'includes/sidebar.php'; ?>
        </div>
        
        <div class="col-md-9 col-lg-10">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Caregiver Schedules</h6>
                    <a href="schedule_add.php" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus"></i> Add Schedule
                    </a>
                </div>
                <div class="card-body">
                    <!-- Caregiver Selection -->
                    <div class="mb-4">
                        <label for="caregiver" class="form-label">Select Caregiver</label>
                        <select class="form-control" id="caregiver" onchange="window.location.href='?caregiver_id=' + this.value">
                            <option value="">Choose a caregiver...</option>
                            <?php foreach ($caregivers as $caregiver): ?>
                                <option value="<?php echo $caregiver['id']; ?>" 
                                        <?php echo $selectedCaregiver == $caregiver['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($caregiver['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <?php if ($selectedCaregiver): ?>
                        <!-- Schedule Calendar -->
                        <div class="schedule-calendar">
                            <?php if (!empty($schedules)): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Time</th>
                                                <th>Senior</th>
                                                <th>Duration</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($schedules as $schedule): ?>
                                                <tr>
                                                    <td><?php echo date('M d, Y', strtotime($schedule['schedule_date'])); ?></td>
                                                    <td><?php echo date('h:i A', strtotime($schedule['start_time'])); ?></td>
                                                    <td><?php echo htmlspecialchars($schedule['senior_name']); ?></td>
                                                    <td><?php echo $schedule['duration']; ?> hours</td>
                                                    <td>
                                                        <span class="badge bg-<?php echo getStatusBadgeClass($schedule['status']); ?>">
                                                            <?php echo ucfirst($schedule['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group">
                                                            <a href="schedule_edit.php?id=<?php echo $schedule['id']; ?>" 
                                                               class="btn btn-sm btn-info">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                            <button class="btn btn-sm btn-danger delete-schedule" 
                                                                    data-id="<?php echo $schedule['id']; ?>">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-center">No schedules found for this caregiver.</p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-center">Please select a caregiver to view their schedule.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    body {
        background: #1E1E1E;
        color: #fff;
    }

    .container-fluid {
        padding: 20px;
    }

    .card {
        background: #2A2A2A;
        border: 1px solid #00F7FF;
        border-radius: 10px;
        box-shadow: 0 0 20px rgba(0, 247, 255, 0.1);
    }

    .card-header {
        background: rgba(0, 247, 255, 0.1);
        border-bottom: 1px solid #00F7FF;
    }

    .form-control {
        background: transparent;
        border: 1px solid #444;
        color: #fff;
        border-radius: 5px;
    }

    .form-control:focus {
        background: #2A2A2A;
        border-color: #00F7FF;
        color: #fff;
        box-shadow: 0 0 0 0.2rem rgba(0, 247, 255, 0.25);
    }

    .table {
        color: #fff;
    }

    .table thead th {
        border-bottom: 2px solid #00F7FF;
        color: #00F7FF;
        background: rgba(0, 247, 255, 0.1);
    }

    .table td {
        border-color: #444;
    }

    .btn-primary {
        background: #00F7FF;
        border: none;
        color: #000;
    }

    .btn-primary:hover {
        background: #00C3FF;
        color: #000;
    }

    .badge {
        padding: 0.5em 1em;
    }

    .bg-pending { background: #FFB800; }
    .bg-completed { background: #00FF9D; }
    .bg-cancelled { background: #FF4C4C; }

    .btn-group .btn {
        margin: 0 2px;
    }

    label {
        color: #00F7FF;
        margin-bottom: 0.5rem;
    }

    select option {
        background: #2A2A2A;
        color: #fff;
    }
</style>

<?php
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'pending':
            return 'warning';
        case 'completed':
            return 'success';
        case 'cancelled':
            return 'danger';
        default:
            return 'info';
    }
}
?>