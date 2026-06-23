<?php
session_start();
include 'connection.php';

if (!isset($_SESSION['owner_id']) && !isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

/* ================= INSERT CLIENT ================= */
$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = trim($_POST['password']);

    if (!empty($name) && !empty($email) && !empty($phone) && !empty($password)) {

        // check duplicate email
        $check = $conn->prepare("SELECT id FROM clients WHERE email=?");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows == 0) {

            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("INSERT INTO clients (name,email,phone,password) VALUES (?,?,?,?)");
            $stmt->bind_param("ssss", $name, $email, $phone, $hashedPassword);

            if ($stmt->execute()) {
                // redirect with success flag
                header("Location: add_client.php?success=1");
                exit();
            } else {
                $error = "Something went wrong while saving this client. Please try again.";
            }

            $stmt->close();
        } else {
            $error = "A client with this email already exists.";
        }

        $check->close();
    } else {
        $error = "Please fill in every field before adding the client.";
    }
}

/* ================= SUCCESS MESSAGE ================= */
$success = isset($_GET['success']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add Client · Grand Superior</title>

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
    --success:#1f9d6f;
    --danger:#e0504a;
    --radius:14px;
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
    min-height:100vh;
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
    letter-spacing:0.2px;
}

/* ===== PAGE SHELL ===== */
.shell{
    max-width:920px;
    margin:64px auto;
    padding:0 20px;
}

.card{
    display:grid;
    grid-template-columns:0.85fr 1.15fr;
    background:var(--surface);
    border-radius:20px;
    overflow:hidden;
    box-shadow:0 20px 50px -20px rgba(13,27,42,0.35);
}

/* ===== LEFT PANEL ===== */
.intro{
    position:relative;
    background:linear-gradient(165deg,var(--ink) 0%,var(--ink-soft) 100%);
    color:white;
    padding:44px 36px;
    display:flex;
    flex-direction:column;
    overflow:hidden;
}

.intro::before{
    content:"";
    position:absolute;
    width:280px;
    height:280px;
    right:-120px;
    top:-120px;
    border-radius:50%;
    background:radial-gradient(circle, rgba(166,206,57,0.25) 0%, rgba(166,206,57,0) 70%);
}

.eyebrow{
    font-size:12px;
    letter-spacing:1.5px;
    text-transform:uppercase;
    color:var(--lime);
    font-weight:600;
    margin-bottom:14px;
}

.intro h1{
    font-family:'Sora',sans-serif;
    font-size:26px;
    line-height:1.3;
    font-weight:600;
    margin-bottom:12px;
}

.intro p{
    font-size:14px;
    line-height:1.6;
    color:rgba(255,255,255,0.7);
    margin-bottom:32px;
}

.checklist{
    position:relative;
    display:flex;
    flex-direction:column;
    gap:0;
    margin-top:auto;
}

.checklist li{
    list-style:none;
    position:relative;
    display:flex;
    align-items:center;
    gap:14px;
    padding:12px 0;
}

.checklist li:not(:last-child)::after{
    content:"";
    position:absolute;
    left:13px;
    top:38px;
    width:1px;
    height:calc(100% - 14px);
    background:rgba(255,255,255,0.15);
}

.check-dot{
    width:26px;
    height:26px;
    flex-shrink:0;
    border-radius:50%;
    background:rgba(166,206,57,0.12);
    border:1px solid rgba(166,206,57,0.4);
    display:flex;
    align-items:center;
    justify-content:center;
    z-index:1;
}

.check-dot svg{
    width:13px;
    height:13px;
    stroke:var(--lime);
}

.checklist .label{
    font-size:13.5px;
    color:rgba(255,255,255,0.85);
    font-weight:500;
}

/* ===== RIGHT PANEL / FORM ===== */
.form-panel{
    padding:44px 40px;
}

.form-panel h2{
    font-family:'Sora',sans-serif;
    font-size:21px;
    font-weight:600;
    margin-bottom:6px;
}

.form-panel .sub{
    font-size:13.5px;
    color:var(--muted);
    margin-bottom:24px;
}

/* ALERTS */
.alert{
    display:flex;
    align-items:flex-start;
    gap:10px;
    padding:13px 14px;
    border-radius:10px;
    font-size:13.5px;
    font-weight:500;
    margin-bottom:20px;
}

.alert svg{
    width:17px;
    height:17px;
    flex-shrink:0;
    margin-top:1px;
}

.alert.success{
    background:rgba(31,157,111,0.1);
    color:var(--success);
    border:1px solid rgba(31,157,111,0.25);
}

.alert.danger{
    background:rgba(224,80,74,0.08);
    color:var(--danger);
    border:1px solid rgba(224,80,74,0.22);
}

/* FORM FIELDS */
form{
    display:flex;
    flex-direction:column;
    gap:16px;
}

.field label{
    display:block;
    font-size:12.5px;
    font-weight:600;
    color:var(--ink);
    margin-bottom:6px;
}

.field .input-wrap{
    position:relative;
    display:flex;
    align-items:center;
}

.field .input-wrap svg{
    position:absolute;
    left:13px;
    width:16px;
    height:16px;
    stroke:var(--muted);
    pointer-events:none;
}

.field input{
    width:100%;
    padding:12px 14px 12px 38px;
    border:1px solid var(--line);
    border-radius:10px;
    font-family:'Inter',sans-serif;
    font-size:14px;
    color:var(--ink);
    background:var(--paper);
    outline:none;
    transition:border-color .15s ease, box-shadow .15s ease, background .15s ease;
}

.field input::placeholder{
    color:#a4afc1;
}

.field input:focus{
    border-color:var(--lime-dark);
    background:white;
    box-shadow:0 0 0 4px rgba(166,206,57,0.18);
}

button{
    margin-top:6px;
    padding:13px;
    background:var(--lime);
    color:var(--ink);
    border:none;
    border-radius:10px;
    font-family:'Inter',sans-serif;
    font-weight:600;
    font-size:14.5px;
    cursor:pointer;
    display:flex;
    align-items:center;
    justify-content:center;
    gap:8px;
    transition:background .15s ease, transform .1s ease;
}

button:hover{
    background:var(--lime-dark);
}

button:active{
    transform:scale(0.99);
}

button svg{
    width:16px;
    height:16px;
}

.back{
    display:flex;
    align-items:center;
    justify-content:center;
    gap:6px;
    margin-top:18px;
    font-size:13.5px;
    text-decoration:none;
    color:var(--muted);
    font-weight:500;
}

.back:hover{
    color:var(--ink);
}

.back svg{
    width:14px;
    height:14px;
}

/* ===== RESPONSIVE ===== */
@media (max-width: 760px){
    .card{
        grid-template-columns:1fr;
    }
    .intro{
        padding:32px 28px;
    }
    .intro::before{
        display:none;
    }
    .checklist{
        margin-top:24px;
    }
    .form-panel{
        padding:32px 28px;
    }
    .shell{
        margin:0;
        padding:0;
    }
    .card{
        border-radius:0;
        box-shadow:none;
        min-height:100vh;
    }
}

@media (prefers-reduced-motion: reduce){
    *{
        transition:none !important;
    }
}
</style>
</head>

<body>

<!-- NAV -->
<div class="navbar">
    <div class="logo">
        <img src="logo.jpg" alt="Grand Superior logo">
        <span>Grand Superior</span>
    </div>
</div>

<div class="shell">
<div class="card">

    <!-- LEFT: CONTEXT PANEL -->
    <div class="intro">
        <div class="eyebrow">Client Management</div>
        <h1>Bring a new client on board.</h1>
        <p>Add their details below to create an account and give them access to their portal.</p>

        <ul class="checklist">
            <li>
                <span class="check-dot">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
                </span>
                <span class="label">Full name</span>
            </li>
            <li>
                <span class="check-dot">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
                </span>
                <span class="label">Email address</span>
            </li>
            <li>
                <span class="check-dot">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
                </span>
                <span class="label">Phone number</span>
            </li>
            <li>
                <span class="check-dot">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
                </span>
                <span class="label">Portal password</span>
            </li>
        </ul>
    </div>

    <!-- RIGHT: FORM PANEL -->
    <div class="form-panel">

        <h2>Add new client</h2>
        <p class="sub">This creates their login and adds them to your client list.</p>

        <?php if ($success): ?>
            <div class="alert success">
                <svg viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
                <span>Client added successfully.</span>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert danger">
                <svg viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <form method="POST">

            <div class="field">
                <label for="name">Client name</label>
                <div class="input-wrap">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    <input type="text" id="name" name="name" placeholder="Jane Doe" required>
                </div>
            </div>

            <div class="field">
                <label for="email">Email address</label>
                <div class="input-wrap">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                    <input type="email" id="email" name="email" placeholder="jane@company.com" required>
                </div>
            </div>

            <div class="field">
                <label for="phone">Phone number</label>
                <div class="input-wrap">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.362 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.338 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                    <input type="text" id="phone" name="phone" placeholder="+254 700 000 000" required>
                </div>
            </div>

            <div class="field">
                <label for="password">Portal password</label>
                <div class="input-wrap">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    <input type="password" id="password" name="password" placeholder="Create a password" required>
                </div>
            </div>

            <button type="submit">
                Add client
                <svg viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
            </button>

        </form>

        <a class="back" href="view_clients.php">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M11 18l-6-6 6-6"/></svg>
            Back to clients
        </a>

    </div>

</div>
</div>

</body>
</html>