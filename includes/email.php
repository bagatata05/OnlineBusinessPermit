<?php
require_once __DIR__ . '/../config.php';

class Email {
    private $conn;
    
    public function __construct() {
        $this->conn = getDBConnection();
    }
    
    /**
     * Send email notification
     */
    public function sendEmail($recipient_email, $subject, $message, $purpose = 'general', $related_id = null, $related_type = null) {
        // Validate email
        if (!filter_var($recipient_email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Invalid email address'];
        }
        
        // Check if email notifications are enabled
        $stmt = $this->conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'email_notifications'");
        $stmt->execute();
        $email_enabled = $stmt->get_result()->fetch_assoc()['setting_value'] ?? 'disabled';
        
        if ($email_enabled !== 'enabled') {
            return ['success' => false, 'message' => 'Email notifications are disabled'];
        }
        
        // Log email attempt
        $notification_id = $this->logEmail($recipient_email, $message, $subject, $purpose, $related_id, $related_type);
        
        // Send email
        $result = $this->sendViaMailer($recipient_email, $subject, $message);
        
        // Update email log with result
        $this->updateEmailStatus($notification_id, $result);
        
        return $result;
    }
    
    /**
     * Send email via mail() or SMTP
     */
    private function sendViaMailer($recipient_email, $subject, $message) {
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: " . ADMIN_EMAIL . "\r\n";
        $headers .= "Reply-To: " . ADMIN_EMAIL . "\r\n";
        
        // Create HTML email
        $html_message = $this->getEmailTemplate($subject, $message);
        
        // Attempt to send
        if (@mail($recipient_email, $subject, $html_message, $headers)) {
            return [
                'success' => true,
                'message' => 'Email sent successfully',
                'recipient' => $recipient_email
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Failed to send email',
                'recipient' => $recipient_email
            ];
        }
    }
    
    /**
     * Get HTML email template
     */
    private function getEmailTemplate($subject, $message) {
        return "<!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px; }
                .header { background: linear-gradient(135deg, #6366f1, #4f46e5); color: white; padding: 20px; border-radius: 5px 5px 0 0; }
                .header h1 { margin: 0; }
                .content { padding: 20px; }
                .footer { background: #f9fafb; padding: 15px; text-align: center; font-size: 12px; color: #999; border-radius: 0 0 5px 5px; }
                .button { background: #6366f1; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>" . SITE_NAME . "</h1>
                </div>
                <div class='content'>
                    <h2>{$subject}</h2>
                    <p>{$message}</p>
                </div>
                <div class='footer'>
                    <p>© " . date('Y') . " " . SITE_NAME . ". All rights reserved.</p>
                    <p><a href='" . SITE_URL . "'>Visit our website</a></p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Log email
     */
    private function logEmail($recipient_email, $message, $subject, $purpose, $related_id, $related_type) {
        $stmt = $this->conn->prepare("
            INSERT INTO notification_logs 
            (recipient_email, message, notification_type, subject, notification_purpose, related_id, related_type, status) 
            VALUES (?, ?, 'email', ?, ?, ?, ?, 'pending')
        ");
        $stmt->bind_param("ssssis", $recipient_email, $message, $subject, $purpose, $related_id, $related_type);
        $stmt->execute();
        $notification_id = $this->conn->insert_id;
        $stmt->close();
        
        return $notification_id;
    }
    
    /**
     * Update email status
     */
    private function updateEmailStatus($notification_id, $result) {
        $status = $result['success'] ? 'sent' : 'failed';
        $response = json_encode($result);
        
        $stmt = $this->conn->prepare("
            UPDATE notification_logs 
            SET status = ?, api_response = ?, sent_at = CURRENT_TIMESTAMP 
            WHERE notification_id = ?
        ");
        $stmt->bind_param("ssi", $status, $response, $notification_id);
        $stmt->execute();
        $stmt->close();
    }
    
    /**
     * Send application submitted email
     */
    public function sendApplicationSubmitted($recipient_email, $permit_number, $business_name) {
        $subject = "Permit Application Received - " . $permit_number;
        $message = "Dear Applicant,\n\n" .
                  "Your permit application for {$business_name} has been received successfully.\n\n" .
                  "Permit Number: {$permit_number}\n\n" .
                  "Please keep this number for your records and use it to track your application status.\n\n" .
                  "You can track your application at: " . SITE_URL . "/index.php?page=tracking\n\n" .
                  "Thank you for using our service.";
        
        return $this->sendEmail($recipient_email, $subject, $message, 'application');
    }
    
    /**
     * Send approval email
     */
    public function sendApprovalEmail($recipient_email, $permit_number, $business_name) {
        $subject = "Permit Application Approved - " . $permit_number;
        $message = "Dear Applicant,\n\n" .
                  "Your permit application for {$business_name} (Permit: {$permit_number}) has been APPROVED.\n\n" .
                  "Please proceed to the business permit office to complete payment and collect your permit.\n\n" .
                  "Track your application: " . SITE_URL . "/index.php?page=tracking\n\n" .
                  "Thank you.";
        
        return $this->sendEmail($recipient_email, $subject, $message, 'approval');
    }
    
    /**
     * Send rejection email
     */
    public function sendRejectionEmail($recipient_email, $permit_number, $business_name, $reason = '') {
        $subject = "Permit Application - Status Update - " . $permit_number;
        $message = "Dear Applicant,\n\n" .
                  "We regret to inform you that your permit application for {$business_name} (Permit: {$permit_number}) requires revision.\n\n" .
                  ($reason ? "Reason: {$reason}\n\n" : "") .
                  "Please contact the business permit office for more information.\n\n" .
                  "Track your application: " . SITE_URL . "/index.php?page=tracking\n\n" .
                  "Thank you.";
        
        return $this->sendEmail($recipient_email, $subject, $message, 'rejection');
    }
    
    /**
     * Send release email
     */
    public function sendReleaseEmail($recipient_email, $permit_number, $business_name) {
        $subject = "Your Permit is Ready - " . $permit_number;
        $message = "Dear Applicant,\n\n" .
                  "Your business permit for {$business_name} (Permit: {$permit_number}) is now ready for release.\n\n" .
                  "Please visit the business permit office during business hours to claim your permit.\n\n" .
                  "Track your application: " . SITE_URL . "/index.php?page=tracking\n\n" .
                  "Thank you.";
        
        return $this->sendEmail($recipient_email, $subject, $message, 'release');
    }
    
    /**
     * Send renewal reminder email
     */
    public function sendRenewalReminderEmail($recipient_email, $permit_number, $expiry_date) {
        $subject = "Permit Renewal Reminder - " . $permit_number;
        $message = "Dear Permit Holder,\n\n" .
                  "This is a reminder that your business permit (Permit: {$permit_number}) will expire on " . date('F d, Y', strtotime($expiry_date)) . ".\n\n" .
                  "Please apply for renewal before the expiry date to avoid penalties.\n\n" .
                  "Renew your permit: " . SITE_URL . "/index.php?page=renewals\n\n" .
                  "Thank you.";
        
        return $this->sendEmail($recipient_email, $subject, $message, 'reminder');
    }
    
    /**
     * Get email statistics
     */
    public function getEmailStats() {
        $stmt = $this->conn->prepare("
            SELECT 
                notification_purpose as email_type,
                COUNT(*) as total_sent,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as successful
            FROM notification_logs
            WHERE notification_type = 'email'
            GROUP BY notification_purpose
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        $stats = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        return $stats;
    }
}
?>
