<?php

$conn = new mysqli("localhost","root","","control_acceso");

if ($conn->connect_error) {
    echo "error";
    exit();
}

$usuario = $_POST['usuario'];
$password = $_POST['password'];

$sql = "SELECT * FROM administradores WHERE usuario='$usuario'";
$result = $conn->query($sql);

if($result && $result->num_rows > 0){
    $row = $result->fetch_assoc();

    if($row['password'] == $password){
        echo "ok";
    }else{
        echo "error";
    }
}else{
    echo "error";
}

$conn->close();

?>