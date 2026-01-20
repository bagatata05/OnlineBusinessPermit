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

$error = '';
$success = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize($_POST['action'] ?? '');
    
    switch ($action) {
        case 'update_profile':
            $profile_data = [
                'first_name' => sanitize($_POST['first_name'] ?? ''),
                'last_name' => sanitize($_POST['last_name'] ?? ''),
                'contact_number' => sanitize($_POST['contact_number'] ?? ''),
                'email' => sanitize($_POST['email'] ?? '')
            ];
            
            $result = $auth->updateProfile($user['user_id'], $profile_data);
            if ($result['success']) {
                $success = $result['message'];
                // Update session data
                $_SESSION['first_name'] = $profile_data['first_name'];
                $_SESSION['last_name'] = $profile_data['last_name'];
                $_SESSION['full_name'] = $profile_data['first_name'] . ' ' . $profile_data['last_name'];
                $_SESSION['contact_number'] = $profile_data['contact_number'];
                $_SESSION['email'] = $profile_data['email'];
                $user = $auth->getCurrentUser(); // Refresh user data
            } else {
                $error = $result['message'];
            }
            break;
            
        case 'change_password':
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            if ($new_password !== $confirm_password) {
                $error = 'New passwords do not match.';
            } else {
                $result = $auth->changePassword($user['user_id'], $current_password, $new_password);
                if ($result['success']) {
                    $success = $result['message'];
                } else {
                    $error = $result['message'];
                }
            }
            break;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php 
    require_once __DIR__ . '/../includes/layout.php';
    renderPageLayout('Profile', $user, 'profile');
    ?>
        <div class="row justify-content-center">
            <div class="col-12 col-lg-8">
                <!-- Profile Information -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h4 class="mb-0">Profile Information</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <input type="hidden" name="action" value="update_profile">
                            
                            <div class="row">
                                <div class="col-12 col-md-6">
                                    <div class="form-group">
                                        <label for="first_name" class="form-label">First Name</label>
                                        <input type="text" class="form-control" id="first_name" name="first_name" 
                                               value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                                        <div class="invalid-feedback"></div>
                                    </div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="form-group">
                                        <label for="last_name" class="form-label">Last Name</label>
                                        <input type="text" class="form-control" id="last_name" name="last_name" 
                                               value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                                        <div class="invalid-feedback"></div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                <div class="form-text">We'll send important updates to this email</div>
                                <div class="invalid-feedback"></div>
                            </div>
                            
                            <div class="form-group">
                                <label for="contact_number" class="form-label">Contact Number</label>
                                <input type="tel" class="form-control" id="contact_number" name="contact_number" 
                                       value="<?php echo htmlspecialchars($user['contact_number']); ?>" required
                                       pattern="[0-9]{10,11}" placeholder="09XXXXXXXXX">
                                <div class="form-text">Format: 09XXXXXXXXX (10-11 digits)</div>
                                <div class="invalid-feedback"></div>
                            </div>
                            
                            <div class="form-group">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" name="username" 
                                       value="<?php echo htmlspecialchars($user['username']); ?>" readonly>
                                <div class="form-text">Username cannot be changed</div>
                            </div>
                            
                            <div class="form-group">
                                <label for="role" class="form-label">Account Type</label>
                                <input type="text" class="form-control" id="role" name="role" 
                                       value="<?php echo ucfirst($user['role']); ?>" readonly>
                                <div class="form-text">Account type is assigned by system administrator</div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                Update Profile
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Change Password -->
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">Change Password</h4>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="change_password">
                            
                            <div class="form-group">
                                <label for="current_password" class="form-label">Current Password</label>
                                <input type="password" class="form-control" id="current_password" 
                                       name="current_password" required>
                                <div class="invalid-feedback"></div>
                            </div>
                            
                            <div class="form-group">
                                <label for="new_password" class="form-label">New Password</label>
                                <input type="password" class="form-control" id="new_password" 
                                       name="new_password" required minlength="8">
                                <div class="form-text">Minimum 8 characters</div>
                                <div class="invalid-feedback"></div>
                            </div>
                            
                            <div class="form-group">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" id="confirm_password" 
                                       name="confirm_password" required>
                                <div class="invalid-feedback"></div>
                            </div>
                            
                            <button type="submit" class="btn btn-warning">
                                Change Password
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php closePageLayout(); ?>
    
    <script>
        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            // Profile form validation
            const profileForm = document.querySelector('form[action=""][method="POST"]');
            if (profileForm) {
                profileForm.addEventListener('submit', function(e) {
                    if (!validateForm(this.id || 'profileForm')) {
                        e.preventDefault();
                    }
                });
            }
            
            // Password form validation
            const passwordForm = document.querySelector('input[name="action"][value="change_password"]').form;
            if (passwordForm) {
                passwordForm.addEventListener('submit', function(e) {
                    const newPassword = this.new_password.value;
                    const confirmPassword = this.confirm_password.value;
                    
                    if (newPassword !== confirmPassword) {
                        showFieldError('confirm_password', 'Passwords do not match');
                        e.preventDefault();
                        return;
                    }
                    
                    if (newPassword.length < 8) {
                        showFieldError('new_password', 'Password must be at least 8 characters');
                        e.preventDefault();
                        return;
                    }
                });
            }
            
            // Real-time validation
            document.getElementById('email')?.addEventListener('blur', function() {
                if (this.value && !validateEmail(this.value)) {
                    showFieldError('email', 'Please enter a valid email address');
                } else {
                    this.classList.remove('is-invalid');
                    this.parentElement.querySelector('.invalid-feedback').textContent = '';
                }
            });
            
            document.getElementById('contact_number')?.addEventListener('blur', function() {
                if (this.value && !validatePhoneNumber(this.value)) {
                    showFieldError('contact_number', 'Please enter a valid Philippine mobile number');
                } else {
                    this.classList.remove('is-invalid');
                    this.parentElement.querySelector('.invalid-feedback').textContent = '';
                }
            });
            
            document.getElementById('confirm_password')?.addEventListener('blur', function() {
                const newPassword = document.getElementById('new_password').value;
                if (this.value && this.value !== newPassword) {
                    showFieldError('confirm_password', 'Passwords do not match');
                } else {
                    this.classList.remove('is-invalid');
                    this.parentElement.querySelector('.invalid-feedback').textContent = '';
                }
            });
        });
        
        function validateEmail(email) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        }
        
        function validatePhoneNumber(phone) {
            const re = /^09\d{8,9}$/;
            return re.test(phone);
        }
        
        function showFieldError(fieldId, message) {
            const field = document.getElementById(fieldId);
            const feedback = field.parentElement.querySelector('.invalid-feedback');
            
            field.classList.add('is-invalid');
            feedback.textContent = message;
        }
    </script>
</body>
</html>
