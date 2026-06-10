<?php

header("Content-Type: application/json; charset=UTF-8");

$conn = new mysqli("localhost", "root", "", "control_acceso");

if ($conn->connect_error) {
    echo json_encode([]);
    exit();
}

$fecha_inicio = isset($_GET['fecha_inicio']) ? trim($_GET['fecha_inicio']) : '';
$fecha_fin = isset($_GET['fecha_fin']) ? trim($_GET['fecha_fin']) : '';
$usuario = isset($_GET['usuario']) ? trim($_GET['usuario']) : '';

$sql = "SELECT * FROM historial_accesos";
$condiciones = [];

// Filtro por fecha inicio y fecha fin
if ($fecha_inicio !== "" && $fecha_fin !== "") {
    $condiciones[] = "fecha BETWEEN '" . $conn->real_escape_string($fecha_inicio) . "' AND '" . $conn->real_escape_string($fecha_fin) . "'";
} elseif ($fecha_inicio !== "") {
    $condiciones[] = "fecha >= '" . $conn->real_escape_string($fecha_inicio) . "'";
} elseif ($fecha_fin !== "") {
    $condiciones[] = "fecha <= '" . $conn->real_escape_string($fecha_fin) . "'";
}

// Filtro por usuario
if ($usuario !== "") {
    $condiciones[] = "nombre_usuario = '" . $conn->real_escape_string($usuario) . "'";
}

// Armar WHERE si hay filtros
if (count($condiciones) > 0) {
    $sql .= " WHERE " . implode(" AND ", $condiciones);
}

$sql .= " ORDER BY fecha DESC, hora DESC";

$result = $conn->query($sql);

$historial = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $historial[] = $row;
    }
}

echo json_encode($historial);

$conn->close();

?>