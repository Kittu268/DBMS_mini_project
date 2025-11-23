<?php
include 'db.php';

if (!isset($_POST['action'])) {
    echo "invalid request";
    exit;
}

// 🧱 STEP 1: Fetch column names for modal form
if ($_POST['action'] === 'get_columns') {
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['table']);
    $res = $conn->query("SHOW COLUMNS FROM `$table`");
    if ($res->num_rows > 0) {
        echo "<input type='hidden' name='table' value='$table'>";
        echo "<input type='hidden' name='action' value='insert_row'>";
        while ($col = $res->fetch_assoc()) {
            $name = htmlspecialchars($col['Field']);
            echo "
              <div class='mb-3'>
                <label class='form-label'>$name</label>
                <input type='text' class='form-control' name='cols[$name]' placeholder='Enter $name'>
              </div>
            ";
        }
    } else {
        echo "<p>No columns found for this table.</p>";
    }
    exit;
}

// 🧩 STEP 2: Insert new row
if ($_POST['action'] === 'insert_row') {
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['table']);
    $cols = $_POST['cols'] ?? [];
    if (empty($cols)) {
        echo "No data to insert.";
        exit;
    }

    $columns = array_keys($cols);
    $values = array_values($cols);

    $colList = implode("`,`", $columns);
    $placeholders = rtrim(str_repeat('?,', count($values)), ',');

    $stmt = $conn->prepare("INSERT INTO `$table` (`$colList`) VALUES ($placeholders)");
    $types = str_repeat('s', count($values));
    $stmt->bind_param($types, ...$values);

    if ($stmt->execute()) {
        echo "✅ Row inserted successfully!";
    } else {
        echo "❌ Insert failed: " . $conn->error;
    }
    exit;
}
?>
