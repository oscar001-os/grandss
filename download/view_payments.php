<?php
// ============================================================
//  Grand Superior Drycleaners — Owner: View & Update Payments
//  Full single-file PHP | uses connection.php for DB
// ============================================================

session_start();
include 'connection.php';

// ── AUTH GUARD (owner only) ──────────────────────────────────
if (!isset($_SESSION['owner_id'])) {
    header("Location: owner_login.php");
    exit();
}

$conn->set_charset('utf8mb4');
$owner_name = htmlspecialchars($_SESSION['owner_name'] ?? 'Owner');

// ── CREATE TABLE IF NOT EXISTS (safety net) ──────────────────
$conn->query("
    CREATE TABLE IF NOT EXISTS payments (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        client_id    INT           NOT NULL DEFAULT 1,
        amount       DECIMAL(10,2),
        method       VARCHAR(50),
        status       VARCHAR(20)   NOT NULL DEFAULT 'Pending',
        bank_message TEXT,
        created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// ── HANDLE STATUS UPDATE (AJAX or form POST) ─────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $pay_id     = (int) ($_POST['pay_id']    ?? 0);
    $new_status = trim($_POST['new_status'] ?? '');

    $allowed = ['Paid', 'Pending', 'Failed'];
    if ($pay_id > 0 && in_array($new_status, $allowed, true)) {

        // 1. Update the payments table
        $upd = $conn->prepare("UPDATE payments SET status = ? WHERE id = ?");
        $upd->bind_param('si', $new_status, $pay_id);
        $upd->execute();
        $upd->close();

        // 2. Sync payment_status in bookings for the same client_id
        //    First fetch the client_id linked to this payment
        $getClient = $conn->prepare("SELECT client_id FROM payments WHERE id = ?");
        $getClient->bind_param('i', $pay_id);
        $getClient->execute();
        $getClient->bind_result($linked_client_id);
        $getClient->fetch();
        $getClient->close();

        if (!empty($linked_client_id)) {
            $syncBooking = $conn->prepare(
                "UPDATE bookings SET payment_status = ? WHERE client_id = ?"
            );
            $syncBooking->bind_param('si', $new_status, $linked_client_id);
            $syncBooking->execute();
            $syncBooking->close();
        }

        // If called via AJAX return JSON
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => true, 'status' => $new_status]);
            $conn->close();
            exit();
        }
    }
    // redirect to same page to avoid resubmit on refresh
    header("Location: " . $_SERVER['PHP_SELF'] . '?' . http_build_query([
        'status' => $_GET['status'] ?? 'All',
        'from'   => $_GET['from']   ?? '',
        'to'     => $_GET['to']     ?? '',
        'q'      => $_GET['q']      ?? '',
    ]));
    exit();
}

// ── FILTERS ──────────────────────────────────────────────────
$filter_status = $_GET['status'] ?? 'All';
$filter_from   = $_GET['from']   ?? '';
$filter_to     = $_GET['to']     ?? '';
$search        = trim($_GET['q'] ?? '');

$where  = ['1=1'];
$params = [];
$types  = '';

if ($filter_status !== 'All' && in_array($filter_status, ['Paid','Pending','Failed'], true)) {
    $where[]  = 'p.status = ?';
    $params[] = $filter_status;
    $types   .= 's';
}
if ($filter_from !== '') {
    $where[]  = 'DATE(p.created_at) >= ?';
    $params[] = $filter_from;
    $types   .= 's';
}
if ($filter_to !== '') {
    $where[]  = 'DATE(p.created_at) <= ?';
    $params[] = $filter_to;
    $types   .= 's';
}
if ($search !== '') {
    $like     = '%' . $search . '%';
    $where[]  = '(p.bank_message LIKE ? OR p.method LIKE ? OR p.status LIKE ?)';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types   .= 'sss';
}

// ── FETCH PAYMENTS (join clients table if it exists) ─────────
$sql = '
    SELECT p.*,
           IFNULL(c.name, CONCAT("Client #", p.client_id)) AS client_name,
           IFNULL(c.phone, "—")                             AS client_phone
    FROM payments p
    LEFT JOIN clients c ON c.id = p.client_id
    WHERE ' . implode(' AND ', $where) . '
    ORDER BY p.created_at DESC
';

$stmt = $conn->prepare($sql);
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ── TOTALS ───────────────────────────────────────────────────
$t = $conn->query("
    SELECT
        COUNT(*)                                                          AS total_count,
        SUM(CASE WHEN status='Paid'    THEN IFNULL(amount,0) ELSE 0 END) AS total_paid,
        SUM(CASE WHEN status='Pending' THEN IFNULL(amount,0) ELSE 0 END) AS total_pending,
        SUM(CASE WHEN status='Failed'  THEN IFNULL(amount,0) ELSE 0 END) AS total_failed
    FROM payments
")->fetch_assoc();

$conn->close();

// ── HELPERS ──────────────────────────────────────────────────
function fmt_kes($v) {
    return ($v === null || $v === '') ? '—' : 'KES ' . number_format((float)$v, 2);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>View Payments — Grand Superior Drycleaners</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
/* ── RESET ── */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Poppins',sans-serif;background:#f0f2f5;color:#0d1b2a;min-height:100vh;}
a{text-decoration:none;}

/* ── SIDEBAR LAYOUT ── */
.layout   {display:flex;min-height:100vh;}
.sidebar  {width:230px;background:#0d1b2a;flex-shrink:0;display:flex;flex-direction:column;padding:28px 0;}
.sb-logo  {display:flex;align-items:center;gap:12px;padding:0 24px 28px;border-bottom:1px solid rgba(255,255,255,.1);}
.sb-logo-box{width:40px;height:40px;border-radius:10px;background:#a6ce39;display:flex;align-items:center;
             justify-content:center;font-weight:800;font-size:15px;color:#0d1b2a;flex-shrink:0;}
.sb-brand {font-size:12px;font-weight:700;color:white;line-height:1.4;}
.sb-nav   {padding:18px 0;flex:1;}
.sb-link  {display:flex;align-items:center;gap:10px;padding:11px 24px;font-size:13px;font-weight:500;
           color:rgba(255,255,255,.65);transition:all .15s;cursor:pointer;}
.sb-link:hover,.sb-link.active{color:white;background:rgba(166,206,57,.15);border-left:3px solid #a6ce39;}
.sb-link .licon{font-size:16px;width:20px;text-align:center;}
.sb-footer{padding:18px 24px;border-top:1px solid rgba(255,255,255,.1);font-size:11px;color:rgba(255,255,255,.4);}

/* ── MAIN ── */
.main     {flex:1;display:flex;flex-direction:column;overflow:hidden;}
.topbar   {background:white;padding:16px 28px;display:flex;align-items:center;justify-content:space-between;
           box-shadow:0 1px 4px rgba(0,0,0,.06);flex-shrink:0;}
.topbar-title{font-size:17px;font-weight:700;color:#0d1b2a;}
.topbar-user {font-size:13px;color:#6b7280;}
.topbar-user strong{color:#0d1b2a;}
.content  {padding:24px 28px;overflow-y:auto;}

/* ── STAT CARDS ── */
.stats    {display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:22px;}
.stat-card{background:white;border-radius:12px;padding:18px 20px;box-shadow:0 2px 10px rgba(0,0,0,.06);}
.stat-label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#9ca3af;margin-bottom:6px;}
.stat-value{font-size:22px;font-weight:700;}

/* ── CARD ── */
.card      {background:white;border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.07);overflow:hidden;}
.card-head {background:#0d1b2a;padding:14px 22px;display:flex;align-items:center;justify-content:space-between;}
.card-head-left{display:flex;align-items:center;gap:9px;}
.card-head .icon {font-size:17px;}
.card-head .title{color:white;font-weight:600;font-size:14px;}
.card-head .count{background:#a6ce39;color:#0d1b2a;font-size:11px;font-weight:700;
                  padding:2px 9px;border-radius:20px;}

/* ── TOOLBAR ── */
.toolbar   {padding:14px 22px;border-bottom:1px solid #f1f5f9;display:flex;flex-wrap:wrap;align-items:center;gap:10px;}
.pill-grp  {display:flex;gap:6px;}
.pill      {padding:5px 14px;border-radius:20px;font-size:12px;font-weight:600;border:2px solid #e5e7eb;
            background:transparent;color:#6b7280;cursor:pointer;text-decoration:none;font-family:inherit;transition:all .15s;}
.pill:hover,.pill.active{border-color:#a6ce39;background:#a6ce39;color:#0d1b2a;}
.search-box{display:flex;align-items:center;gap:8px;background:#f8fafc;border:1.5px solid #e5e7eb;
            border-radius:8px;padding:6px 12px;margin-left:auto;}
.search-box input{border:none;background:transparent;font-size:13px;font-family:inherit;
                  color:#0d1b2a;outline:none;width:190px;}
.date-grp  {display:flex;align-items:center;gap:7px;flex-wrap:wrap;}
.date-lbl  {font-size:12px;color:#9ca3af;font-weight:500;}
.date-inp  {padding:5px 9px;border:1.5px solid #e5e7eb;border-radius:7px;font-size:12px;
            font-family:inherit;color:#374151;outline:none;}
.date-inp:focus{border-color:#a6ce39;}
.apply-btn {padding:5px 14px;border-radius:7px;font-size:12px;font-weight:600;
            background:#0d1b2a;color:white;border:none;cursor:pointer;font-family:inherit;}
.clear-lnk {font-size:12px;color:#dc2626;font-weight:600;}

/* ── TABLE ── */
.table-wrap{overflow-x:auto;}
table{width:100%;border-collapse:collapse;}
thead tr{background:#f8fafc;border-bottom:2px solid #e5e7eb;}
thead th{padding:11px 16px;text-align:left;font-size:11px;font-weight:700;color:#6b7280;
         letter-spacing:.07em;text-transform:uppercase;white-space:nowrap;}
tbody tr{border-bottom:1px solid #f8fafc;transition:background .12s;}
tbody tr:nth-child(odd) {background:white;}
tbody tr:nth-child(even){background:#fafafa;}
tbody tr:hover{background:#f0fdf4 !important;}
tbody td{padding:12px 16px;vertical-align:middle;}
.td-id    {font-size:12px;color:#9ca3af;font-weight:600;}
.td-client{font-size:13px;font-weight:600;color:#0d1b2a;}
.td-phone {font-size:11px;color:#9ca3af;}
.td-amount{font-size:14px;font-weight:700;color:#0d1b2a;white-space:nowrap;}
.td-method{font-size:12px;color:#374151;}
.td-date  {font-size:12px;color:#6b7280;white-space:nowrap;}
.td-msg   {font-size:11px;color:#6b7280;max-width:200px;}
.td-msg span{display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:190px;cursor:pointer;}
.td-empty {padding:56px;text-align:center;color:#9ca3af;font-size:14px;}

/* ── STATUS BADGE ── */
.badge{display:inline-flex;align-items:center;gap:5px;padding:4px 11px;
       border-radius:20px;font-size:12px;font-weight:600;white-space:nowrap;}
.badge-dot{width:6px;height:6px;border-radius:50%;flex-shrink:0;}
.badge-paid   {background:#f0fdf4;color:#16a34a;} .badge-paid    .badge-dot{background:#22c55e;}
.badge-pending{background:#fffbeb;color:#d97706;} .badge-pending .badge-dot{background:#f59e0b;}
.badge-failed {background:#fef2f2;color:#dc2626;} .badge-failed  .badge-dot{background:#ef4444;}

/* ── ACTION BUTTONS ── */
.action-wrap{display:flex;gap:6px;flex-wrap:nowrap;}
.act-btn{border:none;border-radius:7px;padding:6px 13px;font-size:11px;font-weight:700;
         cursor:pointer;font-family:inherit;transition:all .15s;white-space:nowrap;}
.act-paid   {background:#f0fdf4;color:#16a34a;border:1.5px solid #bbf7d0;}
.act-paid:hover   {background:#16a34a;color:white;}
.act-pending{background:#fffbeb;color:#d97706;border:1.5px solid #fde68a;}
.act-pending:hover{background:#d97706;color:white;}
.act-failed {background:#fef2f2;color:#dc2626;border:1.5px solid #fecaca;}
.act-failed:hover {background:#dc2626;color:white;}

/* ── TABLE FOOTER ── */
.t-footer{padding:12px 22px;background:#f8fafc;border-top:1px solid #f1f5f9;
          display:flex;justify-content:space-between;align-items:center;}
.t-footer span{font-size:12px;color:#9ca3af;}

/* ── MESSAGE MODAL ── */
.modal-ov{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center;}
.modal-ov.show{display:flex;}
.modal-box{background:white;border-radius:16px;padding:30px;max-width:500px;width:92%;
           box-shadow:0 20px 60px rgba(0,0,0,.22);animation:pop .25s cubic-bezier(.175,.885,.32,1.275);}
@keyframes pop{from{transform:scale(.75);opacity:0;}to{transform:scale(1);opacity:1;}}
.modal-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;}
.modal-head h3{font-size:15px;font-weight:700;color:#0d1b2a;}
.modal-close-x{background:none;border:none;font-size:20px;cursor:pointer;color:#9ca3af;line-height:1;}
.modal-msg{background:#f8fafc;border-radius:10px;padding:14px 16px;font-size:13px;
           color:#374151;line-height:1.8;word-break:break-word;white-space:pre-wrap;
           max-height:260px;overflow-y:auto;margin-bottom:18px;}
.modal-close-btn{background:#0d1b2a;color:white;border:none;border-radius:8px;
                 padding:10px 24px;font-size:13px;font-weight:600;cursor:pointer;font-family:inherit;}
.modal-close-btn:hover{background:#1e3a5f;}

/* ── TOAST ── */
.toast{position:fixed;bottom:28px;right:28px;background:#0d1b2a;color:white;
       padding:13px 22px;border-radius:10px;font-size:13px;font-weight:600;
       box-shadow:0 8px 24px rgba(0,0,0,.25);z-index:10000;display:none;
       align-items:center;gap:10px;}
.toast.show{display:flex;animation:slideUp .3s ease;}
@keyframes slideUp{from{transform:translateY(20px);opacity:0;}to{transform:translateY(0);opacity:1;}}
.toast-icon{font-size:16px;}

/* ── RESPONSIVE ── */
@media(max-width:900px){
    .sidebar{display:none;}
    .stats{grid-template-columns:1fr 1fr;}
}
@media(max-width:580px){
    .stats{grid-template-columns:1fr 1fr;}
    .content{padding:16px;}
}
</style>
</head>
<body>

<!-- ══ TOAST NOTIFICATION ══ -->
<div class="toast" id="toast">
    <span class="toast-icon" id="toastIcon">✓</span>
    <span id="toastMsg">Status updated</span>
</div>

<!-- ══ MESSAGE MODAL ══ -->
<div class="modal-ov" id="msgModal">
    <div class="modal-box">
        <div class="modal-head">
            <h3>📩 Bank / M-Pesa Confirmation Message</h3>
            <button class="modal-close-x" onclick="closeMsg()">✕</button>
        </div>
        <div class="modal-msg" id="modalMsgText"></div>
        <button class="modal-close-btn" onclick="closeMsg()">Close</button>
    </div>
</div>


<div class="layout">

    <!-- ══ SIDEBAR ══ -->
    <aside class="sidebar">
        <div class="sb-logo">
            <div class="sb-logo-box">GS</div>
            <div class="sb-brand">Grand Superior<br>Drycleaners</div>
        </div>
        <nav class="sb-nav">
            <a href="owner_dashboard.php" class="sb-link">
                <span class="licon">🏠</span> Dashboard
            </a>
            <a href="view_payments.php" class="sb-link active">
                <span class="licon">💳</span> Payments
            </a>
            <a href="owner_logout.php" class="sb-link" style="margin-top:auto;color:#f87171;">
                <span class="licon">🚪</span> Logout
            </a>
        </nav>
        <div class="sb-footer">Grand Superior &copy; <?= date('Y') ?></div>
    </aside>


    <!-- ══ MAIN CONTENT ══ -->
    <div class="main">

        <!-- Topbar -->
        <div class="topbar">
            <div class="topbar-title">💳 Client Payments</div>
            <div class="topbar-user">
                Logged in as <strong><?= $owner_name ?></strong>
                &nbsp;|&nbsp;
                <a href="owner_logout.php" style="color:#dc2626;font-weight:600;font-size:12px;">Logout</a>
            </div>
        </div>

        <div class="content">

            <!-- Stat cards -->
            <div class="stats">
                <div class="stat-card">
                    <div class="stat-label">Total Payments</div>
                    <div class="stat-value" style="color:#0d1b2a;">
                        <?= (int)($t['total_count'] ?? 0) ?>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Total Paid</div>
                    <div class="stat-value" style="color:#16a34a;">
                        KES <?= number_format((float)($t['total_paid'] ?? 0), 2) ?>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Pending</div>
                    <div class="stat-value" style="color:#d97706;">
                        KES <?= number_format((float)($t['total_pending'] ?? 0), 2) ?>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Failed</div>
                    <div class="stat-value" style="color:#dc2626;">
                        KES <?= number_format((float)($t['total_failed'] ?? 0), 2) ?>
                    </div>
                </div>
            </div>

            <!-- Payments card -->
            <div class="card">
                <div class="card-head">
                    <div class="card-head-left">
                        <span class="icon">📋</span>
                        <span class="title">All Client Payments</span>
                    </div>
                    <span class="count"><?= count($payments) ?> record<?= count($payments) !== 1 ? 's' : '' ?></span>
                </div>

                <!-- Toolbar: filters + search -->
                <form method="GET" action="">
                    <div class="toolbar">
                        <!-- Status pills -->
                        <div class="pill-grp">
                            <?php foreach (['All','Paid','Pending','Failed'] as $s):
                                $active = ($filter_status === $s) ? 'active' : '';
                                $url = '?' . http_build_query([
                                    'status' => $s,
                                    'from'   => $filter_from,
                                    'to'     => $filter_to,
                                    'q'      => $search,
                                ]);
                            ?>
                                <a href="<?= $url ?>" class="pill <?= $active ?>"><?= $s ?></a>
                            <?php endforeach; ?>
                        </div>

                        <!-- Date range -->
                        <input type="hidden" name="status" value="<?= htmlspecialchars($filter_status) ?>">
                        <div class="date-grp">
                            <span class="date-lbl">From</span>
                            <input type="date" name="from" class="date-inp"
                                   value="<?= htmlspecialchars($filter_from) ?>">
                            <span class="date-lbl">To</span>
                            <input type="date" name="to" class="date-inp"
                                   value="<?= htmlspecialchars($filter_to) ?>">
                            <button type="submit" class="apply-btn">Apply</button>
                            <?php if ($filter_from || $filter_to || $search): ?>
                                <a href="?status=<?= urlencode($filter_status) ?>" class="clear-lnk">Clear</a>
                            <?php endif; ?>
                        </div>

                        <!-- Search -->
                        <div class="search-box">
                            <span style="color:#9ca3af;font-size:14px;">🔍</span>
                            <input type="text" name="q" placeholder="Search message, method…"
                                   value="<?= htmlspecialchars($search) ?>">
                        </div>
                    </div>
                </form>

                <!-- Table -->
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Client</th>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>Confirmation Message</th>
                                <th>Status</th>
                                <th>Date & Time</th>
                                <th style="text-align:center;">Update Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($payments)): ?>
                            <tr>
                                <td colspan="8" class="td-empty">
                                    No payment records found for the selected filters.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($payments as $row):
                                $dt = new DateTime($row['created_at']);
                                $badgeClass = 'badge-' . strtolower($row['status']);
                            ?>
                            <tr id="row-<?= (int)$row['id'] ?>">
                                <td class="td-id">#<?= (int)$row['id'] ?></td>
                                <td>
                                    <div class="td-client"><?= htmlspecialchars($row['client_name']) ?></div>
                                    <div class="td-phone"><?= htmlspecialchars($row['client_phone']) ?></div>
                                </td>
                                <td class="td-amount"><?= fmt_kes($row['amount']) ?></td>
                                <td class="td-method"><?= htmlspecialchars($row['method'] ?? '—') ?></td>
                                <td class="td-msg">
                                    <?php if (!empty($row['bank_message'])): ?>
                                        <span
                                            title="Click to view full message"
                                            onclick="showMsg(<?= htmlspecialchars(json_encode($row['bank_message']), ENT_QUOTES) ?>)"
                                        ><?= htmlspecialchars($row['bank_message']) ?></span>
                                    <?php else: ?>
                                        <span style="color:#d1d5db;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span
                                        class="badge <?= $badgeClass ?>"
                                        id="badge-<?= (int)$row['id'] ?>"
                                    >
                                        <span class="badge-dot"></span>
                                        <span id="badge-txt-<?= (int)$row['id'] ?>"><?= htmlspecialchars($row['status']) ?></span>
                                    </span>
                                </td>
                                <td class="td-date">
                                    <?= $dt->format('M j, Y') ?><br>
                                    <span style="font-size:11px;color:#9ca3af;"><?= $dt->format('h:i A') ?></span>
                                </td>
                                <td>
                                    <div class="action-wrap">
                                        <button
                                            class="act-btn act-paid"
                                            onclick="updateStatus(<?= (int)$row['id'] ?>, 'Paid')"
                                            title="Mark as Paid"
                                        >✓ Paid</button>
                                        <button
                                            class="act-btn act-pending"
                                            onclick="updateStatus(<?= (int)$row['id'] ?>, 'Pending')"
                                            title="Mark as Pending"
                                        >⏳ Pending</button>
                                        <button
                                            class="act-btn act-failed"
                                            onclick="updateStatus(<?= (int)$row['id'] ?>, 'Failed')"
                                            title="Mark as Failed"
                                        >✕ Failed</button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="t-footer">
                    <span><?= count($payments) ?> record<?= count($payments) !== 1 ? 's' : '' ?> shown</span>
                    <span>Grand Superior Drycleaners &copy; <?= date('Y') ?></span>
                </div>

            </div><!-- /.card -->

        </div><!-- /.content -->
    </div><!-- /.main -->
</div><!-- /.layout -->


<!-- ══ JAVASCRIPT ══ -->
<script>
/* ── View full bank message in modal ── */
function showMsg(text) {
    document.getElementById('modalMsgText').textContent = text;
    document.getElementById('msgModal').classList.add('show');
}
function closeMsg() {
    document.getElementById('msgModal').classList.remove('show');
}
document.getElementById('msgModal').addEventListener('click', function (e) {
    if (e.target === this) closeMsg();
});

/* ── Update payment status via AJAX (no page reload) ── */
function updateStatus(id, newStatus) {
    var fd = new FormData();
    fd.append('update_status', '1');
    fd.append('pay_id',        id);
    fd.append('new_status',    newStatus);

    fetch(window.location.pathname, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: fd
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
        if (data.ok) {
            /* update badge in-place */
            var badgeMap = {
                'Paid':    ['badge-paid',    '✓ Paid'],
                'Pending': ['badge-pending', '⏳ Pending'],
                'Failed':  ['badge-failed',  '✕ Failed'],
            };
            var b   = document.getElementById('badge-' + id);
            var txt = document.getElementById('badge-txt-' + id);
            if (b && badgeMap[data.status]) {
                b.className     = 'badge ' + badgeMap[data.status][0];
                txt.textContent = data.status;
            }
            showToast('✓', 'Payment #' + id + ' marked as ' + data.status + ' (booking updated)', '#16a34a');
        } else {
            showToast('✕', 'Update failed. Try again.', '#dc2626');
        }
    })
    .catch(function () {
        showToast('✕', 'Network error. Try again.', '#dc2626');
    });
}

/* ── Toast notification ── */
function showToast(icon, msg, color) {
    var t = document.getElementById('toast');
    document.getElementById('toastIcon').textContent = icon;
    document.getElementById('toastMsg').textContent  = msg;
    t.style.background = color === '#16a34a' ? '#0d1b2a' : color;
    t.classList.add('show');
    setTimeout(function () { t.classList.remove('show'); }, 3200);
}
</script>

</body>
</html>
