<?php
require_once __DIR__ . '/../auth/auth_check.php';
checkAuth(['member']);

$memberId = $_SESSION['user_id'];

$invoices = $pdo->prepare("SELECT * FROM invoices WHERE user_id = ? ORDER BY created_at DESC");
$invoices->execute([$memberId]);
$invoices = $invoices->fetchAll();

$totalOwed = 0; $totalPaid = 0;
foreach ($invoices as $i) {
    if ($i['status'] === 'paid') $totalPaid += $i['total'];
    elseif (in_array($i['status'], ['pending','overdue'])) $totalOwed += $i['total'];
}

$user = getCurrentUser($pdo);
$currentPlan = strtolower($user['membership_plan'] ?? 'basic');
$subscriptionOptions = [
    'daily' => ['name' => 'Daily Pass', 'price' => 500, 'desc' => '1 Day Access'],
    'weekly' => ['name' => 'Weekly Pass', 'price' => 1500, 'desc' => '7 Days Access'],
    'monthly' => ['name' => 'Monthly Membership', 'price' => 3000, 'desc' => 'Full Month Access']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Billing - <?= APP_NAME ?></title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <style>
    .plan-selector { display: flex; flex-direction: column; gap: 1rem; max-width: 400px; }
    .plan-option { display: flex; align-items: center; justify-content: space-between; padding: 1.25rem; border: 2px solid var(--dark-500); border-radius: 12px; cursor: pointer; transition: all 0.2s; background: var(--dark-700); }
    .plan-option:hover { border-color: var(--primary); background: var(--dark-600); }
    .plan-option.selected { border-color: var(--primary); background: rgba(255,107,53,0.1); }
    .plan-option input[type="radio"] { display: none; }
    .plan-info { display: flex; flex-direction: column; gap: 0.25rem; }
    .plan-title { font-weight: 700; font-size: 1.1rem; color: var(--gray-200); }
    .plan-desc { font-size: 0.85rem; color: var(--gray-400); }
    .plan-price-tag { font-weight: 800; color: var(--primary); font-size: 1.2rem; }

    .mpesa-btn { background: linear-gradient(135deg, #4CAF50, #2E7D32); border: none; color: #fff; font-weight: 700; }
    .mpesa-btn:hover { background: linear-gradient(135deg, #43A047, #1B5E20); transform: translateY(-1px); }
    .mpesa-logo { width: 120px; margin: 0 auto 1rem; display: block; }
    .payment-step { text-align: center; padding: 1.5rem; }
    .payment-step h3 { margin-bottom: 0.5rem; }
    .payment-step p { color: var(--gray-400); font-size: 0.9rem; }
    .phone-input-group { display: flex; gap: 0; max-width: 320px; margin: 1rem auto; }
    .phone-prefix { background: var(--dark-700); border: 1px solid var(--dark-500); border-right: none; border-radius: 8px 0 0 8px; padding: 0.75rem 1rem; color: var(--gray-300); font-weight: 600; display: flex; align-items: center; gap: 0.5rem; }
    .phone-prefix img { width: 24px; height: 16px; border-radius: 2px; }
    .phone-input-group input { border-radius: 0 8px 8px 0; flex: 1; }
    .pin-prompt { background: linear-gradient(135deg, #1a472a, #0d2818); border-radius: 20px; padding: 2rem; max-width: 280px; margin: 1rem auto; border: 1px solid #2E7D32; }
    .pin-prompt h4 { color: #4CAF50; text-align: center; margin-bottom: 1rem; font-size: 0.95rem; }
    .pin-dots { display: flex; justify-content: center; gap: 12px; margin: 1.5rem 0; }
    .pin-dot { width: 14px; height: 14px; border-radius: 50%; border: 2px solid #4CAF50; background: transparent; }
    .pin-dot.filled { background: #4CAF50; }
    .pin-prompt-text { color: #81C784; text-align: center; font-size: 0.8rem; }
    .receipt-card { background: var(--dark-700); border-radius: 12px; padding: 1.5rem; max-width: 360px; margin: 1rem auto; border: 1px solid var(--dark-500); }
    .receipt-row { display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px dashed var(--dark-500); font-size: 0.9rem; }
    .receipt-row:last-child { border: none; }
    .receipt-row span:first-child { color: var(--gray-400); }
    .receipt-row span:last-child { color: var(--gray-200); font-weight: 600; }
    .success-check { width: 80px; height: 80px; border-radius: 50%; background: linear-gradient(135deg, #4CAF50, #2E7D32); display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; font-size: 2rem; }
    .loading-spinner { display: inline-block; width: 20px; height: 20px; border: 3px solid rgba(255,255,255,0.3); border-top-color: #fff; border-radius: 50%; animation: spin 0.8s linear infinite; }
    @keyframes spin { to { transform: rotate(360deg); } }
    @keyframes pinFill { 0% { background: transparent; } 100% { background: #4CAF50; } }
    .pin-dot.animating { animation: pinFill 0.4s ease forwards; }
  </style>
</head>
<body>

<div class="dashboard">
  <?php require_once __DIR__ . '/../includes/member_sidebar.php'; ?>

  <div class="main-content">
    <div class="main-header">
      <div>
        <button class="sidebar-toggle" id="sidebarToggle"><i class="fas fa-bars"></i></button>
        <h1>My Billing</h1>
        <p>View your invoices and make payments</p>
      </div>
    </div>

    <div class="stats-grid" style="grid-template-columns: repeat(2,1fr)">
      <div class="stat-card">
        <div class="stat-header">
          <div><div class="stat-value"><?= formatCurrency($totalPaid) ?></div><div class="stat-label">Total Paid</div></div>
          <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-header">
          <div><div class="stat-value"><?= formatCurrency($totalOwed) ?></div><div class="stat-label">Outstanding Balance</div></div>
          <div class="stat-icon orange"><i class="fas fa-exclamation-circle"></i></div>
        </div>
      </div>
    </div>

    <!-- Purchase Pass Section -->
    <div class="card mb-2">
      <div class="card-header"><h3>Purchase Pass or Membership</h3></div>
      <div class="card-body">
        <div style="display: flex; flex-wrap: wrap; gap: 2rem; align-items: flex-start;">
          <div class="plan-selector">
            <?php foreach ($subscriptionOptions as $key => $opt): ?>
              <label class="plan-option <?= $currentPlan === $key ? 'selected' : '' ?>" onclick="selectPlan(this, '<?= $key ?>', <?= $opt['price'] ?>, '<?= addslashes($opt['name']) ?>')">
                <input type="radio" name="plan_selection" value="<?= $key ?>" <?= $currentPlan === $key ? 'checked' : '' ?>>
                <div class="plan-info">
                  <span class="plan-title"><?= htmlspecialchars($opt['name']) ?> <?php if($currentPlan === $key) echo '<span class="badge badge-success" style="font-size:0.7rem;margin-left:0.5rem">Current</span>'; ?></span>
                  <span class="plan-desc"><?= htmlspecialchars($opt['desc']) ?></span>
                </div>
                <div class="plan-price-tag">KSh <?= number_format($opt['price']) ?></div>
              </label>
            <?php endforeach; ?>
          </div>
          <div style="flex: 1; min-width: 250px;">
            <div style="background: var(--dark-700); padding: 1.5rem; border-radius: 12px; border: 1px solid var(--dark-500);">
              <h4 style="margin-bottom: 1rem; color: var(--gray-200);">Payment Summary</h4>
              <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem; color: var(--gray-300);">
                <span id="summary-name">Select a plan</span>
                <span id="summary-price" style="font-weight: bold; color: #fff;">—</span>
              </div>
              <hr style="border-color: var(--dark-500); margin: 1rem 0;">
              <button class="btn mpesa-btn" style="width: 100%;" onclick="initiatePlanPurchase()" id="btn-buy-plan">
                <i class="fas fa-credit-card"></i> Pay with M-Pesa
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><h3>My Invoices (<?= count($invoices) ?>)</h3></div>
      <div class="card-body no-padding">
        <table class="data-table">
          <thead><tr><th>Invoice #</th><th>Description</th><th>Amount</th><th>Due Date</th><th>Status</th><th>Payment</th><th>Action</th></tr></thead>
          <tbody>
            <?php if (empty($invoices)): ?>
              <tr><td colspan="7" class="text-center" style="padding:2rem;color:var(--gray-500)">No invoices yet</td></tr>
            <?php else: ?>
              <?php foreach ($invoices as $i): ?>
                <tr>
                  <td><strong><?= htmlspecialchars($i['invoice_number']) ?></strong></td>
                  <td><?= htmlspecialchars($i['description']) ?></td>
                  <td><strong><?= formatCurrency($i['total']) ?></strong></td>
                  <td><?= date('M j, Y', strtotime($i['due_date'])) ?></td>
                  <td><span class="badge badge-<?= $i['status']==='paid'?'success':($i['status']==='pending'?'warning':($i['status']==='overdue'?'danger':'info')) ?>"><?= ucfirst($i['status']) ?></span></td>
                  <td><?= $i['payment_method'] ? htmlspecialchars($i['payment_method']) : '—' ?></td>
                  <td>
                    <?php if (in_array($i['status'], ['pending', 'overdue'])): ?>
                      <button class="btn btn-sm mpesa-btn" onclick="startMpesaPayment(<?= $i['id'] ?>, <?= $i['total'] ?>, '<?= htmlspecialchars($i['invoice_number']) ?>', '<?= htmlspecialchars($i['description']) ?>')">
                        <i class="fas fa-mobile-alt"></i> Pay M-Pesa
                      </button>
                    <?php elseif ($i['status'] === 'paid'): ?>
                      <span style="color:var(--success);font-size:0.85rem"><i class="fas fa-check"></i> Paid</span>
                    <?php else: ?>
                      <span style="color:var(--gray-500);font-size:0.85rem">Cancelled</span>
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

<!-- M-Pesa Payment Modal -->
<div class="modal-overlay" id="mpesaModal">
  <div class="modal" style="max-width:440px">
    <div class="modal-header" style="border-bottom-color:#2E7D32">
      <h3 style="color:#4CAF50"><i class="fas fa-mobile-alt"></i> M-Pesa Payment</h3>
      <button class="modal-close" onclick="closeMpesa()">&times;</button>
    </div>
    <div class="modal-body" style="padding:0">

      <!-- Step 1: Enter Phone Number -->
      <div id="mpesa-step1" class="payment-step">
        <div style="background:linear-gradient(135deg,#4CAF50,#2E7D32);color:#fff;padding:1rem;border-radius:8px;margin-bottom:1.5rem">
          <div style="font-size:0.8rem;opacity:0.8">Amount to Pay</div>
          <div id="mpesa-amount" style="font-size:1.8rem;font-weight:800"></div>
          <div id="mpesa-desc" style="font-size:0.85rem;opacity:0.9;margin-top:0.25rem"></div>
        </div>

        <h3>Enter M-Pesa Phone Number</h3>
        <p>Enter the Safaricom number to receive the STK Push prompt</p>

        <div class="phone-input-group">
          <span class="phone-prefix">🇰🇪 +254</span>
          <input type="tel" id="mpesa-phone" class="form-control" placeholder="7XX XXX XXX" maxlength="10">
        </div>

        <p style="font-size:0.8rem;color:var(--gray-500);margin-top:0.5rem">
          <i class="fas fa-lock"></i> Secured by Safaricom M-Pesa
        </p>

        <button class="btn mpesa-btn" style="width:100%;padding:0.9rem;font-size:1rem;margin-top:1rem" onclick="initiateMpesa()">
          <i class="fas fa-paper-plane"></i> Send STK Push
        </button>
      </div>

      <!-- Step 2: Waiting for PIN -->
      <div id="mpesa-step2" class="payment-step" style="display:none">
        <div class="pin-prompt">
          <h4>M-Pesa Payment Request</h4>
          <div style="color:#A5D6A7;font-size:0.8rem;text-align:center;margin-bottom:1rem">
            Pay <strong id="pin-amount"></strong> to<br><strong>FitPulse Gym</strong>
          </div>
          <div class="pin-dots">
            <div class="pin-dot" id="dot1"></div>
            <div class="pin-dot" id="dot2"></div>
            <div class="pin-dot" id="dot3"></div>
            <div class="pin-dot" id="dot4"></div>
          </div>
          <div class="pin-prompt-text">Enter your M-Pesa PIN</div>
        </div>
        <p style="margin-top:1rem;color:var(--gray-400)">
          <span class="loading-spinner"></span><br><br>
          A payment prompt has been sent to <strong id="mpesa-sent-phone"></strong><br>
          <small>Please enter your M-Pesa PIN on your phone</small>
        </p>
        <button class="btn mpesa-btn" style="width:100%;padding:0.9rem;font-size:1rem;margin-top:1rem" onclick="confirmMpesa()">
          <i class="fas fa-check-circle"></i> I've Entered My PIN
        </button>
        <button class="btn btn-ghost" style="width:100%;margin-top:0.5rem" onclick="closeMpesa()">Cancel</button>
      </div>

      <!-- Step 3: Processing -->
      <div id="mpesa-step3" class="payment-step" style="display:none">
        <div style="margin:2rem 0">
          <div class="loading-spinner" style="width:50px;height:50px;border-width:4px"></div>
        </div>
        <h3>Processing Payment...</h3>
        <p>Please wait while we confirm your M-Pesa payment</p>
      </div>

      <!-- Step 4: Success -->
      <div id="mpesa-step4" class="payment-step" style="display:none">
        <div class="success-check"><i class="fas fa-check" style="color:#fff"></i></div>
        <h3 style="color:#4CAF50">Payment Successful!</h3>
        <p>Your payment has been received and recorded</p>

        <div class="receipt-card">
          <div style="text-align:center;margin-bottom:1rem;padding-bottom:0.75rem;border-bottom:2px dashed var(--dark-500)">
            <strong style="color:#4CAF50;font-size:1.1rem">M-Pesa Receipt</strong>
          </div>
          <div class="receipt-row"><span>Receipt No.</span><span id="r-receipt"></span></div>
          <div class="receipt-row"><span>Invoice</span><span id="r-invoice"></span></div>
          <div class="receipt-row"><span>Amount</span><span id="r-amount" style="color:#4CAF50 !important"></span></div>
          <div class="receipt-row"><span>Phone</span><span id="r-phone"></span></div>
          <div class="receipt-row"><span>Date</span><span id="r-date"></span></div>
          <div class="receipt-row"><span>Ref</span><span id="r-ref"></span></div>
        </div>

        <button class="btn btn-primary" style="width:100%;margin-top:1rem" onclick="location.reload()">
          <i class="fas fa-check"></i> Done
        </button>
      </div>

      <!-- Error -->
      <div id="mpesa-error" class="payment-step" style="display:none">
        <div style="width:80px;height:80px;border-radius:50%;background:var(--danger);display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;font-size:2rem">
          <i class="fas fa-times" style="color:#fff"></i>
        </div>
        <h3 style="color:var(--danger)">Payment Failed</h3>
        <p id="mpesa-error-msg" style="color:var(--gray-400)"></p>
        <button class="btn btn-primary" style="width:100%;margin-top:1rem" onclick="resetMpesa()">Try Again</button>
      </div>

    </div>
  </div>
</div>

<script src="../js/main.js"></script>
<script>
let currentInvoiceId = null;
let selectedPlan = null;
let planAmount = 0;

function startMpesaPayment(invoiceId, amount, invoiceNum, desc) {
  currentInvoiceId = invoiceId;
  selectedPlan = null;
  document.getElementById('mpesa-amount').textContent = 'KSh ' + parseFloat(amount).toLocaleString(undefined, {minimumFractionDigits: 2});
  document.getElementById('mpesa-desc').textContent = desc + ' (' + invoiceNum + ')';
  document.getElementById('pin-amount').textContent = 'KSh ' + parseFloat(amount).toLocaleString(undefined, {minimumFractionDigits: 2});
  showStep(1);
  openModal('mpesaModal');
}

function selectPlan(element, key, price, name) {
  selectedPlan = key;
  planAmount = price;
  planName = name;
  
  // Update UI selection
  document.querySelectorAll('.plan-option').forEach(el => el.classList.remove('selected'));
  element.classList.add('selected');
  
  // Update summary
  document.getElementById('summary-name').textContent = name;
  document.getElementById('summary-price').textContent = 'KSh ' + parseFloat(price).toLocaleString(undefined, {minimumFractionDigits: 2});
  
  // Enable button
  let btn = document.getElementById('btn-buy-plan');
  btn.disabled = false;
}

function initiatePlanPurchase() {
  if (!selectedPlan) {
    alert("Please select a plan to purchase.");
    return;
  }
  startPlanPurchase(selectedPlan, planAmount, planName);
}

function startPlanPurchase(planKey, amount, desc) {
  selectedPlan = planKey;
  currentInvoiceId = null;
  planAmount = amount;
  planName = desc;
  
  document.getElementById('mpesa-amount').textContent = 'KSh ' + parseFloat(amount).toLocaleString(undefined, {minimumFractionDigits: 2});
  document.getElementById('mpesa-desc').textContent = desc;
  document.getElementById('pin-amount').textContent = 'KSh ' + parseFloat(amount).toLocaleString(undefined, {minimumFractionDigits: 2});
  
  showStep(1);
  openModal('mpesaModal');
}

function showStep(n) {
  for (let i = 1; i <= 4; i++) {
    const el = document.getElementById('mpesa-step' + i);
    if (el) el.style.display = i === n ? 'block' : 'none';
  }
  document.getElementById('mpesa-error').style.display = n === 'error' ? 'block' : 'none';
}

function closeMpesa() {
  closeModal('mpesaModal');
  setTimeout(() => showStep(1), 300);
}

function resetMpesa() {
  showStep(1);
}

let planName = '';

function initiateMpesa() {
  let phone = document.getElementById('mpesa-phone').value.trim();
  if (!phone) { alert('Please enter your phone number'); return; }

  // Format: add 254 prefix if they entered 07...
  if (phone.startsWith('07') || phone.startsWith('01')) {
    phone = '254' + phone.substring(1);
  } else if (phone.startsWith('7') || phone.startsWith('1')) {
    phone = '254' + phone;
  }

  const formData = new FormData();
  
  if (selectedPlan) {
    formData.append('action', 'purchase_plan');
    formData.append('plan', selectedPlan);
    formData.append('amount', planAmount);
    formData.append('description', planName);
  } else {
    formData.append('action', 'initiate');
    formData.append('invoice_id', currentInvoiceId);
  }
  
  formData.append('phone', phone);

  // Show STK Push step
  document.getElementById('mpesa-sent-phone').textContent = phone.replace(/(\d{3})(\d{3})(\d{3})(\d{3})/, '$1 $2 $3 $4');
  showStep(2);

  // Animate PIN dots
  animatePinDots();

  fetch('../api/mpesa.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
      if (!data.success) {
        document.getElementById('mpesa-error-msg').textContent = data.message;
        showStep('error');
      }
    })
    .catch(() => {
      document.getElementById('mpesa-error-msg').textContent = 'Network error. Please check your connection.';
      showStep('error');
    });
}

function animatePinDots() {
  const dots = document.querySelectorAll('.pin-dot');
  dots.forEach(d => { d.classList.remove('filled', 'animating'); });
  let i = 0;
  const interval = setInterval(() => {
    if (i < dots.length) {
      dots[i].classList.add('animating');
      i++;
    } else {
      clearInterval(interval);
    }
  }, 800);
}

function confirmMpesa() {
  showStep(3);

  // Simulate processing delay
  setTimeout(() => {
    const formData = new FormData();
    formData.append('action', 'confirm');

    fetch('../api/mpesa.php', { method: 'POST', body: formData })
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          document.getElementById('r-receipt').textContent = data.receipt;
          document.getElementById('r-invoice').textContent = data.invoice_number;
          document.getElementById('r-amount').textContent = 'KSh ' + parseFloat(data.amount).toLocaleString(undefined, {minimumFractionDigits: 2});
          document.getElementById('r-phone').textContent = data.phone;
          document.getElementById('r-date').textContent = data.date;
          document.getElementById('r-ref').textContent = data.txn_ref;
          showStep(4);
        } else {
          document.getElementById('mpesa-error-msg').textContent = data.message;
          showStep('error');
        }
      })
      .catch(() => {
        document.getElementById('mpesa-error-msg').textContent = 'Network error. Please try again.';
        showStep('error');
      });
  }, 2500);
}
</script>
</body>
</html>
