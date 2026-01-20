-- ============================================================================
-- Online Business Permit System - Database Migration Script
-- Version: 2.0
-- Date: December 6, 2025
-- ============================================================================
-- 
-- This script contains all the database changes required to implement
-- the new features in the system. Run this AFTER backing up your database.
--
-- IMPORTANT: Make a backup of your database before running these migrations!
--
-- ============================================================================

-- ============================================================================
-- 1. BUSINESSES TABLE - Add Address Components
-- ============================================================================
-- Add ZIP code field for better address management
ALTER TABLE businesses 
ADD COLUMN business_zip_code VARCHAR(20) AFTER business_address;

-- Add city field for dropdown selection
ALTER TABLE businesses 
ADD COLUMN business_city VARCHAR(100) AFTER business_zip_code;

-- Add province field for auto-population
ALTER TABLE businesses 
ADD COLUMN business_province VARCHAR(100) AFTER business_city;

-- ============================================================================
-- 2. PERMITS TABLE - Add New Fields for Enhanced Features
-- ============================================================================
-- Add permit category field for better organization
ALTER TABLE permits 
ADD COLUMN permit_category VARCHAR(100) AFTER permit_type;

-- Add required compliance date field
ALTER TABLE permits 
ADD COLUMN required_compliance_date DATE NULL AFTER expiry_date;

-- Add scheduled visit date field for admin inspection scheduling
ALTER TABLE permits 
ADD COLUMN scheduled_visit_date DATE NULL AFTER required_compliance_date;

-- Add slot ID foreign key reference
ALTER TABLE permits 
ADD COLUMN slot_id INT NULL AFTER scheduled_visit_date;

-- ============================================================================
-- 3. CREATE VISIT_SLOTS TABLE - For Admin Inspection Scheduling
-- ============================================================================
CREATE TABLE IF NOT EXISTS visit_slots (
    slot_id INT PRIMARY KEY AUTO_INCREMENT,
    slot_date DATE NOT NULL,
    slot_time_start TIME NOT NULL,
    slot_time_end TIME NOT NULL,
    capacity INT DEFAULT 5 COMMENT 'Maximum permits that can be inspected in this slot',
    booked INT DEFAULT 0 COMMENT 'Number of permits already booked for this slot',
    is_available BOOLEAN DEFAULT TRUE COMMENT 'Whether slot is open for booking',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_slot (slot_date, slot_time_start) COMMENT 'Prevent duplicate time slots on same date',
    KEY idx_slot_date (slot_date),
    KEY idx_slot_availability (is_available)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Manages available inspection visit time slots for permits';

-- ============================================================================
-- 4. CREATE/UPDATE NOTIFICATION_LOGS TABLE - Replace SMS Logs
-- ============================================================================
-- Drop old sms_logs table if it exists (optional - keep for historical data)
-- DROP TABLE IF EXISTS sms_logs;

-- Create new notification_logs table for both SMS and Email
CREATE TABLE IF NOT EXISTS notification_logs (
    notification_id INT PRIMARY KEY AUTO_INCREMENT,
    recipient_email VARCHAR(100) COMMENT 'Email address for email notifications',
    recipient_number VARCHAR(20) COMMENT 'Phone number for SMS notifications',
    message TEXT NOT NULL COMMENT 'Notification message content',
    notification_type ENUM('email', 'sms') NOT NULL COMMENT 'Type of notification sent',
    subject VARCHAR(255) COMMENT 'Email subject line (for email notifications)',
    notification_purpose ENUM('application', 'approval', 'rejection', 'release', 'renewal', 'payment', 'reminder') NOT NULL COMMENT 'Purpose of notification',
    status ENUM('sent', 'failed', 'pending') DEFAULT 'pending' COMMENT 'Delivery status',
    api_response TEXT COMMENT 'Response from mail/SMS API',
    sent_at TIMESTAMP NULL COMMENT 'When notification was successfully sent',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'When notification was created',
    related_id INT NULL COMMENT 'Related permit, renewal, or payment ID',
    related_type VARCHAR(50) NULL COMMENT 'Type of related record: permit, renewal, payment',
    KEY idx_notification_type (notification_type),
    KEY idx_notification_status (status),
    KEY idx_created_at (created_at),
    KEY idx_related_id (related_id),
    KEY idx_recipient_email (recipient_email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Logs for all email and SMS notifications sent';

-- ============================================================================
-- 5. SYSTEM_SETTINGS TABLE - Add New Configuration Options
-- ============================================================================
-- Add new setting for notification method preference
INSERT INTO system_settings (setting_key, setting_value, description) 
VALUES ('notification_method', 'email', 'Primary notification method: email or sms')
ON DUPLICATE KEY UPDATE setting_value = 'email';

-- Update default SMS setting to disabled
UPDATE system_settings 
SET setting_value = 'disabled' 
WHERE setting_key = 'sms_notifications';

-- Ensure email notifications are enabled
INSERT INTO system_settings (setting_key, setting_value, description) 
VALUES ('email_notifications', 'enabled', 'Enable/disable email notifications')
ON DUPLICATE KEY UPDATE setting_value = 'enabled';

-- ============================================================================
-- 6. CREATE INDEXES FOR PERFORMANCE
-- ============================================================================
-- Indexes on businesses for faster queries
CREATE INDEX idx_businesses_zip_code ON businesses(business_zip_code);
CREATE INDEX idx_businesses_city ON businesses(business_city);

-- Indexes on permits for new fields
CREATE INDEX idx_permits_category ON permits(permit_category);
CREATE INDEX idx_permits_compliance_date ON permits(required_compliance_date);
CREATE INDEX idx_permits_scheduled_visit ON permits(scheduled_visit_date);
CREATE INDEX idx_permits_slot ON permits(slot_id);

-- ============================================================================
-- 7. VERIFICATION QUERIES - Run these to verify installation
-- ============================================================================
-- Uncomment these to verify changes after running migrations:

-- Check if all new columns exist:
-- SELECT COLUMN_NAME, DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS 
-- WHERE TABLE_NAME = 'businesses' AND COLUMN_NAME IN ('business_zip_code', 'business_city', 'business_province');

-- Check if visit_slots table created:
-- SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'visit_slots';

-- Check if notification_logs table created:
-- SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'notification_logs';

-- Check permits table new columns:
-- SELECT COLUMN_NAME, DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS 
-- WHERE TABLE_NAME = 'permits' AND COLUMN_NAME IN ('permit_category', 'required_compliance_date', 'scheduled_visit_date', 'slot_id');

-- Check system settings:
-- SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('notification_method', 'sms_notifications', 'email_notifications');

-- ============================================================================
-- 8. DATA MIGRATION (Optional - for existing records)
-- ============================================================================
-- If you want to migrate existing SMS logs to notification_logs:
-- INSERT INTO notification_logs (recipient_number, message, notification_type, notification_purpose, status, sent_at, created_at, related_id, related_type)
-- SELECT recipient_number, message, 'sms' as notification_type, sms_type, status, sent_at, created_at, related_id, related_type 
-- FROM sms_logs;

-- ============================================================================
-- 9. SAMPLE DATA FOR TESTING (Optional)
-- ============================================================================
-- Create a few sample visit slots for testing:
INSERT INTO visit_slots (slot_date, slot_time_start, slot_time_end, capacity) VALUES
('2025-12-08', '08:00:00', '09:00:00', 5),
('2025-12-08', '09:00:00', '10:00:00', 5),
('2025-12-08', '10:00:00', '11:00:00', 5),
('2025-12-08', '13:00:00', '14:00:00', 5),
('2025-12-08', '14:00:00', '15:00:00', 5),
('2025-12-09', '08:00:00', '09:00:00', 5),
('2025-12-09', '09:00:00', '10:00:00', 5),
('2025-12-09', '10:00:00', '11:00:00', 5);

-- ============================================================================
-- 10. COMPLETION VERIFICATION
-- ============================================================================
-- Run these queries to verify all changes completed successfully:

-- Count new columns in businesses:
-- SELECT COUNT(*) as new_columns FROM INFORMATION_SCHEMA.COLUMNS 
-- WHERE TABLE_NAME = 'businesses' AND COLUMN_NAME IN ('business_zip_code', 'business_city', 'business_province');
-- Expected result: 3

-- Count new columns in permits:
-- SELECT COUNT(*) as new_columns FROM INFORMATION_SCHEMA.COLUMNS 
-- WHERE TABLE_NAME = 'permits' AND COLUMN_NAME IN ('permit_category', 'required_compliance_date', 'scheduled_visit_date', 'slot_id');
-- Expected result: 4

-- Check visit_slots count:
-- SELECT COUNT(*) as total_slots FROM visit_slots;

-- Check notification_logs table exists:
-- SHOW TABLES LIKE 'notification_logs';

-- ============================================================================
-- 11. ROLLBACK SCRIPT (If needed - uncomment to revert changes)
-- ============================================================================
-- CAUTION: Only run if you need to rollback these changes!
-- Uncomment below to revert:

/*
-- Revert changes to permits table
ALTER TABLE permits DROP COLUMN IF EXISTS permit_category;
ALTER TABLE permits DROP COLUMN IF EXISTS required_compliance_date;
ALTER TABLE permits DROP COLUMN IF EXISTS scheduled_visit_date;
ALTER TABLE permits DROP COLUMN IF EXISTS slot_id;

-- Revert changes to businesses table
ALTER TABLE businesses DROP COLUMN IF EXISTS business_zip_code;
ALTER TABLE businesses DROP COLUMN IF EXISTS business_city;
ALTER TABLE businesses DROP COLUMN IF EXISTS business_province;

-- Drop new tables
DROP TABLE IF EXISTS visit_slots;
DROP TABLE IF EXISTS notification_logs;

-- Update system settings back to defaults
UPDATE system_settings SET setting_value = 'enabled' WHERE setting_key = 'sms_notifications';
DELETE FROM system_settings WHERE setting_key IN ('notification_method');
*/

-- ============================================================================
-- END OF MIGRATION SCRIPT
-- ============================================================================
-- 
-- Migration completed successfully!
-- 
-- All database changes have been implemented.
-- The system is now ready for the new features:
-- 
-- ✅ Address management with ZIP codes
-- ✅ Permit categorization
-- ✅ Compliance date tracking
-- ✅ Visit slot scheduling
-- ✅ Email notifications
-- ✅ Enhanced admin controls
-- 
-- For questions or issues, refer to IMPLEMENTATION_SUMMARY.md
-- ============================================================================
