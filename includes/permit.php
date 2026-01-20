<?php
require_once __DIR__ . '/../config.php';

class Permit {
    private $conn;
    
    public function __construct() {
        $this->conn = getDBConnection();
    }
    
    /**
     * Create new permit application
     */
    public function createApplication($business_id, $permit_type, $user_id) {
        // Generate permit number
        $permit_number = generatePermitNumber();
        
        // Get processing fee
        $fee_key = $permit_type === 'renewal' ? 'renewal_fee' : 'processing_fee';
        $stmt = $this->conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
        $stmt->bind_param("s", $fee_key);
        $stmt->execute();
        $fee_result = $stmt->get_result()->fetch_assoc();
        $processing_fee = floatval($fee_result['setting_value'] ?? 500);
        
        // Insert permit
        $stmt = $this->conn->prepare("
            INSERT INTO permits (
                business_id, permit_number, permit_type, application_date, 
                permit_status, processing_fee, total_fee
            ) VALUES (?, ?, ?, CURDATE(), 'pending', ?, ?)
        ");
        
        $total_fee = $processing_fee;
        $stmt->bind_param("issdd", $business_id, $permit_number, $permit_type, $processing_fee, $total_fee);
        
        if ($stmt->execute()) {
            $permit_id = $this->conn->insert_id;
            
            // Add default requirements
            $this->addDefaultRequirements($permit_id, $permit_type);
            
            // Get business owner info for SMS
            $owner_stmt = $this->conn->prepare("
                SELECT u.contact_number, u.first_name, u.last_name, b.business_name 
                FROM businesses b
                JOIN users u ON b.owner_id = u.user_id
                WHERE b.business_id = ?
            ");
            $owner_stmt->bind_param("i", $business_id);
            $owner_stmt->execute();
            $owner = $owner_stmt->get_result()->fetch_assoc();
            $owner_stmt->close();
            
            // Log activity
            logActivity($user_id, 'permit_application', "New permit application: {$permit_number}");
            
            return [
                'success' => true, 
                'permit_id' => $permit_id,
                'permit_number' => $permit_number
            ];
        } else {
            return ['success' => false, 'message' => 'Failed to create permit application'];
        }
    }
    
    /**
     * Add default requirements for permit
     */
    private function addDefaultRequirements($permit_id, $permit_type) {
        $requirements = $this->getDefaultRequirements($permit_type);
        
        foreach ($requirements as $requirement) {
            $stmt = $this->conn->prepare("
                INSERT INTO permit_requirements (permit_id, requirement_type) VALUES (?, ?)
            ");
            $stmt->bind_param("is", $permit_id, $requirement);
            $stmt->execute();
            $stmt->close();
        }
    }
    
    /**
     * Get default requirements based on permit type
     */
    private function getDefaultRequirements($permit_type) {
        switch ($permit_type) {
            case 'renewal':
                return [
                    'Previous Permit',
                    'Updated Barangay Clearance',
                    'Community Tax Certificate',
                    'Picture of Business Establishment',
                    'Updated Business Registration'
                ];
            case 'amendment':
                return [
                    'Original Permit',
                    'Proof of Amendment',
                    'Updated Business Registration',
                    'Barangay Clearance',
                    'Affidavit of Amendment'
                ];
            default: // new
                return [
                    'Business Registration Certificate',
                    'Barangay Clearance',
                    'Zoning Clearance',
                    'Sanitary Permit',
                    'Fire Safety Inspection Certificate',
                    'Community Tax Certificate',
                    'Picture of Business Establishment'
                ];
        }
    }
    
    /**
     * Update permit status
     */
    public function updateStatus($permit_id, $new_status, $user_id, $notes = '') {
        // Get current permit info
        $stmt = $this->conn->prepare("
            SELECT p.*, b.business_name, u.contact_number 
            FROM permits p
            JOIN businesses b ON p.business_id = b.business_id
            JOIN users u ON b.owner_id = u.user_id
            WHERE p.permit_id = ?
        ");
        $stmt->bind_param("i", $permit_id);
        $stmt->execute();
        $permit = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$permit) {
            return ['success' => false, 'message' => 'Permit not found'];
        }
        
        // Update permit status
        $update_fields = "permit_status = ?, updated_at = NOW()";
        $params = [$new_status];
        $types = 's';
        
        // Set additional fields based on status
        switch ($new_status) {
            case 'under_review':
                $update_fields .= ", inspected_by = ?";
                $params[] = $user_id;
                $types .= 'i';
                break;
            case 'approved':
                $update_fields .= ", approved_by = ?, approval_date = CURDATE()";
                $params[] = $user_id;
                $types .= 'i';
                
                // Set expiry date (1 year from approval)
                $expiry_date = date('Y-m-d', strtotime('+1 year'));
                $update_fields .= ", expiry_date = ?";
                $params[] = $expiry_date;
                $types .= 's';
                break;
            case 'released':
                $update_fields .= ", release_date = CURDATE()";
                break;
        }
        
        if ($notes) {
            $update_fields .= ", notes = ?";
            $params[] = $notes;
            $types .= 's';
        }
        
        $params[] = $permit_id;
        $types .= 'i';
        
        $stmt = $this->conn->prepare("
            UPDATE permits SET {$update_fields} WHERE permit_id = ?
        ");
        $stmt->bind_param($types, ...$params);
        
        if ($stmt->execute()) {
            // Send SMS notification based on new status
            $this->sendStatusNotification($permit, $new_status, $notes);
            
            // Log activity
            logActivity($user_id, 'status_update', "Permit {$permit['permit_number']} status updated to: {$new_status}");
            
            return ['success' => true, 'message' => 'Status updated successfully'];
        } else {
            return ['success' => false, 'message' => 'Failed to update status'];
        }
    }
    
    /**
     * Send notification for status change (handled by email system)
     */
    private function sendStatusNotification($permit, $new_status, $notes = '') {
        // Notifications are now handled by the email system
        // This method is kept for compatibility but does not send SMS
    }
    
    /**
     * Get permits with filters and pagination
     */
    public function getPermits($page = 1, $limit = ITEMS_PER_PAGE, $filters = [], $user_id = null, $user_role = 'applicant') {
        $offset = ($page - 1) * $limit;
        
        $sql = "
            SELECT p.*, 
                   b.business_name, b.business_type, b.business_address,
                   u.first_name, u.last_name, u.contact_number,
                   ins.first_name as inspector_name, ins.last_name as inspector_last,
                   app.first_name as approver_name, app.last_name as approver_last
            FROM permits p
            JOIN businesses b ON p.business_id = b.business_id
            JOIN users u ON b.owner_id = u.user_id
            LEFT JOIN users ins ON p.inspected_by = ins.user_id
            LEFT JOIN users app ON p.approved_by = app.user_id
            WHERE 1=1
        ";
        
        $params = [];
        $types = '';
        
        // Apply role-based filtering
        if ($user_role === 'applicant') {
            $sql .= " AND b.owner_id = ?";
            $params[] = $user_id;
            $types .= 'i';
        }
        
        // Apply filters
        if (!empty($filters['status'])) {
            $sql .= " AND p.permit_status = ?";
            $params[] = $filters['status'];
            $types .= 's';
        }
        
        if (!empty($filters['type'])) {
            $sql .= " AND p.permit_type = ?";
            $params[] = $filters['type'];
            $types .= 's';
        }
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND p.application_date >= ?";
            $params[] = $filters['date_from'];
            $types .= 's';
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND p.application_date <= ?";
            $params[] = $filters['date_to'];
            $types .= 's';
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (p.permit_number LIKE ? OR b.business_name LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
            $search_term = '%' . $filters['search'] . '%';
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
            $types .= 'ssss';
        }
        
        // Get total count
        $count_sql = str_replace("SELECT p.*, b.business_name, b.business_type, b.business_address, u.first_name, u.last_name, u.contact_number, ins.first_name as inspector_name, ins.last_name as inspector_last, app.first_name as approver_name, app.last_name as approver_last", "SELECT COUNT(*) as total", $sql);
        $count_sql = str_replace("ORDER BY p.created_at DESC", "", $count_sql);
        
        $count_stmt = $this->conn->prepare($count_sql);
        if ($count_stmt === false) {
            error_log("Failed to prepare count query: " . $this->conn->error);
            $total = 0;
        } else {
            if (!empty($params)) {
                $count_stmt->bind_param($types, ...$params);
            }
            if ($count_stmt->execute()) {
                $result = $count_stmt->get_result()->fetch_assoc();
                $total = isset($result['total']) ? (int)$result['total'] : 0;
            } else {
                error_log("Failed to execute count query: " . $count_stmt->error);
                $total = 0;
            }
            $count_stmt->close();
        }
        
        // Get data
        $sql .= " ORDER BY p.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';
        
        $stmt = $this->conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $permits = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        return [
            'permits' => $permits,
            'total' => $total,
            'pages' => ceil($total / $limit),
            'current_page' => $page
        ];
    }
    
    /**
     * Get permit details by ID
     */
    public function getPermitById($permit_id, $user_id = null, $user_role = 'applicant') {
        $sql = "
            SELECT p.*, 
                   b.business_name, b.business_type, b.business_address,
                   u.first_name, u.last_name, u.contact_number, u.email,
                   ins.first_name as inspector_name, ins.last_name as inspector_last,
                   app.first_name as approver_name, app.last_name as approver_last
            FROM permits p
            JOIN businesses b ON p.business_id = b.business_id
            JOIN users u ON b.owner_id = u.user_id
            LEFT JOIN users ins ON p.inspected_by = ins.user_id
            LEFT JOIN users app ON p.approved_by = app.user_id
            WHERE p.permit_id = ?
        ";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $permit_id);
        $stmt->execute();
        $permit = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$permit) {
            return null;
        }
        
        // Check permissions
        if ($user_role === 'applicant' && $permit['owner_id'] != $user_id) {
            return null;
        }
        
        // Get requirements
        $req_stmt = $this->conn->prepare("
            SELECT * FROM permit_requirements 
            WHERE permit_id = ? 
            ORDER BY requirement_type
        ");
        $req_stmt->bind_param("i", $permit_id);
        $req_stmt->execute();
        $permit['requirements'] = $req_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $req_stmt->close();
        
        // Get payments
        $pay_stmt = $this->conn->prepare("
            SELECT * FROM payments 
            WHERE permit_id = ? 
            ORDER BY created_at DESC
        ");
        $pay_stmt->bind_param("i", $permit_id);
        $pay_stmt->execute();
        $permit['payments'] = $pay_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $pay_stmt->close();
        
        return $permit;
    }
    
    /**
     * Update requirement status
     */
    public function updateRequirement($requirement_id, $is_submitted, $is_verified, $verified_by = null, $notes = '') {
        $stmt = $this->conn->prepare("
            UPDATE permit_requirements 
            SET is_submitted = ?, verified = ?, verified_by = ?, notes = ?, 
                submitted_at = IF(?, NOW(), submitted_at),
                verified_at = IF(?, NOW(), verified_at)
            WHERE requirement_id = ?
        ");
        
        $submitted_at = $is_submitted ? 1 : 0;
        $verified_at = $is_verified ? 1 : 0;
        
        $stmt->bind_param("iiisssi", $is_submitted, $is_verified, $verified_by, $notes, $submitted_at, $verified_at, $requirement_id);
        
        if ($stmt->execute()) {
            // Check if all requirements are complete
            $this->checkRequirementsComplete($requirement_id);
            
            return ['success' => true, 'message' => 'Requirement updated successfully'];
        } else {
            return ['success' => false, 'message' => 'Failed to update requirement'];
        }
    }
    
    /**
     * Check if all requirements for a permit are complete
     */
    private function checkRequirementsComplete($requirement_id) {
        // Get permit_id for this requirement
        $stmt = $this->conn->prepare("SELECT permit_id FROM permit_requirements WHERE requirement_id = ?");
        $stmt->bind_param("i", $requirement_id);
        $stmt->execute();
        $permit_id = $stmt->get_result()->fetch_assoc()['permit_id'];
        $stmt->close();
        
        // Check if all requirements are submitted and verified
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as total,
                   SUM(CASE WHEN is_submitted = 1 THEN 1 ELSE 0 END) as submitted,
                   SUM(CASE WHEN verified = 1 THEN 1 ELSE 0 END) as verified
            FROM permit_requirements 
            WHERE permit_id = ?
        ");
        $stmt->bind_param("i", $permit_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        $all_complete = ($result['total'] > 0 && $result['submitted'] == $result['total'] && $result['verified'] == $result['total']);
        
        // Update permit requirements_complete flag
        $update_stmt = $this->conn->prepare("
            UPDATE permits SET requirements_complete = ? WHERE permit_id = ?
        ");
        $update_stmt->bind_param("ii", $all_complete, $permit_id);
        $update_stmt->execute();
        $update_stmt->close();
    }
    
    /**
     * Get permit statistics
     */
    public function getStats($date_from = null, $date_to = null) {
        $sql = "
            SELECT 
                COUNT(*) as total_applications,
                COALESCE(SUM(CASE WHEN permit_status = 'pending' THEN 1 ELSE 0 END), 0) as pending,
                COALESCE(SUM(CASE WHEN permit_status = 'under_review' THEN 1 ELSE 0 END), 0) as under_review,
                COALESCE(SUM(CASE WHEN permit_status = 'approved' THEN 1 ELSE 0 END), 0) as approved,
                COALESCE(SUM(CASE WHEN permit_status = 'rejected' THEN 1 ELSE 0 END), 0) as rejected,
                COALESCE(SUM(CASE WHEN permit_status = 'released' THEN 1 ELSE 0 END), 0) as released,
                COALESCE(SUM(CASE WHEN permit_type = 'new' THEN 1 ELSE 0 END), 0) as new_applications,
                COALESCE(SUM(CASE WHEN permit_type = 'renewal' THEN 1 ELSE 0 END), 0) as renewals,
                COALESCE(SUM(CASE WHEN permit_type = 'amendment' THEN 1 ELSE 0 END), 0) as amendments,
                COALESCE(SUM(total_fee), 0) as total_revenue
            FROM permits
            WHERE 1=1
        ";
        
        $params = [];
        $types = '';
        
        if ($date_from) {
            $sql .= " AND application_date >= ?";
            $params[] = $date_from;
            $types .= 's';
        }
        
        if ($date_to) {
            $sql .= " AND application_date <= ?";
            $params[] = $date_to;
            $types .= 's';
        }
        
        $stmt = $this->conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $stats = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        return $stats;
    }
    
    /**
     * Get permits expiring soon
     */
    public function getExpiringPermits($days = 30) {
        $expiry_date = date('Y-m-d', strtotime("+{$days} days"));
        
        $stmt = $this->conn->prepare("
            SELECT p.*, b.business_name, u.contact_number, u.email
            FROM permits p
            JOIN businesses b ON p.business_id = b.business_id
            JOIN users u ON b.owner_id = u.user_id
            WHERE p.permit_status = 'released' 
            AND p.expiry_date <= ? 
            AND p.expiry_date >= CURDATE()
            ORDER BY p.expiry_date ASC
        ");
        $stmt->bind_param("s", $expiry_date);
        $stmt->execute();
        $permits = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        return $permits;
    }
    
    /**
     * Send renewal reminders
     */
    public function sendRenewalReminders() {
        $expiring_permits = $this->getExpiringPermits(30);
        $sent_count = 0;
        
        foreach ($expiring_permits as $permit) {
            // Email notifications are handled by the email system
            $sent_count++;
        }
        
        return ['sent' => $sent_count, 'total' => count($expiring_permits)];
    }
}
?>
