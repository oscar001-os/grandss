<?php
session_start();
include("connection.php");
if (!isset($_SESSION['rider_id'])) {
    header("Location: rider_login.php");
    exit();
}
$rider_id = $_SESSION['rider_id'];
/* UPDATE BOOKING STATUS */
if (isset($_POST['update_status'])) {
    $booking_id = intval($_POST['booking_id']);
    $status     = trim($_POST['status']);
    $stmt = $conn->prepare("UPDATE bookings SET status=? WHERE id=?");
    $stmt->bind_param("si", $status, $booking_id);
    $stmt->execute();
    header("Location: rider_dashboard.php");
    exit();
}
/* MARK NOTIFICATION AS READ */
if (isset($_POST['mark_read'])) {
    $notif_id = intval($_POST['notif_id']);
    $stmt = $conn->prepare("UPDATE rider_notifications SET is_read=1 WHERE id=? AND rider_id=?");
    $stmt->bind_param("ii", $notif_id, $rider_id);
    $stmt->execute();
    header("Location: rider_dashboard.php#notifications");
    exit();
}
/* MARK ALL AS READ */
if (isset($_POST['mark_all_read'])) {
    $stmt = $conn->prepare("UPDATE rider_notifications SET is_read=1 WHERE rider_id=?");
    $stmt->bind_param("i", $rider_id);
    $stmt->execute();
    header("Location: rider_dashboard.php#notifications");
    exit();
}
/* RIDER DETAILS */
$stmt = $conn->prepare("SELECT * FROM riders WHERE id=?");
$stmt->bind_param("i", $rider_id);
$stmt->execute();
$rider = $stmt->get_result()->fetch_assoc();
/* BOOKINGS */
$bookings_result = mysqli_query($conn, "SELECT * FROM bookings ORDER BY created_at DESC");
$bookings = mysqli_fetch_all($bookings_result, MYSQLI_ASSOC);
$total    = count($bookings);
$statusCounts = ['pending'=>0,'picked'=>0,'in_progress'=>0,'delivered'=>0,'done'=>0,'cancelled'=>0];
foreach ($bookings as $b) {
    $k = strtolower(str_replace(' ','_',trim($b['status'])));
    if (isset($statusCounts[$k])) $statusCounts[$k]++;
}
$delivered_count = $statusCounts['delivered'] + $statusCounts['done'];
$pending_count   = $statusCounts['pending'];
/* RIDER NOTIFICATIONS */
$notifs_result = $conn->prepare("
    SELECT * FROM rider_notifications
    WHERE rider_id = ?
    ORDER BY sent_at DESC
    LIMIT 20
");
$notifs_result->bind_param("i", $rider_id);
$notifs_result->execute();
$notifs      = $notifs_result->get_result()->fetch_all(MYSQLI_ASSOC);
$unread_count = 0;
foreach ($notifs as $n) { if (!$n['is_read']) $unread_count++; }
/* INITIALS */
$parts    = explode(' ', trim($rider['name']));
$initials = strtoupper(substr($parts[0],0,1) . (isset($parts[1]) ? substr($parts[1],0,1) : ''));
function getStatusBadge($status) {
    $s = strtolower(str_replace(' ','_',trim($status)));
    $map = [
        'pending'     => ['pending',   'Pending'],
        'confirmed'   => ['confirmed', 'Confirmed'],
        'picked'      => ['picked',    'Picked Up'],
        'in_progress' => ['progress',  'In Progress'],
        'delivered'   => ['delivered', 'Delivered'],
        'done'        => ['done',      'Done'],
        'completed'   => ['done',      'Completed'],
        'cancelled'   => ['cancelled', 'Cancelled'],
        'canceled'    => ['cancelled', 'Cancelled'],
        'failed'      => ['failed',    'Failed'],
    ];
    $info = $map[$s] ?? ['default', ucfirst($status)];
    return '<span class="badge badge-'.$info[0].'"><span class="bdot"></span>'.htmlspecialchars($info[1]).'</span>';
}
$hour     = (int)date('H');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
$dateStr  = date('l, j F Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Rider Dashboard — Grand Superior</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
<style>
*, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
:root {
    --dark:#0d1b2a; --accent:#a6ce39; --bg:#f4f5f7; --white:#ffffff;
    --surf2:#f8f9fb; --border:rgba(0,0,0,0.08); --bh:rgba(0,0,0,0.14);
    --t1:#111827; --t2:#6b7280; --t3:#9ca3af;
    --rmd:8px; --rlg:12px; --rxl:16px; --sidebar-w:240px;
}
body { font-family:'Inter',sans-serif; background:var(--bg); color:var(--t1); display:flex; min-height:100vh; font-size:14px; line-height:1.6; -webkit-font-smoothing:antialiased; }
a { text-decoration:none; color:inherit; }
/* SIDEBAR */
.sidebar { width:var(--sidebar-w); background:var(--dark); position:fixed; top:0; left:0; height:100vh; display:flex; flex-direction:column; z-index:100; transition:transform .25s; }
.sidebar-logo { padding:20px 18px; border-bottom:1px solid rgba(255,255,255,0.07); display:flex; align-items:center; gap:10px; }
.logo-icon { width:36px; height:36px; border-radius:var(--rmd); background:rgba(166,206,57,0.15); display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.logo-icon i { font-size:18px; color:var(--accent); }
.logo-name { font-size:15px; font-weight:600; color:#fff; }
.logo-name span { color:var(--accent); }
.sidebar-nav { flex:1; padding:14px 10px; display:flex; flex-direction:column; gap:2px; overflow-y:auto; }
.nav-section-label { font-size:10px; font-weight:600; color:rgba(255,255,255,0.25); text-transform:uppercase; letter-spacing:0.08em; padding:10px 10px 4px; }
.nav-link { display:flex; align-items:center; gap:9px; padding:9px 12px; border-radius:var(--rmd); font-size:13px; font-weight:500; color:rgba(255,255,255,0.55); transition:background .15s, color .15s; }
.nav-link:hover { background:rgba(255,255,255,0.07); color:#fff; }
.nav-link.active { background:rgba(166,206,57,0.12); color:var(--accent); }
.nav-link i { font-size:16px; }
.nav-badge { margin-left:auto; background:#ef4444; color:#fff; font-size:10px; font-weight:700; padding:1px 7px; border-radius:20px; line-height:1.6; }
.sidebar-footer { padding:14px 10px; border-top:1px solid rgba(255,255,255,0.07); }
.rider-row { display:flex; align-items:center; gap:10px; }
.rider-av { width:36px; height:36px; border-radius:var(--rmd); background:rgba(166,206,57,0.2); display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:600; color:var(--accent); flex-shrink:0; }
.rider-info { flex:1; min-width:0; }
.rider-name { font-size:13px; font-weight:500; color:#fff; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.rider-role { font-size:11px; color:rgba(255,255,255,0.35); }
.logout-btn { width:32px; height:32px; border-radius:var(--rmd); background:rgba(239,68,68,0.1); border:0.5px solid rgba(239,68,68,0.2); color:#f87171; display:flex; align-items:center; justify-content:center; flex-shrink:0; transition:background .15s; }
.logout-btn:hover { background:rgba(239,68,68,0.2); }
.logout-btn i { font-size:15px; }
/* MAIN */
.main { flex:1; margin-left:var(--sidebar-w); display:flex; flex-direction:column; min-height:100vh; }
.topbar { background:var(--white); border-bottom:0.5px solid var(--border); padding:0 28px; height:64px; display:flex; align-items:center; justify-content:space-between; gap:12px; position:sticky; top:0; z-index:50; }
.topbar-left h2 { font-size:16px; font-weight:600; color:var(--t1); }
.topbar-left p  { font-size:12px; color:var(--t3); margin-top:1px; }
.online-pill { display:inline-flex; align-items:center; gap:6px; background:#f0fdf4; border:0.5px solid #bbf7d0; padding:6px 12px; border-radius:20px; font-size:12px; font-weight:500; color:#15803d; }
.online-dot { width:7px; height:7px; border-radius:50%; background:#22c55e; }
.topbar-notif { position:relative; display:flex; align-items:center; justify-content:center; width:38px; height:38px; border-radius:var(--rmd); background:var(--surf2); border:0.5px solid var(--border); cursor:pointer; color:var(--t2); text-decoration:none; transition:background .15s; }
.topbar-notif:hover { background:#f0f0f0; }
.topbar-notif i { font-size:18px; }
.topbar-notif .n-badge { position:absolute; top:5px; right:5px; width:8px; height:8px; border-radius:50%; background:#ef4444; border:2px solid var(--white); }
.hamburger { display:none; align-items:center; justify-content:center; width:36px; height:36px; border-radius:var(--rmd); background:var(--surf2); border:0.5px solid var(--border); cursor:pointer; }
.hamburger i { font-size:18px; color:var(--t1); }
.page { padding:24px 28px 48px; display:flex; flex-direction:column; gap:24px; max-width:1200px; }
/* STATS */
.stats { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; }
.stat { background:var(--white); border:0.5px solid var(--border); border-radius:var(--rlg); padding:18px 20px; transition:border-color .15s, transform .15s; }
.stat:hover { border-color:var(--bh); transform:translateY(-1px); }
.stat-icon { width:38px; height:38px; border-radius:var(--rmd); display:flex; align-items:center; justify-content:center; font-size:18px; margin-bottom:12px; }
.icon-orange { background:#fff7ed; color:#ea580c; }
.icon-green  { background:#f0fdf4; color:#16a34a; }
.icon-amber  { background:#fffbeb; color:#d97706; }
.icon-blue   { background:#eff6ff; color:#2563eb; }
.icon-red    { background:#fef2f2; color:#dc2626; }
.stat-value  { font-size:24px; font-weight:600; color:var(--t1); letter-spacing:-0.02em; }
.stat-label  { font-size:12px; color:var(--t2); margin-top:3px; }
.stat-sub    { font-size:11px; color:#16a34a; font-weight:500; margin-top:5px; }
.stat-sub-warn { color:#d97706; }
.stat-sub-red  { color:#dc2626; }
/* PROFILE CARD */
.profile-card { background:var(--white); border:0.5px solid var(--border); border-radius:var(--rxl); padding:24px; }
.profile-head { display:flex; align-items:center; gap:16px; margin-bottom:20px; padding-bottom:20px; border-bottom:0.5px solid var(--border); flex-wrap:wrap; }
.profile-av { width:60px; height:60px; border-radius:var(--rxl); background:#0d1b2a; display:flex; align-items:center; justify-content:center; font-size:20px; font-weight:600; color:var(--accent); flex-shrink:0; }
.profile-meta h3 { font-size:17px; font-weight:600; color:var(--t1); }
.profile-meta p  { font-size:12px; color:var(--t3); margin-top:2px; }
.profile-pills { display:flex; gap:7px; margin-top:8px; flex-wrap:wrap; }
.pill { font-size:11px; font-weight:500; padding:3px 10px; border-radius:20px; }
.pill-active  { background:#f0fdf4; color:#15803d; border:0.5px solid #bbf7d0; }
.pill-vehicle { background:#fff7ed; color:#c2410c; border:0.5px solid #fed7aa; }
.profile-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(160px,1fr)); gap:10px; }
.pf { background:var(--surf2); border:0.5px solid var(--border); border-radius:var(--rmd); padding:12px 14px; }
.pf-label { font-size:10px; font-weight:600; color:var(--t3); text-transform:uppercase; letter-spacing:0.06em; margin-bottom:3px; }
.pf-val   { font-size:13px; color:var(--t1); font-weight:500; }
/* SECTION HEADER */
.section-header { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:8px; }
.section-title { font-size:15px; font-weight:600; color:var(--t1); display:flex; align-items:center; gap:8px; }
.section-title i { font-size:18px; color:var(--t2); }
.section-pill { background:var(--surf2); border:0.5px solid var(--border); border-radius:20px; padding:4px 12px; font-size:12px; font-weight:500; color:var(--t2); }
.section-pill-red { background:#fef2f2; border-color:#fecaca; color:#b91c1c; }
/* ── OWNER NOTIFICATIONS ── */
.notif-section { background:var(--white); border:0.5px solid var(--border); border-radius:var(--rxl); overflow:hidden; }
.notif-section-head {
    display:flex; align-items:center; justify-content:space-between;
    padding:16px 20px; border-bottom:0.5px solid var(--border);
    background:linear-gradient(135deg,#0d1b2a 0%,#1a3a50 100%);
    flex-wrap:wrap; gap:10px;
}
.notif-section-head-left { display:flex; align-items:center; gap:12px; }
.notif-head-icon { width:38px; height:38px; border-radius:var(--rmd); background:rgba(166,206,57,0.15); border:0.5px solid rgba(166,206,57,0.3); display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.notif-head-icon i { font-size:18px; color:#a6ce39; }
.notif-head-title { font-size:14px; font-weight:600; color:#fff; }
.notif-head-sub   { font-size:11.5px; color:rgba(255,255,255,0.45); margin-top:1px; }
.notif-unread-pill { display:inline-flex; align-items:center; gap:5px; background:rgba(239,68,68,0.15); border:0.5px solid rgba(239,68,68,0.35); padding:5px 12px; border-radius:20px; font-size:12px; font-weight:600; color:#fca5a5; }
.notif-unread-dot { width:7px; height:7px; border-radius:50%; background:#ef4444; animation:blink 1.4s ease-in-out infinite; }
@keyframes blink { 0%,100%{opacity:1} 50%{opacity:.3} }
.notif-toolbar { display:flex; align-items:center; justify-content:space-between; padding:12px 20px; border-bottom:0.5px solid var(--border); background:var(--surf2); flex-wrap:wrap; gap:8px; }
.notif-count-label { font-size:12px; color:var(--t2); }
.mark-all-btn { display:inline-flex; align-items:center; gap:6px; padding:7px 14px; border-radius:var(--rmd); background:#fff; border:0.5px solid var(--border); font-family:'Inter',sans-serif; font-size:12px; font-weight:500; color:var(--t2); cursor:pointer; transition:background .15s, color .15s; }
.mark-all-btn:hover { background:var(--accent); color:#0d1b2a; border-color:var(--accent); }
.mark-all-btn i { font-size:14px; }
.notif-list { display:flex; flex-direction:column; }
.notif-item { display:flex; align-items:flex-start; gap:14px; padding:16px 20px; border-bottom:0.5px solid var(--border); transition:background .15s; position:relative; }
.notif-item:last-child { border-bottom:none; }
.notif-item.unread { background:#fffef0; border-left:3px solid #a6ce39; }
.notif-item.read   { background:var(--white); border-left:3px solid transparent; }
.notif-item:hover  { background:#f9fafb; }
.notif-icon-wrap { width:40px; height:40px; border-radius:var(--rlg); display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.notif-item.unread .notif-icon-wrap { background:#f2f9e4; }
.notif-item.read   .notif-icon-wrap { background:var(--surf2); }
.notif-item.unread .notif-icon-wrap i { color:#8ab530; font-size:18px; }
.notif-item.read   .notif-icon-wrap i { color:var(--t3); font-size:18px; }
.notif-body { flex:1; min-width:0; }
.notif-booking-ref { font-family:monospace; font-size:11.5px; font-weight:700; color:#0d1b2a; background:#f0fdf4; border:0.5px solid #bbf7d0; padding:2px 8px; border-radius:6px; display:inline-block; margin-bottom:5px; }
.notif-message { font-size:13.5px; color:var(--t1); line-height:1.5; font-weight:500; }
.notif-item.read .notif-message { font-weight:400; color:var(--t2); }
.notif-meta { display:flex; align-items:center; gap:10px; margin-top:6px; flex-wrap:wrap; }
.notif-time  { font-size:11px; color:var(--t3); display:flex; align-items:center; gap:4px; }
.notif-time i { font-size:12px; }
.unread-label { font-size:10px; font-weight:700; background:#fef9c3; color:#854d0e; border:0.5px solid #fde68a; padding:2px 7px; border-radius:10px; text-transform:uppercase; letter-spacing:0.05em; }
.notif-action { flex-shrink:0; }
.read-btn { display:inline-flex; align-items:center; gap:5px; padding:6px 12px; border-radius:var(--rmd); background:var(--surf2); border:0.5px solid var(--border); font-family:'Inter',sans-serif; font-size:11.5px; font-weight:500; color:var(--t2); cursor:pointer; transition:all .15s; }
.read-btn:hover { background:#a6ce39; color:#0d1b2a; border-color:#a6ce39; }
.read-btn i { font-size:13px; }
.already-read { font-size:11.5px; color:var(--t3); display:flex; align-items:center; gap:4px; }
.already-read i { font-size:13px; color:#22c55e; }
.notif-empty { padding:50px 20px; text-align:center; }
.notif-empty i { font-size:38px; color:var(--t3); display:block; margin-bottom:10px; }
.notif-empty p { color:var(--t2); font-size:14px; }
/* ORDER CARDS */
.orders { display:flex; flex-direction:column; gap:12px; margin-top:14px; }
.order-card { background:var(--white); border:0.5px solid var(--border); border-radius:var(--rlg); overflow:hidden; transition:border-color .15s, box-shadow .15s; }
.order-card:hover { border-color:var(--bh); box-shadow:0 2px 12px rgba(0,0,0,0.06); }
.order-head { display:flex; align-items:center; justify-content:space-between; padding:14px 18px; border-bottom:0.5px solid var(--border); flex-wrap:wrap; gap:8px; }
.order-id   { font-family:monospace; font-size:12px; font-weight:600; color:var(--dark); }
.order-meta { font-size:12px; color:var(--t3); margin-top:2px; }
.order-body { padding:14px 18px; display:grid; grid-template-columns:repeat(auto-fill,minmax(150px,1fr)); gap:12px; }
.of { display:flex; flex-direction:column; gap:2px; }
.of-label { font-size:10px; font-weight:600; color:var(--t3); text-transform:uppercase; letter-spacing:0.06em; }
.of-val   { font-size:13px; color:var(--t1); }
.order-foot { background:var(--surf2); border-top:0.5px solid var(--border); padding:12px 18px; display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
.status-select { background:var(--white); border:0.5px solid var(--border); border-radius:var(--rmd); padding:8px 13px; font-family:'Inter',sans-serif; font-size:13px; color:var(--t1); outline:none; cursor:pointer; transition:border-color .15s; min-width:170px; }
.status-select:focus { border-color:var(--accent); }
.update-btn { display:inline-flex; align-items:center; gap:6px; background:var(--dark); color:#fff; border:none; padding:8px 16px; border-radius:var(--rmd); font-family:'Inter',sans-serif; font-size:13px; font-weight:500; cursor:pointer; transition:opacity .15s; }
.update-btn:hover { opacity:0.85; }
.update-btn i { font-size:14px; }
/* BADGES */
.badge { display:inline-flex; align-items:center; gap:5px; padding:4px 10px; border-radius:20px; font-size:11.5px; font-weight:600; white-space:nowrap; }
.bdot  { width:6px; height:6px; border-radius:50%; flex-shrink:0; }
.badge-pending   { background:#fffbeb; color:#b45309;  border:0.5px solid #fde68a; }
.badge-pending .bdot   { background:#f59e0b; }
.badge-confirmed { background:#eff6ff; color:#1d4ed8;  border:0.5px solid #bfdbfe; }
.badge-confirmed .bdot { background:#3b82f6; }
.badge-picked    { background:#f5f3ff; color:#6d28d9;  border:0.5px solid #ddd6fe; }
.badge-picked .bdot    { background:#8b5cf6; }
.badge-progress  { background:#ecfeff; color:#0e7490;  border:0.5px solid #a5f3fc; }
.badge-progress .bdot  { background:#06b6d4; }
.badge-delivered { background:#f0fdf4; color:#15803d;  border:0.5px solid #bbf7d0; }
.badge-delivered .bdot { background:#22c55e; }
.badge-done      { background:#f0fdf4; color:#166534;  border:0.5px solid #bbf7d0; }
.badge-done .bdot      { background:#16a34a; }
.badge-cancelled { background:#fef2f2; color:#b91c1c;  border:0.5px solid #fecaca; }
.badge-cancelled .bdot { background:#ef4444; }
.badge-failed    { background:#fef2f2; color:#991b1b;  border:0.5px solid #fecaca; }
.badge-failed .bdot    { background:#dc2626; }
.badge-default   { background:var(--surf2); color:var(--t2); border:0.5px solid var(--border); }
.badge-default .bdot   { background:var(--t3); }
.empty { background:var(--white); border:0.5px solid var(--border); border-radius:var(--rlg); padding:60px 24px; text-align:center; }
.empty i { font-size:36px; color:var(--t3); display:block; margin-bottom:10px; }
.empty p { color:var(--t2); font-size:14px; }
.overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.4); z-index:99; }
.overlay.show { display:block; }
@media(max-width:1100px){ .stats { grid-template-columns:repeat(2,1fr); } }
@media(max-width:860px){
    .sidebar { transform:translateX(-100%); }
    .sidebar.open { transform:translateX(0); }
    .main { margin-left:0; }
    .hamburger { display:flex; }
    .page { padding:20px 16px 40px; }
    .topbar { padding:0 16px; }
}
@media(max-width:480px){ .stats { grid-template-columns:1fr 1fr; } }
@media(prefers-reduced-motion:reduce){ * { transition:none!important; animation:none!important; } }
</style>
</head>
<body>
<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <div class="logo-icon"><i class="ti ti-shirt"></i></div>
        <div class="logo-name">Grand <span>Superior</span></div>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section-label">Main</div>
        <a href="rider_dashboard.php" class="nav-link active"><i class="ti ti-layout-dashboard"></i> Dashboard</a>
            <a href="all_bookings.php" class="nav-link"><i class="ti ti-list-check"></i> All orders</a>
        <div class="nav-section-label">Account</div>
        <a href="rider_profile.php" class="nav-link"><i class="ti ti-user-circle"></i> My profile</a>
        <a href="#" class="nav-link"><i class="ti ti-coin"></i> Earnings</a>
        <a href="view_notifications.php" class="nav-link">
            <i class="ti ti-bell"></i> Notifications
            <?php if ($unread_count > 0): ?>
            <span class="nav-badge"><?= $unread_count ?></span>
            <?php endif; ?>
        </a>
             <a href="rider_rider.php" class="nav-link"><i class="ti ti-coin"></i> Logout</a>
    </nav>
    <div class="sidebar-footer">
        <div class="rider-row">
            <div class="rider-av"><?= $initials ?></div>
            <div class="rider-info">
                <div class="rider-name"><?= htmlspecialchars($rider['name']) ?></div>
                <div class="rider-role">Active rider</div>
            </div>
            <a href="rider_logout.php" class="logout-btn" title="Logout"><i class="ti ti-logout"></i></a>
        </div>
    </div>
</aside>
<div class="overlay" id="overlay" onclick="closeSidebar()"></div>
<!-- MAIN -->
<div class="main">
    <header class="topbar">
        <div style="display:flex;align-items:center;gap:12px;">
            <button class="hamburger" onclick="toggleSidebar()" aria-label="Toggle menu">
                <i class="ti ti-menu-2"></i>
            </button>
            <div class="topbar-left">
                <h2><?= $greeting ?>, <?= htmlspecialchars($parts[0]) ?> 👋</h2>
                <p><?= $dateStr ?></p>
            </div>
        </div>
        <div style="display:flex;align-items:center;gap:10px;">
            <a href="#notifications" class="topbar-notif" title="View notifications">
                <i class="ti ti-bell"></i>
                <?php if ($unread_count > 0): ?><span class="n-badge"></span><?php endif; ?>
            </a>
            <div class="online-pill"><span class="online-dot"></span> Online</div>
        </div>
    </header>
    <div class="page">
        <!-- STATS (now 5 cards — adds unread notifications) -->
        <div class="stats" style="grid-template-columns:repeat(5,1fr)">
            <div class="stat">
                <div class="stat-icon icon-orange"><i class="ti ti-clipboard-list"></i></div>
                <div class="stat-value"><?= $total ?></div>
                <div class="stat-label">Total orders</div>
            </div>
            <div class="stat">
                <div class="stat-icon icon-green"><i class="ti ti-circle-check"></i></div>
                <div class="stat-value"><?= $delivered_count ?></div>
                <div class="stat-label">Delivered</div>
                <?php if ($total > 0): ?>
                <div class="stat-sub"><?= round(($delivered_count/$total)*100) ?>% success rate</div>
                <?php endif; ?>
            </div>
            <div class="stat">
                <div class="stat-icon icon-amber"><i class="ti ti-clock"></i></div>
                <div class="stat-value"><?= $pending_count ?></div>
                <div class="stat-label">Pending</div>
                <?php if ($pending_count > 0): ?>
                <div class="stat-sub stat-sub-warn">Action needed</div>
                <?php endif; ?>
            </div>
            <div class="stat">
                <div class="stat-icon icon-blue"><i class="ti ti-truck-delivery"></i></div>
                <div class="stat-value"><?= $statusCounts['picked'] + $statusCounts['in_progress'] ?></div>
                <div class="stat-label">In progress</div>
            </div>
            <div class="stat">
                <div class="stat-icon icon-red"><i class="ti ti-bell-ringing"></i></div>
                <div class="stat-value"><?= $unread_count ?></div>
                <div class="stat-label">Unread alerts</div>
                <?php if ($unread_count > 0): ?>
                <div class="stat-sub stat-sub-red">From owner</div>
                <?php endif; ?>
            </div>
        </div>
        <!-- PROFILE CARD -->
        <div class="profile-card">
            <div class="profile-head">
                <div class="profile-av"><?= $initials ?></div>
                <div class="profile-meta">
                    <h3><?= htmlspecialchars($rider['name']) ?></h3>
                    <p>Rider ID: #RDR-<?= str_pad($rider_id, 4, '0', STR_PAD_LEFT) ?></p>
                    <div class="profile-pills">
                        <span class="pill pill-active">Active</span>
                        <?php if (!empty($rider['vehicle'])): ?>
                        <span class="pill pill-vehicle"><?= htmlspecialchars($rider['vehicle']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="profile-grid">
                <div class="pf"><div class="pf-label">Name</div><div class="pf-val"><?= htmlspecialchars($rider['name']) ?></div></div>
                <div class="pf"><div class="pf-label">Phone</div><div class="pf-val"><?= htmlspecialchars($rider['phone']) ?></div></div>
                <div class="pf"><div class="pf-label">Email</div><div class="pf-val"><?= htmlspecialchars($rider['email']) ?></div></div>
                <div class="pf"><div class="pf-label">Vehicle</div><div class="pf-val"><?= htmlspecialchars($rider['vehicle']) ?></div></div>
                <div class="pf"><div class="pf-label">Address</div><div class="pf-val"><?= htmlspecialchars($rider['address']) ?></div></div>
                <?php if (!empty($rider['national_id'])): ?>
                <div class="pf"><div class="pf-label">National ID</div><div class="pf-val"><?= htmlspecialchars($rider['national_id']) ?></div></div>
                <?php endif; ?>
            </div>
        </div>
        <!-- ══ OWNER NOTIFICATIONS ══ -->
        <div id="notifications">
            <div class="section-header" style="margin-bottom:12px;">
                <div class="section-title">
                    <i class="ti ti-bell-ringing"></i>
                    Owner Notifications
                </div>
                <?php if ($unread_count > 0): ?>
                <span class="section-pill section-pill-red"><?= $unread_count ?> unread</span>
                <?php else: ?>
                <span class="section-pill">All caught up</span>
                <?php endif; ?>
            </div>
            <div class="notif-section">
                <!-- Dark header -->
                <div class="notif-section-head">
                    <div class="notif-section-head-left">
                        <div class="notif-head-icon"><i class="ti ti-bell-ringing"></i></div>
                        <div>
                            <div class="notif-head-title">Messages from the Owner</div>
                            <div class="notif-head-sub">Order dispatches and instructions sent to you</div>
                        </div>
                    </div>
                    <?php if ($unread_count > 0): ?>
                    <div class="notif-unread-pill">
                        <span class="notif-unread-dot"></span>
                        <?= $unread_count ?> new <?= $unread_count === 1 ? 'message' : 'messages' ?>
                    </div>
                    <?php endif; ?>
                </div>
                <!-- Toolbar -->
                <?php if (count($notifs) > 0): ?>
                <div class="notif-toolbar">
                    <span class="notif-count-label"><?= count($notifs) ?> notification<?= count($notifs) !== 1 ? 's' : '' ?> total</span>
                    <?php if ($unread_count > 0): ?>
                    <form method="POST" style="margin:0;">
                        <button type="submit" name="mark_all_read" class="mark-all-btn">
                            <i class="ti ti-checks"></i> Mark all as read
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
                <!-- Notification list -->
                <div class="notif-list">
                <?php foreach ($notifs as $n):
                    $is_unread  = !$n['is_read'];
                    $item_class = $is_unread ? 'unread' : 'read';
                    $booking_ref = $n['booking_id'] ? '#' . str_pad($n['booking_id'], 3, '0', STR_PAD_LEFT) : null;
                    $sent_time  = date('M j, Y · g:i A', strtotime($n['sent_at']));
                ?>
                <div class="notif-item <?= $item_class ?>">
                    <div class="notif-icon-wrap">
                        <i class="ti <?= $is_unread ? 'ti-bell-ringing' : 'ti-bell' ?>"></i>
                    </div>
                    <div class="notif-body">
                        <?php if ($booking_ref): ?>
                        <div class="notif-booking-ref">Order <?= htmlspecialchars($booking_ref) ?></div>
                        <?php endif; ?>
                        <div class="notif-message"><?= htmlspecialchars($n['message']) ?></div>
                        <div class="notif-meta">
                            <span class="notif-time"><i class="ti ti-clock"></i> <?= $sent_time ?></span>
                            <?php if ($is_unread): ?>
                            <span class="unread-label">New</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="notif-action">
                        <?php if ($is_unread): ?>
                        <form method="POST" style="margin:0;">
                            <input type="hidden" name="notif_id" value="<?= $n['id'] ?>">
                            <button type="submit" name="mark_read" class="read-btn">
                                <i class="ti ti-check"></i> Mark read
                            </button>
                        </form>
                        <?php else: ?>
                        <span class="already-read"><i class="ti ti-circle-check"></i> Read</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="notif-empty">
                    <i class="ti ti-bell-off"></i>
                    <p>No notifications yet. The owner will notify you here when an order is ready.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <!-- ORDERS -->
        <div>
            <div class="section-header">
                <div class="section-title"><i class="ti ti-clipboard-list"></i> Laundry orders</div>
                <span class="section-pill"><?= $total ?> orders</span>
            </div>
            <div class="orders">
            <?php if (count($bookings) > 0): ?>
                <?php foreach ($bookings as $row):
                    $status = !empty($row['status']) ? $row['status'] : 'Pending';
                ?>
                <div class="order-card">
                    <div class="order-head">
                        <div>
                            <div class="order-id">#BKG-<?= str_pad($row['id'], 4, '0', STR_PAD_LEFT) ?></div>
                            <div class="order-meta">Client <?= htmlspecialchars($row['client_id']) ?> &nbsp;·&nbsp; Created <?= date('d M Y', strtotime($row['created_at'])) ?></div>
                        </div>
                        <?= getStatusBadge($status) ?>
                    </div>
                    <div class="order-body">
                        <div class="of"><div class="of-label">Service</div><div class="of-val"><?= htmlspecialchars($row['service_type']) ?></div></div>
                        <div class="of"><div class="of-label">Pickup date</div><div class="of-val"><?= htmlspecialchars($row['pickup_date']) ?></div></div>
                        <div class="of"><div class="of-label">Delivery date</div><div class="of-val"><?= htmlspecialchars($row['delivery_date']) ?></div></div>
                        <div class="of"><div class="of-label">Address</div><div class="of-val"><?= htmlspecialchars($row['address']) ?></div></div>
                        <div class="of"><div class="of-label">Notes</div><div class="of-val"><?= $row['notes'] ? htmlspecialchars($row['notes']) : '—' ?></div></div>
                    </div>
                    <form method="POST" class="order-foot">
                        <input type="hidden" name="booking_id" value="<?= $row['id'] ?>">
                        <select name="status" class="status-select" required>
                            <option value="">— Update status —</option>
                            <?php
                            $allStatuses = ['Pending'=>'Pending','Confirmed'=>'Confirmed','Picked'=>'Picked Up','In Progress'=>'In Progress','Delivered'=>'Delivered','Done'=>'Done','Cancelled'=>'Cancelled'];
                            foreach ($allStatuses as $val => $label) {
                                $sel = (strtolower($status) === strtolower($val)) ? ' selected' : '';
                                echo "<option value=\"{$val}\"{$sel}>{$label}</option>";
                            }
                            ?>
                        </select>
                        <button type="submit" name="update_status" class="update-btn">
                            <i class="ti ti-check"></i> Update
                        </button>
                    </form>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty">
                    <i class="ti ti-clipboard-off"></i>
                    <p>No bookings found.</p>
                </div>
            <?php endif; ?>
            </div>
        </div>
    </div><!-- /page -->
</div><!-- /main -->
<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('overlay').classList.toggle('show');
}
function closeSidebar() {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('overlay').classList.remove('show');
}
</script>
</body>
</html>