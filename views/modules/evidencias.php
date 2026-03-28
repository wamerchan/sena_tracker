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
                    $mensaje = "Evidencia registrada en la Ficha $codigo_curso.";
                } else {
                    $stmt = $db->prepare("UPDATE evidencias SET fase=?, guia_aprendizaje=?, codigo_evidencia=?, descripcion=?, observaciones=?, fecha_inicio=?, fecha_entrega=? WHERE id=? AND codigo_curso=?");
                    $stmt->execute([$fase, $guia, $codigo, $descripcion, $observaciones, $fecha_inicio, $fecha_entrega, $id, $codigo_curso]);
                    $mensaje = "Evidencia actualizada para $codigo_curso.";
                }
                $tipo_mensaje = "success";
            } catch (PDOException $e) {
                $mensaje = "Error: " . $e->getMessage();
                $tipo_mensaje = "error";
            }
        } else {
            $mensaje = "Datos incompletos.";
            $tipo_mensaje = "warning";
        }
    }

    // REPLICAR
    if ($_POST['action'] === 'replicar_evidencia') {
        $id_origen = filter_input(INPUT_POST, 'id_origen', FILTER_SANITIZE_NUMBER_INT);
        $curso_destino = trim($_POST['curso_destino'] ?? '');
        $fecha_inicio = $_POST['nueva_fecha_inicio'] ?? '';
        $fecha_entrega = $_POST['nueva_fecha_entrega'] ?? '';

        if ($id_origen && $curso_destino && $fecha_inicio && $fecha_entrega) {
            try {
                $db->beginTransaction();
                $stmtOriginal = $db->prepare("SELECT * FROM evidencias WHERE id = ?");
                $stmtOriginal->execute([$id_origen]);
                $original = $stmtOriginal->fetch(PDO::FETCH_ASSOC);

                if ($original) {
                    $stmtClone = $db->prepare("INSERT INTO evidencias (codigo_curso, fase, guia_aprendizaje, codigo_evidencia, descripcion, observaciones, fecha_inicio, fecha_entrega) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmtClone->execute([$curso_destino, $original['fase'], $original['guia_aprendizaje'], $original['codigo_evidencia'], $original['descripcion'], $original['observaciones'], $fecha_inicio, $fecha_entrega]);
                    $db->commit();
                    $mensaje = "Pensum duplicado hacia la ficha $curso_destino.";
                    $tipo_mensaje = "success";
                } else {
                    throw new Exception("La evidencia origen no existe.");
                }
            } catch (Exception $e) {
                $db->rollBack();
                $mensaje = "Error al replicar: " . $e->getMessage();
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
                $mensaje = "Evidencia eliminada correctamente.";
                $tipo_mensaje = "success";
            } catch (PDOException $e) {
                $mensaje = "No se pudo eliminar: " . $e->getMessage();
                $tipo_mensaje = "error";
            }
        }
    }
}

$vista_activa = isset($_GET['ficha']) ? trim($_GET['ficha']) : null;
$fasesEnum = ['Fase I Analisis', 'Fase II Planeacion', 'Fase III Ejecucion'];
$guiasEnum = ['GA1', 'GA2', 'GA3', 'GA4', 'GA5', 'GA6', 'GA7', 'GA8', 'GA9'];

// ==================== VISTA 1: DASHBOARD FICHAS ====================
if (!$vista_activa):
    $fichas_metrics = $db->query("
        SELECT
            c.codigo_curso,
            (SELECT COUNT(*) FROM evidencias WHERE codigo_curso = c.codigo_curso) AS total_evidencias,
            (SELECT COUNT(a.id) FROM aprendices a WHERE a.codigo_curso = c.codigo_curso) AS total_alumnos
        FROM (SELECT DISTINCT codigo_curso FROM aprendices) c
    ")->fetchAll(PDO::FETCH_ASSOC);

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
    <!-- VISTA 1: DASHBOARD FICHAS -->
    <div class="mb-8 animate-fade-in-up">
        <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white tracking-tight">Seleccionar Ficha Tecnica</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Hace clic en un curso para gestionar su pensum y fechas de entrega.</p>
    </div>

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

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        <?php if(empty($fichas_metrics)): ?>
            <div class="col-span-full card-modern p-10 text-center">
                <div class="w-16 h-16 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center mx-auto mb-4">
                    <i class="fa-solid fa-folder-open text-2xl text-gray-300 dark:text-gray-600"></i>
                </div>
                <p class="text-gray-500 dark:text-gray-400 font-medium">Sin cursos registrados. Agregue aprendices primero.</p>
            </div>
        <?php else: foreach($fichas_metrics as $i => $ficha): ?>
            <a href="?view=evidencias&ficha=<?= urlencode($ficha['codigo_curso']) ?>" class="block group">
                <div class="card-modern metric-card metric-indigo p-6 h-full flex flex-col card-glow-indigo" style="animation: fadeInUp 0.5s ease-out <?= $i * 80 ?>ms forwards; opacity: 0;">
                    <!-- Top section -->
                    <div class="flex justify-between items-start mb-5">
                        <div>
                            <span class="text-[10px] font-bold uppercase tracking-widest text-indigo-500 dark:text-indigo-400 block mb-1.5">Pensum Curso</span>
                            <h4 class="font-extrabold text-2xl text-gray-900 dark:text-white tracking-tight group-hover:text-sena transition-colors"><?= htmlspecialchars($ficha['codigo_curso']) ?></h4>
                        </div>
                        <div class="w-11 h-11 rounded-xl bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center text-indigo-500 dark:text-indigo-400 group-hover:bg-gradient-to-br group-hover:from-sena group-hover:to-emerald-400 group-hover:text-white group-hover:shadow-lg group-hover:shadow-sena/20 transition-all duration-300">
                            <i class="fa-solid fa-arrow-right group-hover:translate-x-0.5 transition-transform"></i>
                        </div>
                    </div>

                    <!-- Stats -->
                    <div class="mt-auto flex gap-3">
                        <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-3 text-center flex-1 border border-gray-100 dark:border-gray-700">
                            <span class="block text-xl font-extrabold text-gray-800 dark:text-gray-100"><?= $ficha['total_evidencias'] ?></span>
                            <span class="block text-[10px] uppercase font-bold tracking-widest text-gray-400 dark:text-gray-500">Modulos</span>
                        </div>
                        <div class="bg-amber-50 dark:bg-amber-900/20 rounded-xl p-3 text-center flex-1 border border-amber-100 dark:border-amber-900/30">
                            <span class="block text-xl font-extrabold text-amber-600 dark:text-amber-400"><?= $ficha['en_curso'] ?></span>
                            <span class="block text-[10px] uppercase font-bold tracking-widest text-amber-500/70">Abiertos</span>
                        </div>
                    </div>

                    <!-- Progress bar -->
                    <div class="mt-4 progress-bar-gradient h-1.5">
                        <div class="progress-fill" style="width: <?= $ficha['porcentaje'] ?>%"></div>
                    </div>
                    <span class="text-[10px] font-bold text-gray-400 mt-1.5 text-right"><?= $ficha['porcentaje'] ?>% completado</span>
                </div>
            </a>
        <?php endforeach; endif; ?>
    </div>

<?php
// ==================== VISTA 2: LISTADO DE EVIDENCIAS POR FICHA ====================
else:
    $stmtE = $db->prepare("SELECT * FROM evidencias WHERE codigo_curso = ? ORDER BY fecha_inicio ASC, fecha_entrega ASC");
    $stmtE->execute([$vista_activa]);
    $evidencias_curso = $stmtE->fetchAll(PDO::FETCH_ASSOC);

    $stmtDestinos = $db->prepare("SELECT DISTINCT codigo_curso FROM aprendices WHERE codigo_curso != ?");
    $stmtDestinos->execute([$vista_activa]);
    $otras_fichas = $stmtDestinos->fetchAll(PDO::FETCH_COLUMN);
?>

    <!-- Header -->
    <div class="mb-6 animate-fade-in-up">
        <a href="?view=evidencias" class="inline-flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400 hover:text-sena dark:hover:text-sena transition-colors font-medium mb-3">
            <i class="fa-solid fa-arrow-left text-xs"></i> Volver a Selector de Fichas
        </a>
        <div class="card-modern p-6 border-l-4 border-l-sena flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <span class="text-[10px] uppercase font-bold tracking-widest text-indigo-500 dark:text-indigo-400 block mb-1">Administrando Curricula</span>
                <h1 class="text-2xl font-extrabold text-gray-900 dark:text-white tracking-tight">Ficha: <?= htmlspecialchars($vista_activa) ?></h1>
            </div>
            <button onclick="openModalCreate()" class="btn-gradient-indigo btn-ripple flex items-center gap-2 text-sm">
                <i class="fa-solid fa-plus"></i> Anadir Unidad
            </button>
        </div>
    </div>

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

    <!-- Timeline -->
    <div class="space-y-4 relative before:absolute before:inset-0 before:ml-5 before:-translate-x-px md:before:mx-auto md:before:translate-x-0 before:h-full before:w-0.5 before:bg-gradient-to-b before:from-sena/50 before:via-gray-200 dark:before:via-gray-700 before:to-transparent">
        <?php if(empty($evidencias_curso)): ?>
            <div class="relative flex items-center justify-center text-center card-modern p-10 mx-10 z-10">
                <div class="text-gray-400 dark:text-gray-500">
                    <div class="w-14 h-14 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center mx-auto mb-3">
                        <i class="fa-solid fa-folder-open text-xl"></i>
                    </div>
                    <p class="font-medium">Esta ficha esta vacia. Su pensum no tiene evidencias.</p>
                </div>
            </div>
        <?php else: foreach($evidencias_curso as $i => $e):
            $fi = new DateTime($e['fecha_inicio']);
            $fv = new DateTime($e['fecha_entrega']);
            $hoy = new DateTime();
            $estado_vencido = ($hoy > $fv);
            $estado_futuro = ($hoy < $fi);
            $colorBadge = $estado_vencido ? 'from-red-500 to-rose-400 shadow-red-500/20' : ($estado_futuro ? 'from-amber-500 to-yellow-400 shadow-amber-500/20' : 'from-sena to-emerald-400 shadow-sena/20');
        ?>
        <div class="relative flex items-center justify-between md:justify-normal group pl-12 md:pl-0 z-10 w-full mb-6" style="animation: fadeInUp 0.4s ease-out <?= $i * 60 ?>ms forwards; opacity: 0;">

            <!-- Fechas (desktop) -->
            <div class="hidden md:flex flex-1 w-full justify-end text-right pr-12 opacity-70 group-hover:opacity-100 transition-opacity duration-200">
                <div class="flex flex-col items-end gap-1">
                    <span class="text-[10px] uppercase font-bold tracking-widest <?= $estado_futuro ? 'text-amber-500' : 'text-sena' ?>">Vigencia Apertura</span>
                    <span class="text-lg font-extrabold text-gray-800 dark:text-gray-200"><?= $fi->format('d M') ?> <span class="text-sm font-medium text-gray-400"><?= $fi->format('H:i') ?>hs</span></span>
                    <div class="h-px w-8 bg-gray-200 dark:bg-gray-600 my-1"></div>
                    <span class="text-[10px] uppercase font-bold tracking-widest <?= $estado_vencido ? 'text-red-500' : 'text-gray-400' ?>">Cierre Requerido</span>
                    <span class="text-lg font-extrabold text-gray-800 dark:text-gray-200"><?= $fv->format('d M') ?> <span class="text-sm font-medium text-gray-400"><?= $fv->format('H:i') ?>hs</span></span>
                </div>
            </div>

            <!-- Dot Marker -->
            <div class="w-10 h-10 rounded-full shadow-lg shrink-0 absolute left-0 md:relative md:left-auto flex items-center justify-center bg-gradient-to-br <?= $colorBadge ?>">
                <i class="fa-solid fa-file-invoice text-white text-[10px]"></i>
            </div>

            <!-- Content Card -->
            <div class="flex-1 w-full md:pl-12">
                <div class="card-modern p-5 card-glow-sena group-hover:border-sena/30 dark:group-hover:border-sena/30">
                    <div class="flex flex-col sm:flex-row justify-between items-start gap-4">
                        <div class="flex-1">
                            <div class="flex flex-wrap items-center gap-2 mb-2">
                                <span class="px-2.5 py-1 rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 text-[10px] font-bold tracking-widest uppercase border border-gray-200 dark:border-gray-600">Guia <?= htmlspecialchars($e['guia_aprendizaje']) ?></span>
                                <span class="px-2.5 py-1 rounded-lg bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 border border-blue-100 dark:border-blue-800 text-[10px] font-bold tracking-widest uppercase"><?= htmlspecialchars($e['fase']) ?></span>
                            </div>
                            <h3 class="font-bold text-gray-900 dark:text-white text-lg tracking-tight mb-1"><?= htmlspecialchars($e['codigo_evidencia']) ?></h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400 leading-relaxed"><?= htmlspecialchars($e['descripcion']) ?></p>

                            <!-- Fechas mobile -->
                            <div class="mt-3 md:hidden flex gap-3 text-xs font-semibold p-2.5 bg-gray-50 dark:bg-gray-900/50 rounded-lg border border-gray-100 dark:border-gray-700">
                                <div class="text-sena"><i class="fa-regular fa-clock mr-1"></i> <?= $fi->format('d/m/y H:i') ?></div>
                                <div class="<?= $estado_vencido ? 'text-red-500' : 'text-gray-500' ?>"><i class="fa-solid fa-flag-checkered mr-1"></i> <?= $fv->format('d/m/y H:i') ?></div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex flex-row sm:flex-col gap-2 shrink-0">
                            <button <?= empty($otras_fichas) ? 'disabled' : "onclick='openModalClone(" . json_encode($e, JSON_HEX_APOS | JSON_HEX_QUOT) . ")'" ?> class="w-10 h-10 flex justify-center items-center rounded-xl bg-indigo-50 dark:bg-indigo-900/20 hover:bg-indigo-100 dark:hover:bg-indigo-900/40 text-indigo-600 dark:text-indigo-400 transition-all duration-200 hover:scale-110 disabled:opacity-30 disabled:cursor-not-allowed disabled:hover:scale-100" title="Clonar unidad">
                                <i class="fa-solid fa-copy text-sm"></i>
                            </button>
                            <button onclick='openModalEdit(<?= json_encode($e, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' class="w-10 h-10 flex justify-center items-center rounded-xl bg-blue-50 dark:bg-blue-900/20 hover:bg-blue-100 dark:hover:bg-blue-900/40 text-blue-600 dark:text-blue-400 transition-all duration-200 hover:scale-110" title="Editar">
                                <i class="fa-solid fa-pen-to-square text-sm"></i>
                            </button>
                            <button onclick="confirmDelete(<?= $e['id'] ?>)" class="w-10 h-10 flex justify-center items-center rounded-xl bg-red-50 dark:bg-red-900/20 hover:bg-red-100 dark:hover:bg-red-900/40 text-red-600 dark:text-red-400 transition-all duration-200 hover:scale-110" title="Eliminar">
                                <i class="fa-solid fa-trash-can text-sm"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; endif; ?>
    </div>

    <!-- Delete form hidden -->
    <form id="deleteForm" method="POST" style="display:none;">
        <input type="hidden" name="action" value="delete_evidencia">
        <input type="hidden" name="delete_id" id="delete_id">
    </form>

    <!-- Modal Overlay -->
    <div id="modalOverlay" class="modal-overlay" style="opacity: 0;">

        <!-- MODAL CRUD -->
        <div id="modalCRUD" class="hidden modal-container max-w-3xl" style="opacity: 0; transform: translateY(16px) scale(0.95);">
            <div class="modal-header-indigo">
                <div class="flex justify-between items-center">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center">
                            <i class="fa-solid fa-file-circle-plus text-lg" id="modalIconCrud"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-lg uppercase tracking-wide" id="modalTitleCrud">Nueva Evidencia</h3>
                            <p class="text-white/70 text-xs">Configura los parametros de la unidad</p>
                        </div>
                    </div>
                    <button onclick="closeModal()" class="w-8 h-8 rounded-lg bg-white/10 hover:bg-white/20 text-white transition-colors flex items-center justify-center">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
            </div>

            <form method="POST" class="p-6">
                <input type="hidden" name="action" value="save_evidencia">
                <input type="hidden" name="id" id="form_id" value="">
                <input type="hidden" name="ficha_owner" value="<?= htmlspecialchars($vista_activa) ?>">

                <div class="space-y-5">
                    <!-- Row 1: Codigo + Dates -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="input-group">
                            <input type="text" name="codigo_evidencia" id="form_codigo" required placeholder=" " class="form-input peer pt-5">
                            <label>Codigo Identificador *</label>
                            <span class="input-icon peer-focus:text-indigo-500"><i class="fa-solid fa-hashtag"></i></span>
                        </div>

                        <div class="datetime-wrapper p-3 border-l-4 border-l-amber-400">
                            <span class="datetime-label text-amber-600 dark:text-amber-400"><i class="fa-regular fa-calendar-check mr-1"></i> Apertura</span>
                            <input type="datetime-local" name="fecha_inicio" id="form_fecha_i" required>
                        </div>

                        <div class="datetime-wrapper p-3 border-l-4 border-l-red-400">
                            <span class="datetime-label text-red-600 dark:text-red-400"><i class="fa-regular fa-calendar-xmark mr-1"></i> Cierre Estricto</span>
                            <input type="datetime-local" name="fecha_entrega" id="form_fecha_f" required>
                        </div>
                    </div>

                    <!-- Row 2: Fase + Guia -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="form-label">Fase Academica *</label>
                            <select name="fase" id="form_fase" required class="form-select">
                                <?php foreach($fasesEnum as $fase): ?><option value="<?= $fase ?>"><?= $fase ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Guia Estipulada *</label>
                            <select name="guia_aprendizaje" id="form_guia" required class="form-select">
                                <?php foreach($guiasEnum as $guia): ?><option value="<?= $guia ?>">Guia <?= $guia ?></option><?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Row 3: Textareas -->
                    <div class="card-modern p-5 bg-gray-50 dark:bg-gray-800/50">
                        <label class="form-label mb-2"><i class="fa-solid fa-align-left mr-1 text-indigo-400"></i> Contenido Curricular *</label>
                        <textarea name="descripcion" id="form_desc" rows="3" required class="form-input mb-4" placeholder="Descripcion general de la tarea..."></textarea>

                        <label class="form-label mb-2"><i class="fa-solid fa-lock mr-1 text-amber-400"></i> Observaciones Internas</label>
                        <textarea name="observaciones" id="form_obs" rows="2" class="form-input opacity-80" placeholder="Tips de evaluacion..."></textarea>
                    </div>
                </div>

                <div class="flex justify-end gap-3 mt-6 pt-5 border-t border-gray-100 dark:border-gray-700">
                    <button type="button" onclick="closeModal()" class="btn-secondary text-sm">Cancelar</button>
                    <button type="submit" id="btnGuardarCrud" class="btn-gradient-sena btn-ripple text-sm">
                        <i class="fa-solid fa-save mr-1.5"></i> Guardar
                    </button>
                </div>
            </form>
        </div>

        <!-- MODAL CLONE -->
        <div id="modalCLONE" class="hidden modal-container max-w-lg" style="opacity: 0; transform: translateY(16px) scale(0.95);">
            <div class="bg-gradient-to-r from-indigo-600 to-purple-600 px-6 py-5 text-white">
                <div class="flex justify-between items-center">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center">
                            <i class="fa-solid fa-copy text-lg"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-lg">Replicar Modulo</h3>
                            <p class="text-indigo-200 text-xs">Migrar contenido hacia otro grupo</p>
                        </div>
                    </div>
                    <button onclick="closeModal()" class="w-8 h-8 rounded-lg bg-white/10 hover:bg-white/20 text-white transition-colors flex items-center justify-center">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
            </div>

            <form method="POST" class="p-6">
                <input type="hidden" name="action" value="replicar_evidencia">
                <input type="hidden" name="id_origen" id="clone_id" value="">

                <div class="bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-100 dark:border-indigo-800 rounded-xl p-4 mb-6 relative overflow-hidden">
                    <span class="absolute right-0 top-0 bg-indigo-500 text-white text-[9px] font-bold uppercase px-2.5 py-1 rounded-bl-lg">SOURCE</span>
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Se copiara la estructura de:</p>
                    <p class="font-bold text-gray-800 dark:text-gray-200 text-sm mt-1" id="clone_name">EVI-XX</p>
                </div>

                <label class="form-label">Destino (Ficha de Inyeccion) *</label>
                <select name="curso_destino" required class="form-select mb-5">
                    <option value="">-- Elija ficha destino --</option>
                    <?php foreach($otras_fichas as $f_dest): ?><option value="<?= $f_dest ?>"><?= $f_dest ?></option><?php endforeach; ?>
                </select>

                <div class="grid grid-cols-2 gap-4 mb-6">
                    <div class="datetime-wrapper p-3">
                        <span class="datetime-label text-gray-500">Nueva Fecha Inicio</span>
                        <input type="datetime-local" name="nueva_fecha_inicio" required>
                    </div>
                    <div class="datetime-wrapper p-3">
                        <span class="datetime-label text-gray-500">Nueva Fecha Cierre</span>
                        <input type="datetime-local" name="nueva_fecha_entrega" required>
                    </div>
                </div>

                <button type="submit" class="w-full py-3 bg-gradient-to-r from-indigo-500 to-purple-500 hover:from-indigo-600 hover:to-purple-600 text-white rounded-xl shadow-lg shadow-indigo-500/20 font-bold uppercase tracking-widest text-sm transition-all duration-300 hover:-translate-y-0.5 btn-ripple">
                    <i class="fa-solid fa-bolt mr-2"></i> Disparar Replicacion
                </button>
            </form>
        </div>
    </div>

    <!-- Modal Script -->
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const overlay = document.getElementById('modalOverlay');
        const modalCRUD = document.getElementById('modalCRUD');
        const modalCLONE = document.getElementById('modalCLONE');
        const modalTitleCrud = document.getElementById('modalTitleCrud');
        const modalIconCrud = document.getElementById('modalIconCrud');
        const btnGuardarCrud = document.getElementById('btnGuardarCrud');

        const openModalBase = (modalElem) => {
            overlay.classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
            modalCRUD.classList.add('hidden');
            modalCLONE.classList.add('hidden');
            modalElem.classList.remove('hidden');

            requestAnimationFrame(() => {
                overlay.style.opacity = '1';
                modalElem.style.opacity = '1';
                modalElem.style.transform = 'translateY(0) scale(1)';
            });
        };

        window.openModalCreate = () => {
            modalTitleCrud.textContent = "NUEVO MODULO AL PENSUM";
            modalIconCrud.className = 'fa-solid fa-file-circle-plus text-lg';
            document.getElementById('form_id').value = '';
            document.getElementById('form_codigo').value = '';
            document.getElementById('form_fecha_i').value = '';
            document.getElementById('form_fecha_f').value = '';
            document.getElementById('form_fase').value = '';
            document.getElementById('form_guia').value = '';
            document.getElementById('form_desc').value = '';
            document.getElementById('form_obs').value = '';
            btnGuardarCrud.innerHTML = '<i class="fa-solid fa-plus mr-1.5"></i> Publicar Modulo Nuevo';
            openModalBase(modalCRUD);
        };

        window.openModalEdit = (ev) => {
            modalTitleCrud.textContent = "EDITAR: " + ev.codigo_evidencia;
            modalIconCrud.className = 'fa-solid fa-pen-to-square text-lg';
            document.getElementById('form_id').value = ev.id;
            document.getElementById('form_codigo').value = ev.codigo_evidencia;
            document.getElementById('form_fecha_i').value = ev.fecha_inicio.replace(' ', 'T').substring(0, 16);
            document.getElementById('form_fecha_f').value = ev.fecha_entrega.replace(' ', 'T').substring(0, 16);
            document.getElementById('form_fase').value = ev.fase;
            document.getElementById('form_guia').value = ev.guia_aprendizaje;
            document.getElementById('form_desc').value = ev.descripcion || '';
            document.getElementById('form_obs').value = ev.observaciones || '';
            btnGuardarCrud.innerHTML = '<i class="fa-solid fa-save mr-1.5"></i> Guardar Cambios';
            openModalBase(modalCRUD);
        };

        window.openModalClone = (ev) => {
            document.getElementById('clone_id').value = ev.id;
            document.getElementById('clone_name').textContent = ev.codigo_evidencia + " (" + ev.fase + ")";
            openModalBase(modalCLONE);
        };

        window.closeModal = () => {
            overlay.style.opacity = '0';
            [modalCRUD, modalCLONE].forEach(m => {
                m.style.opacity = '0';
                m.style.transform = 'translateY(16px) scale(0.95)';
            });
            setTimeout(() => {
                overlay.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
            }, 300);
        };

        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) closeModal();
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !overlay.classList.contains('hidden')) closeModal();
        });

        window.confirmDelete = (id) => {
            showDeleteConfirm({
                title: 'Eliminar Evidencia',
                message: 'Esta evidencia y todas sus calificaciones asociadas seran eliminadas permanentemente. Esta accion no se puede deshacer.',
                confirmText: 'Si, Eliminar',
                onConfirm: () => {
                    document.getElementById('delete_id').value = id;
                    document.getElementById('deleteForm').submit();
                }
            });
        };
    });
    </script>
<?php endif; ?>
