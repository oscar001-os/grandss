<?php
session_start();
include("connection.php");

/* =========================
   AUTH CHECK (OWNER)
========================= */
if (!isset($_SESSION['owner_id'])) {
    header("Location: owner_login.php");
    exit();
}

/* =========================
   FETCH ALL BOOKINGS
========================= */
$sql = "SELECT * FROM bookings ORDER BY created_at DESC";
$result = mysqli_query($conn, $sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>All Bookings</title>

<style>

/* RESET */
*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:Arial, sans-serif;
}

body{
    background:#f4f6fb;
    padding:20px;
}

/* CONTAINER */
.container{
    max-width:1200px;
    margin:auto;
}

/* HEADER */
.header{
    background:#0d1b2a;
    color:white;
    padding:15px;
    border-radius:12px;
    text-align:center;
    margin-bottom:20px;
}

/* TABLE WRAPPER */
.table-box{
    background:white;
    border-radius:12px;
    overflow:auto;
    box-shadow:0 5px 15px rgba(0,0,0,0.08);
}

/* TABLE */
table{
    width:100%;
    border-collapse:collapse;
    min-width:1000px;
}

th{
    background:#0d1b2a;
    color:white;
    padding:12px;
    text-align:left;
    font-size:14px;
}

td{
    padding:12px;
    border-bottom:1px solid #eee;
    font-size:13px;
    vertical-align:top;
}

tr:hover{
    background:#f9fafb;
}

/* STATUS */
.status{
    padding:5px 10px;
    border-radius:20px;
    color:white;
    font-size:12px;
    display:inline-block;
}

.Pending{background:#f39c12;}
.Picked{background:#3498db;}
.Washing{background:#9b59b6;}
.Ironing{background:#1abc9c;}
.Ready{background:#2ecc71;}
.Delivered{background:#27ae60;}
.Done{background:#2ecc71;}

/* NOTES */
.notes{
    max-width:200px;
    word-wrap:break-word;
}

/* BACK BUTTON */
.back{
    display:inline-block;
    margin-bottom:15px;
    background:#a6ce39;
    padding:10px 15px;
    border-radius:8px;
    color:#000;
    font-weight:bold;
    text-decoration:none;
}

</style>
</head>

<body>

<div class="container">

<a href="owner_dashboard.php" class="back">← Back</a>

<div class="header">
    <h2>All Client Bookings</h2>
</div>

<div class="table-box">

<table>

<tr>
    <th>ID</th>
    <th>Client ID</th>
    <th>Service</th>
    <th>Pickup</th>
    <th>Delivery</th>
    <th>Address</th>
    <th>Notes</th>
    <th>Created</th>
    <th>Status</th>
    <th>Rider ID</th>
</tr>

<?php if ($result && mysqli_num_rows($result) > 0) { ?>

    <?php while ($row = mysqli_fetch_assoc($result)) { ?>

        <?php $statusClass = str_replace(" ", "", $row['status']); ?>

        <tr>

            <td><?php echo $row['id']; ?></td>
            <td><?php echo $row['client_id']; ?></td>
            <td><?php echo htmlspecialchars($row['service_type']); ?></td>
            <td><?php echo $row['pickup_date']; ?></td>
            <td><?php echo $row['delivery_date']; ?></td>
            <td><?php echo htmlspecialchars($row['address']); ?></td>
            <td class="notes"><?php echo htmlspecialchars($row['notes']); ?></td>
            <td><?php echo $row['created_at']; ?></td>

            <td>
                <span class="status <?php echo $statusClass; ?>">
                    <?php echo $row['status']; ?>
                </span>
            </td>

            <td><?php echo $row['rider_id']; ?></td>

        </tr>

    <?php } ?>

<?php } else { ?>

    <tr>
        <td colspan="10" style="text-align:center; padding:20px;">
            No bookings found.
        </td>
    </tr>

<?php } ?>

</table>

</div>

</div>

</body>
</html>