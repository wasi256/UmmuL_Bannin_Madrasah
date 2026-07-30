<?php
include 'auth_check.php';
include 'db_connect.php';

$message = "";
$messageType = "";

// ------------------------------------------------------------
// Handle restocking an existing item
// ------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === "restock") {
    $uniform_id = $_POST['uniform_id'];
    $add_qty = (int)$_POST['add_quantity'];

    if ($add_qty <= 0) {
        $message = "Please enter a quantity greater than zero.";
        $messageType = "error";
    } else {
        $stmt = $conn->prepare("UPDATE uniform_items SET quantity_in_stock = quantity_in_stock + ? WHERE uniform_id = ?");
        $stmt->bind_param("ii", $add_qty, $uniform_id);
        if ($stmt->execute()) {
            $message = "Stock updated successfully.";
            $messageType = "success";
        } else {
            $message = "Error updating stock: " . $stmt->error;
            $messageType = "error";
        }
        $stmt->close();
    }
}

// ------------------------------------------------------------
// Handle adding a brand new uniform item
// ------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === "add_item") {
    $item_name = trim($_POST['item_name']);
    $applicable_section = $_POST['applicable_section'];
    $applicable_gender = $_POST['applicable_gender'];
    $price = $_POST['price'];
    $initial_stock = (int)$_POST['initial_stock'];

    if (empty($item_name) || $price === "" || $price < 0) {
        $message = "Please fill in the item name and a valid price.";
        $messageType = "error";
    } else {
        $stmt = $conn->prepare("INSERT INTO uniform_items 
            (item_name, applicable_section, applicable_gender, price, quantity_in_stock) 
            VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssdi", $item_name, $applicable_section, $applicable_gender, $price, $initial_stock);
        if ($stmt->execute()) {
            $message = "New uniform item \"" . htmlspecialchars($item_name) . "\" added successfully.";
            $messageType = "success";
        } else {
            $message = "Error adding item: " . $stmt->error;
            $messageType = "error";
        }
        $stmt->close();
    }
}

// ------------------------------------------------------------
// Fetch all uniform items to display
// ------------------------------------------------------------
$items_result = $conn->query("SELECT * FROM uniform_items ORDER BY item_name");
$items = [];
while ($row = $items_result->fetch_assoc()) {
    $items[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage Uniforms - Ummul Bannin Madrasah</title>
<style>
    * { box-sizing: border-box; }
    body {
        font-family: Arial, Helvetica, sans-serif;
        background-color: #f4f6f5;
        margin: 0;
        padding: 30px;
    }
    .container {
        max-width: 750px;
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
    h2 {
        color: #1b5e20;
        font-size: 18px;
        margin-top: 35px;
        border-bottom: 2px solid #e0e0e0;
        padding-bottom: 8px;
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
    .restock-form {
        display: flex;
        gap: 6px;
        align-items: center;
    }
    .restock-form input {
        width: 70px;
        padding: 6px;
    }
    .restock-form button {
        margin-top: 0;
        padding: 6px 12px;
        font-size: 13px;
    }
    .low-stock { color: #c0392b; font-weight: bold; }
</style>
</head>
<body>

<div class="container">
        <a href="dashboard.php" class="back-link">&larr; Back to Dashboard</a>
    <img src="logo.png" alt="Ummul Bannin Madrasah Badge" class="logo">
    <h1>Ummul Bannin Madrasah</h1>
    <p class="subtitle">Manage Uniforms</p>

    <?php if ($message): ?>
        <div class="message <?php echo $messageType; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <h2>Current Uniform Stock</h2>
    <table>
        <tr>
            <th>Item</th>
            <th>Section</th>
            <th>Gender</th>
            <th>Price</th>
            <th>In Stock</th>
            <th>Add Stock</th>
        </tr>
        <?php foreach ($items as $item): ?>
            <tr>
                <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                <td><?php echo htmlspecialchars($item['applicable_section']); ?></td>
                <td><?php echo htmlspecialchars($item['applicable_gender']); ?></td>
                <td>UGX <?php echo number_format($item['price']); ?></td>
                <td class="<?php echo $item['quantity_in_stock'] == 0 ? 'low-stock' : ''; ?>">
                    <?php echo $item['quantity_in_stock']; ?>
                </td>
                <td>
                    <form method="POST" action="" class="restock-form">
                        <input type="hidden" name="action" value="restock">
                        <input type="hidden" name="uniform_id" value="<?php echo $item['uniform_id']; ?>">
                        <input type="number" name="add_quantity" min="1" placeholder="Qty" required>
                        <button type="submit">Add</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>

    <h2>Add a New Uniform Item</h2>
    <form method="POST" action="">
        <input type="hidden" name="action" value="add_item">

        <label for="item_name">Item Name</label>
        <input type="text" id="item_name" name="item_name" placeholder="e.g. Dress" required>

        <label for="applicable_section">Applies To Section</label>
        <select id="applicable_section" name="applicable_section" required>
            <option value="All">All Sections</option>
            <option value="Nursery">Nursery</option>
            <option value="Lower Primary">Lower Primary</option>
            <option value="Upper Primary">Upper Primary</option>
        </select>

        <label for="applicable_gender">Applies To Gender</label>
        <select id="applicable_gender" name="applicable_gender" required>
            <option value="All">All (Boys & Girls)</option>
            <option value="Female">Girls Only</option>
            <option value="Male">Boys Only</option>
        </select>

        <label for="price">Price (UGX)</label>
        <input type="number" id="price" name="price" min="0" required>

        <label for="initial_stock">Initial Stock Quantity</label>
        <input type="number" id="initial_stock" name="initial_stock" min="0" value="0" required>

        <button type="submit">Add Item</button>
    </form>

</div>

</body>
</html>
