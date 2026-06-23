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

// ── 1. Overall summary ──
$summary = $conn->query("
    SELECT
        COUNT(DISTINCT c.id)                                          AS total_clients,
        COUNT(b.id)                                                   AS total_bookings,
        SUM(CASE WHEN LOWER(b.payment_status)='paid' THEN 1 ELSE 0 END) AS paid_bookings,
        SUM(CASE WHEN LOWER(b.payment_status)!='paid'
                  AND b.payment_status IS NOT NULL
                  AND b.payment_status!='' THEN 1 ELSE 0 END)         AS unpaid_bookings,
        COALESCE(SUM(p.amount),0)                                     AS total_collected
    FROM clients c
    LEFT JOIN bookings b ON b.client_id = c.id
    LEFT JOIN payments p ON p.client_id = c.id AND LOWER(p.status)='paid'
")->fetch_assoc();

// ── 2. Per-client data ──
$clientsResult = $conn->query("
    SELECT
        c.id,
        c.name,
        c.email,
        c.phone,
        COUNT(b.id)                                                        AS booking_count,
        SUM(CASE WHEN LOWER(b.payment_status)='paid'  THEN 1 ELSE 0 END)  AS paid_count,
        SUM(CASE WHEN LOWER(b.payment_status)!='paid'
                  AND b.payment_status IS NOT NULL
                  AND b.payment_status!=''           THEN 1 ELSE 0 END)   AS unpaid_count,
        SUM(CASE WHEN b.payment_status IS NULL
                  OR  b.payment_status=''            THEN 1 ELSE 0 END)   AS unknown_count,
        COALESCE(
            (SELECT SUM(p2.amount)
             FROM payments p2
             WHERE p2.client_id=c.id AND LOWER(p2.status)='paid'), 0)     AS total_paid
    FROM clients c
    LEFT JOIN bookings b ON b.client_id = c.id
    GROUP BY c.id, c.name, c.email, c.phone
    ORDER BY total_paid DESC, c.name ASC
");
$clients = $clientsResult->fetch_all(MYSQLI_ASSOC);

// ── 3. Booking detail per client (for expandable rows) ──
$bookingRows = [];
$br = $conn->query("
    SELECT
        b.id, b.client_id, b.service_type, b.pickup_date, b.delivery_date,
        b.status, b.payment_status, b.created_at,
        COALESCE(
            (SELECT SUM(p.amount) FROM payments p
             WHERE p.booking_id=b.id AND LOWER(p.status)='paid'), 0
        ) AS amount_paid
    FROM bookings b
    ORDER BY b.client_id, b.created_at DESC
");
while ($r = $br->fetch_assoc()) {
    $bookingRows[$r['client_id']][] = $r;
}

// ── Helpers ──
function fmtDate($d) {
    if (empty($d) || $d==='0000-00-00') return '—';
    $ts = strtotime($d);
    return $ts ? date('d M Y',$ts) : '—';
}
function payChip($status) {
    $s = strtolower(trim($status ?? ''));
    if ($s==='paid')    return '<span class="chip chip-paid">Paid</span>';
    if ($s==='pending') return '<span class="chip chip-pending">Pending</span>';
    if ($s==='failed')  return '<span class="chip chip-failed">Failed</span>';
    return '<span class="chip chip-unknown">' . htmlspecialchars(ucfirst($status ?: 'Unknown')) . '</span>';
}
function orderChip($status) {
    $s = strtolower(trim($status ?? ''));
    $map = [
        'pending'     => 'chip-status-pending',
        'confirmed'   => 'chip-status-confirmed',
        'picked'      => 'chip-status-picked',
        'in progress' => 'chip-status-progress',
        'delivered'   => 'chip-status-delivered',
        'done'        => 'chip-status-done',
        'cancelled'   => 'chip-status-cancelled',
    ];
    $cls = $map[$s] ?? 'chip-status-unknown';
    return '<span class="chip ' . $cls . '">' . htmlspecialchars(ucfirst($status ?: 'Unknown')) . '</span>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Client Payment Report — Grand Superior</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
<style>
*, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
:root {
    --dark:#0d1b2a; --accent:#a6ce39; --bg:#f4f6f9; --white:#fff;
    --surf2:#f8f9fb; --border:rgba(0,0,0,0.08); --bh:rgba(0,0,0,0.13);
    --t1:#111827; --t2:#6b7280; --t3:#9ca3af;
    --r8:8px; --r12:12px; --r16:16px;
}
body { font-family:'Inter',sans-serif; background:var(--bg); color:var(--t1); font-size:14px; line-height:1.6; -webkit-font-smoothing:antialiased; }
a { text-decoration:none; color:inherit; }

/* ── TOPBAR ── */
.topbar {
    background:var(--dark);
    padding:0 32px; height:62px;
    display:flex; align-items:center; justify-content:space-between;
    box-shadow:0 2px 12px rgba(0,0,0,.2);
}
.tb-brand { display:flex; align-items:center; gap:10px; }
.tb-brand-icon { width:34px; height:34px; border-radius:var(--r8); background:rgba(166,206,57,.15); display:flex; align-items:center; justify-content:center; }
.tb-brand-icon i { font-size:17px; color:var(--accent); }
.tb-brand-name { font-size:15px; font-weight:700; color:#fff; }
.tb-brand-name span { color:var(--accent); }
.tb-right { display:flex; align-items:center; gap:10px; }
.tb-btn { display:inline-flex; align-items:center; gap:6px; padding:7px 15px; border-radius:var(--r8); font-size:13px; font-weight:500; color:rgba(255,255,255,.7); background:rgba(255,255,255,.07); border:0.5px solid rgba(255,255,255,.1); transition:background .15s, color .15s; }
.tb-btn:hover { background:rgba(255,255,255,.13); color:#fff; }
.tb-btn i { font-size:15px; }
.print-btn { background:rgba(166,206,57,.15); color:var(--accent); border-color:rgba(166,206,57,.3); }
.print-btn:hover { background:var(--accent); color:var(--dark); }

/* ── PAGE ── */
.page { max-width:1280px; margin:0 auto; padding:32px 28px 64px; }

.page-header { margin-bottom:28px; }
.page-title { font-size:22px; font-weight:700; color:var(--dark); display:flex; align-items:center; gap:10px; }
.page-title i { font-size:22px; color:var(--accent); }
.page-sub { font-size:13px; color:var(--t2); margin-top:4px; }

/* ── SUMMARY CARDS ── */
.summary { display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:28px; }
.scard { background:var(--white); border:0.5px solid var(--border); border-radius:var(--r16); padding:20px 22px; display:flex; align-items:center; gap:14px; }
.scard-icon { width:44px; height:44px; border-radius:var(--r12); display:flex; align-items:center; justify-content:center; flex-shrink:0; font-size:20px; }
.si-blue   { background:#eff6ff; color:#2563eb; }
.si-green  { background:#f0fdf4; color:#16a34a; }
.si-amber  { background:#fffbeb; color:#d97706; }
.si-accent { background:rgba(166,206,57,.12); color:#5a8a00; }
.scard-val { font-size:24px; font-weight:700; color:var(--dark); letter-spacing:-.02em; }
.scard-label { font-size:12px; color:var(--t2); margin-top:2px; }

/* ── SECTION HEADER ── */
.section-head { display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:14px; flex-wrap:wrap; }
.section-title { font-size:15px; font-weight:600; color:var(--dark); display:flex; align-items:center; gap:8px; }
.section-title i { font-size:17px; color:var(--t2); }
.count-pill { background:var(--surf2); border:0.5px solid var(--border); border-radius:20px; padding:3px 11px; font-size:12px; font-weight:600; color:var(--t2); }

/* ── SEARCH ── */
.search-wrap { position:relative; }
.search-wrap input { padding:8px 12px 8px 34px; background:var(--white); border:0.5px solid var(--border); border-radius:var(--r8); font-size:13px; font-family:inherit; color:var(--t1); outline:none; transition:border-color .2s; width:230px; }
.search-wrap input:focus { border-color:var(--accent); }
.search-wrap i { position:absolute; left:10px; top:50%; transform:translateY(-50%); font-size:16px; color:var(--t3); pointer-events:none; }

/* ── TABLE CARD ── */
.tcard { background:var(--white); border:0.5px solid var(--border); border-radius:var(--r16); overflow:hidden; box-shadow:0 1px 6px rgba(0,0,0,.05); }
.tcard-head { padding:16px 22px; border-bottom:0.5px solid var(--border); display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; }
.thead-left { display:flex; align-items:center; gap:10px; }
.thead-icon { width:34px; height:34px; border-radius:var(--r8); background:var(--dark); display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.thead-icon i { font-size:16px; color:var(--accent); }
.thead-label { font-size:14px; font-weight:600; color:var(--dark); }

/* ── TABLE ── */
.tbl-wrap { overflow-x:auto; }
table { width:100%; border-collapse:collapse; font-size:13px; }
thead tr { background:var(--surf2); }
th { padding:10px 16px; text-align:left; font-size:11px; font-weight:600; color:var(--t3); text-transform:uppercase; letter-spacing:.06em; border-bottom:0.5px solid var(--border); white-space:nowrap; }
td { padding:13px 16px; color:var(--t2); border-bottom:0.5px solid #f3f4f6; vertical-align:middle; }
tbody tr:last-child > td { border-bottom:none; }
tbody tr.client-row:hover > td { background:#fafbff; cursor:pointer; }
.td-strong { font-weight:600; color:var(--t1); }
.td-mono { font-family:monospace; font-size:12px; font-weight:600; color:var(--dark); }

/* ── CHIPS ── */
.chip { display:inline-flex; align-items:center; gap:4px; padding:3px 9px; border-radius:20px; font-size:11.5px; font-weight:600; white-space:nowrap; }
.chip-paid      { background:#ecfdf5; color:#065f46; border:0.5px solid #bbf7d0; }
.chip-pending   { background:#fffbeb; color:#92400e; border:0.5px solid #fde68a; }
.chip-failed    { background:#fef2f2; color:#991b1b; border:0.5px solid #fecaca; }
.chip-unknown   { background:#f3f4f6; color:#374151; border:0.5px solid #e5e7eb; }
.chip-status-pending   { background:#fffbeb; color:#92400e; border:0.5px solid #fde68a; }
.chip-status-confirmed { background:#eff6ff; color:#1d4ed8; border:0.5px solid #bfdbfe; }
.chip-status-picked    { background:#f5f3ff; color:#6d28d9; border:0.5px solid #ddd6fe; }
.chip-status-progress  { background:#ecfeff; color:#0e7490; border:0.5px solid #a5f3fc; }
.chip-status-delivered { background:#f0fdf4; color:#15803d; border:0.5px solid #bbf7d0; }
.chip-status-done      { background:#f0fdf4; color:#166534; border:0.5px solid #bbf7d0; }
.chip-status-cancelled { background:#fef2f2; color:#b91c1c; border:0.5px solid #fecaca; }
.chip-status-unknown   { background:#f3f4f6; color:#374151; border:0.5px solid #e5e7eb; }

/* ── EXPAND TOGGLE ── */
.expand-btn { background:none; border:none; cursor:pointer; color:var(--t3); font-size:18px; transition:transform .2s, color .2s; display:flex; align-items:center; }
.expand-btn:hover { color:var(--dark); }
.expand-btn.open { transform:rotate(90deg); color:var(--accent); }

/* ── DETAIL ROW ── */
.detail-row { display:none; }
.detail-row.open { display:table-row; }
.detail-cell { padding:0 !important; border:none !important; }
.detail-inner { padding:16px 22px 20px; background:linear-gradient(135deg,#f8f9fb 0%,#f0f4f8 100%); border-bottom:0.5px solid var(--border); }
.detail-title { font-size:12px; font-weight:600; color:var(--t2); text-transform:uppercase; letter-spacing:.06em; margin-bottom:10px; display:flex; align-items:center; gap:6px; }
.detail-title i { font-size:14px; }
.detail-table { width:100%; border-collapse:collapse; font-size:12.5px; background:var(--white); border-radius:var(--r8); overflow:hidden; border:0.5px solid var(--border); }
.detail-table th { padding:8px 14px; background:#f1f5f9; font-size:10.5px; font-weight:600; color:var(--t3); text-transform:uppercase; letter-spacing:.06em; border-bottom:0.5px solid var(--border); }
.detail-table td { padding:9px 14px; color:var(--t2); border-bottom:0.5px solid #f3f4f6; }
.detail-table tbody tr:last-child td { border-bottom:none; }
.detail-table tbody tr:hover td { background:#fafbff; }

/* ── AMOUNT ── */
.amt { font-weight:700; color:var(--dark); }
.amt-zero { font-weight:500; color:var(--t3); }

/* ── AVATAR ── */
.av { width:32px; height:32px; border-radius:var(--r8); background:var(--dark); color:var(--accent); font-size:12px; font-weight:700; display:inline-flex; align-items:center; justify-content:center; flex-shrink:0; }

.client-name-cell { display:flex; align-items:center; gap:10px; }

/* ── EMPTY ── */
.empty { padding:48px 24px; text-align:center; }
.empty i { font-size:36px; color:var(--t3); display:block; margin-bottom:8px; }
.empty p { color:var(--t2); font-size:14px; }

/* ── TABLE FOOT ── */
.tcard-foot { padding:11px 22px; border-top:0.5px solid var(--border); display:flex; justify-content:space-between; align-items:center; font-size:12px; color:var(--t3); }
.tcard-foot-brand { font-weight:600; color:var(--accent); }

/* ── GRAND TOTAL ROW ── */
.total-row td { font-weight:700; color:var(--dark); background:rgba(166,206,57,.07); border-top:1.5px solid rgba(166,206,57,.3) !important; }

/* ── NO RESULTS ── */
.no-results-row td { text-align:center; padding:24px; color:var(--t3); font-style:italic; }

/* ── PRINT ── */
@media print {
    .topbar, .print-btn, .expand-btn, .search-wrap, .tcard-foot { display:none !important; }
    body { background:#fff; }
    .tcard { box-shadow:none; border:1px solid #e5e7eb; }
    .detail-row { display:table-row !important; }
    .page { padding:16px; }
}

@media (max-width:900px) {
    .summary { grid-template-columns:repeat(2,1fr); }
    .page { padding:20px 16px 48px; }
    .topbar { padding:0 16px; }
}
@media (max-width:520px) {
    .summary { grid-template-columns:1fr 1fr; }
}
</style>
</head>
<body>

<!-- TOPBAR -->
<div class="topbar">
    <div class="tb-brand">
        <div class="tb-brand-icon"><i class="ti ti-shirt"></i></div>
        <div class="tb-brand-name">Grand <span>Superior</span> Drycleaners</div>
    </div>
    <div class="tb-right">
        <a href="admin_dashboard.php" class="tb-btn"><i class="ti ti-layout-dashboard"></i> Dashboard</a>
        <a href="view_payments.php"   class="tb-btn"><i class="ti ti-credit-card"></i> Payments</a>
        <button class="tb-btn print-btn" onclick="window.print()"><i class="ti ti-printer"></i> Print Report</button>
    </div>
</div>

<!-- PAGE -->
<div class="page">

    <div class="page-header">
        <div class="page-title"><i class="ti ti-chart-bar"></i> Client Payment Report</div>
        <div class="page-sub">Overview of all clients, their booking history, payment status, and total amounts collected. Generated on <?= date('l, j F Y \a\t g:i A') ?>.</div>
    </div>

    <!-- SUMMARY CARDS -->
    <div class="summary">
        <div class="scard">
            <div class="scard-icon si-blue"><i class="ti ti-users"></i></div>
            <div>
                <div class="scard-val"><?= (int)$summary['total_clients'] ?></div>
                <div class="scard-label">Total Clients</div>
            </div>
        </div>
        <div class="scard">
            <div class="scard-icon si-green"><i class="ti ti-clipboard-list"></i></div>
            <div>
                <div class="scard-val"><?= (int)$summary['total_bookings'] ?></div>
                <div class="scard-label">Total Bookings</div>
            </div>
        </div>
        <div class="scard">
            <div class="scard-icon si-amber"><i class="ti ti-circle-check"></i></div>
            <div>
                <div class="scard-val"><?= (int)$summary['paid_bookings'] ?></div>
                <div class="scard-label">Paid Bookings</div>
            </div>
        </div>
        <div class="scard">
            <div class="scard-icon si-accent"><i class="ti ti-cash"></i></div>
            <div>
                <div class="scard-val">KES <?= number_format((float)$summary['total_collected'], 2) ?></div>
                <div class="scard-label">Total Collected</div>
            </div>
        </div>
    </div>

    <!-- CLIENT TABLE -->
    <div class="section-head">
        <div class="section-title"><i class="ti ti-users"></i> Client Breakdown</div>
        <div class="search-wrap">
            <i class="ti ti-search"></i>
            <input type="text" id="clientSearch" placeholder="Search client, email, phone…" oninput="filterClients()">
        </div>
    </div>

    <div class="tcard">
        <div class="tcard-head">
            <div class="thead-left">
                <div class="thead-icon"><i class="ti ti-users"></i></div>
                <span class="thead-label">All Clients</span>
                <span class="count-pill"><?= count($clients) ?></span>
            </div>
            <span style="font-size:12px;color:var(--t3);">Click a row to expand booking details</span>
        </div>

        <div class="tbl-wrap">
            <table id="clientTable">
                <thead>
                    <tr>
                        <th style="width:36px;"></th>
                        <th>#</th>
                        <th>Client</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Bookings</th>
                        <th>Paid</th>
                        <th>Unpaid</th>
                        <th>Amount Paid</th>
                    </tr>
                </thead>
                <tbody id="clientTableBody">

                <?php if (empty($clients)): ?>
                <tr><td colspan="9"><div class="empty"><i class="ti ti-users-off"></i><p>No clients found.</p></div></td></tr>
                <?php else: ?>

                <?php
                $grandTotal = 0;
                foreach ($clients as $i => $c):
                    $grandTotal += (float)$c['total_paid'];
                    $initials = strtoupper(substr($c['name'],0,1));
                    $bkgs = $bookingRows[$c['id']] ?? [];
                ?>

                <!-- Client summary row -->
                <tr class="client-row" onclick="toggleDetail(<?= $c['id'] ?>)" id="cr-<?= $c['id'] ?>">
                    <td style="text-align:center;">
                        <button class="expand-btn" id="btn-<?= $c['id'] ?>" onclick="event.stopPropagation();toggleDetail(<?= $c['id'] ?>)" title="View bookings">
                            <i class="ti ti-chevron-right"></i>
                        </button>
                    </td>
                    <td class="td-mono"><?= str_pad($c['id'], 3, '0', STR_PAD_LEFT) ?></td>
                    <td>
                        <div class="client-name-cell">
                            <div class="av"><?= htmlspecialchars($initials) ?></div>
                            <span class="td-strong"><?= htmlspecialchars($c['name']) ?></span>
                        </div>
                    </td>
                    <td><?= htmlspecialchars($c['email'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($c['phone'] ?? '—') ?></td>
                    <td class="td-strong"><?= (int)$c['booking_count'] ?></td>
                    <td><span class="chip chip-paid"><?= (int)$c['paid_count'] ?> paid</span></td>
                    <td>
                        <?php if ((int)$c['unpaid_count'] > 0): ?>
                        <span class="chip chip-pending"><?= (int)$c['unpaid_count'] ?> pending</span>
                        <?php else: ?>
                        <span style="color:var(--t3);font-size:12px;">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="<?= (float)$c['total_paid'] > 0 ? 'amt' : 'amt-zero' ?>">
                        KES <?= number_format((float)$c['total_paid'], 2) ?>
                    </td>
                </tr>

                <!-- Expandable booking detail row -->
                <tr class="detail-row" id="dr-<?= $c['id'] ?>">
                    <td class="detail-cell" colspan="9">
                        <div class="detail-inner">
                            <div class="detail-title"><i class="ti ti-clipboard-list"></i> Bookings for <?= htmlspecialchars($c['name']) ?></div>
                            <?php if (empty($bkgs)): ?>
                            <div style="font-size:13px;color:var(--t3);padding:8px 0;">No bookings recorded for this client.</div>
                            <?php else: ?>
                            <table class="detail-table">
                                <thead>
                                    <tr>
                                        <th>Booking ID</th>
                                        <th>Service</th>
                                        <th>Pickup</th>
                                        <th>Delivery</th>
                                        <th>Order Status</th>
                                        <th>Payment Status</th>
                                        <th>Amount Paid</th>
                                        <th>Booked On</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($bkgs as $bk): ?>
                                <tr>
                                    <td class="td-mono">#<?= str_pad($bk['id'], 4, '0', STR_PAD_LEFT) ?></td>
                                    <td class="td-strong"><?= htmlspecialchars($bk['service_type'] ?? '—') ?></td>
                                    <td><?= fmtDate($bk['pickup_date']) ?></td>
                                    <td><?= fmtDate($bk['delivery_date']) ?></td>
                                    <td><?= orderChip($bk['status']) ?></td>
                                    <td><?= payChip($bk['payment_status']) ?></td>
                                    <td class="<?= (float)$bk['amount_paid'] > 0 ? 'amt' : 'amt-zero' ?>">
                                        KES <?= number_format((float)$bk['amount_paid'], 2) ?>
                                    </td>
                                    <td><?= fmtDate($bk['created_at']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>

                <?php endforeach; ?>

                <!-- Grand total row -->
                <tr class="total-row">
                    <td></td>
                    <td></td>
                    <td colspan="6" style="font-size:13px;letter-spacing:.02em;">
                        <i class="ti ti-sum" style="margin-right:6px;color:var(--accent);"></i>
                        Grand Total — All Clients Combined
                    </td>
                    <td>KES <?= number_format($grandTotal, 2) ?></td>
                </tr>

                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="tcard-foot">
            <span id="clientCount"><?= count($clients) ?> client<?= count($clients) !== 1 ? 's' : '' ?></span>
            <span class="tcard-foot-brand">Grand Superior Drycleaners</span>
        </div>
    </div>

</div><!-- /page -->

<script>
/* ── Expand / collapse detail rows ── */
function toggleDetail(id) {
    var dr  = document.getElementById('dr-' + id);
    var btn = document.getElementById('btn-' + id);
    var isOpen = dr.classList.contains('open');
    dr.classList.toggle('open', !isOpen);
    btn.classList.toggle('open', !isOpen);
}

/* ── Client search filter ── */
function filterClients() {
    var q      = document.getElementById('clientSearch').value.toLowerCase().trim();
    var tbody  = document.getElementById('clientTableBody');
    var rows   = Array.from(tbody.querySelectorAll('tr.client-row'));
    var shown  = 0;

    rows.forEach(function(row) {
        var text    = row.textContent.toLowerCase();
        var cid     = row.id.replace('cr-', '');
        var detail  = document.getElementById('dr-' + cid);
        var match   = !q || text.includes(q);
        row.style.display        = match ? '' : 'none';
        if (detail) detail.style.display = match ? '' : 'none';
        if (match) shown++;
    });

    // Grand total row always visible
    var totalRow = tbody.querySelector('.total-row');
    if (totalRow) totalRow.style.display = '';

    // No-results indicator
    var noRow = tbody.querySelector('.no-results-row');
    if (shown === 0 && rows.length > 0) {
        if (!noRow) {
            noRow = document.createElement('tr');
            noRow.className = 'no-results-row';
            noRow.innerHTML = '<td colspan="9">No clients match "<em>' + q.replace(/</g,'&lt;') + '</em>".</td>';
            // insert before the total row
            if (totalRow) tbody.insertBefore(noRow, totalRow);
            else tbody.appendChild(noRow);
        }
    } else if (noRow) {
        noRow.remove();
    }

    var counter = document.getElementById('clientCount');
    if (counter) counter.textContent = shown + ' client' + (shown !== 1 ? 's' : '');
}
</script>
</body>
</html>
