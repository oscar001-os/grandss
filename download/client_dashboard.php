<?php
session_start();
include 'connection.php';

if (!isset($_SESSION['client_id'])) {
    header("Location: client_login.php");
    exit();
}

$client_id = $_SESSION['client_id'];

$stmt = $conn->prepare("SELECT name, email FROM clients WHERE id = ?");
$stmt->bind_param("i", $client_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $client = $result->fetch_assoc();
} else {
    session_destroy();
    header("Location: client_login.php");
    exit();
}

$stmt->close();

// Generate initials
$name_parts = explode(' ', trim($client['name']));
$initials = strtoupper(substr($name_parts[0], 0, 1));
if (count($name_parts) > 1) {
    $initials .= strtoupper(substr(end($name_parts), 0, 1));
}

// Time-based greeting
$hour = (int) date('G');
if ($hour < 12) {
    $greeting = 'Good morning';
} elseif ($hour < 17) {
    $greeting = 'Good afternoon';
} else {
    $greeting = 'Good evening';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard — Grand Superior</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">

<style>
*, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }

:root {
    --dark:   #0d1b2a;
    --accent: #a6ce39;
    --bg:     #f4f5f7;
    --white:  #ffffff;
    --surf2:  #f8f9fb;
    --border: rgba(0,0,0,0.08);
    --bh:     rgba(0,0,0,0.16);
    --t1:     #111827;
    --t2:     #6b7280;
    --t3:     #9ca3af;
    --rmd:    8px;
    --rlg:    12px;
    --rxl:    16px;
}

body { font-family:'Inter',sans-serif; background:var(--bg); color:var(--t1); min-height:100vh; font-size:14px; line-height:1.6; -webkit-font-smoothing:antialiased; }
a { text-decoration:none; color:inherit; }

/* TOPBAR */
.topbar { position:sticky; top:0; z-index:200; background:var(--dark); height:64px; display:flex; align-items:center; justify-content:space-between; padding:0 28px; border-bottom:1px solid rgba(255,255,255,0.06); }
.brand { display:flex; align-items:center; gap:10px; }
.brand-icon { width:36px; height:36px; border-radius:var(--rmd); background:rgba(166,206,57,0.15); display:flex; align-items:center; justify-content:center; }
.brand-icon i { font-size:18px; color:var(--accent); }
.brand-name { font-size:15px; font-weight:600; color:#fff; }
.brand-name span { color:var(--accent); }

.nav { display:flex; align-items:center; gap:2px; }
.nav a { display:flex; align-items:center; gap:6px; padding:7px 12px; border-radius:var(--rmd); font-size:13px; font-weight:500; color:rgba(255,255,255,0.65); transition:background .15s, color .15s; white-space:nowrap; }
.nav a:hover { background:rgba(255,255,255,0.08); color:#fff; }
.nav a i { font-size:15px; }
.nav a.logout { color:#fc8181; margin-left:6px; }
.nav a.logout:hover { background:rgba(252,129,129,0.12); }

.menu-btn { display:none; background:none; border:none; cursor:pointer; color:#fff; padding:6px; border-radius:var(--rmd); }
.menu-btn i { font-size:22px; display:block; }

/* MOBILE DRAWER */
.mobile-drawer { display:none; position:fixed; inset:64px 0 0 0; background:var(--dark); z-index:150; padding:16px; flex-direction:column; gap:4px; border-top:1px solid rgba(255,255,255,0.06); overflow-y:auto; }
.mobile-drawer.open { display:flex; }
.mobile-drawer a { display:flex; align-items:center; gap:10px; padding:12px 14px; border-radius:var(--rlg); font-size:14px; font-weight:500; color:rgba(255,255,255,0.7); transition:background .15s, color .15s; }
.mobile-drawer a:hover { background:rgba(255,255,255,0.07); color:#fff; }
.mobile-drawer a i { font-size:18px; }
.mobile-drawer a.logout { color:#fc8181; margin-top:8px; }

/* PAGE */
.page { max-width:1100px; margin:0 auto; padding:28px 24px 48px; }

/* HERO */
.hero { background:var(--dark); border-radius:var(--rxl); padding:28px 32px; display:flex; align-items:center; justify-content:space-between; gap:20px; margin-bottom:24px; }
.hero-tag { display:inline-flex; align-items:center; gap:5px; background:rgba(166,206,57,0.15); color:var(--accent); font-size:11px; font-weight:500; padding:4px 10px; border-radius:20px; margin-bottom:10px; }
.hero-tag i { font-size:12px; }
.hero h1 { font-size:22px; font-weight:600; color:#fff; margin-bottom:5px; }
.hero-meta { font-size:13px; color:rgba(255,255,255,0.45); }
.hero-avatar { width:56px; height:56px; border-radius:50%; background:rgba(166,206,57,0.18); border:2px solid rgba(166,206,57,0.35); display:flex; align-items:center; justify-content:center; font-size:18px; font-weight:600; color:var(--accent); flex-shrink:0; }

/* STATS */
.stats { display:grid; grid-template-columns:repeat(3,1fr); gap:14px; margin-bottom:28px; }
.stat { background:var(--white); border:0.5px solid var(--border); border-radius:var(--rlg); padding:18px 20px; }
.stat-label { font-size:12px; font-weight:500; color:var(--t3); text-transform:uppercase; letter-spacing:0.05em; margin-bottom:8px; }
.stat-value { font-size:22px; font-weight:600; color:var(--t1); margin-bottom:4px; }
.stat-sub { display:flex; align-items:center; gap:5px; font-size:12px; color:var(--t2); }
.dot { width:7px; height:7px; border-radius:50%; flex-shrink:0; }
.dot-green { background:#22c55e; }
.dot-amber { background:#f59e0b; }

/* SECTION LABEL */
.section-label { font-size:11px; font-weight:600; color:var(--t3); text-transform:uppercase; letter-spacing:0.07em; margin-bottom:14px; }

/* GRID */
.grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:14px; }

/* CARD */
.card { background:var(--white); border:0.5px solid var(--border); border-radius:var(--rlg); padding:20px; display:flex; flex-direction:column; transition:border-color .15s, transform .15s, box-shadow .15s; cursor:pointer; }
.card:hover { border-color:var(--bh); transform:translateY(-2px); box-shadow:0 4px 16px rgba(0,0,0,0.08); }
.card-icon { width:40px; height:40px; border-radius:var(--rmd); display:flex; align-items:center; justify-content:center; font-size:19px; margin-bottom:14px; }
.icon-green  { background:#f0fdf4; color:#16a34a; }
.icon-blue   { background:#eff6ff; color:#2563eb; }
.icon-amber  { background:#fffbeb; color:#d97706; }
.icon-purple { background:#f5f3ff; color:#7c3aed; }
.icon-teal   { background:#f0fdfa; color:#0d9488; }
.icon-coral  { background:#fff7ed; color:#ea580c; }
.icon-pink   { background:#fdf2f8; color:#db2777; }
.card h3 { font-size:14px; font-weight:600; color:var(--t1); margin-bottom:5px; }
.card p  { font-size:12px; color:var(--t2); line-height:1.55; margin-bottom:16px; flex:1; }
.card-link { display:inline-flex; align-items:center; gap:5px; font-size:12px; font-weight:600; border-radius:20px; padding:6px 13px; align-self:flex-start; transition:opacity .15s; }
.card-link:hover { opacity:0.85; }
.card-link i { font-size:13px; }
.cta-primary { background:var(--accent); color:#0d1b2a; }
.cta-ghost   { background:var(--surf2); color:var(--t1); border:0.5px solid var(--border); }

/* RESPONSIVE */
@media(max-width:900px){ .stats { grid-template-columns:repeat(2,1fr); } }
@media(max-width:680px){
    .topbar { padding:0 16px; }
    .nav { display:none; }
    .menu-btn { display:flex; align-items:center; justify-content:center; }
    .page { padding:20px 16px 40px; }
    .hero { padding:22px 20px; }
    .hero h1 { font-size:18px; }
    .stats { grid-template-columns:1fr; }
    .grid { grid-template-columns:1fr 1fr; }
}
@media(max-width:420px){
    .grid { grid-template-columns:1fr; }
    .hero-avatar { display:none; }
}
</style>
</head>
<body>

<!-- TOPBAR -->
<header class="topbar">
    <div class="brand">
        <div class="brand-icon"><i class="ti ti-shirt"></i></div>
        <div class="brand-name">Grand <span>Superior</span></div>
    </div>

    <nav class="nav">
        <a href="book_laundry.php"><i class="ti ti-calendar-plus"></i> Book laundry</a>
        <a href="view_bookings.php"><i class="ti ti-list-check"></i> Bookings</a>
        <a href="payment_history.php"><i class="ti ti-credit-card"></i> Payments</a>
        <a href="edit_profile.php"><i class="ti ti-user-circle"></i> Profile</a>
        <a href="logout.php" class="logout"><i class="ti ti-logout"></i> Logout</a>
    </nav>

    <button class="menu-btn" onclick="toggleMenu()" id="menuBtn" aria-label="Toggle menu">
        <i class="ti ti-menu-2" id="menuIcon"></i>
    </button>
</header>

<!-- MOBILE DRAWER -->
<nav class="mobile-drawer" id="mobileDrawer">
    <a href="book_laundry.php"><i class="ti ti-calendar-plus"></i> Book laundry</a>
    <a href="view_bookings.php"><i class="ti ti-list-check"></i> My bookings</a>
    <a href="payment_history.php"><i class="ti ti-credit-card"></i> Payments</a>
    <a href="edit_profile.php"><i class="ti ti-user-circle"></i> Edit profile</a>
    <a href="pickup_schedule.php"><i class="ti ti-clock"></i> Pickup scheduling</a>
    <a href="tracking.php"><i class="ti ti-map-pin"></i> Order tracking</a>
    <a href="view_notifications.php"><i class="ti ti-bell"></i> Notifications</a>
    <a href="logout.php" class="logout"><i class="ti ti-logout"></i> Logout</a>
</nav>

<!-- PAGE -->
<main class="page">

    <!-- HERO -->
    <section class="hero">
        <div>
            <div class="hero-tag">
                <i class="ti ti-sparkles"></i>
                <?php echo htmlspecialchars($greeting); ?>
            </div>
            <h1>Welcome back, <?php echo htmlspecialchars($client['name']); ?> 👋</h1>
            <p class="hero-meta"><?php echo htmlspecialchars($client['email']); ?></p>
        </div>
        <div class="hero-avatar"><?php echo htmlspecialchars($initials); ?></div>
    </section>

    <!-- STATS -->
    <div class="stats">
        <div class="stat">
            <div class="stat-label">Active orders</div>
            <div class="stat-value">—</div>
            <div class="stat-sub"><span class="dot dot-green"></span> In progress</div>
        </div>
        <div class="stat">
            <div class="stat-label">Total spent</div>
            <div class="stat-value">—</div>
            <div class="stat-sub">This month</div>
        </div>
        <div class="stat">
            <div class="stat-label">Next pickup</div>
            <div class="stat-value">—</div>
            <div class="stat-sub"><span class="dot dot-amber"></span> Scheduled</div>
        </div>
    </div>

    <!-- CARDS -->
    <div class="section-label">Quick actions</div>
    <div class="grid">

        <div class="card" onclick="location.href='book_laundry.php'">
            <div class="card-icon icon-green"><i class="ti ti-calendar-plus"></i></div>
            <h3>Book laundry</h3>
            <p>Schedule a pickup and delivery at your convenience.</p>
            <a href="book_laundry.php" class="card-link cta-primary">Book now <i class="ti ti-arrow-right"></i></a>
        </div>

        <div class="card" onclick="location.href='view_bookings.php'">
            <div class="card-icon icon-blue"><i class="ti ti-list-check"></i></div>
            <h3>View bookings</h3>
            <p>Track your laundry orders and their current status.</p>
            <a href="view_bookings.php" class="card-link cta-primary">View <i class="ti ti-arrow-right"></i></a>
        </div>

        <div class="card" onclick="location.href='payment_history.php'">
            <div class="card-icon icon-amber"><i class="ti ti-credit-card"></i></div>
            <h3>Payments</h3>
            <p>Check invoices and your full payment history.</p>
            <a href="payment_history.php" class="card-link cta-primary">Payments <i class="ti ti-arrow-right"></i></a>
        </div>

        <div class="card" onclick="location.href='edit_profile.php'">
            <div class="card-icon icon-purple"><i class="ti ti-user-circle"></i></div>
            <h3>Manage profile</h3>
            <p>Update your personal details and preferences.</p>
            <a href="edit_profile.php" class="card-link cta-ghost">Edit <i class="ti ti-arrow-right"></i></a>
        </div>

        <div class="card" onclick="location.href='pickup_schedule.php'">
            <div class="card-icon icon-teal"><i class="ti ti-clock"></i></div>
            <h3>Pickup scheduling</h3>
            <p>Choose convenient pickup times for your clothes.</p>
            <a href="pickup_schedule.php" class="card-link cta-ghost">View <i class="ti ti-arrow-right"></i></a>
        </div>

        <div class="card" onclick="location.href='tracking.php'">
            <div class="card-icon icon-coral"><i class="ti ti-map-pin"></i></div>
            <h3>Order tracking</h3>
            <p>Track your clothes from pickup all the way to delivery.</p>
            <a href="tracking.php" class="card-link cta-ghost">Track <i class="ti ti-arrow-right"></i></a>
        </div>

        <div class="card" onclick="location.href='view_notifications.php'">
            <div class="card-icon icon-pink"><i class="ti ti-bell"></i></div>
            <h3>Notifications</h3>
            <p>Get real-time updates on your laundry status.</p>
            <a href="view_notifications.php" class="card-link cta-ghost">View <i class="ti ti-arrow-right"></i></a>
        </div>

    </div>

</main>

<script>
function toggleMenu() {
    var drawer = document.getElementById('mobileDrawer');
    var icon   = document.getElementById('menuIcon');
    var open   = drawer.classList.toggle('open');
    icon.className = open ? 'ti ti-x' : 'ti ti-menu-2';
}
document.querySelectorAll('.mobile-drawer a').forEach(function(a) {
    a.addEventListener('click', function() {
        document.getElementById('mobileDrawer').classList.remove('open');
        document.getElementById('menuIcon').className = 'ti ti-menu-2';
    });
});
</script>

</body>
</html>
