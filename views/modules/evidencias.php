<!-- views/modules/evidencias.php -->
<?php
$db = Database::connect();
$mensaje = '';
$tipo_mensaje = '';

// --- BLOQUE CRUD MULTI-FICHA (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // CREAR / EDITAR
    if ($_POST['action'] === 'save_evidencia') {
        $id = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);
        $codigo_curso = trim($_POST['ficha_owner'] ?? '');
        $codigo = trim($_POST['codigo_evidencia'] ?? '');
        $fase = $_POST['fase'] ?? '';
        $guia = $_POST['guia_aprendizaje'] ?? '';
        $descripcion = trim($_POST['descripcion'] ?? '');
        $observaciones = trim($_POST['observaciones'] ?? '');
        $fecha_inicio = $_POST['fecha_inicio'] ?? '';
        $fecha_entrega = $_POST['fecha_entrega'] ?? '';

        if ($codigo && $fase && $guia && $fecha_inicio && $fecha_entrega && $codigo_curso) {
            try {
                if (empty($id)) {
                    $stmt = $db->prepare("INSERT INTO evidencias (codigo_curso, fase, guia_aprendizaje, codigo_evidencia, descripcion, observaciones, fecha_inicio, fecha_entrega) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$codigo_curso, $fase, $guia, $codigo, $descripcion, $observaciones, $fecha_inicio, $fecha_entrega]);
                    $mensaje = "Evidencia registrada y anclada a la Ficha $codigo_curso.";
                } else {
                    $stmt = $db->prepare("UPDATE evidencias SET fase=?, guia_aprendizaje=?, codigo_evidencia=?, descripcion=?, observaciones=?, fecha_inicio=?, fecha_entrega=? WHERE id=? AND codigo_curso=?");
                    $stmt->execute([$fase, $guia, $codigo, $descripcion, $observaciones, $fecha_inicio, $fecha_entrega, $id, $codigo_curso]);
                    $mensaje = "Configuración de evidencia actualizada para $codigo_curso.";
                }
                $tipo_mensaje = "success";
            } catch (PDOException $e) {
                $mensaje = "Error SQ: " . $e->getMessage();
                $tipo_mensaje = "error";
            }
        } else {
            $mensaje = "Datos incompletos.";
            $tipo_mensaje = "warning";
        }
    }

    // REPLICAR (CLONACIÓN POR VALOR)
    if ($_POST['action'] === 'replicar_evidencia') {
        $id_origen = filter_input(INPUT_POST, 'id_origen', FILTER_SANITIZE_NUMBER_INT);
        $curso_destino = trim($_POST['curso_destino'] ?? '');
        $fecha_inicio = $_POST['nueva_fecha_inicio'] ?? '';
        $fecha_entrega = $_POST['nueva_fecha_entrega'] ?? '';

        if ($id_origen && $curso_destino && $fecha_inicio && $fecha_entrega) {
            try {
                $db->beginTransaction();
                // 1. Obtener la data maestra original
                $stmtOriginal = $db->prepare("SELECT * FROM evidencias WHERE id = ?");
                $stmtOriginal->execute([$id_origen]);
                $original = $stmtOriginal->fetch(PDO::FETCH_ASSOC);

                if ($original) {
                    // 2. Insertar réplica con nuevas fechas y nuevo curso
                    $stmtClone = $db->prepare("INSERT INTO evidencias (codigo_curso, fase, guia_aprendizaje, codigo_evidencia, descripcion, observaciones, fecha_inicio, fecha_entrega) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmtClone->execute([
                        $curso_destino, 
                        $original['fase'], 
                        $original['guia_aprendizaje'], 
                        $original['codigo_evidencia'], 
                        $original['descripcion'], 
                        $original['observaciones'], 
                        $fecha_inicio, 
                        $fecha_entrega
                    ]);
                    $db->commit();
                    $mensaje = "Pensum duplicado exitosamente hacia la ficha $curso_destino.";
                    $tipo_mensaje = "success";
                } else {
                    throw new Exception("La evidencia origen no existe (404).");
                }
            } catch (Exception $e) {
                $db->rollBack();
                $mensaje = "Error al replicar la data: " . $e->getMessage();
                $tipo_mensaje = "error";
            }
        } else {
            $mensaje = "Debes proveer las fechas y la ficha destino.";
            $tipo_mensaje = "warning";
        }
    }

    // ELIMINAR
    if ($_POST['action'] === 'delete_evidencia') {
        $id_borrar = filter_input(INPUT_POST, 'delete_id', FILTER_SANITIZE_NUMBER_INT);
        if ($id_borrar) {
            try {
                $stmt = $db->prepare("DELETE FROM evidencias WHERE id = ?");
                $stmt->execute([$id_borrar]);
                $mensaje = "Evidencia fulminada (eliminación en cascada de calificaciones procesada).";
                $tipo_mensaje = "success";
            } catch (PDOException $e) {
                $mensaje = "No se pudo eliminar la evidencia: " . $e->getMessage();
                $tipo_mensaje = "error";
            }
        }
    }
}
// --- FIN BLOQUE CRUD ---

$vista_activa = isset($_GET['ficha']) ? trim($_GET['ficha']) : null;
$fasesEnum = ['Fase I Análisis', 'Fase II Planeación', 'Fase III Ejecución'];
$guiasEnum = ['GA1', 'GA2', 'GA3', 'GA4', 'GA5', 'GA6', 'GA7', 'GA8', 'GA9'];

// Si NO HAY ficha en URL, renderizar MODO DASHBOARD DE FICHAS
if (!$vista_activa):
    $fichas_metrics = $db->query("
        SELECT 
            c.codigo_curso,
            (SELECT COUNT(*) FROM evidencias WHERE codigo_curso = c.codigo_curso) AS total_evidencias,
            (SELECT COUNT(a.id) FROM aprendices a WHERE a.codigo_curso = c.codigo_curso) AS total_alumnos
        FROM (SELECT DISTINCT codigo_curso FROM aprendices) c
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Progreso aislado 100% por ficha
    foreach ($fichas_metrics as &$fm) {
        if ($fm['total_alumnos'] == 0 || $fm['total_evidencias'] == 0) {
            $fm['cerradas'] = 0;
            $fm['en_curso'] = $fm['total_evidencias'];
            $fm['porcentaje'] = 0;
            continue;
        }
        
        $stmt = $db->prepare("
            SELECT e.id, 
                   (SELECT COUNT(*) FROM calificaciones cal 
                    WHERE cal.id_evidencia = e.id 
                    AND cal.estado_calificacion IN ('Aprobada', 'Devuelta')
                   ) as notas_puestas
            FROM evidencias e
            WHERE e.codigo_curso = ?
        ");
        $stmt->execute([$fm['codigo_curso']]);
        $ev_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $cerradas = 0;
        foreach($ev_stats as $es) {
            if ($es['notas_puestas'] >= $fm['total_alumnos']) {
                $cerradas++;
            }
        }
        $fm['cerradas'] = $cerradas;
        $fm['en_curso'] = $fm['total_evidencias'] - $cerradas;
        $fm['porcentaje'] = $fm['total_evidencias'] > 0 ? round(($cerradas / $fm['total_evidencias']) * 100) : 0;
    }
?>
    <!-- ==================== VISTA 1: DASHBOARD FICHAS ==================== -->
    <div class="mb-6 flex justify-between items-center">
        <div>
            <h1 class="text-3xl font-bold text-gray-800 dark:text-gray-100 uppercase tracking-tight">Seleccionar Ficha Técnica</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Hacé clic en un curso para gestionar su pensum aislado y sus fechas de entrega.</p>
        </div>
    </div>

    <?php if ($mensaje): ?>
    <div class="mb-6 p-4 rounded-lg <?= $tipo_mensaje === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?> border shadow-sm">
        <i class="fa-solid <?= $tipo_mensaje === 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation' ?> mr-2"></i> <?= htmlspecialchars($mensaje) ?>
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php if(empty($fichas_metrics)): ?>
            <div class="col-span-full p-6 text-center text-gray-400 dark:bg-gray-800 rounded-xl border border-dashed border-gray-300 dark:border-gray-700">Sin cursos registrados en la base. Agregá aprendices primero.</div>
        <?php else: foreach($fichas_metrics as $ficha): ?>
            <a href="?view=evidencias&ficha=<?= urlencode($ficha['codigo_curso']) ?>" class="block group">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6 hover:shadow-lg hover:border-sena/50 transition-all relative overflow-hidden h-full flex flex-col">
                    <div class="absolute inset-x-0 bottom-0 h-1.5 bg-gray-100 dark:bg-gray-700">
                        <div class="h-full bg-sena transition-all duration-1000" style="width: <?= $ficha['porcentaje'] ?>%"></div>
                    </div>
                    
                    <div class="flex justify-between items-start mb-6">
                        <div>
                            <span class="text-xs font-bold uppercase tracking-widest text-indigo-500 dark:text-indigo-400 block mb-1">Pensum Curso</span>
                            <h4 class="font-black text-2xl text-gray-800 dark:text-gray-100 tracking-tight group-hover:text-sena transition-colors"><?= htmlspecialchars($ficha['codigo_curso']) ?></h4>
                        </div>
                        <div class="w-12 h-12 rounded-full border-4 border-gray-50 dark:border-gray-700/50 flex items-center justify-center font-bold text-gray-600 dark:text-gray-300 group-hover:bg-sena group-hover:border-sena-light/30 group-hover:text-white transition-all">
                            <i class="fa-solid fa-arrow-right -rotate-45 group-hover:rotate-0 transition-transform"></i>
                        </div>
                    </div>
                    
                    <div class="mt-auto flex gap-3">
                        <div class="bg-gray-50 dark:bg-gray-900/50 rounded-lg p-3 text-center flex-1 border border-gray-100 dark:border-gray-700">
                            <span class="block text-2xl font-bold text-gray-800 dark:text-gray-100"><?= $ficha['total_evidencias'] ?></span>
                            <span class="block text-[10px] uppercase font-bold tracking-widest text-gray-500">Módulos</span>
                        </div>
                        <div class="bg-yellow-50 dark:bg-yellow-900/10 rounded-lg p-3 text-center flex-1 border border-yellow-100 dark:border-yellow-900/50">
                            <span class="block text-2xl font-bold text-yellow-600 dark:text-yellow-500"><?= $ficha['en_curso'] ?></span>
                            <span class="block text-[10px] uppercase font-bold tracking-widest text-yellow-600/70">Abiertos</span>
                        </div>
                    </div>
                </div>
            </a>
        <?php endforeach; endif; ?>
    </div>

<?php 
// ==================== VISTA 2: LISTADO DE EVIDENCIAS POR FICHA ESPECÍFICA ====================
else: 
    $stmtE = $db->prepare("SELECT * FROM evidencias WHERE codigo_curso = ? ORDER BY fecha_inicio ASC, fecha_entrega ASC");
    $stmtE->execute([$vista_activa]);
    $evidencias_curso = $stmtE->fetchAll(PDO::FETCH_ASSOC);

    // Sacamos fichas alternativas para el Modal Clonador
    $stmtDestinos = $db->prepare("SELECT DISTINCT codigo_curso FROM aprendices WHERE codigo_curso != ?");
    $stmtDestinos->execute([$vista_activa]);
    $otras_fichas = $stmtDestinos->fetchAll(PDO::FETCH_COLUMN);
?>

    <div class="mb-6">
        <div class="flex items-center text-sm font-medium text-gray-400 dark:text-gray-500 mb-2 hover:text-sena transition-colors w-fit">
            <a href="?view=evidencias"><i class="fa-solid fa-arrow-left mr-1"></i> Volver a Selector de Fichas</a>
        </div>
        <div class="flex justify-between items-center bg-white dark:bg-gray-800 p-6 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm border-l-4 border-l-sena">
            <div>
                <span class="text-xs uppercase font-bold tracking-widest text-indigo-500 dark:text-indigo-400 block mb-1">Administrando Currícula Aislada</span>
                <h1 class="text-3xl font-black text-gray-800 dark:text-gray-100 uppercase tracking-tight">Ficha: <?= htmlspecialchars($vista_activa) ?></h1>
            </div>
            <button onclick="openModalCreate()" class="bg-indigo-600 hover:bg-indigo-700 text-white py-2 px-5 rounded-lg shadow font-semibold transition-colors flex items-center gap-2">
                <i class="fa-solid fa-plus"></i> Añadir Unidad
            </button>
        </div>
    </div>

    <?php if ($mensaje): ?>
    <div class="mb-6 p-4 rounded-lg <?= $tipo_mensaje === 'success' ? 'bg-green-100 text-green-800 border-green-200' : 'bg-red-100 text-red-800 border-red-200' ?> border shadow-sm">
        <i class="fa-solid <?= $tipo_mensaje === 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation' ?> mr-2"></i> <?= htmlspecialchars($mensaje) ?>
    </div>
    <?php endif; ?>

    <div class="space-y-4 relative before:absolute before:inset-0 before:ml-5 before:-translate-x-px md:before:mx-auto md:before:translate-x-0 before:h-full before:w-0.5 before:bg-gradient-to-b before:from-transparent before:via-gray-300 dark:before:via-gray-600 before:to-transparent">
        <?php if(empty($evidencias_curso)): ?>
            <div class="relative flex items-center justify-between md:justify-normal md:odd:flex-row-reverse group is-active text-center bg-gray-50 dark:bg-gray-800/50 p-6 rounded-xl border border-dashed border-gray-300 dark:border-gray-700 mx-10 z-10">
                <div class="w-full text-gray-400 font-medium">Esta ficha está vacía. Su pensum no tiene evidencias.</div>
            </div>
        <?php else: foreach($evidencias_curso as $e): 
            $fi = new DateTime($e['fecha_inicio']);
            $fv = new DateTime($e['fecha_entrega']);
            $hoy = new DateTime();
            $estado_vencido = ($hoy > $fv);
            $estado_futuro = ($hoy < $fi);
            // TimeLine logic estético
            $colorBadge = $estado_vencido ? 'bg-red-500 border-red-200' : ($estado_futuro ? 'bg-yellow-500 border-yellow-200' : 'bg-sena border-sena-light/50');
        ?>
        <!-- Modulo Estilo Timeline -->
        <div class="relative flex items-center justify-between md:justify-normal group pl-12 md:pl-0 z-10 w-full mb-6">
            
            <div class="hidden md:flex flex-1 w-full justify-end text-right pr-12 opacity-80 group-hover:opacity-100 transition-opacity">
                <!-- Fechas -->
                <div class="flex flex-col items-end gap-1">
                    <span class="text-xs uppercase font-bold tracking-widest <?= $estado_futuro ? 'text-yellow-500' : 'text-sena' ?>">Vigencia Apertura</span>
                    <span class="text-xl font-black text-gray-800 dark:text-gray-200"><?= $fi->format('d M') ?> <span class="text-sm font-medium text-gray-500"><?= $fi->format('H:i') ?>hs</span></span>
                    <div class="h-px w-10 bg-gray-300 dark:bg-gray-600 my-1"></div>
                    <span class="text-xs uppercase font-bold tracking-widest <?= $estado_vencido ? 'text-red-500' : 'text-gray-500' ?>">Cierre Requerido</span>
                    <span class="text-xl font-black text-gray-800 dark:text-gray-200"><?= $fv->format('d M') ?> <span class="text-sm font-medium text-gray-500"><?= $fv->format('H:i') ?>hs</span></span>
                </div>
            </div>

            <!-- Dot Marker Centro -->
            <div class="w-10 h-10 rounded-full border-4 shadow shrink-0 absolute left-0 md:relative md:left-auto flex items-center justify-center bg-white dark:bg-gray-800 <?= $colorBadge ?>">
                <i class="fa-solid fa-file-invoice text-white text-[10px]"></i>
            </div>

            <div class="flex-1 w-full md:pl-12">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-5 hover:shadow-md transition-shadow relative overflow-hidden group-hover:border-gray-300 dark:group-hover:border-gray-500">
                    <div class="flex flex-col sm:flex-row justify-between items-start gap-4">
                        <div>
                            <div class="flex items-center gap-2 mb-2">
                                <span class="px-2 py-0.5 rounded bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300 text-[10px] font-black tracking-widest uppercase border border-gray-200 dark:border-gray-600">Guía <?= htmlspecialchars($e['guia_aprendizaje']) ?></span>
                                <span class="px-2 py-0.5 rounded bg-blue-50 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400 border border-blue-100 dark:border-blue-800 text-[10px] font-black tracking-widest uppercase"><?= htmlspecialchars($e['fase']) ?></span>
                            </div>
                            <h3 class="font-bold text-gray-800 dark:text-gray-100 text-xl tracking-tight mb-1"><?= htmlspecialchars($e['codigo_evidencia']) ?></h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed"><?= htmlspecialchars($e['descripcion']) ?></p>
                            
                            <!-- Fechas Mobile Fallback -->
                            <div class="mt-3 md:hidden flex gap-4 text-xs font-semibold p-2 bg-gray-50 dark:bg-gray-900/50 rounded border border-gray-100 dark:border-gray-700">
                                <div class="text-sena">INICIO: <?= $fi->format('d/m/y H:i') ?></div>
                                <div class="<?= $estado_vencido ? 'text-red-500' : 'text-gray-500' ?>">FIN: <?= $fv->format('d/m/y H:i') ?></div>
                            </div>
                        </div>

                        <!-- Panel Interactivo Hover -->
                        <div class="flex flex-row sm:flex-col gap-2 shrink-0">
                            <!-- Boton Clonar (Solo activo si hay otras fichas) -->
                            <button <?= empty($otras_fichas) ? 'disabled title="Sin fichas alternas"' : "title=\"Clonar unidad\" onclick='openModalClone(" . json_encode($e, JSON_HEX_APOS | JSON_HEX_QUOT) . ")'" ?> class="w-10 h-10 flex justify-center items-center rounded-lg bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 text-indigo-600 dark:bg-indigo-900/30 dark:hover:bg-indigo-900/60 dark:border-indigo-800 dark:text-indigo-400 transition-colors shadow-sm disabled:opacity-30 disabled:cursor-not-allowed">
                                <i class="fa-solid fa-copy"></i>
                            </button>
                            <button title="Ajustar parámetros" onclick='openModalEdit(<?= json_encode($e, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' class="w-10 h-10 flex justify-center items-center rounded-lg bg-gray-50 hover:bg-blue-50 border border-gray-200 hover:border-blue-200 text-blue-600 dark:bg-gray-700 dark:border-gray-600 dark:text-blue-400 dark:hover:bg-gray-600 transition-colors shadow-sm">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </button>
                            <button title="Destruir módulo" onclick="confirmDelete(<?= $e['id'] ?>)" class="w-10 h-10 flex justify-center items-center rounded-lg bg-gray-50 hover:bg-red-50 border border-gray-200 hover:border-red-200 text-red-600 dark:bg-gray-700 dark:border-gray-600 dark:text-red-400 dark:hover:bg-gray-600 transition-colors shadow-sm">
                                <i class="fa-solid fa-trash-can"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; endif; ?>
    </div>


    <!-- ============================================== -->
    <!-- MODALES JS (CREATE/EDIT y CLONE) -->
    <!-- ============================================== -->

    <form id="deleteForm" method="POST" style="display:none;">
        <input type="hidden" name="action" value="delete_evidencia">
        <input type="hidden" name="delete_id" id="delete_id">
    </form>

    <div id="modalOverlay" class="fixed inset-0 bg-gray-900/60 dark:bg-black/80 z-50 hidden flex items-center justify-center backdrop-blur-sm transition-opacity opacity-0 duration-300">
        
        <!-- MODAL CRUD (NUEVO/EDICIÓN) -->
        <div id="modalCRUD" class="hidden bg-white dark:bg-gray-800 rounded-xl shadow-2xl border border-gray-100 dark:border-gray-700 w-full max-w-3xl mx-4 overflow-hidden transform scale-95 transition-transform duration-300">
            <div class="bg-gray-50 dark:bg-gray-900/80 px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
                <h3 class="font-black text-gray-800 dark:text-gray-100 text-lg uppercase tracking-widest" id="modalTitleCrud">Nueva Evidencia</h3>
                <button onclick="closeModal()" class="text-gray-400 hover:text-gray-800 dark:hover:text-white transition-colors"><i class="fa-solid fa-times text-xl"></i></button>
            </div>
            
            <form method="POST" class="p-6">
                <input type="hidden" name="action" value="save_evidencia">
                <input type="hidden" name="id" id="form_id" value="">
                <input type="hidden" name="ficha_owner" value="<?= htmlspecialchars($vista_activa) ?>">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div class="col-span-1 border-l-2 pl-3 border-indigo-500">
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-1">Código Identificador *</label>
                        <input type="text" name="codigo_evidencia" id="form_codigo" required placeholder="Ej: EVI-01-REQ" class="w-full font-mono text-base font-bold bg-transparent border-0 border-b-2 border-gray-200 focus:border-indigo-500 focus:ring-0 p-0 dark:text-white">
                    </div>

                    <div class="col-span-1 flex gap-4">
                        <div class="flex-1 bg-yellow-50 dark:bg-yellow-900/10 p-2 rounded border border-yellow-200 dark:border-yellow-800">
                            <label class="block text-[10px] font-bold text-yellow-600 uppercase tracking-widest mb-1">Apertura (Visible)</label>
                            <input type="datetime-local" name="fecha_inicio" id="form_fecha_i" required class="w-full bg-transparent border-0 text-sm font-semibold p-0 focus:ring-0 dark:text-gray-200">
                        </div>
                        <div class="flex-1 bg-red-50 dark:bg-red-900/10 p-2 rounded border border-red-200 dark:border-red-800">
                            <label class="block text-[10px] font-bold text-red-600 uppercase tracking-widest mb-1">Cierre Estricto</label>
                            <input type="datetime-local" name="fecha_entrega" id="form_fecha_f" required class="w-full bg-transparent border-0 text-sm font-semibold p-0 focus:ring-0 dark:text-gray-200">
                        </div>
                    </div>
                    
                    <div class="col-span-1">
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2">Fase Académica *</label>
                        <select name="fase" id="form_fase" required class="w-full rounded bg-gray-50 dark:bg-gray-900 border-gray-200 dark:border-gray-700 dark:text-gray-200 shadow-inner font-medium text-sm">
                            <?php foreach($fasesEnum as $fase): ?><option value="<?= $fase ?>"><?= $fase ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-span-1">
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2">Guía Estipulada *</label>
                        <select name="guia_aprendizaje" id="form_guia" required class="w-full rounded bg-gray-50 dark:bg-gray-900 border-gray-200 dark:border-gray-700 dark:text-gray-200 shadow-inner font-medium text-sm">
                            <?php foreach($guiasEnum as $guia): ?><option value="<?= $guia ?>">Guía <?= $guia ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="md:col-span-2 bg-gray-50 dark:bg-gray-900 p-4 rounded-xl border border-gray-200 dark:border-gray-700">
                        <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2"><i class="fa-solid fa-align-left"></i> Contenido Curricular</label>
                        <textarea name="descripcion" id="form_desc" rows="3" required class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 text-sm mb-4 font-medium" placeholder="Descripción general de la tarea..."></textarea>
                        
                        <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2"><i class="fa-solid fa-lock text-orange-400"></i> Observaciones Internas (Profe)</label>
                        <textarea name="observaciones" id="form_obs" rows="2" class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 text-sm font-medium opacity-80" placeholder="Tips de evaluación invisibles al alumno..."></textarea>
                    </div>
                </div>
                
                <div class="flex justify-end gap-3">
                    <button type="button" onclick="closeModal()" class="px-5 py-2.5 bg-white border border-gray-300 dark:border-gray-600 text-gray-700 dark:bg-gray-800 dark:text-gray-300 rounded shadow-sm font-bold uppercase tracking-widest text-xs hover:bg-gray-50">Cancelar</button>
                    <button type="submit" id="btnGuardarCrud" class="px-6 py-2.5 bg-sena hover:bg-sena-dark text-white rounded shadow-sm font-bold uppercase tracking-widest text-xs"><i class="fa-solid fa-save mr-1"></i> Guardar En Esta Ficha</button>
                </div>
            </form>
        </div>


        <!-- MODAL CLONADOR (REPLICAR) -->
        <div id="modalCLONE" class="hidden bg-white dark:bg-gray-800 rounded-xl shadow-2xl border border-indigo-500 w-full max-w-lg mx-4 overflow-hidden transform scale-95 transition-transform duration-300">
            <div class="bg-indigo-600 px-6 py-5 border-b border-indigo-700 flex justify-between items-center text-white">
                <div>
                    <h3 class="font-black text-xl uppercase tracking-widest mb-1"><i class="fa-solid fa-copy mr-2 text-indigo-200"></i> Replicar Módulo</h3>
                    <p class="text-xs text-indigo-200 font-medium">Migrar contenido intacto hacia otro grupo.</p>
                </div>
                <button onclick="closeModal()" class="text-indigo-200 hover:text-white transition-colors"><i class="fa-solid fa-times text-2xl"></i></button>
            </div>
            
            <form method="POST" class="p-6">
                <input type="hidden" name="action" value="replicar_evidencia">
                <input type="hidden" name="id_origen" id="clone_id" value="">
                
                <div class="bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg p-3 mb-6 relative overflow-hidden">
                    <span class="absolute right-0 top-0 bg-indigo-500 text-white text-[9px] font-black uppercase px-2 py-0.5 rounded-bl-lg">SOURCE</span>
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Se copiará la estructura de:</p>
                    <p class="font-bold text-gray-800 dark:text-gray-200 text-sm mt-1" id="clone_name">EVI-XX</p>
                </div>
                
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-1">Destino (Ficha de Inyección) *</label>
                <select name="curso_destino" required class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 mb-6 font-bold text-sena shadow-inner">
                    <option value="">-- Elija ficha destino --</option>
                    <?php foreach($otras_fichas as $f_dest): ?><option value="<?= $f_dest ?>"><?= $f_dest ?></option><?php endforeach; ?>
                </select>

                <div class="flex gap-4 mb-8">
                    <div class="flex-1">
                        <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1">Nueva Fecha Inicio</label>
                        <input type="datetime-local" name="nueva_fecha_inicio" required class="w-full bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 dark:text-gray-200 rounded text-sm shadow-sm">
                    </div>
                    <div class="flex-1">
                        <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1">Nueva Fecha Cierre</label>
                        <input type="datetime-local" name="nueva_fecha_entrega" required class="w-full bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 dark:text-gray-200 rounded text-sm shadow-sm">
                    </div>
                </div>
                
                <div class="flex justify-end gap-2">
                    <button type="submit" class="w-full py-3 bg-indigo-600 hover:bg-indigo-500 text-white rounded shadow font-black uppercase tracking-widest text-sm transition-colors cursor-pointer"><i class="fa-solid fa-bolt mr-2 text-indigo-200"></i> Disparar Replicación</button>
                </div>
            </form>
        </div>

    </div>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const overlay = document.getElementById('modalOverlay');
        const modalCRUD = document.getElementById('modalCRUD');
        const modalCLONE = document.getElementById('modalCLONE');
        const modalTitleCrud = document.getElementById('modalTitleCrud');
        const btnGuardarCrud = document.getElementById('btnGuardarCrud');
        
        const openModalBase = (modalElem) => {
            overlay.classList.remove('hidden');
            modalCRUD.classList.add('hidden');
            modalCLONE.classList.add('hidden');
            
            modalElem.classList.remove('hidden');
            setTimeout(() => {
                overlay.classList.remove('opacity-0');
                modalElem.classList.remove('scale-95');
            }, 10);
        };

        window.openModalCreate = () => {
            modalTitleCrud.innerText = "NUEVO MÓDULO AL PENSUM";
            document.getElementById('form_id').value = '';
            document.getElementById('form_codigo').value = '';
            document.getElementById('form_fecha_i').value = '';
            document.getElementById('form_fecha_f').value = '';
            document.getElementById('form_fase').value = '';
            document.getElementById('form_guia').value = '';
            document.getElementById('form_desc').value = '';
            document.getElementById('form_obs').value = '';
            btnGuardarCrud.innerHTML = '<i class="fa-solid fa-plus mr-1"></i> Publicar Módulo Nuevo';
            openModalBase(modalCRUD);
        };

        window.openModalEdit = (ev) => {
            modalTitleCrud.innerText = "RECONFIGURACIÓN DE PARÁMETROS (" + ev.codigo_evidencia + ")";
            document.getElementById('form_id').value = ev.id;
            document.getElementById('form_codigo').value = ev.codigo_evidencia;
            document.getElementById('form_fecha_i').value = ev.fecha_inicio.replace(' ', 'T').substring(0, 16);
            document.getElementById('form_fecha_f').value = ev.fecha_entrega.replace(' ', 'T').substring(0, 16);
            document.getElementById('form_fase').value = ev.fase;
            document.getElementById('form_guia').value = ev.guia_aprendizaje;
            document.getElementById('form_desc').value = ev.descripcion || '';
            document.getElementById('form_obs').value = ev.observaciones || '';
            btnGuardarCrud.innerHTML = '<i class="fa-solid fa-save mr-1"></i> Sobrescribir Configuración';
            openModalBase(modalCRUD);
        };

        window.openModalClone = (ev) => {
            document.getElementById('clone_id').value = ev.id;
            document.getElementById('clone_name').innerText = ev.codigo_evidencia + " (" + ev.fase + ")";
            openModalBase(modalCLONE);
        };

        window.closeModal = () => {
            overlay.classList.add('opacity-0');
            modalCRUD.classList.add('scale-95');
            modalCLONE.classList.add('scale-95');
            setTimeout(() => overlay.classList.add('hidden'), 300);
        };

        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) closeModal();
        });

        window.confirmDelete = (id) => {
            if (confirm("🚨 DESTRUCCIÓN EN CASCADA DECLARADA\nEsta evidencia aisalda y sus notas desaparecerán del servidor. ¿Estás seguro/a?")) {
                document.getElementById('delete_id').value = id;
                document.getElementById('deleteForm').submit();
            }
        };
    });
    </script>
<?php endif; ?>
