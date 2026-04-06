<?php
// ============================================
// FitPulse - Database Configuration (PostgreSQL)
// ============================================

// PostgreSQL connection settings
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '5432');
define('DB_NAME', getenv('DB_NAME') ?: 'fitness_gym');
define('DB_USER', getenv('DB_USER') ?: 'postgres');
define('DB_PASS', getenv('DB_PASS') ?: 'postgres');

// Application settings
define('APP_NAME', 'FitPulse');
define('APP_URL', getenv('APP_URL') ?: 'http://localhost:8000');
define('ADMIN_INVITE_CODE', 'FITPULSE2026');

// Session configuration
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Error reporting (set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// PDO Database Connection (PostgreSQL)
try {
    $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
}

// Helper function: redirect
function redirect($url) {
    header("Location: $url");
    exit();
}

// Helper function: check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Helper function: get current user role
function getUserRole() {
    return $_SESSION['role'] ?? null;
}

// Helper function: require login
function requireLogin() {
    if (!isLoggedIn()) {
        redirect(APP_URL . '/auth/login.php');
    }
}

// Helper function: require specific role
function requireRole($roles) {
    requireLogin();
    if (!is_array($roles)) $roles = [$roles];
    if (!in_array(getUserRole(), $roles)) {
        redirect(APP_URL . '/auth/login.php?error=unauthorized');
    }
}

// Helper function: sanitize input
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Helper function: flash message
function setFlash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// Helper function: format currency (KES)
function formatCurrency($amount) {
    return 'KSh ' . number_format($amount, 2);
}

// Helper function: get current user details
function getCurrentUser($pdo) {
    if (!isset($_SESSION['user_id'])) return null;
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}
?>
