<?php
date_default_timezone_set('America/Bogota');

$conn = new mysqli("localhost","root","","control_acceso");

if ($conn->connect_error) {
    die("Error de conexión");
}

/* ================= TELEGRAM ================= */
function enviarTelegram($mensaje) {

    $token = "8531877567:AAExU9f1vipEcsuGK5IhZKkKW632jmcQKDI";
    $chat_id = "7740714839";

    file_get_contents(
        "https://api.telegram.org/bot$token/sendMessage?chat_id=$chat_id&text=" . urlencode($mensaje)
    );
}

/* ================= ALERTA BD (OPCIONAL PERO RECOMENDADO) ================= */
function registrarAlerta($conn, $usuario, $tipo, $descripcion) {

    $fecha = date("Y-m-d");
    $hora = date("H:i:s");

    $conn->query("INSERT INTO alertas (fecha, hora, usuario, tipo_alerta, descripcion)
    VALUES ('$fecha','$hora','$usuario','$tipo','$descripcion')");
}

/* ================= USUARIOS DENTRO ================= */
$sql = "SELECT * FROM usuarios WHERE estado_presencia = 1 AND hora_entrada IS NOT NULL";
$result = $conn->query($sql);

$limite_minutos = 2;

while ($user = $result->fetch_assoc()) {

    $entrada = $user['hora_entrada'];
    $ahora = date("Y-m-d H:i:s");

    $minutos = (strtotime($ahora) - strtotime($entrada)) / 60;

    if ($minutos >= $limite_minutos) {

        $nombre = $user['nombre'];
        $apellido = $user['apellido'];
        $identificacion = $user['numero_identificacion'];
        $telefono = $user['telefono'];
        $rol = $user['rol'];
        $rfid = $user['rfid'];
        $codigo = $user['codigo'];

        $mensaje = "🚨 ALERTA DE PERMANENCIA EXCEDIDA 🚨\n\n".
                   "👤 Usuario: $nombre $apellido\n".
                   "🆔 ID: $identificacion\n".
                   "📞 Tel: $telefono\n".
                   "🎭 Rol: $rol\n".
                   "🔑 Código PIN: $codigo\n".
                   "📡 RFID: $rfid\n\n".
                   "⏱ Tiempo dentro: ".round($minutos,2)." minutos\n".
                   "📍 Estado: Dentro de la bodega\n".
                   "⚠ Límite permitido: $limite_minutos minutos\n\n".
                   "🔔 Sistema de seguridad activo";

        enviarTelegram($mensaje);

        registrarAlerta(
            $conn,
            $nombre." ".$apellido,
            "PERMANENCIA_EXCEDIDA",
            "Usuario dentro por ".round($minutos,2)." minutos"
        );

        // 🔥 EVITAR SPAM: actualizar hora_entrada para no repetir alerta
        $conn->query("UPDATE usuarios SET hora_entrada = NOW() WHERE id=".$user['id']);
    }
}

$conn->close();
?>