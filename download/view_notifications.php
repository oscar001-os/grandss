<?php
session_start();
include("connection.php");

/* ── Auth: must be client OR rider ── */
if (!isset($_SESSION['client_id']) && !isset($_SESSION['rider_id'])) {
    header("Location: client_login.php");
    exit();
}

if (isset($_SESSION['client_id'])) {
    $user_type  = "client";
    $user_id    = (int) $_SESSION['client_id'];
    $dashboard  = "client_dashboard.php";
    $table      = "clients";
} else {
    $user_type  = "rider";
    $user_id    = (int) $_SESSION['rider_id'];
    $dashboard  = "rider_dashboard.php";
    $table      = "riders";
}

/* ── Fetch user name ── */
$stmt = $conn->prepare("SELECT name FROM {$table} WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$user_name = $user['name'] ?? 'User';

/* ── Fetch notifications (shared for all users) ── */
$result = $conn->query(
    "SELECT id, title, message, created_at
     FROM notifications
     ORDER BY id DESC"
);
$total = $result ? $result->num_rows : 0;

/* ── Helpers ── */
function initials($name) {
    $name = trim($name);
    if ($name === '') return '?';
    $parts = preg_split('/\s+/', $name);
    $i = strtoupper(substr($parts[0], 0, 1));
    if (count($parts) > 1) $i .= strtoupper(substr(end($parts), 0, 1));
    return $i;
}

function timeAgo($datetime) {
    $now  = new DateTime();
    $ago  = new DateTime($datetime);
    $diff = $now->diff($ago);
    if ($diff->y > 0) return $diff->y . ' year'  . ($diff->y > 1 ? 's' : '') . ' ago';
    if ($diff->m > 0) return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
    if ($diff->d > 0) return $diff->d . ' day'   . ($diff->d > 1 ? 's' : '') . ' ago';
    if ($diff->h > 0) return $diff->h . ' hour'  . ($diff->h > 1 ? 's' : '') . ' ago';
    if ($diff->i > 0) return $diff->i . ' min'   . ($diff->i > 1 ? 's' : '') . ' ago';
    return 'Just now';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Notifications · Grand Superior</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

<style>
:root {
    --ink:        #0d1b2a;
    --lime:       #a6ce39;
    --lime-dark:  #8bbf2f;
    --lime-light: #f0f7dc;
    --paper:      #f4f6f9;
    --surface:    #ffffff;
    --line:       #e3e7ee;
    --muted:      #65748a;

    /* rider accent — orange tint, easy to swap */
    --rider-accent: #f59e0b;
    --rider-light:  #fef3c7;
}

*, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

body {
    font-family: 'Inter', sans-serif;
    background: var(--paper);
    color: var(--ink);
    min-height: 100vh;
}

/* ───────────────────────────────── NAVBAR */
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
    box-shadow: 0 2px 12px rgba(0,0,0,.2);
}

.navbar-logo {
    display: flex;
    align-items: center;
    gap: 11px;
    text-decoration: none;
}
.navbar-logo img {
    width: 36px; height: 36px;
    border-radius: 8px; object-fit: cover;
    border: 2px solid var(--lime);
    padding: 2px; background: #fff;
}
.navbar-logo span {
    font-family: 'Sora', sans-serif;
    font-weight: 600; font-size: 16px; color: #fff;
}

.navbar-right {
    display: flex; align-items: center; gap: 10px;
}

/* role badge in navbar */
.role-badge {
    font-size: 10px; font-weight: 700;
    letter-spacing: .1em; text-transform: uppercase;
    padding: 3px 9px; border-radius: 99px;
}
.role-badge.client { background: rgba(166,206,57,.18); color: var(--lime); }
.role-badge.rider  { background: rgba(245,158,11,.18); color: var(--rider-accent); }

.nav-avatar {
    width: 34px; height: 34px;
    border-radius: 50%;
    font-family: 'Sora', sans-serif;
    font-weight: 700; font-size: 12px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.nav-avatar.client { background: var(--lime);         color: var(--ink); }
.nav-avatar.rider  { background: var(--rider-accent);  color: #fff; }

.nav-name { font-size: 13px; font-weight: 500; color: rgba(255,255,255,.82); }

/* ───────────────────────────────── PAGE */
.page {
    max-width: 760px;
    margin: 0 auto;
    padding: 36px 20px 72px;
}

/* ───────────────────────────────── BACK LINK */
.back-link {
    display: inline-flex; align-items: center; gap: 7px;
    text-decoration: none; color: var(--muted);
    font-size: 13.5px; font-weight: 500;
    margin-bottom: 26px; transition: color .15s;
}
.back-link:hover { color: var(--ink); }
.back-link svg {
    width: 14px; height: 14px;
    stroke: currentColor; fill: none;
    stroke-width: 2.5; stroke-linecap: round; stroke-linejoin: round;
}

/* ───────────────────────────────── PAGE HEADER */
.page-header {
    display: flex; align-items: center;
    justify-content: space-between;
    flex-wrap: wrap; gap: 16px;
    margin-bottom: 28px;
}
.page-header-left { display: flex; align-items: center; gap: 16px; }

.page-header-icon {
    width: 52px; height: 52px;
    border-radius: 14px; background: var(--ink);
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.page-header-icon svg {
    width: 24px; height: 24px;
    fill: none; stroke: var(--lime);
    stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;
}

.page-header h1 {
    font-family: 'Sora', sans-serif;
    font-size: 22px; font-weight: 600; margin-bottom: 3px;
}
.page-header p { font-size: 13px; color: var(--muted); }

.count-badge {
    display: inline-flex; align-items: center; gap: 6px;
    background: var(--ink); color: var(--lime);
    font-family: 'Sora', sans-serif;
    font-weight: 700; font-size: 13px;
    padding: 8px 16px; border-radius: 999px; flex-shrink: 0;
}
.count-badge svg {
    width: 14px; height: 14px;
    fill: none; stroke: currentColor;
    stroke-width: 2.5; stroke-linecap: round; stroke-linejoin: round;
}

/* ───────────────────────────────── USER INFO STRIP */
.user-strip {
    display: flex; align-items: center; gap: 12px;
    background: var(--surface);
    border: 1px solid var(--line);
    border-radius: 12px; padding: 14px 18px;
    margin-bottom: 26px;
}
.user-strip-avatar {
    width: 40px; height: 40px; border-radius: 10px;
    font-family: 'Sora', sans-serif;
    font-weight: 700; font-size: 14px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.user-strip-avatar.client { background: var(--lime-light); color: var(--lime-dark); }
.user-strip-avatar.rider  { background: var(--rider-light); color: var(--rider-accent); }

.user-strip-info { flex: 1; min-width: 0; }
.user-strip-name  { font-size: 14px; font-weight: 600; color: var(--ink); }
.user-strip-role  { font-size: 12px; color: var(--muted); text-transform: capitalize; margin-top: 1px; }

.user-strip-tag {
    font-size: 10.5px; font-weight: 700; letter-spacing: .1em;
    text-transform: uppercase; padding: 4px 10px; border-radius: 99px;
}
.user-strip-tag.client { background: var(--lime-light); color: var(--lime-dark); }
.user-strip-tag.rider  { background: var(--rider-light); color: var(--rider-accent); }

/* ───────────────────────────────── NOTIFICATION CARD */
.notif-card {
    background: var(--surface);
    border: 1px solid var(--line);
    border-left: 4px solid var(--lime);
    border-radius: 14px;
    padding: 20px 22px;
    margin-bottom: 14px;
    display: flex; gap: 16px; align-items: flex-start;
    transition: box-shadow .15s, transform .15s;
}
.notif-card:hover {
    box-shadow: 0 8px 24px rgba(13,27,42,.1);
    transform: translateY(-2px);
}

/* rider gets an orange left border */
.notif-card.rider-view { border-left-color: var(--rider-accent); }

.notif-icon {
    width: 40px; height: 40px;
    border-radius: 10px; background: var(--lime-light);
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; margin-top: 2px;
}
.notif-card.rider-view .notif-icon { background: var(--rider-light); }

.notif-icon svg {
    width: 18px; height: 18px;
    fill: none; stroke: var(--lime-dark);
    stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;
}
.notif-card.rider-view .notif-icon svg { stroke: var(--rider-accent); }

.notif-body { flex: 1; min-width: 0; }
.notif-title {
    font-family: 'Sora', sans-serif;
    font-size: 15px; font-weight: 600; color: var(--ink);
    margin-bottom: 6px; line-height: 1.3;
}
.notif-message {
    font-size: 13.5px; color: var(--muted);
    line-height: 1.7; margin-bottom: 12px; word-break: break-word;
}
.notif-footer {
    display: flex; align-items: center; gap: 6px;
    font-size: 12px; color: #9aa5b4;
}
.notif-footer svg {
    width: 13px; height: 13px;
    fill: none; stroke: currentColor;
    stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;
    flex-shrink: 0;
}
.notif-number {
    margin-left: auto;
    font-size: 11px; font-weight: 600;
    border-radius: 6px; padding: 3px 8px; white-space: nowrap;
}
.notif-number.client { color: var(--lime-dark);     background: var(--lime-light); }
.notif-number.rider  { color: var(--rider-accent);  background: var(--rider-light); }

/* ───────────────────────────────── EMPTY STATE */
.empty {
    background: var(--surface);
    border: 1px solid var(--line);
    border-radius: 16px; padding: 60px 24px; text-align: center;
}
.empty-icon {
    width: 64px; height: 64px;
    border-radius: 18px; background: var(--paper);
    border: 1px solid var(--line);
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 20px;
}
.empty-icon svg {
    width: 28px; height: 28px;
    fill: none; stroke: #c8d3e0;
    stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round;
}
.empty h3 {
    font-family: 'Sora', sans-serif;
    font-size: 17px; font-weight: 600; color: var(--ink); margin-bottom: 8px;
}
.empty p { font-size: 13.5px; color: var(--muted); line-height: 1.6; }

/* ───────────────────────────────── DIVIDER LABEL */
.section-label {
    font-size: 11.5px; font-weight: 700; color: var(--muted);
    text-transform: uppercase; letter-spacing: .6px;
    margin-bottom: 14px;
    display: flex; align-items: center; gap: 8px;
}
.section-label::after { content: ''; flex: 1; height: 1px; background: var(--line); }

/* ───────────────────────────────── RESPONSIVE */
@media (max-width: 600px) {
    .navbar      { padding: 0 16px; }
    .nav-name    { display: none; }
    .role-badge  { display: none; }
    .page        { padding: 24px 14px 52px; }
    .notif-card  { flex-direction: column; gap: 12px; }
    .notif-icon  { width: 34px; height: 34px; }
    .page-header { flex-direction: column; align-items: flex-start; }
}
</style>
</head>
<body>

<!-- ░░ NAVBAR ░░ -->
<nav class="navbar">
    <a href="<?= htmlspecialchars($dashboard) ?>" class="navbar-logo">
        <img src="logo.jpg" alt="Grand Superior">
        <span>Grand Superior</span>
    </a>
    <div class="navbar-right">
        <span class="role-badge <?= $user_type ?>"><?= ucfirst($user_type) ?></span>
        <div class="nav-avatar <?= $user_type ?>"><?= htmlspecialchars(initials($user_name)) ?></div>
        <span class="nav-name"><?= htmlspecialchars($user_name) ?></span>
    </div>
</nav>

<!-- ░░ PAGE ░░ -->
<div class="page">

    <a href="<?= htmlspecialchars($dashboard) ?>" class="back-link">
        <svg viewBox="0 0 24 24"><path d="M19 12H5M11 18l-6-6 6-6"/></svg>
        Back to dashboard
    </a>

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-left">
            <div class="page-header-icon">
                <svg viewBox="0 0 24 24">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                    <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                </svg>
            </div>
            <div>
                <h1>Notifications</h1>
                <p>Service updates, promotions &amp; announcements</p>
            </div>
        </div>
        <div class="count-badge">
            <svg viewBox="0 0 24 24">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
            </svg>
            <?= $total ?> notification<?= $total !== 1 ? 's' : '' ?>
        </div>
    </div>

    <!-- Who is viewing -->
    <div class="user-strip">
        <div class="user-strip-avatar <?= $user_type ?>">
            <?= htmlspecialchars(initials($user_name)) ?>
        </div>
        <div class="user-strip-info">
            <div class="user-strip-name"><?= htmlspecialchars($user_name) ?></div>
            <div class="user-strip-role">Logged in as <?= $user_type ?></div>
        </div>
        <span class="user-strip-tag <?= $user_type ?>"><?= ucfirst($user_type) ?></span>
    </div>

    <?php if ($total > 0): ?>

        <div class="section-label">Latest first</div>

        <?php $i = 1; while ($row = $result->fetch_assoc()): ?>

        <div class="notif-card <?= $user_type === 'rider' ? 'rider-view' : '' ?>">
            <div class="notif-icon">
                <svg viewBox="0 0 24 24">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                    <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                </svg>
            </div>
            <div class="notif-body">
                <div class="notif-title"><?= htmlspecialchars($row['title']) ?></div>
                <div class="notif-message"><?= nl2br(htmlspecialchars($row['message'])) ?></div>
                <div class="notif-footer">
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    <?= timeAgo($row['created_at']) ?>
                    &nbsp;·&nbsp;
                    <?= date('M j, Y · g:i A', strtotime($row['created_at'])) ?>
                    <span class="notif-number <?= $user_type ?>">#<?= $i++ ?></span>
                </div>
            </div>
        </div>

        <?php endwhile; ?>

    <?php else: ?>

        <div class="empty">
            <div class="empty-icon">
                <svg viewBox="0 0 24 24">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                    <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                </svg>
            </div>
            <h3>No Notifications Yet</h3>
            <p>You're all caught up. Check back here for booking<br>updates, promotions and service announcements.</p>
        </div>

    <?php endif; ?>

</div>

</body>
</html>
