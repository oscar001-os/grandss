<?php
session_start();
include 'connection.php';

if (!isset($_SESSION['owner_id'])) {
    header("Location: owner_login.php");
    exit();
}

$owner_id = $_SESSION['owner_id'];

/* OWNER DETAILS */
$stmt = $conn->prepare("SELECT name,email,phone FROM owners WHERE id=?");
$stmt->bind_param("i",$owner_id);
$stmt->execute();
$owner = $stmt->get_result()->fetch_assoc();
$stmt->close();

/* COUNTS */
$total_clients = mysqli_fetch_assoc($conn->query("SELECT COUNT(*) AS total FROM clients"))['total'];
$total_bookings = mysqli_fetch_assoc($conn->query("SELECT COUNT(*) AS total FROM bookings"))['total'];
$total_payments = mysqli_fetch_assoc($conn->query("SELECT COUNT(*) AS total FROM payments"))['total'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Owner Dashboard</title>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<style>
:root{
    --primary:#0d1b2a;
    --accent:#a6ce39;
    --bg:#f4f7fb;
    --card:#ffffff;
    --shadow:0 10px 25px rgba(0,0,0,0.12);
}

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:'Poppins',sans-serif;
}

body{
    background:var(--bg);
}

/* ================= TOP BAR ================= */
.topbar{
    position:fixed;
    top:0;
    left:0;
    width:100%;
    height:75px;
    background:rgba(13,27,42,0.97);
    display:flex;
    align-items:center;
    justify-content:space-between;
    padding:0 18px;
    z-index:1000;
    backdrop-filter:blur(10px);
}

/* LOGO */
.logo{
    display:flex;
    align-items:center;
    gap:10px;
}

.logo img{
    width:45px;
    height:45px;
    border-radius:10px;
    background:white;
    padding:4px;
}

.logo h2{
    color:white;
    font-size:18px;
}

.logo span{
    color:var(--accent);
}

/* NAV BUTTON (MOBILE) */
.menu-btn{
    display:none;
    font-size:30px;
    color:white;
    cursor:pointer;
}

/* NAV LINKS */
.nav{
    display:flex;
    gap:12px;
    align-items:center;
}

.nav a{
    text-decoration:none;
    color:white;
    font-size:14px;
    padding:8px 10px;
    border-radius:8px;
    transition:.3s;
}

.nav a:hover{
    background:var(--accent);
    color:#111;
}

.logout{
    background:#ef4444;
    color:white !important;
}

/* ================= MAIN ================= */
.main{
    padding:100px 6% 40px;
}

/* HEADER */
.header{
    background:linear-gradient(135deg,#0d1b2a,#1b3a57);
    color:white;
    padding:25px;
    border-radius:16px;
    box-shadow:var(--shadow);
    display:flex;
    justify-content:space-between;
    flex-wrap:wrap;
}

.header h2{
    font-size:22px;
}

.header p{
    opacity:0.9;
}

/* STATS */
.stats{
    margin-top:20px;
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
    gap:20px;
}

.stat-card{
    background:white;
    padding:20px;
    border-radius:14px;
    box-shadow:var(--shadow);
}

.stat-card h3{
    font-size:14px;
    color:#666;
}

.stat-card h1{
    font-size:32px;
    color:var(--primary);
}

/* CARDS */
.card{
    background:white;
    padding:22px;
    border-radius:14px;
    box-shadow:var(--shadow);
    margin-top:25px;
    overflow-x:auto;
}

.card h3{
    margin-bottom:15px;
    color:var(--primary);
}

/* TABLE */
table{
    width:100%;
    border-collapse:collapse;
    min-width:600px;
}

table th{
    background:var(--primary);
    color:white;
    padding:12px;
    text-align:left;
}

table td{
    padding:12px;
    border-bottom:1px solid #eee;
}

table tr:hover{
    background:#f8fafc;
}

/* BUTTON */
.btn{
    display:inline-block;
    margin-top:15px;
    padding:10px 16px;
    background:var(--accent);
    color:#111;
    text-decoration:none;
    font-weight:600;
    border-radius:10px;
}

/* PROFILE */
.profile p{
    margin:8px 0;
    color:#444;
}

/* ================= MOBILE ================= */
@media(max-width:768px){

.menu-btn{
    display:block;
}

.nav{
    position:absolute;
    top:75px;
    left:0;
    width:100%;
    background:var(--primary);
    flex-direction:column;
    display:none;
    padding:15px;
}

.nav.active{
    display:flex;
}

.nav a{
    width:100%;
    text-align:center;
}

.header{
    flex-direction:column;
    gap:10px;
}

table{
    min-width:100%;
}

}
</style>
</head>

<body>

<!-- TOP BAR -->
<div class="topbar">

    <div class="logo">
        <img src="logo.jpg" alt="Logo">
        <h2>Grand <span>Superior</span></h2>
    </div>

    <div class="menu-btn" onclick="toggleMenu()">☰</div>

    <div class="nav" id="nav">
        <a href="#dashboard">Dashboard</a>
        <a href="view_clients.php">Clients</a>
         <a href="view_riders.php">Riders</a>
        <a href="client_bookings.php">Bookings</a>
        <a href="view_payments.php">Payments</a>
        <a href="send_notifications.php">Notifications</a>
        <a href="owner_profile.php">Profile</a>
        <a href="logout.php" class="logout">Logout</a>
    </div>

</div>

<!-- MAIN -->
<div class="main">

<!-- HEADER -->
<div class="header" id="dashboard">
    <div>
        <h2>Welcome, <?php echo htmlspecialchars($owner['name']); ?></h2>
        <p>Owner Dashboard Overview</p>
    </div>
    <div>
        <?php echo htmlspecialchars($owner['email']); ?>
    </div>
</div>

<!-- STATS -->
<div class="stats">

    <div class="stat-card">
        <h3>Total Clients</h3>
        <h1><?php echo $total_clients; ?></h1>
    </div>

    <div class="stat-card">
        <h3>Total Bookings</h3>
        <h1><?php echo $total_bookings; ?></h1>
    </div>

    <div class="stat-card">
        <h3>Total Payments</h3>
        <h1><?php echo $total_payments; ?></h1>
    </div>

</div>

<!-- CLIENTS -->
<div class="card">
<h3>Recent Clients</h3>

<table>
<tr>
<th>ID</th>
<th>Name</th>
<th>Email</th>
</tr>

<?php
$clients = $conn->query("SELECT id,name,email FROM clients ORDER BY id DESC LIMIT 5");

while($row = $clients->fetch_assoc()){
echo "<tr>
<td>{$row['id']}</td>
<td>".htmlspecialchars($row['name'])."</td>
<td>".htmlspecialchars($row['email'])."</td>
</tr>";
}
?>
</table>

<a href="view_clients.php" class="btn">View All Clients</a>
</div>

<!-- BOOKINGS -->
<div class="card">
<h3>Recent Bookings</h3>

<table>
<tr>
<th>ID</th>
<th>Client</th>
<th>Service</th>
<th>Status</th>
</tr>

<?php
$bookings = $conn->query("SELECT * FROM bookings ORDER BY id DESC LIMIT 5");

while($row = $bookings->fetch_assoc()){
echo "<tr>
<td>{$row['id']}</td>
<td>{$row['client_id']}</td>
<td>{$row['service_type']}</td>
<td>{$row['status']}</td>
</tr>";
}
?>
</table>

<a href="client_bookings.php" class="btn">View All Bookings</a>
</div>

<!-- PAYMENTS -->
<div class="card">
<h3>Recent Payments</h3>

<table>
<tr>
<th>ID</th>
<th>Client</th>
<th>Amount</th>
<th>Status</th>
</tr>

<?php
$payments = $conn->query("SELECT * FROM payments ORDER BY id DESC LIMIT 5");

while($row = $payments->fetch_assoc()){
echo "<tr>
<td>{$row['id']}</td>
<td>{$row['client_id']}</td>
<td>KES ".number_format($row['amount'],2)."</td>
<td>{$row['status']}</td>
</tr>";
}
?>
</table>

<a href="client_payments.php" class="btn">View All Payments</a>
</div>





<script>
function toggleMenu(){
    document.getElementById("nav").classList.toggle("active");
}
</script>

</body>
</html>