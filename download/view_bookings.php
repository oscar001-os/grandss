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
 
$bookings      = [];
$totalBookings = 0;
$activeOrders  = 0;
$completed     = 0;
 
// ── bookings table has: id, client_id, service_type, pickup_date, delivery_date,
//                        address, notes, created_at, payment_status
// ── NO standalone 'status' column — use payment_status for order status
$sql = "SELECT id, service_type, pickup_date, delivery_date,
               address, notes, payment_status, created_at
        FROM bookings
        WHERE client_id = ?
        ORDER BY created_at DESC";
 
$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Query error: " . htmlspecialchars($conn->error));
}
 
$stmt->bind_param("i", $client_id);
$stmt->execute();
$stmt->bind_result(
    $b_id, $b_service_type, $b_pickup_date, $b_delivery_date,
    $b_address, $b_notes, $b_payment_status, $b_created_at
);
 
$raw_bookings = [];
while ($stmt->fetch()) {
    $raw_bookings[] = [
        'id'             => $b_id,
        'service_type'   => $b_service_type,
        'pickup_date'    => $b_pickup_date,
        'delivery_date'  => $b_delivery_date,
        'address'        => $b_address,
        'notes'          => $b_notes,
        'status'         => $b_payment_status, // payment_status drives the badge
        'created_at'     => $b_created_at,
        'payment_id'     => null,
        'paid_amount'    => null,
        'payment_status' => null,
    ];
}
$stmt->close();
 
// ── Fetch payments separately and merge ──
if (!empty($raw_bookings)) {
    $payment_map = [];
 
    $stmt2 = $conn->prepare(
        "SELECT id, booking_id, amount, status
         FROM payments
         WHERE client_id = ?
         ORDER BY created_at DESC"
    );
    if ($stmt2) {
        $stmt2->bind_param("i", $client_id);
        $stmt2->execute();
        $stmt2->bind_result($p_id, $p_booking_id, $p_amount, $p_status);
        while ($stmt2->fetch()) {
            if (!isset($payment_map[$p_booking_id])) {
                $payment_map[$p_booking_id] = [
                    'payment_id'     => $p_id,
                    'paid_amount'    => $p_amount,
                    'payment_status' => $p_status,
                ];
            }
        }
        $stmt2->close();
    }
 
    foreach ($raw_bookings as &$row) {
        if (isset($payment_map[$row['id']])) {
            $row = array_merge($row, $payment_map[$row['id']]);
        }
    }
    unset($row);
}
 
// ── Counts ──
foreach ($raw_bookings as $row) {
    $bookings[] = $row;
    $totalBookings++;
    if (isset($row['status']) && strtolower(trim($row['status'])) === 'delivered') {
        $completed++;
    } else {
        $activeOrders++;
    }
}
 
// ── Helpers ──
function statusConfig($status) {
    $map = [
        'delivered'      => ['label' => 'Delivered',      'bg' => '#ecfdf5', 'text' => '#065f46', 'dot' => '#10b981'],
        'in progress'    => ['label' => 'In Progress',    'bg' => '#eff6ff', 'text' => '#1e40af', 'dot' => '#3b82f6'],
        'pending pickup' => ['label' => 'Pending Pickup', 'bg' => '#fffbeb', 'text' => '#92400e', 'dot' => '#f59e0b'],
        'cancelled'      => ['label' => 'Cancelled',      'bg' => '#fef2f2', 'text' => '#991b1b', 'dot' => '#ef4444'],
        'completed'      => ['label' => 'Completed',      'bg' => '#ecfdf5', 'text' => '#065f46', 'dot' => '#10b981'],
        'paid'           => ['label' => 'Paid',           'bg' => '#ecfdf5', 'text' => '#065f46', 'dot' => '#10b981'],
        'unpaid'         => ['label' => 'Unpaid',         'bg' => '#fffbeb', 'text' => '#92400e', 'dot' => '#f59e0b'],
    ];
    $key = strtolower(trim((string)$status));
    return isset($map[$key])
        ? $map[$key]
        : ['label' => ucfirst((string)$status), 'bg' => '#f3f4f6', 'text' => '#374151', 'dot' => '#9ca3af'];
}
 
function formatDate($d) {
    if (empty($d) || $d === '0000-00-00' || $d === '0000-00-00 00:00:00') return '—';
    $ts = strtotime($d);
    return ($ts !== false) ? date('j M Y', $ts) : '—';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Bookings | Grand Superior Drycleaners</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
 
body {
    font-family: 'Inter', sans-serif;
    background: #f5f7fa;
    color: #1e293b;
    min-height: 100vh;
}
 
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
.nav-right { display: flex; align-items: center; gap: 16px; }
.nav-welcome { font-size: 13px; color: #94a3b8; }
.nav-btn {
    font-size: 13px; font-weight: 600; color: #fff;
    background: rgba(255,255,255,.1); border: none;
    padding: 7px 16px; border-radius: 8px; text-decoration: none; transition: background .2s; cursor: pointer;
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
.page-sub   { font-size: 13px; color: #64748b; margin-top: 4px; margin-bottom: 32px; }
 
/* ── Stats ── */
.stats-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 32px; }
.stat-card {
    background: #fff; border: 1px solid #e2e8f0; border-radius: 14px;
    padding: 18px 20px; display: flex; align-items: center; gap: 16px;
    box-shadow: 0 1px 4px rgba(0,0,0,.05);
}
.stat-icon {
    width: 44px; height: 44px; border-radius: 10px;
    background: #f8fafc; border: 1px solid #e2e8f0;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.stat-icon svg  { width: 20px; height: 20px; }
.stat-icon.blue  { background: #eff6ff; border-color: #bfdbfe; }
.stat-icon.green { background: #ecfdf5; border-color: #a7f3d0; }
.stat-val   { font-size: 26px; font-weight: 700; color: #0d1b2a; line-height: 1; }
.stat-label { font-size: 11px; color: #94a3b8; font-weight: 500; margin-top: 4px; text-transform: uppercase; letter-spacing: .05em; }
 
/* ── Table card ── */
.table-card {
    background: #fff; border: 1px solid #e2e8f0; border-radius: 16px;
    overflow: hidden; box-shadow: 0 1px 8px rgba(0,0,0,.06);
    margin-bottom: 32px;
}
.table-head {
    padding: 16px 24px; border-bottom: 1px solid #f1f5f9;
    display: flex; align-items: center; justify-content: space-between;
}
.table-head-title { font-size: 14px; font-weight: 600; color: #0d1b2a; }
 
.new-btn {
    display: inline-flex; align-items: center; gap: 7px;
    background: #a6ce39; color: #0d1b2a; font-size: 13px; font-weight: 700;
    padding: 8px 16px; border-radius: 8px; text-decoration: none; transition: background .2s;
}
.new-btn:hover { background: #94b934; }
.new-btn svg { width: 14px; height: 14px; }
 
.table-wrap { overflow-x: auto; }
 
table { width: 100%; border-collapse: collapse; min-width: 1000px; font-size: 13px; }
thead tr { background: #f8fafc; }
th {
    padding: 11px 16px; text-align: left; font-size: 11px; font-weight: 600;
    color: #94a3b8; text-transform: uppercase; letter-spacing: .06em;
    border-bottom: 1px solid #f1f5f9; white-space: nowrap;
}
td {
    padding: 13px 16px; color: #475569;
    border-bottom: 1px solid #f8fafc; vertical-align: middle;
}
tbody tr:last-child td { border-bottom: none; }
tbody tr:hover td { background: #fafbff; }
 
.td-id { font-weight: 700; color: #0d1b2a; white-space: nowrap; }
.td-id span { font-weight: 400; font-size: 11px; color: #94a3b8; }
 
.service-cell {
    display: inline-flex; align-items: center; gap: 7px;
    font-weight: 500; color: #334155; white-space: nowrap;
}
.service-dot { width: 7px; height: 7px; border-radius: 50%; background: #a6ce39; flex-shrink: 0; }
 
.address-cell { max-width: 160px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: block; }
.notes-cell   { max-width: 160px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-style: italic; color: #94a3b8; display: block; }
.date-cell    { white-space: nowrap; }
 
/* ── Status badge ── */
.badge {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 4px 10px; border-radius: 9999px;
    font-size: 12px; font-weight: 600; white-space: nowrap;
}
.badge-dot { width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0; }
 
/* ── Pay button ── */
.btn-pay {
    display: inline-flex; align-items: center; gap: 5px;
    background: #0d1b2a; color: #a6ce39;
    font-size: 12px; font-weight: 700;
    padding: 6px 14px; border-radius: 7px;
    text-decoration: none; white-space: nowrap;
    border: 1px solid #a6ce39;
    transition: background .2s, color .2s;
}
.btn-pay:hover { background: #a6ce39; color: #0d1b2a; }
.btn-pay svg { width: 12px; height: 12px; }
 
/* ── Paid badge ── */
.paid-badge {
    display: inline-flex; align-items: center; gap: 5px;
    background: #ecfdf5; color: #065f46;
    font-size: 12px; font-weight: 600;
    padding: 5px 12px; border-radius: 7px; white-space: nowrap;
}
.paid-badge svg { width: 12px; height: 12px; }
 
/* ── View receipt button ── */
.btn-receipt {
    display: inline-flex; align-items: center; gap: 5px;
    background: #eff6ff; color: #1e40af;
    font-size: 12px; font-weight: 700;
    padding: 6px 14px; border-radius: 7px;
    text-decoration: none; white-space: nowrap;
    border: 1px solid #bfdbfe;
    transition: background .2s, color .2s;
}
.btn-receipt:hover { background: #dbeafe; }
.btn-receipt svg { width: 12px; height: 12px; }
 
.no-receipt { color: #cbd5e1; font-size: 13px; }
 
/* ── Empty state ── */
.empty-state { padding: 64px 24px; text-align: center; }
.empty-icon {
    width: 52px; height: 52px; border-radius: 50%; background: #f1f5f9;
    display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;
}
.empty-icon svg { width: 24px; height: 24px; color: #94a3b8; }
.empty-title { font-size: 15px; font-weight: 600; color: #475569; }
.empty-sub   { font-size: 13px; color: #94a3b8; margin-top: 4px; }
 
/* ── Table footer ── */
.table-foot {
    padding: 11px 24px; border-top: 1px solid #f1f5f9;
    display: flex; align-items: center; justify-content: space-between;
    font-size: 12px; color: #94a3b8;
}
.table-foot-brand { font-weight: 600; color: #a6ce39; }
 
/* ── Back link ── */
.back-link {
    display: inline-flex; align-items: center; gap: 6px;
    font-size: 13px; font-weight: 500; color: #64748b; text-decoration: none;
    margin-top: 8px; transition: color .2s;
}
.back-link:hover { color: #0d1b2a; }
.back-link svg { width: 15px; height: 15px; }
 
/* ── Responsive ── */
@media (max-width: 768px) {
    .main { padding: 24px 16px 48px; }
    .stats-row { grid-template-columns: 1fr; gap: 12px; }
    .nav-welcome { display: none; }
    .table-head { flex-direction: column; align-items: flex-start; gap: 12px; }
    .new-btn { width: 100%; justify-content: center; }
    .page-title { font-size: 20px; }
}
</style>
</head>
<body>
 
<!-- ── Navigation ── -->
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
            <a href="client_dashboard.php" class="nav-btn">Dashboard</a>
        </div>
    </div>
</nav>
 
<!-- ── Main content ── -->
<main class="main">
 
    <div class="breadcrumb">
        <a href="client_dashboard.php">Dashboard</a>
        <span class="breadcrumb-sep">›</span>
        <span class="breadcrumb-current">My Bookings</span>
    </div>
 
    <h1 class="page-title">My Laundry Bookings</h1>
    <p class="page-sub">Track your service requests, make payments, and view receipts.</p>
 
    <!-- Stats -->
    <div class="stats-row">
 
        <div class="stat-card">
            <div class="stat-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="#475569" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
            </div>
            <div>
                <div class="stat-val"><?php echo $totalBookings; ?></div>
                <div class="stat-label">Total Bookings</div>
            </div>
        </div>
 
        <div class="stat-card">
            <div class="stat-icon blue">
                <svg viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                </svg>
            </div>
            <div>
                <div class="stat-val"><?php echo $activeOrders; ?></div>
                <div class="stat-label">Active Orders</div>
            </div>
        </div>
 
        <div class="stat-card">
            <div class="stat-icon green">
                <svg viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                </svg>
            </div>
            <div>
                <div class="stat-val"><?php echo $completed; ?></div>
                <div class="stat-label">Completed</div>
            </div>
        </div>
 
    </div>
 
    <!-- Table -->
    <div class="table-card">
 
        <div class="table-head">
            <span class="table-head-title">All Bookings</span>
            <a href="book_laundry.php" class="new-btn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                New Booking
            </a>
        </div>
 
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Booking</th>
                        <th>Service</th>
                        <th>Pickup</th>
                        <th>Delivery</th>
                        <th>Address</th>
                        <th>Notes</th>
                        <th>Status</th>
                        <th>Booked On</th>
                        <th>Payment</th>
                        <th>Receipt</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($bookings)): ?>
                    <tr>
                        <td colspan="10">
                            <div class="empty-state">
                                <div class="empty-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                    </svg>
                                </div>
                                <p class="empty-title">No bookings yet</p>
                                <p class="empty-sub">Your service requests will appear here once placed.</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($bookings as $row):
                        $status = isset($row['status']) ? (string)$row['status'] : '';
                        $cfg    = statusConfig($status);
                        $isPaid = !empty($row['payment_id'])
                               && isset($row['payment_status'])
                               && strtolower(trim($row['payment_status'])) === 'completed';
                    ?>
                    <tr>
                        <td class="td-id"><span>#</span><?php echo (int)$row['id']; ?></td>
                        <td>
                            <span class="service-cell">
                                <span class="service-dot"></span>
                                <?php echo htmlspecialchars((string)($row['service_type'] ?? '')); ?>
                            </span>
                        </td>
                        <td class="date-cell"><?php echo formatDate($row['pickup_date']); ?></td>
                        <td class="date-cell"><?php echo formatDate($row['delivery_date']); ?></td>
                        <td>
                            <span class="address-cell" title="<?php echo htmlspecialchars((string)($row['address'] ?? '')); ?>">
                                <?php echo htmlspecialchars((string)($row['address'] ?? '—')); ?>
                            </span>
                        </td>
                        <td>
                            <?php $notes = (isset($row['notes']) && $row['notes'] !== '') ? $row['notes'] : null; ?>
                            <span class="notes-cell" title="<?php echo htmlspecialchars((string)($notes ?? '')); ?>">
                                <?php echo $notes ? htmlspecialchars($notes) : '—'; ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge" style="background:<?php echo $cfg['bg']; ?>;color:<?php echo $cfg['text']; ?>">
                                <span class="badge-dot" style="background:<?php echo $cfg['dot']; ?>"></span>
                                <?php echo htmlspecialchars($cfg['label']); ?>
                            </span>
                        </td>
                        <td class="date-cell"><?php echo formatDate($row['created_at']); ?></td>
 
                        <!-- Payment -->
                        <td>
                            <?php if ($isPaid): ?>
                                <span class="paid-badge">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="20 6 9 17 4 12"/>
                                    </svg>
                                    Paid
                                </span>
                            <?php else: ?>
                                <a href="payment_history.php?booking_id=<?php echo (int)$row['id']; ?>" class="btn-pay">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/>
                                        <line x1="1" y1="10" x2="23" y2="10"/>
                                    </svg>
                                    Pay
                                </a>
                            <?php endif; ?>
                        </td>
 
                        <!-- Receipt -->
                         <td>
                            <?php if ($isPaid): ?>
                                <span class="paid-badge">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="20 6 9 17 4 12"/>
                                    </svg>
                                    generated
                                </span>
                            <?php else: ?>
                                <a href="generate_receipt.php?booking_id=<?php echo (int)$row['id']; ?>" class="btn-pay">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/>
                                        <line x1="1" y1="10" x2="23" y2="10"/>
                                    </svg>
                                    Generate
                                </a>
                            <?php endif; ?>
                        </td>
 
 
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
 
        <?php if (!empty($bookings)): ?>
        <div class="table-foot">
            <span>Showing <?php echo $totalBookings; ?> of <?php echo $totalBookings; ?> bookings</span>
            <span class="table-foot-brand">Grand Superior Drycleaners</span>
        </div>
        <?php endif; ?>
 
    </div>
 
    <a href="client_dashboard.php" class="back-link">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="15 18 9 12 15 6"/>
        </svg>
        Back to Dashboard
    </a>
 
</main>
</body>
</html>