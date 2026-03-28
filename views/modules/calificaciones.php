<!-- views/modules/calificaciones.php -->
<?php
$db = Database::connect();
$mensaje = '';
$tipo_mensaje = '';

// Lógica de Guardado en Bloque (Bulk Update)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'bulk_update') {
    $id_evidencia_post = filter_input(INPUT_POST, 'id_evidencia', FILTER_SANITIZE_NUMBER_INT);
    $calificaciones = $_POST['calificaciones'] ?? []; // Array [id_aprendiz => estado]

    if ($id_evidencia_post && !empty($calificaciones)) {
        try {
            $db->beginTransaction();
            foreach ($calificaciones as $id_aprendiz => $estado) {
                // Validación básica de ENUM
                if (!in_array($estado, ['Sin calificar', 'Aprobada', 'Devuelta'])) continue;

                $stmt = $db->prepare("
                    INSERT INTO calificaciones (id_aprendiz, id_evidencia, estado_calificacion) 
                    VALUES (:id_aprendiz, :id_evidencia, :estado_insert)
                    ON DUPLICATE KEY UPDATE estado_calificacion = :estado_update
                ");
                $stmt->execute([
                    ':id_aprendiz' => $id_aprendiz,
                    ':id_evidencia' => $id_evidencia_post,
                    ':estado_insert' => $estado,
                    ':estado_update' => $estado
                ]);
            }
            $db->commit();
            $mensaje = "Calificaciones actualizadas correctamente.";
            $tipo_mensaje = "success";
        } catch (PDOException $e) {
            $db->rollBack();
            $mensaje = "Error al actualizar calificaciones: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
    }
}

// Filtros GET
$filtro_curso = filter_input(INPUT_GET, 'curso', FILTER_SANITIZE_STRING) ?? '';
$filtro_evidencia = filter_input(INPUT_GET, 'evidencia', FILTER_SANITIZE_NUMBER_INT) ?? '';

// Obtener listas para los Selects
$cursos = $db->query("SELECT DISTINCT codigo_curso FROM aprendices ORDER BY codigo_curso")->fetchAll(PDO::FETCH_ASSOC);

$evidencias = [];
if ($filtro_curso) {
    $stmtE_list = $db->prepare("SELECT id, codigo_evidencia, descripcion, fecha_entrega FROM evidencias WHERE codigo_curso = ? ORDER BY fecha_entrega DESC");
    $stmtE_list->execute([$filtro_curso]);
    $evidencias = $stmtE_list->fetchAll(PDO::FETCH_ASSOC);
}

// Si hay filtros, cargar alumnos y sus notas
$alumnos = [];
$evidencia_seleccionada = null;

if ($filtro_curso && $filtro_evidencia) {
    // Buscar detalle de evidencia actual (para el control de vencimiento)
    $stmtE = $db->prepare("SELECT fecha_entrega FROM evidencias WHERE id = ?");
    $stmtE->execute([$filtro_evidencia]);
    $evidencia_seleccionada = $stmtE->fetch(PDO::FETCH_ASSOC);

    // Join aprendices con calificaciones para esa evidencia (LEFT JOIN)
    $stmtA = $db->prepare("
        SELECT a.id as id_aprendiz, a.nombres, a.apellidos, a.cedula,
               COALESCE(c.estado_calificacion, 'Sin calificar') as estado
        FROM aprendices a
        LEFT JOIN calificaciones c ON a.id = c.id_aprendiz AND c.id_evidencia = ?
        WHERE a.codigo_curso = ?
        ORDER BY a.apellidos, a.nombres
    ");
    $stmtA->execute([$filtro_evidencia, $filtro_curso]);
    $alumnos = $stmtA->fetchAll(PDO::FETCH_ASSOC);
}

// Helper para fecha
$es_vencida = false;
if ($evidencia_seleccionada) {
    $fecha_actual = new DateTime();
    $fecha_entrega = new DateTime($evidencia_seleccionada['fecha_entrega']);
    $es_vencida = $fecha_actual > $fecha_entrega;
}
?>

<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-800 dark:text-gray-100 uppercase tracking-tight">Evaluación de Evidencias</h1>
    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Califica de forma masiva por ficha (curso).</p>
</div>

<?php if ($mensaje): ?>
<div class="mb-4 p-4 rounded-lg <?= $tipo_mensaje === 'success' ? 'bg-green-100 text-green-800 border check' : 'bg-red-100 text-red-800' ?> transition-all">
    <i class="fa-solid <?= $tipo_mensaje === 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation' ?>"></i> <?= htmlspecialchars($mensaje) ?>
</div>
<?php endif; ?>

<!-- Filters Form -->
<form method="GET" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 mb-6 border border-gray-100 dark:border-gray-700">
    <input type="hidden" name="view" value="calificaciones">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Código de Curso (Ficha)</label>
            <select name="curso" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-sena focus:ring-sena transition-colors" required onchange="this.form.submit()">
                <option value="">-- Seleccione una Ficha --</option>
                <?php foreach($cursos as $c): ?>
                    <option value="<?= htmlspecialchars($c['codigo_curso']) ?>" <?= $filtro_curso === $c['codigo_curso'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['codigo_curso']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Evidencia a Evaluar</label>
            <select name="evidencia" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-sena focus:ring-sena transition-colors" <?= empty($evidencias) ? 'disabled' : 'required' ?>>
                <option value=""><?= empty($filtro_curso) ? '-- Elija Ficha Primero --' : '-- Seleccione Evidencia --' ?></option>
                <?php foreach($evidencias as $e): ?>
                    <option value="<?= $e['id'] ?>" <?= $filtro_evidencia == $e['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($e['codigo_evidencia']) ?> (Vence: <?= date('d/m/Y', strtotime($e['fecha_entrega'])) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="mt-4 flex justify-end">
        <button type="submit" class="bg-sena hover:bg-sena-dark text-white font-semibold py-2 px-6 rounded-lg transition-colors shadow-sm focus:ring focus:ring-sena/50">
            <i class="fa-solid fa-search"></i> Buscar Aprendices
        </button>
    </div>
</form>

<!-- Results Table -->
<?php if ($filtro_curso && $filtro_evidencia): ?>
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
        
        <!-- Header Info -->
        <div class="p-4 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 flex flex-col sm:flex-row justify-between items-center">
            <h3 class="font-bold text-gray-800 dark:text-gray-200">Resultados Ficha: <span class="text-sena"><?= htmlspecialchars($filtro_curso) ?></span></h3>
            <div class="mt-2 sm:mt-0">
                Estado Evidencia: 
                <?php if ($es_vencida): ?>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 border border-red-200">
                        <i class="fa-solid fa-clock mr-1"></i> Vencida
                    </span>
                <?php else: ?>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 border border-green-200">
                        <i class="fa-solid fa-lock-open mr-1"></i> Abierta
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Formularo Bulk Update -->
        <form method="POST" action="?view=calificaciones&curso=<?= urlencode($filtro_curso) ?>&evidencia=<?= urlencode($filtro_evidencia) ?>">
            <input type="hidden" name="action" value="bulk_update">
            <input type="hidden" name="id_evidencia" value="<?= htmlspecialchars($filtro_evidencia) ?>">

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800/50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Documento</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Aprendiz</th>
                            <th class="px-6 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Calificación Actual</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Establecer Nueva Nota</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        <?php if (empty($alumnos)): ?>
                            <tr><td colspan="4" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">No hay aprendices registrados en esta ficha.</td></tr>
                        <?php else: ?>
                            <?php foreach($alumnos as $a): ?>
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800 dark:text-gray-200"><?= htmlspecialchars($a['cedula']) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                        <?= htmlspecialchars($a['apellidos'] . ' ' . $a['nombres']) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-500">
                                        <?php if($a['estado'] == 'Aprobada'): ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800"><i class="fa-solid fa-check mr-1"></i> Aprobada</span>
                                        <?php elseif($a['estado'] == 'Devuelta'): ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800"><i class="fa-solid fa-rotate-left mr-1"></i> Devuelta</span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800"><i class="fa-regular fa-calendar-minus mr-1"></i> Sin calificar</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                        <select name="calificaciones[<?= $a['id_aprendiz'] ?>]" class="rounded text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:ring-sena focus:border-sena">
                                            <option value="Sin calificar" <?= $a['estado'] == 'Sin calificar' ? 'selected' : '' ?>>Sin calificar</option>
                                            <option value="Aprobada" <?= $a['estado'] == 'Aprobada' ? 'selected' : '' ?>>Aprobada (A)</option>
                                            <option value="Devuelta" <?= $a['estado'] == 'Devuelta' ? 'selected' : '' ?>>Devuelta (D)</option>
                                        </select>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if (!empty($alumnos)): ?>
            <div class="p-4 bg-gray-50 dark:bg-gray-800/80 border-t border-gray-100 dark:border-gray-700 flex justify-end">
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 px-6 rounded-lg transition-colors shadow-sm focus:ring focus:ring-indigo-300 gap-2 flex items-center">
                    <i class="fa-solid fa-floppy-disk"></i> Guardar Calificaciones en Bloque
                </button>
            </div>
            <?php endif; ?>
        </form>
    </div>
<?php endif; ?>
