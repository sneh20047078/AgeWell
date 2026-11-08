<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    setAlert('danger', 'Unauthorized access.');
    redirect('login.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = cleanInput($_POST['name'] ?? '');
    $email = cleanInput($_POST['email'] ?? '');
    $phone = cleanInput($_POST['phone'] ?? '');
    $address = cleanInput($_POST['address'] ?? '');
    $specialization = cleanInput($_POST['specialization'] ?? '');
    $experience = cleanInput($_POST['experience'] ?? '');
    
    // Validate inputs
    if (empty($name) || empty($email) || empty($phone)) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Check if email already exists
        $exists = fetchOne("SELECT id FROM userdetails WHERE email = ?", [$email], 's');
        
        if ($exists) {
            $error = 'Email already registered.';
        } else {
            // Generate temporary password
            $tempPassword = bin2hex(random_bytes(8));
            $hashedPassword = password_hash($tempPassword, PASSWORD_DEFAULT);
            
            // Insert caregiver
            $inserted = query(
                "INSERT INTO userdetails (name, email, password, phone, address, role, specialization, experience, status) 
                 VALUES (?, ?, ?, ?, ?, 'caregiver', ?, ?, 'active')",
                [$name, $email, $hashedPassword, $phone, $address, $specialization, $experience],
                'sssssss'
            );
            
            if ($inserted) {
                // Send email with temporary password
                // TODO: Implement email sending functionality
                
                setAlert('success', 'Caregiver added successfully. Temporary password: ' . $tempPassword);
                redirect('admin_dashboard.php');
            } else {
                $error = 'Failed to add caregiver. Please try again.';
            }
        }
    }
}

$pageTitle = 'Add Caregiver';
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
                    <h6 class="m-0 font-weight-bold text-primary">Add New Caregiver</h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>

                    <form method="POST" class="needs-validation" novalidate>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="name">Full Name *</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="email">Email Address *</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="phone">Phone Number *</label>
                                <input type="tel" class="form-control" id="phone" name="phone" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="specialization">Specialization</label>
                                <input type="text" class="form-control" id="specialization" name="specialization">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="address">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="3"></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="experience">Years of Experience</label>
                            <input type="number" class="form-control" id="experience" name="experience" min="0">
                        </div>

                        <button class="btn btn-primary" type="submit">
                            <i class="fas fa-plus"></i> Add Caregiver
                        </button>
                        <a href="admin_dashboard.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>
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
        font-family: 'Inter', sans-serif;
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
        padding: 1.5rem;
    }

    .card-body {
        padding: 2rem;
    }

    .form-control {
        background: transparent;
        border: none;
        border-bottom: 1px solid #444;
        border-radius: 0;
        color: #fff;
        padding: 0.75rem 0;
        transition: all 0.3s ease;
    }

    .form-control:focus {
        background: transparent;
        border-color: #00F7FF;
        box-shadow: none;
        color: #fff;
    }

    .form-control::placeholder {
        color: #666;
    }

    textarea.form-control {
        border: 1px solid #444;
        border-radius: 5px;
        padding: 0.75rem;
    }

    label {
        color: #00F7FF;
        font-size: 0.9rem;
        font-weight: 500;
        margin-bottom: 0.5rem;
    }

    .btn {
        border-radius: 5px;
        font-size: 0.9rem;
        font-weight: 500;
        padding: 0.75rem 1.5rem;
        transition: all 0.3s ease;
    }

    .btn-primary {
        background: #00F7FF;
        border: none;
        color: #000;
    }

    .btn-primary:hover {
        background: #00C3FF;
        transform: translateY(-2px);
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

    .alert {
        background: rgba(255, 76, 76, 0.1);
        border: 1px solid #FF4C4C;
        border-radius: 5px;
        color: #FF4C4C;
    }

    .text-primary {
        color: #00F7FF !important;
    }

    .mb-3 {
        margin-bottom: 1.5rem !important;
    }

    .fas {
        margin-right: 0.5rem;
    }
</style>