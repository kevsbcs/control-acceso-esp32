<?php

$conn = new mysqli("localhost","root","","control_acceso");

if ($conn->connect_error) {
    echo "error";
    exit();
}

$id = $_POST['id'];
$estado = $_POST['estado'];

$sql = "UPDATE usuarios SET estado_usuario='$estado' WHERE id='$id'";
if ($conn->query($sql) === TRUE) {
    echo "ok";
} else {
    echo "error";
}

$conn->close();

?>