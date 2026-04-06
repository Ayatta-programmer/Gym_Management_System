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
$formData = [
    'full_name' => '', 'email' => '', 'phone' => '',
    'role' => 'member', 'security_question' => ''
];

$securityQuestions = [
    'What is the name of your first pet?',
    'What city were you born in?',
    'What is your mother\'s maiden name?',
    'What was the name of your first school?',
    'What is your favorite sport?'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData['full_name'] = sanitize($_POST['full_name'] ?? '');
    $formData['email'] = sanitize($_POST['email'] ?? '');
    $formData['phone'] = sanitize($_POST['phone'] ?? '');
    $formData['role'] = sanitize($_POST['role'] ?? 'member');
    $formData['security_question'] = sanitize($_POST['security_question'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $securityAnswer = $_POST['security_answer'] ?? '';
    $inviteCode = sanitize($_POST['invite_code'] ?? '');

    // Validation
    if (empty($formData['full_name']) || empty($formData['email']) || empty($password) || empty($formData['security_question']) || empty($securityAnswer)) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } elseif ($formData['role'] === 'admin' && $inviteCode !== ADMIN_INVITE_CODE) {
        $error = 'Invalid admin invite code.';
    } else {
        // Check if email already exists
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$formData['email']]);
        if ($stmt->fetch()) {
            $error = 'An account with this email already exists.';
        } else {
            // Create account
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $hashedAnswer = password_hash(strtolower($securityAnswer), PASSWORD_DEFAULT);

            $stmt = $pdo->prepare('INSERT INTO users (full_name, email, phone, password, role, security_question, security_answer) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $formData['full_name'],
                $formData['email'],
                $formData['phone'],
                $hashedPassword,
                $formData['role'],
                $formData['security_question'],
                $hashedAnswer
            ]);

            redirect('login.php?registered=1');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign Up - <?= APP_NAME ?></title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>

<div class="auth-page">
  <div class="auth-card" style="max-width: 540px">
    <div class="auth-header">
      <a href="../index.html" class="auth-logo">FP</a>
      <h2>Create Account</h2>
      <p class="auth-subtitle">Join FitPulse and start your fitness journey</p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i> <?= $error ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="" id="registerForm">
      <div class="form-group">
        <label for="full_name">Full Name <span style="color:var(--danger)">*</span></label>
        <input type="text" id="full_name" name="full_name" class="form-control" placeholder="Enter your full name" value="<?= htmlspecialchars($formData['full_name']) ?>" required>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="email">Email Address <span style="color:var(--danger)">*</span></label>
          <input type="email" id="email" name="email" class="form-control" placeholder="your@email.com" value="<?= htmlspecialchars($formData['email']) ?>" required>
        </div>
        <div class="form-group">
          <label for="phone">Phone Number</label>
          <input type="tel" id="phone" name="phone" class="form-control" placeholder="+254 7XX XXX XXX" value="<?= htmlspecialchars($formData['phone']) ?>">
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="password">Password <span style="color:var(--danger)">*</span></label>
          <input type="password" id="password" name="password" class="form-control" placeholder="Min. 6 characters" required>
        </div>
        <div class="form-group">
          <label for="confirm_password">Confirm Password <span style="color:var(--danger)">*</span></label>
          <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Repeat password" required>
        </div>
      </div>

      <div class="form-group">
        <label for="role">Register As <span style="color:var(--danger)">*</span></label>
        <select id="role" name="role" class="form-control" required>
          <option value="member" <?= $formData['role'] === 'member' ? 'selected' : '' ?>>Member</option>
          <option value="trainer" <?= $formData['role'] === 'trainer' ? 'selected' : '' ?>>Trainer</option>
          <option value="admin" <?= $formData['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
        </select>
      </div>

      <div class="form-group" id="inviteCodeGroup" style="display:none">
        <label for="invite_code">Admin Invite Code <span style="color:var(--danger)">*</span></label>
        <input type="text" id="invite_code" name="invite_code" class="form-control" placeholder="Enter admin invite code">
      </div>

      <div class="form-group">
        <label for="security_question">Security Question <span style="color:var(--danger)">*</span></label>
        <select id="security_question" name="security_question" class="form-control" required>
          <option value="">Select a security question</option>
          <?php foreach ($securityQuestions as $q): ?>
            <option value="<?= htmlspecialchars($q) ?>" <?= $formData['security_question'] === $q ? 'selected' : '' ?>><?= htmlspecialchars($q) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label for="security_answer">Security Answer <span style="color:var(--danger)">*</span></label>
        <input type="text" id="security_answer" name="security_answer" class="form-control" placeholder="Your answer (used for password reset)" required>
      </div>

      <button type="submit" class="btn btn-primary auth-btn">
        <i class="fas fa-user-plus"></i> Create Account
      </button>
    </form>

    <div class="auth-divider">or</div>

    <p class="auth-alt">
      Already have an account? <a href="login.php">Sign In</a>
    </p>
  </div>
</div>

<script src="../js/main.js"></script>
<script>
  // Show/hide invite code field based on role selection
  const roleSelect = document.getElementById('role');
  const inviteGroup = document.getElementById('inviteCodeGroup');

  roleSelect.addEventListener('change', function() {
    inviteGroup.style.display = this.value === 'admin' ? 'block' : 'none';
  });

  // Trigger on page load
  if (roleSelect.value === 'admin') {
    inviteGroup.style.display = 'block';
  }
</script>
</body>
</html>
