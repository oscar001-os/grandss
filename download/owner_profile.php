<?php
session_start();
include 'connection.php';

if (!isset($_SESSION['owner_id'])) {
    header("Location: owner_login.php");
    exit();
}

$owner_id = $_SESSION['owner_id'];

$message = "";
$error = "";

/* Fetch Owner Details */
$stmt = $conn->prepare(
    "SELECT name, email, phone FROM owners WHERE id = ?"
);
$stmt->bind_param("i", $owner_id);
$stmt->execute();
$result = $stmt->get_result();
$owner = $result->fetch_assoc();
$stmt->close();

/* Update Profile */
if (isset($_POST['update_profile'])) {
    $name  = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);

    $stmt = $conn->prepare(
        "UPDATE owners SET name=?, email=?, phone=? WHERE id=?"
    );
    $stmt->bind_param("sssi", $name, $email, $phone, $owner_id);

    if ($stmt->execute()) {
        $message = "Profile updated successfully.";
        $stmt = $conn->prepare("SELECT name, email, phone FROM owners WHERE id=?");
        $stmt->bind_param("i", $owner_id);
        $stmt->execute();
        $owner = $stmt->get_result()->fetch_assoc();
    } else {
        $error = "Failed to update profile.";
    }
    $stmt->close();
}

/* Change Password */
if (isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password     = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    $stmt = $conn->prepare("SELECT password FROM owners WHERE id=?");
    $stmt->bind_param("i", $owner_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if (!password_verify($current_password, $row['password'])) {
        $error = "Current password is incorrect.";
    } elseif ($new_password !== $confirm_password) {
        $error = "New passwords do not match.";
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $update = $conn->prepare("UPDATE owners SET password=? WHERE id=?");
        $update->bind_param("si", $hashed_password, $owner_id);
        if ($update->execute()) {
            $message = "Password changed successfully.";
        } else {
            $error = "Failed to change password.";
        }
        $update->close();
    }
    $stmt->close();
}

function initials($name) {
    $name = trim($name);
    if ($name === '') return '?';
    $parts = preg_split('/\s+/', $name);
    $i = strtoupper(substr($parts[0], 0, 1));
    if (count($parts) > 1) $i .= strtoupper(substr($parts[count($parts) - 1], 0, 1));
    return $i;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Owner Profile · Grand Superior</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

<style>
:root {
    --ink:        #0d1b2a;
    --ink-soft:   #16283c;
    --lime:       #a6ce39;
    --lime-dark:  #8bbf2f;
    --lime-light: #f0f7dc;
    --paper:      #f4f6f9;
    --surface:    #ffffff;
    --line:       #e3e7ee;
    --muted:      #65748a;
}

*, *::before, *::after {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Inter', sans-serif;
    background: var(--paper);
    color: var(--ink);
    min-height: 100vh;
}

/* ===== NAVBAR ===== */
.navbar {
    background: var(--ink);
    padding: 0 28px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    height: 64px;
    position: sticky;
    top: 0;
    z-index: 100;
}

.navbar-logo {
    display: flex;
    align-items: center;
    gap: 11px;
    text-decoration: none;
}

.navbar-logo img {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    object-fit: cover;
    border: 2px solid var(--lime);
    padding: 2px;
    background: #fff;
}

.navbar-logo span {
    font-family: 'Sora', sans-serif;
    font-weight: 600;
    font-size: 16px;
    color: #fff;
}

.navbar-right {
    display: flex;
    align-items: center;
    gap: 14px;
}

.nav-owner-chip {
    display: flex;
    align-items: center;
    gap: 9px;
}

.nav-avatar {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: var(--lime);
    color: var(--ink);
    font-family: 'Sora', sans-serif;
    font-weight: 700;
    font-size: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.nav-name {
    font-size: 13px;
    font-weight: 500;
    color: rgba(255,255,255,0.85);
}

/* ===== LAYOUT ===== */
.page {
    max-width: 860px;
    margin: 0 auto;
    padding: 36px 20px 64px;
}

/* ===== BACK LINK ===== */
.back-link {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    text-decoration: none;
    color: var(--muted);
    font-size: 13.5px;
    font-weight: 500;
    margin-bottom: 24px;
    transition: color 0.15s;
}
.back-link:hover { color: var(--ink); }
.back-link svg {
    width: 14px;
    height: 14px;
    stroke: currentColor;
    fill: none;
    stroke-width: 2.5;
    stroke-linecap: round;
    stroke-linejoin: round;
}

/* ===== PAGE HEADER ===== */
.page-header {
    display: flex;
    align-items: center;
    gap: 18px;
    margin-bottom: 32px;
}

.profile-avatar {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    background: var(--ink);
    color: var(--lime);
    font-family: 'Sora', sans-serif;
    font-weight: 700;
    font-size: 22px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    border: 3px solid var(--lime);
}

.page-header-text h1 {
    font-family: 'Sora', sans-serif;
    font-size: 22px;
    font-weight: 600;
    margin-bottom: 3px;
}

.page-header-text p {
    font-size: 13px;
    color: var(--muted);
}

/* ===== ALERT BANNERS ===== */
.alert {
    display: flex;
    align-items: flex-start;
    gap: 11px;
    padding: 14px 16px;
    border-radius: 12px;
    font-size: 13.5px;
    font-weight: 500;
    margin-bottom: 22px;
    border: 1px solid transparent;
}
.alert svg {
    width: 17px;
    height: 17px;
    fill: none;
    stroke: currentColor;
    stroke-width: 2;
    stroke-linecap: round;
    stroke-linejoin: round;
    flex-shrink: 0;
    margin-top: 1px;
}
.alert-success {
    background: #edfaf4;
    border-color: #a7f3d0;
    color: #065f46;
}
.alert-error {
    background: #fff1f1;
    border-color: #fca5a5;
    color: #991b1b;
}

/* ===== CARD ===== */
.card {
    background: var(--surface);
    border: 1px solid var(--line);
    border-radius: 16px;
    margin-bottom: 20px;
    overflow: hidden;
}

.card-header {
    padding: 20px 24px 16px;
    border-bottom: 1px solid var(--line);
    display: flex;
    align-items: center;
    gap: 12px;
}

.card-header-icon {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    background: var(--lime-light);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.card-header-icon svg {
    width: 18px;
    height: 18px;
    fill: none;
    stroke: var(--lime-dark);
    stroke-width: 2;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.card-header-text h2 {
    font-family: 'Sora', sans-serif;
    font-size: 15px;
    font-weight: 600;
    color: var(--ink);
    margin-bottom: 2px;
}

.card-header-text p {
    font-size: 12px;
    color: var(--muted);
}

.card-body {
    padding: 24px;
}

/* ===== FORM ===== */
.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 18px;
}

.form-grid .span-full {
    grid-column: 1 / -1;
}

.field {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.field label {
    font-size: 12px;
    font-weight: 600;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.field input {
    padding: 11px 14px;
    border: 1px solid var(--line);
    border-radius: 10px;
    font-family: 'Inter', sans-serif;
    font-size: 13.5px;
    color: var(--ink);
    background: var(--paper);
    outline: none;
    transition: border-color 0.15s, box-shadow 0.15s, background 0.15s;
}

.field input:focus {
    border-color: var(--lime-dark);
    box-shadow: 0 0 0 4px rgba(166, 206, 57, 0.18);
    background: #fff;
}

.field input::placeholder {
    color: #b0bac8;
}

/* ===== PASSWORD FIELD ===== */
.password-wrapper {
    position: relative;
}

.password-wrapper input {
    padding-right: 44px;
    width: 100%;
}

.toggle-pw {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    cursor: pointer;
    color: var(--muted);
    padding: 0;
    display: flex;
    align-items: center;
    margin-top: 0;
}

.toggle-pw:hover { color: var(--ink); }

.toggle-pw svg {
    width: 17px;
    height: 17px;
    fill: none;
    stroke: currentColor;
    stroke-width: 2;
    stroke-linecap: round;
    stroke-linejoin: round;
}

/* ===== DIVIDER ===== */
.form-divider {
    border: none;
    border-top: 1px solid var(--line);
    margin: 20px 0;
}

/* ===== SUBMIT BUTTON ===== */
.btn-submit {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 11px 24px;
    background: var(--lime);
    color: var(--ink);
    border: none;
    border-radius: 10px;
    font-family: 'Inter', sans-serif;
    font-size: 13.5px;
    font-weight: 700;
    cursor: pointer;
    transition: background 0.15s, transform 0.15s, box-shadow 0.15s;
    margin-top: 6px;
}

.btn-submit:hover {
    background: var(--lime-dark);
    transform: translateY(-1px);
    box-shadow: 0 6px 18px rgba(166, 206, 57, 0.35);
}

.btn-submit svg {
    width: 15px;
    height: 15px;
    fill: none;
    stroke: currentColor;
    stroke-width: 2.5;
    stroke-linecap: round;
    stroke-linejoin: round;
}

/* ===== SECURITY NOTE ===== */
.security-note {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    background: var(--lime-light);
    border: 1px solid #d4eaaa;
    border-radius: 10px;
    padding: 12px 14px;
    font-size: 12.5px;
    color: #4a6b1a;
    margin-bottom: 20px;
}

.security-note svg {
    width: 15px;
    height: 15px;
    fill: none;
    stroke: currentColor;
    stroke-width: 2;
    stroke-linecap: round;
    stroke-linejoin: round;
    flex-shrink: 0;
    margin-top: 1px;
}

/* ===== RESPONSIVE ===== */
@media (max-width: 600px) {
    .form-grid {
        grid-template-columns: 1fr;
    }
    .form-grid .span-full {
        grid-column: 1;
    }
    .page-header {
        flex-wrap: wrap;
    }
    .navbar { padding: 0 16px; }
    .page { padding: 24px 14px 48px; }
}
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
        <div class="nav-owner-chip">
            <div class="nav-avatar"><?php echo htmlspecialchars(initials($owner['name'])); ?></div>
            <span class="nav-name"><?php echo htmlspecialchars($owner['name']); ?></span>
        </div>
    </div>
</nav>

<!-- PAGE -->
<div class="page">

    <a href="owner_dashboard.php" class="back-link">
        <svg viewBox="0 0 24 24"><path d="M19 12H5M11 18l-6-6 6-6"/></svg>
        Back to dashboard
    </a>

    <!-- Page Header -->
    <div class="page-header">
        <div class="profile-avatar"><?php echo htmlspecialchars(initials($owner['name'])); ?></div>
        <div class="page-header-text">
            <h1><?php echo htmlspecialchars($owner['name']); ?></h1>
            <p><?php echo htmlspecialchars($owner['email']); ?> &nbsp;·&nbsp; Owner Account</p>
        </div>
    </div>

    <!-- Alerts -->
    <?php if (!empty($message)) { ?>
    <div class="alert alert-success">
        <svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        <?php echo htmlspecialchars($message); ?>
    </div>
    <?php } ?>
    <?php if (!empty($error)) { ?>
    <div class="alert alert-error">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <?php echo htmlspecialchars($error); ?>
    </div>
    <?php } ?>

    <!-- Profile Information -->
    <div class="card">
        <div class="card-header">
            <div class="card-header-icon">
                <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            </div>
            <div class="card-header-text">
                <h2>Profile Information</h2>
                <p>Update your name, email address and phone number</p>
            </div>
        </div>
        <div class="card-body">
            <form method="POST">
                <div class="form-grid">
                    <div class="field span-full">
                        <label for="name">Full Name</label>
                        <input
                            type="text"
                            id="name"
                            name="name"
                            value="<?php echo htmlspecialchars($owner['name']); ?>"
                            placeholder="Your full name"
                            required>
                    </div>
                    <div class="field">
                        <label for="email">Email Address</label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            value="<?php echo htmlspecialchars($owner['email']); ?>"
                            placeholder="you@example.com"
                            required>
                    </div>
                    <div class="field">
                        <label for="phone">Phone Number</label>
                        <input
                            type="text"
                            id="phone"
                            name="phone"
                            value="<?php echo htmlspecialchars($owner['phone']); ?>"
                            placeholder="+254 000 000 000"
                            required>
                    </div>
                </div>
                <hr class="form-divider">
                <button type="submit" name="update_profile" class="btn-submit">
                    <svg viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                    Save Changes
                </button>
            </form>
        </div>
    </div>

    <!-- Change Password -->
    <div class="card">
        <div class="card-header">
            <div class="card-header-icon">
                <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            </div>
            <div class="card-header-text">
                <h2>Change Password</h2>
                <p>Keep your account secure with a strong password</p>
            </div>
        </div>
        <div class="card-body">
            <div class="security-note">
                <svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                Use at least 8 characters including uppercase letters, numbers and symbols for a stronger password.
            </div>
            <form method="POST">
                <div class="form-grid">
                    <div class="field span-full">
                        <label for="current_password">Current Password</label>
                        <div class="password-wrapper">
                            <input
                                type="password"
                                id="current_password"
                                name="current_password"
                                placeholder="Enter your current password"
                                required>
                            <button type="button" class="toggle-pw" onclick="togglePw('current_password', this)">
                                <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>
                    </div>
                    <div class="field">
                        <label for="new_password">New Password</label>
                        <div class="password-wrapper">
                            <input
                                type="password"
                                id="new_password"
                                name="new_password"
                                placeholder="New password"
                                required>
                            <button type="button" class="toggle-pw" onclick="togglePw('new_password', this)">
                                <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>
                    </div>
                    <div class="field">
                        <label for="confirm_password">Confirm New Password</label>
                        <div class="password-wrapper">
                            <input
                                type="password"
                                id="confirm_password"
                                name="confirm_password"
                                placeholder="Repeat new password"
                                required>
                            <button type="button" class="toggle-pw" onclick="togglePw('confirm_password', this)">
                                <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>
                    </div>
                </div>
                <hr class="form-divider">
                <button type="submit" name="change_password" class="btn-submit">
                    <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    Update Password
                </button>
            </form>
        </div>
    </div>

</div>

<script>
function togglePw(id, btn) {
    var input = document.getElementById(id);
    var isText = input.type === 'text';
    input.type = isText ? 'password' : 'text';
    btn.innerHTML = isText
        ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>'
        : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';
}
</script>

</body>
</html>