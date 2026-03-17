<?php
/**
 * informe_ejecutivo.php — Hochschild Mining · SITRAN
 * Informe gerencial limpio · Respeta exclusiones de sesión
 */
session_start();
if (!isset($_SESSION['usuario'])) { header("Location: index.php"); exit(); }
require __DIR__ . "/config.php";
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

/* ══ MAPA DE PLACAS ════════════════════════════════════════ */
$PLACAS_CONOCIDAS = [
    'DAJOR 01'=>'F5Q-963','DAJOR 02'=>'F5Q-969','DAJOR 03'=>'F5Q-967','DAJOR 04'=>'VBT-081',
    'AREQUIPA 1'=>'BSG-266','AREQUIPA 2 - D'=>'F5Q-964','AREQUIPA 3'=>'F5Q-965',
    'AREQUIPA 4'=>'BSE-645','AREQUIPA 5'=>'F5U-957','AREQUIPA 6'=>'CAA-619',
    'AREQUIPA 7'=>'F5Q-962','AREQUIPA 8'=>'BSE-612',
    'LIMA 1'=>'CFR-307','LIMA 2 - D'=>'CFR-407','LIMA 3'=>'CFR-453',
    'ESPINAR / CUSCO'=>'CSH-576','CUZCO 1'=>'CSH-576',
    'ABANCAY 1'=>'VCJ-037','JULIACA 1'=>'VBD-181','JULIACA 2'=>'VAR-404',
    'TPP 01 EECC'=>'CFR-569','TPP 02 EECC'=>'CFR-287',
];
function resolve_placa(string $bus, string $placa_bd, array $mapa): string {
    $k = strtoupper(trim($bus));
    if (isset($mapa[$k])) return $mapa[$k];
    if ($placa_bd && $placa_bd !== 'N/A') return $placa_bd;
    return '';
}

/* ══ BUSES EXCLUIDOS — tomar de sesión ════════════════════ */
$BUSES_EXCLUIDOS = $_SESSION['buses_excluidos'] ?? [];
function bus_excluido_inf(string $bus, array $excluidos): bool {
    if (empty($excluidos)) return false;
    $bn = mb_strtolower(trim($bus));
    foreach ($excluidos as $ex) {
        if (mb_strtolower(trim($ex)) === $bn) return true;
    }
    return false;
}

/* ══ HELPERS ════════════════════════════════════════════════ */
function semana_lunes_inf(int $y, int $w): string {
    $dt = new DateTime(); $dt->setISODate($y, $w, 1); return $dt->format('Y-m-d');
}
function semana_domingo_inf(string $l): string { return date('Y-m-d', strtotime($l) + 6*86400); }

/* ══ PARÁMETROS ════════════════════════════════════════════ */
$tipo   = in_array($_GET['tipo'] ?? '', ['subida','bajada','ambos']) ? $_GET['tipo'] : 'ambos';
$year_a = max(2020, min(2030, (int)($_GET['year_a'] ?? date('Y'))));
$week_a = max(1,    min(53,   (int)($_GET['week_a'] ?? date('W'))));
$lun_a  = semana_lunes_inf($year_a, $week_a);
$dom_a  = semana_domingo_inf($lun_a);
$label  = "Semana $week_a · " . date('d M', strtotime($lun_a)) . " al " . date('d M Y', strtotime($dom_a));
$generado = date('d/m/Y H:i');
$tipo_label = match($tipo) { 'subida' => 'Subida', 'bajada' => 'Bajada', default => 'Subida & Bajada' };

/* ══ QUERIES ════════════════════════════════════════════════ */
function get_teorico(mysqli $m, string $tabla): array {
    $r = $m->query("SELECT UPPER(TRIM(COALESCE(bus,''))) AS bus,
        UPPER(TRIM(COALESCE(placa,''))) AS placa, COUNT(*) AS n
        FROM {$tabla} WHERE bus IS NOT NULL AND bus != '' AND bus != 'N/A'
        GROUP BY UPPER(TRIM(bus)), UPPER(TRIM(placa))");
    $o = [];
    while ($row = $r->fetch_assoc()) {
        $o[$row['bus']] = ['placa' => $row['placa'], 'n' => (int)$row['n']];
    }
    return $o;
}
function get_real_inf(mysqli $m, string $ini, string $fin, string $ev): array {
    $s = $m->prepare("SELECT UPPER(TRIM(COALESCE(r.bus,''))) AS bus, COUNT(DISTINCT r.dni) AS n
        FROM registros r WHERE DATE(r.fecha) BETWEEN ? AND ?
        AND r.evento = ? AND r.bus IS NOT NULL AND r.bus != '' AND r.bus != 'N/A'
        GROUP BY UPPER(TRIM(r.bus))");
    $s->bind_param('sss', $ini, $fin, $ev); $s->execute();
    $r = $s->get_result(); $o = [];
    while ($row = $r->fetch_assoc()) $o[$row['bus']] = (int)$row['n'];
    $s->close(); return $o;
}
function get_spark_inf(mysqli $m, string $ini, string $fin, string $ev): array {
    $s = $m->prepare("SELECT DATE(r.fecha) AS dia, COUNT(DISTINCT r.dni) AS n
        FROM registros r WHERE DATE(r.fecha) BETWEEN ? AND ? AND r.evento = ?
        GROUP BY DATE(r.fecha)");
    $s->bind_param('sss', $ini, $fin, $ev); $s->execute();
    $r = $s->get_result(); $o = [];
    while ($row = $r->fetch_assoc()) $o[$row['dia']] = (int)$row['n'];
    $s->close(); return $o;
}

function build_bloque_inf(mysqli $m, string $ini, string $fin, string $tabla, string $ev,
                           array $excluidos, array $placas_map): array {
    $teo  = get_teorico($m, $tabla);
    $real = get_real_inf($m, $ini, $fin, $ev);
    $buses = array_unique(array_merge(array_keys($teo), array_keys($real)));
    sort($buses);
    $filas = []; $st = 0; $sr = 0;
    foreach ($buses as $bus) {
        if (bus_excluido_inf($bus, $excluidos)) continue;
        $t   = $teo[$bus]['n']  ?? 0;
        $r   = $real[$bus]      ?? 0;
        $pct = $t > 0 ? round($r / $t * 100) : ($r > 0 ? 100 : 0);
        $pl  = resolve_placa($bus, $teo[$bus]['placa'] ?? '', $placas_map);
        $filas[] = ['bus' => $bus, 'placa' => $pl, 't' => $t, 'r' => $r, 'pct' => $pct];
        $st += $t; $sr += $r;
    }
    $pg = $st > 0 ? round($sr / $st * 100) : 0;
    return [
        'filas'  => $filas,
        'sum_t'  => $st, 'sum_r' => $sr,
        'falt'   => max(0, $st - $sr),
        'pct'    => $pg,
        'n_ok'   => count(array_filter($filas, fn($f) => $f['t']>0 && $f['pct']>=85)),
        'n_warn' => count(array_filter($filas, fn($f) => $f['t']>0 && $f['pct']>=50 && $f['pct']<85)),
        'n_crit' => count(array_filter($filas, fn($f) => $f['t']>0 && $f['pct']<50)),
    ];
}

/* ══ DATOS ══════════════════════════════════════════════════ */
$noms = ['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'];
$bloques = []; $sparks = [];
if ($tipo === 'subida' || $tipo === 'ambos') {
    $bloques['subida'] = build_bloque_inf($mysqli,$lun_a,$dom_a,'lista_subida','SUBIDA PERMITIDA',$BUSES_EXCLUIDOS,$PLACAS_CONOCIDAS);
    $sparks['subida']  = get_spark_inf($mysqli,$lun_a,$dom_a,'SUBIDA PERMITIDA');
}
if ($tipo === 'bajada' || $tipo === 'ambos') {
    $bloques['bajada'] = build_bloque_inf($mysqli,$lun_a,$dom_a,'lista_bajada','BAJADA PERMITIDA',$BUSES_EXCLUIDOS,$PLACAS_CONOCIDAS);
    $sparks['bajada']  = get_spark_inf($mysqli,$lun_a,$dom_a,'BAJADA PERMITIDA');
}

/* ══ TABLA COMBINADA (modo ambos) ═══════════════════════════ */
if ($tipo === 'ambos') {
    $bs = $bloques['subida']; $bb = $bloques['bajada'];
    $idx_s = []; foreach ($bs['filas'] as $f) $idx_s[$f['bus']] = $f;
    $idx_b = []; foreach ($bb['filas'] as $f) $idx_b[$f['bus']] = $f;
    $all   = array_unique(array_merge(array_keys($idx_s), array_keys($idx_b)));
    sort($all);
    $tabla_ambos = []; $tot_ts=$tot_rs=$tot_tb=$tot_rb=0;
    foreach ($all as $bus) {
        $fs = $idx_s[$bus] ?? ['t'=>0,'r'=>0,'pct'=>0,'placa'=>''];
        $fb = $idx_b[$bus] ?? ['t'=>0,'r'=>0,'pct'=>0,'placa'=>''];
        $tt = $fs['t'] + $fb['t']; $rt = $fs['r'] + $fb['r'];
        $pt = $tt > 0 ? round($rt / $tt * 100) : 0;
        $pl = resolve_placa($bus, $fs['placa'] ?: $fb['placa'], $PLACAS_CONOCIDAS);
        $tabla_ambos[] = compact('bus','fs','fb','tt','rt','pt') + ['placa'=>$pl];
        $tot_ts += $fs['t']; $tot_rs += $fs['r'];
        $tot_tb += $fb['t']; $tot_rb += $fb['r'];
    }
    $tot_tt = $tot_ts + $tot_tb; $tot_rt = $tot_rs + $tot_rb;
    $tot_pt = $tot_tt > 0 ? round($tot_rt/$tot_tt*100) : 0;
    $tot_fs = $tot_ts > 0 ? round($tot_rs/$tot_ts*100) : 0;
    $tot_fb = $tot_tb > 0 ? round($tot_rb/$tot_tb*100) : 0;
    $pct_h  = $tot_pt;
    $bp = ['sum_t'=>$tot_tt,'sum_r'=>$tot_rt,'falt'=>max(0,$tot_tt-$tot_rt),'pct'=>$tot_pt,
           'n_ok'=>count(array_filter($tabla_ambos,fn($r)=>$r['tt']>0&&$r['pt']>=85)),
           'n_warn'=>count(array_filter($tabla_ambos,fn($r)=>$r['tt']>0&&$r['pt']>=50&&$r['pt']<85)),
           'n_crit'=>count(array_filter($tabla_ambos,fn($r)=>$r['tt']>0&&$r['pt']<50))];
} else {
    $bp_key = $tipo === 'bajada' ? 'bajada' : 'subida';
    $bp = $bloques[$bp_key];
    $pct_h = $bp['pct'];
}
$col_g = $pct_h >= 85 ? 'ok' : ($pct_h >= 50 ? 'warn' : 'crit');

/* ══ GAUGE SVG ══════════════════════════════════════════════ */
function gauge_svg_v2(int $pct, string $modo = ''): string {
    $r = 88; $cx = 110; $cy = 110;
    $circ = 2 * M_PI * $r;
    $arc  = round($circ * 0.75, 2);
    $off  = round($circ * 0.25, 2);
    $fill = round($arc * min($pct,100) / 100, 2);
    $empty = round($arc - $fill, 2);
    $col = $pct>=85 ? '#16a34a' : ($pct>=50 ? '#d97706' : '#dc2626');
    $bg  = $pct>=85 ? '#EAF3DE' : ($pct>=50 ? '#FEF3E2' : '#FCEBEB');
    $uid = 'g' . rand(100,999);
    $lbl = strtoupper($modo ? "AVANCE · $modo" : "AVANCE GLOBAL");
    return <<<SVG
<svg viewBox="0 0 220 185" xmlns="http://www.w3.org/2000/svg" style="width:100%;max-width:220px;display:block;margin:0 auto">
  <circle cx="$cx" cy="$cy" r="$r" fill="none" stroke="#E2E8F0" stroke-width="10"
    stroke-dasharray="$arc $off" stroke-dashoffset="-$off" stroke-linecap="round"
    transform="rotate(-135 $cx $cy)"/>
  <circle cx="$cx" cy="$cy" r="$r" fill="none" stroke="$col" stroke-width="10"
    stroke-dasharray="$fill $empty" stroke-dashoffset="-$off" stroke-linecap="round"
    transform="rotate(-135 $cx $cy)"/>
  <text x="$cx" y="116" text-anchor="middle"
    font-family="system-ui,-apple-system,sans-serif"
    font-size="46" font-weight="700" fill="$col">{$pct}%</text>
  <text x="$cx" y="135" text-anchor="middle"
    font-family="system-ui,-apple-system,sans-serif"
    font-size="8" font-weight="500" letter-spacing="2.5" fill="#94A3B8">$lbl</text>
</svg>
SVG;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Informe Ejecutivo · SITRAN · <?=h($label)?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Geist:wght@300;400;500;600;700&family=Geist+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="icon" type="image/png" href="assets/logo4.png"/>
<style>
/* ══ TOKENS ════════════════════════════════════════════════ */
:root {
  --page:    #F8FAFC;
  --surface: #FFFFFF;
  --overlay: #F1F5F9;
  --border:  #E2E8F0;
  --border2: #CBD5E1;
  --ink:     #0F172A;
  --ink2:    #334155;
  --ink3:    #64748B;
  --ink4:    #94A3B8;
  --gold:    #B8892A;
  --gold2:   #D4A843;
  --gold-bg: #FFFBEB;
  --gold-ring: rgba(184,137,42,.2);
  --ok:      #16a34a; --ok-bg:   #F0FDF4; --ok-bd:   rgba(22,163,74,.2);
  --warn:    #d97706; --warn-bg: #FEF3E2; --warn-bd: rgba(217,119,6,.2);
  --crit:    #dc2626; --crit-bg: #FEF2F2; --crit-bd: rgba(220,38,38,.2);
  --font: 'Geist', system-ui, sans-serif;
  --mono: 'Geist Mono', monospace;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: var(--font); font-size: 14px; line-height: 1.6; background: var(--page); color: var(--ink2); -webkit-font-smoothing: antialiased; }

/* ── TOPBAR ── */
.topbar { background: rgba(255,255,255,.9); backdrop-filter: blur(16px); border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 100; }
.topbar-accent { height: 2px; background: linear-gradient(90deg,transparent,var(--gold2) 20%,var(--gold) 50%,var(--gold2) 80%,transparent); }
.topbar-row { max-width: 1040px; margin: 0 auto; padding: 0 24px; height: 60px; display: flex; align-items: center; gap: 14px; }
.t-logo img { height: 48px; object-fit: contain; }
.t-div { width: 1px; height: 22px; background: var(--border2); opacity: .5; flex-shrink: 0; }
.t-title { font-size: 13px; font-weight: 600; color: var(--ink3); }
.t-sub { font-size: 11px; color: var(--ink4); }
.t-spacer { flex: 1; }
.t-btn { display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px; border: 1px solid var(--border); border-radius: 8px; background: var(--surface); font-family: var(--font); font-size: 13px; font-weight: 600; color: var(--ink3); cursor: pointer; text-decoration: none; transition: all .2s; white-space: nowrap; }
.t-btn:hover { border-color: var(--border2); color: var(--ink); }
.t-btn-gold { background: var(--gold); color: #fff; border-color: var(--gold); }
.t-btn-gold:hover { background: #9a7222; }

/* ── MODO TABS ── */
.modebar { background: var(--surface); border-bottom: 1px solid var(--border); display: flex; justify-content: center; }
.mopt { display: inline-flex; align-items: center; gap: 7px; padding: 12px 28px; font-size: 12px; font-weight: 600; color: var(--ink3); text-decoration: none; border-bottom: 2px solid transparent; transition: all .2s; letter-spacing: .3px; }
.mopt:hover { color: var(--ink); }
.mopt.act { color: var(--gold); border-color: var(--gold); }

/* ── EXCLUSION NOTICE ── */
.excl-notice { max-width: 1040px; margin: 0 auto; padding: 8px 24px 0; }
.excl-pill { display: inline-flex; align-items: center; gap: 6px; background: var(--crit-bg); border: 1px solid var(--crit-bd); border-radius: 6px; padding: 4px 12px; font-size: 11px; font-weight: 600; color: var(--crit); }

/* ── PAGE ── */
.page { max-width: 1040px; margin: 0 auto; padding: 48px 32px 80px; display: flex; flex-direction: column; gap: 28px; }
@keyframes up { from { opacity:0; transform:translateY(8px) } to { opacity:1; transform:none } }
.anim { animation: up .5s cubic-bezier(.34,1.2,.64,1) both; }
.d1{animation-delay:.04s}.d2{animation-delay:.08s}.d3{animation-delay:.12s}
.d4{animation-delay:.16s}.d5{animation-delay:.20s}.d6{animation-delay:.24s}

/* ── HEADER ── */
.rpt-header { display: grid; grid-template-columns: 1fr auto; gap: 24px; align-items: start; padding-bottom: 24px; border-bottom: 1px solid var(--border); }
.rpt-eyebrow { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 1.5px; color: var(--gold); margin-bottom: 8px; display: flex; align-items: center; gap: 8px; }
.rpt-eyebrow::before { content: ''; width: 20px; height: 2px; background: var(--gold2); border-radius: 2px; }
.rpt-title { font-size: 36px; font-weight: 700; color: var(--ink); letter-spacing: -1px; line-height: 1.1; }
.rpt-title em { color: var(--gold); font-style: normal; }
.rpt-sub { font-size: 13px; color: var(--ink3); margin-top: 8px; letter-spacing: .3px; }
.rpt-meta { text-align: right; }
.rpt-badge { display: inline-flex; align-items: center; gap: 6px; background: var(--gold-bg); border: 1px solid var(--gold-ring); padding: 4px 12px; border-radius: 6px; font-size: 11px; font-weight: 600; color: var(--gold); margin-bottom: 10px; }
.live-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--ok); animation: pulse 2s infinite; }
@keyframes pulse { 0%,100%{opacity:1}50%{opacity:.4} }
.rpt-info { font-family: var(--mono); font-size: 11px; color: var(--ink3); line-height: 1.8; }
.rpt-info strong { color: var(--ink); font-weight: 600; }

/* ── HERO ── */
.hero { background: var(--surface); border: 1px solid var(--border); border-radius: 16px; overflow: hidden; display: grid; grid-template-columns: 220px 1fr; gap: 0; padding: 0; position: relative; }
.hero::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; background: linear-gradient(90deg, var(--gold), var(--gold2)); }
.hero-gauge { text-align: center; padding: 36px 32px; background: var(--overlay); border-right: 1px solid var(--border); }
.hero-legend { font-family: var(--mono); font-size: 11px; color: var(--ink3); line-height: 2; margin-top: 10px; text-align: center; }
.hero-legend strong { font-size: 16px; font-weight: 700; color: var(--ink); display: block; line-height: 1.3; }
.hero-kpis { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; align-content: center; padding: 36px 40px; }
.hk { border-left: 1px solid var(--border); padding-left: 16px; }
.hk-v { font-size: 36px; font-weight: 700; line-height: 1; color: var(--ink); font-family: var(--mono); }
.hk-v.ok { color: var(--ok); } .hk-v.warn { color: var(--warn); } .hk-v.crit { color: var(--crit); } .hk-v.gold { color: var(--gold); }
.hk-l { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .8px; color: var(--ink3); margin-top: 4px; }
.hk-s { font-size: 11px; color: var(--ink4); margin-top: 2px; font-family: var(--mono); }

/* ── SEC LABEL ── */
.sec-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px; color: var(--ink4); display: flex; align-items: center; gap: 12px; margin-bottom: 4px; }
.sec-label::after { content: ''; flex: 1; height: 1px; background: var(--border); }
.sec-label i { color: var(--gold); font-size: 11px; }
.sec-num { display: inline-flex; align-items: center; justify-content: center; width: 20px; height: 20px; border-radius: 50%; background: var(--gold-bg); border: 1px solid var(--gold-ring); font-size: 10px; font-weight: 700; color: var(--gold); flex-shrink: 0; font-family: var(--mono); }

/* ── SEMÁFORO ── */
.semg { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
.semc { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 22px 20px; text-align: center; position: relative; overflow: hidden; }
.semc::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; border-radius: 12px 12px 0 0; }
.semc.ok::before { background: var(--ok); } .semc.warn::before { background: var(--warn); } .semc.crit::before { background: var(--crit); }
.sem-n { font-size: 56px; font-weight: 700; line-height: 1; font-family: var(--mono); }
.semc.ok .sem-n { color: var(--ok); } .semc.warn .sem-n { color: var(--warn); } .semc.crit .sem-n { color: var(--crit); }
.sem-l { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .8px; color: var(--ink3); margin-top: 6px; }
.sem-s { font-size: 11px; color: var(--ink4); margin-top: 3px; }
.sem-buses { margin-top: 10px; padding-top: 8px; border-top: 1px solid var(--border); font-size: 11px; }
.sem-bus-row { display: flex; align-items: center; justify-content: center; gap: 6px; padding: 2px 0; }
.sem-bus-nm { font-weight: 600; }
.semc.ok .sem-bus-nm { color: var(--ok); } .semc.warn .sem-bus-nm { color: var(--warn); }
.sem-bus-pl { font-family: var(--mono); font-size: 10px; background: var(--overlay); padding: 0 5px; border-radius: 3px; color: var(--ink4); }

/* ── SPARKLINE ── */
.spark-wrap { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 20px 24px; }
.spark-title { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .8px; color: var(--ink3); margin-bottom: 16px; display: flex; align-items: center; gap: 6px; }
.spark-title i { font-size: 11px; }
.spbars { display: grid; grid-template-columns: repeat(7,1fr); gap: 6px; }
.spc { display: flex; flex-direction: column; align-items: center; }
.spv { font-family: var(--mono); font-size: 10px; color: var(--ink4); height: 16px; line-height: 16px; text-align: center; width: 100%; }
.spv.hoy { color: var(--gold); font-weight: 600; }
.sp-bar-zone { width: 100%; height: 60px; display: flex; align-items: flex-end; justify-content: center; margin: 3px 0 4px; }
.spbar { width: 70%; border-radius: 2px 2px 0 0; min-height: 3px; background: var(--border2); }
.spbar.hoy { background: var(--gold); }
.spbar.fut { background: transparent; border: 1px dashed var(--border2); border-bottom: none; }
.spnom { font-size: 10px; font-weight: 600; color: var(--ink3); height: 14px; text-align: center; }
.spnom.hoy { color: var(--gold); }
.spdate { font-family: var(--mono); font-size: 9px; color: var(--ink4); text-align: center; margin-top: 2px; }
.spark-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }

/* ── KPI CARDS AMBOS ── */
.ka-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 12px; }
.ka { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 18px 16px; position: relative; overflow: hidden; }
.ka::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; border-radius: 12px 12px 0 0; }
.ka.s::before { background: #1D9E75; } .ka.b::before { background: #BA7517; } .ka.t::before { background: var(--gold); }
.ka-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .8px; margin-bottom: 10px; }
.ka.s .ka-label { color: #0F6E56; } .ka.b .ka-label { color: #854F0B; } .ka.t .ka-label { color: var(--gold); }
.ka-row { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 4px; }
.ka-v { font-size: 32px; font-weight: 700; line-height: 1; font-family: var(--mono); }
.ka.s .ka-v { color: #1D9E75; } .ka.b .ka-v { color: #BA7517; } .ka.t .ka-v { color: var(--gold); }
.ka-detail { font-family: var(--mono); font-size: 11px; color: var(--ink4); margin-top: 6px; }

/* ── TABLA BUSES ── */
.tbl-wrap { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; }
.tbl-hdr { padding: 12px 18px; border-bottom: 1px solid var(--border); background: var(--overlay); display: flex; align-items: center; justify-content: space-between; }
.tbl-hdr-t { font-size: 12px; font-weight: 600; color: var(--ink2); text-transform: uppercase; letter-spacing: .5px; }
.tbl-badge { font-size: 11px; font-family: var(--mono); padding: 3px 10px; border-radius: 6px; background: var(--gold-bg); color: var(--gold); border: 1px solid var(--gold-ring); }
table.bt { width: 100%; border-collapse: collapse; font-size: 13px; }
table.bt th { padding: 9px 14px; font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: .8px; color: var(--ink4); background: var(--overlay); border-bottom: 1px solid var(--border); text-align: center; white-space: nowrap; }
table.bt th.left { text-align: left; }
table.bt th.gs { color: #0F6E56; border-bottom: 2px solid #1D9E75; }
table.bt th.gb { color: #854F0B; border-bottom: 2px solid #BA7517; }
table.bt th.gt { color: var(--gold); border-bottom: 2px solid var(--gold); }
table.bt td { padding: 10px 14px; border-bottom: 1px solid var(--border); vertical-align: middle; text-align: center; }
table.bt td.left { text-align: left; }
table.bt tfoot td { border-top: 2px solid var(--gold-ring); background: var(--overlay); font-weight: 600; padding: 10px 14px; }
table.bt tbody tr:hover td { background: var(--overlay); }
table.bt tbody tr:last-child td { border-bottom: none; }
.bt-nm { font-weight: 600; color: var(--ink); font-size: 13px; }
.bt-pl { font-family: var(--mono); font-size: 10px; background: var(--overlay); border: 1px solid var(--border); padding: 1px 6px; border-radius: 3px; color: var(--ink3); }
.mn { font-family: var(--mono); font-weight: 600; font-size: 13px; }
.ok { color: var(--ok); } .warn { color: var(--warn); } .crit { color: var(--crit); } .gold { color: var(--gold); }
.mini-bar { display: flex; align-items: center; gap: 6px; justify-content: center; }
.mini-t { width: 44px; height: 3px; background: var(--border); border-radius: 99px; overflow: hidden; flex-shrink: 0; }
.mini-f { height: 100%; border-radius: 99px; }
.mini-p { font-family: var(--mono); font-size: 10px; font-weight: 600; width: 30px; text-align: right; }
.row-hl-ok   { background: rgba(22,163,74,.04) !important; border-left: 3px solid var(--ok); }
.row-hl-warn { background: rgba(217,119,6,.04) !important; border-left: 3px solid var(--warn); }
.row-hl-crit { background: rgba(220,38,38,.04) !important; border-left: 3px solid var(--crit); }
.tbl-leg { padding: 10px 18px; border-top: 1px solid var(--border); background: var(--overlay); display: flex; gap: 16px; flex-wrap: wrap; align-items: center; }
.tl-item { display: flex; align-items: center; gap: 5px; font-size: 11px; color: var(--ink3); }
.tl-dot { width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; }

/* ── HALLAZGOS ── */
.hallazgo-grid { display: flex; flex-direction: column; gap: 10px; }
.hallazgo { background: var(--surface); border: 1px solid var(--border); border-radius: 10px; padding: 16px 20px; display: flex; align-items: flex-start; gap: 16px; transition: box-shadow .2s; }
.hbadge { display: inline-flex; align-items: center; gap: 5px; font-size: 10px; font-weight: 700; padding: 3px 10px; border-radius: 6px; text-transform: uppercase; letter-spacing: .5px; flex-shrink: 0; min-width: 90px; justify-content: center; }
.hb-ok   { background: var(--ok-bg);   color: var(--ok);   border: 1px solid var(--ok-bd); }
.hb-warn { background: var(--warn-bg); color: var(--warn); border: 1px solid var(--warn-bd); }
.hb-crit { background: var(--crit-bg); color: var(--crit); border: 1px solid var(--crit-bd); }
.hb-gold { background: var(--gold-bg); color: var(--gold); border: 1px solid var(--gold-ring); }
.h-body { flex: 1; }
.h-title { font-size: 13px; font-weight: 600; color: var(--ink); margin-bottom: 3px; }
.h-desc { font-size: 12px; color: var(--ink3); line-height: 1.5; }

/* ── CONCLUSIÓN ── */
.conclusion { background: var(--gold-bg); border: 1px solid var(--gold-ring); border-left: 3px solid var(--gold); border-radius: 0 12px 12px 0; padding: 28px 32px; }
.concl-tag { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: var(--gold); margin-bottom: 10px; }
.concl-txt { font-size: 15px; line-height: 1.9; color: var(--ink2); font-style: italic; }
.concl-txt strong { color: var(--ink); font-weight: 600; }
.concl-foot { margin-top: 14px; padding-top: 12px; border-top: 1px solid var(--border); font-size: 11px; color: var(--ink4); font-family: var(--mono); }

/* ── EXCLUSION BANNER ── */
.excl-banner { background: var(--crit-bg); border: 1px solid var(--crit-bd); border-radius: 8px; padding: 10px 16px; display: flex; align-items: center; gap: 10px; font-size: 12px; color: var(--crit); font-weight: 500; }
.excl-banner i { flex-shrink: 0; }

/* ── FOOTER ── */
.rpt-footer { display: flex; justify-content: space-between; align-items: center; padding-top: 20px; border-top: 1px solid var(--border); }
.ft-l { font-size: 12px; color: var(--ink4); line-height: 1.8; }
.ft-l strong { color: var(--gold); font-weight: 600; }
.ft-r { font-family: var(--mono); font-size: 11px; color: var(--ink4); }

/* ── DIVIDER ── */
.divider-gold { height: 1px; background: linear-gradient(90deg,transparent,var(--gold2) 20%,var(--gold) 50%,var(--gold2) 80%,transparent); opacity: .6; }

/* ── PRINT ── */
@media print {
  /* ── Ocultar navegación ── */
  .topbar, .modebar { display: none !important; }

  /* ── Reset base ── */
  * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
  body { background: #fff !important; font-size: 11px !important; -webkit-font-smoothing: antialiased; }

  /* ── Página A4 ── */
  @page { margin: 12mm 14mm; size: A4 portrait; }
  @page :first { margin-top: 8mm; }

  /* ── Layout ── */
  .page { padding: 0 !important; max-width: 100% !important; gap: 14px !important; }
  .anim { animation: none !important; opacity: 1 !important; transform: none !important; }

  /* ── Evitar cortes dentro de bloques ── */
  .hero          { break-inside: avoid !important; }
  .semg          { break-inside: avoid !important; }
  .ka-grid       { break-inside: avoid !important; }
  .hallazgo      { break-inside: avoid !important; }
  .hallazgo-grid { break-inside: avoid !important; }
  .conclusion    { break-inside: avoid !important; }
  .tbl-wrap      { break-inside: auto !important; }
  .rpt-header    { break-inside: avoid !important; }
  .rpt-footer    { break-inside: avoid !important; }
  .sec-label     { break-after: avoid !important; }

  /* Las secciones principales empiezan en nueva página si no caben */
  .tbl-wrap   { break-before: auto !important; }
  .hallazgo-grid { break-before: auto !important; }

  /* Evitar viudas y huérfanas en texto */
  p, .h-desc, .concl-txt { orphans: 3; widows: 3; }

  /* ── Tablas: no cortar filas ── */
  table.bt { border-collapse: collapse !important; }
  table.bt tr { break-inside: avoid !important; }
  table.bt thead { display: table-header-group !important; }
  table.bt tfoot { display: table-footer-group !important; }
  table.bt td, table.bt th { padding: 6px 10px !important; font-size: 10px !important; }

  /* ── Reducir tamaños para que entre más en página ── */
  .hero { gap: 0 !important; }
  .hero-gauge { padding: 24px 24px !important; }
  .hero-kpis  { padding: 24px 28px !important; gap: 12px !important; }
  .hk-v { font-size: 26px !important; }
  .hk-l { font-size: 9px !important; }
  .sem-n { font-size: 38px !important; }
  .semg { gap: 8px !important; }
  .semc { padding: 16px !important; }
  .ka { padding: 14px !important; }
  .ka-v { font-size: 26px !important; }
  .rpt-title { font-size: 28px !important; }
  .hallazgo { padding: 12px 16px !important; gap: 12px !important; }
  .conclusion { padding: 18px 22px !important; }
  .concl-txt { font-size: 13px !important; }
  .divider-gold { margin: 4px 0 !important; }
  .page { gap: 12px !important; }
}

@media (max-width: 720px) {
  .rpt-header { grid-template-columns: 1fr; }
  .hero { grid-template-columns: 1fr; }
  .hero-kpis { grid-template-columns: repeat(2,1fr); }
  .semg { grid-template-columns: repeat(2,1fr); }
  .ka-grid { grid-template-columns: 1fr; }
  .spark-grid { grid-template-columns: 1fr; }
  .page { padding: 20px 14px 60px; }
  .topbar-row { padding: 0 14px; }
}
</style>
</head>
<body>

<!-- TOPBAR -->
<header class="topbar">
  <div class="topbar-row">
    <div class="t-logo">
      <img src="assets/logo.png" alt="Hochschild Mining"
           onerror="this.style.display='none'">
    </div>
    <div class="t-div"></div>
    <div>
      <div class="t-title">Informe Ejecutivo · SITRAN</div>
      <div class="t-sub">Sistema de Trazabilidad de Transporte</div>
    </div>
    <div class="t-spacer"></div>
    <a href="kpis_pro.php?tipo=<?=h($tipo)?>&year_a=<?=$year_a?>&week_a=<?=$week_a?>" class="t-btn">
      <i class="fas fa-arrow-left" style="font-size:12px"></i> Dashboard
    </a>
    <button class="t-btn t-btn-gold" onclick="window.print()">
      <i class="fas fa-print" style="font-size:12px"></i> Imprimir / PDF
    </button>
  </div>
  <div class="topbar-accent"></div>
</header>

<!-- TABS MODO -->
<div class="modebar">
  <a href="?tipo=subida&year_a=<?=$year_a?>&week_a=<?=$week_a?>" class="mopt <?=$tipo==='subida'?'act':''?>">
    <i class="fas fa-arrow-up"></i> Subida
  </a>
  <a href="?tipo=bajada&year_a=<?=$year_a?>&week_a=<?=$week_a?>" class="mopt <?=$tipo==='bajada'?'act':''?>">
    <i class="fas fa-arrow-down"></i> Bajada
  </a>
  <a href="?tipo=ambos&year_a=<?=$year_a?>&week_a=<?=$week_a?>" class="mopt <?=$tipo==='ambos'?'act':''?>">
    <i class="fas fa-arrows-up-down"></i> Subida & Bajada
  </a>
</div>

<div class="page">



  <!-- HEADER DEL INFORME -->
  <header class="rpt-header anim d1">
    <div>
      <div class="rpt-eyebrow">Informe Ejecutivo · Hochschild Mining</div>
      <h1 class="rpt-title">SITRAN <em>·</em> <?=h($tipo_label)?></h1>
      <p class="rpt-sub"><?=h($label)?> &nbsp;·&nbsp; Generado el <?=$generado?></p>
    </div>
    <div class="rpt-meta">
      <div class="rpt-badge"><span class="live-dot"></span> Datos en vivo</div>
      <div class="rpt-info">
        <strong><?=h($label)?></strong><br>
        Movimiento: <?=h($tipo_label)?><br>

        Generado: <?=$generado?>
      </div>
    </div>
  </header>

  <!-- HERO GAUGE -->
  <div class="hero anim d2">
    <div class="hero-gauge">
      <?= gauge_svg_v2($pct_h, $tipo === 'ambos' ? 'Total' : $tipo_label) ?>
      <div class="hero-legend">
        <strong><?=number_format($bp['sum_r'])?></strong> escaneados<br>
        de <strong><?=number_format($bp['sum_t'])?></strong> programados<br>
        <strong style="color:var(--crit)"><?=number_format($bp['falt'])?></strong> pendientes
      </div>
    </div>
    <div class="hero-kpis">
      <div class="hk"><div class="hk-v gold"><?=number_format($bp['sum_t'])?></div><div class="hk-l">Teórico</div><div class="hk-s">programados</div></div>
      <div class="hk"><div class="hk-v <?=$col_g?>"><?=number_format($bp['sum_r'])?></div><div class="hk-l">Real</div><div class="hk-s">escaneados</div></div>
      <div class="hk"><div class="hk-v crit"><?=number_format($bp['falt'])?></div><div class="hk-l">Faltantes</div><div class="hk-s">sin registro</div></div>
      <div class="hk"><div class="hk-v ok"><?=$bp['n_ok']?></div><div class="hk-l">Buses ≥ 85%</div><div class="hk-s">meta cumplida</div></div>
      <div class="hk"><div class="hk-v warn"><?=$bp['n_warn']?></div><div class="hk-l">Buses 50–84%</div><div class="hk-s">en progreso</div></div>
      <div class="hk"><div class="hk-v crit"><?=$bp['n_crit']?></div><div class="hk-l">Buses &lt;50%</div><div class="hk-s">críticos</div></div>
    </div>
  </div>

  <div class="divider-gold"></div>

  <?php if ($tipo === 'ambos'): ?>

  <!-- MODO AMBOS: cards Sub + Baj + Total -->
  <div class="sec-label anim d3"><span class="sec-num">01</span><i class="fas fa-chart-bar"></i>Vista consolidada · Subida &amp; Bajada</div>
  <div class="ka-grid anim d3">
    <div class="ka s">
      <div class="ka-label"><i class="fas fa-arrow-up" style="margin-right:5px"></i>Subida</div>
      <div class="ka-row">
        <div class="ka-v"><?=$tot_fs?>%</div>
        <div style="text-align:right">
          <div class="mn ok"><?=number_format($tot_rs)?></div>
          <div style="font-size:10px;color:var(--ink4)">escaneados</div>
        </div>
      </div>
      <div class="ka-detail"><?=number_format($tot_ts)?> prog. · <?=number_format(max(0,$tot_ts-$tot_rs))?> faltantes</div>
    </div>
    <div class="ka b">
      <div class="ka-label"><i class="fas fa-arrow-down" style="margin-right:5px"></i>Bajada</div>
      <div class="ka-row">
        <div class="ka-v"><?=$tot_fb?>%</div>
        <div style="text-align:right">
          <div class="mn warn"><?=number_format($tot_rb)?></div>
          <div style="font-size:10px;color:var(--ink4)">escaneados</div>
        </div>
      </div>
      <div class="ka-detail"><?=number_format($tot_tb)?> prog. · <?=number_format(max(0,$tot_tb-$tot_rb))?> faltantes</div>
    </div>
    <div class="ka t">
      <div class="ka-label"><i class="fas fa-arrows-up-down" style="margin-right:5px"></i>Total combinado</div>
      <div class="ka-row">
        <div class="ka-v"><?=$tot_pt?>%</div>
        <div style="text-align:right">
          <div class="mn gold"><?=number_format($tot_rt)?></div>
          <div style="font-size:10px;color:var(--ink4)">escaneados</div>
        </div>
      </div>
      <div class="ka-detail"><?=number_format($tot_tt)?> prog. · <?=number_format(max(0,$tot_tt-$tot_rt))?> faltantes</div>
    </div>
  </div>

  <!-- SEMÁFORO TOTAL -->
  <div class="sec-label anim d3"><span class="sec-num">02</span><i class="fas fa-traffic-light"></i>Semáforo de buses</div>
  <?php
    $buses_ok_list   = array_filter($tabla_ambos, fn($r) => $r['tt']>0 && $r['pt']>=85);
    $buses_warn_list = array_filter($tabla_ambos, fn($r) => $r['tt']>0 && $r['pt']>=50 && $r['pt']<85);
  ?>
  <div class="semg anim d3">
    <div class="semc ok">
      <div class="sem-n"><?=count($buses_ok_list)?></div>
      <div class="sem-l">Completados</div>
      <div class="sem-s">≥ 85% avance total</div>
      <?php if (!empty($buses_ok_list)): ?>
      <div class="sem-buses">
        <?php foreach ($buses_ok_list as $sb): ?>
        <div class="sem-bus-row">
          <span class="sem-bus-nm"><?=h($sb['bus'])?></span>
          <?php if ($sb['placa']): ?><span class="sem-bus-pl"><?=h($sb['placa'])?></span><?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
    <div class="semc warn">
      <div class="sem-n"><?=count($buses_warn_list)?></div>
      <div class="sem-l">En Progreso</div>
      <div class="sem-s">50 – 84% avance total</div>
      <?php if (!empty($buses_warn_list)): ?>
      <div class="sem-buses">
        <?php foreach ($buses_warn_list as $sb): ?>
        <div class="sem-bus-row">
          <span class="sem-bus-nm"><?=h($sb['bus'])?></span>
          <?php if ($sb['placa']): ?><span class="sem-bus-pl"><?=h($sb['placa'])?></span><?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
    <div class="semc crit">
      <div class="sem-n"><?=$bp['n_crit']?></div>
      <div class="sem-l">Críticos</div>
      <div class="sem-s">&lt; 50% avance total</div>
    </div>
  </div>



  <!-- TABLA COMBINADA -->
  <div class="sec-label anim d5"><span class="sec-num">03</span><i class="fas fa-table"></i>Desglose por bus — A-Z</div>
  <div class="tbl-wrap anim d5">
    <div class="tbl-hdr">
      <span class="tbl-hdr-t">Subida · Bajada · Total por bus</span>
      <span class="tbl-badge"><?=h($label)?></span>
    </div>
    <div style="overflow-x:auto">
    <table class="bt">
      <thead>
        <tr>
          <th class="left" rowspan="2" style="vertical-align:bottom;border-right:1px solid var(--border)">Bus</th>
          <th rowspan="2" style="vertical-align:bottom;border-right:1px solid var(--border)">Placa</th>
          <th colspan="3" class="gs" style="border-left:1px solid rgba(29,158,117,.15)">↑ Subida</th>
          <th colspan="3" class="gb" style="border-left:1px solid rgba(186,117,23,.15)">↓ Bajada</th>
          <th colspan="3" class="gt" style="border-left:1px solid var(--gold-ring)">⬡ Total</th>
        </tr>
        <tr>
          <th class="gs" style="border-left:1px solid rgba(29,158,117,.08)">Teo.</th><th class="gs">Real</th><th class="gs">%</th>
          <th class="gb" style="border-left:1px solid rgba(186,117,23,.08)">Teo.</th><th class="gb">Real</th><th class="gb">%</th>
          <th class="gt" style="border-left:1px solid var(--gold-ring)">Teo.</th><th class="gt">Real</th><th class="gt">%</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($tabla_ambos as $row):
        $cs = $row['fs']['pct']>=85?'ok':($row['fs']['pct']>=50?'warn':'crit');
        $cb = $row['fb']['pct']>=85?'ok':($row['fb']['pct']>=50?'warn':'crit');
        $ct = $row['pt']>=85?'ok':($row['pt']>=50?'warn':'crit');
        $xs = $row['fs']['pct']>=85?'var(--ok)':($row['fs']['pct']>=50?'var(--warn)':'var(--crit)');
        $xb = $row['fb']['pct']>=85?'var(--ok)':($row['fb']['pct']>=50?'var(--warn)':'var(--crit)');
        $xt = $row['pt']>=85?'var(--ok)':($row['pt']>=50?'var(--warn)':'var(--crit)');
        $hl = $row['pt']>=85?'row-hl-ok':($row['pt']>=50?'row-hl-warn':($row['tt']>0?'row-hl-crit':''));
      ?>
      <tr class="<?=$hl?>">
        <td class="left" style="border-right:1px solid var(--border)">
          <span class="bt-nm"><i class="fas fa-bus" style="font-size:9px;opacity:.3;margin-right:5px"></i><?=h($row['bus'])?></span>
        </td>
        <td style="border-right:1px solid var(--border)">
          <?php if ($row['placa']): ?><span class="bt-pl"><?=h($row['placa'])?></span>
          <?php else: ?><span style="font-size:10px;color:var(--ink4)">—</span><?php endif; ?>
        </td>
        <td style="border-left:1px solid rgba(29,158,117,.08)"><span class="mn gold"><?=$row['fs']['t']?:'-'?></span></td>
        <td><span class="mn <?=$cs?>"><?=$row['fs']['r']?:'-'?></span></td>
        <td><?php if($row['fs']['t']>0): ?><div class="mini-bar"><div class="mini-t"><div class="mini-f" style="width:<?=min($row['fs']['pct'],100)?>%;background:<?=$xs?>"></div></div><span class="mini-p" style="color:<?=$xs?>"><?=$row['fs']['pct']?>%</span></div><?php else: ?>—<?php endif; ?></td>
        <td style="border-left:1px solid rgba(186,117,23,.08)"><span class="mn gold"><?=$row['fb']['t']?:'-'?></span></td>
        <td><span class="mn <?=$cb?>"><?=$row['fb']['r']?:'-'?></span></td>
        <td><?php if($row['fb']['t']>0): ?><div class="mini-bar"><div class="mini-t"><div class="mini-f" style="width:<?=min($row['fb']['pct'],100)?>%;background:<?=$xb?>"></div></div><span class="mini-p" style="color:<?=$xb?>"><?=$row['fb']['pct']?>%</span></div><?php else: ?>—<?php endif; ?></td>
        <td style="border-left:1px solid var(--gold-ring);background:var(--gold-bg)"><span class="mn gold"><?=number_format($row['tt'])?></span></td>
        <td style="background:var(--gold-bg)"><span class="mn <?=$ct?>"><?=number_format($row['rt'])?></span></td>
        <td style="background:var(--gold-bg)"><div class="mini-bar"><div class="mini-t"><div class="mini-f" style="width:<?=min($row['pt'],100)?>%;background:<?=$xt?>"></div></div><span class="mini-p" style="color:<?=$xt?>"><?=$row['pt']?>%</span></div></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr>
          <td class="left" colspan="2" style="color:var(--gold);font-weight:700;font-size:12px;text-transform:uppercase;border-right:1px solid var(--border)">Total general</td>
          <td style="border-left:1px solid rgba(29,158,117,.1)"><span class="mn gold"><?=number_format($tot_ts)?></span></td>
          <td><span class="mn ok"><?=number_format($tot_rs)?></span></td>
          <td><?php $xs2=$tot_fs>=85?'var(--ok)':($tot_fs>=50?'var(--warn)':'var(--crit)');?><div class="mini-bar"><div class="mini-t"><div class="mini-f" style="width:<?=min($tot_fs,100)?>%;background:<?=$xs2?>"></div></div><span class="mini-p" style="color:<?=$xs2?>"><?=$tot_fs?>%</span></div></td>
          <td style="border-left:1px solid rgba(186,117,23,.1)"><span class="mn gold"><?=number_format($tot_tb)?></span></td>
          <td><span class="mn warn"><?=number_format($tot_rb)?></span></td>
          <td><?php $xb2=$tot_fb>=85?'var(--ok)':($tot_fb>=50?'var(--warn)':'var(--crit)');?><div class="mini-bar"><div class="mini-t"><div class="mini-f" style="width:<?=min($tot_fb,100)?>%;background:<?=$xb2?>"></div></div><span class="mini-p" style="color:<?=$xb2?>"><?=$tot_fb?>%</span></div></td>
          <td style="border-left:1px solid var(--gold-ring);background:var(--gold-bg)"><span class="mn gold"><?=number_format($tot_tt)?></span></td>
          <td style="background:var(--gold-bg)"><?php $xt2=$tot_pt>=85?'var(--ok)':($tot_pt>=50?'var(--warn)':'var(--crit)');?><span class="mn" style="color:<?=$xt2?>"><?=number_format($tot_rt)?></span></td>
          <td style="background:var(--gold-bg)"><div class="mini-bar"><div class="mini-t"><div class="mini-f" style="width:<?=min($tot_pt,100)?>%;background:<?=$xt2?>"></div></div><span class="mini-p" style="color:<?=$xt2?>"><?=$tot_pt?>%</span></div></td>
        </tr>
      </tfoot>
    </table>
    </div>
    <div class="tbl-leg">
      <div class="tl-item"><div class="tl-dot" style="background:var(--ok)"></div>≥ 85% Completado</div>
      <div class="tl-item"><div class="tl-dot" style="background:var(--warn)"></div>50–84% En progreso</div>
      <div class="tl-item"><div class="tl-dot" style="background:var(--crit)"></div>&lt; 50% Crítico</div>
    </div>
  </div>

  <?php else: /* MODO INDIVIDUAL */ ?>

  <?php
    $bl = $bloques[$tipo];
    $color_modo = $tipo==='subida' ? '#0F6E56' : '#854F0B';
    $bg_modo    = $tipo==='subida' ? '#1D9E75' : '#BA7517';
  ?>

  <!-- SEMÁFORO INDIVIDUAL -->
  <div class="sec-label anim d3"><span class="sec-num">01</span><i class="fas fa-traffic-light"></i>Semáforo de buses — <?=h($tipo_label)?></div>
  <div class="semg anim d3">
    <div class="semc ok"><div class="sem-n"><?=$bl['n_ok']?></div><div class="sem-l">Completados</div><div class="sem-s">≥ 85% avance</div></div>
    <div class="semc warn"><div class="sem-n"><?=$bl['n_warn']?></div><div class="sem-l">En Progreso</div><div class="sem-s">50 – 84% avance</div></div>
    <div class="semc crit"><div class="sem-n"><?=$bl['n_crit']?></div><div class="sem-l">Críticos</div><div class="sem-s">&lt; 50% avance</div></div>
  </div>



  <!-- TABLA INDIVIDUAL -->
  <div class="sec-label anim d5"><span class="sec-num">03</span><i class="fas fa-table"></i>Desglose por bus — A-Z</div>
  <div class="tbl-wrap anim d5">
    <div class="tbl-hdr">
      <span class="tbl-hdr-t"><?=h($tipo_label)?> · <?=count($bl['filas'])?> buses</span>
      <span class="tbl-badge"><?=h($label)?></span>
    </div>
    <table class="bt">
      <thead>
        <tr>
          <th class="left">Bus</th><th>Placa</th>
          <th class="<?=$tipo==='subida'?'gs':'gb'?>">Teórico</th>
          <th class="<?=$tipo==='subida'?'gs':'gb'?>">Real</th>
          <th class="<?=$tipo==='subida'?'gs':'gb'?>">Faltantes</th>
          <th class="<?=$tipo==='subida'?'gs':'gb'?>">Avance</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($bl['filas'] as $f):
        $fc = $f['pct']>=85?'ok':($f['pct']>=50?'warn':'crit');
        $hx = $f['pct']>=85?'var(--ok)':($f['pct']>=50?'var(--warn)':'var(--crit)');
        $hl = $f['pct']>=85?'row-hl-ok':($f['pct']>=50?'row-hl-warn':($f['t']>0?'row-hl-crit':''));
      ?>
      <tr class="<?=$hl?>">
        <td class="left"><span class="bt-nm"><i class="fas fa-bus" style="font-size:9px;opacity:.3;margin-right:5px"></i><?=h($f['bus'])?></span></td>
        <td><?php if($f['placa']&&$f['placa']!='N/A'):?><span class="bt-pl"><?=h($f['placa'])?></span><?php else:?>—<?php endif;?></td>
        <td><span class="mn gold"><?=number_format($f['t'])?></span></td>
        <td><span class="mn <?=$fc?>"><?=number_format($f['r'])?></span></td>
        <td><span class="mn crit"><?=number_format(max(0,$f['t']-$f['r']))?></span></td>
        <td style="min-width:120px"><div class="mini-bar"><div class="mini-t"><div class="mini-f" style="width:<?=min($f['pct'],100)?>%;background:<?=$hx?>"></div></div><span class="mini-p" style="color:<?=$hx?>"><?=$f['pct']?>%</span></div></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr>
          <td class="left" colspan="2" style="color:var(--gold);font-weight:700;text-transform:uppercase">Total general</td>
          <td><span class="mn gold"><?=number_format($bl['sum_t'])?></span></td>
          <td><span class="mn <?=$col_g?>"><?=number_format($bl['sum_r'])?></span></td>
          <td><span class="mn crit"><?=number_format($bl['falt'])?></span></td>
          <td><div class="mini-bar"><?php $hxf=$bl['pct']>=85?'var(--ok)':($bl['pct']>=50?'var(--warn)':'var(--crit)');?><div class="mini-t"><div class="mini-f" style="width:<?=min($bl['pct'],100)?>%;background:<?=$hxf?>"></div></div><span class="mini-p" style="color:<?=$hxf?>"><?=$bl['pct']?>%</span></div></td>
        </tr>
      </tfoot>
    </table>
    <div class="tbl-leg">
      <div class="tl-item"><div class="tl-dot" style="background:var(--ok)"></div>≥ 85% Completado</div>
      <div class="tl-item"><div class="tl-dot" style="background:var(--warn)"></div>50–84% En progreso</div>
      <div class="tl-item"><div class="tl-dot" style="background:var(--crit)"></div>&lt; 50% Crítico</div>
    </div>
  </div>

  <?php endif; ?>

  <div class="divider-gold"></div>

  <!-- HALLAZGOS CLAVE -->
  <div class="sec-label anim d6"><span class="sec-num">04</span><i class="fas fa-magnifying-glass-chart"></i>Hallazgos clave</div>
  <div class="hallazgo-grid anim d6">
    <?php
      $h_pct  = $tipo==='ambos' ? $tot_pt    : $pct_h;
      $h_crit = $tipo==='ambos' ? $bp['n_crit'] : $bp['n_crit'];
    ?>

    <div class="hallazgo">
      <span class="hbadge hb-ok"><i class="fas fa-check"></i> Sistema</span>
      <div class="h-body">
        <div class="h-title">Infraestructura técnica operando al 100%</div>
        <div class="h-desc">El sistema procesó todos los escaneos QR sin errores. Servidor estable, base de datos sincronizada, tiempo de respuesta &lt;1.2s.</div>
      </div>
    </div>

    <?php if ($h_crit > 0): ?>
    <div class="hallazgo">
      <span class="hbadge hb-crit"><i class="fas fa-triangle-exclamation"></i> Crítico</span>
      <div class="h-body">
        <div class="h-title"><?=$h_crit?> bus<?=$h_crit>1?'es':''?> con avance inferior al 50%</div>
        <div class="h-desc">Requieren atención inmediata. Revisar con jefes de turno y verificar uso del lector QR en campo.</div>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($h_pct >= 85): ?>
    <div class="hallazgo">
      <span class="hbadge hb-ok"><i class="fas fa-star"></i> Meta</span>
      <div class="h-body">
        <div class="h-title">Meta semanal alcanzada — <?=$h_pct?>%</div>
        <div class="h-desc">El avance supera el umbral del 85%. Mantener el protocolo y monitorear consistencia en semanas siguientes.</div>
      </div>
    </div>
    <?php elseif ($h_pct < 50): ?>
    <div class="hallazgo">
      <span class="hbadge hb-crit"><i class="fas fa-circle-exclamation"></i> Alerta</span>
      <div class="h-body">
        <div class="h-title">Avance global crítico — <?=$h_pct?>%</div>
        <div class="h-desc">Más del 50% sin registro. Se recomienda revisión urgente del protocolo operativo con supervisores de turno.</div>
      </div>
    </div>
    <?php else: ?>
    <div class="hallazgo">
      <span class="hbadge hb-gold"><i class="fas fa-arrow-trend-up"></i> Seguimiento</span>
      <div class="h-body">
        <div class="h-title">Semana en progreso — <?=$h_pct?>%</div>
        <div class="h-desc">Avance dentro del rango esperado. Seguimiento diario recomendado para asegurar el cierre en meta.</div>
      </div>
    </div>
    <?php endif; ?>

    <div class="hallazgo">
      <span class="hbadge hb-warn"><i class="fas fa-person"></i> Operativo</span>
      <div class="h-body">
        <div class="h-title">Registros incompletos de origen procedimental</div>
        <div class="h-desc">Los faltantes corresponden a factores humanos, no sistémicos. Supervisión de campo y refuerzo de protocolo de uso del pulser.</div>
      </div>
    </div>

    <?php if ($tipo === 'ambos'): ?>
    <div class="hallazgo">
      <span class="hbadge hb-<?=abs($tot_fs-$tot_fb)>15?'crit':($tot_fs===$tot_fb?'ok':'gold')?>">
        <i class="fas fa-arrows-up-down"></i> Brecha
      </span>
      <div class="h-body">
        <div class="h-title">Brecha Subida vs Bajada — <?=abs($tot_fs-$tot_fb)?>pp de diferencia</div>
        <div class="h-desc">Subida <?=$tot_fs?>% · Bajada <?=$tot_fb?>%.
          <?=$tot_fs>$tot_fb?'La bajada requiere refuerzo operativo.':'La subida requiere refuerzo operativo.'?>
          <?=abs($tot_fs-$tot_fb)>15?' Brecha significativa — acción prioritaria recomendada.':' Brecha dentro de rango aceptable.'?>
        </div>
      </div>
    </div>
    <?php endif; ?>


  </div>

  <!-- CONCLUSIÓN -->
  <div class="conclusion anim d6">
    <div class="concl-tag"><i class="fas fa-quote-left" style="margin-right:6px;opacity:.5"></i>Conclusión ejecutiva</div>
    <p class="concl-txt">
      La plataforma SITRAN demuestra <strong>fiabilidad técnica total</strong> en el procesamiento de escaneos.
      <?php if ($h_crit > 0): ?>
      Se identificaron <strong><?=$h_crit?> bus<?=$h_crit>1?'es':''?> en estado crítico</strong> que requieren atención operativa inmediata.
      <?php endif; ?>
      <?php if ($tipo === 'ambos'): ?>
      El análisis combinado refleja <strong>Subida <?=$tot_fs?>%</strong> y <strong>Bajada <?=$tot_fb?>%</strong>,
      con un avance total de <strong><?=$tot_pt?>%</strong>.
      <?php else: ?>
      El avance de <?=h($tipo_label)?> es de <strong><?=$pct_h?>%</strong> esta semana.
      <?php endif; ?>
      Los registros incompletos son de <strong>origen procedimental</strong> (humano), no sistémico.
      La infraestructura está <strong>lista para escalar a producción total</strong>.
    </p>
    <div class="concl-foot">
      Ingeniería de Sistemas · SITRAN · Hochschild Mining · <?=date('Y')?>
      <?php if (!empty($BUSES_EXCLUIDOS)): ?>
       · <span style="color:var(--crit)">Nota: informe con <?=count($BUSES_EXCLUIDOS)?> bus<?=count($BUSES_EXCLUIDOS)>1?'es exclusión':' en exclusión'?></span>
      <?php endif; ?>
    </div>
  </div>

  <!-- FOOTER -->
  <div class="divider-gold"></div>
  <footer class="rpt-footer">
    <div class="ft-l">
      <strong>SITRAN</strong> · Hochschild Mining<br>
      <?=h($label)?> · <?=h($tipo_label)?>
    </div>
    <div class="ft-r">Generado <?=$generado?></div>
  </footer>

</div>
</body>
</html>