<?php

require('fpdf/fpdf.php');

$conn = new mysqli("localhost", "root", "", "control_acceso");

if ($conn->connect_error) {
    die("Error de conexión");
}

$fecha_inicio = isset($_GET['fecha_inicio']) ? trim($_GET['fecha_inicio']) : '';
$fecha_fin = isset($_GET['fecha_fin']) ? trim($_GET['fecha_fin']) : '';
$usuario = isset($_GET['usuario']) ? trim($_GET['usuario']) : '';

$sql = "SELECT nombre_usuario, numero_identificacion, rfid, pin, fecha, hora, resultado_acceso 
        FROM historial_accesos";

$condiciones = [];

// Filtro por fechas
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

// Agregar WHERE si hay filtros
if (count($condiciones) > 0) {
    $sql .= " WHERE " . implode(" AND ", $condiciones);
}

$sql .= " ORDER BY fecha DESC, hora DESC";

$result = $conn->query($sql);

$pdf = new FPDF('L', 'mm', 'A4');
$pdf->AddPage();

$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 10, utf8_decode('Reporte de Historial de Accesos'), 0, 1, 'C');

$pdf->SetFont('Arial', '', 10);

// Texto descriptivo de filtros aplicados
$descripcionFiltros = [];

if ($fecha_inicio !== "" && $fecha_fin !== "") {
    $descripcionFiltros[] = "Desde: $fecha_inicio Hasta: $fecha_fin";
} elseif ($fecha_inicio !== "") {
    $descripcionFiltros[] = "Desde: $fecha_inicio";
} elseif ($fecha_fin !== "") {
    $descripcionFiltros[] = "Hasta: $fecha_fin";
}

if ($usuario !== "") {
    $descripcionFiltros[] = "Usuario: $usuario";
}

if (count($descripcionFiltros) > 0) {
    $pdf->Cell(0, 8, utf8_decode(implode(' | ', $descripcionFiltros)), 0, 1, 'C');
} else {
    $pdf->Cell(0, 8, utf8_decode("Todos los registros"), 0, 1, 'C');
}

$pdf->Ln(5);

$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(45, 10, 'Usuario', 1, 0, 'C');
$pdf->Cell(35, 10, 'Identificacion', 1, 0, 'C');
$pdf->Cell(35, 10, 'RFID', 1, 0, 'C');
$pdf->Cell(25, 10, 'PIN', 1, 0, 'C');
$pdf->Cell(30, 10, 'Fecha', 1, 0, 'C');
$pdf->Cell(25, 10, 'Hora', 1, 0, 'C');
$pdf->Cell(45, 10, 'Resultado', 1, 1, 'C');

$pdf->SetFont('Arial', '', 8);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {

        $rfid_oculto = "";
        if (!empty($row['rfid'])) {
            $rfid_oculto = "****" . substr($row['rfid'], -4);
        }

        $pin_oculto = "";
        if (!empty($row['pin'])) {
            $pin_oculto = "****";
        }

        $pdf->Cell(45, 10, utf8_decode($row['nombre_usuario']), 1, 0, 'C');
        $pdf->Cell(35, 10, $row['numero_identificacion'], 1, 0, 'C');
        $pdf->Cell(35, 10, $rfid_oculto, 1, 0, 'C');
        $pdf->Cell(25, 10, $pin_oculto, 1, 0, 'C');
        $pdf->Cell(30, 10, $row['fecha'], 1, 0, 'C');
        $pdf->Cell(25, 10, $row['hora'], 1, 0, 'C');
        $pdf->Cell(45, 10, utf8_decode($row['resultado_acceso']), 1, 1, 'C');
    }
} else {
    $pdf->Cell(240, 10, utf8_decode('No hay registros para mostrar'), 1, 1, 'C');
}

$conn->close();

$pdf->Output('D', 'reporte_historial.pdf');

?>