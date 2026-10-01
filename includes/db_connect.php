<?php
// ============================================================
// Database connection file — used by every page that needs
// to talk to the database. Just include this at the top.
// ============================================================

$host = "localhost";
$username = "root";       // default XAMPP MySQL username
$password = "";           // default XAMPP MySQL password (blank)
$database = "ummul_bannin_madrasah";

$conn = new mysqli($host, $username, $password, $database);

// Stop everything with a clear message if connection fails
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
