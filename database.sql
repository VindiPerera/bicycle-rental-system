-- Create database
CREATE DATABASE IF NOT EXISTS bicycle_rental;
USE bicycle_rental;

-- Create users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'staff') DEFAULT 'staff',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create bicycles table
CREATE TABLE IF NOT EXISTS bicycles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bike_number VARCHAR(50) UNIQUE NOT NULL,
    brand VARCHAR(100) NOT NULL,
    model VARCHAR(100),
    status ENUM('available', 'rented', 'maintenance') DEFAULT 'available',
    rental_rate DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create customers table
CREATE TABLE IF NOT EXISTS customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(20) NOT NULL,
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create rentals table
CREATE TABLE IF NOT EXISTS rentals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bicycle_id INT NOT NULL,
    customer_id INT NOT NULL,
    user_id INT NOT NULL,
    start_time DATETIME NOT NULL,
    end_time DATETIME,
    rental_hours DECIMAL(10, 2),
    total_amount DECIMAL(10, 2),
    status ENUM('active', 'completed', 'cancelled') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bicycle_id) REFERENCES bicycles(id),
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Insert default admin user (password: 12345)
INSERT INTO users (username, password, role) 
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Insert sample bicycles
INSERT INTO bicycles (bike_number, brand, model, status, rental_rate) VALUES
('BK001', 'Trek', 'Mountain X1', 'available', 15.00),
('BK002', 'Giant', 'Road Pro', 'available', 20.00),
('BK003', 'Specialized', 'City Comfort', 'available', 12.00),
('BK004', 'Cannondale', 'Hybrid Plus', 'available', 18.00),
('BK005', 'Scott', 'Trail Master', 'available', 22.00);

-- Note: The password hash above is for '12345'
-- You can generate new hashes using: password_hash('your_password', PASSWORD_DEFAULT)
