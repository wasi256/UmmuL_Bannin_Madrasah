<?php
include 'auth_check.php';
include 'db_connect.php';

// ------------------------------------------------------------
// Only Admins can access this page
// ------------------------------------------------------------
if ($_SESSION['role'] !== 'Admin') {
    header("Location: dashboard.php");
    exit();
}

$message = "";
$messageType = "";

// ------------------------------------------------------------
// Add a new staff account
// ------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === "add_user") {
    $username = trim($_POST['username']);
    $full_name = trim($_POST['full_name']);
    $password = $_POST['password'];
    $role = $_POST['role'];

    if (empty($username) || empty($password)) {
        $message = "Username and password are required.";
        $messageType = "error";
    } else {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (username, password_hash, full_name, role) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $username, $password_hash, $full_name, $role);

        if ($stmt->execute()) {
            $message = "Staff account created successfully.";
            $messageType = "success";
        } else {
            if ($conn->errno === 1062) {
                $message = "That username is already taken.";
            } else {
                $message = "Error creating account: " . $stmt->error;
            }
            $messageType = "error";
        }
        $stmt->close();
    }
}

// ------------------------------------------------------------
// Delete a staff account (can't delete yourself)
// ------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === "delete_user") {
    $user_id = $_POST['user_id'];

    if ($user_id == $_SESSION['user_id']) {
        $message = "You can't delete your own account while logged in as it.";
        $messageType = "error";
    } else {
        $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            $message = "Staff account deleted.";
            $messageType = "success";
        } else {
            $message = "Error deleting account: " . $stmt->error;
            $messageType = "error";
        }
        $stmt->close();
    }
}

// ------------------------------------------------------------
// Fetch all staff
// ------------------------------------------------------------
$users = [];
$result = $conn->query("SELECT * FROM users ORDER BY role, username");
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage Staff Accounts - Ummul Bannin Madrasah</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
    :root {
        --emerald: #14532d; --parchment: #faf6ee; --gold: #b8860b;
        --ink: #2b2b2b; --sage: #e3ede3; --sage-border: #cddccd; --white: #ffffff;
    }
    * { box-sizing: border-box; }
    body { font-family: 'Inter', sans-serif; background-color: var(--parchment); color: var(--ink); margin: 0; padding: 30px; }
    .container { max-width: 750px; margin: 0 auto; background: var(--white); padding: 30px 35px; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
    h1 { font-family: 'Amiri', serif; color: var(--emerald); font-size: 24px; margin-bottom: 5px; }
    .subtitle { color: #666; margin-bottom: 20px; font-size: 14px; }
    .back-link { font-size: 13px; margin-bottom: 20px; display: inline-block; color: var(--emerald); }
    .section-title { font-family: 'Amiri', serif; font-size: 18px; color: var(--emerald); margin-top: 30px; margin-bottom: 12px; }
    label { display: block; margin-top: 12px; margin-bottom: 5px; font-weight: 600; font-size: 13px; }
    input[type="text"], input[type="password"], select { width: 100%; padding: 9px; border: 1px solid #ccc; border-radius: 5px; font-size: 14px; }
    button { padding: 10px 18px; background-color: var(--emerald); color: white; border: none; border-radius: 5px; font-size: 14px; cursor: pointer; }
    button:hover { background-color: #0d3a1f; }
    .message { padding: 12px; border-radius: 5px; margin-bottom: 15px; font-size: 14px; }
    .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    table { width: 100%; border-collapse: collapse; font-size: 14px; margin-top: 10px; }
    th, td { text-align: left; padding: 10px 8px; border-bottom: 1px solid #eee; }
    th { background-color: var(--sage); color: var(--emerald); font-size: 12px; text-transform: uppercase; }
    .role-badge { padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; }
    .role-Admin { background-color: #fde8d0; color: #8a5a00; }
    .role-Accountant { background-color: #d4edda; color: #155724; }
    .role-Teacher { background-color: #d1ecf1; color: #0c5460; }
    .btn-small { padding: 6px 12px; font-size: 12px; background-color: #c0392b; }
    .btn-small:hover { background-color: #a5281c; }
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0 15px; }
    .you-tag { font-size: 11px; color: #888; }
</style>
</head>
<body>
<div class="container">
    <a href="dashboard.php" class="back-link">&larr; Back to Dashboard</a>
    <h1>Manage Staff Accounts</h1>
    <p class="subtitle">Add or remove staff who can log in to the system</p>

    <?php if ($message): ?>
        <div class="message <?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <div class="section-title">Add New Staff Account</div>
    <form method="POST" action="">
        <input type="hidden" name="action" value="add_user">
        <div class="form-grid">
            <div>
                <label>Username</label>
                <input type="text" name="username" required>
            </div>
            <div>
                <label>Full Name</label>
                <input type="text" name="full_name" required>
            </div>
            <div>
                <label>Password</label>
                <input type="password" name="password" required>
            </div>
            <div>
                <label>Role</label>
                <select name="role" required>
                    <option value="Accountant">Accountant</option>
                    <option value="Teacher">Teacher</option>
                    <option value="Admin">Admin</option>
                </select>
            </div>
        </div>
        <button type="submit" style="margin-top:18px;">Create Account</button>
    </form>

    <div class="section-title">Current Staff</div>
    <table>
        <tr>
            <th>Username</th>
            <th>Full Name</th>
            <th>Role</th>
            <th>Action</th>
        </tr>
        <?php foreach ($users as $u): ?>
        <tr>
            <td><?php echo htmlspecialchars($u['username']); ?> <?php if ($u['user_id'] == $_SESSION['user_id']) echo '<span class="you-tag">(you)</span>'; ?></td>
            <td><?php echo htmlspecialchars($u['full_name']); ?></td>
            <td><span class="role-badge role-<?php echo $u['role']; ?>"><?php echo htmlspecialchars($u['role']); ?></span></td>
            <td>
                <?php if ($u['user_id'] != $_SESSION['user_id']): ?>
                <form method="POST" action="" onsubmit="return confirm('Remove staff account: <?php echo htmlspecialchars(addslashes($u['username'])); ?>? They will no longer be able to log in.');">
                    <input type="hidden" name="action" value="delete_user">
                    <input type="hidden" name="user_id" value="<?php echo $u['user_id']; ?>">
                    <button type="submit" class="btn-small">Remove</button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
</body>
</html>
