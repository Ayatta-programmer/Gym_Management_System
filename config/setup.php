<?php
// ============================================
// FitPulse - Database Setup Script (PostgreSQL)
// Run once to create all tables
// ============================================

require_once __DIR__ . '/database.php';

try {
    // Users table
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
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
    )");

    // Attendance table
    $pdo->exec("CREATE TABLE IF NOT EXISTS attendance (
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
    )");

    // Calories table
    $pdo->exec("CREATE TABLE IF NOT EXISTS calories (
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
    )");

    // Invoices table
    $pdo->exec("CREATE TABLE IF NOT EXISTS invoices (
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
    )");

    // Reports table
    $pdo->exec("CREATE TABLE IF NOT EXISTS reports (
        id SERIAL PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        report_type VARCHAR(30) NOT NULL CHECK (report_type IN ('membership','attendance','revenue','calories','general')),
        date_from DATE NOT NULL,
        date_to DATE NOT NULL,
        generated_by INT NOT NULL,
        data JSONB DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE CASCADE
    )");

    // Create default admin if not exists
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute(['admin@fitpulse.com']);
    if (!$stmt->fetch()) {
        $adminPass = password_hash('Admin@123', PASSWORD_DEFAULT);
        $secAnswer = password_hash('fitpulse', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('INSERT INTO users (full_name, email, phone, password, role, security_question, security_answer, status, membership_plan) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            'System Admin',
            'admin@fitpulse.com',
            '+254700000000',
            $adminPass,
            'admin',
            'What is the name of this system?',
            $secAnswer,
            'active',
            'premium'
        ]);
    }

    echo "<!DOCTYPE html><html><head><title>Setup Complete</title><link rel='stylesheet' href='../css/style.css'></head><body class='auth-page'><div class='auth-card' style='text-align:center'>";
    echo "<div class='auth-logo'>FP</div>";
    echo "<h2 style='color: var(--success); margin-bottom: 1rem'>✅ Database Setup Complete!</h2>";
    echo "<p style='color: var(--gray-300); margin-bottom: 1.5rem'>All PostgreSQL tables have been created successfully.</p>";
    echo "<p style='color: var(--gray-400); font-size: 0.9rem; margin-bottom: 0.5rem'><strong>Default Admin:</strong></p>";
    echo "<p style='color: var(--gray-300); font-size: 0.85rem'>Email: admin@fitpulse.com</p>";
    echo "<p style='color: var(--gray-300); font-size: 0.85rem; margin-bottom: 1.5rem'>Password: Admin@123</p>";
    echo "<a href='../auth/login.php' class='btn btn-primary btn-lg w-full'>Go to Login</a>";
    echo "</div></body></html>";

} catch (PDOException $e) {
    echo "Setup Error: " . $e->getMessage();
}
?>
