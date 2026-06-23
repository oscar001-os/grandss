<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', '0');   // keep errors out of the page (avoid blank 500 to visitors)
ini_set('log_errors', '1');       // but log them so you can debug (check your PHP error log)
 
include 'connection.php';
 
if (!isset($_SESSION['owner_id'])) {
    header("Location: owner_login.php");
    exit();
}
 
if (!$conn || (method_exists($conn, 'connect_error') && $conn->connect_error)) {
    die("Database connection failed. Please try again later.");
}
 
$owner_id = $_SESSION['owner_id'];
 
/* ── OWNER DETAILS ── */
$owner = ['name' => 'Owner', 'email' => '', 'phone' => ''];
$stmt = $conn->prepare("SELECT name, email, phone FROM owners WHERE id=?");
if ($stmt) {
    $stmt->bind_param("i", $owner_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $fetched = $result ? $result->fetch_assoc() : null;
    if ($fetched) {
        $owner = $fetched;
    }
    $stmt->close();
}
 
/* Make sure required keys always exist to avoid undefined-key warnings/fatals */
$owner['name']  = $owner['name']  ?? 'Owner';
$owner['email'] = $owner['email'] ?? '';
$owner['phone'] = $owner['phone'] ?? '';
 
if (trim($owner['name']) === '') {
    $owner['name'] = 'Owner';
}
 
/* ── SAFE COUNT HELPER ── */
function safeCount($conn, $sql) {
    $res = $conn->query($sql);
    if ($res) {
        $row = $res->fetch_assoc();
        return (int)($row['total'] ?? 0);
    }
    return 0;
}
 
/* COUNTS */
$total_clients  = safeCount($conn, "SELECT COUNT(*) AS total FROM clients");
$total_bookings = safeCount($conn, "SELECT COUNT(*) AS total FROM bookings");
$total_payments = safeCount($conn, "SELECT COUNT(*) AS total FROM payments");
 
/* OWNER INITIALS */
$parts    = explode(' ', trim($owner['name']));
$initials = strtoupper(substr($parts[0] ?? 'O', 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
if ($initials === '') $initials = 'O';
 
/* GREETING */
$hour     = (int)date('H');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
 
/* ── ENSURE RIDER NOTIFICATIONS TABLE ── */
$conn->query("
    CREATE TABLE IF NOT EXISTS rider_notifications (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        rider_id   INT         NOT NULL,
        booking_id INT         DEFAULT NULL,
        message    TEXT        NOT NULL,
        is_read    TINYINT(1)  NOT NULL DEFAULT 0,
        sent_at    DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP
    )
");
 
/* ── HANDLE NOTIFY RIDER ── */
$notify_msg      = "";
$notify_msg_type = "";
if (isset($_POST['notify_rider'])) {
    $rider_id_n   = intval($_POST['rider_id'] ?? 0);
    $booking_id_n = intval($_POST['booking_id'] ?? 0);
    $custom_note  = trim($_POST['custom_note'] ?? '');
 
    $booking_ref = '#' . str_pad((string)$booking_id_n, 3, '0', STR_PAD_LEFT);
    $message     = "Order $booking_ref is ready for pickup and delivery.";
    if ($custom_note !== '') $message .= " Note: $custom_note";
 
    if ($rider_id_n > 0 && $booking_id_n > 0) {
        $stmt = $conn->prepare(
            "INSERT INTO rider_notifications (rider_id, booking_id, message) VALUES (?, ?, ?)"
        );
        if ($stmt) {
            $stmt->bind_param("iis", $rider_id_n, $booking_id_n, $message);
            if ($stmt->execute()) {
                $notify_msg      = "Rider notified successfully for Booking $booking_ref.";
                $notify_msg_type = "success";
            } else {
                $notify_msg      = "Failed to send notification. Please try again.";
                $notify_msg_type = "error";
                error_log("rider_notifications insert error: " . $stmt->error);
            }
            $stmt->close();
        } else {
            $notify_msg      = "Failed to send notification. Please try again.";
            $notify_msg_type = "error";
            error_log("rider_notifications prepare error: " . $conn->error);
        }
    } else {
        $notify_msg      = "Please select both a rider and a booking.";
        $notify_msg_type = "error";
    }
}
 
/* FETCH RIDERS for dropdown */
$riders_list = $conn->query("SELECT id, name, status FROM riders ORDER BY name ASC");
if (!$riders_list) {
    error_log("riders_list query error: " . $conn->error);
    // Fallback in case riders table has no 'status' column
    $riders_list = $conn->query("SELECT id, name FROM riders ORDER BY name ASC");
}
 
/* FETCH BOOKINGS for dropdown
   NOTE: the `bookings` table has no `status` column — it only has
   `payment_status`. We filter out bookings already fully done using
   payment_status instead, and fall back to showing everything if that
   filter returns nothing. */
$bookings_list = $conn->query("
    SELECT b.id, b.service_type, b.payment_status, c.name AS client_name
    FROM bookings b
    LEFT JOIN clients c ON b.client_id = c.id
    WHERE (b.payment_status IS NULL OR b.payment_status NOT IN ('Delivered','Done','Completed','Cancelled'))
    ORDER BY b.id DESC
    LIMIT 50
");
if (!$bookings_list) {
    error_log("bookings_list query error: " . $conn->error);
    // Fallback: show all recent bookings regardless of payment_status
    $bookings_list = $conn->query("
        SELECT b.id, b.service_type, b.payment_status, c.name AS client_name
        FROM bookings b
        LEFT JOIN clients c ON b.client_id = c.id
        ORDER BY b.id DESC
        LIMIT 50
    ");
}
 
/* RECENT RIDER NOTIFICATIONS */
$recent_notifs = $conn->query("
    SELECT rn.*, r.name AS rider_name
    FROM rider_notifications rn
    LEFT JOIN riders r ON rn.rider_id = r.id
    ORDER BY rn.sent_at DESC
    LIMIT 6
");
 
/* STATUS BADGE HELPER */
function statusBadge($status) {
    $status = $status ?? '';
    $s = strtolower(str_replace(' ', '_', trim($status)));
    $map = [
        'pending'     => ['badge-pending',   'Pending'],
        'confirmed'   => ['badge-confirmed', 'Confirmed'],
        'picked'      => ['badge-picked',    'Picked Up'],
        'in_progress' => ['badge-picked',    'In Progress'],
        'delivered'   => ['badge-delivered', 'Delivered'],
        'done'        => ['badge-done',      'Done'],
        'completed'   => ['badge-done',      'Completed'],
        'cancelled'   => ['badge-cancelled', 'Cancelled'],
        'paid'        => ['badge-paid',      'Paid'],
        'unpaid'      => ['badge-unpaid',    'Unpaid'],
        'partial'     => ['badge-partial',   'Partial'],
        'failed'      => ['badge-cancelled', 'Failed'],
    ];
    [$cls, $lbl] = $map[$s] ?? ['badge-default', ucfirst($status ?: 'Unknown')];
    return '<span class="badge ' . $cls . '"><span class="badge-dot"></span>' . htmlspecialchars($lbl) . '</span>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Owner Dashboard — Grand Superior</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
:root{
  --bg:#f4f7f2;
  --surface:#ffffff;
  --surface2:#f8fbf4;
  --surface3:#f0f5ea;
  --border:#ddeec8;
  --border-soft:#eaf3da;
  --accent:#a6ce39;
  --accent-dark:#8ab530;
  --accent-dim:#f2f9e4;
  --primary:#0d1b2a;
  --text:#1a2a14;
  --muted:#6b7e5a;
  --text-dim:#4a5e38;
  --shadow-sm:0 1px 4px rgba(0,0,0,0.07);
  --shadow:0 4px 18px rgba(0,0,0,0.08);
  --shadow-lg:0 8px 36px rgba(0,0,0,0.11);
  --sidebar-w:255px;
}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);color:var(--text);display:flex;min-height:100vh;overflow-x:hidden}
.sidebar{width:var(--sidebar-w);background:var(--primary);position:fixed;top:0;left:0;height:100vh;display:flex;flex-direction:column;z-index:100}
.sidebar-logo{padding:16px 20px;border-bottom:1px solid rgba(255,255,255,0.08);display:flex;align-items:center;justify-content:center}
.sidebar-logo img{width:100%;max-width:180px;height:auto;display:block;object-fit:contain}
.sidebar-nav{flex:1;padding:14px 10px;display:flex;flex-direction:column;gap:2px;overflow-y:auto}
.nav-label{font-size:9.5px;font-weight:700;color:rgba(255,255,255,.3);text-transform:uppercase;letter-spacing:1.2px;padding:10px 10px 5px}
.nav-link{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:10px;color:rgba(255,255,255,.6);text-decoration:none;font-size:13.5px;font-weight:500;transition:all .18s}
.nav-link:hover{background:rgba(255,255,255,.08);color:#fff}
.nav-link.active{background:var(--accent);color:var(--primary);font-weight:700}
.nav-link svg{width:16px;height:16px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;opacity:.7;flex-shrink:0}
.nav-link.active svg{opacity:1}
.logout-link{color:rgba(239,68,68,.8)!important;margin-top:4px}
.logout-link:hover{background:rgba(239,68,68,.12)!important;color:#f87171!important}
.sidebar-bottom{padding:14px 10px;border-top:1px solid rgba(255,255,255,.07)}
.owner-mini{display:flex;align-items:center;gap:10px}
.owner-avatar{width:36px;height:36px;border-radius:10px;background:var(--accent);display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:800;color:var(--primary);flex-shrink:0}
.owner-mini-name{font-size:13px;font-weight:600;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.owner-mini-role{font-size:11px;color:rgba(255,255,255,.4)}
.main{flex:1;margin-left:var(--sidebar-w);min-height:100vh;display:flex;flex-direction:column}
.topbar{background:var(--surface);border-bottom:1px solid var(--border);padding:14px 28px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:50;box-shadow:var(--shadow-sm);flex-wrap:wrap;gap:10px}
.topbar-left h2{font-size:17px;font-weight:700;color:var(--text);letter-spacing:-.2px}
.topbar-left p{font-size:12px;color:var(--muted);margin-top:2px}
.topbar-badge{display:flex;align-items:center;gap:6px;background:var(--accent-dim);border:1px solid var(--border);padding:7px 14px;border-radius:100px;font-size:12px;font-weight:600;color:var(--accent-dark)}
.topbar-badge svg{width:13px;height:13px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.hamburger{display:none;align-items:center;justify-content:center;width:36px;height:36px;border-radius:8px;background:var(--surface2);border:1px solid var(--border);cursor:pointer}
.hamburger svg{width:16px;height:16px;fill:none;stroke:var(--text);stroke-width:2;stroke-linecap:round}
.content{padding:24px 28px;display:flex;flex-direction:column;gap:22px}
.welcome-card{background:linear-gradient(135deg,var(--primary) 0%,#1a3a50 100%);border-radius:18px;padding:26px 28px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;position:relative;overflow:hidden}
.welcome-card::before{content:'';position:absolute;top:-40px;right:-40px;width:200px;height:200px;border-radius:50%;background:rgba(166,206,57,.08)}
.welcome-card::after{content:'';position:absolute;bottom:-60px;right:120px;width:160px;height:160px;border-radius:50%;background:rgba(166,206,57,.05)}
.welcome-text{position:relative;z-index:1}
.welcome-text h2{font-size:20px;font-weight:800;color:#fff;letter-spacing:-.3px}
.welcome-text h2 span{color:var(--accent)}
.welcome-text p{font-size:13px;color:rgba(255,255,255,.6);margin-top:4px}
.welcome-meta{display:flex;gap:14px;flex-wrap:wrap;position:relative;z-index:1}
.meta-item{display:flex;align-items:center;gap:8px;background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.1);padding:8px 14px;border-radius:10px}
.meta-item svg{width:14px;height:14px;fill:none;stroke:var(--accent);stroke-width:2;stroke-linecap:round;stroke-linejoin:round;flex-shrink:0}
.meta-item span{font-size:12.5px;color:rgba(255,255,255,.8);font-weight:500}
.stats-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}
.stat-card{background:var(--surface);border:1.5px solid var(--border);border-radius:16px;padding:20px;overflow:hidden;box-shadow:var(--shadow-sm);transition:box-shadow .2s,transform .2s;animation:fadeUp .5s ease both}
.stat-card:hover{box-shadow:var(--shadow);transform:translateY(-2px)}
.stat-card:nth-child(1){animation-delay:.05s}
.stat-card:nth-child(2){animation-delay:.10s}
.stat-card:nth-child(3){animation-delay:.15s}
.stat-top{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:14px}
.stat-icon{width:42px;height:42px;border-radius:12px;display:flex;align-items:center;justify-content:center}
.stat-icon svg{width:18px;height:18px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.stat-card:nth-child(1) .stat-icon{background:#f0f9e4;color:var(--accent-dark)}
.stat-card:nth-child(2) .stat-icon{background:#eff6ff;color:#3b82f6}
.stat-card:nth-child(3) .stat-icon{background:#fff7ed;color:#f97316}
.stat-trend{font-size:11px;font-weight:600;color:#16a34a;background:#f0fdf4;border:1px solid #bbf7d0;padding:3px 8px;border-radius:100px}
.stat-num{font-family:'Space Mono',monospace;font-size:32px;font-weight:700;color:var(--text);line-height:1}
.stat-label{font-size:12px;color:var(--muted);margin-top:5px;font-weight:500}
.stat-bar{height:3px;border-radius:2px;margin-top:14px;background:var(--border)}
.stat-bar-fill{height:100%;border-radius:2px}
.stat-card:nth-child(1) .stat-bar-fill{background:var(--accent)}
.stat-card:nth-child(2) .stat-bar-fill{background:#3b82f6}
.stat-card:nth-child(3) .stat-bar-fill{background:#f97316}
.notify-card{background:var(--surface);border:1.5px solid var(--border);border-radius:18px;overflow:hidden;box-shadow:var(--shadow-sm);animation:fadeUp .5s ease both;animation-delay:.12s}
.notify-header{padding:18px 22px;border-bottom:1px solid var(--border-soft);background:linear-gradient(135deg,var(--primary) 0%,#1a3a50 100%);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px}
.notify-header-left{display:flex;align-items:center;gap:12px}
.notify-header-icon{width:40px;height:40px;border-radius:11px;background:rgba(166,206,57,.15);border:1px solid rgba(166,206,57,.3);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.notify-header-icon svg{width:18px;height:18px;fill:none;stroke:var(--accent);stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.notify-header h3{font-size:15px;font-weight:700;color:#fff}
.notify-header p{font-size:12px;color:rgba(255,255,255,.5);margin-top:2px}
.notify-body{padding:22px}
.notify-alert{display:flex;align-items:flex-start;gap:9px;padding:12px 14px;border-radius:10px;font-size:13px;font-weight:500;margin-bottom:18px;border:1px solid transparent;animation:slideIn .25s ease}
@keyframes slideIn{from{opacity:0;transform:translateY(-5px)}to{opacity:1;transform:translateY(0)}}
.notify-alert svg{width:15px;height:15px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;flex-shrink:0;margin-top:1px}
.notify-alert.success{background:#f0fdf4;border-color:#bbf7d0;color:#15803d}
.notify-alert.error  {background:#fef2f2;border-color:#fecaca;color:#b91c1c}
.notify-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
@media(max-width:700px){.notify-form-grid{grid-template-columns:1fr}}
.nf-label{font-size:11.5px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px;display:block}
.nf-wrap{position:relative}
.nf-wrap svg.nf-icon{position:absolute;left:11px;top:50%;transform:translateY(-50%);width:14px;height:14px;fill:none;stroke:#9ca3af;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;pointer-events:none}
.nf-wrap select,.nf-wrap input,.nf-wrap textarea{
  width:100%;padding:10px 12px 10px 34px;
  border:1.5px solid #e5e7eb;border-radius:10px;
  font-size:13.5px;font-family:inherit;color:var(--text);
  background:#fafafa;outline:none;
  transition:border-color .2s,box-shadow .2s;
}
.nf-wrap select:focus,.nf-wrap input:focus,.nf-wrap textarea:focus{
  border-color:var(--accent);box-shadow:0 0 0 3px rgba(166,206,57,.15);background:#fff;
}
.nf-wrap textarea{padding-top:11px;min-height:72px;resize:none;line-height:1.55}
.notify-form-grid .full{grid-column:1/-1}
.notify-actions{display:flex;align-items:center;justify-content:flex-end;padding-top:4px}
.btn-notify{display:inline-flex;align-items:center;gap:8px;padding:11px 24px;border-radius:10px;background:var(--accent);color:var(--primary);font-size:13.5px;font-weight:700;border:none;cursor:pointer;font-family:inherit;transition:all .2s}
.btn-notify:hover{background:var(--accent-dark);transform:translateY(-1px)}
.btn-notify svg{width:15px;height:15px;fill:none;stroke:currentColor;stroke-width:2.5;stroke-linecap:round;stroke-linejoin:round}
.rn-list{display:flex;flex-direction:column;gap:0;border-top:1px solid var(--border-soft);margin-top:18px}
.rn-item{display:flex;align-items:flex-start;gap:12px;padding:13px 0;border-bottom:1px solid var(--border-soft)}
.rn-item:last-child{border-bottom:none}
.rn-dot{width:36px;height:36px;border-radius:10px;background:var(--accent-dim);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.rn-dot svg{width:15px;height:15px;fill:none;stroke:var(--accent-dark);stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.rn-rider{font-size:13px;font-weight:700;color:var(--text)}
.rn-msg{font-size:12.5px;color:var(--muted);margin-top:2px;line-height:1.45}
.rn-time{font-size:11px;color:#9ca3af;margin-top:4px}
.rn-unread{display:inline-block;width:7px;height:7px;border-radius:50%;background:var(--accent);margin-left:6px;vertical-align:middle}
.table-card{background:var(--surface);border:1.5px solid var(--border);border-radius:18px;overflow:hidden;box-shadow:var(--shadow-sm);animation:fadeUp .5s ease both}
.table-card:nth-of-type(1){animation-delay:.1s}
.table-card:nth-of-type(2){animation-delay:.15s}
.table-card:nth-of-type(3){animation-delay:.2s}
.table-head{display:flex;align-items:center;justify-content:space-between;padding:18px 20px;border-bottom:1px solid var(--border-soft);background:var(--surface2)}
.table-head h3{font-size:14.5px;font-weight:700;color:var(--text);display:flex;align-items:center;gap:8px}
.table-head h3 svg{width:16px;height:16px;fill:none;stroke:var(--accent-dark);stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.view-btn{display:inline-flex;align-items:center;gap:5px;font-size:12px;font-weight:600;color:var(--accent-dark);text-decoration:none;padding:7px 14px;border-radius:8px;background:var(--accent-dim);border:1px solid var(--border);transition:all .2s}
.view-btn:hover{background:var(--accent);color:var(--primary)}
.view-btn svg{width:12px;height:12px;fill:none;stroke:currentColor;stroke-width:2.5;stroke-linecap:round;stroke-linejoin:round}
.table-scroll{overflow-x:auto}
table{width:100%;border-collapse:collapse;font-size:13.5px}
thead th{padding:11px 16px;text-align:left;font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--muted);background:var(--surface2);border-bottom:1px solid var(--border-soft);white-space:nowrap}
thead th:first-child{padding-left:20px}
tbody tr{border-bottom:1px solid var(--border-soft);transition:background .15s}
tbody tr:last-child{border-bottom:none}
tbody tr:hover{background:var(--surface2)}
td{padding:13px 16px;color:var(--text-dim);vertical-align:middle;white-space:nowrap}
td:first-child{padding-left:20px}
.id-cell{font-family:'Space Mono',monospace;font-size:11.5px;color:var(--accent-dark);font-weight:700}
.name-cell{font-weight:600;color:var(--text)}
.amount-cell{font-family:'Space Mono',monospace;font-size:13px;font-weight:700;color:var(--text)}
.email-cell{font-size:12.5px}
.badge{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:100px;font-size:11px;font-weight:600;white-space:nowrap}
.badge-dot{width:5px;height:5px;border-radius:50%;flex-shrink:0}
.badge-pending   {background:#fffbeb;color:#b45309;border:1px solid #fde68a}
.badge-pending .badge-dot   {background:#f59e0b}
.badge-confirmed {background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe}
.badge-confirmed .badge-dot {background:#3b82f6}
.badge-picked    {background:#f5f3ff;color:#5b21b6;border:1px solid #ddd6fe}
.badge-picked .badge-dot    {background:#7c3aed;animation:pulse 1.5s infinite}
.badge-delivered {background:#f0fdf4;color:#15803d;border:1px solid #bbf7d0}
.badge-delivered .badge-dot {background:#22c55e}
.badge-done      {background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0}
.badge-done .badge-dot      {background:#10b981}
.badge-cancelled {background:#fef2f2;color:#b91c1c;border:1px solid #fecaca}
.badge-cancelled .badge-dot {background:#ef4444}
.badge-paid      {background:#f0fdf4;color:#15803d;border:1px solid #bbf7d0}
.badge-paid .badge-dot      {background:#22c55e}
.badge-unpaid    {background:#fef2f2;color:#b91c1c;border:1px solid #fecaca}
.badge-unpaid .badge-dot    {background:#ef4444}
.badge-partial   {background:#fffbeb;color:#b45309;border:1px solid #fde68a}
.badge-partial .badge-dot   {background:#f59e0b}
.badge-default   {background:var(--surface2);color:var(--muted);border:1px solid var(--border)}
.badge-default .badge-dot   {background:var(--muted)}
.empty-row td{text-align:center;padding:40px;color:var(--muted);font-size:14px}
.sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:99}
.sidebar-overlay.show{display:block}
@keyframes fadeUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.4}}
@media(prefers-reduced-motion:reduce){*{animation:none!important;transition:none!important}}
@media(max-width:1024px){.stats-grid{grid-template-columns:1fr 1fr 1fr}}
@media(max-width:900px){
  .sidebar{transform:translateX(-100%);transition:transform .28s}
  .sidebar.open{transform:translateX(0)}
  .main{margin-left:0}
  .hamburger{display:flex!important}
  .content{padding:16px}
  .topbar{padding:12px 16px}
  .stats-grid{grid-template-columns:1fr 1fr}
  .welcome-meta{display:none}
}
@media(max-width:520px){.stats-grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<!-- ══ SIDEBAR ══ -->
<div class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <img src="logo.jpg" alt="Grand Superior">
  </div>
  <nav class="sidebar-nav">
    <div class="nav-label">Overview</div>
    <a href="#dashboard" class="nav-link active">
      <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
      Dashboard
    </a>
    <div class="nav-label">Management</div>
    <a href="view_clients.php" class="nav-link">
      <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      Clients
    </a>
    <a href="view_riders.php" class="nav-link">
      <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="2"/><path d="M12 2a10 10 0 0 1 7.74 16.33M12 22a10 10 0 0 1-7.74-16.33"/></svg>
      Riders
    </a>
    <a href="all_client_bookings.php" class="nav-link">
      <svg viewBox="0 0 24 24"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1"/><line x1="9" y1="12" x2="15" y2="12"/><line x1="9" y1="16" x2="13" y2="16"/></svg>
      Bookings
    </a>
    <a href="view_payments.php" class="nav-link">
      <svg viewBox="0 0 24 24"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
      Payments
  </a>
      <a href="check_receipt.php" class="nav-link">
      <svg viewBox="0 0 24 24"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
      Check receipt
  </a>
       <a href="report.php" class="nav-link">
      <svg viewBox="0 0 24 24"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
      Report
  </a>
    <div class="nav-label">System</div>
    <a href="notify.php" class="nav-link">
      <svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
      Notifications
    </a>
    <a href="owner_profile.php" class="nav-link">
      <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
      My Profile
    </a>
    <a href="logout.php" class="nav-link logout-link">
      <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      Logout
    </a>
  </nav>
  <div class="sidebar-bottom">
    <div class="owner-mini">
      <div class="owner-avatar"><?= htmlspecialchars($initials) ?></div>
      <div style="flex:1;min-width:0">
        <div class="owner-mini-name"><?= htmlspecialchars($owner['name']) ?></div>
        <div class="owner-mini-role">Business Owner</div>
      </div>
    </div>
  </div>
</div>
<div class="sidebar-overlay" id="overlay"></div>
<!-- ══ MAIN ══ -->
<div class="main">
  <div class="topbar">
    <div style="display:flex;align-items:center;gap:12px">
      <button class="hamburger" id="hamburger" aria-label="Toggle sidebar">
        <svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
      </button>
      <div class="topbar-left">
        <h2>Owner Dashboard</h2>
        <p>Grand Superior Drycleaners &mdash; Admin Overview</p>
      </div>
    </div>
    <div class="topbar-badge">
      <svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
      System Active
    </div>
  </div>
  <div class="content">
    <!-- WELCOME -->
    <div class="welcome-card" id="dashboard">
      <div class="welcome-text">
        <h2><?= htmlspecialchars($greeting) ?>, <span><?= htmlspecialchars(explode(' ', $owner['name'])[0] ?? 'Owner') ?></span> 👋</h2>
        <p>Owner Dashboard &middot; Grand Superior Drycleaners</p>
      </div>
      <div class="welcome-meta">
        <?php if (!empty($owner['email'])): ?>
        <div class="meta-item">
          <svg viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
          <span><?= htmlspecialchars($owner['email']) ?></span>
        </div>
        <?php endif; ?>
        <?php if (!empty($owner['phone'])): ?>
        <div class="meta-item">
          <svg viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.6 3.37 2 2 0 0 1 3.59 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.87a16 16 0 0 0 6 6l.87-.87a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
          <span><?= htmlspecialchars($owner['phone']) ?></span>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <!-- STATS -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-top">
          <div class="stat-icon"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
          <span class="stat-trend">Clients</span>
        </div>
        <div class="stat-num"><?= number_format($total_clients) ?></div>
        <div class="stat-label">Total Registered Clients</div>
        <div class="stat-bar"><div class="stat-bar-fill" style="width:<?= min(100, $total_clients > 0 ? ($total_clients / 200) * 100 : 0) ?>%"></div></div>
      </div>
      <div class="stat-card">
        <div class="stat-top">
          <div class="stat-icon"><svg viewBox="0 0 24 24"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1"/><line x1="9" y1="12" x2="15" y2="12"/></svg></div>
          <span class="stat-trend">Bookings</span>
        </div>
        <div class="stat-num"><?= number_format($total_bookings) ?></div>
        <div class="stat-label">Total Bookings Made</div>
        <div class="stat-bar"><div class="stat-bar-fill" style="width:<?= min(100, $total_bookings > 0 ? ($total_bookings / 500) * 100 : 0) ?>%"></div></div>
      </div>
      <div class="stat-card">
        <div class="stat-top">
          <div class="stat-icon"><svg viewBox="0 0 24 24"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg></div>
          <span class="stat-trend">Payments</span>
        </div>
        <div class="stat-num"><?= number_format($total_payments) ?></div>
        <div class="stat-label">Total Payments Recorded</div>
        <div class="stat-bar"><div class="stat-bar-fill" style="width:<?= min(100, $total_payments > 0 ? ($total_payments / 400) * 100 : 0) ?>%"></div></div>
      </div>
    </div>
    <!-- ══ NOTIFY RIDER SECTION ══ -->
    <div class="notify-card" id="notify-rider">
      <div class="notify-header">
        <div class="notify-header-left">
          <div class="notify-header-icon">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="2"/><path d="M12 2a10 10 0 0 1 7.74 16.33M12 22a10 10 0 0 1-7.74-16.33"/></svg>
          </div>
          <div>
            <h3>Notify Rider — Order Ready</h3>
            <p>Tell a rider that a client's order is cleaned and ready for pickup & delivery</p>
          </div>
        </div>
      </div>
      <div class="notify-body">
        <?php if (!empty($notify_msg)): ?>
        <div class="notify-alert <?= htmlspecialchars($notify_msg_type) ?>">
          <?php if ($notify_msg_type === 'success'): ?>
            <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
          <?php else: ?>
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          <?php endif; ?>
          <?= htmlspecialchars($notify_msg) ?>
        </div>
        <?php endif; ?>
        <form method="POST" id="notifyForm">
          <div class="notify-form-grid">
            <!-- SELECT RIDER -->
            <div>
              <label class="nf-label">Select Rider *</label>
              <div class="nf-wrap">
                <svg class="nf-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="2"/><path d="M12 2a10 10 0 0 1 7.74 16.33M12 22a10 10 0 0 1-7.74-16.33"/></svg>
                <select name="rider_id" required>
                  <option value="">— Choose a rider —</option>
                  <?php
                  if ($riders_list && $riders_list->num_rows > 0):
                    while ($r = $riders_list->fetch_assoc()):
                      $rstatus = $r['status'] ?? '';
                      $avail = $rstatus === 'Available' ? ' ✓' : ($rstatus !== '' ? ' (' . $rstatus . ')' : '');
                  ?>
                    <option value="<?= (int)$r['id'] ?>"><?= htmlspecialchars(($r['name'] ?? 'Rider') . $avail) ?></option>
                  <?php endwhile; else: ?>
                    <option disabled>No riders available</option>
                  <?php endif; ?>
                </select>
              </div>
            </div>
            <!-- SELECT BOOKING -->
            <div>
              <label class="nf-label">Select Booking / Order *</label>
              <div class="nf-wrap">
                <svg class="nf-icon" viewBox="0 0 24 24"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1"/></svg>
                <select name="booking_id" required>
                  <option value="">— Choose an order —</option>
                  <?php
                  if ($bookings_list && $bookings_list->num_rows > 0):
                    while ($b = $bookings_list->fetch_assoc()):
                      $ref     = '#' . str_pad((string)$b['id'], 3, '0', STR_PAD_LEFT);
                      $client  = !empty($b['client_name']) ? ' – ' . $b['client_name'] : '';
                      $service = !empty($b['service_type']) ? ' (' . $b['service_type'] . ')' : '';
                      $bstatus = !empty($b['payment_status']) ? $b['payment_status'] : 'Pending';
                  ?>
                    <option value="<?= (int)$b['id'] ?>"><?= htmlspecialchars($ref . $client . $service) ?> [<?= htmlspecialchars($bstatus) ?>]</option>
                  <?php endwhile; else: ?>
                    <option disabled>No active orders</option>
                  <?php endif; ?>
                </select>
              </div>
            </div>
            <!-- OPTIONAL NOTE -->
            <div class="full">
              <label class="nf-label">Additional Note <span style="color:var(--muted);font-weight:400;text-transform:none">(optional)</span></label>
              <div class="nf-wrap">
                <svg class="nf-icon" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                <textarea name="custom_note" placeholder="e.g. Please pick up from the front desk — items are in bag #3..." maxlength="200"></textarea>
              </div>
            </div>
            <div class="full">
              <div style="background:var(--accent-dim);border:1px solid var(--border);border-radius:10px;padding:11px 14px;font-size:12.5px;color:var(--text-dim);display:flex;align-items:flex-start;gap:8px">
                <svg style="width:14px;height:14px;flex-shrink:0;margin-top:1px;fill:none;stroke:var(--accent-dark);stroke-width:2;stroke-linecap:round;stroke-linejoin:round" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                The rider will see this notification on their dashboard the next time they log in. The message will read: <em>"Order #XXX is ready for pickup and delivery."</em>
              </div>
            </div>
          </div>
          <div class="notify-actions" style="margin-top:16px">
            <button type="submit" name="notify_rider" class="btn-notify">
              <svg viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
              Send Notification to Rider
            </button>
          </div>
        </form>
        <!-- RECENT RIDER NOTIFICATIONS LOG -->
        <?php if ($recent_notifs && $recent_notifs->num_rows > 0): ?>
        <div style="margin-top:4px">
          <div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:2px;padding-top:16px;border-top:1px solid var(--border-soft)">Recent Rider Notifications</div>
          <div class="rn-list">
            <?php while ($rn = $recent_notifs->fetch_assoc()): ?>
            <div class="rn-item">
              <div class="rn-dot">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="2"/><path d="M12 2a10 10 0 0 1 7.74 16.33M12 22a10 10 0 0 1-7.74-16.33"/></svg>
              </div>
              <div style="flex:1;min-width:0">
                <div class="rn-rider">
                  <?= htmlspecialchars($rn['rider_name'] ?? 'Unknown Rider') ?>
                  <?php if (empty($rn['is_read'])): ?><span class="rn-unread" title="Unread"></span><?php endif; ?>
                </div>
                <div class="rn-msg"><?= htmlspecialchars($rn['message'] ?? '') ?></div>
                <div class="rn-time"><?= !empty($rn['sent_at']) ? date('M j, Y · g:i A', strtotime($rn['sent_at'])) : '' ?></div>
              </div>
              <?php if (!empty($rn['booking_id'])): ?>
              <div style="font-family:'Space Mono',monospace;font-size:11px;font-weight:700;color:var(--accent-dark);flex-shrink:0">#<?= str_pad((string)$rn['booking_id'], 3, '0', STR_PAD_LEFT) ?></div>
              <?php endif; ?>
            </div>
            <?php endwhile; ?>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <!-- RECENT CLIENTS -->
    <div class="table-card">
      <div class="table-head">
        <h3><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg> Recent Clients</h3>
        <a href="view_clients.php" class="view-btn">View All <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg></a>
      </div>
      <div class="table-scroll">
        <table>
          <thead><tr><th>#ID</th><th>Name</th><th>Email</th></tr></thead>
          <tbody>
          <?php
          $clients = $conn->query("SELECT id, name, email FROM clients ORDER BY id DESC LIMIT 5");
          if ($clients && $clients->num_rows > 0):
            while ($row = $clients->fetch_assoc()):
          ?>
            <tr>
              <td class="id-cell">#<?= str_pad((string)$row['id'], 3, '0', STR_PAD_LEFT) ?></td>
              <td class="name-cell"><?= htmlspecialchars($row['name'] ?? '') ?></td>
              <td class="email-cell"><?= htmlspecialchars($row['email'] ?? '') ?></td>
            </tr>
          <?php endwhile; else: ?>
            <tr class="empty-row"><td colspan="3">No clients found</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <!-- RECENT BOOKINGS -->
    <div class="table-card">
      <div class="table-head">
        <h3><svg viewBox="0 0 24 24"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1"/></svg> Recent Bookings</h3>
        <a href="all_client_bookings.php" class="view-btn">View All <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg></a>
      </div>
      <div class="table-scroll">
        <table>
          <thead><tr><th>#ID</th><th>Client ID</th><th>Service</th><th>Pickup Date</th><th>Delivery Date</th><th>Payment Status</th></tr></thead>
          <tbody>
          <?php
          $bookings = $conn->query("SELECT * FROM bookings ORDER BY id DESC LIMIT 5");
          if ($bookings && $bookings->num_rows > 0):
            while ($row = $bookings->fetch_assoc()):
              $st = !empty($row['payment_status']) ? $row['payment_status'] : 'Pending';
          ?>
            <tr>
              <td class="id-cell">#<?= str_pad((string)$row['id'], 3, '0', STR_PAD_LEFT) ?></td>
              <td><?= htmlspecialchars($row['client_id'] ?? '') ?></td>
              <td><?= htmlspecialchars($row['service_type'] ?? '') ?></td>
              <td><?= htmlspecialchars($row['pickup_date'] ?? '') ?></td>
              <td><?= htmlspecialchars($row['delivery_date'] ?? '') ?></td>
              <td><?= statusBadge($st) ?></td>
            </tr>
          <?php endwhile; else: ?>
            <tr class="empty-row"><td colspan="6">No bookings found</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <!-- RECENT PAYMENTS -->
    <div class="table-card">
      <div class="table-head">
        <h3><svg viewBox="0 0 24 24"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg> Recent Payments</h3>
        <a href="view_payments.php" class="view-btn">View All <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg></a>
      </div>
      <div class="table-scroll">
        <table>
          <thead><tr><th>#ID</th><th>Client ID</th><th>Amount</th><th>Status</th></tr></thead>
          <tbody>
          <?php
          $payments = $conn->query("SELECT * FROM payments ORDER BY id DESC LIMIT 5");
          if ($payments && $payments->num_rows > 0):
            while ($row = $payments->fetch_assoc()):
              $ps = !empty($row['status']) ? $row['status'] : 'Unpaid';
          ?>
            <tr>
              <td class="id-cell">#<?= str_pad((string)$row['id'], 3, '0', STR_PAD_LEFT) ?></td>
              <td><?= htmlspecialchars($row['client_id'] ?? '') ?></td>
              <td class="amount-cell">KES <?= number_format((float)($row['amount'] ?? 0), 2) ?></td>
              <td><?= statusBadge($ps) ?></td>
            </tr>
          <?php endwhile; else: ?>
            <tr class="empty-row"><td colspan="4">No payments found</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div><!-- /content -->
</div><!-- /main -->
<script>
const sidebar   = document.getElementById('sidebar');
const overlay   = document.getElementById('overlay');
const hamburger = document.getElementById('hamburger');
hamburger.addEventListener('click', () => {
  sidebar.classList.toggle('open');
  overlay.classList.toggle('show');
});
overlay.addEventListener('click', () => {
  sidebar.classList.remove('open');
  overlay.classList.remove('show');
});
</script>
</body>
</html>