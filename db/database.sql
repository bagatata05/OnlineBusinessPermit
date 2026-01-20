-- Online Business Permit and Licensing System Database Schema

-- Create Database
CREATE DATABASE IF NOT EXISTS business_permit_system;
USE business_permit_system;

-- Users Table
CREATE TABLE users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    contact_number VARCHAR(20) NOT NULL,
    role ENUM('admin', 'staff', 'applicant') NOT NULL DEFAULT 'applicant',
    is_active BOOLEAN DEFAULT TRUE,
    email_verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL
);

-- Businesses Table
CREATE TABLE businesses (
    business_id INT PRIMARY KEY AUTO_INCREMENT,
    owner_id INT NOT NULL,
    business_name VARCHAR(200) NOT NULL,
    trade_name VARCHAR(200),
    business_type VARCHAR(100) NOT NULL,
    business_address TEXT NOT NULL,
    business_zip_code VARCHAR(20),
    business_city VARCHAR(100),
    business_province VARCHAR(100),
    business_phone VARCHAR(20),
    business_email VARCHAR(100),
    capital_investment DECIMAL(15,2),
    number_of_employees INT,
    date_established DATE,
    business_registration_number VARCHAR(100),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Permits Table
CREATE TABLE permits (
    permit_id INT PRIMARY KEY AUTO_INCREMENT,
    business_id INT NOT NULL,
    permit_number VARCHAR(50) UNIQUE NOT NULL,
    permit_category VARCHAR(100),
    permit_type ENUM('new', 'renewal', 'amendment') NOT NULL,
    application_date DATE NOT NULL,
    permit_status ENUM('pending', 'under_review', 'approved', 'rejected', 'released', 'expired') DEFAULT 'pending',
    approval_date DATE NULL,
    release_date DATE NULL,
    expiry_date DATE NULL,
    required_compliance_date DATE NULL,
    scheduled_visit_date DATE NULL,
    slot_id INT NULL,
    processing_fee DECIMAL(10,2) NOT NULL,
    penalty_fee DECIMAL(10,2) DEFAULT 0,
    total_fee DECIMAL(10,2) NOT NULL,
    requirements_complete BOOLEAN DEFAULT FALSE,
    inspected_by INT NULL,
    approved_by INT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (business_id) REFERENCES businesses(business_id) ON DELETE CASCADE,
    FOREIGN KEY (inspected_by) REFERENCES users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (approved_by) REFERENCES users(user_id) ON DELETE SET NULL
);

-- Permit Requirements Table
CREATE TABLE permit_requirements (
    requirement_id INT PRIMARY KEY AUTO_INCREMENT,
    permit_id INT NOT NULL,
    requirement_type VARCHAR(100) NOT NULL,
    file_name VARCHAR(255),
    file_path VARCHAR(500),
    is_submitted BOOLEAN DEFAULT FALSE,
    submitted_at TIMESTAMP NULL,
    verified BOOLEAN DEFAULT FALSE,
    verified_by INT NULL,
    verified_at TIMESTAMP NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (permit_id) REFERENCES permits(permit_id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES users(user_id) ON DELETE SET NULL
);

-- Payments Table
CREATE TABLE payments (
    payment_id INT PRIMARY KEY AUTO_INCREMENT,
    permit_id INT NOT NULL,
    payment_amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('cash', 'bank_transfer', 'online', 'check') NOT NULL,
    payment_status ENUM('pending', 'paid', 'failed', 'refunded') DEFAULT 'pending',
    payment_date DATE NULL,
    transaction_id VARCHAR(100),
    receipt_number VARCHAR(100),
    processed_by INT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (permit_id) REFERENCES permits(permit_id) ON DELETE CASCADE,
    FOREIGN KEY (processed_by) REFERENCES users(user_id) ON DELETE SET NULL
);

-- Renewals Table
CREATE TABLE renewals (
    renewal_id INT PRIMARY KEY AUTO_INCREMENT,
    permit_id INT NOT NULL,
    renewal_application_date DATE NOT NULL,
    renewal_status ENUM('pending', 'approved', 'rejected', 'processed') DEFAULT 'pending',
    previous_expiry_date DATE NOT NULL,
    new_expiry_date DATE NULL,
    renewal_fee DECIMAL(10,2) NOT NULL,
    penalty_fee DECIMAL(10,2) DEFAULT 0,
    total_renewal_fee DECIMAL(10,2) NOT NULL,
    processed_by INT NULL,
    processed_date DATE NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (permit_id) REFERENCES permits(permit_id) ON DELETE CASCADE,
    FOREIGN KEY (processed_by) REFERENCES users(user_id) ON DELETE SET NULL
);

-- SMS/Email Logs Table
CREATE TABLE notification_logs (
    notification_id INT PRIMARY KEY AUTO_INCREMENT,
    recipient_email VARCHAR(100),
    recipient_number VARCHAR(20),
    message TEXT NOT NULL,
    notification_type ENUM('email', 'sms') NOT NULL,
    subject VARCHAR(255),
    notification_purpose ENUM('application', 'approval', 'rejection', 'release', 'renewal', 'payment', 'reminder') NOT NULL,
    status ENUM('sent', 'failed', 'pending') DEFAULT 'pending',
    api_response TEXT,
    sent_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    related_id INT NULL,
    related_type VARCHAR(50) NULL
);

-- Visit Slots Table
CREATE TABLE visit_slots (
    slot_id INT PRIMARY KEY AUTO_INCREMENT,
    slot_date DATE NOT NULL,
    slot_time_start TIME NOT NULL,
    slot_time_end TIME NOT NULL,
    capacity INT DEFAULT 5,
    booked INT DEFAULT 0,
    is_available BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_slot (slot_date, slot_time_start)
);

-- Audit Logs Table
CREATE TABLE audit_logs (
    log_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
);

-- System Settings Table
CREATE TABLE system_settings (
    setting_id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert Default System Settings
INSERT INTO system_settings (setting_key, setting_value, description) VALUES
('processing_fee', '500.00', 'Default processing fee for new permits'),
('renewal_fee', '300.00', 'Default renewal fee'),
('penalty_rate', '0.10', 'Penalty rate for late renewals (percentage)'),
('sms_notifications', 'disabled', 'Enable/disable SMS notifications'),
('email_notifications', 'enabled', 'Enable/disable email notifications'),
('permit_validity_days', '365', 'Number of days a permit is valid'),
('renewal_reminder_days', '30', 'Days before expiry to send renewal reminder'),
('notification_method', 'email', 'Primary notification method: sms or email');

-- Insert Default Admin User
INSERT INTO users (username, email, password_hash, first_name, last_name, contact_number, role) VALUES
('admin', 'admin@businesspermit.gov', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System', 'Administrator', '09123456789', 'admin');

-- Create Indexes for Performance
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_username ON users(username);
CREATE INDEX idx_businesses_owner ON businesses(owner_id);
CREATE INDEX idx_permits_business ON permits(business_id);
CREATE INDEX idx_permits_status ON permits(permit_status);
CREATE INDEX idx_permits_number ON permits(permit_number);
CREATE INDEX idx_payments_permit ON payments(permit_id);
CREATE INDEX idx_renewals_permit ON renewals(permit_id);
CREATE INDEX idx_sms_logs_recipient ON sms_logs(recipient_number);
CREATE INDEX idx_audit_logs_user ON audit_logs(user_id);
CREATE INDEX idx_audit_logs_created ON audit_logs(created_at);
