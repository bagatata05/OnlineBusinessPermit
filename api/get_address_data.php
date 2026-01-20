<?php
require_once __DIR__ . '/../includes/address.php';

header('Content-Type: application/json');

$addressManager = new AddressManager();

$city = $_GET['city'] ?? '';
$zip = $_GET['zip'] ?? '';
$get_barangays = $_GET['barangays'] ?? false;

if (!$city) {
    echo json_encode(['error' => 'City parameter is required']);
    exit();
}

if ($get_barangays) {
    // Get barangays for the city
    $barangays = $addressManager->getBarangaysByCity($city);
    echo json_encode(['barangays' => $barangays]);
} elseif ($zip) {
    // Get province for specific ZIP code
    $result = $addressManager->getAddressInfoByZip($city, $zip);
    echo json_encode($result ?: ['error' => 'ZIP code not found']);
} else {
    // Get all ZIP codes for city
    $zips = $addressManager->getZipCodesByCity($city);
    $zip_data = [];
    foreach ($zips as $z) {
        $province = $addressManager->getProvinceByZip($city, $z);
        $zip_data[] = [
            'zip_code' => $z,
            'province' => $province
        ];
    }
    echo json_encode(['zip_codes' => $zip_data]);
}
?>

