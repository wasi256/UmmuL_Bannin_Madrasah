<?php
session_start();
include 'db_connect.php';

$message = "";
$messageType = "";

// If already logged in, go straight to dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        if (password_verify($password, $user['password_hash'])) {
            // Correct password - start session
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];

            header("Location: dashboard.php");
            exit();
        } else {
            $message = "Incorrect username or password.";
            $messageType = "error";
        }
    } else {
        $message = "Incorrect username or password.";
        $messageType = "error";
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Login - Ummul Bannin Madrasah</title>
<style>
    * { box-sizing: border-box; }
    body {
        font-family: Arial, Helvetica, sans-serif;
        background-color: #f4f6f5;
        margin: 0;
        padding: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 90vh;
    }
    .container {
        max-width: 400px;
        width: 100%;
        background: #ffffff;
        padding: 30px 35px;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }
    .logo {
        display: block;
        margin: 0 auto 15px auto;
        width: 130px;
        height: 130px;
        object-fit: contain;
    }
    h1 { color: #1b5e20; text-align: center; font-size: 22px; margin-bottom: 5px; }
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
    .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
</style>
</head>
<body>
<div class="container">
    <img src="logo.png" alt="Ummul Bannin Madrasah Badge" class="logo">
    <h1>Ummul Bannin Madrasah</h1>
    <p class="subtitle">Staff Login</p>

    <?php if ($message): ?>
        <div class="message <?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" required>

        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>

        <button type="submit">Login</button>
    </form>
</div>
</body>
</html>
