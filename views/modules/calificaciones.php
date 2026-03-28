<!-- views/modules/calificaciones.php -->
<?php
$db = Database::connect();
$mensaje = '';
$tipo_mensaje = '';

// ==========================================
// LÓGICA DE GUARDADO EN BLOQUE (Bulk Update)
// Aquí metemos un UPSERT (Insert on Duplicate Key Update).
// Procesamos todas las notas de un totazo, optimizando idas a la base de datos.
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'bulk_update') {
    $id_evidencia_post = filter_input(INPUT_POST, 'id_evidencia', FILTER_SANITIZE_NUMBER_INT);
    $calificaciones = $_POST['calificaciones'] ?? [];

    if ($id_evidencia_post && !empty($calificaciones)) {
        try {
            $db->beginTransaction();
            foreach ($calificaciones as $id_aprendiz => $estado) {
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
            $mensaje = "Error al actualizar: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
    }
}

// Filtros GET
$filtro_curso = filter_input(INPUT_GET, 'curso', FILTER_SANITIZE_STRING) ?? '';
$filtro_evidencia = filter_input(INPUT_GET, 'evidencia', FILTER_SANITIZE_NUMBER_INT) ?? '';

// ==========================================
// LISTAS DINÁMICAS PARA SELECTS
// ==========================================
$cursos = $db->query("SELECT DISTINCT codigo_curso FROM aprendices ORDER BY codigo_curso")->fetchAll(PDO::FETCH_ASSOC);

$evidencias = [];
if ($filtro_curso) {
    $stmtE_list = $db->prepare("SELECT id, codigo_evidencia, descripcion, fecha_entrega FROM evidencias WHERE codigo_curso = ? ORDER BY fecha_entrega DESC");
    $stmtE_list->execute([$filtro_curso]);
    $evidencias = $stmtE_list->fetchAll(PDO::FETCH_ASSOC);
}

$alumnos = [];
$evidencia_seleccionada = null;

if ($filtro_curso && $filtro_evidencia) {
    $stmtE = $db->prepare("SELECT fecha_entrega FROM evidencias WHERE id = ?");
    $stmtE->execute([$filtro_evidencia]);
    $evidencia_seleccionada = $stmtE->fetch(PDO::FETCH_ASSOC);

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

$es_vencida = false;
if ($evidencia_seleccionada) {
    $fecha_actual = new DateTime();
    $fecha_entrega = new DateTime($evidencia_seleccionada['fecha_entrega']);
    $es_vencida = $fecha_actual > $fecha_entrega;
}
?>

<!-- Header -->
<div class="mb-6 animate-fade-in-up">
    <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white tracking-tight">Evaluación de Evidencias</h1>
    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Califica de forma masiva por ficha (curso).</p>
</div>

<!-- Alerts -->
<?php if ($mensaje): ?>
<div class="mb-6 animate-fade-in-down">
    <div class="flex items-center gap-3 p-4 rounded-xl <?= $tipo_mensaje === 'success' ? 'bg-gradient-to-r from-emerald-50 to-green-50 dark:from-emerald-900/20 dark:to-green-900/20 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300' : 'bg-gradient-to-r from-red-50 to-rose-50 dark:from-red-900/20 dark:to-rose-900/20 border border-red-200 dark:border-red-800 text-red-800 dark:text-red-300' ?> shadow-sm">
        <div class="w-8 h-8 rounded-lg flex items-center justify-center <?= $tipo_mensaje === 'success' ? 'bg-emerald-500' : 'bg-red-500' ?> text-white flex-shrink-0">
            <i class="fa-solid <?= $tipo_mensaje === 'success' ? 'fa-check' : 'fa-xmark' ?> text-sm"></i>
        </div>
        <span class="text-sm font-medium"><?= htmlspecialchars($mensaje) ?></span>
    </div>
</div>
<?php endif; ?>

<!-- Filter Form -->
<form method="GET" class="card-modern p-6 mb-6 animate-fade-in-up" style="animation-delay: 100ms">
    <input type="hidden" name="view" value="calificaciones">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
        <div>
            <label class="form-label">Código de Curso (Ficha)</label>
            <select name="curso" class="form-select" required onchange="this.form.submit()">
                <option value="">-- Seleccione una Ficha --</option>
                <?php foreach($cursos as $c): ?>
                    <option value="<?= htmlspecialchars($c['codigo_curso']) ?>" <?= $filtro_curso === $c['codigo_curso'] ? 'selected' : '' ?>><?= htmlspecialchars($c['codigo_curso']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="form-label">Evidencia a Evaluar</label>
            <select name="evidencia" class="form-select" <?= empty($evidencias) ? 'disabled' : 'required' ?>>
                <option value=""><?= empty($filtro_curso) ? '-- Elija Ficha Primero --' : '-- Seleccione Evidencia --' ?></option>
                <?php foreach($evidencias as $e): ?>
                    <option value="<?= $e['id'] ?>" <?= $filtro_evidencia == $e['id'] ? 'selected' : '' ?>><?= htmlspecialchars($e['codigo_evidencia']) ?> (Vence: <?= date('d/m/Y', strtotime($e['fecha_entrega'])) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="mt-5 flex justify-end">
        <button type="submit" class="btn-gradient-sena btn-ripple text-sm flex items-center gap-2">
            <i class="fa-solid fa-search"></i> Buscar Aprendices
        </button>
    </div>
</form>

<!-- Results Table -->
<?php if ($filtro_curso && $filtro_evidencia): ?>
    <div class="card-modern overflow-hidden animate-fade-in-up" style="animation-delay: 200ms">

        <!-- Header Info -->
        <div class="p-5 border-b border-gray-100 dark:border-gray-700 bg-gradient-to-r from-gray-50 to-white dark:from-gray-800/50 dark:to-gray-800 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
            <h3 class="font-bold text-gray-800 dark:text-gray-200">Resultados Ficha: <span class="text-sena font-extrabold"><?= htmlspecialchars($filtro_curso) ?></span></h3>
            <?php if ($es_vencida): ?>
                <span class="badge-gradient-danger"><i class="fa-solid fa-clock mr-1"></i> Evidencia Vencida</span>
            <?php else: ?>
                <span class="badge-gradient-success"><i class="fa-solid fa-lock-open mr-1"></i> Evidencia Abierta</span>
            <?php endif; ?>
        </div>

        <!-- Bulk Update Form -->
        <form method="POST" action="?view=calificaciones&curso=<?= urlencode($filtro_curso) ?>&evidencia=<?= urlencode($filtro_evidencia) ?>">
            <input type="hidden" name="action" value="bulk_update">
            <input type="hidden" name="id_evidencia" value="<?= htmlspecialchars($filtro_evidencia) ?>">

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700">
                    <thead class="bg-gray-50/80 dark:bg-gray-800/50">
                        <tr>
                            <th class="px-6 py-3.5 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Documento</th>
                            <th class="px-6 py-3.5 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Aprendiz</th>
                            <th class="px-6 py-3.5 text-center text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Calificación Actual</th>
                            <th class="px-6 py-3.5 text-right text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Nueva Nota</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50 dark:divide-gray-700/50">
                        <?php if (empty($alumnos)): ?>
                            <tr><td colspan="4" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">No hay aprendices registrados en esta ficha.</td></tr>
                        <?php else: foreach($alumnos as $i => $a): ?>
                            <tr class="table-row-hover" style="animation: fadeInUp 0.4s ease-out <?= $i * 40 ?>ms forwards; opacity: 0;">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-800 dark:text-gray-200"><?= htmlspecialchars($a['cedula']) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900 dark:text-white"><?= htmlspecialchars($a['apellidos'] . ' ' . $a['nombres']) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <?php if($a['estado'] == 'Aprobada'): ?>
                                        <span class="badge-gradient-success"><i class="fa-solid fa-check mr-1"></i> Aprobada</span>
                                    <?php elseif($a['estado'] == 'Devuelta'): ?>
                                        <span class="badge-gradient-warning"><i class="fa-solid fa-rotate-left mr-1"></i> Devuelta</span>
                                    <?php else: ?>
                                        <span class="badge-gradient-neutral"><i class="fa-regular fa-clock mr-1"></i> Sin calificar</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right">
                                    <select name="calificaciones[<?= $a['id_aprendiz'] ?>]" class="form-select text-sm py-2">
                                        <option value="Sin calificar" <?= $a['estado'] == 'Sin calificar' ? 'selected' : '' ?>>Sin calificar</option>
                                        <option value="Aprobada" <?= $a['estado'] == 'Aprobada' ? 'selected' : '' ?>>Aprobada (A)</option>
                                        <option value="Devuelta" <?= $a['estado'] == 'Devuelta' ? 'selected' : '' ?>>Devuelta (D)</option>
                                    </select>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if (!empty($alumnos)): ?>
            <div class="p-5 bg-gray-50/50 dark:bg-gray-800/50 border-t border-gray-100 dark:border-gray-700 flex justify-end">
                <button type="submit" class="btn-gradient-indigo btn-ripple text-sm flex items-center gap-2">
                    <i class="fa-solid fa-floppy-disk"></i> Guardar Calificaciones en Bloque
                </button>
            </div>
            <?php endif; ?>
        </form>
    </div>
<?php endif; ?>
