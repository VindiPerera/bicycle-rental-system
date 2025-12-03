<?php
// Database Setup Script
require_once '../src/db.php';

echo "Setting up database...\n\n";

// Create users table
$sql = "CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'staff') DEFAULT 'staff',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql)) {
    echo "✓ Users table created successfully\n";
} else {
    echo "✗ Error creating table: " . $conn->error . "\n";
}

// Insert default admin user (password: 12345)
$password_hash = password_hash('12345', PASSWORD_DEFAULT);
$sql = "INSERT INTO users (username, password, role) 
        VALUES ('admin', '$password_hash', 'admin')
        ON DUPLICATE KEY UPDATE username = username";

if ($conn->query($sql)) {
    echo "✓ Admin user created successfully\n";
    echo "\nLogin credentials:\n";
    echo "  Username: admin\n";
    echo "  Password: 12345\n";
} else {
    echo "✗ Error creating admin: " . $conn->error . "\n";
}

echo "\nSetup complete! You can now login.\n";

$conn->close();
?>
