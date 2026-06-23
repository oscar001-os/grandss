<?php
session_start();
include("connection.php");
if (!isset($_SESSION['rider_id'])) {
    header("Location: rider_login.php");
    exit();
}
$rider_id = $_SESSION['rider_id'];
$msg      = "";
$msg_type = "";
/* GET RIDER */
$stmt = $conn->prepare("SELECT * FROM riders WHERE id = ?");
$stmt->bind_param("i", $rider_id);
$stmt->execute();
$rider = $stmt->get_result()->fetch_assoc();
/* INITIALS */
$parts    = explode(' ', trim($rider['name'] ?? 'R'));
$initials = strtoupper(substr($parts[0],0,1) . (isset($parts[1]) ? substr($parts[1],0,1) : ''));
/* UPDATE */
if (isset($_POST['update'])) {
    $name        = trim($_POST['name']);
    $phone       = trim($_POST['phone']);
    $email       = trim($_POST['email']);
    $vehicle     = trim($_POST['vehicle']);
    $address     = trim($_POST['address']);
    $national_id = trim($_POST['national_id']);
    $photo       = $rider['photo'];
    if (!empty($_FILES['photo']['name'])) {
        $allowed = ['jpg','jpeg','png','webp'];
        $ext     = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed) && $_FILES['photo']['size'] <= 2 * 1024 * 1024) {
            $targetDir = "uploads/riders/";
            if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
            $photo = time() . "_" . basename($_FILES['photo']['name']);
            move_uploaded_file($_FILES['photo']['tmp_name'], $targetDir . $photo);
        } else {
            $msg      = "Photo must be JPG/PNG/WebP and under 2 MB.";
            $msg_type = "error";
        }
    }
    if (empty($msg)) {
        $stmt = $conn->prepare("UPDATE riders SET name=?,phone=?,email=?,vehicle=?,address=?,national_id=?,photo=? WHERE id=?");
        $stmt->bind_param("sssssssi", $name, $phone, $email, $vehicle, $address, $national_id, $photo, $rider_id);
        if ($stmt->execute()) {
            $msg      = "Profile updated successfully.";
            $msg_type = "success";
        } else {
            $msg      = "Update failed. Please try again.";
            $msg_type = "error";
        }
        /* refresh */
        $stmt = $conn->prepare("SELECT * FROM riders WHERE id = ?");
        $stmt->bind_param("i", $rider_id);
        $stmt->execute();
        $rider = $stmt->get_result()->fetch_assoc();
        $parts    = explode(' ', trim($rider['name'] ?? 'R'));
        $initials = strtoupper(substr($parts[0],0,1) . (isset($parts[1]) ? substr($parts[1],0,1) : ''));
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Profile — Grand Superior</title>
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
  --sidebar-w:255px;
}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);color:var(--text);min-height:100vh}
/* ── PAGE WRAP ── */
.page{max-width:960px;margin:0 auto;padding:32px 20px 48px}
/* ── BACK LINK ── */
.back-link{
  display:inline-flex;align-items:center;gap:7px;
  font-size:13px;font-weight:600;color:var(--primary);text-decoration:none;
  background:var(--surface);border:1.5px solid var(--border);
  padding:8px 16px;border-radius:10px;
  box-shadow:var(--shadow-sm);transition:all .2s;margin-bottom:24px;
}
.back-link:hover{background:var(--accent-dim);border-color:var(--accent)}
.back-link svg{width:15px;height:15px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
/* ── PAGE HEADER ── */
.page-header{margin-bottom:24px}
.page-header h1{font-size:22px;font-weight:800;color:var(--text);letter-spacing:-.3px}
.page-header p{font-size:13px;color:var(--muted);margin-top:3px}
/* ── GRID ── */
.grid{display:grid;grid-template-columns:260px 1fr;gap:20px;align-items:start}
@media(max-width:720px){.grid{grid-template-columns:1fr}}
/* ── PROFILE CARD ── */
.profile-card{
  background:var(--surface);border:1.5px solid var(--border);
  border-radius:20px;box-shadow:var(--shadow-sm);overflow:hidden;
}
.profile-card-top{
  background:linear-gradient(135deg,var(--primary) 0%,#1a3a50 100%);
  padding:28px 20px 20px;text-align:center;position:relative;overflow:hidden;
}
.profile-card-top::before{
  content:'';position:absolute;top:-30px;right:-30px;
  width:120px;height:120px;border-radius:50%;background:rgba(166,206,57,.1);
}
.avatar-wrap{position:relative;display:inline-block;margin-bottom:14px}
.avatar{
  width:88px;height:88px;border-radius:50%;object-fit:cover;
  border:3px solid var(--accent);display:block;
}
.avatar-initials{
  width:88px;height:88px;border-radius:50%;
  background:var(--accent);border:3px solid rgba(255,255,255,.15);
  display:flex;align-items:center;justify-content:center;
  font-size:26px;font-weight:800;color:var(--primary);
}
.avatar-upload-btn{
  position:absolute;bottom:2px;right:2px;
  width:26px;height:26px;border-radius:50%;
  background:var(--accent);border:2px solid #fff;
  display:flex;align-items:center;justify-content:center;cursor:pointer;
}
.avatar-upload-btn svg{width:12px;height:12px;fill:none;stroke:var(--primary);stroke-width:2.5;stroke-linecap:round;stroke-linejoin:round}
#photoInput{display:none}
.preview-name{font-size:15px;font-weight:800;color:#fff;position:relative;z-index:1}
.preview-role{
  display:inline-flex;align-items:center;gap:5px;
  background:rgba(166,206,57,.15);border:1px solid rgba(166,206,57,.3);
  color:var(--accent);font-size:11px;font-weight:600;
  padding:3px 10px;border-radius:100px;margin-top:6px;position:relative;z-index:1;
}
.preview-role svg{width:10px;height:10px;fill:none;stroke:currentColor;stroke-width:2.5;stroke-linecap:round;stroke-linejoin:round}
.profile-card-body{padding:18px}
.info-row{
  display:flex;align-items:flex-start;gap:10px;
  padding:10px 0;border-bottom:1px solid var(--border-soft);
}
.info-row:last-child{border-bottom:none}
.info-icon{
  width:32px;height:32px;border-radius:8px;background:var(--accent-dim);
  display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:1px;
}
.info-icon svg{width:14px;height:14px;fill:none;stroke:var(--accent-dark);stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.info-label{font-size:10.5px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px}
.info-value{font-size:13px;font-weight:600;color:var(--text);margin-top:1px;word-break:break-all}
.info-value.empty{color:var(--muted);font-weight:400;font-style:italic}
.rider-id-badge{
  display:flex;align-items:center;justify-content:center;gap:6px;
  background:var(--surface2);border:1px solid var(--border);
  border-radius:8px;padding:8px 12px;margin-top:14px;
}
.rider-id-badge span:first-child{font-size:11px;color:var(--muted);font-weight:600}
.rider-id-badge span:last-child{font-family:'Space Mono',monospace;font-size:12px;font-weight:700;color:var(--accent-dark)}
/* ── FORM CARD ── */
.form-card{
  background:var(--surface);border:1.5px solid var(--border);
  border-radius:20px;box-shadow:var(--shadow-sm);overflow:hidden;
}
.form-card-header{
  padding:18px 24px;border-bottom:1px solid var(--border-soft);
  background:var(--surface2);display:flex;align-items:center;gap:10px;
}
.form-card-header svg{width:17px;height:17px;fill:none;stroke:var(--accent-dark);stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.form-card-header h2{font-size:15px;font-weight:800;color:var(--text)}
.form-card-header p{font-size:12px;color:var(--muted);margin-top:1px}
.form-body{padding:22px 24px}
/* ── ALERT ── */
.alert{
  display:flex;align-items:flex-start;gap:10px;
  border-radius:12px;padding:13px 16px;margin-bottom:20px;font-size:13px;font-weight:500;
}
.alert svg{width:17px;height:17px;flex-shrink:0;margin-top:1px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.alert-success{background:#f0fdf4;border:1px solid #bbf7d0;color:#15803d}
.alert-error{background:#fef2f2;border:1px solid #fecaca;color:#b91c1c}
/* ── SECTION LABEL ── */
.section-label{
  font-size:10.5px;font-weight:700;color:var(--muted);
  text-transform:uppercase;letter-spacing:1px;
  margin-bottom:12px;padding-bottom:8px;border-bottom:1px solid var(--border-soft);
}
/* ── FIELDS ── */
.fields-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.fields-grid.single{grid-template-columns:1fr}
@media(max-width:520px){.fields-grid{grid-template-columns:1fr}}
.field{display:flex;flex-direction:column;gap:5px}
.field label{font-size:12px;font-weight:700;color:var(--text);letter-spacing:.1px}
.input-wrap{position:relative}
.input-wrap svg.field-icon{
  position:absolute;left:11px;top:50%;transform:translateY(-50%);
  width:14px;height:14px;fill:none;stroke:#9ca3af;stroke-width:2;
  stroke-linecap:round;stroke-linejoin:round;pointer-events:none;
}
.input-wrap input,.input-wrap select{
  width:100%;padding:10px 12px 10px 34px;
  border:1.5px solid #e5e7eb;border-radius:10px;
  font-size:13.5px;font-family:inherit;color:var(--text);
  background:#fafafa;outline:none;
  transition:border-color .2s,box-shadow .2s;
}
.input-wrap input:focus,.input-wrap select:focus{
  border-color:var(--accent);
  box-shadow:0 0 0 3px rgba(166,206,57,.15);
  background:#fff;
}
/* file input */
.file-wrap{
  border:1.5px dashed #d1d5db;border-radius:10px;
  padding:14px;text-align:center;cursor:pointer;
  background:#fafafa;transition:border-color .2s,background .2s;
}
.file-wrap:hover{border-color:var(--accent);background:var(--accent-dim)}
.file-wrap svg{width:22px;height:22px;fill:none;stroke:#9ca3af;stroke-width:1.5;stroke-linecap:round;stroke-linejoin:round;margin-bottom:6px}
.file-wrap p{font-size:12px;color:var(--muted);font-weight:500}
.file-wrap span{font-size:11px;color:#9ca3af}
.file-name{font-size:12px;color:var(--accent-dark);font-weight:600;margin-top:5px}
.form-divider{height:1px;background:var(--border-soft);margin:20px 0}
/* ── SUBMIT ── */
.btn-row{display:flex;align-items:center;justify-content:flex-end;gap:10px}
.btn-reset{
  padding:11px 20px;border-radius:10px;border:1.5px solid var(--border);
  background:var(--surface2);color:var(--text);font-size:13px;font-weight:600;
  cursor:pointer;font-family:inherit;transition:all .2s;
}
.btn-reset:hover{background:var(--border)}
.btn-submit{
  display:inline-flex;align-items:center;gap:7px;
  padding:11px 24px;border-radius:10px;border:none;
  background:var(--accent);color:var(--primary);font-size:13.5px;font-weight:700;
  cursor:pointer;font-family:inherit;transition:all .2s;
}
.btn-submit:hover{background:var(--accent-dark)}
.btn-submit svg{width:14px;height:14px;fill:none;stroke:currentColor;stroke-width:2.5;stroke-linecap:round;stroke-linejoin:round}
@keyframes fadeUp{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
.form-card,.profile-card{animation:fadeUp .4s ease both}
.form-card{animation-delay:.08s}
</style>
</head>
<body>
<div class="page">
  <!-- BACK -->
  <a href="rider_dashboard.php" class="back-link">
    <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
    Back to Dashboard
  </a>
  <!-- PAGE HEADER -->
  <div class="page-header">
    <h1>My Profile</h1>
    <p>View and update your personal information and account details.</p>
  </div>
  <div class="grid">
    <!-- ── LEFT: PROFILE SNAPSHOT ── -->
    <div class="profile-card">
      <div class="profile-card-top">
        <div class="avatar-wrap">
          <?php if (!empty($rider['photo'])): ?>
            <img class="avatar" id="avatarPreview" src="uploads/riders/<?= htmlspecialchars($rider['photo']) ?>" alt="Profile Photo">
          <?php else: ?>
            <div class="avatar-initials" id="avatarInitials"><?= $initials ?></div>
          <?php endif; ?>
          <label class="avatar-upload-btn" for="photoInput" title="Change photo">
            <svg viewBox="0 0 24 24"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
          </label>
        </div>
        <div class="preview-name" id="previewName"><?= htmlspecialchars($rider['name']) ?></div>
        <div class="preview-role">
          <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="2"/><path d="M12 2a10 10 0 0 1 7.74 16.33M12 22a10 10 0 0 1-7.74-16.33"/></svg>
          Delivery Rider
        </div>
      </div>
      <div class="profile-card-body">
        <div class="info-row">
          <div class="info-icon"><svg viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg></div>
          <div>
            <div class="info-label">Email</div>
            <div class="info-value <?= empty($rider['email']) ? 'empty' : '' ?>">
              <?= !empty($rider['email']) ? htmlspecialchars($rider['email']) : 'Not set' ?>
            </div>
          </div>
        </div>
        <div class="info-row">
          <div class="info-icon"><svg viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.6 3.37 2 2 0 0 1 3.59 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.87a16 16 0 0 0 6 6l.87-.87a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg></div>
          <div>
            <div class="info-label">Phone</div>
            <div class="info-value <?= empty($rider['phone']) ? 'empty' : '' ?>">
              <?= !empty($rider['phone']) ? htmlspecialchars($rider['phone']) : 'Not set' ?>
            </div>
          </div>
        </div>
        <div class="info-row">
          <div class="info-icon"><svg viewBox="0 0 24 24"><rect x="1" y="3" width="15" height="13" rx="1"/><path d="M16 8h4l3 5v3h-7z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg></div>
          <div>
            <div class="info-label">Vehicle</div>
            <div class="info-value <?= empty($rider['vehicle']) ? 'empty' : '' ?>">
              <?= !empty($rider['vehicle']) ? htmlspecialchars($rider['vehicle']) : 'Not set' ?>
            </div>
          </div>
        </div>
        <div class="info-row">
          <div class="info-icon"><svg viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg></div>
          <div>
            <div class="info-label">Address</div>
            <div class="info-value <?= empty($rider['address']) ? 'empty' : '' ?>">
              <?= !empty($rider['address']) ? htmlspecialchars($rider['address']) : 'Not set' ?>
            </div>
          </div>
        </div>
        <div class="rider-id-badge">
          <span>Rider ID</span>
          <span>#<?= str_pad($rider['id'], 4, '0', STR_PAD_LEFT) ?></span>
        </div>
      </div>
    </div>
    <!-- ── RIGHT: EDIT FORM ── -->
    <div class="form-card">
      <div class="form-card-header">
        <div>
          <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
          <h2>Edit Profile</h2>
          <p>Update your details below and save changes.</p>
        </div>
      </div>
      <div class="form-body">
        <?php if (!empty($msg)): ?>
        <div class="alert alert-<?= $msg_type ?>">
          <?php if ($msg_type === 'success'): ?>
            <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
          <?php else: ?>
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          <?php endif; ?>
          <?= htmlspecialchars($msg) ?>
        </div>
        <?php endif; ?>
        <form method="POST" enctype="multipart/form-data" id="profileForm">
          <div class="section-label">Personal Information</div>
          <div class="fields-grid">
            <div class="field">
              <label for="name">Full Name</label>
              <div class="input-wrap">
                <svg class="field-icon" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                <input type="text" id="name" name="name"
                  value="<?= htmlspecialchars($rider['name']) ?>" required
                  placeholder="Full name" oninput="document.getElementById('previewName').textContent=this.value">
              </div>
            </div>
            <div class="field">
              <label for="phone">Phone Number</label>
              <div class="input-wrap">
                <svg class="field-icon" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.6 3.37 2 2 0 0 1 3.59 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.87a16 16 0 0 0 6 6l.87-.87a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                <input type="text" id="phone" name="phone"
                  value="<?= htmlspecialchars($rider['phone']) ?>"
                  placeholder="+254 700 000000" required>
              </div>
            </div>
            <div class="field">
              <label for="email">Email Address</label>
              <div class="input-wrap">
                <svg class="field-icon" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                <input type="email" id="email" name="email"
                  value="<?= htmlspecialchars($rider['email'] ?? '') ?>"
                  placeholder="you@example.com">
              </div>
            </div>
            <div class="field">
              <label for="national_id">National ID</label>
              <div class="input-wrap">
                <svg class="field-icon" viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
                <input type="text" id="national_id" name="national_id"
                  value="<?= htmlspecialchars($rider['national_id'] ?? '') ?>"
                  placeholder="ID number">
              </div>
            </div>
          </div>
          <div class="form-divider"></div>
          <div class="section-label">Operational Details</div>
          <div class="fields-grid">
            <div class="field">
              <label for="vehicle">Vehicle / Reg. No.</label>
              <div class="input-wrap">
                <svg class="field-icon" viewBox="0 0 24 24"><rect x="1" y="3" width="15" height="13" rx="1"/><path d="M16 8h4l3 5v3h-7z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                <input type="text" id="vehicle" name="vehicle"
                  value="<?= htmlspecialchars($rider['vehicle'] ?? '') ?>"
                  placeholder="e.g. KBZ 123A">
              </div>
            </div>
            <div class="field">
              <label for="address">Home Address</label>
              <div class="input-wrap">
                <svg class="field-icon" viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                <input type="text" id="address" name="address"
                  value="<?= htmlspecialchars($rider['address'] ?? '') ?>"
                  placeholder="e.g. Nairobi, Kenya">
              </div>
            </div>
          </div>
          <div class="form-divider"></div>
          <div class="section-label">Profile Photo</div>
          <label class="file-wrap" for="photoInput" id="fileDropZone">
            <svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
            <p>Click to upload a new photo</p>
            <span>JPG, PNG or WebP &mdash; max 2 MB</span>
            <div class="file-name" id="fileName"></div>
          </label>
          <input type="file" id="photoInput" name="photo" accept="image/jpeg,image/png,image/webp">
          <div class="form-divider"></div>
          <div class="btn-row">
            <button type="reset" class="btn-reset">Reset</button>
            <button type="submit" name="update" class="btn-submit">
              <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
              Save Changes
            </button>
          </div>
        </form>
      </div>
    </div>
  </div><!-- /grid -->
</div><!-- /page -->
<script>
/* file name preview */
document.getElementById('photoInput').addEventListener('change', function () {
  const label = document.getElementById('fileName');
  label.textContent = this.files[0] ? this.files[0].name : '';
  /* live avatar preview */
  if (this.files[0]) {
    const reader = new FileReader();
    reader.onload = e => {
      const wrap = document.querySelector('.avatar-wrap');
      let img = wrap.querySelector('.avatar');
      const init = wrap.querySelector('.avatar-initials');
      if (!img) {
        img = document.createElement('img');
        img.className = 'avatar';
        img.id = 'avatarPreview';
        if (init) init.replaceWith(img);
        else wrap.prepend(img);
      }
      img.src = e.target.result;
    };
    reader.readAsDataURL(this.files[0]);
  }
});
</script>
</body>
</html>