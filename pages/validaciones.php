<?php
$error = '';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $movimiento_id = (int) ($_POST['movimiento_id'] ?? 0);
    $validador_id = (int) ($_POST['validador_id'] ?? 0);
    $observaciones = trim($_POST['observaciones'] ?? '');

    if ($movimiento_id > 0 && $validador_id > 0) {

        $stmt = $pdo->prepare(
            "SELECT mov.id
             FROM movimiento mov
             JOIN localizacion o ON mov.origen_id = o.id
             JOIN localizacion d ON mov.destino_id = d.id
             LEFT JOIN validacion v ON v.movimiento_id = mov.id
             WHERE mov.id = ?
               AND o.tipo = 'PERSONA' AND d.tipo = 'LUGAR'
               AND d.nombre <> 'Servicio Técnico'
               AND v.id IS NULL"
        );
        $stmt->execute([$movimiento_id]);
        $devolucion_ok = (bool) $stmt->fetchColumn();

        // El validador debe ser una persona, no un lugar
        $stmt = $pdo->prepare("SELECT 1 FROM persona WHERE localizacion_id = ?");
        $stmt->execute([$validador_id]);
        $validador_ok = (bool) $stmt->fetchColumn();

        if (!$devolucion_ok) {
            $error = 'La devolución no existe o ya fue validada.';
        } elseif (!$validador_ok) {
            $error = 'El validador debe ser una persona.';
        } else {
            $pdo->prepare(
                "INSERT INTO validacion (movimiento_id, validador_id, observaciones)
                 VALUES (?, ?, ?)"
            )->execute([$movimiento_id, $validador_id, $observaciones ?: null]);

            header('Location: index.php?page=validaciones');
            exit;
        }
    }
}


$pendientes = $pdo->query(
    "SELECT mov.id, mov.fecha,
            m.codigo AS material_codigo,
            o.nombre AS origen_nombre,
            d.nombre AS destino_nombre
     FROM movimiento mov
     JOIN material m ON mov.material_id = m.id
     JOIN localizacion o ON mov.origen_id = o.id
     JOIN localizacion d ON mov.destino_id = d.id
     LEFT JOIN validacion v ON v.movimiento_id = mov.id
     WHERE o.tipo = 'PERSONA' AND d.tipo = 'LUGAR'
       AND d.nombre <> 'Servicio Técnico'
       AND v.id IS NULL
     ORDER BY mov.fecha DESC, mov.id DESC"
)->fetchAll();


$validadores = $pdo->query(
    "SELECT l.id, l.nombre
     FROM persona p
     JOIN localizacion l ON p.localizacion_id = l.id
     ORDER BY l.nombre"
)->fetchAll();

$validaciones = $pdo->query(
    "SELECT v.fecha, v.observaciones,
            pv.nombre AS validador_nombre,
            m.codigo AS material_codigo,
            o.nombre AS origen_nombre,
            d.nombre AS destino_nombre
     FROM validacion v
     JOIN localizacion pv ON v.validador_id = pv.id
     JOIN movimiento mov  ON v.movimiento_id = mov.id
     JOIN material m       ON mov.material_id = m.id
     JOIN localizacion o   ON mov.origen_id = o.id
     JOIN localizacion d   ON mov.destino_id = d.id
     ORDER BY v.fecha DESC, v.id DESC"
)->fetchAll();
?>

<h2>Validar devolución</h2>

<?php if ($error !== ''): ?>
    <p class="error"><?php echo htmlspecialchars($error); ?></p>
<?php endif; ?>

<?php if (!$pendientes): ?>
    <p class="text-muted">No hay devoluciones pendientes de validar.</p>
<?php else: ?>
    <form method="POST" action="index.php?page=validaciones">
        <select name="movimiento_id" required>
            <option value="">— Devolución —</option>
            <?php foreach ($pendientes as $p): ?>
                <option value="<?php echo (int) $p['id']; ?>">
                    <?php echo htmlspecialchars(
                        $p['material_codigo'] . ' · ' .
                        $p['origen_nombre'] . ' → ' . $p['destino_nombre'] .
                        ' · ' . $p['fecha']
                    ); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="validador_id" required>
            <option value="">— Validador —</option>
            <?php foreach ($validadores as $v): ?>
                <option value="<?php echo (int) $v['id']; ?>">
                    <?php echo htmlspecialchars($v['nombre']); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <input type="text" name="observaciones" placeholder="Observaciones (opcional)">

        <button type="submit">Validar</button>
    </form>
<?php endif; ?>

<h2>Historial de validaciones</h2>
<table>
    <thead>
        <tr>
            <th>Fecha</th>
            <th>Material</th>
            <th>Devolución</th>
            <th>Validador</th>
            <th>Observaciones</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($validaciones as $v): ?>
            <tr>
                <td><?php echo htmlspecialchars($v['fecha']); ?></td>
                <td><?php echo htmlspecialchars($v['material_codigo']); ?></td>
                <td><?php echo htmlspecialchars($v['origen_nombre'] . ' → ' . $v['destino_nombre']); ?></td>
                <td><?php echo htmlspecialchars($v['validador_nombre']); ?></td>
                <td>
                    <?php if ($v['observaciones']): ?>
                        <?php echo htmlspecialchars($v['observaciones']); ?>
                    <?php else: ?>
                        <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>