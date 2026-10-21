<?php
include '../includes/auth_check.php';
include '../includes/db_connect.php';

$message = "";
$messageType = "";

// ------------------------------------------------------------
// Get all withdrawn students, organized by class
// ------------------------------------------------------------
$class_result = $conn->query("SELECT * FROM classes ORDER BY class_name, section");
$classes = [];
while ($row = $class_result->fetch_assoc()) {
    $classes[] = $row;
}

// Build a list of withdrawn students per class
$students_by_class = [];
foreach ($classes as $class) {
    $stmt = $conn->prepare("SELECT s.*, u.username AS received_by_name 
                             FROM students s 
                             LEFT JOIN users u ON s.guardian_name = u.full_name OR TRUE 
                             WHERE s.class_id = ? AND s.status = 'Withdrawn'
                             ORDER BY s.full_name");
    $stmt->bind_param("i", $class['class_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $students = [];
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
    $stmt->close();

    if (count($students) > 0) {
        $students_by_class[$class['class_name'] . ' (' . $class['section'] . ')'] = $students;
    }
}

// ------------------------------------------------------------
// Handle student search
// ------------------------------------------------------------
$search_admission = "";
$search_term = "";

if (isset($_GET['search']) && trim($_GET['search']) !== "") {
    $search_term = trim($_GET['search']);
    $like_term = "%" . $search_term . "%";

    $stmt = $conn->prepare("SELECT s.*, c.class_name, c.section 
                             FROM students s 
                             JOIN classes c ON s.class_id = c.class_id 
                             WHERE s.admission_number = ? OR s.full_name LIKE ?
                             AND s.status = 'Withdrawn'");
    $stmt->bind_param("ss", $search_term, $like_term);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $student = $result->fetch_assoc();
        $search_admission = $student['admission_number'];
    } elseif ($result->num_rows > 1) {
        $multiple_matches = [];
        while ($row = $result->fetch_assoc()) {
            $multiple_matches[] = $row;
        }
    } else {
        $message = "No withdrawn student found matching: " . htmlspecialchars($search_term);
        $messageType = "error";
    }
    $stmt->close();
}

// ------------------------------------------------------------
// Handle detailed view when student is selected via admission_number
// ------------------------------------------------------------
$student = null;
$current_term = null;

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
        $message = "No withdrawn student found with admission number: " . htmlspecialchars($search_admission);
        $messageType = "error";
    }
    $stmt->close();

    // Fetch current term
    $term_result = $conn->query("SELECT * FROM academic_terms WHERE is_current = 1 LIMIT 1");
    if ($term_result && $term_result->num_rows > 0) {
        $current_term = $term_result->fetch_assoc();
    }
}

// ------------------------------------------------------------
// Calculate total due, total paid, balance for withdrawn student
// ------------------------------------------------------------
$total_discount = 0;
$discount_history = [];
$total_paid = 0;
$total_due = 0;
$balance = 0;

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

    // Any discounts/waivers granted this term
    $stmt = $conn->prepare("SELECT * FROM fee_discounts WHERE student_id = ? AND term_id = ? ORDER BY discount_id DESC");
    $stmt->bind_param("ii", $student['student_id'], $current_term['term_id']);
    $stmt->execute();
    $disc_result = $stmt->get_result();
    while ($row = $disc_result->fetch_assoc()) {
        $discount_history[] = $row;
        $total_discount += $row['discount_amount'];
    }
    $stmt->close();

    $total_due = $total_due - $total_discount;
    $balance = $total_due - $total_paid;
}

// ------------------------------------------------------------
// Fetch full payment history for this withdrawn student (all terms)
// ------------------------------------------------------------
$payment_history = [];
$discount_history = [];

if ($student) {
    // Payment history across all terms
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

    // Discount history across all terms
    $stmt = $conn->prepare("SELECT * FROM fee_discounts WHERE student_id = ? ORDER BY discount_id DESC");
    $stmt->bind_param("i", $student['student_id']);
    $stmt->execute();
    $disc_result = $stmt->get_result();
    while ($row = $disc_result->fetch_assoc()) {
        $discount_history[] = $row;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Withdrawn Students - Fee History - Ummul Bannin Madrasah</title>
<style>
    * { box-sizing: border-box; }
    body {
        font-family: Arial, Helvetica, sans-serif;
        background-color: #f4f6f5;
        margin: 0;
        padding: 30px;
    }
    .container {
        max-width: 900px;
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
    .btn-view { background-color: #6c757d; color: white; }
    .btn-view:hover { background-color: #5a6268; }

    .class-section {
        margin-bottom: 40px;
        padding-bottom: 20px;
        border-bottom: 1px solid #e0e0e0;
    }
    .class-section:last-child {
        border-bottom: none;
        margin-bottom: 0;
        padding-bottom: 0;
    }
    .class-header {
        color: #1b5e20;
        font-size: 18px;
        margin-bottom: 15px;
        padding-bottom: 8px;
        border-bottom: 2px solid #1b5e20;
    }
    .student-summary {
        background-color: #f8f9fa;
        padding: 10px 15px;
        margin-bottom: 15px;
        border-radius: 4px;
        font-size: 13px;
    }
    .student-summary span {
        margin-right: 20px;
    }
    .balance-owing { color: #c0392b; font-weight: bold; }
    .balance-zero { color: #1b5e20; font-weight: bold; }
</style>
</head>
<body>

<div class="container">
    <img src="../assets/images/logo.png" alt="Ummul Bannin Madrasah Badge" class="logo">
    <h1>Withdrawn Students - Fee History</h1>
    <p class="subtitle">History preserved for students who have left the madrasah</p>

    <?php if ($message): ?>
        <div class="message <?php echo $messageType; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <!-- Search form -->
    <form method="GET" action="">
        <label for="search">Search Withdrawn Student by Name or Admission Number</label>
        <div class="search-row">
            <input type="text" id="search" name="search" 
                   value="<?php echo htmlspecialchars($search_term); ?>" placeholder="e.g. Amina or UB-001">
            <button type="submit">Search</button>
        </div>
    </form>

    <?php if (isset($student) && $student): ?>
        <!-- Student detail card -->
        <div class="class-section">
            <div class="class-header">
                <?php echo htmlspecialchars($student['full_name']); ?> 
                (Adm#: <?php echo htmlspecialchars($student['admission_number']); ?>)
                - <?php echo htmlspecialchars($student['class_name'] . ' (' . $student['section'] . ')'); ?>
            </div>

            <div class="student-summary">
                <span>Status: Withdrawn</span>
                <span>Class: <?php echo htmlspecialchars($student['class_name'] . ' (' . $student['section'] . ')'); ?></span>
                <span>Boarder: <?php echo $student['is_boarder'] ? "Yes" : "No"; ?></span>
            </div>

            <div class="student-summary">
                <span>Total Fees Due: UGX <?php echo number_format($total_due); ?></span>
                <span>Total Paid: UGX <?php echo number_format($total_paid); ?></span>
                <span>Balance: UGX <?php echo number_format($balance); ?></span>
            </div>

            <?php if (!empty($discount_history)): ?>
            <h3 style="margin:25px 0 10px 0; color:#0c5460;">Discounts / Waivers</h3>
            <table>
                <tr>
                    <th>Date</th>
                    <th>Amount</th>
                    <th>Reason</th>
                    <th>Approved By</th>
                </tr>
                <?php foreach ($discount_history as $d): ?>
                <tr>
                    <td><?php echo htmlspecialchars($d['date_given']); ?></td>
                    <td>UGX <?php echo number_format($d['discount_amount']); ?></td>
                    <td><?php echo htmlspecialchars($d['reason']); ?></td>
                    <td><?php echo htmlspecialchars($d['approved_by']); ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
            <?php endif; ?>

            <!-- Payment History -->
            <?php if (!empty($payment_history)): ?>
            <h3 style="margin:30px 0 10px 0; color:#1b5e20;">Payment History (All Terms)</h3>
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
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
            <?php else: ?>
            <div class="message success">
                This student has no fee payment records.
            </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Classes with withdrawn students -->
    <?php if (!empty($students_by_class)): ?>
    <div style="margin-top:30px;">
        <h2>Withdrawn Students by Class</h2>
        <?php foreach ($students_by_class as $className => $students): ?>
        <div class="class-section">
            <div class="class-header">
                <?php echo htmlspecialchars($className); ?> 
                <span style="font-size:12px; color:#666;">(<?php count($students); ?> withdrawn students)</span>
            </div>
            
            <div class="student-summary">
                <?php foreach ($students as $s): ?>
                <span>
                    <?php echo htmlspecialchars($s['full_name']); ?> 
                    (<?php echo htmlspecialchars($s['admission_number']); ?>) -
                    <?php echo $s['is_boarder'] ? "Boarder" : "Day"; ?> -
                    <?php echo "UGX " . number_format($s['term_fee'] ?? 0); ?>
                </span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</div>

</body>
</html>