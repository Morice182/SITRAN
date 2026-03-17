<?php
/**
 * kpi_destinos.php  —  Hochschild Mining
 * KPI Semanal: Teórico (lista_subida + lista_bajada) vs Real (registros)
 * Funciones: Export Excel · Semáforo · Comparativa · Modal · Rango semanas
 */

session_start();
if (!isset($_SESSION['usuario'])) { header("Location: index.php"); exit(); }
require __DIR__ . "/config.php";
function h($s){ return htmlspecialchars($s, ENT_QUOTES,'UTF-8'); }

/* ═══════════════════════════════════════════════════════
   HELPERS DE SEMANA
═══════════════════════════════════════════════════════ */
function semana_lunes(int $year, int $week): string {
    $dt = new DateTime();
    $dt->setISODate($year, $week, 1);
    return $dt->format('Y-m-d');
}
function semana_domingo(string $lunes): string {
    return date('Y-m-d', strtotime($lunes) + 6*86400);
}
function semana_label(int $year, int $week, string $lunes): string {
    $dom = semana_domingo($lunes);
    return "Sem. $week — " . date('d M', strtotime($lunes)) . ' al ' . date('d M Y', strtotime($dom));
}

/* ═══════════════════════════════════════════════════════
   PARÁMETROS GET
═══════════════════════════════════════════════════════ */
$tipo  = in_array($_GET['tipo'] ?? '', ['subida','bajada','ambos']) ? $_GET['tipo'] : 'ambos';

$year_a  = (int)($_GET['year_a']  ?? date('Y'));
$week_a  = (int)($_GET['week_a']  ?? date('W'));
$year_a  = max(2020, min(2030, $year_a));
$week_a  = max(1,    min(53,   $week_a));
$lun_a   = semana_lunes($year_a, $week_a);
$dom_a   = semana_domingo($lun_a);
$label_a = semana_label($year_a, $week_a, $lun_a);

$usar_b  = isset($_GET['year_b'], $_GET['week_b']);
$year_b  = (int)($_GET['year_b']  ?? $year_a);
$week_b  = (int)($_GET['week_b']  ?? max(1, $week_a - 1));
$year_b  = max(2020, min(2030, $year_b));
$week_b  = max(1,    min(53,   $week_b));
$lun_b   = semana_lunes($year_b, $week_b);
$dom_b   = semana_domingo($lun_b);
$label_b = semana_label($year_b, $week_b, $lun_b);

$week_a_prev = $week_a > 1  ? $week_a - 1 : 52;
$year_a_prev = $week_a > 1  ? $year_a     : $year_a - 1;
$week_a_next = $week_a < 53 ? $week_a + 1 : 1;
$year_a_next = $week_a < 53 ? $year_a     : $year_a + 1;
$sem_actual  = (int)date('W');
$year_actual = (int)date('Y');
$es_actual   = ($week_a === $sem_actual && $year_a === $year_actual);

/* ═══════════════════════════════════════════════════════
   FUNCIONES DB  —  agrupado por BUS + PLACA
═══════════════════════════════════════════════════════ */

/**
 * Clave única por bus: "NOMBRE BUS||PLACA"
 * Devuelve [ clave => ['bus'=>..,'placa'=>..,'n'=>..] ]
 */
function get_teorico_bus($mysqli, $tabla): array {
    $out = [];
    $res = $mysqli->query(
        "SELECT
           UPPER(TRIM(COALESCE(bus,'')))   AS bus,
           UPPER(TRIM(COALESCE(placa,''))) AS placa,
           COUNT(*) AS n
         FROM {$tabla}
         WHERE bus IS NOT NULL AND bus != '' AND bus != 'N/A'
         GROUP BY UPPER(TRIM(bus)), UPPER(TRIM(placa))"
    );
    while ($r = $res->fetch_assoc()) {
        $key = $r['bus'] . '||' . $r['placa'];
        if (!isset($out[$key])) $out[$key] = ['bus'=>$r['bus'],'placa'=>$r['placa'],'n'=>0];
        $out[$key]['n'] += (int)$r['n'];
    }
    return $out;
}

/**
 * Real escaneado agrupado por bus+placa de registros
 * Devuelve [ "BUS||PLACA" => count_dni ]
 */
function get_real_bus($mysqli, string $ini, string $fin, string $tipo): array {
    $ev = match($tipo) {
        'subida' => "AND r.evento = 'SUBIDA PERMITIDA'",
        'bajada' => "AND r.evento = 'BAJADA PERMITIDA'",
        default  => "AND r.evento IN ('SUBIDA PERMITIDA','BAJADA PERMITIDA')"
    };
    $stmt = $mysqli->prepare(
        "SELECT
           UPPER(TRIM(COALESCE(r.bus,'')))   AS bus,
           UPPER(TRIM(COALESCE(r.placa,''))) AS placa,
           COUNT(DISTINCT r.dni) AS n
         FROM registros r
         WHERE DATE(r.fecha) BETWEEN ? AND ?
           $ev
           AND r.bus IS NOT NULL AND r.bus != '' AND r.bus != 'N/A'
         GROUP BY UPPER(TRIM(r.bus)), UPPER(TRIM(r.placa))"
    );
    $stmt->bind_param('ss', $ini, $fin);
    $stmt->execute();
    $res = $stmt->get_result();
    $out = [];
    while ($r = $res->fetch_assoc()) {
        $key = $r['bus'] . '||' . $r['placa'];
        $out[$key] = (int)$r['n'];
    }
    $stmt->close();
    return $out;
}

/**
 * Detalle día a día por bus+placa para el modal
 * Devuelve [ fecha => [ "BUS||PLACA" => n ] ]
 */
function get_dias_bus($mysqli, string $ini, string $fin, string $tipo): array {
    $ev = match($tipo) {
        'subida' => "AND r.evento = 'SUBIDA PERMITIDA'",
        'bajada' => "AND r.evento = 'BAJADA PERMITIDA'",
        default  => "AND r.evento IN ('SUBIDA PERMITIDA','BAJADA PERMITIDA')"
    };
    $stmt = $mysqli->prepare(
        "SELECT
           DATE(r.fecha) AS dia,
           UPPER(TRIM(COALESCE(r.bus,'')))   AS bus,
           UPPER(TRIM(COALESCE(r.placa,''))) AS placa,
           COUNT(DISTINCT r.dni) AS n
         FROM registros r
         WHERE DATE(r.fecha) BETWEEN ? AND ?
           $ev
           AND r.bus IS NOT NULL AND r.bus != '' AND r.bus != 'N/A'
         GROUP BY DATE(r.fecha), UPPER(TRIM(r.bus)), UPPER(TRIM(r.placa))"
    );
    $stmt->bind_param('ss', $ini, $fin);
    $stmt->execute();
    $res = $stmt->get_result();
    $out = [];
    while ($r = $res->fetch_assoc()) {
        $key = $r['bus'] . '||' . $r['placa'];
        $out[$r['dia']][$key] = (int)$r['n'];
    }
    $stmt->close();
    return $out;
}

/* ═══════════════════════════════════════════════════════
   DATOS
═══════════════════════════════════════════════════════ */
$teo_sub = ($tipo !== 'bajada') ? get_teorico_bus($mysqli, 'lista_subida') : [];
$teo_baj = ($tipo !== 'subida') ? get_teorico_bus($mysqli, 'lista_bajada') : [];

/* Combinar teórico: sumar pasajeros del mismo bus en subida + bajada */
$teorico = [];   // [ key => ['bus'=>..,'placa'=>..,'n'=>..] ]
foreach (array_merge($teo_sub, $teo_baj) as $key => $info) {
    if (!isset($teorico[$key]))
        $teorico[$key] = ['bus'=>$info['bus'], 'placa'=>$info['placa'], 'n'=>0];
    $teorico[$key]['n'] += $info['n'];
}

$real_a    = get_real_bus($mysqli, $lun_a, $dom_a, $tipo);
$real_b    = $usar_b ? get_real_bus($mysqli, $lun_b, $dom_b, $tipo) : [];
$dias_data = get_dias_bus($mysqli, $lun_a, $dom_a, $tipo);

/* Días pasados de la semana A */
$dias_lista = [];
for ($i = 0; $i < 7; $i++) {
    $d = date('Y-m-d', strtotime($lun_a) + $i*86400);
    if ($d <= date('Y-m-d')) $dias_lista[] = $d;
}
$dias_pasados = count($dias_lista);

/* ═══════════════════════════════════════════════════════
   CONSTRUIR FILAS  (ordenadas por nombre de bus)
═══════════════════════════════════════════════════════ */
$todas_keys = array_unique(array_merge(array_keys($teorico), array_keys($real_a)));

/* Construir info de bus para claves que solo están en real (sin teórico) */
$bus_info = [];
foreach ($teorico as $key => $info)
    $bus_info[$key] = ['bus'=>$info['bus'], 'placa'=>$info['placa']];
foreach ($real_a as $key => $n) {
    if (!isset($bus_info[$key])) {
        [$b, $p] = explode('||', $key, 2);
        $bus_info[$key] = ['bus'=>$b, 'placa'=>$p];
    }
}

/* Ordenar por nombre de bus */
usort($todas_keys, fn($a,$b) => strcmp($bus_info[$a]['bus'], $bus_info[$b]['bus']));

$filas = []; $sum_teo = 0; $sum_real_a = 0;
foreach ($todas_keys as $key) {
    $t    = $teorico[$key]['n'] ?? 0;
    $ra   = $real_a[$key]       ?? 0;
    $rb   = $real_b[$key]       ?? 0;
    $bus  = $bus_info[$key]['bus'];
    $placa= $bus_info[$key]['placa'];

    $pct_a = $t > 0 ? round(($ra/$t)*100) : ($ra>0?100:0);
    $pct_b = $t > 0 ? round(($rb/$t)*100) : ($rb>0?100:0);

    /* Semáforo: días con al menos un escaneo para este bus */
    $dias_con = 0;
    foreach ($dias_lista as $d)
        if (!empty($dias_data[$d][$key]) && $dias_data[$d][$key] > 0) $dias_con++;
    $dias_sin = max(0, $dias_pasados - $dias_con);
    $alerta   = ($dias_sin >= 3 && $dias_pasados >= 3 && $t > 0);

    /* Detalle días para modal */
    $det = [];
    for ($i = 0; $i < 7; $i++) {
        $d = date('Y-m-d', strtotime($lun_a) + $i*86400);
        $det[] = ['fecha'=>$d, 'n'=>$dias_data[$d][$key] ?? 0, 'fut'=>$d>date('Y-m-d')];
    }

    $filas[] = compact('key','bus','placa','t','ra','rb','pct_a','pct_b','alerta','dias_sin','det');
    $sum_teo    += $t;
    $sum_real_a += $ra;
}
$pct_global = $sum_teo > 0 ? round(($sum_real_a/$sum_teo)*100) : 0;
$faltantes  = max(0, $sum_teo - $sum_real_a);

/* Sparkline: total pasajeros escaneados por día (todos los buses) */
$noms_dias = ['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'];
$spark = [];
for ($i = 0; $i < 7; $i++) {
    $d = date('Y-m-d', strtotime($lun_a) + $i*86400);
    $n = array_sum($dias_data[$d] ?? []);
    $spark[] = ['d'=>$d,'nom'=>$noms_dias[$i],'n'=>$n,'hoy'=>$d===date('Y-m-d'),'fut'=>$d>date('Y-m-d')];
}
$max_spark = max(array_column($spark,'n') ?: [1]);

/* Top 3 críticos (buses con teórico > 0, menor avance) */
$criticos = array_filter($filas, fn($f)=>$f['t']>0);
usort($criticos, fn($a,$b)=>$a['pct_a']-$b['pct_a']);
$criticos = array_slice($criticos, 0, 3);

/* Parámetros comparativa para query string */
$qs_b = $usar_b ? "&year_b={$year_b}&week_b={$week_b}" : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>KPI Semanal por Bus · Hochschild Mining</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@400;600;700;800;900&family=Barlow:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<style>
:root{
  --g0:#b8922a;--g1:#d4ad52;--gd:#8a6a1a;--glow:rgba(184,146,42,.12);
  --ink:#f0f2f5;--s0:#ffffff;--s1:#ffffff;--s2:#f8f9fb;--s3:#eef0f4;
  --sl:#8a94a6;--slb:#5a6478;--w:#1a2030;
  --ok:#16a34a;--warn:#d97706;--crit:#dc2626;
  --ok-bg:rgba(22,163,74,.08);--warn-bg:rgba(217,119,6,.08);--crit-bg:rgba(220,38,38,.08);
  --bd:rgba(0,0,0,.07);--bd-g:rgba(184,146,42,.28);
  --r:12px;--rs:8px;
  --fd:'Barlow Condensed',sans-serif;
  --fb:'Barlow',sans-serif;
  --fm:'JetBrains Mono',monospace;
}
*{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{font-family:var(--fb);background:#f0f2f5;color:var(--w);min-height:100vh;overflow-x:hidden}
::-webkit-scrollbar{width:5px;height:5px}
::-webkit-scrollbar-track{background:#e8eaee}
::-webkit-scrollbar-thumb{background:var(--g0);border-radius:3px}

/* ── HEADER ─────────────────────────────────────────────────── */
.hdr{
  position:sticky;top:0;z-index:200;
  background:#fff;border-bottom:1px solid rgba(0,0,0,.09);
  box-shadow:0 1px 8px rgba(0,0,0,.06);
  display:grid;grid-template-columns:auto 1fr auto;
  align-items:center;padding:0 24px;height:72px;gap:16px;
}
.hdr::after{
  content:'';position:absolute;bottom:0;left:0;right:0;height:3px;
  background:linear-gradient(90deg,var(--gd),var(--g0) 35%,var(--g1) 65%,var(--g0));
}
.btn-back{
  color:var(--sl);text-decoration:none;font-size:10px;font-weight:700;
  text-transform:uppercase;letter-spacing:1.5px;
  display:flex;align-items:center;gap:6px;transition:all .2s;white-space:nowrap;
  background:#f5f6f8;border:1px solid var(--bd);border-radius:6px;padding:6px 11px;
}
.btn-back:hover{color:var(--g0);border-color:var(--bd-g);background:var(--glow)}
.hdr-mid{text-align:center}
.hdr-mid h1{
  font-family:var(--fd);font-size:17px;font-weight:900;
  text-transform:uppercase;letter-spacing:5px;color:var(--w);
}
.hdr-mid small{
  font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:3px;
  color:var(--g0);display:block;margin-top:1px;
}
.hdr-right{display:flex;align-items:center;gap:14px;justify-content:flex-end}
.hdr-logo{height:52px;object-fit:contain;filter:drop-shadow(0 1px 4px rgba(0,0,0,.10))}
.hdr-divider{width:1px;height:32px;background:var(--bd);flex-shrink:0}
.btn-reload{
  display:inline-flex;align-items:center;gap:7px;
  padding:7px 14px;border-radius:var(--rs);border:1px solid var(--bd);
  background:#f5f6f8;color:var(--slb);cursor:pointer;
  font-family:var(--fb);font-size:11px;font-weight:700;
  transition:all .2s;white-space:nowrap;
}
.btn-reload:hover{border-color:var(--g0);color:var(--g0);background:var(--glow)}
.btn-reload i{font-size:12px}

/* ── LAYOUT ─────────────────────────────────────────────────── */
.page{max-width:1120px;margin:0 auto;padding:22px 16px 64px}
@keyframes up{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}
.page>*{animation:up .35s ease both}
.page>*:nth-child(1){animation-delay:.04s}.page>*:nth-child(2){animation-delay:.08s}
.page>*:nth-child(3){animation-delay:.12s}.page>*:nth-child(4){animation-delay:.16s}
.page>*:nth-child(5){animation-delay:.20s}.page>*:nth-child(6){animation-delay:.24s}

/* ── CARDS BASE ─────────────────────────────────────────────── */
.card{background:var(--s1);border:1px solid var(--bd);border-radius:var(--r);overflow:hidden;margin-bottom:14px}
.chdr{
  padding:13px 18px;border-bottom:1px solid var(--bd);background:var(--s2);
  display:flex;align-items:center;justify-content:space-between;
}
.ctitle{
  font-family:var(--fd);font-size:12px;font-weight:800;
  text-transform:uppercase;letter-spacing:2px;color:var(--slb);
  display:flex;align-items:center;gap:8px;
}
.ctitle i{color:var(--g0)}

/* ── CONTROL BAR ────────────────────────────────────────────── */
.cbar{
  background:var(--s1);border:1px solid var(--bd);border-radius:var(--r);
  padding:11px 16px;margin-bottom:14px;
  display:flex;align-items:center;gap:9px;flex-wrap:wrap;
}
.flbl{font-size:8px;font-weight:800;text-transform:uppercase;letter-spacing:1.5px;color:var(--sl);white-space:nowrap}
.fsel{
  padding:6px 10px;background:var(--s2);border:1px solid var(--bd);
  border-radius:var(--rs);font-family:var(--fb);font-size:11px;font-weight:700;
  color:var(--w);outline:none;cursor:pointer;transition:border-color .2s;
}
.fsel:focus{border-color:var(--g0)}
.fsel option{background:var(--s2)}
.vdiv{width:1px;height:22px;background:var(--bd);flex-shrink:0}

/* Semana nav */
.wnav{display:flex;align-items:center;border:1px solid var(--bd);border-radius:var(--rs);overflow:hidden}
.wbtn{
  width:28px;height:28px;display:flex;align-items:center;justify-content:center;
  background:var(--s2);color:var(--sl);cursor:pointer;text-decoration:none;
  font-size:10px;transition:background .15s,color .15s;
}
.wbtn:hover{background:var(--s3);color:var(--g0)}
.wbtn.dis{opacity:.2;pointer-events:none}
.wtag{
  padding:0 12px;font-family:var(--fm);font-size:10px;font-weight:600;color:var(--w);
  background:var(--s2);border-left:1px solid var(--bd);border-right:1px solid var(--bd);
  white-space:nowrap;line-height:28px;
}

/* Buttons */
.btn{
  display:inline-flex;align-items:center;gap:5px;
  padding:6px 12px;border-radius:var(--rs);
  font-family:var(--fb);font-size:10px;font-weight:700;
  cursor:pointer;border:none;transition:all .2s;text-decoration:none;white-space:nowrap;
}
.btn-g{background:var(--g0);color:#fff;box-shadow:0 2px 8px rgba(184,146,42,.25)}.btn-g:hover{background:var(--gd)}
.btn-gh{background:transparent;border:1px solid var(--bd-g);color:var(--g0)}.btn-gh:hover{background:var(--glow)}
.btn-ol{background:transparent;border:1px solid var(--bd);color:var(--slb)}.btn-ol:hover{border-color:var(--g0);color:var(--g0)}

/* Rango comparativa */
.rbar{
  display:none;flex-wrap:wrap;gap:8px;align-items:center;
  background:rgba(200,160,68,.05);border:1px solid var(--bd-g);
  border-radius:var(--rs);padding:9px 14px;margin-top:8px;
}
.rbar.open{display:flex}
.rlab{background:var(--g0);color:#fff;font-size:8px;font-weight:800;
  text-transform:uppercase;letter-spacing:1px;padding:2px 8px;border-radius:4px}
.rlab.b{background:var(--s3);color:var(--slb);border:1px solid var(--bd)}

/* ── KPI CARDS ──────────────────────────────────────────────── */
.krow{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:14px}
.kcard{
  background:var(--s1);border:1px solid var(--bd);border-radius:var(--r);
  padding:17px 15px;position:relative;overflow:hidden;
  transition:transform .2s,border-color .2s;
}
.kcard:hover{transform:translateY(-2px);border-color:var(--bd-g)}
.kcard::before{content:'';position:absolute;top:0;left:0;right:0;height:2px}
.k1::before{background:linear-gradient(90deg,var(--gd),var(--g0))}
.k2::before{background:linear-gradient(90deg,#0e7a3f,var(--ok))}
.k3::before{background:linear-gradient(90deg,#8a1a1a,var(--crit))}
.k4::before{background:linear-gradient(90deg,#1a3a8a,#5a8af5)}
.kico{font-size:13px;margin-bottom:8px}
.k1 .kico{color:var(--g0)}.k2 .kico{color:var(--ok)}.k3 .kico{color:var(--crit)}.k4 .kico{color:#5a8af5}
.kval{font-family:var(--fd);font-size:38px;font-weight:900;line-height:1;letter-spacing:-1px}
.k4 .kval{color:var(--g0)}
.kname{font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:1.5px;color:var(--sl);margin-top:4px}
.ksub{font-family:var(--fm);font-size:7px;color:var(--sl);margin-top:2px;opacity:.65}
.kcard::after{
  content:'';position:absolute;bottom:-18px;right:-18px;
  width:72px;height:72px;border-radius:50%;opacity:.07;
}
.k1::after{background:var(--g0)}.k2::after{background:var(--ok)}.k3::after{background:var(--crit)}.k4::after{background:#5a8af5}

/* ── ALERTAS TOP 3 ──────────────────────────────────────────── */
.arow{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:14px}
.acard{
  background:var(--crit-bg);border:1px solid rgba(232,64,58,.22);
  border-radius:var(--r);padding:13px 14px;
  display:flex;align-items:center;gap:11px;
}
.aico{
  width:34px;height:34px;flex-shrink:0;border-radius:50%;
  background:rgba(232,64,58,.18);display:flex;align-items:center;justify-content:center;
  font-size:13px;color:var(--crit);
}
.adest{font-size:11px;font-weight:700;color:var(--w);line-height:1.3}
.ameta{font-family:var(--fm);font-size:8px;color:var(--crit);margin-top:2px}

/* ── SPARKLINE ──────────────────────────────────────────────── */
.swrap{padding:16px 18px}
.sbars{display:grid;grid-template-columns:repeat(7,1fr);gap:5px;height:75px;align-items:end}
.scol{display:flex;flex-direction:column;align-items:center;gap:3px;height:100%;justify-content:flex-end}
.sval{font-family:var(--fm);font-size:8px;color:var(--sl)}
.sval.act{color:var(--g0);font-weight:600}
.sbar{width:100%;border-radius:3px 3px 0 0;min-height:3px;transition:height .5s ease}
.sbar.p{background:#e2e5eb}.sbar.a{background:var(--g0);box-shadow:0 0 9px rgba(184,146,42,.25)}
.sbar.f{background:transparent;border:1px dashed #d0d4dc;border-bottom:none}
.snom{font-size:7px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:var(--sl)}
.snom.act{color:var(--g0)}
.sdate{font-family:var(--fm);font-size:7px;color:var(--sl);opacity:.45}

/* ── PROGRESO ───────────────────────────────────────────────── */
.pwrap{padding:16px 18px;border-top:1px solid var(--bd)}
.ptop{display:flex;justify-content:space-between;align-items:center;margin-bottom:9px}
.pttl{font-family:var(--fd);font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:2px;color:var(--slb)}
.ppct{font-family:var(--fd);font-size:28px;font-weight:900;color:var(--g0)}
.pbar{height:9px;background:var(--s3);border-radius:100px;overflow:hidden}
.pfill{height:100%;border-radius:100px;transition:width 1.1s cubic-bezier(.4,0,.2,1)}
.pfill.ok{background:linear-gradient(90deg,#0e7a3f,var(--ok))}
.pfill.md{background:linear-gradient(90deg,var(--gd),var(--g0))}
.pfill.cr{background:linear-gradient(90deg,#8a1a1a,var(--crit))}
.pfoot{display:flex;justify-content:space-between;margin-top:7px;font-family:var(--fm);font-size:8px;color:var(--sl)}

/* ── TABLA ──────────────────────────────────────────────────── */
.tscroll{overflow-x:auto}
table{width:100%;border-collapse:collapse}
thead th{
  padding:9px 14px;font-family:var(--fd);font-size:8px;font-weight:800;
  text-transform:uppercase;letter-spacing:1.5px;color:var(--sl);
  background:var(--s2);border-bottom:1px solid var(--bd);
  text-align:left;white-space:nowrap;
}
thead th.num{text-align:center}
thead th.th-b{color:rgba(200,160,68,.7)}
tbody tr{border-bottom:1px solid var(--bd);transition:background .1s;cursor:pointer}
tbody tr:hover{background:#fafbfd}
tbody tr:last-child{border-bottom:none}
td{padding:11px 14px;font-size:12px;vertical-align:middle}

.dc{display:flex;align-items:center;gap:9px}
.ddot{width:7px;height:7px;border-radius:50%;flex-shrink:0}
.dname{font-weight:700;font-size:12px;letter-spacing:.2px}
.dalert{
  display:inline-flex;align-items:center;gap:3px;margin-left:5px;
  font-size:7px;font-weight:800;color:var(--crit);background:var(--crit-bg);
  border:1px solid rgba(232,64,58,.25);padding:1px 5px;border-radius:3px;
  text-transform:uppercase;letter-spacing:.5px;
}
.nc{text-align:center}
.nteo{font-family:var(--fm);font-size:14px;font-weight:600;color:var(--g0)}
.nreal{font-family:var(--fm);font-size:14px;font-weight:600}
.c-ok{color:var(--ok)}.c-warn{color:var(--warn)}.c-crit{color:var(--crit)}
.ncomp{font-family:var(--fm);font-size:9px;display:flex;align-items:center;justify-content:center;gap:3px}
.badge{
  display:inline-flex;align-items:center;gap:3px;
  padding:2px 7px;border-radius:4px;
  font-family:var(--fm);font-size:8px;font-weight:600;
}
.bok{background:var(--ok-bg);color:var(--ok);border:1px solid rgba(31,202,106,.18)}
.bwr{background:var(--warn-bg);color:var(--warn);border:1px solid rgba(240,168,50,.18)}
.bcr{background:var(--crit-bg);color:var(--crit);border:1px solid rgba(232,64,58,.18)}
.bex{background:rgba(74,122,239,.1);color:#7aabef;border:1px solid rgba(74,122,239,.18)}
.mwrap{display:flex;align-items:center;gap:7px}
.mtrack{flex:1;height:5px;background:var(--s3);border-radius:100px;overflow:hidden;min-width:50px}
.mfill{height:100%;border-radius:100px}
.mfill.ok{background:var(--ok)}.mfill.wr{background:var(--warn)}.mfill.cr{background:var(--crit)}
.mpct{font-family:var(--fm);font-size:8px;font-weight:600;width:30px;text-align:right;flex-shrink:0}
.tr-total{background:var(--s2)!important}
.tr-total td{border-top:1px solid var(--bd-g);padding:13px 14px}
.tr-total .dname{font-family:var(--fd);font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:1px;color:var(--g0)}
.tr-total .nteo,.tr-total .nreal{font-size:16px}
.leg{
  display:flex;gap:14px;flex-wrap:wrap;align-items:center;
  padding:9px 14px;border-top:1px solid var(--bd);background:var(--s2);
}
.li{display:flex;align-items:center;gap:4px;font-size:7px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--sl)}
.ld{width:6px;height:6px;border-radius:50%}
.laut{margin-left:auto;font-family:var(--fm);font-size:7px;color:var(--sl);display:flex;align-items:center;gap:3px}
.empty{text-align:center;padding:56px 20px;color:var(--sl)}
.empty i{font-size:34px;opacity:.13;display:block;margin-bottom:12px}

.dot-0{background:#c8a044}.dot-1{background:#4a9ef5}.dot-2{background:#1fca6a}
.dot-3{background:#e8403a}.dot-4{background:#f0a832}.dot-5{background:#a47aea}
.dot-6{background:#20c5b0}.dot-7{background:#f065b0}.dot-8{background:#32b8e8}.dot-9{background:#f5d44a}

/* ── MODAL ──────────────────────────────────────────────────── */
.moverlay{
  display:none;position:fixed;inset:0;z-index:500;
  background:rgba(0,0,0,.45);backdrop-filter:blur(4px);
  align-items:center;justify-content:center;
}
.moverlay.open{display:flex}
.modal{
  background:var(--s1);border:1px solid var(--bd-g);border-radius:16px;
  width:min(460px,92vw);max-height:82vh;overflow:hidden;
  display:flex;flex-direction:column;
  box-shadow:0 24px 80px rgba(0,0,0,.15),0 0 0 1px rgba(184,146,42,.12);
  animation:mIn .22s ease;
}
@keyframes mIn{from{opacity:0;transform:scale(.94)}to{opacity:1;transform:scale(1)}}
.mhdr{
  display:flex;align-items:center;justify-content:space-between;
  padding:14px 18px;border-bottom:1px solid var(--bd);background:var(--s2);
}
.mtitle{font-family:var(--fd);font-size:14px;font-weight:800;text-transform:uppercase;letter-spacing:2px;color:var(--g0)}
.mclose{
  width:26px;height:26px;border-radius:50%;border:1px solid var(--bd);
  background:none;color:var(--sl);cursor:pointer;
  display:flex;align-items:center;justify-content:center;font-size:11px;
  transition:all .2s;
}
.mclose:hover{background:var(--s3);color:var(--w)}
.mbody{padding:18px;overflow-y:auto;flex:1}
.msrow{display:grid;grid-template-columns:repeat(3,1fr);gap:9px;margin-bottom:16px}
.ms{background:var(--s2);border:1px solid var(--bd);border-radius:8px;padding:11px;text-align:center}
.msv{font-family:var(--fd);font-size:24px;font-weight:900}.msv.g{color:var(--g0)}
.msl{font-size:7px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--sl);margin-top:3px}
.mdttl{font-size:8px;font-weight:800;text-transform:uppercase;letter-spacing:1.5px;color:var(--sl);margin-bottom:9px}
.mday{display:flex;align-items:center;gap:10px;padding:7px 0;border-bottom:1px solid var(--bd)}
.mday:last-child{border-bottom:none}
.mn{font-size:9px;font-weight:700;color:var(--slb);width:28px;flex-shrink:0}
.mf{font-family:var(--fm);font-size:8px;color:var(--sl);width:46px;flex-shrink:0}
.mbw{flex:1;height:5px;background:var(--s3);border-radius:100px;overflow:hidden}
.mbb{height:100%;border-radius:100px;background:var(--g0)}
.mv{font-family:var(--fm);font-size:9px;font-weight:600;color:var(--w);width:26px;text-align:right;flex-shrink:0}
.mv.z{color:var(--sl)}
.mfut{font-size:8px;color:var(--sl);opacity:.5;text-align:center;padding:6px 0;font-style:italic}

@media(max-width:700px){
  .krow{grid-template-columns:repeat(2,1fr)}
  .arow{grid-template-columns:1fr}
  .hdr{grid-template-columns:auto 1fr auto;gap:10px;padding:0 14px;height:64px}
  .hdr-mid h1{font-size:13px;letter-spacing:3px}
  .hdr-mid small{display:none}
  .hdr-logo{height:40px}
  .hdr-divider{display:none}
  .btn-reload-txt{display:none}
  .btn-reload{padding:7px 10px}
  td.tdbarra,th.thbarra{display:none}
  .page{padding:14px 10px 56px}
}
@media(max-width:420px){
  .krow{grid-template-columns:1fr 1fr}
  .kval{font-size:30px}
  .hdr-logo{height:34px}
}
</style>
</head>
<body>

<!-- HEADER -->
<header class="hdr">
  <a href="dashboard.php" class="btn-back"><i class="fas fa-arrow-left"></i> Volver</a>
  <div class="hdr-mid">
    <h1>Control de Pasajeros</h1>
    <small>KPI Semanal &nbsp;·&nbsp; Teórico vs Real por Bus</small>
  </div>
  <div class="hdr-right">
    <button class="btn-reload" onclick="location.reload()" title="Actualizar datos">
      <i class="fas fa-rotate-right"></i>
      <span class="btn-reload-txt">Actualizar</span>
    </button>
    <div class="hdr-divider"></div>
    <img src="assets/logo.png" alt="Hochschild Mining" class="hdr-logo">
  </div>
</header>

<div class="page">

  <!-- CONTROL BAR -->
  <div class="cbar">
    <span class="flbl">Movimiento</span>
    <form method="GET" style="display:contents">
      <select name="tipo" class="fsel" onchange="this.form.submit()">
        <option value="ambos"  <?=$tipo==='ambos' ?'selected':''?>>Subida + Bajada</option>
        <option value="bajada" <?=$tipo==='bajada'?'selected':''?>>Solo Bajada</option>
        <option value="subida" <?=$tipo==='subida'?'selected':''?>>Solo Subida</option>
      </select>
      <?php if($usar_b): ?>
        <input type="hidden" name="year_b" value="<?=$year_b?>">
        <input type="hidden" name="week_b" value="<?=$week_b?>">
      <?php endif; ?>
    </form>

    <div class="vdiv"></div>
    <span class="flbl">Semana</span>

    <div class="wnav">
      <a class="wbtn" href="?tipo=<?=h($tipo)?>&year_a=<?=$year_a_prev?>&week_a=<?=$week_a_prev?><?=$qs_b?>">
        <i class="fas fa-chevron-left"></i>
      </a>
      <span class="wtag">Sem.&nbsp;<?=$week_a?>&nbsp;/&nbsp;<?=$year_a?></span>
      <a class="wbtn <?=$es_actual?'dis':''?>" href="?tipo=<?=h($tipo)?>&year_a=<?=$year_a_next?>&week_a=<?=$week_a_next?><?=$qs_b?>">
        <i class="fas fa-chevron-right"></i>
      </a>
    </div>

    <div class="vdiv"></div>

    <button class="btn btn-gh" onclick="toggleRange()">
      <i class="fas fa-code-compare"></i> Comparar semanas
    </button>
    <button class="btn btn-ol" onclick="exportExcel()">
      <i class="fas fa-file-excel"></i> Excel
    </button>
  </div>

  <!-- RANGO COMPARATIVA (F) -->
  <div class="rbar <?=$usar_b?'open':''?>" id="rangeBar">
    <span class="rlab">A — <?=h($label_a)?></span>
    <span style="color:var(--sl);font-size:10px;font-weight:700">vs</span>
    <span class="rlab b">B</span>
    <span class="flbl">Sem B</span>
    <div class="wnav">
      <a class="wbtn" href="?tipo=<?=h($tipo)?>&year_a=<?=$year_a?>&week_a=<?=$week_a?>&year_b=<?=$year_b?>&week_b=<?=max(1,$week_b-1)?>">
        <i class="fas fa-chevron-left"></i>
      </a>
      <span class="wtag">Sem.&nbsp;<?=$week_b?>&nbsp;/&nbsp;<?=$year_b?></span>
      <a class="wbtn" href="?tipo=<?=h($tipo)?>&year_a=<?=$year_a?>&week_a=<?=$week_a?>&year_b=<?=$year_b?>&week_b=<?=min(53,$week_b+1)?>">
        <i class="fas fa-chevron-right"></i>
      </a>
    </div>
    <a href="?tipo=<?=h($tipo)?>&year_a=<?=$year_a?>&week_a=<?=$week_a?>" class="btn btn-ol">
      <i class="fas fa-xmark"></i> Quitar
    </a>
  </div>

  <!-- KPI CARDS -->
  <div class="krow">
    <div class="kcard k1">
      <div class="kico"><i class="fas fa-list-check"></i></div>
      <div class="kval"><?=number_format($sum_teo)?></div>
      <div class="kname">Teórico Total</div>
      <div class="ksub">lista_subida + lista_bajada · por bus</div>
    </div>
    <div class="kcard k2">
      <div class="kico"><i class="fas fa-qrcode"></i></div>
      <div class="kval"><?=number_format($sum_real_a)?></div>
      <div class="kname">Real Escaneado</div>
      <div class="ksub">registros · sem. <?=$week_a?></div>
    </div>
    <div class="kcard k3">
      <div class="kico"><i class="fas fa-user-clock"></i></div>
      <div class="kval"><?=number_format($faltantes)?></div>
      <div class="kname">Faltantes</div>
      <div class="ksub">sin escaneo</div>
    </div>
    <div class="kcard k4">
      <div class="kico"><i class="fas fa-chart-pie"></i></div>
      <div class="kval"><?=$pct_global?>%</div>
      <div class="kname">Avance Global</div>
      <div class="ksub">acumulado semanal</div>
    </div>
  </div>

  <!-- TOP 3 CRÍTICOS -->
  <?php if(!empty($criticos)): ?>
  <div class="arow">
    <?php foreach($criticos as $c): ?>
    <div class="acard">
      <div class="aico"><i class="fas fa-triangle-exclamation"></i></div>
      <div>
        <div class="adest"><i class="fas fa-bus" style="font-size:9px;opacity:.6;margin-right:4px"></i><?=h($c['bus'])?></div>
        <?php if($c['placa'] && $c['placa'] !== 'N/A'): ?>
        <div style="font-family:var(--fm);font-size:8px;color:var(--sl);margin-top:1px"><?=h($c['placa'])?></div>
        <?php endif; ?>
        <div class="ameta">
          <?=$c['pct_a']?>% avance
          <?php if($c['alerta']): ?>&nbsp;·&nbsp;<?=$c['dias_sin']?> días sin escaneo<?php endif; ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- SPARKLINE + PROGRESO -->
  <div class="card">
    <div class="chdr">
      <span class="ctitle"><i class="fas fa-chart-column"></i> Actividad diaria — <?=h($label_a)?></span>
    </div>
    <div class="swrap">
      <div class="sbars">
        <?php foreach($spark as $s):
          $hp = $max_spark>0 ? max(3,round(($s['n']/$max_spark)*60)) : 3;
          $cl = $s['hoy']?'a':($s['fut']?'f':'p');
        ?>
        <div class="scol">
          <span class="sval <?=$s['hoy']?'act':''?>">
            <?=$s['fut']?'—':($s['n']>0?number_format($s['n']):'-')?>
          </span>
          <div class="sbar <?=$cl?>" style="height:<?=$hp?>px"></div>
          <span class="snom <?=$s['hoy']?'act':''?>"><?=$s['nom']?></span>
          <span class="sdate"><?=date('d/m',strtotime($s['d']))?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="pwrap">
      <div class="ptop">
        <span class="pttl"><i class="fas fa-route" style="color:var(--g0);margin-right:6px"></i>Avance semanal acumulado</span>
        <span class="ppct"><?=$pct_global?>%</span>
      </div>
      <?php $bc='md'; if($pct_global>=90)$bc='ok'; elseif($pct_global<40)$bc='cr'; ?>
      <div class="pbar"><div class="pfill <?=$bc?>" style="width:<?=min($pct_global,100)?>%"></div></div>
      <div class="pfoot">
        <span><?=number_format($sum_real_a)?> escaneados de <?=number_format($sum_teo)?> programados</span>
        <span><?=number_format($faltantes)?> pendientes</span>
      </div>
    </div>
  </div>

  <!-- TABLA PRINCIPAL -->
  <div class="card">
    <div class="chdr">
      <span class="ctitle"><i class="fas fa-bus"></i> Desglose por Bus &mdash; A-Z</span>
      <span style="font-family:var(--fm);font-size:9px;color:var(--g0);background:var(--glow);border:1px solid var(--bd-g);padding:3px 9px;border-radius:4px">
        <?=h($label_a)?>
      </span>
    </div>

    <?php if(empty($filas)): ?>
    <div class="empty">
      <i class="fas fa-inbox"></i>
      <p style="font-weight:700">Sin datos para esta semana</p>
    </div>
    <?php else: ?>

    <div class="tscroll">
    <table id="tblData">
      <thead>
        <tr>
          <th>Bus</th>
          <th>Placa</th>
          <th class="num">Teórico</th>
          <th class="num">Real</th>
          <?php if($usar_b): ?><th class="num th-b">Real Sem. <?=$week_b?></th><?php endif; ?>
          <th class="num">Diferencia</th>
          <?php if($usar_b): ?><th class="num th-b">Tendencia</th><?php endif; ?>
          <th class="num thbarra">Avance</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach($filas as $idx=>$f):
        $rc = $f['pct_a']>=90?'c-ok':($f['pct_a']>=50?'c-warn':'c-crit');
        $mc = $f['pct_a']>=90?'ok':($f['pct_a']>=50?'wr':'cr');
        $pc = $f['pct_a']>=90?'var(--ok)':($f['pct_a']>=50?'var(--warn)':'var(--crit)');

        $dv=$f['ra']-$f['t'];
        if($dv===0)    {$dc='bex';$di='fa-check';    $dt='Exacto';}
        elseif($dv>0)  {$dc='bok';$di='fa-arrow-up'; $dt="+$dv";}
        elseif($dv>=-5){$dc='bwr';$di='fa-arrow-down';$dt="$dv";}
        else           {$dc='bcr';$di='fa-arrow-down';$dt="$dv";}

        $delta = $usar_b ? $f['pct_a'] - $f['pct_b'] : 0;

        $mdata = json_encode([
          'bus'=>$f['bus'],'placa'=>$f['placa'],
          'teo'=>$f['t'],'real'=>$f['ra'],'pct'=>$f['pct_a'],'det'=>$f['det']
        ]);
      ?>
      <tr onclick='openModal(<?=htmlspecialchars($mdata,ENT_QUOTES,'UTF-8')?>)'
          style="animation:up .28s ease <?=$idx*.02?>s both">
        <td>
          <div class="dc">
            <span class="ddot dot-<?=$idx%10?>"></span>
            <span class="dname"><i class="fas fa-bus" style="font-size:9px;opacity:.45;margin-right:3px"></i><?=h($f['bus'])?></span>
            <?php if($f['alerta']): ?>
            <span class="dalert"><i class="fas fa-triangle-exclamation"></i> <?=$f['dias_sin']?>d</span>
            <?php endif; ?>
          </div>
        </td>
        <td>
          <?php if($f['placa'] && $f['placa'] !== 'N/A'): ?>
          <span style="font-family:var(--fm);font-size:10px;font-weight:600;color:var(--slb);
                       background:#f0f2f5;border:1px solid var(--bd);padding:2px 7px;border-radius:4px;
                       letter-spacing:.5px">
            <?=h($f['placa'])?>
          </span>
          <?php else: ?>
          <span style="font-family:var(--fm);font-size:9px;color:var(--sl);opacity:.4">—</span>
          <?php endif; ?>
        </td>
        <td class="nc"><span class="nteo"><?=number_format($f['t'])?></span></td>
        <td class="nc"><span class="nreal <?=$rc?>"><?=number_format($f['ra'])?></span></td>
        <?php if($usar_b): ?>
        <td class="nc"><span class="nreal" style="opacity:.6"><?=number_format($f['rb'])?></span></td>
        <?php endif; ?>
        <td class="nc">
          <span class="badge <?=$dc?>"><i class="fas <?=$di?>"></i> <?=$dt?></span>
        </td>
        <?php if($usar_b): ?>
        <td class="nc">
          <div class="ncomp" style="color:<?=$delta>0?'var(--ok)':($delta<0?'var(--crit)':'var(--sl)')?>">
            <i class="fas <?=$delta>0?'fa-arrow-trend-up':($delta<0?'fa-arrow-trend-down':'fa-minus')?>"></i>
            <?=$delta>0?"+$delta":$delta?>%
          </div>
        </td>
        <?php endif; ?>
        <td class="tdbarra thbarra">
          <div class="mwrap">
            <div class="mtrack"><div class="mfill <?=$mc?>" style="width:<?=min($f['pct_a'],100)?>%"></div></div>
            <span class="mpct" style="color:<?=$pc?>"><?=$f['pct_a']?>%</span>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr class="tr-total">
          <td><div class="dc"><i class="fas fa-sigma" style="color:var(--g0)"></i><span class="dname">TOTAL GENERAL</span></div></td>
          <td></td>
          <td class="nc"><span class="nteo"><?=number_format($sum_teo)?></span></td>
          <td class="nc">
            <?php $ct=$sum_real_a>=$sum_teo?'c-ok':($sum_real_a>=$sum_teo*.5?'c-warn':'c-crit')?>
            <span class="nreal <?=$ct?>"><?=number_format($sum_real_a)?></span>
          </td>
          <?php if($usar_b): ?>
          <td class="nc"><span class="nreal" style="opacity:.6"><?=number_format(array_sum(array_column($filas,'rb')))?></span></td>
          <?php endif; ?>
          <td class="nc">
            <?php $tot=$sum_real_a-$sum_teo?>
            <span class="badge <?=$tot>=0?'bok':'bcr'?>">
              <i class="fas <?=$tot>=0?'fa-check':'fa-arrow-down'?>"></i>
              <?=$tot>=0?"+$tot":$tot?>
            </span>
          </td>
          <?php if($usar_b): ?><td></td><?php endif; ?>
          <td class="tdbarra thbarra">
            <div class="mwrap">
              <div class="mtrack"><div class="mfill" style="width:<?=min($pct_global,100)?>%;background:var(--g0)"></div></div>
              <span class="mpct" style="color:var(--g0)"><?=$pct_global?>%</span>
            </div>
          </td>
        </tr>
      </tfoot>
    </table>
    </div>

    <div class="leg">
      <div class="li"><span class="ld" style="background:var(--ok)"></span>≥90% Completado</div>
      <div class="li"><span class="ld" style="background:var(--warn)"></span>50–89% Progreso</div>
      <div class="li"><span class="ld" style="background:var(--crit)"></span>&lt;50% Crítico</div>
      <div class="li"><span class="ld" style="background:var(--crit);opacity:.6"></span>⚠ ≥3 días sin escaneo</div>
      <div class="laut"><i class="fas fa-rotate" style="color:var(--g0)"></i>Auto-refresca 30s</div>
    </div>

    <?php endif; ?>
  </div>

</div><!-- /page -->

<!-- MODAL (D) -->
<div class="moverlay" id="mOverlay" onclick="if(event.target===this)closeM()">
  <div class="modal">
    <div class="mhdr">
      <div>
        <span class="mtitle" id="mTit">—</span>
        <div id="mPlaca" style="font-family:var(--fm);font-size:9px;color:var(--sl);margin-top:2px"></div>
      </div>
      <button class="mclose" onclick="closeM()"><i class="fas fa-xmark"></i></button>
    </div>
    <div class="mbody">
      <div class="msrow">
        <div class="ms"><div class="msv g" id="mTeo">—</div><div class="msl">Teórico</div></div>
        <div class="ms"><div class="msv" id="mReal">—</div><div class="msl">Real</div></div>
        <div class="ms"><div class="msv" id="mPct">—</div><div class="msl">Avance</div></div>
      </div>
      <div class="mdttl">Detalle día a día</div>
      <div id="mDays"></div>
    </div>
  </div>
</div>

<script>
/* RELOAD BUTTON */
document.querySelector('.btn-reload').addEventListener('click', function(){
  const icon = this.querySelector('i');
  icon.style.transition = 'transform .4s ease';
  icon.style.transform = 'rotate(360deg)';
  setTimeout(()=>location.reload(), 350);
});

/* TOGGLE RANGO */
function toggleRange(){document.getElementById('rangeBar').classList.toggle('open')}

/* MODAL */
const DIAS=['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'];
function openModal(d){
  document.getElementById('mTit').textContent = d.bus;
  const pEl = document.getElementById('mPlaca');
  pEl.textContent = (d.placa && d.placa !== 'N/A') ? '🚌 ' + d.placa : '';
  document.getElementById('mTeo').textContent=d.teo.toLocaleString();
  const col=d.pct>=90?'#16a34a':d.pct>=50?'#d97706':'#dc2626';
  const re=document.getElementById('mReal');re.textContent=d.real.toLocaleString();re.style.color=col;
  const pe=document.getElementById('mPct');pe.textContent=d.pct+'%';pe.style.color=col;
  const mx=Math.max(...d.det.map(x=>x.n),1);
  let h='';
  d.det.forEach((x,i)=>{
    if(x.fut){h+=`<div class="mfut">${DIAS[i]} — día futuro</div>`;return;}
    const p=Math.round((x.n/mx)*100);
    h+=`<div class="mday">
      <span class="mn">${DIAS[i]}</span>
      <span class="mf">${x.fecha}</span>
      <div class="mbw"><div class="mbb" style="width:${p}%"></div></div>
      <span class="mv${x.n===0?' z':''}">${x.n>0?x.n:'—'}</span>
    </div>`;
  });
  document.getElementById('mDays').innerHTML=h;
  document.getElementById('mOverlay').classList.add('open');
}
function closeM(){document.getElementById('mOverlay').classList.remove('open')}
document.addEventListener('keydown',e=>{if(e.key==='Escape')closeM()});

/* EXPORT EXCEL */
function exportExcel(){
  const t=document.getElementById('tblData');
  if(!t)return;
  const wb=XLSX.utils.book_new();
  const ws=XLSX.utils.table_to_sheet(t);
  ws['!cols']=[{wch:30},{wch:12},{wch:12},{wch:14},{wch:14},{wch:14}];
  XLSX.utils.book_append_sheet(wb,ws,'KPI Destinos');
  XLSX.writeFile(wb,`Hochschild_KPI_Sem<?=$week_a?>_<?=$year_a?>.xlsx`);
}
</script>
</body>
</html>
