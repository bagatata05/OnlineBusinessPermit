<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permit.php';

// Check authentication and admin role
$auth = new Auth();
$auth->requireRole('admin');

$permit = new Permit();
$conn = getDBConnection();

// Get all permits with full details
$stmt = $conn->prepare("
    SELECT 
        p.permit_number,
        p.permit_type,
        p.application_date,
        p.permit_status,
        p.approval_date,
        p.release_date,
        p.expiry_date,
        p.processing_fee,
        p.total_fee,
        b.business_name,
        b.business_type,
        b.business_address,
        b.capital_investment,
        b.number_of_employees,
        u.first_name,
        u.last_name,
        u.email,
        u.contact_number,
        ins.first_name as inspector_first,
        ins.last_name as inspector_last,
        app.first_name as approver_first,
        app.last_name as approver_last
    FROM permits p
    JOIN businesses b ON p.business_id = b.business_id
    JOIN users u ON b.owner_id = u.user_id
    LEFT JOIN users ins ON p.inspected_by = ins.user_id
    LEFT JOIN users app ON p.approved_by = app.user_id
    ORDER BY p.created_at DESC
");
$stmt->execute();
$permits = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="business_permits_' . date('Y-m-d') . '.csv"');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: 0');

// Open output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for proper Excel display
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// CSV headers
$headers = [
    'Permit Number',
    'Permit Type',
    'Application Date',
    'Status',
    'Approval Date',
    'Release Date',
    'Expiry Date',
    'Processing Fee',
    'Total Fee',
    'Business Name',
    'Business Type',
    'Business Address',
    'Capital Investment',
    'Number of Employees',
    'Owner First Name',
    'Owner Last Name',
    'Owner Email',
    'Owner Contact Number',
    'Inspector',
    'Approver'
];

fputcsv($output, $headers);

// CSV data
foreach ($permits as $permit) {
    $row = [
        $permit['permit_number'],
        ucfirst($permit['permit_type']),
        $permit['application_date'],
        ucfirst(str_replace('_', ' ', $permit['permit_status'])),
        $permit['approval_date'] ?: '',
        $permit['release_date'] ?: '',
        $permit['expiry_date'] ?: '',
        $permit['processing_fee'],
        $permit['total_fee'],
        $permit['business_name'],
        $permit['business_type'],
        $permit['business_address'],
        $permit['capital_investment'],
        $permit['number_of_employees'],
        $permit['first_name'],
        $permit['last_name'],
        $permit['email'],
        $permit['contact_number'],
        $permit['inspector_first'] && $permit['inspector_last'] ? 
            $permit['inspector_first'] . ' ' . $permit['inspector_last'] : '',
        $permit['approver_first'] && $permit['approver_last'] ? 
            $permit['approver_first'] . ' ' . $permit['approver_last'] : ''
    ];
    
    fputcsv($output, $row);
}

// Close output stream
fclose($output);

// Log activity
logActivity($_SESSION['user_id'], 'data_export', 'Exported permits data to CSV');

$conn->close();
exit();
?>
