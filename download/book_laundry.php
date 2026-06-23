<?php
session_start();
include 'connection.php';

if (!isset($_SESSION['client_id'])) {
    header("Location: client_login.php");
    exit();
}

$client_id = $_SESSION['client_id'];
$msg       = "";
$msg_type  = "";

/* Fetch client name for navbar */
$stmt = $conn->prepare("SELECT name FROM clients WHERE id = ?");
$stmt->bind_param("i", $client_id);
$stmt->execute();
$client = $stmt->get_result()->fetch_assoc();
$stmt->close();

/* Keep form values on error */
$form = [
    'service_type'  => '',
    'pickup_date'   => '',
    'delivery_date' => '',
    'address'       => '',
    'notes'         => '',
];

/* Handle form submission */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form['service_type']  = trim($_POST['service_type']  ?? '');
    $form['pickup_date']   = trim($_POST['pickup_date']   ?? '');
    $form['delivery_date'] = trim($_POST['delivery_date'] ?? '');
    $form['address']       = trim($_POST['address']       ?? '');
    $form['notes']         = trim($_POST['notes']         ?? '');

    $stmt = $conn->prepare(
        "INSERT INTO bookings (client_id, service_type, pickup_date, delivery_date, address, notes)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param("isssss",
        $client_id,
        $form['service_type'],
        $form['pickup_date'],
        $form['delivery_date'],
        $form['address'],
        $form['notes']
    );

    if ($stmt->execute()) {
        $msg      = "Your booking has been placed successfully! We'll be in touch shortly.";
        $msg_type = "success";
        $form     = array_fill_keys(array_keys($form), '');
    } else {
        $msg      = "Booking failed: " . $stmt->error . ". Please try again.";
        $msg_type = "error";
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

$today = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Book a Service · Grand Superior</title>

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
}

*, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }

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
    box-shadow: 0 2px 12px rgba(0,0,0,0.18);
}
.navbar-logo { display:flex; align-items:center; gap:11px; text-decoration:none; }
.navbar-logo img {
    width:36px; height:36px; border-radius:8px;
    object-fit:cover; border:2px solid var(--lime);
    padding:2px; background:#fff;
}
.navbar-logo span {
    font-family:'Sora',sans-serif; font-weight:600;
    font-size:16px; color:#fff;
}
.navbar-right { display:flex; align-items:center; gap:9px; }
.nav-avatar {
    width:34px; height:34px; border-radius:50%;
    background:var(--lime); color:var(--ink);
    font-family:'Sora',sans-serif; font-weight:700;
    font-size:12px; display:flex; align-items:center;
    justify-content:center; flex-shrink:0;
}
.nav-name { font-size:13px; font-weight:500; color:rgba(255,255,255,0.85); }

/* ===== PAGE ===== */
.page { max-width:640px; margin:0 auto; padding:36px 20px 72px; }

/* ===== BACK LINK ===== */
.back-link {
    display:inline-flex; align-items:center; gap:7px;
    text-decoration:none; color:var(--muted);
    font-size:13.5px; font-weight:500;
    margin-bottom:26px; transition:color 0.15s;
}
.back-link:hover { color:var(--ink); }
.back-link svg {
    width:14px; height:14px; stroke:currentColor;
    fill:none; stroke-width:2.5;
    stroke-linecap:round; stroke-linejoin:round;
}

/* ===== PAGE HEADER ===== */
.page-header { display:flex; align-items:center; gap:16px; margin-bottom:28px; }
.page-header-icon {
    width:52px; height:52px; border-radius:14px;
    background:var(--ink); display:flex;
    align-items:center; justify-content:center; flex-shrink:0;
}
.page-header-icon svg {
    width:24px; height:24px; fill:none;
    stroke:var(--lime); stroke-width:2;
    stroke-linecap:round; stroke-linejoin:round;
}
.page-header h1 {
    font-family:'Sora',sans-serif; font-size:22px;
    font-weight:600; margin-bottom:3px;
}
.page-header p { font-size:13px; color:var(--muted); }

/* ===== ALERT ===== */
.alert {
    display:flex; align-items:flex-start; gap:11px;
    padding:14px 16px; border-radius:12px;
    font-size:13.5px; font-weight:500;
    margin-bottom:22px; border:1px solid transparent;
    animation:slideIn 0.25s ease;
}
@keyframes slideIn {
    from { opacity:0; transform:translateY(-6px); }
    to   { opacity:1; transform:translateY(0); }
}
.alert svg {
    width:17px; height:17px; fill:none; stroke:currentColor;
    stroke-width:2; stroke-linecap:round; stroke-linejoin:round;
    flex-shrink:0; margin-top:1px;
}
.alert-success { background:#edfaf4; border-color:#a7f3d0; color:#065f46; }
.alert-error   { background:#fff1f1; border-color:#fca5a5; color:#991b1b; }

/* ===== CARD ===== */
.card {
    background:var(--surface); border:1px solid var(--line);
    border-radius:16px; overflow:hidden;
    box-shadow:0 2px 12px rgba(13,27,42,0.05);
}
.card-header {
    padding:20px 24px 16px; border-bottom:1px solid var(--line);
    display:flex; align-items:center; gap:12px; background:#fafbfd;
}
.card-header-icon {
    width:38px; height:38px; border-radius:10px;
    background:var(--lime-light); display:flex;
    align-items:center; justify-content:center; flex-shrink:0;
}
.card-header-icon svg {
    width:18px; height:18px; fill:none;
    stroke:var(--lime-dark); stroke-width:2;
    stroke-linecap:round; stroke-linejoin:round;
}
.card-header-text h2 {
    font-family:'Sora',sans-serif; font-size:15px;
    font-weight:600; color:var(--ink); margin-bottom:2px;
}
.card-header-text p { font-size:12px; color:var(--muted); }
.card-body { padding:26px 24px; }

/* ===== SERVICE PICKER ===== */
.service-grid {
    display:grid;
    grid-template-columns: repeat(2, 1fr);
    gap:10px;
    margin-bottom:24px;
}

.service-option { display:none; }

.service-label {
    display:flex;
    flex-direction:column;
    align-items:center;
    gap:8px;
    padding:16px 12px;
    border:2px solid var(--line);
    border-radius:12px;
    cursor:pointer;
    transition:border-color 0.15s, background 0.15s, box-shadow 0.15s;
    text-align:center;
    background:var(--paper);
    user-select:none;
}
.service-label:hover {
    border-color:var(--lime);
    background:#fff;
}
.service-option:checked + .service-label {
    border-color:var(--lime);
    background:var(--lime-light);
    box-shadow:0 0 0 4px rgba(166,206,57,0.15);
}

.service-label-icon {
    width:40px; height:40px;
    border-radius:10px;
    background:#fff;
    border:1px solid var(--line);
    display:flex;
    align-items:center;
    justify-content:center;
}
.service-label-icon svg {
    width:20px; height:20px;
    fill:none;
    stroke:var(--ink);
    stroke-width:1.8;
    stroke-linecap:round;
    stroke-linejoin:round;
}
.service-option:checked + .service-label .service-label-icon {
    background:var(--lime);
    border-color:var(--lime);
}
.service-option:checked + .service-label .service-label-icon svg {
    stroke:#fff;
}

.service-label span {
    font-size:12.5px;
    font-weight:600;
    color:var(--ink);
}

/* ===== SECTION LABEL ===== */
.section-label {
    font-size:11.5px;
    font-weight:700;
    color:var(--muted);
    text-transform:uppercase;
    letter-spacing:0.5px;
    margin-bottom:14px;
    display:flex;
    align-items:center;
    gap:8px;
}
.section-label::after {
    content:'';
    flex:1;
    height:1px;
    background:var(--line);
}

/* ===== FORM GRID ===== */
.form-grid {
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:14px;
    margin-bottom:14px;
}
.form-grid .field-full { grid-column:1 / -1; }

/* ===== FIELD ===== */
.field { display:flex; flex-direction:column; gap:6px; }

.field label {
    font-size:12px; font-weight:600; color:var(--muted);
    text-transform:uppercase; letter-spacing:0.5px;
}

.field input,
.field select,
.field textarea {
    padding:11px 14px;
    border:1px solid var(--line);
    border-radius:10px;
    font-family:'Inter',sans-serif;
    font-size:13.5px;
    color:var(--ink);
    background:var(--paper);
    outline:none;
    width:100%;
    transition:border-color 0.15s, box-shadow 0.15s, background 0.15s;
    appearance:none;
    -webkit-appearance:none;
}

.select-wrap {
    position:relative;
}
.select-wrap select { padding-right:36px; }
.select-wrap::after {
    content:'';
    position:absolute;
    right:13px;
    top:50%;
    transform:translateY(-50%);
    width:0; height:0;
    border-left:5px solid transparent;
    border-right:5px solid transparent;
    border-top:5px solid var(--muted);
    pointer-events:none;
}

.field textarea {
    resize:vertical;
    min-height:100px;
    line-height:1.65;
}

.field input:focus,
.field select:focus,
.field textarea:focus {
    border-color:var(--lime-dark);
    box-shadow:0 0 0 4px rgba(166,206,57,0.18);
    background:#fff;
}

.field input::placeholder,
.field textarea::placeholder { color:#b0bac8; }

/* ===== TIP BOX ===== */
.tip-box {
    display:flex; align-items:flex-start; gap:10px;
    background:var(--lime-light); border:1px solid #d4eaaa;
    border-radius:10px; padding:12px 14px;
    font-size:12.5px; color:#4a6b1a;
    margin-bottom:22px; line-height:1.6;
}
.tip-box svg {
    width:15px; height:15px; fill:none; stroke:currentColor;
    stroke-width:2; stroke-linecap:round; stroke-linejoin:round;
    flex-shrink:0; margin-top:1px;
}

/* ===== DIVIDER ===== */
.form-divider { border:none; border-top:1px solid var(--line); margin:22px 0; }

/* ===== SUBMIT ===== */
.btn-submit {
    display:flex; align-items:center; justify-content:center;
    gap:9px; width:100%; padding:14px 28px;
    background:var(--lime); color:var(--ink); border:none;
    border-radius:10px; font-family:'Inter',sans-serif;
    font-size:14px; font-weight:700; cursor:pointer;
    transition:background 0.15s, transform 0.15s, box-shadow 0.15s;
}
.btn-submit:hover {
    background:var(--lime-dark); transform:translateY(-1px);
    box-shadow:0 6px 20px rgba(166,206,57,0.4);
}
.btn-submit:disabled {
    opacity:0.65; cursor:not-allowed;
    transform:none; box-shadow:none;
}
.btn-submit svg {
    width:16px; height:16px; fill:none; stroke:currentColor;
    stroke-width:2.5; stroke-linecap:round; stroke-linejoin:round;
}

/* ===== RESPONSIVE ===== */
@media (max-width:600px) {
    .navbar      { padding:0 16px; }
    .nav-name    { display:none; }
    .page        { padding:24px 14px 52px; }
    .card-body   { padding:20px 16px; }
    .card-header { padding:16px; }
    .form-grid   { grid-template-columns:1fr; }
    .service-grid { grid-template-columns:repeat(2,1fr); }
}

@keyframes spin { to { transform:rotate(360deg); } }
</style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar">
    <a href="client_dashboard.php" class="navbar-logo">
        <img src="logo.jpg" alt="Grand Superior">
        <span>Grand Superior</span>
    </a>
    <div class="navbar-right">
        <div class="nav-avatar"><?php echo htmlspecialchars(initials($client['name'])); ?></div>
        <span class="nav-name"><?php echo htmlspecialchars($client['name']); ?></span>
    </div>
</nav>

<!-- PAGE -->
<div class="page">

    <a href="client_dashboard.php" class="back-link">
        <svg viewBox="0 0 24 24"><path d="M19 12H5M11 18l-6-6 6-6"/></svg>
        Back to dashboard
    </a>

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-icon">
            <svg viewBox="0 0 24 24"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
        </div>
        <div>
            <h1>Book a Service</h1>
            <p>Schedule a pickup &amp; delivery for your laundry</p>
        </div>
    </div>

    <!-- Alert -->
    <?php if (!empty($msg)): ?>
    <div class="alert alert-<?php echo $msg_type; ?>">
        <?php if ($msg_type === 'success'): ?>
            <svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        <?php else: ?>
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <?php endif; ?>
        <?php echo htmlspecialchars($msg); ?>
    </div>
    <?php endif; ?>

    <!-- Card -->
    <div class="card">
        <div class="card-header">
            <div class="card-header-icon">
                <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            </div>
            <div class="card-header-text">
                <h2>New Booking</h2>
                <p>Fill in the details below and we'll handle the rest</p>
            </div>
        </div>

        <div class="card-body">

            <div class="tip-box">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="12" x2="12" y2="16"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                Delivery date must be at least one day after the pickup date. We'll confirm your booking via SMS or call.
            </div>

            <form method="POST" action="" id="bookingForm">

                <!-- Service Type -->
                <div class="section-label">Select Service</div>

                <div class="service-grid">

                    <input class="service-option" type="radio" name="service_type" id="svc_dry" value="Dry Cleaning"
                        <?php echo $form['service_type'] === 'Dry Cleaning' ? 'checked' : ''; ?> required>
                    <label class="service-label" for="svc_dry">
                        <div class="service-label-icon">
                            <svg viewBox="0 0 24 24"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                        </div>
                        <span>Dry Cleaning</span>
                    </label>

                    <input class="service-option" type="radio" name="service_type" id="svc_laundry" value="Laundry"
                        <?php echo $form['service_type'] === 'Laundry' ? 'checked' : ''; ?>>
                    <label class="service-label" for="svc_laundry">
                        <div class="service-label-icon">
                            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M8 12a4 4 0 0 0 8 0"/></svg>
                        </div>
                        <span>Laundry</span>
                    </label>

                    <input class="service-option" type="radio" name="service_type" id="svc_iron" value="Ironing"
                        <?php echo $form['service_type'] === 'Ironing' ? 'checked' : ''; ?>>
                    <label class="service-label" for="svc_iron">
                        <div class="service-label-icon">
                            <svg viewBox="0 0 24 24"><path d="M3 17h18v2H3z"/><path d="M5 17V9a7 7 0 0 1 14 0v1H5"/></svg>
                        </div>
                        <span>Ironing</span>
                    </label>

                    <input class="service-option" type="radio" name="service_type" id="svc_stain" value="Stain Removal"
                        <?php echo $form['service_type'] === 'Stain Removal' ? 'checked' : ''; ?>>
                    <label class="service-label" for="svc_stain">
                        <div class="service-label-icon">
                            <svg viewBox="0 0 24 24"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/></svg>
                        </div>
                        <span>Stain Removal</span>
                    </label>

                </div>

                <hr class="form-divider">

                <!-- Dates & Address -->
                <div class="section-label">Schedule &amp; Location</div>

                <div class="form-grid">

                    <div class="field">
                        <label for="pickup_date">Pickup Date</label>
                        <input
                            type="date"
                            id="pickup_date"
                            name="pickup_date"
                            min="<?php echo $today; ?>"
                            value="<?php echo htmlspecialchars($form['pickup_date']); ?>"
                            required>
                    </div>

                    <div class="field">
                        <label for="delivery_date">Delivery Date</label>
                        <input
                            type="date"
                            id="delivery_date"
                            name="delivery_date"
                            min="<?php echo $today; ?>"
                            value="<?php echo htmlspecialchars($form['delivery_date']); ?>"
                            required>
                    </div>

                    <div class="field field-full">
                        <label for="address">Pickup Address</label>
                        <input
                            type="text"
                            id="address"
                            name="address"
                            placeholder="e.g. 14 Ngong Road, Nairobi"
                            value="<?php echo htmlspecialchars($form['address']); ?>"
                            required>
                    </div>

                </div>

                <hr class="form-divider">

                <!-- Notes -->
                <div class="section-label">Additional Notes</div>

                <div class="field" style="margin-bottom:22px;">
                    <label for="notes">Special Instructions <span style="font-weight:400;text-transform:none;letter-spacing:0;">(optional)</span></label>
                    <textarea
                        id="notes"
                        name="notes"
                        placeholder="e.g. Delicate fabrics, handle with care, fragrance-free detergent..."><?php echo htmlspecialchars($form['notes']); ?></textarea>
                </div>

                <button type="submit" class="btn-submit" id="bookBtn">
                    <svg viewBox="0 0 24 24"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                    Confirm Booking
                </button>

            </form>
        </div>
    </div>

</div>

<script>
/* Auto-set delivery date min to be after pickup */
document.getElementById('pickup_date').addEventListener('change', function () {
    var pickup = this.value;
    if (!pickup) return;
    var next = new Date(pickup);
    next.setDate(next.getDate() + 1);
    var min = next.toISOString().split('T')[0];
    var delivery = document.getElementById('delivery_date');
    delivery.min = min;
    if (delivery.value && delivery.value <= pickup) {
        delivery.value = min;
    }
});

/* Spinner on submit */
document.getElementById('bookingForm').addEventListener('submit', function () {
    var btn = document.getElementById('bookBtn');
    btn.disabled = true;
    btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16" style="animation:spin 1s linear infinite"><path d="M21 12a9 9 0 1 1-6.22-8.56"/></svg> Placing Booking...';
});
</script>

</body>
</html>