<?php
// Test API connection
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'config.php';

try {
    // Test database connection
    $conn = getDBConnection();
    
    // Test Razorpay configuration
    $razorpayConfigured = defined('RAZORPAY_KEY_ID') && !empty(RAZORPAY_KEY_ID);
    
    echo json_encode([
        'success' => true,
        'message' => 'API is working correctly',
        'database' => DB_TYPE,
        'razorpay_configured' => $razorpayConfigured,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => 'CONNECTION_FAILED'
    ]);
}
?>
