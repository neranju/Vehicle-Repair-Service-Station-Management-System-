<?php
require_once 'db.php';

$result = $conn->query("SELECT staff_id, name, role_id FROM staff");
if ($result) {
    echo "<h2 style='color:green'>✅ Database connection successful!</h2>";
    echo "<table border='1' cellpadding='8'><tr><th>Staff ID</th><th>Name</th><th>Role ID</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr><td>{$row['staff_id']}</td><td>{$row['name']}</td><td>{$row['role_id']}</td></tr>";
    }
    echo "</table>";
} else {
    echo "<h2 style='color:red'>❌ Query failed: " . $conn->error . "</h2>";
}
$conn->close();
?>