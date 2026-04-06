<?php
require_once __DIR__ . '/../auth/auth_check.php';
checkAuth(['admin']);

$flash = getFlash();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $userId = (int) $_POST['user_id'];
        $activity = sanitize($_POST['activity']);
        $duration = (int) $_POST['duration_minutes'];
        $calories = (float) $_POST['calories_burnt'];
        $date = sanitize($_POST['workout_date']);
        $intensity = sanitize($_POST['intensity']);
        $notes = sanitize($_POST['notes'] ?? '');

        $stmt = $pdo->prepare('INSERT INTO calories (user_id, activity, duration_minutes, calories_burnt, workout_date, intensity, notes, recorded_by) VALUES (?,?,?,?,?,?,?,?)');
        $stmt->execute([$userId, $activity, $duration, $calories, $date, $intensity, $notes, $_SESSION['user_id']]);
        setFlash('success', 'Calorie record added successfully.');
        redirect('calories.php');
    }

    if ($action === 'delete') {
        $id = (int) $_POST['record_id'];
        $pdo->prepare('DELETE FROM calories WHERE id = ?')->execute([$id]);
        setFlash('success', 'Record deleted.');
        redirect('calories.php');
    }
}

// Filters
$filterDate = sanitize($_GET['date'] ?? '');
$filterMember = sanitize($_GET['member'] ?? '');

$sql = "SELECT c.*, u.full_name FROM calories c JOIN users u ON c.user_id = u.id WHERE 1=1";
$params = [];

if ($filterDate) {
    $sql .= " AND c.workout_date = ?";
    $params[] = $filterDate;
}
if ($filterMember) {
    $sql .= " AND u.full_name LIKE ?";
    $params[] = "%$filterMember%";
}
$sql .= " ORDER BY c.workout_date DESC, c.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll();

$allMembers = $pdo->query("SELECT id, full_name FROM users WHERE role IN ('member','trainer') AND status = 'active' ORDER BY full_name")->fetchAll();

// Build member weight map (default 70kg if not set)
$memberWeights = [];
foreach ($allMembers as $m) {
    $memberWeights[$m['id']] = 70; // default weight
}

// Stats
$totalCalories = $pdo->query("SELECT COALESCE(SUM(calories_burnt), 0) FROM calories WHERE workout_date = CURRENT_DATE")->fetchColumn();
$totalSessions = $pdo->query("SELECT COUNT(*) FROM calories WHERE workout_date = CURRENT_DATE")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Calories Burnt - <?= APP_NAME ?></title>
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
        <h1>Calories Burnt Module</h1>
        <p>Track and manage workout calorie records</p>
      </div>
      <button class="btn btn-primary" onclick="openModal('addCalorieModal')">
        <i class="fas fa-plus"></i> Log Calories
      </button>
    </div>

    <?php if ($flash): ?>
      <div class="alert alert-<?= $flash['type'] ?>"><i class="fas fa-check-circle"></i> <?= $flash['message'] ?></div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-grid" style="grid-template-columns: repeat(2, 1fr)">
      <div class="stat-card">
        <div class="stat-header">
          <div>
            <div class="stat-value"><?= number_format($totalCalories) ?></div>
            <div class="stat-label">Today's Total Calories</div>
          </div>
          <div class="stat-icon orange"><i class="fas fa-fire-flame-curved"></i></div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-header">
          <div>
            <div class="stat-value"><?= $totalSessions ?></div>
            <div class="stat-label">Today's Sessions</div>
          </div>
          <div class="stat-icon green"><i class="fas fa-dumbbell"></i></div>
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
            <label>Member</label>
            <input type="text" name="member" class="form-control" placeholder="Search by name..." value="<?= htmlspecialchars($filterMember) ?>">
          </div>
          <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Filter</button>
          <a href="calories.php" class="btn btn-ghost btn-sm"><i class="fas fa-times"></i> Clear</a>
        </form>
      </div>
    </div>

    <!-- Records Table -->
    <div class="card">
      <div class="card-header">
        <h3>Calorie Records (<?= count($records) ?>)</h3>
      </div>
      <div class="card-body no-padding">
        <table class="data-table">
          <thead>
            <tr>
              <th>Member</th>
              <th>Activity</th>
              <th>Duration</th>
              <th>Calories</th>
              <th>Intensity</th>
              <th>Date</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($records)): ?>
              <tr><td colspan="7" class="text-center" style="padding:2rem;color:var(--gray-500)">No calorie records found</td></tr>
            <?php else: ?>
              <?php foreach ($records as $r): ?>
                <tr>
                  <td><?= htmlspecialchars($r['full_name']) ?></td>
                  <td><?= htmlspecialchars($r['activity']) ?></td>
                  <td><?= $r['duration_minutes'] ?> min</td>
                  <td><strong style="color:var(--primary)"><?= number_format($r['calories_burnt']) ?> kcal</strong></td>
                  <td><span class="badge badge-<?= $r['intensity']==='high'?'danger':($r['intensity']==='medium'?'warning':'info') ?>"><?= ucfirst($r['intensity']) ?></span></td>
                  <td><?= date('M j, Y', strtotime($r['workout_date'])) ?></td>
                  <td>
                    <form method="POST" style="display:inline" onsubmit="return confirmDelete('Delete this record?')">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="record_id" value="<?= $r['id'] ?>">
                      <button type="submit" class="btn btn-icon" style="color:var(--danger)"><i class="fas fa-trash"></i></button>
                    </form>
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

<!-- Add Calorie Modal -->
<div class="modal-overlay" id="addCalorieModal">
  <div class="modal">
    <div class="modal-header">
      <h3>Log Calories Burnt</h3>
      <button class="modal-close" onclick="closeModal('addCalorieModal')">&times;</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="add">
      <div class="modal-body">
        <div class="form-group">
          <label>Member</label>
          <select name="user_id" id="cal_member" class="form-control" required>
            <option value="">Select member...</option>
            <?php foreach ($allMembers as $m): ?>
              <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['full_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Activity</label>
          <select name="activity" id="cal_activity" class="form-control" required>
            <option value="">Select activity...</option>
            <option value="Treadmill Running">Treadmill Running</option>
            <option value="Cycling">Cycling</option>
            <option value="Weight Training">Weight Training</option>
            <option value="CrossFit">CrossFit</option>
            <option value="Yoga">Yoga</option>
            <option value="Swimming">Swimming</option>
            <option value="Jump Rope">Jump Rope</option>
            <option value="Rowing">Rowing</option>
            <option value="HIIT">HIIT</option>
            <option value="Other">Other</option>
          </select>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Member Weight (kg)</label>
            <input type="number" id="cal_weight" class="form-control" min="30" max="250" value="70" step="0.5">
          </div>
          <div class="form-group">
            <label>Duration (minutes)</label>
            <input type="number" name="duration_minutes" id="cal_duration" class="form-control" min="1" required>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Intensity</label>
            <select name="intensity" id="cal_intensity" class="form-control">
              <option value="low">Low</option>
              <option value="medium" selected>Medium</option>
              <option value="high">High</option>
            </select>
          </div>
          <div class="form-group">
            <label>Calories Burnt (kcal) <small style="color:var(--primary)">⚡ Auto-calculated</small></label>
            <input type="number" name="calories_burnt" id="cal_calories" class="form-control" min="1" step="0.01" required style="font-weight:700;color:var(--primary)">
          </div>
        </div>
        <div id="cal_breakdown" class="alert alert-info" style="display:none;font-size:0.85rem">
          <i class="fas fa-calculator"></i> <span id="cal_formula"></span>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Date</label>
            <input type="date" name="workout_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
          </div>
        </div>
        <div class="form-group">
          <label>Notes</label>
          <textarea name="notes" class="form-control" rows="2" placeholder="Optional notes..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('addCalorieModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-fire"></i> Log Calories</button>
      </div>
    </form>
  </div>
</div>

<script src="../js/main.js"></script>
<script>
// MET values for each activity (Metabolic Equivalent of Task)
// Source: Compendium of Physical Activities
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

function calculateCalories() {
  const activity = document.getElementById('cal_activity').value;
  const duration = parseFloat(document.getElementById('cal_duration').value) || 0;
  const weight = parseFloat(document.getElementById('cal_weight').value) || 70;
  const intensity = document.getElementById('cal_intensity').value;
  const caloriesField = document.getElementById('cal_calories');
  const breakdown = document.getElementById('cal_breakdown');
  const formula = document.getElementById('cal_formula');

  if (activity && duration > 0 && MET_VALUES[activity]) {
    const met = MET_VALUES[activity][intensity] || 5.0;
    // Formula: Calories = MET × weight(kg) × duration(hours)
    const calories = (met * weight * (duration / 60)).toFixed(0);
    caloriesField.value = calories;
    breakdown.style.display = 'flex';
    formula.textContent = `MET ${met} × ${weight}kg × ${duration}min ÷ 60 = ${calories} kcal`;
  } else {
    breakdown.style.display = 'none';
  }
}

// Attach listeners
['cal_activity', 'cal_duration', 'cal_weight', 'cal_intensity'].forEach(id => {
  const el = document.getElementById(id);
  if (el) el.addEventListener('input', calculateCalories);
  if (el) el.addEventListener('change', calculateCalories);
});
</script>
</body>
</html>
