<?php
session_start();

/* =========================
   CLEAR RIDER SESSION
========================= */
unset($_SESSION['rider_id']);
unset($_SESSION['rider_name']);

/* OPTIONAL: destroy everything */
session_unset();
session_destroy();

/* =========================
   REDIRECT TO LOGIN
========================= */
header("Location: rider_login.php");
exit();
?>