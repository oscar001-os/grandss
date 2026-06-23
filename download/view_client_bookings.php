<?php
session_start();
include("connection.php");
if (!isset($_SESSION['owner_id']) && !isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}
if (!isset($_GET['client_id']) || empty($_GET['client_id'])) {
    header("Location: view_clients.php");
    exit();
}
$client_id = intval($_GET['client_id']);
/* CLIENT */
$stmt = $conn->prepare("SELECT * FROM clients WHERE id = ?");
$stmt->bind_param("i", $client_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) { header("Location: view_clients.php"); exit(); }
$client = $result->fetch_assoc();
$stmt->close();
/* BOOKINGS */
$stmt = $conn->prepare("SELECT * FROM bookings WHERE client_id = ? ORDER BY id DESC");
$stmt->bind_param("i", $client_id);
$stmt->execute();
$bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$total_bookings = count($bookings);
/* STATUS COUNTS */
$counts = ['pending'=>0,'confirmed'=>0,'picked'=>0,'in_progress'=>0,'delivered'=>0,'done'=>0,'cancelled'=>0];
foreach ($bookings as $b) {
    $k = strtolower(str_replace(' ','_',trim($b['status'] ?? '')));
    if (isset($counts[$k])) $counts[$k]++;
}
$active_count   = $counts['pending'] + $counts['confirmed'] + $counts['picked'] + $counts['in_progress'];
$complete_count = $counts['delivered'] + $counts['done'];
/* INITIALS */
$parts    = explode(' ', trim($client['name']));
$initials = strtoupper(substr($parts[0],0,1) . (isset($parts[1]) ? substr($parts[1],0,1) : ''));
function statusBadge($status) {
    $s = strtolower(str_replace(' ','_',trim($status)));
    $map = [
        'pending'     => ['badge-pending',   'Pending'],
        'confirmed'   => ['badge-confirmed', 'Confirmed'],
        'picked'      => ['badge-picked',    'Picked Up'],
        'in_progress' => ['badge-progress',  'In Progress'],
        'delivered'   => ['badge-delivered', 'Delivered'],
        'done'        => ['badge-done',      'Done'],
        'completed'   => ['badge-done',      'Completed'],
        'cancelled'   => ['badge-cancelled', 'Cancelled'],
    ];
    [$cls,$lbl] = $map[$s] ?? ['badge-default', ucfirst($status)];
    return '<span class="badge '.$cls.'"><span class="bdot"></span>'.htmlspecialchars($lbl).'</span>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Client Bookings — Grand Superior</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
:root{
  --bg:#f4f7f2; --surface:#fff; --surface2:#f8fbf4;
  --border:#ddeec8; --border-soft:#eaf3da;
  --accent:#a6ce39; --accent-dark:#8ab530; --accent-dim:#f2f9e4;
  --primary:#0d1b2a; --text:#1a2a14; --muted:#6b7e5a; --text-dim:#4a5e38;
  --shadow-sm:0 1px 4px rgba(0,0,0,.07);
  --shadow:0 4px 18px rgba(0,0,0,.09);
  --shadow-lg:0 8px 36px rgba(0,0,0,.12);
}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);color:var(--text);min-height:100vh}
a{text-decoration:none;color:inherit}
/* NAVBAR */
.navbar{background:var(--primary);padding:0 28px;height:64px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;box-shadow:0 2px 14px rgba(0,0,0,.22)}
.navbar-logo{display:flex;align-items:center;gap:12px}
.navbar-logo img{width:38px;height:38px;border-radius:9px;object-fit:cover;border:2px solid var(--accent);padding:2px;background:#fff}
.navbar-logo span{font-size:16px;font-weight:700;color:#fff}
.nav-back{display:inline-flex;align-items:center;gap:7px;padding:8px 16px;border-radius:9px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);color:rgba(255,255,255,.75);font-size:13px;font-weight:500;transition:all .18s}
.nav-back:hover{background:rgba(255,255,255,.15);color:#fff}
.nav-back svg{width:14px;height:14px;fill:none;stroke:currentColor;stroke-width:2.5;stroke-linecap:round;stroke-linejoin:round}
/* PAGE */
.page{max-width:1160px;margin:0 auto;padding:32px 20px 72px}
/* BREADCRUMB */
.breadcrumb{display:flex;align-items:center;gap:6px;margin-bottom:24px;font-size:12.5px;color:var(--muted)}
.breadcrumb a{color:var(--accent-dark);font-weight:500;transition:color .15s}
.breadcrumb a:hover{color:var(--primary)}
.breadcrumb svg{width:12px;height:12px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
/* HERO */
.hero{background:linear-gradient(135deg,var(--primary) 0%,#1a3a50 100%);border-radius:20px;padding:26px 30px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:20px;margin-bottom:22px;position:relative;overflow:hidden;box-shadow:var(--shadow-lg)}
.hero::before{content:'';position:absolute;top:-50px;right:-50px;width:220px;height:220px;border-radius:50%;background:rgba(166,206,57,.07)}
.hero::after{content:'';position:absolute;bottom:-60px;right:160px;width:170px;height:170px;border-radius:50%;background:rgba(166,206,57,.05)}
.hero-left{display:flex;align-items:center;gap:18px;position:relative;z-index:1}
.hero-av{width:60px;height:60px;border-radius:16px;background:var(--accent);display:flex;align-items:center;justify-content:center;font-size:22px;font-weight:800;color:var(--primary);flex-shrink:0;box-shadow:0 4px 16px rgba(166,206,57,.35)}
.hero-info h2{font-size:20px;font-weight:800;color:#fff;letter-spacing:-.3px}
.hero-info p{font-size:13px;color:rgba(255,255,255,.5);margin-top:3px}
.hero-pills{display:flex;gap:7px;margin-top:9px;flex-wrap:wrap}
.pill{display:inline-flex;align-items:center;gap:5px;padding:4px 11px;border-radius:100px;font-size:11.5px;font-weight:600}
.pill-id{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);color:rgba(255,255,255,.65);font-family:'Space Mono',monospace;font-size:11px}
.pill-email{background:rgba(166,206,57,.12);border:1px solid rgba(166,206,57,.25);color:rgba(255,255,255,.7)}
.hero-stats{display:flex;gap:12px;flex-wrap:wrap;position:relative;z-index:1}
.hs{background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.1);border-radius:13px;padding:13px 18px;text-align:center;min-width:90px}
.hs-num{font-family:'Space Mono',monospace;font-size:24px;font-weight:700;color:var(--accent);line-height:1}
.hs-lbl{font-size:10.5px;color:rgba(255,255,255,.4);margin-top:4px;font-weight:500}
/* MINI STAT STRIP */
.stat-strip{display:grid;grid-template-columns:repeat(4,1fr);gap:13px;margin-bottom:22px}
.ss-card{background:var(--surface);border:1.5px solid var(--border);border-radius:14px;padding:16px 18px;display:flex;align-items:center;gap:13px;box-shadow:var(--shadow-sm);transition:box-shadow .2s,transform .2s}
.ss-card:hover{box-shadow:var(--shadow);transform:translateY(-2px)}
.ss-icon{width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.ss-icon svg{width:17px;height:17px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.icon-blue{background:#eff6ff;color:#3b82f6}
.icon-amber{background:#fffbeb;color:#d97706}
.icon-green{background:#f0fdf4;color:#16a34a}
.icon-red{background:#fef2f2;color:#dc2626}
.ss-num{font-family:'Space Mono',monospace;font-size:22px;font-weight:700;color:var(--text);line-height:1}
.ss-lbl{font-size:11.5px;color:var(--muted);margin-top:3px;font-weight:500}
/* SEARCH / FILTER BAR */
.filter-bar{background:var(--surface);border:1.5px solid var(--border);border-radius:14px;padding:14px 18px;display:flex;align-items:center;gap:12px;margin-bottom:18px;flex-wrap:wrap;box-shadow:var(--shadow-sm)}
.filter-bar input{flex:1;min-width:200px;padding:9px 14px;border:1.5px solid var(--border);border-radius:9px;font-family:inherit;font-size:13.5px;color:var(--text);background:var(--surface2);outline:none;transition:border-color .2s}
.filter-bar input:focus{border-color:var(--accent)}
.filter-bar select{padding:9px 13px;border:1.5px solid var(--border);border-radius:9px;font-family:inherit;font-size:13px;color:var(--text);background:var(--surface2);outline:none;cursor:pointer;transition:border-color .2s}
.filter-bar select:focus{border-color:var(--accent)}
.filter-label{font-size:12px;font-weight:700;color:var(--muted);white-space:nowrap}
/* TABLE CARD */
.table-card{background:var(--surface);border:1.5px solid var(--border);border-radius:18px;overflow:hidden;box-shadow:var(--shadow-sm)}
.table-head{display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid var(--border-soft);background:var(--surface2);flex-wrap:wrap;gap:10px}
.table-head h3{font-size:14.5px;font-weight:700;color:var(--text);display:flex;align-items:center;gap:8px}
.table-head h3 svg{width:16px;height:16px;fill:none;stroke:var(--accent-dark);stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.count-pill{font-size:12px;font-weight:600;background:var(--accent-dim);border:1px solid var(--border);padding:4px 12px;border-radius:100px;color:var(--accent-dark)}
.table-scroll{overflow-x:auto}
table{width:100%;border-collapse:collapse;font-size:13.5px}
thead th{padding:11px 16px;text-align:left;font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--muted);background:var(--surface2);border-bottom:1px solid var(--border-soft);white-space:nowrap}
thead th:first-child{padding-left:20px}
tbody tr{border-bottom:1px solid var(--border-soft);transition:background .15s}
tbody tr:last-child{border-bottom:none}
tbody tr:hover{background:var(--surface2)}
td{padding:13px 16px;color:var(--text-dim);vertical-align:middle;white-space:nowrap}
td:first-child{padding-left:20px}
.td-id{font-family:'Space Mono',monospace;font-size:11.5px;color:var(--accent-dark);font-weight:700}
.td-service{font-weight:600;color:var(--text)}
.td-date{font-size:12.5px;color:var(--muted)}
.td-addr{max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:12.5px}
/* BADGES */
.badge{display:inline-flex;align-items:center;gap:5px;padding:4px 11px;border-radius:100px;font-size:11px;font-weight:600;white-space:nowrap}
.bdot{width:5px;height:5px;border-radius:50%;flex-shrink:0}
.badge-pending  {background:#fffbeb;color:#b45309;border:1px solid #fde68a}
.badge-pending .bdot  {background:#f59e0b}
.badge-confirmed{background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe}
.badge-confirmed .bdot{background:#3b82f6}
.badge-picked   {background:#f5f3ff;color:#6d28d9;border:1px solid #ddd6fe}
.badge-picked .bdot   {background:#8b5cf6;animation:pulse 1.5s infinite}
.badge-progress {background:#ecfeff;color:#0e7490;border:1px solid #a5f3fc}
.badge-progress .bdot {background:#06b6d4;animation:pulse 1.5s infinite}
.badge-delivered{background:#f0fdf4;color:#15803d;border:1px solid #bbf7d0}
.badge-delivered .bdot{background:#22c55e}
.badge-done     {background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0}
.badge-done .bdot     {background:#10b981}
.badge-cancelled{background:#fef2f2;color:#b91c1c;border:1px solid #fecaca}
.badge-cancelled .bdot{background:#ef4444}
.badge-default  {background:var(--surface2);color:var(--muted);border:1px solid var(--border)}
.badge-default .bdot  {background:var(--muted)}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.4}}
/* EMPTY */
.empty-state{padding:60px 20px;text-align:center}
.empty-state svg{width:44px;height:44px;fill:none;stroke:#d1d5db;stroke-width:1.5;stroke-linecap:round;stroke-linejoin:round;display:block;margin:0 auto 12px}
.empty-state p{color:var(--muted);font-size:14px}
/* MOBILE CARDS */
.mobile-cards{display:none;flex-direction:column;gap:12px;padding:16px}
.m-card{background:var(--surface2);border:1px solid var(--border-soft);border-radius:14px;padding:16px}
.m-card-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px}
.m-card-id{font-family:'Space Mono',monospace;font-size:12px;font-weight:700;color:var(--accent-dark)}
.m-field{display:flex;flex-direction:column;gap:2px;margin-bottom:8px}
.m-field:last-child{margin-bottom:0}
.m-label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--muted)}
.m-val{font-size:13px;color:var(--text);font-weight:500}
/* ACTIONS */
.actions{display:flex;gap:12px;flex-wrap:wrap;margin-top:22px}
.btn{display:inline-flex;align-items:center;gap:8px;padding:11px 22px;border-radius:10px;font-size:13.5px;font-weight:600;font-family:inherit;transition:all .18s}
.btn svg{width:15px;height:15px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.btn-primary{background:var(--primary);color:#fff}
.btn-primary:hover{background:#1a3a50;transform:translateY(-1px)}
.btn-accent{background:var(--accent);color:var(--primary)}
.btn-accent:hover{background:var(--accent-dark);transform:translateY(-1px)}
@media(max-width:1000px){.stat-strip{grid-template-columns:repeat(2,1fr)}}
@media(max-width:700px){
  .navbar{padding:0 16px}
  .page{padding:20px 14px 52px}
  .hero{padding:20px}
  .hero-av{width:50px;height:50px;font-size:18px}
  .hero-info h2{font-size:17px}
  .hero-stats{display:none}
  .stat-strip{grid-template-columns:1fr 1fr}
  .table-scroll table{display:none}
  .mobile-cards{display:flex}
  .filter-bar input{min-width:140px}
}
@media(max-width:420px){.stat-strip{grid-template-columns:1fr}}
</style>
</head>
<body>
<!-- NAVBAR -->
<nav class="navbar">
  <div class="navbar-logo">
    <img src="logo.jpg" alt="Grand Superior">
    <span>Grand Superior Drycleaners</span>
  </div>
  <a href="view_clients.php" class="nav-back">
    <svg viewBox="0 0 24 24"><path d="M19 12H5M11 18l-6-6 6-6"/></svg>
    Back to Clients
  </a>
</nav>
<div class="page">
  <!-- BREADCRUMB -->
  <div class="breadcrumb">
    <a href="owner_dashboard.php">Dashboard</a>
    <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
    <a href="view_clients.php">Clients</a>
    <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
    <a href="client_details.php?id=<?= $client_id ?>"><?= htmlspecialchars($client['name']) ?></a>
    <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
    <span>Bookings</span>
  </div>
  <!-- HERO -->
  <div class="hero">
    <div class="hero-left">
      <div class="hero-av"><?= $initials ?></div>
      <div class="hero-info">
        <h2><?= htmlspecialchars($client['name']) ?></h2>
        <p>Booking history &amp; order tracking</p>
        <div class="hero-pills">
          <span class="pill pill-id">#CLT-<?= str_pad($client_id,4,'0',STR_PAD_LEFT) ?></span>
          <span class="pill pill-email">
            <svg style="width:10px;height:10px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
            <?= htmlspecialchars($client['email']) ?>
          </span>
        </div>
      </div>
    </div>
    <div class="hero-stats">
      <div class="hs">
        <div class="hs-num"><?= $total_bookings ?></div>
        <div class="hs-lbl">Total</div>
      </div>
      <div class="hs">
        <div class="hs-num"><?= $active_count ?></div>
        <div class="hs-lbl">Active</div>
      </div>
      <div class="hs">
        <div class="hs-num"><?= $complete_count ?></div>
        <div class="hs-lbl">Completed</div>
      </div>
      <div class="hs">
        <div class="hs-num"><?= $counts['cancelled'] ?></div>
        <div class="hs-lbl">Cancelled</div>
      </div>
    </div>
  </div>
  <!-- STAT STRIP -->
  <div class="stat-strip">
    <div class="ss-card">
      <div class="ss-icon icon-blue"><svg viewBox="0 0 24 24"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1"/></svg></div>
      <div><div class="ss-num"><?= $total_bookings ?></div><div class="ss-lbl">All Bookings</div></div>
    </div>
    <div class="ss-card">
      <div class="ss-icon icon-amber"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
      <div><div class="ss-num"><?= $active_count ?></div><div class="ss-lbl">Active Orders</div></div>
    </div>
    <div class="ss-card">
      <div class="ss-icon icon-green"><svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div>
      <div><div class="ss-num"><?= $complete_count ?></div><div class="ss-lbl">Completed</div></div>
    </div>
    <div class="ss-card">
      <div class="ss-icon icon-red"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg></div>
      <div><div class="ss-num"><?= $counts['cancelled'] ?></div><div class="ss-lbl">Cancelled</div></div>
    </div>
  </div>
  <!-- FILTER BAR -->
  <div class="filter-bar">
    <span class="filter-label">Filter:</span>
    <input type="text" id="searchInput" placeholder="Search service, address, ID..." oninput="filterTable()">
    <select id="statusFilter" onchange="filterTable()">
      <option value="">All statuses</option>
      <option value="pending">Pending</option>
      <option value="confirmed">Confirmed</option>
      <option value="picked">Picked Up</option>
      <option value="in_progress">In Progress</option>
      <option value="delivered">Delivered</option>
      <option value="done">Done</option>
      <option value="cancelled">Cancelled</option>
    </select>
  </div>
  <!-- TABLE CARD -->
  <div class="table-card">
    <div class="table-head">
      <h3>
        <svg viewBox="0 0 24 24"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1"/></svg>
        Booking History
      </h3>
      <span class="count-pill" id="visibleCount"><?= $total_bookings ?> bookings</span>
    </div>
    <?php if ($total_bookings > 0): ?>
    <!-- DESKTOP TABLE -->
    <div class="table-scroll">
      <table id="bookingsTable">
        <thead>
          <tr>
            <th>#ID</th>
            <th>Service Type</th>
            <th>Pickup Date</th>
            <th>Delivery Date</th>
            <th>Address</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($bookings as $b): ?>
        <tr data-status="<?= strtolower(str_replace(' ','_',trim($b['status'] ?? ''))) ?>">
          <td class="td-id">#<?= str_pad($b['id'],3,'0',STR_PAD_LEFT) ?></td>
          <td class="td-service"><?= htmlspecialchars($b['service_type']) ?></td>
          <td class="td-date"><?= $b['pickup_date']   ? date('M j, Y', strtotime($b['pickup_date']))   : '—' ?></td>
          <td class="td-date"><?= $b['delivery_date'] ? date('M j, Y', strtotime($b['delivery_date'])) : '—' ?></td>
          <td class="td-addr" title="<?= htmlspecialchars($b['address'] ?? '') ?>"><?= htmlspecialchars($b['address'] ?? '—') ?></td>
          <td><?= statusBadge($b['status'] ?: 'Pending') ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <!-- MOBILE CARDS -->
    <div class="mobile-cards" id="mobileCards">
    <?php foreach ($bookings as $b): ?>
      <div class="m-card" data-status="<?= strtolower(str_replace(' ','_',trim($b['status'] ?? ''))) ?>">
        <div class="m-card-head">
          <span class="m-card-id">#<?= str_pad($b['id'],3,'0',STR_PAD_LEFT) ?></span>
          <?= statusBadge($b['status'] ?: 'Pending') ?>
        </div>
        <div class="m-field"><div class="m-label">Service</div><div class="m-val"><?= htmlspecialchars($b['service_type']) ?></div></div>
        <div class="m-field"><div class="m-label">Pickup</div><div class="m-val"><?= $b['pickup_date']   ? date('M j, Y', strtotime($b['pickup_date']))   : '—' ?></div></div>
        <div class="m-field"><div class="m-label">Delivery</div><div class="m-val"><?= $b['delivery_date'] ? date('M j, Y', strtotime($b['delivery_date'])) : '—' ?></div></div>
        <div class="m-field"><div class="m-label">Address</div><div class="m-val"><?= htmlspecialchars($b['address'] ?? '—') ?></div></div>
        <?php if (!empty($b['notes'])): ?>
        <div class="m-field"><div class="m-label">Notes</div><div class="m-val"><?= htmlspecialchars($b['notes']) ?></div></div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="empty-state">
      <svg viewBox="0 0 24 24"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1"/></svg>
      <p>No bookings found for this client.</p>
    </div>
    <?php endif; ?>
  </div>
  <!-- ACTIONS -->
  <div class="actions">
    <a href="view_clients.php" class="btn btn-primary">
      <svg viewBox="0 0 24 24"><path d="M19 12H5M11 18l-6-6 6-6"/></svg>
      Back to Clients
    </a>
    <a href="client_details.php?id=<?= $client_id ?>" class="btn btn-accent">
      <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
      Client Details
    </a>
  </div>
</div>
<script>
function filterTable() {
  const search = document.getElementById('searchInput').value.toLowerCase();
  const status = document.getElementById('statusFilter').value;
  const rows    = document.querySelectorAll('#bookingsTable tbody tr');
  const mcards  = document.querySelectorAll('#mobileCards .m-card');
  let visible   = 0;
  rows.forEach((row, i) => {
    const text   = row.textContent.toLowerCase();
    const rowSt  = row.getAttribute('data-status');
    const match  = (!search || text.includes(search)) && (!status || rowSt === status || rowSt.includes(status));
    row.style.display = match ? '' : 'none';
    if (match) visible++;
  });
  mcards.forEach(card => {
    const text  = card.textContent.toLowerCase();
    const cSt   = card.getAttribute('data-status');
    const match = (!search || text.includes(search)) && (!status || cSt === status || cSt.includes(status));
    card.style.display = match ? '' : 'none';
  });
  document.getElementById('visibleCount').textContent =
    visible + ' booking' + (visible !== 1 ? 's' : '');
}
</script>
</body>
</html>