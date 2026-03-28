<!-- views/modules/reportes.php -->
<?php
$db = Database::connect();
$action = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_STRING) ?? '';

if ($action === 'historial'):
    // ==========================================
    // --- VISTA: HISTORIAL ESPECIFICO DE APRENDIZ ---
    // Consulta pesada (JOIN) con lógica de negocio.
    // Cruzamos la master de EVIDENCIAS vs CALIFICACIONES específicas del alumno.
    // Así detectamos huecos y morosidad.
    // ==========================================
    $search_cedula = trim($_GET['cedula'] ?? '');
    $aprendiz = null;
    $historial = [];
    $mensaje = '';

    if (!empty($search_cedula)) {
        $stmtA = $db->prepare("SELECT * FROM aprendices WHERE cedula = ?");
        $stmtA->execute([$search_cedula]);
        $aprendiz = $stmtA->fetch(PDO::FETCH_ASSOC);

        if ($aprendiz) {
            $stmtH = $db->prepare("
                SELECT
                    e.codigo_evidencia, e.fase, e.guia_aprendizaje, e.fecha_entrega, e.fecha_inicio,
                    COALESCE(c.estado_calificacion, 'Pendiente') as nota,
                    c.fecha_calificacion
                FROM evidencias e
                LEFT JOIN calificaciones c ON e.id = c.id_evidencia AND c.id_aprendiz = ?
                WHERE e.codigo_curso = ?
                ORDER BY e.fecha_entrega ASC
            ");
            $stmtH->execute([$aprendiz['id'], $aprendiz['codigo_curso']]);
            $historial = $stmtH->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $mensaje = "No se encontro ningun aprendiz con la cedula ($search_cedula).";
        }
    }
?>
    <!-- HISTORIAL VIEW -->
    <div class="mb-6 animate-fade-in-up">
        <a href="?view=reportes" class="inline-flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400 hover:text-indigo-500 dark:hover:text-indigo-400 transition-colors font-medium mb-3">
            <i class="fa-solid fa-arrow-left text-xs"></i> Volver al Centro de Reportes
        </a>
        <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white tracking-tight">
            <i class="fa-solid fa-id-card-clip text-indigo-500 mr-2"></i> Expediente Académico
        </h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Situación curricular proyectada contra el pensum de la ficha asignada.</p>
    </div>

    <!-- Buscador -->
    <div class="card-modern p-6 mb-6 animate-fade-in-up" style="animation-delay: 100ms">
        <form method="GET" class="flex flex-col md:flex-row items-end gap-4">
            <input type="hidden" name="view" value="reportes">
            <input type="hidden" name="action" value="historial">

            <div class="flex-1 w-full">
                <label class="form-label">Documento de Identidad (Cédula)</label>
                <div class="search-input-wrapper">
                    <input type="text" name="cedula" value="<?= htmlspecialchars($search_cedula) ?>" placeholder="Ej: 1020304050" required class="!py-3.5 !text-lg !font-bold !tracking-wider">
                    <span class="search-icon"><i class="fa-solid fa-magnifying-glass"></i></span>
                </div>
            </div>
            <button type="submit" class="btn-gradient-indigo btn-ripple text-sm flex items-center gap-2 whitespace-nowrap">
                <i class="fa-solid fa-satellite-dish"></i> Rastrear
            </button>
        </form>
        <?php if($mensaje): ?>
            <p class="mt-4 text-red-600 dark:text-red-400 font-medium text-sm"><i class="fa-solid fa-circle-exclamation mr-1"></i> <?= $mensaje ?></p>
        <?php endif; ?>
    </div>

    <!-- Resultados -->
    <?php if($aprendiz): ?>
        <div class="card-modern overflow-hidden animate-fade-in-up" style="animation-delay: 200ms">
            <!-- Header -->
            <div class="bg-gradient-to-r from-gray-50 to-white dark:from-gray-800/50 dark:to-gray-800 border-b border-gray-100 dark:border-gray-700 p-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <h2 class="text-2xl font-extrabold text-gray-900 dark:text-white uppercase"><?= htmlspecialchars($aprendiz['apellidos'] . ', ' . $aprendiz['nombres']) ?></h2>
                    <p class="text-gray-500 dark:text-gray-400 font-mono mt-1 text-sm">
                        <i class="fa-regular fa-id-badge mr-1"></i> <?= htmlspecialchars($aprendiz['cedula']) ?>
                        <span class="mx-2">&bull;</span>
                        <i class="fa-regular fa-envelope mr-1"></i> <?= htmlspecialchars($aprendiz['correo']) ?>
                    </p>
                </div>
                <div class="text-right flex flex-col items-end gap-3">
                    <div>
                        <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest block mb-1">Matrícula Activa en</span>
                        <span class="inline-flex items-center px-3 py-1.5 bg-gradient-to-r from-indigo-500 to-purple-500 text-white rounded-lg font-extrabold text-lg shadow-lg shadow-indigo-500/20 tracking-tight"><?= htmlspecialchars($aprendiz['codigo_curso']) ?></span>
                    </div>
                    <div class="flex gap-2">
                        <button type="button" onclick="exportarExcel()" class="btn-ripple bg-emerald-50 dark:bg-emerald-900/40 border border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-emerald-400 hover:bg-emerald-100 dark:hover:bg-emerald-900/60 transition-colors px-3 py-1.5 rounded-lg text-xs font-bold shadow-sm">
                            <i class="fa-solid fa-file-excel mr-1"></i> XLS
                        </button>
                        <button type="button" onclick="exportarPDF()" class="btn-ripple bg-rose-50 dark:bg-rose-900/40 border border-rose-200 dark:border-rose-800 text-rose-700 dark:text-rose-400 hover:bg-rose-100 dark:hover:bg-rose-900/60 transition-colors px-3 py-1.5 rounded-lg text-xs font-bold shadow-sm">
                            <i class="fa-solid fa-file-pdf mr-1"></i> PDF
                        </button>
                    </div>
                </div>
            </div>

            <!-- Div Wrapper para el PDF -->
            <div id="print-area" class="bg-white dark:bg-gray-800">
                <!-- Información oculta que solo sale en el PDF -->
                <div class="hidden print-header p-6 pb-0">
                    <h2 class="text-2xl font-bold uppercase text-gray-900">Expediente Académico</h2>
                    <p class="text-gray-600"><strong>Aprendiz:</strong> <?= htmlspecialchars($aprendiz['apellidos'] . ', ' . $aprendiz['nombres']) ?> (<?= htmlspecialchars($aprendiz['cedula']) ?>)</p>
                    <p class="text-gray-600"><strong>Ficha:</strong> <?= htmlspecialchars($aprendiz['codigo_curso']) ?></p>
                    <hr class="my-4 border-gray-200">
                </div>

            <!-- Tabla de Historial -->
            <table id="tabla-exportar" class="min-w-full divide-y divide-gray-100 dark:divide-gray-700">
                <thead class="bg-gray-50/80 dark:bg-gray-800/50">
                    <tr>
                        <th class="px-6 py-3.5 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Código Evidencia</th>
                        <th class="px-6 py-3.5 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider hidden md:table-cell">Etapa / Guía</th>
                        <th class="px-6 py-3.5 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Cierre</th>
                        <th class="px-6 py-3.5 text-right text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Estatus</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50 dark:divide-gray-700/50">
                    <?php
                    $aprobadas = 0; $total = count($historial);
                    if(empty($historial)): ?>
                        <tr><td colspan="4" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">La ficha del alumno no tiene pensum cargado.</td></tr>
                    <?php else: foreach($historial as $i => $h):
                        $fv = new DateTime($h['fecha_entrega']);
                        $hoy = new DateTime();

                        $is_aprobada = ($h['nota'] === 'Aprobada');
                        if($is_aprobada) $aprobadas++;

                        if ($h['nota'] === 'Aprobada') {
                            $badge = '<span class="badge-gradient-success"><i class="fa-solid fa-check mr-1"></i> Aprobada</span>';
                        } elseif ($h['nota'] === 'Devuelta') {
                            $badge = '<span class="badge-gradient-warning"><i class="fa-solid fa-rotate-left mr-1"></i> Devuelta</span>';
                        } else {
                            if ($hoy > $fv) {
                                $badge = '<span class="badge-gradient-danger"><i class="fa-solid fa-triangle-exclamation mr-1"></i> Moroso</span>';
                            } else {
                                $badge = '<span class="badge-gradient-neutral"><i class="fa-regular fa-clock mr-1"></i> En ventana</span>';
                            }
                        }
                    ?>
                        <tr class="table-row-hover" style="animation: fadeInUp 0.4s ease-out <?= $i * 40 ?>ms forwards; opacity: 0;">
                            <td class="px-6 py-4">
                                <span class="font-bold text-gray-800 dark:text-gray-200"><?= htmlspecialchars($h['codigo_evidencia']) ?></span>
                            </td>
                            <td class="px-6 py-4 hidden md:table-cell">
                                <span class="block text-xs text-gray-500 uppercase font-bold"><?= htmlspecialchars($h['fase']) ?></span>
                                <span class="block text-sm font-medium text-gray-600 dark:text-gray-400">Guía <?= htmlspecialchars($h['guia_aprendizaje']) ?></span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="text-sm font-medium text-gray-600 dark:text-gray-400"><?= $fv->format('d M y - H:i') ?></span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <?= $badge ?>
                                <?php if($h['fecha_calificacion']): ?>
                                    <span class="block text-[9px] text-gray-400 mt-1.5 uppercase tracking-widest">Corregido: <?= (new DateTime($h['fecha_calificacion']))->format('d/m') ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>

            <?php if($total > 0):
                $porcentaje = round(($aprobadas / $total) * 100);
            ?>
            <div class="bg-gray-50/50 dark:bg-gray-900/50 border-t border-gray-100 dark:border-gray-700 p-5 flex flex-col sm:flex-row items-center justify-between gap-4">
                <span class="text-gray-500 dark:text-gray-400 font-medium text-sm">Progreso del Pensum:</span>
                <div class="flex items-center gap-4">
                    <div class="progress-bar-gradient w-48 h-2.5">
                        <div class="h-full rounded-full bg-gradient-to-r from-indigo-500 to-purple-500 transition-all duration-1000" style="width: <?= $porcentaje ?>%"></div>
                    </div>
                    <span class="font-extrabold text-xl bg-gradient-to-r from-indigo-500 to-purple-500 bg-clip-text text-transparent"><?= $porcentaje ?>%</span>
                </div>
            </div>
            <?php endif; ?>
            </div> <!-- End Print Area -->
        </div>

        <!-- Librerías de Exportación JS -->
        <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
        <script src="https://cdn.sheetjs.com/xlsx-0.20.0/package/dist/xlsx.full.min.js"></script>
        
        <script>
        function exportarPDF() {
            ToastSystem.info('Generando PDF', 'Preparando el documento, esto tomará unos segundos...');
            
            const element = document.getElementById('print-area');
            const header = element.querySelector('.print-header');
            
            // 1. Mostrar cabecera oculta
            header.classList.remove('hidden');
            
            // 2. Controlar 'Dark Mode' y color de fuente
            const originalDark = document.documentElement.classList.contains('dark');
            if (originalDark) document.documentElement.classList.remove('dark');
            
            // Inyectar CSS temporal para blanco y negro puro (textos e íconos)
            const style = document.createElement('style');
            style.id = 'pdf-print-styles';
            style.innerHTML = `
                #print-area * { color: #000 !important; border-color: #000 !important; }
                .badge-gradient-success, .badge-gradient-warning, .badge-gradient-danger, .badge-gradient-neutral {
                    background: transparent !important;
                    border: 1px solid #000 !important;
                }
                .progress-bar-gradient { border: 1px solid #000 !important; background: transparent !important; }
                .progress-bar-gradient > div { background: #000 !important; }
                i.fa-solid, i.fa-regular { color: #000 !important; }
            `;
            document.head.appendChild(style);
            
            // 3. Opciones Apaisado y escalado
            const opt = {
                margin:       10,
                filename:     'Expediente_<?= htmlspecialchars($aprendiz['cedula']) ?>.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2, useCORS: true, windowWidth: element.scrollWidth }, // Captura al ancho real
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'landscape' } // Horizontal 'apaisado'
            };

            // 4. Generar y restaurar
            html2pdf().set(opt).from(element).save().then(() => {
                restaurarEstilos(header, originalDark);
                ToastSystem.success('Exportación Exitosa', 'El archivo PDF ha sido descargado en formato horizontal.');
            }).catch(err => {
                restaurarEstilos(header, originalDark);
                ToastSystem.error('Error', 'Hubo un problema generando el PDF.');
                console.error(err);
            });
        }
        
        function restaurarEstilos(header, wasDark) {
            header.classList.add('hidden');
            const styleNode = document.getElementById('pdf-print-styles');
            if (styleNode) styleNode.remove();
            if (wasDark) document.documentElement.classList.add('dark');
        }

        function exportarExcel() {
            ToastSystem.info('Generando Excel', 'Extrayendo datos de la tabla...');
            
            // Clonar la tabla para limpiarla antes de exportar
            const table = document.getElementById('tabla-exportar').cloneNode(true);
            
            try {
                // xlsx parsea la tabla automáticamente
                const wb = XLSX.utils.table_to_book(table, {sheet: "Expediente"});
                XLSX.writeFile(wb, 'Expediente_<?= htmlspecialchars($aprendiz['cedula']) ?>.xlsx');
                ToastSystem.success('Exportación Exitosa', 'El archivo Excel ha sido descargado.');
            } catch (err) {
                ToastSystem.error('Error', 'Hubo un problema generando el archivo Excel.');
            }
        }
        </script>
    <?php endif; ?>

<?php else:
// ==========================================
// --- VISTA 2: MENÚ DE REPORTES PRINCIPAL ---
// ==========================================
?>
    <div class="mb-8 animate-fade-in-up">
        <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white tracking-tight">Reportes y Exportaciones</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Generación de informes de gestión.</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Card Export 1: CSV -->
        <div class="card-modern p-6 flex flex-col items-center text-center group stagger-item" data-animate>
            <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-sena to-emerald-400 text-white text-2xl flex items-center justify-center mb-4 shadow-lg shadow-sena/20 group-hover:scale-110 group-hover:shadow-xl group-hover:shadow-sena/30 transition-all duration-300">
                <i class="fa-solid fa-file-excel"></i>
            </div>
            <h3 class="font-bold text-gray-800 dark:text-gray-200 text-lg">Listado de Aprendices</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-2 mb-5 leading-relaxed">Exportar todos los aprendices activos en formato CSV o Excel.</p>
            <button onclick="ToastSystem.info('Próximamente', 'La exportación CSV requiere la librería PHPOffice. Restringido por seguridad arquitectónica.')" class="mt-auto btn-secondary text-sm w-full flex items-center justify-center gap-2">
                <i class="fa-solid fa-download"></i> Exportar .CSV
            </button>
        </div>

        <!-- Card Export 2: PDF -->
        <div class="card-modern p-6 flex flex-col items-center text-center group stagger-item" data-animate>
            <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-red-500 to-rose-400 text-white text-2xl flex items-center justify-center mb-4 shadow-lg shadow-red-500/20 group-hover:scale-110 group-hover:shadow-xl group-hover:shadow-red-500/30 transition-all duration-300">
                <i class="fa-solid fa-file-pdf"></i>
            </div>
            <h3 class="font-bold text-gray-800 dark:text-gray-200 text-lg">Estado de un Curso</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-2 mb-5 leading-relaxed">Exportar sábanas de notas completas de una ficha.</p>
            <button onclick="ToastSystem.info('Próximamente', 'La exportación PDF requiere la instalación de FPDF.')" class="mt-auto btn-secondary text-sm w-full flex items-center justify-center gap-2">
                <i class="fa-solid fa-download"></i> Exportar .PDF
            </button>
        </div>

        <!-- Card Export 3: Expediente -->
        <div class="card-modern p-6 flex flex-col items-center text-center group ring-2 ring-indigo-500/20 dark:ring-indigo-500/30 stagger-item card-glow-indigo relative" data-animate>
            <div class="absolute top-3 right-3">
                <span class="flex h-3 w-3">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-indigo-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-3 w-3 bg-indigo-500"></span>
                </span>
            </div>
            <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-indigo-500 to-purple-500 text-white text-2xl flex items-center justify-center mb-4 shadow-lg shadow-indigo-500/20 group-hover:scale-110 group-hover:shadow-xl group-hover:shadow-indigo-500/30 transition-all duration-300">
                <i class="fa-solid fa-id-card-clip"></i>
            </div>
            <h3 class="font-bold text-gray-800 dark:text-gray-200 text-lg">Expediente Individual</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-2 mb-5 leading-relaxed">Rastreo de situación curricular y morosidad por Cédula.</p>
            <a href="?view=reportes&action=historial" class="mt-auto btn-gradient-indigo btn-ripple text-sm w-full flex items-center justify-center gap-2">
                <i class="fa-solid fa-satellite-dish"></i> Acceder al Motor
            </a>
        </div>
    </div>

    <!-- Scroll animations init for stagger items -->
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1 });

        document.querySelectorAll('[data-animate]').forEach(el => observer.observe(el));
    });
    </script>
<?php endif; ?>
