<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .register-container {
            min-height: 100vh;
            background: linear-gradient(135deg, rgba(0, 0, 0, 0.4), rgba(0, 0, 0, 0.4)), url('assets/bg.jpg');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
        }
        
        .register-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
            overflow: hidden;
            max-width: 600px;
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
        
        .register-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        
        .register-header h1 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        
        .register-header p {
            font-size: 14px;
            opacity: 0.95;
            margin: 0;
        }
        
        .register-body {
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
        
        .form-group .form-text {
            display: block;
            font-size: 13px;
            color: #6b7280;
            margin-top: 6px;
        }
        
        .form-group .invalid-feedback {
            display: block;
            color: #ef4444;
            font-size: 13px;
            margin-top: 6px;
            font-weight: 500;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 0;
        }
        
        .form-row .form-group {
            margin-bottom: 0;
        }
        
        @media (max-width: 576px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
        
        .password-strength {
            margin-top: 8px;
            height: 4px;
            background: #e5e7eb;
            border-radius: 2px;
            overflow: hidden;
        }
        
        .password-strength-bar {
            height: 100%;
            width: 0;
            transition: all 0.3s ease;
            border-radius: 2px;
        }
        
        .password-strength-bar.weak {
            width: 33%;
            background: #ef4444;
        }
        
        .password-strength-bar.fair {
            width: 66%;
            background: #f59e0b;
        }
        
        .password-strength-bar.strong {
            width: 100%;
            background: #10b981;
        }
        
        .form-check {
            display: flex;
            align-items: flex-start;
            margin-bottom: 20px;
            padding: 12px;
            background: #f9fafb;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
        }
        
        .form-check-input {
            width: 20px;
            height: 20px;
            margin-top: 2px;
            margin-right: 12px;
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
        
        .form-check-label a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        
        .form-check-label a:hover {
            text-decoration: underline;
        }
        
        .btn-register {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }
        
        .btn-register:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }
        
        .btn-register:active:not(:disabled) {
            transform: translateY(0);
        }
        
        .btn-register:disabled {
            opacity: 0.8;
            cursor: not-allowed;
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
        
        .register-footer {
            padding: 20px 30px;
            background: #f9fafb;
            text-align: center;
            border-top: 1px solid #e5e7eb;
        }
        
        .register-footer p {
            margin: 0;
            font-size: 14px;
            color: #6b7280;
        }
        
        .register-footer a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        
        .register-footer a:hover {
            text-decoration: underline;
        }
        
        .spinner-loading {
            display: inline-block;
            width: 14px;
            height: 14px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 0.6s linear infinite;
            margin-right: 8px;
            vertical-align: middle;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-card">
            <div class="register-header">
                <h1><i class="fas fa-building"></i> Create Account</h1>
                <p>Register for a new business permit account</p>
            </div>
            <div class="register-body">
                        <?php
                        require_once __DIR__ . '/../includes/auth.php';
                        
                        $auth = new Auth();
                        $error = '';
                        $success = '';
                        
                        // Handle registration form submission
                        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                            $userData = [
                                'username' => sanitize($_POST['username'] ?? ''),
                                'email' => sanitize($_POST['email'] ?? ''),
                                'password' => $_POST['password'] ?? '',
                                'first_name' => sanitize($_POST['first_name'] ?? ''),
                                'last_name' => sanitize($_POST['last_name'] ?? ''),
                                'contact_number' => sanitize($_POST['contact_number'] ?? '')
                            ];
                            
                            // Validate password confirmation
                            if ($userData['password'] !== $_POST['confirm_password']) {
                                $error = 'Passwords do not match.';
                            } else {
                                $result = $auth->register($userData);
                                
                                if ($result['success']) {
                                    $success = $result['message'];
                                    // Clear form
                                    $_POST = [];
                                } else {
                                    $error = $result['message'];
                                }
                            }
                        }
                        
                        // Display messages
                        if ($error) {
                            echo '<div class="alert alert-danger"><i class="fas fa-circle-xmark"></i><span>' . htmlspecialchars($error) . '</span></div>';
                        }
                        if ($success) {
                            echo '<div class="alert alert-success"><i class="fas fa-circle-check"></i><span>' . htmlspecialchars($success) . '</span></div>';
                        }
                        ?>
                        
                        <form method="POST" id="registerForm" novalidate>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="first_name"><i class="fas fa-user"></i> First Name</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" 
                                           value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>" required>
                                    <div class="invalid-feedback"></div>
                                </div>
                                <div class="form-group">
                                    <label for="last_name"><i class="fas fa-user"></i> Last Name</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" 
                                           value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>" required>
                                    <div class="invalid-feedback"></div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="username"><i class="fas fa-at"></i> Username</label>
                                <input type="text" class="form-control" id="username" name="username" 
                                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                                <div class="form-text">Choose a unique username for your account (minimum 3 characters)</div>
                                <div class="invalid-feedback"></div>
                            </div>
                            
                            <div class="form-group">
                                <label for="email"><i class="fas fa-envelope"></i> Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                                <div class="form-text">We'll send important updates to this email</div>
                                <div class="invalid-feedback"></div>
                            </div>
                            
                            <div class="form-group">
                                <label for="contact_number"><i class="fas fa-phone"></i> Contact Number</label>
                                <input type="tel" class="form-control" id="contact_number" name="contact_number" 
                                       value="<?php echo htmlspecialchars($_POST['contact_number'] ?? ''); ?>" required
                                       pattern="[0-9]{10,11}" placeholder="09XXXXXXXXX">
                                <div class="form-text">Format: 09XXXXXXXXX (10-11 digits)</div>
                                <div class="invalid-feedback"></div>
                            </div>
                            
                            <div class="form-group">
                                <label for="password"><i class="fas fa-lock"></i> Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                                <div class="password-strength">
                                    <div class="password-strength-bar"></div>
                                </div>
                                <div class="form-text">Minimum 8 characters, mix of letters, numbers, and symbols recommended</div>
                                <div class="invalid-feedback"></div>
                            </div>
                            
                            <div class="form-group">
                                <label for="confirm_password"><i class="fas fa-lock"></i> Confirm Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                <div class="invalid-feedback"></div>
                            </div>
                            
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="terms" name="terms" required>
                                <label class="form-check-label" for="terms">
                                    I agree to the <a href="#" target="_blank">Terms and Conditions</a> and <a href="#" target="_blank">Privacy Policy</a>
                                </label>
                                <div class="invalid-feedback"></div>
                            </div>
                            
                            <button type="submit" class="btn-register" id="registerBtn">
                                <span class="btn-text">Create Account</span>
                            </button>
                        </form>
            </div>
            
            <div class="register-footer">
                <p>
                    Already have an account? 
                    <a href="index.php?page=login">Sign in here</a>
                </p>
            </div>
        </div>
    </div>
    
    <script>
        // Password strength indicator
        function checkPasswordStrength(password) {
            let strength = 'weak';
            
            if (password.length >= 12 && /[a-z]/.test(password) && /[A-Z]/.test(password) && /[0-9]/.test(password) && /[^a-zA-Z0-9]/.test(password)) {
                strength = 'strong';
            } else if (password.length >= 8 && /[a-z]/.test(password) && /[0-9]/.test(password)) {
                strength = 'fair';
            }
            
            return strength;
        }
        
        document.getElementById('password').addEventListener('input', function() {
            const strength = checkPasswordStrength(this.value);
            const strengthBar = this.parentElement.querySelector('.password-strength-bar');
            
            strengthBar.className = 'password-strength-bar ' + strength;
        });
        
        // Form validation
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const form = e.target;
            const btn = document.getElementById('registerBtn');
            
            // Clear previous errors
            form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
            form.querySelectorAll('.invalid-feedback').forEach(el => el.textContent = '');
            
            // Validate form
            let isValid = true;
            
            const firstName = form.first_name.value.trim();
            const lastName = form.last_name.value.trim();
            const username = form.username.value.trim();
            const email = form.email.value.trim();
            const contactNumber = form.contact_number.value.trim();
            const password = form.password.value;
            const confirmPassword = form.confirm_password.value;
            const terms = form.terms.checked;
            
            if (!firstName) {
                showFieldError('first_name', 'First name is required');
                isValid = false;
            }
            
            if (!lastName) {
                showFieldError('last_name', 'Last name is required');
                isValid = false;
            }
            
            if (!username) {
                showFieldError('username', 'Username is required');
                isValid = false;
            } else if (username.length < 3) {
                showFieldError('username', 'Username must be at least 3 characters');
                isValid = false;
            }
            
            if (!email) {
                showFieldError('email', 'Email is required');
                isValid = false;
            } else if (!validateEmail(email)) {
                showFieldError('email', 'Please enter a valid email address');
                isValid = false;
            }
            
            if (!contactNumber) {
                showFieldError('contact_number', 'Contact number is required');
                isValid = false;
            } else if (!validatePhoneNumber(contactNumber)) {
                showFieldError('contact_number', 'Please enter a valid Philippine mobile number');
                isValid = false;
            }
            
            if (!password) {
                showFieldError('password', 'Password is required');
                isValid = false;
            } else if (password.length < 8) {
                showFieldError('password', 'Password must be at least 8 characters');
                isValid = false;
            }
            
            if (!confirmPassword) {
                showFieldError('confirm_password', 'Please confirm your password');
                isValid = false;
            } else if (password !== confirmPassword) {
                showFieldError('confirm_password', 'Passwords do not match');
                isValid = false;
            }
            
            if (!terms) {
                showFieldError('terms', 'You must agree to the terms and conditions');
                isValid = false;
            }
            
            if (!isValid) {
                e.preventDefault();
                return;
            }
            
            // Show loading state
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-loading"></span><span class="btn-text">Creating account...</span>';
            
            // Submit form
            form.submit();
        });
        
        function showFieldError(fieldId, message) {
            const field = document.getElementById(fieldId);
            const feedback = field.parentElement.querySelector('.invalid-feedback');
            
            field.classList.add('is-invalid');
            feedback.textContent = message;
        }
        
        function validateEmail(email) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        }
        
        function validatePhoneNumber(phone) {
            const re = /^09\d{8,9}$/;
            return re.test(phone);
        }
        
        // Real-time validation
        document.getElementById('email').addEventListener('blur', function() {
            if (this.value && !validateEmail(this.value)) {
                showFieldError('email', 'Please enter a valid email address');
            } else {
                this.classList.remove('is-invalid');
                this.parentElement.querySelector('.invalid-feedback').textContent = '';
            }
        });
        
        document.getElementById('contact_number').addEventListener('blur', function() {
            if (this.value && !validatePhoneNumber(this.value)) {
                showFieldError('contact_number', 'Please enter a valid Philippine mobile number');
            } else {
                this.classList.remove('is-invalid');
                this.parentElement.querySelector('.invalid-feedback').textContent = '';
            }
        });
        
        document.getElementById('confirm_password').addEventListener('blur', function() {
            const password = document.getElementById('password').value;
            if (this.value && this.value !== password) {
                showFieldError('confirm_password', 'Passwords do not match');
            } else {
                this.classList.remove('is-invalid');
                this.parentElement.querySelector('.invalid-feedback').textContent = '';
            }
        });
    </script>
</body>
</html>
