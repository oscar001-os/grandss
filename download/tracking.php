<?php
session_start();
include 'connection.php';

if (!isset($_SESSION['client_id'])) {
    header("Location: client_login.php");
    exit();
}

$client_id   = $_SESSION['client_id'];
$client_name = $_SESSION['client_name'] ?? 'Client';

$stmt = mysqli_prepare($conn, "SELECT * FROM bookings WHERE client_id = ? ORDER BY id DESC");
mysqli_stmt_bind_param($stmt, "i", $client_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$orders    = [];
$total     = 0;
$active    = 0;
$delivered = 0;

while ($row = mysqli_fetch_assoc($result)) {
    $orders[] = $row;
    $total++;
    if (strtolower($row['status'] ?? '') === 'delivered') $delivered++;
    else $active++;
}

$STEPS = ['Pending', 'In Progress', 'Ready', 'Delivered'];

function stepIcon(string $step): string {
    $icons = [
        'Pending'     => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
        'In Progress' => 'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15',
        'Ready'       => 'M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4',
        'Delivered'   => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
    ];
    return $icons[$step] ?? $icons['Pending'];
}

function statusBadgeStyle(string $status): array {
    $map = [
        'pending'     => ['bg' => '#fffbeb', 'color' => '#92400e', 'dot' => '#f59e0b'],
        'in progress' => ['bg' => '#eff6ff', 'color' => '#1e40af', 'dot' => '#3b82f6'],
        'ready'       => ['bg' => '#f5f3ff', 'color' => '#5b21b6', 'dot' => '#8b5cf6'],
        'delivered'   => ['bg' => '#ecfdf5', 'color' => '#065f46', 'dot' => '#10b981'],
    ];
    return $map[strtolower($status)] ?? ['bg' => '#f3f4f6', 'color' => '#374151', 'dot' => '#9ca3af'];
}

function fmt(string $d): string { return date('j M Y', strtotime($d)); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Track My Clothes | Grand Superior Drycleaners</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<style>
*, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

body { font-family: 'Inter', sans-serif; background: #f5f7fa; color: #1e293b; min-height: 100vh; }

/* ── Nav ── */
.topnav { background: #0d1b2a; box-shadow: 0 2px 16px rgba(0,0,0,.25); }
.topnav-inner {
    max-width: 1200px; margin: 0 auto; padding: 0 24px;
    height: 64px; display: flex; align-items: center; justify-content: space-between;
}
.brand { display: flex; align-items: center; gap: 12px; text-decoration: none; }
.brand-icon { width: 36px; height: 36px; border-radius: 10px; background: #a6ce39; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.brand-icon svg { width: 18px; height: 18px; }
.brand-name { font-size: 14px; font-weight: 700; color: #fff; line-height: 1.2; }
.brand-sub  { font-size: 9px; font-weight: 600; color: #a6ce39; letter-spacing: .12em; text-transform: uppercase; }
.nav-right  { display: flex; align-items: center; gap: 16px; }
.nav-welcome { font-size: 13px; color: #94a3b8; }
.nav-btn { font-size: 13px; font-weight: 600; color: #fff; background: rgba(255,255,255,.1); border: none; padding: 7px 16px; border-radius: 8px; text-decoration: none; transition: background .2s; }
.nav-btn:hover { background: rgba(255,255,255,.18); }

/* ── Layout ── */
.main { max-width: 1200px; margin: 0 auto; padding: 36px 24px 60px; }

.breadcrumb { display: flex; align-items: center; gap: 6px; font-size: 12px; color: #94a3b8; margin-bottom: 8px; }
.breadcrumb a { color: #94a3b8; text-decoration: none; }
.breadcrumb a:hover { color: #475569; }
.breadcrumb-sep { color: #cbd5e1; }
.breadcrumb-current { color: #475569; font-weight: 500; }
.page-title { font-size: 22px; font-weight: 700; color: #0d1b2a; }
.page-sub   { font-size: 13px; color: #64748b; margin-top: 4px; margin-bottom: 32px; }

/* ── Stats ── */
.stats-row { display: grid; grid-template-columns: repeat(3,1fr); gap: 16px; margin-bottom: 32px; }
.stat-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 18px 20px; display: flex; align-items: center; gap: 16px; box-shadow: 0 1px 4px rgba(0,0,0,.05); }
.stat-icon { width: 44px; height: 44px; border-radius: 10px; background: #f8fafc; border: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.stat-icon svg { width: 20px; height: 20px; }
.stat-icon.blue  { background: #eff6ff; border-color: #bfdbfe; }
.stat-icon.green { background: #ecfdf5; border-color: #a7f3d0; }
.stat-val   { font-size: 26px; font-weight: 700; color: #0d1b2a; line-height: 1; }
.stat-label { font-size: 11px; color: #94a3b8; font-weight: 500; margin-top: 4px; text-transform: uppercase; letter-spacing: .05em; }

/* ── Order cards ── */
.order-list  { display: flex; flex-direction: column; gap: 20px; }
.order-card  { background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; overflow: hidden; box-shadow: 0 1px 8px rgba(0,0,0,.06); }

.card-head   { padding: 16px 24px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
.card-head-left { display: flex; align-items: center; gap: 12px; }
.card-icon   { width: 36px; height: 36px; border-radius: 10px; background: #f8fafc; border: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.card-icon svg { width: 16px; height: 16px; }
.card-id     { font-weight: 700; font-size: 15px; color: #0d1b2a; }
.card-id span { font-weight: 400; font-size: 12px; color: #94a3b8; }
.card-service { font-size: 12px; color: #64748b; margin-top: 2px; display: flex; align-items: center; gap: 5px; }
.service-dot { width: 6px; height: 6px; border-radius: 50%; background: #a6ce39; display: inline-block; }

.badge { display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px; border-radius: 9999px; font-size: 12px; font-weight: 600; }
.badge-dot { width: 6px; height: 6px; border-radius: 50%; }

/* ── Detail grid ── */
.detail-grid { display: grid; grid-template-columns: repeat(4,1fr); gap: 12px; padding: 16px 24px; }
.detail-box  { background: #f8fafc; border-radius: 10px; padding: 12px; }
.detail-lbl  { display: flex; align-items: center; gap: 5px; font-size: 10px; font-weight: 600; color: #94a3b8; text-transform: uppercase; letter-spacing: .06em; margin-bottom: 6px; }
.detail-lbl svg { width: 12px; height: 12px; }
.detail-val  { font-size: 12px; color: #334155; font-weight: 500; line-height: 1.4; }

/* ── Progress ── */
.progress-wrap { padding: 8px 24px 24px; }
.progress-meta { display: flex; justify-content: space-between; font-size: 11px; color: #94a3b8; font-weight: 500; margin-bottom: 6px; }
.progress-pct  { font-weight: 700; color: #0d1b2a; }
.progress-bar  { width: 100%; height: 6px; background: #f1f5f9; border-radius: 9999px; overflow: hidden; }
.progress-fill { height: 100%; background: #a6ce39; border-radius: 9999px; }

/* Timeline */
.timeline     { display: flex; align-items: flex-start; margin-top: 20px; position: relative; }
.timeline::before { content: ''; position: absolute; top: 18px; left: 18px; right: 18px; height: 2px; background: #f1f5f9; z-index: 0; }
.tl-step      { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 8px; position: relative; z-index: 1; }
.tl-circle    { width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
.tl-circle.done   { background: #0d1b2a; }
.tl-circle.active { background: #0d1b2a; box-shadow: 0 0 0 4px rgba(166,206,57,.3); }
.tl-circle.idle   { background: #fff; border: 2px solid #e2e8f0; }
.tl-circle svg { width: 14px; height: 14px; }
.tl-label     { font-size: 11px; font-weight: 600; text-align: center; line-height: 1.3; }
.tl-label.done   { color: #64748b; }
.tl-label.active { color: #0d1b2a; }
.tl-label.idle   { color: #cbd5e1; }

/* Empty */
.empty-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 64px 24px; text-align: center; box-shadow: 0 1px 8px rgba(0,0,0,.06); }
.empty-icon { width: 52px; height: 52px; border-radius: 50%; background: #f1f5f9; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; }
.empty-icon svg { width: 24px; height: 24px; stroke: #94a3b8; }
.empty-title { font-size: 15px; font-weight: 600; color: #475569; }
.empty-sub   { font-size: 13px; color: #94a3b8; margin-top: 4px; }

/* Back */
.back-link { display: inline-flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 500; color: #64748b; text-decoration: none; margin-top: 28px; transition: color .2s; }
.back-link:hover { color: #0d1b2a; }
.back-link svg { width: 15px; height: 15px; }

/* Responsive */
@media(max-width: 900px) {
    .detail-grid { grid-template-columns: repeat(2,1fr); }
}
@media(max-width: 768px) {
    .main { padding: 24px 16px 48px; }
    .stats-row { grid-template-columns: 1fr; gap: 12px; }
    .nav-welcome { display: none; }
    .page-title  { font-size: 20px; }
    .detail-grid { grid-template-columns: repeat(2,1fr); gap: 8px; padding: 12px 16px; }
    .card-head   { padding: 14px 16px; }
    .progress-wrap { padding: 8px 16px 20px; }
}
</style>
</head>
<body>

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
            <span class="nav-welcome">Welcome, <?php echo htmlspecialchars($client_name); ?></span>
            <a href="client_dashboard.php" class="nav-btn">Dashboard</a>
        </div>
    </div>
</nav>

<main class="main">

    <div class="breadcrumb">
        <a href="client_dashboard.php">Dashboard</a>
        <span class="breadcrumb-sep">›</span>
        <span class="breadcrumb-current">Track My Clothes</span>
    </div>

    <h1 class="page-title">Track My Clothes</h1>
    <p class="page-sub">Follow the live progress of each laundry order.</p>

    <!-- Stats -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="#475569" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/>
                </svg>
            </div>
            <div><div class="stat-val"><?php echo $total; ?></div><div class="stat-label">Total Orders</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon blue">
                <svg viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
            </div>
            <div><div class="stat-val"><?php echo $active; ?></div><div class="stat-label">In Progress</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green">
                <svg viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div><div class="stat-val"><?php echo $delivered; ?></div><div class="stat-label">Delivered</div></div>
        </div>
    </div>

    <?php if (empty($orders)): ?>

    <div class="empty-card">
        <div class="empty-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                <path d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/>
            </svg>
        </div>
        <p class="empty-title">No orders to track</p>
        <p class="empty-sub">Place a booking and your order progress will appear here.</p>
    </div>

    <?php else: ?>

    <div class="order-list">

    <?php foreach ($orders as $row):
        $status      = $row['status'] ?? 'Pending';
        $currentStep = array_search($status, $STEPS);
        if ($currentStep === false) $currentStep = 0;
        $pct = (int) round(($currentStep / (count($STEPS) - 1)) * 100);
        $bStyle = statusBadgeStyle($status);
    ?>

        <div class="order-card">

            <!-- Card header -->
            <div class="card-head">
                <div class="card-head-left">
                    <div class="card-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="#475569" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/>
                        </svg>
                    </div>
                    <div>
                        <div class="card-id"><span>#</span><?php echo $row['id']; ?></div>
                        <div class="card-service">
                            <span class="service-dot"></span>
                            <?php echo htmlspecialchars($row['service_type']); ?>
                        </div>
                    </div>
                </div>
                <span class="badge" style="background:<?php echo $bStyle['bg']; ?>;color:<?php echo $bStyle['color']; ?>">
                    <span class="badge-dot" style="background:<?php echo $bStyle['dot']; ?>"></span>
                    <?php echo htmlspecialchars($status); ?>
                </span>
            </div>

            <!-- Details -->
            <div class="detail-grid">
                <?php
                $details = [
                    ['Pickup',   fmt($row['pickup_date']),   'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'],
                    ['Delivery', fmt($row['delivery_date']), 'M20 7l-8 4-8-4m0 6l8 4 8-4m0-6v12'],
                    ['Address',  $row['address'],             'M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z'],
                    ['Notes',    $row['notes'] ?: '—',       'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z'],
                ];
                foreach ($details as [$lbl, $val, $path]):
                ?>
                <div class="detail-box">
                    <div class="detail-lbl">
                        <svg viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                            <path d="<?php echo $path; ?>"/>
                        </svg>
                        <?php echo $lbl; ?>
                    </div>
                    <div class="detail-val"><?php echo htmlspecialchars($val); ?></div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Progress -->
            <div class="progress-wrap">
                <div class="progress-meta">
                    <span>Order Progress</span>
                    <span class="progress-pct"><?php echo $pct; ?>%</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width:<?php echo $pct; ?>%"></div>
                </div>

                <!-- Timeline steps -->
                <div class="timeline">
                    <?php foreach ($STEPS as $i => $step):
                        $done   = $i <= $currentStep;
                        $active = $i === $currentStep;
                        $cls    = $active ? 'active' : ($done ? 'done' : 'idle');
                        $icon   = stepIcon($step);
                    ?>
                    <div class="tl-step">
                        <div class="tl-circle <?php echo $cls; ?>">
                            <?php if ($done): ?>
                            <svg viewBox="0 0 24 24" fill="none" stroke="#a6ce39" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="<?php echo $icon; ?>"/>
                            </svg>
                            <?php else: ?>
                            <svg viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <path d="<?php echo $icon; ?>"/>
                            </svg>
                            <?php endif; ?>
                        </div>
                        <span class="tl-label <?php echo $cls; ?>"><?php echo $step; ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>

    <?php endforeach; ?>

    </div>
    <?php endif; ?>

    <a href="client_dashboard.php" class="back-link">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="15 18 9 12 15 6"/>
        </svg>
        Back to Dashboard
    </a>

</main>

</body>
</html>