<?php
session_start();
ini_set('display_errors', 0);
error_reporting(0);

include 'connection.php';

if (!isset($_SESSION['client_id'])) {
    header("Location: client_login.php");
    exit();
}

$client_id   = (int) $_SESSION['client_id'];
$client_name = isset($_SESSION['client_name']) ? $_SESSION['client_name'] : 'Client';

if (!isset($conn) || !$conn || $conn->connect_errno) {
    die("Database connection failed. Please try again later.");
}

/*
 * bookings columns : id, client_id, service_type, pickup_date, delivery_date,
 *                    address, notes, created_at, status, payment_status
 * payments columns : id, client_id, booking_id, amount, method, status,
 *                    bank_message, notes, created_at
 */

// ── Step 1: fetch all bookings for this client ──
$stmt = $conn->prepare(
    "SELECT id, service_type, pickup_date, delivery_date, address, notes, payment_status, created_at
     FROM bookings
     WHERE client_id = ?
     ORDER BY created_at DESC"
);
if (!$stmt) { die("Query error: " . htmlspecialchars($conn->error)); }

$stmt->bind_param("i", $client_id);
$stmt->execute();
$stmt->bind_result($b_id, $b_service, $b_pickup, $b_delivery, $b_address, $b_notes, $b_pay_status, $b_created);

$bookingMap    = [];
$latestBooking = null;

while ($stmt->fetch()) {
    $row = [
        'booking_id'     => $b_id,
        'service_type'   => $b_service,
        'pickup_date'    => $b_pickup,
        'delivery_date'  => $b_delivery,
        'address'        => $b_address,
        'booking_notes'  => $b_notes,
        'booking_status' => $b_pay_status,   // bookings.payment_status
        'booked_on'      => $b_created,
    ];
    $bookingMap[$b_id] = $row;
    if ($latestBooking === null) {
        $latestBooking = $row;
    }
}
$stmt->close();

// ── Step 2: fetch all payments for this client ──
$stmt2 = $conn->prepare(
    "SELECT id, booking_id, amount, method, status, bank_message, notes, created_at
     FROM payments
     WHERE client_id = ?
     ORDER BY created_at DESC"
);
if (!$stmt2) { die("Query error: " . htmlspecialchars($conn->error)); }

$stmt2->bind_param("i", $client_id);
$stmt2->execute();
$stmt2->bind_result($p_id, $p_booking_id, $p_amount, $p_method, $p_status, $p_bank_msg, $p_notes, $p_created);

$paidBookings = [];

while ($stmt2->fetch()) {
    if ($p_booking_id > 0 && isset($bookingMap[$p_booking_id])) {
        $b = $bookingMap[$p_booking_id];
    } elseif ($latestBooking !== null) {
        $b = $latestBooking;
    } else {
        $b = [
            'booking_id'     => 0,
            'service_type'   => 'General Payment',
            'pickup_date'    => '',
            'delivery_date'  => '',
            'address'        => '—',
            'booking_notes'  => '',
            'booking_status' => $p_status,
            'booked_on'      => $p_created,
        ];
    }

    $paidBookings[] = [
        'booking_id'     => $b['booking_id'],
        'service_type'   => $b['service_type'],
        'pickup_date'    => $b['pickup_date'],
        'delivery_date'  => $b['delivery_date'],
        'address'        => $b['address'],
        'booking_notes'  => $b['booking_notes'],
        'booking_status' => $b['booking_status'],   // bookings.payment_status
        'booked_on'      => $b['booked_on'],
        'payment_id'     => $p_id,
        'amount'         => $p_amount,
        'method'         => $p_method,
        'payment_status' => $p_status,              // payments.status
        'bank_message'   => $p_bank_msg,
        'payment_notes'  => $p_notes,
        'paid_on'        => $p_created,
    ];
}
$stmt2->close();

$total = count($paidBookings);

// ── Helpers ──
function formatDate($d) {
    if (empty($d) || $d === '0000-00-00') return '—';
    $ts = strtotime($d);
    return $ts !== false ? date('j M Y', $ts) : '—';
}
function formatDateTime($d) {
    if (empty($d) || $d === '0000-00-00 00:00:00') return '—';
    $ts = strtotime($d);
    return $ts !== false ? date('j M Y, g:i A', $ts) : '—';
}
function statusConfig($status) {
    $map = [
        'delivered'      => ['bg' => '#ecfdf5', 'text' => '#065f46', 'dot' => '#10b981'],
        'in progress'    => ['bg' => '#eff6ff', 'text' => '#1e40af', 'dot' => '#3b82f6'],
        'pending pickup' => ['bg' => '#fffbeb', 'text' => '#92400e', 'dot' => '#f59e0b'],
        'cancelled'      => ['bg' => '#fef2f2', 'text' => '#991b1b', 'dot' => '#ef4444'],
        'completed'      => ['bg' => '#ecfdf5', 'text' => '#065f46', 'dot' => '#10b981'],
        'paid'           => ['bg' => '#ecfdf5', 'text' => '#065f46', 'dot' => '#10b981'],
        'unpaid'         => ['bg' => '#fffbeb', 'text' => '#92400e', 'dot' => '#f59e0b'],
        'pending'        => ['bg' => '#fffbeb', 'text' => '#92400e', 'dot' => '#f59e0b'],
        'failed'         => ['bg' => '#fef2f2', 'text' => '#991b1b', 'dot' => '#ef4444'],
    ];
    $key = strtolower(trim((string)$status));
    return isset($map[$key]) ? $map[$key] : ['bg' => '#f3f4f6', 'text' => '#374151', 'dot' => '#9ca3af'];
}

// ── Helper: is the booking's payment_status exactly "Paid"? ──
function isFullyPaid($bookingPaymentStatus) {
    return strtolower(trim((string)$bookingPaymentStatus)) === 'paid';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Generate Receipt | Grand Superior Drycleaners</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

body { font-family: 'Inter', sans-serif; background: #f5f7fa; color: #1e293b; min-height: 100vh; }

/* ── Nav ── */
.topnav { background: #0d1b2a; box-shadow: 0 2px 16px rgba(0,0,0,.25); }
.topnav-inner {
    max-width: 1200px; margin: 0 auto; padding: 0 24px;
    height: 64px; display: flex; align-items: center; justify-content: space-between;
}
.brand { display: flex; align-items: center; gap: 12px; text-decoration: none; }
.brand-icon {
    width: 36px; height: 36px; border-radius: 10px; background: #a6ce39;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.brand-icon svg { width: 18px; height: 18px; }
.brand-name { font-size: 14px; font-weight: 700; color: #fff; line-height: 1.2; }
.brand-sub  { font-size: 9px; font-weight: 600; color: #a6ce39; letter-spacing: .12em; text-transform: uppercase; }
.nav-right  { display: flex; align-items: center; gap: 16px; }
.nav-welcome { font-size: 13px; color: #94a3b8; }
.nav-btn {
    font-size: 13px; font-weight: 600; color: #fff;
    background: rgba(255,255,255,.1); border: none;
    padding: 7px 16px; border-radius: 8px;
    text-decoration: none; transition: background .2s;
}
.nav-btn:hover { background: rgba(255,255,255,.18); }

/* ── Layout ── */
.main { max-width: 1200px; margin: 0 auto; padding: 36px 24px 60px; }

/* ── Breadcrumb ── */
.breadcrumb { display: flex; align-items: center; gap: 6px; font-size: 12px; color: #94a3b8; margin-bottom: 8px; }
.breadcrumb a { color: #94a3b8; text-decoration: none; }
.breadcrumb a:hover { color: #475569; }
.breadcrumb-sep { font-size: 11px; color: #cbd5e1; }
.breadcrumb-current { color: #475569; font-weight: 500; }

.page-title { font-size: 22px; font-weight: 700; color: #0d1b2a; }
.page-sub   { font-size: 13px; color: #64748b; margin-top: 4px; margin-bottom: 28px; }

/* ── Notice banner ── */
.notice-banner {
    display: flex; align-items: flex-start; gap: 12px;
    background: #fffbeb; border: 1px solid #fcd34d; border-radius: 10px;
    padding: 14px 18px; margin-bottom: 20px; font-size: 13px; color: #92400e;
}
.notice-banner svg { width: 16px; height: 16px; flex-shrink: 0; margin-top: 1px; color: #f59e0b; }

/* ── Summary banner ── */
.summary-banner {
    display: flex; align-items: center; gap: 20px; flex-wrap: wrap;
    background: linear-gradient(135deg, #0d1b2a 0%, #1a3050 100%);
    border-radius: 14px; padding: 22px 26px; margin-bottom: 28px;
}
.sb-icon {
    width: 50px; height: 50px; border-radius: 12px;
    background: rgba(166,206,57,.15); border: 1px solid rgba(166,206,57,.3);
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.sb-icon svg { width: 22px; height: 22px; color: #a6ce39; }
.sb-stat { flex: 1; min-width: 120px; }
.sb-label { font-size: 11px; font-weight: 600; color: rgba(255,255,255,.4); text-transform: uppercase; letter-spacing: .1em; }
.sb-val   { font-size: 26px; font-weight: 700; color: #fff; margin-top: 2px; }
.sb-note  { font-size: 12px; color: rgba(255,255,255,.4); margin-top: 2px; }
.sb-divider { width: 1px; height: 44px; background: rgba(255,255,255,.08); }

/* ── Table card ── */
.table-card {
    background: #fff; border: 1px solid #e2e8f0;
    border-radius: 16px; overflow: hidden;
    box-shadow: 0 1px 8px rgba(0,0,0,.06);
}
.table-head {
    padding: 16px 24px; border-bottom: 1px solid #f1f5f9;
    display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;
}
.table-head-left { display: flex; align-items: center; gap: 10px; }
.table-head-icon {
    width: 34px; height: 34px; border-radius: 9px; background: #0d1b2a;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.table-head-icon svg { width: 15px; height: 15px; color: #a6ce39; }
.table-head-title { font-size: 14px; font-weight: 600; color: #0d1b2a; }
.count-chip {
    background: #ecfdf5; color: #065f46;
    font-size: 12px; font-weight: 700; padding: 3px 9px; border-radius: 9999px;
}

.search-wrap { position: relative; }
.search-wrap input {
    padding: 7px 12px 7px 34px; background: #f8fafc;
    border: 1px solid #e2e8f0; border-radius: 8px;
    font-size: 13px; font-family: inherit; color: #1e293b;
    outline: none; transition: border-color .2s; width: 220px;
}
.search-wrap input:focus { border-color: #a6ce39; }
.search-wrap svg {
    position: absolute; left: 10px; top: 50%; transform: translateY(-50%);
    width: 14px; height: 14px; color: #94a3b8; pointer-events: none;
}

/* ── Table ── */
.table-wrap { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; min-width: 980px; font-size: 13px; }
thead tr { background: #f8fafc; }
th {
    padding: 11px 16px; text-align: left;
    font-size: 11px; font-weight: 600; color: #94a3b8;
    text-transform: uppercase; letter-spacing: .06em;
    border-bottom: 1px solid #f1f5f9; white-space: nowrap;
}
td { padding: 13px 16px; color: #475569; border-bottom: 1px solid #f8fafc; vertical-align: middle; }
tbody tr:last-child td { border-bottom: none; }
tbody tr:hover td { background: #fafbff; }

.td-id { font-weight: 700; color: #0d1b2a; white-space: nowrap; }
.receipt-no { font-size: 11px; font-weight: 400; color: #94a3b8; margin-top: 2px; }
.unlinked-tag {
    display: inline-block; font-size: 10px; font-weight: 600;
    background: #fff7ed; color: #c2410c; border: 1px solid #fed7aa;
    padding: 1px 6px; border-radius: 4px; margin-top: 3px;
}

.service-cell { display: inline-flex; align-items: center; gap: 7px; font-weight: 500; color: #334155; white-space: nowrap; }
.service-dot  { width: 7px; height: 7px; border-radius: 50%; background: #a6ce39; flex-shrink: 0; }
.date-cell    { white-space: nowrap; }

.badge { display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px; border-radius: 9999px; font-size: 12px; font-weight: 600; white-space: nowrap; }
.badge-dot { width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0; }

/* ── Payment status chip — dynamic colour ── */
.pay-status-chip {
    display: inline-flex; align-items: center; gap: 5px;
    font-size: 12px; font-weight: 600; padding: 4px 10px; border-radius: 7px;
}
.pay-status-chip svg { width: 11px; height: 11px; }
.psc-paid    { background: #ecfdf5; color: #065f46; }
.psc-pending { background: #fffbeb; color: #92400e; }
.psc-failed  { background: #fef2f2; color: #991b1b; }
.psc-other   { background: #f3f4f6; color: #374151; }

.method-chip {
    background: #f1f5f9; color: #475569;
    font-size: 12px; font-weight: 500; padding: 4px 9px; border-radius: 6px;
    display: inline-block; white-space: nowrap;
}
.amount-cell { font-weight: 700; color: #0d1b2a; white-space: nowrap; }

/* ── Receipt button ── */
.btn-gen {
    display: inline-flex; align-items: center; gap: 6px;
    background: #0d1b2a; color: #a6ce39;
    font-size: 12px; font-weight: 700;
    padding: 7px 14px; border-radius: 7px;
    text-decoration: none; white-space: nowrap;
    border: 1px solid #a6ce39;
    transition: background .2s, color .2s;
}
.btn-gen:hover { background: #a6ce39; color: #0d1b2a; }
.btn-gen svg { width: 12px; height: 12px; }

/* ── Locked receipt button (payment not Paid) ── */
.btn-locked {
    display: inline-flex; align-items: center; gap: 6px;
    background: #f8fafc; color: #94a3b8;
    font-size: 12px; font-weight: 700;
    padding: 7px 14px; border-radius: 7px;
    white-space: nowrap; border: 1px solid #e2e8f0;
    cursor: pointer; font-family: inherit;
    transition: background .2s, border-color .2s, color .2s;
}
.btn-locked:hover { background: #fff7ed; border-color: #fcd34d; color: #92400e; }
.btn-locked svg { width: 12px; height: 12px; }

/* ── Warning Modal ── */
.modal-overlay {
    display: none; position: fixed; inset: 0;
    background: rgba(13, 27, 42, 0.55); z-index: 9999;
    align-items: center; justify-content: center;
    backdrop-filter: blur(2px);
}
.modal-overlay.active { display: flex; }
.modal-box {
    background: #fff; border-radius: 18px; padding: 0;
    max-width: 440px; width: 92%;
    box-shadow: 0 24px 60px rgba(0,0,0,.22);
    animation: popIn .25s cubic-bezier(.175,.885,.32,1.275);
    overflow: hidden;
}
@keyframes popIn {
    from { transform: scale(.78); opacity: 0; }
    to   { transform: scale(1);   opacity: 1; }
}
.modal-header {
    background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
    border-bottom: 1px solid #fcd34d;
    padding: 26px 28px 20px;
    display: flex; flex-direction: column; align-items: center; text-align: center;
}
.modal-icon-wrap {
    width: 60px; height: 60px; border-radius: 50%;
    background: #fef3c7; border: 3px solid #fcd34d;
    display: flex; align-items: center; justify-content: center;
    margin-bottom: 14px;
}
.modal-icon-wrap svg { width: 28px; height: 28px; color: #d97706; }
.modal-title { font-size: 17px; font-weight: 700; color: #0d1b2a; }
.modal-body {
    padding: 22px 28px 26px;
    display: flex; flex-direction: column; align-items: center; text-align: center; gap: 10px;
}
.modal-msg {
    font-size: 14px; color: #475569; line-height: 1.7;
}
.modal-msg strong { color: #0d1b2a; }
.modal-hint {
    display: inline-flex; align-items: center; gap: 7px;
    background: #f0fdf4; border: 1px solid #bbf7d0;
    color: #065f46; font-size: 12px; font-weight: 600;
    padding: 7px 14px; border-radius: 8px;
}
.modal-hint svg { width: 13px; height: 13px; }
.modal-actions { display: flex; gap: 10px; margin-top: 6px; width: 100%; }
.modal-close-btn {
    flex: 1; padding: 10px; border-radius: 9px; font-size: 13px; font-weight: 600;
    background: #f1f5f9; color: #64748b; border: 1px solid #e2e8f0;
    cursor: pointer; font-family: inherit; transition: background .15s;
}
.modal-close-btn:hover { background: #e2e8f0; }
.modal-pay-btn {
    flex: 1; padding: 10px; border-radius: 9px; font-size: 13px; font-weight: 700;
    background: #0d1b2a; color: #a6ce39; border: 1px solid #a6ce39;
    cursor: pointer; font-family: inherit; text-decoration: none;
    display: inline-flex; align-items: center; justify-content: center; gap: 6px;
    transition: background .15s;
}
.modal-pay-btn:hover { background: #a6ce39; color: #0d1b2a; }

/* ── Empty ── */
.empty-state { padding: 64px 24px; text-align: center; }
.empty-icon {
    width: 60px; height: 60px; border-radius: 50%; background: #f1f5f9;
    display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;
}
.empty-icon svg { width: 26px; height: 26px; color: #94a3b8; }
.empty-title { font-size: 16px; font-weight: 600; color: #475569; }
.empty-sub   { font-size: 13px; color: #94a3b8; margin-top: 6px; max-width: 340px; margin-left: auto; margin-right: auto; }
.empty-link {
    display: inline-flex; align-items: center; gap: 6px; margin-top: 20px;
    font-size: 13px; font-weight: 600; color: #0d1b2a;
    background: #a6ce39; padding: 9px 18px; border-radius: 9px;
    text-decoration: none; transition: background .2s;
}
.empty-link:hover { background: #94b934; }

/* ── Table footer ── */
.table-foot {
    padding: 11px 24px; border-top: 1px solid #f1f5f9;
    display: flex; align-items: center; justify-content: space-between;
    font-size: 12px; color: #94a3b8;
}
.table-foot-brand { font-weight: 600; color: #a6ce39; }

/* ── Back link ── */
.back-link {
    display: inline-flex; align-items: center; gap: 6px; margin-top: 24px;
    font-size: 13px; font-weight: 500; color: #64748b;
    text-decoration: none; transition: color .2s;
}
.back-link:hover { color: #0d1b2a; }
.back-link svg { width: 15px; height: 15px; }

.no-results { text-align: center; padding: 28px; color: #94a3b8; font-size: 13px; font-style: italic; }

@media (max-width: 768px) {
    .main { padding: 24px 16px 48px; }
    .nav-welcome { display: none; }
    .table-head { flex-direction: column; align-items: flex-start; }
    .search-wrap input { width: 100%; }
    .sb-divider { display: none; }
}
</style>
</head>
<body>

<!-- ── Warning Modal ── -->
<div class="modal-overlay" id="paymentModal" onclick="closeModal(event)">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-icon-wrap">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                    <line x1="12" y1="9" x2="12" y2="13"/>
                    <line x1="12" y1="17" x2="12.01" y2="17"/>
                </svg>
            </div>
            <div class="modal-title">Payment Required</div>
        </div>
        <div class="modal-body">
            <p class="modal-msg">
                Your receipt is not available yet.<br>
                <strong>Please complete your payment first</strong> to generate or view your receipt for this booking.
            </p>
            <div class="modal-hint">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/>
                    <line x1="1" y1="10" x2="23" y2="10"/>
                </svg>
                Receipts are only issued for fully paid bookings
            </div>
            <div class="modal-actions">
                <button class="modal-close-btn" onclick="document.getElementById('paymentModal').classList.remove('active')">
                    Close
                </button>
                <a href="view_bookings.php" class="modal-pay-btn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="width:13px;height:13px;">
                        <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/>
                        <line x1="1" y1="10" x2="23" y2="10"/>
                    </svg>
                    Make Payment
                </a>
            </div>
        </div>
    </div>
</div>

<!-- ── Nav ── -->
<nav class="topnav">
    <div class="topnav-inner">
        <a href="client_dashboard.php" class="brand">
            <div class="brand-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="#0d1b2a" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0h6"/>
                </svg>
            </div>
            <div>
                <div class="brand-name">Grand Superior</div>
                <div class="brand-sub">Drycleaners</div>
            </div>
        </a>
        <div class="nav-right">
            <span class="nav-welcome">Welcome, <?php echo htmlspecialchars($client_name); ?></span>
            <a href="view_bookings.php" class="nav-btn">My Bookings</a>
            <a href="client_dashboard.php" class="nav-btn">Dashboard</a>
        </div>
    </div>
</nav>

<!-- ── Main ── -->
<main class="main">

    <div class="breadcrumb">
        <a href="client_dashboard.php">Dashboard</a>
        <span class="breadcrumb-sep">›</span>
        <a href="view_bookings.php">My Bookings</a>
        <span class="breadcrumb-sep">›</span>
        <span class="breadcrumb-current">Generate Receipt</span>
    </div>

    <h1 class="page-title">Generate Receipt</h1>
    <p class="page-sub">Receipts are only available for bookings with a <strong>Paid</strong> payment status. Click <strong>Generate&nbsp;/&nbsp;View</strong> to open and print your receipt.</p>

    <?php
    $hasUnlinked = false;
    foreach ($paidBookings as $pb) {
        if ((int)$pb['booking_id'] === 0) { $hasUnlinked = true; break; }
    }
    ?>

    <?php if ($hasUnlinked): ?>
    <div class="notice-banner">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
        </svg>
        <span>One or more payments are not linked to a specific booking (recorded as a general payment). Please contact our team if you need these corrected.</span>
    </div>
    <?php endif; ?>

    <?php if ($total > 0):
        $totalKes = array_sum(array_column($paidBookings, 'amount'));
    ?>
    <div class="summary-banner">
        <div class="sb-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                <polyline points="14 2 14 8 20 8"/>
                <line x1="16" y1="13" x2="8" y2="13"/>
                <line x1="16" y1="17" x2="8" y2="17"/>
            </svg>
        </div>
        <div class="sb-stat">
            <div class="sb-label">Total Bookings</div>
            <div class="sb-val"><?php echo $total; ?></div>
            <div class="sb-note">booking<?php echo $total !== 1 ? 's' : ''; ?> on record</div>
        </div>
        <div class="sb-divider"></div>
        <div class="sb-stat">
            <div class="sb-label">Total Paid</div>
            <div class="sb-val">KES <?php echo number_format($totalKes, 2); ?></div>
            <div class="sb-note">across all payments</div>
        </div>
    </div>
    <?php endif; ?>

    <div class="table-card">

        <div class="table-head">
            <div class="table-head-left">
                <div class="table-head-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                    </svg>
                </div>
                <span class="table-head-title">My Bookings</span>
                <?php if ($total > 0): ?>
                <span class="count-chip"><?php echo $total; ?></span>
                <?php endif; ?>
            </div>
            <?php if ($total > 0): ?>
            <div class="search-wrap">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <input type="text" id="searchInput" placeholder="Search by service, method…" oninput="filterTable()">
            </div>
            <?php endif; ?>
        </div>

        <div class="table-wrap">
            <table id="receiptTable">
                <thead>
                    <tr>
                        <th>Booking</th>
                        <th>Service</th>
                        <th>Pickup</th>
                        <th>Delivery</th>
                        <th>Address</th>
                        <th>Payment Status</th>
                        <th>Amount</th>
                        <th>Method</th>
                        <th>Paid On</th>
                        <th>Receipt</th>
                    </tr>
                </thead>
                <tbody>

                <?php if (empty($paidBookings)): ?>
                <tr>
                    <td colspan="10">
                        <div class="empty-state">
                            <div class="empty-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                                    <polyline points="14 2 14 8 20 8"/>
                                    <line x1="16" y1="13" x2="8" y2="13"/>
                                    <line x1="16" y1="17" x2="8" y2="17"/>
                                </svg>
                            </div>
                            <p class="empty-title">No bookings found</p>
                            <p class="empty-sub">Receipts are only available for bookings that have a recorded payment. Make a payment first.</p>
                            <a href="view_bookings.php" class="empty-link">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="width:14px;height:14px;">
                                    <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/>
                                    <line x1="1" y1="10" x2="23" y2="10"/>
                                </svg>
                                Go to My Bookings to Pay
                            </a>
                        </div>
                    </td>
                </tr>

                <?php else: ?>
                <?php foreach ($paidBookings as $row):
                    $isUnlinked  = ((int)$row['booking_id'] === 0);
                    $paidOnTs    = strtotime($row['paid_on']);
                    $receiptNo   = 'GSD-'
                                 . str_pad($row['payment_id'], 5, '0', STR_PAD_LEFT)
                                 . '-' . ($paidOnTs ? date('Y', $paidOnTs) : date('Y'));

                    // Gate: check bookings.payment_status (stored as 'booking_status')
                    $fullyPaid   = isFullyPaid($row['booking_status']);

                    // CSS class for payment status chip
                    $psKey = strtolower(trim($row['booking_status'] ?? ''));
                    $psCls = ($psKey === 'paid') ? 'psc-paid'
                           : (($psKey === 'failed') ? 'psc-failed'
                           : (($psKey === 'pending' || $psKey === '') ? 'psc-pending' : 'psc-other'));

                    // Icon for chip
                    $psIcon = $fullyPaid
                        ? '<svg viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>'
                        : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>';

                    $displayPayStatus = !empty($row['booking_status']) ? ucfirst($row['booking_status']) : 'Pending';
                ?>
                <tr>
                    <td class="td-id">
                        <?php if ($isUnlinked): ?>
                            <span style="color:#94a3b8;">—</span>
                            <div class="unlinked-tag">General</div>
                        <?php else: ?>
                            #<?php echo (int)$row['booking_id']; ?>
                        <?php endif; ?>
                        <div class="receipt-no"><?php echo htmlspecialchars($receiptNo); ?></div>
                    </td>
                    <td>
                        <span class="service-cell">
                            <span class="service-dot"></span>
                            <?php echo htmlspecialchars((string)($row['service_type'] ?? '—')); ?>
                        </span>
                    </td>
                    <td class="date-cell"><?php echo formatDate($row['pickup_date']); ?></td>
                    <td class="date-cell"><?php echo formatDate($row['delivery_date']); ?></td>
                    <td style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                        title="<?php echo htmlspecialchars((string)($row['address'] ?? '')); ?>">
                        <?php echo htmlspecialchars((string)($row['address'] ?? '—')); ?>
                    </td>
                    <td>
                        <span class="pay-status-chip <?php echo $psCls; ?>">
                            <?php echo $psIcon; ?>
                            <?php echo htmlspecialchars($displayPayStatus); ?>
                        </span>
                    </td>
                    <td class="amount-cell">KES <?php echo number_format((float)$row['amount'], 2); ?></td>
                    <td><span class="method-chip"><?php echo htmlspecialchars((string)($row['method'] ?? '—')); ?></span></td>
                    <td class="date-cell"><?php echo formatDateTime($row['paid_on']); ?></td>
                    <td>
                        <?php if ($fullyPaid): ?>
                            <!-- Payment is Paid → allow receipt -->
                            <a href="view_receipt.php?payment_id=<?php echo (int)$row['payment_id']; ?>&booking_id=<?php echo (int)$row['booking_id']; ?>"
                               class="btn-gen" target="_blank">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                                    <polyline points="14 2 14 8 20 8"/>
                                    <line x1="16" y1="13" x2="8" y2="13"/>
                                    <line x1="16" y1="17" x2="8" y2="17"/>
                                </svg>
                                Generate&nbsp;/&nbsp;View
                            </a>
                        <?php else: ?>
                            <!-- Payment not Paid → show lock button that triggers warning modal -->
                            <button class="btn-locked" onclick="openPaymentWarning()">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                                    <path d="M7 11V7a5 5 0 0110 0v4"/>
                                </svg>
                                Generate&nbsp;/&nbsp;View
                            </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>

                </tbody>
            </table>
        </div>

        <?php if ($total > 0): ?>
        <div class="table-foot">
            <span id="rowCount">Showing <?php echo $total; ?> booking<?php echo $total !== 1 ? 's' : ''; ?></span>
            <span class="table-foot-brand">Grand Superior Drycleaners</span>
        </div>
        <?php endif; ?>

    </div>

    <a href="view_bookings.php" class="back-link">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="15 18 9 12 15 6"/>
        </svg>
        Back to My Bookings
    </a>

</main>

<script>
/* ── Payment warning modal ── */
function openPaymentWarning() {
    document.getElementById('paymentModal').classList.add('active');
}
function closeModal(e) {
    // close only if clicking the dark overlay itself
    if (e.target === document.getElementById('paymentModal')) {
        document.getElementById('paymentModal').classList.remove('active');
    }
}
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.getElementById('paymentModal').classList.remove('active');
    }
});

/* ── Table search filter ── */
function filterTable() {
    const q     = document.getElementById('searchInput').value.toLowerCase().trim();
    const tbody = document.querySelector('#receiptTable tbody');
    const rows  = Array.from(tbody.querySelectorAll('tr')).filter(r => !r.classList.contains('no-results-row'));
    let shown   = 0;

    rows.forEach(row => {
        if (!row.querySelector('td')) return;
        const match = row.textContent.toLowerCase().includes(q);
        row.style.display = match ? '' : 'none';
        if (match) shown++;
    });

    const counter = document.getElementById('rowCount');
    if (counter) {
        counter.textContent = 'Showing ' + shown + ' booking' + (shown !== 1 ? 's' : '');
    }

    let noRow = tbody.querySelector('.no-results-row');
    if (shown === 0 && rows.length > 0) {
        if (!noRow) {
            noRow = document.createElement('tr');
            noRow.className = 'no-results-row';
            noRow.innerHTML = '<td colspan="10" class="no-results">No bookings match "<em>' + q.replace(/</g,'&lt;') + '</em>".</td>';
            tbody.appendChild(noRow);
        }
    } else if (noRow) {
        noRow.remove();
    }
}
</script>

</body>
</html>