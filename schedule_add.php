<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    setAlert('danger', 'Unauthorized access.');
    redirect('login.php');
}

$pageTitle = 'Add Schedule';

// Get all active caregivers
$caregivers = fetchAll("SELECT id, name FROM userdetails WHERE role = 'caregiver' AND status = 'active'");

// Get all seniors
$seniors = fetchAll("SELECT id, name FROM seniorcitizens WHERE status = 'active' ORDER BY name ASC");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $caregiver_id = cleanInput($_POST['caregiver_id'] ?? '');
    $senior_id = cleanInput($_POST['senior_id'] ?? '');
    $schedule_date = cleanInput($_POST['schedule_date'] ?? '');
    $start_time = cleanInput($_POST['start_time'] ?? '');
    $duration = cleanInput($_POST['duration'] ?? '');
    $notes = cleanInput($_POST['notes'] ?? '');

    if (empty($caregiver_id) || empty($senior_id) || empty($schedule_date) || empty($start_time) || empty($duration)) {
        setAlert('danger', 'Please fill in all required fields.');
    } else {
        $inserted = query(
            "INSERT INTO schedules (caregiver_id, senior_id, schedule_date, start_time, duration, notes, status) 
             VALUES (?, ?, ?, ?, ?, ?, 'pending')",
            [$caregiver_id, $senior_id, $schedule_date, $start_time, $duration, $notes],
            'iissss'
        );

        if ($inserted) {
            setAlert('success', 'Schedule added successfully.');
            redirect('schedule_view.php');
        } else {
            setAlert('danger', 'Failed to add schedule.');
        }
    }
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
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Add New Schedule</h6>
                </div>
                <div class="card-body">
                    <form method="POST" class="needs-validation" novalidate>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="caregiver_id">Caregiver *</label>
                                <select class="form-control" id="caregiver_id" name="caregiver_id" required>
                                    <option value="">Select Caregiver...</option>
                                    <?php foreach ($caregivers as $caregiver): ?>
                                        <option value="<?php echo $caregiver['id']; ?>">
                                            <?php echo htmlspecialchars($caregiver['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="senior_id">Senior Citizen *</label>
                                <select class="form-control" id="senior_id" name="senior_id" required>
                                    <option value="">Select Senior...</option>
                                    <?php foreach ($seniors as $senior): ?>
                                        <option value="<?php echo $senior['id']; ?>">
                                            <?php echo htmlspecialchars($senior['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="schedule_date">Date *</label>
                                <input type="date" class="form-control" id="schedule_date" name="schedule_date" 
                                       min="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="start_time">Start Time *</label>
                                <input type="time" class="form-control" id="start_time" name="start_time" required>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="duration">Duration (hours) *</label>
                                <input type="number" class="form-control" id="duration" name="duration" 
                                       min="1" max="12" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="notes">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Add Schedule
                            </button>
                            <a href="schedule_view.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Back to Schedules
                            </a>
                        </div>
                    </form>
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

    select option {
        background: #2A2A2A;
        color: #fff;
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

    .btn-secondary {
        background: #2A2A2A;
        border: 1px solid #444;
        color: #fff;
    }

    .btn-secondary:hover {
        background: #333;
        border-color: #00F7FF;
        color: #00F7FF;
    }

    label {
        color: #00F7FF;
        margin-bottom: 0.5rem;
    }

    .text-primary {
        color: #00F7FF !important;
    }
</style>