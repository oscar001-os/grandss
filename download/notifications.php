<?php
session_start();
include("connection.php");

/* =========================
   SHOW ERRORS (DEBUG MODE)
========================= */
ini_set('display_errors', 1);
error_reporting(E_ALL);

/* =========================
   CHECK OWNER LOGIN
========================= */
if (!isset($_SESSION['owner_id'])) {
    header("Location: owner_login.php");
    exit();
}

$owner_id = $_SESSION['owner_id'];

$msg = "";

/* =========================
   CHECK DB CONNECTION
========================= */
if (!$conn) {
    die("Database connection failed");
}

/* =========================
   HANDLE FORM SUBMISSION
========================= */
if (isset($_POST['send'])) {

    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $message = mysqli_real_escape_string($conn, $_POST['message']);

    if (!empty($title) && !empty($message)) {

        $sql = "INSERT INTO notifications (title, message, owner_id)
                VALUES ('$title', '$message', '$owner_id')";

        if (mysqli_query($conn, $sql)) {
            $msg = "Notification sent successfully!";
        } else {
            $msg = "Database error: " . mysqli_error($conn);
        }

    } else {
        $msg = "All fields are required.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Send Notifications</title>

<style>
body{
    font-family: Arial;
    background:#f4f6f9;
}

.box{
    width:420px;
    margin:90px auto;
    background:#fff;
    padding:25px;
    border-radius:10px;
    box-shadow:0 5px 20px rgba(0,0,0,0.1);
}

input,textarea{
    width:100%;
    padding:10px;
    margin-top:10px;
}

button{
    width:100%;
    padding:12px;
    margin-top:15px;
    background:#0d1b2a;
    color:white;
    border:none;
    cursor:pointer;
}

.msg{
    text-align:center;
    margin-top:10px;
    font-weight:bold;
    color:green;
}
</style>
</head>

<body>

<div class="box">

<h2>Send Notification</h2>

<?php if ($msg != "") { ?>
    <div class="msg"><?php echo $msg; ?></div>
<?php } ?>

<form method="POST">

    <label>Title</label>
    <input type="text" name="title">

    <label>Message</label>
    <textarea name="message" rows="5"></textarea>

    <button type="submit" name="send">Send</button>

</form>

</div>

</body>
</html>