-- ============================================
-- FitPulse Gym Management System
-- Database Setup SQL
-- ============================================

CREATE DATABASE IF NOT EXISTS fitpulse_gym;
USE fitpulse_gym;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    phone VARCHAR(20),
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','trainer','member') NOT NULL DEFAULT 'member',
    security_question VARCHAR(255) NOT NULL,
    security_answer VARCHAR(255) NOT NULL,
    status ENUM('active','inactive','suspended') DEFAULT 'active',
    membership_plan VARCHAR(50) DEFAULT 'basic',
    assigned_trainer INT DEFAULT NULL,
    profile_photo VARCHAR(255) DEFAULT NULL,
    joined_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (assigned_trainer) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Attendance table
CREATE TABLE IF NOT EXISTS attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    check_in DATETIME NOT NULL,
    check_out DATETIME DEFAULT NULL,
    status ENUM('present','absent','late') DEFAULT 'present',
    notes TEXT DEFAULT NULL,
    recorded_by INT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Calories table
CREATE TABLE IF NOT EXISTS calories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    activity VARCHAR(100) NOT NULL,
    duration_minutes INT NOT NULL,
    calories_burnt DECIMAL(8,2) NOT NULL,
    workout_date DATE NOT NULL,
    intensity ENUM('low','medium','high') DEFAULT 'medium',
    notes TEXT DEFAULT NULL,
    recorded_by INT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Invoices table
CREATE TABLE IF NOT EXISTS invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    invoice_number VARCHAR(50) UNIQUE NOT NULL,
    description VARCHAR(255) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    tax DECIMAL(10,2) DEFAULT 0.00,
    total DECIMAL(10,2) NOT NULL,
    status ENUM('pending','paid','overdue','cancelled') DEFAULT 'pending',
    due_date DATE NOT NULL,
    paid_date DATE DEFAULT NULL,
    payment_method VARCHAR(50) DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Reports table
CREATE TABLE IF NOT EXISTS reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    report_type ENUM('membership','attendance','revenue','calories','general') NOT NULL,
    date_from DATE NOT NULL,
    date_to DATE NOT NULL,
    generated_by INT NOT NULL,
    data JSON DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default admin account
-- Email: admin@fitpulse.com | Password: Admin@123
INSERT INTO users (full_name, email, phone, password, role, security_question, security_answer, status, membership_plan)
VALUES (
    'System Admin',
    'admin@fitpulse.com',
    '+254700000000',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'admin',
    'What is the name of this system?',
    '$2y$10$e0MYzXyjpJS7Pd0RVkATkOh0se/3ID1o5c.Y.3oeX4fBMGMEyd2Ge',
    'active',
    'premium'
);
