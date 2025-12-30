<?php
// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Access-Control-Max-Age: 3600');
    http_response_code(200);
    exit();
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Max-Age: 3600');

// Include configuration
require_once 'config.php';

try {
    // Test database connection
    $conn = getDBConnection();
    
    // Test Razorpay constants
    $razorpayTest = [
        'key_id_exists' => defined('RAZORPAY_KEY_ID') && !empty(RAZORPAY_KEY_ID),
        'key_secret_exists' => defined('RAZORPAY_KEY_SECRET') && !empty(RAZORPAY_KEY_SECRET),
        'key_id_value' => defined('RAZORPAY_KEY_ID') ? substr(RAZORPAY_KEY_ID, 0, 10) . '...' : 'NOT_SET'
    ];
    
    // Test vendor autoload
    $vendorTest = [
        'autoload_exists' => file_exists(__DIR__ . '/vendor/autoload.php'),
        'razorpay_class_exists' => class_exists('Razorpay\\Api\\Api')
    ];
    
    // Database test
    $dbTest = [
        'connection' => 'SUCCESS',
        'database_name' => DB_NAME,
        'host' => DB_HOST
    ];
    
    // Check if tables exist
    $tables = ['payment_orders', 'payments', 'donations', 'volunteers', 'volunteer_applications'];
    $tableStatus = [];
    
    foreach ($tables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        $tableStatus[$table] = $result->num_rows > 0 ? 'EXISTS' : 'NOT_EXISTS';
    }
    
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'message' => 'PHP Backend is working!',
        'timestamp' => date('Y-m-d H:i:s'),
        'tests' => [
            'database' => $dbTest,
            'razorpay' => $razorpayTest,
            'vendor' => $vendorTest,
            'tables' => $tableStatus
        ],
        'config' => [
            'volunteer_fee' => VOLUNTEER_REGISTRATION_FEE,
            'min_donation' => MIN_DONATION_AMOUNT,
            'max_donation' => MAX_DONATION_AMOUNT
        ]
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Backend test failed',
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
}
?>
