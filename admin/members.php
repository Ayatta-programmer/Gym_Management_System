<?php
require_once __DIR__ . '/../auth/auth_check.php';
checkAuth(['admin']);

$flash = getFlash();

// Handle Add/Edit/Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = sanitize($_POST['full_name']);
        $email = sanitize($_POST['email']);
        $phone = sanitize($_POST['phone']);
        $role = sanitize($_POST['role']);
        $plan = sanitize($_POST['membership_plan']);
        $status = sanitize($_POST['status']);
        $password = password_hash('Member@123', PASSWORD_DEFAULT);
        $secAnswer = password_hash('default', PASSWORD_DEFAULT);

        // Check duplicate email
        $check = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $check->execute([$email]);
        if ($check->fetch()) {
            setFlash('danger', 'Email already exists.');
        } else {
            $stmt = $pdo->prepare('INSERT INTO users (full_name, email, phone, password, role, security_question, security_answer, status, membership_plan) VALUES (?,?,?,?,?,?,?,?,?)');
            $stmt->execute([$name, $email, $phone, $password, $role, 'What is your favorite sport?', $secAnswer, $status, $plan]);
            setFlash('success', 'Member added successfully. Default password: Member@123');
        }
        redirect('members.php');
    }

    if ($action === 'edit') {
        $id = (int) $_POST['member_id'];
        $name = sanitize($_POST['full_name']);
        $email = sanitize($_POST['email']);
        $phone = sanitize($_POST['phone']);
        $role = sanitize($_POST['role']);
        $plan = sanitize($_POST['membership_plan']);
        $status = sanitize($_POST['status']);

        $stmt = $pdo->prepare('UPDATE users SET full_name=?, email=?, phone=?, role=?, membership_plan=?, status=? WHERE id=?');
        $stmt->execute([$name, $email, $phone, $role, $plan, $status, $id]);
        setFlash('success', 'Member updated successfully.');
        redirect('members.php');
    }

    if ($action === 'delete') {
        $id = (int) $_POST['member_id'];
        $stmt = $pdo->prepare('DELETE FROM users WHERE id = ? AND id != ?');
        $stmt->execute([$id, $_SESSION['user_id']]);
        setFlash('success', 'Member deleted successfully.');
        redirect('members.php');
    }
}

// Search & filter
$search = sanitize($_GET['search'] ?? '');
$filterRole = sanitize($_GET['role'] ?? '');
$filterStatus = sanitize($_GET['status'] ?? '');

$sql = "SELECT * FROM users WHERE id != ?";
$params = [$_SESSION['user_id']];

if ($search) {
    $sql .= " AND (full_name LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($filterRole) {
    $sql .= " AND role = ?";
    $params[] = $filterRole;
}
if ($filterStatus) {
    $sql .= " AND status = ?";
    $params[] = $filterStatus;
}
$sql .= " ORDER BY joined_date DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$members = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Member Management - <?= APP_NAME ?></title>
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
        <h1>Member Management</h1>
        <p>Manage all gym members, trainers, and their profiles</p>
      </div>
      <button class="btn btn-primary" onclick="openModal('addMemberModal')">
        <i class="fas fa-plus"></i> Add Member
      </button>
    </div>

    <?php if ($flash): ?>
      <div class="alert alert-<?= $flash['type'] ?>">
        <i class="fas fa-<?= $flash['type']==='success'?'check':'exclamation' ?>-circle"></i>
        <?= $flash['message'] ?>
      </div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="card mb-2">
      <div class="card-body">
        <form method="GET" style="display:flex;gap:1rem;flex-wrap:wrap;align-items:flex-end">
          <div class="form-group" style="margin:0;flex:1;min-width:200px">
            <label>Search</label>
            <input type="text" name="search" class="form-control" placeholder="Search by name or email..." value="<?= htmlspecialchars($search) ?>">
          </div>
          <div class="form-group" style="margin:0">
            <label>Role</label>
            <select name="role" class="form-control">
              <option value="">All Roles</option>
              <option value="member" <?= $filterRole==='member'?'selected':'' ?>>Member</option>
              <option value="trainer" <?= $filterRole==='trainer'?'selected':'' ?>>Trainer</option>
              <option value="admin" <?= $filterRole==='admin'?'selected':'' ?>>Admin</option>
            </select>
          </div>
          <div class="form-group" style="margin:0">
            <label>Status</label>
            <select name="status" class="form-control">
              <option value="">All Status</option>
              <option value="active" <?= $filterStatus==='active'?'selected':'' ?>>Active</option>
              <option value="inactive" <?= $filterStatus==='inactive'?'selected':'' ?>>Inactive</option>
              <option value="suspended" <?= $filterStatus==='suspended'?'selected':'' ?>>Suspended</option>
            </select>
          </div>
          <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Filter</button>
          <a href="members.php" class="btn btn-ghost btn-sm"><i class="fas fa-times"></i> Clear</a>
        </form>
      </div>
    </div>

    <!-- Members Table -->
    <div class="card">
      <div class="card-header">
        <h3>All Members (<?= count($members) ?>)</h3>
      </div>
      <div class="card-body no-padding">
        <table class="data-table">
          <thead>
            <tr>
              <th>Name</th>
              <th>Email</th>
              <th>Phone</th>
              <th>Role</th>
              <th>Plan</th>
              <th>Status</th>
              <th>Joined</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($members)): ?>
              <tr><td colspan="8" class="text-center" style="padding:2rem;color:var(--gray-500)">No members found</td></tr>
            <?php else: ?>
              <?php foreach ($members as $m): ?>
                <?php
                  $parts = explode(' ', $m['full_name']);
                  $ini = strtoupper(substr($parts[0],0,1).(isset($parts[1])?substr($parts[1],0,1):''));
                ?>
                <tr>
                  <td>
                    <div class="user-cell">
                      <div class="user-avatar"><?= $ini ?></div>
                      <span><?= htmlspecialchars($m['full_name']) ?></span>
                    </div>
                  </td>
                  <td><?= htmlspecialchars($m['email']) ?></td>
                  <td><?= htmlspecialchars($m['phone'] ?: '—') ?></td>
                  <td><span class="badge badge-info"><?= ucfirst($m['role']) ?></span></td>
                  <td><?= ucfirst($m['membership_plan'] ?: 'basic') ?></td>
                  <td><span class="badge badge-<?= $m['status']==='active'?'success':($m['status']==='inactive'?'warning':'danger') ?>"><?= ucfirst($m['status']) ?></span></td>
                  <td><?= date('M j, Y', strtotime($m['joined_date'])) ?></td>
                  <td>
                    <div class="d-flex gap-1">
                      <button class="btn btn-icon" onclick='editMember(<?= json_encode($m) ?>)' title="Edit">
                        <i class="fas fa-pen"></i>
                      </button>
                      <form method="POST" style="display:inline" onsubmit="return confirmDelete('Delete this member?')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="member_id" value="<?= $m['id'] ?>">
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

<!-- Add Member Modal -->
<div class="modal-overlay" id="addMemberModal">
  <div class="modal">
    <div class="modal-header">
      <h3>Add New Member</h3>
      <button class="modal-close" onclick="closeModal('addMemberModal')">&times;</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="add">
      <div class="modal-body">
        <div class="form-group">
          <label>Full Name</label>
          <input type="text" name="full_name" class="form-control" required>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" class="form-control" required>
          </div>
          <div class="form-group">
            <label>Phone</label>
            <input type="tel" name="phone" class="form-control">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Role</label>
            <select name="role" class="form-control">
              <option value="member">Member</option>
              <option value="trainer">Trainer</option>
            </select>
          </div>
          <div class="form-group">
            <label>Plan</label>
            <select name="membership_plan" class="form-control">
              <option value="basic">Basic</option>
              <option value="standard">Standard</option>
              <option value="premium">Premium</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label>Status</label>
          <select name="status" class="form-control">
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('addMemberModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Add Member</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Member Modal -->
<div class="modal-overlay" id="editMemberModal">
  <div class="modal">
    <div class="modal-header">
      <h3>Edit Member</h3>
      <button class="modal-close" onclick="closeModal('editMemberModal')">&times;</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="member_id" id="edit_id">
      <div class="modal-body">
        <div class="form-group">
          <label>Full Name</label>
          <input type="text" name="full_name" id="edit_name" class="form-control" required>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" id="edit_email" class="form-control" required>
          </div>
          <div class="form-group">
            <label>Phone</label>
            <input type="tel" name="phone" id="edit_phone" class="form-control">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Role</label>
            <select name="role" id="edit_role" class="form-control">
              <option value="member">Member</option>
              <option value="trainer">Trainer</option>
              <option value="admin">Admin</option>
            </select>
          </div>
          <div class="form-group">
            <label>Plan</label>
            <select name="membership_plan" id="edit_plan" class="form-control">
              <option value="basic">Basic</option>
              <option value="standard">Standard</option>
              <option value="premium">Premium</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label>Status</label>
          <select name="status" id="edit_status" class="form-control">
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
            <option value="suspended">Suspended</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('editMemberModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
      </div>
    </form>
  </div>
</div>

<script src="../js/main.js"></script>
<script>
function editMember(data) {
  document.getElementById('edit_id').value = data.id;
  document.getElementById('edit_name').value = data.full_name;
  document.getElementById('edit_email').value = data.email;
  document.getElementById('edit_phone').value = data.phone || '';
  document.getElementById('edit_role').value = data.role;
  document.getElementById('edit_plan').value = data.membership_plan || 'basic';
  document.getElementById('edit_status').value = data.status;
  openModal('editMemberModal');
}
</script>
</body>
</html>
