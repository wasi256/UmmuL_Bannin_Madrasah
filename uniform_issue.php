<?php
include 'auth_check.php';
include 'db_connect.php';
 
$student = null;
$message = "";
$messageType = "";
$search_term = "";
$multiple_matches = [];
$applicable_items = [];
$past_issues = [];
 
// ------------------------------------------------------------
// Handle student search (by admission number OR name)
// ------------------------------------------------------------
if (isset($_GET['admission_number']) && trim($_GET['admission_number']) !== "") {
    $adm = trim($_GET['admission_number']);
    $stmt = $conn->prepare("SELECT s.*, c.class_name, c.section FROM students s 
                             JOIN classes c ON s.class_id = c.class_id 
                             WHERE s.admission_number = ?");
    $stmt->bind_param("s", $adm);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $student = $result->fetch_assoc();
    }
    $stmt->close();
 
} elseif (isset($_GET['search']) && trim($_GET['search']) !== "") {
    $search_term = trim($_GET['search']);
    $like_term = "%" . $search_term . "%";
 
    $stmt = $conn->prepare("SELECT s.*, c.class_name, c.section FROM students s 
                             JOIN classes c ON s.class_id = c.class_id 
                             WHERE s.admission_number = ? OR s.full_name LIKE ?");
    $stmt->bind_param("ss", $search_term, $like_term);
    $stmt->execute();
    $result = $stmt->get_result();
 
    if ($result->num_rows === 1) {
        $student = $result->fetch_assoc();
    } elseif ($result->num_rows > 1) {
        while ($row = $result->fetch_assoc()) {
            $multiple_matches[] = $row;
        }
    } else {
        $message = "No student found matching: " . htmlspecialchars($search_term);
        $messageType = "error";
    }
    $stmt->close();
}
 
// ------------------------------------------------------------
// Handle issuing a uniform (form submission)
// ------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['student_id'])) {
 
    $student_id   = $_POST['student_id'];
    $uniform_id   = $_POST['uniform_id'];
    $quantity     = (int)$_POST['quantity'];
    $amount_paid  = $_POST['amount_paid'];
    $adm_reload   = $_POST['admission_number'];
 
    // Get the item price and stock
    $stmt = $conn->prepare("SELECT price, quantity_in_stock, item_name FROM uniform_items WHERE uniform_id = ?");
    $stmt->bind_param("i", $uniform_id);
    $stmt->execute();
    $item = $stmt->get_result()->fetch_assoc();
    $stmt->close();
 
    if (!$item) {
        $message = "Selected uniform item not found.";
        $messageType = "error";
    } elseif ($quantity < 1) {
        $message = "Quantity must be at least 1.";
        $messageType = "error";
    } elseif ($quantity > $item['quantity_in_stock']) {
        $message = "Not enough stock. Only " . $item['quantity_in_stock'] . " left for " . htmlspecialchars($item['item_name']) . ".";
        $messageType = "error";
    } else {
        $total_price = $item['price'] * $quantity;
 
        $stmt = $conn->prepare("INSERT INTO uniform_issues 
            (student_id, uniform_id, quantity, total_price, amount_paid) 
            VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iiidd", $student_id, $uniform_id, $quantity, $total_price, $amount_paid);
 
        if ($stmt->execute()) {
            // Reduce stock
            $update = $conn->prepare("UPDATE uniform_items SET quantity_in_stock = quantity_in_stock - ? WHERE uniform_id = ?");
            $update->bind_param("ii", $quantity, $uniform_id);
            $update->execute();
            $update->close();
 
            $message = "Issued " . $quantity . " x " . htmlspecialchars($item['item_name']) . " successfully!";
            $messageType = "success";
        } else {
            $message = "Error saving record: " . $stmt->error;
            $messageType = "error";
        }
        $stmt->close();
    }
 
    // Reload student after submission
    $stmt = $conn->prepare("SELECT s.*, c.class_name, c.section FROM students s 
                             JOIN classes c ON s.class_id = c.class_id 
                             WHERE s.admission_number = ?");
    $stmt->bind_param("s", $adm_reload);
    $stmt->execute();
    $result = $stmt->get_result();
    $student = $result->fetch_assoc();
    $stmt->close();
}
 
// ------------------------------------------------------------
// If we have a student, get applicable uniform items + past issues
// ------------------------------------------------------------
if ($student) {
 
    $stmt = $conn->prepare("SELECT * FROM uniform_items 
                             WHERE (applicable_section = ? OR applicable_section = 'All') 
                             AND (applicable_gender = ? OR applicable_gender = 'All')
                             ORDER BY item_name");
    $stmt->bind_param("ss", $student['section'], $student['gender']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $applicable_items[] = $row;
    }
    $stmt->close();
 
    $stmt = $conn->prepare("SELECT ui.*, u.item_name 
                             FROM uniform_issues ui 
                             JOIN uniform_items u ON ui.uniform_id = u.uniform_id 
                             WHERE ui.student_id = ? 
                             ORDER BY ui.date_issued DESC");
    $stmt->bind_param("i", $student['student_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $past_issues[] = $row;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Uniform Issuing - Ummul Bannin Madrasah</title>
<style>
    * { box-sizing: border-box; }
    body {
        font-family: Arial, Helvetica, sans-serif;
        background-color: #f4f6f5;
        margin: 0;
        padding: 30px;
    }
    .container {
        max-width: 700px;
        margin: 0 auto;
        background: #ffffff;
        padding: 30px 35px;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }
    .logo {
        display: block;
        margin: 0 auto 15px auto;
        width: 150px;
        height: 150px;
        object-fit: contain;
    }
    h1 {
        color: #1b5e20;
        text-align: center;
        margin-bottom: 5px;
        font-size: 24px;
    }
    .subtitle {
        text-align: center;
        color: #666;
        margin-bottom: 25px;
        font-size: 14px;
    }
    label {
        display: block;
        margin-top: 15px;
        margin-bottom: 5px;
        font-weight: bold;
        color: #333;
        font-size: 14px;
    }
    input[type="text"],
    input[type="number"],
    select {
        width: 100%;
        padding: 10px;
        border: 1px solid #ccc;
        border-radius: 5px;
        font-size: 14px;
    }
    button {
        padding: 12px 20px;
        margin-top: 15px;
        background-color: #1b5e20;
        color: white;
        border: none;
        border-radius: 5px;
        font-size: 15px;
        cursor: pointer;
    }
    button:hover { background-color: #164a1a; }
    .message {
        padding: 12px;
        border-radius: 5px;
        margin-bottom: 15px;
        font-size: 14px;
    }
    .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    .error   { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
 
    .search-row {
        display: flex;
        gap: 10px;
        align-items: flex-end;
    }
    .search-row input { flex: 1; }
    .search-row button { margin-top: 0; white-space: nowrap; }
 
    .student-card {
        margin-top: 25px;
        padding: 18px;
        background-color: #f0f7f0;
        border: 1px solid #cde4cd;
        border-radius: 6px;
    }
    .student-card h3 { margin-top: 0; color: #1b5e20; }
    .info-row {
        display: flex;
        justify-content: space-between;
        padding: 6px 0;
        border-bottom: 1px solid #e0e0e0;
        font-size: 14px;
    }
 
    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 15px;
        font-size: 13px;
    }
    th, td {
        text-align: left;
        padding: 8px;
        border-bottom: 1px solid #e0e0e0;
    }
    th { background-color: #f0f7f0; color: #1b5e20; }
</style>
</head>
<body>
 
<div class="container">
        <a href="dashboard.php" class="back-link">&larr; Back to Dashboard</a>
    <img src="logo.png" alt="Ummul Bannin Madrasah Badge" class="logo">
    <h1>Ummul Bannin Madrasah</h1>
    <p class="subtitle">Uniform Issuing</p>
 
    <?php if ($message): ?>
        <div class="message <?php echo $messageType; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>
 
    <!-- Search form -->
    <form method="GET" action="">
        <label for="search">Search Student by Name or Admission Number</label>
        <div class="search-row">
            <input type="text" id="search" name="search" 
                   value="<?php echo htmlspecialchars($search_term); ?>" placeholder="e.g. Amina or UB-001" required>
            <button type="submit">Search</button>
        </div>
    </form>
 
    <?php if (!empty($multiple_matches)): ?>
        <div class="student-card">
            <h3>Multiple students found — select one:</h3>
            <?php foreach ($multiple_matches as $match): ?>
                <div class="info-row">
                    <span>
                        <?php echo htmlspecialchars($match['full_name']); ?> 
                        (<?php echo htmlspecialchars($match['class_name']); ?>, 
                        Adm#: <?php echo htmlspecialchars($match['admission_number']); ?>)
                    </span>
                    <a href="?admission_number=<?php echo urlencode($match['admission_number']); ?>">
                        <button type="button">Select</button>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
 
    <?php if ($student): ?>
        <div class="student-card">
            <h3><?php echo htmlspecialchars($student['full_name']); ?></h3>
            <div class="info-row">
                <span>Admission Number</span>
                <span><?php echo htmlspecialchars($student['admission_number']); ?></span>
            </div>
            <div class="info-row">
                <span>Class</span>
                <span><?php echo htmlspecialchars($student['class_name'] . " (" . $student['section'] . ")"); ?></span>
            </div>
            <div class="info-row">
                <span>Gender</span>
                <span><?php echo htmlspecialchars($student['gender']); ?></span>
            </div>
        </div>
 
        <?php if (!empty($applicable_items)): ?>
        <!-- Issue uniform form -->
        <form method="POST" action="">
            <input type="hidden" name="student_id" value="<?php echo $student['student_id']; ?>">
            <input type="hidden" name="admission_number" value="<?php echo htmlspecialchars($student['admission_number']); ?>">
 
            <label for="uniform_id">Uniform Item</label>
            <select id="uniform_id" name="uniform_id" required>
                <option value="">-- Select Item --</option>
                <?php foreach ($applicable_items as $item): ?>
                    <option value="<?php echo $item['uniform_id']; ?>">
                        <?php echo htmlspecialchars($item['item_name']); ?> 
                        — UGX <?php echo number_format($item['price']); ?> 
                        (<?php echo $item['quantity_in_stock']; ?> in stock)
                    </option>
                <?php endforeach; ?>
            </select>
 
            <label for="quantity">Quantity</label>
            <input type="number" id="quantity" name="quantity" min="1" value="1" required>
 
            <label for="amount_paid">Amount Paid Now (UGX)</label>
            <input type="number" id="amount_paid" name="amount_paid" min="0" required>
 
            <button type="submit">Issue Uniform</button>
        </form>
        <?php else: ?>
            <div class="message error" style="margin-top:20px;">
                No uniform items are set up for this student's section/gender yet. Check the uniform_items table.
            </div>
        <?php endif; ?>
 
        <?php if (!empty($past_issues)): ?>
        <h3 style="margin-top:30px; color:#1b5e20;">Uniform History</h3>
        <table>
            <tr>
                <th>Date</th>
                <th>Item</th>
                <th>Qty</th>
                <th>Total Price</th>
                <th>Paid</th>
                <th>Balance</th>
            </tr>
            <?php foreach ($past_issues as $issue): ?>
                <tr>
                    <td><?php echo htmlspecialchars($issue['date_issued']); ?></td>
                    <td><?php echo htmlspecialchars($issue['item_name']); ?></td>
                    <td><?php echo $issue['quantity']; ?></td>
                    <td><?php echo number_format($issue['total_price']); ?></td>
                    <td><?php echo number_format($issue['amount_paid']); ?></td>
                    <td><?php echo number_format($issue['total_price'] - $issue['amount_paid']); ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
        <?php endif; ?>
 
    <?php endif; ?>
 
</div>
 
</body>
</html>