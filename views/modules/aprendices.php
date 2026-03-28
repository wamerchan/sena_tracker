<!-- views/modules/aprendices.php -->
<?php
$db = Database::connect();
$mensaje = '';
$tipo_mensaje = '';

// ==========================================
// BLOQUE CRUD (POST) - ¡Pilas acá arquitecto!
// Aquí centralizamos la inyección de datos para no tener archivos sueltos procesando formularios.
// Pura cohesión de módulo.
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // Crear o Editar Aprendiz (Upsert lógico)
    if ($_POST['action'] === 'save_aprendiz') {
        $id = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);
        $cedula = trim($_POST['cedula'] ?? '');
        $nombres = trim($_POST['nombres'] ?? '');
        $apellidos = trim($_POST['apellidos'] ?? '');
        $correo = trim($_POST['correo'] ?? '');
        $codigo_curso = trim($_POST['codigo_curso'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');

        if ($cedula && $nombres && $apellidos && $correo && $codigo_curso) {
            try {
                if (empty($id)) {
                    $stmt = $db->prepare("INSERT INTO aprendices (cedula, nombres, apellidos, correo, codigo_curso, telefono) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$cedula, $nombres, $apellidos, $correo, $codigo_curso, $telefono]);
                    $mensaje = "Aprendiz matriculado correctamente.";
                } else {
                    $stmt = $db->prepare("UPDATE aprendices SET cedula=?, nombres=?, apellidos=?, correo=?, codigo_curso=?, telefono=? WHERE id=?");
                    $stmt->execute([$cedula, $nombres, $apellidos, $correo, $codigo_curso, $telefono, $id]);
                    $mensaje = "Datos del aprendiz actualizados.";
                }
                $tipo_mensaje = "success";
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $mensaje = "Error: La cedula o el correo ya estan registrados en otro alumno.";
                } else {
                    $mensaje = "Error de base de datos: " . $e->getMessage();
                }
                $tipo_mensaje = "error";
            }
        } else {
            $mensaje = "Por favor, completa todos los campos obligatorios.";
            $tipo_mensaje = "warning";
        }
    }

    // Eliminar Aprendiz
    if ($_POST['action'] === 'delete_aprendiz') {
        $id_borrar = filter_input(INPUT_POST, 'delete_id', FILTER_SANITIZE_NUMBER_INT);
        if ($id_borrar) {
            try {
                $stmt = $db->prepare("DELETE FROM aprendices WHERE id = ?");
                $stmt->execute([$id_borrar]);
                $mensaje = "Aprendiz eliminado del sistema.";
                $tipo_mensaje = "success";
            } catch (PDOException $e) {
                $mensaje = "No se pudo eliminar al aprendiz: " . $e->getMessage();
                $tipo_mensaje = "error";
            }
        }
    }
}

// ==========================================
// FILTROS Y PAGINACIÓN - Modo Lectura GET
// ==========================================
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$ficha_filter = isset($_GET['ficha']) ? trim($_GET['ficha']) : '';
$sort = isset($_GET['sort']) ? trim($_GET['sort']) : 'apellidos';
$order = isset($_GET['order']) ? trim($_GET['order']) : 'ASC';

if (!function_exists('getSortIcon')) {
    function getSortIcon($column, $current_sort, $current_order) {
        if ($current_sort !== $column) return '<i class="fa-solid fa-sort text-gray-300 dark:text-gray-600 ml-1"></i>';
        return $current_order === 'ASC'
            ? '<i class="fa-solid fa-sort-up text-sena ml-1"></i>'
            : '<i class="fa-solid fa-sort-down text-sena ml-1"></i>';
    }
}

// Fichas disponibles
$fichas_disponibles = $db->query("SELECT DISTINCT codigo_curso FROM aprendices ORDER BY codigo_curso")->fetchAll(PDO::FETCH_COLUMN);

// SQL
$sql = "SELECT * FROM aprendices WHERE 1=1";
$params = [];

if (!empty($ficha_filter)) {
    $sql .= " AND codigo_curso = :ficha";
    $params[':ficha'] = $ficha_filter;
}

if (!empty($search)) {
    $sql .= " AND (cedula LIKE :search1 OR nombres LIKE :search2 OR apellidos LIKE :search3 OR correo LIKE :search4)";
    $search_param = '%' . $search . '%';
    $params[':search1'] = $search_param;
    $params[':search2'] = $search_param;
    $params[':search3'] = $search_param;
    $params[':search4'] = $search_param;
}

$allowed_sorts = ['cedula', 'apellidos', 'codigo_curso'];
$sort_col = in_array($sort, $allowed_sorts) ? $sort : 'apellidos';
$order_dir = (strtoupper($order) === 'DESC') ? 'DESC' : 'ASC';

if ($sort_col === 'apellidos') {
    $sql .= " ORDER BY apellidos $order_dir, nombres $order_dir";
} else {
    $sql .= " ORDER BY $sort_col $order_dir";
}

$stmt = $db->prepare($sql);
$stmt->execute($params);
$aprendices = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ---- RESPUESTA AJAX PARCIAL (DOM Diffing manual) ----
// Parcero, si la petición viene por fetch/XHR, devolvemos SOLO la tabla.
// Así nos ahorramos redibujar todo el layout global. SPA a lo criollo pero efectivo.
$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
if ($is_ajax):
?>
    <div class="px-6 py-3 border-b border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50 text-sm text-gray-500 dark:text-gray-400 flex justify-between items-center">
        <span>Mostrando <strong class="text-gray-700 dark:text-gray-200"><?= count($aprendices) ?></strong> aprendices</span>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700">
            <thead class="bg-gray-50/80 dark:bg-gray-800/50">
                <tr>
                    <th class="px-6 py-3.5 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700/50 transition-colors" onclick="handleSort('cedula', '<?= $sort_col === 'cedula' && $order_dir === 'ASC' ? 'DESC' : 'ASC' ?>')">
                        <span class="flex items-center">Cédula <?= getSortIcon('cedula', $sort_col, $order_dir) ?></span>
                    </th>
                    <th class="px-6 py-3.5 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700/50 transition-colors" onclick="handleSort('apellidos', '<?= $sort_col === 'apellidos' && $order_dir === 'ASC' ? 'DESC' : 'ASC' ?>')">
                        <span class="flex items-center">Nombre Completo <?= getSortIcon('apellidos', $sort_col, $order_dir) ?></span>
                    </th>
                    <th class="px-6 py-3.5 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider hidden md:table-cell">Correo</th>
                    <th class="px-6 py-3.5 text-center text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer" onclick="handleSort('codigo_curso', '<?= $sort_col === 'codigo_curso' && $order_dir === 'ASC' ? 'DESC' : 'ASC' ?>')">
                        <span class="flex items-center justify-center">Ficha <?= getSortIcon('codigo_curso', $sort_col, $order_dir) ?></span>
                    </th>
                    <th class="px-6 py-3.5 text-right text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50 dark:divide-gray-700/50">
                <?php if(empty($aprendices)): ?>
                    <tr>
                        <td colspan="5" class="py-16 text-center">
                            <div class="w-16 h-16 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center mx-auto mb-4">
                                <i class="fa-solid fa-users-slash text-2xl text-gray-300 dark:text-gray-600"></i>
                            </div>
                            <span class="text-sm text-gray-500 dark:text-gray-400 font-medium">No hay aprendices que coincidan.</span>
                        </td>
                    </tr>
                <?php else: foreach($aprendices as $i => $a): ?>
                    <tr class="table-row-hover stagger-item" style="animation-delay: <?= $i * 30 ?>ms">
                        <td class="px-6 py-4 text-sm font-semibold text-gray-900 dark:text-gray-100"><?= htmlspecialchars($a['cedula']) ?></td>
                        <td class="px-6 py-4">
                            <span class="text-sm font-semibold text-gray-800 dark:text-gray-200"><?= htmlspecialchars($a['apellidos'] . ', ' . $a['nombres']) ?></span>
                        </td>
                        <td class="px-6 py-4 hidden md:table-cell">
                            <a href="mailto:<?= htmlspecialchars($a['correo']) ?>" class="text-sm text-gray-500 dark:text-gray-400 hover:text-sena transition-colors"><?= htmlspecialchars($a['correo']) ?></a>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-lg bg-sena/10 dark:bg-sena/20 text-sena text-xs font-bold tracking-wide"><?= htmlspecialchars($a['codigo_curso']) ?></span>
                        </td>
                        <td class="px-6 py-4 text-right whitespace-nowrap">
                            <div class="flex items-center justify-end gap-1">
                                <button onclick='openModal(<?= json_encode($a, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' class="w-8 h-8 rounded-lg bg-blue-50 dark:bg-blue-900/20 hover:bg-blue-100 dark:hover:bg-blue-900/40 text-blue-600 dark:text-blue-400 transition-all duration-200 hover:scale-110 flex items-center justify-center" title="Editar">
                                    <i class="fa-solid fa-pen text-xs"></i>
                                </button>
                                <button onclick='confirmDelete(<?= $a['id'] ?>)' class="w-8 h-8 rounded-lg bg-red-50 dark:bg-red-900/20 hover:bg-red-100 dark:hover:bg-red-900/40 text-red-600 dark:text-red-400 transition-all duration-200 hover:scale-110 flex items-center justify-center" title="Eliminar">
                                    <i class="fa-solid fa-trash text-xs"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
<?php
    exit;
endif;
// ---- FIN RESPUESTA AJAX ----
?>

<!-- VISTA COMPLETA HTML -->

<!-- Header -->
<div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 animate-fade-in-up">
    <div>
        <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white tracking-tight">Gestión de Aprendices</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Alta, baja y listado general de alumnos.</p>
    </div>
    <button onclick="openModal()" class="btn-gradient-indigo btn-ripple flex items-center gap-2 text-sm">
        <i class="fa-solid fa-plus"></i> Nuevo Aprendiz
    </button>
</div>

<!-- Alertas POST -->
<?php if ($mensaje): ?>
<div class="mb-6 animate-fade-in-down">
    <div class="flex items-center gap-3 p-4 rounded-xl <?= $tipo_mensaje === 'success' ? 'bg-gradient-to-r from-emerald-50 to-green-50 dark:from-emerald-900/20 dark:to-green-900/20 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300' : ($tipo_mensaje === 'warning' ? 'bg-gradient-to-r from-amber-50 to-yellow-50 dark:from-amber-900/20 dark:to-yellow-900/20 border border-amber-200 dark:border-amber-800 text-amber-800 dark:text-amber-300' : 'bg-gradient-to-r from-red-50 to-rose-50 dark:from-red-900/20 dark:to-rose-900/20 border border-red-200 dark:border-red-800 text-red-800 dark:text-red-300') ?> shadow-sm">
        <div class="w-8 h-8 rounded-lg flex items-center justify-center <?= $tipo_mensaje === 'success' ? 'bg-emerald-500' : ($tipo_mensaje === 'warning' ? 'bg-amber-500' : 'bg-red-500') ?> text-white flex-shrink-0">
            <i class="fa-solid <?= $tipo_mensaje === 'success' ? 'fa-check' : ($tipo_mensaje === 'warning' ? 'fa-exclamation' : 'fa-xmark') ?> text-sm"></i>
        </div>
        <span class="text-sm font-medium"><?= htmlspecialchars($mensaje) ?></span>
    </div>
</div>
<?php endif; ?>

<!-- Search & Filters -->
<div class="card-modern p-5 mb-6 animate-fade-in-up" style="animation-delay: 100ms">
    <form id="filterForm" onsubmit="event.preventDefault();" class="flex flex-col md:flex-row gap-4">
        <!-- Search -->
        <div class="flex-1">
            <label class="form-label">Buscar en la tabla</label>
            <div class="search-input-wrapper">
                <input type="text" id="searchInput" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Cédula, nombre o correo...">
                <span class="search-icon"><i id="searchIcon" class="fa-solid fa-magnifying-glass"></i></span>
            </div>
        </div>

        <!-- Filtro Ficha -->
        <div class="w-full md:w-64">
            <label class="form-label">Filtrar por Curso</label>
            <select id="fichaSelect" name="ficha" class="form-select">
                <option value="">Todos los cursos</option>
                <?php foreach($fichas_disponibles as $f): ?>
                    <option value="<?= htmlspecialchars($f) ?>" <?= $ficha_filter === $f ? 'selected' : '' ?>><?= htmlspecialchars($f) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <input type="hidden" id="sortInput" name="sort" value="<?= htmlspecialchars($sort_col) ?>">
        <input type="hidden" id="orderInput" name="order" value="<?= htmlspecialchars($order_dir) ?>">

        <div class="flex items-end">
            <button type="button" onclick="clearFilters()" class="btn-secondary flex items-center gap-2 text-sm" title="Limpiar filtros">
                <i class="fa-solid fa-eraser"></i> Limpiar
            </button>
        </div>
    </form>
</div>

<!-- Tabla -->
<div id="tableContainer" class="card-modern overflow-hidden relative animate-fade-in-up" style="animation-delay: 200ms">
    <div id="tableLoader" class="absolute inset-0 bg-white/70 dark:bg-gray-800/70 z-10 hidden flex items-center justify-center backdrop-blur-[2px]">
        <div class="flex flex-col items-center gap-2">
            <i class="fa-solid fa-circle-notch fa-spin text-3xl text-sena"></i>
            <span class="text-sm font-medium text-gray-500">Cargando...</span>
        </div>
    </div>

    <div id="tableContent">
        <div class="px-6 py-3 border-b border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50 text-sm text-gray-500 dark:text-gray-400 flex justify-between items-center">
            <span>Mostrando <strong class="text-gray-700 dark:text-gray-200"><?= count($aprendices) ?></strong> aprendices</span>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700">
                <thead class="bg-gray-50/80 dark:bg-gray-800/50">
                    <tr>
                        <th class="px-6 py-3.5 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700/50 transition-colors" onclick="handleSort('cedula', '<?= $sort_col === 'cedula' && $order_dir === 'ASC' ? 'DESC' : 'ASC' ?>')">
                            <span class="flex items-center">Cédula <?= getSortIcon('cedula', $sort_col, $order_dir) ?></span>
                        </th>
                        <th class="px-6 py-3.5 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700/50 transition-colors" onclick="handleSort('apellidos', '<?= $sort_col === 'apellidos' && $order_dir === 'ASC' ? 'DESC' : 'ASC' ?>')">
                            <span class="flex items-center">Nombre Completo <?= getSortIcon('apellidos', $sort_col, $order_dir) ?></span>
                        </th>
                        <th class="px-6 py-3.5 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider hidden md:table-cell">Correo</th>
                        <th class="px-6 py-3.5 text-center text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer" onclick="handleSort('codigo_curso', '<?= $sort_col === 'codigo_curso' && $order_dir === 'ASC' ? 'DESC' : 'ASC' ?>')">
                            <span class="flex items-center justify-center">Ficha <?= getSortIcon('codigo_curso', $sort_col, $order_dir) ?></span>
                        </th>
                        <th class="px-6 py-3.5 text-right text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50 dark:divide-gray-700/50">
                    <?php if(empty($aprendices)): ?>
                        <tr>
                            <td colspan="5" class="py-16 text-center">
                                <div class="w-16 h-16 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center mx-auto mb-4">
                                    <i class="fa-solid fa-users-slash text-2xl text-gray-300 dark:text-gray-600"></i>
                                </div>
                                <span class="text-sm text-gray-500 dark:text-gray-400 font-medium">No hay aprendices que coincidan.</span>
                            </td>
                        </tr>
                    <?php else: foreach($aprendices as $i => $a): ?>
                        <tr class="table-row-hover" style="animation: fadeInUp 0.4s ease-out <?= $i * 40 ?>ms forwards; opacity: 0;">
                            <td class="px-6 py-4 text-sm font-semibold text-gray-900 dark:text-gray-100"><?= htmlspecialchars($a['cedula']) ?></td>
                            <td class="px-6 py-4">
                                <span class="text-sm font-semibold text-gray-800 dark:text-gray-200"><?= htmlspecialchars($a['apellidos'] . ', ' . $a['nombres']) ?></span>
                            </td>
                            <td class="px-6 py-4 hidden md:table-cell">
                                <a href="mailto:<?= htmlspecialchars($a['correo']) ?>" class="text-sm text-gray-500 dark:text-gray-400 hover:text-sena transition-colors"><?= htmlspecialchars($a['correo']) ?></a>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-lg bg-sena/10 dark:bg-sena/20 text-sena text-xs font-bold tracking-wide"><?= htmlspecialchars($a['codigo_curso']) ?></span>
                            </td>
                            <td class="px-6 py-4 text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-1">
                                    <button onclick='openModal(<?= json_encode($a, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' class="w-8 h-8 rounded-lg bg-blue-50 dark:bg-blue-900/20 hover:bg-blue-100 dark:hover:bg-blue-900/40 text-blue-600 dark:text-blue-400 transition-all duration-200 hover:scale-110 flex items-center justify-center" title="Editar">
                                        <i class="fa-solid fa-pen text-xs"></i>
                                    </button>
                                    <button onclick='confirmDelete(<?= $a['id'] ?>)' class="w-8 h-8 rounded-lg bg-red-50 dark:bg-red-900/20 hover:bg-red-100 dark:hover:bg-red-900/40 text-red-600 dark:text-red-400 transition-all duration-200 hover:scale-110 flex items-center justify-center" title="Eliminar">
                                        <i class="fa-solid fa-trash text-xs"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Formulario oculto de borrado -->
<form id="deleteForm" method="POST" style="display:none;">
    <input type="hidden" name="action" value="delete_aprendiz">
    <input type="hidden" name="delete_id" id="delete_id">
</form>

<!-- Modal Create/Edit -->
<div id="modalOverlay" class="modal-overlay" style="opacity: 0;">
    <div class="modal-container max-w-lg" id="modalContent" style="opacity: 0; transform: translateY(16px) scale(0.95);">
        <!-- Gradient Header -->
        <div class="modal-header-gradient">
            <div class="flex justify-between items-center">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center">
                        <i class="fa-solid fa-user-plus text-lg" id="modalIcon"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-lg" id="modalTitle">Nuevo Aprendiz</h3>
                        <p class="text-white/70 text-xs" id="modalSubtitle">Completa los datos del aprendiz</p>
                    </div>
                </div>
                <button onclick="closeModal()" class="w-8 h-8 rounded-lg bg-white/10 hover:bg-white/20 text-white transition-colors flex items-center justify-center">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
        </div>

        <form method="POST" class="p-6">
            <input type="hidden" name="action" value="save_aprendiz">
            <input type="hidden" name="id" id="form_id" value="">

            <div class="space-y-4">
                <!-- Row 1: Cédula + Ficha -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="input-group">
                        <input type="text" name="cedula" id="form_cedula" required placeholder=" " class="form-input peer pt-5">
                        <label>Cédula *</label>
                        <span class="input-icon peer-focus:text-sena"><i class="fa-solid fa-id-card"></i></span>
                    </div>
                    <div class="input-group">
                        <input type="text" name="codigo_curso" id="form_ficha" required placeholder=" " list="fichas_list" autocomplete="off" class="form-input peer pt-5">
                        <label>Ficha de Curso *</label>
                        <span class="input-icon peer-focus:text-sena"><i class="fa-solid fa-bookmark"></i></span>
                        <datalist id="fichas_list">
                            <?php foreach($fichas_disponibles as $f): ?><option value="<?= htmlspecialchars($f) ?>"><?php endforeach; ?>
                        </datalist>
                    </div>
                </div>

                <!-- Row 2: Nombres + Apellidos -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="input-group">
                        <input type="text" name="nombres" id="form_nombres" required placeholder=" " class="form-input peer pt-5">
                        <label>Nombres *</label>
                        <span class="input-icon peer-focus:text-sena"><i class="fa-solid fa-user"></i></span>
                    </div>
                    <div class="input-group">
                        <input type="text" name="apellidos" id="form_apellidos" required placeholder=" " class="form-input peer pt-5">
                        <label>Apellidos *</label>
                        <span class="input-icon peer-focus:text-sena"><i class="fa-solid fa-user"></i></span>
                    </div>
                </div>

                <!-- Row 3: Correo -->
                <div class="input-group">
                    <input type="email" name="correo" id="form_correo" required placeholder=" " class="form-input peer pt-5">
                    <label>Correo Electrónico *</label>
                    <span class="input-icon peer-focus:text-sena"><i class="fa-solid fa-envelope"></i></span>
                </div>

                <!-- Row 4: Teléfono -->
                <div class="input-group">
                    <input type="text" name="telefono" id="form_telefono" placeholder=" " class="form-input peer pt-5">
                    <label>Teléfono</label>
                    <span class="input-icon peer-focus:text-sena"><i class="fa-solid fa-phone"></i></span>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex justify-end gap-3 mt-6 pt-5 border-t border-gray-100 dark:border-gray-700">
                <button type="button" onclick="closeModal()" class="btn-secondary text-sm">
                    Cancelar
                </button>
                <button type="submit" class="btn-gradient-sena btn-ripple text-sm" id="btnGuardar">
                    <i class="fa-solid fa-floppy-disk mr-1.5"></i> Guardar
                </button>
            </div>
        </form>
    </div>
</div>

<!-- JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    // AJAX Table
    const searchInput = document.getElementById('searchInput');
    const fichaSelect = document.getElementById('fichaSelect');
    const sortInput = document.getElementById('sortInput');
    const orderInput = document.getElementById('orderInput');
    const tableContent = document.getElementById('tableContent');
    const tableLoader = document.getElementById('tableLoader');
    const searchIcon = document.getElementById('searchIcon');

    let debounceTimer;

    const fetchResults = () => {
        tableLoader.classList.remove('hidden');
        searchIcon.classList.replace('fa-magnifying-glass', 'fa-spinner');
        searchIcon.classList.add('fa-spin');

        const params = new URLSearchParams({
            view: 'aprendices',
            search: searchInput.value.trim(),
            ficha: fichaSelect.value,
            sort: sortInput.value,
            order: orderInput.value
        });

        const newUrl = window.location.pathname + '?' + params.toString();
        window.history.replaceState({}, '', newUrl);

        fetch(newUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(res => res.text())
        .then(html => {
            tableContent.innerHTML = html;
        })
        .finally(() => {
            tableLoader.classList.add('hidden');
            searchIcon.classList.replace('fa-spinner', 'fa-magnifying-glass');
            searchIcon.classList.remove('fa-spin');
        });
    };

    searchInput.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(fetchResults, 300);
    });

    fichaSelect.addEventListener('change', fetchResults);

    window.handleSort = (column, newOrder) => {
        sortInput.value = column;
        orderInput.value = newOrder;
        fetchResults();
    };

    window.clearFilters = () => {
        searchInput.value = '';
        fichaSelect.value = '';
        sortInput.value = 'apellidos';
        orderInput.value = 'ASC';
        fetchResults();
    };

    // Modal
    const overlay = document.getElementById('modalOverlay');
    const modalContent = document.getElementById('modalContent');
    const modalTitle = document.getElementById('modalTitle');
    const modalSubtitle = document.getElementById('modalSubtitle');
    const modalIcon = document.getElementById('modalIcon');
    const btnGuardar = document.getElementById('btnGuardar');

    window.openModal = (aprendiz = null) => {
        overlay.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
        requestAnimationFrame(() => {
            overlay.style.opacity = '1';
            modalContent.style.opacity = '1';
            modalContent.style.transform = 'translateY(0) scale(1)';
        });

        if (aprendiz) {
            modalTitle.textContent = 'Editar Aprendiz';
            modalSubtitle.textContent = 'Modifica los datos del aprendiz';
            modalIcon.className = 'fa-solid fa-pen-to-square text-lg';
            document.getElementById('form_id').value = aprendiz.id;
            document.getElementById('form_cedula').value = aprendiz.cedula;
            document.getElementById('form_nombres').value = aprendiz.nombres;
            document.getElementById('form_apellidos').value = aprendiz.apellidos;
            document.getElementById('form_correo').value = aprendiz.correo;
            document.getElementById('form_ficha').value = aprendiz.codigo_curso;
            document.getElementById('form_telefono').value = aprendiz.telefono || '';
            btnGuardar.innerHTML = '<i class="fa-solid fa-cloud-arrow-up mr-1.5"></i> Actualizar';
        } else {
            modalTitle.textContent = 'Nuevo Aprendiz';
            modalSubtitle.textContent = 'Completa los datos del aprendiz';
            modalIcon.className = 'fa-solid fa-user-plus text-lg';
            document.getElementById('form_id').value = '';
            document.getElementById('form_cedula').value = '';
            document.getElementById('form_nombres').value = '';
            document.getElementById('form_apellidos').value = '';
            document.getElementById('form_correo').value = '';
            document.getElementById('form_ficha').value = '';
            document.getElementById('form_telefono').value = '';
            btnGuardar.innerHTML = '<i class="fa-solid fa-floppy-disk mr-1.5"></i> Matricular';
        }
    };

    window.closeModal = () => {
        overlay.style.opacity = '0';
        modalContent.style.opacity = '0';
        modalContent.style.transform = 'translateY(16px) scale(0.95)';
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

    // Delete confirmation (styled modal - NADA DE alert() chimbo)
    window.confirmDelete = (id) => {
        showDeleteConfirm({
            title: 'Eliminar Aprendiz',
            message: 'Eliminar a este mijo lo borra del sistema y vuele sus notas en cascada. Pilas, esta acción no se puede deshacer.',
            confirmText: 'Sí, Eliminar de una',
            onConfirm: () => {
                document.getElementById('delete_id').value = id;
                document.getElementById('deleteForm').submit();
            }
        });
    };
});
</script>
