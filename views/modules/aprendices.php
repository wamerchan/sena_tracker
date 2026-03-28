<!-- views/modules/aprendices.php -->
<?php
$db = Database::connect();
$mensaje = '';
$tipo_mensaje = '';

// --- BLOQUE CRUD (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // Crear o Editar Aprendiz
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
                    // CREATE
                    $stmt = $db->prepare("INSERT INTO aprendices (cedula, nombres, apellidos, correo, codigo_curso, telefono) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$cedula, $nombres, $apellidos, $correo, $codigo_curso, $telefono]);
                    $mensaje = "Aprendiz matriculado correctamente.";
                } else {
                    // UPDATE
                    $stmt = $db->prepare("UPDATE aprendices SET cedula=?, nombres=?, apellidos=?, correo=?, codigo_curso=?, telefono=? WHERE id=?");
                    $stmt->execute([$cedula, $nombres, $apellidos, $correo, $codigo_curso, $telefono, $id]);
                    $mensaje = "Datos del aprendiz actualizados.";
                }
                $tipo_mensaje = "success";
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $mensaje = "Error: La cédula o el correo ya están registrados en otro alumno.";
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
                // Gracias a ON DELETE CASCADE configurado en DB, esto borra sus notas también.
                $stmt = $db->prepare("DELETE FROM aprendices WHERE id = ?");
                $stmt->execute([$id_borrar]);
                $mensaje = "Aprendiz (y sus calificaciones si las tuviera) eliminado del sistema.";
                $tipo_mensaje = "success";
            } catch (PDOException $e) {
                $mensaje = "No se pudo eliminar al aprendiz: " . $e->getMessage();
                $tipo_mensaje = "error";
            }
        }
    }
}
// --- FIN BLOQUE CRUD ---

// 1. Recibir parámetros GET para Filtros/Datatable
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$ficha_filter = isset($_GET['ficha']) ? trim($_GET['ficha']) : '';
$sort = isset($_GET['sort']) ? trim($_GET['sort']) : 'apellidos';
$order = isset($_GET['order']) ? trim($_GET['order']) : 'ASC';

if (!function_exists('getSortLink')) {
    function getSortLink($column, $current_sort, $current_order, $search, $ficha) {
        $new_order = ($current_sort === $column && $current_order === 'ASC') ? 'DESC' : 'ASC';
        $url = "?view=aprendices&sort={$column}&order={$new_order}";
        if (!empty($search)) $url .= "&search=" . urlencode($search);
        if (!empty($ficha)) $url .= "&ficha=" . urlencode($ficha);
        return $url;
    }

    function getSortIcon($column, $current_sort, $current_order) {
        if ($current_sort !== $column) return '<i class="fa-solid fa-sort text-gray-400 ml-1"></i>';
        return $current_order === 'ASC' 
            ? '<i class="fa-solid fa-sort-up text-sena ml-1"></i>' 
            : '<i class="fa-solid fa-sort-down text-sena ml-1"></i>';
    }
}

// Obtener lista de fichas únicas para selector
$fichas_disponibles = $db->query("SELECT DISTINCT codigo_curso FROM aprendices ORDER BY codigo_curso")->fetchAll(PDO::FETCH_COLUMN);

// 2. Construcción SQL Segura
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

// ---- RESPUESTA AJAX PARCIAL PARA LA TABLA ----
$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
if ($is_ajax):
?>
    <div class="px-6 py-3 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/80 text-sm text-gray-500 flex justify-between">
        <span>Mostrando <strong><?= count($aprendices) ?></strong> aprendices encontrados.</span>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-800/50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase hover:bg-gray-100 dark:hover:bg-gray-700 transition cursor-pointer">
                        <a href="javascript:void(0)" onclick="handleSort('cedula', '<?= $sort_col === 'cedula' && $order_dir === 'ASC' ? 'DESC' : 'ASC' ?>')" class="flex items-center group">
                            Cédula <?= getSortIcon('cedula', $sort_col, $order_dir) ?>
                        </a>
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase hover:bg-gray-100 dark:hover:bg-gray-700 transition cursor-pointer">
                        <a href="javascript:void(0)" onclick="handleSort('apellidos', '<?= $sort_col === 'apellidos' && $order_dir === 'ASC' ? 'DESC' : 'ASC' ?>')" class="flex items-center group">
                            Nombre Completo <?= getSortIcon('apellidos', $sort_col, $order_dir) ?>
                        </a>
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase cursor-default">Correo</th>
                    <th class="px-6 py-3 text-center text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase hover:bg-gray-100 dark:hover:bg-gray-700 transition cursor-pointer">
                        <a href="javascript:void(0)" onclick="handleSort('codigo_curso', '<?= $sort_col === 'codigo_curso' && $order_dir === 'ASC' ? 'DESC' : 'ASC' ?>')" class="flex items-center justify-center group">
                            Ficha <?= getSortIcon('codigo_curso', $sort_col, $order_dir) ?>
                        </a>
                    </th>
                    <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase cursor-default">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                <?php if(empty($aprendices)): ?>
                    <tr>
                        <td colspan="5" class="py-12 text-center">
                            <i class="fa-solid fa-users-slash text-4xl text-gray-300 dark:text-gray-600 mb-3 block"></i>
                            <span class="text-sm text-gray-500 dark:text-gray-400">No hay aprendices que coincidan con la búsqueda.</span>
                        </td>
                    </tr>
                <?php else: foreach($aprendices as $a): ?>
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                        <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-gray-200"><?= htmlspecialchars($a['cedula']) ?></td>
                        <td class="px-6 py-4 text-sm text-gray-800 dark:text-gray-300 font-semibold">
                            <?= htmlspecialchars($a['apellidos'] . ', ' . $a['nombres']) ?>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                            <a href="mailto:<?= htmlspecialchars($a['correo']) ?>" class="hover:text-sena hover:underline transition-all">
                                <?= htmlspecialchars($a['correo']) ?>
                            </a>
                        </td>
                        <td class="px-6 py-4 text-sm text-center font-bold text-sena">
                            <span class="bg-sena-light/10 border border-sena-light/20 dark:bg-sena-dark/20 text-sena px-3 py-1 rounded w-full inline-block">
                                <?= htmlspecialchars($a['codigo_curso']) ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 text-right whitespace-nowrap">
                            <!-- Botón Editar que llama JSON encode para el JS -->
                            <button onclick='openModal(<?= json_encode($a, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' class="w-8 h-8 rounded bg-gray-100 hover:bg-blue-100 text-blue-600 hover:text-blue-800 dark:bg-gray-700 dark:text-blue-400 dark:hover:bg-gray-600 transition-colors mx-0.5" title="Editar">
                                <i class="fa-solid fa-pen"></i>
                            </button>
                            <!-- Botón Eliminar que dispara form por POST -->
                            <button onclick='confirmDelete(<?= $a['id'] ?>)' class="w-8 h-8 rounded bg-gray-100 hover:bg-red-100 text-red-600 hover:text-red-800 dark:bg-gray-700 dark:text-red-400 dark:hover:bg-gray-600 transition-colors mx-0.5" title="Eliminar">
                                <i class="fa-solid fa-trash"></i>
                            </button>
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

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-3xl font-bold text-gray-800 dark:text-gray-100 uppercase tracking-tight">Gestión de Aprendices</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Alta, baja y listado general de alumnos.</p>
    </div>
    <button onclick="openModal()" class="bg-indigo-600 hover:bg-indigo-700 text-white py-2 px-4 rounded-lg shadow-sm font-semibold transition-colors flex items-center gap-2">
        <i class="fa-solid fa-plus"></i> Nuevo Alumno
    </button>
</div>

<!-- Alertas POST -->
<?php if ($mensaje): ?>
<div class="mb-6 p-4 rounded-lg <?= $tipo_mensaje === 'success' ? 'bg-green-100 text-green-800 border-green-200' : ($tipo_mensaje === 'warning' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800 border-red-200') ?> border shadow-sm">
    <i class="fa-solid <?= $tipo_mensaje === 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation' ?> mr-2"></i> <?= htmlspecialchars($mensaje) ?>
</div>
<?php endif; ?>

<!-- Barra Buscadora en Vivo -->
<div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-4 mb-6">
    <form id="filterForm" onsubmit="event.preventDefault();" class="flex flex-col md:flex-row gap-4">
        <!-- Búsqueda rápida multicampo (Tiempo API) -->
        <div class="flex-1">
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2 dark:text-gray-400">Buscar en la tabla</label>
            <div class="relative">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                    <i id="searchIcon" class="fa-solid fa-magnifying-glass transition-all"></i>
                </span>
                <input type="text" id="searchInput" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Tipeá cédula, nombre o correo..." 
                       class="w-full pl-10 pr-3 py-2 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:ring-sena focus:border-sena transition-colors shadow-sm">
            </div>
        </div>

        <!-- Filtro Ficha -->
        <div class="w-full md:w-64">
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2 dark:text-gray-400">Filtrar por Curso</label>
            <select id="fichaSelect" name="ficha" class="w-full py-2 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:ring-sena focus:border-sena transition-colors shadow-sm">
                <option value="">-- Todos los cursos --</option>
                <?php foreach($fichas_disponibles as $f): ?>
                    <option value="<?= htmlspecialchars($f) ?>" <?= $ficha_filter === $f ? 'selected' : '' ?>>
                        <?= htmlspecialchars($f) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <input type="hidden" id="sortInput" name="sort" value="<?= htmlspecialchars($sort_col) ?>">
        <input type="hidden" id="orderInput" name="order" value="<?= htmlspecialchars($order_dir) ?>">
        
        <div class="flex items-end">
            <button type="button" onclick="clearFilters()" class="bg-gray-50 hover:bg-gray-100 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-600 dark:text-gray-300 py-2 px-4 rounded-lg border border-gray-200 dark:border-gray-600 shadow-sm transition-colors" title="Limpiar Todo">
                <i class="fa-solid fa-eraser"></i> Limpiar
            </button>
        </div>
    </form>
</div>

<!-- Tabla Inject / DOM Diffing -->
<div id="tableContainer" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden relative">
    <div id="tableLoader" class="absolute inset-0 bg-white/60 dark:bg-gray-800/60 z-10 hidden flex mt-12 mb-auto justify-center backdrop-blur-[2px]">
        <i class="fa-solid fa-circle-notch fa-spin text-4xl text-sena mt-12"></i>
    </div>

    <!-- Contenido PHP puro mapeado en el Frontend -->
    <div id="tableContent">
        <!-- SE CARGAN EXACTAMENTE LAS MISMAS CABECERAS PARA SEO/LOAD INICIAL -->
        <div class="px-6 py-3 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/80 text-sm text-gray-500 flex justify-between">
            <span>Mostrando <strong><?= count($aprendices) ?></strong> aprendices encontrados.</span>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-800/50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase hover:bg-gray-100 dark:hover:bg-gray-700 transition cursor-pointer">
                            <a href="javascript:void(0)" onclick="handleSort('cedula', '<?= $sort_col === 'cedula' && $order_dir === 'ASC' ? 'DESC' : 'ASC' ?>')" class="flex items-center group">
                                Cédula <?= getSortIcon('cedula', $sort_col, $order_dir) ?>
                            </a>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase hover:bg-gray-100 dark:hover:bg-gray-700 transition cursor-pointer">
                            <a href="javascript:void(0)" onclick="handleSort('apellidos', '<?= $sort_col === 'apellidos' && $order_dir === 'ASC' ? 'DESC' : 'ASC' ?>')" class="flex items-center group">
                                Nombre Completo <?= getSortIcon('apellidos', $sort_col, $order_dir) ?>
                            </a>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase cursor-default">Correo</th>
                        <th class="px-6 py-3 text-center text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase hover:bg-gray-100 dark:hover:bg-gray-700 transition cursor-pointer">
                            <a href="javascript:void(0)" onclick="handleSort('codigo_curso', '<?= $sort_col === 'codigo_curso' && $order_dir === 'ASC' ? 'DESC' : 'ASC' ?>')" class="flex items-center justify-center group">
                                Ficha <?= getSortIcon('codigo_curso', $sort_col, $order_dir) ?>
                            </a>
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase cursor-default">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    <?php if(empty($aprendices)): ?>
                        <tr>
                            <td colspan="5" class="py-12 text-center">
                                <i class="fa-solid fa-users-slash text-4xl text-gray-300 dark:text-gray-600 mb-3 block"></i>
                                <span class="text-sm text-gray-500 dark:text-gray-400">No hay aprendices que coincidan con la búsqueda.</span>
                            </td>
                        </tr>
                    <?php else: foreach($aprendices as $a): ?>
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                            <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-gray-200"><?= htmlspecialchars($a['cedula']) ?></td>
                            <td class="px-6 py-4 text-sm text-gray-800 dark:text-gray-300 font-semibold">
                                <?= htmlspecialchars($a['apellidos'] . ', ' . $a['nombres']) ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                <a href="mailto:<?= htmlspecialchars($a['correo']) ?>" class="hover:text-sena hover:underline transition-all">
                                    <?= htmlspecialchars($a['correo']) ?>
                                </a>
                            </td>
                            <td class="px-6 py-4 text-sm text-center font-bold text-sena">
                                <span class="bg-sena-light/10 border border-sena-light/20 dark:bg-sena-dark/20 text-sena px-3 py-1 rounded w-full inline-block">
                                    <?= htmlspecialchars($a['codigo_curso']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right whitespace-nowrap">
                                <button onclick='openModal(<?= json_encode($a, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' class="w-8 h-8 rounded bg-gray-100 hover:bg-blue-100 text-blue-600 hover:text-blue-800 dark:bg-gray-700 dark:text-blue-400 dark:hover:bg-gray-600 transition-colors mx-0.5" title="Editar">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                                <button onclick='confirmDelete(<?= $a['id'] ?>)' class="w-8 h-8 rounded bg-gray-100 hover:bg-red-100 text-red-600 hover:text-red-800 dark:bg-gray-700 dark:text-red-400 dark:hover:bg-gray-600 transition-colors mx-0.5" title="Eliminar">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ============================================== -->
<!-- MODALES Y FORMULARIOS OCULTOS (CREATE/UPDATE/DELETE) -->
<!-- ============================================== -->

<!-- Formulario oculto de borrado (Seguridad para disparar por POST) -->
<form id="deleteForm" method="POST" style="display:none;">
    <input type="hidden" name="action" value="delete_aprendiz">
    <input type="hidden" name="delete_id" id="delete_id">
</form>

<!-- Modal Overlay Background -->
<div id="modalOverlay" class="fixed inset-0 bg-gray-900/60 dark:bg-black/70 z-50 hidden flex items-center justify-center backdrop-blur-sm transition-opacity opacity-0 duration-300">
    <!-- Modal Content -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl border border-gray-100 dark:border-gray-700 w-full max-w-lg mx-4 overflow-hidden transform scale-95 transition-transform duration-300" id="modalContent">
        <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 flex justify-between items-center bg-gray-50 dark:bg-gray-800/80">
            <h3 class="font-bold text-gray-800 dark:text-gray-100 text-lg" id="modalTitle">Nuevo Aprendiz</h3>
            <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors">
                <i class="fa-solid fa-times text-xl"></i>
            </button>
        </div>
        
        <form method="POST" class="p-6">
            <input type="hidden" name="action" value="save_aprendiz">
            <input type="hidden" name="id" id="form_id" value="">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div class="col-span-1">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Cédula *</label>
                    <input type="text" name="cedula" id="form_cedula" required class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:ring-sena focus:border-sena">
                </div>
                <div class="col-span-1 border border-dashed border-gray-300 dark:border-gray-600 rounded p-2 relative">
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Ficha de Curso *</label>
                    <input type="text" name="codigo_curso" id="form_ficha" required placeholder="Ej:ADSO-101" list="fichas_list" autocomplete="off" class="w-full bg-transparent border-0 border-b-2 border-gray-200 dark:border-gray-700 focus:border-sena focus:ring-0 p-1 text-sm dark:text-gray-200 font-bold text-sena">
                    <datalist id="fichas_list">
                        <?php foreach($fichas_disponibles as $f): ?><option value="<?= htmlspecialchars($f) ?>"><?php endforeach; ?>
                    </datalist>
                </div>
                <div class="col-span-1">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nombres *</label>
                    <input type="text" name="nombres" id="form_nombres" required class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:ring-sena focus:border-sena">
                </div>
                <div class="col-span-1">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Apellidos *</label>
                    <input type="text" name="apellidos" id="form_apellidos" required class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:ring-sena focus:border-sena">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Correo Electrónico *</label>
                    <input type="email" name="correo" id="form_correo" required class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:ring-sena focus:border-sena">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Teléfono Fijo / Móvil</label>
                    <input type="text" name="telefono" id="form_telefono" class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:ring-sena focus:border-sena">
                </div>
            </div>
            
            <div class="flex justify-end gap-3 border-t border-gray-100 dark:border-gray-700 pt-4 mt-2">
                <button type="button" onclick="closeModal()" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-800 dark:bg-gray-700 dark:hover:bg-gray-600 dark:text-gray-200 rounded font-semibold transition-colors">Cancelar</button>
                <button type="submit" class="px-6 py-2 bg-sena hover:bg-sena-dark text-white rounded font-semibold shadow-sm transition-colors" id="btnGuardar">
                    <i class="fa-solid fa-floppy-disk mr-1"></i> Guardar Alumno
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Lógica JavaScript (DOM Diffing/AJAX + Modal Mng) -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    // ==== 1. AJAX TABLE FETCH (Real-Time) ====
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
        searchIcon.classList.remove('fa-magnifying-glass');
        searchIcon.classList.add('fa-spinner', 'fa-spin');

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
            searchIcon.classList.add('fa-magnifying-glass');
            searchIcon.classList.remove('fa-spinner', 'fa-spin');
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

    // ==== 2. MODAL & POST SUBMIT ACTIONS ====
    const overlay = document.getElementById('modalOverlay');
    const modalContent = document.getElementById('modalContent');
    const modalTitle = document.getElementById('modalTitle');
    const btnGuardar = document.getElementById('btnGuardar');
    
    window.openModal = (aprendiz = null) => {
        overlay.classList.remove('hidden');
        // Pequeño timeout para permitir el CSS transition
        setTimeout(() => {
            overlay.classList.remove('opacity-0');
            modalContent.classList.remove('scale-95');
        }, 10);

        if (aprendiz) {
            // Modo Edición
            modalTitle.innerHTML = '<i class="fa-solid fa-pen-to-square text-sena mr-2"></i> Editar Aprendiz';
            document.getElementById('form_id').value = aprendiz.id;
            document.getElementById('form_cedula').value = aprendiz.cedula;
            document.getElementById('form_nombres').value = aprendiz.nombres;
            document.getElementById('form_apellidos').value = aprendiz.apellidos;
            document.getElementById('form_correo').value = aprendiz.correo;
            document.getElementById('form_ficha').value = aprendiz.codigo_curso;
            document.getElementById('form_telefono').value = aprendiz.telefono || '';
            btnGuardar.innerHTML = '<i class="fa-solid fa-cloud-arrow-up mr-1"></i> Actualizar';
        } else {
            // Modo Nuevo
            modalTitle.innerHTML = '<i class="fa-solid fa-user-plus text-indigo-500 mr-2"></i> Alta de Alumno';
            document.getElementById('form_id').value = '';
            document.getElementById('form_cedula').value = '';
            document.getElementById('form_nombres').value = '';
            document.getElementById('form_apellidos').value = '';
            document.getElementById('form_correo').value = '';
            document.getElementById('form_ficha').value = '';
            document.getElementById('form_telefono').value = '';
            btnGuardar.innerHTML = '<i class="fa-solid fa-floppy-disk mr-1"></i> Matricular';
        }
    };

    window.closeModal = () => {
        overlay.classList.add('opacity-0');
        modalContent.classList.add('scale-95');
        setTimeout(() => {
            overlay.classList.add('hidden');
        }, 300); // Mismo tiempo que el duration-300 de Tailwind
    };

    // Cierre clickeando fuera del modal
    overlay.addEventListener('click', (e) => {
        if (e.target === overlay) closeModal();
    });

    // Delete Trigger Setup
    window.confirmDelete = (id) => {
        if (confirm("🚨 ¿Estás totalmente seguro de dar de baja curricular a este aprendiz?\n\nAl eliminarlo, se borrará en cascada todo el historial de sus calificaciones obligatoriamente para consistencia de datos.")) {
            document.getElementById('delete_id').value = id;
            document.getElementById('deleteForm').submit();
        }
    };
});
</script>
