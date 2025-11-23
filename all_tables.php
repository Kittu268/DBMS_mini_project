<?php
include 'header.php';
include 'db.php';

// 🔒 Ensure login
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

// ✅ Get all tables dynamically
$tables = [];
$result = $conn->query("SHOW TABLES");
if ($result) {
    while ($row = $result->fetch_array()) {
        $tables[] = $row[0];
    }
}

// ✅ Default message
$msg = "";

// 💾 CREATE
if ($_SERVER["REQUEST_METHOD"] == "POST" && $_POST['action'] === 'create_record') {
    $table = $_POST['table'];
    $fields = $_POST['fields'];
    $columns = implode(",", array_keys($fields));
    $placeholders = implode(",", array_fill(0, count($fields), "?"));
    $values = array_values($fields);
    $types = str_repeat('s', count($values));

    $stmt = $conn->prepare("INSERT INTO `$table` ($columns) VALUES ($placeholders)");
    $stmt->bind_param($types, ...$values);

    $msg = $stmt->execute()
        ? "✅ Record successfully added to '$table'."
        : "❌ Error adding record: " . $conn->error;
}

// ✏️ UPDATE
if ($_SERVER["REQUEST_METHOD"] == "POST" && $_POST['action'] === 'update_record') {
    $table = $_POST['table'];
    $pk = $_POST['primary_key'];
    $pk_value = $_POST['pk_value'];
    $fields = $_POST['fields'];

    $set_clause = implode(", ", array_map(fn($k) => "$k=?", array_keys($fields)));
    $values = array_values($fields);
    $types = str_repeat('s', count($values)) . "s";

    $stmt = $conn->prepare("UPDATE `$table` SET $set_clause WHERE $pk = ?");
    $stmt->bind_param($types, ...array_merge($values, [$pk_value]));

    $msg = $stmt->execute()
        ? "✏️ Record successfully updated in '$table'."
        : "❌ Error updating record: " . $conn->error;
}

// ❌ DELETE
if ($_SERVER["REQUEST_METHOD"] == "POST" && $_POST['action'] === 'delete_record') {
    $table = $_POST['table'];
    $pk = $_POST['primary_key'];
    $pk_value = $_POST['pk_value'];

    $stmt = $conn->prepare("DELETE FROM `$table` WHERE $pk = ?");
    $stmt->bind_param("s", $pk_value);

    $msg = $stmt->execute()
        ? "🗑️ Record successfully deleted from '$table'."
        : "❌ Error deleting record: " . $conn->error;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>📊 Manage All Tables | Airline Reservation System</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <style>
    /* 🌤 Sky Base */
    html, body {
      margin: 0;
      height: 100%;
      overflow-x: hidden;
      font-family: 'Poppins', sans-serif;
      background: linear-gradient(to bottom, #a1c4fd, #c2e9fb);
      position: relative;
      color: #0d47a1;
    }

    /* ☁️ Cloud Layers (Parallax) */
    .cloud-layer {
      position: absolute;
      width: 200%;
      height: 100%;
      background: url('images/cloud.png') repeat-x;
      background-size: contain;
      opacity: 0.45;
      top: 0;
      left: -50%;
      animation: moveClouds 55s linear infinite;
      z-index: 1;
    }
    .cloud-layer:nth-child(1) { animation-duration: 160s; top: 5%; opacity: 0.6; }
    .cloud-layer:nth-child(2) { animation-duration: 190s; top: 40%; opacity: 0.5; }
    .cloud-layer:nth-child(3) { animation-duration: 220s; top: 75%; opacity: 0.4; }

    @keyframes moveClouds {
      from { background-position-x: 0; }
      to { background-position-x: 10000px; }
    }

    /* ✈️ Airplane Animation */
    .plane {
      position: absolute;
      width: 2500px;
      top: -20%;
      left: -600px;
      opacity: 0.5;
      filter: drop-shadow(0 1px 1px rgba(0,0,0,0.25));
      z-index: 1;
      animation: flyInsideClouds 5555s ease-in-out infinite;
    }

    @keyframes flyInsideClouds {
      0% { transform: translate(0, 0) rotate(6deg); }
      50% { transform: translate(110vw, -6vh) rotate(-4deg); }
      100% { transform: translate(-200px, 3vh) rotate(6deg); }
    }

    /* 💎 Glass CRUD Section */
    .glass-box {
      position: relative;
      z-index: 5;
      background: rgba(255, 255, 255, 0.12);
      backdrop-filter: blur(10px);
      border-radius: 18px;
      box-shadow: 0 6px 25px rgba(0, 0, 0, 0.2);
      padding: 30px;
      width: 90%;
      max-width: 1100px;
      margin: 120px auto;
    }

    .table {
      background: rgba(255, 255, 255, 0.85);
    }

    h2 {
      text-align: center;
      font-weight: 700;
      margin-bottom: 25px;
      color: #0d47a1;
    }

    /* ✅ Popup Modal */
    .modal.fade.show {
      display: block;
      background: rgba(0,0,0,0.6);
    }
  </style>
</head>
<body>

  <!-- ☁️ Background Animation Layers -->
  <div class="cloud-layer"></div>
  <div class="cloud-layer"></div>
  <div class="cloud-layer"></div>
  <img src="images/airplane.png" alt="plane" class="plane">

  <div class="glass-box">
    <h2>📊 Manage All Tables</h2>

    <!-- CRUD Selector -->
    <div class="crud-panel mb-4">
      <form method="GET" class="row g-2 align-items-center">
        <div class="col-md-5">
          <select name="table" class="form-select" required>
            <option value="">🔽 Select Table</option>
            <?php foreach ($tables as $table): ?>
              <option value="<?= htmlspecialchars($table) ?>" <?= (isset($_GET['table']) && $_GET['table'] === $table) ? 'selected' : '' ?>>
                <?= ucfirst($table) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <select name="action" class="form-select" required>
            <option value="">⚡ Select Action</option>
            <option value="create" <?= ($_GET['action'] ?? '') === 'create' ? 'selected' : '' ?>>➕ Create</option>
            <option value="read" <?= ($_GET['action'] ?? '') === 'read' ? 'selected' : '' ?>>📋 Read</option>
            <option value="update" <?= ($_GET['action'] ?? '') === 'update' ? 'selected' : '' ?>>✏️ Update</option>
            <option value="delete" <?= ($_GET['action'] ?? '') === 'delete' ? 'selected' : '' ?>>❌ Delete</option>
          </select>
        </div>
        <div class="col-md-3">
          <button type="submit" class="btn btn-primary w-100">Go</button>
        </div>
      </form>
    </div>

    <?php
    if (isset($_GET['table']) && in_array($_GET['table'], $tables)) {
        $table_name = $_GET['table'];
        $action = $_GET['action'] ?? 'read';

        echo "<div class='card border-0 shadow-sm'>";
        echo "<div class='card-header bg-primary text-white fw-bold fs-5'>".ucfirst($table_name)."</div>";
        echo "<div class='card-body table-responsive'>";

        // READ Data
        $data = $conn->query("SELECT * FROM `$table_name`");
        if ($data && $data->num_rows > 0) {
            echo "<table class='table table-bordered table-striped text-center'>";
            echo "<thead class='table-light'><tr>";
            $fields = $data->fetch_fields();
            foreach ($fields as $field) echo "<th>{$field->name}</th>";
            echo "</tr></thead><tbody>";
            while ($row = $data->fetch_assoc()) {
                echo "<tr>";
                foreach ($row as $v) echo "<td>" . ($v === NULL || $v === '' ? '—' : htmlspecialchars($v)) . "</td>";
                echo "</tr>";
            }
            echo "</tbody></table>";
        } else {
            echo "<p class='text-muted'>No data found in this table.</p>";
        }

        // FORMS
        if ($action === 'create' || $action === 'update' || $action === 'delete') {
            $columns = $conn->query("DESCRIBE `$table_name`");
            $first_col = $columns->fetch_assoc();
            $pk = $first_col['Field'];
            $columns->data_seek(0);

            echo "<hr><form method='POST' class='mt-3'>";
            echo "<input type='hidden' name='table' value='$table_name'>";

            if ($action === 'create') {
                echo "<input type='hidden' name='action' value='create_record'>";
                echo "<h5 class='text-success'>➕ Add New Record</h5>";
                while ($col = $columns->fetch_assoc()) {
                    $field = htmlspecialchars($col['Field']);
                    echo "<div class='mb-2'><input type='text' name='fields[$field]' placeholder='$field' class='form-control'></div>";
                }
                echo "<button type='submit' class='btn btn-success mt-2'>Add Record</button>";
            }

            if ($action === 'update') {
                echo "<input type='hidden' name='action' value='update_record'>";
                echo "<input type='hidden' name='primary_key' value='$pk'>";
                echo "<h5 class='text-warning'>✏️ Update Record</h5>";
                echo "<input type='text' name='pk_value' placeholder='Enter $pk to Update' class='form-control mb-2'>";
                while ($col = $columns->fetch_assoc()) {
                    $field = htmlspecialchars($col['Field']);
                    echo "<div class='mb-2'><input type='text' name='fields[$field]' placeholder='New value for $field' class='form-control'></div>";
                }
                echo "<button type='submit' class='btn btn-warning mt-2'>Update Record</button>";
            }

            if ($action === 'delete') {
                echo "<input type='hidden' name='action' value='delete_record'>";
                echo "<input type='hidden' name='primary_key' value='$pk'>";
                echo "<h5 class='text-danger'>❌ Delete Record</h5>";
                echo "<input type='text' name='pk_value' placeholder='Enter $pk to Delete' class='form-control mb-2'>";
                echo "<button type='submit' class='btn btn-danger'>Delete Record</button>";
            }

            echo "</form>";
        }

        echo "</div></div>";
    }
    ?>
  </div>

  <!-- ✅ Popup Modal for CRUD Message -->
  <?php if (!empty($msg)): ?>
  <div class="modal fade show" id="statusModal" tabindex="-1" style="display:block;">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content text-center p-4">
        <?php if (str_starts_with($msg, '✅') || str_starts_with($msg, '✏️') || str_starts_with($msg, '🗑️')): ?>
          <h5 class="text-success"><?= $msg ?></h5>
        <?php else: ?>
          <h5 class="text-danger"><?= $msg ?></h5>
        <?php endif; ?>
        <button class="btn btn-primary mt-3" onclick="closeModal()">OK</button>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <script>
    function closeModal() {
      document.getElementById('statusModal').style.display = 'none';
      window.location.href = "all_tables.php";
    }
  </script>
</body>
</html>
