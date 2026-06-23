<?php
// ============================================================
//  Grand Superior Drycleaners — Client Payment Portal
//  Full single-file PHP | uses connection.php for DB
// ============================================================

session_start();
include 'connection.php';

// ── AUTH GUARD ───────────────────────────────────────────────
if (!isset($_SESSION['client_id'])) {
    header("Location: client_login.php");
    exit();
}

$conn->set_charset('utf8mb4');

$client_id   = (int) $_SESSION['client_id'];
$client_name = htmlspecialchars($_SESSION['client_name'] ?? 'Client');

// ── CREATE TABLE IF NOT EXISTS ───────────────────────────────
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

// ── HANDLE FORM SUBMISSION ───────────────────────────────────
$show_success = false;
$error_msg    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_payment'])) {

    $bank_message = trim($_POST['bank_message'] ?? '');
    $amount       = trim($_POST['amount']       ?? '');
    $method       = trim($_POST['method']       ?? 'M-Pesa');

    if ($bank_message === '') {
        $error_msg = 'Please paste your M-Pesa / bank confirmation message before submitting.';
    } else {

        /*
         * AMOUNT: MySQLi bind_param 'd' (double) cannot receive PHP null —
         * it silently fails on some builds. We always send a float.
         * If the user left amount blank we store 0.00.
         */
        $amount_val = ($amount !== '') ? (float) $amount : 0.00;

        /*
         * METHOD: default to 'M-Pesa' if somehow empty
         */
        $method_val = ($method !== '') ? $method : 'M-Pesa';

        /*
         * INSERT — types: i=client_id(int), d=amount(float),
         *                  s=method(string), s=bank_message(string)
         * status is hardcoded 'Pending' in the SQL, created_at uses NOW()
         */
        $stmt = $conn->prepare(
            "INSERT INTO payments (client_id, amount, method, status, bank_message, created_at)
             VALUES (?, ?, ?, 'Pending', ?, NOW())"
        );

        if (!$stmt) {
            $error_msg = 'Prepare failed: ' . $conn->error;
        } else {
            $stmt->bind_param('idss', $client_id, $amount_val, $method_val, $bank_message);

            if ($stmt->execute()) {
                $show_success = true;
                // Clear fields so they don't repopulate after success
                $bank_message = '';
                $amount       = '';
                $method       = 'M-Pesa';
            } else {
                $error_msg = 'Insert failed: ' . $stmt->error;
            }

            $stmt->close();
        }
    }
}

// ── FETCH PAYMENT HISTORY ────────────────────────────────────
$filter_status = $_GET['status'] ?? 'All';
$filter_from   = $_GET['from']   ?? '';
$filter_to     = $_GET['to']     ?? '';

$where  = ['p.client_id = ?'];
$params = [$client_id];
$types  = 'i';

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

$sql  = 'SELECT * FROM payments p WHERE ' . implode(' AND ', $where) . ' ORDER BY p.created_at DESC';
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ── TOTALS ───────────────────────────────────────────────────
$stmt_t = $conn->prepare("
    SELECT
        COUNT(*)                                                          AS total_count,
        SUM(CASE WHEN status='Paid'    THEN IFNULL(amount,0) ELSE 0 END) AS total_paid,
        SUM(CASE WHEN status='Pending' THEN IFNULL(amount,0) ELSE 0 END) AS total_pending,
        SUM(CASE WHEN status='Failed'  THEN IFNULL(amount,0) ELSE 0 END) AS total_failed
    FROM payments WHERE client_id = ?
");
$stmt_t->bind_param('i', $client_id);
$stmt_t->execute();
$totals = $stmt_t->get_result()->fetch_assoc();
$stmt_t->close();

$conn->close();

// ── HELPERS ──────────────────────────────────────────────────
function fmt_kes($val) {
    if ($val === null || $val === '') return '—';
    return 'KES ' . number_format((float)$val, 2);
}

function status_badge($status) {
    $map = [
        'Paid'    => ['#f0fdf4','#16a34a','#22c55e'],
        'Pending' => ['#fffbeb','#d97706','#f59e0b'],
        'Failed'  => ['#fef2f2','#dc2626','#ef4444'],
    ];
    $c = $map[$status] ?? ['#f3f4f6','#6b7280','#9ca3af'];
    return '<span style="display:inline-flex;align-items:center;gap:5px;padding:4px 11px;'
         . 'border-radius:20px;background:' . $c[0] . ';color:' . $c[1] . ';font-size:12px;font-weight:600;">'
         . '<span style="width:6px;height:6px;border-radius:50%;background:' . $c[2] . ';flex-shrink:0;"></span>'
         . htmlspecialchars($status) . '</span>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Client Payment Portal — Grand Superior Drycleaners</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
/* ── RESET ── */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Poppins',sans-serif;background:#f0f2f5;color:#0d1b2a;min-height:100vh;padding:32px 16px;}
a{text-decoration:none;}

/* ── LAYOUT ── */
.wrapper  {max-width:1040px;margin:0 auto;}
.card     {background:white;border-radius:14px;box-shadow:0 2px 16px rgba(0,0,0,.07);overflow:hidden;margin-bottom:20px;}
.card-head{background:#0d1b2a;padding:15px 24px;display:flex;align-items:center;gap:10px;}
.card-head .icon {font-size:18px;}
.card-head .title{color:white;font-weight:600;font-size:15px;}
.card-body{padding:22px 24px;}

/* ── HEADER ── */
.top-header{display:flex;align-items:center;gap:14px;margin-bottom:28px;flex-wrap:wrap;}
.logo-box{width:48px;height:48px;border-radius:12px;background:#a6ce39;display:flex;align-items:center;
          justify-content:center;font-weight:800;font-size:17px;color:#0d1b2a;flex-shrink:0;}
.brand-label{font-size:11px;font-weight:600;color:#9ca3af;letter-spacing:.09em;text-transform:uppercase;}
.page-title {font-size:22px;font-weight:700;color:#0d1b2a;line-height:1.2;}

/* ── PAYMENT DETAIL BOXES ── */
.detail-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
.detail-box {border:2px solid #a6ce39;border-radius:12px;padding:18px 22px;
             display:flex;justify-content:space-between;align-items:center;background:#fafff0;}
.detail-label{font-size:10px;font-weight:700;color:#a6ce39;text-transform:uppercase;letter-spacing:.09em;margin-bottom:6px;}
.detail-value{font-size:32px;font-weight:800;color:#0d1b2a;letter-spacing:3px;}
.copy-btn{background:#f3f4f6;border:none;border-radius:8px;padding:9px 16px;font-size:12px;font-weight:700;
          cursor:pointer;color:#6b7280;font-family:inherit;transition:background .15s,color .15s;white-space:nowrap;}
.copy-btn:hover{background:#a6ce39;color:#0d1b2a;}
.hint{font-size:13px;color:#6b7280;line-height:1.7;margin-bottom:18px;}

/* ── FORM ── */
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:16px;}
.form-group label{display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:6px;}
.form-control{width:100%;padding:10px 13px;border:1.5px solid #e5e7eb;border-radius:8px;
              font-size:13px;font-family:inherit;color:#0d1b2a;outline:none;transition:border-color .15s;}
.form-control:focus{border-color:#a6ce39;}
textarea.form-control{resize:vertical;min-height:130px;line-height:1.7;}
.required{color:#dc2626;}
.alert{border-radius:9px;padding:12px 16px;font-size:13px;font-weight:500;line-height:1.6;margin-bottom:14px;}
.alert-error{background:#fef2f2;border:1px solid #fecaca;color:#dc2626;}
.submit-btn{background:#a6ce39;color:#0d1b2a;border:none;border-radius:9px;padding:12px 28px;
            font-size:14px;font-weight:700;cursor:pointer;font-family:inherit;transition:background .2s;}
.submit-btn:hover{background:#8bb32f;}

/* ── SUMMARY STRIP ── */
.summary-strip{display:grid;grid-template-columns:repeat(4,1fr);border-bottom:1px solid #f1f5f9;}
.summary-cell{padding:16px 20px;}
.summary-cell:not(:last-child){border-right:1px solid #f1f5f9;}
.summary-label{font-size:10px;color:#9ca3af;font-weight:700;text-transform:uppercase;letter-spacing:.08em;margin-bottom:5px;}
.summary-value{font-size:19px;font-weight:700;}

/* ── FILTER BAR ── */
.filter-bar{padding:14px 24px;border-bottom:1px solid #f1f5f9;display:flex;flex-wrap:wrap;align-items:center;gap:10px;}
.pill-group{display:flex;gap:6px;}
.pill{padding:5px 14px;border-radius:20px;font-size:12px;font-weight:600;border:2px solid #e5e7eb;
      background:transparent;color:#6b7280;cursor:pointer;text-decoration:none;font-family:inherit;transition:all .15s;}
.pill:hover,.pill.active{border-color:#a6ce39;background:#a6ce39;color:#0d1b2a;}
.date-filters{display:flex;align-items:center;gap:8px;margin-left:auto;flex-wrap:wrap;}
.date-label{font-size:12px;color:#9ca3af;font-weight:500;}
.date-input{padding:5px 10px;border:1.5px solid #e5e7eb;border-radius:7px;font-size:12px;
            font-family:inherit;color:#374151;outline:none;transition:border-color .15s;}
.date-input:focus{border-color:#a6ce39;}
.clear-link{font-size:12px;color:#dc2626;font-weight:600;}

/* ── TABLE ── */
.table-wrap{overflow-x:auto;}
table{width:100%;border-collapse:collapse;}
thead tr{background:#f8fafc;border-bottom:2px solid #e5e7eb;}
thead th{padding:11px 18px;text-align:left;font-size:11px;font-weight:700;color:#6b7280;
         letter-spacing:.07em;text-transform:uppercase;white-space:nowrap;}
tbody tr{border-bottom:1px solid #f8fafc;}
tbody tr:nth-child(odd) {background:white;}
tbody tr:nth-child(even){background:#fafafa;}
tbody tr:hover{background:#f0fdf4 !important;}
tbody td{padding:13px 18px;}
.td-id    {font-size:12px;color:#9ca3af;font-weight:600;}
.td-amount{font-size:14px;font-weight:700;color:#0d1b2a;white-space:nowrap;}
.td-method{font-size:13px;color:#374151;white-space:nowrap;}
.td-date  {font-size:12px;color:#6b7280;white-space:nowrap;}
.td-time  {font-size:11px;color:#9ca3af;}
.td-msg   {font-size:11px;color:#9ca3af;max-width:220px;}
.td-msg span{display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:200px;}
.td-empty {padding:56px 18px;text-align:center;color:#9ca3af;font-size:14px;}
.table-footer{padding:13px 24px;background:#f8fafc;border-top:1px solid #f1f5f9;
              display:flex;justify-content:space-between;align-items:center;}
.table-footer span{font-size:12px;color:#9ca3af;}

/* ── SUCCESS MODAL ── */
.modal-overlay{
    display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);
    z-index:9999;align-items:center;justify-content:center;
}
.modal-overlay.show{display:flex;}
.modal-box{
    background:white;border-radius:18px;padding:44px 40px;max-width:420px;width:90%;
    text-align:center;box-shadow:0 20px 60px rgba(0,0,0,.2);
    animation:popIn .3s cubic-bezier(.175,.885,.32,1.275);
}
@keyframes popIn{from{transform:scale(.7);opacity:0;}to{transform:scale(1);opacity:1;}}
.modal-icon{
    width:72px;height:72px;border-radius:50%;background:#f0fdf4;border:3px solid #a6ce39;
    display:flex;align-items:center;justify-content:center;margin:0 auto 20px;
    font-size:34px;
}
.modal-title{font-size:20px;font-weight:700;color:#0d1b2a;margin-bottom:10px;}
.modal-text {font-size:13px;color:#6b7280;line-height:1.8;margin-bottom:28px;}
.modal-close{
    background:#a6ce39;color:#0d1b2a;border:none;border-radius:9px;
    padding:12px 36px;font-size:14px;font-weight:700;cursor:pointer;
    font-family:inherit;transition:background .2s;
}
.modal-close:hover{background:#8bb32f;}

/* ── RESPONSIVE ── */
@media(max-width:640px){
    .detail-grid   {grid-template-columns:1fr;}
    .form-grid     {grid-template-columns:1fr;}
    .summary-strip {grid-template-columns:1fr 1fr;}
    .date-filters  {margin-left:0;}
    .top-header    {flex-wrap:wrap;}
}
</style>
</head>
<body>

<!-- ══════════════════════════════════════════════════════════
     SUCCESS MODAL
══════════════════════════════════════════════════════════ -->
<div class="modal-overlay <?= $show_success ? 'show' : '' ?>" id="successModal">
    <div class="modal-box">
        <div class="modal-icon">✅</div>
        <div class="modal-title">Payment Submitted!</div>
        <div class="modal-text">
            Your payment confirmation has been received and saved.<br>
            Our team will verify the transaction and update your
            status to <strong>Paid</strong> shortly.
        </div>
        <button class="modal-close" onclick="closeModal()">OK, Got It</button>
    </div>
</div>


<div class="wrapper">

    <!-- ══ TOP HEADER ══ -->
    <div class="top-header">
        <div class="logo-box">GS</div>
        <div>
            <div class="brand-label">Grand Superior Drycleaners</div>
            <div class="page-title">Client Payment Portal</div>
        </div>
        <div style="margin-left:auto;font-size:13px;color:#6b7280;">
            Welcome, <strong><?= $client_name ?></strong>
            &nbsp;|&nbsp;
            <a href="client_dashboard.php" style="color:#a6ce39;font-weight:600;">← Dashboard</a>
        </div>
    </div>


    <!-- ══ SECTION 1 : PAYMENT DETAILS ══ -->
    <div class="card">
        <div class="card-head">
            <span class="icon">🏦</span>
            <span class="title">Payment Details</span>
        </div>
        <div class="card-body">
            <p class="hint">
                Send your payment via M-Pesa using the details below, then paste your
                confirmation SMS in the form below so our team can verify it.
            </p>
            <div class="detail-grid">
                <div class="detail-box">
                    <div>
                        <div class="detail-label">Paybill Number</div>
                        <div class="detail-value">521000</div>
                    </div>
                    <button class="copy-btn" onclick="copyText('521000', this)">Copy</button>
                </div>
                <div class="detail-box">
                    <div>
                        <div class="detail-label">Account Number</div>
                        <div class="detail-value">1338</div>
                    </div>
                    <button class="copy-btn" onclick="copyText('1338', this)">Copy</button>
                </div>
            </div>
        </div>
    </div>


    <!-- ══ SECTION 2 : SUBMIT PAYMENT FORM ══ -->
    <div class="card">
        <div class="card-head">
            <span class="icon">📩</span>
            <span class="title">Submit Payment Confirmation</span>
        </div>
        <div class="card-body">
            <p class="hint">
                After completing your M-Pesa transaction, fill in the amount and method,
                then paste the full confirmation SMS. Our team will verify and update your status.
            </p>

            <?php if ($error_msg): ?>
                <div class="alert alert-error">✕ <?= htmlspecialchars($error_msg) ?></div>
            <?php endif; ?>

            <form method="POST" action="" id="paymentForm">

                <div class="form-grid">
                    <div class="form-group">
                        <label for="amount">Amount Paid (KES)</label>
                        <input
                            type="number"
                            id="amount"
                            name="amount"
                            class="form-control"
                            placeholder="e.g. 1500"
                            min="0"
                            step="0.01"
                            value="<?= htmlspecialchars($amount ?? '') ?>"
                        >
                    </div>
                    <div class="form-group">
                        <label for="method">Payment Method</label>
                        <select id="method" name="method" class="form-control">
                            <?php
                            $methods = ['M-Pesa','Cash','Credit Card','Bank Transfer','Cheque','Other'];
                            $sel     = $method ?? 'M-Pesa';
                            foreach ($methods as $m):
                                $selected = ($sel === $m) ? 'selected' : '';
                            ?>
                                <option value="<?= $m ?>" <?= $selected ?>><?= $m ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:20px;">
                    <label for="bank_message">
                        M-Pesa / Bank Confirmation Message
                        <span class="required">*</span>
                    </label>
                    <textarea
                        id="bank_message"
                        name="bank_message"
                        class="form-control"
                        placeholder="Paste your full M-Pesa SMS here...

Example:
QAB123XYZ Confirmed. KSh 1,500 sent to GRAND SUPERIOR DRYCLEANERS
521000 Account 1338 on 19/6/26 at 9:05 AM.
New M-PESA balance is KSh 3,200.00."
                    ><?= htmlspecialchars($bank_message ?? '') ?></textarea>
                </div>

                <!-- Hidden flag — PHP checks this, NOT the button name,
                     so disabling the button never blocks the POST value -->
                <input type="hidden" name="submit_payment" id="submit_flag" value="1">

                <button type="submit" class="submit-btn" id="submitBtn">
                    Submit Payment
                </button>
            </form>
        </div>
    </div>


    <!-- ══ SECTION 3 : PAYMENT HISTORY ══ -->
    <div class="card">
        <div class="card-head">
            <span class="icon">📋</span>
            <span class="title">My Payment History</span>
        </div>

        <!-- Summary strip -->
        <div class="summary-strip">
            <div class="summary-cell" style="background:#f8fafc;">
                <div class="summary-label">Total Records</div>
                <div class="summary-value" style="color:#0d1b2a;">
                    <?= (int)($totals['total_count'] ?? 0) ?>
                </div>
            </div>
            <div class="summary-cell" style="background:#f0fdf4;">
                <div class="summary-label">Total Paid</div>
                <div class="summary-value" style="color:#16a34a;">
                    KES <?= number_format((float)($totals['total_paid'] ?? 0), 2) ?>
                </div>
            </div>
            <div class="summary-cell" style="background:#fffbeb;">
                <div class="summary-label">Pending</div>
                <div class="summary-value" style="color:#d97706;">
                    KES <?= number_format((float)($totals['total_pending'] ?? 0), 2) ?>
                </div>
            </div>
            <div class="summary-cell" style="background:#fef2f2;">
                <div class="summary-label">Failed</div>
                <div class="summary-value" style="color:#dc2626;">
                    KES <?= number_format((float)($totals['total_failed'] ?? 0), 2) ?>
                </div>
            </div>
        </div>

        <!-- Filter bar -->
        <div class="filter-bar">
            <div class="pill-group">
                <?php foreach (['All','Paid','Pending','Failed'] as $s):
                    $active = ($filter_status === $s) ? 'active' : '';
                    $url = '?' . http_build_query([
                        'status' => $s,
                        'from'   => $filter_from,
                        'to'     => $filter_to,
                    ]);
                ?>
                    <a href="<?= $url ?>" class="pill <?= $active ?>"><?= $s ?></a>
                <?php endforeach; ?>
            </div>

            <form method="GET" action="" class="date-filters">
                <input type="hidden" name="status" value="<?= htmlspecialchars($filter_status) ?>">
                <span class="date-label">From</span>
                <input type="date" name="from" class="date-input"
                       value="<?= htmlspecialchars($filter_from) ?>">
                <span class="date-label">To</span>
                <input type="date" name="to" class="date-input"
                       value="<?= htmlspecialchars($filter_to) ?>">
                <button type="submit" class="pill">Apply</button>
                <?php if ($filter_from || $filter_to): ?>
                    <a href="?status=<?= urlencode($filter_status) ?>" class="clear-link">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Table -->
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Amount (KES)</th>
                        <th>Method</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Confirmation Message</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($payments)): ?>
                    <tr>
                        <td colspan="7" class="td-empty">
                            No payment records found for the selected period.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($payments as $row):
                        $dt = new DateTime($row['created_at']);
                    ?>
                    <tr>
                        <td class="td-id">#<?= (int)$row['id'] ?></td>
                        <td class="td-amount"><?= fmt_kes($row['amount']) ?></td>
                        <td class="td-method">
                            <?= $row['method'] ? htmlspecialchars($row['method']) : '—' ?>
                        </td>
                        <td><?= status_badge($row['status']) ?></td>
                        <td class="td-date"><?= $dt->format('M j, Y') ?></td>
                        <td class="td-time"><?= $dt->format('h:i A') ?></td>
                        <td class="td-msg">
                            <?php if (!empty($row['bank_message'])): ?>
                                <span title="<?= htmlspecialchars($row['bank_message']) ?>">
                                    <?= htmlspecialchars($row['bank_message']) ?>
                                </span>
                            <?php else: ?>
                                <span style="color:#d1d5db;">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Table footer -->
        <div class="table-footer">
            <span>
                <?= count($payments) ?> record<?= count($payments) !== 1 ? 's' : '' ?> shown
            </span>
            <span>Grand Superior Drycleaners &copy; <?= date('Y') ?></span>
        </div>

    </div><!-- /.card -->

</div><!-- /.wrapper -->


<!-- ══ JAVASCRIPT ══ -->
<script>
function copyText(text, btn) {
    navigator.clipboard.writeText(text).then(function () {
        var orig = btn.textContent;
        btn.textContent = '✓ Copied';
        btn.style.background = '#a6ce39';
        btn.style.color = '#0d1b2a';
        setTimeout(function () {
            btn.textContent = orig;
            btn.style.background = '';
            btn.style.color = '';
        }, 1600);
    });
}

function closeModal() {
    document.getElementById('successModal').classList.remove('show');
}

document.getElementById('successModal').addEventListener('click', function (e) {
    if (e.target === this) closeModal();
});

document.getElementById('paymentForm').addEventListener('submit', function () {
    // Mark the hidden flag FIRST so PHP receives submit_payment=1
    document.getElementById('submit_flag').value = '1';
    // Then visually disable the button (purely cosmetic — does NOT affect POST)
    var btn = document.getElementById('submitBtn');
    btn.textContent = 'Submitting…';
    btn.style.background = '#d1d5db';
    btn.style.cursor = 'not-allowed';
    btn.setAttribute('disabled', 'disabled'); // cosmetic only; hidden field carries the flag
});
</script>

</body>
</html>
