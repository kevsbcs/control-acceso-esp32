<?php

header("Content-Type: application/json; charset=UTF-8");

$conn = new mysqli("localhost","root","","control_acceso");

if ($conn->connect_error) {
    echo json_encode([]);
    exit();
}

$sql = "SELECT * FROM alertas ORDER BY fecha DESC, hora DESC";

$result = $conn->query($sql);

$alertas = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $alertas[] = $row;
    }
}

echo json_encode($alertas);

$conn->close();

?>