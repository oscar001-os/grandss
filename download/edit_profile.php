<?php
session_start();
include 'connection.php';

if (!isset($_SESSION['client_id'])) {
    header("Location: client_login.php");
    exit();
}

$client_id = $_SESSION['client_id'];
$success   = "";
$error     = "";

$stmt = $conn->prepare("SELECT name, email FROM clients WHERE id = ?");
$stmt->bind_param("i", $client_id);
$stmt->execute();
$client = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name     = trim($_POST['name']);
    $email    = trim($_POST['email']);
    $password = trim($_POST['password']);
    $confirm  = trim($_POST['confirm_password']);

    if (!empty($password) && $password !== $confirm) {
        $error = "Passwords do not match. Please try again.";
    } else {
        if (!empty($password)) {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE clients SET name=?, email=?, password=? WHERE id=?");
            $stmt->bind_param("sssi", $name, $email, $hashed, $client_id);
        } else {
            $stmt = $conn->prepare("UPDATE clients SET name=?, email=? WHERE id=?");
            $stmt->bind_param("ssi", $name, $email, $client_id);
        }

        if ($stmt->execute()) {
            $success             = "Profile updated successfully!";
            $client['name']      = $name;
            $client['email']     = $email;
            $_SESSION['client_name'] = $name;
        } else {
            $error = "Failed to update profile. Please try again.";
        }
        $stmt->close();
    }
}

// Avatar initials
$initials = '';
foreach (explode(' ', $client['name']) as $word) {
    $initials .= strtoupper(mb_substr($word, 0, 1));
}
$initials = mb_substr($initials, 0, 2);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Profile | Grand Superior Drycleaners</title>
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
    max-width: 1200px; margin: 0 auto;
    padding: 0 24px; height: 64px;
    display: flex; align-items: center; justify-content: space-between;
}
.brand { display: flex; align-items: center; gap: 12px; text-decoration: none; }
.brand-icon {
    width: 36px; height: 36px; border-radius: 10px;
    background: #a6ce39; display: flex; align-items: center;
    justify-content: center; flex-shrink: 0;
}
.brand-icon svg { width: 18px; height: 18px; }
.brand-name { font-size: 14px; font-weight: 700; color: #fff; line-height: 1.2; }
.brand-sub  { font-size: 9px; font-weight: 600; color: #a6ce39; letter-spacing: .12em; text-transform: uppercase; }
.nav-right  { display: flex; align-items: center; gap: 16px; }
.nav-welcome { font-size: 13px; color: #94a3b8; }
.nav-btn {
    font-size: 13px; font-weight: 600; color: #fff;
    background: rgba(255,255,255,.1); border: none;
    padding: 7px 16px; border-radius: 8px;
    text-decoration: none; transition: background .2s; cursor: pointer;
}
.nav-btn:hover { background: rgba(255,255,255,.18); }

/* ── Layout ── */
.main { max-width: 1200px; margin: 0 auto; padding: 36px 24px 60px; }

.breadcrumb {
    display: flex; align-items: center; gap: 6px;
    font-size: 12px; color: #94a3b8; margin-bottom: 8px;
}
.breadcrumb a { color: #94a3b8; text-decoration: none; }
.breadcrumb a:hover { color: #475569; }
.breadcrumb-sep { color: #cbd5e1; }
.breadcrumb-current { color: #475569; font-weight: 500; }

.page-title { font-size: 22px; font-weight: 700; color: #0d1b2a; }
.page-sub   { font-size: 13px; color: #64748b; margin-top: 4px; margin-bottom: 32px; }

/* ── Grid ── */
.grid {
    display: grid;
    grid-template-columns: 1fr 2fr;
    gap: 28px;
    align-items: start;
}

/* ── Card ── */
.card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    box-shadow: 0 1px 8px rgba(0,0,0,.06);
    overflow: hidden;
}

/* ── Avatar card ── */
.avatar-card {
    padding: 28px 20px;
    display: flex; flex-direction: column; align-items: center; text-align: center;
}
.avatar-circle {
    width: 76px; height: 76px; border-radius: 50%;
    background: #0d1b2a; display: flex; align-items: center;
    justify-content: center; margin-bottom: 16px;
    box-shadow: 0 4px 16px rgba(13,27,42,.25);
}
.avatar-initials { font-size: 26px; font-weight: 700; color: #a6ce39; letter-spacing: .04em; }
.avatar-name  { font-size: 15px; font-weight: 600; color: #0d1b2a; }
.avatar-email { font-size: 13px; color: #64748b; margin-top: 4px; word-break: break-all; }

.avatar-meta {
    width: 100%; margin-top: 20px;
    border-top: 1px solid #f1f5f9; padding-top: 16px;
    display: flex; flex-direction: column; gap: 8px;
}
.meta-row { display: flex; justify-content: space-between; font-size: 12px; }
.meta-label { color: #94a3b8; }
.meta-val   { font-weight: 500; color: #475569; }

/* ── Form card ── */
.form-section { padding: 20px 24px; }
.form-section + .form-divider { border: none; border-top: 1px solid #f1f5f9; margin: 0; }

.section-label {
    font-size: 11px; font-weight: 600; color: #94a3b8;
    text-transform: uppercase; letter-spacing: .08em; margin-bottom: 4px;
}
.section-hint { font-size: 12px; color: #94a3b8; margin-bottom: 16px; }

.field { margin-bottom: 16px; }
.field:last-child { margin-bottom: 0; }
.field label { display: block; font-size: 13px; font-weight: 500; color: #374151; margin-bottom: 6px; }

.input-wrap { position: relative; }
.input-icon {
    position: absolute; left: 12px; top: 50%; transform: translateY(-50%);
    color: #94a3b8; display: flex; pointer-events: none;
}
.input-icon svg { width: 16px; height: 16px; }

input[type="text"],
input[type="email"],
input[type="password"] {
    width: 100%; padding: 10px 12px 10px 38px;
    border: 1px solid #e2e8f0; border-radius: 10px;
    font-size: 13px; font-family: inherit; color: #1e293b;
    background: #fff; transition: border-color .2s, box-shadow .2s;
    outline: none;
}
input:focus {
    border-color: #a6ce39;
    box-shadow: 0 0 0 3px rgba(166,206,57,.15);
}

.toggle-pass {
    position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
    background: none; border: none; cursor: pointer;
    color: #94a3b8; display: flex; padding: 0;
    transition: color .2s;
}
.toggle-pass:hover { color: #475569; }
.toggle-pass svg { width: 15px; height: 15px; }

/* ── Alerts ── */
.alert {
    display: flex; align-items: center; gap: 10px;
    padding: 12px 16px; border-radius: 10px;
    font-size: 13px; font-weight: 500; margin: 20px 24px 0;
}
.alert svg { width: 16px; height: 16px; flex-shrink: 0; }
.alert-success { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; }
.alert-error   { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }

/* ── Form actions ── */
.form-actions { padding: 16px 24px 24px; display: flex; gap: 12px; }
.btn-primary {
    flex: 1; padding: 11px; background: #a6ce39; border: none;
    border-radius: 10px; font-size: 13px; font-weight: 700;
    color: #0d1b2a; cursor: pointer; transition: background .2s;
    font-family: inherit;
}
.btn-primary:hover { background: #94b934; }
.btn-cancel {
    flex: 1; padding: 11px; background: #f1f5f9; border: none;
    border-radius: 10px; font-size: 13px; font-weight: 600;
    color: #475569; cursor: pointer; text-decoration: none;
    display: inline-flex; align-items: center; justify-content: center;
    transition: background .2s; font-family: inherit;
}
.btn-cancel:hover { background: #e2e8f0; }

/* ── Back link ── */
.back-link {
    display: inline-flex; align-items: center; gap: 6px;
    font-size: 13px; font-weight: 500; color: #64748b;
    text-decoration: none; margin-top: 24px; transition: color .2s;
}
.back-link:hover { color: #0d1b2a; }
.back-link svg { width: 15px; height: 15px; }

/* ── Responsive ── */
@media (max-width: 768px) {
    .main { padding: 24px 16px 48px; }
    .grid { grid-template-columns: 1fr; gap: 16px; }
    .nav-welcome { display: none; }
    .page-title { font-size: 20px; }
    .form-actions { flex-direction: column; }
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
            <span class="nav-welcome">Welcome, <?php echo htmlspecialchars($client['name']); ?></span>
            <a href="client_dashboard.php" class="nav-btn">Dashboard</a>
        </div>
    </div>
</nav>

<!-- ── Main ── -->
<main class="main">

    <div class="breadcrumb">
        <a href="client_dashboard.php">Dashboard</a>
        <span class="breadcrumb-sep">›</span>
        <span class="breadcrumb-current">Edit Profile</span>
    </div>

    <h1 class="page-title">Edit Profile</h1>
    <p class="page-sub">Update your account information and password.</p>

    <div class="grid">

        <!-- ── Avatar sidebar ── -->
        <div class="card avatar-card">
            <div class="avatar-circle">
                <span class="avatar-initials"><?php echo htmlspecialchars($initials); ?></span>
            </div>
            <p class="avatar-name"><?php echo htmlspecialchars($client['name']); ?></p>
            <p class="avatar-email"><?php echo htmlspecialchars($client['email']); ?></p>
            <div class="avatar-meta">
                <div class="meta-row">
                    <span class="meta-label">Account type</span>
                    <span class="meta-val">Client</span>
                </div>
                <div class="meta-row">
                    <span class="meta-label">Status</span>
                    <span class="meta-val" style="color:#10b981;">Active</span>
                </div>
            </div>
        </div>

        <!-- ── Form ── -->
        <div>
            <form method="POST" action="" class="card">

                <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <?php echo htmlspecialchars($success); ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="12" y1="8" x2="12" y2="12"/>
                        <line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>
                    <?php echo htmlspecialchars($error); ?>
                </div>
                <?php endif; ?>

                <!-- Account info -->
                <div class="form-section" style="padding-top: <?php echo (!empty($success) || !empty($error)) ? '20px' : '24px'; ?>">
                    <p class="section-label">Account Information</p>

                    <div class="field" style="margin-top:14px;">
                        <label for="name">Full Name</label>
                        <div class="input-wrap">
                            <span class="input-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/>
                                </svg>
                            </span>
                            <input type="text" id="name" name="name"
                                   value="<?php echo htmlspecialchars($client['name']); ?>"
                                   placeholder="Your full name" required>
                        </div>
                    </div>

                    <div class="field">
                        <label for="email">Email Address</label>
                        <div class="input-wrap">
                            <span class="input-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                                    <polyline points="22,6 12,13 2,6"/>
                                </svg>
                            </span>
                            <input type="email" id="email" name="email"
                                   value="<?php echo htmlspecialchars($client['email']); ?>"
                                   placeholder="your@email.com" required>
                        </div>
                    </div>
                </div>

                <hr class="form-divider">

                <!-- Password -->
                <div class="form-section">
                    <p class="section-label">Change Password</p>
                    <p class="section-hint">Leave blank to keep your current password.</p>

                    <div class="field">
                        <label for="password">New Password</label>
                        <div class="input-wrap">
                            <span class="input-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                                    <path d="M7 11V7a5 5 0 0110 0v4"/>
                                </svg>
                            </span>
                            <input type="password" id="password" name="password"
                                   placeholder="New password">
                            <button type="button" class="toggle-pass" onclick="toggleVis('password', this)">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="field">
                        <label for="confirm_password">Confirm New Password</label>
                        <div class="input-wrap">
                            <span class="input-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                                    <path d="M7 11V7a5 5 0 0110 0v4"/>
                                </svg>
                            </span>
                            <input type="password" id="confirm_password" name="confirm_password"
                                   placeholder="Confirm new password">
                            <button type="button" class="toggle-pass" onclick="toggleVis('confirm_password', this)">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="form-actions">
                    <button type="submit" class="btn-primary">Save Changes</button>
                    <a href="client_dashboard.php" class="btn-cancel">Cancel</a>
                </div>

            </form>

            <a href="client_dashboard.php" class="back-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
                Back to Dashboard
            </a>
        </div>

    </div>
</main>

<script>
function toggleVis(fieldId, btn) {
    const input = document.getElementById(fieldId);
    const isText = input.type === 'text';
    input.type = isText ? 'password' : 'text';
    btn.innerHTML = isText
        ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>'
        : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';
}
</script>

</body>
</html>