<?php
session_start();
include("connection.php");
if (!isset($_SESSION['owner_id']) && !isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: view_clients.php");
    exit();
}
$client_id = intval($_GET['id']);
/* FETCH CLIENT */
$stmt = $conn->prepare("SELECT * FROM clients WHERE id = ?");
$stmt->bind_param("i", $client_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    header("Location: view_clients.php");
    exit();
}
$client = $result->fetch_assoc();
$stmt->close();
/* BOOKING STATS */
$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM bookings WHERE client_id = ?");
$stmt->bind_param("i", $client_id);
$stmt->execute();
$booking_count = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();
/* PAYMENT STATS */
$stmt = $conn->prepare("SELECT COUNT(*) AS total, COALESCE(SUM(amount),0) AS sum FROM payments WHERE client_id = ?");
$stmt->bind_param("i", $client_id);
$stmt->execute();
$pay = $stmt->get_result()->fetch_assoc();
$stmt->close();
$payment_count = $pay['total'];
$payment_total = $pay['sum'];
/* LATEST BOOKINGS */
$stmt = $conn->prepare("SELECT id, service_type, status, created_at FROM bookings WHERE client_id = ? ORDER BY id DESC LIMIT 5");
$stmt->bind_param("i", $client_id);
$stmt->execute();
$bookings_recent = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
/* INITIALS */
$parts    = explode(' ', trim($client['name']));
$initials = strtoupper(substr($parts[0],0,1) . (isset($parts[1]) ? substr($parts[1],0,1) : ''));
function statusBadge($status) {
    $s = strtolower(str_replace(' ','_',trim($status)));
    $map = [
        'pending'     => ['#fffbeb','#b45309','#fde68a','Pending'],
        'confirmed'   => ['#eff6ff','#1d4ed8','#bfdbfe','Confirmed'],
        'picked'      => ['#f5f3ff','#6d28d9','#ddd6fe','Picked Up'],
        'in_progress' => ['#ecfeff','#0e7490','#a5f3fc','In Progress'],
        'delivered'   => ['#f0fdf4','#15803d','#bbf7d0','Delivered'],
        'done'        => ['#ecfdf5','#065f46','#a7f3d0','Done'],
        'completed'   => ['#ecfdf5','#065f46','#a7f3d0','Completed'],
        'cancelled'   => ['#fef2f2','#b91c1c','#fecaca','Cancelled'],
    ];
    [$bg,$color,$border,$label] = $map[$s] ?? ['#f3f4f6','#374151','#d1d5db', ucfirst($status)];
    return "<span style=\"display:inline-flex;align-items:center;gap:5px;padding:4px 11px;border-radius:100px;font-size:11px;font-weight:600;background:$bg;color:$color;border:1px solid $border\">$label</span>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Client Details — Grand Superior</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
:root{
  --bg:#f4f7f2; --surface:#fff; --surface2:#f8fbf4; --surface3:#f0f5ea;
  --border:#ddeec8; --border-soft:#eaf3da;
  --accent:#a6ce39; --accent-dark:#8ab530; --accent-dim:#f2f9e4;
  --primary:#0d1b2a; --text:#1a2a14; --muted:#6b7e5a; --text-dim:#4a5e38;
  --shadow-sm:0 1px 4px rgba(0,0,0,0.07);
  --shadow:0 4px 18px rgba(0,0,0,0.09);
  --shadow-lg:0 8px 36px rgba(0,0,0,0.12);
}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);color:var(--text);min-height:100vh}
a{text-decoration:none;color:inherit}
/* NAVBAR */
.navbar{background:var(--primary);padding:0 28px;height:64px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;box-shadow:0 2px 14px rgba(0,0,0,0.22)}
.navbar-logo{display:flex;align-items:center;gap:12px}
.navbar-logo img{width:38px;height:38px;border-radius:9px;object-fit:cover;border:2px solid var(--accent);padding:2px;background:#fff}
.navbar-logo span{font-size:16px;font-weight:700;color:#fff}
.navbar-right{display:flex;align-items:center;gap:8px}
.nav-back{display:inline-flex;align-items:center;gap:7px;padding:8px 16px;border-radius:9px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);color:rgba(255,255,255,.75);font-size:13px;font-weight:500;transition:all .18s}
.nav-back:hover{background:rgba(255,255,255,.15);color:#fff}
.nav-back svg{width:14px;height:14px;fill:none;stroke:currentColor;stroke-width:2.5;stroke-linecap:round;stroke-linejoin:round}
/* PAGE */
.page{max-width:940px;margin:0 auto;padding:32px 20px 72px}
/* BREADCRUMB */
.breadcrumb{display:flex;align-items:center;gap:6px;margin-bottom:24px;font-size:12.5px;color:var(--muted)}
.breadcrumb a{color:var(--accent-dark);font-weight:500;transition:color .15s}
.breadcrumb a:hover{color:var(--primary)}
.breadcrumb svg{width:12px;height:12px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;flex-shrink:0}
/* HERO CARD */
.hero-card{background:linear-gradient(135deg,var(--primary) 0%,#1a3a50 100%);border-radius:20px;padding:28px 32px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:20px;margin-bottom:22px;position:relative;overflow:hidden;box-shadow:var(--shadow-lg)}
.hero-card::before{content:'';position:absolute;top:-50px;right:-50px;width:220px;height:220px;border-radius:50%;background:rgba(166,206,57,.07)}
.hero-card::after{content:'';position:absolute;bottom:-60px;right:140px;width:170px;height:170px;border-radius:50%;background:rgba(166,206,57,.05)}
.hero-left{display:flex;align-items:center;gap:20px;position:relative;z-index:1}
.hero-avatar{width:70px;height:70px;border-radius:18px;background:var(--accent);display:flex;align-items:center;justify-content:center;font-size:24px;font-weight:800;color:var(--primary);flex-shrink:0;box-shadow:0 4px 16px rgba(166,206,57,.35)}
.hero-info h2{font-size:22px;font-weight:800;color:#fff;letter-spacing:-.3px}
.hero-info p{font-size:13px;color:rgba(255,255,255,.5);margin-top:3px}
.hero-pills{display:flex;gap:8px;margin-top:10px;flex-wrap:wrap}
.pill{display:inline-flex;align-items:center;gap:5px;padding:4px 12px;border-radius:100px;font-size:11.5px;font-weight:600}
.pill-active{background:rgba(166,206,57,.15);border:1px solid rgba(166,206,57,.3);color:var(--accent)}
.pill-id{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);color:rgba(255,255,255,.65);font-family:'Space Mono',monospace;font-size:11px}
.hero-right{display:flex;gap:12px;flex-wrap:wrap;position:relative;z-index:1}
.hero-stat{background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.1);border-radius:14px;padding:14px 20px;text-align:center;min-width:100px}
.hero-stat .hs-num{font-family:'Space Mono',monospace;font-size:26px;font-weight:700;color:var(--accent);line-height:1}
.hero-stat .hs-lbl{font-size:11px;color:rgba(255,255,255,.45);margin-top:4px;font-weight:500}
/* GRID */
.grid-2{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:22px}
@media(max-width:700px){.grid-2{grid-template-columns:1fr}}
/* SECTION CARD */
.sec-card{background:var(--surface);border:1.5px solid var(--border);border-radius:18px;overflow:hidden;box-shadow:var(--shadow-sm);animation:fadeUp .45s ease both}
@keyframes fadeUp{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
.sec-card:nth-child(1){animation-delay:.05s}
.sec-card:nth-child(2){animation-delay:.10s}
.sec-head{display:flex;align-items:center;gap:10px;padding:16px 20px;border-bottom:1px solid var(--border-soft);background:var(--surface2)}
.sec-head-icon{width:34px;height:34px;border-radius:9px;background:var(--accent-dim);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.sec-head-icon svg{width:16px;height:16px;fill:none;stroke:var(--accent-dark);stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.sec-head h3{font-size:14px;font-weight:700;color:var(--text)}
.sec-body{padding:20px}
.info-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
@media(max-width:480px){.info-grid{grid-template-columns:1fr}}
.info-box{background:var(--surface2);border:1px solid var(--border-soft);border-radius:10px;padding:13px 15px}
.ib-label{font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);margin-bottom:4px}
.ib-val{font-size:13.5px;color:var(--text);font-weight:500;word-break:break-word}
.ib-val.mono{font-family:'Space Mono',monospace;font-size:12.5px;color:var(--accent-dark)}
/* FULL-WIDTH CARD */
.full-card{background:var(--surface);border:1.5px solid var(--border);border-radius:18px;overflow:hidden;box-shadow:var(--shadow-sm);margin-bottom:22px;animation:fadeUp .45s ease both;animation-delay:.15s}
.table-scroll{overflow-x:auto}
table{width:100%;border-collapse:collapse;font-size:13.5px}
thead th{padding:11px 16px;text-align:left;font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--muted);background:var(--surface2);border-bottom:1px solid var(--border-soft);white-space:nowrap}
thead th:first-child{padding-left:20px}
tbody tr{border-bottom:1px solid var(--border-soft);transition:background .15s}
tbody tr:last-child{border-bottom:none}
tbody tr:hover{background:var(--surface2)}
td{padding:13px 16px;color:var(--text-dim);vertical-align:middle;white-space:nowrap}
td:first-child{padding-left:20px}
.td-id{font-family:'Space Mono',monospace;font-size:11.5px;color:var(--accent-dark);font-weight:700}
.td-service{font-weight:600;color:var(--text)}
.td-date{font-size:12.5px;color:var(--muted)}
.empty-row td{text-align:center;padding:36px;color:var(--muted);font-size:13px}
/* ACTIONS */
.actions{display:flex;gap:12px;flex-wrap:wrap}
.btn{display:inline-flex;align-items:center;gap:8px;padding:11px 22px;border-radius:10px;font-size:13.5px;font-weight:600;font-family:inherit;cursor:pointer;transition:all .18s;border:none}
.btn svg{width:15px;height:15px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.btn-primary{background:var(--primary);color:#fff}
.btn-primary:hover{background:#1a3a50;transform:translateY(-1px)}
.btn-accent{background:var(--accent);color:var(--primary)}
.btn-accent:hover{background:var(--accent-dark);transform:translateY(-1px)}
@media(max-width:600px){.navbar{padding:0 16px}.page{padding:20px 14px 52px}.hero-card{padding:20px}.hero-avatar{width:56px;height:56px;font-size:20px}.hero-info h2{font-size:18px}}
</style>
</head>
<body>
<!-- NAVBAR -->
<nav class="navbar">
    <div class="navbar-logo">
        <img src="logo.jpg" alt="Grand Superior">
        <span>Grand Superior Drycleaners</span>
    </div>
    <div class="navbar-right">
        <a href="view_clients.php" class="nav-back">
            <svg viewBox="0 0 24 24"><path d="M19 12H5M11 18l-6-6 6-6"/></svg>
            Back to Clients
        </a>
    </div>
</nav>
<div class="page">
    <!-- BREADCRUMB -->
    <div class="breadcrumb">
        <a href="owner_dashboard.php">Dashboard</a>
        <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
        <a href="view_clients.php">Clients</a>
        <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
        <span><?= htmlspecialchars($client['name']) ?></span>
    </div>
    <!-- HERO CARD -->
    <div class="hero-card">
        <div class="hero-left">
            <div class="hero-avatar"><?= $initials ?></div>
            <div class="hero-info">
                <h2><?= htmlspecialchars($client['name']) ?></h2>
                <p><?= htmlspecialchars($client['email']) ?></p>
                <div class="hero-pills">
                    <span class="pill pill-active">
                        <svg style="width:9px;height:9px;fill:#a6ce39;flex-shrink:0" viewBox="0 0 8 8"><circle cx="4" cy="4" r="4"/></svg>
                        Active Client
                    </span>
                    <span class="pill pill-id">#CLT-<?= str_pad($client['id'],4,'0',STR_PAD_LEFT) ?></span>
                </div>
            </div>
        </div>
        <div class="hero-right">
            <div class="hero-stat">
                <div class="hs-num"><?= number_format($booking_count) ?></div>
                <div class="hs-lbl">Bookings</div>
            </div>
            <div class="hero-stat">
                <div class="hs-num"><?= number_format($payment_count) ?></div>
                <div class="hs-lbl">Payments</div>
            </div>
            <div class="hero-stat">
                <div class="hs-num" style="font-size:18px">KES <?= number_format($payment_total,0) ?></div>
                <div class="hs-lbl">Total Paid</div>
            </div>
        </div>
    </div>
    <!-- DETAILS GRID -->
    <div class="grid-2">
        <!-- Client Info -->
        <div class="sec-card">
            <div class="sec-head">
                <div class="sec-head-icon">
                    <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                </div>
                <h3>Client Information</h3>
            </div>
            <div class="sec-body">
                <div class="info-grid">
                    <div class="info-box">
                        <div class="ib-label">Full Name</div>
                        <div class="ib-val"><?= htmlspecialchars($client['name']) ?></div>
                    </div>
                    <div class="info-box">
                        <div class="ib-label">Client ID</div>
                        <div class="ib-val mono">#CLT-<?= str_pad($client['id'],4,'0',STR_PAD_LEFT) ?></div>
                    </div>
                    <div class="info-box" style="grid-column:1/-1">
                        <div class="ib-label">Email Address</div>
                        <div class="ib-val"><?= htmlspecialchars($client['email']) ?></div>
                    </div>
                    <?php if (!empty($client['phone'])): ?>
                    <div class="info-box">
                        <div class="ib-label">Phone</div>
                        <div class="ib-val"><?= htmlspecialchars($client['phone']) ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($client['address'])): ?>
                    <div class="info-box">
                        <div class="ib-label">Address</div>
                        <div class="ib-val"><?= htmlspecialchars($client['address']) ?></div>
                    </div>
                    <?php endif; ?>
                    <div class="info-box">
                        <div class="ib-label">Account Status</div>
                        <div class="ib-val" style="color:#15803d;font-weight:700">✓ Active</div>
                    </div>
                    <?php if (!empty($client['created_at'])): ?>
                    <div class="info-box">
                        <div class="ib-label">Registered</div>
                        <div class="ib-val"><?= date('M j, Y', strtotime($client['created_at'])) ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <!-- Summary Stats -->
        <div class="sec-card">
            <div class="sec-head">
                <div class="sec-head-icon">
                    <svg viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                </div>
                <h3>Activity Summary</h3>
            </div>
            <div class="sec-body">
                <div style="display:flex;flex-direction:column;gap:12px">
                    <div style="background:var(--surface2);border:1px solid var(--border-soft);border-radius:10px;padding:16px 18px;display:flex;align-items:center;justify-content:space-between">
                        <div>
                            <div style="font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);margin-bottom:3px">Total Bookings</div>
                            <div style="font-family:'Space Mono',monospace;font-size:28px;font-weight:700;color:var(--text);line-height:1"><?= number_format($booking_count) ?></div>
                        </div>
                        <div style="width:44px;height:44px;border-radius:12px;background:#eff6ff;display:flex;align-items:center;justify-content:center">
                            <svg style="width:20px;height:20px;fill:none;stroke:#3b82f6;stroke-width:2;stroke-linecap:round;stroke-linejoin:round" viewBox="0 0 24 24"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1"/></svg>
                        </div>
                    </div>
                    <div style="background:var(--surface2);border:1px solid var(--border-soft);border-radius:10px;padding:16px 18px;display:flex;align-items:center;justify-content:space-between">
                        <div>
                            <div style="font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);margin-bottom:3px">Payments Made</div>
                            <div style="font-family:'Space Mono',monospace;font-size:28px;font-weight:700;color:var(--text);line-height:1"><?= number_format($payment_count) ?></div>
                        </div>
                        <div style="width:44px;height:44px;border-radius:12px;background:#fff7ed;display:flex;align-items:center;justify-content:center">
                            <svg style="width:20px;height:20px;fill:none;stroke:#f97316;stroke-width:2;stroke-linecap:round;stroke-linejoin:round" viewBox="0 0 24 24"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                        </div>
                    </div>
                    <div style="background:var(--accent-dim);border:1.5px solid var(--border);border-radius:10px;padding:16px 18px;display:flex;align-items:center;justify-content:space-between">
                        <div>
                            <div style="font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--accent-dark);margin-bottom:3px">Total Amount Paid</div>
                            <div style="font-family:'Space Mono',monospace;font-size:20px;font-weight:700;color:var(--primary);line-height:1">KES <?= number_format($payment_total, 2) ?></div>
                        </div>
                        <div style="width:44px;height:44px;border-radius:12px;background:var(--accent);display:flex;align-items:center;justify-content:center">
                            <svg style="width:20px;height:20px;fill:none;stroke:#0d1b2a;stroke-width:2;stroke-linecap:round;stroke-linejoin:round" viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- RECENT BOOKINGS TABLE -->
    <div class="full-card">
        <div class="sec-head" style="padding:16px 20px">
            <div class="sec-head-icon">
                <svg viewBox="0 0 24 24"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1"/></svg>
            </div>
            <h3>Recent Bookings</h3>
            <a href="view_client_bookings.php?client_id=<?= $client['id'] ?>" style="margin-left:auto;font-size:12px;font-weight:600;color:var(--accent-dark);background:var(--accent-dim);border:1px solid var(--border);padding:6px 14px;border-radius:8px;display:inline-flex;align-items:center;gap:5px;transition:all .18s" onmouseover="this.style.background='var(--accent)';this.style.color='var(--primary)'" onmouseout="this.style.background='var(--accent-dim)';this.style.color='var(--accent-dark)'">
                View All
                <svg style="width:12px;height:12px;fill:none;stroke:currentColor;stroke-width:2.5;stroke-linecap:round;stroke-linejoin:round" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
            </a>
        </div>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr><th>#ID</th><th>Service</th><th>Status</th><th>Date</th></tr>
                </thead>
                <tbody>
                <?php if (count($bookings_recent) > 0): ?>
                    <?php foreach ($bookings_recent as $b): ?>
                    <tr>
                        <td class="td-id">#<?= str_pad($b['id'],3,'0',STR_PAD_LEFT) ?></td>
                        <td class="td-service"><?= htmlspecialchars($b['service_type']) ?></td>
                        <td><?= statusBadge($b['status'] ?: 'Pending') ?></td>
                        <td class="td-date"><?= date('M j, Y', strtotime($b['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr class="empty-row"><td colspan="4">No bookings found for this client</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <!-- ACTIONS -->
    <div class="actions">
        <a href="view_clients.php" class="btn btn-primary">
            <svg viewBox="0 0 24 24"><path d="M19 12H5M11 18l-6-6 6-6"/></svg>
            Back to Clients
        </a>
        <a href="view_client_bookings.php?client_id=<?= $client['id'] ?>" class="btn btn-accent">
            <svg viewBox="0 0 24 24"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1"/></svg>
            View All Bookings
        </a>
    </div>
</div>
</body>
</html>