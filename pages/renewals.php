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

// Get user's permits that can be renewed
$renewable_permits = [];

if ($user['role'] === 'applicant') {
    $stmt = $conn->prepare('
        SELECT p.*, b.business_name 
        FROM permits p
        JOIN businesses b ON p.business_id = b.business_id
        WHERE b.owner_id = ?
        AND p.permit_status = "released" 
        AND p.expiry_date >= CURDATE() - INTERVAL 90 DAY
        AND p.expiry_date <= CURDATE() + INTERVAL 30 DAY
    ');
    $stmt->bind_param("i", $user['user_id']);
} else {
    $stmt = $conn->prepare('
        SELECT p.*, b.business_name 
        FROM permits p
        JOIN businesses b ON p.business_id = b.business_id
        WHERE p.permit_status = "released" 
        AND p.expiry_date >= CURDATE() - INTERVAL 90 DAY
        AND p.expiry_date <= CURDATE() + INTERVAL 30 DAY
    ');
}
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $renewable_permits[] = $row;
}
$stmt->close();

// Get renewal applications
$renewal_applications = [];

if ($user['role'] === 'applicant') {
    $stmt = $conn->prepare('
        SELECT r.*, p.permit_number, b.business_name
        FROM renewals r
        JOIN permits p ON r.permit_id = p.permit_id
        JOIN businesses b ON p.business_id = b.business_id
        WHERE b.owner_id = ?
        ORDER BY r.created_at DESC
    ');
    $stmt->bind_param("i", $user['user_id']);
} else {
    $stmt = $conn->prepare('
        SELECT r.*, p.permit_number, b.business_name, u.first_name, u.last_name
        FROM renewals r
        JOIN permits p ON r.permit_id = p.permit_id
        JOIN businesses b ON p.business_id = b.business_id
        JOIN users u ON b.owner_id = u.user_id
        ORDER BY r.created_at DESC
    ');
}
$stmt->execute();
$renewal_applications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$error = '';
$success = '';

// Handle renewal application
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $permit_id = intval($_POST['permit_id'] ?? 0);
    
    if (!$permit_id) {
        $error = 'Please select a permit to renew.';
    } else {
        // Get renewal fee
        $stmt = $conn->prepare('SELECT setting_value FROM system_settings WHERE setting_key = "renewal_fee"');
        $stmt->execute();
        $fee_result = $stmt->get_result()->fetch_assoc();
        $renewal_fee = floatval($fee_result['setting_value'] ?? 300);
        
        // Check for penalty
        $stmt = $conn->prepare('SELECT expiry_date FROM permits WHERE permit_id = ?');
        $stmt->bind_param("i", $permit_id);
        $stmt->execute();
        $permit = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        $penalty_fee = 0;
        if (strtotime($permit['expiry_date']) < strtotime(date('Y-m-d'))) {
            $stmt = $conn->prepare('SELECT setting_value FROM system_settings WHERE setting_key = "penalty_rate"');
            $stmt->execute();
            $penalty_result = $stmt->get_result()->fetch_assoc();
            $penalty_rate = floatval($penalty_result['setting_value'] ?? 0.10);
            $penalty_fee = $renewal_fee * $penalty_rate;
        }
        
        $total_renewal_fee = $renewal_fee + $penalty_fee;
        
        // Create renewal application
        $stmt = $conn->prepare('
            INSERT INTO renewals (
                permit_id, renewal_application_date, renewal_status, 
                previous_expiry_date, renewal_fee, penalty_fee, total_renewal_fee
            ) VALUES (?, CURDATE(), "pending", ?, ?, ?, ?)
        ');
        
        $stmt->bind_param("idddd", $permit_id, $permit['expiry_date'], $renewal_fee, $penalty_fee, $total_renewal_fee);
        
        if ($stmt->execute()) {
            $renewal_id = $conn->insert_id;
            
            // Get business owner info
            $owner_stmt = $conn->prepare('
                SELECT u.contact_number, b.business_name, p.permit_number
                FROM permits p
                JOIN businesses b ON p.business_id = b.business_id
                JOIN users u ON b.owner_id = u.user_id
                WHERE p.permit_id = ?
            ');
            $owner_stmt->bind_param("i", $permit_id);
            $owner_stmt->execute();
            $owner = $owner_stmt->get_result()->fetch_assoc();
            $owner_stmt->close();
            
            logActivity($user['user_id'], 'renewal_application', "Renewal application submitted for permit ID: {$permit_id}");
            
            $success = 'Renewal application submitted successfully!';
            $_POST = [];
        } else {
            $error = 'Failed to submit renewal application. Please try again.';
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
    <title>Permit Renewals - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php 
    require_once __DIR__ . '/../includes/layout.php';
    renderPageLayout('Permit Renewals', $user, 'renewals');
    ?>
        <!-- Renewal Application -->
        <div class="card mb-4">
            <div class="card-header">
                <h4 class="mb-0">Apply for Permit Renewal</h4>
                <p class="text-gray mb-0 mt-2">Select a permit that is due for renewal.</p>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>
                
                <?php if (empty($renewable_permits)): ?>
                    <div class="alert alert-info">
                        <h5 class="alert-heading">No Permits Available for Renewal</h5>
                        <p class="mb-3">
                            Permits are eligible for renewal within 30 days before expiry or up to 90 days after expiry.
                        </p>
                        <?php if ($user['role'] === 'applicant'): ?>
                            <a href="index.php?page=permit-application" class="btn btn-primary">
                                Apply for New Permit
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <form method="POST">
                        <div class="form-group">
                            <label for="permit_id" class="form-label">Select Permit to Renew</label>
                            <select class="form-control" id="permit_id" name="permit_id" required>
                                <option value="">Choose a permit</option>
                                <?php foreach ($renewable_permits as $permit): ?>
                                    <option value="<?php echo $permit['permit_id']; ?>" 
                                            <?php echo (($_POST['permit_id'] ?? '') == $permit['permit_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($permit['permit_number']); ?> - 
                                        <?php echo htmlspecialchars($permit['business_name']); ?> - 
                                        Expires: <?php echo formatDate($permit['expiry_date']); ?>
                                        <?php 
                                        $days_until_expiry = (new DateTime($permit['expiry_date']))->diff(new DateTime())->days;
                                        if (strtotime($permit['expiry_date']) < strtotime(date('Y-m-d'))) {
                                            echo ' (EXPIRED - ' . $days_until_expiry . ' days ago)';
                                        } else {
                                            echo ' (' . $days_until_expiry . ' days left)';
                                        }
                                        ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Select the permit you want to renew</div>
                            <div class="invalid-feedback"></div>
                        </div>
                        
                        <div class="card mt-3">
                            <div class="card-header">
                                <h6 class="mb-0">Renewal Information</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-12 col-md-4">
                                        <p class="mb-2">
                                            <strong>Renewal Fee:</strong> 
                                            <span class="text-primary">₱300.00</span>
                                        </p>
                                    </div>
                                    <div class="col-12 col-md-4">
                                        <p class="mb-2">
                                            <strong>Penalty (if expired):</strong> 
                                            <span class="text-danger">₱30.00</span>
                                        </p>
                                    </div>
                                    <div class="col-12 col-md-4">
                                        <p class="mb-2">
                                            <strong>Processing Time:</strong> 
                                            <span class="text-info">3-5 working days</span>
                                        </p>
                                    </div>
                                </div>
                                <div class="alert alert-info mt-3">
                                    <small>
                                        <strong>Note:</strong> A 10% penalty fee applies for permits renewed after expiry date.
                                        The new permit will be valid for 1 year from the approval date.
                                    </small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group mt-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="acknowledge" name="acknowledge" required>
                                <label class="form-check-label" for="acknowledge">
                                    I acknowledge that I have read and understood the renewal process and fees.
                                </label>
                                <div class="invalid-feedback"></div>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            Submit Renewal Application
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Renewal Applications -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Renewal Applications</h5>
            </div>
            <div class="card-body">
                <?php if (empty($renewal_applications)): ?>
                    <p class="text-gray text-center">No renewal applications found.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Renewal ID</th>
                                    <th>Permit #</th>
                                    <th>Business Name</th>
                                    <?php if ($user['role'] !== 'applicant'): ?>
                                        <th>Applicant</th>
                                    <?php endif; ?>
                                    <th>Application Date</th>
                                    <th>Status</th>
                                    <th>Fee</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($renewal_applications as $renewal): ?>
                                    <tr>
                                        <td>#<?php echo $renewal['renewal_id']; ?></td>
                                        <td>
                                            <a href="index.php?page=tracking&id=<?php echo $renewal['permit_id']; ?>" 
                                               class="text-primary">
                                                <?php echo htmlspecialchars($renewal['permit_number']); ?>
                                            </a>
                                        </td>
                                        <td><?php echo htmlspecialchars($renewal['business_name']); ?></td>
                                        <?php if ($user['role'] !== 'applicant'): ?>
                                            <td><?php echo htmlspecialchars($renewal['first_name'] . ' ' . $renewal['last_name']); ?></td>
                                        <?php endif; ?>
                                        <td><?php echo formatDate($renewal['renewal_application_date']); ?></td>
                                        <td>
                                            <?php
                                            $statusClass = '';
                                            switch($renewal['renewal_status']) {
                                                case 'pending':
                                                    $statusClass = 'badge-warning';
                                                    break;
                                                case 'approved':
                                                    $statusClass = 'badge-success';
                                                    break;
                                                case 'rejected':
                                                    $statusClass = 'badge-danger';
                                                    break;
                                                case 'processed':
                                                    $statusClass = 'badge-primary';
                                                    break;
                                                default:
                                                    $statusClass = 'badge-secondary';
                                            }
                                            ?>
                                            <span class="badge <?php echo $statusClass; ?>">
                                                <?php echo ucfirst($renewal['renewal_status']); ?>
                                            </span>
                                        </td>
                                        <td>₱<?php echo number_format($renewal['total_renewal_fee'], 2); ?></td>
                                        <td>
                                            <a href="index.php?page=tracking&id=<?php echo $renewal['permit_id']; ?>" 
                                               class="btn btn-sm btn-outline-primary">
                                                Track
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php closePageLayout(); ?>
    
    <script>
        // Form validation
        document.querySelector('form')?.addEventListener('submit', function(e) {
            const permitId = this.permit_id.value;
            const acknowledge = this.acknowledge.checked;
            
            if (!permitId) {
                e.preventDefault();
                showFieldError('permit_id', 'Please select a permit to renew');
                return;
            }
            
            if (!acknowledge) {
                e.preventDefault();
                showFieldError('acknowledge', 'You must acknowledge the renewal terms');
                return;
            }
        });
        
        function showFieldError(fieldId, message) {
            const field = document.getElementById(fieldId);
            const feedback = field.parentElement.querySelector('.invalid-feedback');
            
            field.classList.add('is-invalid');
            feedback.textContent = message;
        }
    </script>
</body>
</html>
