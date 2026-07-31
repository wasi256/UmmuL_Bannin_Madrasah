<?php
include 'auth_check.php';
include 'db_connect.php';

// ------------------------------------------------------------
// Current term
// ------------------------------------------------------------
$current_term = null;
$tr = $conn->query("SELECT * FROM academic_terms WHERE is_current = 1 LIMIT 1");
if ($tr && $tr->num_rows > 0) {
    $current_term = $tr->fetch_assoc();
}

$boarding_amount = 0;
$br = $conn->query("SELECT amount FROM boarding_fee LIMIT 1");
if ($br && $br->num_rows > 0) {
    $boarding_amount = $br->fetch_assoc()['amount'];
}

// ------------------------------------------------------------
// Outstanding balances - every active student, this term
// ------------------------------------------------------------
$outstanding = [];
$total_outstanding = 0;

if ($current_term) {
    $stmt = $conn->prepare("SELECT s.student_id, s.admission_number, s.full_name, s.is_boarder, 
                             c.class_name, c.term_fee 
                             FROM students s 
                             JOIN classes c ON s.class_id = c.class_id 
                             WHERE s.status = 'Active' 
                             ORDER BY c.class_id ASC, s.full_name ASC");
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $due = $row['is_boarder'] ? $boarding_amount : $row['term_fee'];

        $disc_stmt = $conn->prepare("SELECT COALESCE(SUM(discount_amount),0) AS disc FROM fee_discounts WHERE student_id = ? AND term_id = ?");
        $disc_stmt->bind_param("ii", $row['student_id'], $current_term['term_id']);
        $disc_stmt->execute();
        $discount_total = $disc_stmt->get_result()->fetch_assoc()['disc'];
        $disc_stmt->close();

        $due = $due - $discount_total;

        $paid_stmt = $conn->prepare("SELECT COALESCE(SUM(amount_paid),0) AS paid FROM fee_payments WHERE student_id = ? AND term_id = ?");
        $paid_stmt->bind_param("ii", $row['student_id'], $current_term['term_id']);
        $paid_stmt->execute();
        $paid = $paid_stmt->get_result()->fetch_assoc()['paid'];
        $paid_stmt->close();

        $balance = $due - $paid;

        if ($balance > 0) {
            $row['total_due'] = $due;
            $row['total_paid'] = $paid;
            $row['discount'] = $discount_total;
            $row['balance'] = $balance;
            $outstanding[] = $row;
            $total_outstanding += $balance;
        }
    }
    $stmt->close();
}

// ------------------------------------------------------------
// Total fees collected this term
// ------------------------------------------------------------
$total_collected_term = 0;
if ($current_term) {
    $stmt = $conn->prepare("SELECT COALESCE(SUM(amount_paid),0) AS total FROM fee_payments WHERE term_id = ?");
    $stmt->bind_param("i", $current_term['term_id']);
    $stmt->execute();
    $total_collected_term = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
}

// Total fees collected all-time
$total_collected_all = $conn->query("SELECT COALESCE(SUM(amount_paid),0) AS total FROM fee_payments")->fetch_assoc()['total'];

// ------------------------------------------------------------
// Uniform sales summary
// ------------------------------------------------------------
$uniform_stats = $conn->query("SELECT 
    COALESCE(SUM(quantity),0) AS total_items_sold, 
    COALESCE(SUM(total_price),0) AS total_value, 
    COALESCE(SUM(amount_paid),0) AS total_collected 
    FROM uniform_issues")->fetch_assoc();

$uniform_balance_owed = $uniform_stats['total_value'] - $uniform_stats['total_collected'];

// Breakdown by item
$uniform_breakdown = [];
$ub = $conn->query("SELECT u.item_name, 
                     COALESCE(SUM(ui.quantity),0) AS qty_sold, 
                     COALESCE(SUM(ui.total_price),0) AS revenue 
                     FROM uniform_items u 
                     LEFT JOIN uniform_issues ui ON u.uniform_id = ui.uniform_id 
                     GROUP BY u.uniform_id 
                     ORDER BY revenue DESC");
while ($row = $ub->fetch_assoc()) {
    $uniform_breakdown[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Reports - Ummul Bannin Madrasah</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
    :root {
        --emerald: #14532d;
        --parchment: #faf6ee;
        --gold: #b8860b;
        --ink: #2b2b2b;
        --sage: #e3ede3;
        --sage-border: #cddccd;
        --white: #ffffff;
    }
    * { box-sizing: border-box; }
    body {
        font-family: 'Inter', sans-serif;
        background-color: var(--parchment);
        color: var(--ink);
        margin: 0;
        padding: 30px;
    }
    .container {
        max-width: 1100px;
        margin: 0 auto;
        background: var(--white);
        padding: 30px 35px;
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    }
    h1 {
        font-family: 'Amiri', serif;
        color: var(--emerald);
        font-size: 24px;
        margin-bottom: 5px;
    }
    .subtitle { color: #666; margin-bottom: 20px; font-size: 14px; }
    .back-link { font-size: 13px; margin-bottom: 20px; display: inline-block; color: var(--emerald); }

    .section-title {
        font-family: 'Amiri', serif;
        font-size: 19px;
        color: var(--emerald);
        margin-top: 35px;
        margin-bottom: 14px;
        border-bottom: 2px solid var(--sage);
        padding-bottom: 8px;
    }

    .summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 16px;
    }
    .summary-card {
        background-color: var(--sage);
        border: 1px solid var(--sage-border);
        border-radius: 8px;
        padding: 18px;
    }
    .summary-card .amount {
        font-family: 'Amiri', serif;
        font-size: 24px;
        font-weight: 700;
        color: var(--emerald);
    }
    .summary-card .label {
        font-size: 12.5px;
        color: #555;
        margin-top: 4px;
    }
    .summary-card.warning { background-color: #fdeeea; border-color: #f5cfc4; }
    .summary-card.warning .amount { color: #c0392b; }

    table { width: 100%; border-collapse: collapse; font-size: 13px; margin-top: 10px; }
    th, td { text-align: left; padding: 9px 8px; border-bottom: 1px solid #eee; }
    th { background-color: var(--sage); color: var(--emerald); font-size: 11.5px; text-transform: uppercase; letter-spacing: 0.4px; }

    .balance-owing { color: #c0392b; font-weight: 600; }
    .empty-state { text-align: center; color: #999; padding: 25px 0; }

    .print-btn {
        float: right;
        padding: 8px 16px;
        background-color: var(--emerald);
        color: white;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        font-size: 13px;
    }

    @media print {
        .back-link, .print-btn { display: none; }
        body { padding: 0; background: white; }
        .container { box-shadow: none; }
    }
</style>
</head>
<body>

<div class="container">
    <a href="dashboard.php" class="back-link">&larr; Back to Dashboard</a>
    <button class="print-btn" onclick="window.print()">Print Report</button>
    <h1>Reports</h1>
    <p class="subtitle"><?php echo $current_term ? "Term " . $current_term['term_number'] . ", " . $current_term['academic_year'] : "No current term set"; ?></p>

    <!-- Fee summary -->
    <div class="section-title">Fee Collection Summary</div>
    <div class="summary-grid">
        <div class="summary-card">
            <div class="amount">UGX <?php echo number_format($total_collected_term); ?></div>
            <div class="label">Collected This Term</div>
        </div>
        <div class="summary-card">
            <div class="amount">UGX <?php echo number_format($total_collected_all); ?></div>
            <div class="label">Collected All-Time</div>
        </div>
        <div class="summary-card warning">
            <div class="amount">UGX <?php echo number_format($total_outstanding); ?></div>
            <div class="label">Outstanding Balance (This Term)</div>
        </div>
        <div class="summary-card warning">
            <div class="amount"><?php echo count($outstanding); ?></div>
            <div class="label">Students With Balances</div>
        </div>
    </div>

    <!-- Outstanding balances table -->
    <div class="section-title">Students With Outstanding Balances</div>
    <?php if (empty($outstanding)): ?>
        <div class="empty-state">Everyone is fully paid up for this term. Nothing to chase.</div>
    <?php else: ?>
    <table>
        <tr>
            <th>Admission #</th>
            <th>Name</th>
            <th>Class</th>
            <th>Boarding</th>
            <th>Total Due</th>
            <th>Discount</th>
            <th>Paid</th>
            <th>Balance</th>
        </tr>
        <?php foreach ($outstanding as $o): ?>
        <tr>
            <td><?php echo htmlspecialchars($o['admission_number']); ?></td>
            <td><?php echo htmlspecialchars($o['full_name']); ?></td>
            <td><?php echo htmlspecialchars($o['class_name']); ?></td>
            <td><?php echo $o['is_boarder'] ? 'Yes' : 'No'; ?></td>
            <td>UGX <?php echo number_format($o['total_due']); ?></td>
            <td style="color:<?php echo $o['discount'] > 0 ? '#0c5460' : '#999'; ?>;">
                <?php echo $o['discount'] > 0 ? '- UGX ' . number_format($o['discount']) : '—'; ?>
            </td>
            <td>UGX <?php echo number_format($o['total_paid']); ?></td>
            <td class="balance-owing">UGX <?php echo number_format($o['balance']); ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>

    <!-- Uniform sales summary -->
    <div class="section-title">Uniform Sales Summary</div>
    <div class="summary-grid">
        <div class="summary-card">
            <div class="amount"><?php echo $uniform_stats['total_items_sold']; ?></div>
            <div class="label">Total Items Issued</div>
        </div>
        <div class="summary-card">
            <div class="amount">UGX <?php echo number_format($uniform_stats['total_collected']); ?></div>
            <div class="label">Total Collected</div>
        </div>
        <div class="summary-card <?php echo $uniform_balance_owed > 0 ? 'warning' : ''; ?>">
            <div class="amount">UGX <?php echo number_format($uniform_balance_owed); ?></div>
            <div class="label">Outstanding on Uniforms</div>
        </div>
    </div>

    <?php if (!empty($uniform_breakdown)): ?>
    <table style="margin-top:20px;">
        <tr>
            <th>Item</th>
            <th>Quantity Sold</th>
            <th>Revenue (Total Value)</th>
        </tr>
        <?php foreach ($uniform_breakdown as $u): ?>
        <tr>
            <td><?php echo htmlspecialchars($u['item_name']); ?></td>
            <td><?php echo $u['qty_sold']; ?></td>
            <td>UGX <?php echo number_format($u['revenue']); ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>

</div>

</body>
</html>
