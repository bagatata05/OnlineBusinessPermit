<?php
// Philippine cities and municipalities with ZIP codes and barangays
// This is a sample dataset - expand as needed

class AddressManager {
    private $locations = [
        // Format: 'city' => ['zip_code' => 'province']
        'Manila' => ['1000' => 'Metro Manila', '1001' => 'Metro Manila', '1002' => 'Metro Manila', '1003' => 'Metro Manila'],
        'Quezon City' => ['1100' => 'Metro Manila', '1101' => 'Metro Manila', '1103' => 'Metro Manila', '1104' => 'Metro Manila'],
        'Makati' => ['1200' => 'Metro Manila', '1201' => 'Metro Manila', '1202' => 'Metro Manila', '1203' => 'Metro Manila'],
        'Cebu' => ['6000' => 'Cebu', '6001' => 'Cebu', '6004' => 'Cebu', '6005' => 'Cebu'],
        'Davao' => ['8000' => 'Davao del Sur', '8001' => 'Davao del Sur', '8002' => 'Davao del Sur'],
        'Caloocan' => ['1400' => 'Metro Manila', '1401' => 'Metro Manila', '1402' => 'Metro Manila'],
        'Pasay' => ['1300' => 'Metro Manila', '1301' => 'Metro Manila', '1302' => 'Metro Manila'],
        'Las Piñas' => ['1740' => 'Metro Manila', '1741' => 'Metro Manila', '1742' => 'Metro Manila'],
        'Parañaque' => ['1700' => 'Metro Manila', '1701' => 'Metro Manila', '1702' => 'Metro Manila'],
        'Taguig' => ['1630' => 'Metro Manila', '1631' => 'Metro Manila', '1632' => 'Metro Manila'],
        'Pasig' => ['1600' => 'Metro Manila', '1601' => 'Metro Manila', '1602' => 'Metro Manila'],
        'Taytay' => ['1900' => 'Rizal', '1901' => 'Rizal'],
        'Zamboanga City' => ['9000' => 'Zamboanga del Sur', '9001' => 'Zamboanga del Sur', '9002' => 'Zamboanga del Sur', '9003' => 'Zamboanga del Sur', '9004' => 'Zamboanga del Sur'],
    ];
    
    private $barangays = [
        // Format: 'city' => ['barangay1', 'barangay2', ...]
        'Zamboanga City' => [
            'Barangay Tetuan',
            'Barangay Cawit',
            'Barangay Curuan',
            'Barangay Calarian',
            'Barangay Pasilao',
            'Barangay Putik',
            'Barangay Recodo',
            'Barangay Caricatura',
            'Barangay San Roque',
            'Barangay Rio Hondo',
            'Barangay Taguiam',
            'Barangay Talosa',
            'Barangay Lumbia',
            'Barangay Guiwan',
            'Barangay Tuod',
            'Barangay Magbangon',
            'Barangay Talibao',
            'Barangay Bunguran',
            'Barangay Labuhan',
            'Barangay Manalipa',
            'Barangay Mantangale',
            'Barangay Sinunuc',
            'Barangay Santa Maria',
            'Barangay Campostela',
            'Barangay Busay',
            'Barangay Mampang',
            'Barangay Sta. Catalina',
            'Barangay Luyang',
            'Barangay Kasanyangan',
            'Barangay Malandag',
        ]
    ];
    
    /**
     * Get all available cities/municipalities
     */
    public function getCities() {
        return array_keys($this->locations);
    }
    
    /**
     * Get ZIP codes for a city
     */
    public function getZipCodesByCity($city) {
        if (!isset($this->locations[$city])) {
            return [];
        }
        return array_keys($this->locations[$city]);
    }
    
    /**
     * Get province by ZIP code
     */
    public function getProvinceByZip($city, $zip_code) {
        if (!isset($this->locations[$city][$zip_code])) {
            return null;
        }
        return $this->locations[$city][$zip_code];
    }
    
    /**
     * Get address info by ZIP code
     */
    public function getAddressInfoByZip($city, $zip_code) {
        $province = $this->getProvinceByZip($city, $zip_code);
        
        if (!$province) {
            return null;
        }
        
        return [
            'city' => $city,
            'zip_code' => $zip_code,
            'province' => $province
        ];
    }
    
    /**
     * Get barangays for a city
     */
    public function getBarangaysByCity($city) {
        if (!isset($this->barangays[$city])) {
            return [];
        }
        return $this->barangays[$city];
    }
    
    /**
     * Format complete address
     */
    public function formatAddress($street, $city, $zip_code, $province) {
        return trim("{$street}, {$city} {$zip_code}, {$province}");
    }
    
    /**
     * Get address dropdown JSON for frontend
     */
    public function getCitiesJSON() {
        $cities = $this->getCities();
        sort($cities);
        return json_encode(['cities' => $cities]);
    }
    
    /**
     * Get ZIP codes JSON for a city
     */
    public function getZipCodesJSON($city) {
        $zips = $this->getZipCodesByCity($city);
        sort($zips);
        
        $zip_data = [];
        foreach ($zips as $zip) {
            $province = $this->getProvinceByZip($city, $zip);
            $zip_data[] = [
                'zip_code' => $zip,
                'province' => $province
            ];
        }
        
        return json_encode(['zip_codes' => $zip_data]);
    }
    
    /**
     * Get barangays JSON for a city
     */
    public function getBarangaysJSON($city) {
        $barangays = $this->getBarangaysByCity($city);
        return json_encode(['barangays' => $barangays]);
    }
}
?>
