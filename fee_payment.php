<?php
include 'auth_check.php';
include 'db_connect.php';

$student = null;
$total_due = 0;
$total_paid = 0;
$balance = 0;
$message = "";
$messageType = "";
$current_term = null;

// ------------------------------------------------------------
// Get the current academic term
// ------------------------------------------------------------
$term_result = $conn->query("SELECT * FROM academic_terms WHERE is_current = 1 LIMIT 1");
if ($term_result && $term_result->num_rows > 0) {
    $current_term = $term_result->fetch_assoc();
}

// ------------------------------------------------------------
// Handle student search (by admission number OR name)
// ------------------------------------------------------------
$search_admission = "";
$search_term = "";
$multiple_matches = [];

// Direct lookup - used when a specific student was picked from a name search
if (isset($_GET['admission_number']) && trim($_GET['admission_number']) !== "") {
    $search_admission = trim($_GET['admission_number']);

    $stmt = $conn->prepare("SELECT s.*, c.class_name, c.section, c.term_fee 
                             FROM students s 
                             JOIN classes c ON s.class_id = c.class_id 
                             WHERE s.admission_number = ?");
    $stmt->bind_param("s", $search_admission);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $student = $result->fetch_assoc();
    } else {
        $message = "No student found with admission number: " . htmlspecialchars($search_admission);
        $messageType = "error";
    }
    $stmt->close();

// General search - matches admission number OR name
} elseif (isset($_GET['search']) && trim($_GET['search']) !== "") {
    $search_term = trim($_GET['search']);
    $like_term = "%" . $search_term . "%";

    $stmt = $conn->prepare("SELECT s.*, c.class_name, c.section, c.term_fee 
                             FROM students s 
                             JOIN classes c ON s.class_id = c.class_id 
                             WHERE s.admission_number = ? OR s.full_name LIKE ?");
    $stmt->bind_param("ss", $search_term, $like_term);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        // Exactly one match - go straight to their record
        $student = $result->fetch_assoc();
        $search_admission = $student['admission_number'];
    } elseif ($result->num_rows > 1) {
        // Multiple matches - let the user pick
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
// Handle payment submission
// ------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['student_id'])) {

    $student_id     = $_POST['student_id'];
    $amount_paid    = $_POST['amount_paid'];
    $payment_method = $_POST['payment_method'];
    $received_by    = trim($_POST['received_by']);
    $term_id        = $_POST['term_id'];

    if (empty($amount_paid) || $amount_paid <= 0) {
        $message = "Please enter a valid payment amount.";
        $messageType = "error";
    } else {

        // Generate a simple unique receipt number
        $receipt_number = "RCT-" . date("Ymd") . "-" . rand(1000, 9999);

        $stmt = $conn->prepare("INSERT INTO fee_payments 
            (student_id, term_id, amount_paid, payment_method, received_by, receipt_number) 
            VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iidsss", $student_id, $term_id, $amount_paid, $payment_method, $received_by, $receipt_number);

        if ($stmt->execute()) {
            $new_payment_id = $conn->insert_id;
            $message = "Payment of UGX " . number_format($amount_paid) . " recorded successfully! Receipt #: " . $receipt_number 
                . " — <a href='receipt.php?payment_id=" . $new_payment_id . "' target='_blank' style='color:#155724; text-decoration:underline; font-weight:600;'>Print Receipt</a>";
            $messageType = "success";
        } else {
            $message = "Error recording payment: " . $stmt->error;
            $messageType = "error";
        }
        $stmt->close();

        // Reload the student record after payment so balance updates instantly
        $search_admission = $_POST['admission_number'];
        $stmt = $conn->prepare("SELECT s.*, c.class_name, c.section, c.term_fee 
                                 FROM students s 
                                 JOIN classes c ON s.class_id = c.class_id 
                                 WHERE s.student_id = ?");
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $student = $result->fetch_assoc();
        $stmt->close();
    }
}

// ------------------------------------------------------------
// Handle editing an existing payment
// ------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === "edit_payment") {

    $payment_id     = $_POST['payment_id'];
    $amount_paid    = $_POST['amount_paid'];
    $payment_method = $_POST['payment_method'];
    $received_by    = trim($_POST['received_by']);

    if (empty($amount_paid) || $amount_paid <= 0) {
        $message = "Please enter a valid payment amount.";
        $messageType = "error";
    } else {
        $stmt = $conn->prepare("UPDATE fee_payments 
            SET amount_paid = ?, payment_method = ?, received_by = ? 
            WHERE payment_id = ?");
        $stmt->bind_param("dssi", $amount_paid, $payment_method, $received_by, $payment_id);

        if ($stmt->execute()) {
            $message = "Payment updated successfully.";
            $messageType = "success";
        } else {
            $message = "Error updating payment: " . $stmt->error;
            $messageType = "error";
        }
        $stmt->close();
    }

    $search_admission = $_POST['admission_number'];
}

// ------------------------------------------------------------
// Handle deleting a payment
// ------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === "delete_payment") {

    $payment_id = $_POST['payment_id'];

    $stmt = $conn->prepare("DELETE FROM fee_payments WHERE payment_id = ?");
    $stmt->bind_param("i", $payment_id);

    if ($stmt->execute()) {
        $message = "Payment deleted successfully.";
        $messageType = "success";
    } else {
        $message = "Error deleting payment: " . $stmt->error;
        $messageType = "error";
    }
    $stmt->close();

    $search_admission = $_POST['admission_number'];
}

// Reload the student if we just edited/deleted a payment, so the balance and table refresh
if (!empty($search_admission) && !$student) {
    $stmt = $conn->prepare("SELECT s.*, c.class_name, c.section, c.term_fee 
                             FROM students s 
                             JOIN classes c ON s.class_id = c.class_id 
                             WHERE s.admission_number = ?");
    $stmt->bind_param("s", $search_admission);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $student = $result->fetch_assoc();
    }
    $stmt->close();
}

// ------------------------------------------------------------
// Calculate total due, total paid, and balance for the student
// ------------------------------------------------------------
if ($student && $current_term) {

    if ($student['is_boarder']) {
        $boarding_result = $conn->query("SELECT amount FROM boarding_fee LIMIT 1");
        $boarding_row = $boarding_result->fetch_assoc();
        $total_due = $boarding_row['amount'];
    } else {
        $total_due = $student['term_fee'];
    }

    $stmt = $conn->prepare("SELECT COALESCE(SUM(amount_paid),0) AS paid 
                             FROM fee_payments 
                             WHERE student_id = ? AND term_id = ?");
    $stmt->bind_param("ii", $student['student_id'], $current_term['term_id']);
    $stmt->execute();
    $paid_result = $stmt->get_result()->fetch_assoc();
    $total_paid = $paid_result['paid'];
    $stmt->close();

    $balance = $total_due - $total_paid;
}

// ------------------------------------------------------------
// Fetch full payment history for this student (all terms)
// ------------------------------------------------------------
$payment_history = [];
if ($student) {
    $stmt = $conn->prepare("SELECT fp.*, at.term_number, at.academic_year 
                             FROM fee_payments fp 
                             JOIN academic_terms at ON fp.term_id = at.term_id 
                             WHERE fp.student_id = ? 
                             ORDER BY fp.payment_id DESC");
    $stmt->bind_param("i", $student['student_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $payment_history[] = $row;
    }
    $stmt->close();
}

// Which payment (if any) is currently being edited
$editing_payment_id = isset($_GET['edit_payment']) ? (int)$_GET['edit_payment'] : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Fee Payment - Ummul Bannin Madrasah</title>
<style>
    * { box-sizing: border-box; }
    body {
        font-family: Arial, Helvetica, sans-serif;
        background-color: #f4f6f5;
        margin: 0;
        padding: 30px;
    }
    .container {
        max-width: 650px;
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
    .student-card h3 {
        margin-top: 0;
        color: #1b5e20;
    }
    .info-row {
        display: flex;
        justify-content: space-between;
        padding: 6px 0;
        border-bottom: 1px solid #e0e0e0;
        font-size: 14px;
    }
    .balance-row {
        font-size: 18px;
        font-weight: bold;
        margin-top: 10px;
        padding-top: 10px;
    }
    .balance-zero { color: #1b5e20; }
    .balance-owing { color: #c0392b; }

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
        vertical-align: middle;
    }
    th { background-color: #f0f7f0; color: #1b5e20; }

    .row-actions {
        display: flex;
        gap: 6px;
    }
    .btn-small {
        padding: 6px 10px;
        margin-top: 0;
        font-size: 12px;
        border-radius: 4px;
        cursor: pointer;
        border: none;
    }
    .btn-edit { background-color: #e0a800; color: white; }
    .btn-edit:hover { background-color: #c69500; }
    .btn-delete { background-color: #c0392b; color: white; }
    .btn-delete:hover { background-color: #a5281c; }
    .btn-cancel { background-color: #999; color: white; }

    .edit-panel {
        margin-top: 15px;
        padding: 15px;
        background-color: #fff8e6;
        border: 1px solid #ffe8a1;
        border-radius: 6px;
    }
    .edit-panel h4 { margin-top: 0; color: #856404; font-size: 14px; }
</style>
</head>
<body>

<div class="container">
    <a href="dashboard.php" class="back-link">&larr; Back to Dashboard</a>
    <img src="logo.png" alt="Ummul Bannin Madrasah Badge" class="logo">
    <h1>Ummul Bannin Madrasah</h1>
    <p class="subtitle">Fee Payment</p>

    <?php if ($message): ?>
        <div class="message <?php echo $messageType; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <?php if (!$current_term): ?>
        <div class="message error">
            No current academic term is set. Please set one in the academic_terms table (is_current = 1).
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
                <span>Boarding</span>
                <span><?php echo $student['is_boarder'] ? "Yes" : "No"; ?></span>
            </div>
            <div class="info-row">
                <span>Term</span>
                <span><?php echo $current_term ? "Term " . $current_term['term_number'] . ", " . $current_term['academic_year'] : "N/A"; ?></span>
            </div>
            <div class="info-row">
                <span>Total Fees Due</span>
                <span>UGX <?php echo number_format($total_due); ?></span>
            </div>
            <div class="info-row">
                <span>Total Paid So Far</span>
                <span>UGX <?php echo number_format($total_paid); ?></span>
            </div>
            <div class="info-row balance-row <?php echo $balance > 0 ? 'balance-owing' : 'balance-zero'; ?>">
                <span>Balance</span>
                <span>UGX <?php echo number_format($balance); ?></span>
            </div>
        </div>

        <?php if ($current_term && $balance > 0): ?>
        <!-- Payment form -->
        <form method="POST" action="">
            <input type="hidden" name="student_id" value="<?php echo $student['student_id']; ?>">
            <input type="hidden" name="term_id" value="<?php echo $current_term['term_id']; ?>">
            <input type="hidden" name="admission_number" value="<?php echo htmlspecialchars($student['admission_number']); ?>">

            <label for="amount_paid">Amount Being Paid (UGX)</label>
            <input type="number" id="amount_paid" name="amount_paid" min="1" max="<?php echo $balance; ?>" required>

            <label for="payment_method">Payment Method</label>
            <select id="payment_method" name="payment_method" required>
                <option value="Cash">Cash</option>
                <option value="Mobile Money">Mobile Money</option>
                <option value="Bank">Bank</option>
            </select>

            <label for="received_by">Received By (Staff Name)</label>
            <input type="text" id="received_by" name="received_by" required>

            <button type="submit">Record Payment</button>
        </form>
        <?php elseif ($current_term): ?>
            <div class="message success" style="margin-top:20px;">
                This student has fully paid for the current term. No balance remaining.
            </div>
        <?php endif; ?>

        <?php if (!empty($payment_history)): ?>
        <h3 style="margin-top:30px; color:#1b5e20;">Payment History</h3>
        <table>
            <tr>
                <th>Date</th>
                <th>Term</th>
                <th>Amount</th>
                <th>Method</th>
                <th>Received By</th>
                <th>Receipt #</th>
                <th>Actions</th>
            </tr>
            <?php foreach ($payment_history as $p): ?>
                <?php if ($editing_payment_id === (int)$p['payment_id']): ?>
                <tr>
                    <td colspan="7">
                        <div class="edit-panel">
                            <h4>Editing payment — Receipt <?php echo htmlspecialchars($p['receipt_number']); ?></h4>
                            <form method="POST" action="">
                                <input type="hidden" name="action" value="edit_payment">
                                <input type="hidden" name="payment_id" value="<?php echo $p['payment_id']; ?>">
                                <input type="hidden" name="admission_number" value="<?php echo htmlspecialchars($student['admission_number']); ?>">

                                <label>Amount (UGX)</label>
                                <input type="number" name="amount_paid" min="1" value="<?php echo $p['amount_paid']; ?>" required>

                                <label>Payment Method</label>
                                <select name="payment_method" required>
                                    <option value="Cash" <?php echo $p['payment_method'] === 'Cash' ? 'selected' : ''; ?>>Cash</option>
                                    <option value="Mobile Money" <?php echo $p['payment_method'] === 'Mobile Money' ? 'selected' : ''; ?>>Mobile Money</option>
                                    <option value="Bank" <?php echo $p['payment_method'] === 'Bank' ? 'selected' : ''; ?>>Bank</option>
                                </select>

                                <label>Received By</label>
                                <input type="text" name="received_by" value="<?php echo htmlspecialchars($p['received_by']); ?>" required>

                                <div style="display:flex; gap:8px; margin-top:12px;">
                                    <button type="submit" class="btn-small btn-edit">Save Changes</button>
                                    <a href="?admission_number=<?php echo urlencode($student['admission_number']); ?>">
                                        <button type="button" class="btn-small btn-cancel">Cancel</button>
                                    </a>
                                </div>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                <tr>
                    <td><?php echo htmlspecialchars($p['date_paid']); ?></td>
                    <td>T<?php echo $p['term_number']; ?> <?php echo $p['academic_year']; ?></td>
                    <td>UGX <?php echo number_format($p['amount_paid']); ?></td>
                    <td><?php echo htmlspecialchars($p['payment_method']); ?></td>
                    <td><?php echo htmlspecialchars($p['received_by']); ?></td>
                    <td><?php echo htmlspecialchars($p['receipt_number']); ?></td>
                    <td>
                        <div class="row-actions">
                            <a href="receipt.php?payment_id=<?php echo $p['payment_id']; ?>" target="_blank">
                                <button type="button" class="btn-small" style="background-color:#1b5e20;">Print</button>
                            </a>
                            <a href="?admission_number=<?php echo urlencode($student['admission_number']); ?>&edit_payment=<?php echo $p['payment_id']; ?>">
                                <button type="button" class="btn-small btn-edit">Edit</button>
                            </a>
                            <form method="POST" action="" onsubmit="return confirm('Delete this payment record? This cannot be undone.');" style="display:inline;">
                                <input type="hidden" name="action" value="delete_payment">
                                <input type="hidden" name="payment_id" value="<?php echo $p['payment_id']; ?>">
                                <input type="hidden" name="admission_number" value="<?php echo htmlspecialchars($student['admission_number']); ?>">
                                <button type="submit" class="btn-small btn-delete">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endif; ?>
            <?php endforeach; ?>
        </table>
        <?php endif; ?>

    <?php endif; ?>

</div>

</body>
</html>
