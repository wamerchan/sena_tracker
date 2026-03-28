<!-- views/layout/header.php -->
<!DOCTYPE html>
<html lang="es" class="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title> Evidencias - SENA</title>

    <!-- Tailwind CSS compilado -->
    <link href="assets/css/output.css" rel="stylesheet">

    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Google Fonts - Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap"
        rel="stylesheet">

    <!-- Dark Mode Initializer (FOUC prevention) -->
    <script>
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
</head>

<body class="flex bg-gray-50 dark:bg-gray-900 transition-colors duration-300">
    <!-- Toast Notification Container -->
    <div id="toast-container"></div>

    <!-- Mobile Top Navbar -->
    <div
        class="md:hidden fixed top-0 w-full h-16 bg-gradient-to-r from-white to-gray-50 dark:from-gray-800 dark:to-gray-800/95 shadow-md flex items-center justify-between px-4 z-40 transition-colors duration-300 backdrop-blur-sm border-b border-gray-100 dark:border-gray-700">
        <div class="flex items-center gap-2.5">
            <div
                class="w-8 h-8 rounded-lg bg-gradient-to-br from-sena to-emerald-400 flex items-center justify-center shadow-sm">
                <i class="fa-solid fa-leaf text-white text-sm"></i>
            </div>
            <span class="text-gray-800 dark:text-white font-bold text-lg tracking-tight"> Evidencias SENA</span>
        </div>
        <button id="mobile-menu-btn"
            class="w-10 h-10 rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 focus:outline-none flex items-center justify-center transition-all duration-200 hover:bg-gray-200 dark:hover:bg-gray-600 active:scale-95">
            <i class="fa-solid fa-bars text-lg"></i>
        </button>
    </div>