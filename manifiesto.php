<?php
// ══════════════════════════════════════════════
// DIAGNÓSTICO TEMPORAL — borrar cuando funcione
// ══════════════════════════════════════════════
if (isset($_GET['debug'])) {
    session_start();
    require __DIR__ . "/config.php";
    echo "<pre style='background:#111;color:#0f0;padding:20px;font-size:13px;'>";
    echo "=== SESSION ===\n";
    echo "usuario: " . ($_SESSION['usuario'] ?? '❌ NO EXISTE') . "\n";
    echo "\n=== POST (al guardar) ===\n";
    print_r($_POST);
    echo "\n=== GET ===\n";
    print_r($_GET);
    echo "\n=== DB TEST ===\n";
    $t = $mysqli->query("SELECT COUNT(*) as total FROM cabecera_viaje");
    $r = $t->fetch_assoc();
    echo "Registros en cabecera_viaje: " . $r['total'] . "\n";
    echo "\n=== ÚLTIMO ERROR MYSQL ===\n";
    echo $mysqli->error ?: 'ninguno';
    echo "</pre>";
    exit();
}
// ══════════════════════════════════════════════

session_start();
// TEMPORAL: comentamos el redirect para que no bloquee el guardado
if (!isset($_SESSION['usuario'])) {
    // Si hay POST (intentando guardar), mostramos error en vez de redirigir
    if (isset($_POST['btn_guardar'])) {
        die('<div style="background:red;color:white;padding:20px;font-size:16px;">
            ❌ ERROR: Sesión expirada al intentar guardar.<br>
            Por favor <a href="index.php" style="color:yellow;">inicia sesión</a> nuevamente.
        </div>');
    }
    header("Location: index.php");
    exit();
}

require __DIR__ . "/config.php";

// ─── PARÁMETROS ───────────────────────────────────────────────────
$bus_seleccionado = trim($_GET['bus'] ?? '');
$fecha_filtro     = $_GET['fecha'] ?? date('Y-m-d');
$tipo_lista       = $_GET['tipo'] ?? 'bajada';
$mensaje_exito    = false;

// Validar fecha
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_filtro)) {
    $fecha_filtro = date('Y-m-d');
}

// ✅ Whitelist para tabla_origen
$tipos_validos = ['subida' => 'lista_subida', 'bajada' => 'lista_bajada'];
$tabla_origen  = $tipos_validos[$tipo_lista] ?? 'lista_bajada';

// ─── CONFIGURACIÓN DE EMPRESAS ────────────────────────────────────
$empresas_config = [
    'DAJOR'    => ['razon' => 'DAJOR SUR E.I.R.L', 'ruc' => '20456187901', 'dir' => 'CAL. MORONA NRO. 310 PT. ZAMACOLA, AREQUIPA.', 'logo' => 'Logos Transporte/dajor.png'],
    'CALEB'    => ['razon' => 'MINERIA & TRANSPORTES CALEB S.R.L.', 'ruc' => '20602079857', 'dir' => 'Sector I Mza. D Lote. 4 A.V., AREQUIPA', 'logo' => 'Logos Transporte/caleb.png'],
    'TPP'      => ['razon' => 'TRANSPORTE DE PERSONAL PERUANO S.A.C.', 'ruc' => '20603986734', 'dir' => 'Av. Paseo de la Republica Nro. 656, LIMA.', 'logo' => 'Logos Transporte/tpp.png'],
    'NEW_ROAD' => ['razon' => 'SERVICIOS GENERALES R & F NEW ROAD', 'ruc' => '20604576459', 'dir' => 'MZA. C LOTE. 05 ASC. URB. CAYMA, AREQUIPA', 'logo' => 'Logos Transporte/new_road.png'],
];

$datos_auto   = $empresas_config['TPP'];
$logo_mostrar = 'assets/logo.png';
$bus_upper    = strtoupper($bus_seleccionado);

if ($bus_seleccionado) {
    if (preg_match('/DAJOR|AREQUIPA/i', $bus_upper))                                     { $datos_auto = $empresas_config['DAJOR'];    $logo_mostrar = $datos_auto['logo']; }
    elseif (preg_match('/CALEB|CUZCO|ESPINAR|JULIACA|ABANCAY/i', $bus_upper))            { $datos_auto = $empresas_config['CALEB'];    $logo_mostrar = $datos_auto['logo']; }
    elseif (preg_match('/TPP|LIMA/i', $bus_upper))                                       { $datos_auto = $empresas_config['TPP'];      $logo_mostrar = $datos_auto['logo']; }
    elseif (preg_match('/NEW|ANIZO|OYOLO|PAUSA/i', $bus_upper))                          { $datos_auto = $empresas_config['NEW_ROAD']; $logo_mostrar = $datos_auto['logo']; }
}

// ─── GUARDAR DATOS ────────────────────────────────────────────────
if (isset($_POST['btn_guardar'])) {

    $bus_f   = trim($_POST['bus_hidden'] ?? '');
    $fecha_f = trim($_POST['fecha_hidden'] ?? '');
    $tipo_f  = isset($tipos_validos[$_POST['tipo_hidden'] ?? '']) ? $_POST['tipo_hidden'] : 'bajada';

    // Función segura de limpieza
    $limpiar = fn($v) => strtoupper(trim($v ?? ''));

    $placa  = $limpiar($_POST['placa']          ?? '');
    $manif  = $limpiar($_POST['nro_manifiesto'] ?? '');
    $razon  = $limpiar($_POST['razon']          ?? '');
    $ruc    = $limpiar($_POST['ruc']            ?? '');
    $dir    = $limpiar($_POST['direccion']      ?? '');
    $hora   = trim($_POST['hora']               ?? '07:00'); // NO uppercase — es HH:MM
    $ori    = $limpiar($_POST['origen']         ?? '');
    $dest   = $limpiar($_POST['destino']        ?? '');

    $c1 = $limpiar($_POST['c1'] ?? ''); $b1 = $limpiar($_POST['b1'] ?? '');
    $c2 = $limpiar($_POST['c2'] ?? ''); $b2 = $limpiar($_POST['b2'] ?? '');
    $c3 = $limpiar($_POST['c3'] ?? ''); $b3 = $limpiar($_POST['b3'] ?? '');
    $c4 = $limpiar($_POST['c4'] ?? ''); $b4 = $limpiar($_POST['b4'] ?? '');

    $pp  = $limpiar($_POST['p_placa']  ?? ''); $pm  = $limpiar($_POST['p_modelo'] ?? '');
    $pc1 = $limpiar($_POST['pc1'] ?? ''); $pb1 = $limpiar($_POST['pb1'] ?? '');
    $pc2 = $limpiar($_POST['pc2'] ?? ''); $pb2 = $limpiar($_POST['pb2'] ?? '');
    $pc3 = $limpiar($_POST['pc3'] ?? ''); $pb3 = $limpiar($_POST['pb3'] ?? '');

    $error_guardar = '';

    // Verificar si ya existe
    $check_stmt = $mysqli->prepare(
        "SELECT id FROM cabecera_viaje WHERE nombre_bus=? AND fecha_viaje=? AND tipo_lista=?"
    );
    if (!$check_stmt) {
        $error_guardar = 'prepare_check: ' . $mysqli->error;
    } else {
        $check_stmt->bind_param("sss", $bus_f, $fecha_f, $tipo_f);
        $check_stmt->execute();
        $check_stmt->store_result();
        $existe = $check_stmt->num_rows > 0;
        $check_stmt->close();

        if ($existe) {
            // UPDATE
            $sql_stmt = $mysqli->prepare(
                "UPDATE cabecera_viaje SET
                    placa_rodaje=?, nro_manifiesto=?, razon_social_transporte=?, ruc_empresa=?,
                    direccion_empresa=?, hora_salida=?, origen=?, destino=?,
                    conductor_1=?, brevete_1=?, conductor_2=?, brevete_2=?,
                    conductor_3=?, brevete_3=?, conductor_4=?, brevete_4=?,
                    ploteo_placa=?, ploteo_modelo=?, ploteo_c1=?, ploteo_b1=?,
                    ploteo_c2=?, ploteo_b2=?, ploteo_c3=?, ploteo_b3=?
                 WHERE nombre_bus=? AND fecha_viaje=? AND tipo_lista=?"
            );
            if (!$sql_stmt) {
                $error_guardar = 'prepare_update: ' . $mysqli->error;
            } else {
                $sql_stmt->bind_param(
                    "sssssssssssssssssssssssssss",
                    $placa,$manif,$razon,$ruc,$dir,$hora,$ori,$dest,
                    $c1,$b1,$c2,$b2,$c3,$b3,$c4,$b4,
                    $pp,$pm,$pc1,$pb1,$pc2,$pb2,$pc3,$pb3,
                    $bus_f,$fecha_f,$tipo_f
                );
                if (!$sql_stmt->execute()) {
                    $error_guardar = 'execute_update: ' . $sql_stmt->error;
                }
                $sql_stmt->close();
            }
        } else {
            // INSERT
            $sql_stmt = $mysqli->prepare(
                "INSERT INTO cabecera_viaje
                    (nombre_bus, fecha_viaje, tipo_lista, placa_rodaje, nro_manifiesto,
                     razon_social_transporte, ruc_empresa, direccion_empresa, hora_salida, origen, destino,
                     conductor_1, brevete_1, conductor_2, brevete_2, conductor_3, brevete_3, conductor_4, brevete_4,
                     ploteo_placa, ploteo_modelo, ploteo_c1, ploteo_b1, ploteo_c2, ploteo_b2, ploteo_c3, ploteo_b3)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
            );
            if (!$sql_stmt) {
                $error_guardar = 'prepare_insert: ' . $mysqli->error;
            } else {
                $sql_stmt->bind_param(
                    "sssssssssssssssssssssssssss",
                    $bus_f,$fecha_f,$tipo_f,$placa,$manif,$razon,$ruc,$dir,$hora,$ori,$dest,
                    $c1,$b1,$c2,$b2,$c3,$b3,$c4,$b4,
                    $pp,$pm,$pc1,$pb1,$pc2,$pb2,$pc3,$pb3
                );
                if (!$sql_stmt->execute()) {
                    $error_guardar = 'execute_insert: ' . $sql_stmt->error;
                }
                $sql_stmt->close();
            }
        }
    }

    if ($error_guardar) {
        // Mostrar error visible en pantalla para diagnóstico
        die('<div style="background:red;color:white;padding:20px;font-size:16px;font-family:monospace;">
            <strong>ERROR AL GUARDAR:</strong><br>' . htmlspecialchars($error_guardar) . '
            <br><br><a href="javascript:history.back()" style="color:yellow;">← Volver</a>
        </div>');
    }

    header("Location: manifiesto.php?bus=".urlencode($bus_f)."&fecha=".$fecha_f."&tipo=".$tipo_f."&guardado=ok");
    exit();
}

if (isset($_GET['guardado']) && $_GET['guardado'] === 'ok') { $mensaje_exito = true; }

// ─── LEER DATOS GUARDADOS ─────────────────────────────────────────
$datos = [
    'placa' => '', 'manifiesto' => '001', 'razon' => $datos_auto['razon'],
    'ruc'   => $datos_auto['ruc'], 'dir' => $datos_auto['dir'],
    'hora'  => '07:00', 'origen' => 'MINA', 'destino' => 'LIMA',
    'c1'=>'','b1'=>'','c2'=>'','b2'=>'','c3'=>'','b3'=>'','c4'=>'','b4'=>'',
    'pp'=>'','pm'=>'','pc1'=>'','pb1'=>'','pc2'=>'','pb2'=>'','pc3'=>'','pb3'=>'',
];

if ($bus_seleccionado) {
    $q_stmt = $mysqli->prepare(
        "SELECT * FROM cabecera_viaje WHERE nombre_bus=? AND fecha_viaje=? AND tipo_lista=?"
    );
    $q_stmt->bind_param("sss", $bus_seleccionado, $fecha_filtro, $tipo_lista);
    $q_stmt->execute();
    $row = $q_stmt->get_result()->fetch_assoc();
    $q_stmt->close();

    if ($row) {
        $datos['placa']     = $row['placa_rodaje'];      $datos['manifiesto'] = $row['nro_manifiesto'];
        $datos['razon']     = $row['razon_social_transporte']; $datos['ruc']  = $row['ruc_empresa'];
        $datos['dir']       = $row['direccion_empresa']; $datos['hora']        = substr($row['hora_salida'],0,5);
        $datos['origen']    = $row['origen'];            $datos['destino']     = $row['destino'];
        $datos['c1']=$row['conductor_1']; $datos['b1']=$row['brevete_1'];
        $datos['c2']=$row['conductor_2']; $datos['b2']=$row['brevete_2'];
        $datos['c3']=$row['conductor_3']; $datos['b3']=$row['brevete_3'];
        $datos['c4']=$row['conductor_4']??''; $datos['b4']=$row['brevete_4']??'';
        $datos['pp']=$row['ploteo_placa']??'';  $datos['pm']=$row['ploteo_modelo']??'';
        $datos['pc1']=$row['ploteo_c1']??'';    $datos['pb1']=$row['ploteo_b1']??'';
        $datos['pc2']=$row['ploteo_c2']??'';    $datos['pb2']=$row['ploteo_b2']??'';
        $datos['pc3']=$row['ploteo_c3']??'';    $datos['pb3']=$row['ploteo_b3']??'';
    } else {
        $placa_stmt = $mysqli->prepare("SELECT placa FROM {$tabla_origen} WHERE bus=? LIMIT 1");
        $placa_stmt->bind_param("s", $bus_seleccionado);
        $placa_stmt->execute();
        $placa_stmt->bind_result($p_placa);
        if ($placa_stmt->fetch()) $datos['placa'] = $p_placa;
        $placa_stmt->close();

        // ─── AUTO-CARGA DESDE TABLA conductores ───────────────────────────
        // Solo si existe la tabla; si no, falla silenciosamente (no rompe nada)
        $chk = $mysqli->query("SHOW TABLES LIKE 'conductores'");
        if ($chk && $chk->num_rows > 0) {
            // 1) Conductores principales del bus (máx 4, ordenados por id)
            $cond_stmt = $mysqli->prepare(
                "SELECT nombre, licencia FROM conductores
                  WHERE bus = ? AND (es_ploteo = 0 OR es_ploteo IS NULL)
                  ORDER BY id ASC LIMIT 4"
            );
            if ($cond_stmt) {
                $cond_stmt->bind_param("s", $bus_seleccionado);
                $cond_stmt->execute();
                $cond_res = $cond_stmt->get_result();
                $ci = 1;
                while ($cr = $cond_res->fetch_assoc()) {
                    $datos["c$ci"] = $cr['nombre'];
                    $datos["b$ci"] = $cr['licencia'];
                    $ci++;
                }
                $cond_stmt->close();
            }

            // 2) Datos de ploteo asignados a este bus (placa, modelo, 3 conductores)
            $plot_stmt = $mysqli->prepare(
                "SELECT placa, marca,
                        placa_ploteo, marca_ploteo,
                        pc1, pb1, pc2, pb2, pc3, pb3
                   FROM conductores
                  WHERE bus = ? LIMIT 1"
            );
            if ($plot_stmt) {
                $plot_stmt->bind_param("s", $bus_seleccionado);
                $plot_stmt->execute();
                $pr = $plot_stmt->get_result()->fetch_assoc();
                $plot_stmt->close();
                if ($pr) {
                    // Placa del bus si aún está vacía
                    if (empty($datos['placa'])) $datos['placa'] = $pr['placa'] ?? '';
                    // Datos de ploteo
                    $datos['pp']  = $pr['placa_ploteo'] ?? '';
                    $datos['pm']  = $pr['marca_ploteo']  ?? '';
                    $datos['pc1'] = $pr['pc1'] ?? '';  $datos['pb1'] = $pr['pb1'] ?? '';
                    $datos['pc2'] = $pr['pc2'] ?? '';  $datos['pb2'] = $pr['pb2'] ?? '';
                    $datos['pc3'] = $pr['pc3'] ?? '';  $datos['pb3'] = $pr['pb3'] ?? '';
                }
            }
        }
        // ─────────────────────────────────────────────────────────────────
    }
}

// ─── PASAJEROS ────────────────────────────────────────────────────
$pasajeros_map = []; $total_a_bordo = 0;
if ($bus_seleccionado) {
    $p_stmt = $mysqli->prepare(
        "SELECT l.asiento, l.dni, p.apellidos, p.nombres, p.empresa, p.cargo
         FROM {$tabla_origen} l
         LEFT JOIN personal p ON l.dni = p.dni
         WHERE l.bus = ?
         ORDER BY CAST(l.asiento AS UNSIGNED) ASC"
    );
    $p_stmt->bind_param("s", $bus_seleccionado);
    $p_stmt->execute();
    $res = $p_stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $num = (int)$r['asiento'];
        $pasajeros_map[$num] = $r;
        if (!empty($r['dni'])) $total_a_bordo++;
    }
    $p_stmt->close();
}

// Unificado a 30 asientos
$TOTAL_ASIENTOS = 30;

$es_ploteo = preg_match('/DAJOR|AREQUIPA|CUZCO|ESPINAR|CUSCO|ABANCAY|JULIACA|SUMAYRO|TPP|LIMA|ANIZO|CALACCAPCHA|OYOLO|PACAPAUSA|PAUSA/i', $bus_seleccionado);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Manifiesto — <?= h($bus_seleccionado) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="icon" type="image/png" href="../assets/logo4.png"/>

    <style>
        :root { --gold: #b8872b; --dark: #1a1c1e; --radius: 10px; }

        body { font-family: 'Segoe UI', Arial, sans-serif; background: #eef1f5; margin: 0; padding: 10px; padding-bottom: 90px; }

        /* ── BARRA FLOTANTE MEJORADA ── */
        .barra-control {
            position: fixed; bottom: 0; left: 0; width: 100%;
            background: white; padding: 12px 16px;
            box-shadow: 0 -4px 20px rgba(0,0,0,0.12);
            z-index: 1000; border-top: 3px solid var(--gold); box-sizing: border-box;
        }
        .acciones { display: flex; width: 100%; gap: 8px; justify-content: space-between; }
        .btn {
            flex: 1; padding: 13px 5px; border: none; border-radius: 10px;
            color: white; cursor: pointer; font-weight: 700; text-decoration: none;
            font-size: 11px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 3px;
            transition: transform 0.1s, opacity 0.2s;
        }
        .btn:active { transform: scale(0.97); }
        .btn i { font-size: 17px; }
        .btn-gold  { background: var(--gold); }
        .btn-dark  { background: #2c3e50; }
        .btn-back  { background: #7f8c8d; }
        .btn-excel { background: #217346; }

        /* Botón guardar con feedback */
        .btn-gold.saved { background: #10b981; }

        /* ── INFO HEADER ── */
        .header-info {
            background: #fff; padding: 12px 16px; border-radius: 12px; margin-bottom: 15px;
            display: flex; align-items: center; justify-content: space-between;
            font-size: 13px; font-weight: 700; color: #333;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05); border-left: 4px solid var(--gold);
        }
        .header-info .tipo-badge {
            background: var(--gold); color: white;
            padding: 4px 12px; border-radius: 20px; font-size: 10px; font-weight: 800;
        }

        /* ── ALERTA ÉXITO ── */
        .alerta-exito {
            position: fixed; top: 20px; left: 50%; transform: translateX(-50%);
            background: #10b981; color: white; padding: 12px 24px; border-radius: 50px;
            box-shadow: 0 4px 20px rgba(16,185,129,0.4); font-weight: 700; z-index: 2000;
            display: flex; align-items: center; gap: 10px; animation: slideDown 0.4s ease-out;
        }
        @keyframes slideDown { from { top: -60px; opacity: 0; } to { top: 20px; opacity: 1; } }

        /* ── HOJA ── */
        .hoja {
            background: white; width: 210mm; min-height: 297mm; margin: 0 auto; padding: 10mm;
            box-sizing: border-box; box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        .tbl-print { width: 100%; border-collapse: collapse; font-size: 10px; margin-bottom: 8px; }
        .tbl-print th, .tbl-print td { border: 1px solid #000; padding: 4px; vertical-align: middle; }
        .tbl-print th { background: #e0e0e0; font-weight: bold; text-align: center; }
        .inp-print { width: 100%; border: none; font-family: inherit; font-size: inherit; background: transparent; outline: none; }
        .text-center { text-align: center; }
        .logo-box { width: 140px; height: 60px; display: flex; align-items: center; justify-content: center; }
        .logo-box img { max-width: 100%; max-height: 100%; object-fit: contain; }

        @media only screen and (max-width: 768px) {
            .hoja { width: 100%; max-width: 100%; min-height: auto; padding: 5px; box-shadow: none; }
            .tbl-print { font-size: 11px; }
            .inp-print { font-size: 11px; font-weight: 500; }
        }

        @media print {
            .no-print { display: none !important; }
            .hoja { box-shadow: none; margin: 0; width: 100%; padding: 0; }
            body { padding: 0; margin: 0; background: white; }
        }
    </style>
</head>
<body>

<?php if ($mensaje_exito): ?>
<div class="alerta-exito" id="alertaExito">
    <i class="fas fa-check-circle"></i> ¡Manifiesto guardado correctamente!
</div>
<script>setTimeout(function(){document.getElementById('alertaExito').style.display='none';},3500);</script>
<?php endif; ?>

<!-- HEADER INFO (solo en pantalla) -->
<div class="header-info no-print">
    <div>
        <i class="fas fa-bus" style="color:var(--gold)"></i>
        <strong> <?= h($bus_seleccionado ?: 'Sin unidad') ?></strong><br>
        <small style="color:#666"><?= date('d/m/Y', strtotime($fecha_filtro)) ?></small>
    </div>
    <?php if (!empty($datos['placa'])): ?>
    <div style="text-align:center;">
        <small style="color:#999;font-size:9px;display:block;letter-spacing:1px;">PLACA</small>
        <strong style="font-size:15px;letter-spacing:2px;color:#333;"><?= h($datos['placa']) ?></strong>
    </div>
    <?php endif; ?>
    <span class="tipo-badge"><?= strtoupper(h($tipo_lista)) ?></span>
</div>

<!-- BARRA FLOTANTE -->
<div class="barra-control no-print">
    <div class="acciones">
        <a href="index_manifiesto.php" class="btn btn-back">
            <i class="fas fa-undo"></i><span>VOLVER</span>
        </a>
        <?php if ($bus_seleccionado): ?>
            <a href="exportar_excel.php?bus=<?= urlencode($bus_seleccionado) ?>&fecha=<?= h($fecha_filtro) ?>&tipo=<?= h($tipo_lista) ?>" class="btn btn-excel">
                <i class="fas fa-file-excel"></i><span>EXCEL</span>
            </a>
            <button onclick="guardarManifiesto()" class="btn btn-gold" id="btnGuardar">
                <i class="fas fa-save" id="iconGuardar"></i><span id="textoGuardar">GUARDAR</span>
            </button>
            <button onclick="window.print()" class="btn btn-dark">
                <i class="fas fa-print"></i><span>IMPRIMIR</span>
            </button>
        <?php endif; ?>
    </div>
</div>

<?php if ($bus_seleccionado): ?>
<form id="form_data" method="POST">
    <input type="hidden" name="bus_hidden"   value="<?= h($bus_seleccionado) ?>">
    <input type="hidden" name="fecha_hidden" value="<?= h($fecha_filtro) ?>">
    <input type="hidden" name="tipo_hidden"  value="<?= h($tipo_lista) ?>">
    <input type="hidden" name="btn_guardar"  value="1">

    <div class="hoja">

        <!-- CABECERA -->
        <table style="width:100%; border:none; margin-bottom:10px;">
            <tr>
                <td style="width:20%; border:none;">
                    <div class="logo-box">
                        <img src="<?= h($logo_mostrar) ?>" alt="LOGO" onerror="this.src='assets/logo.png'">
                    </div>
                </td>
                <td style="width:60%; border:none; text-align:center;">
                    <h2 style="margin:0; font-size:16px; text-decoration:underline;">MANIFIESTO DE PASAJEROS</h2>
                    <input type="text" name="razon" class="inp-print text-center" style="font-weight:bold;" value="<?= h($datos['razon']) ?>">
                </td>
                <td style="width:20%; border:none; text-align:right;">
                    <span style="color:red; font-weight:bold; font-size:14px;">N° </span>
                    <input type="text" name="nro_manifiesto" value="<?= h($datos['manifiesto']) ?>" style="width:50px; color:red; font-weight:bold; border:none; text-align:right; font-size:14px;">
                </td>
            </tr>
        </table>

        <!-- DATOS GENERALES -->
        <table class="tbl-print">
            <tr>
                <th width="15%">RUC:</th><td width="35%"><input type="text" name="ruc"      class="inp-print" value="<?= h($datos['ruc']) ?>"></td>
                <th width="15%">DIRECCIÓN:</th><td width="35%"><input type="text" name="direccion" class="inp-print" value="<?= h($datos['dir']) ?>"></td>
            </tr>
            <tr>
                <th>ORIGEN:</th><td><input type="text" name="origen"  class="inp-print" value="<?= h($datos['origen']) ?>"></td>
                <th>DESTINO:</th><td><input type="text" name="destino" class="inp-print" value="<?= h($datos['destino']) ?>"></td>
            </tr>
            <tr>
                <th>FECHA:</th><td><?= date('d/m/Y', strtotime($fecha_filtro)) ?></td>
                <th>HORA SALIDA:</th><td><input type="time" name="hora" class="inp-print" value="<?= h($datos['hora']) ?>"></td>
            </tr>
            <tr>
                <th>UNIDAD:</th><td style="font-weight:bold;"><?= h($bus_seleccionado) ?></td>
                <th>PLACA:</th><td><input type="text" name="placa" class="inp-print" style="font-weight:bold;" value="<?= h($datos['placa']) ?>"></td>
            </tr>
        </table>

        <!-- CONDUCTORES -->
        <table class="tbl-print">
            <tr><th colspan="2">CONDUCTORES</th><th>LICENCIA</th></tr>
            <?php for ($c = 1; $c <= 4; $c++): ?>
            <tr>
                <td width="15%"><strong>CONDUCTOR <?= $c ?>:</strong></td>
                <td><input type="text" name="c<?=$c?>" class="inp-print" value="<?= h($datos["c$c"]) ?>"></td>
                <td width="20%"><input type="text" name="b<?=$c?>" class="inp-print text-center" value="<?= h($datos["b$c"]) ?>"></td>
            </tr>
            <?php endfor; ?>
        </table>

        <!-- LISTA DE PASAJEROS (30 asientos) -->
        <table class="tbl-print">
            <thead>
                <tr>
                    <th width="5%">N°</th>
                    <th>APELLIDOS Y NOMBRES</th>
                    <th width="10%">DNI</th>
                    <th width="25%">EMPRESA</th>
                    <th width="20%">CARGO</th>
                </tr>
            </thead>
            <tbody id="tabla_en_vivo">
                <?php for ($i = 1; $i <= $TOTAL_ASIENTOS; $i++):
                    $p = $pasajeros_map[$i] ?? null; ?>
                <tr>
                    <td class="text-center"><strong><?= $i ?></strong></td>
                    <td style="padding-left:5px;"><?= $p ? h($p['apellidos'].' '.$p['nombres']) : '' ?></td>
                    <td class="text-center"><?= $p ? h($p['dni']) : '' ?></td>
                    <td class="text-center"><?= $p ? h($p['empresa']) : '' ?></td>
                    <td class="text-center"><?= $p ? h($p['cargo']) : '' ?></td>
                </tr>
                <?php endfor; ?>
            </tbody>
        </table>

        <div style="text-align:right; font-weight:bold; font-size:11px; margin-top:5px; padding-right:10px;">
            TOTAL PASAJEROS A BORDO: <span id="contador_en_vivo"><?= $total_a_bordo ?></span>
        </div>

        <!-- PLOTEO (si aplica) -->
        <?php if ($es_ploteo): ?>
        <div style="margin-top:15px;">
            <table class="tbl-print" style="border:2px solid #000; width:60%;">
                <thead>
                    <tr><th colspan="3" style="background:#333!important; color:white;">DATOS DE PLOTEO - UNIDAD EXTERNA</th></tr>
                </thead>
                <tbody>
                    <tr>
                        <th width="25%">PLACA:</th>
                        <td colspan="2"><input type="text" name="p_placa"  class="inp-print" value="<?= h($datos['pp']) ?>" placeholder="---" autocomplete="off" id="inpPlaca"></td>
                    </tr>
                    <tr>
                        <th>MODELO:</th>
                        <td colspan="2"><input type="text" name="p_modelo" class="inp-print" value="<?= h($datos['pm']) ?>" placeholder="---" id="inpModelo"></td>
                    </tr>
                    <tr style="background:#f2f2f2;">
                        <th style="font-size:8px;">ROL</th><th style="font-size:8px;">CONDUCTOR</th><th style="font-size:8px;">LICENCIA</th>
                    </tr>
                    <?php for ($pc = 1; $pc <= 3; $pc++): ?>
                    <tr>
                        <td><strong>CONDUCTOR <?= $pc ?>:</strong></td>
                        <td><input type="text" name="pc<?=$pc?>" class="inp-print" value="<?= h($datos["pc$pc"]) ?>"></td>
                        <td><input type="text" name="pb<?=$pc?>" class="inp-print text-center" value="<?= h($datos["pb$pc"]) ?>"></td>
                    </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <div style="font-size:9px; text-align:center; margin-top:10px; color:#888;">
            Documento generado el <?= date('d/m/Y H:i') ?> | Sistema de Control Hochschild
        </div>
    </div>
</form>

<?php else: ?>
<div style="text-align:center; margin-top:60px; color:#64748b;">
    <i class="fas fa-bus" style="font-size:40px; opacity:0.3; display:block; margin-bottom:15px;"></i>
    <p>No se ha seleccionado ninguna unidad.</p>
    <a href="index_manifiesto.php" style="background:var(--gold); color:white; padding:12px 24px; border-radius:10px; text-decoration:none; font-weight:700;">SELECCIONAR UNIDAD</a>
</div>
<?php endif; ?>

<script>
// ── GUARDAR CON FEEDBACK VISUAL ──────────────────────────────────
function guardarManifiesto() {
    const btn  = document.getElementById('btnGuardar');
    const icon = document.getElementById('iconGuardar');
    const text = document.getElementById('textoGuardar');

    btn.classList.add('saved');
    icon.className = 'fas fa-spinner fa-spin';
    text.innerText = 'GUARDANDO...';

    setTimeout(() => { document.getElementById('form_data').submit(); }, 400);
}

// ── AUTOCOMPLETAR MODELO POR PLACA (ploteo) ────────────────────────
const vehiculosBD = {
    'V8F-900': 'TOYOTA HILUX',
    'V1A-123': 'HYUNDAI H1',
    'X2B-456': 'SPRINTER MERCEDES',
    'D4J-777': 'VAN TOYOTA'
};
const inpPlaca  = document.getElementById('inpPlaca');
const inpModelo = document.getElementById('inpModelo');
if (inpPlaca) {
    inpPlaca.addEventListener('input', function () {
        const modelo = vehiculosBD[this.value.toUpperCase()];
        if (modelo) inpModelo.value = modelo;
    });
}

// ── ACTUALIZAR TABLA EN TIEMPO REAL ──────────────────────────────
const config = {
    bus:   "<?= h($bus_seleccionado) ?>",
    tipo:  "<?= h($tipo_lista) ?>",
    fecha: "<?= h($fecha_filtro) ?>"
};

function recargarPasajeros() {
    if (!config.bus) return;
    fetch(`api_pasajeros.php?bus=${encodeURIComponent(config.bus)}&tipo=${config.tipo}&fecha=${config.fecha}`)
        .then(r => r.json())
        .then(datos => {
            const tabla    = document.getElementById('tabla_en_vivo');
            const contador = document.getElementById('contador_en_vivo');
            if (tabla)    tabla.innerHTML   = datos.tabla;
            if (contador) contador.innerText = datos.total;
        })
        .catch(() => {});
}

recargarPasajeros();
setInterval(recargarPasajeros, 3000);
</script>

</body>
</html>