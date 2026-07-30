<?php
include 'auth_check.php';
include 'db_connect.php';

// ------------------------------------------------------------
// Current term + boarding fee
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

// All classes for the dropdown
$all_classes = [];
$cr = $conn->query("SELECT class_id, class_name, section FROM classes ORDER BY class_id");
while ($row = $cr->fetch_assoc()) {
    $all_classes[] = $row;
}

$selected_class_id = isset($_GET['class_id']) && $_GET['class_id'] !== "" ? (int)$_GET['class_id'] : null;
$selected_class = null;
$paid_up = [];
$owing = [];

if ($selected_class_id && $current_term) {

    foreach ($all_classes as $c) {
        if ($c['class_id'] == $selected_class_id) {
            $selected_class = $c;
        }
    }

    $stmt = $conn->prepare("SELECT s.student_id, s.admission_number, s.full_name, s.is_boarder, c.term_fee 
                             FROM students s 
                             JOIN classes c ON s.class_id = c.class_id 
                             WHERE s.class_id = ? AND s.status = 'Active' 
                             ORDER BY s.full_name ASC");
    $stmt->bind_param("i", $selected_class_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $due = $row['is_boarder'] ? $boarding_amount : $row['term_fee'];

        $paid_stmt = $conn->prepare("SELECT COALESCE(SUM(amount_paid),0) AS paid FROM fee_payments WHERE student_id = ? AND term_id = ?");
        $paid_stmt->bind_param("ii", $row['student_id'], $current_term['term_id']);
        $paid_stmt->execute();
        $paid = $paid_stmt->get_result()->fetch_assoc()['paid'];
        $paid_stmt->close();

        $balance = $due - $paid;
        $row['total_due'] = $due;
        $row['total_paid'] = $paid;
        $row['balance'] = $balance;

        if ($balance <= 0) {
            $paid_up[] = $row;
        } else {
            $owing[] = $row;
        }
    }
    $stmt->close();

    // Sort owing by highest balance first - most urgent to follow up
    usort($owing, function($a, $b) { return $b['balance'] <=> $a['balance']; });
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Fee Status by Class - Ummul Bannin Madrasah</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
    :root {
        --emerald: #14532d; --parchment: #faf6ee; --gold: #b8860b;
        --ink: #2b2b2b; --sage: #e3ede3; --sage-border: #cddccd; --white: #ffffff;
    }
    * { box-sizing: border-box; }
    body { font-family: 'Inter', sans-serif; background-color: var(--parchment); color: var(--ink); margin: 0; padding: 30px; }
    .container { max-width: 950px; margin: 0 auto; background: var(--white); padding: 30px 35px; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
    h1 { font-family: 'Amiri', serif; color: var(--emerald); font-size: 24px; margin-bottom: 5px; }
    .subtitle { color: #666; margin-bottom: 20px; font-size: 14px; }
    .back-link { font-size: 13px; margin-bottom: 20px; display: inline-block; color: var(--emerald); }

    .search-row { display: flex; gap: 12px; align-items: flex-end; margin-bottom: 10px; }
    .search-row > div { flex: 1; }
    label { display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px; }
    select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; font-size: 14px; }
    button { padding: 10px 20px; background-color: var(--emerald); color: white; border: none; border-radius: 5px; font-size: 14px; cursor: pointer; }
    button:hover { background-color: #0d3a1f; }

    .summary-bar {
        display: flex;
        gap: 16px;
        margin: 20px 0 30px 0;
    }
    .summary-pill {
        flex: 1;
        padding: 14px 18px;
        border-radius: 8px;
        text-align: center;
    }
    .summary-pill.paid { background-color: #d4edda; }
    .summary-pill.owing { background-color: #fdeeea; }
    .summary-pill .count { font-family: 'Amiri', serif; font-size: 26px; font-weight: 700; }
    .summary-pill.paid .count { color: #155724; }
    .summary-pill.owing .count { color: #c0392b; }
    .summary-pill .label { font-size: 12.5px; color: #555; margin-top: 3px; }

    .section-title {
        font-family: 'Amiri', serif;
        font-size: 18px;
        margin-top: 30px;
        margin-bottom: 12px;
        padding-bottom: 8px;
        border-bottom: 2px solid var(--sage);
    }
    .section-title.paid-title { color: #1b5e20; }
    .section-title.owing-title { color: #c0392b; }

    table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
    th, td { text-align: left; padding: 9px 8px; border-bottom: 1px solid #eee; }
    th { background-color: var(--sage); color: var(--emerald); font-size: 11.5px; text-transform: uppercase; letter-spacing: 0.4px; }

    .balance-owing { color: #c0392b; font-weight: 600; }
    .balance-clear { color: #1b5e20; font-weight: 600; }
    .empty-state { text-align: center; color: #999; padding: 25px 0; font-size: 14px; }
</style>
</head>
<body>
<div class="container">
    <a href="dashboard.php" class="back-link">&larr; Back to Dashboard</a>
    <h1>Fee Status by Class</h1>
    <p class="subtitle"><?php echo $current_term ? "Term " . $current_term['term_number'] . ", " . $current_term['academic_year'] : "No current term set"; ?></p>

    <form method="GET" action="">
        <div class="search-row">
            <div>
                <label for="class_id">Select a Class</label>
                <select id="class_id" name="class_id" required>
                    <option value="">-- Choose Class --</option>
                    <?php foreach ($all_classes as $c): ?>
                        <option value="<?php echo $c['class_id']; ?>" <?php echo $selected_class_id == $c['class_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['class_name'] . " (" . $c['section'] . ")"); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="flex:0;">
                <button type="submit">View Status</button>
            </div>
        </div>
    </form>

    <?php if ($selected_class_id && !$current_term): ?>
        <div class="empty-state">No current academic term is set. Go to Academic Terms and set one first.</div>
    <?php elseif ($selected_class && $current_term): ?>

        <div class="summary-bar">
            <div class="summary-pill paid">
                <div class="count"><?php echo count($paid_up); ?></div>
                <div class="label">Fully Paid</div>
            </div>
            <div class="summary-pill owing">
                <div class="count"><?php echo count($owing); ?></div>
                <div class="label">With Balances</div>
            </div>
        </div>

        <!-- Students with balances -->
        <div class="section-title owing-title">Students With Outstanding Balances</div>
        <?php if (empty($owing)): ?>
            <div class="empty-state">Nobody in this class owes anything. Everyone is paid up.</div>
        <?php else: ?>
        <table>
            <tr>
                <th>Admission #</th>
                <th>Name</th>
                <th>Boarding</th>
                <th>Total Due</th>
                <th>Paid</th>
                <th>Balance</th>
            </tr>
            <?php foreach ($owing as $o): ?>
            <tr>
                <td><?php echo htmlspecialchars($o['admission_number']); ?></td>
                <td><?php echo htmlspecialchars($o['full_name']); ?></td>
                <td><?php echo $o['is_boarder'] ? 'Yes' : 'No'; ?></td>
                <td>UGX <?php echo number_format($o['total_due']); ?></td>
                <td>UGX <?php echo number_format($o['total_paid']); ?></td>
                <td class="balance-owing">UGX <?php echo number_format($o['balance']); ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php endif; ?>

        <!-- Students fully paid -->
        <div class="section-title paid-title">Students Fully Paid</div>
        <?php if (empty($paid_up)): ?>
            <div class="empty-state">No one in this class has completed their fees yet.</div>
        <?php else: ?>
        <table>
            <tr>
                <th>Admission #</th>
                <th>Name</th>
                <th>Boarding</th>
                <th>Total Due</th>
                <th>Paid</th>
                <th>Balance</th>
            </tr>
            <?php foreach ($paid_up as $p): ?>
            <tr>
                <td><?php echo htmlspecialchars($p['admission_number']); ?></td>
                <td><?php echo htmlspecialchars($p['full_name']); ?></td>
                <td><?php echo $p['is_boarder'] ? 'Yes' : 'No'; ?></td>
                <td>UGX <?php echo number_format($p['total_due']); ?></td>
                <td>UGX <?php echo number_format($p['total_paid']); ?></td>
                <td class="balance-clear">UGX 0</td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php endif; ?>

    <?php endif; ?>
</div>
</body>
</html>
