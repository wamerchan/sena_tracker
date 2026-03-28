<!-- views/modules/dashboard.php -->
<?php
$db = Database::connect();

$metrics = [
    'aprendices' => $db->query("SELECT COUNT(*) FROM aprendices")->fetchColumn(),
    'evidencias' => $db->query("SELECT COUNT(*) FROM evidencias")->fetchColumn(),
    'calificaciones_aprobadas' => $db->query("SELECT COUNT(*) FROM calificaciones WHERE estado_calificacion = 'Aprobada'")->fetchColumn(),
    'calificaciones_devueltas' => $db->query("SELECT COUNT(*) FROM calificaciones WHERE estado_calificacion = 'Devuelta'")->fetchColumn(),
    'vencidas' => $db->query("SELECT COUNT(*) FROM evidencias WHERE fecha_entrega < NOW()")->fetchColumn(),
];
?>

<!-- Header -->
<div class="mb-8 animate-fade-in-up">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white tracking-tight">Dashboard General</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Resumen de metricas del programa ADSO</p>
        </div>
        <div class="flex items-center gap-2 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg px-4 py-2 shadow-sm">
            <div class="w-2 h-2 rounded-full bg-sena animate-pulse"></div>
            <span class="text-xs font-semibold text-gray-600 dark:text-gray-300">
                <i class="fa-regular fa-calendar mr-1.5"></i> <?= date('d M, Y') ?>
            </span>
        </div>
    </div>
</div>

<!-- Metrics Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
    <!-- Card 1: Total Aprendices -->
    <div class="card-modern metric-card metric-blue p-5 stagger-item" data-animate>
        <div class="flex items-start justify-between mb-4">
            <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-blue-500 to-blue-400 flex items-center justify-center text-white shadow-lg shadow-blue-500/20">
                <i class="fa-solid fa-users text-base"></i>
            </div>
            <span class="text-[10px] font-bold uppercase tracking-widest text-blue-500 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/20 px-2 py-0.5 rounded-full">Total</span>
        </div>
        <p class="counter-value text-2xl font-extrabold text-gray-900 dark:text-white" data-count="<?= $metrics['aprendices'] ?>">0</p>
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 font-medium">Aprendices Registrados</p>
        <div class="mt-3 flex items-center gap-1 text-xs text-emerald-600 dark:text-emerald-400 font-medium">
            <i class="fa-solid fa-arrow-trend-up"></i> Activos en el sistema
        </div>
    </div>

    <!-- Card 2: Evidencias -->
    <div class="card-modern metric-card metric-green p-5 stagger-item" data-animate>
        <div class="flex items-start justify-between mb-4">
            <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-sena to-emerald-400 flex items-center justify-center text-white shadow-lg shadow-sena/20">
                <i class="fa-solid fa-file-lines text-base"></i>
            </div>
            <span class="text-[10px] font-bold uppercase tracking-widest text-sena bg-sena/10 px-2 py-0.5 rounded-full">Config</span>
        </div>
        <p class="counter-value text-2xl font-extrabold text-gray-900 dark:text-white" data-count="<?= $metrics['evidencias'] ?>">0</p>
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 font-medium">Evidencias Configuradas</p>
        <div class="mt-3 flex items-center gap-1 text-xs text-sena font-medium">
            <i class="fa-solid fa-layer-group"></i> M&aacute;dulos totales
        </div>
    </div>

    <!-- Card 3: Aprobados vs Devueltos -->
    <div class="card-modern metric-card metric-yellow p-5 stagger-item" data-animate>
        <div class="flex items-start justify-between mb-4">
            <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-amber-500 to-yellow-400 flex items-center justify-center text-white shadow-lg shadow-amber-500/20">
                <i class="fa-solid fa-scale-balanced text-base"></i>
            </div>
            <span class="text-[10px] font-bold uppercase tracking-widest text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/20 px-2 py-0.5 rounded-full">Ratio</span>
        </div>
        <?php
        $total_calificadas = $metrics['calificaciones_aprobadas'] + $metrics['calificaciones_devueltas'];
        $porcentaje = $total_calificadas > 0 ? round(($metrics['calificaciones_aprobadas'] / $total_calificadas) * 100) : 0;
        ?>
        <div class="flex items-end gap-2 mb-1">
            <span class="counter-value text-2xl font-extrabold text-gray-900 dark:text-white" data-count="<?= $metrics['calificaciones_aprobadas'] ?>">0</span>
            <span class="text-sm font-bold text-red-400 mb-1">/ <?= $metrics['calificaciones_devueltas'] ?></span>
        </div>
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 font-medium">Aprobadas vs Devueltas</p>
        <!-- Progress Bar with gradient -->
        <div class="mt-3">
            <div class="progress-bar-gradient h-2">
                <div class="progress-fill" style="width: <?= $porcentaje ?>%"></div>
            </div>
            <p class="text-[10px] text-gray-400 mt-1 text-right font-semibold"><?= $porcentaje ?>% aprobacion</p>
        </div>
    </div>

    <!-- Card 4: Vencidas -->
    <div class="card-modern metric-card metric-red p-5 stagger-item" data-animate>
        <div class="flex items-start justify-between mb-4">
            <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-red-500 to-rose-400 flex items-center justify-center text-white shadow-lg shadow-red-500/20">
                <i class="fa-solid fa-clock-rotate-left text-base"></i>
            </div>
            <span class="text-[10px] font-bold uppercase tracking-widest text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/20 px-2 py-0.5 rounded-full">Alerta</span>
        </div>
        <p class="counter-value text-2xl font-extrabold text-gray-900 dark:text-white" data-count="<?= $metrics['vencidas'] ?>">0</p>
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 font-medium">Evidencias Vencidas</p>
        <div class="mt-3 flex items-center gap-1 text-xs text-red-500 dark:text-red-400 font-medium">
            <i class="fa-solid fa-triangle-exclamation"></i> Requieren atenci&oacute;n
        </div>
    </div>
</div>

<!-- Welcome Banner -->
<div class="banner-gradient stagger-item" data-animate>
    <div class="flex items-start gap-4">
        <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-500 flex items-center justify-center text-white shadow-lg shadow-indigo-500/20 flex-shrink-0 animate-float">
            <i class="fa-solid fa-rocket text-lg"></i>
        </div>
        <div class="flex-1">
            <h4 class="font-bold text-gray-800 dark:text-gray-100 text-lg mb-1">&iexcl;Bienvenido a ADSO SENA!</h4>
            <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">La plataforma est&aacute; lista para usar. Ve a <a href="?view=calificaciones" class="font-semibold text-indigo-600 dark:text-indigo-400 hover:text-indigo-700 dark:hover:text-indigo-300 underline underline-offset-2 decoration-indigo-300 dark:decoration-indigo-600 transition-colors">Calificaciones</a> para actualizar notas en bloque por c&oacute;digo de curso y evidencia.</p>
        </div>
    </div>
</div>

<!-- Counter Animation Script -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    const counters = document.querySelectorAll('.counter-value[data-count]');
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const el = entry.target;
                const target = parseInt(el.dataset.count);
                animateCounter(el, target, 1200);
                observer.unobserve(el);
            }
        });
    }, { threshold: 0.5 });

    counters.forEach(counter => observer.observe(counter));

    // Also trigger scroll animations for stagger items
    const staggerObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                staggerObserver.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1 });

    document.querySelectorAll('[data-animate]').forEach(el => {
        staggerObserver.observe(el);
    });
});
</script>
