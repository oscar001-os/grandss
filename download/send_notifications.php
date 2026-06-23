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
/* Fetch owner name */
$stmt = $conn->prepare("SELECT name FROM owners WHERE id = ?");
$stmt->bind_param("i", $owner_id);
$stmt->execute();
$owner = $stmt->get_result()->fetch_assoc();
$stmt->close();
function initials($name) {
    $name  = trim($name);
    if ($name === '') return '?';
    $parts = preg_split('/\s+/', $name);
    $i     = strtoupper(substr($parts[0], 0, 1));
    if (count($parts) > 1) $i .= strtoupper(substr($parts[count($parts)-1], 0, 1));
    return $i;
}

/* ── HANDLE SEND ── */
if (isset($_POST['send'])) {
    $title   = trim($_POST['title']   ?? '');
    $message = trim($_POST['message'] ?? '');
    if ($title === '' || $message === '') {
        $msg      = "Both title and message are required.";
        $msg_type = "error";
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO table notifications (title, message, owner_id, created_at) VALUES (?, ?, ?, NOW())"
        );
        if (!$stmt) {
            $msg      = "Prepare error: " . $conn->error;
            $msg_type = "error";
        } else {
            $stmt->bind_param("ssi", $title, $message, $owner_id);
            if ($stmt->execute()) {
                $msg      = "Notification sent and saved successfully.";
                $msg_type = "success";
            } else {
                $msg      = "Insert failed: " . $stmt->error;
                $msg_type = "error";
            }
            $stmt->close();
        }
    }
}
/* ── FETCH RECENT NOTIFICATIONS ── */
$recent = $conn->query("
    SELECT n.*, o.name AS owner_name
    FROM notifications n
    LEFT JOIN owners o ON n.owner_id = o.id
    ORDER BY n.created_at DESC
    LIMIT 10
");
$recent_rows = $recent ? $recent->fetch_all(MYSQLI_ASSOC) : [];
$total_notifs = mysqli_fetch_assoc($conn->query("SELECT COUNT(*) AS c FROM notifications"))['c'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Send Notification · Grand Superior</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
:root{
  --ink:#0d1b2a; --lime:#a6ce39; --lime-dark:#8ab530;
  --lime-light:#f2f9e4; --paper:#f4f7f2; --surface:#fff;
  --surface2:#f8fbf4; --line:#ddeec8; --line-soft:#eaf3da;
  --muted:#6b7e5a; --text:#1a2a14; --text-dim:#4a5e38;
  --shadow-sm:0 1px 4px rgba(0,0,0,0.07);
  --shadow:0 4px 18px rgba(0,0,0,0.08);
}
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--paper);color:var(--text);min-height:100vh}
a{text-decoration:none;color:inherit}
/* NAVBAR */
.navbar{background:var(--ink);padding:0 28px;display:flex;align-items:center;justify-content:space-between;height:64px;position:sticky;top:0;z-index:100;box-shadow:0 2px 14px rgba(0,0,0,0.2)}
.navbar-logo{display:flex;align-items:center;gap:11px}
.navbar-logo img{width:38px;height:38px;border-radius:9px;object-fit:cover;border:2px solid var(--lime);padding:2px;background:#fff}
.navbar-logo span{font-size:16px;font-weight:700;color:#fff}
.navbar-right{display:flex;align-items:center;gap:10px}
.nav-avatar{width:34px;height:34px;border-radius:50%;background:var(--lime);color:var(--ink);font-weight:800;font-size:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.nav-name{font-size:13px;font-weight:500;color:rgba(255,255,255,.8)}
/* PAGE */
.page{max-width:720px;margin:0 auto;padding:36px 20px 72px}
.back-link{display:inline-flex;align-items:center;gap:7px;color:var(--muted);font-size:13px;font-weight:500;margin-bottom:26px;transition:color .15s}
.back-link:hover{color:var(--text)}
.back-link svg{width:14px;height:14px;fill:none;stroke:currentColor;stroke-width:2.5;stroke-linecap:round;stroke-linejoin:round}
/* PAGE HEADER */
.page-header{background:linear-gradient(135deg,var(--ink) 0%,#1a3a50 100%);border-radius:18px;padding:26px 28px;display:flex;align-items:center;gap:18px;margin-bottom:28px;position:relative;overflow:hidden}
.page-header::before{content:'';position:absolute;top:-40px;right:-40px;width:180px;height:180px;border-radius:50%;background:rgba(166,206,57,.07)}
.ph-icon{width:52px;height:52px;border-radius:14px;background:rgba(166,206,57,.15);border:1px solid rgba(166,206,57,.3);display:flex;align-items:center;justify-content:center;flex-shrink:0;position:relative;z-index:1}
.ph-icon svg{width:24px;height:24px;fill:none;stroke:var(--lime);stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.ph-text{position:relative;z-index:1;flex:1}
.ph-text h1{font-size:20px;font-weight:800;color:#fff}
.ph-text p{font-size:13px;color:rgba(255,255,255,.5);margin-top:3px}
.ph-stat{position:relative;z-index:1;text-align:right}
.ph-stat .num{font-family:'Space Mono',monospace;font-size:28px;font-weight:700;color:var(--lime);line-height:1}
.ph-stat .lbl{font-size:11px;color:rgba(255,255,255,.4);margin-top:2px}
/* ALERT */
.alert{display:flex;align-items:flex-start;gap:11px;padding:14px 16px;border-radius:12px;font-size:13.5px;font-weight:500;margin-bottom:22px;border:1px solid transparent;animation:slideIn .25s ease}
@keyframes slideIn{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:translateY(0)}}
.alert svg{width:16px;height:16px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;flex-shrink:0;margin-top:1px}
.alert-success{background:#edfaf4;border-color:#a7f3d0;color:#065f46}
.alert-error  {background:#fff1f1;border-color:#fca5a5;color:#991b1b}
/* CARD */
.card{background:var(--surface);border:1.5px solid var(--line);border-radius:18px;overflow:hidden;box-shadow:var(--shadow-sm);margin-bottom:22px}
.card-header{padding:18px 22px;border-bottom:1px solid var(--line-soft);background:var(--surface2);display:flex;align-items:center;gap:12px}
.card-header-icon{width:38px;height:38px;border-radius:10px;background:var(--lime-light);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.card-header-icon svg{width:18px;height:18px;fill:none;stroke:var(--lime-dark);stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.card-header-text h2{font-size:15px;font-weight:700;color:var(--text)}
.card-header-text p{font-size:12px;color:var(--muted);margin-top:1px}
.card-body{padding:26px 24px}
/* TIP */
.tip-box{display:flex;align-items:flex-start;gap:10px;background:var(--lime-light);border:1px solid #d4eaaa;border-radius:10px;padding:12px 14px;font-size:12.5px;color:#4a6b1a;margin-bottom:22px;line-height:1.6}
.tip-box svg{width:15px;height:15px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;flex-shrink:0;margin-top:1px}
/* FORM */
.field{display:flex;flex-direction:column;gap:6px;margin-bottom:6px}
.field label{font-size:11.5px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px}
.field input,.field textarea{padding:11px 14px;border:1.5px solid var(--line);border-radius:10px;font-family:'Plus Jakarta Sans',sans-serif;font-size:13.5px;color:var(--text);background:var(--paper);outline:none;width:100%;transition:border-color .15s,box-shadow .15s,background .15s}
.field textarea{resize:vertical;min-height:140px;line-height:1.65}
.field input:focus,.field textarea:focus{border-color:var(--lime-dark);box-shadow:0 0 0 4px rgba(166,206,57,.18);background:#fff}
.field input::placeholder,.field textarea::placeholder{color:#b0bac8}
.field-meta{display:flex;justify-content:flex-end;font-size:11.5px;color:var(--muted);margin-bottom:16px}
.field-meta.near-limit{color:#e53e3e}
.form-divider{border:none;border-top:1px solid var(--line);margin:20px 0}
.btn-submit{display:flex;align-items:center;justify-content:center;gap:9px;width:100%;padding:13px 28px;background:var(--lime);color:var(--ink);border:none;border-radius:10px;font-family:'Plus Jakarta Sans',sans-serif;font-size:14px;font-weight:700;cursor:pointer;transition:background .15s,transform .15s,box-shadow .15s}
.btn-submit:hover{background:var(--lime-dark);transform:translateY(-1px);box-shadow:0 6px 20px rgba(166,206,57,.4)}
.btn-submit:disabled{opacity:.65;cursor:not-allowed;transform:none;box-shadow:none}
.btn-submit svg{width:16px;height:16px;fill:none;stroke:currentColor;stroke-width:2.5;stroke-linecap:round;stroke-linejoin:round}
/* RECENT LOG TABLE */
.log-table-wrap{overflow-x:auto}
table{width:100%;border-collapse:collapse;font-size:13px}
thead th{padding:10px 16px;text-align:left;font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--muted);background:var(--surface2);border-bottom:1px solid var(--line-soft);white-space:nowrap}
thead th:first-child{padding-left:20px}
tbody tr{border-bottom:1px solid var(--line-soft);transition:background .15s}
tbody tr:last-child{border-bottom:none}
tbody tr:hover{background:var(--surface2)}
td{padding:13px 16px;color:var(--text-dim);vertical-align:top}
td:first-child{padding-left:20px}
.td-id{font-family:'Space Mono',monospace;font-size:11.5px;color:var(--lime-dark);font-weight:700;white-space:nowrap}
.td-title{font-weight:600;color:var(--text);max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.td-msg{color:var(--muted);font-size:12.5px;max-width:260px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.td-time{font-size:12px;color:var(--muted);white-space:nowrap}
.td-owner{font-size:12px;font-weight:600;color:var(--lime-dark)}
.empty-log{padding:40px;text-align:center;color:var(--muted);font-size:14px}
@keyframes spin{to{transform:rotate(360deg)}}
@media(max-width:600px){.navbar{padding:0 16px}.nav-name{display:none}.page{padding:22px 14px 52px}.card-body{padding:20px 16px}.card-header{padding:16px}}
</style>
</head>
<body>
<!-- NAVBAR -->
<nav class="navbar">
    <a href="owner_dashboard.php" class="navbar-logo">
        <img src="logo.jpg" alt="Grand Superior">
        <span>Grand Superior</span>
    </a>
    <div class="navbar-right">
        <div class="nav-avatar"><?= htmlspecialchars(initials($owner['name'])) ?></div>
        <span class="nav-name"><?= htmlspecialchars($owner['name']) ?></span>
    </div>
</nav>
<div class="page">
    <a href="owner_dashboard.php" class="back-link">
        <svg viewBox="0 0 24 24"><path d="M19 12H5M11 18l-6-6 6-6"/></svg>
        Back to dashboard
    </a>
    <!-- PAGE HEADER -->
    <div class="page-header">
        <div class="ph-icon">
            <svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        </div>
        <div class="ph-text">
            <h1>Send Notification</h1>
            <p>Broadcast a message to all riders and clients</p>
        </div>
        <div class="ph-stat">
            <div class="num"><?= number_format($total_notifs) ?></div>
            <div class="lbl">sent so far</div>
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
    <!-- COMPOSE CARD -->
    <div class="card">
        <div class="card-header">
            <div class="card-header-icon">
                <svg viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
            </div>
            <div class="card-header-text">
                <h2>New Notification</h2>
                <p>Saved to the notifications table and visible to all riders &amp; clients</p>
            </div>
        </div>
        <div class="card-body">
            <div class="tip-box">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="12" x2="12" y2="16"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                This message will be stored in the <strong>notifications</strong> table and will be visible to all riders on their dashboard and all clients on their notifications page.
            </div>
            <form method="POST" id="notifForm">
                <div class="field">
                    <label for="title">Notification Title</label>
                    <input
                        type="text" id="title" name="title"
                        placeholder="e.g. Service Update, Price Change, Holiday Hours..."
                        maxlength="100"
                        oninput="count('title','tc',100)"
                        autocomplete="off" required>
                </div>
                <div class="field-meta" id="tc-wrap"><span id="tc">0</span> / 100</div>
                <div class="field">
                    <label for="message">Message Body</label>
                    <textarea
                        id="message" name="message"
                        placeholder="Write your message here..."
                        maxlength="600"
                        oninput="count('message','mc',600)"
                        required></textarea>
                </div>
                <div class="field-meta" id="mc-wrap"><span id="mc">0</span> / 600</div>
                <hr class="form-divider">
                <button type="submit" name="send" class="btn-submit" id="sendBtn">
                    <svg viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                    Send &amp; Save Notification
                </button>
            </form>
        </div>
    </div>
    <!-- RECENT LOG CARD -->
    <div class="card">
        <div class="card-header">
            <div class="card-header-icon">
                <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            </div>
            <div class="card-header-text">
                <h2>Notifications Log</h2>
                <p>Last <?= count($recent_rows) ?> entries from the <code>notifications</code> table</p>
            </div>
        </div>
        <?php if (count($recent_rows) > 0): ?>
        <div class="log-table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#ID</th>
                        <th>Title</th>
                        <th>Message</th>
                        <th>Sent By</th>
                        <th>Date &amp; Time</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($recent_rows as $row): ?>
                <tr>
                    <td class="td-id">#<?= str_pad($row['id'], 3, '0', STR_PAD_LEFT) ?></td>
                    <td class="td-title" title="<?= htmlspecialchars($row['title']) ?>"><?= htmlspecialchars($row['title']) ?></td>
                    <td class="td-msg"   title="<?= htmlspecialchars($row['message']) ?>"><?= htmlspecialchars($row['message']) ?></td>
                    <td class="td-owner"><?= htmlspecialchars($row['owner_name'] ?? 'Owner') ?></td>
                    <td class="td-time"><?= date('M j, Y · g:i A', strtotime($row['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-log">No notifications sent yet. Use the form above to send the first one.</div>
        <?php endif; ?>
    </div>
</div>
<script>
function count(inputId, countId, max) {
    var len  = document.getElementById(inputId).value.length;
    var el   = document.getElementById(countId);
    var wrap = document.getElementById(countId + '-wrap');
    el.textContent = len;
    wrap.classList.toggle('near-limit', len >= Math.floor(max * 0.9));
}
document.getElementById('notifForm').addEventListener('submit', function() {
    var btn = document.getElementById('sendBtn');
    btn.disabled = true;
    btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16" style="animation:spin 1s linear infinite"><path d="M21 12a9 9 0 1 1-6.22-8.56"/></svg> Sending...';
});
</script>
</body>
</html>