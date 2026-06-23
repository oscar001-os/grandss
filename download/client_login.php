<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'connection.php';
session_start();

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    if (empty($email) || empty($password)) {

        $error = "Please enter both email and password.";

    } else {

        $stmt = $conn->prepare(
            "SELECT id, name, email, password
             FROM clients
             WHERE email = ?"
        );

        if (!$stmt) {
            die("Prepare Failed: " . $conn->error);
        }

        $stmt->bind_param("s", $email);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows == 1) {

            $client = $result->fetch_assoc();

            if (password_verify($password, $client['password'])) {

                $_SESSION['client_id'] = $client['id'];
                $_SESSION['client_name'] = $client['name'];
                $_SESSION['client_email'] = $client['email'];

                header("Location: client_dashboard.php");
                exit();

            } else {

                $error = "Invalid email or password.";
            }

        } else {

            $error = "Invalid email or password.";
        }

        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Client Login - Grand Superior Drycleaners</title>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">

<style>
*{
    margin:0;
    padding:0;
    box-sizing:border-box;
}

body{
    font-family:'Poppins',sans-serif;
    background:#f5f7fa;
    display:flex;
    justify-content:center;
    align-items:center;
    min-height:100vh;
}

.login-container{
    background:#fff;
    width:400px;
    padding:40px;
    border-radius:12px;
    box-shadow:0 8px 20px rgba(0,0,0,0.1);
}

.login-container h2{
    text-align:center;
    margin-bottom:20px;
    color:#0d1b2a;
}

.login-container input{
    width:100%;
    padding:12px;
    margin-bottom:15px;
    border:1px solid #ddd;
    border-radius:8px;
    outline:none;
}

.login-container button{
    width:100%;
    padding:12px;
    border:none;
    border-radius:8px;
    background:#a6ce39;
    color:#000;
    font-weight:600;
    cursor:pointer;
}

.login-container button:hover{
    background:#8bb32f;
}

.login-container p{
    text-align:center;
    margin-top:15px;
}

.login-container a{
    text-decoration:none;
    color:#0d1b2a;
    font-weight:600;
}

.error{
    color:red;
    text-align:center;
    margin-bottom:15px;
}
</style>
</head>

<body>

<div class="login-container">

    <h2>Client Login</h2>

    <?php if(!empty($error)){ ?>
        <div class="error"><?php echo $error; ?></div>
    <?php } ?>

    <form method="POST">

        <input
            type="email"
            name="email"
            placeholder="Enter Email"
            required
        >

        <input
            type="password"
            name="password"
            placeholder="Enter Password"
            required
        >

        <button type="submit">Login</button>

    </form>

    <p>
        Don't have an account?
        <a href="client_register.php">Register</a>
    </p>

</div>

</body>
</html>

