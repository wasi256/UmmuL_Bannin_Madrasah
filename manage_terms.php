<?php
include 'auth_check.php';
include 'db_connect.php';

$message = "";
$messageType = "";

// ------------------------------------------------------------
// Add a new term
// ------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === "add_term") {
    $academic_year = $_POST['academic_year'];
    $term_number = $_POST['term_number'];

    // Check it doesn't already exist
    $check = $conn->prepare("SELECT term_id FROM academic_terms WHERE academic_year = ? AND term_number = ?");
    $check->bind_param("ii", $academic_year, $term_number);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $message = "That term already exists.";
        $messageType = "error";
    } else {
        $stmt = $conn->prepare("INSERT INTO academic_terms (academic_year, term_number, is_current) VALUES (?, ?, 0)");
        $stmt->bind_param("ii", $academic_year, $term_number);
        if ($stmt->execute()) {
            $message = "Term added successfully. Set it as current when you're ready to start using it.";
            $messageType = "success";
        } else {
            $message = "Error adding term: " . $stmt->error;
            $messageType = "error";
        }
        $stmt->close();
    }
    $check->close();
}

// ------------------------------------------------------------
// Set a term as current (unsets all others first)
// ------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === "set_current") {
    $term_id = $_POST['term_id'];

    $conn->query("UPDATE academic_terms SET is_current = 0");
    $stmt = $conn->prepare("UPDATE academic_terms SET is_current = 1 WHERE term_id = ?");
    $stmt->bind_param("i", $term_id);
    if ($stmt->execute()) {
        $message = "Current term updated. All new fee payments will now be recorded under this term.";
        $messageType = "success";
    } else {
        $message = "Error updating current term: " . $stmt->error;
        $messageType = "error";
    }
    $stmt->close();
}

// ------------------------------------------------------------
// Fetch all terms
// ------------------------------------------------------------
$terms = [];
$result = $conn->query("SELECT * FROM academic_terms ORDER BY academic_year DESC, term_number DESC");
while ($row = $result->fetch_assoc()) {
    $terms[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage Academic Terms - Ummul Bannin Madrasah</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
    :root {
        --emerald: #14532d; --parchment: #faf6ee; --gold: #b8860b;
        --ink: #2b2b2b; --sage: #e3ede3; --sage-border: #cddccd; --white: #ffffff;
    }
    * { box-sizing: border-box; }
    body { font-family: 'Inter', sans-serif; background-color: var(--parchment); color: var(--ink); margin: 0; padding: 30px; }
    .container { max-width: 700px; margin: 0 auto; background: var(--white); padding: 30px 35px; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
    h1 { font-family: 'Amiri', serif; color: var(--emerald); font-size: 24px; margin-bottom: 5px; }
    .subtitle { color: #666; margin-bottom: 20px; font-size: 14px; }
    .back-link { font-size: 13px; margin-bottom: 20px; display: inline-block; color: var(--emerald); }
    .section-title { font-family: 'Amiri', serif; font-size: 18px; color: var(--emerald); margin-top: 30px; margin-bottom: 12px; }
    label { display: block; margin-top: 12px; margin-bottom: 5px; font-weight: 600; font-size: 13px; }
    input[type="number"], select { width: 100%; padding: 9px; border: 1px solid #ccc; border-radius: 5px; font-size: 14px; }
    button { padding: 10px 18px; background-color: var(--emerald); color: white; border: none; border-radius: 5px; font-size: 14px; cursor: pointer; }
    button:hover { background-color: #0d3a1f; }
    .message { padding: 12px; border-radius: 5px; margin-bottom: 15px; font-size: 14px; }
    .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    table { width: 100%; border-collapse: collapse; font-size: 14px; margin-top: 10px; }
    th, td { text-align: left; padding: 10px 8px; border-bottom: 1px solid #eee; }
    th { background-color: var(--sage); color: var(--emerald); font-size: 12px; text-transform: uppercase; }
    .current-badge { background-color: #d4edda; color: #155724; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; }
    .btn-small { padding: 6px 12px; font-size: 12px; }
    .form-row { display: flex; gap: 12px; align-items: flex-end; }
    .form-row > div { flex: 1; }
</style>
</head>
<body>
<div class="container">
    <a href="dashboard.php" class="back-link">&larr; Back to Dashboard</a>
    <h1>Manage Academic Terms</h1>
    <p class="subtitle">Add new terms and control which one is currently active</p>

    <?php if ($message): ?>
        <div class="message <?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <div class="section-title">Add New Term</div>
    <form method="POST" action="">
        <input type="hidden" name="action" value="add_term">
        <div class="form-row">
            <div>
                <label>Academic Year</label>
                <input type="number" name="academic_year" value="<?php echo date('Y'); ?>" required>
            </div>
            <div>
                <label>Term</label>
                <select name="term_number" required>
                    <option value="1">Term 1</option>
                    <option value="2">Term 2</option>
                    <option value="3">Term 3</option>
                </select>
            </div>
            <div style="flex:0;">
                <button type="submit">Add Term</button>
            </div>
        </div>
    </form>

    <div class="section-title">All Terms</div>
    <table>
        <tr>
            <th>Year</th>
            <th>Term</th>
            <th>Status</th>
            <th>Action</th>
        </tr>
        <?php foreach ($terms as $t): ?>
        <tr>
            <td><?php echo $t['academic_year']; ?></td>
            <td>Term <?php echo $t['term_number']; ?></td>
            <td>
                <?php if ($t['is_current']): ?>
                    <span class="current-badge">Current</span>
                <?php endif; ?>
            </td>
            <td>
                <?php if (!$t['is_current']): ?>
                <form method="POST" action="" onsubmit="return confirm('Switch the current term to Term <?php echo $t['term_number']; ?>, <?php echo $t['academic_year']; ?>? All new fee payments will be recorded under this term.');">
                    <input type="hidden" name="action" value="set_current">
                    <input type="hidden" name="term_id" value="<?php echo $t['term_id']; ?>">
                    <button type="submit" class="btn-small">Set as Current</button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
</body>
</html>
