<?php
// Trainer Sidebar Include
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$user = getCurrentUser($pdo);
$initials = '';
if ($user) {
    $parts = explode(' ', $user['full_name']);
    $initials = strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
}
?>
<aside class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <div class="logo-icon">FP</div>
    <span>FitPulse</span>
  </div>

  <div class="sidebar-menu">
    <div class="sidebar-label">Main</div>
    <a href="dashboard.php" class="sidebar-link <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
      <span class="icon"><i class="fas fa-th-large"></i></span>
      Dashboard
    </a>

    <div class="sidebar-label">Modules</div>
    <a href="members.php" class="sidebar-link <?= $currentPage === 'members' ? 'active' : '' ?>">
      <span class="icon"><i class="fas fa-users"></i></span>
      My Members
    </a>
    <a href="attendance.php" class="sidebar-link <?= $currentPage === 'attendance' ? 'active' : '' ?>">
      <span class="icon"><i class="fas fa-calendar-check"></i></span>
      Attendance
    </a>
    <a href="calories.php" class="sidebar-link <?= $currentPage === 'calories' ? 'active' : '' ?>">
      <span class="icon"><i class="fas fa-fire-flame-curved"></i></span>
      Calories
    </a>

    <div class="sidebar-label">Account</div>
    <a href="../auth/logout.php" class="sidebar-link">
      <span class="icon"><i class="fas fa-sign-out-alt"></i></span>
      Logout
    </a>
  </div>

  <div class="sidebar-footer">
    <div class="sidebar-user">
      <div class="avatar"><?= $initials ?></div>
      <div class="user-info">
        <div class="name"><?= htmlspecialchars($user['full_name'] ?? 'Trainer') ?></div>
        <div class="role">Trainer</div>
      </div>
    </div>
  </div>
</aside>
