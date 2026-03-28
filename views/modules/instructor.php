<!-- views/modules/instructor.php -->
<?php
$db = Database::connect();
$mensaje = '';
$tipo_mensaje = '';

// ==========================================
// PROCESAR FORMULARIO DE EDICIÓN
// Actualizamos los datos del instructor que rige en el sistema.
// ==========================================
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
            if ($e->getCode() == 23000) {
                $mensaje = "Error: La cédula o el correo ya están en uso, verifique bien mijo.";
            } else {
                $mensaje = "Error en base de datos: " . $e->getMessage();
            }
            $tipo_mensaje = "error";
        }
    } else {
        $mensaje = "Por favor, completa los campos requeridos.";
        $tipo_mensaje = "error";
    }
}

// Obtener instructor
$stmt = $db->query("SELECT * FROM instructor LIMIT 1");
$instructor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$instructor) {
    $db->query("INSERT IGNORE INTO instructor (nombres, apellidos, cedula, correo, cargo) VALUES ('Instructor', 'Nuevo', '1111111', 'instructor@sena.edu.co', 'Instructor SENA')");
    $instructor = $db->query("SELECT * FROM instructor LIMIT 1")->fetch(PDO::FETCH_ASSOC);
}
?>

<!-- Header -->
<div class="mb-6 flex justify-between items-center animate-fade-in-up">
    <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white tracking-tight">Perfil de Instructor</h1>
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

<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
    <!-- Profile Card -->
    <div class="md:col-span-1">
        <div class="card-modern overflow-hidden animate-fade-in-up" style="animation-delay: 100ms">
            <!-- Gradient Header -->
            <div class="bg-gradient-to-br from-sena via-emerald-500 to-emerald-400 h-28 relative overflow-hidden">
                <div class="absolute inset-0 opacity-20">
                    <div class="absolute -top-4 -right-4 w-24 h-24 rounded-full bg-white/20"></div>
                    <div class="absolute bottom-0 left-0 w-32 h-32 rounded-full bg-white/10 -translate-x-8 translate-y-8"></div>
                </div>
            </div>
            <div class="px-6 pb-6 flex flex-col items-center -mt-14 relative z-10">
                <div class="w-28 h-28 bg-white dark:bg-gray-700 rounded-2xl border-4 border-white dark:border-gray-800 flex items-center justify-center text-5xl shadow-lg relative z-20">
                    <i class="fa-solid fa-user-tie text-blue-900 dark:text-blue-400 relative z-30"></i>
                </div>
                <h2 class="text-xl font-extrabold text-gray-900 dark:text-white mt-3 text-center"><?= htmlspecialchars($instructor['nombres'] . ' ' . $instructor['apellidos']) ?></h2>
                <div class="flex items-center gap-1.5 mt-1">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-gradient-to-r from-sena/10 to-emerald-500/10 text-sena dark:from-sena-dark/20 dark:to-emerald-900/20 dark:text-sena-300 border border-sena/20 dark:border-sena-dark/30">
                        <i class="fa-solid fa-briefcase mr-1.5"></i> <?= htmlspecialchars($instructor['cargo']) ?>
                    </span>
                </div>
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-3 font-mono">ID: <?= htmlspecialchars($instructor['id']) ?></p>
            </div>
        </div>
    </div>

    <!-- Edit Form -->
    <div class="md:col-span-2">
        <div class="card-modern p-6 animate-fade-in-up" style="animation-delay: 200ms">
            <div class="flex items-center gap-3 mb-6 pb-4 border-b border-gray-100 dark:border-gray-700">
                <div class="w-9 h-9 rounded-lg bg-gradient-to-br from-indigo-500 to-purple-500 flex items-center justify-center text-white shadow-sm">
                    <i class="fa-solid fa-pen-to-square text-sm"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-gray-800 dark:text-white">Actualizar Datos</h3>
                    <p class="text-xs text-gray-400 dark:text-gray-500">Modifica tu información personal</p>
                </div>
            </div>

            <form method="POST" action="?view=instructor">
                <input type="hidden" name="action" value="edit_instructor">
                <input type="hidden" name="id" value="<?= htmlspecialchars($instructor['id']) ?>">

                <div class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="input-group">
                            <input type="text" name="nombres" value="<?= htmlspecialchars($instructor['nombres']) ?>" required placeholder=" " class="form-input peer pt-5">
                            <label>Nombres *</label>
                            <span class="input-icon peer-focus:text-sena"><i class="fa-solid fa-user"></i></span>
                        </div>
                        <div class="input-group">
                            <input type="text" name="apellidos" value="<?= htmlspecialchars($instructor['apellidos']) ?>" required placeholder=" " class="form-input peer pt-5">
                            <label>Apellidos *</label>
                            <span class="input-icon peer-focus:text-sena"><i class="fa-solid fa-user"></i></span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="input-group">
                            <input type="text" name="cedula" value="<?= htmlspecialchars($instructor['cedula']) ?>" required placeholder=" " class="form-input peer pt-5">
                            <label>Cédula *</label>
                            <span class="input-icon peer-focus:text-sena"><i class="fa-solid fa-id-card"></i></span>
                        </div>
                        <div class="input-group">
                            <input type="text" name="telefono" value="<?= htmlspecialchars($instructor['telefono'] ?? '') ?>" placeholder=" " class="form-input peer pt-5">
                            <label>Teléfono</label>
                            <span class="input-icon peer-focus:text-sena"><i class="fa-solid fa-phone"></i></span>
                        </div>
                    </div>

                    <div class="input-group">
                        <input type="email" name="correo" value="<?= htmlspecialchars($instructor['correo']) ?>" required placeholder=" " class="form-input peer pt-5">
                        <label>Correo Electrónico *</label>
                        <span class="input-icon peer-focus:text-sena"><i class="fa-solid fa-envelope"></i></span>
                    </div>
                </div>

                <div class="mt-6 flex justify-end">
                    <button type="submit" class="btn-gradient-sena btn-ripple text-sm flex items-center gap-2">
                        <i class="fa-solid fa-save"></i> Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
