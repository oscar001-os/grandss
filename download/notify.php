<?php
session_start();
include("connection.php");
if (!isset($_SESSION['owner_id'])) {
    header("Location: owner_login.php");
    exit();
}
$owner_id = $_SESSION['owner_id'];
$msg      = "";
$msg_type = "";
/* OWNER NAME */
$stmt = $conn->prepare("SELECT name FROM owners WHERE id = ?");
$stmt->bind_param("i", $owner_id);
$stmt->execute();
$owner = $stmt->get_result()->fetch_assoc();
$stmt->close();
$parts    = explode(' ', trim($owner['name']));
$initials = strtoupper(substr($parts[0],0,1) . (isset($parts[1]) ? substr($parts[1],0,1) : ''));
/* CREATE TABLE IF NOT EXISTS */
$conn->query("
    CREATE TABLE IF NOT EXISTS notifications (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        title      VARCHAR(255) NOT NULL,
        message    TEXT         NOT NULL,
        owner_id   INT          NOT NULL,
        created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
    )
");
/* HANDLE SEND — checked via hidden input, NOT the button name */
if (isset($_POST['send'])) {
    $title   = trim($_POST['title']   ?? '');
    $message = trim($_POST['message'] ?? '');
    if ($title === '' || $message === '') {
        $msg      = "Both title and message are required.";
        $msg_type = "error";
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO notifications (title, message, owner_id, created_at) VALUES (?, ?, ?, NOW())"
        );
        if (!$stmt) {
            $msg      = "Database error: " . $conn->error;
            $msg_type = "error";
        } else {
            $stmt->bind_param("ssi", $title, $message, $owner_id);
            if ($stmt->execute()) {
                $msg      = "Notification sent and saved successfully.";
                $msg_type = "success";
                /* Clear inputs after success */
                $title   = '';
                $message = '';
            } else {
                $msg      = "Failed to send: " . $stmt->error;
                $msg_type = "error";
            }
            $stmt->close();
        }
    }
}
/* FETCH RECENT */
$recent      = $conn->query("SELECT * FROM notifications ORDER BY created_at DESC LIMIT 8");
$recent_rows = $recent ? $recent->fetch_all(MYSQLI_ASSOC) : [];
$total       = $conn->query("SELECT COUNT(*) AS c FROM notifications")->fetch_assoc()['c'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Send Notification — Grand Superior</title>
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
  --sidebar-w:255px;
}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);color:var(--text);display:flex;min-height:100vh}
a{text-decoration:none;color:inherit}
/* ── SIDEBAR ── */
.sidebar{width:var(--sidebar-w);background:var(--primary);position:fixed;top:0;left:0;height:100vh;display:flex;flex-direction:column;z-index:100}
.sidebar-logo{padding:16px 20px;border-bottom:1px solid rgba(255,255,255,.08);display:flex;align-items:center;justify-content:center}
.sidebar-logo img{width:100%;max-width:180px;height:auto;object-fit:contain;display:block}
.sidebar-nav{flex:1;padding:14px 10px;display:flex;flex-direction:column;gap:2px;overflow-y:auto}
.nav-label{font-size:9.5px;font-weight:700;color:rgba(255,255,255,.3);text-transform:uppercase;letter-spacing:1.2px;padding:10px 10px 5px}
.nav-link{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:10px;color:rgba(255,255,255,.6);font-size:13.5px;font-weight:500;transition:all .18s}
.nav-link:hover{background:rgba(255,255,255,.08);color:#fff}
.nav-link.active{background:var(--accent);color:var(--primary);font-weight:700}
.nav-link svg{width:16px;height:16px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;opacity:.7;flex-shrink:0}
.nav-link.active svg{opacity:1}
.logout-link{color:rgba(239,68,68,.8)!important;margin-top:4px}
.logout-link:hover{background:rgba(239,68,68,.12)!important;color:#f87171!important}
.sidebar-bottom{padding:14px 10px;border-top:1px solid rgba(255,255,255,.07)}
.owner-mini{display:flex;align-items:center;gap:10px}
.owner-av{width:36px;height:36px;border-radius:10px;background:var(--accent);display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:800;color:var(--primary);flex-shrink:0}
.owner-mini-name{font-size:13px;font-weight:600;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.owner-mini-role{font-size:11px;color:rgba(255,255,255,.4)}
/* ── MAIN ── */
.main{flex:1;margin-left:var(--sidebar-w);min-height:100vh;display:flex;flex-direction:column}
.topbar{background:var(--surface);border-bottom:1px solid var(--border);padding:14px 28px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:50;box-shadow:var(--shadow-sm);flex-wrap:wrap;gap:10px}
.topbar-left h2{font-size:17px;font-weight:700;color:var(--text)}
.topbar-left p{font-size:12px;color:var(--muted);margin-top:2px}
.topbar-badge{display:flex;align-items:center;gap:6px;background:var(--accent-dim);border:1px solid var(--border);padding:7px 14px;border-radius:100px;font-size:12px;font-weight:600;color:var(--accent-dark)}
.topbar-badge svg{width:13px;height:13px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.hamburger{display:none;align-items:center;justify-content:center;width:36px;height:36px;border-radius:8px;background:var(--surface2);border:1px solid var(--border);cursor:pointer;background:none;border:none}
.hamburger svg{width:20px;height:20px;fill:none;stroke:var(--text);stroke-width:2;stroke-linecap:round}
/* ── CONTENT ── */
.content{padding:28px;display:flex;flex-direction:column;gap:22px;max-width:820px}
/* PAGE HEADER CARD */
.page-hero{background:linear-gradient(135deg,var(--primary) 0%,#1a3a50 100%);border-radius:18px;padding:24px 28px;display:flex;align-items:center;gap:18px;position:relative;overflow:hidden;box-shadow:var(--shadow-lg)}
.page-hero::before{content:'';position:absolute;top:-40px;right:-40px;width:180px;height:180px;border-radius:50%;background:rgba(166,206,57,.07)}
.ph-icon{width:52px;height:52px;border-radius:14px;background:rgba(166,206,57,.15);border:1px solid rgba(166,206,57,.3);display:flex;align-items:center;justify-content:center;flex-shrink:0;position:relative;z-index:1}
.ph-icon svg{width:24px;height:24px;fill:none;stroke:var(--accent);stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.ph-text{position:relative;z-index:1;flex:1}
.ph-text h2{font-size:20px;font-weight:800;color:#fff}
.ph-text p{font-size:13px;color:rgba(255,255,255,.5);margin-top:3px}
.ph-count{position:relative;z-index:1;text-align:right}
.ph-count .num{font-family:'Space Mono',monospace;font-size:28px;font-weight:700;color:var(--accent);line-height:1}
.ph-count .lbl{font-size:11px;color:rgba(255,255,255,.4);margin-top:2px}
/* ALERT */
.alert{display:flex;align-items:flex-start;gap:10px;padding:14px 16px;border-radius:12px;font-size:13.5px;font-weight:500;border:1px solid transparent;animation:slideIn .25s ease}
@keyframes slideIn{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:translateY(0)}}
.alert svg{width:16px;height:16px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;flex-shrink:0;margin-top:1px}
.alert-success{background:#edfaf4;border-color:#a7f3d0;color:#065f46}
.alert-error  {background:#fff1f1;border-color:#fca5a5;color:#991b1b}
/* FORM CARD */
.form-card{background:var(--surface);border:1.5px solid var(--border);border-radius:18px;overflow:hidden;box-shadow:var(--shadow-sm)}
.form-card-head{padding:18px 22px;border-bottom:1px solid var(--border-soft);background:var(--surface2);display:flex;align-items:center;gap:12px}
.fch-icon{width:38px;height:38px;border-radius:10px;background:var(--accent-dim);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.fch-icon svg{width:17px;height:17px;fill:none;stroke:var(--accent-dark);stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.fch-title{font-size:15px;font-weight:700;color:var(--text)}
.fch-sub{font-size:12px;color:var(--muted);margin-top:1px}
.form-body{padding:26px 24px}
/* TIP */
.tip{display:flex;align-items:flex-start;gap:9px;background:var(--accent-dim);border:1px solid #d4eaaa;border-radius:10px;padding:12px 14px;font-size:12.5px;color:#4a6b1a;margin-bottom:22px;line-height:1.6}
.tip svg{width:14px;height:14px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;flex-shrink:0;margin-top:1px}
/* FIELDS */
.field{display:flex;flex-direction:column;gap:6px;margin-bottom:6px}
.field label{font-size:11.5px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px}
.field input,.field textarea{padding:11px 14px;border:1.5px solid var(--border);border-radius:10px;font-family:'Plus Jakarta Sans',sans-serif;font-size:13.5px;color:var(--text);background:var(--surface2);outline:none;width:100%;transition:border-color .15s,box-shadow .15s,background .15s}
.field textarea{resize:vertical;min-height:130px;line-height:1.65}
.field input:focus,.field textarea:focus{border-color:var(--accent-dark);box-shadow:0 0 0 4px rgba(166,206,57,.18);background:#fff}
.field input::placeholder,.field textarea::placeholder{color:#b8c9a0}
.field-meta{display:flex;justify-content:flex-end;font-size:11.5px;color:var(--muted);margin-bottom:18px}
.field-meta.warn{color:#dc2626}
.divider{border:none;border-top:1px solid var(--border-soft);margin:20px 0}
/* SUBMIT BTN */
.btn-send{display:flex;align-items:center;justify-content:center;gap:9px;width:100%;padding:13px;background:var(--accent);color:var(--primary);border:none;border-radius:10px;font-family:'Plus Jakarta Sans',sans-serif;font-size:14px;font-weight:700;cursor:pointer;transition:background .15s,transform .15s,box-shadow .15s}
.btn-send:hover{background:var(--accent-dark);transform:translateY(-1px);box-shadow:0 6px 20px rgba(166,206,57,.4)}
.btn-send:disabled{opacity:.6;cursor:not-allowed;transform:none;box-shadow:none}
.btn-send svg{width:16px;height:16px;fill:none;stroke:currentColor;stroke-width:2.5;stroke-linecap:round;stroke-linejoin:round}
/* LOG CARD */
.log-card{background:var(--surface);border:1.5px solid var(--border);border-radius:18px;overflow:hidden;box-shadow:var(--shadow-sm)}
.log-head{display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid var(--border-soft);background:var(--surface2);flex-wrap:wrap;gap:8px}
.log-head h3{font-size:14.5px;font-weight:700;color:var(--text);display:flex;align-items:center;gap:8px}
.log-head h3 svg{width:15px;height:15px;fill:none;stroke:var(--accent-dark);stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.log-pill{font-size:12px;font-weight:600;background:var(--accent-dim);border:1px solid var(--border);padding:4px 12px;border-radius:100px;color:var(--accent-dark)}
.log-list{display:flex;flex-direction:column}
.log-item{display:flex;align-items:flex-start;gap:14px;padding:16px 20px;border-bottom:1px solid var(--border-soft);transition:background .15s}
.log-item:last-child{border-bottom:none}
.log-item:hover{background:var(--surface2)}
.log-dot{width:38px;height:38px;border-radius:10px;background:var(--accent-dim);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.log-dot svg{width:16px;height:16px;fill:none;stroke:var(--accent-dark);stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.log-body{flex:1;min-width:0}
.log-title{font-size:13.5px;font-weight:700;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.log-msg{font-size:12.5px;color:var(--muted);margin-top:2px;line-height:1.45;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.log-meta{display:flex;align-items:center;gap:10px;margin-top:6px;flex-wrap:wrap}
.log-time{font-size:11px;color:#9ca3af;display:flex;align-items:center;gap:4px}
.log-time svg{width:11px;height:11px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.log-id{font-family:'Space Mono',monospace;font-size:10.5px;font-weight:700;color:var(--accent-dark);background:var(--accent-dim);border:1px solid var(--border);padding:2px 7px;border-radius:6px}
.log-empty{padding:44px 20px;text-align:center}
.log-empty svg{width:40px;height:40px;fill:none;stroke:#d1d5db;stroke-width:1.5;stroke-linecap:round;stroke-linejoin:round;display:block;margin:0 auto 10px}
.log-empty p{color:var(--muted);font-size:13.5px}
/* OVERLAY + RESPONSIVE */
.sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:99}
.sidebar-overlay.show{display:block}
@keyframes spin{to{transform:rotate(360deg)}}
@media(max-width:900px){
  .sidebar{transform:translateX(-100%);transition:transform .28s}
  .sidebar.open{transform:translateX(0)}
  .main{margin-left:0}
  .hamburger{display:flex!important}
  .content{padding:16px}
  .topbar{padding:12px 16px}
}
</style>
</head>
<body>
<!-- SIDEBAR -->
<div class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <img src="logo.jpg" alt="Grand Superior Drycleaners">
  </div>
  <nav class="sidebar-nav">
    <div class="nav-label">Overview</div>
    <a href="owner_dashboard.php" class="nav-link">
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
      <svg viewBox="0 0 24 24"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1"/><line x1="9" y1="12" x2="15" y2="12"/></svg>
      Bookings
    </a>
    <a href="view_payments.php" class="nav-link">
      <svg viewBox="0 0 24 24"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
      Payments
    </a>
    <div class="nav-label">System</div>
    <a href="notify.php" class="nav-link active">
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
      <div class="owner-av"><?= $initials ?></div>
      <div style="flex:1;min-width:0">
        <div class="owner-mini-name"><?= htmlspecialchars($owner['name']) ?></div>
        <div class="owner-mini-role">Business Owner</div>
      </div>
    </div>
  </div>
</div>
<div class="sidebar-overlay" id="overlay"></div>
<!-- MAIN -->
<div class="main">
  <div class="topbar">
    <div style="display:flex;align-items:center;gap:12px">
      <button class="hamburger" id="hamburger" aria-label="Toggle menu">
        <svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
      </button>
      <div class="topbar-left">
        <h2>Send Notification</h2>
        <p>Broadcast a message to all riders and clients</p>
      </div>
    </div>
    <div class="topbar-badge">
      <svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
      <?= number_format($total) ?> sent total
    </div>
  </div>
  <div class="content">
    <!-- PAGE HERO -->
    <div class="page-hero">
      <div class="ph-icon">
        <svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
      </div>
      <div class="ph-text">
        <h2>Owner Notifications</h2>
        <p>Messages are saved to the <code style="background:rgba(255,255,255,.1);padding:1px 6px;border-radius:5px;font-size:12px">notifications</code> table and visible to all riders &amp; clients</p>
      </div>
      <div class="ph-count">
        <div class="num"><?= number_format($total) ?></div>
        <div class="lbl">total sent</div>
      </div>
    </div>
    <!-- ALERT -->
    <?php if (!empty($msg)): ?>
    <div class="alert alert-<?= $msg_type ?>">
      <?php if ($msg_type === 'success'): ?>
        <svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
      <?php else: ?>
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      <?php endif; ?>
      <?= htmlspecialchars($msg) ?>
    </div>
    <?php endif; ?>
    <!-- COMPOSE FORM -->
    <div class="form-card">
      <div class="form-card-head">
        <div class="fch-icon">
          <svg viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
        </div>
        <div>
          <div class="fch-title">Compose Notification</div>
          <div class="fch-sub">Saved instantly to the notifications table on send</div>
        </div>
      </div>
      <div class="form-body">
        <div class="tip">
          <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="12" x2="12" y2="16"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
          This notification will be stored in the <strong>notifications</strong> table and will appear for all <strong>riders</strong> and <strong>clients</strong> on their dashboards.
        </div>
        <form method="POST" id="notifyForm">
          <!-- Hidden flag: PHP checks this, NOT the button.
               Disabling the button cosmetically never blocks the POST value. -->
          <input type="hidden" name="send" value="1">
          <div class="field">
            <label for="title">Notification Title *</label>
            <input
              type="text" id="title" name="title"
              placeholder="e.g. Holiday Hours, Price Update, Service Alert..."
              maxlength="100"
              oninput="charCount('title','tc',100)"
              value="<?= htmlspecialchars($title ?? '') ?>"
              autocomplete="off" required>
          </div>
          <div class="field-meta" id="tc-wrap"><span id="tc">0</span> / 100 characters</div>
          <div class="field">
            <label for="message">Message Body *</label>
            <textarea
              id="message" name="message"
              placeholder="Write your message here. Keep it clear and concise..."
              maxlength="600"
              oninput="charCount('message','mc',600)"
              required><?= htmlspecialchars($message ?? '') ?></textarea>
          </div>
          <div class="field-meta" id="mc-wrap"><span id="mc">0</span> / 600 characters</div>
          <hr class="divider">
          <button type="submit" class="btn-send" id="sendBtn">
            <svg viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
            Send &amp; Save Notification
          </button>
        </form>
      </div>
    </div>
    <!-- RECENT LOG -->
    <div class="log-card">
      <div class="log-head">
        <h3>
          <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
          Sent Notifications Log
        </h3>
        <span class="log-pill">Last <?= count($recent_rows) ?> entries</span>
      </div>
      <?php if (count($recent_rows) > 0): ?>
      <div class="log-list">
        <?php foreach ($recent_rows as $row): ?>
        <div class="log-item">
          <div class="log-dot">
            <svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
          </div>
          <div class="log-body">
            <div class="log-title"><?= htmlspecialchars($row['title']) ?></div>
            <div class="log-msg"><?= htmlspecialchars($row['message']) ?></div>
            <div class="log-meta">
              <span class="log-time">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                <?= date('M j, Y · g:i A', strtotime($row['created_at'])) ?>
              </span>
              <span class="log-id">#<?= str_pad($row['id'],3,'0',STR_PAD_LEFT) ?></span>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <div class="log-empty">
        <svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        <p>No notifications sent yet. Use the form above to send the first one.</p>
      </div>
      <?php endif; ?>
    </div>
  </div><!-- /content -->
</div><!-- /main -->
<script>
function charCount(inputId, countId, max) {
    const len  = document.getElementById(inputId).value.length;
    const el   = document.getElementById(countId);
    const wrap = document.getElementById(countId + '-wrap');
    el.textContent = len;
    wrap.classList.toggle('warn', len >= Math.floor(max * 0.9));
}
/* Init counts on page load */
charCount('title',   'tc', 100);
charCount('message', 'mc', 600);
document.getElementById('notifyForm').addEventListener('submit', function() {
    /* Cosmetic only — hidden input already carries send=1 in the POST */
    const btn = document.getElementById('sendBtn');
    btn.setAttribute('disabled', 'disabled');
    btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16" style="animation:spin 1s linear infinite"><path d="M21 12a9 9 0 1 1-6.22-8.56"/></svg> Sending...';
});
/* Sidebar toggle */
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
