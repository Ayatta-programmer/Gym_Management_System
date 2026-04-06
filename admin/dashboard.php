<?php
require_once __DIR__ . '/../auth/auth_check.php';
checkAuth(['admin']);

// Fetch stats
$totalMembers = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'member'")->fetchColumn();
$totalTrainers = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'trainer'")->fetchColumn();
$activeToday = $pdo->query("SELECT COUNT(DISTINCT user_id) FROM attendance WHERE DATE(check_in) = CURRENT_DATE")->fetchColumn();
$pendingInvoices = $pdo->query("SELECT COUNT(*) FROM invoices WHERE status = 'pending'")->fetchColumn();
$totalRevenue = $pdo->query("SELECT COALESCE(SUM(total), 0) FROM invoices WHERE status = 'paid'")->fetchColumn();
$todayCalories = $pdo->query("SELECT COALESCE(SUM(calories_burnt), 0) FROM calories WHERE workout_date = CURRENT_DATE")->fetchColumn();

// Recent members
$recentMembers = $pdo->query("SELECT id, full_name, email, role, status, joined_date FROM users WHERE role IN ('member','trainer') ORDER BY joined_date DESC LIMIT 5")->fetchAll();

// Recent attendance
$recentAttendance = $pdo->query("
    SELECT a.*, u.full_name FROM attendance a 
    JOIN users u ON a.user_id = u.id 
    ORDER BY a.check_in DESC LIMIT 5
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard - <?= APP_NAME ?></title>
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
        <h1>Dashboard</h1>
        <p>Welcome back, <?= htmlspecialchars($_SESSION['user_name']) ?>! Here's an overview.</p>
      </div>
      <div>
        <span style="color: var(--gray-400); font-size: 0.85rem">
          <i class="fas fa-calendar"></i> <?= date('l, F j, Y') ?>
        </span>
      </div>
    </div>

    <!-- Stats Cards -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-header">
          <div>
            <div class="stat-value"><?= $totalMembers ?></div>
            <div class="stat-label">Total Members</div>
          </div>
          <div class="stat-icon orange"><i class="fas fa-users"></i></div>
        </div>
        <div class="stat-change positive"><i class="fas fa-arrow-up"></i> Active accounts</div>
      </div>

      <div class="stat-card">
        <div class="stat-header">
          <div>
            <div class="stat-value"><?= $activeToday ?></div>
            <div class="stat-label">Active Today</div>
          </div>
          <div class="stat-icon green"><i class="fas fa-calendar-check"></i></div>
        </div>
        <div class="stat-change positive"><i class="fas fa-check-circle"></i> Checked in today</div>
      </div>

      <div class="stat-card">
        <div class="stat-header">
          <div>
            <div class="stat-value"><?= formatCurrency($totalRevenue) ?></div>
            <div class="stat-label">Total Revenue</div>
          </div>
          <div class="stat-icon blue"><i class="fas fa-money-bill-wave"></i></div>
        </div>
        <div class="stat-change positive"><i class="fas fa-arrow-up"></i> From paid invoices</div>
      </div>

      <div class="stat-card">
        <div class="stat-header">
          <div>
            <div class="stat-value"><?= $pendingInvoices ?></div>
            <div class="stat-label">Pending Invoices</div>
          </div>
          <div class="stat-icon yellow"><i class="fas fa-clock"></i></div>
        </div>
        <div class="stat-change negative"><i class="fas fa-exclamation-circle"></i> Awaiting payment</div>
      </div>
    </div>

    <!-- Charts Row -->
    <div class="charts-grid">
      <div class="card">
        <div class="card-header">
          <h3>Attendance Overview (Last 7 Days)</h3>
        </div>
        <div class="card-body">
          <div class="chart-container">
            <canvas id="attendanceChart"></canvas>
          </div>
        </div>
      </div>
      <div class="card">
        <div class="card-header">
          <h3>Revenue Breakdown</h3>
        </div>
        <div class="card-body">
          <div class="chart-container">
            <canvas id="revenueChart"></canvas>
          </div>
        </div>
      </div>
    </div>

    <!-- Recent Members Table -->
    <div class="card mb-2">
      <div class="card-header">
        <h3>Recent Members</h3>
        <a href="members.php" class="btn btn-sm btn-ghost">View All <i class="fas fa-arrow-right"></i></a>
      </div>
      <div class="card-body no-padding">
        <table class="data-table">
          <thead>
            <tr>
              <th>Name</th>
              <th>Email</th>
              <th>Role</th>
              <th>Status</th>
              <th>Joined</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($recentMembers)): ?>
              <tr><td colspan="5" class="text-center" style="padding:2rem;color:var(--gray-500)">No members yet</td></tr>
            <?php else: ?>
              <?php foreach ($recentMembers as $m): ?>
                <?php
                  $parts = explode(' ', $m['full_name']);
                  $initials = strtoupper(substr($parts[0],0,1).(isset($parts[1])?substr($parts[1],0,1):''));
                ?>
                <tr>
                  <td>
                    <div class="user-cell">
                      <div class="user-avatar"><?= $initials ?></div>
                      <span><?= htmlspecialchars($m['full_name']) ?></span>
                    </div>
                  </td>
                  <td><?= htmlspecialchars($m['email']) ?></td>
                  <td><span class="badge badge-info"><?= ucfirst($m['role']) ?></span></td>
                  <td><span class="badge badge-<?= $m['status']==='active'?'success':($m['status']==='inactive'?'warning':'danger') ?>"><?= ucfirst($m['status']) ?></span></td>
                  <td><?= date('M j, Y', strtotime($m['joined_date'])) ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Recent Attendance -->
    <div class="card">
      <div class="card-header">
        <h3>Recent Attendance</h3>
        <a href="attendance.php" class="btn btn-sm btn-ghost">View All <i class="fas fa-arrow-right"></i></a>
      </div>
      <div class="card-body no-padding">
        <table class="data-table">
          <thead>
            <tr>
              <th>Member</th>
              <th>Check In</th>
              <th>Check Out</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($recentAttendance)): ?>
              <tr><td colspan="4" class="text-center" style="padding:2rem;color:var(--gray-500)">No attendance records yet</td></tr>
            <?php else: ?>
              <?php foreach ($recentAttendance as $a): ?>
                <tr>
                  <td><?= htmlspecialchars($a['full_name']) ?></td>
                  <td><?= date('M j, g:i A', strtotime($a['check_in'])) ?></td>
                  <td><?= $a['check_out'] ? date('M j, g:i A', strtotime($a['check_out'])) : '<span class="badge badge-warning">Still In</span>' ?></td>
                  <td><span class="badge badge-<?= $a['status']==='present'?'success':($a['status']==='late'?'warning':'danger') ?>"><?= ucfirst($a['status']) ?></span></td>
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
// Attendance Chart
<?php
$attendanceDays = [];
$attendanceCounts = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $label = date('D', strtotime("-$i days"));
    $count = $pdo->prepare("SELECT COUNT(DISTINCT user_id) FROM attendance WHERE DATE(check_in) = CAST(? AS DATE)");
    $count->execute([$date]);
    $attendanceDays[] = $label;
    $attendanceCounts[] = (int) $count->fetchColumn();
}
?>

const attCtx = document.getElementById('attendanceChart').getContext('2d');
new Chart(attCtx, {
  type: 'bar',
  data: {
    labels: <?= json_encode($attendanceDays) ?>,
    datasets: [{
      label: 'Check-ins',
      data: <?= json_encode($attendanceCounts) ?>,
      backgroundColor: 'rgba(255, 107, 53, 0.6)',
      borderColor: '#ff6b35',
      borderWidth: 2,
      borderRadius: 8,
      borderSkipped: false
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

// Revenue Chart
<?php
$paid = $pdo->query("SELECT COALESCE(SUM(total),0) FROM invoices WHERE status='paid'")->fetchColumn();
$pending = $pdo->query("SELECT COALESCE(SUM(total),0) FROM invoices WHERE status='pending'")->fetchColumn();
$overdue = $pdo->query("SELECT COALESCE(SUM(total),0) FROM invoices WHERE status='overdue'")->fetchColumn();
?>

const revCtx = document.getElementById('revenueChart').getContext('2d');
new Chart(revCtx, {
  type: 'doughnut',
  data: {
    labels: ['Paid', 'Pending', 'Overdue'],
    datasets: [{
      data: [<?= $paid ?>, <?= $pending ?>, <?= $overdue ?>],
      backgroundColor: ['#10b981', '#f59e0b', '#ef4444'],
      borderWidth: 0,
      spacing: 4
    }]
  },
  options: {
    responsive: true,
    plugins: {
      legend: { position: 'bottom', labels: { color: '#9090a0', padding: 15, usePointStyle: true } }
    },
    cutout: '65%'
  }
});
</script>
</body>
</html>
