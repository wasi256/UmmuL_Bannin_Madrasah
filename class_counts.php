<?php
include 'auth_check.php';
include 'db_connect.php';

// ------------------------------------------------------------
// Count of active students per class
// ------------------------------------------------------------
$class_counts = [];
$total_students = 0;

$result = $conn->query("SELECT c.class_id, c.class_name, c.section, 
                         COUNT(s.student_id) AS student_count 
                         FROM classes c 
                         LEFT JOIN students s ON s.class_id = c.class_id AND s.status = 'Active' 
                         GROUP BY c.class_id 
                         ORDER BY c.class_id ASC");
while ($row = $result->fetch_assoc()) {
    $class_counts[] = $row;
    $total_students += $row['student_count'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Class Numbers - Ummul Bannin Madrasah</title>
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

    .total-banner {
        background-color: var(--sage);
        border: 1px solid var(--sage-border);
        border-radius: 8px;
        padding: 18px;
        text-align: center;
        margin-bottom: 25px;
    }
    .total-banner .count {
        font-family: 'Amiri', serif;
        font-size: 30px;
        font-weight: 700;
        color: var(--emerald);
    }
    .total-banner .label { font-size: 13px; color: #555; margin-top: 3px; }

    table { width: 100%; border-collapse: collapse; font-size: 14px; }
    th, td { text-align: left; padding: 12px 10px; border-bottom: 1px solid #eee; }
    th { background-color: var(--sage); color: var(--emerald); font-size: 12px; text-transform: uppercase; letter-spacing: 0.4px; }

    .count-badge {
        display: inline-block;
        background-color: var(--emerald);
        color: white;
        padding: 4px 12px;
        border-radius: 14px;
        font-weight: 700;
        font-size: 14px;
        min-width: 30px;
        text-align: center;
    }
    .count-badge.zero { background-color: #999; }

    .view-link {
        font-size: 12.5px;
        color: var(--emerald);
        text-decoration: none;
        font-weight: 600;
    }
    .view-link:hover { text-decoration: underline; }
</style>
</head>
<body>
<div class="container">
    <a href="dashboard.php" class="back-link">&larr; Back to Dashboard</a>
    <h1>Class Numbers</h1>
    <p class="subtitle">How many students are currently in each class</p>

    <div class="total-banner">
        <div class="count"><?php echo $total_students; ?></div>
        <div class="label">Total Active Students, School-Wide</div>
    </div>

    <table>
        <tr>
            <th>Class</th>
            <th>Section</th>
            <th>Number of Students</th>
            <th></th>
        </tr>
        <?php foreach ($class_counts as $c): ?>
        <tr>
            <td><?php echo htmlspecialchars($c['class_name']); ?></td>
            <td><?php echo htmlspecialchars($c['section']); ?></td>
            <td>
                <span class="count-badge <?php echo $c['student_count'] == 0 ? 'zero' : ''; ?>">
                    <?php echo $c['student_count']; ?>
                </span>
            </td>
            <td>
                <a href="manage_students.php?filter_class=<?php echo $c['class_id']; ?>" class="view-link">View Names &rarr;</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
</body>
</html>
