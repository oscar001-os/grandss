<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
 
include 'connection.php';
 
/* =========================
   OWNER AUTH CHECK
========================= */
if (!isset($_SESSION['owner_id'])) {
    header("Location: owner_login.php");
    exit();
}
 
if (!$conn || (method_exists($conn, 'connect_error') && $conn->connect_error)) {
    die("Database connection failed. Please try again later.");
}
 
// ── Helpers ──────────────────────────────────────────────────
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
function receiptNo($id, $created) {
    $yr  = ($created && strtotime($created)) ? date('Y', strtotime($created)) : date('Y');
    return 'GSD-' . str_pad((int)$id, 5, '0', STR_PAD_LEFT) . '-' . $yr;
}

// ── Filters ──────────────────────────────────────────────────
$search     = isset($_GET['search'])  ? trim($_GET['search'])  : '';
$filter_st  = isset($_GET['status'])  ? trim($_GET['status'])  : '';
$filter_met = isset($_GET['method'])  ? trim($_GET['method'])  : '';
$page       = max(1, (int)(isset($_GET['page']) ? $_GET['page'] : 1));
$per_page   = 20;
$offset     = ($page - 1) * $per_page;

// ── Detail view (AJAX or direct link) ───────────────────────
$view_id = isset($_GET['view']) ? (int)$_GET['view'] : 0;
if ($view_id > 0) {
    // Fetch payment
    $s = $conn->prepare(
        "SELECT p.id, p.booking_id, p.client_id, p.amount, p.method, p.status,
                p.bank_message, p.notes, p.created_at,
                c.name AS client_name
         FROM payments p
         LEFT JOIN clients c ON c.id = p.client_id
         WHERE p.id = ?
         LIMIT 1"
    );
    $detail = null;
    if ($s) {
        $s->bind_param("i", $view_id);
        $s->execute();
        $res = $s->get_result();
        $detail = $res->fetch_assoc();
        $s->close();
    }

    // Fetch booking
    $booking = null;
    if ($detail && (int)$detail['booking_id'] > 0) {
        $bid = (int)$detail['booking_id'];
        $s2  = $conn->prepare(
            "SELECT id, service_type, pickup_date, delivery_date, address, notes, payment_status, created_at
             FROM bookings WHERE id = ? LIMIT 1"
        );
        if ($s2) {
            $s2->bind_param("i", $bid);
            $s2->execute();
            $r2 = $s2->get_result();
            $booking = $r2->fetch_assoc();
            $s2->close();
        }
    }

    // Prepare status colours
    $statusMap = [
        'delivered'      => ['bg'=>'#ecfdf5','text'=>'#065f46','dot'=>'#10b981'],
        'in progress'    => ['bg'=>'#eff6ff','text'=>'#1e40af','dot'=>'#3b82f6'],
        'pending pickup' => ['bg'=>'#fffbeb','text'=>'#92400e','dot'=>'#f59e0b'],
        'cancelled'      => ['bg'=>'#fef2f2','text'=>'#991b1b','dot'=>'#ef4444'],
        'completed'      => ['bg'=>'#ecfdf5','text'=>'#065f46','dot'=>'#10b981'],
        'paid'           => ['bg'=>'#ecfdf5','text'=>'#065f46','dot'=>'#10b981'],
        'unpaid'         => ['bg'=>'#fffbeb','text'=>'#92400e','dot'=>'#f59e0b'],
    ];
    $sKey = strtolower(trim((string)($booking['payment_status'] ?? '')));
    $sCfg = $statusMap[$sKey] ?? ['bg'=>'#f3f4f6','text'=>'#374151','dot'=>'#9ca3af'];

    $rNo = receiptNo($detail['id'], $detail['created_at']);
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Receipt <?php echo htmlspecialchars($rNo); ?> | Grand Superior Drycleaners</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Inter',sans-serif;background:#f0f2f5;color:#1e293b;min-height:100vh}
.toolbar{background:#0d1b2a;padding:0 28px;height:54px;display:flex;align-items:center;justify-content:space-between;gap:12px;position:sticky;top:0;z-index:100;box-shadow:0 2px 12px rgba(0,0,0,.3)}
.toolbar-left{display:flex;align-items:center;gap:8px}
.toolbar-title{font-size:13px;font-weight:600;color:#fff}
.toolbar-receipt-no{font-size:12px;color:#a6ce39;font-weight:700}
.toolbar-actions{display:flex;align-items:center;gap:8px}
.btn-back{display:inline-flex;align-items:center;gap:5px;font-size:12px;font-weight:600;color:#fff;background:rgba(255,255,255,.1);border:none;padding:6px 13px;border-radius:7px;text-decoration:none;transition:background .2s;cursor:pointer}
.btn-back:hover{background:rgba(255,255,255,.18)}
.btn-back svg{width:13px;height:13px}
.btn-print{display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:700;color:#0d1b2a;background:#a6ce39;border:none;padding:6px 16px;border-radius:7px;cursor:pointer;transition:background .2s}
.btn-print:hover{background:#94b934}
.btn-print svg{width:13px;height:13px}
.page-wrap{max-width:720px;margin:28px auto 48px;padding:0 16px}
.receipt-card{background:#fff;border:1px solid #dde3ec;border-radius:14px;box-shadow:0 6px 32px rgba(0,0,0,.1);overflow:hidden}
.rh{background:linear-gradient(135deg,#0d1b2a 0%,#162d47 100%);padding:26px 32px 22px}
.rh-top{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap}
.rh-logo-wrap{display:flex;align-items:center;gap:14px}
.rh-logo{height:56px;width:auto;display:block;filter:drop-shadow(0 2px 6px rgba(0,0,0,.35));object-fit:contain}
.rh-company-name{font-size:17px;font-weight:700;color:#fff;line-height:1.2;letter-spacing:.01em}
.rh-company-sub{font-size:9.5px;font-weight:600;color:#a6ce39;text-transform:uppercase;letter-spacing:.14em;margin-top:3px}
.rh-company-contact{font-size:10.5px;color:rgba(255,255,255,.38);margin-top:5px;line-height:1.55}
.rh-meta{text-align:right;flex-shrink:0}
.rh-meta-label{font-size:9.5px;font-weight:700;color:rgba(255,255,255,.35);text-transform:uppercase;letter-spacing:.12em}
.rh-meta-no{font-size:19px;font-weight:700;color:#a6ce39;letter-spacing:.04em;margin-top:3px}
.rh-meta-date{font-size:10.5px;color:rgba(255,255,255,.38);margin-top:5px}
.rh-divider{border:none;border-top:1px solid rgba(255,255,255,.1);margin:18px 0 16px}
.rh-strip{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.rh-paid-pill{display:inline-flex;align-items:center;gap:5px;background:rgba(16,185,129,.15);border:1px solid rgba(16,185,129,.3);color:#6ee7b7;font-size:11.5px;font-weight:700;padding:4px 13px;border-radius:9999px}
.rh-paid-pill svg{width:11px;height:11px}
.rh-method-pill{background:rgba(255,255,255,.09);color:rgba(255,255,255,.5);font-size:11.5px;font-weight:500;padding:4px 12px;border-radius:9999px}
.rh-amount{margin-left:auto;font-size:24px;font-weight:700;color:#fff;letter-spacing:.01em}
.rh-amount span{font-size:12px;font-weight:400;color:rgba(255,255,255,.38);margin-right:4px}
.rb{padding:22px 32px 20px}
.info-grid{display:grid;grid-template-columns:1fr 1fr;border:1px solid #edf0f5;border-radius:9px;overflow:hidden;margin-bottom:18px}
.ig-cell{padding:11px 14px;border-bottom:1px solid #edf0f5;border-right:1px solid #edf0f5}
.ig-cell:nth-child(even){border-right:none}
.ig-cell:nth-last-child(-n+2){border-bottom:none}
.ig-cell.full{grid-column:1/-1;border-right:none}
.ig-label{font-size:9.5px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.09em;margin-bottom:3px}
.ig-value{font-size:12.5px;font-weight:500;color:#1e293b;line-height:1.4}
.ig-value.large{font-size:15px;font-weight:700;color:#0d1b2a}
.ig-value.mono{font-family:'Courier New',monospace;font-size:11.5px;color:#475569}
.s-badge{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:9999px;font-size:11px;font-weight:600}
.s-dot{width:5px;height:5px;border-radius:50%;flex-shrink:0}
.sec-head{display:flex;align-items:center;gap:8px;margin:0 0 10px}
.sec-head-icon{width:22px;height:22px;border-radius:6px;background:#0d1b2a;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.sec-head-icon svg{width:11px;height:11px;color:#a6ce39}
.sec-head-label{font-size:10px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.1em}
.sec-head-line{flex:1;height:1px;background:#edf0f5}
.unlinked-note{display:flex;align-items:flex-start;gap:8px;background:#fffbeb;border:1px solid #fcd34d;border-radius:8px;padding:9px 13px;font-size:11.5px;color:#92400e;margin-bottom:16px}
.unlinked-note svg{width:13px;height:13px;flex-shrink:0;margin-top:1px;color:#f59e0b}
.rf{background:#f8fafc;border-top:1px solid #edf0f5;padding:14px 32px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
.rf-text{font-size:11.5px;color:#64748b;line-height:1.5}
.rf-text strong{color:#0d1b2a;font-weight:600}
.rf-stamp{display:inline-flex;align-items:center;gap:6px;background:#ecfdf5;border:1px solid #a7f3d0;padding:5px 14px;border-radius:9999px;font-size:11.5px;font-weight:700;color:#065f46;white-space:nowrap}
.rf-stamp svg{width:13px;height:13px;color:#10b981}
@media print{
    @page{size:A4;margin:14mm}
    body{background:#fff!important}
    .toolbar{display:none!important}
    .page-wrap{margin:0;padding:0;max-width:100%}
    .receipt-card{border:none!important;box-shadow:none!important;border-radius:0!important}
    .rh,.info-grid,.unlinked-note,.rf,.s-badge{-webkit-print-color-adjust:exact;print-color-adjust:exact}
}
@media(max-width:580px){
    .rh{padding:20px 18px 16px}
    .rb{padding:16px 18px 14px}
    .rf{padding:12px 18px}
    .info-grid{grid-template-columns:1fr}
    .ig-cell{border-right:none!important}
    .ig-cell:nth-last-child(-n+2){border-bottom:1px solid #edf0f5}
    .ig-cell:last-child{border-bottom:none}
    .rh-amount{margin-left:0;width:100%;margin-top:8px}
    .rh-meta{text-align:left}
    .rh-top{flex-direction:column;align-items:flex-start}
}
</style>
</head>
<body>

<div class="toolbar">
    <div class="toolbar-left">
        <span class="toolbar-title">Receipt</span>
        <span style="color:rgba(255,255,255,.3);font-size:13px;">/</span>
        <span class="toolbar-receipt-no"><?php echo htmlspecialchars($rNo); ?></span>
    </div>
    <div class="toolbar-actions">
        <a href="check_receipt.php" class="btn-back">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
            All Receipts
        </a>
        <button class="btn-print" onclick="window.print()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
            Print / Save PDF
        </button>
    </div>
</div>

<div class="page-wrap">
<div class="receipt-card">

    <div class="rh">
        <div class="rh-top">
            <div class="rh-logo-wrap">
                <img src="logo.jpg" alt="Grand Superior Drycleaners" class="rh-logo">
                <div class="rh-company">
                    <div class="rh-company-name">Grand Superior Drycleaners</div>
                    <div class="rh-company-sub">Official Payment Receipt</div>
                    <div class="rh-company-contact">Nairobi, Kenya &nbsp;·&nbsp; info@grandsuperior.co.ke</div>
                </div>
            </div>
            <div class="rh-meta">
                <div class="rh-meta-label">Receipt No.</div>
                <div class="rh-meta-no"><?php echo htmlspecialchars($rNo); ?></div>
                <div class="rh-meta-date">Issued: <?php echo formatDateTime($detail['created_at']); ?></div>
            </div>
        </div>
        <hr class="rh-divider">
        <div class="rh-strip">
            <div class="rh-paid-pill">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                <?php echo ucfirst(htmlspecialchars((string)$detail['status'])); ?>
            </div>
            <div class="rh-method-pill"><?php echo htmlspecialchars((string)$detail['method']); ?></div>
            <div class="rh-amount"><span>KES</span><?php echo number_format((float)$detail['amount'], 2); ?></div>
        </div>
    </div>

    <div class="rb">

        <?php if (!$booking): ?>
        <div class="unlinked-note">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            This payment is not linked to a specific booking. It was recorded as a general payment.
        </div>
        <?php endif; ?>

        <!-- Billed To -->
        <div class="sec-head">
            <div class="sec-head-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            </div>
            <span class="sec-head-label">Billed To</span>
            <div class="sec-head-line"></div>
        </div>
        <div class="info-grid" style="margin-bottom:16px;">
            <div class="ig-cell">
                <div class="ig-label">Client Name</div>
                <div class="ig-value"><?php echo htmlspecialchars((string)($detail['client_name'] ?? '—')); ?></div>
            </div>
            <div class="ig-cell">
                <div class="ig-label">Client ID</div>
                <div class="ig-value">#<?php echo (int)$detail['client_id']; ?></div>
            </div>
        </div>

        <!-- Booking Details -->
        <div class="sec-head">
            <div class="sec-head-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            </div>
            <span class="sec-head-label">Booking Details</span>
            <div class="sec-head-line"></div>
        </div>
        <?php if ($booking): ?>
        <div class="info-grid" style="margin-bottom:16px;">
            <div class="ig-cell">
                <div class="ig-label">Booking ID</div>
                <div class="ig-value">#<?php echo (int)$booking['id']; ?></div>
            </div>
            <div class="ig-cell">
                <div class="ig-label">Service</div>
                <div class="ig-value"><?php echo htmlspecialchars((string)$booking['service_type']); ?></div>
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
                        <?php echo ucwords((string)$booking['payment_status']); ?>
                    </span>
                </div>
            </div>
            <div class="ig-cell">
                <div class="ig-label">Address</div>
                <div class="ig-value"><?php echo htmlspecialchars((string)$booking['address']); ?></div>
            </div>
            <?php if (!empty($booking['notes'])): ?>
            <div class="ig-cell full">
                <div class="ig-label">Booking Notes</div>
                <div class="ig-value"><?php echo nl2br(htmlspecialchars((string)$booking['notes'])); ?></div>
            </div>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div style="background:#f8fafc;border:1px solid #edf0f5;border-radius:9px;padding:14px 16px;margin-bottom:16px;font-size:12.5px;color:#64748b;">
            No booking record linked to this payment.
        </div>
        <?php endif; ?>

        <!-- Payment Details -->
        <div class="sec-head">
            <div class="sec-head-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
            </div>
            <span class="sec-head-label">Payment Details</span>
            <div class="sec-head-line"></div>
        </div>
        <div class="info-grid">
            <div class="ig-cell">
                <div class="ig-label">Receipt No.</div>
                <div class="ig-value mono"><?php echo htmlspecialchars($rNo); ?></div>
            </div>
            <div class="ig-cell">
                <div class="ig-label">Payment ID</div>
                <div class="ig-value">#<?php echo (int)$detail['id']; ?></div>
            </div>
            <div class="ig-cell">
                <div class="ig-label">Amount Paid</div>
                <div class="ig-value large">KES <?php echo number_format((float)$detail['amount'], 2); ?></div>
            </div>
            <div class="ig-cell">
                <div class="ig-label">Method</div>
                <div class="ig-value"><?php echo htmlspecialchars((string)$detail['method']); ?></div>
            </div>
            <div class="ig-cell">
                <div class="ig-label">Payment Status</div>
                <div class="ig-value">
                    <span class="s-badge" style="background:#ecfdf5;color:#065f46;">
                        <span class="s-dot" style="background:#10b981;"></span>
                        <?php echo ucfirst(htmlspecialchars((string)$detail['status'])); ?>
                    </span>
                </div>
            </div>
            <div class="ig-cell">
                <div class="ig-label">Paid On</div>
                <div class="ig-value"><?php echo formatDateTime($detail['created_at']); ?></div>
            </div>
            <?php if (!empty($detail['bank_message'])): ?>
            <div class="ig-cell full">
                <div class="ig-label">Bank / M-Pesa Message</div>
                <div class="ig-value mono"><?php echo htmlspecialchars((string)$detail['bank_message']); ?></div>
            </div>
            <?php endif; ?>
            <?php if (!empty($detail['notes'])): ?>
            <div class="ig-cell full">
                <div class="ig-label">Payment Notes</div>
                <div class="ig-value"><?php echo nl2br(htmlspecialchars((string)$detail['notes'])); ?></div>
            </div>
            <?php endif; ?>
        </div>

    </div>

    <div class="rf">
        <div class="rf-text">
            <strong>Thank you for choosing Grand Superior Drycleaners!</strong><br>
            This is an official receipt. Please keep it for your records.
        </div>
        <div class="rf-stamp">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
            Payment Confirmed
        </div>
    </div>

</div>
</div>
</body>
</html>
    <?php
    exit();
}

// ── LIST VIEW ─────────────────────────────────────────────────────────────────

// Build WHERE clause
$where  = "WHERE 1=1";
$params = [];
$types  = "";

if ($search !== '') {
    $like = '%' . $conn->real_escape_string($search) . '%';
    $where .= " AND (c.name LIKE ? OR p.method LIKE ? OR CAST(p.id AS CHAR) LIKE ?)";
    $params[] = $like; $params[] = $like; $params[] = $like;
    $types .= "sss";
}
if ($filter_st !== '') {
    $where .= " AND p.status = ?";
    $params[] = $filter_st;
    $types .= "s";
}
if ($filter_met !== '') {
    $where .= " AND p.method = ?";
    $params[] = $filter_met;
    $types .= "s";
}

// Total count
$count_sql = "SELECT COUNT(*) FROM payments p LEFT JOIN clients c ON c.id = p.client_id $where";
$cnt_stmt  = $conn->prepare($count_sql);
$total     = 0;
if ($cnt_stmt) {
    if ($types) { $cnt_stmt->bind_param($types, ...$params); }
    $cnt_stmt->execute();
    $cnt_stmt->bind_result($total);
    $cnt_stmt->fetch();
    $cnt_stmt->close();
}
$total_pages = max(1, ceil($total / $per_page));

// Fetch page
$list_sql = "SELECT p.id, p.booking_id, p.client_id, p.amount, p.method, p.status, p.created_at,
                    c.name AS client_name
             FROM payments p
             LEFT JOIN clients c ON c.id = p.client_id
             $where
             ORDER BY p.created_at DESC
             LIMIT ? OFFSET ?";
$list_params = $params;
$list_types  = $types . "ii";
$list_params[] = $per_page;
$list_params[] = $offset;

$rows = [];
$lst  = $conn->prepare($list_sql);
if ($lst) {
    $lst->bind_param($list_types, ...$list_params);
    $lst->execute();
    $res = $lst->get_result();
    while ($row = $res->fetch_assoc()) { $rows[] = $row; }
    $lst->close();
}

// Distinct methods for filter dropdown
$methods = [];
$mres = $conn->query("SELECT DISTINCT method FROM payments WHERE method IS NOT NULL AND method != '' ORDER BY method");
if ($mres) { while ($mr = $mres->fetch_assoc()) { $methods[] = $mr['method']; } }

// Summary stats
$stats = ['total_payments'=>0,'total_amount'=>0,'paid'=>0,'pending'=>0];
$sres  = $conn->query("SELECT COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS total,
                               SUM(status='paid') AS paid, SUM(status!='paid') AS pending
                        FROM payments");
if ($sres) { $stats = $sres->fetch_assoc(); }

function qStr($extra=[]) {
    $p = $_GET;
    foreach ($extra as $k => $v) {
        if ($v === null) unset($p[$k]); else $p[$k] = $v;
    }
    unset($p['view']);
    return http_build_query($p);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Check Receipts | Grand Superior Drycleaners</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Inter',sans-serif;background:#f0f2f5;color:#1e293b;min-height:100vh}

/* ── Top bar ── */
.topbar{background:#0d1b2a;height:56px;display:flex;align-items:center;padding:0 28px;gap:14px;position:sticky;top:0;z-index:100;box-shadow:0 2px 12px rgba(0,0,0,.3)}
.topbar-logo{display:flex;align-items:center;gap:10px;text-decoration:none}
.topbar-logo img{height:34px;width:auto;object-fit:contain;filter:drop-shadow(0 1px 4px rgba(0,0,0,.3))}
.topbar-name{font-size:14px;font-weight:700;color:#fff}
.topbar-sep{width:1px;height:20px;background:rgba(255,255,255,.12)}
.topbar-title{font-size:13px;color:rgba(255,255,255,.55);font-weight:400}
.topbar-right{margin-left:auto;font-size:12px;color:rgba(255,255,255,.35)}

/* ── Page wrap ── */
.wrap{max-width:1100px;margin:0 auto;padding:24px 20px 48px}

/* ── Page header ── */
.ph{margin-bottom:20px}
.ph-title{font-size:20px;font-weight:700;color:#0d1b2a}
.ph-sub{font-size:13px;color:#64748b;margin-top:3px}

/* ── Stats ── */
.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px}
.stat-card{background:#fff;border:1px solid #dde3ec;border-radius:10px;padding:14px 16px}
.stat-label{font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.09em}
.stat-val{font-size:20px;font-weight:700;color:#0d1b2a;margin-top:4px}
.stat-val.green{color:#059669}
.stat-val.blue{color:#2563eb}

/* ── Toolbar ── */
.list-toolbar{background:#fff;border:1px solid #dde3ec;border-radius:10px;padding:12px 16px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:14px}
.search-wrap{position:relative;flex:1;min-width:180px}
.search-wrap svg{position:absolute;left:10px;top:50%;transform:translateY(-50%);width:13px;height:13px;color:#94a3b8;pointer-events:none}
.search-input{width:100%;padding:7px 10px 7px 30px;border:1px solid #e2e8f0;border-radius:7px;font-size:12.5px;outline:none;background:#f8fafc;transition:border .2s}
.search-input:focus{border-color:#a6ce39;background:#fff}
.filter-select{padding:7px 10px;border:1px solid #e2e8f0;border-radius:7px;font-size:12.5px;outline:none;background:#f8fafc;color:#374151;cursor:pointer;transition:border .2s}
.filter-select:focus{border-color:#a6ce39}
.btn-filter{background:#a6ce39;color:#0d1b2a;font-size:12px;font-weight:700;padding:7px 16px;border-radius:7px;border:none;cursor:pointer;transition:background .2s}
.btn-filter:hover{background:#94b934}
.btn-reset{background:transparent;color:#64748b;font-size:12px;font-weight:500;padding:7px 12px;border-radius:7px;border:1px solid #e2e8f0;cursor:pointer;text-decoration:none;display:inline-block;transition:all .2s}
.btn-reset:hover{background:#f1f5f9;color:#0d1b2a}

/* ── Table card ── */
.table-card{background:#fff;border:1px solid #dde3ec;border-radius:10px;overflow:hidden}
.table-wrap{overflow-x:auto}
table{width:100%;border-collapse:collapse;font-size:12.5px}
thead tr{background:#f8fafc;border-bottom:2px solid #dde3ec}
th{padding:10px 14px;text-align:left;font-size:10px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.09em;white-space:nowrap}
tbody tr{border-bottom:1px solid #f1f5f9;transition:background .15s;cursor:pointer}
tbody tr:hover{background:#f0fdf4}
tbody tr:last-child{border-bottom:none}
td{padding:11px 14px;vertical-align:middle}
td.receipt-no-cell{font-weight:700;white-space:nowrap}
.receipt-link{color:#0d1b2a;text-decoration:none;display:inline-flex;align-items:center;gap:5px;font-weight:700;font-size:12.5px;padding:4px 10px;background:#f0f9ff;border:1px solid #bae6fd;border-radius:7px;transition:all .2s}
.receipt-link:hover{background:#0d1b2a;color:#a6ce39;border-color:#0d1b2a}
.receipt-link svg{width:11px;height:11px;flex-shrink:0}
.badge{display:inline-flex;align-items:center;gap:3px;padding:3px 9px;border-radius:9999px;font-size:10.5px;font-weight:600}
.dot{width:4px;height:4px;border-radius:50%;flex-shrink:0}
.amount-cell{font-weight:700;color:#0d1b2a;white-space:nowrap}
.client-cell{color:#374151}
.date-cell{color:#64748b;white-space:nowrap}
.no-rows td{text-align:center;padding:40px;color:#94a3b8;font-size:13px}

/* ── Pagination ── */
.pagination{display:flex;align-items:center;justify-content:space-between;padding:12px 16px;border-top:1px solid #f1f5f9;flex-wrap:wrap;gap:8px}
.pg-info{font-size:12px;color:#64748b}
.pg-links{display:flex;gap:4px}
.pg-btn{display:inline-flex;align-items:center;justify-content:center;min-width:30px;height:30px;padding:0 8px;border-radius:6px;font-size:12px;font-weight:500;text-decoration:none;border:1px solid #e2e8f0;color:#374151;background:#fff;transition:all .2s}
.pg-btn:hover{background:#f1f5f9}
.pg-btn.active{background:#0d1b2a;color:#fff;border-color:#0d1b2a;font-weight:700}
.pg-btn.disabled{opacity:.4;pointer-events:none}

@media(max-width:700px){
    .stats{grid-template-columns:1fr 1fr}
    .topbar-sep,.topbar-title{display:none}
}
@media(max-width:480px){
    .stats{grid-template-columns:1fr}
    .wrap{padding:16px 12px 40px}
}
</style>
</head>
<body>

<div class="topbar">
    <a href="#" class="topbar-logo">
        <img src="logo.jpg" alt="GSD">
        <span class="topbar-name">Grand Superior Drycleaners</span>
    </a>
    <div class="topbar-sep"></div>
    <span class="topbar-title">Owner Portal</span>
    <div class="topbar-right">Receipts Manager</div>
</div>

<div class="wrap">

    <div class="ph">
        <div class="ph-title">Check Receipts</div>
        <div class="ph-sub">Click any receipt number to view the full payment and booking details.</div>
    </div>

    <!-- Stats -->
    <div class="stats">
        <div class="stat-card">
            <div class="stat-label">Total Receipts</div>
            <div class="stat-val"><?php echo number_format((int)$stats['cnt']); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Total Collected</div>
            <div class="stat-val green">KES <?php echo number_format((float)$stats['total'], 2); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Paid</div>
            <div class="stat-val blue"><?php echo number_format((int)$stats['paid']); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Pending / Other</div>
            <div class="stat-val"><?php echo number_format((int)$stats['pending']); ?></div>
        </div>
    </div>

    <!-- Search / filter toolbar -->
    <form method="GET" action="check_receipt.php">
        <div class="list-toolbar">
            <div class="search-wrap">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input class="search-input" type="text" name="search" placeholder="Search client name, method, ID…" value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <select class="filter-select" name="status">
                <option value="">All Statuses</option>
                <?php foreach(['paid','pending','unpaid','failed'] as $st): ?>
                <option value="<?php echo $st; ?>" <?php echo ($filter_st===$st)?'selected':''; ?>><?php echo ucfirst($st); ?></option>
                <?php endforeach; ?>
            </select>
            <select class="filter-select" name="method">
                <option value="">All Methods</option>
                <?php foreach($methods as $m): ?>
                <option value="<?php echo htmlspecialchars($m); ?>" <?php echo ($filter_met===$m)?'selected':''; ?>><?php echo htmlspecialchars($m); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn-filter">Search</button>
            <?php if($search||$filter_st||$filter_met): ?>
            <a href="check_receipt.php" class="btn-reset">Clear</a>
            <?php endif; ?>
        </div>
    </form>

    <!-- Table -->
    <div class="table-card">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Receipt No.</th>
                        <th>Client</th>
                        <th>Amount (KES)</th>
                        <th>Method</th>
                        <th>Status</th>
                        <th>Booking ID</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($rows)): ?>
                    <tr class="no-rows"><td colspan="7">No receipts found.</td></tr>
                <?php else: ?>
                    <?php foreach($rows as $r):
                        $rn   = receiptNo($r['id'], $r['created_at']);
                        $st   = strtolower(trim((string)$r['status']));
                        $stCfg = [
                            'paid'    =>['bg'=>'#ecfdf5','text'=>'#065f46','dot'=>'#10b981'],
                            'pending' =>['bg'=>'#fffbeb','text'=>'#92400e','dot'=>'#f59e0b'],
                            'unpaid'  =>['bg'=>'#fffbeb','text'=>'#92400e','dot'=>'#f59e0b'],
                            'failed'  =>['bg'=>'#fef2f2','text'=>'#991b1b','dot'=>'#ef4444'],
                        ][$st] ?? ['bg'=>'#f3f4f6','text'=>'#374151','dot'=>'#9ca3af'];
                    ?>
                    <tr onclick="window.location='check_receipt.php?view=<?php echo (int)$r['id']; ?>'">
                        <td class="receipt-no-cell">
                            <a class="receipt-link" href="check_receipt.php?view=<?php echo (int)$r['id']; ?>" onclick="event.stopPropagation()">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                <?php echo htmlspecialchars($rn); ?>
                            </a>
                        </td>
                        <td class="client-cell"><?php echo htmlspecialchars((string)($r['client_name']??'—')); ?></td>
                        <td class="amount-cell"><?php echo number_format((float)$r['amount'],2); ?></td>
                        <td><?php echo htmlspecialchars((string)$r['method']); ?></td>
                        <td>
                            <span class="badge" style="background:<?php echo $stCfg['bg'];?>;color:<?php echo $stCfg['text'];?>">
                                <span class="dot" style="background:<?php echo $stCfg['dot'];?>"></span>
                                <?php echo ucfirst((string)$r['status']); ?>
                            </span>
                        </td>
                        <td><?php echo $r['booking_id'] ? '#'.(int)$r['booking_id'] : '—'; ?></td>
                        <td class="date-cell"><?php echo formatDateTime($r['created_at']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if($total_pages > 1): ?>
        <div class="pagination">
            <div class="pg-info">
                Showing <?php echo min($total, $offset+1); ?>–<?php echo min($total,$offset+$per_page); ?> of <?php echo $total; ?> receipts
            </div>
            <div class="pg-links">
                <a class="pg-btn <?php echo $page<=1?'disabled':''; ?>" href="?<?php echo qStr(['page'=>$page-1]); ?>">&#8249;</a>
                <?php
                $start = max(1,$page-2);
                $end   = min($total_pages,$start+4);
                if($end-$start<4) $start=max(1,$end-4);
                for($i=$start;$i<=$end;$i++):
                ?>
                <a class="pg-btn <?php echo $i===$page?'active':''; ?>" href="?<?php echo qStr(['page'=>$i]); ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
                <a class="pg-btn <?php echo $page>=$total_pages?'disabled':''; ?>" href="?<?php echo qStr(['page'=>$page+1]); ?>">&#8250;</a>
            </div>
        </div>
        <?php endif; ?>

    </div><!-- /table-card -->

</div><!-- /wrap -->
</body>
</html>
