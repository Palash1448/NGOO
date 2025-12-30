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
use Razorpay\Api\Errors\BadRequestError;

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    // Validate required fields
    $requiredFields = ['razorpay_order_id', 'razorpay_payment_id', 'razorpay_signature', 'payment_type', 'amount'];
    foreach ($requiredFields as $field) {
        if (!isset($input[$field]) || empty($input[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }
    
    $orderId = $input['razorpay_order_id'];
    $paymentId = $input['razorpay_payment_id'];
    $signature = $input['razorpay_signature'];
    $paymentType = $input['payment_type'];
    $amount = (int)$input['amount'];
    $userDetails = $input['user_details'] ?? [];
    
    // Initialize Razorpay API
    $api = new Api(RAZORPAY_KEY_ID, RAZORPAY_KEY_SECRET);
    
    // Verify signature
    $expectedSignature = hash_hmac('sha256', $orderId . '|' . $paymentId, RAZORPAY_KEY_SECRET);
    
    if ($signature !== $expectedSignature) {
        throw new Exception('Payment signature verification failed');
    }
    
    // Fetch payment details from Razorpay
    try {
        $payment = $api->payment->fetch($paymentId);
        $order = $api->order->fetch($orderId);
    } catch (BadRequestError $e) {
        throw new Exception('Invalid payment or order ID');
    }
    
    // Verify payment status
    if ($payment['status'] !== 'captured' && $payment['status'] !== 'authorized') {
        throw new Exception('Payment not successful');
    }
    
    // Verify amount
    if ($payment['amount'] !== ($amount * 100)) {
        throw new Exception('Payment amount mismatch');
    }
    
    // Connect to database
    $conn = getDBConnection();
    
    // Start transaction
    if (DB_TYPE === 'sqlite') {
        $conn->beginTransaction();
    } else {
        $conn->begin_transaction();
    }
    
    try {
        // Update payment order status
        if (DB_TYPE === 'sqlite') {
            $stmt = $conn->prepare("UPDATE payment_orders SET razorpay_payment_id = ?, razorpay_signature = ?, status = 'completed', updated_at = CURRENT_TIMESTAMP WHERE order_id = ?");
            $stmt->execute([$paymentId, $signature, $orderId]);
        } else {
            $stmt = $conn->prepare("UPDATE payment_orders SET payment_id = ?, signature = ?, status = 'completed', completed_at = NOW() WHERE order_id = ?");
            $stmt->bind_param("sss", $paymentId, $signature, $orderId);
            $stmt->execute();
            $stmt->close();
        }
        
        // Handle specific payment types
        if ($paymentType === 'donation') {
            // Insert donation record
            if (DB_TYPE === 'sqlite') {
                $stmt = $conn->prepare("INSERT INTO donations (donor_name, donor_email, donor_phone, amount, payment_id, status) VALUES (?, ?, ?, ?, ?, 'completed')");
                $stmt->execute([$userDetails['name'], $userDetails['email'], $userDetails['phone'] ?? '', $amount, $paymentId]);
            } else {
                $stmt = $conn->prepare("INSERT INTO donations (donor_name, donor_email, donor_phone, amount, payment_id, status, donated_at) VALUES (?, ?, ?, ?, ?, 'completed', NOW())");
                $stmt->bind_param("sssis", $userDetails['name'], $userDetails['email'], $userDetails['phone'], $amount, $paymentId);
                $stmt->execute();
                $stmt->close();
            }
        }
        
        // Commit transaction
        if (DB_TYPE === 'sqlite') {
            $conn->commit();
        } else {
            $conn->commit();
        }
        
        // Return success response
        echo json_encode([
            'success' => true,
            'message' => 'Payment verified and processed successfully',
            'payment' => [
                'id' => $paymentId,
                'order_id' => $orderId,
                'amount' => $payment['amount'] / 100, // Convert back to rupees
                'currency' => $payment['currency'],
                'method' => $payment['method'],
                'status' => $payment['status'],
                'payment_type' => $paymentType
            ],
            'redirect_url' => $paymentType === 'volunteer' ? '/volunteer-success' : '/donation-success'
        ]);
        
    } catch (Exception $e) {
        if (DB_TYPE === 'sqlite') {
            $conn->rollback();
        } else {
            $conn->rollback();
        }
        throw $e;
    }
    
    // Close connection (PDO closes automatically, but for mysqli we need to close)
    if (DB_TYPE !== 'sqlite' && method_exists($conn, 'close')) {
        $conn->close();
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => 'PAYMENT_VERIFICATION_FAILED'
    ]);
}
?>
