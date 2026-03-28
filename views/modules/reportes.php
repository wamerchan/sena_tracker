<!-- views/modules/reportes.php -->
<?php
$db = Database::connect();
$action = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_STRING) ?? '';

if ($action === 'export_xls_aprendices') {
    $codigo_curso = filter_input(INPUT_GET, 'codigo_curso', FILTER_SANITIZE_STRING) ?? '';
    if(empty($codigo_curso)) die("Error: Ficha o Código de curso no suministrado.");

    // Obtenemos todos los aprendices filtrados por la ficha exacta armada
    $stmtXLS = $db->prepare("SELECT * FROM aprendices WHERE codigo_curso = ? ORDER BY apellidos, nombres");
    $stmtXLS->execute([$codigo_curso]);
    $aprendices_xls = $stmtXLS->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Generando XLS Ficha <?= htmlspecialchars($codigo_curso) ?></title>
        <script src="https://cdn.sheetjs.com/xlsx-0.20.0/package/dist/xlsx.full.min.js"></script>
        <style>
            body { font-family: sans-serif; background: #333; display: flex; justify-content: center; padding-top: 50px; color: white; }
            #loader { background: rgba(0,0,0,0.8); padding: 20px 40px; border-radius: 12px; font-weight: bold; border-left: 5px solid #10b981; }
            /* Ocultamos la tabla en pantalla */
            table { display: none; }
        </style>
    </head>
    <body>
        <div id="loader">Construyendo Matriz Excel (.xlsx) de la Ficha <?= htmlspecialchars($codigo_curso) ?>...</div>
        
        <table id="tabla-xls">
            <thead>
                <tr>
                    <th>ID BD</th>
                    <th>Documento</th>
                    <th>Apellidos</th>
                    <th>Nombres</th>
                    <th>Correo</th>
                    <th>Ficha (Curso)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($aprendices_xls as $a): ?>
                <tr>
                    <td><?= htmlspecialchars($a['id'] ?? '') ?></td>
                    <td><?= htmlspecialchars($a['cedula'] ?? '') ?></td>
                    <td><?= htmlspecialchars($a['apellidos'] ?? '') ?></td>
                    <td><?= htmlspecialchars($a['nombres'] ?? '') ?></td>
                    <td><?= htmlspecialchars($a['correo'] ?? '') ?></td>
                    <td><?= htmlspecialchars($a['codigo_curso'] ?? '') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <script>
            window.onload = () => {
                try {
                    const table = document.getElementById('tabla-xls');
                    const wb = XLSX.utils.table_to_book(table, {sheet: "Ficha <?= htmlspecialchars($codigo_curso) ?>"});
                    XLSX.writeFile(wb, 'Aprendices_Ficha_<?= htmlspecialchars($codigo_curso) ?>.xlsx');
                    
                    const loader = document.getElementById('loader');
                    loader.innerText = "¡Excel generado exitosamente! Puede cerrar esta pestaña.";
                    loader.style.borderLeftColor = "#059669";
                    
                    setTimeout(() => { try { window.close(); } catch(e){} }, 3000);
                } catch(e) {
                    const loader = document.getElementById('loader');
                    loader.innerText = "Error: " + e.message;
                    loader.style.borderLeftColor = "#e11d48";
                }
            };
        </script>
    </body>
    </html>
    <?php
    exit;
}

if ($action === 'export_curso_pdf') {
    $codigo_curso = filter_input(INPUT_GET, 'codigo_curso', FILTER_SANITIZE_STRING) ?? '';
    if(empty($codigo_curso)) die("Error: Ficha o Código de curso no suministrado.");
    
    $stmtA = $db->prepare("SELECT id, cedula, nombres, apellidos FROM aprendices WHERE codigo_curso = ? ORDER BY apellidos");
    $stmtA->execute([$codigo_curso]);
    $alumnos = $stmtA->fetchAll(PDO::FETCH_ASSOC);
    
    $stmtE = $db->prepare("SELECT id, codigo_evidencia, fase FROM evidencias WHERE codigo_curso = ? ORDER BY fase, fecha_entrega");
    $stmtE->execute([$codigo_curso]);
    $evidencias = $stmtE->fetchAll(PDO::FETCH_ASSOC);
    
    $stmtC = $db->prepare("SELECT id_aprendiz, id_evidencia, estado_calificacion FROM calificaciones c JOIN evidencias e ON e.id = c.id_evidencia WHERE e.codigo_curso = ?");
    $stmtC->execute([$codigo_curso]);
    $calificaciones_raw = $stmtC->fetchAll(PDO::FETCH_ASSOC);
    
    $notas = [];
    foreach($calificaciones_raw as $c) {
        $notas[$c['id_aprendiz']][$c['id_evidencia']] = $c['estado_calificacion'];
    }
    
    // Render UI estéril para impresión e invocamos script auto-descarga
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Sábana Ficha <?= htmlspecialchars($codigo_curso) ?></title>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
        <style>
            body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background: #555; display: flex; justify-content: center; padding: 20px; }
            .hoja { background: white; padding: 10mm; width: 297mm; min-height: 210mm; box-shadow: 0 0 10px rgba(0,0,0,0.5); }
            h2 { text-align: center; color: #333; margin-bottom: 5px; text-transform: uppercase; font-size: 18px;}
            p.sub { text-align: center; color: #666; font-size: 12px; margin-top: 0; margin-bottom: 20px;}
            table { width: 100%; border-collapse: collapse; font-size: 9px; }
            th, td { border: 1px solid #ccc; padding: 4px; text-align: center; }
            th { background: #f3f4f6; color: #333; font-weight: bold; }
            .name-cell { text-align: left; font-size: 10px; }
            .aprobada { color: #166534; font-weight: bold; background: #dcfce7 !important; }
            .devuelta { color: #991b1b; font-weight: bold; background: #fee2e2 !important; }
            .pendiente { color: #9ca3af; }
            
            #loader { position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: rgba(0,0,0,0.8); color: white; padding: 20px 30px; border-radius: 10px; font-weight: bold; font-family: sans-serif; z-index: 100; box-shadow: 0 10px 25px rgba(0,0,0,0.5);}
        </style>
    </head>
    <body>
        <div id="loader">Generando PDF Oficial y Procesando Vectores... Por favor espere.</div>
        <div class="hoja" id="pdf-content">
            <h2>SÁBANA OFICIAL DE CALIFICACIONES</h2>
            <p class="sub">Programa ADSO - Ficha / Curso: <strong><?= htmlspecialchars($codigo_curso) ?></strong></p>
            
            <table>
                <thead>
                    <tr>
                        <th style="width: 25%;">APRENDIZ</th>
                        <?php foreach($evidencias as $e): ?>
                            <th title="<?= htmlspecialchars($e['fase']) ?>"><?= htmlspecialchars($e['codigo_evidencia']) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($alumnos)): ?>
                        <tr><td colspan="100%">No hay matriculados en esta ficha.</td></tr>
                    <?php endif; ?>
                    <?php foreach($alumnos as $a): ?>
                    <tr>
                        <td class="name-cell">
                            <strong><?= htmlspecialchars($a['apellidos'] . ', ' . $a['nombres']) ?></strong><br>
                            <span style="color: #666;">CC: <?= htmlspecialchars($a['cedula']) ?></span>
                        </td>
                        <?php foreach($evidencias as $e): 
                            $estado = $notas[$a['id']][$e['id']] ?? 'Pendiente';
                            $class = $estado === 'Aprobada' ? 'aprobada' : ($estado === 'Devuelta' ? 'devuelta' : 'pendiente');
                            $letra = $estado === 'Aprobada' ? 'A' : ($estado === 'Devuelta' ? 'D' : '-');
                        ?>
                            <td class="<?= $class ?>"><?= $letra ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <script>
            window.onload = () => {
                const element = document.getElementById('pdf-content');
                const opt = {
                    margin:       5,
                    filename:     'Sabana_Ficha_<?= htmlspecialchars($codigo_curso) ?>.pdf',
                    image:        { type: 'jpeg', quality: 1 },
                    html2canvas:  { scale: 2, useCORS: true },
                    jsPDF:        { unit: 'mm', format: 'a4', orientation: 'landscape' }
                };
                
                html2pdf().set(opt).from(element).save().then(() => {
                    document.getElementById('loader').innerText = "¡Descarga Completa! Puede cerrar esta pestaña.";
                    document.getElementById('loader').style.background = "#166534";
                    // Opcionalmente intentar cerrarla:
                    setTimeout(() => { try { window.close(); } catch(e){} }, 3000);
                }).catch(e => {
                    document.getElementById('loader').innerText = "Error: " + e.message;
                    document.getElementById('loader').style.background = "#991b1b";
                });
            };
        </script>
    </body>
    </html>
    <?php
    exit;
}

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
        </div>
        
        <!-- Plantilla PDF Oculta y Estéril -->
        <div style="position: absolute; left: -9999px; top: -9999px; width: 1100px; background: #ffffff;" id="pdf-container">
            <div id="pdf-content" style="padding: 40px; font-family: Arial, Helvetica, sans-serif; color: #000; background: #ffffff; width: 100%; box-sizing: border-box;">
                <table style="width: 100%; border-bottom: 2px solid #000; margin-bottom: 20px;">
                    <tr>
                        <td style="vertical-align: bottom;">
                            <h1 style="font-size: 26px; font-weight: bold; margin: 0; text-transform: uppercase; color: #000;">Expediente Académico</h1>
                        </td>
                        <td style="text-align: right; vertical-align: bottom;">
                            <span style="font-size: 16px; font-weight: bold; color: #000;">Documento Oficial SENA</span>
                        </td>
                    </tr>
                </table>

                <table style="width: 100%; margin-bottom: 25px; font-size: 14px; text-transform: uppercase;">
                    <tr>
                        <td style="padding: 5px 0;"><strong>Aprendiz:</strong> <?= htmlspecialchars($aprendiz['apellidos'] . ', ' . $aprendiz['nombres']) ?></td>
                        <td style="padding: 5px 0;"><strong>ID:</strong> <?= htmlspecialchars($aprendiz['cedula']) ?></td>
                        <td style="padding: 5px 0;"><strong>Ficha:</strong> <?= htmlspecialchars($aprendiz['codigo_curso']) ?></td>
                    </tr>
                </table>

                <table style="width: 100%; border-collapse: collapse; font-size: 12px; margin-bottom: 30px;">
                    <thead>
                        <tr>
                            <th style="border: 1px solid #000; padding: 10px; text-align: left; background: #f3f4f6; color: #000; font-weight: bold;">CÓDIGO EVIDENCIA</th>
                            <th style="border: 1px solid #000; padding: 10px; text-align: left; background: #f3f4f6; color: #000; font-weight: bold;">ETAPA / GUÍA</th>
                            <th style="border: 1px solid #000; padding: 10px; text-align: center; background: #f3f4f6; color: #000; font-weight: bold;">CIERRE LÍMITE</th>
                            <th style="border: 1px solid #000; padding: 10px; text-align: center; background: #f3f4f6; color: #000; font-weight: bold;">ESTATUS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($historial)): ?>
                            <tr><td colspan="4" style="border: 1px solid #000; padding: 15px; text-align: center;">No hay data curricular.</td></tr>
                        <?php else: foreach($historial as $h): ?>
                            <tr>
                                <td style="border: 1px solid #000; padding: 10px; font-weight: bold; color: #000;"><?= htmlspecialchars($h['codigo_evidencia']) ?></td>
                                <td style="border: 1px solid #000; padding: 10px; color: #000;"><?= htmlspecialchars($h['fase']) ?> - Guía <?= htmlspecialchars($h['guia_aprendizaje']) ?></td>
                                <td style="border: 1px solid #000; padding: 10px; text-align: center; color: #000;"><?= (new DateTime($h['fecha_entrega']))->format('d/m/Y H:i') ?></td>
                                <td style="border: 1px solid #000; padding: 10px; text-align: center; font-weight: bold; color: <?= $h['nota'] === 'Aprobada' ? '#166534' : ($h['nota'] === 'Devuelta' ? '#92400e' : '#b91c1c') ?>;"><?= htmlspecialchars($h['nota']) ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>

                <?php if($total > 0): ?>
                <table style="width: 100%;">
                    <tr>
                        <td style="text-align: right; font-size: 16px;">
                            <strong>PROGRESO DEL PENSUM APROBADO:</strong> <?= $porcentaje ?>%
                        </td>
                    </tr>
                </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Librerías de Exportación JS -->
        <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
        <script src="https://cdn.sheetjs.com/xlsx-0.20.0/package/dist/xlsx.full.min.js"></script>
        
        <script>
        function exportarPDF() {
            ToastSystem.info('Generando PDF', 'Preparando el documento oficial de registro...');
            
            // 1. Apuntar directamente a la plantilla oculta y pura
            const element = document.getElementById('pdf-content');
            
            // 2. Opciones PDF Limpias
            const opt = {
                margin:       10,
                filename:     'Expediente_<?= htmlspecialchars($aprendiz['cedula']) ?>.pdf',
                image:        { type: 'jpeg', quality: 1 },
                html2canvas:  { scale: 2, useCORS: true, letterRendering: true, backgroundColor: '#ffffff' }, 
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'landscape' }
            };

            // 3. Captura directa
            html2pdf().set(opt).from(element).save().then(() => {
                ToastSystem.success('Exportación Exitosa', 'El archivo PDF ha sido descargado en formato horizontal.');
            }).catch(err => {
                ToastSystem.error('Error', 'Hubo un problema generando el PDF.');
                console.error(err);
            });
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

    <?php
    // Obtener fichas para el selector de PDFs
    $fichas_disponibles = $db->query("SELECT DISTINCT codigo_curso FROM aprendices ORDER BY codigo_curso")->fetchAll(PDO::FETCH_COLUMN);
    ?>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Card Export 1: CSV -->
        <div class="card-modern p-6 flex flex-col items-center text-center group stagger-item" data-animate>
            <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-sena to-emerald-400 text-white text-2xl flex items-center justify-center mb-4 shadow-lg shadow-sena/20 group-hover:scale-110 group-hover:shadow-xl group-hover:shadow-sena/30 transition-all duration-300">
                <i class="fa-solid fa-file-excel"></i>
            </div>
            <h3 class="font-bold text-gray-800 dark:text-gray-200 text-lg">Listado de Aprendices</h3>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 mb-3 leading-relaxed">Generar Listado por Ficha en formato Excel (.xlsx).</p>
            
            <form action="index.php" method="GET" target="_blank" class="w-full mt-auto flex flex-col gap-2">
                <input type="hidden" name="view" value="reportes">
                <input type="hidden" name="action" value="export_xls_aprendices">
                <select name="codigo_curso" required class="form-input !py-2 !text-xs font-bold text-center border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 rounded-lg shadow-inner">
                    <option value="">-- Seleccionar Ficha --</option>
                    <?php foreach($fichas_disponibles as $ficha): ?>
                        <option value="<?= htmlspecialchars($ficha) ?>"><?= htmlspecialchars($ficha) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn-secondary text-sm w-full flex items-center justify-center gap-2 py-2.5 rounded-lg border border-gray-200 dark:border-gray-600 hover:border-sena/50 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 transition-all">
                    <i class="fa-solid fa-file-excel text-emerald-600 dark:text-emerald-400"></i> Descargar Excel
                </button>
            </form>
        </div>

        <!-- Card Export 2: PDF Sabana -->
        <div class="card-modern p-6 flex flex-col items-center text-center group stagger-item" data-animate>
            <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-red-500 to-rose-400 text-white text-2xl flex items-center justify-center mb-4 shadow-lg shadow-red-500/20 group-hover:scale-110 group-hover:shadow-xl group-hover:shadow-red-500/30 transition-all duration-300">
                <i class="fa-solid fa-file-pdf"></i>
            </div>
            <h3 class="font-bold text-gray-800 dark:text-gray-200 text-lg">Estado de la Ficha</h3>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 mb-3 leading-relaxed">Generar Sábana de Notas en PDF.</p>
            
            <form action="index.php" method="GET" target="_blank" class="w-full mt-auto flex flex-col gap-2">
                <input type="hidden" name="view" value="reportes">
                <input type="hidden" name="action" value="export_curso_pdf">
                <select name="codigo_curso" required class="form-input !py-2 !text-xs font-bold text-center border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 rounded-lg shadow-inner">
                    <option value="">-- Seleccionar Ficha --</option>
                    <?php foreach($fichas_disponibles as $ficha): ?>
                        <option value="<?= htmlspecialchars($ficha) ?>"><?= htmlspecialchars($ficha) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn-secondary text-sm w-full flex items-center justify-center gap-2 py-2.5 rounded-lg border border-gray-200 dark:border-gray-600 hover:border-red-500/50 hover:bg-red-50 dark:hover:bg-red-900/20 transition-all">
                    <i class="fa-solid fa-print text-red-500"></i> Renderizar Sábana
                </button>
            </form>
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
