<?php
/**
 * index_manifiesto.php — Selección de Manifiesto
 * Hochschild Mining · Sistema Integral de Transporte
 * Diseño coherente con dashboard v8.0
 */

session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit();
}

require_once __DIR__ . "/config.php";
$mysqli->set_charset("utf8mb4");

function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

$usuario_sesion = $_SESSION['usuario'];
$nombre_sesion  = $_SESSION['nombre'] ?? $usuario_sesion;

$partes    = explode(' ', trim($nombre_sesion));
$iniciales = strtoupper(substr($partes[0],0,1) . (isset($partes[1]) ? substr($partes[1],0,1) : ''));

const EMPRESAS = [
    'DAJOR'    => ['nombre' => 'Dajor Sur',    'logo' => 'Logos Transporte/dajor.png',    'rutas' => ['AREQUIPA'],                          'color' => '#DC2626', 'bg' => '#FEF2F2'],
    'CALEB'    => ['nombre' => 'Trans. Caleb', 'logo' => 'Logos Transporte/caleb.png',    'rutas' => ['CUSCO','ESPINAR','JULIACA','ABANCAY'], 'color' => '#D97706', 'bg' => '#FFFBEB'],
    'TPP'      => ['nombre' => 'TPP S.A.C.',   'logo' => 'Logos Transporte/tpp.png',      'rutas' => ['LIMA'],                               'color' => '#16A34A', 'bg' => '#F0FDF4'],
    'NEW_ROAD' => ['nombre' => 'New Road',     'logo' => 'Logos Transporte/new_road.png', 'rutas' => ['COMUNIDADES'],                        'color' => '#1D4ED8', 'bg' => '#EFF6FF'],
];

function detectarEmpresa(string $bus): array {
    $b = strtoupper($bus);
    if      (preg_match('/DAJOR|AREQUIPA/i', $b))                return ['empresa' => 'DAJOR',    'ciudad' => 'AREQUIPA'];
    elseif  (preg_match('/TPP|LIMA/i', $b))                      return ['empresa' => 'TPP',      'ciudad' => 'LIMA'];
    elseif  (preg_match('/ANIZO|OYOLO|PAUSA/i', $b))             return ['empresa' => 'NEW_ROAD', 'ciudad' => 'COMUNIDADES'];
    elseif  (preg_match('/CALEB|CUZCO|CUSCO/i', $b))             return ['empresa' => 'CALEB',    'ciudad' => 'CUSCO'];
    elseif  (preg_match('/ESPINAR/i', $b))                       return ['empresa' => 'CALEB',    'ciudad' => 'ESPINAR'];
    elseif  (preg_match('/JULIACA/i', $b))                       return ['empresa' => 'CALEB',    'ciudad' => 'JULIACA'];
    elseif  (preg_match('/ABANCAY/i', $b))                       return ['empresa' => 'CALEB',    'ciudad' => 'ABANCAY'];
    return ['empresa' => 'OTRO', 'ciudad' => 'OTRO'];
}

$buses_db = [];
foreach (['lista_bajada' => 'bajada', 'lista_subida' => 'subida'] as $tabla => $tipo) {
    $result = $mysqli->query("SELECT DISTINCT bus FROM `$tabla` WHERE bus != '' ORDER BY bus");
    if (!$result) continue;
    while ($row = $result->fetch_assoc()) {
        $bus  = strtoupper(trim($row['bus']));
        $meta = detectarEmpresa($bus);
        $buses_db[] = ['nombre' => $bus, 'tipo' => $tipo, ...$meta];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="theme-color" content="#F4F4F4">
<title>Manifiesto · Hochschild Mining</title>
<link rel="icon" type="image/png" href="assets/logo.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">

<style>
/* ═══════════════════════════════════════
   TOKENS — idénticos al dashboard v8.0
═══════════════════════════════════════ */
:root {
    --g:      #C49A2C;
    --gd:     #8A6A14;
    --gl:     #FBF6E8;
    --gr:     rgba(196,154,44,.15);
    --g-grad: linear-gradient(135deg,#7A5A0E,#C49A2C,#E8C85A);

    --ink:    #0A0A0A;
    --ink2:   #1A1A1A;
    --ink3:   #3A3A3A;
    --ink4:   #666666;
    --ink5:   #999999;
    --ink6:   #BBBBBB;

    --bg:     #F4F4F4;
    --surf:   #FFFFFF;
    --surf2:  #FAFAFA;

    --b:      rgba(0,0,0,.08);
    --b2:     rgba(0,0,0,.05);
    --b-gold: rgba(196,154,44,.3);

    --red:    #DC2626;
    --rb:     #FEF2F2;
    --rbo:    rgba(252,165,165,.4);
    --green:  #16A34A;
    --gb:     #F0FDF4;
    --gbo:    #86EFAC;

    --glass:  rgba(255,255,255,.95);

    --font:   'Inter', system-ui, sans-serif;
    --mono:   'JetBrains Mono', monospace;

    --r-sm:   8px;
    --r-md:   12px;
    --r-lg:   16px;
    --r-xl:   20px;
    --r-2xl:  24px;

    --safe-t: env(safe-area-inset-top, 0px);
    --safe-b: env(safe-area-inset-bottom, 0px);
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { height: 100%; scroll-behavior: smooth; -webkit-text-size-adjust: 100%; }
body {
    font-family: var(--font);
    font-size: 14px;
    line-height: 1.5;
    background: var(--bg);
    color: var(--ink);
    min-height: 100svh;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
    -webkit-tap-highlight-color: transparent;
    overflow-x: hidden;
}
a { text-decoration: none; color: inherit; }
button { font-family: var(--font); border: none; cursor: pointer; background: none; }
img { display: block; max-width: 100%; }
::-webkit-scrollbar { width: 4px; height: 4px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: #E8E8E8; border-radius: 4px; }

/* ═══════════════════════════════════════
   TOPBAR — copia exacta del dashboard
═══════════════════════════════════════ */
.topbar {
    position: sticky; top: 0; z-index: 200;
    background: rgba(255,255,255,.97);
    backdrop-filter: blur(24px);
    -webkit-backdrop-filter: blur(24px);
    border-bottom: 1px solid rgba(0,0,0,.07);
    padding-top: var(--safe-t);
    box-shadow: 0 1px 0 #fff, 0 2px 16px rgba(0,0,0,.05);
}
.topbar-inner {
    max-width: 480px; margin: 0 auto;
    padding: 0 16px; height: 66px;
    display: flex; align-items: center; gap: 0;
}
.logo-block { display: flex; align-items: center; gap: 11px; flex-shrink: 0; }
.logo-marks { display: flex; flex-direction: column; gap: 3px; }
.logo-marks span:first-child  { display: block; width: 28px; height: 2.5px; background: linear-gradient(90deg,#0A0A0A,#C49A2C); border-radius: 1px; }
.logo-marks span:last-child   { display: block; width: 16px; height: 1.5px; background: rgba(0,0,0,.2); border-radius: 1px; }
.logo-img   { height: 52px; width: auto; object-fit: contain; display: none; }
.logo-sep   { width: 1px; height: 32px; background: rgba(0,0,0,.1); margin: 0 4px; }
.logo-wordmark { display: flex; flex-direction: column; justify-content: center; }
.logo-name  { font-size: 12px; font-weight: 900; color: var(--ink); letter-spacing: 5px; text-transform: uppercase; line-height: 1; }
.logo-sub   { font-size: 6.5px; font-weight: 500; color: var(--ink5); letter-spacing: 2px; text-transform: uppercase; margin-top: 3.5px; }
.top-space  { flex: 1; }
.top-clock  { font-family: var(--mono); font-size: 10px; font-weight: 500; color: var(--ink4); background: var(--surf2); border: 1px solid var(--b); border-radius: var(--r-sm); padding: 5px 10px; margin-right: 8px; letter-spacing: .3px; }
.top-live   { display: flex; align-items: center; gap: 5px; background: var(--gb); border: 1px solid var(--gbo); border-radius: 99px; padding: 5px 11px; margin-right: 10px; }
.top-live-dot { width: 6px; height: 6px; border-radius: 50%; background: #22C55E; box-shadow: 0 0 7px rgba(34,197,94,.7); animation: live-pulse 2s ease-in-out infinite; }
@keyframes live-pulse {
    0%,100% { opacity:1; box-shadow: 0 0 6px rgba(34,197,94,.7); }
    50%      { opacity:.6; box-shadow: 0 0 12px rgba(34,197,94,.2), 0 0 0 4px rgba(34,197,94,.08); }
}
.top-live-txt { font-size: 7.5px; font-weight: 700; color: var(--green); letter-spacing: .6px; text-transform: uppercase; }
.top-av { width: 36px; height: 36px; border-radius: 50%; background: var(--ink); display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 800; color: #fff; box-shadow: 0 2px 8px rgba(0,0,0,.2); flex-shrink: 0; border: 2px solid var(--g); }
.top-back { display: flex; align-items: center; gap: 5px; font-size: 11px; font-weight: 500; color: var(--ink4); padding: 6px 12px; border: 1px solid var(--b); border-radius: var(--r-sm); background: var(--surf); margin-left: 8px; transition: all .2s; }
.top-back:hover { color: var(--ink); border-color: rgba(0,0,0,.15); }
.top-back svg { width: 13px; height: 13px; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; }

/* Línea dorada — idéntica al dashboard */
.topbar-gold {
    height: 2px;
    background: linear-gradient(90deg, #0A0A0A 0%, #C49A2C 20%, #E8C85A 50%, #C49A2C 80%, transparent 100%);
    opacity: .7;
}

/* ═══════════════════════════════════════
   PULL INDICATOR
═══════════════════════════════════════ */
.pull-bar { display: none; align-items: center; justify-content: center; gap: 10px; padding: 10px; font-size: 11.5px; font-weight: 600; color: var(--gd); background: var(--gl); border-bottom: 1px solid var(--gr); }
.pull-spin { width: 16px; height: 16px; border: 2px solid var(--gr); border-top-color: var(--g); border-radius: 50%; animation: spin .8s linear infinite; flex-shrink: 0; }
@keyframes spin { to { transform: rotate(360deg); } }

/* ═══════════════════════════════════════
   MAIN WRAP
═══════════════════════════════════════ */
.main-wrap {
    max-width: 480px; margin: 0 auto;
    padding: 18px 14px 40px;
    display: flex; flex-direction: column; gap: 12px;
}

/* ═══════════════════════════════════════
   HERO — mismo estilo que dashboard
═══════════════════════════════════════ */
.hero {
    background: var(--surf);
    border: 1px solid rgba(0,0,0,.07);
    border-radius: var(--r-2xl);
    padding: 18px 20px;
    position: relative; overflow: hidden;
    box-shadow: 0 2px 16px rgba(0,0,0,.06), 0 1px 0 #fff inset;
}
.hero::before {
    content: '';
    position: absolute; top: 0; left: 0; right: 0; height: 2px;
    background: linear-gradient(90deg, #0A0A0A 0%, #C49A2C 35%, #E8C85A 60%, transparent 100%);
    border-radius: var(--r-2xl) var(--r-2xl) 0 0;
}
.hero-eyebrow { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; }
.hero-eyebrow-bar { width: 16px; height: 2px; background: var(--g); border-radius: 1px; }
.hero-eyebrow-txt { font-size: 8px; font-weight: 700; letter-spacing: 2.5px; color: var(--g); text-transform: uppercase; }
.hero-title { font-size: 26px; font-weight: 900; color: var(--ink); letter-spacing: -1px; line-height: 1.05; margin-bottom: 4px; }
.hero-sub   { font-size: 11px; color: var(--ink5); font-weight: 400; margin-bottom: 14px; }

/* Selector de fecha en el hero */
.hero-date-row { display: flex; align-items: center; justify-content: space-between; }
.hero-date-chip {
    display: inline-flex; align-items: center; gap: 6px;
    background: var(--surf2); border: 1px solid var(--b);
    border-radius: var(--r-sm); padding: 5px 11px;
    font-size: 11px; font-weight: 600; color: var(--ink3);
    position: relative; cursor: pointer;
    transition: border-color .2s;
}
.hero-date-chip:hover { border-color: var(--b-gold); color: var(--gd); }
.hero-date-chip svg { width: 11px; height: 11px; stroke: var(--g); fill: none; stroke-width: 2; stroke-linecap: round; flex-shrink: 0; }
.hero-date-chip input[type="date"] { position: absolute; opacity: 0; inset: 0; cursor: pointer; width: 100%; height: 100%; border: none; outline: none; }

.hero-date-display { font-family: var(--mono); font-size: 22px; font-weight: 600; color: var(--ink); letter-spacing: -.5px; }
.hero-date-display span { font-size: 12px; font-weight: 500; color: var(--ink5); margin-left: 4px; }

/* ═══════════════════════════════════════
   STEPPER — pill compacto
═══════════════════════════════════════ */
.stepper-wrap {
    background: var(--surf); border: 1px solid rgba(0,0,0,.07);
    border-radius: var(--r-xl); padding: 12px 16px;
    box-shadow: 0 1px 4px rgba(0,0,0,.04);
    display: flex; align-items: center; gap: 0;
}
.s-dot {
    width: 26px; height: 26px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 11px; font-weight: 700;
    background: var(--surf2); color: var(--ink5);
    border: 1px solid var(--b); flex-shrink: 0;
    transition: all .25s;
}
.s-dot.active { background: var(--ink); color: #fff; border-color: var(--ink); box-shadow: 0 2px 8px rgba(0,0,0,.2); }
.s-dot.done   { background: var(--gl); color: var(--gd); border-color: var(--b-gold); }
.s-line { flex: 1; height: 1px; background: var(--b); transition: background .3s; }
.s-line.done { background: var(--b-gold); }
.s-labels { display: flex; justify-content: space-between; padding: 6px 4px 0; }
.s-lbl { font-size: 8.5px; font-weight: 600; letter-spacing: .5px; text-transform: uppercase; color: var(--ink5); text-align: center; width: 26px; transition: color .25s; }
.s-lbl.active { color: var(--ink); }

/* ═══════════════════════════════════════
   SECTION ROW — mismo que dashboard
═══════════════════════════════════════ */
.sec-row { display: flex; align-items: center; gap: 8px; padding: 0 2px; }
.sec-txt { font-size: 9px; font-weight: 800; letter-spacing: 1.4px; text-transform: uppercase; color: var(--ink); white-space: nowrap; }
.sec-line { flex: 1; height: 1px; background: rgba(0,0,0,.1); }

/* ═══════════════════════════════════════
   PASOS
═══════════════════════════════════════ */
.step { display: none; }
.step.active { display: flex; flex-direction: column; gap: 12px; animation: fi .32s cubic-bezier(.34,1.1,.64,1) both; }

/* ═══════════════════════════════════════
   BACK BUTTON — misma estética que top-back
═══════════════════════════════════════ */
.back-btn {
    display: inline-flex; align-items: center; gap: 6px;
    font-size: 11px; font-weight: 600; color: var(--ink4);
    padding: 7px 13px; border: 1px solid var(--b);
    border-radius: var(--r-sm); background: var(--surf);
    cursor: pointer; width: fit-content;
    transition: all .2s;
}
.back-btn:hover { color: var(--ink); border-color: rgba(0,0,0,.15); }
.back-btn svg { width: 12px; height: 12px; stroke: currentColor; fill: none; stroke-width: 2.5; stroke-linecap: round; flex-shrink: 0; }

/* Empresa pill — contexto del paso actual */
.emp-pill {
    display: inline-flex; align-items: center; gap: 8px;
    background: var(--gl); border: 1px solid var(--b-gold);
    border-radius: 99px; padding: 5px 12px 5px 6px;
    width: fit-content;
}
.emp-pill-logo { width: 22px; height: 22px; border-radius: 50%; background: var(--surf); border: 1px solid var(--b); display: flex; align-items: center; justify-content: center; overflow: hidden; padding: 2px; }
.emp-pill-logo img { max-width: 100%; max-height: 100%; object-fit: contain; }
.emp-pill-name { font-size: 10px; font-weight: 700; color: var(--gd); letter-spacing: .3px; }

/* ═══════════════════════════════════════
   PASO 1: TRANSPORTISTAS
   Tarjetas = misma familia que .mcard
═══════════════════════════════════════ */
.carriers-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }

.carrier-card {
    background: var(--surf); border: 1px solid rgba(0,0,0,.07);
    border-radius: var(--r-xl); overflow: hidden;
    display: flex; flex-direction: column;
    cursor: pointer; position: relative;
    box-shadow: 0 1px 4px rgba(0,0,0,.04);
    transition: transform .22s, box-shadow .22s, border-color .22s;
}
.carrier-card:hover  { transform: translateY(-2px); box-shadow: 0 4px 16px rgba(196,154,44,.12); border-color: var(--b-gold); }
.carrier-card:active { transform: scale(.97); }
.carrier-card.selected { border-color: var(--g); box-shadow: 0 4px 16px rgba(196,154,44,.18); }

/* Acento lateral izquierdo — igual que .mcard-accent */
.carrier-accent {
    position: absolute; left: 0; top: 0; bottom: 0; width: 3px;
    border-radius: var(--r-xl) 0 0 var(--r-xl);
    transition: opacity .2s; opacity: 0;
}
.carrier-card:hover .carrier-accent,
.carrier-card.selected .carrier-accent { opacity: 1; }

.carrier-body { padding: 14px 13px 13px 16px; display: flex; flex-direction: column; align-items: flex-start; gap: 10px; }

.carrier-logo {
    width: 100%; height: 38px;
    display: flex; align-items: center; justify-content: flex-start;
}
.carrier-logo img { max-height: 34px; max-width: 90%; object-fit: contain; }
.carrier-logo-fb { font-size: 11px; font-weight: 800; color: var(--ink3); }

.carrier-info { flex: 1; }
.carrier-name { font-size: 12px; font-weight: 700; color: var(--ink); line-height: 1.2; }
.carrier-routes { font-size: 9.5px; color: var(--ink5); margin-top: 2px; }

.carrier-footer {
    width: 100%; display: flex; align-items: center; justify-content: space-between;
    border-top: 1px solid var(--b); padding-top: 9px; margin-top: 2px;
}
.carrier-buses-count { font-family: var(--mono); font-size: 9px; color: var(--ink5); font-weight: 500; }
.carrier-arrow { font-size: 18px; color: #D8D4CE; line-height: 1; transition: color .2s, transform .2s; }
.carrier-card:hover .carrier-arrow { color: var(--g); transform: translateX(2px); }

/* Check badge */
.carrier-check {
    position: absolute; top: 9px; right: 9px;
    width: 18px; height: 18px; border-radius: 50%;
    background: var(--g); display: flex; align-items: center; justify-content: center;
    opacity: 0; transform: scale(.3);
    transition: all .22s cubic-bezier(.34,1.56,.64,1);
}
.carrier-card.selected .carrier-check { opacity: 1; transform: scale(1); }
.carrier-check svg { width: 9px; height: 9px; stroke: #fff; fill: none; stroke-width: 3; stroke-linecap: round; stroke-linejoin: round; }

/* ═══════════════════════════════════════
   PASO 2: DIRECCIÓN — mcard style
═══════════════════════════════════════ */
.dir-list { display: flex; flex-direction: column; gap: 7px; }

.dir-card {
    background: var(--surf); border: 1px solid rgba(0,0,0,.07);
    border-radius: var(--r-xl); display: flex; overflow: hidden;
    cursor: pointer;
    box-shadow: 0 1px 4px rgba(0,0,0,.04);
    transition: transform .22s, box-shadow .22s, border-color .22s;
}
.dir-card:hover  { border-color: var(--b-gold); box-shadow: 0 4px 16px rgba(196,154,44,.12); transform: translateX(2px); }
.dir-card:active { transform: scale(.98); }
.dir-accent { width: 3px; flex-shrink: 0; border-radius: var(--r-xl) 0 0 var(--r-xl); }
.dir-body   { flex: 1; padding: 14px 14px; display: flex; align-items: center; gap: 13px; }
.dir-icon   { width: 42px; height: 42px; border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; box-shadow: 0 1px 4px rgba(0,0,0,.07); }
.dir-icon svg { width: 20px; height: 20px; fill: none; stroke-width: 2.5; stroke-linecap: round; }
.dir-text { flex: 1; min-width: 0; }
.dir-title { font-size: 13px; font-weight: 700; color: var(--ink); }
.dir-sub   { font-size: 10px; color: var(--ink4); margin-top: 2px; }
.dir-arr   { font-size: 20px; color: #D8D4CE; line-height: 1; padding-right: 4px; transition: color .2s, transform .2s; }
.dir-card:hover .dir-arr { color: var(--g); transform: translateX(3px); }

/* ═══════════════════════════════════════
   PASO 3: CIUDADES
═══════════════════════════════════════ */
.cities-list { display: flex; flex-direction: column; gap: 7px; }

.city-card {
    background: var(--surf); border: 1px solid rgba(0,0,0,.07);
    border-radius: var(--r-xl); display: flex; overflow: hidden;
    cursor: pointer;
    box-shadow: 0 1px 4px rgba(0,0,0,.04);
    transition: transform .22s, box-shadow .22s, border-color .22s;
}
.city-card:hover  { border-color: var(--b-gold); box-shadow: 0 4px 16px rgba(196,154,44,.12); transform: translateX(2px); }
.city-card:active { transform: scale(.98); }
.city-accent  { width: 3px; flex-shrink: 0; border-radius: var(--r-xl) 0 0 var(--r-xl); background: var(--g); }
.city-body    { flex: 1; padding: 13px 14px; display: flex; align-items: center; gap: 12px; }
.city-ico-box { width: 40px; height: 40px; border-radius: 11px; background: var(--gl); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.city-ico-box svg { width: 18px; height: 18px; stroke: var(--gd); fill: none; stroke-width: 2; stroke-linecap: round; }
.city-text  { flex: 1; min-width: 0; }
.city-name  { font-size: 13px; font-weight: 700; color: var(--ink); }
.city-sub   { font-size: 10px; color: var(--ink4); margin-top: 2px; }
.city-badge { font-size: 9px; font-weight: 700; background: var(--gl); color: var(--gd); border: 1px solid var(--b-gold); padding: 3px 9px; border-radius: 6px; white-space: nowrap; flex-shrink: 0; margin-right: 4px; }

/* ═══════════════════════════════════════
   PASO 4: BÚSQUEDA + BUSES
═══════════════════════════════════════ */
.search-row { position: relative; }
.search-input {
    width: 100%; background: var(--surf);
    border: 1px solid rgba(0,0,0,.09); border-radius: var(--r-lg);
    padding: 11px 14px 11px 40px;
    font-family: var(--font); font-size: 13px; color: var(--ink); outline: none;
    transition: border-color .2s, box-shadow .2s;
    box-shadow: 0 1px 4px rgba(0,0,0,.04);
}
.search-input::placeholder { color: var(--ink6); }
.search-input:focus { border-color: var(--b-gold); box-shadow: 0 0 0 3px var(--gr); }
.search-icon { position: absolute; left: 13px; top: 50%; transform: translateY(-50%); pointer-events: none; }
.search-icon svg { width: 15px; height: 15px; stroke: var(--ink5); fill: none; stroke-width: 2; stroke-linecap: round; }

.buses-list { display: flex; flex-direction: column; gap: 7px; }

/* Bus card = .mcard exacto */
.bus-card {
    background: var(--surf); border: 1px solid rgba(0,0,0,.07);
    border-radius: var(--r-xl); display: flex; overflow: hidden;
    text-decoration: none; cursor: pointer;
    box-shadow: 0 1px 4px rgba(0,0,0,.04);
    transition: transform .22s, box-shadow .22s, border-color .22s;
}
.bus-card:hover  { border-color: var(--b-gold); box-shadow: 0 4px 16px rgba(196,154,44,.12); transform: translateX(2px); }
.bus-card:active { transform: scale(.98); }
.bus-accent { width: 3px; flex-shrink: 0; border-radius: var(--r-xl) 0 0 var(--r-xl); background: linear-gradient(180deg,#1D4ED8,rgba(29,78,216,.15)); }
.bus-body   { flex: 1; padding: 11px 13px; display: flex; align-items: center; gap: 12px; }
.bus-ico    { width: 40px; height: 40px; border-radius: 12px; background: rgba(235,243,255,.9); display: flex; align-items: center; justify-content: center; flex-shrink: 0; box-shadow: 0 1px 4px rgba(0,0,0,.07); }
.bus-ico svg { width: 22px; height: 22px; stroke: #1D4ED8; fill: none; stroke-width: 1.8; stroke-linecap: round; }
.bus-text  { flex: 1; min-width: 0; }
.bus-name  { font-size: 12.5px; font-weight: 700; color: var(--ink); line-height: 1.25; }
.bus-ruta  { font-size: 10px; color: var(--ink4); margin-top: 2px; }
.bus-arr   { font-size: 20px; color: #D8D4CE; padding-right: 4px; line-height: 1; transition: color .2s, transform .2s; }
.bus-card:hover .bus-arr { color: var(--g); transform: translateX(3px); }

.empty-state { padding: 36px 20px; text-align: center; color: var(--ink5); }
.empty-state svg { width: 32px; height: 32px; stroke: var(--ink6); fill: none; stroke-width: 1.5; stroke-linecap: round; margin: 0 auto 10px; opacity: .5; }
.empty-state p { font-size: 13px; }

/* ═══════════════════════════════════════
   FOOTER
═══════════════════════════════════════ */
.page-foot { font-size: 11px; color: var(--ink5); text-align: center; padding: 10px 0 4px; border-top: 1px solid rgba(0,0,0,.06); }

/* ═══════════════════════════════════════
   BOTTOM NAV — mismo que dashboard
═══════════════════════════════════════ */
.bottom-nav { display: none; }

/* ═══════════════════════════════════════
   ANIMACIONES
═══════════════════════════════════════ */
.fi { opacity: 0; transform: translateY(12px) scale(.99); animation: fi .5s cubic-bezier(.34,1.1,.64,1) forwards; }
@keyframes fi { to { opacity: 1; transform: translateY(0) scale(1); } }
.d1{animation-delay:.04s} .d2{animation-delay:.10s} .d3{animation-delay:.16s} .d4{animation-delay:.22s} .d5{animation-delay:.28s}

/* ═══════════════════════════════════════
   RESPONSIVE
═══════════════════════════════════════ */
@media (max-width: 768px) {
    .top-clock { display: none; }
    .logo-sub  { display: none; }
}
@media (max-width: 480px) {
    .topbar-inner { padding: 0 14px; height: 54px; }
    .logo-marks   { display: none; }
    .logo-name    { font-size: 9.5px; letter-spacing: 3.5px; }
    .logo-sub     { display: none; }
    .top-clock    { display: none; }

    .main-wrap { padding: 14px 12px calc(80px + var(--safe-b)); gap: 10px; }

    .bottom-nav {
        display: flex; position: fixed; bottom: 0; left: 0; right: 0; z-index: 300;
        background: rgba(255,255,255,.97);
        backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
        border-top: 1px solid rgba(0,0,0,.07);
        padding: 8px 6px calc(8px + var(--safe-b));
        align-items: center; justify-content: space-around;
        box-shadow: 0 -4px 24px rgba(0,0,0,.06);
    }
    .bn-item { display: flex; flex-direction: column; align-items: center; gap: 3px; cursor: pointer; padding: 5px 10px; border-radius: 10px; flex: 1; transition: background .12s; }
    .bn-item:active { background: rgba(0,0,0,.04); transform: scale(.94); }
    .bn-ico { width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; }
    .bn-ico svg { width: 22px; height: 22px; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; transition: stroke .15s; }
    .bn-lbl { font-size: 10px; font-weight: 600; transition: color .15s; }
    .bn-item.active .bn-ico svg { stroke: var(--g); filter: drop-shadow(0 0 4px rgba(196,154,44,.4)); }
    .bn-item.active .bn-lbl    { color: var(--g); }
    .bn-item:not(.active) .bn-ico svg { stroke: var(--ink5); }
    .bn-item:not(.active) .bn-lbl    { color: var(--ink5); }
}

@media (prefers-reduced-motion: reduce) {
    .fi { animation: none; opacity: 1; transform: none; }
}
</style>
</head>
<body>

<!-- PULL INDICATOR -->
<div class="pull-bar" id="pullBar">
    <div class="pull-spin"></div>
    Actualizando datos…
</div>

<!-- ══ TOPBAR ══ -->
<header class="topbar">
    <div class="topbar-inner">
        <div class="logo-block">
            <img class="logo-img" src="assets/logo.png" alt="Hochschild Mining"
                 onload="this.style.display='block';this.nextElementSibling.style.display='none';this.nextElementSibling.nextElementSibling.style.display='none'"
                 onerror="this.style.display='none'">
            <div class="logo-marks"><span></span><span></span></div>
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

        <a href="dashboard.php" class="top-back">
            <svg viewBox="0 0 24 24"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
            Dashboard
        </a>
    </div>
    <div class="topbar-gold"></div>
</header>

<!-- ══ MAIN ══ -->
<main class="main-wrap">

    <!-- ── HERO ── -->
    <section class="hero fi d1">
        <div class="hero-eyebrow">
            <div class="hero-eyebrow-bar"></div>
            <span class="hero-eyebrow-txt">Control de transporte</span>
        </div>
        <h1 class="hero-title">Manifiestos<br>de Pasajeros</h1>
        <p class="hero-sub">Selecciona empresa, dirección, destino y unidad</p>

        <div class="hero-date-row">
            <div class="hero-date-display" id="date-display">
                <?= date('d') ?><span><?= date('M Y') ?></span>
            </div>
            <div class="hero-date-chip">
                <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                <span id="date-chip-txt"><?= date('d M Y') ?></span>
                <input type="date" id="fecha_viaje" value="<?= date('Y-m-d') ?>">
            </div>
        </div>
    </section>

    <!-- ── STEPPER ── -->
    <div class="fi d2">
        <div class="stepper-wrap">
            <div class="s-dot active" id="dot-1">1</div>
            <div class="s-line"       id="line-1"></div>
            <div class="s-dot"        id="dot-2">2</div>
            <div class="s-line"       id="line-2"></div>
            <div class="s-dot"        id="dot-3">3</div>
            <div class="s-line"       id="line-3"></div>
            <div class="s-dot"        id="dot-4">4</div>
        </div>
        <div class="s-labels" style="padding:0 16px;">
            <span class="s-lbl active" id="lbl-1">Empresa</span>
            <span class="s-lbl"        id="lbl-2">Dirección</span>
            <span class="s-lbl"        id="lbl-3">Destino</span>
            <span class="s-lbl"        id="lbl-4">Unidad</span>
        </div>
    </div>

    <!-- ══ PASO 1: TRANSPORTISTA ══ -->
    <div id="paso-1" class="step active fi d3">
        <div class="sec-row">
            <span class="sec-txt">Transportistas</span>
            <div class="sec-line"></div>
        </div>
        <div class="carriers-grid">
            <?php foreach (EMPRESAS as $key => $emp):
                $cnt = count(array_filter($buses_db, fn($b) => $b['empresa'] === $key)); ?>
            <div class="carrier-card" id="carrier-<?= $key ?>" onclick="selectEmpresa('<?= $key ?>')">
                <div class="carrier-check">
                    <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                </div>
                <div class="carrier-accent" style="background:linear-gradient(180deg,<?= h($emp['color']) ?>,rgba(<?= implode(',', sscanf($emp['color'], '#%02x%02x%02x')) ?>, .15))"></div>
                <div class="carrier-body">
                    <div class="carrier-logo">
                        <img src="<?= h($emp['logo']) ?>" alt="<?= h($emp['nombre']) ?>"
                             onerror="this.outerHTML='<span class=\'carrier-logo-fb\'><?= h($key) ?></span>'">
                    </div>
                    <div class="carrier-info">
                        <div class="carrier-name"><?= h($emp['nombre']) ?></div>
                        <div class="carrier-routes"><?= implode(' · ', $emp['rutas']) ?></div>
                    </div>
                    <div class="carrier-footer">
                        <span class="carrier-buses-count"><?= $cnt ?> unidad<?= $cnt !== 1 ? 'es' : '' ?></span>
                        <span class="carrier-arrow">›</span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ══ PASO 2: DIRECCIÓN ══ -->
    <div id="paso-2" class="step">
        <button class="back-btn" onclick="irPaso(1)">
            <svg viewBox="0 0 24 24"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
            Cambiar transportista
        </button>
        <div id="pill-2" class="emp-pill"></div>
        <div class="sec-row">
            <span class="sec-txt">Dirección del viaje</span>
            <div class="sec-line"></div>
        </div>
        <div class="dir-list">
            <div class="dir-card" onclick="selectTipo('subida')">
                <div class="dir-accent" style="background:linear-gradient(180deg,#16A34A,rgba(22,163,74,.15))"></div>
                <div class="dir-body">
                    <div class="dir-icon" style="background:#F0FDF4;">
                        <svg viewBox="0 0 24 24" style="stroke:#16A34A"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></svg>
                    </div>
                    <div class="dir-text">
                        <div class="dir-title">SUBIDA</div>
                        <div class="dir-sub">Ingreso a operaciones</div>
                    </div>
                    <span class="dir-arr">›</span>
                </div>
            </div>
            <div class="dir-card" onclick="selectTipo('bajada')">
                <div class="dir-accent" style="background:linear-gradient(180deg,#DC2626,rgba(220,38,38,.15))"></div>
                <div class="dir-body">
                    <div class="dir-icon" style="background:#FEF2F2;">
                        <svg viewBox="0 0 24 24" style="stroke:#DC2626"><line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/></svg>
                    </div>
                    <div class="dir-text">
                        <div class="dir-title">BAJADA</div>
                        <div class="dir-sub">Salida de operaciones</div>
                    </div>
                    <span class="dir-arr">›</span>
                </div>
            </div>
        </div>
    </div>

    <!-- ══ PASO 3: DESTINO ══ -->
    <div id="paso-3" class="step">
        <button class="back-btn" onclick="irPaso(2)">
            <svg viewBox="0 0 24 24"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
            Cambiar dirección
        </button>
        <div id="pill-3" class="emp-pill"></div>
        <div class="sec-row">
            <span class="sec-txt">Seleccionar destino</span>
            <div class="sec-line"></div>
        </div>
        <div class="cities-list" id="lista-ciudades"></div>
    </div>

    <!-- ══ PASO 4: UNIDAD ══ -->
    <div id="paso-4" class="step">
        <button class="back-btn" onclick="irPaso(3)">
            <svg viewBox="0 0 24 24"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
            Cambiar destino
        </button>
        <div id="pill-4" class="emp-pill"></div>
        <div class="sec-row">
            <span class="sec-txt">Seleccionar unidad</span>
            <div class="sec-line"></div>
        </div>
        <div class="search-row">
            <input class="search-input" id="search-bus" type="text"
                   placeholder="Buscar placa o número de unidad…"
                   oninput="filtrarBuses()">
            <span class="search-icon">
                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            </span>
        </div>
        <div class="buses-list" id="lista-buses"></div>
    </div>

    <div class="page-foot">© <?= date('Y') ?> Hochschild Mining · Sistema Integral de Transporte</div>

</main>

<!-- ══ BOTTOM NAV ══ -->
<nav class="bottom-nav" id="bottomNav">
    <div class="bn-item" onclick="window.location='dashboard.php'">
        <div class="bn-ico">
            <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
        </div>
        <span class="bn-lbl">Inicio</span>
    </div>
    <div class="bn-item" onclick="window.location='buses.php'">
        <div class="bn-ico">
            <svg viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="3"/><path d="M2 9.5h20M8 5v4.5M16 5v4.5"/><circle cx="6.5" cy="15" r="1.5" fill="currentColor" stroke="none"/><circle cx="17.5" cy="15" r="1.5" fill="currentColor" stroke="none"/></svg>
        </div>
        <span class="bn-lbl">Buses</span>
    </div>
    <div class="bn-item active">
        <div class="bn-ico">
            <svg viewBox="0 0 24 24"><rect x="5" y="3" width="14" height="18" rx="2.5"/><path d="M9 2h6v3H9z"/><path d="M8.5 10.5h7M8.5 13.5h7M8.5 16.5h5" stroke-linecap="round"/></svg>
        </div>
        <span class="bn-lbl">Manifiestos</span>
    </div>
    <div class="bn-item" onclick="window.location='personal.php'">
        <div class="bn-ico">
            <svg viewBox="0 0 24 24"><circle cx="9" cy="7" r="4"/><path d="M2 21c0-3.3 3.1-6 7-6s7 2.7 7 6"/><circle cx="19" cy="8" r="2.5"/><path d="M22 21c0-2.2-1.3-4-3-4.5"/></svg>
        </div>
        <span class="bn-lbl">Personal</span>
    </div>
<div class="bn-item" onclick="window.location='https://hocmind.com/mina/monitoreoderuta_full.php'">
        <div class="bn-ico">
            <svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
        </div>
        <span class="bn-lbl">KPIs</span>
    </div>
</nav>

<script>
(function () {
/* ── Reloj ── */
const DIAS  = ['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'];
const MESES = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
function tick() {
    const n = new Date(), p = v => String(v).padStart(2,'0');
    const el = document.getElementById('clockEl');
    if (el) el.textContent = DIAS[n.getDay()] + ', ' + n.getDate() + ' ' + MESES[n.getMonth()] + '  ·  ' + p(n.getHours()) + ':' + p(n.getMinutes()) + ':' + p(n.getSeconds());
}
tick(); setInterval(tick, 1000);

/* ── Pull-to-refresh ── */
let startY = 0, pulling = false;
document.addEventListener('touchstart', e => { if (window.scrollY === 0) startY = e.touches[0].clientY; }, { passive: true });
document.addEventListener('touchend', e => {
    if (startY && !pulling && (e.changedTouches[0].clientY - startY) > 80) {
        pulling = true;
        const bar = document.getElementById('pullBar');
        if (bar) bar.style.display = 'flex';
        setTimeout(() => window.location.reload(), 1800);
    }
    startY = 0;
}, { passive: true });

/* ═══════════════════════════════
   DATOS
═══════════════════════════════ */
const EMPRESAS = <?= json_encode(EMPRESAS, JSON_UNESCAPED_UNICODE) ?>;
const BUSES    = <?= json_encode($buses_db, JSON_UNESCAPED_UNICODE) ?>;
const MESES_ES = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
const sel      = { empresa: '', tipo: '', ciudad: '' };
const LS_KEY   = 'hm_manifiesto_fecha';

/* ── FECHA ── */
function formatFecha(iso) {
    const [y, m, d] = iso.split('-');
    return `${parseInt(d)} ${MESES_ES[parseInt(m)-1]} ${y}`;
}
function sincFecha(val) {
    document.getElementById('fecha_viaje').value = val;
    document.getElementById('date-chip-txt').textContent = formatFecha(val);
    const [y, m, d] = val.split('-');
    document.getElementById('date-display').innerHTML = `${parseInt(d)}<span>${MESES_ES[parseInt(m)-1]} ${y}</span>`;
    localStorage.setItem(LS_KEY, val);
}
document.getElementById('fecha_viaje').addEventListener('change', e => sincFecha(e.target.value));
const saved = localStorage.getItem(LS_KEY);
if (saved) sincFecha(saved);

/* ── STEPPER ── */
function updateStepper(active) {
    ['Empresa','Dir.','Destino','Unidad'].forEach((_, i) => {
        const n = i + 1;
        const dot  = document.getElementById('dot-'  + n);
        const line = n < 4 ? document.getElementById('line-' + n) : null;
        const lbl  = document.getElementById('lbl-'  + n);
        dot.classList.remove('active','done');
        lbl.classList.remove('active');
        if (n < active)       { dot.classList.add('done');   if (line) line.classList.add('done'); }
        else if (n === active) { dot.classList.add('active'); lbl.classList.add('active'); if (line) line.classList.remove('done'); }
        else                  { if (line) line.classList.remove('done'); }
    });
}

/* ── NAVEGACIÓN ── */
function irPaso(n) {
    document.querySelectorAll('.step').forEach(el => el.classList.remove('active'));
    const paso = document.getElementById('paso-' + n);
    paso.classList.add('active');
    updateStepper(n);
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

/* ── PILLS ── */
function setPills(key) {
    const e   = EMPRESAS[key];
    const img = `<img src="${e.logo}" alt="${e.nombre}" onerror="this.style.display='none'">`;
    const html = `<div class="emp-pill-logo">${img}</div><span class="emp-pill-name">${e.nombre}</span>`;
    [2, 3, 4].forEach(n => {
        const el = document.getElementById('pill-' + n);
        if (el) el.innerHTML = html;
    });
}

/* ── PASO 1 ── */
function selectEmpresa(key) {
    document.querySelectorAll('.carrier-card').forEach(c => c.classList.remove('selected'));
    document.getElementById('carrier-' + key)?.classList.add('selected');
    sel.empresa = key;
    setPills(key);
    setTimeout(() => irPaso(2), 180);
}

/* ── PASO 2 ── */
function selectTipo(tipo) {
    sel.tipo = tipo;
    renderCiudades();
    irPaso(3);
}

/* ── PASO 3 ── */
function renderCiudades() {
    const cont  = document.getElementById('lista-ciudades');
    const rutas = EMPRESAS[sel.empresa]?.rutas ?? [];
    const dir   = sel.tipo === 'subida' ? 'Subida' : 'Bajada';
    cont.innerHTML = rutas.map(r => {
        const cnt = BUSES.filter(b => b.empresa === sel.empresa && b.tipo === sel.tipo && b.ciudad === r).length;
        return `
        <div class="city-card" onclick="selectCiudad('${r}')">
            <div class="city-accent"></div>
            <div class="city-body">
                <div class="city-ico-box">
                    <svg viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/><circle cx="12" cy="9" r="2.5"/></svg>
                </div>
                <div class="city-text">
                    <div class="city-name">${r}</div>
                    <div class="city-sub">${dir} · ${cnt} unidad${cnt !== 1 ? 'es' : ''}</div>
                </div>
                <span class="city-badge">${cnt}</span>
                <span style="font-size:20px;color:#D8D4CE;line-height:1;">›</span>
            </div>
        </div>`;
    }).join('');
}

function selectCiudad(ciudad) {
    sel.ciudad = ciudad;
    document.getElementById('search-bus').value = '';
    renderBuses();
    irPaso(4);
}

/* ── PASO 4 ── */
function renderBuses() {
    const cont  = document.getElementById('lista-buses');
    const fecha = document.getElementById('fecha_viaje').value;
    const lista = BUSES.filter(b => b.empresa === sel.empresa && b.tipo === sel.tipo && b.ciudad === sel.ciudad);
    if (!lista.length) {
        cont.innerHTML = `<div class="empty-state">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
            <p>Sin unidades programadas para esta selección.</p>
        </div>`;
        return;
    }
    cont.innerHTML = lista.map(b => {
        const url = `manifiesto.php?bus=${encodeURIComponent(b.nombre)}&tipo=${sel.tipo}&fecha=${fecha}`;
        return `
        <a class="bus-card bus-item" href="${url}" data-search="${b.nombre.toUpperCase()}">
            <div class="bus-accent"></div>
            <div class="bus-body">
                <div class="bus-ico">
                    <svg viewBox="0 0 24 24"><rect x="2" y="4" width="20" height="15" rx="3"/><path d="M2 9h20M8 4v5M16 4v5"/><circle cx="6.5" cy="15" r="1.7" fill="#1D4ED8" stroke="none"/><circle cx="17.5" cy="15" r="1.7" fill="#1D4ED8" stroke="none"/><path d="M9.5 15h5" stroke="#1D4ED8" stroke-width="1.8" stroke-linecap="round"/></svg>
                </div>
                <div class="bus-text">
                    <div class="bus-name">${b.nombre}</div>
                    <div class="bus-ruta">Destino: ${sel.ciudad}</div>
                </div>
                <span class="bus-arr">›</span>
            </div>
        </a>`;
    }).join('');
}

/* ── BÚSQUEDA ── */
function filtrarBuses() {
    const q = document.getElementById('search-bus').value.toUpperCase();
    document.querySelectorAll('.bus-item').forEach(el => {
        el.style.display = el.dataset.search.includes(q) ? 'flex' : 'none';
    });
}

/* Exponer al HTML */
window.selectEmpresa  = selectEmpresa;
window.selectTipo     = selectTipo;
window.selectCiudad   = selectCiudad;
window.filtrarBuses   = filtrarBuses;
window.irPaso         = irPaso;

})();
</script>

</body>
</html>