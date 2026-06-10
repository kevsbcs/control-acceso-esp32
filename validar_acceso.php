<?php
date_default_timezone_set('America/Bogota');
header("Content-Type: application/json; charset=UTF-8");

$conn = new mysqli("localhost","root","","control_acceso");

if ($conn->connect_error) {
    echo json_encode(["estado"=>"ERROR","mensaje"=>"Error BD"]);
    exit();
}

/* ================= TELEGRAM ================= */
function enviarTelegram($mensaje) {

    $token = "8531877567:AAExU9f1vipEcsuGK5IhZKkKW632jmcQKDI";
    $chat_id = "7740714839";


    file_get_contents(
        "https://api.telegram.org/bot$token/sendMessage?chat_id=$chat_id&text=" . urlencode($mensaje)
    );
}

/* ================= ALERTA ================= */
function registrarAlerta($conn, $usuario, $tipo, $descripcion) {
    $conn->query("INSERT INTO alertas (fecha, hora, usuario, tipo_alerta, descripcion)
    VALUES (CURDATE(),CURTIME(),'$usuario','$tipo','$descripcion')");
}

/* ================= INTENTOS ================= */
function actualizarIntentos($conn, $valor, $tipo) {

    $q = $conn->query("SELECT * FROM intentos_fallidos WHERE identificador='$valor'");

    if ($q && $q->num_rows > 0) {
        $row = $q->fetch_assoc();
        $contador = $row['contador'] + 1;

        $conn->query("UPDATE intentos_fallidos 
            SET contador=$contador, ultima_fecha=CURDATE(), ultima_hora=CURTIME()
            WHERE identificador='$valor'");
    } else {
        $contador = 1;

        $conn->query("INSERT INTO intentos_fallidos 
            (identificador, tipo, contador, ultima_fecha, ultima_hora)
            VALUES ('$valor','$tipo',1,CURDATE(),CURTIME())");
    }

    return $contador;
}

/* ================= DATOS ================= */
$tipo = $_POST['tipo'] ?? '';
$valor = $_POST['valor'] ?? '';
$dispositivo = $_POST['dispositivo'] ?? '';

if ($tipo=="" || $valor=="") {
    echo json_encode(["estado"=>"ERROR","mensaje"=>"Datos incompletos"]);
    exit();
}

/* ================= BUSCAR ================= */
if ($tipo == "PIN") {
    $sql = "SELECT * FROM usuarios WHERE codigo='$valor' LIMIT 1";
} else {
    $valor_limpio = strtoupper(str_replace(" ","",trim($valor)));
    $sql = "SELECT * FROM usuarios 
            WHERE REPLACE(UPPER(TRIM(rfid)), ' ', '')='$valor_limpio' LIMIT 1";
}

$result = $conn->query($sql);

/* ================= NO EXISTE ================= */
if (!$result || $result->num_rows == 0) {

    $contador = actualizarIntentos($conn, $valor, $tipo);

    registrarAlerta($conn, "DESCONOCIDO ($valor)", "NO_ENCONTRADO", "Intento inválido");

    if ($contador >= 3) {
        enviarTelegram("🚨 3 intentos fallidos\nValor: $valor");
        $conn->query("UPDATE intentos_fallidos SET contador=0 WHERE identificador='$valor'");
    }

    echo json_encode(["estado"=>"DENIED","mensaje"=>"Usuario no encontrado"]);
    exit();
}

$usuario = $result->fetch_assoc();

/* ================= INACTIVO ================= */
if ($usuario['estado_usuario'] == 0) {

    $contador = actualizarIntentos($conn, $valor, $tipo);

    registrarAlerta($conn, $usuario['nombre'], "INACTIVO", "Usuario inactivo");

    if ($contador >= 3) {
        enviarTelegram("🚨 Usuario inactivo: ".$usuario['nombre']);
    }

    echo json_encode(["estado"=>"DENIED","mensaje"=>"Usuario inactivo"]);
    exit();
}

$id = $usuario['id'];
$nombre = $usuario['nombre']." ".$usuario['apellido'];

/* ================= VALIDACIONES ================= */

// ENTRADA duplicada
if ($dispositivo == "ENTRADA" && $usuario['estado_presencia'] == 1) {

    $contador = actualizarIntentos($conn, $valor, $tipo);

    registrarAlerta($conn, $nombre, "ENTRADA_DUPLICADA", "Ya está dentro");

    if ($contador >= 3) {
        enviarTelegram("🚨 Intentos repetidos de entrada: $nombre");
    }

    echo json_encode(["estado"=>"DENIED","mensaje"=>"Ya está dentro"]);
    exit();
}

// 🔥 SALIDA inválida (AQUÍ ESTABA EL ERROR)
if ($dispositivo == "SALIDA" && $usuario['estado_presencia'] == 0) {

    $contador = actualizarIntentos($conn, $valor, $tipo);

    registrarAlerta($conn, $nombre, "SALIDA_SIN_ENTRADA", "Intento inválido");

    if ($contador >= 3) {
        enviarTelegram("🚨 Intentos de salida inválidos: $nombre");
    }

    echo json_encode([
        "estado"=>"DENIED",
        "mensaje"=>"No registra entrada activa"
    ]);
    exit();
}

/* ================= ACCESO OK ================= */
$nuevo_estado = ($dispositivo=="ENTRADA") ? 1 : 0;
$mov = ($dispositivo=="ENTRADA") ? "ENTRADA" : "SALIDA";

$conn->query("UPDATE usuarios SET estado_presencia='$nuevo_estado' WHERE id='$id'");

$conn->query("INSERT INTO historial_accesos
(
    nombre_usuario,
    numero_identificacion,
    rfid,
    pin,
    fecha,
    hora,
    resultado_acceso
)
VALUES
(
    '$nombre',
    '".$usuario['numero_identificacion']."',
    '".$usuario['rfid']."',
    '".$usuario['codigo']."',
    CURDATE(),
    CURTIME(),
    '$mov'
)");
echo json_encode([
    "estado"=>"GRANTED",
    "nombre"=>$nombre,
    "movimiento"=>$mov,
    "mensaje"=>"Acceso permitido"
]);

$id_historial = $conn->insert_id;

$conn->close();
?>