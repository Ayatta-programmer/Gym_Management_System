<?php
require_once __DIR__ . '/../auth/auth_check.php';
checkAuth(['trainer']);

// Get all members (trainers can see all members to log for them)
$allMembers = $pdo->query("SELECT id, full_name, email, phone, status, membership_plan, joined_date FROM users WHERE role = 'member' AND status = 'active' ORDER BY full_name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Members - <?= APP_NAME ?></title>
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
        <h1>My Members</h1>
        <p>View member profiles and details</p>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <h3>Active Members (<?= count($allMembers) ?>)</h3>
      </div>
      <div class="card-body no-padding">
        <table class="data-table">
          <thead>
            <tr><th>Name</th><th>Email</th><th>Phone</th><th>Plan</th><th>Joined</th></tr>
          </thead>
          <tbody>
            <?php if (empty($allMembers)): ?>
              <tr><td colspan="5" class="text-center" style="padding:2rem;color:var(--gray-500)">No members found</td></tr>
            <?php else: ?>
              <?php foreach ($allMembers as $m): ?>
                <?php $ini = strtoupper(substr($m['full_name'],0,1)); ?>
                <tr>
                  <td>
                    <div class="user-cell">
                      <div class="user-avatar"><?= $ini ?></div>
                      <span><?= htmlspecialchars($m['full_name']) ?></span>
                    </div>
                  </td>
                  <td><?= htmlspecialchars($m['email']) ?></td>
                  <td><?= htmlspecialchars($m['phone'] ?: '—') ?></td>
                  <td><span class="badge badge-info"><?= ucfirst($m['membership_plan'] ?: 'Basic') ?></span></td>
                  <td><?= date('M j, Y', strtotime($m['joined_date'])) ?></td>
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
