<?php
/**
 * dashboard.php — Panel Principal
 * Hochschild Mining · Sistema Integral de Transporte
 * v8.0 — Fusion Premium: Executive White + Luxury Glass
 *        Diseño mobile-first · Identidad dorada Hochschild
 */

session_start();

if (!isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit();
}

require __DIR__ . "/config.php";

function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

$usuario_sesion = $_SESSION['usuario'];
$nombre_sesion  = $_SESSION['nombre'] ?? $usuario_sesion;

/* ── Datos de usuario ── */
$stmt = $mysqli->prepare("SELECT cargo_real, rol FROM usuarios WHERE nombre_usuario = ? LIMIT 1");
if ($stmt) {
    $stmt->bind_param("s", $usuario_sesion);
    $stmt->execute();
    $stmt->bind_result($cargo_real, $rol_sistema);
    if (!$stmt->fetch()) {
        $cargo_real  = $_SESSION['cargo_real'] ?? 'Colaborador';
        $rol_sistema = $_SESSION['rol']        ?? 'agente';
    }
    $stmt->close();
} else {
    $cargo_real  = $_SESSION['cargo_real'] ?? 'Colaborador';
    $rol_sistema = $_SESSION['rol']        ?? 'agente';
}

/* ── Total personal ── */
$total_personal = 0;
$stmt2 = $mysqli->prepare("SELECT COUNT(*) FROM fuerza_laboral");
if ($stmt2) {
    $stmt2->execute();
    $stmt2->bind_result($total_personal);
    $stmt2->fetch();
    $stmt2->close();
}

/* ── KPIs rápidos: avance semanal ── */
$kpi_avance   = 0;
$kpi_escaneados = 0;
$kpi_faltantes  = 0;
$kpi_rutas      = 0;

$lun_actual = date('Y-m-d', strtotime('monday this week'));
$dom_actual = date('Y-m-d', strtotime('sunday this week'));

/* Teórico (lista_subida + lista_bajada) */
$stmt_teo = $mysqli->prepare(
    "SELECT COUNT(*) FROM lista_subida WHERE bus IS NOT NULL AND bus != '' AND bus != 'N/A'"
);
if ($stmt_teo) {
    $stmt_teo->execute();
    $stmt_teo->bind_result($teo_sub);
    $stmt_teo->fetch();
    $stmt_teo->close();
} else { $teo_sub = 0; }

$stmt_teo2 = $mysqli->prepare(
    "SELECT COUNT(*) FROM lista_bajada WHERE bus IS NOT NULL AND bus != '' AND bus != 'N/A'"
);
if ($stmt_teo2) {
    $stmt_teo2->execute();
    $stmt_teo2->bind_result($teo_baj);
    $stmt_teo2->fetch();
    $stmt_teo2->close();
} else { $teo_baj = 0; }

$teo_total = $teo_sub + $teo_baj;

/* Real esta semana */
$stmt_real = $mysqli->prepare(
    "SELECT COUNT(DISTINCT dni) FROM registros
     WHERE DATE(fecha) BETWEEN ? AND ?
       AND evento IN ('SUBIDA PERMITIDA','BAJADA PERMITIDA')"
);
if ($stmt_real) {
    $stmt_real->bind_param("ss", $lun_actual, $dom_actual);
    $stmt_real->execute();
    $stmt_real->bind_result($kpi_escaneados);
    $stmt_real->fetch();
    $stmt_real->close();
}

/* Rutas activas (buses distintos esta semana) */
$stmt_rutas = $mysqli->prepare(
    "SELECT COUNT(DISTINCT UPPER(TRIM(bus))) FROM registros
     WHERE DATE(fecha) BETWEEN ? AND ?
       AND evento IN ('SUBIDA PERMITIDA','BAJADA PERMITIDA')
       AND bus IS NOT NULL AND bus != '' AND bus != 'N/A'"
);
if ($stmt_rutas) {
    $stmt_rutas->bind_param("ss", $lun_actual, $dom_actual);
    $stmt_rutas->execute();
    $stmt_rutas->bind_result($kpi_rutas);
    $stmt_rutas->fetch();
    $stmt_rutas->close();
}

if ($teo_total > 0) {
    $kpi_avance    = round(($kpi_escaneados / $teo_total) * 100);
    $kpi_faltantes = max(0, $teo_total - $kpi_escaneados);
}

/* KPI semana anterior (para delta) */
$lun_prev = date('Y-m-d', strtotime('monday last week'));
$dom_prev = date('Y-m-d', strtotime('sunday last week'));
$kpi_esc_prev = 0;
$stmt_prev = $mysqli->prepare(
    "SELECT COUNT(DISTINCT dni) FROM registros
     WHERE DATE(fecha) BETWEEN ? AND ?
       AND evento IN ('SUBIDA PERMITIDA','BAJADA PERMITIDA')"
);
if ($stmt_prev) {
    $stmt_prev->bind_param("ss", $lun_prev, $dom_prev);
    $stmt_prev->execute();
    $stmt_prev->bind_result($kpi_esc_prev);
    $stmt_prev->fetch();
    $stmt_prev->close();
}
$kpi_avance_prev  = $teo_total > 0 ? round(($kpi_esc_prev / $teo_total) * 100) : 0;
$delta_avance     = $kpi_avance - $kpi_avance_prev;
$delta_faltantes  = ($teo_total > 0 ? max(0, $teo_total - $kpi_esc_prev) : 0) - $kpi_faltantes;

/* ── Saludo por hora ── */
$hora = (int)date('H');
if ($hora < 12)     $saludo = 'Buenos días';
elseif ($hora < 19) $saludo = 'Buenas tardes';
else                $saludo = 'Buenas noches';

/* ── Iniciales del usuario ── */
$partes    = explode(' ', trim($nombre_sesion));
$iniciales = strtoupper(
    substr($partes[0], 0, 1) .
    (isset($partes[1]) ? substr($partes[1], 0, 1) : '')
);

/* ── Primer nombre para el hero ── */
$nombre_partes = explode(' ', trim($nombre_sesion));
$primer_nombre = $nombre_partes[0] ?? $nombre_sesion;
$apellido      = $nombre_partes[1] ?? '';

/* ── Roles ── */
$es_sup   = in_array($rol_sistema, ['supervisor', 'administrador']);
$es_admin = ($rol_sistema === 'administrador');

$cargo_lower    = strtolower($cargo_real ?? '');
$es_programador = str_contains($cargo_lower, 'programador') || str_contains($cargo_lower, 'desarrollador');

/* ── Semana actual ── */
$sem_label = 'Sem. ' . date('W') . ' · ' . date('d M', strtotime($lun_actual)) . ' al ' . date('d M', strtotime($dom_actual));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="theme-color" content="#F9F7F3">
    <title>Dashboard · Hochschild Mining</title>
    <link rel="icon" type="image/png" href="assets/logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">

    <style>
    /* ═══════════════════════════════════════
       TOKENS DE MARCA — Hochschild Mining
       Fusion Premium v8.0
    ═══════════════════════════════════════ */
    :root {
        /* ── Dorado Hochschild ── */
        --g:      #C49A2C;
        --gd:     #8A6A14;
        --gl:     #FBF6E8;
        --gr:     rgba(196,154,44,.15);
        --g-grad: linear-gradient(135deg,#7A5A0E,#C49A2C,#E8C85A);

        /* ── Blanco / Negro (paleta principal) ── */
        --ink:    #0A0A0A;
        --ink2:   #1A1A1A;
        --ink3:   #3A3A3A;
        --ink4:   #666666;
        --ink5:   #999999;
        --ink6:   #BBBBBB;

        /* Fondos BLANCOS puros */
        --bg:     #F4F4F4;
        --surf:   #FFFFFF;
        --surf2:  #FAFAFA;

        /* Bordes */
        --b:      rgba(0,0,0,.08);
        --b2:     rgba(0,0,0,.05);
        --b-gold: rgba(196,154,44,.3);

        /* Semánticos */
        --red:    #DC2626;
        --rb:     #FEF2F2;
        --rbo:    rgba(252,165,165,.4);
        --green:  #16A34A;
        --gb:     #F0FDF4;
        --gbo:    #86EFAC;
        --sv:     #888888;
        --svb:    #E8E8E8;

        /* Glass — blanco */
        --glass:  rgba(255,255,255,.95);
        --glass2: rgba(255,255,255,.98);
        --blur:   blur(20px);
        --blur2:  blur(12px);

        /* Tipografías */
        --font:   'Inter', system-ui, sans-serif;
        --mono:   'JetBrains Mono', monospace;

        /* Radios */
        --r-sm:   8px;
        --r-md:   12px;
        --r-lg:   16px;
        --r-xl:   20px;
        --r-2xl:  24px;

        /* Safe areas */
        --safe-t: env(safe-area-inset-top, 0px);
        --safe-b: env(safe-area-inset-bottom, 0px);
    }

    /* ── Reset ── */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html { height: 100%; scroll-behavior: smooth; -webkit-text-size-adjust: 100%; }
    body {
        font-family: var(--font);
        font-size: 14px;
        line-height: 1.5;
        background: #F0F0F0;
        color: var(--ink);
        min-height: 100svh;
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
        -webkit-tap-highlight-color: transparent;
    }
    a { text-decoration: none; color: inherit; }
    button { font-family: var(--font); border: none; cursor: pointer; background: none; }
    img { display: block; max-width: 100%; }

    /* Scrollbar */
    ::-webkit-scrollbar { width: 4px; height: 4px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: var(--svb); border-radius: 4px; }

    /* ═══════════════════════════════════════
       TOPBAR
    ═══════════════════════════════════════ */
    .topbar {
        position: sticky;
        top: 0;
        z-index: 200;
        background: rgba(255,255,255,.97);
        backdrop-filter: blur(24px);
        -webkit-backdrop-filter: blur(24px);
        border-bottom: 1px solid rgba(0,0,0,.07);
        padding-top: var(--safe-t);
        box-shadow: 0 1px 0 rgba(255,255,255,1), 0 2px 16px rgba(0,0,0,.05);
    }
    .topbar-inner {
        max-width: 480px;
        margin: 0 auto;
        padding: 0 16px;
        height: 66px;
        display: flex;
        align-items: center;
        gap: 0;
    }

    /* Marca — logo más grande */
    .logo-block {
        display: flex;
        align-items: center;
        gap: 11px;
        flex-shrink: 0;
    }
    .logo-marks {
        display: flex;
        flex-direction: column;
        gap: 3px;
    }
    .logo-marks span:first-child {
        display: block;
        width: 28px;
        height: 2.5px;
        background: linear-gradient(90deg,#0A0A0A,#C49A2C);
        border-radius: 1px;
    }
    .logo-marks span:last-child {
        display: block;
        width: 16px;
        height: 1.5px;
        background: rgba(0,0,0,.2);
        border-radius: 1px;
    }
    .logo-img {
        height: 52px;   /* más grande */
        width: auto;
        object-fit: contain;
        display: none;
    }
    /* Separador vertical entre logo y wordmark */
    .logo-sep {
        width: 1px;
        height: 32px;
        background: rgba(0,0,0,.1);
        margin: 0 4px;
    }
    .logo-wordmark { display: flex; flex-direction: column; justify-content: center; }
    .logo-name {
        font-size: 12px;
        font-weight: 900;
        color: var(--ink);
        letter-spacing: 5px;
        text-transform: uppercase;
        line-height: 1;
    }
    .logo-sub {
        font-size: 6.5px;
        font-weight: 500;
        color: var(--ink5);
        letter-spacing: 2px;
        text-transform: uppercase;
        margin-top: 3.5px;
    }

    .top-space { flex: 1; }

    /* Reloj */
    .top-clock {
        font-family: var(--mono);
        font-size: 10px;
        font-weight: 500;
        color: var(--ink4);
        background: var(--surf2);
        border: 1px solid var(--b);
        border-radius: var(--r-sm);
        padding: 5px 10px;
        margin-right: 8px;
        letter-spacing: .3px;
    }

    /* Píldora ACTIVO */
    .top-live {
        display: flex;
        align-items: center;
        gap: 5px;
        background: var(--gb);
        border: 1px solid var(--gbo);
        border-radius: 99px;
        padding: 5px 11px;
        margin-right: 10px;
    }
    .top-live-dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: #22C55E;
        box-shadow: 0 0 7px rgba(34,197,94,.7);
        animation: live-pulse 2s ease-in-out infinite;
    }
    @keyframes live-pulse {
        0%,100% { opacity:1; box-shadow: 0 0 6px rgba(34,197,94,.7); }
        50%      { opacity:.6; box-shadow: 0 0 12px rgba(34,197,94,.2), 0 0 0 4px rgba(34,197,94,.08); }
    }
    .top-live-txt {
        font-size: 7.5px;
        font-weight: 700;
        color: var(--green);
        letter-spacing: .6px;
        text-transform: uppercase;
    }

    /* Avatar */
    .top-av {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: var(--ink);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        font-weight: 800;
        color: #fff;
        box-shadow: 0 2px 8px rgba(0,0,0,.2);
        flex-shrink: 0;
        cursor: pointer;
        border: 2px solid var(--g);
    }

    /* Logout */
    .top-logout {
        display: flex;
        align-items: center;
        gap: 5px;
        font-size: 11px;
        font-weight: 500;
        color: var(--ink4);
        padding: 6px 12px;
        border: 1px solid var(--b);
        border-radius: var(--r-sm);
        background: var(--surf);
        margin-left: 8px;
        transition: all .2s;
    }
    .top-logout:hover { color: var(--red); border-color: var(--rbo); background: var(--rb); }
    .top-logout svg { width: 13px; height: 13px; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; }

    /* Línea dorada */
    .topbar-gold {
        height: 2px;
        background: linear-gradient(90deg,
            #0A0A0A 0%,
            #C49A2C 20%,
            #E8C85A 50%,
            #C49A2C 80%,
            transparent 100%);
        opacity: .7;
    }

    /* ═══════════════════════════════════════
       MAIN WRAP
    ═══════════════════════════════════════ */
    .main-wrap {
        max-width: 480px;
        margin: 0 auto;
        padding: 18px 14px 40px;
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    /* ═══════════════════════════════════════
       HERO — blanco puro con acento dorado
    ═══════════════════════════════════════ */
    .hero {
        background: var(--surf);
        border: 1px solid rgba(0,0,0,.07);
        border-radius: var(--r-2xl);
        padding: 18px 20px;
        position: relative;
        overflow: hidden;
        box-shadow: 0 2px 16px rgba(0,0,0,.06), 0 1px 0 #fff inset;
    }
    /* Acento dorado top izquierda */
    .hero::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0;
        height: 2px;
        background: linear-gradient(90deg, #0A0A0A 0%, #C49A2C 35%, #E8C85A 60%, transparent 100%);
        border-radius: var(--r-2xl) var(--r-2xl) 0 0;
    }
    /* Sin orbe del after — limpio */
    .hero::after { display: none; }

    /* Línea superior */
    .hero-top-line { display: none; }

    .hero-eyebrow {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 8px;
        position: relative; z-index: 1;
    }
    .hero-eyebrow-bar {
        width: 16px; height: 2px;
        background: var(--g);
        border-radius: 1px;
    }
    .hero-eyebrow-txt {
        font-size: 8px;
        font-weight: 700;
        letter-spacing: 2.5px;
        color: var(--g);
        text-transform: uppercase;
    }
    .hero-name {
        font-size: 30px;
        font-weight: 900;
        color: var(--ink);
        letter-spacing: -1px;
        line-height: 1.0;
        margin-bottom: 4px;
        position: relative; z-index: 1;
    }
    .hero-role {
        font-size: 10px;
        color: var(--ink5);
        margin-bottom: 12px;
        position: relative; z-index: 1;
        font-weight: 400;
    }
    .hero-chips {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 6px;
        position: relative; z-index: 1;
        overflow-x: auto;
        scrollbar-width: none;
        -webkit-overflow-scrolling: touch;
        padding-bottom: 2px;
    }
    .hero-chips::-webkit-scrollbar { display: none; }

    /* ── Chips B&W+Gold ── */
    .chip {
        font-size: 9px;
        font-weight: 600;
        padding: 4px 11px;
        border-radius: 6px;
        letter-spacing: .1px;
        white-space: nowrap;
        flex-shrink: 0;
    }
    /* Chip negro sólido para rol */
    .chip-gold   {
        background: var(--ink);
        border: 1px solid var(--ink);
        color: #fff;
    }
    /* Chip dorado outline */
    .chip-silver {
        background: transparent;
        border: 1px solid rgba(0,0,0,.15);
        color: var(--ink3);
    }
    /* Chip verde para activo */
    .chip-green  {
        background: var(--gb);
        border: 1px solid var(--gbo);
        color: #15652A;
    }
    .chip-dev {
        background: #EEF2FF;
        border: 1px solid #C7D2FE;
        color: #1E40AF;
        font-family: var(--mono);
        font-size: 10px;
    }

    /* Sem actual */
    .hero-sem {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        margin-top: 12px;
        font-size: 8.5px;
        font-weight: 500;
        color: var(--ink5);
        position: relative; z-index: 1;
        background: var(--surf2);
        border: 1px solid var(--b);
        border-radius: 6px;
        padding: 3px 9px;
    }

    /* ═══════════════════════════════════════
       KPI STRIP
    ═══════════════════════════════════════ */
    .kpi-strip {
        display: flex;
        gap: 8px;
    }
    .kpi-card {
        flex: 1;
        background: var(--surf);
        border: 1px solid rgba(0,0,0,.07);
        border-radius: var(--r-lg);
        padding: 12px 8px 14px;
        text-align: center;
        position: relative;
        overflow: hidden;
        box-shadow: 0 1px 6px rgba(0,0,0,.05);
        transition: transform .2s;
    }
    .kpi-card:active { transform: scale(.97); }
    .kpi-bar {
        position: absolute;
        bottom: 0; left: 0; right: 0;
        height: 2.5px;
        border-radius: 0 0 var(--r-lg) var(--r-lg);
    }
    .kpi-val {
        font-size: 24px;
        font-weight: 800;
        color: var(--ink);
        line-height: 1;
        font-variant-numeric: tabular-nums;
        letter-spacing: -.5px;
    }
    .kpi-val sup {
        font-size: 12px;
        font-weight: 600;
        color: var(--ink5);
        letter-spacing: 0;
        vertical-align: super;
    }
    .kpi-lbl {
        font-size: 7px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .9px;
        color: rgba(0,0,0,.3);
        margin-top: 3px;
    }
    .kpi-delta {
        font-size: 8px;
        font-weight: 700;
        margin-top: 2px;
    }

    /* ═══════════════════════════════════════
       MONITOR BANNER — degradado premium dorado
    ═══════════════════════════════════════ */
    .monitor-banner {
        background: var(--surf);
        border: 1px solid rgba(0,0,0,.07);
        border-radius: var(--r-xl);
        padding: 16px 18px;
        display: flex;
        align-items: center;
        gap: 14px;
        text-decoration: none;
        cursor: pointer;
        position: relative;
        overflow: hidden;
        box-shadow: 0 2px 12px rgba(0,0,0,.06), 0 1px 0 #fff inset;
        transition: transform .22s, box-shadow .22s, border-color .22s;
    }
    /* Acento dorado izquierdo — se mantiene como firma */
    .monitor-banner::before {
        content: '';
        position: absolute;
        top: 0; left: 0; bottom: 0;
        width: 3px;
        background: linear-gradient(180deg, #E8C85A, #C49A2C, #8A6A14);
        border-radius: var(--r-xl) 0 0 var(--r-xl);
    }
    /* Línea dorada superior sutil */
    .monitor-banner::after {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0;
        height: 2px;
        background: linear-gradient(90deg, #C49A2C 0%, #E8C85A 40%, transparent 100%);
        border-radius: var(--r-xl) var(--r-xl) 0 0;
        opacity: .6;
        pointer-events: none;
    }
    .monitor-banner:hover { transform: translateY(-2px); box-shadow: 0 6px 24px rgba(196,154,44,.14), 0 0 0 1px var(--b-gold); border-color: var(--b-gold); }
    .monitor-banner:active { transform: scale(.99); }
    .mon-ico-wrap {
        width: 46px; height: 46px;
        border-radius: 12px;
        background: var(--gl);
        border: 1px solid var(--b-gold);
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0;
        position: relative; z-index: 1;
    }
    .mon-ico-wrap svg { width: 22px; height: 22px; stroke: var(--gd); fill: none; stroke-width: 1.8; stroke-linecap: round; }
    .mon-dot {
        width: 8px; height: 8px;
        border-radius: 50%;
        background: #22C55E;
        box-shadow: 0 0 9px rgba(34,197,94,.8);
        animation: live-pulse 2s infinite;
        flex-shrink: 0;
    }
    .mon-content { flex: 1; min-width: 0; position: relative; z-index: 1; }
    .mon-label {
        font-size: 8px;
        font-weight: 700;
        letter-spacing: 1.5px;
        text-transform: uppercase;
        color: var(--gd);
        margin-bottom: 3px;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .mon-t { font-size: 14px; font-weight: 800; color: var(--ink); letter-spacing: -.2px; line-height: 1.2; }
    .mon-s { font-size: 10px; color: var(--ink5); margin-top: 3px; }
    .mon-arr {
        margin-left: auto;
        position: relative; z-index: 1;
        flex-shrink: 0;
    }
    .mon-arr svg { width: 20px; height: 20px; stroke: #D8D4CE; fill: none; stroke-width: 2; stroke-linecap: round; transition: stroke .2s, transform .2s; }
    .monitor-banner:hover .mon-arr svg { stroke: var(--g); transform: translateX(3px); }

    /* ═══════════════════════════════════════
       SECTION LABEL
    ═══════════════════════════════════════ */
    .sec-row {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 0 2px;
    }
    .sec-txt {
        font-size: 9px;
        font-weight: 800;
        letter-spacing: 1.4px;
        text-transform: uppercase;
        color: var(--ink);
        white-space: nowrap;
    }
    .sec-line {
        flex: 1;
        height: 1px;
        background: rgba(0,0,0,.1);
    }

    /* ═══════════════════════════════════════
       MÓDULOS
    ═══════════════════════════════════════ */
    .mod-list {
        display: flex;
        flex-direction: column;
        gap: 7px;
    }
    .mcard {
        background: var(--surf);
        border: 1px solid rgba(0,0,0,.07);
        border-radius: var(--r-xl);
        display: flex;
        overflow: hidden;
        text-decoration: none;
        cursor: pointer;
        box-shadow: 0 1px 4px rgba(0,0,0,.04);
        transition: transform .22s, box-shadow .22s, border-color .22s;
        position: relative;
    }
    .mcard:hover {
        border-color: var(--b-gold);
        box-shadow: 0 4px 16px rgba(196,154,44,.12);
        transform: translateX(2px);
    }
    .mcard:active { transform: scale(.98); }
    .mcard-accent {
        width: 3px;
        flex-shrink: 0;
        border-radius: var(--r-xl) 0 0 var(--r-xl);
    }
    .mcard-body {
        flex: 1;
        padding: 11px 13px;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .mcard-ico {
        width: 42px; height: 42px;
        border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        font-size: 20px;
        flex-shrink: 0;
        box-shadow: 0 1px 4px rgba(0,0,0,.07);
    }
    .mcard-text { flex: 1; min-width: 0; }
    .mcard-t {
        font-size: 12.5px;
        font-weight: 700;
        color: var(--ink);
        line-height: 1.25;
    }
    .mcard-s {
        font-size: 10px;
        color: var(--ink4);
        margin-top: 2px;
    }
    .mcard-right {
        margin-left: auto;
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 4px;
        flex-shrink: 0;
        padding-right: 4px;
    }
    .mcard-arr {
        font-size: 20px;
        color: var(--b);
        color: #D8D4CE;
        line-height: 1;
        transition: transform .2s, color .2s;
    }
    .mcard:hover .mcard-arr { color: var(--g); transform: translateX(3px); }
    .mcard-badge {
        font-size: 8px;
        font-weight: 700;
        padding: 2px 7px;
        border-radius: 5px;
    }

    /* Tarjeta Admin especial */
    .mcard-admin {
        background: #FFFDF5;
        border-color: rgba(196,154,44,.2);
    }
    .mcard-admin:hover { box-shadow: 0 4px 18px rgba(196,154,44,.15); border-color: var(--g); }

    /* ═══════════════════════════════════════
       EMPRESAS AFILIADAS — sin recuadro
    ═══════════════════════════════════════ */
    .companies {
        background: var(--surf);
        border: 1px solid rgba(0,0,0,.07);
        border-radius: var(--r-lg);
        padding: 14px 16px;
        box-shadow: 0 1px 4px rgba(0,0,0,.04);
    }
    .co-lbl {
        font-size: 7.5px;
        font-weight: 700;
        letter-spacing: 1.2px;
        text-transform: uppercase;
        color: var(--ink6);
        margin-bottom: 12px;
    }
    .co-items {
        display: flex;
        gap: 6px;
        overflow-x: auto;
        scrollbar-width: none;
        -webkit-overflow-scrolling: touch;
        justify-content: space-between;
        align-items: center;
    }
    .co-items::-webkit-scrollbar { display: none; }
    .co-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 5px;
        cursor: default;
        flex: 1;
        min-width: 56px;
        max-width: 80px;
        transition: transform .25s cubic-bezier(.34,1.56,.64,1);
    }
    .co-item:hover { transform: translateY(-2px); }
    /* Logo directo sin recuadro */
    .co-item img {
        max-height: 32px;
        max-width: 100%;
        object-fit: contain;
        display: block;
    }
    .co-fallback {
        font-size: 9px;
        font-weight: 700;
        color: var(--ink4);
        text-align: center;
        line-height: 1.3;
    }
    .co-name {
        font-size: 8px;
        font-weight: 500;
        color: var(--ink5);
        text-align: center;
        white-space: nowrap;
    }

    /* ═══════════════════════════════════════
       EMERGENCIA
    ═══════════════════════════════════════ */
    .emg-btn {
        background: rgba(254,242,242,.88);
        backdrop-filter: var(--blur2);
        -webkit-backdrop-filter: var(--blur2);
        border: 1px solid var(--rbo);
        border-radius: var(--r-xl);
        padding: 13px 16px;
        display: flex;
        align-items: center;
        gap: 12px;
        cursor: pointer;
        position: relative;
        overflow: hidden;
        box-shadow: 0 2px 10px rgba(220,38,38,.06);
        transition: box-shadow .22s, transform .22s, border-color .22s;
    }
    .emg-btn::before {
        content: '';
        position: absolute; inset: 0;
        background: linear-gradient(90deg, rgba(220,38,38,.03), transparent);
        pointer-events: none;
    }
    .emg-btn:hover { border-color: rgba(220,38,38,.45); box-shadow: 0 6px 22px rgba(220,38,38,.12); transform: translateY(-1px); }
    .emg-btn:active { transform: scale(.98); }

    /* Punto pulsante */
    .emg-pulse {
        position: absolute;
        top: 11px; right: 13px;
        width: 7px; height: 7px;
        border-radius: 50%;
        background: var(--red);
        animation: emg-blink 1.5s ease-in-out infinite;
    }
    @keyframes emg-blink {
        0%,100% { box-shadow: 0 0 5px rgba(220,38,38,.9); }
        50%      { box-shadow: 0 0 14px rgba(220,38,38,.3), 0 0 0 6px rgba(220,38,38,.08); }
    }
    .emg-ico {
        width: 40px; height: 40px;
        border-radius: 50%;
        background: rgba(255,255,255,.85);
        border: 1px solid var(--rbo);
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0;
        box-shadow: 0 0 0 4px rgba(220,38,38,.06);
    }
    .emg-ico svg {
        width: 18px; height: 18px;
        stroke: var(--red); stroke-width: 2.5;
        fill: none; stroke-linecap: round; stroke-linejoin: round;
    }
    .emg-t { font-size: 12.5px; font-weight: 700; color: var(--red); letter-spacing: -.1px; }
    .emg-s { font-size: 9px; color: rgba(220,38,38,.45); margin-top: 1px; }
    .emg-arr {
        margin-left: auto;
        font-size: 10px;
        font-weight: 700;
        color: rgba(220,38,38,.6);
        letter-spacing: .5px;
        white-space: nowrap;
    }

    /* ═══════════════════════════════════════
       FOOTER
    ═══════════════════════════════════════ */
    .page-foot {
        font-size: 11px;
        color: var(--ink5);
        text-align: center;
        padding: 10px 0 4px;
        border-top: 1px solid rgba(0,0,0,.06);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }

    /* ═══════════════════════════════════════
       BOTTOM NAV (solo móvil ≤ 480px)
    ═══════════════════════════════════════ */
    .bottom-nav { display: none; }

    /* ═══════════════════════════════════════
       MODAL DE INCIDENTE
    ═══════════════════════════════════════ */
    .overlay {
        display: none;
        position: fixed; inset: 0; z-index: 400;
        background: rgba(0,0,0,.35);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        align-items: center; justify-content: center;
        padding: 24px;
    }
    .overlay.open { display: flex; animation: fade-in .2s ease; }
    @keyframes fade-in { from { opacity: 0; } to { opacity: 1; } }

    .modal {
        background: var(--surf);
        border: 1px solid var(--b);
        border-radius: var(--r-2xl);
        width: 100%; max-width: 500px;
        overflow: hidden;
        box-shadow: 0 24px 60px rgba(0,0,0,.14);
        animation: modal-in .3s cubic-bezier(.34,1.2,.64,1);
    }
    @keyframes modal-in {
        from { opacity: 0; transform: scale(.96) translateY(10px); }
        to   { opacity: 1; transform: none; }
    }
    .modal-handle { display: none; width: 40px; height: 4px; border-radius: 2px; background: var(--svb); margin: 12px auto 0; }
    .modal-hd {
        display: flex; align-items: center; gap: 12px;
        padding: 16px 20px; border-bottom: 1px solid var(--b);
        background: var(--rb);
    }
    .modal-hd-ico {
        width: 38px; height: 38px; border-radius: 50%;
        background: rgba(255,255,255,.8); border: 1px solid var(--rbo);
        display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    }
    .modal-hd-ico svg { width: 18px; height: 18px; stroke: var(--red); stroke-width: 2.5; fill: none; stroke-linecap: round; }
    .modal-ttl { font-size: 14px; font-weight: 700; color: var(--red); }
    .modal-sub { font-size: 11px; color: var(--ink4); margin-top: 2px; }
    .modal-close {
        margin-left: auto; width: 32px; height: 32px; border-radius: 50%;
        background: var(--surf); border: 1px solid var(--b);
        display: flex; align-items: center; justify-content: center;
        cursor: pointer; font-size: 18px; color: var(--ink4);
        transition: all .2s; flex-shrink: 0;
    }
    .modal-close:hover { background: var(--svb); transform: rotate(90deg); color: var(--ink); }
    .modal-body { padding: 16px 20px; }
    .modal-row {
        display: flex; gap: 12px;
        padding: 10px 0; border-bottom: 1px solid var(--b);
        align-items: baseline;
    }
    .modal-row:last-child { border-bottom: none; }
    .modal-k { font-size: 9.5px; font-weight: 700; color: var(--ink5); text-transform: uppercase; letter-spacing: .5px; min-width: 110px; flex-shrink: 0; }
    .modal-v { font-size: 13px; font-weight: 500; color: var(--ink); line-height: 1.5; }
    .modal-v strong { font-weight: 700; }

    /* ═══════════════════════════════════════
       TOAST
    ═══════════════════════════════════════ */
    .toast {
        position: fixed;
        bottom: calc(20px + var(--safe-b));
        left: 50%; transform: translateX(-50%) translateY(16px);
        background: var(--ink); color: #fff;
        padding: 10px 18px; border-radius: 99px;
        font-size: 12.5px; font-weight: 600;
        display: flex; align-items: center; gap: 8px;
        z-index: 500; opacity: 0; pointer-events: none;
        transition: all .3s cubic-bezier(.34,1.2,.64,1);
        box-shadow: 0 8px 24px rgba(0,0,0,.2); white-space: nowrap;
    }
    .toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
    .toast-ok {
        width: 18px; height: 18px; border-radius: 50%;
        background: #22C55E; color: #fff;
        display: flex; align-items: center; justify-content: center;
        font-size: 10px; font-weight: 700;
    }

    /* ═══════════════════════════════════════
       PULL-TO-REFRESH
    ═══════════════════════════════════════ */
    .pull-bar {
        display: none; align-items: center; justify-content: center; gap: 10px;
        padding: 10px; font-size: 11.5px; font-weight: 600;
        color: var(--gd); background: var(--gl);
        border-bottom: 1px solid var(--gr);
    }
    .pull-spin {
        width: 16px; height: 16px;
        border: 2px solid var(--gr); border-top-color: var(--g);
        border-radius: 50%; animation: spin .8s linear infinite; flex-shrink: 0;
    }
    @keyframes spin { to { transform: rotate(360deg); } }

    /* ═══════════════════════════════════════
       ENTRANCE ANIMATIONS
    ═══════════════════════════════════════ */
    .fi {
        opacity: 0;
        transform: translateY(12px) scale(.99);
        animation: float-up .5s cubic-bezier(.34,1.1,.64,1) forwards;
    }
    @keyframes float-up { to { opacity: 1; transform: translateY(0) scale(1); } }
    .d1 { animation-delay: .04s; }
    .d2 { animation-delay: .1s;  }
    .d3 { animation-delay: .16s; }
    .d4 { animation-delay: .22s; }
    .d5 { animation-delay: .28s; }
    .d6 { animation-delay: .34s; }

    /* ═══════════════════════════════════════
       RESPONSIVE — TABLET
    ═══════════════════════════════════════ */
    @media (max-width: 768px) {
        .top-clock { display: none; }
        .logo-sub  { display: none; }
    }

    /* ═══════════════════════════════════════
       RESPONSIVE — MÓVIL
    ═══════════════════════════════════════ */
    @media (max-width: 480px) {

        /* Topbar compacto */
        .topbar-inner { padding: 0 14px; height: 54px; }
        .logo-marks { display: none; }
        .logo-name { font-size: 9.5px; letter-spacing: 3.5px; }
        .logo-sub  { display: none; }
        .top-clock { display: none; }
        .top-logout { display: none; }

        /* Main */
        .main-wrap { padding: 14px 12px calc(80px + var(--safe-b)); gap: 10px; }

        /* Hero compacto */
        .hero { padding: 14px 15px 15px; border-radius: 18px; }
        .hero-name { font-size: 24px; }

        /* KPIs */
        .kpi-val { font-size: 20px; }

        /* Bottom nav visible */
        .bottom-nav {
            display: flex;
            position: fixed; bottom: 0; left: 0; right: 0; z-index: 300;
            background: rgba(255,255,255,.97);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-top: 1px solid rgba(0,0,0,.07);
            padding: 8px 6px calc(8px + var(--safe-b));
            align-items: center; justify-content: space-around;
            box-shadow: 0 -4px 24px rgba(0,0,0,.06);
        }
        .bn-item {
            display: flex; flex-direction: column; align-items: center; gap: 3px;
            cursor: pointer; padding: 5px 10px; border-radius: 10px; flex: 1;
            transition: background .12s;
            -webkit-tap-highlight-color: transparent;
        }
        .bn-item:active { background: rgba(0,0,0,.04); transform: scale(.94); }
        .bn-ico {
            width: 24px; height: 24px;
            display: flex; align-items: center; justify-content: center;
        }
        .bn-ico svg {
            width: 22px; height: 22px;
            fill: none; stroke-width: 2;
            stroke-linecap: round; stroke-linejoin: round;
            transition: stroke .15s;
        }
        .bn-lbl { font-size: 10px; font-weight: 600; transition: color .15s; }
        .bn-item.active .bn-ico svg { stroke: var(--g); filter: drop-shadow(0 0 4px rgba(196,154,44,.4)); }
        .bn-item.active .bn-lbl    { color: var(--g); }
        .bn-item:not(.active) .bn-ico svg { stroke: var(--ink5); }
        .bn-item:not(.active) .bn-lbl    { color: var(--ink5); }

        /* Botón emergencia central elevado */
        .bn-emg {
            display: flex; flex-direction: column; align-items: center; gap: 3px;
            cursor: pointer; flex-shrink: 0;
            position: relative; top: -14px;
            -webkit-tap-highlight-color: transparent;
        }
        .bn-emg-circle {
            width: 52px; height: 52px; border-radius: 50%;
            background: linear-gradient(145deg,#B91C1C,#DC2626,#EF4444);
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 4px 18px rgba(220,38,38,.45), 0 0 0 4px rgba(220,38,38,.1), inset 0 1.5px 0 rgba(255,255,255,.2);
            transition: transform .12s, box-shadow .12s;
        }
        .bn-emg:active .bn-emg-circle { transform: scale(.91); box-shadow: 0 2px 8px rgba(220,38,38,.35); }
        .bn-emg-circle svg { width: 24px; height: 24px; stroke: #fff; stroke-width: 2.5; fill: none; stroke-linecap: round; }
        .bn-emg-lbl { font-size: 10px; font-weight: 700; color: var(--red); }

        /* Modal como bottom sheet */
        .overlay { align-items: flex-end; justify-content: center; padding: 0; }
        .modal {
            border-radius: 20px 20px 0 0; max-width: 100%;
            animation: slide-up .32s cubic-bezier(.34,1.1,.64,1);
            padding-bottom: var(--safe-b);
        }
        @keyframes slide-up { from { transform: translateY(100%); } to { transform: translateY(0); } }
        .modal-handle { display: block; }
        .modal-hd { padding: 13px 16px; }
        .modal-body { padding: 13px 16px; }
        .modal-row { flex-direction: column; gap: 3px; padding: 9px 0; }
        .modal-k { min-width: 0; }

        /* Toast sobre nav */
        .toast { bottom: calc(72px + var(--safe-b)); font-size: 12px; }
    }

    @media (prefers-reduced-motion: reduce) {
        .fi { animation: none; opacity: 1; transform: none; }
        .live-dot, .mon-dot, .pull-spin, .emg-pulse { animation: none; }
    }

    /* ═══════════════════════════════════════
       MODAL IRIS — Integración de emergencias
    ═══════════════════════════════════════ */
    .iris-modal {
        background: var(--surf);
        border: 1px solid var(--b);
        border-radius: var(--r-2xl);
        width: 100%; max-width: 460px;
        overflow: hidden;
        box-shadow: 0 24px 60px rgba(0,0,0,.18);
        animation: modal-in .3s cubic-bezier(.34,1.2,.64,1);
    }
    @keyframes modal-in {
        from { opacity: 0; transform: scale(.96) translateY(10px); }
        to   { opacity: 1; transform: none; }
    }
    .iris-handle {
        display: none; width: 40px; height: 4px;
        border-radius: 2px; background: var(--svb);
        margin: 12px auto 0;
    }
    .iris-hd {
        background: linear-gradient(135deg, #7F1D1D 0%, #991B1B 40%, #DC2626 100%);
        padding: 20px 20px 18px; position: relative; overflow: hidden;
    }
    .iris-hd::before {
        content: ''; position: absolute; top: -30px; right: -20px;
        width: 120px; height: 120px; border-radius: 50%;
        background: rgba(255,255,255,.06); pointer-events: none;
    }
    .iris-hd::after {
        content: ''; position: absolute; bottom: 0; left: 0; right: 0; height: 2px;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,.25), transparent);
    }
    .iris-hd-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; }
    .iris-hd-brand { display: flex; align-items: center; gap: 10px; }
    .iris-hd-logo {
        width: 36px; height: 36px; border-radius: 10px;
        background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.2);
        display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    }
    .iris-hd-logo svg { width: 18px; height: 18px; stroke: #fff; fill: none; stroke-width: 2.2; stroke-linecap: round; }
    .iris-hd-name { font-size: 19px; font-weight: 900; color: #fff; letter-spacing: -.3px; line-height: 1; }
    .iris-hd-sub  { font-size: 9px; font-weight: 600; color: rgba(255,255,255,.5); letter-spacing: 1.5px; text-transform: uppercase; margin-top: 2px; }
    .iris-close {
        width: 30px; height: 30px; border-radius: 50%;
        background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.18);
        display: flex; align-items: center; justify-content: center;
        color: rgba(255,255,255,.7); font-size: 18px; cursor: pointer;
        transition: all .2s; line-height: 1; flex-shrink: 0;
    }
    .iris-close:hover { background: rgba(255,255,255,.22); color: #fff; }
    .iris-pulse-row { display: flex; align-items: center; gap: 8px; }
    .iris-pulse-dot {
        width: 8px; height: 8px; border-radius: 50%; background: #FCA5A5;
        animation: iris-dot 1.6s ease-in-out infinite; flex-shrink: 0;
    }
    @keyframes iris-dot {
        0%   { box-shadow: 0 0 0 0 rgba(252,165,165,.6); }
        70%  { box-shadow: 0 0 0 8px rgba(252,165,165,0); }
        100% { box-shadow: 0 0 0 0 rgba(252,165,165,0); }
    }
    .iris-pulse-txt { font-size: 10.5px; font-weight: 700; color: rgba(255,255,255,.65); letter-spacing: .5px; text-transform: uppercase; }
    .iris-body { padding: 18px 18px 20px; display: flex; flex-direction: column; gap: 10px; }
    .iris-btn-primary {
        display: flex; align-items: center; gap: 14px;
        background: var(--ink); border: 1px solid var(--ink);
        border-radius: var(--r-xl); padding: 16px;
        cursor: pointer; text-decoration: none; position: relative; overflow: hidden;
        transition: transform .2s, box-shadow .2s;
    }
    .iris-btn-primary::before {
        content: ''; position: absolute; top: 0; left: 0; right: 0; height: 2px;
        background: linear-gradient(90deg, #7A5A0E, #C49A2C, #E8C85A);
    }
    .iris-btn-primary:hover  { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,.25); }
    .iris-btn-primary:active { transform: scale(.98); }
    .iris-btn-ico {
        width: 44px; height: 44px; border-radius: 12px;
        background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.1);
        display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    }
    .iris-btn-ico svg { width: 22px; height: 22px; stroke: var(--g); fill: none; stroke-width: 1.8; stroke-linecap: round; }
    .iris-btn-txt { flex: 1; min-width: 0; }
    .iris-btn-lbl  { font-size: 7.5px; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; color: rgba(255,255,255,.35); margin-bottom: 2px; }
    .iris-btn-tit  { font-size: 14px; font-weight: 800; color: #fff; letter-spacing: -.2px; }
    .iris-btn-desc { font-size: 10px; color: rgba(255,255,255,.35); margin-top: 2px; }
    .iris-btn-arr  { color: rgba(255,255,255,.25); font-size: 22px; line-height: 1; flex-shrink: 0; transition: color .2s, transform .2s; }
    .iris-btn-primary:hover .iris-btn-arr { color: var(--g); transform: translateX(3px); }
    .iris-divider  { display: flex; align-items: center; gap: 10px; }
    .iris-div-line { flex: 1; height: 1px; background: var(--b); }
    .iris-div-txt  { font-size: 9px; font-weight: 600; color: var(--ink5); letter-spacing: .5px; text-transform: uppercase; }
    .iris-btn-wa {
        display: flex; align-items: center; gap: 14px;
        background: var(--gb); border: 1px solid var(--gbo);
        border-radius: var(--r-xl); padding: 14px 16px;
        cursor: pointer; text-decoration: none;
        transition: transform .2s, box-shadow .2s, border-color .2s;
    }
    .iris-btn-wa:hover  { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(22,163,74,.12); border-color: #4ADE80; }
    .iris-btn-wa:active { transform: scale(.98); }
    .iris-wa-ico {
        width: 42px; height: 42px; border-radius: 12px; background: #22C55E;
        display: flex; align-items: center; justify-content: center; flex-shrink: 0;
        box-shadow: 0 4px 12px rgba(34,197,94,.3);
    }
    .iris-wa-ico svg { width: 22px; height: 22px; fill: #fff; }
    .iris-wa-txt { flex: 1; }
    .iris-wa-lbl { font-size: 7.5px; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; color: #15803D; margin-bottom: 2px; }
    .iris-wa-tit { font-size: 14px; font-weight: 800; color: #15803D; }
    .iris-wa-num { font-family: var(--mono); font-size: 11px; color: #16A34A; margin-top: 2px; }
    .iris-wa-arr { color: var(--gbo); font-size: 22px; line-height: 1; flex-shrink: 0; transition: color .2s; }
    .iris-btn-wa:hover .iris-wa-arr { color: #22C55E; }
    .iris-footer {
        padding: 12px 18px; border-top: 1px solid var(--b);
        display: flex; align-items: center; gap: 8px; background: var(--surf2);
    }
    .iris-ftr-dot { width: 6px; height: 6px; border-radius: 50%; background: #22C55E; box-shadow: 0 0 6px rgba(34,197,94,.6); flex-shrink: 0; }
    .iris-ftr-txt { font-size: 10px; color: var(--ink5); line-height: 1.5; }

    @media (max-width: 480px) {
        .iris-modal { border-radius: 20px 20px 0 0; max-width: 100%; animation: slide-up .32s cubic-bezier(.34,1.1,.64,1); padding-bottom: var(--safe-b); }
        .iris-handle { display: block; }
    }
    </style>
</head>
<body>

<!-- ══ PULL INDICATOR ══ -->
<div class="pull-bar" id="pullBar">
    <div class="pull-spin"></div>
    Actualizando datos…
</div>

<!-- ══ TOPBAR ══ -->
<header class="topbar">
    <div class="topbar-inner">

        <div class="logo-block">
            <!-- Logo imagen si existe -->
            <img class="logo-img" src="assets/logo.png" alt="Hochschild Mining"
                 onload="this.style.display='block';this.nextElementSibling.style.display='none';this.nextElementSibling.nextElementSibling.style.display='none'"
                 onerror="this.style.display='none'">
            <!-- Marcas geométricas fallback -->
            <div class="logo-marks">
                <span></span>
                <span></span>
            </div>
            <!-- Separador -->
            <div class="logo-sep"></div>
            <div class="logo-wordmark">
                <div class="logo-name">HOCHSCHILD</div>
                <div class="logo-sub">Sistema de Transporte</div>
            </div>
        </div>

        <div class="top-space"></div>

        <span class="top-clock" id="clockEl">--:--:--</span>

        <div class="top-live">
            <div class="top-live-dot"></div>
            <span class="top-live-txt">Activo</span>
        </div>

        <div class="top-av"><?= h($iniciales) ?></div>

        <a href="logout.php" class="top-logout">
            <svg viewBox="0 0 24 24">
                <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/>
                <polyline points="16 17 21 12 16 7"/>
                <line x1="21" y1="12" x2="9" y2="12"/>
            </svg>
            Salir
        </a>

    </div>
    <div class="topbar-gold"></div>
</header>

<!-- ══ MAIN ══ -->
<main class="main-wrap">

    <!-- ── HERO ── -->
    <section class="hero fi d1">
        <div class="hero-top-line"></div>

        <div class="hero-eyebrow">
            <div class="hero-eyebrow-bar"></div>
            <span class="hero-eyebrow-txt"><?= h($saludo) ?></span>
        </div>

        <h1 class="hero-name"><?= h($primer_nombre) ?><br><?= h($apellido) ?></h1>
        <p class="hero-role"><?= h($cargo_real) ?></p>

        <div class="hero-chips">
            <span class="chip chip-gold">⬡ <?= h(ucfirst($rol_sistema)) ?></span>

            <?php if ($es_programador): ?>
                <span class="chip chip-dev">&lt;/&gt; <?= h($cargo_real) ?></span>
            <?php else: ?>
                <span class="chip chip-silver"><?= h($cargo_real) ?></span>
            <?php endif; ?>

            <span class="chip chip-green">● Sistema activo</span>
        </div>

        <div class="hero-sem">
            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
            </svg>
            <?= h($sem_label) ?>
        </div>
    </section>

    <!-- ── KPI STRIP ── -->
    <div class="kpi-strip fi d2">

        <div class="kpi-card">
            <div class="kpi-val" style="color:<?= $kpi_avance >= 85 ? '#15803D' : ($kpi_avance >= 60 ? '#B45309' : '#DC2626') ?>">
                <?= $kpi_avance ?><sup>%</sup>
            </div>
            <div class="kpi-lbl">Avance</div>
            <div class="kpi-delta" style="color:<?= $delta_avance >= 0 ? '#15803D' : '#DC2626' ?>">
                <?= $delta_avance >= 0 ? '↑ +' : '↓ ' ?><?= abs($delta_avance) ?>pp
            </div>
            <div class="kpi-bar" style="background:linear-gradient(90deg,<?= $kpi_avance >= 85 ? '#15803D,#22C55E' : ($kpi_avance >= 60 ? '#92400E,#F59E0B' : '#991B1B,#DC2626') ?>)"></div>
        </div>

        <div class="kpi-card">
            <div class="kpi-val" style="color:#C49A2C"><?= number_format($kpi_escaneados) ?></div>
            <div class="kpi-lbl">Escaneados</div>
            <div class="kpi-delta" style="color:#C49A2C">esta sem.</div>
            <div class="kpi-bar" style="background:linear-gradient(90deg,#8A6A14,#C49A2C)"></div>
        </div>

        <div class="kpi-card">
            <div class="kpi-val" style="color:<?= $kpi_faltantes == 0 ? '#15803D' : '#DC2626' ?>">
                <?= number_format($kpi_faltantes) ?>
            </div>
            <div class="kpi-lbl">Faltantes</div>
            <div class="kpi-delta" style="color:<?= $delta_faltantes >= 0 ? '#15803D' : '#DC2626' ?>">
                <?= $delta_faltantes >= 0 ? '↓ mejoró' : '↑ subió' ?>
            </div>
            <div class="kpi-bar" style="background:linear-gradient(90deg,<?= $kpi_faltantes == 0 ? '#15803D,#22C55E' : '#991B1B,#DC2626' ?>)"></div>
        </div>

    </div>

    <!-- ── CENTRO DE CONTROL — tarjeta destacada ── -->
    <?php if ($es_sup): ?>
    <a href="monitoreoderuta_full.php" class="monitor-banner fi d3">
        <div class="mon-ico-wrap">
            <svg viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="9" stroke-width="1.5"/>
                <circle cx="12" cy="12" r="5" stroke-width="1.2" opacity=".55"/>
                <circle cx="12" cy="12" r="2.2" fill="#C49A2C" stroke="none"/>
                <line x1="12" y1="3"    x2="12" y2="5.5"   stroke-width="1.8" stroke-linecap="round"/>
                <line x1="12" y1="18.5" x2="12" y2="21"    stroke-width="1.8" stroke-linecap="round"/>
                <line x1="3"  y1="12"   x2="5.5" y2="12"   stroke-width="1.8" stroke-linecap="round"/>
                <line x1="18.5" y1="12" x2="21"  y2="12"   stroke-width="1.8" stroke-linecap="round"/>
            </svg>
        </div>
        <div class="mon-content">
            <div class="mon-label">
                <div class="mon-dot"></div>
                Centro de Control
            </div>
            <div class="mon-t">Monitoreo Gerencial</div>
            <div class="mon-s"><?= $kpi_rutas ?> rutas · embarques e indicadores críticos</div>
        </div>
        <div class="mon-arr">
            <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
        </div>
    </a>
    <?php endif; ?>

    <!-- ── MÓDULOS DEL SISTEMA ── -->
    <div class="fi d<?= $es_sup ? '4' : '3' ?>">
        <div class="sec-row" style="margin-bottom:8px">
            <span class="sec-txt">Módulos del sistema</span>
            <div class="sec-line"></div>
        </div>

        <div class="mod-list">

            <a href="buses.php" class="mcard">
                <div class="mcard-accent" style="background:linear-gradient(180deg,#EA580C,rgba(234,88,12,.15))"></div>
                <div class="mcard-body">
                    <div class="mcard-ico" style="background:rgba(255,243,232,.9)">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <rect x="2" y="4" width="20" height="15" rx="3" stroke="#EA580C" stroke-width="1.8"/>
                            <path d="M2 9h20M8 4v5M16 4v5" stroke="#EA580C" stroke-width="1.5" stroke-linecap="round"/>
                            <circle cx="6.5"  cy="15" r="1.7" fill="#EA580C"/>
                            <circle cx="17.5" cy="15" r="1.7" fill="#EA580C"/>
                            <path d="M9.5 15h5" stroke="#EA580C" stroke-width="1.8" stroke-linecap="round"/>
                            <path d="M2 17.5L1 19.5M22 17.5l1 2" stroke="#EA580C" stroke-width="1.5" stroke-linecap="round"/>
                        </svg>
                    </div>
                    <div class="mcard-text">
                        <div class="mcard-t">Embarque de Buses</div>
                        <div class="mcard-s">Control de ascensos y descensos</div>
                    </div>
                    <div class="mcard-right">
                        <div class="mcard-arr">›</div>
                    </div>
                </div>
            </a>

            <a href="index_manifiesto.php" class="mcard">
                <div class="mcard-accent" style="background:linear-gradient(180deg,#1D4ED8,rgba(29,78,216,.15))"></div>
                <div class="mcard-body">
                    <div class="mcard-ico" style="background:rgba(235,243,255,.9)">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <rect x="5" y="3" width="14" height="18" rx="2.5" stroke="#1D4ED8" stroke-width="1.8"/>
                            <path d="M9 2h6v3H9z" stroke="#1D4ED8" stroke-width="1.4" stroke-linejoin="round" fill="#EBF3FF"/>
                            <path d="M8.5 10.5h7M8.5 13.5h7M8.5 16.5h5" stroke="#1D4ED8" stroke-width="1.5" stroke-linecap="round"/>
                            <circle cx="7.5" cy="10.5" r="1.1" fill="#1D4ED8"/>
                            <circle cx="7.5" cy="13.5" r="1.1" fill="#1D4ED8"/>
                            <circle cx="7.5" cy="16.5" r="1.1" fill="#1D4ED8"/>
                        </svg>
                    </div>
                    <div class="mcard-text">
                        <div class="mcard-t">Manifiesto de Pasajeros</div>
                        <div class="mcard-s">Reportes de ruta y ocupación</div>
                    </div>
                    <div class="mcard-right">
                        <div class="mcard-arr">›</div>
                    </div>
                </div>
            </a>

            <a href="personal.php" class="mcard">
                <div class="mcard-accent" style="background:linear-gradient(180deg,#15803D,rgba(21,128,61,.15))"></div>
                <div class="mcard-body">
                    <div class="mcard-ico" style="background:rgba(236,253,245,.9)">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <circle cx="8.5" cy="7" r="3.2" stroke="#15803D" stroke-width="1.8"/>
                            <path d="M2 21c0-3.1 2.9-5.6 6.5-5.6s6.5 2.5 6.5 5.6" stroke="#15803D" stroke-width="1.8" stroke-linecap="round"/>
                            <circle cx="18.5" cy="8" r="2.5" stroke="#15803D" stroke-width="1.5"/>
                            <path d="M22 21c0-2.4-1.6-4.4-3.5-4.7" stroke="#15803D" stroke-width="1.5" stroke-linecap="round"/>
                            <circle cx="20" cy="13.5" r="3.8" fill="#ECFDF5" stroke="#15803D" stroke-width="1.3"/>
                            <path d="M18.5 13.5l1.2 1.3 2.2-2.3" stroke="#15803D" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <div class="mcard-text">
                        <div class="mcard-t">Directorio de Personal</div>
                        <div class="mcard-s">Gestión integral de trabajadores</div>
                    </div>
                    <div class="mcard-right">
                        <div class="mcard-arr">›</div>
                        <?php if ($total_personal): ?>
                        <div class="mcard-badge" style="background:#F0FDF4;border:1px solid #A7F3D0;color:#15652A">
                            <?= number_format($total_personal) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </a>

            <?php if ($es_sup): ?>
            <?php /* Control Gerencial ya está en el banner destacado de arriba, no se repite */ ?>
            <?php else: ?>
            <a href="kpis_pro.php" class="mcard">
                <div class="mcard-accent" style="background:linear-gradient(180deg,#6D28D9,rgba(109,40,217,.15))"></div>
                <div class="mcard-body">
                    <div class="mcard-ico" style="background:rgba(245,243,255,.9)">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <polyline points="22 12 18 12 15 21 9 3 6 12 2 12" stroke="#6D28D9" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <div class="mcard-text">
                        <div class="mcard-t">Mis KPIs</div>
                        <div class="mcard-s">Indicadores personales de operación</div>
                    </div>
                    <div class="mcard-right">
                        <div class="mcard-arr">›</div>
                    </div>
                </div>
            </a>
            <?php endif; ?>

        </div>
    </div>

    <!-- ── EMPRESAS AFILIADAS ── -->
    <div class="fi d5">
        <div class="sec-row" style="margin-bottom:10px">
            <span class="sec-txt">Empresas afiliadas</span>
            <div class="sec-line"></div>
        </div>
        <div class="companies">
            <div class="co-lbl">Alianzas estratégicas</div>
            <div class="co-items">

                <div class="co-item">
                    <img src="Logos Transporte/dajor.png" alt="Dajor"
                         onerror="this.style.display='none';this.nextElementSibling.style.display='block'">
                    <span class="co-fallback" style="display:none">Dajor</span>
                    <span class="co-name">Dajor</span>
                </div>

                <div class="co-item">
                    <img src="Logos Transporte/tpp.png" alt="TPP"
                         onerror="this.style.display='none';this.nextElementSibling.style.display='block'">
                    <span class="co-fallback" style="display:none">TPP</span>
                    <span class="co-name">TPP</span>
                </div>

                <div class="co-item">
                    <img src="Logos Transporte/caleb.png" alt="Caleb"
                         onerror="this.style.display='none';this.nextElementSibling.style.display='block'">
                    <span class="co-fallback" style="display:none">Caleb</span>
                    <span class="co-name">Caleb</span>
                </div>

                <div class="co-item">
                    <img src="Logos Transporte/new_road.png" alt="New Road"
                         onerror="this.style.display='none';this.nextElementSibling.style.display='block'">
                    <span class="co-fallback" style="display:none">New Road</span>
                    <span class="co-name">New Road</span>
                </div>

            </div>
        </div>
    </div>

    <!-- ── EMERGENCIA ── -->
    <button class="emg-btn fi d6" id="btnEmergencia" type="button">
        <div class="emg-pulse"></div>
        <div class="emg-ico">
            <svg viewBox="0 0 24 24">
                <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                <line x1="12" y1="9"  x2="12"   y2="13"/>
                <line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
        </div>
        <div>
            <div class="emg-t">Reporte de Incidente</div>
            <div class="emg-s">Activar protocolo de emergencia</div>
        </div>
        <div class="emg-arr">ACTIVAR →</div>
    </button>

    <!-- ── FOOTER ── -->
    <div class="page-foot fi d6" style="animation-delay:.4s">
        <svg width="14" height="18" viewBox="0 0 14 18" fill="none" aria-hidden="true">
            <path d="M.5 4.5L4.5 2l3 4.5-3 4.5-4-2.5z"  fill="#C49A2C" opacity=".6"/>
            <path d="M7.5 6.5L11 4l2.5 4-2.5 4-3-2z"   fill="#B8BEC4" opacity=".6"/>
            <path d="M1 11l3.5 2L7 17l-3-1z"             fill="#C49A2C" opacity=".35"/>
            <path d="M7 11l3 2 2.5 4-3-1z"               fill="#B8BEC4" opacity=".35"/>
        </svg>
        © <?= date('Y') ?> Hochschild Mining · Sistema Integral de Transporte
    </div>

</main>

<!-- ══ BOTTOM NAV (móvil ≤ 480px) ══ -->
<nav class="bottom-nav" id="bottomNav" aria-label="Navegación principal">

    <div class="bn-item active" data-page="inicio" aria-label="Inicio">
        <div class="bn-ico">
            <svg viewBox="0 0 24 24">
                <rect x="3" y="3" width="7" height="7" rx="1.5"/>
                <rect x="14" y="3" width="7" height="7" rx="1.5"/>
                <rect x="3" y="14" width="7" height="7" rx="1.5"/>
                <rect x="14" y="14" width="7" height="7" rx="1.5"/>
            </svg>
        </div>
        <span class="bn-lbl">Inicio</span>
    </div>

    <div class="bn-item" onclick="window.location='buses.php'" aria-label="Buses">
        <div class="bn-ico">
            <svg viewBox="0 0 24 24">
                <rect x="2" y="5" width="20" height="14" rx="3"/>
                <path d="M2 9.5h20M8 5v4.5M16 5v4.5"/>
                <circle cx="6.5"  cy="15" r="1.5" fill="currentColor" stroke="none"/>
                <circle cx="17.5" cy="15" r="1.5" fill="currentColor" stroke="none"/>
            </svg>
        </div>
        <span class="bn-lbl">Buses</span>
    </div>

    <!-- Emergencia central -->
    <div class="bn-emg" id="btnEmgNav" aria-label="Reporte de incidente">
        <div class="bn-emg-circle">
            <svg viewBox="0 0 24 24">
                <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                <line x1="12" y1="9"  x2="12"   y2="13"/>
                <line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
        </div>
        <span class="bn-emg-lbl">Alerta</span>
    </div>

    <div class="bn-item" onclick="window.location='personal.php'" aria-label="Personal">
        <div class="bn-ico">
            <svg viewBox="0 0 24 24">
                <circle cx="9" cy="7" r="4"/>
                <path d="M2 21c0-3.3 3.1-6 7-6s7 2.7 7 6"/>
                <circle cx="19" cy="8" r="2.5"/>
                <path d="M22 21c0-2.2-1.3-4-3-4.5"/>
            </svg>
        </div>
        <span class="bn-lbl">Personal</span>
    </div>

    <?php if ($es_sup): ?>
    <div class="bn-item" onclick="window.location='monitoreoderuta_full.php'" aria-label="Monitor">
        <div class="bn-ico">
            <svg viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="2"/>
                <path d="M16.24 7.76a6 6 0 010 8.49M7.76 16.24a6 6 0 010-8.49M19.07 4.93a10 10 0 010 14.14M4.93 19.07a10 10 0 010-14.14"/>
            </svg>
        </div>
        <span class="bn-lbl">Monitor</span>
    </div>
    <?php else: ?>
    <div class="bn-item" onclick="window.location='kpis_pro.php'" aria-label="KPIs">
        <div class="bn-ico">
            <svg viewBox="0 0 24 24">
                <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
            </svg>
        </div>
        <span class="bn-lbl">KPIs</span>
    </div>
    <?php endif; ?>

</nav>

<!-- ══ MODAL IRIS — Emergencias ══ -->
<div class="overlay" id="modalOverlay" role="dialog" aria-modal="true" aria-labelledby="irisModalTitle">
    <div class="iris-modal">

        <div class="iris-handle"></div>

        <div class="iris-hd">
            <div class="iris-hd-top">
                <div class="iris-hd-brand">
                    <div class="iris-hd-logo">
                        <svg viewBox="0 0 24 24">
                            <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                            <line x1="12" y1="9"  x2="12"   y2="13"/>
                            <line x1="12" y1="17" x2="12.01" y2="17"/>
                        </svg>
                    </div>
                    <div>
                        <div class="iris-hd-name" id="irisModalTitle">IRIS.</div>
                        <div class="iris-hd-sub">Inteligencia de Riesgos · Hochschild</div>
                    </div>
                </div>
                <button class="iris-close" id="closeModal" aria-label="Cerrar">&times;</button>
            </div>
            <div class="iris-pulse-row">
                <div class="iris-pulse-dot"></div>
                <span class="iris-pulse-txt">Central de emergencias · disponible 24 / 7</span>
            </div>
        </div>

        <div class="iris-body">

            <a href="https://hocmind.com/IRIS/"
               target="_blank" rel="noopener noreferrer"
               class="iris-btn-primary" onclick="closeModal()">
                <div class="iris-btn-ico">
                    <svg viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="9"   stroke-width="1.5"/>
                        <circle cx="12" cy="12" r="5"   stroke-width="1.2" opacity=".5"/>
                        <circle cx="12" cy="12" r="2"   fill="#C49A2C" stroke="none"/>
                        <line x1="12" y1="3"    x2="12" y2="5.5"  stroke-width="1.8" stroke-linecap="round"/>
                        <line x1="12" y1="18.5" x2="12" y2="21"   stroke-width="1.8" stroke-linecap="round"/>
                        <line x1="3"  y1="12"   x2="5.5" y2="12"  stroke-width="1.8" stroke-linecap="round"/>
                        <line x1="18.5" y1="12" x2="21" y2="12"   stroke-width="1.8" stroke-linecap="round"/>
                    </svg>
                </div>
                <div class="iris-btn-txt">
                    <div class="iris-btn-lbl">Centro de control</div>
                    <div class="iris-btn-tit">Abrir IRIS</div>
                    <div class="iris-btn-desc">Sistema táctico de riesgos · UM Inmaculada</div>
                </div>
                <span class="iris-btn-arr">›</span>
            </a>

            <div class="iris-divider">
                <div class="iris-div-line"></div>
                <span class="iris-div-txt">o contacto directo</span>
                <div class="iris-div-line"></div>
            </div>

            <a href="https://wa.me/51955588932?text=<?= urlencode('🚨 EMERGENCIA HOCHSCHILD - IRIS.' . "\n\n" . 'Reporte desde el Sistema de Control Operativo.' . "\n" . 'Usuario: ' . ($nombre_sesion ?? 'Operador') . "\n" . 'Fecha: ' . date('d/m/Y H:i')) ?>"
               target="_blank" rel="noopener noreferrer"
               class="iris-btn-wa" onclick="closeModal()">
                <div class="iris-wa-ico">
                    <svg viewBox="0 0 24 24">
                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                    </svg>
                </div>
                <div class="iris-wa-txt">
                    <div class="iris-wa-lbl">Contacto directo</div>
                    <div class="iris-wa-tit">FONOHOC</div>
                    <div class="iris-wa-num">955 588 932</div>
                </div>
                <span class="iris-wa-arr">›</span>
            </a>

        </div>

        <div class="iris-footer">
            <div class="iris-ftr-dot"></div>
            <span class="iris-ftr-txt">Centro de Control · UM Inmaculada · En línea</span>
        </div>

    </div>
</div>

<!-- ══ TOAST ══ -->
<div class="toast" id="toast" role="status" aria-live="polite">
    <div class="toast-ok">✓</div>
    <span id="toastMsg">Actualizado</span>
</div>

<script>
(function () {

    /* ── Reloj ── */
    const DIAS = ['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'];
    const MESES = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
    function tick() {
        const n = new Date(), p = v => String(v).padStart(2,'0');
        const el = document.getElementById('clockEl');
        if (el) el.textContent =
            DIAS[n.getDay()] + ', ' + n.getDate() + ' ' + MESES[n.getMonth()] +
            '  ·  ' + p(n.getHours()) + ':' + p(n.getMinutes()) + ':' + p(n.getSeconds());
    }
    tick(); setInterval(tick, 1000);

    /* ── Toast ── */
    function showToast(msg) {
        const t = document.getElementById('toast');
        document.getElementById('toastMsg').textContent = msg;
        t.classList.add('show');
        setTimeout(() => t.classList.remove('show'), 2800);
    }

    /* ── Modal ── */
    const overlay = document.getElementById('modalOverlay');
    function openModal() {
        if (navigator.vibrate) navigator.vibrate([40, 20, 80]);
        overlay.classList.add('open');
        document.body.style.overflow = 'hidden';
        document.getElementById('closeModal').focus();
    }
    function closeModal() {
        overlay.classList.remove('open');
        document.body.style.overflow = '';
    }

    /* Botón emergencia en hero */
    const btnEmg = document.getElementById('btnEmergencia');
    if (btnEmg) btnEmg.addEventListener('click', openModal);

    /* Botón emergencia en nav */
    const btnNav = document.getElementById('btnEmgNav');
    if (btnNav) btnNav.addEventListener('click', openModal);

    document.getElementById('closeModal').addEventListener('click', closeModal);
    overlay.addEventListener('click', e => { if (e.target === overlay) closeModal(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

    /* Swipe down para cerrar en móvil */
    let mStartY = 0;
    overlay.addEventListener('touchstart', e => { mStartY = e.touches[0].clientY; }, { passive: true });
    overlay.addEventListener('touchend',   e => {
        if (e.changedTouches[0].clientY - mStartY > 80) closeModal();
    }, { passive: true });

    /* ── Pull-to-refresh ── */
    function showSkeleton() {
        const bar = document.getElementById('pullBar');
        if (!bar) return;
        bar.style.display = 'flex';
        setTimeout(() => {
            bar.style.display = 'none';
            window.location.reload();
        }, 1800);
    }
    let startY = 0, pulling = false;
    document.addEventListener('touchstart', e => {
        if (window.scrollY === 0) startY = e.touches[0].clientY;
    }, { passive: true });
    document.addEventListener('touchend', e => {
        if (startY && !pulling && (e.changedTouches[0].clientY - startY) > 80) {
            pulling = true;
            showSkeleton();
            setTimeout(() => pulling = false, 2200);
        }
        startY = 0;
    }, { passive: true });

    /* ── Bottom nav active state ── */
    document.querySelectorAll('.bn-item').forEach(el => {
        el.addEventListener('click', function () {
            document.querySelectorAll('.bn-item').forEach(x => x.classList.remove('active'));
            this.classList.add('active');
        });
    });

})();
</script>

</body>
</html>