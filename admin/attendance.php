<?php
require_once __DIR__ . '/../auth/auth_check.php';
checkAuth(['admin']);

$flash = getFlash();

// Handle check-in/check-out
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'checkin') {
        $userId = (int) $_POST['user_id'];
        $notes = sanitize($_POST['notes'] ?? '');

        // Check if already checked in today without checkout
        $existing = $pdo->prepare("SELECT id FROM attendance WHERE user_id = ? AND DATE(check_in) = CURRENT_DATE AND check_out IS NULL");
        $existing->execute([$userId]);
        if ($existing->fetch()) {
            setFlash('warning', 'This member is already checked in today.');
        } else {
            $stmt = $pdo->prepare('INSERT INTO attendance (user_id, check_in, status, notes, recorded_by) VALUES (?, NOW(), ?, ?, ?)');
            $stmt->execute([$userId, 'present', $notes, $_SESSION['user_id']]);
            setFlash('success', 'Check-in recorded successfully.');
        }
        redirect('attendance.php');
    }

    if ($action === 'checkout') {
        $attId = (int) $_POST['att_id'];
        $stmt = $pdo->prepare('UPDATE attendance SET check_out = NOW() WHERE id = ? AND check_out IS NULL');
        $stmt->execute([$attId]);
        setFlash('success', 'Check-out recorded successfully.');
        redirect('attendance.php');
    }

    if ($action === 'delete') {
        $attId = (int) $_POST['att_id'];
        $pdo->prepare('DELETE FROM attendance WHERE id = ?')->execute([$attId]);
        setFlash('success', 'Attendance record deleted.');
        redirect('attendance.php');
    }
}

// Filters
$filterDate = sanitize($_GET['date'] ?? date('Y-m-d'));
$filterMember = sanitize($_GET['member'] ?? '');

$sql = "SELECT a.*, u.full_name, u.email FROM attendance a JOIN users u ON a.user_id = u.id WHERE DATE(a.check_in) = ?";
$params = [$filterDate];

if ($filterMember) {
    $sql .= " AND u.full_name LIKE ?";
    $params[] = "%$filterMember%";
}
$sql .= " ORDER BY a.check_in DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll();

// Get all active members for check-in dropdown
$allMembers = $pdo->query("SELECT id, full_name FROM users WHERE role IN ('member','trainer') AND status = 'active' ORDER BY full_name")->fetchAll();

// Today's stats
$todayCheckins = $pdo->query("SELECT COUNT(DISTINCT user_id) FROM attendance WHERE DATE(check_in) = CURRENT_DATE")->fetchColumn();
$stillIn = $pdo->query("SELECT COUNT(*) FROM attendance WHERE DATE(check_in) = CURRENT_DATE AND check_out IS NULL")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Attendance Monitoring - <?= APP_NAME ?></title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>

<div class="dashboard">
  <?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>

  <div class="main-content">
    <div class="main-header">
      <div>
        <button class="sidebar-toggle" id="sidebarToggle"><i class="fas fa-bars"></i></button>
        <h1>Attendance Monitoring</h1>
        <p>Track member check-ins and check-outs</p>
      </div>
      <button class="btn btn-primary" onclick="openModal('checkinModal')">
        <i class="fas fa-sign-in-alt"></i> New Check-In
      </button>
    </div>

    <?php if ($flash): ?>
      <div class="alert alert-<?= $flash['type'] ?>">
        <i class="fas fa-<?= $flash['type']==='success'?'check':($flash['type']==='warning'?'exclamation':'exclamation') ?>-circle"></i>
        <?= $flash['message'] ?>
      </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-grid" style="grid-template-columns: repeat(2, 1fr)">
      <div class="stat-card">
        <div class="stat-header">
          <div>
            <div class="stat-value"><?= $todayCheckins ?></div>
            <div class="stat-label">Today's Check-ins</div>
          </div>
          <div class="stat-icon green"><i class="fas fa-calendar-check"></i></div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-header">
          <div>
            <div class="stat-value"><?= $stillIn ?></div>
            <div class="stat-label">Currently In Gym</div>
          </div>
          <div class="stat-icon orange"><i class="fas fa-person-running"></i></div>
        </div>
      </div>
    </div>

    <!-- Filter -->
    <div class="card mb-2">
      <div class="card-body">
        <form method="GET" style="display:flex;gap:1rem;flex-wrap:wrap;align-items:flex-end">
          <div class="form-group" style="margin:0">
            <label>Date</label>
            <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($filterDate) ?>">
          </div>
          <div class="form-group" style="margin:0;flex:1;min-width:200px">
            <label>Member Name</label>
            <input type="text" name="member" class="form-control" placeholder="Search member..." value="<?= htmlspecialchars($filterMember) ?>">
          </div>
          <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Filter</button>
          <a href="attendance.php" class="btn btn-ghost btn-sm"><i class="fas fa-times"></i> Clear</a>
        </form>
      </div>
    </div>

    <!-- Attendance Table -->
    <div class="card">
      <div class="card-header">
        <h3>Attendance Records — <?= date('M j, Y', strtotime($filterDate)) ?> (<?= count($records) ?>)</h3>
      </div>
      <div class="card-body no-padding">
        <table class="data-table">
          <thead>
            <tr>
              <th>Member</th>
              <th>Check In</th>
              <th>Check Out</th>
              <th>Duration</th>
              <th>Status</th>
              <th>Notes</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($records)): ?>
              <tr><td colspan="7" class="text-center" style="padding:2rem;color:var(--gray-500)">No attendance records for this date</td></tr>
            <?php else: ?>
              <?php foreach ($records as $r): ?>
                <?php
                  $duration = '—';
                  if ($r['check_out']) {
                      $diff = strtotime($r['check_out']) - strtotime($r['check_in']);
                      $hours = floor($diff / 3600);
                      $mins = floor(($diff % 3600) / 60);
                      $duration = "{$hours}h {$mins}m";
                  }
                ?>
                <tr>
                  <td><?= htmlspecialchars($r['full_name']) ?></td>
                  <td><?= date('g:i A', strtotime($r['check_in'])) ?></td>
                  <td><?= $r['check_out'] ? date('g:i A', strtotime($r['check_out'])) : '<span class="badge badge-warning">Still In</span>' ?></td>
                  <td><?= $duration ?></td>
                  <td><span class="badge badge-<?= $r['status']==='present'?'success':($r['status']==='late'?'warning':'danger') ?>"><?= ucfirst($r['status']) ?></span></td>
                  <td><?= htmlspecialchars($r['notes'] ?: '—') ?></td>
                  <td>
                    <div class="d-flex gap-1">
                      <?php if (!$r['check_out']): ?>
                        <form method="POST" style="display:inline">
                          <input type="hidden" name="action" value="checkout">
                          <input type="hidden" name="att_id" value="<?= $r['id'] ?>">
                          <button type="submit" class="btn btn-icon" style="color:var(--success)" title="Check Out">
                            <i class="fas fa-sign-out-alt"></i>
                          </button>
                        </form>
                      <?php endif; ?>
                      <form method="POST" style="display:inline" onsubmit="return confirmDelete('Delete this record?')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="att_id" value="<?= $r['id'] ?>">
                        <button type="submit" class="btn btn-icon" style="color:var(--danger)" title="Delete">
                          <i class="fas fa-trash"></i>
                        </button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Check-in Modal -->
<div class="modal-overlay" id="checkinModal">
  <div class="modal">
    <div class="modal-header">
      <h3>New Check-In</h3>
      <button class="modal-close" onclick="closeModal('checkinModal')">&times;</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="checkin">
      <div class="modal-body">
        <div class="form-group">
          <label>Select Member</label>
          <select name="user_id" class="form-control" required>
            <option value="">Choose a member...</option>
            <?php foreach ($allMembers as $m): ?>
              <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['full_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Notes (optional)</label>
          <textarea name="notes" class="form-control" rows="3" placeholder="Any notes..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('checkinModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-sign-in-alt"></i> Check In</button>
      </div>
    </form>
  </div>
</div>

<script src="../js/main.js"></script>
</body>
</html>
