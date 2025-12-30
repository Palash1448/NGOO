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
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    // Validate required fields
    $requiredFields = ['name', 'email', 'phone', 'address', 'skills', 'availability', 'motivation'];
    foreach ($requiredFields as $field) {
        if (!isset($input[$field]) || empty(trim($input[$field]))) {
            throw new Exception("Missing required field: $field");
        }
    }
    
    // Sanitize input data
    $name = trim($input['name']);
    $email = trim($input['email']);
    $phone = trim($input['phone']);
    $address = trim($input['address']);
    $skills = trim($input['skills']);
    $availability = trim($input['availability']);
    $motivation = trim($input['motivation']);
    $experience = isset($input['experience']) ? trim($input['experience']) : '';
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }
    
    // Validate phone number (basic validation)
    if (!preg_match('/^[+]?[\d\s\-\(\)]{10,}$/', $phone)) {
        throw new Exception('Invalid phone number format');
    }
    
    // Connect to database
    $conn = getDBConnection();
    
    // Check if email already exists
    $stmt = $conn->prepare("SELECT id FROM volunteer_applications WHERE email = ? AND status != 'rejected'");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        throw new Exception('An application with this email already exists');
    }
    $stmt->close();
    
    // Insert volunteer application
    $stmt = $conn->prepare("INSERT INTO volunteer_applications (name, email, phone, address, skills, availability, motivation, experience, status, application_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending_payment', NOW())");
    $stmt->bind_param("ssssssss", $name, $email, $phone, $address, $skills, $availability, $motivation, $experience);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to submit application: " . $stmt->error);
    }
    
    $applicationId = $conn->insert_id;
    $stmt->close();
    $conn->close();
    
    // Send confirmation email (optional - you can implement this later)
    // sendConfirmationEmail($email, $name);
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Application submitted successfully',
        'application_id' => $applicationId,
        'data' => [
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'status' => 'pending_payment'
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => 'APPLICATION_SUBMISSION_FAILED'
    ]);
}

// Function to send confirmation email (implement as needed)
function sendConfirmationEmail($email, $name) {
    // You can implement email sending logic here
    // Using PHPMailer or similar library
    
    $subject = "Volunteer Application Received - Welfare Muze NGO";
    $message = "
    Dear $name,
    
    Thank you for your interest in volunteering with Welfare Muze NGO!
    
    We have received your application and it is currently pending payment completion.
    Please complete the registration fee payment to finalize your application.
    
    Registration Fee: ₹500 (One-time)
    
    After payment completion:
    - You will receive a confirmation email
    - Our team will contact you within 2-3 business days
    - You'll be added to our volunteer WhatsApp group
    - We'll inform you about upcoming volunteer opportunities
    
    Thank you for choosing to make a difference with us!
    
    Best regards,
    Welfare Muze NGO Team
    ";
    
    // Implement actual email sending here
    // mail($email, $subject, $message);
}
?>
