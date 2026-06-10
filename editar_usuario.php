<?php

$conn = new mysqli("localhost","root","","control_acceso");

if ($conn->connect_error) {
    echo "error";
    exit();
}

$id = $_POST['id'];
$nombre = $_POST['nombre'];
$apellido = $_POST['apellido'];
$tipo_id = $_POST['tipo_id'];
$numero_identificacion = $_POST['numero_identificacion'];
$direccion = $_POST['direccion'];
$telefono = $_POST['telefono'];
$codigo = $_POST['codigo'];
$rfid = $_POST['rfid'];
$rol = $_POST['rol'];

$sql = "UPDATE usuarios SET
nombre='$nombre',
apellido='$apellido',
tipo_id='$tipo_id',
numero_identificacion='$numero_identificacion',
direccion='$direccion',
telefono='$telefono',
codigo='$codigo',
rfid='$rfid',
rol='$rol'
WHERE id='$id'";

if ($conn->query($sql) === TRUE) {
    echo "ok";
} else {
    echo "error";
}

$conn->close();

?>