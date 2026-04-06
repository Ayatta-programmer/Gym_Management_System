-- ============================================
-- FitPulse Gym Management System
-- PostgreSQL Database Schema
-- ============================================

-- Create database (run this separately if needed):
-- CREATE DATABASE fitness_gym;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    phone VARCHAR(20),
    password VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'member' CHECK (role IN ('admin','trainer','member')),
    security_question VARCHAR(255) NOT NULL,
    security_answer VARCHAR(255) NOT NULL,
    status VARCHAR(20) DEFAULT 'active' CHECK (status IN ('active','inactive','suspended')),
    membership_plan VARCHAR(50) DEFAULT 'basic',
    assigned_trainer INT DEFAULT NULL,
    profile_photo VARCHAR(255) DEFAULT NULL,
    joined_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (assigned_trainer) REFERENCES users(id) ON DELETE SET NULL
);

-- Attendance table
CREATE TABLE IF NOT EXISTS attendance (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL,
    check_in TIMESTAMP NOT NULL,
    check_out TIMESTAMP DEFAULT NULL,
    status VARCHAR(20) DEFAULT 'present' CHECK (status IN ('present','absent','late')),
    notes TEXT DEFAULT NULL,
    recorded_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Calories table
CREATE TABLE IF NOT EXISTS calories (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL,
    activity VARCHAR(100) NOT NULL,
    duration_minutes INT NOT NULL,
    calories_burnt DECIMAL(8,2) NOT NULL,
    workout_date DATE NOT NULL,
    intensity VARCHAR(20) DEFAULT 'medium' CHECK (intensity IN ('low','medium','high')),
    notes TEXT DEFAULT NULL,
    recorded_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Invoices table
CREATE TABLE IF NOT EXISTS invoices (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL,
    invoice_number VARCHAR(50) UNIQUE NOT NULL,
    description VARCHAR(255) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    tax DECIMAL(10,2) DEFAULT 0.00,
    total DECIMAL(10,2) NOT NULL,
    status VARCHAR(20) DEFAULT 'pending' CHECK (status IN ('pending','paid','overdue','cancelled')),
    due_date DATE NOT NULL,
    paid_date DATE DEFAULT NULL,
    payment_method VARCHAR(50) DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Reports table
CREATE TABLE IF NOT EXISTS reports (
    id SERIAL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    report_type VARCHAR(30) NOT NULL CHECK (report_type IN ('membership','attendance','revenue','calories','general')),
    date_from DATE NOT NULL,
    date_to DATE NOT NULL,
    generated_by INT NOT NULL,
    data JSONB DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Insert default admin account
-- Email: admin@fitpulse.com | Password: Admin@123
INSERT INTO users (full_name, email, phone, password, role, security_question, security_answer, status, membership_plan)
SELECT
    'System Admin',
    'admin@fitpulse.com',
    '+254700000000',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'admin',
    'What is the name of this system?',
    '$2y$10$e0MYzXyjpJS7Pd0RVkATkOh0se/3ID1o5c.Y.3oeX4fBMGMEyd2Ge',
    'active',
    'premium'
WHERE NOT EXISTS (
    SELECT 1 FROM users WHERE email = 'admin@fitpulse.com'
);
