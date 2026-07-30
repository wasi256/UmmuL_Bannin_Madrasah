<?php
include 'auth_check.php';
include 'db_connect.php';

$message = "";
$messageType = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Fetch the current user's stored hash
    $stmt = $conn->prepare("SELECT password_hash FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user || !password_verify($current_password, $user['password_hash'])) {
        $message = "Your current password is incorrect.";
        $messageType = "error";
    } elseif (strlen($new_password) < 6) {
        $message = "New password must be at least 6 characters.";
        $messageType = "error";
    } elseif ($new_password !== $confirm_password) {
        $message = "New password and confirmation do not match.";
        $messageType = "error";
    } else {
        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
        $stmt->bind_param("si", $new_hash, $_SESSION['user_id']);
        if ($stmt->execute()) {
            $message = "Password changed successfully. Use your new password next time you log in.";
            $messageType = "success";
        } else {
            $message = "Error updating password: " . $stmt->error;
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
<title>Change Password - Ummul Bannin Madrasah</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
    :root {
        --emerald: #14532d; --parchment: #faf6ee; --gold: #b8860b;
        --ink: #2b2b2b; --sage: #e3ede3; --sage-border: #cddccd; --white: #ffffff;
    }
    * { box-sizing: border-box; }
    body { font-family: 'Inter', sans-serif; background-color: var(--parchment); color: var(--ink); margin: 0; padding: 30px; }
    .container { max-width: 420px; margin: 0 auto; background: var(--white); padding: 30px 35px; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
    h1 { font-family: 'Amiri', serif; color: var(--emerald); font-size: 22px; margin-bottom: 5px; }
    .subtitle { color: #666; margin-bottom: 20px; font-size: 14px; }
    .back-link { font-size: 13px; margin-bottom: 20px; display: inline-block; color: var(--emerald); }
    label { display: block; margin-top: 15px; margin-bottom: 5px; font-weight: 600; font-size: 13px; }
    input[type="password"] { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; font-size: 14px; }
    button { width: 100%; padding: 12px; margin-top: 20px; background-color: var(--emerald); color: white; border: none; border-radius: 5px; font-size: 15px; cursor: pointer; }
    button:hover { background-color: #0d3a1f; }
    .message { padding: 12px; border-radius: 5px; margin-bottom: 15px; font-size: 14px; }
    .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    .hint { font-size: 12px; color: #888; margin-top: 4px; }
</style>
</head>
<body>
<div class="container">
    <a href="dashboard.php" class="back-link">&larr; Back to Dashboard</a>
    <h1>Change Password</h1>
    <p class="subtitle">Logged in as <?php echo htmlspecialchars($_SESSION['username']); ?></p>

    <?php if ($message): ?>
        <div class="message <?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <label>Current Password</label>
        <input type="password" name="current_password" required>

        <label>New Password</label>
        <input type="password" name="new_password" required>
        <div class="hint">At least 6 characters</div>

        <label>Confirm New Password</label>
        <input type="password" name="confirm_password" required>

        <button type="submit">Update Password</button>
    </form>
</div>
</body>
</html>
