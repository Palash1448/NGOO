<?php
// Configuration file for Welfare Muze NGO

// Database Configuration
define('DB_TYPE', 'sqlite'); // sqlite or mysql
define('DB_HOST', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'welfare_muze_db');
define('SQLITE_DB_PATH', __DIR__ . '/database.sqlite');

// Razorpay Configuration
define('RAZORPAY_KEY_ID', 'rzp_test_R8L2IvxoQ9JlMP');
define('RAZORPAY_KEY_SECRET', 'A3MsQzFu9SbUv4g67BP5cGGe');
define('RAZORPAY_WEBHOOK_SECRET', 'your_webhook_secret');

// Payment Configuration
define('VOLUNTEER_REGISTRATION_FEE', 500); // in INR
define('MIN_DONATION_AMOUNT', 10); // in INR
define('MAX_DONATION_AMOUNT', 100000); // in INR

// Email Configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your_email@gmail.com');
define('SMTP_PASSWORD', 'your_app_password');
define('FROM_EMAIL', 'noreply@welfaremuze.org');
define('FROM_NAME', 'Welfare Muze NGO');

// Application URLs
define('FRONTEND_URL', 'http://localhost:3002');
define('SUCCESS_URL', FRONTEND_URL . '/success');
define('CANCEL_URL', FRONTEND_URL . '/cancel');
define('VOLUNTEER_SUCCESS_URL', FRONTEND_URL . '/volunteer-success');

// File Upload Configuration
define('UPLOAD_DIR', '../uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_FILE_TYPES', ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx']);

// Security Configuration
define('JWT_SECRET', 'your_jwt_secret_key');
define('ENCRYPTION_KEY', 'your_encryption_key');
define('SESSION_TIMEOUT', 3600); // 1 hour

// API Rate Limiting
define('RATE_LIMIT_REQUESTS', 100); // requests per hour
define('RATE_LIMIT_WINDOW', 3600); // 1 hour in seconds

// Logging Configuration
define('LOG_FILE', '../logs/app.log');
define('ERROR_LOG_FILE', '../logs/error.log');
define('PAYMENT_LOG_FILE', '../logs/payments.log');

// Timezone
date_default_timezone_set('Asia/Kolkata');

// Error Reporting (set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Function to get database connection
function getDBConnection() {
    static $conn = null;
    
    if ($conn === null) {
        if (DB_TYPE === 'sqlite') {
            try {
                $conn = new PDO('sqlite:' . SQLITE_DB_PATH);
                $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                
                // Create tables if they don't exist
                createTablesIfNotExist($conn);
            } catch (PDOException $e) {
                error_log("SQLite connection failed: " . $e->getMessage());
                throw new Exception("Database connection failed: " . $e->getMessage());
            }
        } else {
            // MySQL connection
            $conn = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);
            
            if ($conn->connect_error) {
                error_log("MySQL connection failed: " . $conn->connect_error);
                throw new Exception("Database connection failed: " . $conn->connect_error);
            }
            
            $conn->set_charset("utf8mb4");
        }
    }
    
    return $conn;
}

// Function to create tables for SQLite
function createTablesIfNotExist($conn) {
    $tables = [
        'payment_orders' => '
            CREATE TABLE IF NOT EXISTS payment_orders (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                order_id VARCHAR(100) UNIQUE NOT NULL,
                amount INTEGER NOT NULL,
                currency VARCHAR(10) DEFAULT "INR",
                receipt VARCHAR(100) NOT NULL,
                payment_type VARCHAR(50) NOT NULL,
                user_details TEXT,
                status VARCHAR(20) DEFAULT "created",
                razorpay_payment_id VARCHAR(100),
                razorpay_signature VARCHAR(200),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )',
        'volunteer_applications' => '
            CREATE TABLE IF NOT EXISTS volunteer_applications (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(100) NOT NULL,
                email VARCHAR(100) NOT NULL,
                phone VARCHAR(20) NOT NULL,
                address TEXT,
                skills TEXT,
                experience TEXT,
                motivation TEXT,
                availability VARCHAR(100),
                status VARCHAR(20) DEFAULT "pending",
                payment_status VARCHAR(20) DEFAULT "pending",
                order_id VARCHAR(100),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )',
        'donations' => '
            CREATE TABLE IF NOT EXISTS donations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                donor_name VARCHAR(100) NOT NULL,
                donor_email VARCHAR(100) NOT NULL,
                donor_phone VARCHAR(20),
                amount INTEGER NOT NULL,
                currency VARCHAR(10) DEFAULT "INR",
                message TEXT,
                order_id VARCHAR(100),
                payment_id VARCHAR(100),
                status VARCHAR(20) DEFAULT "pending",
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )'
    ];
    
    foreach ($tables as $tableName => $sql) {
        try {
            $conn->exec($sql);
        } catch (PDOException $e) {
            error_log("Failed to create table $tableName: " . $e->getMessage());
        }
    }
}

// Function to log activities
function logActivity($message, $type = 'INFO', $file = LOG_FILE) {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] [$type] $message" . PHP_EOL;
    file_put_contents($file, $logMessage, FILE_APPEND | LOCK_EX);
}

// Function to validate environment
function validateEnvironment() {
    $requiredConstants = [
        'DB_HOST', 'DB_USERNAME', 'DB_NAME',
        'RAZORPAY_KEY_ID', 'RAZORPAY_KEY_SECRET'
    ];
    
    foreach ($requiredConstants as $constant) {
        if (!defined($constant) || (empty(constant($constant)) && $constant !== 'DB_PASSWORD')) {
            throw new Exception("Missing required configuration: $constant");
        }
    }
    
    // Check DB_PASSWORD separately (can be empty for local development)
    if (!defined('DB_PASSWORD')) {
        throw new Exception("Missing required configuration: DB_PASSWORD");
    }
}

// Validate environment on load
try {
    validateEnvironment();
} catch (Exception $e) {
    error_log("Configuration error: " . $e->getMessage());
    if (php_sapi_name() !== 'cli') {
        http_response_code(500);
        echo json_encode(['error' => 'Server configuration error']);
        exit;
    }
}
?>
