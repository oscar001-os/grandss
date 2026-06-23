<?php
// ── TEMPORARY DEBUG FILE — DELETE AFTER FIXING ──
// Upload this as debug_bookings.php and visit it in your browser.
// It will tell you exactly what is wrong.
 
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
 
echo "<h2>Step 1: PHP is working</h2>";
echo "PHP version: " . phpversion() . "<br>";
 
// Check mysqli exists
if (!extension_loaded('mysqli')) {
    die("<b style='color:red'>FAIL: mysqli extension is NOT loaded. Contact InfinityFree support.</b>");
}
echo "<h2>Step 2: mysqli extension OK</h2>";
 
// Check mysqlnd (needed for get_result)
if (extension_loaded('mysqlnd')) {
    echo "mysqlnd: YES (get_result is available)<br>";
} else {
    echo "<b style='color:orange'>mysqlnd: NO — get_result() will NOT work. Using bind_result() is required.</b><br>";
}
 
// Try connection
$servername = "sql304.infinityfree.com";
$username   = "if0_42165071";
$password   = "4LV17q1xpxLO";
$database   = "if0_42165071_grand";
 
$conn = new mysqli($servername, $username, $password, $database);
 
if ($conn->connect_error) {
    die("<b style='color:red'>FAIL: DB Connection failed: " . $conn->connect_error . "</b>");
}
echo "<h2>Step 3: Database connected OK</h2>";
 
// Check bookings table
$r = $conn->query("SHOW COLUMNS FROM bookings");
if (!$r) {
    die("<b style='color:red'>FAIL: bookings table missing or error: " . $conn->error . "</b>");
}
echo "<h2>Step 4: bookings table columns</h2><ul>";
while ($col = $r->fetch_assoc()) {
    echo "<li>" . htmlspecialchars($col['Field']) . " — " . htmlspecialchars($col['Type']) . "</li>";
}
echo "</ul>";
 
// Check payments table
$r2 = $conn->query("SHOW COLUMNS FROM payments");
if (!$r2) {
    die("<b style='color:red'>FAIL: payments table missing or error: " . $conn->error . "</b>");
}
echo "<h2>Step 5: payments table columns</h2><ul>";
while ($col = $r2->fetch_assoc()) {
    echo "<li>" . htmlspecialchars($col['Field']) . " — " . htmlspecialchars($col['Type']) . "</li>";
}
echo "</ul>";
 
// Check session
session_start();
echo "<h2>Step 6: Session</h2>";
if (isset($_SESSION['client_id'])) {
    echo "client_id = " . (int)$_SESSION['client_id'] . "<br>";
} else {
    echo "<b style='color:orange'>WARNING: No client_id in session (you may not be logged in — this is normal if testing directly).</b><br>";
    $_SESSION['client_id'] = 1; // fake for test
    echo "Using fake client_id = 1 for query test.<br>";
}
 
$client_id = (int)$_SESSION['client_id'];
 
// Test bookings query with bind_result
echo "<h2>Step 7: Test bookings query</h2>";
$stmt = $conn->prepare("SELECT id, service_type, pickup_date, delivery_date, address, notes, status, created_at FROM bookings WHERE client_id = ? ORDER BY created_at DESC");
if (!$stmt) {
    die("<b style='color:red'>FAIL: prepare() failed: " . $conn->error . "</b>");
}
$stmt->bind_param("i", $client_id);
$stmt->execute();
$stmt->bind_result($b_id, $b_service, $b_pickup, $b_delivery, $b_address, $b_notes, $b_status, $b_created);
$count = 0;
while ($stmt->fetch()) { $count++; }
$stmt->close();
echo "Bookings found for client_id=$client_id: <b>$count</b><br>";
 
// Test payments query with bind_result
echo "<h2>Step 8: Test payments query</h2>";
$stmt2 = $conn->prepare("SELECT id, booking_id, amount, status FROM payments WHERE client_id = ?");
if (!$stmt2) {
    die("<b style='color:red'>FAIL: payments prepare() failed: " . $conn->error . "</b>");
}
$stmt2->bind_param("i", $client_id);
$stmt2->execute();
$stmt2->bind_result($p_id, $p_booking_id, $p_amount, $p_status);
$pcount = 0;
while ($stmt2->fetch()) { $pcount++; }
$stmt2->close();
echo "Payments found for client_id=$client_id: <b>$pcount</b><br>";
 
$conn->close();
 
echo "<h2 style='color:green'>✔ All checks passed — the main script should work.</h2>";
echo "<p><b>Delete this file immediately after debugging!</b></p>";
?>