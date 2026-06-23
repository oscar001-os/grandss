<?php
session_start();
include 'connection.php';

if (!isset($_SESSION['owner_id'])) {
    header("Location: owner_login.php");
    exit();
}

/* Archive Client */
if (isset($_GET['archive'])) {
    $client_id = intval($_GET['archive']);
    $stmt = $conn->prepare("UPDATE clients SET status='Archived' WHERE id=?");
    $stmt->bind_param("i", $client_id);
    $stmt->execute();
    $stmt->close();
    header("Location: view_clients.php");
      exit();
}

/* Unarchive Client */
if (isset($_GET['unarchive'])) {
    $client_id = intval($_GET['unarchive']);
    $stmt = $conn->prepare("UPDATE clients SET status='Active' WHERE id=?");
    $stmt->bind_param("i", $client_id);
    $stmt->execute();
    $stmt->close();
    header("Location: view_clients.php");
    exit();
}

/* Search */
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

if (!empty($search)) {
    $stmt = $conn->prepare("SELECT * FROM clients WHERE name LIKE ? OR email LIKE ? ORDER BY id DESC");
    $term = "%" . $search . "%";
    $stmt->bind_param("ss", $term, $term);
    $stmt->execute();
    $clients = $stmt->get_result();
} else {
      $clients = $conn->query("SELECT * FROM clients ORDER BY id DESC");
}

$rows         = $clients->fetch_all(MYSQLI_ASSOC);
$total        = count($rows);
$activeRows   = array_values(array_filter($rows, fn($r) => strtolower($r['status']) === 'active'));
$archivedRows = array_values(array_filter($rows, fn($r) => strtolower($r['status']) !== 'active'));
$active       = count($activeRows);
$archived     = count($archivedRows);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Clients — Grand Superior</title>
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
    --bh:     rgba(0,0,0,0.14);
    --t1:     #111827;
    --t2:     #6b7280;
    --t3:     #9ca3af;
    --rmd:    8px;
    --rlg:    12px;
    --rxl:    16px;
}

body { font-family:'Inter',sans-serif; background:var(--bg); color:var(--t1); min-height:100vh; font-size:14px; line-height:1.6; -webkit-font-smoothing:antialiased; }
a { text-decoration:none; color:inherit; }

/* ── TOPBAR ── */
.topbar { background:var(--dark); height:64px; display:flex; align-items:center; padding:0 28px; gap:14px; position:sticky; top:0; z-index:100; border-bottom:1px solid rgba(255,255,255,0.06); }
.brand { display:flex; align-items:center; gap:10px; }
.brand-icon { width:36px; height:36px; border-radius:var(--rmd); background:rgba(166,206,57,0.15); display:flex; align-items:center; justify-content:center; }
.brand-icon i { font-size:18px; color:var(--accent); }
.brand-name { font-size:15px; font-weight:600; color:#fff; }
.brand-name span { color:var(--accent); }
.topbar-sep { width:1px; height:24px; background:rgba(255,255,255,0.1); }
.back-link { display:inline-flex; align-items:center; gap:6px; font-size:13px; font-weight:500; color:rgba(255,255,255,0.55); padding:6px 12px; border-radius:var(--rmd); transition:background .15s,color .15s; }
.back-link:hover { background:rgba(255,255,255,0.07); color:#fff; }
.back-link i { font-size:15px; }
.topbar-right { margin-left:auto; }
.btn-add { display:inline-flex; align-items:center; gap:6px; background:var(--accent); color:#0d1b2a; font-size:13px; font-weight:600; padding:8px 16px; border-radius:var(--rmd); transition:opacity .15s; white-space:nowrap; }
.btn-add:hover { opacity:0.88; }
.btn-add i { font-size:15px; }

/* ── PAGE ── */
.page { max-width:1200px; margin:0 auto; padding:28px 24px 60px; }

/* ── PAGE HEADER ── */
.page-header { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; margin-bottom:24px; }
.page-title { font-size:20px; font-weight:600; color:var(--t1); letter-spacing:-0.02em; }
.record-pill { background:var(--surf2); border:0.5px solid var(--border); border-radius:20px; padding:4px 12px; font-size:12px; font-weight:500; color:var(--t2); }

/* ── STATS ── */
.stats { display:grid; grid-template-columns:repeat(3,1fr); gap:14px; margin-bottom:24px; }
.stat { background:var(--white); border:0.5px solid var(--border); border-radius:var(--rlg); padding:16px 20px; }
.stat-label { font-size:11px; font-weight:500; color:var(--t3); text-transform:uppercase; letter-spacing:0.06em; margin-bottom:6px; display:flex; align-items:center; gap:5px; }
.stat-dot { width:7px; height:7px; border-radius:50%; flex-shrink:0; }
.dot-all    { background:#6b7280; }
.dot-active { background:#22c55e; }
.dot-arch   { background:#ef4444; }
.stat-value { font-size:24px; font-weight:600; color:var(--t1); letter-spacing:-0.02em; }

/* ── TOOLBAR ── */
.toolbar { display:flex; align-items:center; gap:10px; margin-bottom:16px; flex-wrap:wrap; }
.search-wrap { position:relative; flex:1; min-width:220px; }
.search-wrap i.icon-search { position:absolute; left:11px; top:50%; transform:translateY(-50%); font-size:16px; color:var(--t3); pointer-events:none; }
.search-input { width:100%; background:var(--white); border:0.5px solid var(--border); border-radius:var(--rmd); padding:9px 12px 9px 34px; font-family:'Inter',sans-serif; font-size:13px; color:var(--t1); outline:none; transition:border-color .15s; }
.search-input:focus { border-color:#a6ce39; }
.search-input::placeholder { color:var(--t3); }
.search-btn { display:inline-flex; align-items:center; gap:6px; background:var(--dark); color:#fff; border:none; padding:9px 16px; border-radius:var(--rmd); font-family:'Inter',sans-serif; font-size:13px; font-weight:500; cursor:pointer; transition:opacity .15s; white-space:nowrap; }
.search-btn:hover { opacity:0.85; }
.search-btn i { font-size:15px; }
.clear-link { display:inline-flex; align-items:center; gap:5px; font-size:13px; color:var(--t2); padding:9px 14px; border-radius:var(--rmd); border:0.5px solid var(--border); background:var(--white); transition:border-color .15s; white-space:nowrap; }
.clear-link:hover { border-color:var(--bh); color:var(--t1); }
.clear-link i { font-size:14px; }

/* ── SECTION HEADERS ── */
.section-header { display:flex; align-items:center; gap:10px; margin-bottom:14px; }
.section-title { font-size:15px; font-weight:600; color:var(--t1); }
.section-count { background:var(--surf2); border:0.5px solid var(--border); border-radius:20px; padding:2px 10px; font-size:11.5px; font-weight:500; color:var(--t2); }
.section-line { flex:1; height:1px; background:var(--border); }

/* ── TABLE CARD ── */
.table-card { background:var(--white); border:0.5px solid var(--border); border-radius:var(--rxl); overflow:hidden; }
.table-scroll { overflow-x:auto; -webkit-overflow-scrolling:touch; }
table { width:100%; border-collapse:collapse; font-size:13px; }
thead tr { background:var(--surf2); border-bottom:0.5px solid var(--border); }
thead th { padding:12px 16px; text-align:left; font-size:11px; font-weight:600; color:var(--t3); text-transform:uppercase; letter-spacing:0.07em; white-space:nowrap; }
thead th:first-child { padding-left:20px; }
tbody tr { border-bottom:0.5px solid var(--border); transition:background .12s; }
tbody tr:last-child { border-bottom:none; }
tbody tr:hover { background:var(--surf2); }
td { padding:14px 16px; color:var(--t2); vertical-align:middle; }
td:first-child { padding-left:20px; }

.td-id    { font-family:monospace; font-size:12px; font-weight:600; color:var(--dark); white-space:nowrap; }
.td-name  { font-weight:500; color:var(--t1); }
.td-email { font-size:12px; color:var(--t2); }
.td-avatar { width:34px; height:34px; border-radius:50%; background:var(--surf2); border:0.5px solid var(--border); display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:600; color:var(--dark); flex-shrink:0; }
.td-avatar.arch { background:#fef2f2; border-color:#fecaca; color:#b91c1c; }

/* ── BADGES ── */
.badge { display:inline-flex; align-items:center; gap:5px; padding:4px 10px; border-radius:20px; font-size:11.5px; font-weight:600; white-space:nowrap; }
.bdot  { width:6px; height:6px; border-radius:50%; flex-shrink:0; }
.badge-active   { background:#f0fdf4; color:#15803d; border:0.5px solid #bbf7d0; }
.badge-active .bdot   { background:#22c55e; }
.badge-archived { background:#fef2f2; color:#b91c1c; border:0.5px solid #fecaca; }
.badge-archived .bdot { background:#ef4444; }

/* ── ACTION BUTTONS ── */
.actions { display:flex; align-items:center; gap:7px; flex-wrap:wrap; }
.btn-view { display:inline-flex; align-items:center; gap:5px; background:var(--surf2); color:var(--t1); border:0.5px solid var(--border); font-size:12px; font-weight:500; padding:6px 12px; border-radius:var(--rmd); transition:border-color .15s,background .15s; white-space:nowrap; cursor:pointer; }
.btn-view:hover { background:#eff6ff; border-color:#bfdbfe; color:#1d4ed8; }
.btn-view i { font-size:14px; }
.btn-arch { display:inline-flex; align-items:center; gap:5px; background:#fef2f2; color:#b91c1c; border:0.5px solid #fecaca; font-size:12px; font-weight:500; padding:6px 12px; border-radius:var(--rmd); transition:background .15s; white-space:nowrap; cursor:pointer; }
.btn-arch:hover { background:#fee2e2; }
.btn-arch i { font-size:14px; }
.btn-unarch { display:inline-flex; align-items:center; gap:5px; background:#f0fdf4; color:#15803d; border:0.5px solid #bbf7d0; font-size:12px; font-weight:500; padding:6px 12px; border-radius:var(--rmd); transition:background .15s; white-space:nowrap; }
.btn-unarch:hover { background:#dcfce7; }
.btn-unarch i { font-size:14px; }

/* ── EMPTY STATE ── */
.empty { text-align:center; padding:56px 24px; }
.empty i { font-size:36px; color:var(--t3); display:block; margin-bottom:10px; }
.empty p { color:var(--t2); font-size:14px; }

/* ── TABLE FOOTER ── */
.table-footer { display:flex; align-items:center; justify-content:space-between; padding:12px 20px; border-top:0.5px solid var(--border); flex-wrap:wrap; gap:8px; }
.footer-count { font-size:12px; color:var(--t3); }

/* ── ARCHIVE CONFIRM MODAL ── */
.modal-overlay {
    display:none; position:fixed; inset:0; z-index:200;
    background:rgba(13,27,42,0.55);
    backdrop-filter:blur(3px); -webkit-backdrop-filter:blur(3px);
    align-items:center; justify-content:center; padding:16px;
}
.modal-overlay.open { display:flex; }
.modal {
    background:var(--white); border-radius:var(--rxl);
    width:100%; max-width:420px;
    box-shadow:0 20px 60px rgba(0,0,0,0.18);
    animation:modalIn .18s ease;
}
@keyframes modalIn {
    from { opacity:0; transform:translateY(12px) scale(0.97); }
    to   { opacity:1; transform:translateY(0) scale(1); }
}
.modal-header { display:flex; align-items:center; justify-content:space-between; padding:20px 20px 0; }
.modal-icon { width:42px; height:42px; border-radius:var(--rlg); background:#fef2f2; display:flex; align-items:center; justify-content:center; }
.modal-icon i { font-size:20px; color:#ef4444; }
.modal-close { width:30px; height:30px; border-radius:var(--rmd); background:none; border:none; cursor:pointer; display:flex; align-items:center; justify-content:center; color:var(--t3); transition:background .12s,color .12s; }
.modal-close:hover { background:var(--surf2); color:var(--t1); }
.modal-close i { font-size:18px; }
.modal-body { padding:16px 20px 20px; }
.modal-title { font-size:16px; font-weight:600; color:var(--t1); margin-bottom:4px; }
.modal-sub   { font-size:13px; color:var(--t2); margin-bottom:18px; line-height:1.5; }
.modal-client-card { background:var(--surf2); border:0.5px solid var(--border); border-radius:var(--rlg); padding:14px 16px; display:flex; align-items:center; gap:12px; margin-bottom:20px; }
.modal-avatar { width:44px; height:44px; border-radius:50%; background:#fef2f2; border:0.5px solid #fecaca; display:flex; align-items:center; justify-content:center; font-size:14px; font-weight:700; color:#b91c1c; flex-shrink:0; }
.modal-client-info { flex:1; min-width:0; }
.modal-client-name  { font-size:14px; font-weight:600; color:var(--t1); }
.modal-client-email { font-size:12px; color:var(--t2); margin-top:1px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.modal-client-id    { font-family:monospace; font-size:11px; color:var(--t3); margin-top:2px; }
.modal-actions { display:flex; gap:8px; }
.btn-cancel {
    flex:1; padding:10px; border-radius:var(--rmd);
    background:var(--surf2); border:0.5px solid var(--border);
    font-family:'Inter',sans-serif; font-size:13px; font-weight:500;
    color:var(--t2); cursor:pointer; transition:background .12s,border-color .12s;
}
.btn-cancel:hover { background:#f3f4f6; border-color:var(--bh); color:var(--t1); }
.btn-confirm-arch {
    flex:1; padding:10px; border-radius:var(--rmd);
    background:#ef4444; border:none;
    font-family:'Inter',sans-serif; font-size:13px; font-weight:600;
    color:#fff; cursor:pointer; transition:background .12s;
    display:flex; align-items:center; justify-content:center; gap:6px;
    text-decoration:none;
}
.btn-confirm-arch:hover { background:#dc2626; }
.btn-confirm-arch i { font-size:15px; }

/* ── MOBILE CARDS ── */
.mobile-cards { display:none; }
.client-card { background:var(--white); border:0.5px solid var(--border); border-radius:var(--rlg); padding:16px; margin-bottom:10px; }
.cc-head { display:flex; align-items:center; gap:12px; margin-bottom:12px; }
.cc-info { flex:1; min-width:0; }
.cc-name  { font-size:14px; font-weight:600; color:var(--t1); }
.cc-email { font-size:12px; color:var(--t3); margin-top:1px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.cc-id    { font-family:monospace; font-size:11px; color:var(--t3); margin-top:3px; }
.cc-foot  { display:flex; align-items:center; gap:8px; padding-top:12px; border-top:0.5px solid var(--border); flex-wrap:wrap; }

/* ── ARCHIVED SECTION ── */
.archived-section { margin-top:40px; }

/* ── RESPONSIVE ── */
@media(max-width:860px){ .stats { grid-template-columns:repeat(3,1fr); } }
@media(max-width:680px){
    .topbar { padding:0 16px; }
    .topbar .brand-name { display:none; }
    .page { padding:20px 16px 48px; }
    .stats { grid-template-columns:repeat(3,1fr); gap:10px; }
    .stat { padding:12px 14px; }
    .stat-value { font-size:20px; }
    .table-card .table-scroll { display:none; }
    .table-card .table-footer { display:none; }
    .mobile-cards { display:block; }
    .toolbar { flex-direction:column; align-items:stretch; }
    .search-wrap { width:100%; }
    .search-btn, .clear-link { width:100%; justify-content:center; }
    .page-header { flex-direction:column; align-items:flex-start; gap:8px; }
}
@media(max-width:420px){ .stats { grid-template-columns:1fr 1fr; } }
@media(prefers-reduced-motion:reduce){ * { transition:none!important; } }
</style>
</head>
<body>

<!-- ── ARCHIVE CONFIRMATION MODAL ── -->
<div class="modal-overlay" id="archiveModal">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="archiveModalTitle">
        <div class="modal-header">
            <div class="modal-icon"><i class="ti ti-archive"></i></div>
            <button class="modal-close" onclick="closeArchiveModal()" aria-label="Close">
                <i class="ti ti-x"></i>
            </button>
        </div>
        <div class="modal-body">
            <h2 class="modal-title" id="archiveModalTitle">Archive this client?</h2>
            <p class="modal-sub">This client will be moved to the archived list. You can restore them at any time.</p>
            <div class="modal-client-card">
                <div class="modal-avatar" id="archModalAvatar"></div>
                <div class="modal-client-info">
                    <div class="modal-client-name"  id="archModalName"></div>
                    <div class="modal-client-email" id="archModalEmail"></div>
                    <div class="modal-client-id"    id="archModalId"></div>
                </div>
            </div>
            <div class="modal-actions">
                <button class="btn-cancel" onclick="closeArchiveModal()">Cancel</button>
                <a class="btn-confirm-arch" id="archModalConfirmLink" href="#">
                    <i class="ti ti-archive"></i> Archive client
                </a>
            </div>
        </div>
    </div>
</div>

<!-- ── TOPBAR ── -->
<header class="topbar">
    <div class="brand">
        <div class="brand-icon"><i class="ti ti-shirt"></i></div>
        <div class="brand-name">Grand <span>Superior</span></div>
    </div>
    <div class="topbar-sep"></div>
    <a href="owner_dashboard.php" class="back-link">
        <i class="ti ti-arrow-left"></i> Dashboard
    </a>
    <div class="topbar-right">
        <a href="add_client.php" class="btn-add">
            <i class="ti ti-plus"></i> Add client
        </a>
    </div>
</header>

<main class="page">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <h1 class="page-title">Manage Clients</h1>
        <span class="record-pill"><?= $total ?> total</span>
    </div>

    <!-- STATS -->
    <div class="stats">
        <div class="stat">
            <div class="stat-label"><span class="stat-dot dot-all"></span> Total</div>
            <div class="stat-value"><?= $total ?></div>
        </div>
        <div class="stat">
            <div class="stat-label"><span class="stat-dot dot-active"></span> Active</div>
            <div class="stat-value"><?= $active ?></div>
        </div>
        <div class="stat">
            <div class="stat-label"><span class="stat-dot dot-arch"></span> Archived</div>
            <div class="stat-value"><?= $archived ?></div>
        </div>
    </div>

    <!-- TOOLBAR -->
    <form method="GET" class="toolbar">
        <div class="search-wrap">
            <i class="ti ti-search icon-search"></i>
            <input type="text" name="search" class="search-input"
                placeholder="Search by name or email…"
                value="<?= htmlspecialchars($search) ?>">
        </div>
        <button type="submit" class="search-btn">
            <i class="ti ti-search"></i> Search
        </button>
        <?php if (!empty($search)): ?>
        <a href="view_clients.php" class="clear-link">
            <i class="ti ti-x"></i> Clear
        </a>
        <?php endif; ?>
    </form>

    <!-- ══════════════════════════════════════
         ACTIVE CLIENTS
    ══════════════════════════════════════ -->
    <div class="section-header">
        <span class="section-title">Active Clients</span>
        <span class="section-count"><?= $active ?></span>
        <span class="section-line"></span>
    </div>

    <!-- Desktop table — Active -->
    <div class="table-card">
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>#ID</th>
                        <th>Client</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (count($activeRows) > 0): ?>
                    <?php foreach ($activeRows as $row):
                        $nameParts = explode(' ', trim($row['name']));
                        $initials  = strtoupper(substr($nameParts[0],0,1) . (isset($nameParts[1]) ? substr($nameParts[1],0,1) : ''));
                        $paddedId  = '#' . str_pad($row['id'], 4, '0', STR_PAD_LEFT);
                    ?>
                    <tr>
                        <td class="td-id"><?= $paddedId ?></td>
                        <td>
                            <div style="display:flex;align-items:center;gap:10px;">
                                <div class="td-avatar"><?= $initials ?></div>
                                <span class="td-name"><?= htmlspecialchars($row['name']) ?></span>
                            </div>
                        </td>
                        <td class="td-email"><?= htmlspecialchars($row['email']) ?></td>
                        <td><span class="badge badge-active"><span class="bdot"></span> Active</span></td>
                        <td>
                            <div class="actions">
                                <a href="client_details.php?id=<?= $row['id'] ?>" class="btn-view">
                                    <i class="ti ti-eye"></i> View
                                </a>
                                
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="5">
                        <div class="empty">
                            <i class="ti ti-users-off"></i>
                            <p><?= !empty($search) ? 'No active clients matched your search.' : 'No active clients found.' ?></p>
                        </div>
                    </td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="table-footer">
            <span class="footer-count">Showing <?= $active ?> active client<?= $active !== 1 ? 's' : '' ?></span>
        </div>
    </div>

    <!-- Mobile cards — Active -->
    <div class="mobile-cards">
    <?php if (count($activeRows) > 0): ?>
        <?php foreach ($activeRows as $row):
            $nameParts = explode(' ', trim($row['name']));
            $initials  = strtoupper(substr($nameParts[0],0,1) . (isset($nameParts[1]) ? substr($nameParts[1],0,1) : ''));
            $paddedId  = '#' . str_pad($row['id'], 4, '0', STR_PAD_LEFT);
        ?>
        <div class="client-card">
            <div class="cc-head">
                <div class="td-avatar" style="width:40px;height:40px;font-size:13px;"><?= $initials ?></div>
                <div class="cc-info">
                    <div class="cc-name"><?= htmlspecialchars($row['name']) ?></div>
                    <div class="cc-email"><?= htmlspecialchars($row['email']) ?></div>
                    <div class="cc-id"><?= $paddedId ?></div>
                </div>
                <span class="badge badge-active"><span class="bdot"></span> Active</span>
            </div>
            <div class="cc-foot">
                <a href="client_details.php?id=<?= $row['id'] ?>" class="btn-view" style="flex:1;justify-content:center;">
                    <i class="ti ti-eye"></i> View
                </a>
                <button type="button" class="btn-arch" style="flex:1;justify-content:center;"
                    onclick="openArchiveModal(
                        <?= $row['id'] ?>,
                        <?= json_encode($row['name']) ?>,
                        <?= json_encode($row['email']) ?>,
                        <?= json_encode($paddedId) ?>,
                        <?= json_encode($initials) ?>
                    )">
                    <i class="ti ti-archive"></i> Archive
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="client-card" style="text-align:center;padding:40px 24px;">
            <i class="ti ti-users-off" style="font-size:32px;color:var(--t3);display:block;margin-bottom:8px;"></i>
            <p style="color:var(--t2);"><?= !empty($search) ? 'No active clients matched your search.' : 'No active clients found.' ?></p>
        </div>
    <?php endif; ?>
    </div>


    <!-- ══════════════════════════════════════
         ARCHIVED CLIENTS
    ══════════════════════════════════════ -->
    

        <!-- Desktop table — Archived -->
        <div class="table-card">
            <div class="table-scroll">
                <table>
                   
                    <tbody>
                    <?php if (count($archivedRows) > 0): ?>
                        <?php foreach ($archivedRows as $row):
                            $nameParts = explode(' ', trim($row['name']));
                            $initials  = strtoupper(substr($nameParts[0],0,1) . (isset($nameParts[1]) ? substr($nameParts[1],0,1) : ''));
                            $paddedId  = '#' . str_pad($row['id'], 4, '0', STR_PAD_LEFT);
                        ?>
                        <tr>
                            <td class="td-id"><?= $paddedId ?></td>
                            <td>
                                <div style="display:flex;align-items:center;gap:10px;">
                                    <div class="td-avatar arch"><?= $initials ?></div>
                                    <span class="td-name" style="color:var(--t2);"><?= htmlspecialchars($row['name']) ?></span>
                                </div>
                            </td>
                            <td class="td-email"><?= htmlspecialchars($row['email']) ?></td>
                            <td><span class="badge badge-archived"><span class="bdot"></span> Archived</span></td>
                           
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                       
                    <?php endif; ?>
                    </tbody>
                </table>
           

        <!-- Mobile cards — Archived -->
        <div class="mobile-cards">
        <?php if (count($archivedRows) > 0): ?>
            <?php foreach ($archivedRows as $row):
                $nameParts = explode(' ', trim($row['name']));
                $initials  = strtoupper(substr($nameParts[0],0,1) . (isset($nameParts[1]) ? substr($nameParts[1],0,1) : ''));
                $paddedId  = '#' . str_pad($row['id'], 4, '0', STR_PAD_LEFT);
            ?>
            <div class="client-card" style="opacity:0.88;">
                <div class="cc-head">
                    <div class="td-avatar arch" style="width:40px;height:40px;font-size:13px;"><?= $initials ?></div>
                    <div class="cc-info">
                        <div class="cc-name" style="color:var(--t2);"><?= htmlspecialchars($row['name']) ?></div>
                        <div class="cc-email"><?= htmlspecialchars($row['email']) ?></div>
                        <div class="cc-id"><?= $paddedId ?></div>
                    </div>
                    <span class="badge badge-archived"><span class="bdot"></span> Archived</span>
                </div>
                <div class="cc-foot">
                    <a href="client_details.php?id=<?= $row['id'] ?>" class="btn-view" style="flex:1;justify-content:center;">
                        <i class="ti ti-eye"></i> View
                    </a>
                    <a href="view_clients.php?unarchive=<?= $row['id'] ?>" class="btn-unarch" style="flex:1;justify-content:center;">
                        <i class="ti ti-refresh"></i> Unarchive
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="client-card" style="text-align:center;padding:40px 24px;">
                <i class="ti ti-archive-off" style="font-size:32px;color:var(--t3);display:block;margin-bottom:8px;"></i>
                <p style="color:var(--t2);">No archived clients yet.</p>
            </div>
        <?php endif; ?>
        </div>

    </div><!-- /archived-section -->

</main>

<script>
function openArchiveModal(id, name, email, paddedId, initials) {
    document.getElementById('archModalAvatar').textContent = initials;
    document.getElementById('archModalName').textContent   = name;
    document.getElementById('archModalEmail').textContent  = email;
    document.getElementById('archModalId').textContent     = paddedId;
    document.getElementById('archModalConfirmLink').href   = 'view_clients.php?archive=' + id;
    document.getElementById('archiveModal').classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeArchiveModal() {
    document.getElementById('archiveModal').classList.remove('open');
    document.body.style.overflow = '';
}

document.getElementById('archiveModal').addEventListener('click', function(e) {
    if (e.target === this) closeArchiveModal();
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeArchiveModal();
});
</script>

</body>
</html>