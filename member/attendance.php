<?php
require_once __DIR__ . '/../auth/auth_check.php';
checkAuth(['member']);

$memberId = $_SESSION['user_id'];
$filterMonth = sanitize($_GET['month'] ?? date('Y-m'));

$stmt = $pdo->prepare("
    SELECT * FROM attendance 
    WHERE user_id = ? AND TO_CHAR(check_in, 'YYYY-MM') = ? 
    ORDER BY check_in DESC
");
$stmt->execute([$memberId, $filterMonth]);
$records = $stmt->fetchAll();

$totalDays = count($records);
$totalHours = 0;
foreach ($records as $r) {
    if ($r['check_out']) {
        $totalHours += (strtotime($r['check_out']) - strtotime($r['check_in'])) / 3600;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Attendance - <?= APP_NAME ?></title>
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
        <h1>My Attendance</h1>
        <p>View your gym attendance history</p>
      </div>
    </div>

    <div class="stats-grid" style="grid-template-columns: repeat(2,1fr)">
      <div class="stat-card">
        <div class="stat-header">
          <div><div class="stat-value"><?= $totalDays ?></div><div class="stat-label">Days This Month</div></div>
          <div class="stat-icon green"><i class="fas fa-calendar-check"></i></div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-header">
          <div><div class="stat-value"><?= round($totalHours, 1) ?></div><div class="stat-label">Total Hours</div></div>
          <div class="stat-icon blue"><i class="fas fa-clock"></i></div>
        </div>
      </div>
    </div>

    <div class="card mb-2">
      <div class="card-body">
        <form method="GET" style="display:flex;gap:1rem;align-items:flex-end">
          <div class="form-group" style="margin:0">
            <label>Month</label>
            <input type="month" name="month" class="form-control" value="<?= $filterMonth ?>">
          </div>
          <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filter</button>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><h3>Attendance History (<?= $totalDays ?>)</h3></div>
      <div class="card-body no-padding">
        <table class="data-table">
          <thead><tr><th>Date</th><th>Check In</th><th>Check Out</th><th>Duration</th><th>Status</th></tr></thead>
          <tbody>
            <?php if (empty($records)): ?>
              <tr><td colspan="5" class="text-center" style="padding:2rem;color:var(--gray-500)">No records this month</td></tr>
            <?php else: ?>
              <?php foreach ($records as $r):
                $dur = '—';
                if ($r['check_out']) {
                    $d = strtotime($r['check_out']) - strtotime($r['check_in']);
                    $dur = floor($d/3600).'h '.floor(($d%3600)/60).'m';
                }
              ?>
                <tr>
                  <td><?= date('M j, Y (D)', strtotime($r['check_in'])) ?></td>
                  <td><?= date('g:i A', strtotime($r['check_in'])) ?></td>
                  <td><?= $r['check_out'] ? date('g:i A', strtotime($r['check_out'])) : '<span class="badge badge-warning">—</span>' ?></td>
                  <td><?= $dur ?></td>
                  <td><span class="badge badge-success"><?= ucfirst($r['status']) ?></span></td>
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
