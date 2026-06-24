<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? 'crear';

    if ($accion === 'eliminar') {

        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            // Borrado lógico: se conserva el rastro para auditoría
            $pdo->prepare("UPDATE material SET activo = 0 WHERE id = ?")
                ->execute([$id]);
        }
        header('Location: index.php?page=materiales');
        exit;
    }

    if ($accion === 'reparar') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $servicio_id = (int) $pdo->query(
                "SELECT id FROM localizacion
                 WHERE tipo = 'LUGAR' AND nombre = 'Servicio Técnico' LIMIT 1"
            )->fetchColumn();

            if ($servicio_id > 0) {
                $stmt = $pdo->prepare("SELECT localizacion_actual_id FROM material WHERE id = ?");
                $stmt->execute([$id]);
                $actual = $stmt->fetchColumn();
                $origen_id = $actual !== false && $actual !== null ? (int) $actual : null;

                try {
                    $pdo->beginTransaction();

                    $pdo->prepare(
                        "INSERT INTO movimiento (material_id, origen_id, destino_id)
                         VALUES (?, ?, ?)"
                    )->execute([$id, $origen_id, $servicio_id]);

                    $pdo->prepare(
                        "UPDATE material
                         SET localizacion_actual_id = ?, estado = 'EN_REPARACION'
                         WHERE id = ?"
                    )->execute([$servicio_id, $id]);
                    $pdo->commit();
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    die('Error al enviar a reparación.');
                }
            }
        }
        header('Location: index.php?page=materiales');
        exit;
    }

    if ($accion === 'reparado') {

        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare(
                "UPDATE material SET estado = 'DISPONIBLE'
                 WHERE id = ? AND estado = 'EN_REPARACION'"
            )->execute([$id]);
        }
        header('Location: index.php?page=materiales');
        exit;
    }

    if ($accion === 'crear') {
        $descripcion = trim($_POST['descripcion'] ?? '');
        $categoria = trim($_POST['categoria'] ?? '');
        $lugar_id = (int) ($_POST['lugar_id'] ?? 0);

        // No se permite crear materiales directamente en Servicio Técnico
        $lugar_valido = false;
        if ($lugar_id > 0) {
            $stmt = $pdo->prepare("SELECT 1 FROM localizacion WHERE id = ? AND tipo = 'LUGAR' AND nombre <> 'Servicio Técnico'");
            $stmt->execute([$lugar_id]);
            $lugar_valido = (bool) $stmt->fetchColumn();
        }

        if ($descripcion !== '' && $lugar_valido) {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare(
                    "INSERT INTO material (codigo, descripcion, categoria, localizacion_actual_id)
                     VALUES (?, ?, ?, ?)"
                );
                $stmt->execute(['TEMP', $descripcion, $categoria ?: null, $lugar_id]);

                $id = $pdo->lastInsertId();
                $codigo = 'MAT-' . str_pad($id, 4, '0', STR_PAD_LEFT);
                $pdo->prepare("UPDATE material SET codigo = ? WHERE id = ?")
                    ->execute([$codigo, $id]);

                $pdo->commit();
            } catch (PDOException $e) {
                $pdo->rollBack();
                die('Error al crear el material.');
            }

            header('Location: index.php?page=materiales');
            exit;
        }
    }
}

$lugares = $pdo->query(
    "SELECT id, nombre
     FROM localizacion
     WHERE tipo = 'LUGAR'
       AND nombre <> 'Servicio Técnico'
     ORDER BY nombre"
)->fetchAll();

// "Desde cuando" derivado del último movimiento, con fecha_alta de respaldo
$sql = "SELECT m.id, m.codigo, m.descripcion, m.categoria, m.estado,
               l.tipo AS ubicacion_tipo, l.nombre AS ubicacion_nombre,
               COALESCE(
                   (SELECT MAX(mov.fecha)
                    FROM movimiento mov
                    WHERE mov.material_id = m.id),
                   m.fecha_alta
               ) AS ubicacion_desde
        FROM material m
        LEFT JOIN localizacion l ON m.localizacion_actual_id = l.id
        WHERE m.activo = 1
        ORDER BY m.codigo";
$materiales = $pdo->query($sql)->fetchAll();
?>



<h2>Nuevo material</h2>
<form method="POST" action="index.php?page=materiales">
    <input type="hidden" name="accion" value="crear">
    <input type="text" name="descripcion" placeholder="Descripción" required>
    <input type="text" name="categoria" placeholder="Categoría (opcional)">
    <select name="lugar_id" required>
        <option value="">— Lugar —</option>
        <?php foreach ($lugares as $l): ?>
            <option value="<?php echo (int) $l['id']; ?>">
                <?php echo htmlspecialchars($l['nombre']); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <button type="submit">Crear material</button>
</form>

<h2>Materiales</h2>
<table>
    <thead>
        <tr>
            <th>Código</th>
            <th>Descripción</th>
            <th>Categoría</th>
            <th>Estado</th>
            <th>Ubicación actual</th>
            <th>Desde</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($materiales as $m): ?>
            <tr>
                <td>
                    <a class="codigo-link" href="index.php?page=historial&id=<?php echo (int) $m['id']; ?>">
                        <?php echo htmlspecialchars($m['codigo']); ?>
                    </a>
                </td>
                <td><?php echo htmlspecialchars($m['descripcion']); ?></td>
                <td>
                    <?php if ($m['categoria']): ?>
                        <?php echo htmlspecialchars($m['categoria']); ?>
                    <?php else: ?>
                        <span class="text-muted">Sin categoría</span>
                    <?php endif; ?>
                </td>
                <td><?php echo htmlspecialchars(ucfirst(strtolower(str_replace('_', ' ', $m['estado'])))); ?></td>
                <td>
                    <?php if ($m['ubicacion_nombre']): ?>
                        <?php echo htmlspecialchars($m['ubicacion_nombre']); ?>
                    <?php else: ?>
                        Sin ubicación
                    <?php endif; ?>
                </td>
                <td><?php echo htmlspecialchars($m['ubicacion_desde']); ?></td>
                <td>
                    <div class="acciones">
                        <?php if ($m['estado'] === 'EN_REPARACION'): ?>
                            <form method="POST" action="index.php?page=materiales">
                                <input type="hidden" name="accion" value="reparado">
                                <input type="hidden" name="id" value="<?php echo (int) $m['id']; ?>">
                                <button type="submit">Marcar reparado</button>
                            </form>
                        <?php else: ?>
                            <form method="POST" action="index.php?page=materiales">
                                <input type="hidden" name="accion" value="reparar">
                                <input type="hidden" name="id" value="<?php echo (int) $m['id']; ?>">
                                <button type="submit">Enviar a reparar</button>
                            </form>
                        <?php endif; ?>

                        <form method="POST" action="index.php?page=materiales"
                            onsubmit="return confirm('¿Eliminar este material?');">
                            <input type="hidden" name="accion" value="eliminar">
                            <input type="hidden" name="id" value="<?php echo (int) $m['id']; ?>">
                            <button type="submit">Eliminar</button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>