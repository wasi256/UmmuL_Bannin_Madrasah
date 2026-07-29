<?php
include 'db_connect.php';

$message = "";
$messageType = "";

// ------------------------------------------------------------
// Safety check: only allow this page to run if no admin exists yet.
// Prevents someone from creating extra accounts later via this file.
// ------------------------------------------------------------
$check = $conn->query("SELECT COUNT(*) AS total FROM users");
$row = $check->fetch_assoc();
$already_has_users = $row['total'] > 0;

if ($_SERVER["REQUEST_METHOD"] === "POST" && !$already_has_users) {

    $username = trim($_POST['username']);
    $full_name = trim($_POST['full_name']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $message = "Please fill in all fields.";
        $messageType = "error";
    } else {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("INSERT INTO users (username, password_hash, full_name, role) VALUES (?, ?, ?, 'Admin')");
        $stmt->bind_param("sss", $username, $password_hash, $full_name);

        if ($stmt->execute()) {
            $message = "Admin account created successfully! You can now delete this file (create_admin.php) for security, and go to login.php to sign in.";
            $messageType = "success";
            $already_has_users = true;
        } else {
            $message = "Error creating account: " . $stmt->error;
            $messageType = "error";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Create Admin Account - Ummul Bannin Madrasah</title>
<style>
    * { box-sizing: border-box; }
    body {
        font-family: Arial, Helvetica, sans-serif;
        background-color: #f4f6f5;
        margin: 0;
        padding: 30px;
    }
    .container {
        max-width: 450px;
        margin: 0 auto;
        background: #ffffff;
        padding: 30px 35px;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }
    h1 { color: #1b5e20; text-align: center; font-size: 22px; }
    .subtitle { text-align: center; color: #666; margin-bottom: 25px; font-size: 14px; }
    label { display: block; margin-top: 15px; margin-bottom: 5px; font-weight: bold; color: #333; font-size: 14px; }
    input[type="text"], input[type="password"] {
        width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; font-size: 14px;
    }
    button {
        width: 100%; padding: 12px; margin-top: 20px;
        background-color: #1b5e20; color: white; border: none; border-radius: 5px;
        font-size: 15px; cursor: pointer;
    }
    button:hover { background-color: #164a1a; }
    .message { padding: 12px; border-radius: 5px; margin-bottom: 15px; font-size: 14px; }
    .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    .error   { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    a { color: #1b5e20; }
</style>
</head>
<body>
<div class="container">
    <h1>Ummul Bannin Madrasah</h1>
    <p class="subtitle">One-Time Admin Setup</p>

    <?php if ($message): ?>
        <div class="message <?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <?php if ($already_has_users): ?>
        <p>An account already exists. This setup page is now locked for security.</p>
        <p><a href="login.php">Go to Login</a></p>
    <?php else: ?>
        <form method="POST" action="">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" required>

            <label for="full_name">Full Name</label>
            <input type="text" id="full_name" name="full_name" required>

            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>

            <button type="submit">Create Admin Account</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
