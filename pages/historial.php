<?php
$material_id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    "SELECT m.codigo, m.descripcion, m.estado, m.fecha_alta,
            l.nombre AS ubicacion_nombre
     FROM material m
     LEFT JOIN localizacion l ON m.localizacion_actual_id = l.id
     WHERE m.id = ?"
);
$stmt->execute([$material_id]);
$material = $stmt->fetch();

if (!$material) {
    echo '<p class="error">Material no encontrado.</p>';
    echo '<p><a href="index.php?page=materiales">← Volver a materiales</a></p>';
    return;
}

$stmt = $pdo->prepare(
    "SELECT mov.fecha,
            o.nombre AS origen_nombre, o.tipo AS origen_tipo,
            d.nombre AS destino_nombre, d.tipo AS destino_tipo,
            v.fecha AS validacion_fecha,
            v.observaciones AS validacion_observaciones,
            pv.nombre AS validador_nombre
     FROM movimiento mov
     LEFT JOIN localizacion o ON mov.origen_id = o.id
     JOIN localizacion d ON mov.destino_id = d.id
     LEFT JOIN validacion v ON v.movimiento_id = mov.id
     LEFT JOIN localizacion pv ON v.validador_id = pv.id
     WHERE mov.material_id = ?
     ORDER BY mov.fecha ASC, mov.id ASC"
);
$stmt->execute([$material_id]);
$movimientos = $stmt->fetchAll();
?>

<p><a href="index.php?page=materiales">← Volver a materiales</a></p>

<h2>Historial de <?php echo htmlspecialchars($material['codigo']); ?></h2>
<p class="subtitulo">
    <?php echo htmlspecialchars($material['descripcion']); ?> ·
    Estado actual: <?php echo htmlspecialchars($material['estado']); ?> ·
    Ubicación actual:
    <?php echo $material['ubicacion_nombre']
        ? htmlspecialchars($material['ubicacion_nombre'])
        : 'Sin ubicación'; ?>
</p>

<table>
    <thead>
        <tr>
            <th>Fecha</th>
            <th>Evento</th>
            <th>Origen</th>
            <th>Destino</th>
            <th>Validación</th>
        </tr>
    </thead>
    <tbody>

        <tr>
            <!-- El alta no es un movimiento. Se añade como primer evento del historial -->
            <td><?php echo htmlspecialchars($material['fecha_alta']); ?></td>
            <td>Alta</td>
            <td><span class="text-muted">—</span></td>
            <td><span class="text-muted">—</span></td>
            <td><span class="text-muted">—</span></td>
        </tr>
        <?php foreach ($movimientos as $mov): ?>
            <?php
            // El tipo de evento se deduce de los tipos de origen/destino
            if ($mov['destino_nombre'] === 'Servicio Técnico') {
                $tipo_evento = 'Reparación';
            } elseif ($mov['origen_tipo'] === 'PERSONA' && $mov['destino_tipo'] === 'LUGAR') {
                $tipo_evento = 'Devolución';
            } elseif ($mov['origen_tipo'] === 'LUGAR' && $mov['destino_tipo'] === 'PERSONA') {
                $tipo_evento = 'Recogida';
            } elseif ($mov['origen_tipo'] === 'PERSONA' && $mov['destino_tipo'] === 'PERSONA') {
                $tipo_evento = 'Entrega';
            } else {
                $tipo_evento = 'Movimiento';
            }
            ?>
            <tr>
                <td><?php echo htmlspecialchars($mov['fecha']); ?></td>
                <td><?php echo htmlspecialchars($tipo_evento); ?></td>
                <td>
                    <?php if ($mov['origen_nombre']): ?>
                        <?php echo htmlspecialchars($mov['origen_nombre']); ?>
                    <?php else: ?>
                        <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
                <td><?php echo htmlspecialchars($mov['destino_nombre']); ?></td>
                <td>
                    <?php if ($tipo_evento !== 'Devolución'): ?>
                        <span class="text-muted">—</span>
                    <?php elseif ($mov['validacion_fecha']): ?>
                        Validada ·
                        <?php echo htmlspecialchars($mov['validador_nombre']); ?> ·
                        <?php echo htmlspecialchars($mov['validacion_fecha']); ?>
                        <?php if ($mov['validacion_observaciones']): ?>
                            <br>
                            <span class="text-muted">
                                <?php echo htmlspecialchars($mov['validacion_observaciones']); ?>
                            </span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="text-muted">Pendiente</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>