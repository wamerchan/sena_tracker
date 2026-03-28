<?php
// index.php
session_start();
require_once 'config/database.php';

// Router Basico
$view = isset($_GET['view']) ? $_GET['view'] : 'dashboard';
$allowed_views = ['dashboard', 'aprendices', 'evidencias', 'calificaciones', 'instructor', 'reportes'];

if (!in_array($view, $allowed_views)) {
    $view = 'dashboard';
}

$view_file = "views/modules/{$view}.php";

// Deteccion central de AJAX
$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if (!$is_ajax) {
    require_once 'views/layout/header.php';
    require_once 'views/layout/sidebar.php';
    echo '<div class="flex-1 w-full min-h-screen pt-16 md:pt-0 md:ml-64 bg-gray-50 dark:bg-gray-900 transition-colors duration-300">';
    echo '<main class="p-4 md:p-6 lg:p-8 h-full text-gray-800 dark:text-gray-100 page-enter">';
}

// Carga del Modulo
if (file_exists($view_file)) {
    require_once $view_file;
} else {
    echo '<div class="flex items-center justify-center min-h-[60vh]">';
    echo '<div class="text-center">';
    echo '<div class="w-20 h-20 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center mx-auto mb-4 animate-float">';
    echo '<i class="fa-solid fa-hard-drive text-3xl text-red-500 dark:text-red-400"></i>';
    echo '</div>';
    echo '<h2 class="text-2xl font-bold text-gray-800 dark:text-gray-100 mb-2">Modulo no encontrado</h2>';
    echo '<p class="text-gray-500 dark:text-gray-400 text-sm mb-6">El modulo solicitado no existe o esta en construccion.</p>';
    echo '<a href="?view=dashboard" class="inline-flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-sena to-emerald-500 text-white rounded-lg font-semibold shadow-md hover:shadow-lg transition-all duration-200 hover:-translate-y-0.5"><i class="fa-solid fa-arrow-left"></i> Volver al Dashboard</a>';
    echo '</div>';
    echo '</div>';
}

if (!$is_ajax) {
    echo '</main>';
    echo '</div>';
    require_once 'views/layout/footer.php';
}
?>