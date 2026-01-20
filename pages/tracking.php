<?php
require_once __DIR__ . '/../includes/auth.php';

// Check authentication (optional for public tracking)
$auth = new Auth();
$user = $auth->checkAuth() ? $auth->getCurrentUser() : null;
$conn = getDBConnection();

$permit = null;
$error = '';
$permit_number = '';

// Handle search
if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['id'])) {
    if (isset($_GET['id'])) {
        $permit_id = intval($_GET['id']);
        
        // Check if user has permission to view this permit
        if ($user) {
            if ($user['role'] === 'admin' || $user['role'] === 'staff') {
                $stmt = $conn->prepare("
                    SELECT p.*, b.business_name, b.business_address, b.business_type,
                           u.first_name, u.last_name, u.contact_number, u.email,
                           ins.first_name as inspector_name, ins.last_name as inspector_last,
                           app.first_name as approver_name, app.last_name as approver_last
                    FROM permits p
                    JOIN businesses b ON p.business_id = b.business_id
                    JOIN users u ON b.owner_id = u.user_id
                    LEFT JOIN users ins ON p.inspected_by = ins.user_id
                    LEFT JOIN users app ON p.approved_by = app.user_id
                    WHERE p.permit_id = ?
                ");
                $stmt->bind_param("i", $permit_id);
            } else {
                // Applicant can only view their own permits
                $stmt = $conn->prepare("
                    SELECT p.*, b.business_name, b.business_address, b.business_type,
                           u.first_name, u.last_name, u.contact_number, u.email,
                           ins.first_name as inspector_name, ins.last_name as inspector_last,
                           app.first_name as approver_name, app.last_name as approver_last
                    FROM permits p
                    JOIN businesses b ON p.business_id = b.business_id
                    JOIN users u ON b.owner_id = u.user_id
                    LEFT JOIN users ins ON p.inspected_by = ins.user_id
                    LEFT JOIN users app ON p.approved_by = app.user_id
                    WHERE p.permit_id = ? AND b.owner_id = ?
                ");
                $stmt->bind_param("ii", $permit_id, $user['user_id']);
            }
        } else {
            $error = 'Please login to track your application.';
        }
    } else {
        // Public tracking by permit number
        $permit_number = sanitize($_POST['permit_number'] ?? '');
        
        if (empty($permit_number)) {
            $error = 'Please enter a permit number.';
        } else {
            $stmt = $conn->prepare("
                SELECT p.*, b.business_name, b.business_address, b.business_type,
                       u.first_name, u.last_name, u.contact_number, u.email,
                       ins.first_name as inspector_name, ins.last_name as inspector_last,
                       app.first_name as approver_name, app.last_name as approver_last
                FROM permits p
                JOIN businesses b ON p.business_id = b.business_id
                JOIN users u ON b.owner_id = u.user_id
                LEFT JOIN users ins ON p.inspected_by = ins.user_id
                LEFT JOIN users app ON p.approved_by = app.user_id
                WHERE p.permit_number = ?
            ");
            $stmt->bind_param("s", $permit_number);
        }
    }
    
    if (!$error && isset($stmt)) {
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $permit = $result->fetch_assoc();
            
            // Get requirements status
            $req_stmt = $conn->prepare("
                SELECT * FROM permit_requirements 
                WHERE permit_id = ? 
                ORDER BY requirement_type
            ");
            $req_stmt->bind_param("i", $permit['permit_id']);
            $req_stmt->execute();
            $requirements = $req_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $permit['requirements'] = $requirements;
            $req_stmt->close();
            
            // Get payment status
            $pay_stmt = $conn->prepare("
                SELECT * FROM payments 
                WHERE permit_id = ? 
                ORDER BY created_at DESC
            ");
            $pay_stmt->bind_param("i", $permit['permit_id']);
            $pay_stmt->execute();
            $payments = $pay_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $permit['payments'] = $payments;
            $pay_stmt->close();
            
            // Get activity log
            if ($user && ($user['role'] === 'admin' || $user['role'] === 'staff')) {
                $log_stmt = $conn->prepare("
                    SELECT al.*, u.first_name, u.last_name 
                    FROM audit_logs al
                    LEFT JOIN users u ON al.user_id = u.user_id
                    WHERE al.details LIKE ?
                    ORDER BY al.created_at DESC
                    LIMIT 10
                ");
                $log_param = "%{$permit['permit_number']}%";
                $log_stmt->bind_param("s", $log_param);
                $log_stmt->execute();
                $activity_log = $log_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $permit['activity_log'] = $activity_log;
                $log_stmt->close();
            }
        } else {
            $error = 'Permit not found. Please check the permit number and try again.';
        }
        $stmt->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Application - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php if ($user): ?>
        <?php 
        require_once __DIR__ . '/../includes/layout.php';
        renderPageLayout('Track Application', $user, 'tracking');
        ?>
    <?php else: ?>
        <div class="login-wrapper">
            <div class="login-card">
                <div class="login-header">
                    <div class="sidebar-logo-icon" style="margin: 0 auto 1rem;">📋</div>
                    <h1 style="margin: 0; font-size: 1.5rem; font-weight: 700;">Track Application</h1>
                </div>
                <div class="login-body">
    <?php endif; ?>
        <div class="row justify-content-center">
            <div class="col-12 col-lg-10">
                <!-- Search Form -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h4 class="mb-0">Track Your Application</h4>
                        <p class="text-gray mb-0 mt-2">Enter your permit number to check the status of your application.</p>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        
                        <form method="POST" id="trackingForm">
                            <div class="row align-items-end">
                                <div class="col-12 col-md-8">
                                    <div class="form-group">
                                        <label for="permit_number" class="form-label">Permit Number</label>
                                        <input type="text" class="form-control" id="permit_number" name="permit_number" 
                                               value="<?php echo htmlspecialchars($permit_number); ?>" 
                                               placeholder="BP2025-XXXXXXXX" required>
                                        <div class="form-text">Format: BP2025-XXXXXXXX (found on your application receipt)</div>
                                        <div class="invalid-feedback"></div>
                                    </div>
                                </div>
                                <div class="col-12 col-md-4">
                                    <button type="submit" class="btn btn-primary w-100" id="trackBtn">
                                        Track Application
                                    </button>
                                </div>
                            </div>
                        </form>
                        
                        <?php if ($user && $user['role'] === 'applicant'): ?>
                            <div class="mt-3">
                                <a href="index.php?page=dashboard" class="btn btn-outline-primary btn-sm">
                                    <i>←</i> Back to Dashboard
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Results -->
                <?php if ($permit): ?>
                    <div class="card">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Application Status</h5>
                                <?php
                                $statusClass = '';
                                $statusIcon = '';
                                switch($permit['permit_status']) {
                                    case 'pending':
                                        $statusClass = 'badge-warning';
                                        $statusIcon = '⏳';
                                        break;
                                    case 'under_review':
                                        $statusClass = 'badge-info';
                                        $statusIcon = '🔍';
                                        break;
                                    case 'approved':
                                        $statusClass = 'badge-success';
                                        $statusIcon = '✅';
                                        break;
                                    case 'rejected':
                                        $statusClass = 'badge-danger';
                                        $statusIcon = '❌';
                                        break;
                                    case 'released':
                                        $statusClass = 'badge-primary';
                                        $statusIcon = '📋';
                                        break;
                                    default:
                                        $statusClass = 'badge-secondary';
                                        $statusIcon = '📄';
                                }
                                ?>
                                <span class="badge <?php echo $statusClass; ?> fs-6">
                                    <?php echo $statusIcon . ' ' . ucfirst(str_replace('_', ' ', $permit['permit_status'])); ?>
                                </span>
                            </div>
                        </div>
                        <div class="card-body">
                            <!-- Progress Timeline -->
                            <div class="mb-4">
                                <h6 class="mb-3">Application Progress</h6>
                                <div class="progress" style="height: 30px;">
                                    <?php
                                    $progress = 0;
                                    switch($permit['permit_status']) {
                                        case 'pending':
                                            $progress = 20;
                                            break;
                                        case 'under_review':
                                            $progress = 50;
                                            break;
                                        case 'approved':
                                            $progress = 80;
                                            break;
                                        case 'released':
                                            $progress = 100;
                                            break;
                                    }
                                    ?>
                                    <div class="progress-bar" style="width: <?php echo $progress; ?>%">
                                        <?php echo $progress; ?>% Complete
                                    </div>
                                </div>
                            </div>

                            <!-- Business Information -->
                            <div class="row mb-4">
                                <div class="col-12 col-md-6">
                                    <h6 class="mb-3">Business Information</h6>
                                    <table class="table table-sm">
                                        <tr>
                                            <td><strong>Permit Number:</strong></td>
                                            <td><?php echo htmlspecialchars($permit['permit_number']); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Business Name:</strong></td>
                                            <td><?php echo htmlspecialchars($permit['business_name']); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Business Type:</strong></td>
                                            <td><?php echo htmlspecialchars($permit['business_type']); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Address:</strong></td>
                                            <td><?php echo htmlspecialchars($permit['business_address']); ?></td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-12 col-md-6">
                                    <h6 class="mb-3">Application Details</h6>
                                    <table class="table table-sm">
                                        <tr>
                                            <td><strong>Application Date:</strong></td>
                                            <td><?php echo formatDate($permit['application_date']); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Permit Type:</strong></td>
                                            <td><?php echo ucfirst($permit['permit_type']); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Processing Fee:</strong></td>
                                            <td>₱<?php echo number_format($permit['processing_fee'], 2); ?></td>
                                        </tr>
                                        <?php if ($permit['approval_date']): ?>
                                        <tr>
                                            <td><strong>Approval Date:</strong></td>
                                            <td><?php echo formatDate($permit['approval_date']); ?></td>
                                        </tr>
                                        <?php endif; ?>
                                        <?php if ($permit['expiry_date']): ?>
                                        <tr>
                                            <td><strong>Expiry Date:</strong></td>
                                            <td><?php echo formatDate($permit['expiry_date']); ?></td>
                                        </tr>
                                        <?php endif; ?>
                                    </table>
                                </div>
                            </div>

                            <!-- Requirements Status -->
                            <div class="mb-4">
                                <h6 class="mb-3">Requirements Status</h6>
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Document</th>
                                                <th>Status</th>
                                                <th>File</th>
                                                <th>Submitted Date</th>
                                                <th>Admin Notes</th>
                                                <th>Verified By</th>
                                                <?php if ($user && $user['role'] === 'applicant'): ?>
                                                    <th>Actions</th>
                                                <?php endif; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($permit['requirements'] as $req): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($req['requirement_type']); ?></td>
                                                    <td>
                                                        <?php if ($req['is_submitted']): ?>
                                                            <?php if ($req['verified']): ?>
                                                                <span class="badge badge-success">Verified</span>
                                                            <?php elseif ($req['verified_by'] !== null && $req['verified'] === false): ?>
                                                                <span class="badge badge-danger">Declined</span>
                                                            <?php else: ?>
                                                                <span class="badge badge-warning">Submitted</span>
                                                            <?php endif; ?>
                                                        <?php else: ?>
                                                            <span class="badge badge-secondary">Pending</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($req['file_path']): ?>
                                                            <button type="button" 
                                                                    class="btn btn-sm btn-outline-primary" 
                                                                    onclick="viewDocumentInNewTab(<?php echo $req['requirement_id']; ?>)"
                                                                    title="<?php echo htmlspecialchars($req['file_name'] ?? 'View file'); ?>">
                                                                👁️ View
                                                            </button>
                                                        <?php else: ?>
                                                            <span class="text-secondary">No file uploaded</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php echo $req['submitted_at'] ? date('M d, Y', strtotime($req['submitted_at'])) : '-'; ?>
                                                    </td>
                                                    <td>
                                                        <?php 
                                                        if ($req['verified_by'] !== null) {
                                                            if (!empty($req['notes'])) {
                                                                $status_class = $req['verified'] ? 'success' : 'danger';
                                                                $status_icon = $req['verified'] ? '✅' : '❌';
                                                                $status_text = $req['verified'] ? 'Approved' : 'Declined';
                                                                echo '<div class="border border-' . $status_class . ' rounded p-2 bg-light" style="border-left: 4px solid var(--' . $status_class . ') !important;">';
                                                                echo '<strong class="text-' . $status_class . '">' . $status_icon . ' ' . $status_text . ':</strong><br>';
                                                                echo '<span class="text-dark">' . htmlspecialchars($req['notes']) . '</span>';
                                                                echo '</div>';
                                                            } else {
                                                                echo '<span class="text-muted">No notes</span>';
                                                            }
                                                        } else {
                                                            echo '<span class="text-muted">-</span>';
                                                        }
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php 
                                                        if ($req['verified_by']) {
                                                            echo 'Staff Member';
                                                        } else {
                                                            echo '-';
                                                        }
                                                        ?>
                                                    </td>
                                                    <?php if ($user && $user['role'] === 'applicant'): ?>
                                                        <td>
                                                            <?php if (!$req['is_submitted'] || !$req['file_path']): ?>
                                                                <button type="button" 
                                                                        class="btn btn-sm btn-outline-primary" 
                                                                        onclick="showUploadModal(<?php echo $req['requirement_id']; ?>, '<?php echo htmlspecialchars($req['requirement_type'], ENT_QUOTES); ?>', <?php echo $permit['permit_id']; ?>)">
                                                                    Upload
                                                                </button>
                                                            <?php elseif ($req['verified_by'] !== null && $req['verified'] === false): ?>
                                                                <button type="button" 
                                                                        class="btn btn-sm btn-outline-warning" 
                                                                        onclick="showUploadModal(<?php echo $req['requirement_id']; ?>, '<?php echo htmlspecialchars($req['requirement_type'], ENT_QUOTES); ?>', <?php echo $permit['permit_id']; ?>)">
                                                                    🔄 Re-upload
                                                                </button>
                                                            <?php else: ?>
                                                                <button type="button" 
                                                                        class="btn btn-sm btn-outline-secondary" 
                                                                        onclick="showUploadModal(<?php echo $req['requirement_id']; ?>, '<?php echo htmlspecialchars($req['requirement_type'], ENT_QUOTES); ?>', <?php echo $permit['permit_id']; ?>)">
                                                                    Replace
                                                                </button>
                                                            <?php endif; ?>
                                                        </td>
                                                    <?php endif; ?>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Payment Status -->
                            <?php if (!empty($permit['payments'])): ?>
                            <div class="mb-4">
                                <h6 class="mb-3">Payment History</h6>
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Amount</th>
                                                <th>Method</th>
                                                <th>Status</th>
                                                <th>Payment Date</th>
                                                <th>Receipt #</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($permit['payments'] as $payment): ?>
                                                <tr>
                                                    <td>₱<?php echo number_format($payment['payment_amount'], 2); ?></td>
                                                    <td><?php echo ucfirst($payment['payment_method']); ?></td>
                                                    <td>
                                                        <span class="badge badge-<?php echo $payment['payment_status'] === 'paid' ? 'success' : 'warning'; ?>">
                                                            <?php echo ucfirst($payment['payment_status']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo $payment['payment_date'] ? formatDate($payment['payment_date']) : '-'; ?></td>
                                                    <td><?php echo htmlspecialchars($payment['receipt_number'] ?? '-'); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Activity Log (Admin/Staff only) -->
                            <?php if ($user && ($user['role'] === 'admin' || $user['role'] === 'staff') && !empty($permit['activity_log'])): ?>
                            <div class="mb-4">
                                <h6 class="mb-3">Activity Log</h6>
                                <div class="activity-log">
                                    <?php foreach ($permit['activity_log'] as $activity): ?>
                                        <div class="mb-2 pb-2 border-bottom">
                                            <small class="text-gray">
                                                <?php echo date('M d, Y h:i A', strtotime($activity['created_at'])); ?>
                                            </small>
                                            <div class="mt-1">
                                                <strong><?php echo htmlspecialchars($activity['action']); ?></strong>
                                                <?php if ($activity['first_name']): ?>
                                                    by <?php echo htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']); ?>
                                                <?php endif; ?>
                                            </div>
                                            <?php if ($activity['details']): ?>
                                                <small class="text-gray"><?php echo htmlspecialchars($activity['details']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Action Buttons -->
                            <div class="d-flex justify-content-between gap-2">
                                <a href="index.php?page=tracking" class="btn btn-outline-primary">
                                    Track Another Application
                                </a>
                                <?php if ($user): ?>
                                    <a href="index.php?page=dashboard" class="btn btn-primary">
                                        Back to Dashboard
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php if ($user): ?>
        <?php closePageLayout(); ?>
    <?php else: ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Upload Document Modal -->
    <?php if ($user && $user['role'] === 'applicant' && $permit): ?>
    <div class="modal" id="uploadModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Upload Document</h5>
                    <button type="button" class="modal-close" onclick="hideUploadModal()">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="uploadForm" enctype="multipart/form-data">
                        <input type="hidden" id="uploadRequirementId" name="requirement_id">
                        <input type="hidden" id="uploadPermitId" name="permit_id">
                        
                        <div class="form-group">
                            <label class="form-label">Document Type</label>
                            <input type="text" class="form-control" id="uploadDocType" readonly>
                        </div>
                        
                        <div class="form-group">
                            <label for="documentFile" class="form-label">Select File *</label>
                            <input type="file" 
                                   class="form-control" 
                                   id="documentFile" 
                                   name="document" 
                                   accept=".pdf,.jpg,.jpeg,.png,.gif"
                                   required>
                            <div class="form-text">
                                Allowed formats: PDF, JPG, PNG, GIF (Max: 5MB)
                            </div>
                            <div class="invalid-feedback"></div>
                        </div>
                        
                        <div id="uploadProgress" class="d-none">
                            <div class="progress mb-2">
                                <div class="progress-bar" role="progressbar" style="width: 0%"></div>
                            </div>
                            <small class="text-secondary" id="progressText">Uploading...</small>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="hideUploadModal()">Cancel</button>
                    <button type="button" class="btn btn-primary" id="uploadBtn" onclick="uploadDocument()">Upload</button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <script>
        // Form validation
        document.getElementById('trackingForm')?.addEventListener('submit', function(e) {
            const form = e.target;
            const btn = document.getElementById('trackBtn');
            
            // Clear previous errors
            form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
            form.querySelectorAll('.invalid-feedback').forEach(el => el.textContent = '');
            
            // Validate form
            let isValid = true;
            
            const permitNumber = form.permit_number.value.trim();
            
            if (!permitNumber) {
                showFieldError('permit_number', 'Permit number is required');
                isValid = false;
            } else if (!validatePermitNumber(permitNumber)) {
                showFieldError('permit_number', 'Please enter a valid permit number (e.g., BP2025-XXXXXXXX)');
                isValid = false;
            }
            
            if (!isValid) {
                e.preventDefault();
                return;
            }
            
            // Show loading state
            btn.disabled = true;
            btn.textContent = 'Tracking...';
            
            // Submit form
            form.submit();
        });
        
        function showFieldError(fieldId, message) {
            const field = document.getElementById(fieldId);
            const feedback = field.parentElement.querySelector('.invalid-feedback');
            
            field.classList.add('is-invalid');
            feedback.textContent = message;
        }
        
        function validatePermitNumber(permitNumber) {
            const re = /^BP\d{4}-[A-F0-9]{12}$/i;
            return re.test(permitNumber);
        }
        
        // Auto-refresh for tracking results
        <?php if ($permit): ?>
        setInterval(function() {
            location.reload();
        }, 60000); // Refresh every minute
        <?php endif; ?>

        // Upload Document Functions
        function showUploadModal(requirementId, docType, permitId) {
            document.getElementById('uploadRequirementId').value = requirementId;
            document.getElementById('uploadPermitId').value = permitId;
            document.getElementById('uploadDocType').value = docType;
            document.getElementById('documentFile').value = '';
            document.getElementById('uploadProgress').classList.add('d-none');
            const form = document.getElementById('uploadForm');
            if (form) {
                form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
            }
            
            const modal = document.getElementById('uploadModal');
            modal.classList.add('show');
            modal.style.display = 'flex';
            document.body.classList.add('modal-open');
            
            // Add backdrop
            if (!document.getElementById('upload-modal-backdrop')) {
                const backdrop = document.createElement('div');
                backdrop.className = 'modal-backdrop';
                backdrop.id = 'upload-modal-backdrop';
                backdrop.onclick = function() { hideUploadModal(); };
                document.body.appendChild(backdrop);
            }
        }

        function hideUploadModal() {
            const modal = document.getElementById('uploadModal');
            modal.classList.remove('show');
            modal.style.display = 'none';
            document.body.classList.remove('modal-open');
            
            // Remove backdrop
            const backdrop = document.getElementById('upload-modal-backdrop');
            if (backdrop) {
                backdrop.remove();
            }
        }

        function uploadDocument() {
            const form = document.getElementById('uploadForm');
            const fileInput = document.getElementById('documentFile');
            const uploadBtn = document.getElementById('uploadBtn');
            const progressDiv = document.getElementById('uploadProgress');
            const progressBar = progressDiv.querySelector('.progress-bar');
            const progressText = document.getElementById('progressText');
            
            // Validate file
            if (!fileInput.files || !fileInput.files[0]) {
                fileInput.classList.add('is-invalid');
                const feedback = fileInput.parentElement.querySelector('.invalid-feedback');
                if (feedback) feedback.textContent = 'Please select a file';
                return;
            }

            const file = fileInput.files[0];
            const maxSize = 5 * 1024 * 1024; // 5MB
            const allowedTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png', 'image/gif'];

            if (!allowedTypes.includes(file.type)) {
                fileInput.classList.add('is-invalid');
                const feedback = fileInput.parentElement.querySelector('.invalid-feedback');
                if (feedback) feedback.textContent = 'Invalid file type. Only PDF, JPG, PNG, and GIF are allowed.';
                return;
            }

            if (file.size > maxSize) {
                fileInput.classList.add('is-invalid');
                const feedback = fileInput.parentElement.querySelector('.invalid-feedback');
                if (feedback) feedback.textContent = 'File size exceeds 5MB limit';
                return;
            }

            // Clear errors
            fileInput.classList.remove('is-invalid');

            // Show progress
            uploadBtn.disabled = true;
            uploadBtn.textContent = 'Uploading...';
            progressDiv.classList.remove('d-none');
            progressBar.style.width = '0%';

            // Create FormData
            const formData = new FormData(form);

            // Upload file
            const xhr = new XMLHttpRequest();

            xhr.upload.addEventListener('progress', function(e) {
                if (e.lengthComputable) {
                    const percentComplete = (e.loaded / e.total) * 100;
                    progressBar.style.width = percentComplete + '%';
                }
            });

            xhr.addEventListener('load', function() {
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            progressBar.style.width = '100%';
                            progressText.textContent = 'Upload complete! Redirecting...';
                            showSuccess('Document uploaded successfully!', 'Upload Complete');
                            setTimeout(function() {
                                hideUploadModal();
                                location.reload();
                            }, 1500);
                        } else {
                            showError(response.message || 'Upload failed', 'Upload Error');
                            uploadBtn.disabled = false;
                            uploadBtn.textContent = 'Upload';
                            progressDiv.classList.add('d-none');
                            progressText.textContent = '';
                        }
                    } catch (e) {
                        showError('Invalid response from server', 'Upload Error');
                        uploadBtn.disabled = false;
                        uploadBtn.textContent = 'Upload';
                        progressDiv.classList.add('d-none');
                        progressText.textContent = '';
                    }
                } else {
                    showError('Upload failed with status: ' + xhr.status, 'Upload Error');
                    uploadBtn.disabled = false;
                    uploadBtn.textContent = 'Upload';
                    progressDiv.classList.add('d-none');
                    progressText.textContent = '';
                }
            });

            xhr.addEventListener('error', function() {
                showError('Upload failed. Please check your connection and try again.', 'Connection Error');
                uploadBtn.disabled = false;
                uploadBtn.textContent = 'Upload';
                progressDiv.classList.add('d-none');
                progressText.textContent = '';
            });

            xhr.open('POST', 'api/upload_document.php');
            xhr.send(formData);
        }

        // File Viewer Functions
        function viewDocumentInNewTab(requirementId) {
            // Open file in new tab with viewer
            const viewerUrl = `api/view_file_tab.php?id=${requirementId}`;
            window.open(viewerUrl, '_blank');
        }
    </script>
</body>
</html>
