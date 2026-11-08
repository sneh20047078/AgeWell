<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/bulk_email_functions.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    setAlert('danger', 'Unauthorized access.');
    redirect('login.php');
}

$pageTitle = 'Send Bulk Email';
$successMessage = '';
$errorMessage = '';

// Get email templates
$email_templates = getEmailTemplates();

// Get client categories for filtering
$care_types = fetchAll("SELECT DISTINCT care_type FROM clients WHERE care_type IS NOT NULL ORDER BY care_type");
$locations = fetchAll("SELECT id, name FROM locations ORDER BY name");

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_email'])) {
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);
    $use_template = isset($_POST['use_template']) ? $_POST['use_template'] : '';
    
    // Validate form data
    if (empty($subject)) {
        $errorMessage = 'Subject is required.';
    } elseif (empty($message)) {
        $errorMessage = 'Message content is required.';
    } else {
        // Fix common email content issues
        $message = fixEmailContent($message);
        
        // Get recipient selection method
        $selection_method = $_POST['selection_method'];
        
        // Initialize filters
        $filters = [];
        $recipients = [];
        
        if ($selection_method === 'all') {
            // No filters, send to all clients
        } elseif ($selection_method === 'filtered') {
            // Apply filters based on form selection
            if (!empty($_POST['care_type'])) {
                $filters['care_type'] = $_POST['care_type'];
            }
            
            if (!empty($_POST['status'])) {
                $filters['status'] = $_POST['status'];
            }
            
            if (!empty($_POST['location_id'])) {
                $filters['location_id'] = (int)$_POST['location_id'];
            }
            
            if (!empty($_POST['min_age'])) {
                $filters['min_age'] = (int)$_POST['min_age'];
            }
            
            if (!empty($_POST['max_age'])) {
                $filters['max_age'] = (int)$_POST['max_age'];
            }
        } elseif ($selection_method === 'manual') {
            // Parse manually entered emails
            $manual_emails = trim($_POST['manual_emails']);
            $email_list = preg_split('/[,;\s]+/', $manual_emails, -1, PREG_SPLIT_NO_EMPTY);
            
            foreach ($email_list as $email) {
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $recipients[] = $email;
                }
            }
            
            if (empty($recipients)) {
                $errorMessage = 'No valid email addresses provided.';
            }
        }
        
        if (empty($errorMessage)) {
            // Prepare email options
            $options = [
                'use_bcc' => isset($_POST['use_bcc']) && $_POST['use_bcc'] === '1'
            ];
            
            // Handle file attachments
            if (!empty($_FILES['attachments']['name'][0])) {
                $options['attachments'] = [];
                $upload_dir = 'uploads/email_attachments/';
                
                // Create directory if it doesn't exist
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                // Process each uploaded file
                $file_count = count($_FILES['attachments']['name']);
                for ($i = 0; $i < $file_count; $i++) {
                    if ($_FILES['attachments']['error'][$i] === UPLOAD_ERR_OK) {
                        $tmp_name = $_FILES['attachments']['tmp_name'][$i];
                        $name = basename($_FILES['attachments']['name'][$i]);
                        $file_path = $upload_dir . time() . '_' . $name;
                        
                        if (move_uploaded_file($tmp_name, $file_path)) {
                            $options['attachments'][] = [
                                'path' => $file_path,
                                'name' => $name
                            ];
                        }
                    }
                }
            }
            
            // Send the email
            if (!empty($recipients)) {
                // Manual recipient list
                $result = sendBulkEmail($recipients, $subject, $message, '', $options);
            } else {
                // Filtered client list
                $result = sendEmailToFilteredClients($filters, $subject, $message, $options);
            }
            
            if ($result['success']) {
                $successMessage = "Bulk email sent successfully: {$result['sent_count']} emails sent";
                if ($result['failed_count'] > 0) {
                    $successMessage .= ", {$result['failed_count']} failed";
                }
                
                // Clear form data on success
                $_POST = [];
            } else {
                $errorMessage = "Failed to send bulk email: {$result['message']}";
            }
        }
    }
}

/**
 * Fix common issues in email content
 *
 * @param string $content The email content to fix
 * @return string The fixed email content
 */
function fixEmailContent($content) {
    // Fix duplicate salutations like "Dear Sneha, Dear Sneha"
    $content = preg_replace('/<p>Dear\s+([^<,]+),<\/p>\s*<p>Dear\s+([^<,]+),<\/p>/i', '<p>Dear $1,</p>', $content);
    
    // Fix consecutive line breaks
    $content = preg_replace('/<p>\s*<\/p>\s*<p>\s*<\/p>/', '<p>&nbsp;</p>', $content);
    
    // Fix missing closing tags
    if (substr_count($content, '<p>') > substr_count($content, '</p>')) {
        $content .= str_repeat('</p>', substr_count($content, '<p>') - substr_count($content, '</p>'));
    }
    
    return $content;
}

require_once 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-3 col-lg-2">
            <?php require_once 'includes/sidebar.php'; ?>
        </div>
        
        <div class="col-md-9 col-lg-10">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Send Bulk Email</h1>
            </div>
            
            <?php if (!empty($successMessage)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $successMessage; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($errorMessage)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $errorMessage; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-envelope-open-text me-2"></i>Compose Bulk Email
                    </h6>
                </div>
                <div class="card-body">
                    <form action="" method="post" enctype="multipart/form-data">
                        <!-- Email Selection Method -->
                        <div class="mb-4">
                            <h5 class="text-primary">
                                <i class="fas fa-users me-2"></i>Select Recipients
                            </h5>
                            <div class="border rounded p-3 bg-light">
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="radio" name="selection_method" id="selection_all" value="all" checked>
                                    <label class="form-check-label" for="selection_all">
                                        All Clients
                                    </label>
                                </div>
                                
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="radio" name="selection_method" id="selection_filtered" value="filtered">
                                    <label class="form-check-label" for="selection_filtered">
                                        Filtered Clients
                                    </label>
                                </div>
                                
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="selection_method" id="selection_manual" value="manual">
                                    <label class="form-check-label" for="selection_manual">
                                        Manual Email List
                                    </label>
                                </div>
                                
                                <!-- Filtered Options (hidden by default) -->
                                <div id="filtered_options" class="mt-3 ps-4 border-start" style="display: none;">
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label for="care_type" class="form-label">Care Type</label>
                                            <select class="form-select" id="care_type" name="care_type">
                                                <option value="">Any Care Type</option>
                                                <?php foreach ($care_types as $type): ?>
                                                    <option value="<?php echo htmlspecialchars($type['care_type']); ?>">
                                                        <?php echo htmlspecialchars($type['care_type']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-4">
                                            <label for="status" class="form-label">Client Status</label>
                                            <select class="form-select" id="status" name="status">
                                                <option value="">Any Status</option>
                                                <option value="active">Active</option>
                                                <option value="inactive">Inactive</option>
                                                <option value="pending">Pending</option>
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-4">
                                            <label for="location_id" class="form-label">Location</label>
                                            <select class="form-select" id="location_id" name="location_id">
                                                <option value="">Any Location</option>
                                                <?php foreach ($locations as $location): ?>
                                                    <option value="<?php echo $location['id']; ?>">
                                                        <?php echo htmlspecialchars($location['name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-4">
                                            <label for="min_age" class="form-label">Minimum Age</label>
                                            <input type="number" class="form-control" id="min_age" name="min_age" min="0" max="120">
                                        </div>
                                        
                                        <div class="col-md-4">
                                            <label for="max_age" class="form-label">Maximum Age</label>
                                            <input type="number" class="form-control" id="max_age" name="max_age" min="0" max="120">
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Manual Email Input (hidden by default) -->
                                <div id="manual_options" class="mt-3 ps-4 border-start" style="display: none;">
                                    <div class="mb-3">
                                        <label for="manual_emails" class="form-label">Email Addresses</label>
                                        <textarea class="form-control" id="manual_emails" name="manual_emails" rows="3" 
                                                placeholder="Enter email addresses separated by commas, semicolons, or line breaks"></textarea>
                                        <div class="form-text">Example: john@example.com, jane@example.com</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Email Content -->
                        <div class="mb-4">
                            <h5 class="text-primary">
                                <i class="fas fa-pen-fancy me-2"></i>Email Content
                            </h5>
                            <div class="border rounded p-3 bg-light">
                                <!-- Email Templates -->
                                <div class="mb-3">
                                    <label for="use_template" class="form-label">Use Template (Optional)</label>
                                    <select class="form-select" id="use_template" name="use_template">
                                        <option value="">Select a template...</option>
                                        <?php foreach ($email_templates as $template): ?>
                                            <option value="<?php echo $template['id']; ?>">
                                                <?php echo htmlspecialchars($template['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <!-- Subject -->
                                <div class="mb-3">
                                    <label for="subject" class="form-label">Subject</label>
                                    <input type="text" class="form-control" id="subject" name="subject" 
                                        value="<?php echo isset($_POST['subject']) ? htmlspecialchars($_POST['subject']) : ''; ?>" required>
                                </div>
                                
                                <!-- Message -->
                                <div class="mb-3">
                                    <label for="message" class="form-label">Message</label>
                                    <textarea class="form-control" id="message" name="message" rows="10" required><?php echo isset($_POST['message']) ? htmlspecialchars($_POST['message']) : ''; ?></textarea>
                                </div>
                                
                                <!-- Preview Panel -->
                                <div class="mb-3 border p-3 d-none" id="preview_panel">
                                    <h6 class="border-bottom pb-2">Email Preview</h6>
                                    <div id="email_preview"></div>
                                </div>
                                
                                <!-- Email Options -->
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="use_bcc" name="use_bcc" value="1" checked>
                                    <label class="form-check-label" for="use_bcc">
                                        Use BCC for multiple recipients (recommended for privacy)
                                    </label>
                                </div>
                                
                                <!-- Attachments -->
                                <div class="mb-3">
                                    <label for="attachments" class="form-label">Attachments (Optional)</label>
                                    <input class="form-control" type="file" id="attachments" name="attachments[]" multiple>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Submit Button -->
                        <div class="text-end">
                            <button type="button" class="btn btn-secondary me-2" id="preview_button">
                                <i class="fas fa-eye me-1"></i> Preview Email
                            </button>
                            <button type="submit" name="send_email" class="btn btn-primary">
                                <i class="fas fa-paper-plane me-1"></i> Send Bulk Email
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle selection method change
    const selectionMethod = document.querySelectorAll('input[name="selection_method"]');
    const filteredOptions = document.getElementById('filtered_options');
    const manualOptions = document.getElementById('manual_options');
    
    function updateSelectionOptions() {
        const selected = document.querySelector('input[name="selection_method"]:checked').value;
        
        filteredOptions.style.display = (selected === 'filtered') ? 'block' : 'none';
        manualOptions.style.display = (selected === 'manual') ? 'block' : 'none';
    }
    
    selectionMethod.forEach(function(radio) {
        radio.addEventListener('change', updateSelectionOptions);
    });
    
    // Initialize display
    updateSelectionOptions();
    
    // Handle template selection
    const templateSelect = document.getElementById('use_template');
    const subjectInput = document.getElementById('subject');
    const messageInput = document.getElementById('message');
    
    templateSelect.addEventListener('change', function() {
        const selectedTemplate = this.value;
        if (!selectedTemplate) return;
        
        // Find the selected template from our templates array
        <?php echo 'const templates = ' . json_encode($email_templates) . ';'; ?>
        
        const template = templates.find(t => t.id === selectedTemplate);
        if (template) {
            subjectInput.value = template.subject;
            messageInput.value = template.body;
        }
    });
    
    // Handle preview button
    const previewButton = document.getElementById('preview_button');
    const previewPanel = document.getElementById('preview_panel');
    const emailPreview = document.getElementById('email_preview');
    
    previewButton.addEventListener('click', function() {
        const messageContent = messageInput.value;
        
        if (!messageContent.trim()) {
            alert('Please enter email content to preview');
            return;
        }
        
        // Fix common email formatting issues for preview
        let fixedContent = messageContent
            .replace(/(<p>Dear\s+([^<,]+),<\/p>\s*<p>Dear\s+([^<,]+),<\/p>)/gi, '<p>Dear $2,</p>');
            
        // Show preview
        emailPreview.innerHTML = `
            <div class="email-preview">
                <div class="email-header border-bottom pb-2 mb-3">
                    <strong>Subject:</strong> ${subjectInput.value || '[No Subject]'}
                </div>
                <div class="email-body">
                    ${fixedContent}
                </div>
            </div>
        `;
        
        previewPanel.classList.remove('d-none');
    });
});
</script> 