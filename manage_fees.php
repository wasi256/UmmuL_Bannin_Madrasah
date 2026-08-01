<?php

include 'auth_check.php';
include 'db_connect.php';

$message = "";
$messageType = "";

// ensure fee_defaults table exists
$conn->query("CREATE TABLE IF NOT EXISTS fee_defaults (
    fee_key VARCHAR(50) PRIMARY KEY,
    label VARCHAR(100) NOT NULL,
    amount INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// insert default rows if missing
$defaults = [
    ['boarding', 'Boarding', 350000],
    ['upper_primary', 'Upper Primary', 90000],
    ['lower_primary', 'Lower Primary', 90000],
    ['nursery', 'Nursery', 80000]
];

$stmtCheck = $conn->prepare("SELECT COUNT(*) FROM fee_defaults WHERE fee_key = ?");
$stmtInsert = $conn->prepare("INSERT INTO fee_defaults (fee_key, label, amount) VALUES (?, ?, ?)");
foreach ($defaults as $d) {
    $stmtCheck->bind_param("s", $d[0]);
    $stmtCheck->execute();
    $stmtCheck->bind_result($count);
    $stmtCheck->fetch();
    $stmtCheck->free_result();

    if ($count == 0) {
        $stmtInsert->bind_param("ssi", $d[0], $d[1], $d[2]);
        $stmtInsert->execute();
    }
}
$stmtCheck->close();
$stmtInsert->close();

// handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_fee') {
    $fee_key = $_POST['fee_key'] ?? '';
    $amount = (int)($_POST['amount'] ?? 0);

    if ($fee_key === '' || $amount < 0) {
        $message = "Invalid fee or amount.";
        $messageType = "error";
    } else {
        $stmt = $conn->prepare("UPDATE fee_defaults SET amount = ? WHERE fee_key = ?");
        $stmt->bind_param("is", $amount, $fee_key);
        if ($stmt->execute()) {
            $message = "Fee updated.";
            $messageType = "success";
        } else {
            $message = "Error updating fee: " . $stmt->error;
            $messageType = "error";
        }
        $stmt->close();
    }
}

// fetch fees
$fees = [];
$result = $conn->query("SELECT * FROM fee_defaults ORDER BY label");
while ($row = $result->fetch_assoc()) {
    $fees[] = $row;
}

$editing = isset($_GET['edit']) ? $_GET['edit'] : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Manage Fees - Ummul Bannin Madrasah</title>
<style>
body{font-family:Arial;background:#f4f6f5;padding:30px}
.container{max-width:800px;margin:0 auto;background:#fff;padding:24px;border-radius:8px}
table{width:100%;border-collapse:collapse;margin-top:12px}
th,td{padding:10px;border-bottom:1px solid #eee;text-align:left}
button{padding:8px 12px;background:#1b5e20;color:#fff;border:none;border-radius:4px;cursor:pointer}
.input-small{width:140px;padding:8px}
.message{padding:10px;border-radius:6px;margin-bottom:12px}
.success{background:#d4edda;color:#155724}
.error{background:#f8d7da;color:#721c24}
a.button{display:inline-block;padding:8px 12px;background:#6c757d;color:#fff;border-radius:4px;text-decoration:none}
</style>
</head>
<body>
<div class="container">
    <a href="dashboard.php" class="button">&larr; Back to Dashboard</a>
    <h2>Default Fees</h2>
    <?php if ($message): ?>
        <div class="message <?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <table>
        <tr><th>Fee</th><th>Amount (UGX)</th><th>Action</th></tr>
        <?php foreach ($fees as $fee): ?>
            <?php if ($editing === $fee['fee_key']): ?>
                <tr>
                    <td><?php echo htmlspecialchars($fee['label']); ?></td>
                    <td>
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="update_fee">
                            <input type="hidden" name="fee_key" value="<?php echo htmlspecialchars($fee['fee_key']); ?>">
                            <input class="input-small" type="number" name="amount" min="0" value="<?php echo (int)$fee['amount']; ?>" required>
                    </td>
                    <td>
                            <button type="submit">Save</button>
                            <a href="manage_fees.php" class="button" style="background:#999;margin-left:8px">Cancel</a>
                        </form>
                    </td>
                </tr>
            <?php else: ?>
                <tr>
                    <td><?php echo htmlspecialchars($fee['label']); ?></td>
                    <td>UGX <?php echo number_format($fee['amount']); ?></td>
                    <td><a href="?edit=<?php echo urlencode($fee['fee_key']); ?>" class="button">Edit</a></td>
                </tr>
            <?php endif; ?>
        <?php endforeach; ?>
    </table>
</div>
</body>
</html>