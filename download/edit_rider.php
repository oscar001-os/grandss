<?php
session_start();
include 'connection.php';

/* AUTH CHECK */
if (!isset($_SESSION['owner_id'])) {
    header("Location: owner_login.php");
    exit();
}

/* GET RIDER ID */
if (!isset($_GET['id'])) {
    header("Location: manage_riders.php");
    exit();
}

$id = intval($_GET['id']);

/* FETCH RIDER */
$stmt = $conn->prepare("SELECT * FROM riders WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header("Location: manage_riders.php");
    exit();
}

$rider = $result->fetch_assoc();

/* SUCCESS + ERROR */
$success = "";
$errors = [];

/* =========================
   UPDATE RIDER
========================= */
if (isset($_POST['update_rider'])) {

    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $national_id = trim($_POST['national_id']);
    $address = trim($_POST['address']);
    $vehicle = trim($_POST['vehicle']);
    $status = trim($_POST['status']);

    /* VALIDATION */
    if ($name == "") $errors[] = "Name is required";
    if ($phone == "") $errors[] = "Phone is required";
    if ($vehicle == "") $errors[] = "Vehicle is required";
    if ($status == "") $errors[] = "Status is required";

    if (empty($errors)) {

        $photoName = $rider['photo'];

        /* PHOTO UPLOAD */
        if (!empty($_FILES['photo']['name'])) {

            $targetDir = "uploads/riders/";

            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0777, true);
            }

            $photoName = time() . "_" . basename($_FILES["photo"]["name"]);
            move_uploaded_file($_FILES["photo"]["tmp_name"], $targetDir . $photoName);
        }

        /* UPDATE QUERY (FIXED) */
        $update = $conn->prepare("
            UPDATE riders 
            SET name=?, phone=?, email=?, national_id=?, address=?, vehicle=?, photo=?, status=?
            WHERE id=?
        ");

        if (!$update) {
            die("SQL Error: " . $conn->error);
        }

        $update->bind_param(
            "ssssssssi",
            $name,
            $phone,
            $email,
            $national_id,
            $address,
            $vehicle,
            $photoName,
            $status,
            $id
        );

        if ($update->execute()) {
            $success = "Rider updated successfully!";
        } else {
            $errors[] = "Failed to update rider.";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Rider</title>

<style>

body{
    font-family:Arial;
    background:#f4f6fb;
    padding:20px;
}

.container{
    max-width:600px;
    margin:auto;
    background:white;
    padding:20px;
    border-radius:12px;
    box-shadow:0 5px 20px rgba(0,0,0,0.1);
}

h2{
    text-align:center;
    color:#0d1b2a;
}

input, select{
    width:100%;
    padding:10px;
    margin-top:10px;
    border:1px solid #ccc;
    border-radius:8px;
}

button{
    width:100%;
    padding:12px;
    margin-top:15px;
    background:#a6ce39;
    border:none;
    font-weight:bold;
    border-radius:8px;
    cursor:pointer;
}

button:hover{
    background:#8bb32f;
}

.success{
    background:#22c55e;
    color:white;
    padding:10px;
    text-align:center;
    margin-bottom:10px;
    border-radius:8px;
}

.error{
    background:#e74c3c;
    color:white;
    padding:10px;
    margin-bottom:10px;
    border-radius:8px;
}

img{
    width:80px;
    height:80px;
    border-radius:50%;
    display:block;
    margin:10px auto;
}

</style>
</head>
<body>

<div class="container">

<h2>Edit Rider</h2>

<!-- SUCCESS -->
<?php if($success != "") { ?>
    <div class="success"><?php echo $success; ?></div>
<?php } ?>

<!-- ERRORS -->
<?php if(!empty($errors)) { ?>
    <?php foreach($errors as $err) { ?>
        <div class="error"><?php echo $err; ?></div>
    <?php } ?>
<?php } ?>

<!-- IMAGE -->
<?php if($rider['photo']) { ?>
    <img src="uploads/riders/<?php echo $rider['photo']; ?>">
<?php } ?>

<form method="POST" enctype="multipart/form-data">

    <input type="text" name="name" value="<?php echo $rider['name']; ?>" placeholder="Name">

    <input type="text" name="phone" value="<?php echo $rider['phone']; ?>" placeholder="Phone">

    <input type="email" name="email" value="<?php echo $rider['email']; ?>" placeholder="Email">

    <input type="text" name="national_id" value="<?php echo $rider['national_id']; ?>" placeholder="National ID">

    <input type="text" name="vehicle" value="<?php echo $rider['vehicle']; ?>" placeholder="Vehicle">

    <input type="text" name="address" value="<?php echo $rider['address']; ?>" placeholder="Address">

    <select name="status">
        <option value="Available" <?php if($rider['status']=="Available") echo "selected"; ?>>Available</option>
        <option value="Busy" <?php if($rider['status']=="Busy") echo "selected"; ?>>Busy</option>
        <option value="Inactive" <?php if($rider['status']=="Inactive") echo "selected"; ?>>Inactive</option>
    </select>

    <input type="file" name="photo">

    <!-- IMPORTANT FIX -->
    <button type="submit" name="update_rider">Update Rider</button>

</form>

    <a class="back" href="view_riders.php">← Back to Riders</a>
</div>

</body>
</html>