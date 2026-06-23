<?php
// Database connection settings
$servername = "sql304.infinityfree.com";   // MySQL Hostname
$username   = "if0_42165071";              // MySQL Username
$password   = "4LV17q1xpxLO";              // MySQL Password
$database   = "if0_42165071_grand";        // Database Name

// Create connection
$conn = new mysqli($servername, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
} else {
    // Optional: uncomment for debugging
    // echo "Connected successfully to the database!";
}
?>
