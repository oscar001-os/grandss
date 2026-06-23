<?php
session_start();
include 'connection.php';

if (!isset($_SESSION['client_id'])) {
    header("Location: client_login.php");
    exit();
}

$client_id   = $_SESSION['client_id'];
$client_name = $_SESSION['client_name'] ?? 'Client';

$stmt = mysqli_prepare($conn, "
    SELECT id, service_type, pickup_date, delivery_date, address, notes, status
    FROM bookings
    WHERE client_id = ?
    ORDER BY pickup_date ASC
");
mysqli_stmt_bind_param($stmt, "i", $client_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$schedules  = [];
$total      = 0;
$upcoming   = 0;
$completed  = 0;

while ($row = mysqli_fetch_assoc($result)) {
    $schedules[] = $row;
    $total++;
    if (strtolower($row['status'] ?? '') === 'completed') {
        $completed++;
    } else {
        $upcoming++;
    }
}

function statusBadge(string $status): string {
    switch (strtolower($status)) {
        case 'upcoming':
            return '<span class="badge badge-upcoming"><span class="badge-dot"></span>Upcoming</span>';
        case 'completed':
            return '<span class="badge badge-completed"><span class="badge-dot"></span>Completed</span>';
        default:
            return '<span class="badge badge-default"><span class="badge-dot"></span>' . htmlspecialchars(ucfirst($status)) . '</span>';
    }
}

function timelineIcon(string $status): string {
    if (strtolower($status) === 'completed') {
        return '<div class="tl-icon tl-icon--completed">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
            </svg>
        </div>';
    }
    return '<div class="tl-icon tl-icon--upcoming">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
        </svg>
    </div>';
}

function fmt(string $d): string {
    return date('j M Y', strtotime($d));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pickup Schedule | Grand Superior Drycleaners</title>
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
    max-width: 1200px; margin: 0 auto; padding: 0 24px;
    height: 64px; display: flex; align-items: center; justify-content: space-between;
}
.brand { display: flex; align-items: center; gap: 12px; text-decoration: none; }
.brand-icon {
    width: 36px; height: 36px; border-radius: 10px; background: #a6ce39;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.brand-icon svg { width: 18px; height: 18px; }
.brand-name { font-size: 14px; font-weight: 700; color: #fff; line-height: 1.2; }
.brand-sub  { font-size: 9px; font-weight: 600; color: #a6ce39; letter-spacing: .12em; text-transform: uppercase; }
.nav-right  { display: flex; align-items: center; gap: 16px; }
.nav-welcome { font-size: 13px; color: #94a3b8; }
.nav-btn {
    font-size: 13px; font-weight: 600; color: #fff;
    background: rgba(255,255,255,.1); border: none;
    padding: 7px 16px; border-radius: 8px; text-decoration: none;
    transition: background .2s;
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

/* ── Stats ── */
.stats-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 32px; }
.stat-card {
    background: #fff; border: 1px solid #e2e8f0; border-radius: 14px;
    padding: 18px 20px; display: flex; align-items: center; gap: 16px;
    box-shadow: 0 1px 4px rgba(0,0,0,.05);
}
.stat-icon {
    width: 44px; height: 44px; border-radius: 10px;
    background: #f8fafc; border: 1px solid #e2e8f0;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.stat-icon svg { width: 20px; height: 20px; }
.stat-icon.blue  { background: #eff6ff; border-color: #bfdbfe; }
.stat-icon.green { background: #ecfdf5; border-color: #a7f3d0; }
.stat-val   { font-size: 26px; font-weight: 700; color: #0d1b2a; line-height: 1; }
.stat-label { font-size: 11px; color: #94a3b8; font-weight: 500; margin-top: 4px; text-transform: uppercase; letter-spacing: .05em; }

/* ── Table card ── */
.table-card {
    background: #fff; border: 1px solid #e2e8f0;
    border-radius: 16px; overflow: hidden;
    box-shadow: 0 1px 8px rgba(0,0,0,.06);
}
.table-head {
    padding: 16px 24px; border-bottom: 1px solid #f1f5f9;
    display: flex; align-items: center; justify-content: space-between;
}
.table-head-title { font-size: 14px; font-weight: 600; color: #0d1b2a; }
.pill-group { display: flex; gap: 8px; }
.pill {
    display: inline-flex; align-items: center; gap: 5px;
    font-size: 12px; font-weight: 500; padding: 4px 10px; border-radius: 9999px;
}
.pill-blue  { background: #eff6ff; color: #1d4ed8; }
.pill-green { background: #ecfdf5; color: #065f46; }
.pill-dot   { width: 6px; height: 6px; border-radius: 50%; }
.pill-blue  .pill-dot { background: #3b82f6; }
.pill-green .pill-dot { background: #10b981; }

.table-wrap { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; min-width: 860px; font-size: 13px; }
thead tr { background: #f8fafc; }
th {
    padding: 11px 20px; text-align: left;
    font-size: 11px; font-weight: 600; color: #94a3b8;
    text-transform: uppercase; letter-spacing: .06em;
    border-bottom: 1px solid #f1f5f9; white-space: nowrap;
}
td { padding: 14px 20px; color: #475569; border-bottom: 1px solid #f8fafc; vertical-align: middle; }
tbody tr:last-child td { border-bottom: none; }
tbody tr:hover td { background: #fafbff; }

/* Timeline icon */
.tl-cell { display: flex; align-items: center; gap: 10px; }
.tl-icon {
    width: 32px; height: 32px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.tl-icon svg { width: 14px; height: 14px; }
.tl-icon--upcoming  { background: #eff6ff; color: #3b82f6; }
.tl-icon--completed { background: #ecfdf5; color: #10b981; }
.tl-icon--upcoming  svg { stroke: #3b82f6; }
.tl-icon--completed svg { stroke: #10b981; }

.td-id { font-weight: 700; color: #0d1b2a; white-space: nowrap; }
.td-id-hash { font-weight: 400; font-size: 11px; color: #94a3b8; }

.service-cell { display: inline-flex; align-items: center; gap: 7px; font-weight: 500; color: #334155; white-space: nowrap; }
.service-dot  { width: 7px; height: 7px; border-radius: 50%; background: #a6ce39; flex-shrink: 0; }

.date-primary { font-weight: 500; color: #334155; white-space: nowrap; }

.truncate-cell { max-width: 170px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.notes-cell    { max-width: 160px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-style: italic; color: #94a3b8; }

/* Badges */
.badge {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 4px 10px; border-radius: 9999px;
    font-size: 12px; font-weight: 600; white-space: nowrap;
}
.badge-dot { width: 6px; height: 6px; border-radius: 50%; }
.badge-upcoming  { background: #eff6ff; color: #1d4ed8; }
.badge-upcoming  .badge-dot { background: #3b82f6; }
.badge-completed { background: #ecfdf5; color: #065f46; }
.badge-completed .badge-dot { background: #10b981; }
.badge-default   { background: #f3f4f6; color: #374151; }
.badge-default   .badge-dot { background: #9ca3af; }

/* Empty */
.empty-state { padding: 64px 24px; text-align: center; }
.empty-icon {
    width: 52px; height: 52px; border-radius: 50%; background: #f1f5f9;
    display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;
}
.empty-icon svg { width: 24px; height: 24px; stroke: #94a3b8; }
.empty-title { font-size: 15px; font-weight: 600; color: #475569; }
.empty-sub   { font-size: 13px; color: #94a3b8; margin-top: 4px; }

/* Footer */
.table-foot {
    padding: 11px 24px; border-top: 1px solid #f1f5f9;
    display: flex; align-items: center; justify-content: space-between;
    font-size: 12px; color: #94a3b8;
}
.table-foot-brand { font-weight: 600; color: #a6ce39; }

/* Back link */
.back-link {
    display: inline-flex; align-items: center; gap: 6px;
    font-size: 13px; font-weight: 500; color: #64748b;
    text-decoration: none; margin-top: 24px; transition: color .2s;
}
.back-link:hover { color: #0d1b2a; }
.back-link svg { width: 15px; height: 15px; }

/* Responsive */
@media (max-width: 768px) {
    .main { padding: 24px 16px 48px; }
    .stats-row { grid-template-columns: 1fr; gap: 12px; }
    .nav-welcome { display: none; }
    .pill-group  { display: none; }
    .page-title  { font-size: 20px; }
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
        <span class="breadcrumb-current">Pickup Schedule</span>
    </div>

    <h1 class="page-title">My Pickup Schedule</h1>
    <p class="page-sub">Track all your scheduled laundry pickups and deliveries.</p>

    <!-- Stats -->
    <div class="stats-row">

        <div class="stat-card">
            <div class="stat-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="#475569" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
            </div>
            <div>
                <div class="stat-val"><?php echo $total; ?></div>
                <div class="stat-label">Total Schedules</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon blue">
                <svg viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                </svg>
            </div>
            <div>
                <div class="stat-val"><?php echo $upcoming; ?></div>
                <div class="stat-label">Upcoming</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon green">
                <svg viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                </svg>
            </div>
            <div>
                <div class="stat-val"><?php echo $completed; ?></div>
                <div class="stat-label">Completed</div>
            </div>
        </div>

    </div>

    <!-- Table -->
    <div class="table-card">

        <div class="table-head">
            <span class="table-head-title">All Schedules</span>
            <div class="pill-group">
                <span class="pill pill-blue"><span class="pill-dot"></span><?php echo $upcoming; ?> upcoming</span>
                <span class="pill pill-green"><span class="pill-dot"></span><?php echo $completed; ?> completed</span>
            </div>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Booking</th>
                        <th>Service</th>
                        <th>Pickup</th>
                        <th>Delivery</th>
                        <th>Address</th>
                        <th>Notes</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($schedules)): ?>
                    <tr>
                        <td colspan="7">
                            <div class="empty-state">
                                <div class="empty-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                </div>
                                <p class="empty-title">No pickup schedules found</p>
                                <p class="empty-sub">Your scheduled pickups will appear here once booked.</p>
                            </div>
                        </td>
                    </tr>
                <?php else: foreach ($schedules as $row): ?>
                    <tr>
                        <td>
                            <div class="tl-cell">
                                <?php echo timelineIcon($row['status'] ?? ''); ?>
                                <span class="td-id">
                                    <span class="td-id-hash">#</span><?php echo $row['id']; ?>
                                </span>
                            </div>
                        </td>
                        <td>
                            <span class="service-cell">
                                <span class="service-dot"></span>
                                <?php echo htmlspecialchars($row['service_type']); ?>
                            </span>
                        </td>
                        <td><span class="date-primary"><?php echo fmt($row['pickup_date']); ?></span></td>
                        <td><span class="date-primary"><?php echo fmt($row['delivery_date']); ?></span></td>
                        <td>
                            <span class="truncate-cell" title="<?php echo htmlspecialchars($row['address']); ?>">
                                <?php echo htmlspecialchars($row['address']); ?>
                            </span>
                        </td>
                        <td>
                            <span class="notes-cell" title="<?php echo htmlspecialchars($row['notes']); ?>">
                                <?php echo $row['notes'] ? htmlspecialchars($row['notes']) : '—'; ?>
                            </span>
                        </td>
                        <td><?php echo statusBadge($row['status'] ?? 'Upcoming'); ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <?php if (!empty($schedules)): ?>
        <div class="table-foot">
            <span>Showing <?php echo $total; ?> of <?php echo $total; ?> schedules</span>
            <span class="table-foot-brand">Grand Superior Drycleaners</span>
        </div>
        <?php endif; ?>

    </div>

    <a href="client_dashboard.php" class="back-link">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="15 18 9 12 15 6"/>
        </svg>
        Back to Dashboard
    </a>

</main>

</body>
</html>