<?php
session_start();
include 'connection.php';

// Handle login form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email    = trim($_POST['email']);
    $password = trim($_POST['password']);

    // Check owner credentials
    $stmt = $conn->prepare("SELECT id, password FROM owners WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($id, $hashed_password);
        $stmt->fetch();

        if (password_verify($password, $hashed_password)) {
            $_SESSION['owner_id'] = $id;
            header("Location: owner_dashboard.php");
            exit();
        } else {
            $error = "Invalid email or password.";
        }
    } else {
        $error = "Invalid email or password.";
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Owner Login - Grand Superior Drycleaners</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
body {
    font-family:'Poppins',sans-serif;
    background:linear-gradient(135deg,#0d1b2a,#1b263b);
    display:flex;
    justify-content:center;
    align-items:center;
    height:100vh;
    color:#333;
}
.login-container {
    background:white;
    padding:40px;
    border-radius:16px;
    box-shadow:0 10px 25px rgba(0,0,0,0.2);
    width:380px;
    text-align:center;
}
.login-container h2 {
    margin-bottom:20px;
    color:#0d1b2a;
    font-weight:600;
}
.login-container input {
    width:100%;
    padding:12px;
    margin:10px 0;
    border:1px solid #ccc;
    border-radius:8px;
    font-size:14px;
}
.login-container button {
    width:100%;
    padding:12px;
    background:#a6ce39;
    border:none;
    border-radius:8px;
    font-weight:600;
    cursor:pointer;
    transition:.3s;
}
.login-container button:hover {
    background:#8bb32f;
}
.login-container p {
    margin-top:15px;
    font-size:14px;
}
.login-container a {
    color:#0d1b2a;
    text-decoration:none;
    font-weight:500;
}
.login-container a:hover {
    text-decoration:underline;
}
.error {
    color:red;
    margin-bottom:10px;
}
</style>
</head>
<body>

<div class="login-container">
    <h2>Owner Portal Login</h2>
    <?php if (!empty($error)) echo "<p class='error'>$error</p>"; ?>
    <form method="POST" action="">
        <input type="email" name="email" placeholder="Enter Email" required>
        <input type="password" name="password" placeholder="Enter Password" required>
        <button type="submit">Login</button>
    </form>
    <p>Don’t have an account? <a href="owner_register.php">Register</a></p>
</div>

</body>
</html>
