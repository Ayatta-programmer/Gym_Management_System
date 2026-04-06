<?php
require_once __DIR__ . '/../auth/auth_check.php';
checkAuth(['member']);

$memberId = $_SESSION['user_id'];

// Stats
$attendanceCount = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE user_id = ? AND EXTRACT(MONTH FROM check_in) = EXTRACT(MONTH FROM CURRENT_DATE) AND EXTRACT(YEAR FROM check_in) = EXTRACT(YEAR FROM CURRENT_DATE)");
$attendanceCount->execute([$memberId]);
$monthAttendance = $attendanceCount->fetchColumn();

$totalCalories = $pdo->prepare("SELECT COALESCE(SUM(calories_burnt), 0) FROM calories WHERE user_id = ? AND EXTRACT(MONTH FROM workout_date) = EXTRACT(MONTH FROM CURRENT_DATE) AND EXTRACT(YEAR FROM workout_date) = EXTRACT(YEAR FROM CURRENT_DATE)");
$totalCalories->execute([$memberId]);
$monthCalories = $totalCalories->fetchColumn();

$pendingBills = $pdo->prepare("SELECT COALESCE(SUM(total), 0) FROM invoices WHERE user_id = ? AND status IN ('pending','overdue')");
$pendingBills->execute([$memberId]);
$pendingAmount = $pendingBills->fetchColumn();

// Attendance streak
$streakStmt = $pdo->prepare("SELECT DISTINCT DATE(check_in) as d FROM attendance WHERE user_id = ? ORDER BY d DESC LIMIT 30");
$streakStmt->execute([$memberId]);
$dates = $streakStmt->fetchAll(PDO::FETCH_COLUMN);
$streak = 0;
$today = new DateTime();
foreach ($dates as $d) {
    $expected = (clone $today)->modify("-{$streak} days")->format('Y-m-d');
    if ($d === $expected) { $streak++; } else { break; }
}

// Recent activity
$recentAtt = $pdo->prepare("SELECT * FROM attendance WHERE user_id = ? ORDER BY check_in DESC LIMIT 5");
$recentAtt->execute([$memberId]);
$recentAttendance = $recentAtt->fetchAll();

$recentCal = $pdo->prepare("SELECT * FROM calories WHERE user_id = ? ORDER BY workout_date DESC LIMIT 5");
$recentCal->execute([$memberId]);
$recentCalories = $recentCal->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Dashboard - <?= APP_NAME ?></title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<div class="dashboard">
  <?php require_once __DIR__ . '/../includes/member_sidebar.php'; ?>

  <div class="main-content">
    <div class="main-header">
      <div>
        <button class="sidebar-toggle" id="sidebarToggle"><i class="fas fa-bars"></i></button>
        <h1>My Dashboard</h1>
        <p>Welcome back, <?= htmlspecialchars($_SESSION['user_name']) ?>!</p>
      </div>
      <span style="color:var(--gray-400);font-size:0.85rem"><i class="fas fa-calendar"></i> <?= date('l, F j, Y') ?></span>
    </div>

    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-header">
          <div>
            <div class="stat-value"><?= $streak ?></div>
            <div class="stat-label">Day Streak 🔥</div>
          </div>
          <div class="stat-icon orange"><i class="fas fa-fire"></i></div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-header">
          <div>
            <div class="stat-value"><?= $monthAttendance ?></div>
            <div class="stat-label">This Month's Check-ins</div>
          </div>
          <div class="stat-icon green"><i class="fas fa-calendar-check"></i></div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-header">
          <div>
            <div class="stat-value"><?= number_format($monthCalories) ?></div>
            <div class="stat-label">Calories Burnt (Month)</div>
          </div>
          <div class="stat-icon blue"><i class="fas fa-fire-flame-curved"></i></div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-header">
          <div>
            <div class="stat-value"><?= formatCurrency($pendingAmount) ?></div>
            <div class="stat-label">Outstanding Balance</div>
          </div>
          <div class="stat-icon yellow"><i class="fas fa-file-invoice-dollar"></i></div>
        </div>
      </div>
    </div>

    <div class="charts-grid">
      <div class="card">
        <div class="card-header">
          <h3>Recent Attendance</h3>
          <a href="attendance.php" class="btn btn-sm btn-ghost">View All <i class="fas fa-arrow-right"></i></a>
        </div>
        <div class="card-body no-padding">
          <table class="data-table">
            <thead><tr><th>Date</th><th>Check In</th><th>Check Out</th><th>Status</th></tr></thead>
            <tbody>
              <?php if (empty($recentAttendance)): ?>
                <tr><td colspan="4" class="text-center" style="padding:2rem;color:var(--gray-500)">No records yet</td></tr>
              <?php else: ?>
                <?php foreach ($recentAttendance as $a): ?>
                  <tr>
                    <td><?= date('M j, Y', strtotime($a['check_in'])) ?></td>
                    <td><?= date('g:i A', strtotime($a['check_in'])) ?></td>
                    <td><?= $a['check_out'] ? date('g:i A', strtotime($a['check_out'])) : '<span class="badge badge-warning">—</span>' ?></td>
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
          <h3>Recent Workouts</h3>
          <a href="calories.php" class="btn btn-sm btn-ghost">View All <i class="fas fa-arrow-right"></i></a>
        </div>
        <div class="card-body no-padding">
          <table class="data-table">
            <thead><tr><th>Activity</th><th>Duration</th><th>Calories</th><th>Date</th></tr></thead>
            <tbody>
              <?php if (empty($recentCalories)): ?>
                <tr><td colspan="4" class="text-center" style="padding:2rem;color:var(--gray-500)">No records yet</td></tr>
              <?php else: ?>
                <?php foreach ($recentCalories as $c): ?>
                  <tr>
                    <td><?= htmlspecialchars($c['activity']) ?></td>
                    <td><?= $c['duration_minutes'] ?> min</td>
                    <td><strong style="color:var(--primary)"><?= number_format($c['calories_burnt']) ?> kcal</strong></td>
                    <td><?= date('M j', strtotime($c['workout_date'])) ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Weekly Calories Chart -->
    <div class="card">
      <div class="card-header"><h3>This Week's Calories</h3></div>
      <div class="card-body"><canvas id="weeklyCalChart"></canvas></div>
    </div>
  </div>
</div>

<script src="../js/main.js"></script>
<script>
<?php
$weekData = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $l = date('D', strtotime("-$i days"));
    $s = $pdo->prepare("SELECT COALESCE(SUM(calories_burnt),0) FROM calories WHERE user_id = ? AND workout_date = ?");
    $s->execute([$memberId, $d]);
    $weekData[] = ['label' => $l, 'val' => (float)$s->fetchColumn()];
}
?>
new Chart(document.getElementById('weeklyCalChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode(array_column($weekData, 'label')) ?>,
    datasets: [{
      label: 'Calories',
      data: <?= json_encode(array_column($weekData, 'val')) ?>,
      backgroundColor: 'rgba(255,107,53,0.6)', borderColor: '#ff6b35', borderWidth: 2, borderRadius: 8
    }]
  },
  options: {
    responsive: true, plugins: { legend: { display: false } },
    scales: {
      y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#9090a0' } },
      x: { grid: { display: false }, ticks: { color: '#9090a0' } }
    }
  }
});
</script>
</body>
</html>
