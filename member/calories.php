<?php
require_once __DIR__ . '/../auth/auth_check.php';
checkAuth(['member']);

$memberId = $_SESSION['user_id'];

$records = $pdo->prepare("SELECT * FROM calories WHERE user_id = ? ORDER BY workout_date DESC, created_at DESC");
$records->execute([$memberId]);
$records = $records->fetchAll();

$totalCal = 0; $totalSessions = count($records); $totalMinutes = 0;
foreach ($records as $r) { $totalCal += $r['calories_burnt']; $totalMinutes += $r['duration_minutes']; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Calories - <?= APP_NAME ?></title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>

<div class="dashboard">
  <?php require_once __DIR__ . '/../includes/member_sidebar.php'; ?>

  <div class="main-content">
    <div class="main-header">
      <div>
        <button class="sidebar-toggle" id="sidebarToggle"><i class="fas fa-bars"></i></button>
        <h1>My Calories</h1>
        <p>Track your fitness progress</p>
      </div>
    </div>

    <div class="stats-grid" style="grid-template-columns: repeat(3,1fr)">
      <div class="stat-card">
        <div class="stat-header">
          <div><div class="stat-value"><?= number_format($totalCal) ?></div><div class="stat-label">Total Calories Burnt</div></div>
          <div class="stat-icon orange"><i class="fas fa-fire"></i></div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-header">
          <div><div class="stat-value"><?= $totalSessions ?></div><div class="stat-label">Total Sessions</div></div>
          <div class="stat-icon green"><i class="fas fa-dumbbell"></i></div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-header">
          <div><div class="stat-value"><?= round($totalMinutes / 60, 1) ?></div><div class="stat-label">Total Hours</div></div>
          <div class="stat-icon blue"><i class="fas fa-clock"></i></div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><h3>Workout History</h3></div>
      <div class="card-body no-padding">
        <table class="data-table">
          <thead><tr><th>Date</th><th>Activity</th><th>Duration</th><th>Calories</th><th>Intensity</th></tr></thead>
          <tbody>
            <?php if (empty($records)): ?>
              <tr><td colspan="5" class="text-center" style="padding:2rem;color:var(--gray-500)">No workout records yet</td></tr>
            <?php else: ?>
              <?php foreach ($records as $r): ?>
                <tr>
                  <td><?= date('M j, Y', strtotime($r['workout_date'])) ?></td>
                  <td><?= htmlspecialchars($r['activity']) ?></td>
                  <td><?= $r['duration_minutes'] ?> min</td>
                  <td><strong style="color:var(--primary)"><?= number_format($r['calories_burnt']) ?> kcal</strong></td>
                  <td><span class="badge badge-<?= $r['intensity']==='high'?'danger':($r['intensity']==='medium'?'warning':'info') ?>"><?= ucfirst($r['intensity']) ?></span></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script src="../js/main.js"></script>
</body>
</html>
