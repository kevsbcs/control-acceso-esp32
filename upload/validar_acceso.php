<?php
date_default_timezone_set('America/Bogota');
header("Content-Type: application/json; charset=UTF-8");

$conn = new mysqli("localhost","root","","control_acceso");

if ($conn->connect_error) {
    echo json_encode([
        "estado" => "ERROR",
        "mensaje" => "Error de conexión a la base de datos"
    ]);
    exit();
}

// -------- DATOS RECIBIDOS --------
$tipo = $_POST['tipo'] ?? $_GET['tipo'] ?? '';
$valor = $_POST['valor'] ?? $_GET['valor'] ?? '';
$dispositivo = $_POST['dispositivo'] ?? $_GET['dispositivo'] ?? '';

if ($tipo == "" || $valor == "") {
    echo json_encode([
        "estado" => "ERROR",
        "mensaje" => "Datos incompletos"
    ]);
    exit();
}

// -------- BUSCAR USUARIO --------
if ($tipo == "PIN") {
    $sql = "SELECT * FROM usuarios WHERE codigo='$valor' LIMIT 1";
} else if ($tipo == "RFID") {
    $valor_limpio = strtoupper(str_replace(" ", "", trim($valor)));
    $sql = "SELECT * FROM usuarios WHERE REPLACE(UPPER(TRIM(rfid)), ' ', '') = '$valor_limpio' LIMIT 1";
} else {
    echo json_encode([
        "estado" => "ERROR",
        "mensaje" => "Tipo inválido"
    ]);
    exit();
}

$result = $conn->query($sql);

if (!$result || $result->num_rows == 0) {
    echo json_encode([
        "estado" => "DENIED",
        "mensaje" => "Acceso denegado: usuario no encontrado"
    ]);
    exit();
}

$usuario = $result->fetch_assoc();

// -------- VALIDAR ESTADO USUARIO --------
if ($usuario['estado_usuario'] == 0) {
    echo json_encode([
        "estado" => "DENIED",
        "mensaje" => "Acceso denegado: usuario inactivo"
    ]);
    exit();
}

// -------- LOGICA ENTRADA / SALIDA --------
$id = $usuario['id'];
$nombre = $usuario['nombre'] . " " . $usuario['apellido'];

$fecha = date("Y-m-d");
$hora = date("H:i:s");

if ($dispositivo == "ENTRADA") {

    if ($usuario['estado_presencia'] == 1) {
        echo json_encode([
            "estado" => "DENIED",
            "mensaje" => "Acceso denegado: el usuario ya se encuentra dentro"
        ]);
        exit();
    }

    $nuevo_estado = 1;
    $tipo_movimiento = "ENTRADA";

} else if ($dispositivo == "SALIDA") {

    if ($usuario['estado_presencia'] == 0) {
        echo json_encode([
            "estado" => "DENIED",
            "mensaje" => "Acceso denegado: el usuario no registra entrada activa"
        ]);
        exit();
    }

    $nuevo_estado = 0;
    $tipo_movimiento = "SALIDA";

} else {
    echo json_encode([
        "estado" => "ERROR",
        "mensaje" => "Dispositivo no definido"
    ]);
    exit();
}

// -------- ACTUALIZAR PRESENCIA --------
$update = $conn->query("UPDATE usuarios SET estado_presencia='$nuevo_estado' WHERE id='$id'");

if (!$update) {
    echo json_encode([
        "estado" => "ERROR",
        "mensaje" => "No se pudo actualizar el estado de presencia"
    ]);
    exit();
}

// -------- GUARDAR HISTORIAL --------
$insert = $conn->query("INSERT INTO historial_accesos 
(nombre_usuario, numero_identificacion, rfid, pin, fecha, hora, resultado_acceso)
VALUES
('$nombre','".$usuario['numero_identificacion']."','".$usuario['rfid']."','".$usuario['codigo']."','$fecha','$hora','$tipo_movimiento')");

if (!$insert) {
    echo json_encode([
        "estado" => "ERROR",
        "mensaje" => "No se pudo guardar el historial de acceso"
    ]);
    exit();
}




// -------- NOTIFICAR A IA (PYTHON) --------
$url_ia = "http://localhost:5000/api/validacion";
$data_ia = array(
    'tipo' => $tipo_movimiento, // 'ENTRADA' o 'SALIDA'
    'usuario' => $nombre,
    'identificacion' => $usuario['numero_identificacion'],
    'metodo' => $tipo, // 'PIN' o 'RFID'
    'timestamp' => date('Y-m-d H:i:s')
);

// Enviar sin esperar respuesta para no afectar rendimiento
$opciones = array(
    'http' => array(
        'header' => "Content-Type: application/json\r\n",
        'method' => 'POST',
        'content' => json_encode($data_ia),
        'timeout' => 0.5 // Timeout corto para no bloquear
    )
);

$contexto = stream_context_create($opciones);
@file_get_contents($url_ia, false, $contexto); // @ suprime errores si IA no está corriendo


// -------- RESPUESTA --------
echo json_encode([
    "estado" => "GRANTED",
    "nombre" => $nombre,
    "movimiento" => $tipo_movimiento,
    "mensaje" => "Acceso permitido: $tipo_movimiento registrado correctamente"
]);

$conn->close();
?>