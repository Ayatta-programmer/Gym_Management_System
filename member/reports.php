<?php
require_once __DIR__ . '/../auth/auth_check.php';
checkAuth(['member']);

$memberId = $_SESSION['user_id'];

// Monthly attendance data
$attData = $pdo->prepare("
    SELECT TO_CHAR(check_in, 'YYYY-MM') as month, COUNT(*) as count 
    FROM attendance WHERE user_id = ? 
    GROUP BY TO_CHAR(check_in, 'YYYY-MM') 
    ORDER BY month DESC LIMIT 6
");
$attData->execute([$memberId]);
$monthlyAttendance = array_reverse($attData->fetchAll());

// Monthly calories data
$calData = $pdo->prepare("
    SELECT TO_CHAR(workout_date, 'YYYY-MM') as month, SUM(calories_burnt) as total 
    FROM calories WHERE user_id = ? 
    GROUP BY TO_CHAR(workout_date, 'YYYY-MM') 
    ORDER BY month DESC LIMIT 6
");
$calData->execute([$memberId]);
$monthlyCalories = array_reverse($calData->fetchAll());

// Activity breakdown
$actData = $pdo->prepare("
    SELECT activity, SUM(calories_burnt) as total, COUNT(*) as sessions 
    FROM calories WHERE user_id = ? 
    GROUP BY activity ORDER BY total DESC
");
$actData->execute([$memberId]);
$activityBreakdown = $actData->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Reports - <?= APP_NAME ?></title>
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
        <h1>My Reports</h1>
        <p>Track your personal fitness progress</p>
      </div>
      <button class="btn btn-primary" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
    </div>

    <div class="charts-grid">
      <div class="card">
        <div class="card-header"><h3>Monthly Attendance</h3></div>
        <div class="card-body"><canvas id="attChart"></canvas></div>
      </div>
      <div class="card">
        <div class="card-header"><h3>Monthly Calories Burnt</h3></div>
        <div class="card-body"><canvas id="calChart"></canvas></div>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><h3>Activity Breakdown</h3></div>
      <div class="card-body no-padding">
        <table class="data-table">
          <thead><tr><th>Activity</th><th>Sessions</th><th>Total Calories</th></tr></thead>
          <tbody>
            <?php if (empty($activityBreakdown)): ?>
              <tr><td colspan="3" class="text-center" style="padding:2rem;color:var(--gray-500)">No data yet</td></tr>
            <?php else: ?>
              <?php foreach ($activityBreakdown as $a): ?>
                <tr>
                  <td><?= htmlspecialchars($a['activity']) ?></td>
                  <td><?= $a['sessions'] ?></td>
                  <td><strong style="color:var(--primary)"><?= number_format($a['total']) ?> kcal</strong></td>
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
<script>
new Chart(document.getElementById('attChart'), {
  type: 'line',
  data: {
    labels: <?= json_encode(array_map(fn($r) => date('M Y', strtotime($r['month'].'-01')), $monthlyAttendance)) ?>,
    datasets: [{
      label: 'Check-ins', data: <?= json_encode(array_map(fn($r) => (int)$r['count'], $monthlyAttendance)) ?>,
      borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,0.1)', fill: true, tension: 0.4, pointRadius: 5
    }]
  },
  options: { responsive: true, plugins: { legend: { display: false } },
    scales: { y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#9090a0' } }, x: { grid: { display: false }, ticks: { color: '#9090a0' } } }
  }
});

new Chart(document.getElementById('calChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode(array_map(fn($r) => date('M Y', strtotime($r['month'].'-01')), $monthlyCalories)) ?>,
    datasets: [{
      label: 'Calories', data: <?= json_encode(array_map(fn($r) => (float)$r['total'], $monthlyCalories)) ?>,
      backgroundColor: 'rgba(255,107,53,0.6)', borderColor: '#ff6b35', borderWidth: 2, borderRadius: 8
    }]
  },
  options: { responsive: true, plugins: { legend: { display: false } },
    scales: { y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#9090a0' } }, x: { grid: { display: false }, ticks: { color: '#9090a0' } } }
  }
});
</script>
</body>
</html>
