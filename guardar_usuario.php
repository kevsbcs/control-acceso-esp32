<?php

$conn = new mysqli("localhost","root","","control_acceso");

if ($conn->connect_error) {
    echo "error";
    exit();
}

$nombre = $_POST['nombre'];
$apellido = $_POST['apellido'];
$tipo_id = $_POST['tipo_id'];
$numero_identificacion = $_POST['numero_identificacion'];
$direccion = $_POST['direccion'];
$telefono = $_POST['telefono'];
$codigo = $_POST['codigo'];
$rfid = $_POST['rfid'];
$rol = $_POST['rol'];

$sql = "INSERT INTO usuarios 
(nombre, apellido, tipo_id, numero_identificacion, direccion, telefono, codigo, rfid, rol, estado_presencia, estado_usuario)
VALUES
('$nombre','$apellido','$tipo_id','$numero_identificacion','$direccion','$telefono','$codigo','$rfid','$rol',0,1)";

if ($conn->query($sql) === TRUE) {
    echo "ok";
} else {
    echo "error";
}

$conn->close();

?>