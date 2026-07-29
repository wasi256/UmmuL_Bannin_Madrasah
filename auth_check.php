<?php
// ============================================================
// Include this file at the very top of any page that should
// only be accessible to logged-in staff.
// Example: include 'auth_check.php';
// ============================================================

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
?>
