<?php

header("Content-Type: application/json; charset=UTF-8");

$conn = new mysqli("localhost", "root", "", "control_acceso");

if ($conn->connect_error) {
    echo json_encode([]);
    exit();
}

$sql = "SELECT id, nombre, apellido, tipo_id, numero_identificacion, direccion, telefono, codigo, rfid, rol, estado_presencia, estado_usuario 
        FROM usuarios
        ORDER BY id DESC";

$result = $conn->query($sql);

$usuarios = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $usuarios[] = $row;
    }
}

echo json_encode($usuarios);

$conn->close();

?>