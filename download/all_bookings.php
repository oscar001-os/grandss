<?php
session_start();
include("connection.php");

if (!isset($_SESSION['rider_id'])) {
    header("Location: rider_login.php");
    exit();
}

$sql    = "SELECT * FROM bookings ORDER BY id DESC";
$result = $conn->query($sql);
if (!$result) { die("Database Error: " . $conn->error); }

$total = $result->num_rows;
$rows  = $result->fetch_all(MYSQLI_ASSOC);

$statusCounts = [];
foreach ($rows as $row) {
    $s = strtolower(trim($row['status']));
    $statusCounts[$s] = ($statusCounts[$s] ?? 0) + 1;
}

function getStatusBadge($status) {
    $s = strtolower(trim($status));
    $map = [
        'pending'     => ['pending',    'Pending'],
        'confirmed'   => ['confirmed',  'Confirmed'],
        'in_progress' => ['progress',   'In Progress'],
        'in progress' => ['progress',   'In Progress'],
        'processing'  => ['progress',   'Processing'],
        'delivered'   => ['delivered',  'Delivered'],
        'completed'   => ['completed',  'Completed'],
        'cancelled'   => ['cancelled',  'Cancelled'],
        'canceled'    => ['cancelled',  'Cancelled'],
        'failed'      => ['failed',     'Failed'],
    ];
    $info = $map[$s] ?? ['default', ucfirst($status)];
    return '<span class="badge badge-' . $info[0] . '"><span class="bdot"></span>' . htmlspecialchars($info[1]) . '</span>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>All Bookings — Grand Superior</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">

<style>
*, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }

:root {
    --dark:    #0d1b2a;
    --accent:  #a6ce39;
    --bg:      #f4f5f7;
    --white:   #ffffff;
    --surf2:   #f8f9fb;
    --border:  rgba(0,0,0,0.08);
    --bh:      rgba(0,0,0,0.14);
    --t1:      #111827;
    --t2:      #6b7280;
    --t3:      #9ca3af;
    --rmd:     8px;
    --rlg:     12px;
    --rxl:     16px;
}

body { font-family:'Inter',sans-serif; background:var(--bg); color:var(--t1); min-height:100vh; font-size:14px; line-height:1.6; -webkit-font-smoothing:antialiased; }
a { text-decoration:none; color:inherit; }

/* TOPBAR */
.topbar { background:var(--dark); height:64px; display:flex; align-items:center; padding:0 28px; gap:16px; position:sticky; top:0; z-index:100; border-bottom:1px solid rgba(255,255,255,0.06); }
.brand { display:flex; align-items:center; gap:10px; }
.brand-icon { width:36px; height:36px; border-radius:var(--rmd); background:rgba(166,206,57,0.15); display:flex; align-items:center; justify-content:center; }
.brand-icon i { font-size:18px; color:var(--accent); }
.brand-name { font-size:15px; font-weight:600; color:#fff; }
.brand-name span { color:var(--accent); }
.topbar-sep { width:1px; height:24px; background:rgba(255,255,255,0.1); }
.back-link { display:inline-flex; align-items:center; gap:6px; font-size:13px; font-weight:500; color:rgba(255,255,255,0.55); padding:6px 12px; border-radius:var(--rmd); transition:background .15s, color .15s; }
.back-link:hover { background:rgba(255,255,255,0.07); color:#fff; }
.back-link i { font-size:15px; }

/* PAGE */
.page { max-width:1300px; margin:0 auto; padding:28px 24px 48px; }

/* PAGE HEADER */
.page-header { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; margin-bottom:24px; }
.page-header-left { display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
.page-title { font-size:20px; font-weight:600; color:var(--t1); letter-spacing:-0.02em; }
.page-title span { color:var(--dark); }
.record-pill { background:var(--surf2); border:0.5px solid var(--border); border-radius:20px; padding:4px 12px; font-size:12px; font-weight:500; color:var(--t2); }

/* STATS */
.stats { display:grid; grid-template-columns:repeat(auto-fill,minmax(130px,1fr)); gap:12px; margin-bottom:24px; }
.stat { background:var(--white); border:0.5px solid var(--border); border-radius:var(--rlg); padding:16px 18px; }
.stat-label { font-size:11px; font-weight:500; color:var(--t3); text-transform:uppercase; letter-spacing:0.06em; margin-bottom:6px; display:flex; align-items:center; gap:5px; }
.stat-dot { width:7px; height:7px; border-radius:50%; flex-shrink:0; }
.stat-value { font-size:22px; font-weight:600; color:var(--t1); letter-spacing:-0.02em; }

/* TOOLBAR */
.toolbar { display:flex; align-items:center; gap:10px; margin-bottom:16px; flex-wrap:wrap; }
.search-wrap { position:relative; flex:1; min-width:220px; }
.search-wrap i { position:absolute; left:11px; top:50%; transform:translateY(-50%); font-size:16px; color:var(--t3); pointer-events:none; }
.search-input { width:100%; background:var(--white); border:0.5px solid var(--border); border-radius:var(--rmd); padding:9px 12px 9px 34px; font-family:'Inter',sans-serif; font-size:13px; color:var(--t1); outline:none; transition:border-color .15s; }
.search-input:focus { border-color:#a6ce39; }
.search-input::placeholder { color:var(--t3); }
.filter-select { background:var(--white); border:0.5px solid var(--border); border-radius:var(--rmd); padding:9px 14px; font-family:'Inter',sans-serif; font-size:13px; color:var(--t1); outline:none; cursor:pointer; transition:border-color .15s; }
.filter-select:focus { border-color:#a6ce39; }

/* TABLE CARD */
.table-card { background:var(--white); border:0.5px solid var(--border); border-radius:var(--rxl); overflow:hidden; }
.table-scroll { overflow-x:auto; }
table { width:100%; border-collapse:collapse; font-size:13px; }
thead tr { background:var(--surf2); border-bottom:0.5px solid var(--border); }
thead th { padding:12px 16px; text-align:left; font-size:11px; font-weight:600; color:var(--t3); text-transform:uppercase; letter-spacing:0.07em; white-space:nowrap; }
thead th:first-child { padding-left:20px; }
tbody tr { border-bottom:0.5px solid var(--border); transition:background .12s; }
tbody tr:last-child { border-bottom:none; }
tbody tr:hover { background:var(--surf2); }
td { padding:13px 16px; color:var(--t2); vertical-align:middle; white-space:nowrap; }
td:first-child { padding-left:20px; }

.td-id { font-family:monospace; font-size:12px; font-weight:600; color:var(--dark); }
.td-client { font-weight:500; color:var(--t1); }
.td-service { display:inline-flex; align-items:center; background:var(--surf2); border:0.5px solid var(--border); border-radius:6px; padding:3px 10px; font-size:12px; color:var(--t1); }
.td-date { font-size:12px; }
.td-truncate { max-width:160px; overflow:hidden; text-overflow:ellipsis; font-size:12px; }

/* STATUS BADGES */
.badge { display:inline-flex; align-items:center; gap:5px; padding:4px 10px; border-radius:20px; font-size:11.5px; font-weight:600; white-space:nowrap; }
.bdot { width:6px; height:6px; border-radius:50%; flex-shrink:0; }

.badge-pending   { background:#fffbeb; color:#b45309; border:0.5px solid #fde68a; }
.badge-pending .bdot   { background:#f59e0b; }

.badge-confirmed { background:#eff6ff; color:#1d4ed8; border:0.5px solid #bfdbfe; }
.badge-confirmed .bdot { background:#3b82f6; }

.badge-progress  { background:#ecfeff; color:#0e7490; border:0.5px solid #a5f3fc; }
.badge-progress .bdot  { background:#06b6d4; }

.badge-delivered { background:#f0fdf4; color:#15803d; border:0.5px solid #bbf7d0; }
.badge-delivered .bdot { background:#22c55e; }

.badge-completed { background:#f0fdf4; color:#166534; border:0.5px solid #bbf7d0; }
.badge-completed .bdot { background:#16a34a; }

.badge-cancelled { background:#fef2f2; color:#b91c1c; border:0.5px solid #fecaca; }
.badge-cancelled .bdot { background:#ef4444; }

.badge-failed    { background:#fef2f2; color:#991b1b; border:0.5px solid #fecaca; }
.badge-failed .bdot    { background:#dc2626; }

.badge-default   { background:var(--surf2); color:var(--t2); border:0.5px solid var(--border); }
.badge-default .bdot   { background:var(--t3); }

/* EMPTY STATE */
.empty-state { text-align:center; padding:64px 24px; }
.empty-icon { font-size:40px; color:var(--t3); margin-bottom:12px; display:block; }
.empty-state p { color:var(--t2); font-size:14px; }

/* MOBILE CARDS */
.mobile-cards { display:none; }
.booking-card { background:var(--white); border:0.5px solid var(--border); border-radius:var(--rlg); padding:16px; margin-bottom:10px; }
.bc-head { display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; flex-wrap:wrap; gap:8px; }
.bc-id { font-family:monospace; font-size:12px; font-weight:600; color:var(--dark); }
.bc-grid { display:grid; grid-template-columns:1fr 1fr; gap:8px 16px; }
.bc-field label { font-size:10px; font-weight:600; color:var(--t3); text-transform:uppercase; letter-spacing:0.06em; display:block; margin-bottom:2px; }
.bc-field span { font-size:13px; color:var(--t1); }
.bc-full { grid-column:1/-1; }

/* TABLE FOOTER */
.table-footer { display:flex; align-items:center; justify-content:space-between; padding:12px 20px; border-top:0.5px solid var(--border); flex-wrap:wrap; gap:8px; }
.footer-count { font-size:12px; color:var(--t3); }

/* RESPONSIVE */
@media(max-width:900px){ .stats { grid-template-columns:repeat(3,1fr); } }
@media(max-width:680px){
    .topbar { padding:0 16px; }
    .page { padding:20px 16px 40px; }
    .table-card .table-scroll { display:none; }
    .table-card .table-footer { display:none; }
    .mobile-cards { display:block; }
    .stats { grid-template-columns:repeat(2,1fr); }
    .toolbar { flex-direction:column; }
    .search-wrap, .filter-select { width:100%; }
}
@media(max-width:420px){ .stats { grid-template-columns:1fr 1fr; } }
@media(prefers-reduced-motion:reduce){ * { transition:none!important; animation:none!important; } }
</style>
</head>
<body>

<!-- TOPBAR -->
<header class="topbar">
    <div class="brand">
        <div class="brand-icon"><i class="ti ti-shirt"></i></div>
        <div class="brand-name">Grand <span>Superior</span></div>
    </div>
    <div class="topbar-sep"></div>
    <a href="rider_dashboard.php" class="back-link">
        <i class="ti ti-arrow-left"></i> Back to dashboard
    </a>
</header>

<main class="page">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div class="page-header-left">
            <h1 class="page-title">All Bookings</h1>
            <span class="record-pill"><?= $total ?> records</span>
        </div>
    </div>

    <!-- STATS -->
    <div class="stats">
        <div class="stat">
            <div class="stat-label">Total</div>
            <div class="stat-value"><?= $total ?></div>
        </div>
        <?php
        $statConf = [
            'pending'     => ['#f59e0b', 'Pending'],
            'confirmed'   => ['#3b82f6', 'Confirmed'],
            'in_progress' => ['#06b6d4', 'In Progress'],
            'delivered'   => ['#22c55e', 'Delivered'],
            'completed'   => ['#16a34a', 'Completed'],
            'cancelled'   => ['#ef4444', 'Cancelled'],
            'failed'      => ['#dc2626', 'Failed'],
        ];
        foreach ($statConf as $key => [$color, $label]) {
            $count = $statusCounts[$key] ?? 0;
            if ($count > 0): ?>
            <div class="stat">
                <div class="stat-label">
                    <span class="stat-dot" style="background:<?= $color ?>"></span>
                    <?= $label ?>
                </div>
                <div class="stat-value"><?= $count ?></div>
            </div>
        <?php endif; } ?>
    </div>

    <!-- TOOLBAR -->
    <div class="toolbar">
        <div class="search-wrap">
            <i class="ti ti-search"></i>
            <input type="text" class="search-input" id="searchInput" placeholder="Search client, address, service, notes…">
        </div>
        <select class="filter-select" id="statusFilter">
            <option value="">All statuses</option>
            <?php foreach (array_keys($statusCounts) as $s): ?>
            <option value="<?= htmlspecialchars($s) ?>"><?= ucwords(str_replace('_', ' ', $s)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- DESKTOP TABLE -->
    <div class="table-card">
        <div class="table-scroll">
            <table id="bookingsTable">
                <thead>
                    <tr>
                        <th>#ID</th>
                        <th>Client ID</th>
                        <th>Service</th>
                        <th>Pickup date</th>
                        <th>Delivery date</th>
                        <th>Address</th>
                        <th>Notes</th>
                        <th>Created</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (count($rows) > 0): ?>
                    <?php foreach ($rows as $row): ?>
                    <tr>
                        <td class="td-id">#<?= str_pad($row['id'], 4, '0', STR_PAD_LEFT) ?></td>
                        <td class="td-client"><?= htmlspecialchars($row['client_id']) ?></td>
                        <td><span class="td-service"><?= htmlspecialchars($row['service_type']) ?></span></td>
                        <td class="td-date"><?= htmlspecialchars($row['pickup_date']) ?></td>
                        <td class="td-date"><?= htmlspecialchars($row['delivery_date']) ?></td>
                        <td class="td-truncate" title="<?= htmlspecialchars($row['address']) ?>"><?= htmlspecialchars($row['address']) ?></td>
                        <td class="td-truncate" title="<?= htmlspecialchars($row['notes']) ?>"><?= $row['notes'] ? htmlspecialchars($row['notes']) : '—' ?></td>
                        <td class="td-date"><?= htmlspecialchars($row['created_at']) ?></td>
                        <td><?= getStatusBadge($row['status']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="9">
                        <div class="empty-state">
                            <i class="ti ti-calendar-off empty-icon"></i>
                            <p>No bookings found in the database.</p>
                        </div>
                    </td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="table-footer">
            <span class="footer-count" id="visibleCount">Showing <?= count($rows) ?> of <?= $total ?> records</span>
        </div>
    </div>

    <!-- MOBILE CARDS -->
    <div class="mobile-cards" id="mobileCards">
        <?php foreach ($rows as $row): ?>
        <div class="booking-card">
            <div class="bc-head">
                <span class="bc-id">#<?= str_pad($row['id'], 4, '0', STR_PAD_LEFT) ?> &nbsp;·&nbsp; Client <?= htmlspecialchars($row['client_id']) ?></span>
                <?= getStatusBadge($row['status']) ?>
            </div>
            <div class="bc-grid">
                <div class="bc-field"><label>Service</label><span><?= htmlspecialchars($row['service_type']) ?></span></div>
                <div class="bc-field"><label>Pickup</label><span><?= htmlspecialchars($row['pickup_date']) ?></span></div>
                <div class="bc-field"><label>Delivery</label><span><?= htmlspecialchars($row['delivery_date']) ?></span></div>
                <div class="bc-field"><label>Created</label><span><?= htmlspecialchars($row['created_at']) ?></span></div>
                <div class="bc-field bc-full"><label>Address</label><span><?= htmlspecialchars($row['address']) ?></span></div>
                <?php if ($row['notes']): ?>
                <div class="bc-field bc-full"><label>Notes</label><span><?= htmlspecialchars($row['notes']) ?></span></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

</main>

<script>
var searchInput  = document.getElementById('searchInput');
var statusFilter = document.getElementById('statusFilter');
var tableRows    = document.querySelectorAll('#bookingsTable tbody tr');
var visibleCount = document.getElementById('visibleCount');
var total        = <?= $total ?>;

function filterTable() {
    var q = searchInput.value.toLowerCase();
    var s = statusFilter.value.toLowerCase().replace('_', ' ');
    var shown = 0;
    tableRows.forEach(function(row) {
        var text   = row.textContent.toLowerCase();
        var badge  = row.querySelector('.badge');
        var status = badge ? badge.textContent.toLowerCase().trim() : '';
        var matchQ = !q || text.includes(q);
        var matchS = !s || status.includes(s);
        var visible = matchQ && matchS;
        row.style.display = visible ? '' : 'none';
        if (visible) shown++;
    });
    if (visibleCount) {
        visibleCount.textContent = 'Showing ' + shown + ' of ' + total + ' records';
    }
}

searchInput.addEventListener('input', filterTable);
statusFilter.addEventListener('change', filterTable);
</script>

</body>
</html>
