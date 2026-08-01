<?php
include 'auth_check.php';
include 'db_connect.php';

$message = "";
$messageType = "";

// ------------------------------------------------------------
// Handle delete (removes student + their related records safely)
// ------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === "delete_student") {
    $student_id = $_POST['student_id'];

    $conn->begin_transaction();
    try {
        $conn->query("DELETE FROM fee_payments WHERE student_id = " . intval($student_id));
        $conn->query("DELETE FROM fee_discounts WHERE student_id = " . intval($student_id));
        $conn->query("DELETE FROM uniform_issues WHERE student_id = " . intval($student_id));
        $conn->query("DELETE FROM students WHERE student_id = " . intval($student_id));
        $conn->commit();
        $message = "Student and all their related records were deleted.";
        $messageType = "success";
    } catch (Exception $e) {
        $conn->rollback();
        $message = "Error deleting student: " . $e->getMessage();
        $messageType = "error";
    }
}

// ------------------------------------------------------------
// Handle edit / update
// ------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === "update_student") {
    $student_id     = $_POST['student_id'];
    $full_name      = trim($_POST['full_name']);
    $gender         = $_POST['gender'];
    $date_of_birth  = $_POST['date_of_birth'];
    $class_id       = $_POST['class_id'];
    $is_boarder     = isset($_POST['is_boarder']) ? 1 : 0;
    $guardian_name  = trim($_POST['guardian_name']);
    $guardian_phone = trim($_POST['guardian_phone']);
    $status         = $_POST['status'];

    $stmt = $conn->prepare("UPDATE students SET 
        full_name = ?, gender = ?, date_of_birth = ?, class_id = ?, 
        is_boarder = ?, guardian_name = ?, guardian_phone = ?, status = ? 
        WHERE student_id = ?");
    $stmt->bind_param("sssiisssi", $full_name, $gender, $date_of_birth, $class_id, 
                       $is_boarder, $guardian_name, $guardian_phone, $status, $student_id);

    if ($stmt->execute()) {
        $message = "Student updated successfully.";
        $messageType = "success";
    } else {
        $message = "Error updating student: " . $stmt->error;
        $messageType = "error";
    }
    $stmt->close();
}

// ------------------------------------------------------------
// Handle search/filter (Clear button just reloads with no params)
// ------------------------------------------------------------
$search = isset($_GET['search']) ? trim($_GET['search']) : "";
$filter_class = isset($_GET['filter_class']) ? $_GET['filter_class'] : "";

$query = "SELECT s.*, c.class_name, c.section FROM students s JOIN classes c ON s.class_id = c.class_id WHERE 1=1";
$params = [];
$types = "";

if ($search !== "") {
    $query .= " AND (s.full_name LIKE ? OR s.admission_number LIKE ?)";
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $types .= "ss";
}
if ($filter_class !== "") {
    $query .= " AND s.class_id = ?";
    $params[] = $filter_class;
    $types .= "i";
}
$query .= " ORDER BY c.class_id ASC, s.full_name ASC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$students_result = $stmt->get_result();
$students = [];
while ($row = $students_result->fetch_assoc()) {
    $students[] = $row;
}
$stmt->close();

// All classes for dropdowns
$classes_result = $conn->query("SELECT class_id, class_name, section FROM classes ORDER BY class_id");
$all_classes = [];
while ($row = $classes_result->fetch_assoc()) {
    $all_classes[] = $row;
}

$editing_id = isset($_GET['edit']) ? (int)$_GET['edit'] : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage Students - Ummul Bannin Madrasah</title>
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

    label { display: block; margin-top: 12px; margin-bottom: 5px; font-weight: 600; color: #333; font-size: 13px; }
    input[type="text"], input[type="date"], select {
        width: 100%; padding: 9px; border: 1px solid #ccc; border-radius: 5px; font-size: 14px;
    }
    button {
        padding: 10px 18px; background-color: var(--emerald); color: white; border: none;
        border-radius: 5px; font-size: 14px; cursor: pointer;
    }
    button:hover { background-color: #0d3a1f; }

    .filter-row {
        display: flex;
        gap: 12px;
        align-items: flex-end;
        margin-bottom: 25px;
        flex-wrap: wrap;
    }
    .filter-row > div { flex: 1; min-width: 160px; }
    .filter-row button { white-space: nowrap; }
    .btn-clear { background-color: #888; }
    .btn-clear:hover { background-color: #666; }

    .message { padding: 12px; border-radius: 5px; margin-bottom: 15px; font-size: 14px; }
    .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    .error   { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

    table { width: 100%; border-collapse: collapse; font-size: 13px; }
    th, td { text-align: left; padding: 9px 8px; border-bottom: 1px solid #eee; vertical-align: middle; }
    th { background-color: var(--sage); color: var(--emerald); font-size: 11.5px; text-transform: uppercase; letter-spacing: 0.4px; }

    .row-actions { display: flex; gap: 6px; }
    .btn-small { padding: 6px 10px; font-size: 12px; border-radius: 4px; }
    .btn-edit { background-color: #e0a800; }
    .btn-edit:hover { background-color: #c69500; }
    .btn-delete { background-color: #c0392b; }
    .btn-delete:hover { background-color: #a5281c; }
    .btn-cancel { background-color: #999; }

    .edit-panel {
        margin: 10px 0; padding: 18px; background-color: #fff8e6;
        border: 1px solid #ffe8a1; border-radius: 6px;
    }
    .edit-panel h4 { margin: 0 0 5px 0; color: #856404; font-family: 'Amiri', serif; font-size: 16px; }
    .edit-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0 15px; }
    .checkbox-row { display: flex; align-items: center; gap: 8px; margin-top: 15px; }
    .checkbox-row input { width: auto; }

    .status-badge {
        padding: 3px 8px; border-radius: 10px; font-size: 11px; font-weight: 600;
    }
    .status-Active { background-color: #d4edda; color: #155724; }
    .status-Graduated { background-color: #d1ecf1; color: #0c5460; }
    .status-Withdrawn { background-color: #f8d7da; color: #721c24; }

    .empty-state { text-align: center; color: #999; padding: 30px 0; }
</style>
</head>
<body>

<div class="container">
    <a href="dashboard.php" class="back-link">&larr; Back to Dashboard</a>
    <h1>Manage Students</h1>
    <p class="subtitle">View, edit, or remove student records</p>

    <?php if ($message): ?>
        <div class="message <?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <!-- Search / filter -->
    <form method="GET" action="">
        <div class="filter-row">
            <div>
                <label for="search">Search by Name or Admission #</label>
                <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="e.g. Amina or UB-001">
            </div>
            <div>
                <label for="filter_class">Filter by Class</label>
                <select id="filter_class" name="filter_class">
                    <option value="">All Classes</option>
                    <?php foreach ($all_classes as $c): ?>
                        <option value="<?php echo $c['class_id']; ?>" <?php echo $filter_class == $c['class_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['class_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="flex: 0;">
                <button type="submit">Search</button>
            </div>
            <div style="flex: 0;">
                <a href="manage_students.php"><button type="button" class="btn-clear">Clear</button></a>
            </div>
        </div>
    </form>

    <a href="register_student.php"><button type="button" style="margin-bottom:20px;">+ Add New Student</button></a>

    <?php if (empty($students)): ?>
        <div class="empty-state">No students found matching your search.</div>
    <?php else: ?>
    <table>
        <tr>
            <th>Admission #</th>
            <th>Name</th>
            <th>Class</th>
            <th>Gender</th>
            <th>Boarding</th>
            <th>Guardian</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>
        <?php foreach ($students as $s): ?>
            <?php if ($editing_id === (int)$s['student_id']): ?>
            <tr>
                <td colspan="8">
                    <div class="edit-panel">
                        <h4>Editing: <?php echo htmlspecialchars($s['full_name']); ?> (<?php echo htmlspecialchars($s['admission_number']); ?>)</h4>
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="update_student">
                            <input type="hidden" name="student_id" value="<?php echo $s['student_id']; ?>">

                            <div class="edit-grid">
                                <div>
                                    <label>Full Name</label>
                                    <input type="text" name="full_name" value="<?php echo htmlspecialchars($s['full_name']); ?>" required>
                                </div>
                                <div>
                                    <label>Gender</label>
                                    <select name="gender" required>
                                        <option value="Male" <?php echo $s['gender'] === 'Male' ? 'selected' : ''; ?>>Male</option>
                                        <option value="Female" <?php echo $s['gender'] === 'Female' ? 'selected' : ''; ?>>Female</option>
                                    </select>
                                </div>
                                <div>
                                    <label>Date of Birth</label>
                                    <input type="date" name="date_of_birth" value="<?php echo htmlspecialchars($s['date_of_birth']); ?>">
                                </div>
                                <div>
                                    <label>Class</label>
                                    <select name="class_id" required>
                                        <?php foreach ($all_classes as $c): ?>
                                            <option value="<?php echo $c['class_id']; ?>" <?php echo $s['class_id'] == $c['class_id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($c['class_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label>Guardian Name</label>
                                    <input type="text" name="guardian_name" value="<?php echo htmlspecialchars($s['guardian_name']); ?>">
                                </div>
                                <div>
                                    <label>Guardian Phone</label>
                                    <input type="text" name="guardian_phone" value="<?php echo htmlspecialchars($s['guardian_phone']); ?>">
                                </div>
                                <div>
                                    <label>Status</label>
                                    <select name="status" required>
                                        <option value="Active" <?php echo $s['status'] === 'Active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="Graduated" <?php echo $s['status'] === 'Graduated' ? 'selected' : ''; ?>>Graduated</option>
                                        <option value="Withdrawn" <?php echo $s['status'] === 'Withdrawn' ? 'selected' : ''; ?>>Withdrawn</option>
                                    </select>
                                </div>
                            </div>

                            <div class="checkbox-row">
                                <input type="checkbox" id="is_boarder_<?php echo $s['student_id']; ?>" name="is_boarder" <?php echo $s['is_boarder'] ? 'checked' : ''; ?>>
                                <label for="is_boarder_<?php echo $s['student_id']; ?>" style="margin:0;">Boarding Student</label>
                            </div>

                            <div style="display:flex; gap:8px; margin-top:15px;">
                                <button type="submit" class="btn-small btn-edit">Save Changes</button>
                                <a href="manage_students.php?search=<?php echo urlencode($search); ?>&filter_class=<?php echo urlencode($filter_class); ?>">
                                    <button type="button" class="btn-small btn-cancel">Cancel</button>
                                </a>
                            </div>
                        </form>
                    </div>
                </td>
            </tr>
            <?php else: ?>
            <tr>
                <td><?php echo htmlspecialchars($s['admission_number']); ?></td>
                <td><?php echo htmlspecialchars($s['full_name']); ?></td>
                <td><?php echo htmlspecialchars($s['class_name']); ?></td>
                <td><?php echo htmlspecialchars($s['gender']); ?></td>
                <td><?php echo $s['is_boarder'] ? 'Yes' : 'No'; ?></td>
                <td><?php echo htmlspecialchars($s['guardian_name']) ?: '—'; ?></td>
                <td><span class="status-badge status-<?php echo $s['status']; ?>"><?php echo htmlspecialchars($s['status']); ?></span></td>
                <td>
                    <div class="row-actions">
                        <a href="?search=<?php echo urlencode($search); ?>&filter_class=<?php echo urlencode($filter_class); ?>&edit=<?php echo $s['student_id']; ?>">
                            <button type="button" class="btn-small btn-edit">Edit</button>
                        </a>
                        <form method="POST" action="" onsubmit="return confirm('Delete <?php echo htmlspecialchars(addslashes($s['full_name'])); ?>? This also deletes all their fee payment, discount, and uniform records. This cannot be undone.');" style="display:inline;">
                            <input type="hidden" name="action" value="delete_student">
                            <input type="hidden" name="student_id" value="<?php echo $s['student_id']; ?>">
                            <button type="submit" class="btn-small btn-delete">Delete</button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endif; ?>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>

</div>

</body>
</html>
