<!-- views/modules/instructor.php -->
<?php
$db = Database::connect();
$mensaje = '';
$tipo_mensaje = '';

// Procesar Formulario de Edición
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_instructor') {
    $id = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);
    $nombres = filter_input(INPUT_POST, 'nombres', FILTER_SANITIZE_STRING);
    $apellidos = filter_input(INPUT_POST, 'apellidos', FILTER_SANITIZE_STRING);
    $correo = filter_input(INPUT_POST, 'correo', FILTER_SANITIZE_EMAIL);
    $telefono = filter_input(INPUT_POST, 'telefono', FILTER_SANITIZE_STRING);
    $cedula = filter_input(INPUT_POST, 'cedula', FILTER_SANITIZE_STRING);

    if ($id && $nombres && $apellidos && $correo && $cedula) {
        try {
            $stmt = $db->prepare("UPDATE instructor SET nombres = ?, apellidos = ?, correo = ?, telefono = ?, cedula = ? WHERE id = ?");
            $stmt->execute([$nombres, $apellidos, $correo, $telefono, $cedula, $id]);
            $mensaje = "Perfil actualizado correctamente.";
            $tipo_mensaje = "success";
        } catch (PDOException $e) {
            // Manejar error de cedula/correo duplicado
            if ($e->getCode() == 23000) {
                $mensaje = "Error: La cédula o el correo ya están en uso por otro instructor.";
            } else {
                $mensaje = "Error de base de datos: " . $e->getMessage();
            }
            $tipo_mensaje = "error";
        }
    } else {
        $mensaje = "Por favor, completa los campos requeridos.";
        $tipo_mensaje = "error";
    }
}

// Obtener o Crear instructor dummy si está vacío (Fallback safety)
$stmt = $db->query("SELECT * FROM instructor LIMIT 1");
$instructor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$instructor) {
    // Insert inicial en caso fortuito (auto-recuperación)
    $db->query("INSERT IGNORE INTO instructor (nombres, apellidos, cedula, correo, cargo) VALUES ('Instructor', 'Nuevo', '1111111', 'instructor@sena.edu.co', 'Instructor SENA')");
    $instructor = $db->query("SELECT * FROM instructor LIMIT 1")->fetch(PDO::FETCH_ASSOC);
}
?>

<div class="mb-6 flex justify-between items-center">
    <h1 class="text-3xl font-bold text-gray-800 dark:text-gray-100 uppercase tracking-tight">Perfil de Instructor</h1>
</div>

<?php if ($mensaje): ?>
<div class="mb-4 p-4 rounded-lg <?= $tipo_mensaje === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?> transition-all">
    <i class="fa-solid <?= $tipo_mensaje === 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation' ?>"></i> <?= htmlspecialchars($mensaje) ?>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
    <!-- Visualización del Perfil -->
    <div class="md:col-span-1 max-w-sm w-full bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-100 dark:border-gray-700 overflow-hidden h-fit">
        <div class="bg-sena h-24 w-full"></div>
        <div class="px-6 py-4 flex flex-col items-center -mt-16">
            <div class="w-24 h-24 bg-white dark:bg-gray-700 rounded-full border-4 border-white dark:border-gray-800 flex items-center justify-center text-4xl text-gray-400 shadow-sm">
                <i class="fa-solid fa-user-tie text-sena"></i>
            </div>
            <h2 class="text-xl font-bold text-gray-800 dark:text-gray-100 mt-2 text-center"><?= htmlspecialchars($instructor['nombres'] . ' ' . $instructor['apellidos']) ?></h2>
            <p class="text-sm text-sena font-medium"><?= htmlspecialchars($instructor['cargo']) ?></p>
            <p class="text-xs text-gray-400 mt-2">ID: <?= htmlspecialchars($instructor['id']) ?></p>
        </div>
    </div>

    <!-- Formulario de Edición -->
    <div class="md:col-span-2 bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-100 dark:border-gray-700 p-6">
        <h3 class="text-lg font-bold text-gray-800 dark:text-gray-200 border-b border-gray-100 dark:border-gray-700 pb-3 mb-4">Actualizar Datos</h3>
        <form method="POST" action="?view=instructor">
            <input type="hidden" name="action" value="edit_instructor">
            <input type="hidden" name="id" value="<?= htmlspecialchars($instructor['id']) ?>">
            
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nombres *</label>
                    <input type="text" name="nombres" value="<?= htmlspecialchars($instructor['nombres']) ?>" required
                           class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:ring-sena focus:border-sena">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Apellidos *</label>
                    <input type="text" name="apellidos" value="<?= htmlspecialchars($instructor['apellidos']) ?>" required
                           class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:ring-sena focus:border-sena">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Cédula *</label>
                    <input type="text" name="cedula" value="<?= htmlspecialchars($instructor['cedula']) ?>" required
                           class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:ring-sena focus:border-sena">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Teléfono</label>
                    <input type="text" name="telefono" value="<?= htmlspecialchars($instructor['telefono'] ?? '') ?>"
                           class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:ring-sena focus:border-sena">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Correo Electrónico *</label>
                    <input type="email" name="correo" value="<?= htmlspecialchars($instructor['correo']) ?>" required
                           class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:ring-sena focus:border-sena">
                </div>
            </div>

            <div class="mt-6 flex justify-end">
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 px-6 rounded-lg transition-colors shadow focus:ring focus:ring-indigo-300 gap-2 flex items-center">
                    <i class="fa-solid fa-save"></i> Guardar Cambios
                </button>
            </div>
        </form>
    </div>
</div>
