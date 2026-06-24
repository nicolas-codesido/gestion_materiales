# Sistema de Gestión de Materiales

App web de inventario y trazabilidad de materiales. El objetivo central es saber en todo
momento dónde está cada material, quién lo tiene, cuándo se movió y en qué estado está.

## Instalación

1. Clonar el repo dentro de `htdocs/` (la carpeta de Apache en XAMPP)
2. Arrancar Apache y MySQL desde el panel de XAMPP
3. Importar la base de datos. Desde phpMyAdmin: pestaña _Importar_, seleccionar
   `database.sql` y pulsar _Continuar_

El script crea la base `gestion_materiales_nicolas`, las tablas y algunos datos de ejemplo
La conexión está en `includes/database.php`, por defecto con usuario `root` sin contraseña

## Ejecución

Abrir `http://localhost/<carpeta-del-proyecto>/index.php`. Arriba están las secciones
Materiales, Movimientos y Validaciones. El historial de cada material se abre desde su
código en la lista

## Diseño de la base de datos

**`localizacion`** — Tabla base de todo lo que puede contener un material
Campo `tipo` (PERSONA/LUGAR) y `nombre`. Permite que `material` y `movimiento`
apunten a una persona o a un lugar con una sola FK

**`persona`** — Especializa `localizacion` (email, rol). Su PK _es_ la FK a
`localizacion` (relación 1:1, herencia por tabla)

**`lugar`** — Especializa `localizacion` (direccion). Misma relación 1:1

**`material`** — La entidad central. `codigo` UNIQUE (MAT-0001), `descripcion`,
`categoria`, `estado` (DISPONIBLE/ASIGNADO/EN_REPARACION), `activo` (borrado
lógico) y `fecha_alta`. `localizacion_actual_id` apunta a la ubicación actual

**`movimiento`** — Registro de cada transacción: `material_id`, `origen_id` (NULL en
el primer movimiento), `destino_id` y `fecha`. Es la fuente de verdad del
historial y de la trazabilidad

**`validacion`** — Cuelga de un `movimiento` (`movimiento_id`) e identifica al
`validador_id` (una persona), con `fecha` y `observaciones` No bloqueante

### Relaciones

- `persona.localizacion_id` → `localizacion.id` (1:1)
- `lugar.localizacion_id` → `localizacion.id` (1:1)
- `material.localizacion_actual_id` → `localizacion.id` (N:1)
- `movimiento.material_id` → `material.id` (N:1)
- `movimiento.origen_id` / `destino_id` → `localizacion.id` (N:1)
- `validacion.movimiento_id` → `movimiento.id` (N:1)
- `validacion.validador_id` → `persona.localizacion_id` (N:1)

## Decisiones de diseño

**Servicio Técnico.** La reparación se asigna mediante sus propios botones ("Enviar a
reparar" / "Marcar reparado"), no eligiéndolo como destino. Con más tiempo se marcaría con un campo.

**Localización polimórfica.** Un material puede estar en una persona o en un lugar. En vez
de dos FKs, una tabla `localizacion` con `tipo` (PERSONA/LUGAR) y dos que la especializan
(`persona`, `lugar`). Así una sola FK en `material` vale para ambos casos

**Ubicación actual guardada en el material.** Se guarda `localizacion_actual_id` en
`material` para que los listados salgan directos, pero el dato real vive en `movimiento`.
Si ese campo se corrompiera, se reconstruye desde el último movimiento

**"Desde cuándo" derivado.** Se calcula con el `MAX(fecha)` de los movimientos (con
`fecha_alta`), en vez de una columna que habría que mantener sincronizada

**Borrado lógico.** El enunciado pide eliminar, pero también conservar historial y auditoría.
Borrar de verdad rompería eso, así que eliminar marca `activo = 0`

**"Marcar reparado" deja el material disponible pero aún en el Servicio Técnico** hasta que
alguien lo recoja

**Validación origen-destino duplicada en cliente y servidor.** El JS filtra el desplegable
por comodidad. En `movimientos.php` un POST manipulado se rechaza igual

**Autovalidación permitida.** No se restringe que el validador sea distinto de quien
devuelve

## Seguridad

Medidas a implementar en un entorno de trabajo real

- **CSRF.** Los formularios que modifican estado no llevan token anti-CSRF; en
  producción se añadiría uno por sesión verificado en cada POST.
- **Autenticación.** No hay login ni sesión: los actores se eligen en un
  desplegable, no de un usuario autenticado. Coherente con la autovalidación
  permitida.
