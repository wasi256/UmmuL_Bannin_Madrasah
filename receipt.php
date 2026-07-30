<?php
include 'auth_check.php';
include 'db_connect.php';

$payment = null;

if (isset($_GET['payment_id'])) {
    $payment_id = (int)$_GET['payment_id'];

    $stmt = $conn->prepare("SELECT fp.*, s.full_name, s.admission_number, s.is_boarder, 
                             c.class_name, c.section, at.term_number, at.academic_year 
                             FROM fee_payments fp 
                             JOIN students s ON fp.student_id = s.student_id 
                             JOIN classes c ON s.class_id = c.class_id 
                             JOIN academic_terms at ON fp.term_id = at.term_id 
                             WHERE fp.payment_id = ?");
    $stmt->bind_param("i", $payment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $payment = $result->fetch_assoc();
    }
    $stmt->close();
}

if (!$payment) {
    die("Receipt not found. <a href='fee_payment.php'>Go back</a>");
}

// Calculate this student's total due and balance as of now
$due = $payment['term_fee'] ?? 0;
$stmt = $conn->prepare("SELECT c.term_fee FROM students s JOIN classes c ON s.class_id = c.class_id WHERE s.student_id = ?");
$stmt->bind_param("i", $payment['student_id']);
$stmt->execute();
$class_row = $stmt->get_result()->fetch_assoc();
$stmt->close();
$due = $class_row['term_fee'];

if ($payment['is_boarder']) {
    $b = $conn->query("SELECT amount FROM boarding_fee LIMIT 1")->fetch_assoc();
    $due = $b['amount'];
}

$stmt = $conn->prepare("SELECT COALESCE(SUM(amount_paid),0) AS paid FROM fee_payments WHERE student_id = ? AND term_id = ?");
$stmt->bind_param("ii", $payment['student_id'], $payment['term_id']);
$stmt->execute();
$total_paid = $stmt->get_result()->fetch_assoc()['paid'];
$stmt->close();

$balance = $due - $total_paid;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Receipt <?php echo htmlspecialchars($payment['receipt_number']); ?> - Ummul Bannin Madrasah</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
    :root {
        --emerald: #14532d; --parchment: #faf6ee; --gold: #b8860b;
        --ink: #2b2b2b; --sage: #e3ede3; --sage-border: #cddccd; --white: #ffffff;
    }
    * { box-sizing: border-box; }
    body {
        font-family: 'Inter', sans-serif;
        background-color: var(--parchment);
        color: var(--ink);
        margin: 0;
        padding: 30px;
        display: flex;
        justify-content: center;
    }
    .receipt {
        max-width: 480px;
        width: 100%;
        background: var(--white);
        border: 1px solid var(--sage-border);
        border-radius: 10px;
        padding: 35px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    }
    .receipt-header {
        text-align: center;
        border-bottom: 2px dashed var(--gold);
        padding-bottom: 18px;
        margin-bottom: 18px;
    }
    .receipt-header img {
        width: 70px; height: 70px; object-fit: contain; margin-bottom: 8px;
    }
    .receipt-header h1 {
        font-family: 'Amiri', serif;
        color: var(--emerald);
        font-size: 20px;
        margin: 0;
    }
    .receipt-header .tagline {
        font-size: 11px;
        color: #888;
        margin-top: 3px;
    }
    .receipt-title {
        text-align: center;
        font-family: 'Amiri', serif;
        font-size: 17px;
        color: var(--gold);
        margin-bottom: 20px;
        letter-spacing: 1px;
        text-transform: uppercase;
    }
    .row {
        display: flex;
        justify-content: space-between;
        padding: 7px 0;
        border-bottom: 1px solid #f0ece0;
        font-size: 14px;
    }
    .row .label { color: #666; }
    .row .value { font-weight: 600; }
    .amount-row {
        margin-top: 15px;
        padding: 14px;
        background-color: var(--sage);
        border-radius: 8px;
        text-align: center;
    }
    .amount-row .amount {
        font-family: 'Amiri', serif;
        font-size: 26px;
        font-weight: 700;
        color: var(--emerald);
    }
    .amount-row .amount-label {
        font-size: 12px;
        color: #555;
        margin-top: 2px;
    }
    .balance-note {
        text-align: center;
        margin-top: 12px;
        font-size: 13px;
        font-weight: 600;
    }
    .balance-owing { color: #c0392b; }
    .balance-clear { color: #1b5e20; }
    .footer-note {
        text-align: center;
        margin-top: 25px;
        font-size: 11px;
        color: #999;
    }
    .print-btn {
        display: block;
        width: 100%;
        margin-top: 20px;
        padding: 12px;
        background-color: var(--emerald);
        color: white;
        border: none;
        border-radius: 6px;
        font-size: 14px;
        cursor: pointer;
    }
    .print-btn:hover { background-color: #0d3a1f; }

    @media print {
        body { padding: 0; background: white; }
        .print-btn { display: none; }
        .receipt { box-shadow: none; border: none; }
    }
</style>
</head>
<body>
<div class="receipt">
    <div class="receipt-header">
        <img src="logo.png" alt="Ummul Bannin Madrasah Badge">
        <h1>Ummul Bannin Madrasah</h1>
        <div class="tagline">Through Hard Work We Toil</div>
    </div>

    <div class="receipt-title">Fee Payment Receipt</div>

    <div class="row"><span class="label">Receipt No.</span><span class="value"><?php echo htmlspecialchars($payment['receipt_number']); ?></span></div>
    <div class="row"><span class="label">Date</span><span class="value"><?php echo htmlspecialchars($payment['date_paid']); ?></span></div>
    <div class="row"><span class="label">Student Name</span><span class="value"><?php echo htmlspecialchars($payment['full_name']); ?></span></div>
    <div class="row"><span class="label">Admission No.</span><span class="value"><?php echo htmlspecialchars($payment['admission_number']); ?></span></div>
    <div class="row"><span class="label">Class</span><span class="value"><?php echo htmlspecialchars($payment['class_name']); ?></span></div>
    <div class="row"><span class="label">Term</span><span class="value">Term <?php echo $payment['term_number']; ?>, <?php echo $payment['academic_year']; ?></span></div>
    <div class="row"><span class="label">Payment Method</span><span class="value"><?php echo htmlspecialchars($payment['payment_method']); ?></span></div>
    <div class="row"><span class="label">Received By</span><span class="value"><?php echo htmlspecialchars($payment['received_by']); ?></span></div>

    <div class="amount-row">
        <div class="amount">UGX <?php echo number_format($payment['amount_paid']); ?></div>
        <div class="amount-label">Amount Paid</div>
    </div>

    <div class="balance-note <?php echo $balance > 0 ? 'balance-owing' : 'balance-clear'; ?>">
        <?php if ($balance > 0): ?>
            Remaining Balance This Term: UGX <?php echo number_format($balance); ?>
        <?php else: ?>
            Fully Paid For This Term
        <?php endif; ?>
    </div>

    <div class="footer-note">Please keep this receipt for your records.</div>

    <button class="print-btn" onclick="window.print()">Print Receipt</button>
</div>
</body>
</html>
