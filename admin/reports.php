<?php
require_once __DIR__ . '/../auth/auth_check.php';
checkAuth(['admin']);

// Prepare report data
$reportType = sanitize($_GET['type'] ?? 'membership');
$dateFrom = sanitize($_GET['from'] ?? date('Y-m-01'));
$dateTo = sanitize($_GET['to'] ?? date('Y-m-d'));

// Membership Report
$membershipData = $pdo->query("
    SELECT 
        role, 
        COUNT(*) as count, 
        SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN status='inactive' THEN 1 ELSE 0 END) as inactive,
        SUM(CASE WHEN status='suspended' THEN 1 ELSE 0 END) as suspended
    FROM users GROUP BY role
")->fetchAll();

$planData = $pdo->query("
    SELECT membership_plan, COUNT(*) as count FROM users WHERE role = 'member' GROUP BY membership_plan
")->fetchAll();

// Attendance Report
$attendanceStmt = $pdo->prepare("
    SELECT DATE(check_in) as date, COUNT(DISTINCT user_id) as checkins 
    FROM attendance 
    WHERE DATE(check_in) BETWEEN ? AND ? 
    GROUP BY DATE(check_in) 
    ORDER BY date
");
$attendanceStmt->execute([$dateFrom, $dateTo]);
$attendanceReport = $attendanceStmt->fetchAll();

// Revenue Report
$revenueStmt = $pdo->prepare("
    SELECT TO_CHAR(created_at, 'YYYY-MM') as month, 
           SUM(CASE WHEN status='paid' THEN total ELSE 0 END) as revenue,
           SUM(CASE WHEN status='pending' THEN total ELSE 0 END) as pending,
           COUNT(*) as invoice_count
    FROM invoices 
    WHERE DATE(created_at) BETWEEN ? AND ? 
    GROUP BY TO_CHAR(created_at, 'YYYY-MM') 
    ORDER BY month
");
$revenueStmt->execute([$dateFrom, $dateTo]);
$revenueReport = $revenueStmt->fetchAll();

// Calories Report
$caloriesStmt = $pdo->prepare("
    SELECT activity, SUM(calories_burnt) as total_calories, COUNT(*) as sessions, AVG(duration_minutes) as avg_duration
    FROM calories 
    WHERE workout_date BETWEEN ? AND ? 
    GROUP BY activity 
    ORDER BY total_calories DESC
");
$caloriesStmt->execute([$dateFrom, $dateTo]);
$caloriesReport = $caloriesStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reports - <?= APP_NAME ?></title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<div class="dashboard">
  <?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>

  <div class="main-content">
    <div class="main-header">
      <div>
        <button class="sidebar-toggle" id="sidebarToggle"><i class="fas fa-bars"></i></button>
        <h1>System Reports</h1>
        <p>Generate and view comprehensive analytics</p>
      </div>
      <button class="btn btn-primary" onclick="window.print()">
        <i class="fas fa-print"></i> Print Report
      </button>
    </div>

    <!-- Report Type Tabs & Date Filter -->
    <div class="card mb-2">
      <div class="card-body">
        <form method="GET" style="display:flex;gap:1rem;flex-wrap:wrap;align-items:flex-end">
          <div class="form-group" style="margin:0">
            <label>Report Type</label>
            <select name="type" class="form-control">
              <option value="membership" <?= $reportType==='membership'?'selected':'' ?>>Membership</option>
              <option value="attendance" <?= $reportType==='attendance'?'selected':'' ?>>Attendance</option>
              <option value="revenue" <?= $reportType==='revenue'?'selected':'' ?>>Revenue</option>
              <option value="calories" <?= $reportType==='calories'?'selected':'' ?>>Calories</option>
            </select>
          </div>
          <div class="form-group" style="margin:0">
            <label>From</label>
            <input type="date" name="from" class="form-control" value="<?= $dateFrom ?>">
          </div>
          <div class="form-group" style="margin:0">
            <label>To</label>
            <input type="date" name="to" class="form-control" value="<?= $dateTo ?>">
          </div>
          <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-chart-line"></i> Generate</button>
        </form>
      </div>
    </div>

    <?php if ($reportType === 'membership'): ?>
    <!-- Membership Report -->
    <div class="charts-grid">
      <div class="card">
        <div class="card-header"><h3>Users by Role</h3></div>
        <div class="card-body">
          <canvas id="roleChart"></canvas>
        </div>
      </div>
      <div class="card">
        <div class="card-header"><h3>Membership Plans</h3></div>
        <div class="card-body">
          <canvas id="planChart"></canvas>
        </div>
      </div>
    </div>
    <div class="card">
      <div class="card-header"><h3>Membership Summary</h3></div>
      <div class="card-body no-padding">
        <table class="data-table">
          <thead>
            <tr><th>Role</th><th>Total</th><th>Active</th><th>Inactive</th><th>Suspended</th></tr>
          </thead>
          <tbody>
            <?php foreach ($membershipData as $r): ?>
              <tr>
                <td><strong><?= ucfirst($r['role']) ?></strong></td>
                <td><?= $r['count'] ?></td>
                <td><span class="badge badge-success"><?= $r['active'] ?></span></td>
                <td><span class="badge badge-warning"><?= $r['inactive'] ?></span></td>
                <td><span class="badge badge-danger"><?= $r['suspended'] ?></span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <script>
    new Chart(document.getElementById('roleChart'), {
      type: 'pie',
      data: {
        labels: <?= json_encode(array_map(fn($r) => ucfirst($r['role']), $membershipData)) ?>,
        datasets: [{
          data: <?= json_encode(array_map(fn($r) => (int)$r['count'], $membershipData)) ?>,
          backgroundColor: ['#ff6b35', '#3b82f6', '#10b981'],
          borderWidth: 0
        }]
      },
      options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { color: '#9090a0' } } } }
    });

    new Chart(document.getElementById('planChart'), {
      type: 'doughnut',
      data: {
        labels: <?= json_encode(array_map(fn($r) => ucfirst($r['membership_plan'] ?: 'Basic'), $planData)) ?>,
        datasets: [{
          data: <?= json_encode(array_map(fn($r) => (int)$r['count'], $planData)) ?>,
          backgroundColor: ['#f59e0b', '#3b82f6', '#10b981'],
          borderWidth: 0
        }]
      },
      options: { responsive: true, cutout: '60%', plugins: { legend: { position: 'bottom', labels: { color: '#9090a0' } } } }
    });
    </script>

    <?php elseif ($reportType === 'attendance'): ?>
    <!-- Attendance Report -->
    <div class="card mb-2">
      <div class="card-header"><h3>Daily Attendance Trend</h3></div>
      <div class="card-body"><canvas id="attChart" height="100"></canvas></div>
    </div>
    <div class="card">
      <div class="card-header"><h3>Attendance Details</h3></div>
      <div class="card-body no-padding">
        <table class="data-table">
          <thead><tr><th>Date</th><th>Check-ins</th></tr></thead>
          <tbody>
            <?php if (empty($attendanceReport)): ?>
              <tr><td colspan="2" class="text-center" style="padding:2rem;color:var(--gray-500)">No data for this period</td></tr>
            <?php else: ?>
              <?php foreach ($attendanceReport as $r): ?>
                <tr>
                  <td><?= date('M j, Y (l)', strtotime($r['date'])) ?></td>
                  <td><strong><?= $r['checkins'] ?></strong></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <script>
    new Chart(document.getElementById('attChart'), {
      type: 'line',
      data: {
        labels: <?= json_encode(array_map(fn($r) => date('M j', strtotime($r['date'])), $attendanceReport)) ?>,
        datasets: [{
          label: 'Check-ins',
          data: <?= json_encode(array_map(fn($r) => (int)$r['checkins'], $attendanceReport)) ?>,
          borderColor: '#ff6b35',
          backgroundColor: 'rgba(255,107,53,0.1)',
          fill: true,
          tension: 0.4,
          pointBackgroundColor: '#ff6b35',
          pointRadius: 4
        }]
      },
      options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
          y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#9090a0' } },
          x: { grid: { display: false }, ticks: { color: '#9090a0' } }
        }
      }
    });
    </script>

    <?php elseif ($reportType === 'revenue'): ?>
    <!-- Revenue Report -->
    <div class="card mb-2">
      <div class="card-header"><h3>Monthly Revenue</h3></div>
      <div class="card-body"><canvas id="revChart" height="100"></canvas></div>
    </div>
    <div class="card">
      <div class="card-header"><h3>Revenue Details</h3></div>
      <div class="card-body no-padding">
        <table class="data-table">
          <thead><tr><th>Month</th><th>Invoices</th><th>Revenue</th><th>Pending</th></tr></thead>
          <tbody>
            <?php if (empty($revenueReport)): ?>
              <tr><td colspan="4" class="text-center" style="padding:2rem;color:var(--gray-500)">No data for this period</td></tr>
            <?php else: ?>
              <?php foreach ($revenueReport as $r): ?>
                <tr>
                  <td><?= date('F Y', strtotime($r['month'] . '-01')) ?></td>
                  <td><?= $r['invoice_count'] ?></td>
                  <td><strong style="color:var(--success)"><?= formatCurrency($r['revenue']) ?></strong></td>
                  <td><span style="color:var(--warning)"><?= formatCurrency($r['pending']) ?></span></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <script>
    new Chart(document.getElementById('revChart'), {
      type: 'bar',
      data: {
        labels: <?= json_encode(array_map(fn($r) => date('M Y', strtotime($r['month'].'-01')), $revenueReport)) ?>,
        datasets: [
          { label: 'Revenue', data: <?= json_encode(array_map(fn($r) => (float)$r['revenue'], $revenueReport)) ?>, backgroundColor: '#10b981', borderRadius: 8 },
          { label: 'Pending', data: <?= json_encode(array_map(fn($r) => (float)$r['pending'], $revenueReport)) ?>, backgroundColor: '#f59e0b', borderRadius: 8 }
        ]
      },
      options: {
        responsive: true,
        plugins: { legend: { labels: { color: '#9090a0' } } },
        scales: {
          y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#9090a0' } },
          x: { grid: { display: false }, ticks: { color: '#9090a0' } }
        }
      }
    });
    </script>

    <?php elseif ($reportType === 'calories'): ?>
    <!-- Calories Report -->
    <div class="card mb-2">
      <div class="card-header"><h3>Calories by Activity</h3></div>
      <div class="card-body"><canvas id="calChart" height="100"></canvas></div>
    </div>
    <div class="card">
      <div class="card-header"><h3>Activity Breakdown</h3></div>
      <div class="card-body no-padding">
        <table class="data-table">
          <thead><tr><th>Activity</th><th>Sessions</th><th>Total Calories</th><th>Avg Duration</th></tr></thead>
          <tbody>
            <?php if (empty($caloriesReport)): ?>
              <tr><td colspan="4" class="text-center" style="padding:2rem;color:var(--gray-500)">No data for this period</td></tr>
            <?php else: ?>
              <?php foreach ($caloriesReport as $r): ?>
                <tr>
                  <td><?= htmlspecialchars($r['activity']) ?></td>
                  <td><?= $r['sessions'] ?></td>
                  <td><strong style="color:var(--primary)"><?= number_format($r['total_calories']) ?> kcal</strong></td>
                  <td><?= round($r['avg_duration']) ?> min</td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <script>
    new Chart(document.getElementById('calChart'), {
      type: 'bar',
      data: {
        labels: <?= json_encode(array_map(fn($r) => $r['activity'], $caloriesReport)) ?>,
        datasets: [{
          label: 'Calories Burnt',
          data: <?= json_encode(array_map(fn($r) => (float)$r['total_calories'], $caloriesReport)) ?>,
          backgroundColor: 'rgba(255,107,53,0.7)',
          borderColor: '#ff6b35',
          borderWidth: 2,
          borderRadius: 8
        }]
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
          x: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#9090a0' } },
          y: { grid: { display: false }, ticks: { color: '#9090a0' } }
        }
      }
    });
    </script>
    <?php endif; ?>

  </div>
</div>

<script src="../js/main.js"></script>
</body>
</html>
