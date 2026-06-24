<?php
$host = 'localhost';
$dbname = 'gestion_materiales_nicolas';
$username = 'root';
// Al ser una prueba lo dejo sin contraseña para facilitar el acceso
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Error de conexión a la base de datos.');
}
?>