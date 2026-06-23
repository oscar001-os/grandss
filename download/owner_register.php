<?php
session_start();
include 'connection.php';
$success  = "";
$errors   = [];
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';
    /* ── SERVER-SIDE VALIDATION ── */
    if (empty($name))  $errors[] = "Full name is required.";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL))
        $errors[] = "A valid email address is required.";
    if (empty($phone) || !preg_match('/^[0-9+\s\-]{7,15}$/', $phone))
        $errors[] = "Enter a valid phone number (7–15 digits).";
    /* Password rules */
    if (strlen($password) < 8)
        $errors[] = "Password must be at least 8 characters.";
    if (!preg_match('/[A-Z]/', $password))
        $errors[] = "Password must contain at least one uppercase letter.";
    if (!preg_match('/[a-z]/', $password))
        $errors[] = "Password must contain at least one lowercase letter.";
    if (!preg_match('/[0-9]/', $password))
        $errors[] = "Password must contain at least one number.";
    if (!preg_match('/[\W_]/', $password))
        $errors[] = "Password must contain at least one special character (!@#\$%^&*).";
    if ($password !== $confirm)
        $errors[] = "Passwords do not match.";
    if (empty($errors)) {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $check = $conn->prepare("SELECT id FROM owners WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();
        if ($check->num_rows > 0) {
            $errors[] = "This email is already registered. Please login.";
        } else {
            $insert = $conn->prepare("INSERT INTO owners (name, email, phone, password) VALUES (?, ?, ?, ?)");
            $insert->bind_param("ssss", $name, $email, $phone, $hashed);
            if ($insert->execute()) {
                $success = "Registration successful! You can now login.";
            } else {
                $errors[] = "Registration failed. Please try again.";
            }
            $insert->close();
        }
        $check->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Owner Registration — Grand Superior</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root{
  --bg:#0d1b2a;
  --bg2:#1a2e42;
  --surface:#ffffff;
  --accent:#a6ce39;
  --accent-dark:#8ab530;
  --accent-dim:#f2f9e4;
  --border:#ddeec8;
  --text:#1a2a14;
  --muted:#6b7e5a;
  --error:#ef4444;
  --error-bg:#fef2f2;
  --error-border:#fecaca;
  --success:#16a34a;
  --success-bg:#f0fdf4;
  --success-border:#bbf7d0;
  --shadow:0 8px 40px rgba(0,0,0,0.28);
}
*{margin:0;padding:0;box-sizing:border-box}
body{
  font-family:'Plus Jakarta Sans',sans-serif;
  background:linear-gradient(135deg,var(--bg) 0%,var(--bg2) 100%);
  min-height:100vh;
  display:flex;
  align-items:center;
  justify-content:center;
  padding:24px;
}
/* ── CARD ── */
.card{
  background:var(--surface);
  border-radius:22px;
  box-shadow:var(--shadow);
  width:100%;
  max-width:460px;
  overflow:hidden;
}
.card-header{
  background:linear-gradient(135deg,var(--bg) 0%,#1a3a50 100%);
  padding:30px 36px 26px;
  text-align:center;
  position:relative;
  overflow:hidden;
}
.card-header::before{
  content:'';position:absolute;top:-40px;right:-40px;
  width:160px;height:160px;border-radius:50%;
  background:rgba(166,206,57,.08);
}
.card-header::after{
  content:'';position:absolute;bottom:-50px;left:-30px;
  width:130px;height:130px;border-radius:50%;
  background:rgba(166,206,57,.05);
}
.logo-wrap{
  position:relative;z-index:1;
  display:flex;justify-content:center;margin-bottom:16px;
}
.logo-wrap img{
  max-width:160px;height:auto;display:block;
}
.card-header h1{
  position:relative;z-index:1;
  font-size:18px;font-weight:800;color:#fff;letter-spacing:-.3px;
}
.card-header p{
  position:relative;z-index:1;
  font-size:12px;color:rgba(255,255,255,.5);margin-top:4px;font-weight:500;
}
/* ── BODY ── */
.card-body{padding:28px 36px 32px}
/* ── ALERTS ── */
.alert{
  border-radius:10px;padding:12px 14px;margin-bottom:18px;font-size:13px;
  display:flex;flex-direction:column;gap:4px;
}
.alert-error{background:var(--error-bg);border:1px solid var(--error-border);color:#b91c1c}
.alert-success{background:var(--success-bg);border:1px solid var(--success-border);color:var(--success)}
.alert ul{padding-left:16px;margin-top:4px}
.alert ul li{margin-top:2px;font-size:12.5px}
.alert a{color:inherit;font-weight:700;text-decoration:underline}
/* ── FORM ── */
.field{margin-bottom:14px}
.field label{
  display:block;font-size:12px;font-weight:700;color:var(--text);
  margin-bottom:5px;letter-spacing:.2px;
}
.field label span{color:var(--error);margin-left:2px}
.input-wrap{position:relative}
.input-wrap svg{
  position:absolute;left:12px;top:50%;transform:translateY(-50%);
  width:15px;height:15px;fill:none;stroke:#9ca3af;stroke-width:2;
  stroke-linecap:round;stroke-linejoin:round;pointer-events:none;
}
.input-wrap input{
  width:100%;padding:11px 12px 11px 36px;
  border:1.5px solid #e5e7eb;border-radius:10px;
  font-size:13.5px;font-family:inherit;color:var(--text);
  transition:border-color .2s,box-shadow .2s;outline:none;
  background:#fafafa;
}
.input-wrap input:focus{
  border-color:var(--accent);
  box-shadow:0 0 0 3px rgba(166,206,57,.15);
  background:#fff;
}
.input-wrap input.invalid{border-color:var(--error);background:#fff8f8}
.input-wrap input.valid{border-color:#22c55e;background:#f0fdf4}
.toggle-pw{
  position:absolute;right:12px;top:50%;transform:translateY(-50%);
  background:none;border:none;cursor:pointer;padding:0;
  color:#9ca3af;display:flex;align-items:center;
}
.toggle-pw svg{width:15px;height:15px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
/* ── PASSWORD STRENGTH ── */
.pw-rules{margin-top:8px;display:none;flex-direction:column;gap:4px}
.pw-rules.show{display:flex}
.pw-rule{
  display:flex;align-items:center;gap:7px;
  font-size:11.5px;color:#6b7280;transition:color .2s;
}
.pw-rule svg{width:13px;height:13px;flex-shrink:0;fill:none;stroke:currentColor;stroke-width:2.5;stroke-linecap:round;stroke-linejoin:round}
.pw-rule.ok{color:#16a34a}
.pw-rule.fail{color:#b91c1c}
.strength-bar{
  display:flex;gap:4px;margin-top:8px;height:4px;border-radius:4px;overflow:hidden;
}
.strength-seg{flex:1;background:#e5e7eb;border-radius:4px;transition:background .25s}
.strength-seg.filled-1{background:#ef4444}
.strength-seg.filled-2{background:#f97316}
.strength-seg.filled-3{background:#facc15}
.strength-seg.filled-4{background:#84cc16}
.strength-seg.filled-5{background:#22c55e}
.strength-label{font-size:11px;color:var(--muted);margin-top:4px;text-align:right;font-weight:600}
/* ── DIVIDER ── */
.divider{height:1px;background:#f0f0f0;margin:18px 0}
/* ── SUBMIT ── */
.btn-submit{
  width:100%;padding:13px;
  background:var(--accent);border:none;border-radius:10px;
  font-size:14px;font-weight:700;color:#0d1b2a;
  cursor:pointer;transition:background .2s,transform .15s;
  font-family:inherit;letter-spacing:.2px;
}
.btn-submit:hover{background:var(--accent-dark)}
.btn-submit:active{transform:scale(.985)}
/* ── FOOTER ── */
.form-footer{
  text-align:center;margin-top:16px;
  font-size:13px;color:var(--muted);
}
.form-footer a{
  color:#0d1b2a;font-weight:700;text-decoration:none;
}
.form-footer a:hover{text-decoration:underline}
</style>
</head>
<body>
<div class="card">
  <!-- HEADER -->
  <div class="card-header">
    <div class="logo-wrap">
      <img src="logo.jpg" alt="Grand Superior">
    </div>
    <h1>Create Owner Account</h1>
    <p>Grand Superior Drycleaners &mdash; Owner Portal</p>
  </div>
  <!-- BODY -->
  <div class="card-body">
    <?php if (!empty($success)): ?>
    <div class="alert alert-success">
      <strong>✓ <?= htmlspecialchars($success) ?></strong>
      <a href="owner_login.php">Go to Login &rarr;</a>
    </div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
    <div class="alert alert-error">
      <strong>Please fix the following:</strong>
      <ul>
        <?php foreach ($errors as $e): ?>
          <li><?= htmlspecialchars($e) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>
    <?php if (empty($success)): ?>
    <form method="POST" action="" id="regForm" novalidate>
      <!-- Full Name -->
      <div class="field">
        <label for="name">Full Name <span>*</span></label>
        <div class="input-wrap">
          <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          <input type="text" id="name" name="name"
            placeholder="e.g. Jane Doe"
            value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
        </div>
      </div>
      <!-- Email -->
      <div class="field">
        <label for="email">Email Address <span>*</span></label>
        <div class="input-wrap">
          <svg viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
          <input type="email" id="email" name="email"
            placeholder="you@example.com"
            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
        </div>
      </div>
      <!-- Phone -->
      <div class="field">
        <label for="phone">Phone Number <span>*</span></label>
        <div class="input-wrap">
          <svg viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.6 3.37 2 2 0 0 1 3.59 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.87a16 16 0 0 0 6 6l.87-.87a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
          <input type="text" id="phone" name="phone"
            placeholder="e.g. +254 700 000000"
            value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" required>
        </div>
      </div>
      <div class="divider"></div>
      <!-- Password -->
      <div class="field">
        <label for="password">Password <span>*</span></label>
        <div class="input-wrap">
          <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          <input type="password" id="password" name="password"
            placeholder="Create a strong password" required autocomplete="new-password">
          <button type="button" class="toggle-pw" onclick="togglePw('password','eyeIcon1')">
            <svg id="eyeIcon1" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          </button>
        </div>
        <!-- Strength bar -->
        <div class="strength-bar" id="strengthBar">
          <div class="strength-seg" id="seg1"></div>
          <div class="strength-seg" id="seg2"></div>
          <div class="strength-seg" id="seg3"></div>
          <div class="strength-seg" id="seg4"></div>
          <div class="strength-seg" id="seg5"></div>
        </div>
        <div class="strength-label" id="strengthLabel"></div>
        <!-- Rules checklist -->
        <div class="pw-rules" id="pwRules">
          <div class="pw-rule" id="rule-len">
            <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
            At least 8 characters
          </div>
          <div class="pw-rule" id="rule-upper">
            <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
            At least one uppercase letter (A–Z)
          </div>
          <div class="pw-rule" id="rule-lower">
            <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
            At least one lowercase letter (a–z)
          </div>
          <div class="pw-rule" id="rule-num">
            <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
            At least one number (0–9)
          </div>
          <div class="pw-rule" id="rule-special">
            <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
            At least one special character (!@#$%^&*)
          </div>
        </div>
      </div>
      <!-- Confirm Password -->
      <div class="field">
        <label for="confirm_password">Confirm Password <span>*</span></label>
        <div class="input-wrap">
          <svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
          <input type="password" id="confirm_password" name="confirm_password"
            placeholder="Re-enter your password" required autocomplete="new-password">
          <button type="button" class="toggle-pw" onclick="togglePw('confirm_password','eyeIcon2')">
            <svg id="eyeIcon2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          </button>
        </div>
        <div class="pw-rule" id="rule-match" style="margin-top:7px">
          <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
          Passwords match
        </div>
      </div>
      <button type="submit" class="btn-submit">Create Account</button>
    </form>
    <p class="form-footer">Already have an account? <a href="owner_login.php">Sign in</a></p>
    <?php endif; ?>
  </div>
</div>
<script>
/* ── SHOW / HIDE PASSWORD ── */
function togglePw(inputId, iconId) {
  const inp  = document.getElementById(inputId);
  const icon = document.getElementById(iconId);
  if (inp.type === 'password') {
    inp.type = 'text';
    icon.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/>';
  } else {
    inp.type = 'password';
    icon.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
  }
}
/* ── PASSWORD RULES & STRENGTH ── */
const pwInput   = document.getElementById('password');
const cfmInput  = document.getElementById('confirm_password');
const pwRules   = document.getElementById('pwRules');
const segs      = [1,2,3,4,5].map(i => document.getElementById('seg'+i));
const strengthLabel = document.getElementById('strengthLabel');
const levels    = ['','Weak','Fair','Moderate','Strong','Very Strong'];
const levelColors = ['','filled-1','filled-2','filled-3','filled-4','filled-5'];
function checkRule(id, passed) {
  const el = document.getElementById(id);
  el.classList.toggle('ok',   passed);
  el.classList.toggle('fail', !passed && pwInput.value.length > 0);
  /* swap icon */
  el.querySelector('svg').innerHTML = passed
    ? '<polyline points="20 6 9 17 4 12"/>'
    : '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>';
  return passed;
}
function updateStrength() {
  const v = pwInput.value;
  const rules = [
    checkRule('rule-len',     v.length >= 8),
    checkRule('rule-upper',   /[A-Z]/.test(v)),
    checkRule('rule-lower',   /[a-z]/.test(v)),
    checkRule('rule-num',     /[0-9]/.test(v)),
    checkRule('rule-special', /[\W_]/.test(v)),
  ];
  const score = rules.filter(Boolean).length;
  segs.forEach((s, i) => {
    s.className = 'strength-seg';
    if (i < score) s.classList.add(levelColors[score]);
  });
  strengthLabel.textContent = v.length > 0 ? levels[score] : '';
  /* colour label */
  strengthLabel.style.color = ['','#ef4444','#f97316','#facc15','#84cc16','#22c55e'][score] || '';
  /* validate field border */
  if (v.length > 0) {
    pwInput.classList.toggle('valid',   score === 5);
    pwInput.classList.toggle('invalid', score < 5);
  } else {
    pwInput.classList.remove('valid','invalid');
  }
  checkMatchRule();
}
function checkMatchRule() {
  const pw  = pwInput.value;
  const cfm = cfmInput.value;
  const rule = document.getElementById('rule-match');
  if (cfm.length === 0 && pw.length === 0) {
    rule.classList.remove('ok','fail');
    cfmInput.classList.remove('valid','invalid');
    rule.querySelector('svg').innerHTML = '<polyline points="20 6 9 17 4 12"/>';
    return;
  }
  const match = pw === cfm && cfm.length > 0;
  rule.classList.toggle('ok',   match);
  rule.classList.toggle('fail', !match);
  rule.querySelector('svg').innerHTML = match
    ? '<polyline points="20 6 9 17 4 12"/>'
    : '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>';
  cfmInput.classList.toggle('valid',   match);
  cfmInput.classList.toggle('invalid', !match);
}
pwInput.addEventListener('focus',  () => pwRules.classList.add('show'));
pwInput.addEventListener('input',  updateStrength);
cfmInput.addEventListener('input', checkMatchRule);
/* ── CLIENT-SIDE GATE ── */
document.getElementById('regForm').addEventListener('submit', function(e) {
  const v     = pwInput.value;
  const valid =
    v.length >= 8 &&
    /[A-Z]/.test(v) &&
    /[a-z]/.test(v) &&
    /[0-9]/.test(v) &&
    /[\W_]/.test(v) &&
    v === cfmInput.value;
  if (!valid) {
    e.preventDefault();
    pwRules.classList.add('show');
    pwInput.classList.add('invalid');
    updateStrength();
  }
});
</script>
</body>
</html>