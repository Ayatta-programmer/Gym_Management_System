<?php
require_once __DIR__ . '/../auth/auth_check.php';
checkAuth(['trainer']);

$flash = getFlash();
$trainerId = $_SESSION['user_id'];

// Handle check-in
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'checkin') {
        $userId = (int) $_POST['user_id'];
        $notes = sanitize($_POST['notes'] ?? '');
        $existing = $pdo->prepare("SELECT id FROM attendance WHERE user_id = ? AND DATE(check_in) = CURRENT_DATE AND check_out IS NULL");
        $existing->execute([$userId]);
        if ($existing->fetch()) {
            setFlash('warning', 'Member already checked in.');
        } else {
            $stmt = $pdo->prepare('INSERT INTO attendance (user_id, check_in, status, notes, recorded_by) VALUES (?, NOW(), ?, ?, ?)');
            $stmt->execute([$userId, 'present', $notes, $trainerId]);
            setFlash('success', 'Check-in recorded.');
        }
        redirect('attendance.php');
    }
    if ($action === 'checkout') {
        $attId = (int) $_POST['att_id'];
        $pdo->prepare('UPDATE attendance SET check_out = NOW() WHERE id = ?')->execute([$attId]);
        setFlash('success', 'Check-out recorded.');
        redirect('attendance.php');
    }
}

// Get today's records
$records = $pdo->query("
    SELECT a.*, u.full_name FROM attendance a 
    JOIN users u ON a.user_id = u.id 
    WHERE DATE(a.check_in) = CURRENT_DATE 
    ORDER BY a.check_in DESC
")->fetchAll();

$allMembers = $pdo->query("SELECT id, full_name FROM users WHERE role = 'member' AND status = 'active' ORDER BY full_name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Attendance - <?= APP_NAME ?></title>
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
        <h1>Log Attendance</h1>
        <p>Record check-ins and check-outs for today</p>
      </div>
      <button class="btn btn-primary" onclick="openModal('checkinModal')">
        <i class="fas fa-sign-in-alt"></i> Check In
      </button>
    </div>

    <?php if ($flash): ?>
      <div class="alert alert-<?= $flash['type'] ?>"><i class="fas fa-check-circle"></i> <?= $flash['message'] ?></div>
    <?php endif; ?>

    <div class="card">
      <div class="card-header"><h3>Today's Attendance (<?= count($records) ?>)</h3></div>
      <div class="card-body no-padding">
        <table class="data-table">
          <thead><tr><th>Member</th><th>Check In</th><th>Check Out</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody>
            <?php if (empty($records)): ?>
              <tr><td colspan="5" class="text-center" style="padding:2rem;color:var(--gray-500)">No records today</td></tr>
            <?php else: ?>
              <?php foreach ($records as $r): ?>
                <tr>
                  <td><?= htmlspecialchars($r['full_name']) ?></td>
                  <td><?= date('g:i A', strtotime($r['check_in'])) ?></td>
                  <td><?= $r['check_out'] ? date('g:i A', strtotime($r['check_out'])) : '<span class="badge badge-warning">Still In</span>' ?></td>
                  <td><span class="badge badge-success"><?= ucfirst($r['status']) ?></span></td>
                  <td>
                    <?php if (!$r['check_out']): ?>
                      <form method="POST" style="display:inline">
                        <input type="hidden" name="action" value="checkout">
                        <input type="hidden" name="att_id" value="<?= $r['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-sign-out-alt"></i> Check Out</button>
                      </form>
                    <?php else: ?>
                      <span style="color:var(--gray-500)">Completed</span>
                    <?php endif; ?>
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
            <option value="">Choose...</option>
            <?php foreach ($allMembers as $m): ?>
              <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['full_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Notes</label>
          <textarea name="notes" class="form-control" rows="2" placeholder="Optional..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('checkinModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Check In</button>
      </div>
    </form>
  </div>
</div>

<script src="../js/main.js"></script>
</body>
</html>
