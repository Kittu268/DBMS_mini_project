<?php
include 'db.php';

if (!isset($_POST['table'], $_POST['column'], $_POST['newValue'], $_POST['pkCol'], $_POST['pkVal'])) {
    echo "missing parameters";
    exit;
}

$table = $_POST['table'];
$column = $_POST['column'];
$newValue = $_POST['newValue'];
$pkCol = $_POST['pkCol'];
$pkVal = $_POST['pkVal'];

// Escape identifiers safely
$table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
$column = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
$pkCol = preg_replace('/[^a-zA-Z0-9_]/', '', $pkCol);

// Prepare dynamic update query safely
$stmt = $conn->prepare("UPDATE `$table` SET `$column` = ? WHERE `$pkCol` = ?");
if(!$stmt){
    echo "prepare error: " . $conn->error;
    exit;
}
$stmt->bind_param("ss", $newValue, $pkVal);

if ($stmt->execute()) {
    echo "success";
} else {
    echo "error: " . $conn->error;
}
$stmt->close();
?>
