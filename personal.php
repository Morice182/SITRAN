<?php
session_start();
require __DIR__ . "/config.php";

if (!isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit();
}

$rol_actual      = $_SESSION['rol'] ?? 'agente';
$nombre_sesion   = $_SESSION['nombre'] ?? $_SESSION['usuario'];
$partes          = explode(' ', trim($nombre_sesion));
$iniciales       = strtoupper(substr($partes[0],0,1).(isset($partes[1])?substr($partes[1],0,1):''));

$q = isset($_GET["q"]) ? trim($_GET["q"]) : "";

$edit = null;
if (isset($_GET["dni"]) && $_GET["dni"] !== "") {
    $dniEdit = trim($_GET["dni"]);
    $st = $mysqli->prepare("SELECT * FROM personal WHERE dni=? LIMIT 1");
    $st->bind_param("s", $dniEdit);
    $st->execute();
    $edit = $st->get_result()->fetch_assoc();
    $st->close();
}

$lista = [];
$total_personal   = 0;
$total_filtrado   = 0;
$filtro_guardia   = isset($_GET['guardia']) ? strtoupper(trim($_GET['guardia'])) : '';
$page             = max(1, (int)($_GET['page'] ?? 1));
$per_page         = 50;
$offset           = ($page - 1) * $per_page;

if ($rol_actual === 'administrador') {
    // Total general (sin filtros)
    $c = $mysqli->prepare("SELECT COUNT(*) FROM personal");
    $c->execute();
    $c->bind_result($total_personal);
    $c->fetch();
    $c->close();

    // Construir WHERE dinámico
    $where  = [];
    $params = [];
    $types  = '';

    if ($q !== '') {
        $where[]  = "(dni LIKE ? OR nombres LIKE ? OR apellidos LIKE ? OR empresa LIKE ?)";
        $lk       = "%$q%";
        $params   = array_merge($params, [$lk, $lk, $lk, $lk]);
        $types   .= 'ssss';
    }
    if ($filtro_guardia !== '') {
        // TRIM+UPPER para manejar valores con espacios como ' A', ' B', ' C'
        $where[]  = "UPPER(TRIM(GUARDIA)) = ?";
        $params[] = $filtro_guardia;
        $types   .= 's';
    }

    $where_sql = $where ? " WHERE " . implode(" AND ", $where) : "";

    // COUNT filtrado (para paginación)
    $csql  = "SELECT COUNT(*) FROM personal" . $where_sql;
    $cstmt = $mysqli->prepare($csql);
    if ($params) $cstmt->bind_param($types, ...$params);
    $cstmt->execute();
    $cstmt->bind_result($total_filtrado);
    $cstmt->fetch();
    $cstmt->close();

    $total_pages = max(1, (int)ceil($total_filtrado / $per_page));
    $page        = min($page, $total_pages);
    $offset      = ($page - 1) * $per_page;

    // Datos paginados
    $sql    = "SELECT * FROM personal" . $where_sql . " ORDER BY apellidos ASC LIMIT ? OFFSET ?";
    $pstmt  = $mysqli->prepare($sql);
    $p_all  = array_merge($params, [$per_page, $offset]);
    $t_all  = $types . 'ii';
    $pstmt->bind_param($t_all, ...$p_all);
    $pstmt->execute();
    $lista = $pstmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $pstmt->close();
}

if (!function_exists('h')) {
    function h($str) { return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8'); }
}

/* Genera 1-2 iniciales a partir de nombres+apellidos */
function initials($nombres, $apellidos) {
    $a = trim($apellidos ?? '');
    $n = trim($nombres  ?? '');
    $i = '';
    if ($a) $i .= strtoupper(mb_substr($a, 0, 1));
    if ($n) $i .= strtoupper(mb_substr($n, 0, 1));
    return $i ?: '?';
}

/* Clase CSS para badge de estado */
function estadoClass($est) {
    if (strpos($est,'PROCESO') !== false) return 'st-proceso';
    if (strpos($est,'VISITA')  !== false) return 'st-visita';
    return 'st-afiliado'; // AFILIADO = verde
}

/* Color de avatar basado en iniciales (deterministico) */
function avatarColor($str) {
    $palettes = [
        ['#1a3a2a','#2d6a4f'], // verde oscuro
        ['#1a2a3a','#1d4ed8'], // azul oscuro
        ['#3a1a1a','#b91c1c'], // rojo oscuro
        ['#2a1a3a','#6d28d9'], // morado oscuro
        ['#2a2a1a','#b45309'], // ámbar oscuro
        ['#1a3a38','#0d6e6e'], // teal oscuro
    ];
    $idx = crc32($str) % count($palettes);
    if ($idx < 0) $idx += count($palettes);
    return $palettes[$idx];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="theme-color" content="#FFFFFF">
    <title>Gestión de Personal · Hochschild Mining</title>
    <link rel="icon" type="image/png" href="assets/logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
/* ═══════════════════════════════════════
   BRAND TOKENS — idénticos al dashboard
═══════════════════════════════════════ */
:root {
    --g:   #C49A2C; --gd:  #8A6A14; --gl: #FDF6E3; --gr: rgba(196,154,44,.22);
    --sv:  #B8BEC4; --svb: #ECEDF0;
    --ink: #111111; --k2:  #1A1A1A; --k3: #484848; --k4: #6B6B6B; --k5: #909090;
    --bg:  #F4F4F6; --s:   #FFFFFF; --ov: #F8F8FA; --ov2: #F0F0F4;
    --b:   #E2E2E8; --b2:  #C8C8D0;
    --red: #DC2626; --rb:  #FEF2F2; --rbo:#FECACA;
    --green:#15803D; --greenb:#DCFCE7; --greend:#166534;
    --blue: #1D4ED8; --blueb:#DBEAFE;
    --amber:#B45309; --amberb:#FEF3C7;
    --font:'Inter',system-ui,sans-serif;
    --mono:'JetBrains Mono',monospace;
    --r-sm:8px; --r-md:12px; --r-lg:16px; --r-xl:20px;
    --safe-t:env(safe-area-inset-top,0px);
    --safe-b:env(safe-area-inset-bottom,0px);
}

*,*::before,*::after { box-sizing:border-box; margin:0; padding:0; }
html { height:100%; scroll-behavior:smooth; -webkit-text-size-adjust:100%; }
body {
    font-family:var(--font); font-size:14px; line-height:1.5;
    background:var(--bg); color:var(--k2); min-height:100svh;
    -webkit-font-smoothing:antialiased;
    -webkit-tap-highlight-color:transparent;
}
a { text-decoration:none; color:inherit; }
button { font-family:var(--font); border:none; cursor:pointer; background:none; }
img { display:block; max-width:100%; }
::-webkit-scrollbar { width:5px; height:5px; }
::-webkit-scrollbar-track { background:var(--bg); }
::-webkit-scrollbar-thumb { background:var(--b2); border-radius:3px; }

/* ═══════════════
   TOPBAR — estilo buses
═══════════════ */
.topbar {
    position:sticky; top:0; z-index:200;
    background:#fff;
    border-bottom:1px solid #e8e5e0;
    box-shadow:0 1px 3px rgba(0,0,0,.06), 0 1px 2px rgba(0,0,0,.04);
    padding-top:var(--safe-t);
}
.topbar-inner {
    height:52px; display:flex; align-items:center;
    justify-content:space-between; padding:0 14px;
}
.topbar-accent {
    height:2.5px;
    background:linear-gradient(90deg,transparent,var(--gd) 10%,var(--g) 30%,#EDD98A 55%,var(--g) 75%,var(--gd) 90%,transparent);
}
/* Bloque marca — logo + nombre, clickeable */
.logo-block {
    display:flex; align-items:center; gap:9px;
    text-decoration:none; color:inherit; flex-shrink:0;
}
.logo-block:hover { opacity:.85; }
.logo-img { height:30px; width:auto; object-fit:contain; flex-shrink:0; }
.logo-wordmark { display:flex; flex-direction:column; }
.logo-name { font-size:12px; font-weight:800; letter-spacing:.05em; color:var(--ink); text-transform:uppercase; line-height:1; }
.logo-name span { color:var(--g); }
.logo-sub  { font-family:var(--mono); font-size:7px; color:var(--k5); letter-spacing:.08em; text-transform:uppercase; margin-top:2px; }
/* Lado derecho */
.top-right { display:flex; align-items:center; gap:7px; }
.back-btn {
    width:34px; height:34px; border-radius:50%;
    background:var(--ov2); border:1px solid var(--b);
    display:flex; align-items:center; justify-content:center;
    color:var(--k4); transition:all .18s; flex-shrink:0;
    text-decoration:none;
}
.back-btn:hover { background:var(--rb); color:var(--red); border-color:var(--rbo); }
.back-btn svg { width:15px; height:15px; stroke:currentColor; fill:none; stroke-width:2.5; stroke-linecap:round; stroke-linejoin:round; }
.top-clock {
    font-family:var(--mono); font-size:9px; font-weight:500;
    color:var(--k4); background:var(--ov); padding:4px 9px;
    border-radius:99px; border:1px solid var(--b); letter-spacing:.3px;
    white-space:nowrap;
}
.top-user {
    display:flex; align-items:center; gap:7px;
    padding:4px 12px 4px 4px;
    border:1px solid var(--b); border-radius:99px; background:var(--s);
}
.top-av {
    width:26px; height:26px; border-radius:50%;
    background:linear-gradient(135deg,var(--g),#EDD98A);
    display:flex; align-items:center; justify-content:center;
    font-size:10px; font-weight:800; color:#fff;
}
.top-name { font-size:12px; font-weight:600; color:var(--ink); max-width:110px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
/* Quitar elementos que ya no aplican */
.top-space { display:none; }

/* ═══════════════
   PAGE HEADER
═══════════════ */
.page-header {
    max-width:1100px; margin:0 auto; padding:16px 24px 0;
    display:flex; align-items:center; gap:14px;
}
.page-header-icon {
    width:42px; height:42px; border-radius:var(--r-md);
    background:linear-gradient(135deg,var(--green),#22c55e);
    display:flex; align-items:center; justify-content:center;
    flex-shrink:0;
}
.page-header-icon svg { width:20px; height:20px; stroke:#fff; fill:none; stroke-width:2; stroke-linecap:round; stroke-linejoin:round; }
.page-header-text h1 { font-size:20px; font-weight:800; color:var(--ink); letter-spacing:-.3px; }
.page-header-text p  { font-size:12px; color:var(--k4); margin-top:1px; }
.page-header-space { flex:1; }
.count-pill {
    display:flex; align-items:center; gap:6px;
    background:var(--s); border:1px solid var(--b);
    border-radius:99px; padding:5px 14px 5px 8px;
    font-size:13px; font-weight:600; color:var(--k3);
}
.count-dot { width:7px; height:7px; border-radius:50%; background:var(--green); }

/* ═══════════════
   MAIN WRAP
═══════════════ */
.main-wrap {
    max-width:1100px; margin:0 auto;
    padding:14px 24px 120px;
    display:flex; flex-direction:column; gap:14px;
}

/* ═══════════════
   ALERT
═══════════════ */
.alert {
    display:flex; align-items:center; gap:10px;
    padding:12px 16px; border-radius:var(--r-md); font-size:14px; font-weight:500;
}
.alert-ok  { background:var(--greenb); color:var(--greend); border:1px solid rgba(21,128,61,.2); }
.alert-err { background:var(--rb);     color:var(--red);    border:1px solid var(--rbo); }
.alert i { font-size:15px; }

/* ═══════════════════════════════════════
   TARJETA HORIZONTAL — business card
═══════════════════════════════════════ */
.fc-scene {
    width:100%; display:flex; flex-direction:column; align-items:center; gap:16px;
}
.fc-btns { display:flex; gap:6px; }
.fc-btn {
    display:inline-flex; align-items:center; gap:6px;
    padding:7px 20px; border-radius:99px; font-size:12px; font-weight:600;
    border:1.5px solid var(--b); background:var(--s); color:var(--k4);
    cursor:pointer; font-family:var(--font); transition:all .18s; letter-spacing:.02em;
}
.fc-btn i { font-size:10px; }
.fc-btn.active { background:var(--ink); color:#fff; border-color:var(--ink); }
.fc-btn:not(.active):hover { background:var(--ov2); border-color:var(--b2); }

.fc-stage { width:100%; perspective:1400px; }
.fc-flipper {
    width:100%; position:relative;
    transform-style:preserve-3d;
    transition:transform .62s cubic-bezier(.4,0,.2,1);
    will-change:transform;
    -webkit-transform-style:preserve-3d;
}
.fc-flipper.flipped { transform:rotateY(180deg); }

.fc-face {
    width:100%; border-radius:16px;
    backface-visibility:hidden; -webkit-backface-visibility:hidden;
    -webkit-transform:translateZ(0);
}
.fc-back { position:absolute; top:0; left:0; transform:rotateY(180deg); }
/* La cruz médica CSS se espejea con el flip — contra-rotarla */
.fc-med-symbol { transform:scaleX(-1); }

/* En móvil: flip funciona pero sin columna de marca en el reverso */
@media(max-width:640px) {
    .fc-stage { perspective:900px; }
    .fc-flipper { transform-style:preserve-3d; transition:transform .55s cubic-bezier(.4,0,.2,1); }
    .fc-flipper.flipped { transform:rotateY(180deg); }
    .fc-face { backface-visibility:hidden; -webkit-backface-visibility:hidden; }
    .fc-back { position:absolute; top:0; left:0; transform:rotateY(180deg); }
    /* Reverso en móvil: sin columna de marca, solo campos */
    .fc-face-back { grid-template-columns:1fr; }
    .fc-back-brand-col { display:none; }
    .fc-back-fields-col { border-radius:16px; }
}

/* ══ FRENTE — layout horizontal 2 columnas ══ */
.fc-face-front {
    background:#F8F7F3;
    border:1px solid #dedad2;
    display:grid;
    grid-template-columns:1fr 1fr;
    min-height:320px;
}

/* Columna izquierda — logo dominante, paleta sistema */
.fc-brand-col {
    background:#F8F7F3;
    border-radius:16px 0 0 16px;
    border-right:1px solid var(--b);
    padding:0;
    display:flex; flex-direction:column;
    position:relative; overflow:hidden;
}
.fc-gold-line {
    position:absolute; top:0; left:0; right:0; height:3px; z-index:2;
    background:linear-gradient(90deg,transparent,var(--gd) 20%,var(--g) 50%,var(--gd) 80%,transparent);
    border-radius:16px 0 0 0;
}
.fc-brand-top {
    flex:1; display:flex; align-items:center; justify-content:center; z-index:1;
    padding:44px 20px 20px;
}
.fc-logo-wrap { display:flex; align-items:center; justify-content:center; width:100%; }
.fc-logo-wrap img { width:92%; max-width:240px; height:auto; object-fit:contain; display:block; }
.fc-logo-fb { font-size:40px; font-weight:900; letter-spacing:3px; color:var(--k3); text-transform:uppercase; line-height:1; text-align:center; }
.fc-brand-divider,.fc-co-name,.fc-co-sub { display:none; }
.fc-brand-bot {
    background:var(--s); border-top:1px solid var(--b);
    padding:14px 22px; border-radius:0 0 0 16px;
}
.fc-collab-label { font-size:8px; font-weight:700; letter-spacing:.15em; color:var(--k5); text-transform:uppercase; margin-bottom:3px; }
.fc-collab-name  { font-size:13px; font-weight:700; color:var(--ink); letter-spacing:.01em; line-height:1.3; }

/* ══ REVERSO — plateado suave, distinto pero paleta sistema ══ */
.fc-face-back {
    background:var(--s); border:1px solid var(--b);
    display:grid; grid-template-columns:1fr 1fr; min-height:320px;
    -webkit-backface-visibility:hidden; backface-visibility:hidden;
}
.fc-back-brand-col {
    background:var(--svb);
    border-radius:16px 0 0 16px;
    border-right:1px solid var(--b);
    padding:0;
    display:flex; flex-direction:column;
    position:relative; overflow:hidden;
    -webkit-backface-visibility:hidden; backface-visibility:hidden;
}
.fc-back-brand-col::before { display:none; }
.fc-back-red-bar {
    height:3px; flex-shrink:0;
    background:linear-gradient(90deg,transparent,#fca5a5 20%,#f87171 50%,#fca5a5 80%,transparent);
    border-radius:16px 0 0 0;
}
.fc-back-brand-top {
    flex:1; display:flex; flex-direction:column;
    align-items:center; justify-content:center;
    padding:28px 20px; gap:16px; z-index:1; text-align:center;
}
.fc-med-symbol {
    width:48px; height:48px; position:relative; flex-shrink:0;
}
.fc-med-symbol::before,
.fc-med-symbol::after {
    content:''; position:absolute; background:rgba(220,38,38,.25); border-radius:4px;
}
.fc-med-symbol::before { width:14px; height:48px; top:0; left:17px; }
.fc-med-symbol::after  { width:48px; height:14px; top:17px; left:0; }
.fc-back-brand-label {
    font-size:8px; font-weight:700; letter-spacing:3px;
    color:var(--k5); text-transform:uppercase;
}
.fc-back-brand-name {
    font-size:9px; font-weight:700; letter-spacing:4px;
    color:var(--k3); text-transform:uppercase; line-height:1.8;
}
.fc-back-brand-sub { font-size:8px; letter-spacing:2px; color:var(--k5); text-transform:uppercase; }
.fc-back-gold-rule { height:1px; width:40px; background:linear-gradient(90deg,transparent,var(--sv),transparent); }
.fc-back-brand-bot {
    background:var(--s); border-top:1px solid var(--b);
    padding:14px 22px; border-radius:0 0 0 16px;
    display:flex; align-items:center; gap:10px;
}
.fc-back-ico {
    width:30px; height:30px; border-radius:50%;
    background:#fef2f2; border:1px solid #fecaca;
    display:flex; align-items:center; justify-content:center;
    font-size:12px; color:#dc2626; flex-shrink:0;
}
.fc-back-ico-row { display:flex; align-items:center; gap:10px; }
.fc-back-ico-label { font-size:9px; font-weight:600; letter-spacing:1.5px; color:var(--k4); text-transform:uppercase; line-height:1.5; }

/* Columna derecha — campos */
.fc-fields-col {
    padding:28px 28px 20px;
    display:flex; flex-direction:column; gap:0;
    overflow-y:auto;
}
.fc-back-fields-col {
    padding:28px 28px 20px;
    display:flex; flex-direction:column; gap:0;
}

/* Sección */
.fc-sec { display:flex; flex-direction:column; gap:10px; margin-bottom:16px; }
.fc-sec-title {
    font-size:9px; font-weight:700; letter-spacing:.12em; text-transform:uppercase;
    color:var(--k5); display:flex; align-items:center; gap:8px;
}
.fc-sec-title::after { content:''; flex:1; height:1px; background:var(--b); }
.fc-sec-title i { font-size:9px; color:var(--g); }
.fc-sec-title.med i { color:#dc2626; }

.fc-row { display:grid; grid-template-columns:1fr 1fr; gap:8px 14px; }
.fc-row.c3 { grid-template-columns:1fr 1fr 1fr; }

.fc-field { display:flex; flex-direction:column; gap:3px; }
.fc-field label { font-size:9px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:var(--k5); }
.fc-field input,
.fc-field select {
    height:34px; padding:0 10px;
    background:var(--s); border:1px solid var(--b);
    border-radius:var(--r-sm); font-family:var(--font);
    font-size:16px; color:var(--ink); outline:none; width:100%;
    transition:border-color .15s, box-shadow .15s;
    appearance:none; -webkit-appearance:none;
}
.fc-field input:focus,
.fc-field select:focus { border-color:var(--g); box-shadow:0 0 0 3px var(--gr); }
.fc-field input[readonly] { background:var(--ov2); color:var(--k4); cursor:not-allowed; }
.fc-field select {
    background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23909090' stroke-width='2.5' stroke-linecap='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
    background-repeat:no-repeat; background-position:right 9px center; padding-right:28px;
}
.fc-card-footer {
    margin-top:auto; padding-top:14px; border-top:1px solid var(--b);
    display:flex; align-items:center; gap:8px; flex-wrap:wrap;
}
.fc-med-note {
    display:flex; align-items:center; gap:8px;
    padding:9px 11px; background:#fff5f5; border:1px solid #fecaca;
    border-radius:var(--r-sm); font-size:11px; color:#dc2626; margin-top:4px;
}

/* Responsive */
@media(max-width:720px) {
    .fc-face-front, .fc-face-back { grid-template-columns:1fr; }
    .fc-brand-col { border-radius:16px 16px 0 0; padding:24px 20px; min-height:auto; }
    .fc-back-brand-col { border-radius:16px 16px 0 0; border-right:none; border-bottom:1px solid #dedad2; padding:20px; }
    .fc-gold-line { border-radius:16px 16px 0 0; }
    .fc-fields-col, .fc-back-fields-col { padding:20px; }
    .fc-row.c3 { grid-template-columns:1fr 1fr; }
    .fc-co-name { font-size:15px; }
}
@media(max-width:420px) {
    .fc-row, .fc-row.c3 { grid-template-columns:1fr; }
}
.btn-primary {
    display:inline-flex; align-items:center; gap:8px;
    padding:10px 20px; border-radius:var(--r-md);
    background:var(--ink); color:#fff;
    font-size:14px; font-weight:600; font-family:var(--font);
    border:none; cursor:pointer;
    transition:opacity .15s, transform .1s;
    border-bottom:3px solid rgba(0,0,0,.35);
}
.btn-primary:hover { opacity:.88; }
.btn-primary:active { transform:translateY(1px); border-bottom-width:1px; }
.btn-secondary {
    display:inline-flex; align-items:center; gap:8px;
    padding:10px 18px; border-radius:var(--r-md);
    background:var(--s); color:var(--k4);
    font-size:14px; font-weight:600; font-family:var(--font);
    border:1px solid var(--b); cursor:pointer;
    transition:all .15s; text-decoration:none;
}
.btn-secondary:hover { background:var(--ov2); color:var(--k3); }

/* ═══════════════
   TOOLBAR (búsqueda + export)
═══════════════ */
.toolbar {
    display:flex; align-items:center; gap:10px;
    background:var(--s); border:1px solid var(--b);
    border-radius:var(--r-lg); padding:10px 14px;
}
.search-wrap {
    flex:1; display:flex; align-items:center; gap:8px;
    background:var(--ov); border:1.5px solid var(--b);
    border-radius:var(--r-md); padding:0 12px; height:40px;
    transition:border-color .15s, box-shadow .15s;
}
.search-wrap:focus-within {
    border-color:var(--g); box-shadow:0 0 0 3px var(--gr);
    background:var(--s);
}
.search-wrap i { color:var(--k5); font-size:13px; flex-shrink:0; }
.search-wrap input {
    flex:1; border:none; background:none; font-family:var(--font);
    font-size:14px; color:var(--ink); outline:none;
}
.search-wrap input::placeholder { color:var(--k5); }
.clear-btn {
    display:flex; align-items:center; gap:5px; flex-shrink:0;
    font-size:12px; font-weight:600; color:var(--k5);
    padding:4px 8px; border-radius:6px; cursor:pointer;
    transition:all .15s; text-decoration:none;
}
.clear-btn:hover { background:var(--ov2); color:var(--ink); }
.btn-export {
    display:flex; align-items:center; gap:6px;
    padding:8px 14px; border-radius:var(--r-md);
    background:var(--s); border:1.5px solid var(--b);
    color:var(--k3); font-size:13px; font-weight:600;
    text-decoration:none; transition:all .15s; flex-shrink:0;
    white-space:nowrap;
}
.btn-export:hover { background:var(--greenb); border-color:rgba(21,128,61,.3); color:var(--greend); }
.btn-export i { font-size:14px; }

/* Resultados count */
.results-info {
    font-size:12px; color:var(--k5); padding:0 4px;
    font-family:var(--mono); white-space:nowrap; flex-shrink:0;
}

/* ── Chips de filtro guardia ── */
.guardia-chips {
    display:flex; align-items:center; gap:5px; flex-wrap:wrap; flex-shrink:0;
}
.gc-label {
    font-size:11px; font-weight:600; color:var(--k5);
    display:flex; align-items:center; gap:4px; padding-right:2px;
    white-space:nowrap;
}
.gc-label i { font-size:10px; }
.gc-chip {
    padding:5px 12px; border-radius:99px; font-size:12px; font-weight:700;
    border:1.5px solid var(--b); background:var(--s); color:var(--k4);
    cursor:pointer; font-family:var(--font); transition:all .15s;
    white-space:nowrap; display:inline-flex; align-items:center; gap:4px;
}
.gc-chip:hover { border-color:var(--b2); background:var(--ov); color:var(--k2); }
.gc-chip i { font-size:10px; }
/* Activo por guardia */
.gc-A.active { background:var(--gl);  border-color:var(--g);   color:var(--gd); }
.gc-B.active { background:#F0F0F4;    border-color:#484848;    color:#1A1A1A;   }
.gc-C.active { background:var(--svb); border-color:var(--sv);  color:#4A5568;   }
/* Hover con color */
.gc-A:hover { border-color:var(--g);  color:var(--gd); }
.gc-B:hover { border-color:#484848;   color:#1A1A1A;   }
.gc-C:hover { border-color:var(--sv); color:#4A5568;   }

/* ═══════════════
   TARJETAS DE PERSONAL
═══════════════ */
.cards-grid {
    display:grid;
    grid-template-columns:repeat(auto-fill, minmax(310px, 1fr));
    gap:12px;
    padding-top:12px;
}
.p-card {
    background:var(--s); border:1px solid var(--b);
    border-radius:var(--r-lg); overflow:visible;
    transition:box-shadow .18s, transform .12s;
    animation:fadeUp .22s ease both;
}
.p-card:hover { box-shadow:0 4px 20px rgba(17,17,17,.08); transform:translateY(-2px); }
@keyframes fadeUp { from{opacity:0;transform:translateY(8px)} to{opacity:1;transform:translateY(0)} }

.p-card-top {
    display:flex; align-items:flex-start; gap:12px; padding:14px 14px 10px;
    border-radius:var(--r-lg) var(--r-lg) 0 0; overflow:hidden;
}
.p-avatar {
    width:44px; height:44px; border-radius:50%; flex-shrink:0;
    display:flex; align-items:center; justify-content:center;
    font-size:14px; font-weight:800; color:#fff; letter-spacing:-.5px;
}
.p-info { flex:1; min-width:0; }
.p-name {
    font-size:14px; font-weight:700; color:var(--ink);
    white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
}
.p-dni {
    font-family:var(--mono); font-size:11px; color:var(--k5);
    margin-top:2px;
}
.p-badges { display:flex; align-items:center; gap:5px; margin-top:5px; flex-wrap:wrap; }
.badge {
    display:inline-flex; align-items:center; gap:4px;
    padding:2px 9px; border-radius:99px; font-size:11px; font-weight:700;
    letter-spacing:.03em; white-space:nowrap;
}
.badge::before { content:''; width:5px; height:5px; border-radius:50%; background:currentColor; }
.st-afiliado { background:#DCFCE7; color:#166534; }
.st-proceso  { background:#FEF3C7; color:#92400E; }
.st-visita   { background:#DBEAFE; color:#1e40af; }

.p-meta {
    display:grid; grid-template-columns:1fr 1fr; gap:0;
    border-top:1px solid var(--b); margin-top:0;
}
.p-meta-item {
    padding:9px 14px; display:flex; flex-direction:column; gap:2px;
}
.p-meta-item:nth-child(1) { border-right:1px solid var(--b); }
.p-meta-full {
    grid-column:1/-1;
    border-top:1px solid var(--b);
}
.p-meta-label { font-size:10px; font-weight:600; color:var(--k5); letter-spacing:.06em; text-transform:uppercase; }
.p-meta-val { font-size:12px; font-weight:500; color:var(--k3); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }

.p-actions {
    display:flex; border-top:1px solid var(--b);
    border-radius:0 0 var(--r-lg) var(--r-lg); overflow:hidden;
}
.p-action-btn {
    flex:1; display:flex; align-items:center; justify-content:center; gap:6px;
    padding:10px 8px; font-size:12px; font-weight:600;
    cursor:pointer; transition:background .12s;
    border:none; font-family:var(--font); color:var(--k4);
    background:none;
}
.p-action-btn:first-child { border-right:1px solid var(--b); }
.p-action-btn:hover { background:var(--ov); }
.p-action-btn.btn-edit:hover  { color:var(--g); }
.p-action-btn.btn-del:hover   { color:var(--red); background:var(--rb); }
.p-action-btn i { font-size:13px; }

/* Celular opcional */
.p-cel {
    font-size:11px; color:var(--g); font-weight:600;
    margin-top:2px; display:flex; align-items:center; gap:4px;
}

/* ── Guardia badge — esquina superior derecha de la tarjeta ── */
.p-card { position:relative; }
.guardia-badge {
    position:absolute; top:-10px; right:-10px; z-index:10;
    width:36px; height:36px; border-radius:50%;
    display:flex; flex-direction:column; align-items:center; justify-content:center;
    border:2.5px solid var(--s);
    box-shadow:0 2px 8px rgba(0,0,0,.18);
    line-height:1; gap:0; cursor:default;
    transition:transform .15s;
}
.guardia-badge:hover { transform:scale(1.1); }
.guardia-badge .gb-letra { font-size:14px; font-weight:900; color:#fff; line-height:1; }
.guardia-badge .gb-sub   { font-size:6px;  font-weight:700; color:rgba(255,255,255,.75); letter-spacing:.05em; text-transform:uppercase; line-height:1; margin-bottom:1px; }
.gb-A { background:linear-gradient(135deg, #8A6A14, #C49A2C); } /* Dorado marca */
.gb-B { background:linear-gradient(135deg, #1A1A1A, #3a3a3a); } /* Grafito ink  */
.gb-C { background:linear-gradient(135deg, #6B7280, #B8BEC4); } /* Plateado sv  */

/* ── Pill "ver datos médicos" — inline junto al badge ── */
.med-pill {
    display:inline-flex; align-items:center; gap:4px;
    padding:2px 8px 2px 6px; border-radius:99px;
    background:var(--ov); border:1px solid var(--b);
    font-size:11px; font-weight:600; color:var(--k4);
    cursor:pointer; font-family:var(--font);
    transition:all .15s; white-space:nowrap;
    vertical-align:middle;
}
.med-pill:hover { background:#fef2f2; border-color:#fecaca; color:#dc2626; }
.med-pill i { font-size:10px; color:#e11d48; }
.med-pill-txt { }
.med-pill-arrow {
    width:10px; height:10px; stroke:var(--k5); fill:none;
    stroke-width:2.5; stroke-linecap:round; stroke-linejoin:round;
    transition:transform .2s;
}
.med-pill.open { background:#fef2f2; border-color:#fecaca; color:#dc2626; }
.med-pill.open .med-pill-arrow { transform:rotate(180deg); stroke:#dc2626; }

/* ── Drawer médico (dentro de p-info) ── */
.p-med-drawer {
    overflow:hidden; max-height:0;
    transition:max-height .26s cubic-bezier(.4,0,.2,1);
    margin-top:0;
}
.p-med-drawer.open { max-height:200px; margin-top:7px; }
.p-med-inner {
    background:#fef9f9; border:1px solid #fce7e7;
    border-radius:var(--r-sm); padding:8px 10px;
    display:grid; grid-template-columns:1fr 1fr; gap:6px 10px;
}
.p-med-row { display:flex; flex-direction:column; gap:1px; }
.p-med-full { grid-column:1/-1; }
.p-med-lbl { font-size:10px; font-weight:600; color:#e11d48; letter-spacing:.05em; text-transform:uppercase; opacity:.7; }
.p-med-val { font-size:12px; font-weight:500; color:var(--k3); }
.p-med-val.empty { color:var(--k5); font-style:italic; font-weight:400; }
.p-med-gs {
    display:inline-flex; align-items:center; justify-content:center;
    width:28px; height:28px; border-radius:50%;
    background:#fef2f2; border:1.5px solid #fca5a5;
    font-size:11px; font-weight:800; color:#dc2626;
    margin-top:1px;
}

/* ═══════════════
   EMPTY STATE
═══════════════ */
.empty-state {
    grid-column:1/-1; text-align:center;
    padding:60px 20px;
    display:flex; flex-direction:column; align-items:center; gap:12px;
}
.empty-icon {
    width:60px; height:60px; border-radius:var(--r-lg);
    background:var(--ov); border:1px solid var(--b);
    display:flex; align-items:center; justify-content:center;
    color:var(--k5); font-size:24px;
}
.empty-title { font-size:16px; font-weight:700; color:var(--k3); }
.empty-desc  { font-size:13px; color:var(--k5); max-width:300px; line-height:1.6; }

/* ═══════════════
   INFO BOX (rol sin acceso)
═══════════════ */
.info-box {
    background:var(--s); border:1px solid var(--b); border-radius:var(--r-xl);
    padding:48px 24px; text-align:center;
    display:flex; flex-direction:column; align-items:center; gap:12px;
}
.info-box-icon {
    width:56px; height:56px; border-radius:var(--r-lg);
    background:var(--ov); border:1px solid var(--b);
    display:flex; align-items:center; justify-content:center;
    color:var(--k5); font-size:22px;
}
.info-box h3 { font-size:17px; font-weight:700; color:var(--k3); }
.info-box p  { font-size:13px; color:var(--k5); max-width:280px; line-height:1.7; }

/* ═══════════════
   SECTION LABEL
═══════════════ */
.section-label {
    font-size:11px; font-weight:700; letter-spacing:.1em; text-transform:uppercase;
    color:var(--k5); display:flex; align-items:center; gap:8px;
}
.section-label::after { content:''; flex:1; height:1px; background:var(--b); }

/* ═══════════════
   PAGINACIÓN
═══════════════ */
.pagination {
    display:flex; align-items:center; justify-content:center;
    gap:6px; padding:8px 0 4px; flex-wrap:wrap;
}
.pg-info {
    font-size:12px; color:var(--k5); font-family:var(--mono);
    margin-right:6px; white-space:nowrap;
}
.pg-btn {
    min-width:36px; height:36px; padding:0 10px;
    display:inline-flex; align-items:center; justify-content:center;
    border-radius:var(--r-sm); border:1px solid var(--b);
    background:var(--s); color:var(--k3); font-size:13px; font-weight:600;
    font-family:var(--font); cursor:pointer; text-decoration:none;
    transition:all .15s; white-space:nowrap; gap:5px;
}
.pg-btn:hover { background:var(--ov2); border-color:var(--b2); }
.pg-btn.active { background:var(--ink); color:#fff; border-color:var(--ink); cursor:default; }
.pg-btn.disabled { opacity:.35; pointer-events:none; }
.pg-btn svg { width:14px; height:14px; stroke:currentColor; fill:none; stroke-width:2.5; stroke-linecap:round; stroke-linejoin:round; }
.pg-dots { color:var(--k5); font-size:13px; padding:0 2px; line-height:36px; }

/* ═══════════════
   BOTTOM NAV (móvil)
═══════════════ */
.bottom-nav { display:none; }

/* ═══════════════
   RESPONSIVE — MÓVIL
═══════════════ */
@media(max-width:640px) {
    .topbar-inner { padding:0 12px; height:52px; }
    .logo-img { height:26px; }
    .logo-name { font-size:11px; }
    .logo-sub { display:none; }
    .top-clock { display:none; }
    .top-name { max-width:72px; font-size:11px; }

    .page-header { padding:12px 14px 0; gap:10px; }
    .page-header-text h1 { font-size:17px; }
    .page-header-text p { display:none; }

    .main-wrap { padding:12px 12px 100px; gap:12px; }

    /* Tarjeta formulario en móvil */
    .fc-scene { gap:10px; }
    .fc-btns { gap:5px; width:100%; justify-content:center; }
    .fc-btn { padding:7px 18px; font-size:12px; flex:1; justify-content:center; }
    .fc-face-front, .fc-face-back { grid-template-columns:1fr; border-radius:16px; }
    /* Columna de marca en frente: compacta horizontal */
    .fc-brand-col {
        border-radius:16px 16px 0 0;
        border-right:none; border-bottom:1px solid var(--b);
        flex-direction:row; align-items:center;
        padding:14px 18px; min-height:auto; gap:14px;
    }
    .fc-brand-top { flex:none; padding:0; }
    .fc-logo-wrap img { max-width:90px; width:90px; }
    .fc-brand-bot {
        border-radius:0; padding:14px 18px;
        background:transparent; border-top:none; border-left:1px solid var(--b);
        flex:1;
    }
    .fc-collab-label { color:var(--k5); }
    .fc-collab-name { color:var(--ink); }
    .fc-gold-line { border-radius:16px 16px 0 0; }
    /* Campos: inputs más grandes para táctil */
    .fc-fields-col, .fc-back-fields-col { padding:16px 14px 14px; }
    .fc-field input, .fc-field select { height:42px; font-size:14px; }
    .fc-row { gap:8px 10px; }
    .fc-row.c3 { grid-template-columns:1fr 1fr; }
    .fc-card-footer { padding-top:12px; flex-wrap:wrap; gap:8px; }
    .fc-card-footer .btn-primary { flex:1; justify-content:center; }
    .fc-sec { margin-bottom:14px; }

    /* Toolbar */
    .toolbar {
        padding:8px 10px; gap:6px; flex-wrap:wrap; align-items:center;
    }
    .search-wrap { height:38px; min-width:0; }
    .guardia-chips { width:100%; justify-content:flex-start; }
    .gc-label { display:none; }
    .gc-chip { padding:5px 12px; font-size:11px; flex:1; justify-content:center; }
    .results-info { display:none; }
    .btn-export {
        padding:8px 12px; font-size:12px; flex-shrink:0;
    }
    .btn-export span { display:inline; }

    .cards-grid { grid-template-columns:1fr; gap:10px; }
    .pagination { gap:4px; }
    .pg-info { display:none; }
    .pg-btn { min-width:32px; height:32px; font-size:12px; padding:0 8px; }

    /* Bottom nav */
    .bottom-nav {
        display:flex;
        position:fixed; bottom:0; left:0; right:0; z-index:300;
        background:rgba(255,255,255,.95);
        backdrop-filter:blur(14px); -webkit-backdrop-filter:blur(14px);
        border-top:1px solid var(--b);
        padding:8px 6px calc(8px + var(--safe-b));
        align-items:center; justify-content:space-around;
    }
    .bn-item {
        display:flex; flex-direction:column; align-items:center; gap:3px;
        cursor:pointer; padding:5px 10px; border-radius:10px; flex:1;
        transition:background .12s;
    }
    .bn-item:active { background:var(--ov); transform:scale(.94); }
    .bn-ico { width:24px; height:24px; display:flex; align-items:center; justify-content:center; }
    .bn-ico svg { width:22px; height:22px; fill:none; stroke-width:2; stroke-linecap:round; stroke-linejoin:round; transition:stroke .15s; }
    .bn-lbl { font-size:10px; font-weight:600; transition:color .15s; }
    .bn-item.active .bn-ico svg { stroke:var(--g); }
    .bn-item.active .bn-lbl { color:var(--g); }
    .bn-item:not(.active) .bn-ico svg { stroke:var(--k5); }
    .bn-item:not(.active) .bn-lbl { color:var(--k5); }
    .bn-emg {
        display:flex; flex-direction:column; align-items:center; gap:3px;
        cursor:pointer; flex-shrink:0; position:relative; top:-14px;
    }
    .bn-emg-circle {
        width:52px; height:52px; border-radius:50%; background:var(--red);
        display:flex; align-items:center; justify-content:center;
        box-shadow:0 4px 16px rgba(220,38,38,.4), 0 0 0 4px rgba(220,38,38,.1);
        transition:transform .12s;
    }
    .bn-emg:active .bn-emg-circle { transform:scale(.91); }
    .bn-emg-circle svg { width:23px; height:23px; stroke:#fff; stroke-width:2.5; fill:none; stroke-linecap:round; }
    .bn-emg-lbl { font-size:10px; font-weight:700; color:var(--red); }

    /* Ocultar campo código en móvil para no romper el grid en 2col */
    .field-full { grid-column:1/-1; }
}

@media(max-width:380px) {
    .field-grid { grid-template-columns:1fr; }
}

@media(min-width:641px) and (max-width:900px) {
    .cards-grid { grid-template-columns:repeat(2, 1fr); }
}

@media(prefers-reduced-motion:reduce) {
    .p-card { animation:none; transition:none; }
    .p-card:hover { transform:none; }
}
</style>
</head>
<body>

<!-- ══════════════ TOPBAR ══════════════ -->
<header class="topbar">
    <div class="topbar-inner">
        <a href="dashboard.php" class="logo-block" title="Volver al Dashboard">
            <img class="logo-img" src="assets/logo.png" alt="Hochschild Mining"
                 onerror="this.style.display='none'">
            <div class="logo-wordmark">
                <div class="logo-name">Hochschild <span>Mining</span></div>
                <div class="logo-sub">Gestión de Personal</div>
            </div>
        </a>
        <div class="top-right">
            <span class="top-clock" id="clockEl">--:--:--</span>
            <div class="top-user">
                <div class="top-av"><?= h($iniciales) ?></div>
                <span class="top-name"><?= h($nombre_sesion) ?></span>
            </div>
            <a href="dashboard.php" class="back-btn" title="Volver al Dashboard">
                <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
            </a>
        </div>
    </div>
    <div class="topbar-accent"></div>
</header>

<!-- ══════════════ PAGE HEADER ══════════════ -->
<div class="page-header">
    <div class="page-header-icon">
        <svg viewBox="0 0 24 24">
            <circle cx="9" cy="7" r="4"/>
            <path d="M2 21c0-3.3 3.1-6 7-6s7 2.7 7 6"/>
            <circle cx="19" cy="8" r="2.5"/>
            <path d="M22 21c0-2.2-1.3-4-3-4.5"/>
        </svg>
    </div>
    <div class="page-header-text">
        <h1><?= $edit ? 'Editar Colaborador' : 'Gestión de Personal' ?></h1>
        <p>Registro y directorio de colaboradores · Hochschild Mining</p>
    </div>
    <div class="page-header-space"></div>
    <?php if ($rol_actual === 'administrador' && $total_personal > 0): ?>
    <div class="count-pill">
        <div class="count-dot"></div>
        <?= number_format($total_personal) ?> registros
    </div>
    <?php endif; ?>
</div>

<!-- ══════════════ MAIN ══════════════ -->
<main class="main-wrap">

    <?php if (isset($_GET['msg'])): ?>
    <div class="alert <?= $_GET['msg'] === 'ok' ? 'alert-ok' : 'alert-err' ?>">
        <?php if ($_GET['msg'] === 'ok'): ?>
            <i class="fas fa-check-circle"></i> Operación realizada con éxito.
        <?php else: ?>
            <i class="fas fa-exclamation-circle"></i> Ocurrió un error o el DNI ya existe.
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ═══ TARJETA HORIZONTAL FLIP ═══ -->
    <form action="personal_guardar.php" method="POST" id="credForm">
    <div class="fc-scene">

        <div class="fc-btns">
            <button type="button" class="fc-btn active" id="btnFrente" onclick="flipCard('front')">
                <i class="fas fa-id-card"></i> Datos Generales
            </button>
            <button type="button" class="fc-btn" id="btnReverso" onclick="flipCard('back')">
                <i class="fas fa-heart-pulse"></i> Datos Médicos
            </button>
        </div>

        <div class="fc-stage">
        <div class="fc-flipper" id="fcFlipper">

            <!-- ══ FRENTE ══ -->
            <div class="fc-face fc-face-front">

                <!-- Col izq: marca oscura -->
                <div class="fc-brand-col">
                    <div class="fc-gold-line"></div>
                    <div class="fc-brand-top">
                        <div class="fc-logo-wrap">
                            <img src="assets/Hochscild_logo3.png" alt="Hochschild Mining"
                                 onerror="this.style.display='none';this.nextElementSibling.style.display='block'">
                            <span class="fc-logo-fb" style="display:none">HM</span>
                        </div>
                        <div class="fc-brand-divider"></div>
                        <div class="fc-co-name">Hochschild<br>Mining</div>
                        <div class="fc-co-sub">Sistema de Transporte</div>
                    </div>
                    <div class="fc-brand-bot">
                        <div class="fc-collab-label">Colaborador</div>
                        <div class="fc-collab-name" id="fcCollabName">
                            <?= $edit ? h(($edit['apellidos'] ?? '') . ', ' . ($edit['nombres'] ?? '')) : 'Nuevo registro' ?>
                        </div>
                    </div>
                </div>

                <!-- Col der: campos -->
                <div class="fc-fields-col">

                    <div class="fc-sec">
                        <div class="fc-sec-title"><i class="fas fa-fingerprint"></i> Identificación</div>
                        <div class="fc-row">
                            <div class="fc-field">
                                <label>DNI</label>
                                <input type="text" name="dni" maxlength="8"
                                       value="<?= h($edit['dni'] ?? '') ?>"
                                       <?= $edit ? 'readonly' : 'required' ?>
                                       placeholder="12345678">
                            </div>
                            <div class="fc-field">
                                <label>Código Fotocheck</label>
                                <input type="text" name="codigo"
                                       value="<?= h($edit['codigo'] ?? '') ?>"
                                       placeholder="HC-0000">
                            </div>
                            <div class="fc-field">
                                <label>Nombres</label>
                                <input type="text" name="nombres"
                                       value="<?= h($edit['nombres'] ?? '') ?>" required
                                       placeholder="Nombres">
                            </div>
                            <div class="fc-field">
                                <label>Apellidos</label>
                                <input type="text" name="apellidos"
                                       value="<?= h($edit['apellidos'] ?? '') ?>" required
                                       placeholder="Apellidos">
                            </div>
                            <div class="fc-field">
                                <label>Celular</label>
                                <input type="text" name="celular"
                                       value="<?= h($edit['celular'] ?? '') ?>"
                                       placeholder="9xx xxx xxx">
                            </div>
                        </div>
                    </div>

                    <div class="fc-sec">
                        <div class="fc-sec-title"><i class="fas fa-building"></i> Empresa y rol</div>
                        <div class="fc-row c3">
                            <div class="fc-field">
                                <label>Empresa</label>
                                <input type="text" name="empresa"
                                       value="<?= h($edit['empresa'] ?? '') ?>"
                                       placeholder="Razón social">
                            </div>
                            <div class="fc-field">
                                <label>Área</label>
                                <input type="text" name="area"
                                       value="<?= h($edit['area'] ?? '') ?>"
                                       placeholder="Área">
                            </div>
                            <div class="fc-field">
                                <label>Cargo</label>
                                <input type="text" name="cargo"
                                       value="<?= h($edit['cargo'] ?? '') ?>"
                                       placeholder="Puesto">
                            </div>
                        </div>
                    </div>

                    <div class="fc-sec">
                        <div class="fc-sec-title"><i class="fas fa-sliders"></i> Clasificación</div>
                        <div class="fc-row">
                            <div class="fc-field">
                                <label>Estado de Validación</label>
                                <select name="estado_validacion">
                                    <option value="AFILIADO"                 <?= ($edit['estado_validacion']??'') === 'AFILIADO'                 ? 'selected':'' ?>>AFILIADO (Verde)</option>
                                    <option value="EN PROCESO DE AFILIACION" <?= ($edit['estado_validacion']??'') === 'EN PROCESO DE AFILIACION' ? 'selected':'' ?>>EN PROCESO (Amarillo)</option>
                                    <option value="VISITA"                   <?= ($edit['estado_validacion']??'') === 'VISITA'                   ? 'selected':'' ?>>VISITA (Azul)</option>
                                </select>
                            </div>
                            <div class="fc-field">
                                <label>Guardia</label>
                                <select name="GUARDIA">
                                    <option value="">Sin asignar</option>
                                    <?php foreach(['A','B','C'] as $g): ?>
                                    <option value="<?= $g ?>" <?= strtoupper(trim($edit['GUARDIA'] ?? $edit['guardia'] ?? '')) === $g ? 'selected':'' ?>>GUARDIA <?= $g ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="fc-card-footer">
                        <button type="submit" class="btn-primary">
                            <i class="fas <?= $edit ? 'fa-save' : 'fa-user-plus' ?>"></i>
                            <?= $edit ? 'Guardar Cambios' : 'Registrar Personal' ?>
                        </button>
                        <?php if ($edit): ?>
                            <a href="personal.php" class="btn-secondary"><i class="fas fa-times"></i> Cancelar</a>
                        <?php endif; ?>
                        <span style="flex:1"></span>
                        <button type="button" class="fc-btn" onclick="flipCard('back')" style="padding:5px 12px;font-size:11px;">
                            Datos médicos <i class="fas fa-arrow-right" style="font-size:9px"></i>
                        </button>
                    </div>

                </div><!-- /fc-fields-col -->
            </div><!-- /fc-face-front -->

            <!-- ══ REVERSO ══ -->
            <div class="fc-face fc-back">

                <div class="fc-back-brand-col">
                    <div class="fc-back-red-bar"></div>
                    <div class="fc-back-brand-top">
                        <div class="fc-med-symbol"></div>
                        <div class="fc-back-brand-label">Hochschild Mining</div>
                        <div class="fc-back-gold-rule"></div>
                        <div class="fc-back-brand-name">DATOS<br>MÉDICOS</div>
                        <div class="fc-back-brand-sub">Información confidencial</div>
                    </div>
                    <div class="fc-back-brand-bot">
                        <div class="fc-back-ico-row">
                            <div class="fc-back-ico"><i class="fas fa-heart-pulse"></i></div>
                            <div class="fc-back-ico-label">Emergencia<br>médica</div>
                        </div>
                    </div>
                </div>

                <!-- Col der: campos médicos -->
                <div class="fc-back-fields-col">

                    <div class="fc-sec">
                        <div class="fc-sec-title med"><i class="fas fa-droplet"></i> Datos médicos</div>
                        <div class="fc-row c3">
                            <div class="fc-field">
                                <label>Grupo Sanguíneo</label>
                                <select name="grupo_sanguineo">
                                    <option value="">—</option>
                                    <?php foreach(['O+','O-','A+','A-','B+','AB+'] as $gs): ?>
                                    <option value="<?= $gs ?>" <?= ($edit['grupo_sanguineo']??'') === $gs ? 'selected':'' ?>><?= $gs ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="fc-field">
                                <label>Contacto Emergencia</label>
                                <input type="text" name="contacto_emergencia"
                                       value="<?= h($edit['contacto_emergencia'] ?? '') ?>"
                                       placeholder="Nombre completo">
                            </div>
                            <div class="fc-field">
                                <label>Teléfono Emergencia</label>
                                <input type="tel" name="telefono_emergencia"
                                       value="<?= h($edit['telefono_emergencia'] ?? '') ?>"
                                       placeholder="9xx xxx xxx">
                            </div>
                        </div>
                    </div>

                    <div class="fc-sec">
                        <div class="fc-sec-title med"><i class="fas fa-notes-medical"></i> Condiciones de salud</div>
                        <div class="fc-row">
                            <div class="fc-field">
                                <label>Enfermedades</label>
                                <input type="text" name="enfermedades"
                                       value="<?= h($edit['enfermedades'] ?? '') ?>"
                                       placeholder="Crónicas / Pre-existentes">
                            </div>
                            <div class="fc-field">
                                <label>Alergias</label>
                                <input type="text" name="alergias"
                                       value="<?= h($edit['alergias'] ?? '') ?>"
                                       placeholder="Medicamentos / Alimentos">
                            </div>
                        </div>
                        <?php if ($edit && ($edit['grupo_sanguineo'] || $edit['contacto_emergencia'])): ?>
                        <div class="fc-med-note">
                            <i class="fas fa-shield-heart"></i> Información médica registrada. Mantenla actualizada.
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="fc-card-footer">
                        <button type="submit" class="btn-primary">
                            <i class="fas <?= $edit ? 'fa-save' : 'fa-user-plus' ?>"></i>
                            <?= $edit ? 'Guardar Cambios' : 'Registrar Personal' ?>
                        </button>
                        <?php if ($edit): ?>
                            <a href="personal.php" class="btn-secondary"><i class="fas fa-times"></i> Cancelar</a>
                        <?php endif; ?>
                        <span style="flex:1"></span>
                        <button type="button" class="fc-btn" onclick="flipCard('front')" style="padding:5px 12px;font-size:11px;">
                            <i class="fas fa-arrow-left" style="font-size:9px"></i> Datos generales
                        </button>
                    </div>

                </div><!-- /fc-back-fields-col -->
            </div><!-- /fc-face-back -->

        </div><!-- /fc-flipper -->
        </div><!-- /fc-stage -->

    </div><!-- /fc-scene -->
    </form>

    <!-- ═══ LISTADO (solo admin) ═══ -->
    <?php if ($rol_actual === 'administrador'): ?>

        <div class="section-label">Directorio de colaboradores</div>

        <!-- Toolbar búsqueda + filtro guardia -->
        <div class="toolbar">
            <form method="GET" action="personal.php" id="filterForm" style="flex:1; display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                <div class="search-wrap" style="flex:1; min-width:180px;">
                    <i class="fas fa-search"></i>
                    <input type="text" name="q"
                           placeholder="Buscar por DNI, nombre, empresa..."
                           value="<?= h($q) ?>"
                           autocomplete="off"
                           id="searchInput">
                    <?php if ($q !== '' || $filtro_guardia !== ''): ?>
                        <a href="personal.php" class="clear-btn" title="Limpiar filtros">
                            <i class="fas fa-times"></i> Limpiar
                        </a>
                    <?php endif; ?>
                </div>
                <!-- Chips de guardia -->
                <div class="guardia-chips">
                    <span class="gc-label"><i class="fas fa-layer-group"></i> Guardia</span>
                    <?php foreach(['A','B','C'] as $g):
                        $active = $filtro_guardia === $g;
                    ?>
                    <button type="submit" name="guardia" value="<?= $g ?>"
                            class="gc-chip gc-<?= $g ?> <?= $active ? 'active' : '' ?>">
                        <?= $active ? '<i class="fas fa-check"></i> ' : '' ?>Guardia <?= $g ?>
                    </button>
                    <?php endforeach; ?>
                    <?php if ($filtro_guardia !== ''): ?>
                        <input type="hidden" name="q" value="<?= h($q) ?>">
                    <?php endif; ?>
                </div>
            </form>

            <?php
            if ($q !== '' || $filtro_guardia !== ''): ?>
                <span class="results-info"><?= $total_filtrado ?> resultado<?= $total_filtrado !== 1 ? 's' : '' ?></span>
            <?php else: ?>
                <span class="results-info"><?= $total_personal ?> total</span>
            <?php endif; ?>

            <?php
            // Export respeta el filtro activo
            $export_params = [];
            if ($q !== '') $export_params[] = 'q=' . urlencode($q);
            if ($filtro_guardia !== '') $export_params[] = 'guardia=' . urlencode($filtro_guardia);
            $export_url = 'exportar_personal.php' . ($export_params ? '?' . implode('&', $export_params) : '');
            ?>
            <a href="<?= $export_url ?>" class="btn-export" title="Exportar a Excel">
                <i class="fas fa-file-excel"></i>
                <span><?= $filtro_guardia ? "Exportar Grd. $filtro_guardia" : 'Exportar' ?></span>
            </a>
        </div>

        <!-- Grid de tarjetas -->
        <div class="cards-grid" id="cardsGrid">
            <?php if (empty($lista)): ?>
                <div class="empty-state">
                    <div class="empty-icon"><i class="fas fa-user-slash"></i></div>
                    <div class="empty-title">Sin resultados</div>
                    <div class="empty-desc">
                        <?php
                        if ($filtro_guardia !== '' && $q !== '')
                            echo 'Sin resultados para "' . h($q) . '" en Guardia ' . h($filtro_guardia) . '.';
                        elseif ($filtro_guardia !== '')
                            echo 'No hay colaboradores en Guardia ' . h($filtro_guardia) . '.';
                        elseif ($q !== '')
                            echo 'No se encontraron colaboradores para "' . h($q) . '".';
                        else
                            echo 'No hay personal registrado en el sistema.';
                        ?>
                    </div>
                    <?php if ($q !== ''): ?>
                        <a href="personal.php" class="btn-secondary" style="margin-top:4px;">
                            <i class="fas fa-arrow-left"></i> Ver todos
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <?php foreach ($lista as $i => $p):
                    $est    = $p['estado_validacion'] ?? 'VISITA';
                    $cls    = estadoClass($est);
                    $ini    = initials($p['nombres'], $p['apellidos']);
                    $colors = avatarColor($ini);
                    $estLabel = $est;
                    if (strpos($est, 'PROCESO') !== false) $estLabel = 'En proceso';
                    elseif (strpos($est, 'VISITA')  !== false) $estLabel = 'Visita';
                    else $estLabel = 'Afiliado';
                ?>
                <div class="p-card" style="animation-delay:<?= min($i * 0.03, 0.3) ?>s">
                    <div class="p-card-top">
                        <div class="p-avatar"
                             style="background:linear-gradient(135deg,<?= $colors[0] ?>,<?= $colors[1] ?>)">
                            <?= h($ini) ?>
                        </div>
                        <div class="p-info">
                            <div class="p-name"><?= h($p['apellidos'] . ', ' . $p['nombres']) ?></div>
                            <div class="p-dni">
                                DNI <?= h($p['dni']) ?>
                                <?= $p['codigo'] ? ' · ' . h($p['codigo']) : '' ?>
                            </div>
                            <?php if ($p['celular']): ?>
                            <div class="p-cel">
                                <i class="fas fa-mobile-alt" style="font-size:10px"></i>
                                <?= h($p['celular']) ?>
                            </div>
                            <?php endif; ?>
                            <div class="p-badges">
                                <span class="badge <?= $cls ?>"><?= $estLabel ?></span>
                                <?php
                                // Botón inline "ver datos médicos"
                                $cardId = 'med_' . preg_replace('/\W/','_', $p['dni']);
                                ?>
                                <button class="med-pill" type="button"
                                        onclick="toggleMed('<?= $cardId ?>', this)">
                                    <i class="fas fa-heart-pulse"></i>
                                    <span class="med-pill-txt">Ver datos médicos</span>
                                    <svg class="med-pill-arrow" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                                </button>
                            </div>

                            <!-- Drawer médico inline -->
                            <div class="p-med-drawer" id="<?= $cardId ?>">
                                <div class="p-med-inner">
                                    <div class="p-med-row">
                                        <span class="p-med-lbl">Grupo sanguíneo</span>
                                        <?php if ($p['grupo_sanguineo']): ?>
                                            <span class="p-med-gs"><?= h($p['grupo_sanguineo']) ?></span>
                                        <?php else: ?>
                                            <span class="p-med-val empty">—</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="p-med-row">
                                        <span class="p-med-lbl">Tel. emergencia</span>
                                        <span class="p-med-val <?= $p['telefono_emergencia'] ? '' : 'empty' ?>">
                                            <?= $p['telefono_emergencia'] ? h($p['telefono_emergencia']) : '—' ?>
                                        </span>
                                    </div>
                                    <?php if ($p['contacto_emergencia']): ?>
                                    <div class="p-med-row p-med-full">
                                        <span class="p-med-lbl">Contacto emergencia</span>
                                        <span class="p-med-val"><?= h($p['contacto_emergencia']) ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($p['enfermedades']): ?>
                                    <div class="p-med-row p-med-full">
                                        <span class="p-med-lbl">Enfermedades</span>
                                        <span class="p-med-val"><?= h($p['enfermedades']) ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($p['alergias']): ?>
                                    <div class="p-med-row p-med-full">
                                        <span class="p-med-lbl">Alergias</span>
                                        <span class="p-med-val"><?= h($p['alergias']) ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!$p['grupo_sanguineo'] && !$p['contacto_emergencia'] && !$p['telefono_emergencia'] && !$p['enfermedades'] && !$p['alergias']): ?>
                                    <div class="p-med-row p-med-full" style="padding:4px 0 2px">
                                        <span class="p-med-val empty">No hay datos médicos registrados.</span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div><!-- /p-info -->
                    </div><!-- /p-card-top -->

                        <?php
                        $guardia = strtoupper(trim($p['GUARDIA'] ?? $p['guardia'] ?? ''));
                        if ($guardia && in_array($guardia, ['A','B','C'])):
                            $gclass = "gb-$guardia";
                        ?>
                        <div class="guardia-badge <?= $gclass ?>" title="Guardia <?= h($guardia) ?>">
                            <span class="gb-sub">GRD</span>
                            <span class="gb-letra"><?= h($guardia) ?></span>
                        </div>
                        <?php endif; ?>

                    <div class="p-meta">
                        <div class="p-meta-item">
                            <span class="p-meta-label">Empresa</span>
                            <span class="p-meta-val" title="<?= h($p['empresa']) ?>">
                                <?= h($p['empresa'] ?: '—') ?>
                            </span>
                        </div>
                        <div class="p-meta-item">
                            <span class="p-meta-label">Área</span>
                            <span class="p-meta-val" title="<?= h($p['area']) ?>">
                                <?= h($p['area'] ?: '—') ?>
                            </span>
                        </div>
                        <?php if ($p['cargo']): ?>
                        <div class="p-meta-item p-meta-full">
                            <span class="p-meta-label">Cargo</span>
                            <span class="p-meta-val" title="<?= h($p['cargo']) ?>">
                                <?= h($p['cargo']) ?>
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="p-actions">
                        <a href="personal.php?dni=<?= urlencode($p['dni']) ?>"
                           class="p-action-btn btn-edit">
                            <i class="fas fa-pen-to-square"></i> Editar
                        </a>
                        <button class="p-action-btn btn-del"
                                onclick="confirmarBorrado('<?= h($p['dni']) ?>', '<?= h($p['apellidos'] . ' ' . $p['nombres']) ?>')">
                            <i class="fas fa-trash-alt"></i> Eliminar
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if ($total_pages > 1):
            // Construye URL base conservando q y guardia
            $pg_base = '?';
            if ($q !== '')             $pg_base .= 'q=' . urlencode($q) . '&';
            if ($filtro_guardia !== '') $pg_base .= 'guardia=' . urlencode($filtro_guardia) . '&';
            $pg_base .= 'page=';

            // Genera rango de páginas con elipsis
            function pg_range($cur, $total) {
                $r = [];
                for ($i = 1; $i <= $total; $i++) {
                    if ($i === 1 || $i === $total || abs($i - $cur) <= 2) $r[] = $i;
                    elseif (end($r) !== '…') $r[] = '…';
                }
                return $r;
            }
        ?>
        <div class="pagination">
            <span class="pg-info">
                <?= (($page-1)*$per_page)+1 ?>–<?= min($page*$per_page, $total_filtrado) ?>
                de <?= $total_filtrado ?>
            </span>
            <a class="pg-btn <?= $page <= 1 ? 'disabled' : '' ?>"
               href="<?= $pg_base . ($page-1) ?>">
                <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
            </a>
            <?php foreach (pg_range($page, $total_pages) as $p_num): ?>
                <?php if ($p_num === '…'): ?>
                    <span class="pg-dots">…</span>
                <?php else: ?>
                    <a class="pg-btn <?= $p_num === $page ? 'active' : '' ?>"
                       href="<?= $pg_base . $p_num ?>"><?= $p_num ?></a>
                <?php endif; ?>
            <?php endforeach; ?>
            <a class="pg-btn <?= $page >= $total_pages ? 'disabled' : '' ?>"
               href="<?= $pg_base . ($page+1) ?>">
                <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
            </a>
        </div>
        <?php endif; ?>

    <?php else: ?>

        <!-- Sin acceso al listado -->
        <div class="info-box">
            <div class="info-box-icon"><i class="fas fa-lock"></i></div>
            <h3>Base de datos protegida</h3>
            <p>El listado completo de colaboradores es accesible únicamente para administradores del sistema.</p>
        </div>

    <?php endif; ?>

</main>

<!-- ══════════════ BOTTOM NAV (móvil) ══════════════ -->
<nav class="bottom-nav">
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
    <div class="bn-emg" onclick="window.location='dashboard.php#emergencia'">
        <div class="bn-emg-circle">
            <svg viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        </div>
        <span class="bn-emg-lbl">Alerta</span>
    </div>
    <div class="bn-item active">
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
/* ── Reloj ── */
(function(){
    const D=['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'];
    const M=['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
    function tick(){
        const n=new Date(), p=v=>String(v).padStart(2,'0');
        const el=document.getElementById('clockEl');
        if(el) el.textContent=D[n.getDay()]+', '+n.getDate()+' '+M[n.getMonth()]+'  ·  '+p(n.getHours())+':'+p(n.getMinutes())+':'+p(n.getSeconds());
    }
    tick(); setInterval(tick,1000);
})();

/* ── Flip de tarjeta ── */
function flipCard(face) {
    const flipper = document.getElementById('fcFlipper');
    const btnF = document.getElementById('btnFrente');
    const btnR = document.getElementById('btnReverso');
    if (face === 'back') {
        flipper.classList.add('flipped');
        btnF.classList.remove('active');
        btnR.classList.add('active');
    } else {
        flipper.classList.remove('flipped');
        btnF.classList.add('active');
        btnR.classList.remove('active');
    }
}
function syncFlipperHeight() {
    const flipper = document.getElementById('fcFlipper');
    if (!flipper) return;
    const front = flipper.querySelector('.fc-face-front');
    const back  = flipper.querySelector('.fc-back');
    if (!front || !back) return;
    flipper.style.height = '';
    const savedPos = back.style.position;
    const savedTr  = back.style.transform;
    const savedVis = back.style.visibility;
    back.style.position = 'relative';
    back.style.transform = 'none';
    back.style.visibility = 'hidden';
    const backH = back.offsetHeight;
    back.style.position = savedPos;
    back.style.transform = savedTr;
    back.style.visibility = savedVis;
    flipper.style.height = Math.max(front.offsetHeight, backH) + 'px';
}

document.addEventListener('DOMContentLoaded', function() {
    syncFlipperHeight();
    window.addEventListener('resize', syncFlipperHeight);
    const params = new URLSearchParams(window.location.search);
    if (params.get('tab') === 'emergencia') switchTab('emergencia');

    // Búsqueda: 8 dígitos + 1.2s, o Enter
    const input = document.getElementById('searchInput');
    if (input) {
        let timer;
        input.addEventListener('input', function() {
            clearTimeout(timer);
            const v = this.value.trim();
            if (v.length === 0) {
                timer = setTimeout(() => document.getElementById('filterForm').submit(), 600);
                return;
            }
            if (v.length >= 8) {
                timer = setTimeout(() => document.getElementById('filterForm').submit(), 1200);
            }
        });
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                clearTimeout(timer);
                e.preventDefault();
                document.getElementById('filterForm').submit();
            }
        });
    }
});

/* ── Toggle drawer médico ── */
function toggleMed(id, btn) {
    const drawer = document.getElementById(id);
    if (!drawer) return;
    const isOpen = drawer.classList.contains('open');
    drawer.classList.toggle('open', !isOpen);
    btn.classList.toggle('open', !isOpen);
}

/* ── Confirmar borrado ── */
function confirmarBorrado(dni, nombre) {
    Swal.fire({
        title: '¿Eliminar colaborador?',
        html: '<b>' + nombre + '</b><br><small style="color:#6B6B6B">DNI: ' + dni + '</small><br><br>Se eliminará el colaborador y su historial. Esta acción no se puede deshacer.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#111827',
        cancelButtonColor: '#be123c',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        focusCancel: true,
        customClass: { popup: 'swal-popup-hm' }
    }).then(r => {
        if (r.isConfirmed) window.location.href = 'personal_eliminar.php?dni=' + encodeURIComponent(dni);
    });
}
</script>

</body>
</html>