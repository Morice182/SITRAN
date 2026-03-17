<?php
/**
 * emergencia.php — Módulo de Emergencias
 * Hochschild Mining — Sistema Integral de Transporte
 */

session_start();

if (!isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit();
}

require __DIR__ . "/config.php";

$email_sesion  = $_SESSION['usuario'];
$nombre_sesion = $_SESSION['nombre'];

// Obtener rol
$stmt = $mysqli->prepare("SELECT rol FROM usuarios WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email_sesion);
$stmt->execute();
$stmt->bind_result($rol_sistema);
if (!$stmt->fetch()) $rol_sistema = $_SESSION['rol'] ?? 'agente';
$stmt->close();

if (!in_array($rol_sistema, ['supervisor', 'administrador'])) {
    header("Location: dashboard.php");
    exit();
}

// ─── HELPERS ─────────────────────────────────────────────────────
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function jsonResponse($data) {
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

// ─── AJAX HANDLERS ───────────────────────────────────────────────
if (isset($_GET['ajax'])) {
    global $mysqli, $nombre_sesion;

    // 1. Buscar buses por placa o nombre
    if ($_GET['ajax'] === 'buscar_bus') {
        $q = '%' . trim($_GET['q'] ?? '') . '%';
        $st = $mysqli->prepare(
            "SELECT id, nombre_bus, placa_rodaje, conductor_1, origen, destino, fecha_viaje, hora_salida
             FROM cabecera_viaje
             WHERE placa_rodaje LIKE ? OR nombre_bus LIKE ?
             ORDER BY fecha_viaje DESC LIMIT 15"
        );
        $st->bind_param("ss", $q, $q);
        $st->execute();
        $res = $st->get_result()->fetch_all(MYSQLI_ASSOC);
        $st->close();
        jsonResponse($res);
    }

    // 2. Obtener pasajeros de un bus (subida O bajada)
    if ($_GET['ajax'] === 'pasajeros_bus') {
        $placa = trim($_GET['placa'] ?? '');
        $bus   = trim($_GET['bus']   ?? '');

        // Buscar en lista_subida y lista_bajada, preferir subida
        $rows = [];
        foreach (['lista_subida', 'lista_bajada'] as $tabla) {
            $st = $mysqli->prepare(
                "SELECT l.dni, l.asiento, l.destino,
                        p.nombres, p.apellidos, p.empresa, p.cargo,
                        p.grupo_sanguineo, p.alergias, p.enfermedades,
                        p.contacto_emergencia, p.telefono_emergencia
                 FROM `$tabla` l
                 LEFT JOIN personal p ON p.dni = l.dni
                 WHERE l.bus = ? OR l.placa = ?
                 ORDER BY l.asiento ASC"
            );
            $st->bind_param("ss", $bus, $placa);
            $st->execute();
            $found = $st->get_result()->fetch_all(MYSQLI_ASSOC);
            $st->close();
            if (count($found) > 0) { $rows = $found; break; }
        }

        // Si la placa fue ingresada manualmente y no hay coincidencias, devolver vacío
        jsonResponse(['pasajeros' => $rows, 'total' => count($rows)]);
    }

    // 3. Crear emergencia
    if ($_GET['ajax'] === 'crear_emergencia' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $st = $mysqli->prepare(
            "INSERT INTO emergencias (tipo_incidente, bus_nombre, bus_placa, conductor, ubicacion, observaciones, usuario_activa)
             VALUES (?,?,?,?,?,?,?)"
        );
        $st->bind_param("sssssss",
            $data['tipo_incidente'], $data['bus_nombre'], $data['bus_placa'],
            $data['conductor'], $data['ubicacion'], $data['observaciones'],
            $nombre_sesion
        );
        $st->execute();
        $emergencia_id = $mysqli->insert_id;
        $st->close();

        // Insertar pasajeros snapshot
        if (!empty($data['pasajeros'])) {
            $sp = $mysqli->prepare(
                "INSERT INTO emergencia_pasajeros
                 (emergencia_id, dni, nombres, apellidos, empresa, cargo, asiento,
                  grupo_sanguineo, alergias, enfermedades, contacto_emergencia, telefono_emergencia)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?)"
            );
            foreach ($data['pasajeros'] as $p) {
                $sp->bind_param("isssssssssss",
                    $emergencia_id, $p['dni'], $p['nombres'], $p['apellidos'],
                    $p['empresa'], $p['cargo'], $p['asiento'],
                    $p['grupo_sanguineo'], $p['alergias'], $p['enfermedades'],
                    $p['contacto_emergencia'], $p['telefono_emergencia']
                );
                $sp->execute();
            }
            $sp->close();
        }

        jsonResponse(['ok' => true, 'id' => $emergencia_id]);
    }

    // 4. Actualizar estado de pasajero
    if ($_GET['ajax'] === 'update_pasajero' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $st = $mysqli->prepare(
            "UPDATE emergencia_pasajeros SET estado=?, notas=? WHERE id=? AND emergencia_id=?"
        );
        $st->bind_param("ssii", $data['estado'], $data['notas'], $data['id'], $data['emergencia_id']);
        $st->execute();
        $st->close();
        jsonResponse(['ok' => true]);
    }

    // 5. Cargar emergencia activa
    if ($_GET['ajax'] === 'cargar_emergencia') {
        $eid = (int)$_GET['id'];
        $st = $mysqli->prepare("SELECT * FROM emergencias WHERE id=?");
        $st->bind_param("i", $eid);
        $st->execute();
        $em = $st->get_result()->fetch_assoc();
        $st->close();

        $sp = $mysqli->prepare("SELECT * FROM emergencia_pasajeros WHERE emergencia_id=? ORDER BY asiento ASC");
        $sp->bind_param("i", $eid);
        $sp->execute();
        $pax = $sp->get_result()->fetch_all(MYSQLI_ASSOC);
        $sp->close();

        jsonResponse(['emergencia' => $em, 'pasajeros' => $pax]);
    }

    // 6. Cerrar emergencia
    if ($_GET['ajax'] === 'cerrar_emergencia' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $st = $mysqli->prepare(
            "UPDATE emergencias SET estado_evento=?, usuario_cierra=?, fecha_cierre=NOW() WHERE id=?"
        );
        $st->bind_param("ssi", $data['estado'], $nombre_sesion, $data['id']);
        $st->execute();
        $st->close();
        jsonResponse(['ok' => true]);
    }

    // 7. Historial
    if ($_GET['ajax'] === 'historial') {
        $st = $mysqli->prepare(
            "SELECT e.*, 
             (SELECT COUNT(*) FROM emergencia_pasajeros WHERE emergencia_id=e.id) as total_pax,
             (SELECT COUNT(*) FROM emergencia_pasajeros WHERE emergencia_id=e.id AND estado='FALLECIDO') as fallecidos,
             (SELECT COUNT(*) FROM emergencia_pasajeros WHERE emergencia_id=e.id AND estado IN ('HERIDO_LEVE','HERIDO_GRAVE')) as heridos
             FROM emergencias e ORDER BY e.fecha_hora DESC LIMIT 30"
        );
        $st->execute();
        $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
        $st->close();
        jsonResponse($rows);
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Módulo de Emergencias | Hochschild Mining</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=IBM+Plex+Mono:wght@400;600&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<style>
:root {
    --bg:        #0a0b0d;
    --surface:   #111318;
    --surface2:  #1a1d24;
    --border:    #2a2d36;
    --gold:      #c5a059;
    --gold-dim:  rgba(197,160,89,0.15);
    --red:       #ff3b3b;
    --red-dim:   rgba(255,59,59,0.12);
    --orange:    #ff8c00;
    --yellow:    #ffd60a;
    --green:     #00e676;
    --gray:      #94a3b8;
    --white:     #f0f4ff;
    --radius:    12px;

    --s-ileso:       #00e676;
    --s-leve:        #ffd60a;
    --s-grave:       #ff8c00;
    --s-fallecido:   #ff3b3b;
    --s-noloc:       #94a3b8;
    --s-trasladado:  #60a5fa;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
    font-family: 'Manrope', sans-serif;
    background: var(--bg);
    color: var(--white);
    min-height: 100vh;
    -webkit-font-smoothing: antialiased;
}

/* ── TOPBAR ── */
.topbar {
    display: flex; align-items: center; justify-content: space-between;
    padding: 16px 20px;
    background: var(--surface);
    border-bottom: 1px solid var(--border);
    position: sticky; top: 0; z-index: 100;
    gap: 12px;
}
.topbar-left { display: flex; align-items: center; gap: 14px; }
.back-btn {
    width: 36px; height: 36px;
    background: var(--surface2); border: 1px solid var(--border);
    border-radius: 8px; color: var(--gray);
    display: flex; align-items: center; justify-content: center;
    text-decoration: none; transition: .2s; font-size: 14px; flex-shrink: 0;
}
.back-btn:hover { color: var(--white); border-color: var(--gold); }

.module-title { font-family: 'Syne', sans-serif; font-size: 16px; font-weight: 800; color: var(--white); line-height: 1.1; }
.module-subtitle { font-size: 10px; color: var(--gray); font-family: 'IBM Plex Mono', monospace; }

.status-pill {
    display: flex; align-items: center; gap: 6px;
    font-family: 'IBM Plex Mono', monospace;
    font-size: 10px; font-weight: 600;
    padding: 5px 12px; border-radius: 20px;
    transition: .3s;
}
.status-pill.standby { background: var(--surface2); border: 1px solid var(--border); color: var(--gray); }
.status-pill.active  { background: var(--red-dim); border: 1px solid var(--red); color: var(--red); }
.status-pill.active .pulse {
    width: 7px; height: 7px; border-radius: 50%; background: var(--red);
    animation: pulse 1.2s ease-in-out infinite;
}
.status-pill.standby .pulse { display: none; }
@keyframes pulse { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.4;transform:scale(1.4)} }

/* ── MAIN ── */
.main { max-width: 700px; margin: 0 auto; padding: 24px 16px 80px; }

/* ── TABS ── */
.tabs {
    display: grid; grid-template-columns: 1fr 1fr;
    background: var(--surface); border: 1px solid var(--border);
    border-radius: var(--radius); overflow: hidden; margin-bottom: 24px;
}
.tab-btn {
    padding: 12px; text-align: center;
    font-size: 12px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase;
    cursor: pointer; color: var(--gray); border: none; background: none;
    transition: .2s; font-family: 'Manrope', sans-serif;
}
.tab-btn.active { background: var(--surface2); color: var(--white); }
.tab-btn:first-child { border-right: 1px solid var(--border); }

/* ── PANEL: NUEVA EMERGENCIA ── */
.panel { display: none; }
.panel.active { display: block; }

/* ── BIG RED BUTTON ── */
.trigger-zone {
    display: flex; flex-direction: column; align-items: center;
    padding: 32px 20px; margin-bottom: 28px;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 20px;
    position: relative; overflow: hidden;
}
.trigger-zone::before {
    content: '';
    position: absolute; inset: 0;
    background: radial-gradient(ellipse at top, var(--red-dim) 0%, transparent 70%);
    pointer-events: none;
}
.trigger-label {
    font-family: 'IBM Plex Mono', monospace;
    font-size: 10px; font-weight: 600; letter-spacing: 3px;
    color: var(--gray); text-transform: uppercase; margin-bottom: 24px;
}

.sos-btn {
    width: 120px; height: 120px;
    border-radius: 50%;
    background: conic-gradient(from 0deg, #ff1a1a, #ff5050, #ff1a1a);
    border: none; cursor: pointer; position: relative;
    box-shadow: 0 0 0 8px rgba(255,59,59,0.1), 0 0 0 16px rgba(255,59,59,0.05);
    transition: transform .15s, box-shadow .15s;
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    gap: 4px;
}
.sos-btn:hover {
    transform: scale(1.05);
    box-shadow: 0 0 0 10px rgba(255,59,59,0.15), 0 0 0 22px rgba(255,59,59,0.08);
}
.sos-btn:active { transform: scale(0.96); }
.sos-btn.disabled { opacity: 0.4; cursor: not-allowed; }
.sos-btn .sos-text {
    font-family: 'Syne', sans-serif; font-size: 26px; font-weight: 800;
    color: white; letter-spacing: 2px; line-height: 1;
}
.sos-btn .sos-sub {
    font-family: 'IBM Plex Mono', monospace; font-size: 8px;
    color: rgba(255,255,255,0.7); letter-spacing: 2px;
}
.sos-btn.armed {
    animation: armed-pulse 2s ease-in-out infinite;
}
@keyframes armed-pulse {
    0%,100% { box-shadow: 0 0 0 8px rgba(255,59,59,0.2), 0 0 0 16px rgba(255,59,59,0.08); }
    50%      { box-shadow: 0 0 0 14px rgba(255,59,59,0.3), 0 0 0 28px rgba(255,59,59,0.12); }
}

.arm-hint { font-size: 11px; color: var(--gray); margin-top: 20px; text-align: center; }

/* ── FORMULARIO EMERGENCIA ── */
.form-section {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: var(--radius); padding: 20px; margin-bottom: 16px;
}
.form-section-title {
    font-family: 'IBM Plex Mono', monospace;
    font-size: 10px; font-weight: 600; color: var(--gold);
    text-transform: uppercase; letter-spacing: 2px;
    margin-bottom: 16px; display: flex; align-items: center; gap: 8px;
}
.form-section-title::after {
    content: ''; flex: 1; height: 1px; background: var(--border);
}

.form-row { display: flex; gap: 12px; }
.form-group { display: flex; flex-direction: column; gap: 6px; flex: 1; }
label { font-size: 11px; font-weight: 700; color: var(--gray); text-transform: uppercase; letter-spacing: 0.5px; }

input[type=text], textarea, select {
    background: var(--bg); border: 1px solid var(--border);
    color: var(--white); border-radius: 8px;
    padding: 10px 12px; font-size: 13px;
    font-family: 'Manrope', sans-serif;
    transition: border-color .2s; width: 100%;
    -webkit-appearance: none;
}
input[type=text]:focus, textarea:focus, select:focus {
    outline: none; border-color: var(--gold);
}
textarea { resize: none; min-height: 80px; }
select option { background: #1a1d24; }

/* Bus Search */
.bus-search-wrap { position: relative; }
.bus-dropdown {
    display: none; position: absolute; top: calc(100% + 4px); left: 0; right: 0;
    background: var(--surface2); border: 1px solid var(--border);
    border-radius: 8px; z-index: 50; max-height: 240px; overflow-y: auto;
}
.bus-dropdown.open { display: block; }
.bus-option {
    padding: 10px 14px; cursor: pointer; border-bottom: 1px solid var(--border);
    transition: background .15s;
}
.bus-option:last-child { border: none; }
.bus-option:hover { background: var(--border); }
.bus-option .bus-name { font-size: 13px; font-weight: 700; color: var(--white); }
.bus-option .bus-meta { font-size: 11px; color: var(--gray); font-family: 'IBM Plex Mono', monospace; margin-top: 2px; }
.bus-option .bus-placa {
    display: inline-block; background: var(--gold-dim); color: var(--gold);
    font-family: 'IBM Plex Mono', monospace; font-size: 10px; font-weight: 700;
    padding: 2px 8px; border-radius: 4px; margin-left: 6px;
}

/* ── KPI COUNTER ── */
.kpi-bar {
    display: grid; grid-template-columns: repeat(3, 1fr) repeat(3, 1fr);
    gap: 8px; margin-bottom: 20px;
}
@media (max-width: 500px) { .kpi-bar { grid-template-columns: repeat(3, 1fr); } }

.kpi-box {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: 10px; padding: 12px 8px; text-align: center;
}
.kpi-box .kpi-n { font-family: 'Syne', sans-serif; font-size: 24px; font-weight: 800; line-height: 1; }
.kpi-box .kpi-l { font-size: 9px; font-weight: 700; color: var(--gray); text-transform: uppercase; letter-spacing: 0.5px; margin-top: 4px; }
.kpi-box.k-total  { border-color: var(--border); }
.kpi-box.k-ileso  { border-color: var(--s-ileso);     } .kpi-box.k-ileso  .kpi-n { color: var(--s-ileso); }
.kpi-box.k-leve   { border-color: var(--s-leve);      } .kpi-box.k-leve   .kpi-n { color: var(--s-leve); }
.kpi-box.k-grave  { border-color: var(--s-grave);     } .kpi-box.k-grave  .kpi-n { color: var(--s-grave); }
.kpi-box.k-fall   { border-color: var(--s-fallecido); } .kpi-box.k-fall   .kpi-n { color: var(--s-fallecido); }
.kpi-box.k-noloc  { border-color: var(--s-noloc);     } .kpi-box.k-noloc  .kpi-n { color: var(--s-noloc); }

/* ── PASAJEROS TABLE ── */
.pax-header {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 12px;
}
.pax-title {
    font-family: 'IBM Plex Mono', monospace; font-size: 11px; font-weight: 600;
    color: var(--gold); text-transform: uppercase; letter-spacing: 1px;
}
.pax-count { font-size: 11px; color: var(--gray); }

.pax-list { display: flex; flex-direction: column; gap: 8px; }

.pax-card {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: 10px; padding: 14px;
    border-left: 4px solid var(--border);
    transition: border-color .2s;
    animation: slideIn .3s ease-out;
}
@keyframes slideIn { from{opacity:0;transform:translateY(8px)} to{opacity:1;transform:none} }

.pax-card[data-estado="ILESO"]        { border-left-color: var(--s-ileso); }
.pax-card[data-estado="HERIDO_LEVE"]  { border-left-color: var(--s-leve); }
.pax-card[data-estado="HERIDO_GRAVE"] { border-left-color: var(--s-grave); }
.pax-card[data-estado="FALLECIDO"]    { border-left-color: var(--s-fallecido); background: rgba(255,59,59,0.04); }
.pax-card[data-estado="NO_LOCALIZADO"]{ border-left-color: var(--s-noloc); }
.pax-card[data-estado="TRASLADADO"]   { border-left-color: var(--s-trasladado); }

.pax-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; }
.pax-info .pax-name { font-size: 14px; font-weight: 700; color: var(--white); }
.pax-info .pax-meta { font-size: 11px; color: var(--gray); margin-top: 2px; }
.pax-info .pax-dni  { font-family: 'IBM Plex Mono', monospace; font-size: 10px; color: var(--gray); }

.asiento-badge {
    background: var(--surface2); border: 1px solid var(--border);
    border-radius: 6px; padding: 4px 8px;
    font-family: 'IBM Plex Mono', monospace; font-size: 11px; font-weight: 600; color: var(--gold);
    white-space: nowrap; flex-shrink: 0;
}

.pax-medical {
    margin-top: 10px; padding-top: 10px;
    border-top: 1px solid var(--border);
    display: grid; grid-template-columns: repeat(2, 1fr); gap: 6px;
}
.med-item { font-size: 10px; }
.med-label { color: var(--gray); font-weight: 700; text-transform: uppercase; }
.med-val   { color: var(--white); margin-top: 2px; }
.med-val.blood {
    display: inline-block; background: var(--red-dim); color: var(--red);
    border-radius: 4px; padding: 1px 8px; font-family: 'IBM Plex Mono', monospace;
    font-weight: 700; font-size: 11px;
}

.pax-controls { margin-top: 12px; display: flex; gap: 8px; flex-wrap: wrap; }

.estado-select {
    flex: 1; min-width: 140px;
    background: var(--bg); border: 1px solid var(--border);
    color: var(--white); border-radius: 6px; padding: 6px 10px;
    font-size: 12px; font-family: 'Manrope', sans-serif;
}
.notas-input {
    flex: 2; min-width: 0;
    background: var(--bg); border: 1px solid var(--border);
    color: var(--white); border-radius: 6px; padding: 6px 10px;
    font-size: 12px; font-family: 'Manrope', sans-serif;
}
.save-pax-btn {
    background: var(--gold-dim); border: 1px solid var(--gold);
    color: var(--gold); border-radius: 6px; padding: 6px 14px;
    font-size: 11px; font-weight: 700; cursor: pointer;
    white-space: nowrap; transition: .2s;
}
.save-pax-btn:hover { background: var(--gold); color: #000; }

/* ── BUTTONS ── */
.btn {
    display: inline-flex; align-items: center; justify-content: center; gap: 8px;
    padding: 12px 20px; border-radius: 8px; border: none; cursor: pointer;
    font-size: 13px; font-weight: 700; font-family: 'Manrope', sans-serif;
    transition: .2s; text-decoration: none;
}
.btn-primary { background: var(--gold); color: #000; }
.btn-primary:hover { background: #d4af6a; }
.btn-danger  { background: var(--red); color: white; }
.btn-danger:hover { background: #ff5555; }
.btn-outline { background: transparent; border: 1px solid var(--border); color: var(--gray); }
.btn-outline:hover { border-color: var(--gold); color: var(--gold); }
.btn-green { background: rgba(0,230,118,0.15); border: 1px solid var(--green); color: var(--green); }
.btn-green:hover { background: rgba(0,230,118,0.25); }
.btn-full { width: 100%; }

.action-row { display: flex; gap: 10px; margin-top: 20px; }

/* ── HISTORIAL ── */
.hist-card {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: var(--radius); padding: 16px; margin-bottom: 10px;
    cursor: pointer; transition: border-color .2s;
    animation: slideIn .3s ease-out;
}
.hist-card:hover { border-color: var(--gold); }
.hist-top { display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; }
.hist-tipo { font-size: 14px; font-weight: 700; color: var(--white); }
.hist-bus  { font-size: 11px; color: var(--gray); font-family: 'IBM Plex Mono', monospace; margin-top: 3px; }
.hist-fecha { font-size: 10px; color: var(--gray); font-family: 'IBM Plex Mono', monospace; text-align: right; flex-shrink: 0; }
.hist-stats { display: flex; gap: 8px; margin-top: 10px; flex-wrap: wrap; }
.hist-badge {
    font-size: 10px; font-weight: 700; padding: 3px 10px;
    border-radius: 20px; font-family: 'IBM Plex Mono', monospace;
}
.hist-badge.activo     { background: var(--red-dim);           color: var(--red);        border: 1px solid var(--red); }
.hist-badge.controlado { background: rgba(255,140,0,0.12);     color: var(--orange);     border: 1px solid var(--orange); }
.hist-badge.cerrado    { background: rgba(148,163,184,0.1);    color: var(--gray);       border: 1px solid var(--border); }

.empty-state {
    text-align: center; padding: 60px 20px;
    color: var(--gray); font-size: 13px;
}
.empty-state i { font-size: 32px; display: block; margin-bottom: 12px; opacity: .4; }

/* ── MODAL ── */
.modal-overlay {
    display: none; position: fixed; inset: 0;
    background: rgba(0,0,0,.8); z-index: 200;
    align-items: flex-end; justify-content: center;
}
.modal-overlay.open { display: flex; }
.modal {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: 20px 20px 0 0; padding: 24px 20px 40px;
    width: 100%; max-width: 700px; max-height: 90vh; overflow-y: auto;
    animation: slideUp .35s cubic-bezier(0.16,1,0.3,1);
}
@keyframes slideUp { from{transform:translateY(60px);opacity:0} to{transform:none;opacity:1} }
.modal-title {
    font-family: 'Syne', sans-serif; font-size: 18px; font-weight: 800;
    color: var(--white); margin-bottom: 4px;
}
.modal-sub { font-size: 12px; color: var(--gray); margin-bottom: 20px; }

/* ── LOADER ── */
.loader { display: none; text-align: center; padding: 40px; }
.loader.show { display: block; }
.spinner {
    width: 32px; height: 32px; border: 3px solid var(--border);
    border-top-color: var(--gold); border-radius: 50%;
    animation: spin .7s linear infinite; margin: 0 auto 12px;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ── TOAST ── */
.toast {
    position: fixed; bottom: 24px; right: 20px; left: 20px; max-width: 360px; margin: 0 auto;
    background: var(--surface2); border: 1px solid var(--border);
    border-radius: 10px; padding: 12px 16px;
    font-size: 13px; font-weight: 600;
    display: none; z-index: 500;
    animation: fadeIn .3s ease;
}
.toast.show { display: block; }
@keyframes fadeIn { from{opacity:0;transform:translateY(10px)} to{opacity:1;transform:none} }
.toast.ok { border-color: var(--green); color: var(--green); }
.toast.err { border-color: var(--red); color: var(--red); }

/* ── CONFIRM DIALOG ── */
.confirm-overlay {
    display: none; position: fixed; inset: 0;
    background: rgba(0,0,0,.85); z-index: 300;
    align-items: center; justify-content: center; padding: 20px;
}
.confirm-overlay.open { display: flex; }
.confirm-box {
    background: var(--surface); border: 2px solid var(--red);
    border-radius: 16px; padding: 28px 24px; max-width: 340px; width: 100%;
    text-align: center; animation: scaleIn .25s cubic-bezier(0.16,1,0.3,1);
}
@keyframes scaleIn { from{opacity:0;transform:scale(0.9)} to{opacity:1;transform:scale(1)} }
.confirm-icon { font-size: 40px; color: var(--red); margin-bottom: 14px; }
.confirm-title { font-family: 'Syne', sans-serif; font-size: 18px; font-weight: 800; margin-bottom: 8px; }
.confirm-text { font-size: 13px; color: var(--gray); margin-bottom: 24px; line-height: 1.5; }
.confirm-btns { display: flex; gap: 10px; }

/* ── PRINT ── */
@media print {
    .topbar, .tabs, .trigger-zone, .action-row, .pax-controls, .tab-btn, .modal-overlay,
    .confirm-overlay, .toast, .back-btn, #tabHistorial { display: none !important; }
    body { background: white; color: black; }
    .kpi-box, .pax-card { border: 1px solid #ccc !important; break-inside: avoid; }
    .main { max-width: 100%; padding: 0; }
    .print-header { display: block !important; }
}
.print-header {
    display: none;
    text-align: center; margin-bottom: 20px; padding-bottom: 16px;
    border-bottom: 2px solid #333;
}
.print-header h1 { font-size: 20px; font-weight: 800; }
.print-header p  { font-size: 12px; color: #555; }
</style>
</head>
<body>

<!-- TOPBAR -->
<header class="topbar">
    <div class="topbar-left">
        <a href="dashboard.php" class="back-btn"><i class="fas fa-arrow-left"></i></a>
        <div>
            <div class="module-title">🚨 EMERGENCIAS</div>
            <div class="module-subtitle">HOCHSCHILD MINING</div>
        </div>
    </div>
    <div class="status-pill standby" id="statusPill">
        <span class="pulse"></span>
        <span id="statusText">STANDBY</span>
    </div>
</header>

<!-- PRINT HEADER -->
<div class="print-header">
    <h1>REPORTE DE EMERGENCIA — HOCHSCHILD MINING</h1>
    <p id="printMeta"></p>
</div>

<div class="main">

    <!-- TABS -->
    <div class="tabs">
        <button class="tab-btn active" onclick="switchTab('nueva')">
            <i class="fas fa-bolt"></i> &nbsp;NUEVA EMERGENCIA
        </button>
        <button class="tab-btn" onclick="switchTab('historial')">
            <i class="fas fa-clock-rotate-left"></i> &nbsp;HISTORIAL
        </button>
    </div>

    <!-- PANEL: NUEVA EMERGENCIA -->
    <div class="panel active" id="panelNueva">

        <!-- BOTÓN SOS -->
        <div class="trigger-zone" id="triggerZone">
            <div class="trigger-label">— Activar protocolo de emergencia —</div>
            <button class="sos-btn" id="sosBtn" onclick="armSOS()">
                <span class="sos-text">SOS</span>
                <span class="sos-sub">ACTIVAR</span>
            </button>
            <p class="arm-hint" id="armHint">Presiona para armar · Segunda pulsación confirma</p>
        </div>

        <!-- FORMULARIO (oculto hasta activar) -->
        <div id="formEmergencia" style="display:none">

            <!-- TIPO E INFO -->
            <div class="form-section">
                <div class="form-section-title"><i class="fas fa-triangle-exclamation"></i> Tipo de Incidente</div>
                <div class="form-row" style="margin-bottom:12px">
                    <div class="form-group">
                        <label>Tipo de incidente</label>
                        <select id="tipoIncidente">
                            <option value="">— Seleccionar —</option>
                            <option>Accidente de tránsito</option>
                            <option>Volcadura de bus</option>
                            <option>Despiste</option>
                            <option>Incendio a bordo</option>
                            <option>Falla mecánica grave</option>
                            <option>Derrumbe / Huayco</option>
                            <option>Asalto / Robo</option>
                            <option>Emergencia médica a bordo</option>
                            <option>Otro</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Ubicación del incidente</label>
                    <input type="text" id="ubicacion" placeholder="Ej: Km 48 ruta Arequipa-Inmaculada">
                </div>
            </div>

            <!-- BUS -->
            <div class="form-section">
                <div class="form-section-title"><i class="fas fa-bus"></i> Identificación del Bus</div>
                <div class="form-group" style="margin-bottom:12px">
                    <label>Buscar por nombre o placa</label>
                    <div class="bus-search-wrap">
                        <input type="text" id="busSearch" placeholder="Ej: Arequipa 2 o F5Q-964" autocomplete="off">
                        <div class="bus-dropdown" id="busDropdown"></div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Nombre del Bus</label>
                        <input type="text" id="busNombre" placeholder="AREQUIPA 2 - D">
                    </div>
                    <div class="form-group">
                        <label>Placa</label>
                        <input type="text" id="busPlaca" placeholder="F5Q-964">
                    </div>
                </div>
                <div class="form-group" style="margin-top:12px">
                    <label>Conductor</label>
                    <input type="text" id="busConductor" placeholder="Nombre del conductor">
                </div>
            </div>

            <!-- OBSERVACIONES -->
            <div class="form-section">
                <div class="form-section-title"><i class="fas fa-note-sticky"></i> Observaciones Generales</div>
                <textarea id="observaciones" placeholder="Descripción del evento, condiciones del lugar, acciones tomadas..."></textarea>
            </div>

            <!-- BTN CARGAR PASAJEROS -->
            <button class="btn btn-primary btn-full" onclick="cargarPasajeros()">
                <i class="fas fa-users"></i> Cargar Manifiesto de Pasajeros
            </button>

            <!-- LOADER -->
            <div class="loader" id="loaderPax">
                <div class="spinner"></div>
                <p style="color:var(--gray);font-size:12px">Cargando pasajeros...</p>
            </div>

            <!-- KPI BAR -->
            <div id="kpiBar" style="display:none; margin-top:20px">
                <div class="kpi-bar">
                    <div class="kpi-box k-total">
                        <div class="kpi-n" id="kTotal">0</div>
                        <div class="kpi-l">Total</div>
                    </div>
                    <div class="kpi-box k-ileso">
                        <div class="kpi-n" id="kIleso">0</div>
                        <div class="kpi-l">Ilesos</div>
                    </div>
                    <div class="kpi-box k-leve">
                        <div class="kpi-n" id="kLeve">0</div>
                        <div class="kpi-l">H. Leve</div>
                    </div>
                    <div class="kpi-box k-grave">
                        <div class="kpi-n" id="kGrave">0</div>
                        <div class="kpi-l">H. Grave</div>
                    </div>
                    <div class="kpi-box k-fall">
                        <div class="kpi-n" id="kFall">0</div>
                        <div class="kpi-l">Fallecidos</div>
                    </div>
                    <div class="kpi-box k-noloc">
                        <div class="kpi-n" id="kNoloc">0</div>
                        <div class="kpi-l">No loc.</div>
                    </div>
                </div>
            </div>

            <!-- LISTA PASAJEROS -->
            <div id="paxSection" style="display:none; margin-top:16px">
                <div class="pax-header">
                    <div class="pax-title"><i class="fas fa-users"></i> Pasajeros</div>
                    <div class="pax-count" id="paxCount"></div>
                </div>
                <div class="pax-list" id="paxList"></div>
            </div>

            <!-- ACTIONS -->
            <div class="action-row" id="actionRow" style="display:none">
                <button class="btn btn-danger" style="flex:1" onclick="confirmarActivar()">
                    <i class="fas fa-siren-on"></i> Registrar Emergencia
                </button>
                <button class="btn btn-outline" onclick="printReport()">
                    <i class="fas fa-print"></i>
                </button>
            </div>
        </div>

        <!-- EMERGENCIA ACTIVA (post-registro) -->
        <div id="emergenciaActiva" style="display:none">
            <div id="kpiBarActiva" style="margin-bottom:20px">
                <div class="kpi-bar">
                    <div class="kpi-box k-total"><div class="kpi-n" id="kaTotal">0</div><div class="kpi-l">Total</div></div>
                    <div class="kpi-box k-ileso"><div class="kpi-n" id="kaIleso">0</div><div class="kpi-l">Ilesos</div></div>
                    <div class="kpi-box k-leve"><div class="kpi-n" id="kaLeve">0</div><div class="kpi-l">H. Leve</div></div>
                    <div class="kpi-box k-grave"><div class="kpi-n" id="kaGrave">0</div><div class="kpi-l">H. Grave</div></div>
                    <div class="kpi-box k-fall"><div class="kpi-n" id="kaFall">0</div><div class="kpi-l">Fallecidos</div></div>
                    <div class="kpi-box k-noloc"><div class="kpi-n" id="kaNoloc">0</div><div class="kpi-l">No loc.</div></div>
                </div>
            </div>
            <div class="pax-list" id="paxListActiva"></div>
            <div class="action-row">
                <button class="btn btn-outline" style="flex:1" onclick="showCerrarModal()">
                    <i class="fas fa-circle-check"></i> Cerrar Emergencia
                </button>
                <button class="btn btn-outline" onclick="printReport()">
                    <i class="fas fa-print"></i>
                </button>
            </div>
        </div>

    </div><!-- /panelNueva -->

    <!-- PANEL: HISTORIAL -->
    <div class="panel" id="panelHistorial">
        <div class="loader show" id="loaderHist">
            <div class="spinner"></div>
            <p style="color:var(--gray);font-size:12px">Cargando historial...</p>
        </div>
        <div id="histList"></div>
    </div>

</div><!-- /main -->

<!-- MODAL: Ver emergencia del historial -->
<div class="modal-overlay" id="histModal">
    <div class="modal">
        <div class="modal-title" id="histModalTitle">Emergencia</div>
        <div class="modal-sub" id="histModalSub"></div>
        <div class="kpi-bar" id="histModalKpi"></div>
        <div class="pax-list" id="histModalPax" style="margin-top:16px"></div>
        <div style="margin-top:20px">
            <button class="btn btn-outline btn-full" onclick="closeHistModal()">
                <i class="fas fa-times"></i> Cerrar
            </button>
        </div>
    </div>
</div>

<!-- MODAL: Cerrar emergencia -->
<div class="modal-overlay" id="cerrarModal">
    <div class="modal">
        <div class="modal-title">Cerrar Emergencia</div>
        <div class="modal-sub">Actualiza el estado final del evento</div>
        <div class="form-group" style="margin-bottom:16px">
            <label>Estado final</label>
            <select id="estadoCierre">
                <option value="CONTROLADO">CONTROLADO</option>
                <option value="CERRADO">CERRADO</option>
            </select>
        </div>
        <div class="confirm-btns">
            <button class="btn btn-outline" style="flex:1" onclick="closeCerrarModal()">Cancelar</button>
            <button class="btn btn-green" style="flex:1" onclick="cerrarEmergencia()">
                <i class="fas fa-circle-check"></i> Confirmar Cierre
            </button>
        </div>
    </div>
</div>

<!-- CONFIRM: Registrar emergencia -->
<div class="confirm-overlay" id="confirmOverlay">
    <div class="confirm-box">
        <div class="confirm-icon"><i class="fas fa-triangle-exclamation"></i></div>
        <div class="confirm-title">¿Confirmar Emergencia?</div>
        <div class="confirm-text">
            Esta acción quedará registrada con fecha, hora y tu usuario.<br>
            Solo activa si es una emergencia real.
        </div>
        <div class="confirm-btns">
            <button class="btn btn-outline" style="flex:1" onclick="cancelarActivar()">Cancelar</button>
            <button class="btn btn-danger" style="flex:1" onclick="activarEmergencia()">
                <i class="fas fa-siren-on"></i> ACTIVAR
            </button>
        </div>
    </div>
</div>

<!-- TOAST -->
<div class="toast" id="toast"></div>

<script>
// ── STATE ──────────────────────────────────────────────────────────
let armed = false;
let armTimer = null;
let paxData = []; // pasajeros en formulario (pre-registro)
let emergenciaId = null;
let paxActiva = []; // pasajeros con id de BD (post-registro)

// ── UTILS ─────────────────────────────────────────────────────────
function showToast(msg, type='ok') {
    const t = document.getElementById('toast');
    t.className = 'toast show ' + type;
    t.innerHTML = (type==='ok' ? '✓ ' : '✗ ') + msg;
    setTimeout(() => t.className = 'toast', 3000);
}

function updateStatusPill(state) {
    const pill = document.getElementById('statusPill');
    const txt  = document.getElementById('statusText');
    pill.className = 'status-pill ' + state;
    txt.textContent = state === 'active' ? 'EMERGENCIA ACTIVA' : 'STANDBY';
}

function formatDateTime(s) {
    if (!s) return '—';
    return new Date(s.replace(' ','T')).toLocaleString('es-PE', {
        day:'2-digit', month:'2-digit', year:'numeric',
        hour:'2-digit', minute:'2-digit'
    });
}

function estadoLabel(e) {
    const m = {
        'ILESO':'✅ Ileso','HERIDO_LEVE':'🟡 Herido leve','HERIDO_GRAVE':'🟠 Herido grave',
        'FALLECIDO':'⚫ Fallecido','NO_LOCALIZADO':'❓ No localizado','TRASLADADO':'🔵 Trasladado'
    };
    return m[e] || e;
}

// ── TABS ──────────────────────────────────────────────────────────
function switchTab(tab) {
    document.querySelectorAll('.tab-btn').forEach((b,i) => {
        b.classList.toggle('active', (i===0) === (tab==='nueva'));
    });
    document.getElementById('panelNueva').classList.toggle('active', tab==='nueva');
    document.getElementById('panelHistorial').classList.toggle('active', tab==='historial');
    if (tab === 'historial') loadHistorial();
}

// ── ARM / SOS ─────────────────────────────────────────────────────
function armSOS() {
    if (emergenciaId) return; // ya hay una activa
    if (!armed) {
        armed = true;
        const btn = document.getElementById('sosBtn');
        btn.classList.add('armed');
        document.getElementById('armHint').textContent = '⚠️ Presiona de nuevo para confirmar — se cancela en 5s';
        armTimer = setTimeout(() => {
            armed = false;
            btn.classList.remove('armed');
            document.getElementById('armHint').textContent = 'Presiona para armar · Segunda pulsación confirma';
        }, 5000);
    } else {
        clearTimeout(armTimer);
        armed = false;
        document.getElementById('sosBtn').classList.remove('armed');
        document.getElementById('sosBtn').classList.add('disabled');
        document.getElementById('triggerZone').style.display = 'none';
        document.getElementById('formEmergencia').style.display = 'block';
        updateStatusPill('active');
    }
}

// ── BUS SEARCH ────────────────────────────────────────────────────
let searchTimer;
document.getElementById('busSearch').addEventListener('input', function() {
    clearTimeout(searchTimer);
    const q = this.value.trim();
    if (q.length < 2) { document.getElementById('busDropdown').classList.remove('open'); return; }
    searchTimer = setTimeout(() => {
        fetch(`?ajax=buscar_bus&q=${encodeURIComponent(q)}`)
            .then(r => r.json())
            .then(data => {
                const dd = document.getElementById('busDropdown');
                if (!data.length) { dd.classList.remove('open'); return; }
                dd.innerHTML = data.map(b => `
                    <div class="bus-option" onclick="selectBus(${JSON.stringify(b).replace(/"/g,'&quot;')})">
                        <div class="bus-name">${b.nombre_bus || '—'} <span class="bus-placa">${b.placa_rodaje || ''}</span></div>
                        <div class="bus-meta">${b.origen || ''} → ${b.destino || ''} &nbsp;|&nbsp; ${b.conductor_1 || ''}</div>
                    </div>`).join('');
                dd.classList.add('open');
            });
    }, 300);
});

document.addEventListener('click', e => {
    if (!e.target.closest('.bus-search-wrap'))
        document.getElementById('busDropdown').classList.remove('open');
});

function selectBus(b) {
    document.getElementById('busNombre').value    = b.nombre_bus    || '';
    document.getElementById('busPlaca').value     = b.placa_rodaje  || '';
    document.getElementById('busConductor').value = b.conductor_1   || '';
    document.getElementById('busSearch').value    = '';
    document.getElementById('busDropdown').classList.remove('open');
}

// ── CARGAR PASAJEROS ──────────────────────────────────────────────
function cargarPasajeros() {
    const placa = document.getElementById('busPlaca').value.trim();
    const bus   = document.getElementById('busNombre').value.trim();
    if (!placa && !bus) { showToast('Ingresa el bus o placa primero', 'err'); return; }

    document.getElementById('loaderPax').classList.add('show');
    document.getElementById('paxSection').style.display = 'none';
    document.getElementById('kpiBar').style.display = 'none';
    document.getElementById('actionRow').style.display = 'none';

    fetch(`?ajax=pasajeros_bus&placa=${encodeURIComponent(placa)}&bus=${encodeURIComponent(bus)}`)
        .then(r => r.json())
        .then(data => {
            document.getElementById('loaderPax').classList.remove('show');
            paxData = data.pasajeros;

            if (!paxData.length) {
                showToast('No se encontraron pasajeros para este bus. Puedes continuar igual.', 'err');
            }

            renderPaxList(paxData, 'paxList', false);
            updateKPIs(paxData, false);
            document.getElementById('paxSection').style.display = 'block';
            document.getElementById('paxCount').textContent = paxData.length + ' pasajeros';
            document.getElementById('kpiBar').style.display = 'block';
            document.getElementById('actionRow').style.display = 'flex';
        });
}

// ── RENDER PASAJEROS ──────────────────────────────────────────────
function renderPaxList(pax, containerId, modoActivo) {
    const container = document.getElementById(containerId);
    if (!pax.length) {
        container.innerHTML = '<div class="empty-state"><i class="fas fa-user-slash"></i>Sin pasajeros cargados</div>';
        return;
    }
    container.innerHTML = pax.map((p, idx) => {
        const estado = p.estado || 'ILESO';
        const paxId  = modoActivo ? p.id : idx;
        const sangre = p.grupo_sanguineo ? `<span class="blood">${p.grupo_sanguineo}</span>` : '—';

        const medicalHtml = (p.grupo_sanguineo || p.alergias || p.enfermedades || p.contacto_emergencia) ? `
        <div class="pax-medical">
            <div class="med-item"><div class="med-label">Sangre</div><div class="med-val">${sangre}</div></div>
            <div class="med-item"><div class="med-label">Contacto</div><div class="med-val">${p.contacto_emergencia || '—'}</div></div>
            ${p.alergias   ? `<div class="med-item" style="grid-column:1/-1"><div class="med-label">Alergias</div><div class="med-val" style="color:#ff8c00">${p.alergias}</div></div>` : ''}
            ${p.enfermedades ? `<div class="med-item" style="grid-column:1/-1"><div class="med-label">Condiciones</div><div class="med-val">${p.enfermedades}</div></div>` : ''}
        </div>` : '';

        const controls = modoActivo ? `
        <div class="pax-controls">
            <select class="estado-select" onchange="this.closest('.pax-card').setAttribute('data-estado', this.value)">
                ${['ILESO','HERIDO_LEVE','HERIDO_GRAVE','FALLECIDO','NO_LOCALIZADO','TRASLADADO'].map(s =>
                    `<option value="${s}" ${s===estado?'selected':''}>${estadoLabel(s)}</option>`
                ).join('')}
            </select>
            <input class="notas-input" type="text" placeholder="Notas..." value="${p.notas||''}">
            <button class="save-pax-btn" onclick="savePax(this, ${paxId})">Guardar</button>
        </div>` : `
        <div class="pax-controls">
            <select class="estado-select" onchange="
                paxData[${idx}].estado = this.value;
                this.closest('.pax-card').setAttribute('data-estado', this.value);
                updateKPIs(paxData, false);">
                ${['ILESO','HERIDO_LEVE','HERIDO_GRAVE','FALLECIDO','NO_LOCALIZADO','TRASLADADO'].map(s =>
                    `<option value="${s}" ${s===estado?'selected':''}>${estadoLabel(s)}</option>`
                ).join('')}
            </select>
            <input class="notas-input" type="text" placeholder="Notas..." value="${p.notas||''}"
                   oninput="paxData[${idx}].notas = this.value">
        </div>`;

        return `
        <div class="pax-card" data-estado="${estado}" data-paxid="${paxId}">
            <div class="pax-top">
                <div class="pax-info">
                    <div class="pax-name">${p.apellidos ? p.apellidos+', '+p.nombres : (p.nombres||'DNI: '+p.dni)}</div>
                    <div class="pax-meta">${p.empresa||''} ${p.cargo ? '· '+p.cargo : ''}</div>
                    <div class="pax-dni">DNI ${p.dni || '—'}</div>
                </div>
                <div class="asiento-badge">A${p.asiento||'?'}</div>
            </div>
            ${medicalHtml}
            ${controls}
        </div>`;
    }).join('');
}

// ── KPIs ──────────────────────────────────────────────────────────
function updateKPIs(pax, activo) {
    const pfx = activo ? 'ka' : 'k';
    const count = s => pax.filter(p => (p.estado||'ILESO') === s).length;
    document.getElementById(pfx+'Total').textContent = pax.length;
    document.getElementById(pfx+'Ileso').textContent = count('ILESO') + pax.filter(p=>(p.estado||'ILESO')==='TRASLADADO').length;
    document.getElementById(pfx+'Leve').textContent  = count('HERIDO_LEVE');
    document.getElementById(pfx+'Grave').textContent = count('HERIDO_GRAVE');
    document.getElementById(pfx+'Fall').textContent  = count('FALLECIDO');
    document.getElementById(pfx+'Noloc').textContent = count('NO_LOCALIZADO');
}

// ── CONFIRMAR Y ACTIVAR ───────────────────────────────────────────
function confirmarActivar() {
    const tipo = document.getElementById('tipoIncidente').value;
    if (!tipo) { showToast('Selecciona el tipo de incidente', 'err'); return; }
    document.getElementById('confirmOverlay').classList.add('open');
}
function cancelarActivar() { document.getElementById('confirmOverlay').classList.remove('open'); }

function activarEmergencia() {
    document.getElementById('confirmOverlay').classList.remove('open');

    const payload = {
        tipo_incidente: document.getElementById('tipoIncidente').value,
        bus_nombre:     document.getElementById('busNombre').value,
        bus_placa:      document.getElementById('busPlaca').value,
        conductor:      document.getElementById('busConductor').value,
        ubicacion:      document.getElementById('ubicacion').value,
        observaciones:  document.getElementById('observaciones').value,
        pasajeros:      paxData
    };

    fetch('?ajax=crear_emergencia', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(res => {
        if (res.ok) {
            emergenciaId = res.id;
            showToast('Emergencia registrada #'+res.id, 'ok');
            document.getElementById('formEmergencia').style.display = 'none';
            cargarEmergenciaActiva(emergenciaId);
        } else {
            showToast('Error al registrar emergencia', 'err');
        }
    });
}

// ── CARGAR EMERGENCIA ACTIVA ──────────────────────────────────────
function cargarEmergenciaActiva(id) {
    fetch(`?ajax=cargar_emergencia&id=${id}`)
        .then(r => r.json())
        .then(res => {
            paxActiva = res.pasajeros;
            updateKPIs(paxActiva, true);
            renderPaxList(paxActiva, 'paxListActiva', true);
            document.getElementById('emergenciaActiva').style.display = 'block';
            document.getElementById('printMeta').textContent =
                `Bus: ${res.emergencia.bus_nombre||''} | Placa: ${res.emergencia.bus_placa||''} | ` +
                `Tipo: ${res.emergencia.tipo_incidente} | Fecha: ${formatDateTime(res.emergencia.fecha_hora)}`;
        });
}

// ── GUARDAR ESTADO PASAJERO (modo activo) ─────────────────────────
function savePax(btn, paxId) {
    const card   = btn.closest('.pax-card');
    const estado = card.querySelector('.estado-select').value;
    const notas  = card.querySelector('.notas-input').value;

    fetch('?ajax=update_pasajero', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ id: paxId, emergencia_id: emergenciaId, estado, notas })
    })
    .then(r => r.json())
    .then(res => {
        if (res.ok) {
            card.setAttribute('data-estado', estado);
            // Actualizar array local
            const p = paxActiva.find(x => x.id == paxId);
            if (p) { p.estado = estado; p.notas = notas; }
            updateKPIs(paxActiva, true);
            showToast('Estado actualizado', 'ok');
        }
    });
}

// ── CERRAR EMERGENCIA ─────────────────────────────────────────────
function showCerrarModal() { document.getElementById('cerrarModal').classList.add('open'); }
function closeCerrarModal(){ document.getElementById('cerrarModal').classList.remove('open'); }

function cerrarEmergencia() {
    const estado = document.getElementById('estadoCierre').value;
    fetch('?ajax=cerrar_emergencia', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ id: emergenciaId, estado })
    })
    .then(r => r.json())
    .then(res => {
        if (res.ok) {
            closeCerrarModal();
            updateStatusPill('standby');
            showToast('Emergencia cerrada correctamente', 'ok');
            emergenciaId = null;
            paxActiva = [];
            document.getElementById('emergenciaActiva').style.display = 'none';
            document.getElementById('triggerZone').style.display = 'flex';
            document.getElementById('sosBtn').classList.remove('disabled');
        }
    });
}

// ── HISTORIAL ─────────────────────────────────────────────────────
function loadHistorial() {
    document.getElementById('loaderHist').classList.add('show');
    document.getElementById('histList').innerHTML = '';
    fetch('?ajax=historial')
        .then(r => r.json())
        .then(rows => {
            document.getElementById('loaderHist').classList.remove('show');
            if (!rows.length) {
                document.getElementById('histList').innerHTML =
                    '<div class="empty-state"><i class="fas fa-folder-open"></i>Sin emergencias registradas</div>';
                return;
            }
            document.getElementById('histList').innerHTML = rows.map(e => `
                <div class="hist-card" onclick="openHistModal(${e.id})">
                    <div class="hist-top">
                        <div>
                            <div class="hist-tipo">${e.tipo_incidente}</div>
                            <div class="hist-bus">${e.bus_nombre||'—'} &nbsp;|&nbsp; ${e.bus_placa||'—'}</div>
                        </div>
                        <div class="hist-fecha">${formatDateTime(e.fecha_hora)}</div>
                    </div>
                    <div class="hist-stats">
                        <span class="hist-badge ${e.estado_evento.toLowerCase()}">${e.estado_evento}</span>
                        <span class="hist-badge cerrado">${e.total_pax} pasajeros</span>
                        ${e.heridos > 0 ? `<span class="hist-badge controlado">${e.heridos} heridos</span>` : ''}
                        ${e.fallecidos > 0 ? `<span class="hist-badge activo">${e.fallecidos} fallecidos</span>` : ''}
                    </div>
                </div>`).join('');
        });
}

function openHistModal(id) {
    document.getElementById('histModal').classList.add('open');
    fetch(`?ajax=cargar_emergencia&id=${id}`)
        .then(r => r.json())
        .then(res => {
            const e = res.emergencia;
            document.getElementById('histModalTitle').textContent = e.tipo_incidente;
            document.getElementById('histModalSub').textContent =
                `${e.bus_nombre||''} · ${e.bus_placa||''} · ${formatDateTime(e.fecha_hora)} · ${e.usuario_activa||''}`;

            // mini KPIs
            const pax = res.pasajeros;
            const count = s => pax.filter(p => p.estado===s).length;
            document.getElementById('histModalKpi').innerHTML = `
                <div class="kpi-box k-total"><div class="kpi-n">${pax.length}</div><div class="kpi-l">Total</div></div>
                <div class="kpi-box k-ileso"><div class="kpi-n">${count('ILESO')}</div><div class="kpi-l">Ilesos</div></div>
                <div class="kpi-box k-leve"><div class="kpi-n">${count('HERIDO_LEVE')}</div><div class="kpi-l">H. Leve</div></div>
                <div class="kpi-box k-grave"><div class="kpi-n">${count('HERIDO_GRAVE')}</div><div class="kpi-l">H. Grave</div></div>
                <div class="kpi-box k-fall"><div class="kpi-n">${count('FALLECIDO')}</div><div class="kpi-l">Fallecidos</div></div>
                <div class="kpi-box k-noloc"><div class="kpi-n">${count('NO_LOCALIZADO')}</div><div class="kpi-l">No loc.</div></div>
            `;
            // Pasajeros solo lectura
            const cont = document.getElementById('histModalPax');
            cont.innerHTML = pax.map(p => `
                <div class="pax-card" data-estado="${p.estado||'ILESO'}">
                    <div class="pax-top">
                        <div class="pax-info">
                            <div class="pax-name">${p.apellidos ? p.apellidos+', '+p.nombres : p.dni}</div>
                            <div class="pax-meta">${estadoLabel(p.estado||'ILESO')}</div>
                            ${p.notas ? `<div class="pax-meta" style="color:var(--gold)">${p.notas}</div>` : ''}
                        </div>
                        <div class="asiento-badge">A${p.asiento||'?'}</div>
                    </div>
                </div>`).join('');
        });
}

function closeHistModal() { document.getElementById('histModal').classList.remove('open'); }

// ── PRINT ─────────────────────────────────────────────────────────
function printReport() { window.print(); }

// Close modals on overlay click
document.getElementById('histModal').addEventListener('click', e => { if(e.target===e.currentTarget) closeHistModal(); });
document.getElementById('cerrarModal').addEventListener('click', e => { if(e.target===e.currentTarget) closeCerrarModal(); });
</script>
</body>
</html>
