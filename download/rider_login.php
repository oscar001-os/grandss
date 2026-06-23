<?php
session_start();
include 'connection.php';

if (isset($_SESSION['rider_id'])) {
    header("Location: rider_dashboard.php");
    exit();
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if (empty($email) || empty($phone)) {
        $error = "Both email address and phone number are required.";
    } else {
        // Normalise phone: strip all non-digit characters for loose matching
        $phoneDigits = preg_replace('/\D/', '', $phone);

        $stmt = $conn->prepare("
            SELECT id, name, email, phone, vehicle, status, photo, address, national_id
            FROM riders
            WHERE email = ?
              AND REGEXP_REPLACE(phone, '[^0-9]', '') = ?
            LIMIT 1
        ");
        $stmt->bind_param("ss", $email, $phoneDigits);
        $stmt->execute();
        $result = $stmt->get_result();
        $rider  = $result->fetch_assoc();
        $stmt->close();

        if (!$rider) {
            $error = "No rider account found with those details. Please check your email and phone number.";
        } elseif (strtolower($rider['status']) === 'inactive') {
            $error = "Your account is inactive. Please contact the administrator.";
        } else {
            $_SESSION['rider_id']      = $rider['id'];
            $_SESSION['rider_name']    = $rider['name'];
            $_SESSION['rider_email']   = $rider['email'];
            $_SESSION['rider_phone']   = $rider['phone'];
            $_SESSION['rider_vehicle'] = $rider['vehicle'];
            $_SESSION['rider_photo']   = $rider['photo'];
            $_SESSION['rider_status']  = $rider['status'];
            $_SESSION['rider_address'] = $rider['address'];

            header("Location: rider_dashboard.php");
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Rider Login | Grand Superior Drycleaners</title>
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
    background: #0d1b2a;
    padding: 0 24px; height: 56px;
    display: flex; align-items: center; gap: 10px; flex-shrink: 0;
}
.topbar-icon {
    width: 28px; height: 28px; border-radius: 8px; background: #a6ce39;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.topbar-icon svg { width: 14px; height: 14px; }
.topbar-name { font-size: 14px; font-weight: 700; color: #fff; }
.topbar-sub  { font-size: 9px; font-weight: 600; color: #a6ce39; letter-spacing: .12em; text-transform: uppercase; margin-left: 8px; }

/* ── Main ── */
main { flex: 1; display: flex; align-items: center; justify-content: center; padding: 40px 16px; }
.login-wrap { width: 100%; max-width: 440px; }

/* ── Card ── */
.card { background: #fff; border: 1px solid #e2e8f0; border-radius: 18px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,.08); }
.card-accent { height: 5px; background: linear-gradient(90deg, #0d1b2a 0%, #a6ce39 100%); }
.card-body   { padding: 36px 36px 28px; }

/* ── Heading ── */
.card-icon-wrap {
    width: 56px; height: 56px; border-radius: 16px; background: #0d1b2a;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 16px; box-shadow: 0 4px 14px rgba(13,27,42,.3);
}
.card-icon-wrap svg { width: 26px; height: 26px; }
.card-title { text-align: center; font-size: 20px; font-weight: 700; color: #0d1b2a; }
.card-sub   { text-align: center; font-size: 13px; color: #64748b; margin-top: 4px; margin-bottom: 28px; }

/* ── Alert ── */
.alert-error {
    display: flex; align-items: center; gap: 9px;
    background: #fef2f2; border: 1px solid #fecaca; color: #991b1b;
    border-radius: 10px; padding: 11px 14px;
    font-size: 13px; font-weight: 500; margin-bottom: 20px;
}
.alert-error svg { width: 15px; height: 15px; flex-shrink: 0; }

/* ── Fields ── */
.field       { margin-bottom: 16px; }
.field label { display: block; font-size: 13px; font-weight: 500; color: #374151; margin-bottom: 6px; }

.input-wrap { position: relative; }
.input-icon {
    position: absolute; left: 12px; top: 50%; transform: translateY(-50%);
    color: #94a3b8; display: flex; pointer-events: none;
}
.input-icon svg { width: 16px; height: 16px; }

input[type="email"],
input[type="tel"] {
    width: 100%; padding: 11px 14px 11px 40px;
    border: 1px solid #e2e8f0; border-radius: 11px;
    font-size: 13px; font-family: inherit; color: #1e293b;
    background: #fff; outline: none;
    transition: border-color .2s, box-shadow .2s;
}
input:focus { border-color: #a6ce39; box-shadow: 0 0 0 3px rgba(166,206,57,.15); }
input::placeholder { color: #94a3b8; }

/* ── Hint ── */
.field-hint {
    display: flex; align-items: center; gap: 5px;
    font-size: 12px; color: #94a3b8; margin-top: 12px;
}
.field-hint svg { width: 13px; height: 13px; flex-shrink: 0; }

/* ── Submit ── */
.btn-submit {
    width: 100%; margin-top: 20px; padding: 13px;
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
    padding: 14px 36px;
    display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px;
}
.footer-note { font-size: 11px; color: #94a3b8; }
.footer-link { font-size: 12px; font-weight: 500; color: #64748b; text-decoration: none; }
.footer-link:hover { color: #0d1b2a; }

/* ── Copyright ── */
.copyright { text-align: center; font-size: 12px; color: #94a3b8; margin-top: 20px; }

@media(max-width: 480px) {
    .card-body   { padding: 28px 20px 22px; }
    .card-footer { padding: 12px 20px; }
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
    <div class="login-wrap">

        <div class="card">
            <div class="card-accent"></div>

            <div class="card-body">

                <div class="card-icon-wrap">
                    <svg viewBox="0 0 24 24" fill="none" stroke="#a6ce39" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 22s-8-4.5-8-11.8A8 8 0 0112 2a8 8 0 018 8.2c0 7.3-8 11.8-8 11.8z"/>
                        <circle cx="12" cy="10" r="3"/>
                    </svg>
                </div>
                <h1 class="card-title">Rider Portal</h1>
                <p class="card-sub">Enter your registered email and phone number to sign in.</p>

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

                <form method="POST" action="">

                    <!-- Email -->
                    <div class="field">
                        <label for="email">Email Address</label>
                        <div class="input-wrap">
                            <span class="input-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                                    <polyline points="22,6 12,13 2,6"/>
                                </svg>
                            </span>
                            <input
                                type="email" id="email" name="email"
                                placeholder="rider@email.com"
                                value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                required autocomplete="email"
                            >
                        </div>
                    </div>

                    <!-- Phone -->
                    <div class="field">
                        <label for="phone">Phone Number</label>
                        <div class="input-wrap">
                            <span class="input-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                                </svg>
                            </span>
                            <input
                                type="tel" id="phone" name="phone"
                                placeholder="0244 123 456"
                                value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>"
                                required autocomplete="tel"
                            >
                        </div>
                    </div>

                    <!-- Hint -->
                    <div class="field-hint">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"/>
                            <line x1="12" y1="16" x2="12" y2="12"/>
                            <line x1="12" y1="8" x2="12.01" y2="8"/>
                        </svg>
                        Both details must match your registered rider account.
                    </div>

                    <button type="submit" class="btn-submit">
                        Sign In to Rider Portal
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M5 12h14M12 5l7 7-7 7"/>
                        </svg>
                    </button>

                </form>
            </div>

            <div class="card-footer">
                <span class="footer-note">Rider staff only. Unauthorised access is prohibited.</span>
               
            </div>
        </div>

        <p class="copyright">© <?php echo date('Y'); ?> Grand Superior Drycleaners. All rights reserved.</p>
    </div>
</main>

</body>
</html>