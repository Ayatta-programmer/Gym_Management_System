<?php
// ============================================
// FitPulse - M-Pesa Payment API (Simulation)
// ============================================
// In production, this would integrate with Safaricom's
// Daraja API for real STK Push requests.
// This simulation mimics the real flow for demonstration.

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$action = $_POST['action'] ?? '';

// Step 1: Initiate STK Push (simulation)
if ($action === 'initiate') {
    $invoiceId = (int) ($_POST['invoice_id'] ?? 0);
    $phone = sanitize($_POST['phone'] ?? '');

    // Validate phone
    if (!preg_match('/^(254|0)\d{9}$/', $phone)) {
        echo json_encode(['success' => false, 'message' => 'Invalid phone number. Use format 254XXXXXXXXX or 0XXXXXXXXX']);
        exit;
    }

    // Format phone to 254 format
    if (strpos($phone, '0') === 0) {
        $phone = '254' . substr($phone, 1);
    }

    // Validate invoice
    $stmt = $pdo->prepare("SELECT i.*, u.full_name FROM invoices i JOIN users u ON i.user_id = u.id WHERE i.id = ? AND i.user_id = ?");
    $stmt->execute([$invoiceId, $_SESSION['user_id']]);
    $invoice = $stmt->fetch();

    if (!$invoice) {
        echo json_encode(['success' => false, 'message' => 'Invoice not found']);
        exit;
    }

    if ($invoice['status'] === 'paid') {
        echo json_encode(['success' => false, 'message' => 'This invoice is already paid']);
        exit;
    }

    // Generate a transaction reference
    $txnRef = 'FP' . strtoupper(substr(md5(uniqid()), 0, 8));

    // Store pending transaction in session
    $_SESSION['mpesa_pending'] = [
        'type' => 'invoice',
        'invoice_id' => $invoiceId,
        'phone' => $phone,
        'amount' => $invoice['total'],
        'txn_ref' => $txnRef,
        'initiated_at' => time(),
        'description' => $invoice['description'],
        'invoice_number' => $invoice['invoice_number']
    ];

    echo json_encode([
        'success' => true,
        'message' => "STK Push sent to $phone. Please enter your M-Pesa PIN on your phone.",
        'txn_ref' => $txnRef,
        'amount' => $invoice['total'],
        'phone' => $phone
    ]);
    exit;
}

// Step 1b: Initiate Plan Purchase
if ($action === 'purchase_plan') {
    $plan = sanitize($_POST['plan'] ?? '');
    $amount = (float) ($_POST['amount'] ?? 0);
    $desc = sanitize($_POST['description'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');

    if (!preg_match('/^(254|0)\d{9}$/', $phone)) {
        echo json_encode(['success' => false, 'message' => 'Invalid phone number']);
        exit;
    }

    // Auto-generate invoice
    $invoiceNumber = 'INV-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    $stmt = $pdo->prepare('INSERT INTO invoices (user_id, invoice_number, description, amount, tax, total, due_date, status) VALUES (?,?,?,?,?,?,?,?)');
    $stmt->execute([$_SESSION['user_id'], $invoiceNumber, $desc, $amount, 0, $amount, date('Y-m-d'), 'pending']);
    $newInvoiceId = $pdo->lastInsertId('invoices_id_seq');

    $txnRef = 'FP' . strtoupper(substr(md5(uniqid()), 0, 8));

    $_SESSION['mpesa_pending'] = [
        'type' => 'plan_purchase',
        'plan_name' => $plan,
        'invoice_id' => $newInvoiceId,
        'phone' => $phone,
        'amount' => $amount,
        'txn_ref' => $txnRef,
        'initiated_at' => time(),
        'description' => $desc,
        'invoice_number' => $invoiceNumber
    ];

    echo json_encode([
        'success' => true,
        'message' => "STK Push sent",
        'txn_ref' => $txnRef,
        'amount' => $amount,
        'phone' => $phone
    ]);
    exit;
}

// Step 2: Confirm payment (simulation)
if ($action === 'confirm') {
    $pending = $_SESSION['mpesa_pending'] ?? null;

    if (!$pending) {
        echo json_encode(['success' => false, 'message' => 'No pending transaction found']);
        exit;
    }

    // Check if transaction hasn't expired (5 minutes)
    if (time() - $pending['initiated_at'] > 300) {
        unset($_SESSION['mpesa_pending']);
        echo json_encode(['success' => false, 'message' => 'Transaction expired. Please try again.']);
        exit;
    }

    // Simulate successful payment — generate M-Pesa receipt
    $receiptNo = 'S' . strtoupper(substr(md5(time() . $pending['txn_ref']), 0, 9));

    // Update invoice to paid
    $stmt = $pdo->prepare("UPDATE invoices SET status = 'paid', paid_date = CURRENT_DATE, payment_method = ? WHERE id = ?");
    $stmt->execute(["M-Pesa ($receiptNo)", $pending['invoice_id']]);

    // If it was a plan purchase, update the user's membership plan
    if (isset($pending['type']) && $pending['type'] === 'plan_purchase' && isset($pending['plan_name'])) {
        $planStmt = $pdo->prepare("UPDATE users SET membership_plan = ? WHERE id = ?");
        $planStmt->execute([$pending['plan_name'], $_SESSION['user_id']]);
    }

    // Clear pending transaction
    $txnData = $pending;
    unset($_SESSION['mpesa_pending']);

    echo json_encode([
        'success' => true,
        'message' => 'Payment received successfully!',
        'receipt' => $receiptNo,
        'amount' => $txnData['amount'],
        'phone' => $txnData['phone'],
        'txn_ref' => $txnData['txn_ref'],
        'invoice_number' => $txnData['invoice_number'],
        'date' => date('M j, Y g:i A')
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
