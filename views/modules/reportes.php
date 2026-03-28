<!-- views/modules/reportes.php -->
<?php
$db = Database::connect();
$action = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_STRING) ?? '';

if ($action === 'historial'):
    // --- VISTA: HISTORIAL ESPECÍFICO DE APRENDIZ ---
    $search_cedula = trim($_GET['cedula'] ?? '');
    $aprendiz = null;
    $historial = [];
    $mensaje = '';

    if (!empty($search_cedula)) {
        // Buscar al aprendiz
        $stmtA = $db->prepare("SELECT * FROM aprendices WHERE cedula = ?");
        $stmtA->execute([$search_cedula]);
        $aprendiz = $stmtA->fetch(PDO::FETCH_ASSOC);

        if ($aprendiz) {
            // Traer TODAS las evidencias de la ficha que le corresponde al aprendiz
            // y hacer LEFT JOIN con sus calificaciones personales.
            // Asi mostramos incluso las que NO le han calificado (obligaciones curriculares).
            $stmtH = $db->prepare("
                SELECT 
                    e.codigo_evidencia, e.fase, e.guia_aprendizaje, e.fecha_entrega, e.fecha_inicio,
                    COALESCE(c.estado_calificacion, 'Pendiente') as nota,
                    c.fecha_calificacion
                FROM evidencias e
                LEFT JOIN calificaciones c ON e.id = c.id_evidencia AND c.id_aprendiz = ?
                WHERE e.codigo_curso = ?
                ORDER BY e.fecha_entrega ASC
            ");
            $stmtH->execute([$aprendiz['id'], $aprendiz['codigo_curso']]);
            $historial = $stmtH->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $mensaje = "No se encontró ningún aprendiz matriculado con la cédula proporcionada ($search_cedula).";
        }
    }
?>
    <div class="mb-6">
        <div class="flex items-center text-sm font-medium text-gray-400 dark:text-gray-500 mb-2 hover:text-indigo-500 transition-colors w-fit">
            <a href="?view=reportes"><i class="fa-solid fa-arrow-left mr-1"></i> Volver al Centro de Reportes</a>
        </div>
        <h1 class="text-3xl font-black text-gray-800 dark:text-gray-100 uppercase tracking-tight"><i class="fa-solid fa-id-card-clip text-indigo-500 mr-2"></i> Expediente Académico</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Situación curricular proyectada contra el pensum de la ficha asignada.</p>
    </div>

    <!-- Buscador -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6 mb-6">
        <form method="GET" class="flex flex-col md:flex-row items-end gap-4">
            <input type="hidden" name="view" value="reportes">
            <input type="hidden" name="action" value="historial">
            
            <div class="flex-1 w-full">
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2 dark:text-gray-400">Documento de Identidad (Cédula)</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                        <i class="fa-solid fa-magnifying-glass"></i>
                    </span>
                    <input type="text" name="cedula" value="<?= htmlspecialchars($search_cedula) ?>" placeholder="Ej: 1020304050" required class="w-full pl-10 pr-3 py-3 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:ring-indigo-500 focus:border-indigo-500 transition-colors shadow-sm font-bold text-lg tracking-wider">
                </div>
            </div>
            <button type="submit" class="w-full md:w-auto px-8 py-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg shadow-sm font-black uppercase tracking-widest transition-colors h-fit"><i class="fa-solid fa-satellite-dish mr-2"></i> Rastrear</button>
        </form>
        <?php if($mensaje): ?>
            <p class="mt-4 text-red-600 dark:text-red-400 font-medium text-sm"><i class="fa-solid fa-circle-exclamation mr-1"></i> <?= $mensaje ?></p>
        <?php endif; ?>
    </div>

    <!-- Resultados -->
    <?php if($aprendiz): ?>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
            <!-- Header del Expediente -->
            <div class="bg-gray-50 dark:bg-gray-900 border-b border-gray-100 dark:border-gray-700 p-6 flex justify-between items-start">
                <div>
                    <h2 class="text-2xl font-black text-gray-800 dark:text-gray-100 uppercase"><?= htmlspecialchars($aprendiz['apellidos'] . ', ' . $aprendiz['nombres']) ?></h2>
                    <p class="text-gray-500 dark:text-gray-400 font-mono mt-1"><i class="fa-regular fa-id-badge mr-1"></i> <?= htmlspecialchars($aprendiz['cedula']) ?> &bull; <i class="fa-regular fa-envelope ml-2 mr-1"></i> <?= htmlspecialchars($aprendiz['correo']) ?></p>
                </div>
                <div class="text-right">
                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest block mb-1">Matrícula Activa en</span>
                    <span class="px-3 py-1 bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800 rounded font-black text-xl shadow-sm tracking-tight"><?= htmlspecialchars($aprendiz['codigo_curso']) ?></span>
                </div>
            </div>
            
            <!-- Tabla de Historial Curricular -->
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50/50 dark:bg-gray-800/50">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-black text-gray-500 dark:text-gray-400 uppercase tracking-widest">Código Evidencia</th>
                        <th class="px-6 py-4 text-left text-xs font-black text-gray-500 dark:text-gray-400 uppercase tracking-widest hidden md:table-cell">Etapa / Guía</th>
                        <th class="px-6 py-4 text-left text-xs font-black text-gray-500 dark:text-gray-400 uppercase tracking-widest">Cierre Dictaminado</th>
                        <th class="px-6 py-4 text-right text-xs font-black text-gray-500 dark:text-gray-400 uppercase tracking-widest">Estatus de Nota</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">
                    <?php 
                    $aprobadas = 0; $total = count($historial);
                    if(empty($historial)): ?>
                        <tr><td colspan="4" class="px-6 py-10 text-center text-gray-500">La ficha del alumno no tiene pensum cargado.</td></tr>
                    <?php else: foreach($historial as $h): 
                        $fv = new DateTime($h['fecha_entrega']);
                        $hoy = new DateTime();
                        
                        $is_aprobada = ($h['nota'] === 'Aprobada');
                        if($is_aprobada) $aprobadas++;

                        // Calculo de estilos del estatus
                        if ($h['nota'] === 'Aprobada') {
                            $badge = '<span class="px-3 py-1 rounded bg-green-100 text-green-800 font-bold text-xs uppercase tracking-wider"><i class="fa-solid fa-check mr-1"></i> A (Aprobada)</span>';
                        } elseif ($h['nota'] === 'Devuelta') {
                            $badge = '<span class="px-3 py-1 rounded bg-yellow-100 text-yellow-800 font-bold text-xs uppercase tracking-wider"><i class="fa-solid fa-rotate-left mr-1"></i> D (Devuelta)</span>';
                        } else {
                            // Sin calificar. ¿Vencida o a tiempo?
                            if ($hoy > $fv) {
                                $badge = '<span class="px-3 py-1 rounded bg-red-100 text-red-800 font-bold text-xs uppercase tracking-wider"><i class="fa-solid fa-triangle-exclamation mr-1"></i> Moroso / Sin Nota</span>';
                            } else {
                                $badge = '<span class="px-3 py-1 rounded bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300 font-bold text-xs uppercase tracking-wider"><i class="fa-regular fa-clock mr-1"></i> En ventana</span>';
                            }
                        }
                    ?>
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                            <td class="px-6 py-4">
                                <span class="font-bold text-gray-800 dark:text-gray-200"><?= htmlspecialchars($h['codigo_evidencia']) ?></span>
                            </td>
                            <td class="px-6 py-4 hidden md:table-cell">
                                <span class="block text-xs text-gray-500 uppercase font-semibold"><?= htmlspecialchars($h['fase']) ?></span>
                                <span class="block text-sm font-medium text-gray-700 dark:text-gray-400">Guía <?= htmlspecialchars($h['guia_aprendizaje']) ?></span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="text-sm font-medium text-gray-600 dark:text-gray-400"><?= $fv->format('d M y - H:i') ?></span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <?= $badge ?>
                                <?php if($h['fecha_calificacion']): ?>
                                    <span class="block text-[9px] text-gray-400 mt-1 uppercase tracking-widest border-t border-gray-100 dark:border-gray-700 pt-1 mt-2">Corregido: <?= (new DateTime($h['fecha_calificacion']))->format('d/m') ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
            <?php if($total > 0): 
                $porcentaje = round(($aprobadas / $total) * 100);
            ?>
            <div class="bg-gray-50 dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700 p-6 flex items-center justify-between">
                <div class="text-gray-500 font-medium text-sm">Progreso Analítico del Pensum:</div>
                <div class="flex items-center gap-4">
                    <div class="w-48 h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                        <div class="h-full bg-indigo-500" style="width: <?= $porcentaje ?>%"></div>
                    </div>
                    <span class="font-black text-xl text-indigo-600 dark:text-indigo-400"><?= $porcentaje ?>% <span class="text-sm">Aprobado</span></span>
                </div>
            </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

<?php else: 
// --- VISTA 2: MENÚ DE REPORTES PRINCIPAL ---
?>
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-800 dark:text-gray-100 uppercase tracking-tight">Reportes y Exportaciones</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Generación de informes de gestión.</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Card Export 1 -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6 flex flex-col items-center text-center hover:shadow-md transition-shadow">
            <div class="w-16 h-16 rounded-full bg-sena-light/10 text-sena text-2xl flex items-center justify-center mb-4"><i class="fa-solid fa-file-excel"></i></div>
            <h3 class="font-bold text-gray-800 dark:text-gray-200">Listado de Aprendices</h3>
            <p class="text-sm text-gray-500 mt-2 mb-4">Exportar todos los aprendices activos en formato CSV o Excel.</p>
            <button onclick="alert('Exportación CSV requiere librería PHPOffice. Restrinjido por seguridad por el Arquitecto.')" class="mt-auto px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded font-semibold text-sm hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">Exportar .CSV</button>
        </div>

        <!-- Card Export 2 -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6 flex flex-col items-center text-center hover:shadow-md transition-shadow">
            <div class="w-16 h-16 rounded-full bg-red-50 text-red-600 dark:bg-red-900/20 dark:text-red-400 text-2xl flex items-center justify-center mb-4"><i class="fa-solid fa-file-pdf"></i></div>
            <h3 class="font-bold text-gray-800 dark:text-gray-200">Estado de un Curso</h3>
            <p class="text-sm text-gray-500 mt-2 mb-4">Exportar sábanas de notas completas de una ficha.</p>
            <button onclick="alert('Exportación PDF bloqueada. Instalación de FPDF requerida.')" class="mt-auto px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded font-semibold text-sm hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">Exportar .PDF</button>
        </div>

        <!-- Card Export 3 -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-indigo-100 dark:border-indigo-800 p-6 flex flex-col items-center text-center hover:shadow-indigo-500/20 hover:shadow-lg transition-all ring-1 ring-indigo-50 dark:ring-0">
            <div class="absolute top-3 right-3"><span class="flex h-3 w-3"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-indigo-400 opacity-75"></span><span class="relative inline-flex rounded-full h-3 w-3 bg-indigo-500"></span></span></div>
            <div class="w-16 h-16 rounded-full bg-indigo-50 text-indigo-600 dark:bg-indigo-900/40 dark:text-indigo-400 text-2xl flex items-center justify-center mb-4"><i class="fa-solid fa-id-card-clip"></i></div>
            <h3 class="font-bold text-gray-800 dark:text-gray-200">Expediente Individual</h3>
            <p class="text-sm text-gray-500 mt-2 mb-4">Rastreo de situación curricular y morosidad de aprendiz por Cédula.</p>
            <a href="?view=reportes&action=historial" class="mt-auto block w-full px-4 py-2 bg-indigo-600 text-white font-bold tracking-widest uppercase text-xs rounded hover:bg-indigo-700 shadow shadow-indigo-200 dark:shadow-none transition-colors">Acceder al Motor</a>
        </div>
    </div>
<?php endif; ?>
