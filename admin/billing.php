<?php
require_once __DIR__ . '/../auth/auth_check.php';
checkAuth(['admin']);

$flash = getFlash();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $userId = (int) $_POST['user_id'];
        $description = sanitize($_POST['description']);
        $amount = (float) $_POST['amount'];
        $tax = (float) ($_POST['tax'] ?? 0);
        $total = $amount + $tax;
        $dueDate = sanitize($_POST['due_date']);
        $invoiceNumber = 'INV-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);

        $stmt = $pdo->prepare('INSERT INTO invoices (user_id, invoice_number, description, amount, tax, total, due_date, created_by) VALUES (?,?,?,?,?,?,?,?)');
        $stmt->execute([$userId, $invoiceNumber, $description, $amount, $tax, $total, $dueDate, $_SESSION['user_id']]);
        setFlash('success', "Invoice $invoiceNumber created successfully.");
        redirect('billing.php');
    }

    if ($action === 'mark_paid') {
        $id = (int) $_POST['invoice_id'];
        $method = sanitize($_POST['payment_method'] ?? 'Cash');
        $stmt = $pdo->prepare("UPDATE invoices SET status = 'paid', paid_date = CURRENT_DATE, payment_method = ? WHERE id = ?");
        $stmt->execute([$method, $id]);
        setFlash('success', 'Invoice marked as paid.');
        redirect('billing.php');
    }

    if ($action === 'cancel') {
        $id = (int) $_POST['invoice_id'];
        $pdo->prepare("UPDATE invoices SET status = 'cancelled' WHERE id = ?")->execute([$id]);
        setFlash('success', 'Invoice cancelled.');
        redirect('billing.php');
    }

    if ($action === 'delete') {
        $id = (int) $_POST['invoice_id'];
        $pdo->prepare('DELETE FROM invoices WHERE id = ?')->execute([$id]);
        setFlash('success', 'Invoice deleted.');
        redirect('billing.php');
    }
}

// Filters
$filterStatus = sanitize($_GET['status'] ?? '');
$filterMember = sanitize($_GET['member'] ?? '');

$sql = "SELECT i.*, u.full_name FROM invoices i JOIN users u ON i.user_id = u.id WHERE 1=1";
$params = [];

if ($filterStatus) { $sql .= " AND i.status = ?"; $params[] = $filterStatus; }
if ($filterMember) { $sql .= " AND u.full_name LIKE ?"; $params[] = "%$filterMember%"; }
$sql .= " ORDER BY i.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$invoices = $stmt->fetchAll();

$allMembers = $pdo->query("SELECT id, full_name FROM users WHERE role IN ('member','trainer') AND status = 'active' ORDER BY full_name")->fetchAll();

// Stats
$totalPaid = $pdo->query("SELECT COALESCE(SUM(total),0) FROM invoices WHERE status='paid'")->fetchColumn();
$totalPending = $pdo->query("SELECT COALESCE(SUM(total),0) FROM invoices WHERE status='pending'")->fetchColumn();
$totalOverdue = $pdo->query("SELECT COALESCE(SUM(total),0) FROM invoices WHERE status='overdue'")->fetchColumn();
$invoiceCount = $pdo->query("SELECT COUNT(*) FROM invoices")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Billing - <?= APP_NAME ?></title>
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
        <h1>Billing & Invoicing</h1>
        <p>Create and manage invoices and payments</p>
      </div>
      <button class="btn btn-primary" onclick="openModal('addInvoiceModal')">
        <i class="fas fa-plus"></i> New Invoice
      </button>
    </div>

    <?php if ($flash): ?>
      <div class="alert alert-<?= $flash['type'] ?>"><i class="fas fa-check-circle"></i> <?= $flash['message'] ?></div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-header">
          <div>
            <div class="stat-value"><?= $invoiceCount ?></div>
            <div class="stat-label">Total Invoices</div>
          </div>
          <div class="stat-icon blue"><i class="fas fa-file-invoice"></i></div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-header">
          <div>
            <div class="stat-value"><?= formatCurrency($totalPaid) ?></div>
            <div class="stat-label">Total Paid</div>
          </div>
          <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-header">
          <div>
            <div class="stat-value"><?= formatCurrency($totalPending) ?></div>
            <div class="stat-label">Pending</div>
          </div>
          <div class="stat-icon yellow"><i class="fas fa-clock"></i></div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-header">
          <div>
            <div class="stat-value"><?= formatCurrency($totalOverdue) ?></div>
            <div class="stat-label">Overdue</div>
          </div>
          <div class="stat-icon orange"><i class="fas fa-exclamation-triangle"></i></div>
        </div>
      </div>
    </div>

    <!-- Filter -->
    <div class="card mb-2">
      <div class="card-body">
        <form method="GET" style="display:flex;gap:1rem;flex-wrap:wrap;align-items:flex-end">
          <div class="form-group" style="margin:0;flex:1;min-width:200px">
            <label>Member</label>
            <input type="text" name="member" class="form-control" placeholder="Search by name..." value="<?= htmlspecialchars($filterMember) ?>">
          </div>
          <div class="form-group" style="margin:0">
            <label>Status</label>
            <select name="status" class="form-control">
              <option value="">All</option>
              <option value="pending" <?= $filterStatus==='pending'?'selected':'' ?>>Pending</option>
              <option value="paid" <?= $filterStatus==='paid'?'selected':'' ?>>Paid</option>
              <option value="overdue" <?= $filterStatus==='overdue'?'selected':'' ?>>Overdue</option>
              <option value="cancelled" <?= $filterStatus==='cancelled'?'selected':'' ?>>Cancelled</option>
            </select>
          </div>
          <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Filter</button>
          <a href="billing.php" class="btn btn-ghost btn-sm"><i class="fas fa-times"></i> Clear</a>
        </form>
      </div>
    </div>

    <!-- Invoices Table -->
    <div class="card">
      <div class="card-header">
        <h3>Invoices (<?= count($invoices) ?>)</h3>
      </div>
      <div class="card-body no-padding">
        <table class="data-table">
          <thead>
            <tr>
              <th>Invoice #</th>
              <th>Member</th>
              <th>Description</th>
              <th>Amount</th>
              <th>Tax</th>
              <th>Total</th>
              <th>Due Date</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($invoices)): ?>
              <tr><td colspan="9" class="text-center" style="padding:2rem;color:var(--gray-500)">No invoices found</td></tr>
            <?php else: ?>
              <?php foreach ($invoices as $inv): ?>
                <tr>
                  <td><strong><?= htmlspecialchars($inv['invoice_number']) ?></strong></td>
                  <td><?= htmlspecialchars($inv['full_name']) ?></td>
                  <td><?= htmlspecialchars($inv['description']) ?></td>
                  <td><?= formatCurrency($inv['amount']) ?></td>
                  <td><?= formatCurrency($inv['tax']) ?></td>
                  <td><strong><?= formatCurrency($inv['total']) ?></strong></td>
                  <td><?= date('M j, Y', strtotime($inv['due_date'])) ?></td>
                  <td>
                    <span class="badge badge-<?= $inv['status']==='paid'?'success':($inv['status']==='pending'?'warning':($inv['status']==='overdue'?'danger':'info')) ?>">
                      <?= ucfirst($inv['status']) ?>
                    </span>
                  </td>
                  <td>
                    <div class="d-flex gap-1">
                      <?php if ($inv['status'] === 'pending' || $inv['status'] === 'overdue'): ?>
                        <form method="POST" style="display:inline">
                          <input type="hidden" name="action" value="mark_paid">
                          <input type="hidden" name="invoice_id" value="<?= $inv['id'] ?>">
                          <input type="hidden" name="payment_method" value="Cash">
                          <button type="submit" class="btn btn-icon" style="color:var(--success)" title="Mark Paid">
                            <i class="fas fa-check"></i>
                          </button>
                        </form>
                        <form method="POST" style="display:inline">
                          <input type="hidden" name="action" value="cancel">
                          <input type="hidden" name="invoice_id" value="<?= $inv['id'] ?>">
                          <button type="submit" class="btn btn-icon" style="color:var(--warning)" title="Cancel">
                            <i class="fas fa-ban"></i>
                          </button>
                        </form>
                      <?php endif; ?>
                      <form method="POST" style="display:inline" onsubmit="return confirmDelete('Delete this invoice?')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="invoice_id" value="<?= $inv['id'] ?>">
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

<!-- Add Invoice Modal -->
<div class="modal-overlay" id="addInvoiceModal">
  <div class="modal">
    <div class="modal-header">
      <h3>Create New Invoice</h3>
      <button class="modal-close" onclick="closeModal('addInvoiceModal')">&times;</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="add">
      <div class="modal-body">
        <div class="form-group">
          <label>Member</label>
          <select name="user_id" class="form-control" required>
            <option value="">Select member...</option>
            <?php foreach ($allMembers as $m): ?>
              <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['full_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Description</label>
          <select name="description" class="form-control" required>
            <option value="">Select type...</option>
            <option value="Monthly Membership - Basic">Monthly Membership - Basic</option>
            <option value="Monthly Membership - Standard">Monthly Membership - Standard</option>
            <option value="Monthly Membership - Premium">Monthly Membership - Premium</option>
            <option value="Personal Training Session">Personal Training Session</option>
            <option value="Registration Fee">Registration Fee</option>
            <option value="Equipment Rental">Equipment Rental</option>
            <option value="Other">Other</option>
          </select>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Amount (KSh)</label>
            <input type="number" name="amount" class="form-control" min="0" step="0.01" required>
          </div>
          <div class="form-group">
            <label>Tax (KSh)</label>
            <input type="number" name="tax" class="form-control" min="0" step="0.01" value="0">
          </div>
        </div>
        <div class="form-group">
          <label>Due Date</label>
          <input type="date" name="due_date" class="form-control" required>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('addInvoiceModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-file-invoice-dollar"></i> Create Invoice</button>
      </div>
    </form>
  </div>
</div>

<script src="../js/main.js"></script>
</body>
</html>
