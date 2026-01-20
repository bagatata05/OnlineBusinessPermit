<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/address.php';

// Check authentication
$auth = new Auth();
if (!$auth->checkAuth()) {
    header('Location: index.php?page=login');
    exit();
}

$user = $auth->getCurrentUser();
$conn = getDBConnection();
$addressManager = new AddressManager();

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $businessData = [
        'business_name' => sanitize($_POST['business_name'] ?? ''),
        'trade_name' => sanitize($_POST['trade_name'] ?? ''),
        'business_type' => sanitize($_POST['business_type'] ?? ''),
        'business_street' => sanitize($_POST['business_street'] ?? ''),
        'business_barangay' => sanitize($_POST['business_barangay'] ?? ''),
        'business_city' => sanitize($_POST['business_city'] ?? ''),
        'business_zip_code' => sanitize($_POST['business_zip_code'] ?? ''),
        'business_province' => sanitize($_POST['business_province'] ?? ''),
        'business_phone' => sanitize($_POST['business_phone'] ?? ''),
        'business_email' => sanitize($_POST['business_email'] ?? ''),
        'capital_investment' => floatval($_POST['capital_investment'] ?? 0),
        'number_of_employees' => intval($_POST['number_of_employees'] ?? 0),
        'date_established' => $_POST['date_established'] ?? '',
        'business_registration_number' => sanitize($_POST['business_registration_number'] ?? '')
    ];
    
    // Validate required fields
    $required = ['business_name', 'business_type', 'business_street', 'business_city', 'business_zip_code'];
    foreach ($required as $field) {
        if (empty($businessData[$field])) {
            $error = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
            break;
        }
    }
    
    if (!$error) {
        // Combine address fields with barangay
        $barangay_part = $businessData['business_barangay'] ? ', ' . $businessData['business_barangay'] : '';
        $full_address = "{$businessData['business_street']}{$barangay_part}, {$businessData['business_city']} {$businessData['business_zip_code']}, {$businessData['business_province']}";
        
        // Insert business record with barangay
        $stmt = $conn->prepare("
            INSERT INTO businesses (
                owner_id, business_name, trade_name, business_type, business_address, 
                business_zip_code, business_city, business_province,
                business_phone, business_email, capital_investment, number_of_employees, 
                date_established, business_registration_number
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->bind_param("isssssssssidis", 
            $user['user_id'],
            $businessData['business_name'],
            $businessData['trade_name'],
            $businessData['business_type'],
            $full_address,
            $businessData['business_zip_code'],
            $businessData['business_city'],
            $businessData['business_province'],
            $businessData['business_phone'],
            $businessData['business_email'],
            $businessData['capital_investment'],
            $businessData['number_of_employees'],
            $businessData['date_established'],
            $businessData['business_registration_number']
        );
        
        if ($stmt->execute()) {
            $business_id = $conn->insert_id;
            logActivity($user['user_id'], 'business_registration', 'New business registered: ' . $businessData['business_name']);
            $success = 'Business registered successfully! You can now apply for a permit.';
            
            // Clear form
            $_POST = [];
        } else {
            $error = 'Failed to register business. Please try again.';
        }
        $stmt->close();
    }
}

$conn->close();
$cities = $addressManager->getCities();
sort($cities);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Business Registration - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php 
    require_once __DIR__ . '/../includes/layout.php';
    renderPageLayout('Register Business', $user, 'business-registration');
    ?>
        <div class="row justify-content-center">
            <div class="col-12 col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">Register New Business</h4>
                        <p class="text-gray mb-0 mt-2">Please provide complete and accurate information about your business.</p>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <?php echo htmlspecialchars($success); ?>
                                <div class="mt-2">
                                    <a href="index.php?page=permit-application" class="btn btn-primary btn-sm">
                                        Apply for Permit Now
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" id="businessForm">
                            <div class="row">
                                <div class="col-12">
                                    <h5 class="mb-3">Basic Information</h5>
                                </div>
                                
                                <div class="col-12 col-md-6">
                                    <div class="form-group">
                                        <label for="business_name" class="form-label">Business Name *</label>
                                        <input type="text" class="form-control" id="business_name" name="business_name" 
                                               value="<?php echo htmlspecialchars($_POST['business_name'] ?? ''); ?>" required>
                                        <div class="form-text">Legal registered name of the business</div>
                                        <div class="invalid-feedback"></div>
                                    </div>
                                </div>
                                
                                <div class="col-12 col-md-6">
                                    <div class="form-group">
                                        <label for="trade_name" class="form-label">Trade Name / DBA</label>
                                        <input type="text" class="form-control" id="trade_name" name="trade_name" 
                                               value="<?php echo htmlspecialchars($_POST['trade_name'] ?? ''); ?>">
                                        <div class="form-text">Doing business as (optional)</div>
                                        <div class="invalid-feedback"></div>
                                    </div>
                                </div>
                                
                                <div class="col-12 col-md-6">
                                    <div class="form-group">
                                        <label for="business_type" class="form-label">Business Type *</label>
                                        <select class="form-control" id="business_type" name="business_type" required>
                                            <option value="">Select business type</option>
                                            <option value="Retail" <?php echo ($_POST['business_type'] ?? '') === 'Retail' ? 'selected' : ''; ?>>Retail</option>
                                            <option value="Wholesale" <?php echo ($_POST['business_type'] ?? '') === 'Wholesale' ? 'selected' : ''; ?>>Wholesale</option>
                                            <option value="Service" <?php echo ($_POST['business_type'] ?? '') === 'Service' ? 'selected' : ''; ?>>Service</option>
                                            <option value="Manufacturing" <?php echo ($_POST['business_type'] ?? '') === 'Manufacturing' ? 'selected' : ''; ?>>Manufacturing</option>
                                            <option value="Restaurant" <?php echo ($_POST['business_type'] ?? '') === 'Restaurant' ? 'selected' : ''; ?>>Restaurant/Food Service</option>
                                            <option value="Construction" <?php echo ($_POST['business_type'] ?? '') === 'Construction' ? 'selected' : ''; ?>>Construction</option>
                                            <option value="Technology" <?php echo ($_POST['business_type'] ?? '') === 'Technology' ? 'selected' : ''; ?>>Technology/IT</option>
                                            <option value="Healthcare" <?php echo ($_POST['business_type'] ?? '') === 'Healthcare' ? 'selected' : ''; ?>>Healthcare</option>
                                            <option value="Education" <?php echo ($_POST['business_type'] ?? '') === 'Education' ? 'selected' : ''; ?>>Education</option>
                                            <option value="Real Estate" <?php echo ($_POST['business_type'] ?? '') === 'Real Estate' ? 'selected' : ''; ?>>Real Estate</option>
                                            <option value="Transportation" <?php echo ($_POST['business_type'] ?? '') === 'Transportation' ? 'selected' : ''; ?>>Transportation</option>
                                            <option value="Other" <?php echo ($_POST['business_type'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                                        </select>
                                        <div class="invalid-feedback"></div>
                                    </div>
                                </div>
                                
                                <div class="col-12 col-md-6">
                                    <div class="form-group">
                                        <label for="date_established" class="form-label">Date Established</label>
                                        <input type="date" class="form-control" id="date_established" name="date_established" 
                                               value="<?php echo htmlspecialchars($_POST['date_established'] ?? ''); ?>">
                                        <div class="form-text">When the business started operations</div>
                                        <div class="invalid-feedback"></div>
                                    </div>
                                </div>
                            </div>
                            
                            <hr class="my-4">
                            
                            <div class="row">
                                <div class="col-12">
                                    <h5 class="mb-3">Location & Contact</h5>
                                </div>
                                
                                <div class="col-12 col-md-6">
                                    <div class="form-group">
                                        <label for="business_street" class="form-label">Street Address *</label>
                                        <input type="text" class="form-control" id="business_street" name="business_street" 
                                               value="<?php echo htmlspecialchars($_POST['business_street'] ?? ''); ?>" 
                                               placeholder="Street/Building Name" required>
                                        <div class="form-text">Street or building name</div>
                                        <div class="invalid-feedback"></div>
                                    </div>
                                </div>
                                
                                <div class="col-12 col-md-6">
                                    <div class="form-group">
                                        <label for="business_barangay" class="form-label">Barangay</label>
                                        <select class="form-control" id="business_barangay" name="business_barangay">
                                            <option value="">Select barangay (if available)</option>
                                            <?php 
                                            $selected_city = $_POST['business_city'] ?? '';
                                            if ($selected_city) {
                                                $barangays = $addressManager->getBarangaysByCity($selected_city);
                                                foreach ($barangays as $barangay):
                                            ?>
                                                <option value="<?php echo htmlspecialchars($barangay); ?>"
                                                    <?php echo ($_POST['business_barangay'] ?? '') === $barangay ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($barangay); ?>
                                                </option>
                                            <?php 
                                                endforeach;
                                            }
                                            ?>
                                        </select>
                                        <div class="form-text">Select barangay/subdivision if available</div>
                                    </div>
                                </div>
                                
                                <div class="col-12 col-md-6">
                                    <div class="form-group">
                                        <label for="business_city" class="form-label">City / Municipality *</label>
                                        <select class="form-control" id="business_city" name="business_city" required onchange="updateZipCodes()">
                                            <option value="">Select city</option>
                                            <?php foreach ($cities as $city): ?>
                                                <option value="<?php echo htmlspecialchars($city); ?>" 
                                                    <?php echo ($_POST['business_city'] ?? '') === $city ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($city); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="invalid-feedback"></div>
                                    </div>
                                </div>
                                
                                <div class="col-12 col-md-6">
                                    <div class="form-group">
                                        <label for="business_zip_code" class="form-label">ZIP Code *</label>
                                        <select class="form-control" id="business_zip_code" name="business_zip_code" required onchange="updateProvince()">
                                            <option value="">Select ZIP code</option>
                                            <?php 
                                            $selected_city = $_POST['business_city'] ?? '';
                                            if ($selected_city) {
                                                $zips = $addressManager->getZipCodesByCity($selected_city);
                                                foreach ($zips as $zip):
                                            ?>
                                                <option value="<?php echo htmlspecialchars($zip); ?>"
                                                    <?php echo ($_POST['business_zip_code'] ?? '') === $zip ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($zip); ?>
                                                </option>
                                            <?php 
                                                endforeach;
                                            }
                                            ?>
                                        </select>
                                        <div class="form-text">Postal/ZIP code area</div>
                                        <div class="invalid-feedback"></div>
                                    </div>
                                </div>
                                
                                <div class="col-12 col-md-6">
                                    <div class="form-group">
                                        <label for="business_province" class="form-label">Province</label>
                                        <input type="text" class="form-control" id="business_province" name="business_province" 
                                               value="<?php echo htmlspecialchars($_POST['business_province'] ?? ''); ?>" 
                                               readonly>
                                        <div class="form-text">Auto-populated based on ZIP code</div>
                                    </div>
                                </div>
                                
                                <div class="col-12 col-md-6">
                                    <div class="form-group">
                                        <label for="business_phone" class="form-label">Business Phone</label>
                                        <input type="tel" class="form-control" id="business_phone" name="business_phone" 
                                               value="<?php echo htmlspecialchars($_POST['business_phone'] ?? ''); ?>"
                                               pattern="[0-9]{10,11}" placeholder="09XXXXXXXXX">
                                        <div class="form-text">Business contact number</div>
                                        <div class="invalid-feedback"></div>
                                    </div>
                                </div>
                                
                                <div class="col-12 col-md-6">
                                    <div class="form-group">
                                        <label for="business_email" class="form-label">Business Email</label>
                                        <input type="email" class="form-control" id="business_email" name="business_email" 
                                               value="<?php echo htmlspecialchars($_POST['business_email'] ?? ''); ?>">
                                        <div class="form-text">Official business email address</div>
                                        <div class="invalid-feedback"></div>
                                    </div>
                                </div>
                            </div>
                            
                            <hr class="my-4">
                            
                            <div class="row">
                                <div class="col-12">
                                    <h5 class="mb-3">Financial Information</h5>
                                </div>
                                
                                <div class="col-12 col-md-6">
                                    <div class="form-group">
                                        <label for="capital_investment" class="form-label">Capital Investment (PHP)</label>
                                        <input type="number" class="form-control" id="capital_investment" name="capital_investment" 
                                               value="<?php echo htmlspecialchars($_POST['capital_investment'] ?? ''); ?>"
                                               min="0" step="0.01">
                                        <div class="form-text">Total capital investment in Philippine Peso</div>
                                        <div class="invalid-feedback"></div>
                                    </div>
                                </div>
                                
                                <div class="col-12 col-md-6">
                                    <div class="form-group">
                                        <label for="number_of_employees" class="form-label">Number of Employees</label>
                                        <input type="number" class="form-control" id="number_of_employees" name="number_of_employees" 
                                               value="<?php echo htmlspecialchars($_POST['number_of_employees'] ?? ''); ?>"
                                               min="0">
                                        <div class="form-text">Total number of employees</div>
                                        <div class="invalid-feedback"></div>
                                    </div>
                                </div>
                                
                                <div class="col-12">
                                    <div class="form-group">
                                        <label for="business_registration_number" class="form-label">Business Registration Number</label>
                                        <input type="text" class="form-control" id="business_registration_number" 
                                               name="business_registration_number" 
                                               value="<?php echo htmlspecialchars($_POST['business_registration_number'] ?? ''); ?>">
                                        <div class="form-text">DTI/SEC/CDA Registration Number (if applicable)</div>
                                        <div class="invalid-feedback"></div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group mt-4">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="certify" name="certify" required>
                                    <label class="form-check-label" for="certify">
                                        I certify that all information provided is true and accurate. I understand that 
                                        false statements may result in the denial or revocation of my business permit.
                                    </label>
                                    <div class="invalid-feedback"></div>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between gap-2">
                                <a href="index.php?page=dashboard" class="btn btn-outline-primary">
                                    Cancel
                                </a>
                                <button type="submit" class="btn btn-primary" id="submitBtn">
                                    Register Business
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php closePageLayout(); ?>
    
    <script>
        // City and ZIP code data
        const cityZipData = <?php echo $addressManager->getCitiesJSON(); ?>;
        
        function updateZipCodes() {
            const city = document.getElementById('business_city').value;
            const zipSelect = document.getElementById('business_zip_code');
            const provinceInput = document.getElementById('business_province');
            const barangaySelect = document.getElementById('business_barangay');
            
            // Clear ZIP code select
            zipSelect.innerHTML = '<option value="">Select ZIP code</option>';
            provinceInput.value = '';
            
            // Clear barangay select
            barangaySelect.innerHTML = '<option value="">Select barangay (if available)</option>';
            
            if (!city) return;
            
            // Fetch ZIP codes for the selected city
            Promise.all([
                fetch('api/get_address_data.php?city=' + encodeURIComponent(city))
                    .then(r => r.json()),
                fetch('api/get_address_data.php?city=' + encodeURIComponent(city) + '&barangays=1')
                    .then(r => r.json())
            ])
            .then(([zipData, barangayData]) => {
                // Populate ZIP codes
                if (zipData.zip_codes) {
                    zipData.zip_codes.forEach(item => {
                        const option = document.createElement('option');
                        option.value = item.zip_code;
                        option.textContent = item.zip_code + ' - ' + item.province;
                        zipSelect.appendChild(option);
                    });
                }
                
                // Populate barangays
                if (barangayData.barangays && barangayData.barangays.length > 0) {
                    barangayData.barangays.forEach(barangay => {
                        const option = document.createElement('option');
                        option.value = barangay;
                        option.textContent = barangay;
                        barangaySelect.appendChild(option);
                    });
                }
            })
            .catch(error => console.error('Error loading address data:', error));
        }
        
        function updateProvince() {
            const city = document.getElementById('business_city').value;
            const zipCode = document.getElementById('business_zip_code').value;
            const provinceInput = document.getElementById('business_province');
            
            if (!city || !zipCode) return;
            
            // Fetch province info
            fetch('api/get_address_data.php?city=' + encodeURIComponent(city) + '&zip=' + encodeURIComponent(zipCode))
                .then(response => response.json())
                .then(data => {
                    if (data.province) {
                        provinceInput.value = data.province;
                    }
                })
                .catch(error => console.error('Error loading province:', error));
        }
        
        // Form validation
        document.getElementById('businessForm').addEventListener('submit', function(e) {
            const form = e.target;
            const btn = document.getElementById('submitBtn');
            
            // Clear previous errors
            form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
            form.querySelectorAll('.invalid-feedback').forEach(el => el.textContent = '');
            
            // Validate form
            let isValid = true;
            
            const businessName = form.business_name.value.trim();
            const businessType = form.business_type.value;
            const businessStreet = form.business_street.value.trim();
            const businessCity = form.business_city.value;
            const businessZip = form.business_zip_code.value;
            const certify = form.certify.checked;
            
            if (!businessName) {
                showFieldError('business_name', 'Business name is required');
                isValid = false;
            }
            
            if (!businessType) {
                showFieldError('business_type', 'Please select a business type');
                isValid = false;
            }
            
            if (!businessStreet) {
                showFieldError('business_street', 'Street address is required');
                isValid = false;
            }
            
            if (!businessCity) {
                showFieldError('business_city', 'City/Municipality is required');
                isValid = false;
            }
            
            if (!businessZip) {
                showFieldError('business_zip_code', 'ZIP code is required');
                isValid = false;
            }
            
            if (!certify) {
                showFieldError('certify', 'You must certify the information is true');
                isValid = false;
            }
            
            // Validate phone number if provided
            const businessPhone = form.business_phone.value.trim();
            if (businessPhone && !validatePhoneNumber(businessPhone)) {
                showFieldError('business_phone', 'Please enter a valid Philippine mobile number');
                isValid = false;
            }
            
            // Validate email if provided
            const businessEmail = form.business_email.value.trim();
            if (businessEmail && !validateEmail(businessEmail)) {
                showFieldError('business_email', 'Please enter a valid email address');
                isValid = false;
            }
            
            if (!isValid) {
                e.preventDefault();
                return;
            }
            
            // Show loading state
            btn.disabled = true;
            btn.textContent = 'Registering...';
            
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
        document.getElementById('business_phone').addEventListener('blur', function() {
            if (this.value && !validatePhoneNumber(this.value)) {
                showFieldError('business_phone', 'Please enter a valid Philippine mobile number');
            } else {
                this.classList.remove('is-invalid');
                this.parentElement.querySelector('.invalid-feedback').textContent = '';
            }
        });
        
        document.getElementById('business_email').addEventListener('blur', function() {
            if (this.value && !validateEmail(this.value)) {
                showFieldError('business_email', 'Please enter a valid email address');
            } else {
                this.classList.remove('is-invalid');
                this.parentElement.querySelector('.invalid-feedback').textContent = '';
            }
        });
    </script>
</body>
</html>
