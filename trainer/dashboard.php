<?php
require_once __DIR__ . '/../auth/auth_check.php';
checkAuth(['trainer']);

$trainerId = $_SESSION['user_id'];

// Stats
$myMembers = $pdo->prepare("SELECT COUNT(*) FROM users WHERE assigned_trainer = ? AND role = 'member'");
$myMembers->execute([$trainerId]);
$memberCount = $myMembers->fetchColumn();

$todayAttendance = $pdo->prepare("
    SELECT COUNT(DISTINCT a.user_id) FROM attendance a 
    JOIN users u ON a.user_id = u.id 
    WHERE u.assigned_trainer = ? AND DATE(a.check_in) = CURRENT_DATE
");
$todayAttendance->execute([$trainerId]);
$todayAttCount = $todayAttendance->fetchColumn();

$todayCals = $pdo->prepare("
    SELECT COALESCE(SUM(c.calories_burnt), 0) FROM calories c 
    WHERE c.recorded_by = ? AND c.workout_date = CURRENT_DATE
");
$todayCals->execute([$trainerId]);
$todayCalories = $todayCals->fetchColumn();

// Recent activity
$recentAtt = $pdo->prepare("
    SELECT a.*, u.full_name FROM attendance a 
    JOIN users u ON a.user_id = u.id 
    WHERE a.recorded_by = ? 
    ORDER BY a.check_in DESC LIMIT 5
");
$recentAtt->execute([$trainerId]);
$recentAttendance = $recentAtt->fetchAll();

$recentCal = $pdo->prepare("
    SELECT c.*, u.full_name FROM calories c 
    JOIN users u ON c.user_id = u.id 
    WHERE c.recorded_by = ? 
    ORDER BY c.created_at DESC LIMIT 5
");
$recentCal->execute([$trainerId]);
$recentCalories = $recentCal->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Trainer Dashboard - <?= APP_NAME ?></title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>

<div class="dashboard">
  <?php require_once __DIR__ . '/../includes/trainer_sidebar.php'; ?>

  <div class="main-content">
    <div class="main-header">
      <div>
        <button class="sidebar-toggle" id="sidebarToggle"><i class="fas fa-bars"></i></button>
        <h1>Trainer Dashboard</h1>
        <p>Welcome, <?= htmlspecialchars($_SESSION['user_name']) ?>!</p>
      </div>
      <span style="color:var(--gray-400);font-size:0.85rem"><i class="fas fa-calendar"></i> <?= date('l, F j, Y') ?></span>
    </div>

    <div class="stats-grid" style="grid-template-columns: repeat(3, 1fr)">
      <div class="stat-card">
        <div class="stat-header">
          <div>
            <div class="stat-value"><?= $memberCount ?></div>
            <div class="stat-label">Assigned Members</div>
          </div>
          <div class="stat-icon orange"><i class="fas fa-users"></i></div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-header">
          <div>
            <div class="stat-value"><?= $todayAttCount ?></div>
            <div class="stat-label">Today's Attendance</div>
          </div>
          <div class="stat-icon green"><i class="fas fa-calendar-check"></i></div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-header">
          <div>
            <div class="stat-value"><?= number_format($todayCalories) ?></div>
            <div class="stat-label">Today's Calories Logged</div>
          </div>
          <div class="stat-icon blue"><i class="fas fa-fire-flame-curved"></i></div>
        </div>
      </div>
    </div>

    <div class="charts-grid">
      <div class="card">
        <div class="card-header">
          <h3>Recent Attendance Logs</h3>
          <a href="attendance.php" class="btn btn-sm btn-ghost">View All <i class="fas fa-arrow-right"></i></a>
        </div>
        <div class="card-body no-padding">
          <table class="data-table">
            <thead><tr><th>Member</th><th>Check In</th><th>Status</th></tr></thead>
            <tbody>
              <?php if (empty($recentAttendance)): ?>
                <tr><td colspan="3" class="text-center" style="padding:2rem;color:var(--gray-500)">No records yet</td></tr>
              <?php else: ?>
                <?php foreach ($recentAttendance as $a): ?>
                  <tr>
                    <td><?= htmlspecialchars($a['full_name']) ?></td>
                    <td><?= date('M j, g:i A', strtotime($a['check_in'])) ?></td>
                    <td><span class="badge badge-success"><?= ucfirst($a['status']) ?></span></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <h3>Recent Calorie Logs</h3>
          <a href="calories.php" class="btn btn-sm btn-ghost">View All <i class="fas fa-arrow-right"></i></a>
        </div>
        <div class="card-body no-padding">
          <table class="data-table">
            <thead><tr><th>Member</th><th>Activity</th><th>Calories</th></tr></thead>
            <tbody>
              <?php if (empty($recentCalories)): ?>
                <tr><td colspan="3" class="text-center" style="padding:2rem;color:var(--gray-500)">No records yet</td></tr>
              <?php else: ?>
                <?php foreach ($recentCalories as $c): ?>
                  <tr>
                    <td><?= htmlspecialchars($c['full_name']) ?></td>
                    <td><?= htmlspecialchars($c['activity']) ?></td>
                    <td><strong style="color:var(--primary)"><?= number_format($c['calories_burnt']) ?> kcal</strong></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="../js/main.js"></script>
</body>
</html>
