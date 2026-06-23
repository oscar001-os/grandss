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
 
// ── Get params ──
$payment_id = isset($_GET['payment_id']) ? (int)$_GET['payment_id'] : 0;
$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;
 
if ($payment_id <= 0) {
    die("Invalid receipt reference.");
}
 
// ── Fetch payment (must belong to this client) ──
$stmt = $conn->prepare(
    "SELECT id, booking_id, amount, method, status, bank_message, notes, created_at
     FROM payments
     WHERE id = ? AND client_id = ?
     LIMIT 1"
);
if (!$stmt) { die("Query error."); }
$stmt->bind_param("ii", $payment_id, $client_id);
$stmt->execute();
$stmt->bind_result($p_id, $p_booking_id, $p_amount, $p_method, $p_status, $p_bank_msg, $p_notes, $p_created);
$paymentFound = $stmt->fetch();
$stmt->close();
 
if (!$paymentFound) {
    die("Receipt not found or access denied.");
}
 
// ── Fetch booking ──
$booking    = null;
$lookup_id  = ($booking_id > 0) ? $booking_id : (($p_booking_id > 0) ? $p_booking_id : 0);
 
if ($lookup_id > 0) {
    $stmt2 = $conn->prepare(
        "SELECT id, service_type, pickup_date, delivery_date, address, notes, payment_status, created_at
         FROM bookings
         WHERE id = ? AND client_id = ?
         LIMIT 1"
    );
    if ($stmt2) {
        $stmt2->bind_param("ii", $lookup_id, $client_id);
        $stmt2->execute();
        $stmt2->bind_result($b_id, $b_service, $b_pickup, $b_delivery, $b_address, $b_notes, $b_pay_status, $b_created);
        if ($stmt2->fetch()) {
            $booking = [
                'booking_id'     => $b_id,
                'service_type'   => $b_service,
                'pickup_date'    => $b_pickup,
                'delivery_date'  => $b_delivery,
                'address'        => $b_address,
                'booking_notes'  => $b_notes,
                'booking_status' => $b_pay_status,
                'booked_on'      => $b_created,
            ];
        }
        $stmt2->close();
    }
}
 
// Fallback: most recent booking
if (!$booking) {
    $stmt3 = $conn->prepare(
        "SELECT id, service_type, pickup_date, delivery_date, address, notes, payment_status, created_at
         FROM bookings
         WHERE client_id = ?
         ORDER BY created_at DESC
         LIMIT 1"
    );
    if ($stmt3) {
        $stmt3->bind_param("i", $client_id);
        $stmt3->execute();
        $stmt3->bind_result($b_id, $b_service, $b_pickup, $b_delivery, $b_address, $b_notes, $b_pay_status, $b_created);
        if ($stmt3->fetch()) {
            $booking = [
                'booking_id'     => $b_id,
                'service_type'   => $b_service,
                'pickup_date'    => $b_pickup,
                'delivery_date'  => $b_delivery,
                'address'        => $b_address,
                'booking_notes'  => $b_notes,
                'booking_status' => $b_pay_status,
                'booked_on'      => $b_created,
            ];
        }
        $stmt3->close();
    }
}
 
// Last resort placeholder
if (!$booking) {
    $booking = [
        'booking_id'     => 0,
        'service_type'   => 'General Payment',
        'pickup_date'    => '',
        'delivery_date'  => '',
        'address'        => '—',
        'booking_notes'  => '',
        'booking_status' => 'paid',
        'booked_on'      => $p_created,
    ];
}
 
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
 
$paidOnTs   = strtotime($p_created);
$receiptNo  = 'GSD-' . str_pad($p_id, 5, '0', STR_PAD_LEFT) . '-' . ($paidOnTs ? date('Y', $paidOnTs) : date('Y'));
$isUnlinked = ((int)$booking['booking_id'] === 0);
 
$statusMap = [
    'delivered'      => ['bg' => '#ecfdf5', 'text' => '#065f46', 'dot' => '#10b981'],
    'in progress'    => ['bg' => '#eff6ff', 'text' => '#1e40af', 'dot' => '#3b82f6'],
    'pending pickup' => ['bg' => '#fffbeb', 'text' => '#92400e', 'dot' => '#f59e0b'],
    'cancelled'      => ['bg' => '#fef2f2', 'text' => '#991b1b', 'dot' => '#ef4444'],
    'completed'      => ['bg' => '#ecfdf5', 'text' => '#065f46', 'dot' => '#10b981'],
    'paid'           => ['bg' => '#ecfdf5', 'text' => '#065f46', 'dot' => '#10b981'],
    'unpaid'         => ['bg' => '#fffbeb', 'text' => '#92400e', 'dot' => '#f59e0b'],
];
$sKey = strtolower(trim((string)($booking['booking_status'] ?? '')));
$sCfg = isset($statusMap[$sKey]) ? $statusMap[$sKey] : ['bg' => '#f3f4f6', 'text' => '#374151', 'dot' => '#9ca3af'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Receipt <?php echo htmlspecialchars($receiptNo); ?> | Grand Superior Drycleaners</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
 
body {
    font-family: 'Inter', sans-serif;
    background: #f0f2f5;
    color: #1e293b;
    min-height: 100vh;
}
 
/* ── Screen toolbar ── */
.toolbar {
    background: #0d1b2a;
    padding: 0 28px;
    height: 54px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    position: sticky;
    top: 0;
    z-index: 100;
    box-shadow: 0 2px 12px rgba(0,0,0,.3);
}
.toolbar-left { display: flex; align-items: center; gap: 8px; }
.toolbar-title { font-size: 13px; font-weight: 600; color: #fff; }
.toolbar-receipt-no { font-size: 12px; color: #a6ce39; font-weight: 700; }
.toolbar-actions { display: flex; align-items: center; gap: 8px; }
.btn-back {
    display: inline-flex; align-items: center; gap: 5px;
    font-size: 12px; font-weight: 600; color: #fff;
    background: rgba(255,255,255,.1); border: none;
    padding: 6px 13px; border-radius: 7px; text-decoration: none;
    transition: background .2s; cursor: pointer;
}
.btn-back:hover { background: rgba(255,255,255,.18); }
.btn-back svg { width: 13px; height: 13px; }
.btn-print {
    display: inline-flex; align-items: center; gap: 6px;
    font-size: 12px; font-weight: 700; color: #0d1b2a;
    background: #a6ce39; border: none;
    padding: 6px 16px; border-radius: 7px; cursor: pointer;
    transition: background .2s;
}
.btn-print:hover { background: #94b934; }
.btn-print svg { width: 13px; height: 13px; }
 
/* ── Page wrapper ── */
.page-wrap {
    max-width: 720px;
    margin: 28px auto 48px;
    padding: 0 16px;
}
 
/* ── Receipt card ── */
.receipt-card {
    background: #fff;
    border: 1px solid #dde3ec;
    border-radius: 14px;
    box-shadow: 0 6px 32px rgba(0,0,0,.1);
    overflow: hidden;
}
 
/* ════════════════════════════
   HEADER
════════════════════════════ */
.rh {
    background: linear-gradient(135deg, #0d1b2a 0%, #162d47 100%);
    padding: 26px 32px 22px;
}
 
.rh-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
}
 
/* Logo */
.rh-logo-wrap {
    display: flex;
    align-items: center;
    gap: 14px;
}
.rh-logo {
    height: 56px;
    width: auto;
    display: block;
    /* drop-shadow so logo pops on dark bg */
    filter: drop-shadow(0 2px 6px rgba(0,0,0,.35));
    object-fit: contain;
}
.rh-company { }
.rh-company-name {
    font-size: 17px;
    font-weight: 700;
    color: #fff;
    line-height: 1.2;
    letter-spacing: .01em;
}
.rh-company-sub {
    font-size: 9.5px;
    font-weight: 600;
    color: #a6ce39;
    text-transform: uppercase;
    letter-spacing: .14em;
    margin-top: 3px;
}
.rh-company-contact {
    font-size: 10.5px;
    color: rgba(255,255,255,.38);
    margin-top: 5px;
    line-height: 1.55;
}
 
/* Receipt meta (right side) */
.rh-meta { text-align: right; flex-shrink: 0; }
.rh-meta-label {
    font-size: 9.5px;
    font-weight: 700;
    color: rgba(255,255,255,.35);
    text-transform: uppercase;
    letter-spacing: .12em;
}
.rh-meta-no {
    font-size: 19px;
    font-weight: 700;
    color: #a6ce39;
    letter-spacing: .04em;
    margin-top: 3px;
}
.rh-meta-date {
    font-size: 10.5px;
    color: rgba(255,255,255,.38);
    margin-top: 5px;
}
 
/* Divider */
.rh-divider {
    border: none;
    border-top: 1px solid rgba(255,255,255,.1);
    margin: 18px 0 16px;
}
 
/* Amount + status strip */
.rh-strip {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}
.rh-paid-pill {
    display: inline-flex; align-items: center; gap: 5px;
    background: rgba(16,185,129,.15);
    border: 1px solid rgba(16,185,129,.3);
    color: #6ee7b7;
    font-size: 11.5px; font-weight: 700;
    padding: 4px 13px; border-radius: 9999px;
}
.rh-paid-pill svg { width: 11px; height: 11px; }
.rh-method-pill {
    background: rgba(255,255,255,.09);
    color: rgba(255,255,255,.5);
    font-size: 11.5px; font-weight: 500;
    padding: 4px 12px; border-radius: 9999px;
}
.rh-amount {
    margin-left: auto;
    font-size: 24px;
    font-weight: 700;
    color: #fff;
    letter-spacing: .01em;
}
.rh-amount span {
    font-size: 12px;
    font-weight: 400;
    color: rgba(255,255,255,.38);
    margin-right: 4px;
}
 
/* ════════════════════════════
   BODY
════════════════════════════ */
.rb {
    padding: 22px 32px 20px;
}
 
/* Two-column info grid */
.info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    border: 1px solid #edf0f5;
    border-radius: 9px;
    overflow: hidden;
    margin-bottom: 18px;
}
.ig-cell {
    padding: 11px 14px;
    border-bottom: 1px solid #edf0f5;
    border-right: 1px solid #edf0f5;
}
.ig-cell:nth-child(even) { border-right: none; }
.ig-cell:nth-last-child(-n+2) { border-bottom: none; }
.ig-cell.full {
    grid-column: 1 / -1;
    border-right: none;
}
/* handle odd count — if last item is odd, span full */
.ig-cell.full:last-child { border-bottom: none; }
 
.ig-label {
    font-size: 9.5px;
    font-weight: 700;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: .09em;
    margin-bottom: 3px;
}
.ig-value {
    font-size: 12.5px;
    font-weight: 500;
    color: #1e293b;
    line-height: 1.4;
}
.ig-value.large {
    font-size: 15px;
    font-weight: 700;
    color: #0d1b2a;
}
.ig-value.mono {
    font-family: 'Courier New', monospace;
    font-size: 11.5px;
    color: #475569;
}
 
/* Status badge */
.s-badge {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 3px 9px; border-radius: 9999px;
    font-size: 11px; font-weight: 600;
}
.s-dot { width: 5px; height: 5px; border-radius: 50%; flex-shrink: 0; }
 
/* Section heading */
.sec-head {
    display: flex; align-items: center; gap: 8px;
    margin: 0 0 10px;
}
.sec-head-icon {
    width: 22px; height: 22px; border-radius: 6px; background: #0d1b2a;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.sec-head-icon svg { width: 11px; height: 11px; color: #a6ce39; }
.sec-head-label {
    font-size: 10px; font-weight: 700; color: #64748b;
    text-transform: uppercase; letter-spacing: .1em;
}
.sec-head-line { flex: 1; height: 1px; background: #edf0f5; }
 
/* Unlinked notice */
.unlinked-note {
    display: flex; align-items: flex-start; gap: 8px;
    background: #fffbeb; border: 1px solid #fcd34d;
    border-radius: 8px; padding: 9px 13px;
    font-size: 11.5px; color: #92400e; margin-bottom: 16px;
}
.unlinked-note svg { width: 13px; height: 13px; flex-shrink: 0; margin-top: 1px; color: #f59e0b; }
 
/* ════════════════════════════
   FOOTER
════════════════════════════ */
.rf {
    background: #f8fafc;
    border-top: 1px solid #edf0f5;
    padding: 14px 32px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
}
.rf-text { font-size: 11.5px; color: #64748b; line-height: 1.5; }
.rf-text strong { color: #0d1b2a; font-weight: 600; }
.rf-stamp {
    display: inline-flex; align-items: center; gap: 6px;
    background: #ecfdf5; border: 1px solid #a7f3d0;
    padding: 5px 14px; border-radius: 9999px;
    font-size: 11.5px; font-weight: 700; color: #065f46;
    white-space: nowrap;
}
.rf-stamp svg { width: 13px; height: 13px; color: #10b981; }
 
/* ════════════════════════════
   PRINT
════════════════════════════ */
@media print {
    @page { size: A4; margin: 14mm 14mm 14mm 14mm; }
    body { background: #fff !important; }
    .toolbar { display: none !important; }
    .page-wrap { margin: 0; padding: 0; max-width: 100%; }
    .receipt-card {
        border: none !important;
        box-shadow: none !important;
        border-radius: 0 !important;
    }
    .rh {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    .info-grid, .unlinked-note, .rf, .s-badge {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
}
 
/* ── Responsive ── */
@media (max-width: 580px) {
    .rh { padding: 20px 18px 16px; }
    .rb { padding: 16px 18px 14px; }
    .rf { padding: 12px 18px; }
    .info-grid { grid-template-columns: 1fr; }
    .ig-cell { border-right: none !important; }
    .ig-cell:nth-last-child(-n+2) { border-bottom: 1px solid #edf0f5; }
    .ig-cell:last-child { border-bottom: none; }
    .rh-amount { margin-left: 0; width: 100%; margin-top: 8px; }
    .rh-meta { text-align: left; }
    .rh-top { flex-direction: column; align-items: flex-start; }
}
</style>
</head>
<body>
 
<!-- ── Toolbar (screen only) ── -->
<div class="toolbar">
    <div class="toolbar-left">
        <span class="toolbar-title">Receipt</span>
        <span style="color:rgba(255,255,255,.3);font-size:13px;">/</span>
        <span class="toolbar-receipt-no"><?php echo htmlspecialchars($receiptNo); ?></span>
    </div>
    <div class="toolbar-actions">
        <a href="generate_receipt.php" class="btn-back">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="15 18 9 12 15 6"/>
            </svg>
            Back
        </a>
        <button class="btn-print" onclick="window.print()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="6 9 6 2 18 2 18 9"/>
                <path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/>
                <rect x="6" y="14" width="12" height="8"/>
            </svg>
            Print / Save PDF
        </button>
    </div>
</div>
 
<!-- ── Receipt ── -->
<div class="page-wrap">
<div class="receipt-card">
 
    <!-- ── Header ── -->
    <div class="rh">
        <div class="rh-top">
 
            <!-- Logo + company name -->
            <div class="rh-logo-wrap">
                <img src="logo.jpg" alt="Grand Superior Drycleaners Logo" class="rh-logo">
                <div class="rh-company">
                    <div class="rh-company-name">Grand Superior Drycleaners</div>
                    <div class="rh-company-sub">Official Payment Receipt</div>
                    <div class="rh-company-contact">
                        Nairobi, Kenya &nbsp;·&nbsp; info@grandsuperior.co.ke
                    </div>
                </div>
            </div>
 
            <!-- Receipt number + date -->
            <div class="rh-meta">
                <div class="rh-meta-label">Receipt No.</div>
                <div class="rh-meta-no"><?php echo htmlspecialchars($receiptNo); ?></div>
                <div class="rh-meta-date">Issued: <?php echo formatDateTime($p_created); ?></div>
            </div>
 
        </div>
 
        <hr class="rh-divider">
 
        <!-- Amount + status strip -->
        <div class="rh-strip">
            <div class="rh-paid-pill">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="20 6 9 17 4 12"/>
                </svg>
                <?php echo ucfirst(htmlspecialchars((string)$p_status)); ?>
            </div>
            <div class="rh-method-pill"><?php echo htmlspecialchars((string)$p_method); ?></div>
            <div class="rh-amount">
                <span>KES</span><?php echo number_format((float)$p_amount, 2); ?>
            </div>
        </div>
    </div>
 
    <!-- ── Body ── -->
    <div class="rb">
 
        <?php if ($isUnlinked): ?>
        <div class="unlinked-note">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/>
                <line x1="12" y1="8" x2="12" y2="12"/>
                <line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
            This payment was recorded as a general payment and is not linked to a specific booking. Please contact us if you need this corrected.
        </div>
        <?php endif; ?>
 
        <!-- Client -->
        <div class="sec-head">
            <div class="sec-head-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/>
                    <circle cx="12" cy="7" r="4"/>
                </svg>
            </div>
            <span class="sec-head-label">Billed To</span>
            <div class="sec-head-line"></div>
        </div>
 
        <div class="info-grid" style="margin-bottom:16px;">
            <div class="ig-cell">
                <div class="ig-label">Client Name</div>
                <div class="ig-value"><?php echo htmlspecialchars($client_name); ?></div>
            </div>
            <div class="ig-cell">
                <div class="ig-label">Client ID</div>
                <div class="ig-value">#<?php echo $client_id; ?></div>
            </div>
        </div>
 
        <!-- Booking Details -->
        <div class="sec-head">
            <div class="sec-head-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                    <line x1="16" y1="2" x2="16" y2="6"/>
                    <line x1="8" y1="2" x2="8" y2="6"/>
                    <line x1="3" y1="10" x2="21" y2="10"/>
                </svg>
            </div>
            <span class="sec-head-label">Booking Details</span>
            <div class="sec-head-line"></div>
        </div>
 
        <div class="info-grid" style="margin-bottom:16px;">
            <div class="ig-cell">
                <div class="ig-label">Booking ID</div>
                <div class="ig-value">
                    <?php echo ($booking['booking_id'] > 0) ? '#' . (int)$booking['booking_id'] : '—'; ?>
                </div>
            </div>
            <div class="ig-cell">
                <div class="ig-label">Service</div>
                <div class="ig-value"><?php echo htmlspecialchars((string)($booking['service_type'] ?? '—')); ?></div>
            </div>
            <div class="ig-cell">
                <div class="ig-label">Pickup Date</div>
                <div class="ig-value"><?php echo formatDate($booking['pickup_date']); ?></div>
            </div>
            <div class="ig-cell">
                <div class="ig-label">Delivery Date</div>
                <div class="ig-value"><?php echo formatDate($booking['delivery_date']); ?></div>
            </div>
            <div class="ig-cell">
                <div class="ig-label">Booking Status</div>
                <div class="ig-value">
                    <span class="s-badge" style="background:<?php echo $sCfg['bg']; ?>;color:<?php echo $sCfg['text']; ?>">
                        <span class="s-dot" style="background:<?php echo $sCfg['dot']; ?>"></span>
                        <?php echo ucwords((string)($booking['booking_status'] ?? '—')); ?>
                    </span>
                </div>
            </div>
            <div class="ig-cell">
                <div class="ig-label">Address</div>
                <div class="ig-value"><?php echo htmlspecialchars((string)($booking['address'] ?? '—')); ?></div>
            </div>
        </div>
 
        <!-- Payment Details -->
        <div class="sec-head">
            <div class="sec-head-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/>
                    <line x1="1" y1="10" x2="23" y2="10"/>
                </svg>
            </div>
            <span class="sec-head-label">Payment Details</span>
            <div class="sec-head-line"></div>
        </div>
 
        <div class="info-grid">
            <div class="ig-cell">
                <div class="ig-label">Receipt No.</div>
                <div class="ig-value mono"><?php echo htmlspecialchars($receiptNo); ?></div>
            </div>
            <div class="ig-cell">
                <div class="ig-label">Payment ID</div>
                <div class="ig-value">#<?php echo (int)$p_id; ?></div>
            </div>
            <div class="ig-cell">
                <div class="ig-label">Amount Paid</div>
                <div class="ig-value large">KES <?php echo number_format((float)$p_amount, 2); ?></div>
            </div>
            <div class="ig-cell">
                <div class="ig-label">Method</div>
                <div class="ig-value"><?php echo htmlspecialchars((string)$p_method); ?></div>
            </div>
            <div class="ig-cell">
                <div class="ig-label">Payment Status</div>
                <div class="ig-value">
                    <span class="s-badge" style="background:#ecfdf5;color:#065f46;">
                        <span class="s-dot" style="background:#10b981;"></span>
                        <?php echo ucfirst(htmlspecialchars((string)$p_status)); ?>
                    </span>
                </div>
            </div>
            <div class="ig-cell">
                <div class="ig-label">Paid On</div>
                <div class="ig-value"><?php echo formatDateTime($p_created); ?></div>
            </div>
        </div>
 
    </div><!-- /rb -->
 
    <!-- ── Footer ── -->
    <div class="rf">
        <div class="rf-text">
            <strong>Thank you for choosing Grand Superior Drycleaners!</strong><br>
            This is an official receipt. Please keep it for your records.
        </div>
        <div class="rf-stamp">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="20 6 9 17 4 12"/>
            </svg>
            Payment Confirmed
        </div>
    </div>
 
</div><!-- /receipt-card -->
</div><!-- /page-wrap -->
 
</body>
</html>