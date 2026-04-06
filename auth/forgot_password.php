<?php
require_once __DIR__ . '/../config/database.php';

$step = 1;
$error = '';
$success = '';
$email = '';
$securityQuestion = '';

// Step 1: Find account by email
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['step'])) {
    $currentStep = (int)$_POST['step'];

    if ($currentStep === 1) {
        $email = sanitize($_POST['email'] ?? '');
        if (empty($email)) {
            $error = 'Please enter your email address.';
        } else {
            $stmt = $pdo->prepare('SELECT id, email, security_question FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                $step = 2;
                $securityQuestion = $user['security_question'];
                $_SESSION['reset_user_id'] = $user['id'];
                $_SESSION['reset_email'] = $user['email'];
            } else {
                $error = 'No account found with this email address.';
            }
        }
    }

    if ($currentStep === 2) {
        $answer = strtolower(trim($_POST['security_answer'] ?? ''));
        $email = sanitize($_POST['email'] ?? '');

        if (empty($answer)) {
            $error = 'Please provide your security answer.';
            $step = 2;
        } else {
            $userId = $_SESSION['reset_user_id'] ?? null;
            if (!$userId) {
                $error = 'Session expired. Please start over.';
                $step = 1;
            } else {
                $stmt = $pdo->prepare('SELECT security_answer, security_question FROM users WHERE id = ?');
                $stmt->execute([$userId]);
                $user = $stmt->fetch();
                $securityQuestion = $user['security_question'];

                if ($user && password_verify($answer, $user['security_answer'])) {
                    $step = 3;
                    $_SESSION['reset_verified'] = true;
                } else {
                    $error = 'Incorrect security answer. Please try again.';
                    $step = 2;
                }
            }
        }
    }

    if ($currentStep === 3) {
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        $verified = $_SESSION['reset_verified'] ?? false;
        $userId = $_SESSION['reset_user_id'] ?? null;

        if (!$verified || !$userId) {
            $error = 'Session expired. Please start over.';
            $step = 1;
        } elseif (empty($newPassword) || strlen($newPassword) < 6) {
            $error = 'Password must be at least 6 characters.';
            $step = 3;
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'Passwords do not match.';
            $step = 3;
        } else {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
            $stmt->execute([$hashedPassword, $userId]);

            // Clean up session
            unset($_SESSION['reset_user_id'], $_SESSION['reset_email'], $_SESSION['reset_verified']);

            redirect('login.php?reset=1');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Forgot Password - <?= APP_NAME ?></title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>

<div class="auth-page">
  <div class="auth-card" style="max-width: 500px">
    <div class="auth-header">
      <a href="../index.html" class="auth-logo">FP</a>
      <h2>Reset Password</h2>
      <p class="auth-subtitle">Follow the steps below to recover your account</p>
    </div>

    <!-- Step Wizard -->
    <div class="step-wizard">
      <div class="step <?= $step >= 1 ? ($step > 1 ? 'completed' : 'active') : '' ?>">
        <span class="step-number"><?= $step > 1 ? '<i class="fas fa-check"></i>' : '1' ?></span>
        <span>Email</span>
      </div>
      <div class="step <?= $step >= 2 ? ($step > 2 ? 'completed' : 'active') : '' ?>">
        <span class="step-number"><?= $step > 2 ? '<i class="fas fa-check"></i>' : '2' ?></span>
        <span>Verify</span>
      </div>
      <div class="step <?= $step >= 3 ? 'active' : '' ?>">
        <span class="step-number">3</span>
        <span>Reset</span>
      </div>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i> <?= $error ?>
      </div>
    <?php endif; ?>

    <!-- Step 1: Enter Email -->
    <div class="step-content <?= $step === 1 ? 'active' : '' ?>">
      <form method="POST" action="">
        <input type="hidden" name="step" value="1">
        <div class="form-group">
          <label for="email">Email Address</label>
          <input type="email" id="email" name="email" class="form-control" placeholder="Enter your registered email" value="<?= htmlspecialchars($email) ?>" required>
        </div>
        <button type="submit" class="btn btn-primary auth-btn">
          <i class="fas fa-arrow-right"></i> Continue
        </button>
      </form>
    </div>

    <!-- Step 2: Security Question -->
    <div class="step-content <?= $step === 2 ? 'active' : '' ?>">
      <form method="POST" action="">
        <input type="hidden" name="step" value="2">
        <input type="hidden" name="email" value="<?= htmlspecialchars($_SESSION['reset_email'] ?? $email) ?>">

        <div class="form-group">
          <label>Security Question</label>
          <p style="color: var(--white); font-size: 0.95rem; padding: 0.75rem; background: var(--gray-900); border-radius: var(--radius-md); border: 1px solid var(--gray-700);">
            <?= htmlspecialchars($securityQuestion) ?>
          </p>
        </div>

        <div class="form-group">
          <label for="security_answer">Your Answer</label>
          <input type="text" id="security_answer" name="security_answer" class="form-control" placeholder="Enter your security answer" required>
        </div>

        <button type="submit" class="btn btn-primary auth-btn">
          <i class="fas fa-shield-halved"></i> Verify Answer
        </button>
      </form>
    </div>

    <!-- Step 3: New Password -->
    <div class="step-content <?= $step === 3 ? 'active' : '' ?>">
      <form method="POST" action="">
        <input type="hidden" name="step" value="3">

        <div class="form-group">
          <label for="new_password">New Password</label>
          <input type="password" id="new_password" name="new_password" class="form-control" placeholder="Min. 6 characters" required>
        </div>

        <div class="form-group">
          <label for="confirm_password">Confirm New Password</label>
          <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Repeat new password" required>
        </div>

        <button type="submit" class="btn btn-primary auth-btn">
          <i class="fas fa-key"></i> Reset Password
        </button>
      </form>
    </div>

    <div class="auth-divider">or</div>
    <p class="auth-alt">
      Remember your password? <a href="login.php">Sign In</a>
    </p>
  </div>
</div>

<script src="../js/main.js"></script>
</body>
</html>
