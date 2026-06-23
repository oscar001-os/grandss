<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include("connection.php");

if (!isset($_SESSION['rider_id'])) {
    header("Location: rider_login.php");
    exit();
}

$rider_id = $_SESSION['rider_id'];

/* FETCH RIDER INFO */
$stmt = $conn->prepare("SELECT name, status FROM riders WHERE id = ?");
$stmt->bind_param("i", $rider_id);
$stmt->execute();
$rider = $stmt->get_result()->fetch_assoc();
$stmt->close();

/* FETCH BOOKINGS */
$stmt = $conn->prepare("SELECT * FROM bookings WHERE rider_id = ? ORDER BY id DESC");
$stmt->bind_param("i", $rider_id);
$stmt->execute();
$orders = $stmt->get_result();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>View Orders</title>
<style>
body{font-family:sans-serif;background:#0f172a;color:#e5e7eb;margin:0;}
.topbar{background:#111c33;padding:15px;display:flex;justify-content:space-between;align-items:center;}
.topbar strong{color:#38bdf8;}
.container{padding:20px;}
.order{background:#162544;padding:15px;border-radius:10px;margin-bottom:15px;}
.order p{margin:5px 0;color:#94a3b8;}
.badge{padding:4px 8px;border-radius:12px;font-size:12px;color:#fff;}
.Completed{background:#22c55e;}
.Pending{background:#f59e0b;}
.Cancelled{background:#ef4444;}
</style>
</head>
<body>

<div class="topbar">
    <strong>Laundry Rider</strong>
    <a href="rider_logout.php" style="color:#ef4444;">Logout</a>
</div>

<div class="container">
    <h2>Welcome, <?php echo htmlspecialchars($rider['name']); ?> 🚴</h2>
    <p>Status: <span class="badge <?php echo $rider['status']; ?>"><?php echo $rider['status']; ?></span></p>

    <h3>📦 My Orders</h3>
    <?php if ($orders->num_rows > 0) { ?>
        <?php while ($row = $orders->fetch_assoc()) { ?>
            <div class="order">
                <p><strong>Booking ID:</strong> <?php echo $row['id']; ?></p>
                <?php if (isset($row['service_type'])) { ?><p><strong>Service:</strong> <?php echo $row['service_type']; ?></p><?php } ?>
                <?php if (isset($row['address'])) { ?><p><strong>Location:</strong> <?php echo $row['address']; ?></p><?php } ?>
                <?php if (isset($row['pickup_date'])) { ?><p><strong>Pickup Date:</strong> <?php echo $row['pickup_date']; ?></p><?php } ?>
                <?php if (isset($row['delivery_date'])) { ?><p><strong>Delivery Date:</strong> <?php echo $row['delivery_date']; ?></p><?php } ?>
                <?php if (isset($row['status'])) { ?><p><strong>Status:</strong> <span class="badge <?php echo $row['status']; ?>"><?php echo $row['status']; ?></span></p><?php } ?>
            </div>
        <?php } ?>
    <?php } else { ?>
        <p>No orders assigned yet.</p>
    <?php } ?>
</div>

</body>
</html>
