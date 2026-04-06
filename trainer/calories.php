<?php
require_once __DIR__ . '/../auth/auth_check.php';
checkAuth(['trainer']);

$flash = getFlash();
$trainerId = $_SESSION['user_id'];

// Handle add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $userId = (int) $_POST['user_id'];
    $activity = sanitize($_POST['activity']);
    $duration = (int) $_POST['duration_minutes'];
    $calories = (float) $_POST['calories_burnt'];
    $date = sanitize($_POST['workout_date']);
    $intensity = sanitize($_POST['intensity']);
    $notes = sanitize($_POST['notes'] ?? '');

    $stmt = $pdo->prepare('INSERT INTO calories (user_id, activity, duration_minutes, calories_burnt, workout_date, intensity, notes, recorded_by) VALUES (?,?,?,?,?,?,?,?)');
    $stmt->execute([$userId, $activity, $duration, $calories, $date, $intensity, $notes, $trainerId]);
    setFlash('success', 'Calories logged successfully.');
    redirect('calories.php');
}

// Get records logged by this trainer
$records = $pdo->prepare("
    SELECT c.*, u.full_name FROM calories c 
    JOIN users u ON c.user_id = u.id 
    WHERE c.recorded_by = ? 
    ORDER BY c.workout_date DESC, c.created_at DESC LIMIT 50
");
$records->execute([$trainerId]);
$records = $records->fetchAll();

$allMembers = $pdo->query("SELECT id, full_name FROM users WHERE role = 'member' AND status = 'active' ORDER BY full_name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Calories - <?= APP_NAME ?></title>
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
        <h1>Log Calories</h1>
        <p>Record workout sessions and calories for members</p>
      </div>
      <button class="btn btn-primary" onclick="openModal('addCalModal')">
        <i class="fas fa-plus"></i> Log Calories
      </button>
    </div>

    <?php if ($flash): ?>
      <div class="alert alert-<?= $flash['type'] ?>"><i class="fas fa-check-circle"></i> <?= $flash['message'] ?></div>
    <?php endif; ?>

    <div class="card">
      <div class="card-header"><h3>My Calorie Logs (<?= count($records) ?>)</h3></div>
      <div class="card-body no-padding">
        <table class="data-table">
          <thead><tr><th>Member</th><th>Activity</th><th>Duration</th><th>Calories</th><th>Intensity</th><th>Date</th></tr></thead>
          <tbody>
            <?php if (empty($records)): ?>
              <tr><td colspan="6" class="text-center" style="padding:2rem;color:var(--gray-500)">No records yet</td></tr>
            <?php else: ?>
              <?php foreach ($records as $r): ?>
                <tr>
                  <td><?= htmlspecialchars($r['full_name']) ?></td>
                  <td><?= htmlspecialchars($r['activity']) ?></td>
                  <td><?= $r['duration_minutes'] ?> min</td>
                  <td><strong style="color:var(--primary)"><?= number_format($r['calories_burnt']) ?> kcal</strong></td>
                  <td><span class="badge badge-<?= $r['intensity']==='high'?'danger':($r['intensity']==='medium'?'warning':'info') ?>"><?= ucfirst($r['intensity']) ?></span></td>
                  <td><?= date('M j, Y', strtotime($r['workout_date'])) ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<div class="modal-overlay" id="addCalModal">
  <div class="modal">
    <div class="modal-header">
      <h3>Log Calories Burnt</h3>
      <button class="modal-close" onclick="closeModal('addCalModal')">&times;</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="add">
      <div class="modal-body">
        <div class="form-group">
          <label>Member</label>
          <select name="user_id" class="form-control" required>
            <option value="">Select...</option>
            <?php foreach ($allMembers as $m): ?>
              <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['full_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Activity</label>
          <select name="activity" id="t_cal_activity" class="form-control" required>
            <option value="">Select...</option>
            <option>Treadmill Running</option><option>Cycling</option><option>Weight Training</option>
            <option>CrossFit</option><option>Yoga</option><option>Swimming</option>
            <option>Jump Rope</option><option>Rowing</option><option>HIIT</option><option>Other</option>
          </select>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Weight (kg)</label><input type="number" id="t_cal_weight" class="form-control" min="30" max="250" value="70" step="0.5"></div>
          <div class="form-group"><label>Duration (min)</label><input type="number" name="duration_minutes" id="t_cal_duration" class="form-control" min="1" required></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Intensity</label>
            <select name="intensity" id="t_cal_intensity" class="form-control">
              <option value="low">Low</option><option value="medium" selected>Medium</option><option value="high">High</option>
            </select>
          </div>
          <div class="form-group">
            <label>Calories (kcal) <small style="color:var(--primary)">⚡ Auto</small></label>
            <input type="number" name="calories_burnt" id="t_cal_calories" class="form-control" min="1" step="0.01" required style="font-weight:700;color:var(--primary)">
          </div>
        </div>
        <div id="t_cal_breakdown" class="alert alert-info" style="display:none;font-size:0.85rem">
          <i class="fas fa-calculator"></i> <span id="t_cal_formula"></span>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Date</label><input type="date" name="workout_date" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
        </div>
        <div class="form-group"><label>Notes</label><textarea name="notes" class="form-control" rows="2" placeholder="Optional..."></textarea></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('addCalModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-fire"></i> Log</button>
      </div>
    </form>
  </div>
</div>

<script src="../js/main.js"></script>
<script>
const MET_VALUES = {
  'Treadmill Running': { low: 6.0, medium: 8.3, high: 11.0 },
  'Cycling':           { low: 4.0, medium: 6.8, high: 10.0 },
  'Weight Training':   { low: 3.5, medium: 5.0, high: 6.0 },
  'CrossFit':          { low: 6.0, medium: 8.0, high: 12.0 },
  'Yoga':              { low: 2.0, medium: 2.5, high: 4.0 },
  'Swimming':          { low: 4.5, medium: 7.0, high: 10.0 },
  'Jump Rope':         { low: 8.0, medium: 10.0, high: 12.3 },
  'Rowing':            { low: 4.0, medium: 7.0, high: 8.5 },
  'HIIT':              { low: 6.0, medium: 8.0, high: 12.0 },
  'Other':             { low: 3.0, medium: 5.0, high: 7.0 }
};

function calcTrainerCalories() {
  const activity = document.getElementById('t_cal_activity').value;
  const duration = parseFloat(document.getElementById('t_cal_duration').value) || 0;
  const weight = parseFloat(document.getElementById('t_cal_weight').value) || 70;
  const intensity = document.getElementById('t_cal_intensity').value;

  if (activity && duration > 0 && MET_VALUES[activity]) {
    const met = MET_VALUES[activity][intensity] || 5.0;
    const calories = (met * weight * (duration / 60)).toFixed(0);
    document.getElementById('t_cal_calories').value = calories;
    document.getElementById('t_cal_breakdown').style.display = 'flex';
    document.getElementById('t_cal_formula').textContent = `MET ${met} × ${weight}kg × ${duration}min ÷ 60 = ${calories} kcal`;
  } else {
    document.getElementById('t_cal_breakdown').style.display = 'none';
  }
}

['t_cal_activity', 't_cal_duration', 't_cal_weight', 't_cal_intensity'].forEach(id => {
  const el = document.getElementById(id);
  if (el) { el.addEventListener('input', calcTrainerCalories); el.addEventListener('change', calcTrainerCalories); }
});
</script>
</body>
</html>

