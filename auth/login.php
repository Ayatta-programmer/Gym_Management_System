<?php
require_once __DIR__ . '/../config/database.php';

// Redirect if already logged in
if (isLoggedIn()) {
    $role = getUserRole();
    switch ($role) {
        case 'admin': redirect('../admin/dashboard.php'); break;
        case 'trainer': redirect('../trainer/dashboard.php'); break;
        default: redirect('../member/dashboard.php');
    }
}

$error = '';
$success = '';

if (isset($_GET['error']) && $_GET['error'] === 'unauthorized') {
    $error = 'You do not have permission to access that page.';
}
if (isset($_GET['registered'])) {
    $success = 'Account created successfully! Please sign in.';
}
if (isset($_GET['reset'])) {
    $success = 'Password reset successfully! Please sign in with your new password.';
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        $stmt = $pdo->prepare('SELECT id, full_name, email, password, role, status FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] !== 'active') {
                $error = 'Your account has been ' . $user['status'] . '. Contact admin.';
            } else {
                // Set session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['full_name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['role'] = $user['role'];

                // Redirect based on role
                switch ($user['role']) {
                    case 'admin': redirect('../admin/dashboard.php'); break;
                    case 'trainer': redirect('../trainer/dashboard.php'); break;
                    default: redirect('../member/dashboard.php');
                }
            }
        } else {
            $error = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign In - <?= APP_NAME ?></title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>

<div class="auth-page">
  <div class="auth-card">
    <div class="auth-header">
      <a href="../index.html" class="auth-logo">FP</a>
      <h2>Welcome Back</h2>
      <p class="auth-subtitle">Sign in to your FitPulse account</p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i> <?= $error ?>
      </div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> <?= $success ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="" id="loginForm">
      <div class="form-group">
        <label for="email">Email Address</label>
        <input type="email" id="email" name="email" class="form-control" placeholder="Enter your email" value="<?= htmlspecialchars($email ?? '') ?>" required>
      </div>

      <div class="form-group">
        <label for="password">Password</label>
        <div style="position:relative">
          <input type="password" id="password" name="password" class="form-control" placeholder="Enter your password" required>
          <button type="button" class="toggle-password" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--gray-500);cursor:pointer">
            <i class="fas fa-eye"></i>
          </button>
        </div>
      </div>

      <div class="form-footer">
        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;color:var(--gray-400)">
          <input type="checkbox" name="remember" style="accent-color:var(--primary)"> Remember me
        </label>
        <a href="forgot_password.php">Forgot Password?</a>
      </div>

      <button type="submit" class="btn btn-primary auth-btn">
        <i class="fas fa-sign-in-alt"></i> Sign In
      </button>
    </form>

    <div class="auth-divider">or</div>

    <p class="auth-alt">
      Don't have an account? <a href="register.php">Create one</a>
    </p>
  </div>
</div>

<script src="../js/main.js"></script>
</body>
</html>
