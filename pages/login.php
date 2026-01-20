<?php
require_once __DIR__ . '/../includes/auth.php';

$auth = new Auth();
$error = '';
$success = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    $result = $auth->login($username, $password, $remember);
    
    if ($result['success']) {
        // Redirect based on role
        switch ($result['user']['role']) {
            case 'admin':
                header('Location: index.php?page=admin');
                break;
            case 'staff':
                header('Location: index.php?page=dashboard');
                break;
            default:
                header('Location: index.php?page=dashboard');
                break;
        }
        exit();
    } else {
        $error = $result['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .login-wrapper {
            min-height: 100vh;
            background: linear-gradient(135deg, rgba(0, 0, 0, 0.4), rgba(0, 0, 0, 0.4)), url('assets/bg.jpg');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
            overflow: hidden;
            max-width: 450px;
            width: 100%;
            animation: slideUp 0.5s ease-out;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        
        .login-header h1 {
            font-size: 24px;
            font-weight: 700;
            margin: 0;
        }
        
        .login-header p {
            font-size: 14px;
            opacity: 0.95;
            margin: 0.5rem 0 0;
        }
        
        .login-body {
            padding: 40px 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #1f2937;
            font-size: 14px;
        }
        
        .form-group .form-control {
            width: 100%;
            padding: 12px 14px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
            font-family: inherit;
        }
        
        .form-group .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            outline: none;
        }
        
        .form-group .form-control.is-invalid {
            border-color: #ef4444;
            background-color: #fef2f2;
        }
        
        .form-group .invalid-feedback {
            display: block;
            color: #ef4444;
            font-size: 13px;
            margin-top: 6px;
            font-weight: 500;
        }
        
        .form-check {
            display: flex;
            align-items: center;
        }
        
        .form-check-input {
            width: 18px;
            height: 18px;
            margin-right: 8px;
            cursor: pointer;
            accent-color: #667eea;
            border: 2px solid #d1d5db;
            border-radius: 4px;
            transition: all 0.2s ease;
            flex-shrink: 0;
        }
        
        .form-check-input:checked {
            background-color: #667eea;
            border-color: #667eea;
        }
        
        .form-check-label {
            font-size: 14px;
            color: #374151;
            cursor: pointer;
            margin: 0;
        }
        
        .d-flex {
            display: flex;
        }
        
        .justify-content-between {
            justify-content: space-between;
        }
        
        .align-items-center {
            align-items: center;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-block;
            text-align: center;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            width: 100%;
        }
        
        .btn-primary:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }
        
        .btn-primary:disabled {
            opacity: 0.8;
            cursor: not-allowed;
        }
        
        .w-100 {
            width: 100%;
        }
        
        .mt-4 {
            margin-top: 24px;
        }
        
        .mt-3 {
            margin-top: 16px;
        }
        
        .text-center {
            text-align: center;
        }
        
        .text-secondary {
            color: #6b7280;
            font-size: 14px;
        }
        
        .text-primary {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        
        .text-primary:hover {
            text-decoration: underline;
        }
        
        .alert {
            padding: 14px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        
        .alert-danger {
            background-color: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }
        
        .alert-success {
            background-color: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #15803d;
        }
        
        .alert i {
            font-size: 16px;
            margin-top: 2px;
            flex-shrink: 0;
        }
        
        .sidebar-logo-icon {
            font-size: 32px;
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-card">
            <div class="login-header">
                <h1><i class="fas fa-building"></i> Business Permit System</h1>
                <p>Sign in to your account</p>
            </div>
            <div class="login-body">
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><i class="fas fa-circle-xmark"></i><span><?php echo htmlspecialchars($error); ?></span></div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success"><i class="fas fa-circle-check"></i><span><?php echo htmlspecialchars($success); ?></span></div>
                        <?php endif; ?>
                        
                        <form method="POST" id="loginForm" novalidate>
                            <div class="form-group">
                                <label for="username"><i class="fas fa-user"></i> Username or Email</label>
                                <input type="text" class="form-control" id="username" name="username" 
                                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                                <div class="invalid-feedback"></div>
                            </div>
                            
                            <div class="form-group">
                                <label for="password"><i class="fas fa-lock"></i> Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                                <div class="invalid-feedback"></div>
                            </div>
                            
                            <div class="form-group">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" id="remember" name="remember">
                                        <label class="form-check-label" for="remember">Remember me</label>
                                    </div>
                                    <a href="index.php?page=forgot-password" class="text-primary">Forgot password?</a>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100" id="loginBtn">
                                Sign In
                            </button>
                        </form>
                        
                <div class="text-center mt-4">
                    <p class="text-secondary">
                        Don't have an account? 
                        <a href="index.php?page=register" class="text-primary">Register here</a>
                    </p>
                </div>
                
                <div class="text-center mt-3">
                    <small class="text-secondary">
                        Default Admin: admin / password
                    </small>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Form validation
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const form = e.target;
            const btn = document.getElementById('loginBtn');
            
            // Clear previous errors
            form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
            form.querySelectorAll('.invalid-feedback').forEach(el => el.textContent = '');
            
            // Validate form
            let isValid = true;
            
            const username = form.username.value.trim();
            const password = form.password.value;
            
            if (!username) {
                showFieldError('username', 'Username or email is required');
                isValid = false;
            }
            
            if (!password) {
                showFieldError('password', 'Password is required');
                isValid = false;
            }
            
            if (!isValid) {
                e.preventDefault();
                return;
            }
            
            // Show loading state
            btn.disabled = true;
            btn.textContent = 'Signing in...';
            
            // Form will submit normally
        });
        
        function showFieldError(fieldId, message) {
            const field = document.getElementById(fieldId);
            const feedback = field.parentElement.querySelector('.invalid-feedback');
            
            field.classList.add('is-invalid');
            feedback.textContent = message;
        }
        
        // Auto-focus username field
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('username').focus();
        });
    </script>
</body>
</html>
