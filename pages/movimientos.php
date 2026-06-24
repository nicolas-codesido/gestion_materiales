<?php
$error = '';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $material_id = (int) ($_POST['material_id'] ?? 0);
    $destino_id = (int) ($_POST['destino_id'] ?? 0);

    if ($material_id > 0 && $destino_id > 0) {

        // Estado y ubicación actual del material (solo si está activo)
        $stmt = $pdo->prepare(
            "SELECT m.estado, m.localizacion_actual_id, l.tipo AS origen_tipo
             FROM material m
             LEFT JOIN localizacion l ON m.localizacion_actual_id = l.id
             WHERE m.id = ? AND m.activo = 1"
        );
        $stmt->execute([$material_id]);
        $actual = $stmt->fetch();

        // Tipo y nombre del destino
        $stmt = $pdo->prepare("SELECT tipo, nombre FROM localizacion WHERE id = ?");
        $stmt->execute([$destino_id]);
        $destino = $stmt->fetch();

        if (!$actual) {
            $error = 'El material no existe o no está activo.';
        } elseif (!$destino) {
            $error = 'El destino no existe.';
        } else {
            $origen_id = $actual['localizacion_actual_id'] !== null
                ? (int) $actual['localizacion_actual_id']
                : null;
            $origen_tipo = $actual['origen_tipo'] ?? null;
            $tipo_destino = $destino['tipo'];


            $destinos_validos = [
                'LUGAR' => ['PERSONA'],
                'PERSONA' => ['LUGAR', 'PERSONA'],
            ];
            $combinacion_valida = in_array(
                $tipo_destino,
                $destinos_validos[$origen_tipo] ?? [],
                true
            );

            if ($actual['estado'] === 'EN_REPARACION') {
                $error = 'El material está en reparación; márcalo como reparado antes de moverlo.';
            } elseif ($destino['nombre'] === 'Servicio Técnico') {
                $error = 'Para enviar a reparación usa la acción «Enviar a reparar».';
            } elseif (!$combinacion_valida) {
                $error = 'Un material no puede pasar de un lugar a otro sin una persona de por medio.';
            } elseif ($origen_id !== null && $origen_id === $destino_id) {
                $error = 'El destino no puede ser la ubicación actual del material.';
            }

            if ($error === '') {
                try {
                    $pdo->beginTransaction();

                    // El estado se deriva del tipo de destino
                    $nuevo_estado = $tipo_destino === 'PERSONA' ? 'ASIGNADO' : 'DISPONIBLE';

                    $stmt = $pdo->prepare(
                        "INSERT INTO movimiento (material_id, origen_id, destino_id)
                         VALUES (?, ?, ?)"
                    );
                    $stmt->execute([$material_id, $origen_id, $destino_id]);

                    $stmt = $pdo->prepare(
                        "UPDATE material
                         SET localizacion_actual_id = ?, estado = ?
                         WHERE id = ?"
                    );
                    $stmt->execute([$destino_id, $nuevo_estado, $material_id]);

                    $pdo->commit();
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    die('Error al registrar el movimiento.');
                }

                header('Location: index.php?page=movimientos');
                exit;
            }
        }
    }
}

// No se listan materiales en reparación: salen de ese estado solo con «Marcar reparado»
$materiales = $pdo->query(
    "SELECT m.id, m.codigo, m.descripcion, l.tipo AS ubicacion_tipo
     FROM material m
     LEFT JOIN localizacion l ON m.localizacion_actual_id = l.id
     WHERE m.activo = 1
       AND m.estado <> 'EN_REPARACION'
     ORDER BY m.codigo"
)->fetchAll();

// Servicio Técnico no es un destino manual: la reparación tiene su propio flujo
$localizaciones = $pdo->query(
    "SELECT id, tipo, nombre
     FROM localizacion
     WHERE nombre <> 'Servicio Técnico'
     ORDER BY tipo, nombre"
)->fetchAll();


$sql = "SELECT mov.id, mov.fecha,
               m.codigo AS material_codigo,
               o.nombre AS origen_nombre, o.tipo AS origen_tipo,
               d.nombre AS destino_nombre, d.tipo AS destino_tipo
        FROM movimiento mov
        JOIN material m ON mov.material_id = m.id
        LEFT JOIN localizacion o ON mov.origen_id = o.id
        JOIN localizacion d ON mov.destino_id = d.id
        ORDER BY mov.fecha DESC, mov.id DESC";
$movimientos = $pdo->query($sql)->fetchAll();
?>

<h2>Nuevo movimiento</h2>

<?php if ($error !== ''): ?>
    <p class="error"><?php echo htmlspecialchars($error); ?></p>
<?php endif; ?>

<form method="POST" action="index.php?page=movimientos">
    <select name="material_id" id="sel-material" required>
        <option value="">— Material —</option>
        <?php foreach ($materiales as $m): ?>
            <option value="<?php echo (int) $m['id']; ?>"
                data-ubicacion="<?php echo htmlspecialchars($m['ubicacion_tipo'] ?? ''); ?>">
                <?php echo htmlspecialchars($m['codigo'] . ' · ' . $m['descripcion']); ?>
            </option>
        <?php endforeach; ?>
    </select>

    <select name="destino_id" id="sel-destino" required>
        <option value="">— Destino —</option>
        <?php foreach ($localizaciones as $l): ?>
            <option value="<?php echo (int) $l['id']; ?>" data-tipo="<?php echo htmlspecialchars($l['tipo']); ?>">
                <?php echo htmlspecialchars($l['nombre']); ?>
            </option>
        <?php endforeach; ?>
    </select>

    <button type="submit">Registrar movimiento</button>
</form>

<h2>Historial de movimientos</h2>
<table>
    <thead>
        <tr>
            <th>Fecha</th>
            <th>Material</th>
            <th>Origen</th>
            <th>Destino</th>
            <th>Tipo</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($movimientos as $mov): ?>
            <tr>
                <td><?php echo htmlspecialchars($mov['fecha']); ?></td>
                <td><?php echo htmlspecialchars($mov['material_codigo']); ?></td>
                <td>
                    <?php if ($mov['origen_nombre']): ?>
                        <?php echo htmlspecialchars($mov['origen_nombre']); ?>
                    <?php else: ?>
                        <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
                <td><?php echo htmlspecialchars($mov['destino_nombre']); ?></td>
                <td>
                    <?php

                    if ($mov['destino_nombre'] === 'Servicio Técnico') {
                        echo 'Reparación';
                    } elseif ($mov['origen_tipo'] === 'PERSONA' && $mov['destino_tipo'] === 'LUGAR') {
                        echo 'Devolución';
                    } elseif ($mov['origen_tipo'] === 'LUGAR' && $mov['destino_tipo'] === 'PERSONA') {
                        echo 'Recogida';
                    } elseif ($mov['origen_tipo'] === 'PERSONA' && $mov['destino_tipo'] === 'PERSONA') {
                        echo 'Entrega';
                    } else {
                        echo '—';
                    }
                    ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<script src="assets/js/jquery.js"></script>
<script src="assets/js/movimientos.js"></script>