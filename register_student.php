<?php
include 'auth_check.php';
include 'db_connect.php';

$message = "";
$messageType = "";

// ------------------------------------------------------------
// Handle form submission
// ------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $admission_number = trim($_POST['admission_number']);
    $full_name        = trim($_POST['full_name']);
    $gender           = $_POST['gender'];
    $date_of_birth    = $_POST['date_of_birth'];
    $class_id         = $_POST['class_id'];
    $is_boarder       = isset($_POST['is_boarder']) ? 1 : 0;
    $guardian_name    = trim($_POST['guardian_name']);
    $guardian_phone   = trim($_POST['guardian_phone']);

    // Basic validation
    if (empty($admission_number) || empty($full_name) || empty($class_id)) {
        $message = "Please fill in admission number, full name, and class.";
        $messageType = "error";
    } else {

        // Use a prepared statement to prevent SQL injection
        $stmt = $conn->prepare("INSERT INTO students 
            (admission_number, full_name, gender, date_of_birth, class_id, is_boarder, guardian_name, guardian_phone) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

        $stmt->bind_param("ssssiiss", 
            $admission_number, 
            $full_name, 
            $gender, 
            $date_of_birth, 
            $class_id, 
            $is_boarder, 
            $guardian_name, 
            $guardian_phone
        );

        if ($stmt->execute()) {
            $message = "Student registered successfully! <a href='manage_students.php?filter_class=" . $class_id . "' style='color:#155724; text-decoration:underline; font-weight:600;'>View all students in this class</a> or <a href='class_counts.php' style='color:#155724; text-decoration:underline; font-weight:600;'>see numbers per class</a>";
            $messageType = "success";
        } else {
            if ($conn->errno === 1062) {
                $message = "That admission number already exists. Please use a different one.";
            } else {
                $message = "Error saving student: " . $stmt->error;
            }
            $messageType = "error";
        }

        $stmt->close();
    }
}

// ------------------------------------------------------------
// Fetch classes for the dropdown
// ------------------------------------------------------------
$classes_result = $conn->query("SELECT class_id, class_name, section FROM classes ORDER BY class_id");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Register Student - Ummul Bannin Madrasah</title>
<style>
    * {
        box-sizing: border-box;
    }
    body {
        font-family: Arial, Helvetica, sans-serif;
        background-color: #f4f6f5;
        margin: 0;
        padding: 30px;
    }
    .container {
        max-width: 600px;
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
    input[type="date"],
    select {
        width: 100%;
        padding: 10px;
        border: 1px solid #ccc;
        border-radius: 5px;
        font-size: 14px;
    }
    .checkbox-row {
        display: flex;
        align-items: center;
        margin-top: 18px;
    }
    .checkbox-row input {
        width: auto;
        margin-right: 8px;
    }
    button {
        width: 100%;
        padding: 12px;
        margin-top: 25px;
        background-color: #1b5e20;
        color: white;
        border: none;
        border-radius: 5px;
        font-size: 16px;
        cursor: pointer;
    }
    button:hover {
        background-color: #164a1a;
    }
    .message {
        padding: 12px;
        border-radius: 5px;
        margin-bottom: 15px;
        font-size: 14px;
    }
    .success {
        background-color: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }
    .error {
        background-color: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
</style>
</head>
<body>

<div class="container">
    <img src="logo.png" alt="Ummul Bannin Madrasah Badge" class="logo">
    <h1>Ummul Bannin Madrasah</h1>
    <p class="subtitle">Student Registration</p>

    <?php if ($message): ?>
        <div class="message <?php echo $messageType; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">

        <label for="admission_number">Admission Number</label>
        <input type="text" id="admission_number" name="admission_number" required>

        <label for="full_name">Full Name</label>
        <input type="text" id="full_name" name="full_name" required>

        <label for="gender">Gender</label>
        <select id="gender" name="gender" required>
            <option value="">-- Select --</option>
            <option value="Male">Male</option>
            <option value="Female">Female</option>
        </select>

        <label for="date_of_birth">Date of Birth</label>
        <input type="date" id="date_of_birth" name="date_of_birth">

        <label for="class_id">Class</label>
        <select id="class_id" name="class_id" required>
            <option value="">-- Select Class --</option>
            <?php while ($row = $classes_result->fetch_assoc()): ?>
                <option value="<?php echo $row['class_id']; ?>">
                    <?php echo htmlspecialchars($row['class_name'] . " (" . $row['section'] . ")"); ?>
                </option>
            <?php endwhile; ?>
        </select>

        <div class="checkbox-row">
            <input type="checkbox" id="is_boarder" name="is_boarder">
            <label for="is_boarder" style="margin:0;">Boarding Student (+ 350,000)</label>
        </div>

        <label for="guardian_name">Guardian Name</label>
        <input type="text" id="guardian_name" name="guardian_name">

        <label for="guardian_phone">Guardian Phone</label>
        <input type="text" id="guardian_phone" name="guardian_phone">

        <button type="submit">Register Student</button>

    </form>
</div>

</body>
</html>
