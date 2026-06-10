<?php
include "conexion.php";
date_default_timezone_set("America/Bogota");
function enviarTelegram($mensaje){

    $token = "8531877567:AAExU9f1vipEcsuGK5IhZKkKW632jmcQKDI";
    $chat_id = "7740714839";

    $url = "https://api.telegram.org/bot$token/sendMessage";

    $data = [
        "chat_id" => $chat_id,
        "text" => $mensaje
    ];

    $options = [
        "http" => [
            "header"  => "Content-Type: application/x-www-form-urlencoded\r\n",
            "method"  => "POST",
            "content" => http_build_query($data),
        ]
    ];

    $context = stream_context_create($options);
    file_get_contents($url, false, $context);
}
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// ================= ALERTAS DESDE ESP32 =================

if(isset($_POST['alerta_uid'])){

    $uid = $_POST['alerta_uid'];
    $fecha = date("Y-m-d");
    $hora = date("H:i:s");

    $mensaje = "🚨 ALERTA 🚨\nIntento con puerta abierta\nUID: $uid\nHora: $hora";

    enviarTelegram($mensaje);

    mysqli_query($conexion, "INSERT INTO alertas 
    (fecha, hora, usuario, tipo_alerta, descripcion) 
    VALUES 
    ('$fecha', '$hora', 'DESCONOCIDO', 'PUERTA_ABIERTA', 'Intento con RFID con puerta abierta')");

    echo "alerta";
    exit();
}

if(isset($_POST['alerta_pin'])){

    $pin = $_POST['alerta_pin'];
    $fecha = date("Y-m-d");
    $hora = date("H:i:s");

    $mensaje = "🚨 ALERTA 🚨\nIntento con puerta abierta\nPIN: $pin\nHora: $hora";

    enviarTelegram($mensaje);

    mysqli_query($conexion, "INSERT INTO alertas 
    (fecha, hora, usuario, tipo_alerta, descripcion) 
    VALUES 
    ('$fecha', '$hora', 'DESCONOCIDO', 'PUERTA_ABIERTA', 'Intento con PIN con puerta abierta')");

    echo "alerta";
    exit();
}
// ================= RECIBIR DATOS =================
$uid = $_POST['uid'] ?? null;
$pin = $_POST['pin'] ?? null;

// ================= VALIDAR MÉTODO =================
if($uid){
    $sql = "SELECT * FROM usuarios WHERE rfid = '$uid'";
} 
else if($pin){
    $sql = "SELECT * FROM usuarios WHERE codigo = '$pin'";
} 
else {
    echo "sin_datos";
    exit;
}

$resultado = mysqli_query($conexion, $sql);

// ================= SI EXISTE USUARIO =================
if(mysqli_num_rows($resultado) > 0){

    $usuario = mysqli_fetch_assoc($resultado);

    $id_usuario = $usuario['id'];
    $nombre = $usuario['nombre'] . " " . $usuario['apellido'];
    $identificacion = $usuario['numero_identificacion'];
    $rfid = $usuario['rfid'];
    $codigo = $usuario['codigo'];

    // 🔥 NORMALIZACIÓN (CLAVE)
    $ubicacion = strtolower(trim($usuario['ubicacion']));
    $estado = strtolower(trim($usuario['estado']));

    $fecha = date("Y-m-d");
    $hora = date("H:i:s");

    // ================= USUARIO INACTIVO =================
    if($estado === "inactivo"){

        mysqli_query($conexion, "INSERT INTO alertas 
        (fecha, hora, usuario, tipo_alerta, descripcion) 
        VALUES 
        ('$fecha', '$hora', '$nombre', 'USUARIO_INACTIVO', 'Intento de acceso con usuario inactivo')");

        mysqli_query($conexion, "INSERT INTO historial_accesos 
        (nombre_usuario, numero_identificacion, rfid, pin, fecha, hora, resultado_acceso) 
        VALUES 
        ('$nombre', '$identificacion', '$rfid', '$codigo', '$fecha', '$hora', 'DENEGADO_INACTIVO')");

        $mensaje = "⚠️ USUARIO INACTIVO\n$nombre intentó acceder\nHora: $hora";
enviarTelegram($mensaje);

echo "inactivo";
        exit;
    }

    // ================= LÓGICA ENTRADA / SALIDA =================

    if($ubicacion !== "bodega"){ // ====== ENTRADA ======

        // Registrar apertura
        mysqli_query($conexion, "INSERT INTO control_puerta 
        (usuario_id, fecha, hora_apertura, tipo_acceso) 
        VALUES 
        ('$id_usuario', '$fecha', '$hora', 'entrada')");

        // Historial
        mysqli_query($conexion, "INSERT INTO historial_accesos 
        (nombre_usuario, numero_identificacion, rfid, pin, fecha, hora, resultado_acceso) 
        VALUES 
        ('$nombre', '$identificacion', '$rfid', '$codigo', '$fecha', '$hora', 'ENTRADA')");

        // Cambiar ubicación
        mysqli_query($conexion, "UPDATE usuarios SET ubicacion='Bodega' WHERE id='$id_usuario'");

        echo "ok_entrada";

    } else { // ====== SALIDA ======

        // Registrar apertura de salida
        mysqli_query($conexion, "INSERT INTO control_puerta 
        (usuario_id, fecha, hora_apertura, tipo_acceso) 
        VALUES 
        ('$id_usuario', '$fecha', '$hora', 'salida')");

        // Historial
        mysqli_query($conexion, "INSERT INTO historial_accesos 
        (nombre_usuario, numero_identificacion, rfid, pin, fecha, hora, resultado_acceso) 
        VALUES 
        ('$nombre', '$identificacion', '$rfid', '$codigo', '$fecha', '$hora', 'SALIDA')");

        // Cambiar ubicación
        mysqli_query($conexion, "UPDATE usuarios SET ubicacion='Fuera' WHERE id='$id_usuario'");

        echo "ok_salida";
    }

} else {

    $fecha = date("Y-m-d");
    $hora = date("H:i:s");

    mysqli_query($conexion, "INSERT INTO alertas 
    (fecha, hora, usuario, tipo_alerta, descripcion) 
    VALUES 
    ('$fecha', '$hora', 'DESCONOCIDO', 'INTRUSO', 'Intento de acceso no reconocido')");
    $mensaje = "🚨 INTRUSO DETECTADO\nIntento no reconocido\nHora: $hora";
enviarTelegram($mensaje);
    echo "no_encontrado";
}
?>