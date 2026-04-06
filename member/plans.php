<?php
require_once __DIR__ . '/../auth/auth_check.php';
checkAuth(['member']);

$memberId = $_SESSION['user_id'];
$user = getCurrentUser($pdo);
$currentPlan = strtolower($user['membership_plan'] ?? 'basic');

$plans = [
    'basic' => [
        'name' => 'Basic Plan',
        'price' => 3000,
        'features' => ['Gym Access 24/7', 'Locker Room Access', 'Basic Equipment Usage'],
        'color' => '#3498db' // blue
    ],
    'standard' => [
        'name' => 'Standard Plan',
        'price' => 5000,
        'features' => ['Gym Access 24/7', 'Locker Room Access', 'All Equipment Usage', 'Group Classes (2/week)'],
        'color' => '#f39c12' // orange
    ],
    'premium' => [
        'name' => 'Premium Plan',
        'price' => 8000,
        'features' => ['Gym Access 24/7', 'Locker Room & Sauna', 'All Equipment Usage', 'Unlimited Group Classes', '1 Personal Training Session/Month'],
        'color' => '#9b59b6' // purple
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Plans & Pricing - <?= APP_NAME ?></title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <style>
    .plans-container { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem; margin-top: 1.5rem; }
    .plan-card {
      background: var(--dark-700);
      border-radius: 12px;
      padding: 2rem;
      border: 1px solid var(--dark-500);
      position: relative;
      overflow: hidden;
      transition: transform 0.3s ease, box-shadow 0.3s ease;
      display: flex;
      flex-direction: column;
    }
    .plan-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.2); }
    .plan-card.current { border: 2px solid var(--primary); }
    .plan-badge { position: absolute; top: 1rem; right: -2rem; background: var(--primary); color: #fff; padding: 0.25rem 3rem; transform: rotate(45deg); font-size: 0.8rem; font-weight: 700; box-shadow: 0 2px 4px rgba(0,0,0,0.2); }
    .plan-header { text-align: center; margin-bottom: 2rem; position: relative; z-index: 1; }
    .plan-name { font-size: 1.25rem; font-weight: 600; margin-bottom: 0.5rem; color: var(--gray-200); }
    .plan-price { font-size: 2.5rem; font-weight: 800; color: #fff; display: flex; align-items: baseline; justify-content: center; gap: 0.25rem; }
    .plan-price sub { font-size: 1rem; color: var(--gray-400); font-weight: 500; }
    .plan-features { list-style: none; padding: 0; margin: 0 0 2rem 0; flex-grow: 1; }
    .plan-features li { padding: 0.75rem 0; color: var(--gray-300); border-bottom: 1px dashed var(--dark-500); display: flex; align-items: center; gap: 0.75rem; font-size: 0.95rem; }
    .plan-features li:last-child { border-bottom: none; }
    .plan-features li i { color: var(--success); font-size: 1.1rem; }
    
    /* M-Pesa Styles Inherited from Billing */
    .mpesa-btn { background: linear-gradient(135deg, #4CAF50, #2E7D32); border: none; color: #fff; font-weight: 700; width: 100%; }
    .mpesa-btn:hover { background: linear-gradient(135deg, #43A047, #1B5E20); transform: translateY(-1px); }
    .payment-step { text-align: center; padding: 1.5rem; }
    .payment-step h3 { margin-bottom: 0.5rem; }
    .payment-step p { color: var(--gray-400); font-size: 0.9rem; }
    .phone-input-group { display: flex; gap: 0; max-width: 320px; margin: 1rem auto; }
    .phone-prefix { background: var(--dark-700); border: 1px solid var(--dark-500); border-right: none; border-radius: 8px 0 0 8px; padding: 0.75rem 1rem; color: var(--gray-300); font-weight: 600; display: flex; align-items: center; gap: 0.5rem; }
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
        <h1>Membership Plans</h1>
        <p>Choose or upgrade your fitness journey</p>
      </div>
    </div>

    <div class="plans-container">
      <?php foreach ($plans as $key => $plan): ?>
        <div class="plan-card <?= $currentPlan === $key ? 'current' : '' ?>" style="border-top: 4px solid <?= $plan['color'] ?>">
          <?php if ($currentPlan === $key): ?>
            <div class="plan-badge">CURRENT</div>
          <?php endif; ?>
          
          <div class="plan-header">
            <div class="plan-name" style="color: <?= $plan['color'] ?>"><?= $plan['name'] ?></div>
            <div class="plan-price">
              <sub style="color: <?= $plan['color'] ?>">KSh</sub>
              <?= number_format($plan['price']) ?>
              <sub>/mo</sub>
            </div>
          </div>
          
          <ul class="plan-features">
            <?php foreach ($plan['features'] as $feature): ?>
              <li><i class="fas fa-check-circle" style="color: <?= $plan['color'] ?>"></i> <?= $feature ?></li>
            <?php endforeach; ?>
          </ul>
          
          <div class="plan-action mt-auto">
            <?php if ($currentPlan === $key): ?>
              <button class="btn btn-outline" style="width: 100%; border-color: <?= $plan['color'] ?>; color: <?= $plan['color'] ?>" onclick="startPlanPurchase('<?= $key ?>', <?= $plan['price'] ?>, 'Monthly Membership - <?= $plan['name'] ?>')">
                Renew Plan
              </button>
            <?php else: ?>
              <button class="btn mpesa-btn" onclick="startPlanPurchase('<?= $key ?>', <?= $plan['price'] ?>, 'Monthly Membership - <?= $plan['name'] ?>')">
                <i class="fas fa-credit-card"></i> Buy with M-Pesa
              </button>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
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
        <p>Your subscription is active!</p>

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
let selectedPlan = null;
let planAmount = 0;

function startPlanPurchase(planKey, amount, desc) {
  selectedPlan = planKey;
  planAmount = amount;
  
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

function initiateMpesa() {
  let phone = document.getElementById('mpesa-phone').value.trim();
  if (!phone) { alert('Please enter your phone number'); return; }

  // Format phone
  if (phone.startsWith('07') || phone.startsWith('01')) {
    phone = '254' + phone.substring(1);
  } else if (phone.startsWith('7') || phone.startsWith('1')) {
    phone = '254' + phone;
  }

  const formData = new FormData();
  formData.append('action', 'purchase_plan');
  formData.append('plan', selectedPlan);
  formData.append('phone', phone);
  formData.append('amount', planAmount);
  formData.append('description', 'Monthly Membership - ' + selectedPlan.charAt(0).toUpperCase() + selectedPlan.slice(1) + ' Plan');

  document.getElementById('mpesa-sent-phone').textContent = phone.replace(/(\d{3})(\d{3})(\d{3})(\d{3})/, '$1 $2 $3 $4');
  showStep(2);
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
      document.getElementById('mpesa-error-msg').textContent = 'Network error. Please try again.';
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
