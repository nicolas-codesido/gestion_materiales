<?php
require_once 'includes/database.php';

$paginas = ['materiales', 'movimientos', 'validaciones', 'historial'];
$page = $_GET['page'] ?? 'materiales';
if (!in_array($page, $paginas)) {
    $page = 'materiales';
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Materiales</title>
    <link rel="stylesheet" href="assets/css/reset.css">
    <link rel="stylesheet" href="assets/css/styles.css">
</head>

<body>
    <header>
        <h1>Gestión de Materiales</h1>
        <nav>
            <a href="?page=materiales">Materiales</a>
            <a href="?page=movimientos">Movimientos</a>
            <a href="?page=validaciones">Validaciones</a>
        </nav>
    </header>
    <main>
        <?php require_once "pages/{$page}.php"; ?>
    </main>
</body>

</html>