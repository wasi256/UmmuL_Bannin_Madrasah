<?php
include 'auth_check.php';
include 'db_connect.php';

// ------------------------------------------------------------
// Real stats pulled from the database
// ------------------------------------------------------------
$total_students = $conn->query("SELECT COUNT(*) AS c FROM students WHERE status = 'Active'")->fetch_assoc()['c'];
$total_boarders = $conn->query("SELECT COUNT(*) AS c FROM students WHERE status = 'Active' AND is_boarder = 1")->fetch_assoc()['c'];
$total_day = $conn->query("SELECT COUNT(*) AS c FROM students WHERE status = 'Active' AND is_boarder = 0")->fetch_assoc()['c'];
$total_classes = $conn->query("SELECT COUNT(*) AS c FROM classes")->fetch_assoc()['c'];
$low_stock_items = $conn->query("SELECT COUNT(*) AS c FROM uniform_items WHERE quantity_in_stock <= 5")->fetch_assoc()['c'];

// Get current term id (needed for balance calculation below)
$current_term_id = null;
$tr_check = $conn->query("SELECT term_id FROM academic_terms WHERE is_current = 1 LIMIT 1");
if ($tr_check && $tr_check->num_rows > 0) {
    $current_term_id = $tr_check->fetch_assoc()['term_id'];
}

// Recent fee payments (last 5), arranged by class order (Baby Class -> P.7), with balance shown
$recent_payments = [];
$rp = $conn->query("SELECT fp.amount_paid, fp.date_paid, fp.receipt_number, 
                     s.student_id, s.full_name, s.is_boarder, c.class_id, c.class_name, c.term_fee 
                     FROM fee_payments fp 
                     JOIN students s ON fp.student_id = s.student_id 
                     JOIN classes c ON s.class_id = c.class_id 
                     ORDER BY fp.payment_id DESC LIMIT 5");
if ($rp) {
    while ($row = $rp->fetch_assoc()) {

        // Work out this student's current balance
        $due = $row['term_fee'];
        if ($row['is_boarder']) {
            $boarding_row = $conn->query("SELECT amount FROM boarding_fee LIMIT 1")->fetch_assoc();
            $due = $boarding_row['amount'];
        }

        $disc_stmt = $conn->prepare("SELECT COALESCE(SUM(discount_amount),0) AS disc FROM fee_discounts WHERE student_id = ? AND term_id = ?");
        $disc_stmt->bind_param("ii", $row['student_id'], $current_term_id);
        $disc_stmt->execute();
        $discount_total = $disc_stmt->get_result()->fetch_assoc()['disc'];
        $disc_stmt->close();

        $due = $due - $discount_total;

        $paid_stmt = $conn->prepare("SELECT COALESCE(SUM(amount_paid),0) AS paid FROM fee_payments WHERE student_id = ? AND term_id = ?");
        $paid_stmt->bind_param("ii", $row['student_id'], $current_term_id);
        $paid_stmt->execute();
        $paid_total = $paid_stmt->get_result()->fetch_assoc()['paid'];
        $paid_stmt->close();

        $row['balance'] = $due - $paid_total;
        $recent_payments[] = $row;
    }

    // Arrange by class order: Baby Class -> P.7 (class_id reflects this order)
    usort($recent_payments, function($a, $b) {
        return $a['class_id'] <=> $b['class_id'];
    });
}

// Current term label
$term_label = "No term set";
$tr = $conn->query("SELECT * FROM academic_terms WHERE is_current = 1 LIMIT 1");
if ($tr && $tr->num_rows > 0) {
    $t = $tr->fetch_assoc();
    $term_label = "Term " . $t['term_number'] . ", " . $t['academic_year'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Dashboard - Ummul Bannin Madrasah</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
    :root {
        --emerald: #14532d;
        --emerald-dark: #0d3a1f;
        --parchment: #faf6ee;
        --gold: #b8860b;
        --gold-light: #d4a72c;
        --ink: #2b2b2b;
        --sage: #e3ede3;
        --sage-border: #cddccd;
        --white: #ffffff;
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
        font-family: 'Inter', sans-serif;
        background-color: var(--parchment);
        color: var(--ink);
        display: flex;
        min-height: 100vh;
    }

    /* ---------------- SIDEBAR ---------------- */
    .sidebar {
        width: 250px;
        background-color: var(--emerald);
        color: var(--white);
        display: flex;
        flex-direction: column;
        min-height: 100vh;
        flex-shrink: 0;
    }

    .sidebar-header {
        position: relative;
        background-color: var(--emerald-dark);
        border-radius: 0 0 60% 60% / 0 0 25px 25px;
        padding: 30px 20px 25px 20px;
        text-align: center;
    }

    .sidebar-header img {
        width: 72px;
        height: 72px;
        object-fit: contain;
        background: var(--parchment);
        border-radius: 50%;
        padding: 6px;
        border: 2px solid var(--gold);
    }

    .sidebar-header h1 {
        font-family: 'Amiri', serif;
        font-size: 19px;
        margin-top: 12px;
        line-height: 1.3;
        color: var(--white);
    }

    .gold-divider {
        height: 2px;
        margin: 0 25px;
        background: repeating-linear-gradient(
            90deg,
            var(--gold) 0px, var(--gold) 6px,
            transparent 6px, transparent 12px
        );
    }

    .nav-section {
        padding: 20px 15px;
        flex-grow: 1;
    }

    .nav-label {
        font-size: 11px;
        letter-spacing: 1.5px;
        text-transform: uppercase;
        color: rgba(255,255,255,0.5);
        margin: 15px 10px 8px 10px;
    }

    .nav-link {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 11px 14px;
        border-radius: 8px;
        color: rgba(255,255,255,0.9);
        text-decoration: none;
        font-size: 14px;
        font-weight: 500;
        margin-bottom: 4px;
        transition: background-color 0.15s ease;
    }

    .nav-link:hover {
        background-color: rgba(255,255,255,0.1);
    }

    .nav-link .icon {
        font-size: 16px;
        width: 20px;
        text-align: center;
    }

    .sidebar-footer {
        padding: 18px 20px;
        border-top: 1px solid rgba(255,255,255,0.12);
    }

    .user-name {
        font-size: 13px;
        font-weight: 600;
        color: var(--white);
    }
    .user-role {
        font-size: 11px;
        color: rgba(255,255,255,0.55);
        margin-bottom: 12px;
    }

    .logout-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        color: #f3b3b3;
        text-decoration: none;
        font-size: 13px;
        font-weight: 500;
    }
    .logout-btn:hover { color: #ffffff; }

    /* ---------------- MAIN CONTENT ---------------- */
    .main {
        flex-grow: 1;
        padding: 35px 40px;
    }

    .topbar {
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        margin-bottom: 30px;
        flex-wrap: wrap;
        gap: 10px;
    }

    .topbar h2 {
        font-family: 'Amiri', serif;
        font-size: 26px;
        color: var(--emerald);
    }

    .topbar .term-tag {
        background-color: var(--sage);
        border: 1px solid var(--sage-border);
        color: var(--emerald);
        padding: 6px 14px;
        border-radius: 20px;
        font-size: 13px;
        font-weight: 600;
    }

    /* Stat cards */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 18px;
        margin-bottom: 35px;
    }

    .stat-card {
        background: var(--white);
        border: 1px solid var(--sage-border);
        border-radius: 10px;
        padding: 20px;
        display: flex;
        align-items: center;
        gap: 14px;
    }

    .stat-icon {
        width: 46px;
        height: 46px;
        border-radius: 10px;
        background-color: var(--sage);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        flex-shrink: 0;
    }

    .stat-icon.alert {
        background-color: #fbe7e3;
    }

    .stat-number {
        font-family: 'Amiri', serif;
        font-size: 26px;
        font-weight: 700;
        color: var(--emerald);
        line-height: 1;
    }

    .stat-label {
        font-size: 12.5px;
        color: #666;
        margin-top: 3px;
    }

    /* Quick actions */
    .section-title {
        font-family: 'Amiri', serif;
        font-size: 18px;
        color: var(--emerald);
        margin-bottom: 14px;
    }

    .actions-row {
        display: flex;
        gap: 14px;
        flex-wrap: wrap;
        margin-bottom: 35px;
    }

    .action-btn {
        display: flex;
        align-items: center;
        gap: 10px;
        background: var(--white);
        border: 1px solid var(--sage-border);
        color: var(--emerald);
        padding: 13px 18px;
        border-radius: 8px;
        text-decoration: none;
        font-size: 14px;
        font-weight: 600;
        transition: all 0.15s ease;
    }

    .action-btn:hover {
        background-color: var(--emerald);
        color: var(--white);
        border-color: var(--emerald);
    }

    /* Recent payments table */
    .panel {
        background: var(--white);
        border: 1px solid var(--sage-border);
        border-radius: 10px;
        padding: 24px;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13.5px;
    }

    th {
        text-align: left;
        padding: 10px 8px;
        color: var(--emerald);
        border-bottom: 2px solid var(--sage);
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    td {
        padding: 12px 8px;
        border-bottom: 1px solid #f0ece0;
    }

    .empty-state {
        text-align: center;
        color: #999;
        padding: 30px 0;
        font-size: 14px;
    }

    @media (max-width: 800px) {
        body { flex-direction: column; }
        .sidebar { width: 100%; min-height: auto; }
        .sidebar-header { border-radius: 0 0 30px 30px; }
    }
</style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-header">
        <img src="logo.png" alt="Ummul Bannin Madrasah Badge">
        <h1>Ummul Bannin<br>Madrasah</h1>
    </div>
    <div class="gold-divider"></div>

    <div class="nav-section">
        <div class="nav-label">Navigation</div>
        <a href="dashboard.php" class="nav-link"><span class="icon">&#8962;</span> Dashboard</a>
        <a href="register_student.php" class="nav-link"><span class="icon">&#9998;</span> Register Student</a>
        <a href="manage_students.php" class="nav-link"><span class="icon">&#128101;</span> Manage Students</a>
        <a href="class_counts.php" class="nav-link"><span class="icon">&#128203;</span> Class Numbers</a>
        <a href="fee_payment.php" class="nav-link"><span class="icon">&#128176;</span> Fee Payment</a>
        <a href="manage_fees.php" class="nav-link"><span class="icon">&#9878;</span> Manage Fees</a>
        <a href="uniform_issue.php" class="nav-link"><span class="icon">&#128085;</span> Issue Uniform</a>
        <a href="manage_uniforms.php" class="nav-link"><span class="icon">&#128230;</span> Manage Uniforms</a>
        <a href="reports.php" class="nav-link"><span class="icon">&#128202;</span> Reports</a>
        <a href="class_fee_status.php" class="nav-link"><span class="icon">&#127891;</span> Fee Status by Class</a>
        <a href="manage_terms.php" class="nav-link"><span class="icon">&#128197;</span> Academic Terms</a>
        <?php if ($_SESSION['role'] === 'Admin'): ?>
        <a href="manage_users.php" class="nav-link"><span class="icon">&#128100;</span> Staff Accounts</a>
        <?php endif; ?>
        <a href="backup.php" class="nav-link"><span class="icon">&#128190;</span> Backup</a>
    </div>

    <div class="sidebar-footer">
        <div class="user-name"><?php echo htmlspecialchars($_SESSION['full_name'] ?: $_SESSION['username']); ?></div>
        <div class="user-role"><?php echo htmlspecialchars($_SESSION['role']); ?></div>
        <a href="change_password.php" style="font-size:12px; color:rgba(255,255,255,0.7); text-decoration:none; display:block; margin-bottom:10px;">Change Password</a>
        <a href="logout.php" class="logout-btn"><span>&#10162;</span> Log Out</a>
    </div>
</div>

<div class="main">
    <div class="topbar">
        <h2>Assalamu Alaikum, <?php echo htmlspecialchars(explode(' ', $_SESSION['full_name'] ?: $_SESSION['username'])[0]); ?></h2>
        <span class="term-tag"><?php echo htmlspecialchars($term_label); ?></span>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">&#128101;</div>
            <div>
                <div class="stat-number"><?php echo $total_students; ?></div>
                <div class="stat-label">Active Students</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">&#127968;</div>
            <div>
                <div class="stat-number"><?php echo $total_boarders; ?></div>
                <div class="stat-label">Boarding Students</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">&#127970;</div>
            <div>
                <div class="stat-number"><?php echo $total_day; ?></div>
                <div class="stat-label">Day Students</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">&#128218;</div>
            <div>
                <div class="stat-number"><?php echo $total_classes; ?></div>
                <div class="stat-label">Classes</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon <?php echo $low_stock_items > 0 ? 'alert' : ''; ?>">&#9888;</div>
            <div>
                <div class="stat-number"><?php echo $low_stock_items; ?></div>
                <div class="stat-label">Low Stock Uniforms</div>
            </div>
        </div>
    </div>

    <div class="section-title">Quick Actions</div>
    <div class="actions-row">
        <a href="register_student.php" class="action-btn"><span>&#9998;</span> Register Student</a>
        <a href="fee_payment.php" class="action-btn"><span>&#128176;</span> Record Fee Payment</a>
        <a href="uniform_issue.php" class="action-btn"><span>&#128085;</span> Issue Uniform</a>
        <a href="manage_uniforms.php" class="action-btn"><span>&#128230;</span> Manage Stock</a>
    </div>

    <div class="section-title">Recent Fee Payments</div>
    <div class="panel">
        <?php if (!empty($recent_payments)): ?>
        <table>
            <tr>
                <th>Student</th>
                <th>Class</th>
                <th>Amount</th>
                <th>Balance</th>
                <th>Date</th>
                <th>Receipt #</th>
            </tr>
            <?php foreach ($recent_payments as $p): ?>
            <tr>
                <td><?php echo htmlspecialchars($p['full_name']); ?></td>
                <td><?php echo htmlspecialchars($p['class_name']); ?></td>
                <td>UGX <?php echo number_format($p['amount_paid']); ?></td>
                <td style="color: <?php echo $p['balance'] > 0 ? '#c0392b' : '#1b5e20'; ?>; font-weight:600;">
                    UGX <?php echo number_format($p['balance']); ?>
                </td>
                <td><?php echo htmlspecialchars($p['date_paid']); ?></td>
                <td><?php echo htmlspecialchars($p['receipt_number']); ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php else: ?>
            <div class="empty-state">No fee payments recorded yet. Once you record one, it'll show up here.</div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
