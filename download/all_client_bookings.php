
<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
 
include 'connection.php';
 
/* =========================
   OWNER AUTH CHECK
========================= */
if (!isset($_SESSION['owner_id'])) {
    header("Location: owner_login.php");
    exit();
}
 
if (!$conn || (method_exists($conn, 'connect_error') && $conn->connect_error)) {
    die("Database connection failed. Please try again later.");
}
 
/* =========================
   FETCH ALL BOOKINGS
   NOTE: the `bookings` table has its own `payment_status` column,
   but actual payment records (Paid/Unpaid/Partial, amount, method)
   live in the `payments` table linked via payments.booking_id.
   We pull the most recent payment record per booking and prefer
   that as the source of truth; fall back to bookings.payment_status
   if no payment record exists yet.
========================= */
$sql = "SELECT 
            b.id,
            b.client_id,
            c.name AS client_name,
            c.email,
            b.service_type,
            b.pickup_date,
            b.delivery_date,
            b.address,
            b.notes,
            b.created_at,
            b.payment_status,
            (SELECT p.status
               FROM payments p
              WHERE p.booking_id = b.id
              ORDER BY p.created_at DESC
              LIMIT 1) AS payment_record_status,
            (SELECT p.amount
               FROM payments p
              WHERE p.booking_id = b.id
              ORDER BY p.created_at DESC
              LIMIT 1) AS payment_amount
        FROM bookings b
        LEFT JOIN clients c ON b.client_id = c.id
        ORDER BY b.created_at DESC";
 
$result = $conn->query($sql);
if (!$result) {
    error_log("all_client_bookings query error: " . $conn->error);
}
 
/* small helper for the avatar initials */
function initials($name) {
    $name = trim((string)$name);
    if ($name === '') return '?';
    $parts = preg_split('/\s+/', $name);
    $i = strtoupper(substr($parts[0], 0, 1));
    if (count($parts) > 1) {
        $i .= strtoupper(substr($parts[count($parts) - 1], 0, 1));
    }
    return $i;
}
 
/* 
   Normalize whatever is in payment_status into a known bucket so the
   badge always renders sensibly, even for values like "pending pickup"
   or blank/NULL.
   Returns [cssClass, label]
*/
function paymentStatusMeta($status) {
    $raw = trim((string)$status);
    if ($raw === '') return ['Pending', 'Pending'];
 
    $s = strtolower($raw);
 
    if (strpos($s, 'cancel') !== false)            return ['Cancelled', $raw];
    if (strpos($s, 'deliver') !== false)           return ['Delivered', $raw];
    if (strpos($s, 'paid') !== false && strpos($s,'unpaid') === false) return ['Paid', $raw];
    if (strpos($s, 'partial') !== false)           return ['Partial', $raw];
    if (strpos($s, 'unpaid') !== false)            return ['Unpaid', $raw];
    if (strpos($s, 'pickup') !== false)            return ['PendingPickup', $raw];
    if (strpos($s, 'pending') !== false)           return ['Pending', $raw];
 
    return ['Other', $raw];
}
 
$statusOrder = ['Pending', 'PendingPickup', 'Unpaid', 'Partial', 'Paid', 'Delivered', 'Cancelled', 'Other'];
$statusLabels = [
    'Pending'       => 'Pending',
    'PendingPickup' => 'Pending Pickup',
    'Unpaid'        => 'Unpaid',
    'Partial'       => 'Partial',
    'Paid'          => 'Paid',
    'Delivered'     => 'Delivered',
    'Cancelled'     => 'Cancelled',
    'Other'         => 'Other',
];
 
$totalCount = $result ? $result->num_rows : 0;
?>
 
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>All Client Bookings · Grand Superior</title>
 
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
 
<style>
:root{
    --ink:#0d1b2a;
    --ink-soft:#16283c;
    --lime:#a6ce39;
    --lime-dark:#8bbf2f;
    --paper:#f4f6f9;
    --surface:#ffffff;
    --line:#e3e7ee;
    --muted:#65748a;
 
    --st-pending-bg:#fef3e2;  --st-pending-fg:#b3680a; --st-pending-dot:#f59e0b;
    --st-pickup-bg:#e8f1fd;   --st-pickup-fg:#1d63c4;  --st-pickup-dot:#3b82f6;
    --st-unpaid-bg:#fdeaea;   --st-unpaid-fg:#b91c1c;  --st-unpaid-dot:#ef4444;
    --st-partial-bg:#fef3e2;  --st-partial-fg:#b3680a; --st-partial-dot:#f59e0b;
    --st-paid-bg:#e6f6ec;     --st-paid-fg:#15803d;    --st-paid-dot:#22c55e;
    --st-delivered-bg:#eef9e2;--st-delivered-fg:#5a8a16;--st-delivered-dot:#a6ce39;
    --st-cancelled-bg:#fdeaea;--st-cancelled-fg:#b91c1c;--st-cancelled-dot:#ef4444;
    --st-other-bg:#eef1f5;    --st-other-fg:#475569;   --st-other-dot:#94a3b8;
}
 
*{
    margin:0;
    padding:0;
    box-sizing:border-box;
}
 
body{
    font-family:'Inter',sans-serif;
    background:var(--paper);
    color:var(--ink);
}
 
/* ===== TOP BAR ===== */
.navbar{
    background:var(--ink);
    color:white;
    padding:16px 28px;
    display:flex;
    align-items:center;
}
 
.logo{
    display:flex;
    align-items:center;
    gap:12px;
}
 
.logo img{
    width:36px;
    height:36px;
    border-radius:8px;
    background:white;
    padding:3px;
    display:block;
}
 
.logo span{
    font-family:'Sora',sans-serif;
    font-weight:600;
    font-size:17px;
}
 
.container{
    max-width:1100px;
    margin:0 auto;
    padding:32px 20px 60px;
}
 
.back{
    display:inline-flex;
    align-items:center;
    gap:7px;
    margin-bottom:22px;
    text-decoration:none;
    color:var(--muted);
    font-weight:500;
    font-size:13.5px;
}
 
.back:hover{
    color:var(--ink);
}
 
.back svg{
    width:14px;
    height:14px;
    stroke:currentColor;
}
 
/* ===== PAGE HEADER ===== */
.page-head{
    display:flex;
    align-items:flex-end;
    justify-content:space-between;
    flex-wrap:wrap;
    gap:16px;
    margin-bottom:22px;
}
 
.page-head h1{
    font-family:'Sora',sans-serif;
    font-size:24px;
    font-weight:600;
    margin-bottom:4px;
}
 
.page-head .count{
    font-size:13.5px;
    color:var(--muted);
}
 
.page-head .count strong{
    color:var(--ink);
    font-weight:600;
}
 
/* ===== TOOLBAR ===== */
.toolbar{
    display:flex;
    align-items:center;
    gap:12px;
    flex-wrap:wrap;
    background:var(--surface);
    border:1px solid var(--line);
    border-radius:14px;
    padding:12px 14px;
    margin-bottom:22px;
}
 
.search-wrap{
    position:relative;
    flex:1 1 220px;
    min-width:200px;
}
 
.search-wrap svg{
    position:absolute;
    left:12px;
    top:50%;
    transform:translateY(-50%);
    width:15px;
    height:15px;
    stroke:var(--muted);
}
 
.search-wrap input{
    width:100%;
    padding:10px 12px 10px 36px;
    border:1px solid var(--line);
    border-radius:9px;
    font-family:'Inter',sans-serif;
    font-size:13.5px;
    background:var(--paper);
    outline:none;
    transition:border-color .15s ease, box-shadow .15s ease;
}
 
.search-wrap input:focus{
    border-color:var(--lime-dark);
    box-shadow:0 0 0 4px rgba(166,206,57,0.18);
    background:white;
}
 
.pills{
    display:flex;
    gap:6px;
    flex-wrap:wrap;
}
 
.pill{
    border:1px solid var(--line);
    background:var(--paper);
    color:var(--muted);
    padding:7px 13px;
    border-radius:999px;
    font-size:12.5px;
    font-weight:600;
    cursor:pointer;
    font-family:'Inter',sans-serif;
    transition:all .12s ease;
}
 
.pill:hover{
    border-color:var(--lime-dark);
    color:var(--ink);
}
 
.pill.active{
    background:var(--ink);
    border-color:var(--ink);
    color:white;
}
 
/* ===== CARD ===== */
.card{
    background:var(--surface);
    border:1px solid var(--line);
    padding:18px 20px;
    margin-bottom:14px;
    border-radius:14px;
    transition:box-shadow .15s ease;
}
 
.card:hover{
    box-shadow:0 8px 22px -12px rgba(13,27,42,0.18);
}
 
.card-top{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:14px;
    flex-wrap:wrap;
    margin-bottom:16px;
    padding-bottom:14px;
    border-bottom:1px solid var(--line);
}
 
.client-id{
    display:flex;
    align-items:center;
    gap:12px;
}
 
.avatar{
    width:38px;
    height:38px;
    border-radius:50%;
    background:var(--ink);
    color:var(--lime);
    font-family:'Sora',sans-serif;
    font-weight:600;
    font-size:13px;
    display:flex;
    align-items:center;
    justify-content:center;
    flex-shrink:0;
}
 
.client-name{
    font-weight:600;
    font-size:14.5px;
}
 
.client-email{
    font-size:12.5px;
    color:var(--muted);
}
 
/* STATUS PILL */
.status-pill{
    display:inline-flex;
    align-items:center;
    gap:6px;
    padding:6px 12px;
    border-radius:999px;
    font-size:12px;
    font-weight:600;
}
 
.status-pill .dot{
    width:6px;
    height:6px;
    border-radius:50%;
}
 
.Pending        { background:var(--st-pending-bg);   color:var(--st-pending-fg); }
.Pending .dot         { background:var(--st-pending-dot); }
.PendingPickup  { background:var(--st-pickup-bg);    color:var(--st-pickup-fg); }
.PendingPickup .dot   { background:var(--st-pickup-dot); }
.Unpaid         { background:var(--st-unpaid-bg);    color:var(--st-unpaid-fg); }
.Unpaid .dot          { background:var(--st-unpaid-dot); }
.Partial        { background:var(--st-partial-bg);   color:var(--st-partial-fg); }
.Partial .dot         { background:var(--st-partial-dot); }
.Paid           { background:var(--st-paid-bg);      color:var(--st-paid-fg); }
.Paid .dot            { background:var(--st-paid-dot); }
.Delivered      { background:var(--st-delivered-bg); color:var(--st-delivered-fg); }
.Delivered .dot       { background:var(--st-delivered-dot); }
.Cancelled      { background:var(--st-cancelled-bg); color:var(--st-cancelled-fg); }
.Cancelled .dot       { background:var(--st-cancelled-dot); }
.Other          { background:var(--st-other-bg);     color:var(--st-other-fg); }
.Other .dot           { background:var(--st-other-dot); }
 
/* META GRID */
.grid{
    display:grid;
    grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));
    gap:16px;
    margin-bottom:14px;
}
 
.meta-item{
    display:flex;
    gap:10px;
}
 
.meta-item svg{
    width:15px;
    height:15px;
    stroke:var(--lime-dark);
    flex-shrink:0;
    margin-top:2px;
}
 
.meta-item .label{
    font-size:11px;
    text-transform:uppercase;
    letter-spacing:0.4px;
    color:var(--muted);
    font-weight:600;
    margin-bottom:2px;
}
 
.meta-item .value{
    font-size:13.5px;
    color:var(--ink);
}
 
/* NOTES + FOOTER */
.notes-row{
    display:flex;
    gap:10px;
    background:var(--paper);
    border-radius:10px;
    padding:11px 13px;
    font-size:13px;
    color:var(--ink-soft);
    margin-bottom:12px;
}
 
.notes-row svg{
    width:15px;
    height:15px;
    stroke:var(--muted);
    flex-shrink:0;
    margin-top:1px;
}
 
.card-footer{
    font-size:12px;
    color:var(--muted);
}
 
/* EMPTY STATES */
.empty{
    text-align:center;
    padding:60px 20px;
    color:var(--muted);
    background:var(--surface);
    border:1px solid var(--line);
    border-radius:14px;
}
 
.empty svg{
    width:34px;
    height:34px;
    stroke:var(--line);
    margin-bottom:12px;
}
 
.empty p{
    font-size:14px;
}
 
#no-match{
    display:none;
}
 
@media (max-width:600px){
    .toolbar{
        flex-direction:column;
        align-items:stretch;
    }
    .pills{
        overflow-x:auto;
        padding-bottom:2px;
    }
}
</style>
</head>
 
<body>
 
<div class="navbar">
    <div class="logo">
        <img src="logo.jpg" alt="Grand Superior logo">
        <span>Grand Superior</span>
    </div>
</div>
 
<div class="container">
 
    <a class="back" href="owner_dashboard.php">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M11 18l-6-6 6-6"/></svg>
        Back to dashboard
    </a>
 
    <div class="page-head">
        <div>
            <h1>All client bookings</h1>
            <div class="count"><strong id="visible-count"><?php echo $totalCount; ?></strong> of <?php echo $totalCount; ?> bookings shown</div>
        </div>
    </div>
 
    <?php if ($result && $result->num_rows > 0) { ?>
 
        <div class="toolbar">
            <div class="search-wrap">
                <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                <input type="text" id="search" placeholder="Search by client, email, address...">
            </div>
            <div class="pills" id="pills">
                <button class="pill active" data-status="all">All</button>
                <?php foreach ($statusOrder as $s) { ?>
                    <button class="pill" data-status="<?php echo htmlspecialchars($s); ?>"><?php echo htmlspecialchars($statusLabels[$s]); ?></button>
                <?php } ?>
            </div>
        </div>
 
        <div id="cards">
        <?php while ($row = $result->fetch_assoc()) {
 
            /* Prefer the actual payment record's status (from the
               payments table) over the booking's own payment_status,
               since that's where Paid/Unpaid/Partial really gets set. */
            $effectiveStatus = $row['payment_record_status'] ?? '';
            if ($effectiveStatus === '' || $effectiveStatus === null) {
                $effectiveStatus = $row['payment_status'] ?? '';
            }
 
            [$statusClass, $statusLabel] = paymentStatusMeta($effectiveStatus);
 
            $clientName = $row['client_name'] ?? '';
            $email      = $row['email'] ?? '';
            $service    = $row['service_type'] ?? '';
            $address    = $row['address'] ?? '';
 
            $searchBlob = strtolower($clientName . ' ' . $email . ' ' . $service . ' ' . $address);
        ?>
 
            <div class="card" data-status="<?php echo htmlspecialchars($statusClass); ?>" data-search="<?php echo htmlspecialchars($searchBlob); ?>">
 
                <div class="card-top">
                    <div class="client-id">
                        <div class="avatar"><?php echo htmlspecialchars(initials($clientName)); ?></div>
                        <div>
                            <div class="client-name"><?php echo htmlspecialchars($clientName ?: 'Unknown client'); ?></div>
                            <div class="client-email"><?php echo htmlspecialchars($email); ?></div>
                        </div>
                    </div>
 
                    <span class="status-pill <?php echo htmlspecialchars($statusClass); ?>">
                        <span class="dot"></span>
                        <?php echo htmlspecialchars($statusLabel); ?>
                    </span>
                </div>
 
                <div class="grid">
 
                    <div class="meta-item">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.59 2.59A2 2 0 0 0 11.17 2H4a2 2 0 0 0-2 2v7.17a2 2 0 0 0 .59 1.41l8.7 8.7a2.43 2.43 0 0 0 3.42 0l6.58-6.58a2.43 2.43 0 0 0 0-3.42Z"/><circle cx="7.5" cy="7.5" r="1.5" fill="currentColor" stroke="none"/></svg>
                        <div>
                            <div class="label">Service</div>
                            <div class="value"><?php echo htmlspecialchars($service); ?></div>
                        </div>
                    </div>
 
                    <div class="meta-item">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                        <div>
                            <div class="label">Pickup</div>
                            <div class="value"><?php echo htmlspecialchars($row['pickup_date'] ?? ''); ?></div>
                        </div>
                    </div>
 
                    <div class="meta-item">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18M8 14h.01M12 14h.01M16 14h.01"/></svg>
                        <div>
                            <div class="label">Delivery</div>
                            <div class="value"><?php echo htmlspecialchars($row['delivery_date'] ?? ''); ?></div>
                        </div>
                    </div>
 
                    <div class="meta-item">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                        <div>
                            <div class="label">Address</div>
                            <div class="value"><?php echo htmlspecialchars($address); ?></div>
                        </div>
                    </div>
 
                    <?php if (isset($row['payment_amount']) && $row['payment_amount'] !== null) { ?>
                    <div class="meta-item">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                        <div>
                            <div class="label">Amount Paid</div>
                            <div class="value">KES <?php echo htmlspecialchars(number_format((float)$row['payment_amount'], 2)); ?></div>
                        </div>
                    </div>
                    <?php } ?>
 
                </div>
 
                <?php if (!empty($row['notes'])) { ?>
                    <div class="notes-row">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3v4a1 1 0 0 0 1 1h4"/><path d="M17 21H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h7l5 5v11a2 2 0 0 1-2 2Z"/><path d="M9 13h6M9 17h6"/></svg>
                        <span><?php echo htmlspecialchars($row['notes']); ?></span>
                    </div>
                <?php } ?>
 
                <div class="card-footer">
                    Booked on <?php echo htmlspecialchars($row['created_at'] ?? ''); ?> · Booking #<?php echo htmlspecialchars($row['id']); ?>
                </div>
 
            </div>
 
        <?php } ?>
        </div>
 
        <div class="empty" id="no-match">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            <p>No bookings match your search.</p>
        </div>
 
    <?php } else { ?>
 
        <div class="empty">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.9 19.8 14.7 5a2 2 0 0 0-3.4 0L5.1 19.8a1 1 0 0 0 .9 1.4h13a1 1 0 0 0 .9-1.4Z"/><path d="M12 9v4M12 17h.01"/></svg>
            <p>No bookings found in the system.</p>
        </div>
 
    <?php } ?>
 
</div>
 
<script>
(function(){
    var search = document.getElementById('search');
    var pills = document.querySelectorAll('.pill');
    var cards = document.querySelectorAll('.card');
    var visibleCount = document.getElementById('visible-count');
    var noMatch = document.getElementById('no-match');
    var activeStatus = 'all';
 
    function applyFilters(){
        var term = (search ? search.value : '').trim().toLowerCase();
        var shown = 0;
 
        cards.forEach(function(card){
            var matchesStatus = activeStatus === 'all' || card.dataset.status === activeStatus;
            var matchesSearch = term === '' || card.dataset.search.indexOf(term) !== -1;
            var visible = matchesStatus && matchesSearch;
            card.style.display = visible ? '' : 'none';
            if (visible) shown++;
        });
 
        if (visibleCount) visibleCount.textContent = shown;
        if (noMatch) noMatch.style.display = (shown === 0 && cards.length > 0) ? 'block' : 'none';
    }
 
    if (search) search.addEventListener('input', applyFilters);
 
    pills.forEach(function(pill){
        pill.addEventListener('click', function(){
            pills.forEach(function(p){ p.classList.remove('active'); });
            pill.classList.add('active');
            activeStatus = pill.dataset.status;
            applyFilters();
        });
    });
})();
</script>
 
</body>
</html>