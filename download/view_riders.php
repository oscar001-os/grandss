<?php
session_start();
include 'connection.php';
if (!isset($_SESSION['owner_id'])) {
    header("Location: owner_login.php");
    exit();
}
$owner_id = $_SESSION['owner_id'];
/* ── OWNER NAME ── */
$stmt = $conn->prepare("SELECT name FROM owners WHERE id = ?");
$stmt->bind_param("i", $owner_id);
$stmt->execute();
$owner = $stmt->get_result()->fetch_assoc();
$stmt->close();
function ownerInitials($name) {
    $parts = preg_split('/\s+/', trim($name));
    $i = strtoupper(substr($parts[0], 0, 1));
    if (count($parts) > 1) $i .= strtoupper(substr($parts[count($parts)-1], 0, 1));
    return $i;
}
/* ── ADD RIDER ── */
if (isset($_POST['add_rider'])) {
    $name        = trim($_POST['name']);
    $phone       = trim($_POST['phone']);
    $email       = trim($_POST['email']);
    $national_id = trim($_POST['national_id']);
    $address     = trim($_POST['address']);
    $vehicle     = trim($_POST['vehicle']);
    $photoName   = "";
    if (!empty($_FILES['photo']['name'])) {
        $allowed = ['jpg','jpeg','png','webp'];
        $ext     = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed) && $_FILES['photo']['size'] <= 2*1024*1024) {
            $targetDir = "uploads/riders/";
            if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
            $photoName = time() . "_" . basename($_FILES['photo']['name']);
            move_uploaded_file($_FILES['photo']['tmp_name'], $targetDir . $photoName);
        } else {
            $_SESSION['error'] = "Photo must be JPG/PNG/WebP and under 2 MB.";
        }
    }
    if (!empty($name) && !empty($phone) && empty($_SESSION['error'])) {
        $stmt = $conn->prepare("
            INSERT INTO riders (name, phone, email, national_id, address, vehicle, photo, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'Available')
        ");
        $stmt->bind_param("sssssss", $name, $phone, $email, $national_id, $address, $vehicle, $photoName);
        if ($stmt->execute()) {
            $_SESSION['success'] = "Rider <strong>" . htmlspecialchars($name) . "</strong> added successfully.";
        } else {
            $_SESSION['error'] = "Failed to add rider: " . $stmt->error;
        }
        $stmt->close();
    } elseif (empty($name) || empty($phone)) {
        $_SESSION['error'] = "Name and phone are required.";
    }
    header("Location: view_riders.php");
    exit();
}
/* ── DELETE RIDER ── */
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM riders WHERE id = ?");
    $stmt->bind_param("i", $id);
    $_SESSION[$stmt->execute() ? 'success' : 'error'] = $stmt->execute()
        ? "Rider deleted successfully."
        : "Failed to delete rider.";
    /* clean delete */
    $stmt = $conn->prepare("DELETE FROM riders WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $_SESSION['success'] = "Rider deleted successfully.";
    } else {
        $_SESSION['error']   = "Failed to delete rider.";
    }
    $stmt->close();
    header("Location: view_riders.php");
    exit();
}
/* ── SEARCH & FETCH ── */
$search = trim($_GET['search'] ?? '');
if ($search !== '') {
    $stmt = $conn->prepare("
        SELECT * FROM riders
        WHERE name LIKE ? OR phone LIKE ? OR email LIKE ? OR vehicle LIKE ?
        ORDER BY id DESC
    ");
    $like = "%$search%";
    $stmt->bind_param("ssss", $like, $like, $like, $like);
    $stmt->execute();
    $riders = $stmt->get_result();
} else {
    $riders = $conn->query("SELECT * FROM riders ORDER BY id DESC");
}
$total_riders    = $conn->query("SELECT COUNT(*) AS c FROM riders")->fetch_assoc()['c'];
$available_count = $conn->query("SELECT COUNT(*) AS c FROM riders WHERE status='Available'")->fetch_assoc()['c'];
$busy_count      = $conn->query("SELECT COUNT(*) AS c FROM riders WHERE status!='Available'")->fetch_assoc()['c'];
function statusBadge($status) {
    $s = strtolower(trim($status));
    $map = [
        'available' => ['#f0fdf4','#15803d','#bbf7d0','#22c55e'],
        'busy'      => ['#fffbeb','#b45309','#fde68a','#f59e0b'],
        'on trip'   => ['#f5f3ff','#5b21b6','#ddd6fe','#7c3aed'],
        'offline'   => ['#f9fafb','#6b7280','#e5e7eb','#9ca3af'],
    ];
    [$bg,$color,$border,$dot] = $map[$s] ?? ['#f9fafb','#6b7280','#e5e7eb','#9ca3af'];
    return "<span style='display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:100px;font-size:11px;font-weight:600;background:$bg;color:$color;border:1px solid $border'>
        <span style='width:5px;height:5px;border-radius:50%;background:$dot;flex-shrink:0'></span>
        " . htmlspecialchars($status) . "
    </span>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Riders — Grand Superior</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
:root{
  --bg:#f4f7f2;
  --surface:#ffffff;
  --surface2:#f8fbf4;
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
  --shadow:0 4px 18px rgba(0,0,0,0.09);
}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);color:var(--text);min-height:100vh}
/* ── NAVBAR ── */
.navbar{
  background:var(--primary);height:62px;padding:0 28px;
  display:flex;align-items:center;justify-content:space-between;
  position:sticky;top:0;z-index:100;box-shadow:0 2px 12px rgba(0,0,0,0.18);
}
.navbar-logo{display:flex;align-items:center;gap:10px;text-decoration:none}
.navbar-logo img{width:34px;height:34px;border-radius:8px;object-fit:cover;border:2px solid var(--accent);padding:2px;background:#fff}
.navbar-logo span{font-size:15px;font-weight:800;color:#fff}
.nav-right{display:flex;align-items:center;gap:10px}
.nav-avatar{width:34px;height:34px;border-radius:50%;background:var(--accent);color:var(--primary);font-size:12px;font-weight:800;display:flex;align-items:center;justify-content:center}
.nav-name{font-size:13px;font-weight:500;color:rgba(255,255,255,.8)}
.nav-back{display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:8px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);color:rgba(255,255,255,.8);text-decoration:none;font-size:12.5px;font-weight:600;transition:all .2s}
.nav-back:hover{background:rgba(255,255,255,.15);color:#fff}
.nav-back svg{width:13px;height:13px;fill:none;stroke:currentColor;stroke-width:2.5;stroke-linecap:round;stroke-linejoin:round}
/* ── PAGE ── */
.page{max-width:1280px;margin:0 auto;padding:28px 24px 60px}
/* ── PAGE HEADER ── */
.page-header{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:24px}
.page-header-left h1{font-size:21px;font-weight:800;color:var(--text);letter-spacing:-.3px}
.page-header-left p{font-size:13px;color:var(--muted);margin-top:3px}
.btn-add-rider{display:inline-flex;align-items:center;gap:7px;padding:10px 20px;border-radius:10px;background:var(--accent);color:var(--primary);font-size:13.5px;font-weight:700;border:none;cursor:pointer;font-family:inherit;transition:all .2s}
.btn-add-rider:hover{background:var(--accent-dark)}
.btn-add-rider svg{width:15px;height:15px;fill:none;stroke:currentColor;stroke-width:2.5;stroke-linecap:round;stroke-linejoin:round}
/* ── ALERTS ── */
.alert{display:flex;align-items:flex-start;gap:10px;padding:13px 16px;border-radius:12px;font-size:13.5px;font-weight:500;margin-bottom:18px;border:1px solid transparent;animation:slideIn .25s ease}
@keyframes slideIn{from{opacity:0;transform:translateY(-5px)}to{opacity:1;transform:translateY(0)}}
.alert svg{width:16px;height:16px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;flex-shrink:0;margin-top:1px}
.alert-success{background:#f0fdf4;border-color:#bbf7d0;color:#15803d}
.alert-error  {background:#fef2f2;border-color:#fecaca;color:#b91c1c}
/* ── STATS ── */
.stats-row{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:22px}
.stat-mini{background:var(--surface);border:1.5px solid var(--border);border-radius:14px;padding:16px 18px;display:flex;align-items:center;gap:14px;box-shadow:var(--shadow-sm)}
.stat-mini-icon{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.stat-mini-icon svg{width:17px;height:17px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.stat-mini:nth-child(1) .stat-mini-icon{background:#f0f9e4;color:var(--accent-dark)}
.stat-mini:nth-child(2) .stat-mini-icon{background:#f0fdf4;color:#16a34a}
.stat-mini:nth-child(3) .stat-mini-icon{background:#fffbeb;color:#b45309}
.stat-mini-num{font-family:'Space Mono',monospace;font-size:22px;font-weight:700;color:var(--text);line-height:1}
.stat-mini-label{font-size:11.5px;color:var(--muted);margin-top:2px;font-weight:500}
/* ── ADD FORM PANEL ── */
.panel{background:var(--surface);border:1.5px solid var(--border);border-radius:18px;overflow:hidden;box-shadow:var(--shadow-sm);margin-bottom:22px}
.panel-header{padding:16px 22px;border-bottom:1px solid var(--border-soft);background:var(--surface2);display:flex;align-items:center;justify-content:space-between;cursor:pointer;user-select:none}
.panel-header-left{display:flex;align-items:center;gap:10px}
.panel-header-left svg{width:17px;height:17px;fill:none;stroke:var(--accent-dark);stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.panel-header-left h2{font-size:14.5px;font-weight:700;color:var(--text)}
.panel-toggle svg{width:16px;height:16px;fill:none;stroke:var(--muted);stroke-width:2;stroke-linecap:round;stroke-linejoin:round;transition:transform .25s}
.panel-toggle.open svg{transform:rotate(180deg)}
.panel-body{padding:22px;display:none}
.panel-body.show{display:block}
/* FORM GRID */
.form-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}
@media(max-width:900px){.form-grid{grid-template-columns:1fr 1fr}}
@media(max-width:600px){.form-grid{grid-template-columns:1fr}}
.form-grid .full{grid-column:1/-1}
.field-label{font-size:11.5px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px;display:block}
.input-wrap{position:relative}
.input-wrap svg.fi{position:absolute;left:11px;top:50%;transform:translateY(-50%);width:14px;height:14px;fill:none;stroke:#9ca3af;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;pointer-events:none}
.input-wrap input,.input-wrap select{
  width:100%;padding:10px 12px 10px 34px;
  border:1.5px solid #e5e7eb;border-radius:9px;
  font-size:13.5px;font-family:inherit;color:var(--text);
  background:#fafafa;outline:none;
  transition:border-color .2s,box-shadow .2s;
}
.input-wrap input:focus,.input-wrap select:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(166,206,57,.15);background:#fff}
.file-drop{border:1.5px dashed #d1d5db;border-radius:9px;padding:12px;text-align:center;background:#fafafa;cursor:pointer;transition:all .2s}
.file-drop:hover{border-color:var(--accent);background:var(--accent-dim)}
.file-drop p{font-size:12px;color:var(--muted);font-weight:500}
.file-drop span{font-size:11px;color:#9ca3af}
.btn-submit-form{display:inline-flex;align-items:center;gap:7px;padding:10px 24px;border-radius:9px;background:var(--accent);color:var(--primary);font-size:13px;font-weight:700;border:none;cursor:pointer;font-family:inherit;transition:all .2s}
.btn-submit-form:hover{background:var(--accent-dark)}
.btn-submit-form svg{width:14px;height:14px;fill:none;stroke:currentColor;stroke-width:2.5;stroke-linecap:round;stroke-linejoin:round}
/* ── TABLE PANEL ── */
.table-panel{background:var(--surface);border:1.5px solid var(--border);border-radius:18px;overflow:hidden;box-shadow:var(--shadow-sm)}
.table-toolbar{padding:16px 22px;border-bottom:1px solid var(--border-soft);background:var(--surface2);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px}
.toolbar-left{display:flex;align-items:center;gap:10px}
.toolbar-left svg{width:17px;height:17px;fill:none;stroke:var(--accent-dark);stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.toolbar-left h2{font-size:14.5px;font-weight:700;color:var(--text)}
.search-wrap{position:relative}
.search-wrap svg{position:absolute;left:10px;top:50%;transform:translateY(-50%);width:14px;height:14px;fill:none;stroke:#9ca3af;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;pointer-events:none}
.search-wrap input{padding:8px 12px 8px 32px;border:1.5px solid #e5e7eb;border-radius:8px;font-size:13px;font-family:inherit;outline:none;background:#fafafa;width:230px;transition:border-color .2s}
.search-wrap input:focus{border-color:var(--accent);background:#fff}
.table-scroll{overflow-x:auto}
table{width:100%;border-collapse:collapse;font-size:13.5px}
thead th{padding:11px 14px;text-align:left;font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--muted);background:var(--surface2);border-bottom:1px solid var(--border-soft);white-space:nowrap}
thead th:first-child{padding-left:20px}
tbody tr{border-bottom:1px solid var(--border-soft);transition:background .12s}
tbody tr:last-child{border-bottom:none}
tbody tr:hover{background:var(--surface2)}
td{padding:12px 14px;color:var(--text-dim);vertical-align:middle}
td:first-child{padding-left:20px}
.id-cell{font-family:'Space Mono',monospace;font-size:11.5px;color:var(--accent-dark);font-weight:700}
.rider-cell{display:flex;align-items:center;gap:10px}
.rider-thumb{width:34px;height:34px;border-radius:8px;object-fit:cover;border:1.5px solid var(--border);flex-shrink:0}
.rider-avatar{width:34px;height:34px;border-radius:8px;background:var(--accent);display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;color:var(--primary);flex-shrink:0}
.rider-name{font-weight:600;color:var(--text);font-size:13.5px}
.rider-email{font-size:11.5px;color:var(--muted)}
.date-cell{font-size:12px;color:var(--muted);white-space:nowrap}
.action-btn{display:inline-flex;align-items:center;gap:5px;padding:6px 12px;border-radius:7px;font-size:12px;font-weight:600;text-decoration:none;transition:all .2s;white-space:nowrap}
.btn-edit{background:var(--accent-dim);color:var(--accent-dark);border:1px solid var(--border)}
.btn-edit:hover{background:var(--accent);color:var(--primary)}
.btn-delete{background:#fef2f2;color:#b91c1c;border:1px solid #fecaca}
.btn-delete:hover{background:#ef4444;color:#fff}
.action-btn svg{width:12px;height:12px;fill:none;stroke:currentColor;stroke-width:2.5;stroke-linecap:round;stroke-linejoin:round}
.actions-cell{display:flex;align-items:center;gap:6px;white-space:nowrap}
.empty-row td{text-align:center;padding:48px;color:var(--muted);font-size:14px}
@media(max-width:900px){.stats-row{grid-template-columns:1fr 1fr}}
@media(max-width:600px){.stats-row{grid-template-columns:1fr}.navbar{padding:0 14px}.nav-name{display:none}.page{padding:18px 12px 48px}}
</style>
</head>
<body>
<!-- NAVBAR -->
<nav class="navbar">
  <a href="owner_dashboard.php" class="navbar-logo">
    <img src="logo.jpg" alt="Grand Superior">
    <span>Grand Superior</span>
  </a>
  <div class="nav-right">
    <a href="owner_dashboard.php" class="nav-back">
      <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
      Dashboard
    </a>
    <div class="nav-avatar"><?= htmlspecialchars(ownerInitials($owner['name'])) ?></div>
    <span class="nav-name"><?= htmlspecialchars($owner['name']) ?></span>
  </div>
</nav>
<div class="page">
  <!-- PAGE HEADER -->
  <div class="page-header">
    <div class="page-header-left">
      <h1>Rider Management</h1>
      <p>Add, view and manage all delivery riders.</p>
    </div>
    <button class="btn-add-rider" onclick="togglePanel()">
      <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Add New Rider
    </button>
  </div>
  <!-- ALERTS -->
  <?php if (isset($_SESSION['success'])): ?>
  <div class="alert alert-success">
    <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
    <?= $_SESSION['success'] ?>
  </div>
  <?php unset($_SESSION['success']); endif; ?>
  <?php if (isset($_SESSION['error'])): ?>
  <div class="alert alert-error">
    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <?= $_SESSION['error'] ?>
  </div>
  <?php unset($_SESSION['error']); endif; ?>
  <!-- STATS -->
  <div class="stats-row">
    <div class="stat-mini">
      <div class="stat-mini-icon">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="2"/><path d="M12 2a10 10 0 0 1 7.74 16.33M12 22a10 10 0 0 1-7.74-16.33"/></svg>
      </div>
      <div>
        <div class="stat-mini-num"><?= number_format($total_riders) ?></div>
        <div class="stat-mini-label">Total Riders</div>
      </div>
    </div>
    <div class="stat-mini">
      <div class="stat-mini-icon">
        <svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
      </div>
      <div>
        <div class="stat-mini-num"><?= number_format($available_count) ?></div>
        <div class="stat-mini-label">Available</div>
      </div>
    </div>
    <div class="stat-mini">
      <div class="stat-mini-icon">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
      </div>
      <div>
        <div class="stat-mini-num"><?= number_format($busy_count) ?></div>
        <div class="stat-mini-label">On Duty / Busy</div>
      </div>
    </div>
  </div>
  <!-- ADD RIDER PANEL -->
  <div class="panel" id="addPanel">
    <div class="panel-header" onclick="togglePanel()">
      <div class="panel-header-left">
        <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="16" y1="11" x2="22" y2="11"/></svg>
        <h2>Add New Rider</h2>
      </div>
      <div class="panel-toggle" id="panelToggle">
        <svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
      </div>
    </div>
    <div class="panel-body" id="panelBody">
      <form method="POST" enctype="multipart/form-data">
        <div class="form-grid">
          <div>
            <label class="field-label">Full Name *</label>
            <div class="input-wrap">
              <svg class="fi" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
              <input type="text" name="name" placeholder="e.g. John Doe" required>
            </div>
          </div>
          <div>
            <label class="field-label">Phone Number *</label>
            <div class="input-wrap">
              <svg class="fi" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.6 3.37 2 2 0 0 1 3.59 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.87a16 16 0 0 0 6 6l.87-.87a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
              <input type="text" name="phone" placeholder="+254 700 000000" required>
            </div>
          </div>
          <div>
            <label class="field-label">Email Address</label>
            <div class="input-wrap">
              <svg class="fi" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
              <input type="email" name="email" placeholder="rider@example.com">
            </div>
          </div>
          <div>
            <label class="field-label">National ID</label>
            <div class="input-wrap">
              <svg class="fi" viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
              <input type="text" name="national_id" placeholder="ID number">
            </div>
          </div>
          <div>
            <label class="field-label">Vehicle / Reg. No.</label>
            <div class="input-wrap">
              <svg class="fi" viewBox="0 0 24 24"><rect x="1" y="3" width="15" height="13" rx="1"/><path d="M16 8h4l3 5v3h-7z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
              <input type="text" name="vehicle" placeholder="e.g. KBZ 123A">
            </div>
          </div>
          <div>
            <label class="field-label">Home Address</label>
            <div class="input-wrap">
              <svg class="fi" viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
              <input type="text" name="address" placeholder="e.g. Nairobi, Kenya">
            </div>
          </div>
          <div class="full">
            <label class="field-label">Profile Photo</label>
            <label class="file-drop" for="photoFile">
              <p>Click to upload photo (JPG / PNG / WebP — max 2 MB)</p>
              <span id="photoFileName">No file chosen</span>
            </label>
            <input type="file" id="photoFile" name="photo" accept="image/jpeg,image/png,image/webp" style="display:none" onchange="document.getElementById('photoFileName').textContent=this.files[0]?this.files[0].name:'No file chosen'">
          </div>
          <div class="full" style="display:flex;justify-content:flex-end;padding-top:4px">
            <button type="submit" name="add_rider" class="btn-submit-form">
              <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
              Add Rider
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>
  <!-- RIDERS TABLE -->
  <div class="table-panel">
    <div class="table-toolbar">
      <div class="toolbar-left">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="2"/><path d="M12 2a10 10 0 0 1 7.74 16.33M12 22a10 10 0 0 1-7.74-16.33"/></svg>
        <h2>All Riders <?php if ($search !== ''): ?><span style="font-size:12px;color:var(--muted);font-weight:500"> — results for "<?= htmlspecialchars($search) ?>"</span><?php endif; ?></h2>
      </div>
      <form method="GET">
        <div class="search-wrap">
          <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input type="text" name="search" placeholder="Search name, phone, email..." value="<?= htmlspecialchars($search) ?>">
        </div>
      </form>
    </div>
    <div class="table-scroll">
      <table>
        <thead>
          <tr>
            <th>#ID</th>
            <th>Rider</th>
            <th>Phone</th>
            <th>Vehicle</th>
            <th>National ID</th>
            <th>Address</th>
            <th>Status</th>
            <th>Joined</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php
        $count = 0;
        while ($row = $riders->fetch_assoc()):
            $count++;
            $parts = preg_split('/\s+/', trim($row['name']));
            $av = strtoupper(substr($parts[0],0,1) . (isset($parts[1]) ? substr($parts[1],0,1) : ''));
        ?>
          <tr>
            <td class="id-cell">#<?= str_pad($row['id'],3,'0',STR_PAD_LEFT) ?></td>
            <td>
              <div class="rider-cell">
                <?php if (!empty($row['photo'])): ?>
                  <img class="rider-thumb" src="uploads/riders/<?= htmlspecialchars($row['photo']) ?>" alt="">
                <?php else: ?>
                  <div class="rider-avatar"><?= $av ?></div>
                <?php endif; ?>
                <div>
                  <div class="rider-name"><?= htmlspecialchars($row['name']) ?></div>
                  <div class="rider-email"><?= htmlspecialchars($row['email'] ?? '—') ?></div>
                </div>
              </div>
            </td>
            <td><?= htmlspecialchars($row['phone']) ?></td>
            <td><?= htmlspecialchars($row['vehicle'] ?? '—') ?></td>
            <td><?= htmlspecialchars($row['national_id'] ?? '—') ?></td>
            <td><?= htmlspecialchars($row['address'] ?? '—') ?></td>
            <td><?= statusBadge($row['status'] ?? 'Available') ?></td>
            <td class="date-cell"><?= date('M j, Y', strtotime($row['created_at'])) ?></td>
            <td>
              <div class="actions-cell">
                <a class="action-btn btn-edit" href="edit_rider.php?id=<?= $row['id'] ?>">
                  <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                  Edit
                </a>
                <a class="action-btn btn-delete"
                   href="?delete=<?= $row['id'] ?>"
                   onclick="return confirm('Delete <?= htmlspecialchars(addslashes($row['name'])) ?>? This cannot be undone.')">
                  <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
                  Delete
                </a>
              </div>
            </td>
          </tr>
        <?php endwhile; ?>
        <?php if ($count === 0): ?>
          <tr class="empty-row"><td colspan="9">No riders found<?= $search ? ' for "'.htmlspecialchars($search).'"' : '' ?>.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<script>
function togglePanel() {
  const body   = document.getElementById('panelBody');
  const toggle = document.getElementById('panelToggle');
  body.classList.toggle('show');
  toggle.classList.toggle('open');
}
</script>
</body>
</html>