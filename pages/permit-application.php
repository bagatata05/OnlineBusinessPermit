<?php
require_once __DIR__ . '/../includes/auth.php';

// Check authentication
$auth = new Auth();
if (!$auth->checkAuth()) {
    header('Location: index.php?page=login');
    exit();
}

$user = $auth->getCurrentUser();
$conn = getDBConnection();

// Get user's businesses
$businesses = [];
$stmt = $conn->prepare("SELECT business_id, business_name FROM businesses WHERE owner_id = ? AND is_active = 1 ORDER BY business_name");
$stmt->bind_param("i", $user['user_id']);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $businesses[] = $row;
}
$stmt->close();

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $business_id = intval($_POST['business_id'] ?? 0);
    $permit_type = sanitize($_POST['permit_type'] ?? '');
    $permit_category = sanitize($_POST['permit_category'] ?? '');
    $application_date = $_POST['application_date'] ?? date('Y-m-d');
    
    // Validate
    if (!$business_id) {
        $error = 'Please select a business.';
    } elseif (!$permit_type) {
        $error = 'Please select a permit type.';
    } elseif (!$permit_category) {
        $error = 'Please select a permit category.';
    } else {
        // Validate application date (must be today or future)
        if (strtotime($application_date) < strtotime('today')) {
            $error = 'Application date cannot be in the past.';
        } else {
            // Get system settings for fees
            $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'processing_fee'");
            $stmt->execute();
            $fee_result = $stmt->get_result()->fetch_assoc();
            $processing_fee = floatval($fee_result['setting_value'] ?? 500);
            
            // Generate permit number
            $permit_number = generatePermitNumber();
            
            // Insert permit application with category and future date
            $stmt = $conn->prepare("
                INSERT INTO permits (
                    business_id, permit_number, permit_category, permit_type, application_date, 
                    permit_status, processing_fee, total_fee
                ) VALUES (?, ?, ?, ?, ?, 'pending', ?, ?)
            ");
            
            $total_fee = $processing_fee;
            $stmt->bind_param("issssdd", $business_id, $permit_number, $permit_category, $permit_type, $application_date, $processing_fee, $total_fee);
            
            if ($stmt->execute()) {
                $permit_id = $conn->insert_id;
                
                // Add default requirements
                $default_requirements = [
                    'Business Registration Certificate',
                    'Barangay Clearance',
                    'Zoning Clearance',
                    'Sanitary Permit',
                    'Fire Safety Inspection Certificate',
                    'Community Tax Certificate',
                    'Picture of Business Establishment'
                ];
                
                foreach ($default_requirements as $requirement) {
                    $req_stmt = $conn->prepare("
                        INSERT INTO permit_requirements (permit_id, requirement_type) VALUES (?, ?)
                    ");
                    $req_stmt->bind_param("is", $permit_id, $requirement);
                    $req_stmt->execute();
                    $req_stmt->close();
                }
                
                logActivity($user['user_id'], 'permit_application', 'New permit application: ' . $permit_number);
                
                // Send email notification (if email is enabled)
                require_once __DIR__ . '/../includes/email.php';
                $email = new Email();
                
                // Get business info
                $biz_stmt = $conn->prepare("SELECT b.business_name FROM businesses b WHERE b.business_id = ?");
                $biz_stmt->bind_param("i", $business_id);
                $biz_stmt->execute();
                $biz_data = $biz_stmt->get_result()->fetch_assoc();
                $biz_stmt->close();
                
                $email->sendApplicationSubmitted($user['email'], $permit_number, $biz_data['business_name']);
                
                $success = "Permit application submitted successfully! Your permit number is {$permit_number}.";
                
                // Clear form
                $_POST = [];
            } else {
                $error = 'Failed to submit application. Please try again.';
            }
            $stmt->close();
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Permit Application - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php 
    require_once __DIR__ . '/../includes/layout.php';
    renderPageLayout('Apply for Permit', $user, 'permit-application');
    ?>
        <div class="row justify-content-center">
            <div class="col-12 col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">Apply for Business Permit</h4>
                        <p class="text-gray mb-0 mt-2">Submit your application for a new business permit.</p>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <?php echo htmlspecialchars($success); ?>
                                <div class="mt-3">
                                    <h6 class="mb-2">Next Steps:</h6>
                                    <ol class="mb-0">
                                        <li>Prepare the required documents listed below</li>
                                        <li>Submit documents to the business permit office</li>
                                        <li>Pay the processing fee</li>
                                        <li>Wait for inspection and approval</li>
                                    </ol>
                                    <div class="mt-3">
                                        <a href="index.php?page=tracking" class="btn btn-primary btn-sm">
                                            Track Application Status
                                        </a>
                                        <a href="index.php?page=dashboard" class="btn btn-outline-primary btn-sm ms-2">
                                            Back to Dashboard
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (empty($businesses)): ?>
                            <div class="alert alert-info">
                                <h5 class="alert-heading">No Registered Business</h5>
                                <p class="mb-3">You need to register a business first before applying for a permit.</p>
                                <a href="index.php?page=business-registration" class="btn btn-primary">
                                    Register Business Now
                                </a>
                            </div>
                        <?php else: ?>
                            <form method="POST" id="permitForm">
                                <div class="form-group">
                                    <label for="business_id" class="form-label">Select Business *</label>
                                    <select class="form-control" id="business_id" name="business_id" required>
                                        <option value="">Choose a business</option>
                                        <?php foreach ($businesses as $business): ?>
                                            <option value="<?php echo $business['business_id']; ?>" 
                                                    <?php echo (($_POST['business_id'] ?? '') == $business['business_id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($business['business_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text">Select the business for which you're applying for a permit</div>
                                    <div class="invalid-feedback"></div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="permit_category" class="form-label">Permit Category *</label>
                                    <select class="form-control" id="permit_category" name="permit_category" required style="appearance: auto;">
                                        <option value="">-- Select permit category --</option>
                                        <option value="General Trade">General Trade</option>
                                        <option value="Manufacturing">Manufacturing</option>
                                        <option value="Restaurant/Food Service">Restaurant/Food Service</option>
                                        <option value="Healthcare">Healthcare</option>
                                        <option value="Education">Education</option>
                                        <option value="Entertainment">Entertainment</option>
                                        <option value="Professional Services">Professional Services</option>
                                        <option value="Construction">Construction</option>
                                        <option value="Other">Other</option>
                                    </select>
                                    <div class="form-text">Choose the category that best describes your business</div>
                                    <div class="invalid-feedback"></div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="permit_type" class="form-label">Application Type *</label>
                                    <select class="form-control" id="permit_type" name="permit_type" required style="appearance: auto;">
                                        <option value="">-- Select application type --</option>
                                        <option value="new">New Permit Application</option>
                                        <option value="renewal">Permit Renewal</option>
                                        <option value="amendment">Permit Amendment</option>
                                    </select>
                                    <div class="form-text">Choose the type of application</div>
                                    <div class="invalid-feedback"></div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="application_date" class="form-label">Application Date *</label>
                                    <input type="date" class="form-control" id="application_date" name="application_date" 
                                           value="<?php echo $_POST['application_date'] ?? date('Y-m-d'); ?>"
                                           min="<?php echo date('Y-m-d'); ?>" required>
                                    <div class="form-text">Date when you want to apply (today or future date)</div>
                                    <div class="invalid-feedback"></div>
                                </div>
                                
                                <div class="card mt-4">
                                    <div class="card-header">
                                        <h6 class="mb-0">Required Documents</h6>
                                    </div>
                                    <div class="card-body">
                                        <p class="text-gray mb-3">Please prepare the following documents for submission:</p>
                                        <ul class="list-unstyled">
                                            <li class="mb-2">
                                                <i class="text-success">✓</i> Business Registration Certificate (DTI/SEC/CDA)
                                            </li>
                                            <li class="mb-2">
                                                <i class="text-success">✓</i> Barangay Clearance
                                            </li>
                                            <li class="mb-2">
                                                <i class="text-success">✓</i> Zoning Clearance (if applicable)
                                            </li>
                                            <li class="mb-2">
                                                <i class="text-success">✓</i> Sanitary Permit (for food businesses)
                                            </li>
                                            <li class="mb-2">
                                                <i class="text-success">✓</i> Fire Safety Inspection Certificate
                                            </li>
                                            <li class="mb-2">
                                                <i class="text-success">✓</i> Community Tax Certificate
                                            </li>
                                            <li class="mb-2">
                                                <i class="text-success">✓</i> Picture of Business Establishment
                                            </li>
                                        </ul>
                                        <div class="alert alert-info mt-3">
                                            <small>
                                                <strong>Note:</strong> Additional requirements may be needed depending on your business type. 
                                                You will be notified if any additional documents are required.
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="card mt-4">
                                    <div class="card-header">
                                        <h6 class="mb-0">Processing Information</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-12 col-md-6">
                                                <p class="mb-2">
                                                    <strong>Processing Fee:</strong> 
                                                    <span class="text-primary">₱500.00</span>
                                                </p>
                                            </div>
                                            <div class="col-12 col-md-6">
                                                <p class="mb-2">
                                                    <strong>Processing Time:</strong> 
                                                    <span class="text-info">5-7 working days</span>
                                                </p>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-12">
                                                <p class="mb-0">
                                                    <strong>Payment Method:</strong> 
                                                    Cash, Bank Transfer, or Online Payment (upon approval)
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-group mt-4">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" id="acknowledge" name="acknowledge" required>
                                        <label class="form-check-label" for="acknowledge">
                                            I acknowledge that I have read and understood the requirements and procedures 
                                            for business permit application. I understand that submission of false information 
                                            may result in denial of my application.
                                        </label>
                                        <div class="invalid-feedback"></div>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-between gap-2 mt-4">
                                    <a href="index.php?page=dashboard" class="btn btn-outline-primary">
                                        Cancel
                                    </a>
                                    <button type="submit" class="btn btn-primary" id="submitBtn">
                                        Submit Application
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php closePageLayout(); ?>
    
    <script>
        // Form validation
        document.getElementById('permitForm')?.addEventListener('submit', function(e) {
            const form = e.target;
            const btn = document.getElementById('submitBtn');
            
            // Clear previous errors
            form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
            form.querySelectorAll('.invalid-feedback').forEach(el => el.textContent = '');
            
            // Validate form
            let isValid = true;
            
            const businessId = form.business_id.value;
            const permitType = form.permit_type.value;
            const acknowledge = form.acknowledge.checked;
            
            if (!businessId) {
                showFieldError('business_id', 'Please select a business');
                isValid = false;
            }
            
            if (!permitType) {
                showFieldError('permit_type', 'Please select a permit type');
                isValid = false;
            }
            
            if (!acknowledge) {
                showFieldError('acknowledge', 'You must acknowledge the requirements');
                isValid = false;
            }
            
            if (!isValid) {
                e.preventDefault();
                return;
            }
            
            // Show loading state
            btn.disabled = true;
            btn.textContent = 'Submitting...';
            
            // Form will submit normally
        });
        
        function showFieldError(fieldId, message) {
            const field = document.getElementById(fieldId);
            const feedback = field.parentElement.querySelector('.invalid-feedback');
            
            field.classList.add('is-invalid');
            feedback.textContent = message;
        }
        
        // Show/hide additional requirements based on permit type
        document.getElementById('permit_type')?.addEventListener('change', function() {
            const permitType = this.value;
            const requirementsList = document.querySelector('.list-unstyled');
            
            if (permitType === 'renewal') {
                // Update requirements for renewal
                requirementsList.innerHTML = `
                    <li class="mb-2"><i class="text-success">✓</i> Previous Permit</li>
                    <li class="mb-2"><i class="text-success">✓</i> Updated Barangay Clearance</li>
                    <li class="mb-2"><i class="text-success">✓</i> Community Tax Certificate</li>
                    <li class="mb-2"><i class="text-success">✓</i> Picture of Business Establishment</li>
                `;
            } else if (permitType === 'amendment') {
                // Update requirements for amendment
                requirementsList.innerHTML = `
                    <li class="mb-2"><i class="text-success">✓</i> Original Permit</li>
                    <li class="mb-2"><i class="text-success">✓</i> Proof of Amendment</li>
                    <li class="mb-2"><i class="text-success">✓</i> Updated Business Registration</li>
                    <li class="mb-2"><i class="text-success">✓</i> Barangay Clearance</li>
                `;
            } else {
                // Reset to new permit requirements
                location.reload();
            }
        });

        // Form Auto-Save Functionality
        let autoSaveTimer;
        const form = document.getElementById('permitForm');
        
        // Function to save form data to localStorage
        function saveFormData() {
            const formData = new FormData(form);
            const data = {};
            
            // Convert FormData to regular object
            for (let [key, value] of formData.entries()) {
                data[key] = value;
            }
            
            // Save to localStorage with timestamp
            localStorage.setItem('permitApplicationDraft', JSON.stringify({
                data: data,
                timestamp: new Date().getTime()
            }));
            
            console.log('Form auto-saved');
        }
        
        // Function to load saved form data
        function loadFormData() {
            const saved = localStorage.getItem('permitApplicationDraft');
            if (!saved) return;
            
            try {
                const parsed = JSON.parse(saved);
                const data = parsed.data;
                const timestamp = parsed.timestamp;
                
                // Check if data is less than 24 hours old
                const hoursOld = (new Date().getTime() - timestamp) / (1000 * 60 * 60);
                if (hoursOld > 24) {
                    localStorage.removeItem('permitApplicationDraft');
                    return;
                }
                
                // Fill form fields with saved data
                Object.keys(data).forEach(key => {
                    const field = form.querySelector(`[name="${key}"]`);
                    if (field) {
                        if (field.type === 'radio' || field.type === 'checkbox') {
                            // Handle radio/checkbox
                            const radioButton = form.querySelector(`[name="${key}"][value="${data[key]}"]`);
                            if (radioButton) radioButton.checked = true;
                        } else {
                            // Handle text, select, textarea
                            field.value = data[key];
                        }
                    }
                });
                
                // Show notification about restored data
                const timeAgo = Math.floor(hoursOld);
                let timeText = '';
                if (timeAgo < 1) {
                    timeText = 'less than an hour ago';
                } else if (timeAgo === 1) {
                    timeText = '1 hour ago';
                } else {
                    timeText = `${timeAgo} hours ago`;
                }
                
                showInfo(`Previous application data restored (saved ${timeText}).`, 'Draft Restored');
                
            } catch (e) {
                console.error('Error loading saved form data:', e);
                localStorage.removeItem('permitApplicationDraft');
            }
        }
        
        // Function to clear saved data
        function clearSavedData() {
            localStorage.removeItem('permitApplicationDraft');
        }
        
        // Auto-save on form input changes
        form.addEventListener('input', function() {
            clearTimeout(autoSaveTimer);
            autoSaveTimer = setTimeout(saveFormData, 2000); // Save 2 seconds after input
        });
        
        form.addEventListener('change', function() {
            clearTimeout(autoSaveTimer);
            autoSaveTimer = setTimeout(saveFormData, 1000); // Save 1 second after change
        });
        
        // Clear saved data on successful submission
        form.addEventListener('submit', function() {
            clearSavedData();
        });
        
        // Load saved data when page loads
        document.addEventListener('DOMContentLoaded', function() {
            loadFormData();
        });
        
        // Add clear draft button
        const clearDraftBtn = document.createElement('button');
        clearDraftBtn.type = 'button';
        clearDraftBtn.className = 'btn btn-outline-secondary btn-sm ml-2';
        clearDraftBtn.innerHTML = '🗑️ Clear Draft';
        clearDraftBtn.onclick = function() {
            if (confirm('Are you sure you want to clear the saved draft?')) {
                clearSavedData();
                showInfo('Draft cleared successfully.', 'Draft Cleared');
            }
        };
        
        // Add button near the submit button
        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.parentNode.appendChild(clearDraftBtn);
        }
    </script>
</body>
</html>
