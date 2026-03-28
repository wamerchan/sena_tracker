<!-- views/modules/dashboard.php -->
<?php
$db = Database::connect();

// Cargar catálogos para los selects
$fichas = $db->query("SELECT DISTINCT codigo_curso FROM evidencias ORDER BY codigo_curso")->fetchAll(PDO::FETCH_COLUMN);
$evidencias_list = $db->query("SELECT DISTINCT codigo_evidencia FROM evidencias ORDER BY codigo_evidencia")->fetchAll(PDO::FETCH_COLUMN);
$aprendices_data = $db->query("SELECT id, cedula, nombres, apellidos, codigo_curso FROM aprendices ORDER BY apellidos, nombres")->fetchAll(PDO::FETCH_ASSOC);

// Variables de contexto
$filter_type = filter_input(INPUT_GET, 'filter_type', FILTER_SANITIZE_STRING) ?? 'global';
$filter_value = trim(filter_input(INPUT_GET, 'filter_value', FILTER_SANITIZE_STRING) ?? '');

$aprendices_count = 0;
$evidencias_count = 0;
$calificaciones_aprobadas = 0;
$calificaciones_devueltas = 0;
$calificaciones_sin_calificar = 0;
$abiertas_count = 0;
$cerradas_count = 0;
$context_name = "Global";

// MOTOR DE CONTEXTUALIZACIÓN
if ($filter_type === 'ficha' && !empty($filter_value)) {
    $context_name = "Ficha " . htmlspecialchars($filter_value);
    
    $stmtA = $db->prepare("SELECT COUNT(*) FROM aprendices WHERE codigo_curso = ?");
    $stmtA->execute([$filter_value]);
    $aprendices_count = $stmtA->fetchColumn();
    
    $stmtE = $db->prepare("SELECT COUNT(*) FROM evidencias WHERE codigo_curso = ?");
    $stmtE->execute([$filter_value]);
    $evidencias_count = $stmtE->fetchColumn();
    
    $stmtC = $db->prepare("SELECT COUNT(*) FROM calificaciones c JOIN aprendices a ON a.id = c.id_aprendiz WHERE a.codigo_curso = ? AND c.estado_calificacion = 'Aprobada'");
    $stmtC->execute([$filter_value]);
    $calificaciones_aprobadas = $stmtC->fetchColumn();
    
    $stmtD = $db->prepare("SELECT COUNT(*) FROM calificaciones c JOIN aprendices a ON a.id = c.id_aprendiz WHERE a.codigo_curso = ? AND c.estado_calificacion = 'Devuelta'");
    $stmtD->execute([$filter_value]);
    $calificaciones_devueltas = $stmtD->fetchColumn();

    $stmtS = $db->prepare("SELECT COUNT(*) FROM calificaciones c JOIN aprendices a ON a.id = c.id_aprendiz WHERE a.codigo_curso = ? AND c.estado_calificacion = 'Sin calificar'");
    $stmtS->execute([$filter_value]);
    $calificaciones_sin_calificar = $stmtS->fetchColumn();
    
    $stmtAb = $db->prepare("SELECT COUNT(*) FROM evidencias WHERE codigo_curso = ? AND fecha_entrega >= NOW()");
    $stmtAb->execute([$filter_value]);
    $abiertas_count = $stmtAb->fetchColumn();
    
    $stmtCerr = $db->prepare("SELECT COUNT(*) FROM evidencias WHERE codigo_curso = ? AND fecha_entrega < NOW()");
    $stmtCerr->execute([$filter_value]);
    $cerradas_count = $stmtCerr->fetchColumn();
}
elseif ($filter_type === 'evidencia' && !empty($filter_value)) {
    $context_name = "Evidencia " . htmlspecialchars($filter_value);
    
    $stmtA = $db->prepare("SELECT COUNT(*) FROM aprendices WHERE codigo_curso = (SELECT codigo_curso FROM evidencias WHERE codigo_evidencia = ? LIMIT 1)");
    $stmtA->execute([$filter_value]);
    $aprendices_count = $stmtA->fetchColumn();
    
    $evidencias_count = 1;
    
    $stmtC = $db->prepare("SELECT COUNT(*) FROM calificaciones c JOIN evidencias e ON e.id = c.id_evidencia WHERE e.codigo_evidencia = ? AND c.estado_calificacion = 'Aprobada'");
    $stmtC->execute([$filter_value]);
    $calificaciones_aprobadas = $stmtC->fetchColumn();
    
    $stmtD = $db->prepare("SELECT COUNT(*) FROM calificaciones c JOIN evidencias e ON e.id = c.id_evidencia WHERE e.codigo_evidencia = ? AND c.estado_calificacion = 'Devuelta'");
    $stmtD->execute([$filter_value]);
    $calificaciones_devueltas = $stmtD->fetchColumn();

    $stmtS = $db->prepare("SELECT COUNT(*) FROM calificaciones c JOIN evidencias e ON e.id = c.id_evidencia WHERE e.codigo_evidencia = ? AND c.estado_calificacion = 'Sin calificar'");
    $stmtS->execute([$filter_value]);
    $calificaciones_sin_calificar = $stmtS->fetchColumn();
    
    $stmtAb = $db->prepare("SELECT COUNT(*) FROM evidencias WHERE codigo_evidencia = ? AND fecha_entrega >= NOW()");
    $stmtAb->execute([$filter_value]);
    $abiertas_count = $stmtAb->fetchColumn();

    $stmtCerr = $db->prepare("SELECT COUNT(*) FROM evidencias WHERE codigo_evidencia = ? AND fecha_entrega < NOW()");
    $stmtCerr->execute([$filter_value]);
    $cerradas_count = $stmtCerr->fetchColumn();
}
elseif ($filter_type === 'aprendiz' && !empty($filter_value)) {
    $stmt = $db->prepare("SELECT nombres, apellidos, codigo_curso FROM aprendices WHERE id = ?");
    $stmt->execute([$filter_value]);
    $ap = $stmt->fetch();
    
    if($ap) {
        $context_name = "Aprendiz: " . htmlspecialchars($ap['apellidos'] . ' ' . $ap['nombres']);
        $aprendices_count = 1;
        
        $stmtE = $db->prepare("SELECT COUNT(*) FROM evidencias WHERE codigo_curso = ?");
        $stmtE->execute([$ap['codigo_curso']]);
        $evidencias_count = $stmtE->fetchColumn();
        
        $stmtC = $db->prepare("SELECT COUNT(*) FROM calificaciones WHERE id_aprendiz = ? AND estado_calificacion = 'Aprobada'");
        $stmtC->execute([$filter_value]);
        $calificaciones_aprobadas = $stmtC->fetchColumn();
        
        $stmtD = $db->prepare("SELECT COUNT(*) FROM calificaciones WHERE id_aprendiz = ? AND estado_calificacion = 'Devuelta'");
        $stmtD->execute([$filter_value]);
        $calificaciones_devueltas = $stmtD->fetchColumn();

        $stmtS = $db->prepare("SELECT COUNT(*) FROM calificaciones WHERE id_aprendiz = ? AND estado_calificacion = 'Sin calificar'");
        $stmtS->execute([$filter_value]);
        $calificaciones_sin_calificar = $stmtS->fetchColumn();
        
        $stmtAb = $db->prepare("SELECT COUNT(*) FROM evidencias WHERE codigo_curso = ? AND fecha_entrega >= NOW()");
        $stmtAb->execute([$ap['codigo_curso']]);
        $abiertas_count = $stmtAb->fetchColumn();

        // Evidencias cerradas que el alumno NO ha aprobado
        $stmtCerr = $db->prepare("
            SELECT COUNT(*) FROM evidencias e 
            LEFT JOIN calificaciones c ON c.id_evidencia = e.id AND c.id_aprendiz = ?
            WHERE e.codigo_curso = ? 
            AND e.fecha_entrega < NOW()
            AND (c.estado_calificacion IS NULL OR c.estado_calificacion != 'Aprobada')
        ");
        $stmtCerr->execute([$filter_value, $ap['codigo_curso']]);
        $cerradas_count = $stmtCerr->fetchColumn();
    } else {
        $filter_type = 'global'; // Fallback
    }
}

if ($filter_type === 'global') {
    $aprendices_count = $db->query("SELECT COUNT(*) FROM aprendices")->fetchColumn();
    $evidencias_count = $db->query("SELECT COUNT(*) FROM evidencias")->fetchColumn();
    $calificaciones_aprobadas = $db->query("SELECT COUNT(*) FROM calificaciones WHERE estado_calificacion = 'Aprobada'")->fetchColumn();
    $calificaciones_devueltas = $db->query("SELECT COUNT(*) FROM calificaciones WHERE estado_calificacion = 'Devuelta'")->fetchColumn();
    $calificaciones_sin_calificar = $db->query("SELECT COUNT(*) FROM calificaciones WHERE estado_calificacion = 'Sin calificar'")->fetchColumn();
    $abiertas_count = $db->query("SELECT COUNT(*) FROM evidencias WHERE fecha_entrega >= NOW()")->fetchColumn();
    $cerradas_count = $db->query("SELECT COUNT(*) FROM evidencias WHERE fecha_entrega < NOW()")->fetchColumn();
}

$metrics = [
    'aprendices' => $aprendices_count,
    'evidencias' => $evidencias_count,
    'calificaciones_aprobadas' => $calificaciones_aprobadas,
    'calificaciones_devueltas' => $calificaciones_devueltas,
    'calificaciones_sin_calificar' => $calificaciones_sin_calificar,
    'abiertas' => $abiertas_count,
    'cerradas' => $cerradas_count,
];
?>

<!-- Header -->
<div class="mb-6 animate-fade-in-up">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white tracking-tight flex items-center gap-2">
                Dashboard 
                <?php if($filter_type !== 'global'): ?>
                    <span class="text-indigo-500 font-medium text-2xl">| <?= htmlspecialchars($context_name) ?></span>
                <?php else: ?>
                    <span class="text-gray-400 font-medium text-2xl">| Global</span>
                <?php endif; ?>
            </h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Métricas en tiempo real adaptativas.</p>
        </div>
        <div class="flex items-center gap-2 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg px-4 py-2 shadow-sm">
            <div class="w-2 h-2 rounded-full bg-sena animate-pulse"></div>
            <span class="text-xs font-semibold text-gray-600 dark:text-gray-300">
                <i class="fa-regular fa-calendar mr-1.5"></i> <?= date('d M, Y') ?>
            </span>
        </div>
    </div>
</div>

<!-- Filter Panel -->
<div class="card-modern !overflow-visible relative z-40 p-5 mb-8 animate-fade-in-up" style="animation-delay: 100ms">
    <div class="flex flex-col md:flex-row items-center justify-between gap-4 border-b border-gray-100 dark:border-gray-700 pb-4 mb-4">
        <h3 class="text-sm font-bold text-gray-800 dark:text-gray-200 uppercase tracking-wider">
            <i class="fa-solid fa-filter mr-2 text-indigo-500"></i> Contexto de Reporte
        </h3>
        <?php if($filter_type !== 'global'): ?>
            <a href="?view=dashboard" class="text-xs font-bold text-red-500 hover:text-red-700 hover:underline bg-red-50 dark:bg-red-900/20 px-3 py-1.5 rounded-full transition-colors"><i class="fa-solid fa-xmark mr-1"></i> Borrar Filtro (Ver Global)</a>
        <?php endif; ?>
    </div>
    
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Filtro Ficha -->
        <form method="GET" class="flex flex-col gap-2 relative">
            <input type="hidden" name="view" value="dashboard">
            <input type="hidden" name="filter_type" value="ficha">
            <label class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Por Ficha</label>
            <div class="flex shadow-sm rounded-lg">
                <select name="filter_value" class="form-input rounded-r-none border-r-0 !py-2.5 !text-sm flex-1 font-medium bg-gray-50 dark:bg-gray-900/50" required>
                    <option value="">Seleccione Ficha...</option>
                    <?php foreach($fichas as $f): ?>
                        <option value="<?= htmlspecialchars($f) ?>" <?= ($filter_type === 'ficha' && $filter_value === $f) ? 'selected' : '' ?>><?= htmlspecialchars($f) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn-gradient-indigo rounded-l-none px-4"><i class="fa-solid fa-search"></i></button>
            </div>
        </form>

        <!-- Filtro Evidencia -->
        <form method="GET" class="flex flex-col gap-2 relative">
            <input type="hidden" name="view" value="dashboard">
            <input type="hidden" name="filter_type" value="evidencia">
            <label class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Por Evidencia</label>
            <div class="flex shadow-sm rounded-lg">
                <select name="filter_value" class="form-input rounded-r-none border-r-0 !py-2.5 !text-sm flex-1 font-medium bg-gray-50 dark:bg-gray-900/50" required>
                    <option value="">Seleccione Evidencia...</option>
                    <?php foreach($evidencias_list as $e): ?>
                        <option value="<?= htmlspecialchars($e) ?>" <?= ($filter_type === 'evidencia' && $filter_value === $e) ? 'selected' : '' ?>><?= htmlspecialchars($e) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn-gradient-indigo rounded-l-none px-4"><i class="fa-solid fa-search"></i></button>
            </div>
        </form>

        <!-- Filtro Aprendiz (Live Combobox) -->
        <div class="flex flex-col gap-2 relative">
            <label class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Por Aprendiz (Buscador)</label>
            <div class="relative shadow-sm rounded-lg">
                <input type="text" id="aprendizSearchHelper" class="form-input !py-2.5 !text-sm pr-10 font-medium bg-gray-50 dark:bg-gray-900/50" placeholder="Ej: Perez, Juan, o Cédula" autocomplete="off" <?= $filter_type === 'aprendiz' ? 'value="Reingrese búsqueda..."' : ''; ?>>
                <i class="fa-solid fa-magnifying-glass absolute right-3 top-3 text-gray-400 pointer-events-none"></i>
                
                <div id="aprendizDatalist" class="hidden absolute top-full left-0 right-0 mt-2 max-h-64 overflow-y-auto bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-2xl rounded-lg z-50 divide-y divide-gray-100 dark:divide-gray-700 custom-scrollbar"></div>
            </div>
            
            <form id="formAprendiz" method="GET" class="hidden">
                <input type="hidden" name="view" value="dashboard">
                <input type="hidden" name="filter_type" value="aprendiz">
                <input type="hidden" name="filter_value" id="aprendizFilterValue">
            </form>
        </div>
    </div>
</div>

<!-- Metrics Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
    <!-- Card 1: Aprendices Contexto -->
    <div class="card-modern metric-card metric-blue p-5 stagger-item" data-animate>
        <div class="flex items-start justify-between mb-4">
            <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-blue-500 to-blue-400 flex items-center justify-center text-white shadow-lg shadow-blue-500/20">
                <i class="fa-solid fa-users text-base"></i>
            </div>
            <span class="text-[10px] font-bold uppercase tracking-widest text-blue-500 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/20 px-2 py-0.5 rounded-full"><?= $filter_type === 'aprendiz' ? 'Target' : 'Alcance' ?></span>
        </div>
        <p class="counter-value text-2xl font-extrabold text-gray-900 dark:text-white" data-count="<?= $metrics['aprendices'] ?>">0</p>
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 font-medium">Aprendices Involucrados</p>
    </div>

    <!-- Card 2: Evidencias Contexto -->
    <div class="card-modern metric-card metric-green p-5 stagger-item" data-animate>
        <div class="flex items-start justify-between mb-4">
            <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-sena to-emerald-400 flex items-center justify-center text-white shadow-lg shadow-sena/20">
                <i class="fa-solid fa-file-alt text-base"></i>
            </div>
            <span class="text-[10px] font-bold uppercase tracking-widest text-sena bg-sena/10 px-2 py-0.5 rounded-full"><?= $filter_type === 'evidencia' ? 'Target' : 'Pensum' ?></span>
        </div>
        <p class="counter-value text-2xl font-extrabold text-gray-900 dark:text-white" data-count="<?= $metrics['evidencias'] ?>">0</p>
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 font-medium">Evidencias en Contexto</p>
    </div>

    <!-- Card 3: Aprobadas vs Devueltas vs Sin calificar -->
    <div class="card-modern metric-card metric-yellow p-5 stagger-item" data-animate>
        <div class="flex items-start justify-between mb-4">
            <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-amber-500 to-yellow-400 flex items-center justify-center text-white shadow-lg shadow-amber-500/20">
                <i class="fa-solid fa-scale-balanced text-base"></i>
            </div>
            <span class="text-[10px] font-bold uppercase tracking-widest text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/20 px-2 py-0.5 rounded-full">Ratio</span>
        </div>
        <?php
        $total_calificadas = $metrics['calificaciones_aprobadas'] + $metrics['calificaciones_devueltas'] + $metrics['calificaciones_sin_calificar'];
        $porcentaje = $total_calificadas > 0 ? round(($metrics['calificaciones_aprobadas'] / $total_calificadas) * 100) : 0;
        ?>
        <div class="flex items-baseline gap-1.5 mb-1 font-extrabold text-xl tracking-tight">
            <span class="counter-value text-gray-900 dark:text-white" data-count="<?= $metrics['calificaciones_aprobadas'] ?>">0</span>
            <span class="text-gray-400 uppercase text-sm">A <span class="mx-0.5">/</span></span>
            
            <span class="counter-value text-red-500" data-count="<?= $metrics['calificaciones_devueltas'] ?>">0</span>
            <span class="text-gray-400 uppercase text-sm">D <span class="mx-0.5">/</span></span>
            
            <span class="counter-value text-amber-500" data-count="<?= $metrics['calificaciones_sin_calificar'] ?>">0</span>
            <span class="text-gray-400 uppercase text-sm">SC</span>
        </div>
        <p class="text-[10px] uppercase tracking-widest text-gray-400 mt-1 font-bold">Aprobadas (A) / Devueltas (D) / Sin Calificar (SC)</p>
        <div class="mt-3">
            <div class="progress-bar-gradient h-2">
                <div class="progress-fill" style="width: <?= $porcentaje ?>%"></div>
            </div>
            <p class="text-[10px] text-gray-400 mt-1 text-right font-semibold"><?= $porcentaje ?>% efectividad</p>
        </div>
    </div>

    <!-- Card 4: Abiertas vs Cerradas -->
    <div class="card-modern metric-card metric-purple p-5 stagger-item" data-animate>
        <div class="flex items-start justify-between mb-4">
            <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-purple-500 to-fuchsia-400 flex items-center justify-center text-white shadow-lg shadow-purple-500/20">
                <i class="fa-solid fa-hourglass-half text-base"></i>
            </div>
            <span class="text-[10px] font-bold uppercase tracking-widest text-purple-600 dark:text-purple-400 bg-purple-50 dark:bg-purple-900/20 px-2 py-0.5 rounded-full">Evidencias</span>
        </div>
        <?php
        $total_evidencias_tiempo = $metrics['abiertas'] + $metrics['cerradas'];
        $porcentaje_cerradas = $total_evidencias_tiempo > 0 ? round(($metrics['cerradas'] / $total_evidencias_tiempo) * 100) : 0;
        ?>
        <div class="flex items-baseline gap-1.5 mb-1 font-extrabold text-xl tracking-tight">
            <span class="counter-value text-gray-900 dark:text-white" data-count="<?= $metrics['abiertas'] ?>">0</span>
            <span class="text-gray-400 uppercase text-sm">A <span class="mx-0.5">/</span></span>
            
            <span class="counter-value text-red-500" data-count="<?= $metrics['cerradas'] ?>">0</span>
            <span class="text-gray-400 uppercase text-sm">C</span>
        </div>
        <p class="text-[10px] uppercase tracking-widest text-gray-400 mt-1 font-bold">Abiertas (A) / Cerradas (C)</p>
        <div class="mt-3">
            <div class="progress-bar-gradient h-2">
                <div class="progress-fill bg-gradient-to-r from-purple-500 to-fuchsia-400" style="width: <?= $porcentaje_cerradas ?>%"></div>
            </div>
            <p class="text-[10px] text-gray-400 mt-1 text-right font-semibold"><?= $porcentaje_cerradas ?>% del cronograma cerrado</p>
        </div>
    </div>
</div>

<!-- Scripts Ocultos y Combobox Engine -->
<script>
    const aprendicesData = <?= json_encode($aprendices_data, JSON_UNESCAPED_UNICODE) ?>;
    
    document.addEventListener('DOMContentLoaded', () => {
        const input = document.getElementById('aprendizSearchHelper');
        const list = document.getElementById('aprendizDatalist');
        const form = document.getElementById('formAprendiz');
        const hidValue = document.getElementById('aprendizFilterValue');
        
        let debounceTimer;

        input.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            const val = this.value.toLowerCase().trim();
            list.innerHTML = '';
            
            if (val.length < 2) {
                list.classList.add('hidden');
                return;
            }
            
            debounceTimer = setTimeout(() => {
                const filtered = aprendicesData.filter(a => {
                    const searchStr = `${a.cedula} ${a.nombres} ${a.apellidos}`.toLowerCase();
                    return searchStr.includes(val);
                }).slice(0, 15); // Limitar a 15 resultados por render
                
                if (filtered.length > 0) {
                    filtered.forEach(a => {
                        const div = document.createElement('div');
                        div.className = 'px-4 py-2.5 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 cursor-pointer transition-colors';
                        div.innerHTML = `
                            <div class="font-bold text-sm text-gray-800 dark:text-gray-200">${a.apellidos}, ${a.nombres}</div>
                            <div class="text-[11px] text-gray-500 font-mono mt-0.5"><i class="fa-regular fa-id-badge"></i> ${a.cedula} &nbsp;&bull;&nbsp; <span class="bg-gray-100 dark:bg-gray-700 px-1.5 py-0.5 rounded font-bold">${a.codigo_curso}</span></div>
                        `;
                        div.addEventListener('click', () => {
                            input.value = `${a.apellidos}, ${a.nombres}`;
                            hidValue.value = a.id;
                            list.classList.add('hidden');
                            form.submit();
                        });
                        list.appendChild(div);
                    });
                } else {
                    list.innerHTML = `<div class="px-4 py-4 text-sm text-gray-500 text-center font-medium"><i class="fa-solid fa-ghost mr-2"></i>Ningún aprendiz coincide con '${val}'</div>`;
                }
                list.classList.remove('hidden');
            }, 100);
        });
        
        // Hide on click outside
        document.addEventListener('click', (e) => {
            if(!input.contains(e.target) && !list.contains(e.target)) {
                list.classList.add('hidden');
            }
        });

        // Contadores Animados
        const counters = document.querySelectorAll('.counter-value[data-count]');
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const el = entry.target;
                    const target = parseInt(el.dataset.count);
                    animateCounter(el, target, 800);
                    observer.unobserve(el);
                }
            });
        }, { threshold: 0.5 });
        counters.forEach(counter => observer.observe(counter));

        // Stagger Items
        const staggerObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    staggerObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1 });
        document.querySelectorAll('[data-animate]').forEach(el => staggerObserver.observe(el));
    });

    function animateCounter(el, target, duration) {
        let startTimestamp = null;
        const step = (timestamp) => {
            if (!startTimestamp) startTimestamp = timestamp;
            const progress = Math.min((timestamp - startTimestamp) / duration, 1);
            // easeOutQuad
            const ease = progress * (2 - progress);
            el.innerHTML = Math.floor(ease * target);
            if (progress < 1) {
                window.requestAnimationFrame(step);
            } else {
                el.innerHTML = target;
            }
        };
        window.requestAnimationFrame(step);
    }
</script>
