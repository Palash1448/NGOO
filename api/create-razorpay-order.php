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

// Include configuration and autoload
require_once 'config.php';
require_once 'vendor/autoload.php';
use Razorpay\Api\Api;

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    // Validate required fields
    $requiredFields = ['amount', 'currency', 'receipt', 'payment_type'];
    foreach ($requiredFields as $field) {
        if (!isset($input[$field]) || empty($input[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }
    
    $amount = (int)$input['amount']; // Amount in paise
    $currency = $input['currency'];
    $receipt = $input['receipt'];
    $paymentType = $input['payment_type'];
    $userDetails = $input['user_details'] ?? [];
    
    // Validate amount
    if ($amount < 100) { // Minimum 1 rupee
        throw new Exception('Amount must be at least ₹1');
    }
    
    // Initialize Razorpay API
    $api = new Api(RAZORPAY_KEY_ID, RAZORPAY_KEY_SECRET);
    
    // Create order
    $orderData = [
        'receipt' => $receipt,
        'amount' => $amount,
        'currency' => $currency,
        'notes' => [
            'payment_type' => $paymentType,
            'user_name' => $userDetails['name'] ?? '',
            'user_email' => $userDetails['email'] ?? '',
            'user_phone' => $userDetails['phone'] ?? ''
        ]
    ];
    
    $razorpayOrder = $api->order->create($orderData);
    
    // Connect to database
    $conn = getDBConnection();
    
    // Store order in database
    if (DB_TYPE === 'sqlite') {
        $stmt = $conn->prepare("INSERT INTO payment_orders (order_id, amount, currency, receipt, payment_type, user_details, status) VALUES (?, ?, ?, ?, ?, ?, 'created')");
        $userDetailsJson = json_encode($userDetails);
        
        if (!$stmt->execute([$razorpayOrder['id'], $amount, $currency, $receipt, $paymentType, $userDetailsJson])) {
            throw new Exception("Failed to store order in database");
        }
    } else {
        // MySQL version
        $stmt = $conn->prepare("INSERT INTO payment_orders (order_id, amount, currency, receipt, payment_type, user_details, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'created', NOW())");
        $userDetailsJson = json_encode($userDetails);
        $stmt->bind_param("sissss", $razorpayOrder['id'], $amount, $currency, $receipt, $paymentType, $userDetailsJson);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to store order in database");
        }
        
        $stmt->close();
        $conn->close();
    }
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Order created successfully',
        'order' => [
            'id' => $razorpayOrder['id'],
            'amount' => $razorpayOrder['amount'],
            'currency' => $razorpayOrder['currency'],
            'receipt' => $razorpayOrder['receipt'],
            'status' => $razorpayOrder['status']
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => 'ORDER_CREATION_FAILED'
    ]);
}
?>
