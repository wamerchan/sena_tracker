<!-- views/layout/sidebar.php -->
<aside id="sidebar"
    class="fixed inset-y-0 left-0 bg-white dark:bg-gray-800 w-64 shadow-xl z-50 transform -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out flex flex-col">

    <!-- Gradient accent bar on left edge -->
    <div class="absolute left-0 top-0 bottom-0 w-1 bg-gradient-to-b from-sena via-emerald-400 to-sena-dark rounded-r">
    </div>

    <!-- Logo -->
    <div class="h-20 flex items-center justify-center border-b border-gray-100 dark:border-gray-700 mx-5 relative">
        <div class="flex flex-col items-center">
            <div class="flex items-center gap-2">
                <div
                    class="w-9 h-9 rounded-lg bg-gradient-to-br from-sena to-emerald-400 flex items-center justify-center shadow-sm">
                    <i class="fa-solid fa-graduation-cap text-white text-base"></i>
                </div>
                <span class="text-gray-800 dark:text-white font-extrabold text-xl tracking-tight uppercase">SENA</span>
            </div>
            <span
                class="text-[10px] text-gray-400 dark:text-gray-500 font-semibold tracking-widest uppercase mt-0.5">Administrador
                de Evidencias</span>
            <span class="text-[9px] text-sena/70 dark:text-emerald-400/60 font-mono tracking-widest mt-0.5">Versión:
                <?= htmlspecialchars($_ENV['APP_VERSION'] ?? '1.0.0') ?></span>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="mt-5 px-3 space-y-1 flex-1">
        <span
            class="text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest px-3 mb-2 block">Menu
            Principal</span>
        <?php
        $currentView = $view ?? 'dashboard';
        $menuItems = [
            ['view' => 'dashboard', 'icon' => 'fa-chart-line', 'title' => 'Dashboard'],
            ['view' => 'aprendices', 'icon' => 'fa-users', 'title' => 'Aprendices'],
            ['view' => 'evidencias', 'icon' => 'fa-folder-open', 'title' => 'Evidencias'],
            ['view' => 'calificaciones', 'icon' => 'fa-check-double', 'title' => 'Calificaciones'],
            ['view' => 'instructor', 'icon' => 'fa-user-tie', 'title' => 'Instructor'],
            ['view' => 'reportes', 'icon' => 'fa-chart-pie', 'title' => 'Reportes'],
        ];

        foreach ($menuItems as $item):
            $active = ($currentView === $item['view']);
            $activeClasses = $active
                ? 'sidebar-active-item bg-gradient-to-r from-sena/10 to-transparent text-sena dark:from-sena-dark/20 dark:to-transparent dark:text-sena-light font-semibold'
                : 'text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700/50 hover:text-gray-800 dark:hover:text-white';
            $activeIconBg = $active ? 'bg-sena/10 text-sena dark:bg-sena-dark/20 dark:text-sena-light' : 'text-gray-400 dark:text-gray-500 group-hover:text-gray-600 dark:group-hover:text-gray-300';
            ?>
            <a href="?view=<?= $item['view'] ?>"
                class="group flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all duration-200 relative <?= $activeClasses ?>">
                <?php if ($active): ?>
                    <div
                        class="absolute left-0 top-1/4 bottom-1/4 w-[3px] rounded-r-full bg-gradient-to-b from-sena to-emerald-400">
                    </div>
                <?php endif; ?>
                <div
                    class="w-8 h-8 rounded-lg flex items-center justify-center transition-all duration-200 <?= $activeIconBg ?>">
                    <i class="fa-solid <?= $item['icon'] ?> text-sm"></i>
                </div>
                <span class="text-sm"><?= $item['title'] ?></span>
                <?php if ($active): ?>
                    <div class="ml-auto w-1.5 h-1.5 rounded-full bg-sena animate-pulse"></div>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <!-- Bottom Section -->
    <div class="px-3 pb-5 space-y-2">
        <!-- Dark Mode Toggle -->
        <button id="theme-toggle"
            class="flex items-center justify-center w-full py-2.5 px-4 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50 hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-400 transition-all duration-200 shadow-sm gap-2 group">
            <i class="fa-solid fa-moon dark:hidden text-indigo-500 group-hover:scale-110 transition-transform"></i>
            <span class="dark:hidden font-medium text-sm">Modo Oscuro</span>
            <i
                class="fa-solid fa-sun hidden dark:inline text-yellow-400 group-hover:scale-110 transition-transform"></i>
            <span class="hidden dark:inline font-medium text-sm">Modo Claro</span>
        </button>
    </div>
</aside>

<!-- Mobile Sidebar Overlay -->
<div id="sidebar-overlay" class="fixed inset-0 bg-black/50 z-40 hidden md:hidden transition-opacity backdrop-blur-sm">
</div>