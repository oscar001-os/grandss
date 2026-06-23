<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'connection.php';
session_start();

if (isset($_SESSION['client_id'])) {
    header("Location: client_dashboard.php");
    exit();
}

$error   = "";
$success = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name     = trim($_POST['name']     ?? '');
    $email    = trim($_POST['email']    ?? '');
    $phone    = trim($_POST['phone']    ?? '');
    $address  = trim($_POST['address']  ?? '');
    $password = $_POST['password']      ?? '';
    $confirm  = $_POST['confirm']       ?? '';

    // ── Presence check ──
    if (empty($name) || empty($email) || empty($phone) || empty($password) || empty($confirm)) {
        $error = "All required fields must be filled in.";

    // ── Password rules ──
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters.";
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $error = "Password must contain at least one uppercase letter.";
    } elseif (!preg_match('/[a-z]/', $password)) {
        $error = "Password must contain at least one lowercase letter.";
    } elseif (!preg_match('/[0-9]/', $password)) {
        $error = "Password must contain at least one number.";

    // ── Confirm match ──
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";

    } else {
        // ── Duplicate email check ──
        $check = $conn->prepare("SELECT id FROM clients WHERE email = ? LIMIT 1");
        if (!$check) die("Prepare failed: " . $conn->error);
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $error = "This email address is already registered. Please log in.";
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);

            $insert = $conn->prepare(
                "INSERT INTO clients (name, email, phone, address, password) VALUES (?, ?, ?, ?, ?)"
            );
            if (!$insert) die("Insert prepare failed: " . $conn->error);

            $insert->bind_param("sssss", $name, $email, $phone, $address, $hashed);

            if ($insert->execute()) {
                $_SESSION['client_id']    = $insert->insert_id;
                $_SESSION['client_name']  = $name;
                $_SESSION['client_email'] = $email;
                $_SESSION['client_phone'] = $phone;
                header("Location: client_dashboard.php");
                exit();
            } else {
                $error = "Registration failed. Please try again. (" . $insert->error . ")";
            }

            $insert->close();
        }
        $check->close();
    }
}

// Pre-fill values (exclude passwords for security)
$v_name    = htmlspecialchars($_POST['name']    ?? '');
$v_email   = htmlspecialchars($_POST['email']   ?? '');
$v_phone   = htmlspecialchars($_POST['phone']   ?? '');
$v_address = htmlspecialchars($_POST['address'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Client Registration | Grand Superior Drycleaners</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<style>
*, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

body {
    font-family: 'Inter', sans-serif;
    background: #f5f7fa;
    color: #1e293b;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
}

/* ── Top bar ── */
.topbar {
    background: #0d1b2a; padding: 0 24px; height: 56px;
    display: flex; align-items: center; gap: 10px; flex-shrink: 0;
}
.topbar-icon {
    width: 28px; height: 28px; border-radius: 8px; background: #a6ce39;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.topbar-icon svg { width: 14px; height: 14px; }
.topbar-name { font-size: 14px; font-weight: 700; color: #fff; }
.topbar-sub  { font-size: 9px; font-weight: 600; color: #a6ce39; letter-spacing: .12em; text-transform: uppercase; margin-left: 8px; }

/* ── Layout ── */
main { flex: 1; display: flex; align-items: center; justify-content: center; padding: 32px 16px; }
.wrap { width: 100%; max-width: 500px; }

/* ── Card ── */
.card { background: #fff; border: 1px solid #e2e8f0; border-radius: 18px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,.08); }
.card-accent { height: 5px; background: linear-gradient(90deg, #0d1b2a 0%, #a6ce39 100%); }
.card-body   { padding: 32px 36px 28px; }

/* ── Heading ── */
.card-icon-wrap {
    width: 48px; height: 48px; border-radius: 14px; background: #0d1b2a;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 14px; box-shadow: 0 4px 14px rgba(13,27,42,.3);
}
.card-icon-wrap svg { width: 22px; height: 22px; }
.card-title { text-align: center; font-size: 18px; font-weight: 700; color: #0d1b2a; }
.card-sub   { text-align: center; font-size: 12px; color: #64748b; margin-top: 4px; margin-bottom: 24px; }

/* ── Alert ── */
.alert-error {
    display: flex; align-items: center; gap: 9px;
    background: #fef2f2; border: 1px solid #fecaca; color: #991b1b;
    border-radius: 10px; padding: 11px 14px;
    font-size: 12px; font-weight: 500; margin-bottom: 18px;
}
.alert-error svg { width: 15px; height: 15px; flex-shrink: 0; }

/* ── Grid row ── */
.row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }

/* ── Fields ── */
.field         { margin-bottom: 14px; }
.field label   { display: block; font-size: 12px; font-weight: 500; color: #374151; margin-bottom: 5px; }
.field label .req { color: #f87171; }

.input-wrap { position: relative; display: flex; align-items: center; }
.input-icon {
    position: absolute; left: 12px; top: 50%; transform: translateY(-50%);
    color: #94a3b8; display: flex; pointer-events: none;
}
.input-icon svg { width: 15px; height: 15px; }
.toggle-pw {
    position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
    background: none; border: none; cursor: pointer; color: #94a3b8; display: flex; padding: 0;
}
.toggle-pw:hover { color: #64748b; }
.toggle-pw svg { width: 15px; height: 15px; }

input[type="text"],
input[type="email"],
input[type="tel"],
input[type="password"] {
    width: 100%; padding: 10px 14px 10px 38px;
    border: 1px solid #e2e8f0; border-radius: 10px;
    font-size: 13px; font-family: inherit; color: #1e293b;
    background: #fff; outline: none;
    transition: border-color .2s, box-shadow .2s;
}
input.has-toggle { padding-right: 38px; }
input:focus { border-color: #a6ce39; box-shadow: 0 0 0 3px rgba(166,206,57,.15); }
input.match    { border-color: #86efac; }
input.mismatch { border-color: #fca5a5; }
input::placeholder { color: #94a3b8; }

/* ── Divider ── */
.divider { display: flex; align-items: center; gap: 10px; margin: 8px 0 14px; }
.divider-line { flex: 1; height: 1px; background: #f1f5f9; }
.divider-label { font-size: 11px; color: #94a3b8; font-weight: 500; }

/* ── Strength bar ── */
.strength-bars { display: flex; gap: 4px; margin-bottom: 8px; }
.strength-bars span { flex: 1; height: 4px; border-radius: 99px; background: #e2e8f0; transition: background .3s; }

.strength-rules { display: grid; grid-template-columns: 1fr 1fr; gap: 2px 16px; }
.rule {
    display: flex; align-items: center; gap: 5px;
    font-size: 11px; color: #94a3b8; transition: color .2s;
}
.rule.ok  { color: #16a34a; }
.rule svg { width: 11px; height: 11px; flex-shrink: 0; }

.match-msg {
    font-size: 11px; margin-top: 4px;
    display: flex; align-items: center; gap: 4px;
}
.match-msg svg { width: 11px; height: 11px; }
.match-ok  { color: #16a34a; }
.match-err { color: #ef4444; }

/* ── Submit ── */
.btn-submit {
    width: 100%; margin-top: 18px; padding: 12px;
    background: #a6ce39; border: none; border-radius: 11px;
    font-size: 13px; font-weight: 700; color: #0d1b2a;
    cursor: pointer; font-family: inherit;
    display: flex; align-items: center; justify-content: center; gap: 8px;
    transition: background .2s;
}
.btn-submit:hover { background: #94b934; }
.btn-submit svg   { width: 15px; height: 15px; }

/* ── Card footer ── */
.card-footer {
    background: #f8fafc; border-top: 1px solid #f1f5f9;
    padding: 13px 36px; text-align: center;
    font-size: 12px; color: #64748b;
}
.card-footer a { color: #0d1b2a; font-weight: 600; text-decoration: none; }
.card-footer a:hover { text-decoration: underline; }

.copyright { text-align: center; font-size: 11px; color: #94a3b8; margin-top: 18px; }

@media(max-width: 560px) {
    .card-body { padding: 24px 20px 20px; }
    .card-footer { padding: 12px 20px; }
    .row-2 { grid-template-columns: 1fr; }
}
</style>
</head>
<body>

<div class="topbar">
    <div class="topbar-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="#0d1b2a" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0h6"/>
        </svg>
    </div>
    <span class="topbar-name">Grand Superior</span>
    <span class="topbar-sub">Drycleaners</span>
</div>

<main>
<div class="wrap">

<div class="card">
    <div class="card-accent"></div>
    <div class="card-body">

        <div class="card-icon-wrap">
            <svg viewBox="0 0 24 24" fill="none" stroke="#a6ce39" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/>
                <circle cx="9" cy="7" r="4"/>
                <line x1="19" y1="8" x2="19" y2="14"/>
                <line x1="22" y1="11" x2="16" y2="11"/>
            </svg>
        </div>
        <h1 class="card-title">Create Your Account</h1>
        <p class="card-sub">Fill in your details to register as a client.</p>

        <?php if (!empty($error)): ?>
        <div class="alert-error">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/>
                <line x1="12" y1="8" x2="12" y2="12"/>
                <line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="" id="regForm" novalidate>

            <!-- Full Name -->
            <div class="field">
                <label for="name">Full Name <span class="req">*</span></label>
                <div class="input-wrap">
                    <span class="input-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/>
                            <circle cx="12" cy="7" r="4"/>
                        </svg>
                    </span>
                    <input type="text" id="name" name="name" placeholder="John Mensah" value="<?= $v_name ?>" required>
                </div>
            </div>

            <!-- Email + Phone -->
            <div class="row-2">
                <div class="field">
                    <label for="email">Email Address <span class="req">*</span></label>
                    <div class="input-wrap">
                        <span class="input-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                                <polyline points="22,6 12,13 2,6"/>
                            </svg>
                        </span>
                        <input type="email" id="email" name="email" placeholder="john@email.com" value="<?= $v_email ?>" required>
                    </div>
                </div>
                <div class="field">
                    <label for="phone">Phone Number <span class="req">*</span></label>
                    <div class="input-wrap">
                        <span class="input-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                            </svg>
                        </span>
                        <input type="tel" id="phone" name="phone" placeholder="0244 123 456" value="<?= $v_phone ?>" required>
                    </div>
                </div>
            </div>

            <!-- Address -->
            <div class="field">
                <label for="address">Home Address</label>
                <div class="input-wrap">
                    <span class="input-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0h6"/>
                        </svg>
                    </span>
                    <input type="text" id="address" name="address" placeholder="123 Osu Lane, Accra" value="<?= $v_address ?>">
                </div>
            </div>

            <div class="divider">
                <div class="divider-line"></div>
                <span class="divider-label">Security</span>
                <div class="divider-line"></div>
            </div>

            <!-- Password -->
            <div class="field">
                <label for="password">Password <span class="req">*</span></label>
                <div class="input-wrap">
                    <span class="input-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                            <path d="M7 11V7a5 5 0 0110 0v4"/>
                        </svg>
                    </span>
                    <input type="password" id="password" name="password" placeholder="Create a strong password" class="has-toggle" required>
                    <button type="button" class="toggle-pw" onclick="toggleVis('password',this)">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Strength indicators (hidden until typing) -->
            <div id="strengthBlock" style="display:none; margin-top:-8px; margin-bottom:14px;">
                <div class="strength-bars">
                    <span id="b1"></span><span id="b2"></span><span id="b3"></span><span id="b4"></span>
                </div>
                <div class="strength-rules">
                    <div class="rule" id="r-len">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/></svg>
                        At least 8 characters
                    </div>
                    <div class="rule" id="r-upper">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/></svg>
                        One uppercase letter
                    </div>
                    <div class="rule" id="r-lower">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/></svg>
                        One lowercase letter
                    </div>
                    <div class="rule" id="r-num">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/></svg>
                        One number
                    </div>
                    <div class="rule" id="r-spec">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/></svg>
                        One special character
                    </div>
                </div>
            </div>

            <!-- Confirm Password -->
            <div class="field">
                <label for="confirm">Confirm Password <span class="req">*</span></label>
                <div class="input-wrap">
                    <span class="input-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="9 11 12 14 22 4"/>
                            <path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/>
                        </svg>
                    </span>
                    <input type="password" id="confirm" name="confirm" placeholder="Repeat your password" class="has-toggle" required>
                    <button type="button" class="toggle-pw" onclick="toggleVis('confirm',this)">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                    </button>
                </div>
                <div id="matchMsg" class="match-msg" style="display:none;"></div>
            </div>

            <button type="submit" class="btn-submit">
                Create Account
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M5 12h14M12 5l7 7-7 7"/>
                </svg>
            </button>

        </form>
    </div>

    <div class="card-footer">
        Already have an account? <a href="client_login.php">Sign in here →</a>
    </div>
</div>

<p class="copyright">© <?= date('Y') ?> Grand Superior Drycleaners. All rights reserved.</p>

</div>
</main>

<script>
const CHECK_SVG  = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>`;
const CIRCLE_SVG = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/></svg>`;

const COLORS = { weak:"#ef4444", fair:"#f97316", good:"#eab308", strong:"#a6ce39" };

function setRule(id, ok) {
    const el = document.getElementById(id);
    if (!el) return;
    el.className = 'rule' + (ok ? ' ok' : '');
    el.querySelector('svg').outerHTML; // trigger re-render
    el.innerHTML = (ok ? CHECK_SVG : CIRCLE_SVG) + el.innerHTML.replace(/<svg[\s\S]*?<\/svg>/, '');
    el.className = 'rule' + (ok ? ' ok' : '');
}

document.getElementById('password').addEventListener('input', function () {
    const pw  = this.value;
    const block = document.getElementById('strengthBlock');
    if (pw) { block.style.display = 'block'; } else { block.style.display = 'none'; return; }

    const r = {
        len:   pw.length >= 8,
        upper: /[A-Z]/.test(pw),
        lower: /[a-z]/.test(pw),
        num:   /[0-9]/.test(pw),
        spec:  /[^A-Za-z0-9]/.test(pw)
    };

    // Score
    let score = [r.len, r.upper, r.lower, r.num, r.spec].filter(Boolean).length;
    let bars = score <= 1 ? 1 : score === 2 ? 2 : score === 3 ? 3 : 4;
    let color = score <= 1 ? COLORS.weak : score === 2 ? COLORS.fair : score === 3 ? COLORS.good : COLORS.strong;

    for (let i = 1; i <= 4; i++) {
        document.getElementById('b'+i).style.background = i <= bars ? color : '#e2e8f0';
    }

    // Rules
    ['len','upper','lower','num','spec'].forEach(k => {
        const el = document.getElementById('r-'+k);
        if (!el) return;
        const ok = r[k];
        el.className = 'rule' + (ok ? ' ok' : '');
        const txt = el.textContent.trim();
        el.innerHTML = (ok ? CHECK_SVG : CIRCLE_SVG) + ' ' + txt;
    });

    checkMatch();
});

document.getElementById('confirm').addEventListener('input', checkMatch);

function checkMatch() {
    const pw = document.getElementById('password').value;
    const cf = document.getElementById('confirm').value;
    const msg = document.getElementById('matchMsg');
    const inp = document.getElementById('confirm');
    if (!cf) { msg.style.display = 'none'; inp.className = 'has-toggle'; return; }
    msg.style.display = 'flex';
    if (pw === cf) {
        msg.className = 'match-msg match-ok';
        msg.innerHTML = CHECK_SVG + ' Passwords match';
        inp.className = 'has-toggle match';
    } else {
        msg.className = 'match-msg match-err';
        msg.innerHTML = CIRCLE_SVG + ' Passwords do not match';
        inp.className = 'has-toggle mismatch';
    }
}

function toggleVis(id, btn) {
    const inp = document.getElementById(id);
    const showing = inp.type === 'text';
    inp.type = showing ? 'password' : 'text';
    btn.innerHTML = showing
        ? `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>`
        : `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>`;
}
</script>

</body>
</html>