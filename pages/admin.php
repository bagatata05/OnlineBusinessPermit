<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permit.php';

// Check authentication and admin role
$auth = new Auth();
$auth->requireRole('admin');

$user = $auth->getCurrentUser();
$conn = getDBConnection();

$permit = new Permit();

// Get statistics
$stats = $permit->getStats();

// Get recent permits
$recent_permits = $permit->getPermits(1, 10, [], null, 'admin');

// Get expiring permits
$expiring_permits = $permit->getExpiringPermits(30);

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize($_POST['action'] ?? '');
    
    switch ($action) {
        case 'send_reminders':
            $result = $permit->sendRenewalReminders();
            $message = "Renewal reminders sent to {$result['sent']} out of {$result['total']} businesses.";
            break;
            
        case 'update_settings':
            foreach ($_POST['settings'] as $key => $value) {
                $stmt = $conn->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");
                $stmt->bind_param("ss", $value, $key);
                $stmt->execute();
                $stmt->close();
            }
            $message = "Settings updated successfully.";
            logActivity($user['user_id'], 'settings_update', 'System settings updated');
            break;
    }
}

// Get pending documents for review
$pending_docs_stmt = $conn->prepare("
    SELECT pr.*, p.permit_number, p.permit_status, 
           b.business_name, u.first_name, u.last_name, u.contact_number
    FROM permit_requirements pr
    JOIN permits p ON pr.permit_id = p.permit_id
    JOIN businesses b ON p.business_id = b.business_id
    JOIN users u ON b.owner_id = u.user_id
    WHERE pr.is_submitted = TRUE AND pr.verified = FALSE
    ORDER BY pr.submitted_at ASC
    LIMIT 20
");
$pending_docs_stmt->execute();
$pending_documents = $pending_docs_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$pending_docs_stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php 
    require_once __DIR__ . '/../includes/layout.php';
    renderPageLayout('Admin Panel', $user, 'admin');
    ?>
        <?php if (isset($message)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

                <!-- Overview Statistics -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-card-icon primary">📊</div>
                        <div class="stat-card-value text-primary"><?php echo number_format($stats['total_applications'] ?? 0); ?></div>
                        <div class="stat-card-label">Total Applications</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-card-icon warning">⏳</div>
                        <div class="stat-card-value text-warning"><?php echo number_format(($stats['pending'] ?? 0) + ($stats['under_review'] ?? 0)); ?></div>
                        <div class="stat-card-label">Pending Review</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-card-icon success">✅</div>
                        <div class="stat-card-value text-success"><?php echo number_format($stats['approved'] ?? 0); ?></div>
                        <div class="stat-card-label">Approved</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-card-icon info">💰</div>
                        <div class="stat-card-value text-info">₱<?php echo number_format($stats['total_revenue'] ?? 0, 2); ?></div>
                        <div class="stat-card-label">Total Revenue</div>
                    </div>
                </div>

        <!-- Charts Section -->
        <div class="row mb-4">
            <div class="col-12 col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Application Trends (Last 12 Months)</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="trendsChart" height="100"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Application Types</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="typesChart" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Notification & Permit Management -->
        <div class="row mb-4">
            <div class="col-12 col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="mb-3">
                            <input type="hidden" name="action" value="send_reminders">
                            <button type="submit" class="btn btn-warning w-100" onclick="return confirm('Send renewal reminders to all businesses with expiring permits?')">
                                Send Renewal Reminders
                            </button>
                        </form>
                        
                        <button type="button" class="btn btn-secondary w-100" onclick="exportData()">
                            Export Data to CSV
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Applications -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Recent Applications</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table" id="permitsTable">
                                <thead>
                                    <tr>
                                        <th>Permit #</th>
                                        <th>Business Name</th>
                                        <th>Applicant</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_permits['permits'] as $permit): ?>
                                        <tr>
                                            <td>
                                                <a href="index.php?page=tracking&id=<?php echo $permit['permit_id']; ?>" 
                                                   class="text-primary">
                                                    <?php echo htmlspecialchars($permit['permit_number']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo htmlspecialchars($permit['business_name']); ?></td>
                                            <td><?php echo htmlspecialchars($permit['first_name'] . ' ' . $permit['last_name']); ?></td>
                                            <td>
                                                <span class="badge badge-info">
                                                    <?php echo ucfirst($permit['permit_type']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php
                                                $statusClass = '';
                                                switch($permit['permit_status']) {
                                                    case 'pending':
                                                        $statusClass = 'badge-warning';
                                                        break;
                                                    case 'under_review':
                                                        $statusClass = 'badge-info';
                                                        break;
                                                    case 'approved':
                                                        $statusClass = 'badge-success';
                                                        break;
                                                    case 'rejected':
                                                        $statusClass = 'badge-danger';
                                                        break;
                                                    case 'released':
                                                        $statusClass = 'badge-primary';
                                                        break;
                                                }
                                                ?>
                                                <span class="badge <?php echo $statusClass; ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $permit['permit_status'])); ?>
                                                </span>
                                            </td>
                                            <td><?php echo formatDate($permit['application_date']); ?></td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button type="button" class="btn btn-outline-primary" 
                                                            onclick="viewPermit(<?php echo $permit['permit_id']; ?>)">
                                                        View
                                                    </button>
                                                    <?php if ($permit['permit_status'] === 'pending'): ?>
                                                        <button type="button" class="btn btn-outline-info" 
                                                                onclick="updateStatus(<?php echo $permit['permit_id']; ?>, 'under_review')">
                                                        Review
                                                        </button>
                                                    <?php endif; ?>
                                                    <?php if ($permit['permit_status'] === 'under_review'): ?>
                                                        <button type="button" class="btn btn-outline-success" 
                                                                onclick="updateStatus(<?php echo $permit['permit_id']; ?>, 'approved')">
                                                        Approve
                                                        </button>
                                                        <button type="button" class="btn btn-outline-danger" 
                                                                onclick="updateStatus(<?php echo $permit['permit_id']; ?>, 'rejected')">
                                                        Reject
                                                        </button>
                                                    <?php endif; ?>
                                                    <?php if ($permit['permit_status'] === 'approved'): ?>
                                                        <button type="button" class="btn btn-outline-primary" 
                                                                onclick="updateStatus(<?php echo $permit['permit_id']; ?>, 'released')">
                                                        Release
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php if ($recent_permits['pages'] > 1): ?>
                            <div class="d-flex justify-content-center mt-3">
                                <nav>
                                    <ul class="pagination">
                                        <?php for ($i = 1; $i <= $recent_permits['pages']; $i++): ?>
                                            <li class="page-item <?php echo $i === $recent_permits['current_page'] ? 'active' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                            </li>
                                        <?php endfor; ?>
                                    </ul>
                                </nav>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- File Management Section -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Pending Document Review</h5>
                        <p class="text-gray mb-0 mt-2">Review and approve or decline submitted documents from applicants</p>
                    </div>
                    <div class="card-body">
                        <?php if (empty($pending_documents)): ?>
                            <div class="text-center py-4">
                                <div style="font-size: 2rem; margin-bottom: 1rem;">📋</div>
                                <h6 class="text-gray">No Pending Documents</h6>
                                <p class="text-gray">All submitted documents have been reviewed.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table" id="pendingDocumentsTable">
                                    <thead>
                                        <tr>
                                            <th>Permit #</th>
                                            <th>Business Name</th>
                                            <th>Applicant</th>
                                            <th>Document Type</th>
                                            <th>File Name</th>
                                            <th>Submitted</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pending_documents as $doc): ?>
                                            <tr>
                                                <td>
                                                    <a href="index.php?page=tracking&id=<?php echo $doc['permit_id']; ?>" 
                                                       class="text-primary">
                                                        <?php echo htmlspecialchars($doc['permit_number']); ?>
                                                    </a>
                                                </td>
                                                <td><?php echo htmlspecialchars($doc['business_name']); ?></td>
                                                <td><?php echo htmlspecialchars($doc['first_name'] . ' ' . $doc['last_name']); ?></td>
                                                <td>
                                                    <span class="badge badge-info">
                                                        <?php echo htmlspecialchars($doc['requirement_type']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <button type="button" 
                                                            class="btn btn-sm btn-outline-primary" 
                                                            onclick="viewDocumentInNewTab(<?php echo $doc['requirement_id']; ?>)"
                                                            title="<?php echo htmlspecialchars($doc['file_name'] ?? 'View file'); ?>">
                                                        👁️ View
                                                    </button>
                                                </td>
                                                <td>
                                                    <?php echo $doc['submitted_at'] ? date('M d, Y', strtotime($doc['submitted_at'])) : '-'; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <button type="button" 
                                                                class="btn btn-outline-success" 
                                                                onclick="showFileApprovalModal(<?php echo $doc['requirement_id']; ?>, 'approve', '<?php echo htmlspecialchars($doc['requirement_type'], ENT_QUOTES); ?>')">
                                                            ✅ Approve
                                                        </button>
                                                        <button type="button" 
                                                                class="btn btn-outline-danger" 
                                                                onclick="showFileApprovalModal(<?php echo $doc['requirement_id']; ?>, 'decline', '<?php echo htmlspecialchars($doc['requirement_type'], ENT_QUOTES); ?>')">
                                                            ❌ Decline
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <?php if (count($pending_documents) >= 20): ?>
                                <div class="text-center mt-3">
                                    <button type="button" class="btn btn-outline-primary" onclick="loadMoreDocuments()">
                                        Load More Documents
                                    </button>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Expiring Permits -->
        <?php if (!empty($expiring_permits)): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-warning">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0">Permits Exiring Soon (Next 30 Days)</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Permit #</th>
                                        <th>Business Name</th>
                                        <th>Owner</th>
                                        <th>Contact</th>
                                        <th>Expiry Date</th>
                                        <th>Days Left</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($expiring_permits as $permit): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($permit['permit_number']); ?></td>
                                            <td><?php echo htmlspecialchars($permit['business_name']); ?></td>
                                            <td><?php echo htmlspecialchars($permit['first_name'] . ' ' . $permit['last_name']); ?></td>
                                            <td><?php echo htmlspecialchars($permit['contact_number']); ?></td>
                                            <td><?php echo formatDate($permit['expiry_date']); ?></td>
                                            <td>
                                                <?php 
                                                $days_left = (new DateTime($permit['expiry_date']))->diff(new DateTime())->days;
                                                echo $days_left . ' days';
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    <?php closePageLayout(); ?>

    <!-- Status Update Modal -->
    <div class="modal" id="statusModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Permit Status</h5>
                    <button type="button" class="modal-close" onclick="hideModal('statusModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="statusForm">
                        <input type="hidden" id="modalPermitId" name="permit_id">
                        <div class="form-group">
                            <label for="modalStatus" class="form-label">New Status</label>
                            <select class="form-control" id="modalStatus" name="status">
                                <option value="pending">Pending</option>
                                <option value="under_review">Under Review</option>
                                <option value="approved">Approved</option>
                                <option value="rejected">Rejected</option>
                                <option value="released">Released</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="modalNotes" class="form-label">Notes</label>
                            <textarea class="form-control" id="modalNotes" name="notes" rows="3"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="hideModal('statusModal')">Cancel</button>
                    <button type="button" class="btn btn-primary" id="updateStatusBtn" onclick="submitStatusUpdate()">Update</button>
                </div>
            </div>
        </div>
    </div>

    <!-- File Approval Modal -->
    <div class="modal" id="fileApprovalModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="fileApprovalTitle">Document Review</h5>
                    <button type="button" class="modal-close" onclick="hideModal('fileApprovalModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="fileApprovalForm">
                        <input type="hidden" id="fileRequirementId" name="requirement_id">
                        <input type="hidden" id="fileApprovalAction" name="action">
                        
                        <div class="form-group">
                            <label class="form-label">Document Type</label>
                            <input type="text" class="form-control" id="fileDocType" readonly>
                        </div>
                        
                        <div class="form-group">
                            <label for="fileApprovalNotes" class="form-label">Notes (Optional)</label>
                            <textarea class="form-control" id="fileApprovalNotes" name="notes" rows="3" 
                                      placeholder="Add any comments or reasons for this decision..."></textarea>
                            <div class="form-text">
                                These notes will be visible to the applicant and included in the audit log.
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="hideModal('fileApprovalModal')">Cancel</button>
                    <button type="button" class="btn btn-success" id="approveFileBtn" onclick="submitFileApproval()">
                        ✅ Approve Document
                    </button>
                    <button type="button" class="btn btn-danger" id="declineFileBtn" onclick="submitFileApproval()">
                        ❌ Decline Document
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Initialize charts
        document.addEventListener('DOMContentLoaded', function() {
            // Trends Chart
            const trendsCtx = document.getElementById('trendsChart').getContext('2d');
            new Chart(trendsCtx, {
                type: 'line',
                data: {
                    labels: <?php 
                        $labels = array_column($stats['monthly_trends'] ?? [], 'month');
                        echo json_encode(array_map(function($month) {
                            return date('M Y', strtotime($month . '-01'));
                        }, $labels));
                    ?>,
                    datasets: [{
                        label: 'Applications',
                        data: <?php echo json_encode(array_column($stats['monthly_trends'] ?? [], 'applications')); ?>,
                        borderColor: '#2563eb',
                        backgroundColor: 'rgba(37, 99, 235, 0.1)',
                        tension: 0.4
                    }, {
                        label: 'Approved',
                        data: <?php echo json_encode(array_column($stats['monthly_trends'] ?? [], 'approved')); ?>,
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });

            // Types Chart
            const typesCtx = document.getElementById('typesChart').getContext('2d');
            new Chart(typesCtx, {
                type: 'doughnut',
                data: {
                    labels: ['New', 'Renewal', 'Amendment'],
                    datasets: [{
                        data: [
                            <?php echo $stats['new_applications']; ?>,
                            <?php echo $stats['renewals']; ?>,
                            <?php echo $stats['amendments']; ?>
                        ],
                        backgroundColor: ['#2563eb', '#10b981', '#f59e0b']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                        }
                    }
                }
            });
        });

        // Status update functions
        function updateStatus(permitId, status) {
            document.getElementById('modalPermitId').value = permitId;
            document.getElementById('modalStatus').value = status;
            showModal('statusModal');
        }

        function submitStatusUpdate() {
            const permitId = document.getElementById('modalPermitId').value;
            const status = document.getElementById('modalStatus').value;
            const notes = document.getElementById('modalNotes').value;

            BusinessPermitSystem.updatePermitStatus(permitId, status, notes);
        }

        function viewPermit(permitId) {
            window.open('index.php?page=tracking&id=' + permitId, '_blank');
        }

        function exportData() {
            if (confirm('Export all permit data to CSV? This may take a moment.')) {
                window.open('api/export_csv.php', '_blank');
            }
        }

        // File Approval Functions
        function viewDocumentInNewTab(requirementId) {
            // Open file in new tab with viewer
            const viewerUrl = `api/view_file_tab.php?id=${requirementId}`;
            window.open(viewerUrl, '_blank');
        }

        function showFileApprovalModal(requirementId, action, documentType) {
            document.getElementById('fileRequirementId').value = requirementId;
            document.getElementById('fileApprovalAction').value = action;
            document.getElementById('fileDocType').value = documentType;
            document.getElementById('fileApprovalNotes').value = '';
            
            // Update modal title and buttons based on action
            const title = document.getElementById('fileApprovalTitle');
            const approveBtn = document.getElementById('approveFileBtn');
            const declineBtn = document.getElementById('declineFileBtn');
            
            if (action === 'approve') {
                title.textContent = 'Approve Document';
                approveBtn.style.display = 'inline-block';
                declineBtn.style.display = 'none';
            } else {
                title.textContent = 'Decline Document';
                approveBtn.style.display = 'none';
                declineBtn.style.display = 'inline-block';
            }
            
            showModal('fileApprovalModal');
        }

        function submitFileApproval() {
            const formData = new FormData(document.getElementById('fileApprovalForm'));
            const action = formData.get('action');
            const requirementId = formData.get('requirement_id');
            
            console.log('Submitting file approval:', {
                action: action,
                requirementId: requirementId,
                notes: formData.get('notes')
            });
            
            if (!requirementId) {
                console.error('No requirement ID found in form');
                alert('Error: No document selected. Please try again.');
                return;
            }
            
            const confirmMessage = action === 'approve' ? 
                'Are you sure you want to approve this document?' : 
                'Are you sure you want to decline this document?';
            
            if (!confirm(confirmMessage)) {
                return;
            }
            
            // Show loading state
            const submitBtn = action === 'approve' ? 
                document.getElementById('approveFileBtn') : 
                document.getElementById('declineFileBtn');
            const originalText = submitBtn.textContent;
            submitBtn.disabled = true;
            submitBtn.textContent = 'Processing...';
            
            // Submit the request
            fetch('api/approve_file.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Response status:', response.status);
                return response.json();
            })
            .then(data => {
                console.log('Response data:', data);
                if (data.success) {
                    // Show success notification
                    showSuccess(data.message, 'Document Review Complete');
                    
                    // Remove the document row from the table
                    const requirementId = formData.get('requirement_id');
                    console.log('Looking for row with requirement ID:', requirementId);
                    
                    // Try multiple selectors to find the row
                    let row = null;
                    const selectors = [
                        `button[onclick*="${requirementId}"]`,
                        `button[onclick*="'${requirementId}'"]`,
                        `button[onclick*="showFileApprovalModal(${requirementId},"]`
                    ];
                    
                    for (let selector of selectors) {
                        const button = document.querySelector(selector);
                        if (button) {
                            row = button.closest('tr');
                            console.log('Found row using selector:', selector);
                            break;
                        }
                    }
                    
                    if (row) {
                        console.log('Removing row from table');
                        row.remove();
                        
                        // Check if there are no more pending documents
                        const remainingRows = document.querySelectorAll('#pendingDocumentsTable tbody tr');
                        console.log('Remaining rows:', remainingRows.length);
                        
                        if (remainingRows.length === 0) {
                            console.log('No more rows, reloading page');
                            setTimeout(() => {
                                location.reload();
                            }, 2000);
                        }
                    } else {
                        console.log('Row not found, reloading page');
                        setTimeout(() => {
                            location.reload();
                        }, 2000);
                    }
                    
                    // Hide modal
                    hideModal('fileApprovalModal');
                    
                } else {
                    console.error('Server returned error:', data);
                    showError(data.message || 'Failed to process request', 'Review Error');
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                showError('Failed to process request. Please try again.', 'Network Error');
            })
            .finally(() => {
                // Reset button state
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            });
        }

        function loadMoreDocuments() {
            // This function can be implemented to load more documents via AJAX
            alert('Load more functionality will be implemented in a future update.');
        }

        // Auto-refresh dashboard every 2 minutes
        BusinessPermitSystem.setupAutoRefresh(120);
    </script>
</body>
</html>
