<?php
date_default_timezone_set('America/Bogota');

$conn = new mysqli("localhost","root","","control_acceso");

if ($conn->connect_error) {
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

/* ================= ALERTA BD (YA EXISTE Y FUNCIONA) ================= */
function registrarAlerta($conn, $usuario, $tipo, $descripcion) {

    $fecha = date("Y-m-d");
    $hora = date("H:i:s");

    $conn->query("INSERT INTO alertas (fecha, hora, usuario, tipo_alerta, descripcion)
    VALUES ('$fecha','$hora','$usuario','$tipo','$descripcion')");
}

/* ================= CONFIG ================= */
$limite_minutos = 2;

/* ================= CONSULTA ENTRADAS ================= */
$sql = "SELECT * FROM historial_accesos 
        WHERE resultado_acceso = 'ENTRADA'
        AND alerta_enviada = 0";

$result = $conn->query($sql);

while ($row = $result->fetch_assoc()) {

    // 🔥 reconstruir datetime de entrada
    $entrada = $row['fecha'] . " " . $row['hora'];
    $ahora = date("Y-m-d H:i:s");

    $minutos = (strtotime($ahora) - strtotime($entrada)) / 60;

    if ($minutos >= $limite_minutos) {

        $nombre = $row['nombre_usuario'];

        // 🚨 MENSAJE TELEGRAM (NO CAMBIAMOS TU FORMATO)
        $mensaje = "🚨 ALERTA PERMANENCIA EXCEDIDA 🚨\n\n".
                   "Usuario: $nombre\n".
                   "ID: ".$row['numero_identificacion']."\n".
                   "RFID: ".$row['rfid']."\n".
                   "PIN: ".$row['pin']."\n\n".
                   "⏱ Tiempo dentro: ".round($minutos,2)." minutos\n".
                   "📍 Límite: $limite_minutos minutos\n".
                   "⚠ Estado: Permanencia excedida en bodega";

        // 🔥 TELEGRAM
        enviarTelegram($mensaje);

        // 🔥 ALERTA BD (TU SISTEMA YA FUNCIONA AQUÍ)
        registrarAlerta(
            $conn,
            $nombre,
            "PERMANENCIA_EXCEDIDA",
            "Tiempo: $minutos minutos"
        );

        // 🔥 MARCAR COMO ENVIADA (EVITA REPETICIÓN)
        $conn->query("UPDATE historial_accesos 
                      SET alerta_enviada = 1 
                      WHERE id = ".$row['id']);
    }
}

$conn->close();
?>