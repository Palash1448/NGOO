-- Database schema for Welfare Muze NGO with Razorpay integration

-- Create database
CREATE DATABASE IF NOT EXISTS welfare_muze_db;
USE welfare_muze_db;

-- Payment Orders Table
CREATE TABLE IF NOT EXISTS payment_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id VARCHAR(255) UNIQUE NOT NULL,
    amount INT NOT NULL, -- Amount in paise
    currency VARCHAR(10) DEFAULT 'INR',
    receipt VARCHAR(255) NOT NULL,
    payment_type ENUM('donation', 'volunteer') NOT NULL,
    user_details JSON,
    payment_id VARCHAR(255) NULL,
    signature VARCHAR(255) NULL,
    status ENUM('created', 'attempted', 'completed', 'failed') DEFAULT 'created',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    INDEX idx_order_id (order_id),
    INDEX idx_payment_type (payment_type),
    INDEX idx_status (status)
);

-- Payments Table
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payment_id VARCHAR(255) UNIQUE NOT NULL,
    order_id VARCHAR(255) NOT NULL,
    amount INT NOT NULL, -- Amount in paise
    currency VARCHAR(10) DEFAULT 'INR',
    method VARCHAR(50),
    status VARCHAR(50),
    payment_type ENUM('donation', 'volunteer') NOT NULL,
    user_details JSON,
    razorpay_data JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_payment_id (payment_id),
    INDEX idx_order_id (order_id),
    INDEX idx_payment_type (payment_type),
    FOREIGN KEY (order_id) REFERENCES payment_orders(order_id)
);

-- Donations Table
CREATE TABLE IF NOT EXISTS donations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    donor_name VARCHAR(255) NOT NULL,
    donor_email VARCHAR(255),
    donor_phone VARCHAR(20),
    amount DECIMAL(10,2) NOT NULL,
    payment_id VARCHAR(255) UNIQUE,
    status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
    message TEXT,
    is_anonymous BOOLEAN DEFAULT FALSE,
    donated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_payment_id (payment_id),
    INDEX idx_status (status),
    INDEX idx_donated_at (donated_at),
    FOREIGN KEY (payment_id) REFERENCES payments(payment_id)
);

-- Volunteers Table
CREATE TABLE IF NOT EXISTS volunteers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    skills TEXT,
    availability TEXT,
    payment_id VARCHAR(255) UNIQUE,
    registration_fee DECIMAL(10,2) DEFAULT 500.00,
    status ENUM('pending', 'active', 'inactive', 'suspended') DEFAULT 'pending',
    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_payment_id (payment_id),
    INDEX idx_status (status),
    FOREIGN KEY (payment_id) REFERENCES payments(payment_id)
);

-- Volunteer Applications Table (for tracking applications before payment)
CREATE TABLE IF NOT EXISTS volunteer_applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    skills TEXT,
    availability TEXT,
    motivation TEXT,
    experience TEXT,
    status ENUM('pending_payment', 'payment_completed', 'approved', 'rejected') DEFAULT 'pending_payment',
    application_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    payment_id VARCHAR(255) NULL,
    INDEX idx_email (email),
    INDEX idx_status (status),
    INDEX idx_payment_id (payment_id)
);

-- Settings Table for configuration
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(255) UNIQUE NOT NULL,
    setting_value TEXT,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default settings
INSERT INTO settings (setting_key, setting_value, description) VALUES 
('volunteer_registration_fee', '500', 'Registration fee for volunteers in INR'),
('razorpay_key_id', 'rzp_test_your_key_id', 'Razorpay Key ID'),
('razorpay_webhook_secret', 'your_webhook_secret', 'Razorpay Webhook Secret'),
('min_donation_amount', '10', 'Minimum donation amount in INR'),
('max_donation_amount', '100000', 'Maximum donation amount in INR')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- Create indexes for better performance
CREATE INDEX idx_donations_amount ON donations(amount);
CREATE INDEX idx_volunteers_registered_at ON volunteers(registered_at);
CREATE INDEX idx_payments_created_at ON payments(created_at);

-- Create views for reporting
CREATE OR REPLACE VIEW donation_summary AS
SELECT 
    DATE(donated_at) as donation_date,
    COUNT(*) as total_donations,
    SUM(amount) as total_amount,
    AVG(amount) as average_amount
FROM donations 
WHERE status = 'completed'
GROUP BY DATE(donated_at)
ORDER BY donation_date DESC;

CREATE OR REPLACE VIEW volunteer_summary AS
SELECT 
    DATE(registered_at) as registration_date,
    COUNT(*) as total_registrations,
    SUM(registration_fee) as total_fees,
    COUNT(CASE WHEN status = 'active' THEN 1 END) as active_volunteers
FROM volunteers 
GROUP BY DATE(registered_at)
ORDER BY registration_date DESC;

-- Sample data for testing (optional)
-- INSERT INTO volunteer_applications (name, email, phone, skills, availability, motivation) VALUES 
-- ('Test Volunteer', 'test@example.com', '+91 9876543210', 'Teaching, Community Work', 'Weekends', 'Want to help the community');
