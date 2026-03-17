<?php
/**
 * kpis_pro.php  —  Hochschild Mining
 * KPI Semanal: Teórico (lista_subida + lista_bajada) vs Real (registros)
 * v2 · Filtro de buses excluidos · KPIs con comparativa vs semana anterior
 */

session_start();
if (!isset($_SESSION['usuario'])) { header("Location: index.php"); exit(); }
require __DIR__ . "/config.php";
function h($s){ return htmlspecialchars($s, ENT_QUOTES,'UTF-8'); }

/* ── AJAX endpoint: devuelve asientos de un bus ── */
if (isset($_GET['ajax']) && $_GET['ajax'] === 'asientos') {
    header('Content-Type: application/json');
    $bus_req  = strtoupper(trim($_GET['bus'] ?? ''));
    $year_req = max(2020, min(2030, (int)($_GET['year_a'] ?? date('Y'))));
    $week_req = max(1,    min(53,   (int)($_GET['week_a'] ?? date('W'))));
    if (!$bus_req) { echo json_encode([]); exit(); }

function get_asientos_ajax($mysqli, string $bus, string $ini, string $fin): array {
    /* Paso 1: Obtener Teórico (Asignados) y Normalizar DNIs */
    $stmt = $mysqli->prepare(
        "SELECT l.asiento, TRIM(l.dni) AS dni, 'subida' AS tipo
         FROM lista_subida l WHERE UPPER(TRIM(l.bus)) = ? AND l.asiento IS NOT NULL AND l.asiento != ''
         UNION ALL
         SELECT l.asiento, TRIM(l.dni) AS dni, 'bajada' AS tipo
         FROM lista_bajada l WHERE UPPER(TRIM(l.bus)) = ? AND l.asiento IS NOT NULL AND l.asiento != ''"
    );
    $stmt->bind_param('ss', $bus, $bus);
    $stmt->execute();
    $res = $stmt->get_result();

    $lista = []; 
    while ($r = $res->fetch_assoc()) {
        $a = (int)$r['asiento'];
        if ($a < 1 || $a > 30) continue;
        // SOLUCIÓN CASO 2: Borramos ceros a la izquierda para evitar duplicados falsos
        $dni_norm = ltrim(trim($r['dni']), '0'); 
        $lista[] = ['asiento' => $a, 'dni' => $dni_norm, 'tipo' => $r['tipo']];
    }
    $stmt->close();

    /* Paso 2: Obtener Escaneos Reales (TODOS, para detectar Extras) */
    $stmt3 = $mysqli->prepare(
        "SELECT TRIM(r.dni) AS dni, r.evento, DATE(r.fecha) AS dia
         FROM registros r
         WHERE UPPER(TRIM(r.bus)) = ? AND DATE(r.fecha) BETWEEN ? AND ?
           AND r.evento IN ('SUBIDA PERMITIDA','BAJADA PERMITIDA')
         ORDER BY r.fecha DESC"
    );
    $stmt3->bind_param('sss', $bus, $ini, $fin);
    $stmt3->execute();
    $res3 = $stmt3->get_result();

    $escaneados = [];
    while ($r3 = $res3->fetch_assoc()) {
        $dni_norm = ltrim(trim($r3['dni']), '0');
        if (!isset($escaneados[$dni_norm])) {
            $escaneados[$dni_norm] = [
                'evento' => $r3['evento'],
                'dia'    => $r3['dia']
            ];
        }
    }
    $stmt3->close();

    /* Paso 3: Obtener Nombres uniendo DNIs teóricos y reales */
    $dnis_teoricos = array_column($lista, 'dni');
    $dnis_reales   = array_keys($escaneados);
    $todos_dnis    = array_unique(array_merge($dnis_teoricos, $dnis_reales));
    $todos_dnis    = array_filter($todos_dnis);

    $nombres_map = [];
    if (!empty($todos_dnis)) {
        // Generamos versiones con y sin cero inicial para asegurar que la BD lo encuentre
        $dnis_a_buscar = [];
        foreach ($todos_dnis as $d) {
            $dnis_a_buscar[] = $d;
            if (strlen($d) < 8) $dnis_a_buscar[] = str_pad($d, 8, '0', STR_PAD_LEFT);
        }
        $dnis_a_buscar = array_unique($dnis_a_buscar);

        $placeholders = implode(',', array_fill(0, count($dnis_a_buscar), '?'));
        $types = str_repeat('s', count($dnis_a_buscar));
        $stmt2 = $mysqli->prepare(
            "SELECT TRIM(dni) AS dni, CONCAT(TRIM(COALESCE(nombres,'')), ' ', TRIM(COALESCE(apellidos,''))) AS nombre
             FROM personal WHERE TRIM(dni) IN ($placeholders)"
        );
        if ($stmt2) {
            $stmt2->bind_param($types, ...$dnis_a_buscar);
            $stmt2->execute();
            $res2 = $stmt2->get_result();
            while ($r2 = $res2->fetch_assoc()) {
                $dni_norm = ltrim(trim($r2['dni']), '0');
                $nombres_map[$dni_norm] = trim($r2['nombre']);
            }
            $stmt2->close();
        }
    }

    /* Paso 4: Armar el resultado separando asientos y extras */
    $sub = []; $baj = [];
    $dnis_en_asiento_sub = [];
    $dnis_en_asiento_baj = [];

    // 4.1 Asignados a asientos
    foreach ($lista as $info) {
        $asiento = $info['asiento'];
        $dni     = $info['dni'];
        $tipo    = $info['tipo'];
        $nombre  = $nombres_map[$dni] ?? $dni;
        $scan    = $escaneados[$dni] ?? null;

        $entry = [
            'nombre'    => $nombre ?: 'Sin nombre',
            'dni'       => $dni,
            'escaneado' => $scan !== null,
            'dia'       => $scan ? $scan['dia'] : '',
        ];

        if ($tipo === 'subida') {
            $sub[$asiento] = $entry;
            $dnis_en_asiento_sub[] = $dni;
        } else {
            $baj[$asiento] = $entry;
            $dnis_en_asiento_baj[] = $dni;
        }
    }

    // 4.2 SOLUCIÓN CASO 1: Detectar Extras (escaneados que no tenían asiento programado)
    $extras_sub = [];
    $extras_baj = [];
    foreach ($escaneados as $dni_scan => $data_scan) {
        $nombre = $nombres_map[$dni_scan] ?? $dni_scan;
        $entry_extra = [
            'nombre' => $nombre ?: 'Sin nombre',
            'dni'    => $dni_scan,
            'dia'    => $data_scan['dia']
        ];

        if ($data_scan['evento'] === 'SUBIDA PERMITIDA') {
            if (!in_array($dni_scan, $dnis_en_asiento_sub)) {
                $extras_sub[] = $entry_extra;
            }
        } else {
            if (!in_array($dni_scan, $dnis_en_asiento_baj)) {
                $extras_baj[] = $entry_extra;
            }
        }
    }

    return [
        'subida'     => $sub, 
        'bajada'     => $baj, 
        'extras_sub' => $extras_sub, 
        'extras_baj' => $extras_baj
    ];
}

    function sem_lunes_ajax(int $y, int $w): string {
        $dt = new DateTime(); $dt->setISODate($y,$w,1); return $dt->format('Y-m-d');
    }
    $lun_req = sem_lunes_ajax($year_req, $week_req);
    $dom_req = date('Y-m-d', strtotime($lun_req) + 6*86400);
    $asientos = get_asientos_ajax($mysqli, $bus_req, $lun_req, $dom_req);
    echo json_encode($asientos);
    exit();
}

/* ═══════════════════════════════════════════════════════
   BUSES EXCLUIDOS — gestionados vía $_SESSION
   El admin puede agregar/quitar desde el panel en la UI.
   Se inicializa con los valores por defecto si no existe.
═══════════════════════════════════════════════════════ */
if (!isset($_SESSION['buses_excluidos'])) {
    $_SESSION['buses_excluidos'] = [];
}

/* Acción: agregar bus excluido */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    if ($_POST['accion'] === 'excluir_bus') {
        /* Soporta tanto nombre único como array de nombres */
        $nombres = (array)($_POST['bus_nombres'] ?? ($_POST['bus_nombre'] ?? []));
        foreach ($nombres as $nb) {
            // SOLUCIÓN: Usar mb_strtoupper en lugar de strtoupper para soportar tildes
            $nuevo = mb_strtoupper(trim($nb), 'UTF-8');
            if ($nuevo && !in_array($nuevo, $_SESSION['buses_excluidos'], true))
                $_SESSION['buses_excluidos'][] = $nuevo;
        }
    }
    if ($_POST['accion'] === 'incluir_bus' && isset($_POST['bus_idx'])) {
        $idx = (int)$_POST['bus_idx'];
        if (isset($_SESSION['buses_excluidos'][$idx]))
            array_splice($_SESSION['buses_excluidos'], $idx, 1);
    }
    if ($_POST['accion'] === 'reset_excluidos') {
        $_SESSION['buses_excluidos'] = [];
    }
    /* Redirigir para evitar reenvío del form */
    $qs_redirect = http_build_query(array_filter([
        'tipo'   => $_GET['tipo']   ?? null,
        'year_a' => $_GET['year_a'] ?? null,
        'week_a' => $_GET['week_a'] ?? null,
        'year_b' => $_GET['year_b'] ?? null,
        'week_b' => $_GET['week_b'] ?? null,
    ]));
    header("Location: ?" . $qs_redirect);
    exit();
}

$BUSES_EXCLUIDOS = $_SESSION['buses_excluidos'];

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

/* Semana anterior (para comparativa KPI) — siempre activa */
$week_prev  = $week_a > 1  ? $week_a - 1 : 52;
$year_prev  = $week_a > 1  ? $year_a     : $year_a - 1;
$lun_prev   = semana_lunes($year_prev, $week_prev);
$dom_prev   = semana_domingo($lun_prev);
$label_prev = semana_label($year_prev, $week_prev, $lun_prev);

/* Semana B manual (comparativa avanzada) */
$usar_b  = isset($_GET['year_b'], $_GET['week_b']);
$year_b  = (int)($_GET['year_b']  ?? $year_a);
$week_b  = (int)($_GET['week_b']  ?? $week_prev);
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
         GROUP BY DATE(r.fecha), UPPER(TRIM(r.bus)), UPPER(TRIM(r.placa))  "
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
   HELPER: ¿este bus está excluido?
═══════════════════════════════════════════════════════ */
function bus_excluido(string $bus, array $excluidos): bool {
    // Usamos mb_strtoupper para convertir correctamente la Ó y la Ñ
    $b = mb_strtoupper(trim($bus), 'UTF-8');
    
    /* Normalizar variantes con/sin tilde para comparación robusta */
    $norm = strtr($b, [
        'Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ü'=>'U','Ñ'=>'N',
        'À'=>'A','È'=>'E','Ì'=>'I','Ò'=>'O','Ù'=>'U',
    ]);
    
    foreach ($excluidos as $ex) {
        $ex_upper = mb_strtoupper(trim($ex), 'UTF-8');
        $ex_norm = strtr($ex_upper, [
            'Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ü'=>'U','Ñ'=>'N',
            'À'=>'A','È'=>'E','Ì'=>'I','Ò'=>'O','Ù'=>'U',
        ]);
        if ($b === $ex_upper || $norm === $ex_norm) return true;
    }
    return false;
}

/* Obtener TODOS los buses disponibles para el dropdown (sin filtro).
   Busca en lista_subida, lista_bajada Y registros para no perder
   buses que solo aparezcan en registros (ej: BUS SUPERVISIÓN). */
function get_todos_buses($mysqli): array {
    $out = [];
    $fuentes = [
        "SELECT DISTINCT TRIM(COALESCE(bus,'')) AS bus FROM lista_subida  WHERE bus IS NOT NULL AND bus != '' AND bus != 'N/A'",
        "SELECT DISTINCT TRIM(COALESCE(bus,'')) AS bus FROM lista_bajada  WHERE bus IS NOT NULL AND bus != '' AND bus != 'N/A'",
        "SELECT DISTINCT TRIM(COALESCE(bus,'')) AS bus FROM registros     WHERE bus IS NOT NULL AND bus != '' AND bus != 'N/A' LIMIT 500",
    ];
    foreach ($fuentes as $sql) {
        $res = $mysqli->query($sql);
        if (!$res) continue;
        while ($r = $res->fetch_assoc()) {
            $b = trim($r['bus']);
            if ($b === '') continue;
            /* Guardar con clave en UPPER para deduplicar, valor = nombre original */
            $key = strtoupper($b);
            if (!isset($out[$key])) $out[$key] = $b;
        }
    }
    /* Ordenar por nombre original */
    $buses = array_values($out);
    usort($buses, fn($a,$b) => strcmp(strtoupper($a), strtoupper($b)));
    return $buses;
}

/* ═══════════════════════════════════════════════════════
   CACHÉ DE SESIÓN — TTL 5 minutos por semana + tipo
   Evita re-ejecutar las 4 queries pesadas en cada carga.
   Se invalida automáticamente al cambiar semana o tipo.
═══════════════════════════════════════════════════════ */
$cache_ttl  = 300; // segundos (5 min)
$cache_key  = "kpi_{$year_a}_{$week_a}_{$tipo}";

function cache_get(string $key, int $ttl): mixed {
    if (!isset($_SESSION['_cache'][$key])) return null;
    $entry = $_SESSION['_cache'][$key];
    if ((time() - $entry['ts']) > $ttl) {
        unset($_SESSION['_cache'][$key]);
        return null;
    }
    return $entry['data'];
}
function cache_set(string $key, mixed $data): void {
    $_SESSION['_cache'][$key] = ['ts' => time(), 'data' => $data];
}
function cache_invalidate_old(int $keep = 10): void {
    if (!isset($_SESSION['_cache'])) return;
    $keys = array_keys($_SESSION['_cache']);
    if (count($keys) > $keep) {
        uasort($_SESSION['_cache'], fn($a,$b) => $a['ts'] <=> $b['ts']);
        array_splice($_SESSION['_cache'], 0, count($keys) - $keep);
    }
}
cache_invalidate_old();

/* Forzar refresco si viene de POST (exclusiones) o ?flush=1 (botón Actualizar) */
if (isset($_GET['flush'])) {
    unset($_SESSION['_cache']);
    /* Redirigir sin el parámetro flush para URL limpia */
    $qs_clean = http_build_query(array_filter(array_diff_key($_GET, ['flush'=>1])));
    header("Location: ?" . $qs_clean);
    exit();
}
$force_refresh = ($_SERVER['REQUEST_METHOD'] === 'POST');

$cached = $force_refresh ? null : cache_get($cache_key, $cache_ttl);

if ($cached !== null) {
    /* ── Servir desde caché ── */
    $todos_buses   = $cached['todos_buses'];
    $teo_sub       = $cached['teo_sub'];
    $teo_baj       = $cached['teo_baj'];
    $real_a_raw    = $cached['real_a_raw'];
    $real_prev_raw = $cached['real_prev_raw'];
    $dias_data_raw = $cached['dias_data_raw'];
    $dias_prev_raw = $cached['dias_prev_raw'];
    $real_sub_raw  = $cached['real_sub_raw']  ?? [];
    $real_baj_raw  = $cached['real_baj_raw']  ?? [];
    $dias_sub_raw  = $cached['dias_sub_raw']  ?? [];
    $dias_baj_raw  = $cached['dias_baj_raw']  ?? [];
    $cache_hit     = true;
} else {
    /* ── Consultar BD ── */
    $todos_buses      = get_todos_buses($mysqli);
    $teo_sub          = get_teorico_bus($mysqli, 'lista_subida');
    $teo_baj          = get_teorico_bus($mysqli, 'lista_bajada');
    $real_a_raw       = get_real_bus($mysqli, $lun_a,    $dom_a,    $tipo);
    $real_prev_raw    = get_real_bus($mysqli, $lun_prev, $dom_prev, $tipo);
    $dias_data_raw    = get_dias_bus($mysqli, $lun_a,    $dom_a,    $tipo);
    $dias_prev_raw    = get_dias_bus($mysqli, $lun_prev, $dom_prev, $tipo);
    /* Siempre cachear sub y baj por separado para modo ambos */
    $real_sub_raw     = get_real_bus($mysqli, $lun_a, $dom_a, 'subida');
    $real_baj_raw     = get_real_bus($mysqli, $lun_a, $dom_a, 'bajada');
    $dias_sub_raw     = get_dias_bus($mysqli, $lun_a, $dom_a, 'subida');
    $dias_baj_raw     = get_dias_bus($mysqli, $lun_a, $dom_a, 'bajada');
    cache_set($cache_key, compact(
        'todos_buses','teo_sub','teo_baj',
        'real_a_raw','real_prev_raw','dias_data_raw','dias_prev_raw',
        'real_sub_raw','real_baj_raw','dias_sub_raw','dias_baj_raw'
    ));
    $cache_hit = false;
}

/* ═══════════════════════════════════════════════════════
   DATOS — aplicar filtro de excluidos sobre caché
   Teórico subida y bajada siempre independientes
═══════════════════════════════════════════════════════ */

/* Helper para filtrar un array raw por excluidos */
function filtrar_excluidos(array $raw, array $excl): array {
    return array_filter($raw, fn($v,$k) =>
        !bus_excluido(explode('||',$k)[0], $excl),
        ARRAY_FILTER_USE_BOTH
    );
}
function filtrar_dias_excluidos(array $raw, array $excl): array {
    $out = [];
    foreach ($raw as $dia => $buses)
        foreach ($buses as $k => $n)
            if (!bus_excluido(explode('||',$k)[0], $excl))
                $out[$dia][$k] = $n;
    return $out;
}

/* Teórico por separado (siempre disponibles) */
$teorico_sub = [];
foreach ($teo_sub as $key => $info) {
    if (bus_excluido($info['bus'], $BUSES_EXCLUIDOS)) continue;
    if (!isset($teorico_sub[$key]))
        $teorico_sub[$key] = ['bus'=>$info['bus'],'placa'=>$info['placa'],'n'=>0];
    $teorico_sub[$key]['n'] += $info['n'];
}
$teorico_baj = [];
foreach ($teo_baj as $key => $info) {
    if (bus_excluido($info['bus'], $BUSES_EXCLUIDOS)) continue;
    if (!isset($teorico_baj[$key]))
        $teorico_baj[$key] = ['bus'=>$info['bus'],'placa'=>$info['placa'],'n'=>0];
    $teorico_baj[$key]['n'] += $info['n'];
}

/* Teórico combinado — iterar sub y baj por separado para NO perder claves duplicadas */
$teorico = [];
foreach ($teorico_sub as $key => $info) {
    if (!isset($teorico[$key]))
        $teorico[$key] = ['bus'=>$info['bus'],'placa'=>$info['placa'],'n'=>0];
    $teorico[$key]['n'] += $info['n'];
}
foreach ($teorico_baj as $key => $info) {
    if (!isset($teorico[$key]))
        $teorico[$key] = ['bus'=>$info['bus'],'placa'=>$info['placa'],'n'=>0];
    $teorico[$key]['n'] += $info['n'];
}
/* Para modos individuales, usar solo la lista correspondiente */
if ($tipo === 'subida') $teorico = $teorico_sub;
if ($tipo === 'bajada') $teorico = $teorico_baj;

/* En modo ambos también necesitamos todas_keys de sub + baj por separado */
$todas_keys_sub = array_keys($teorico_sub);
$todas_keys_baj = array_keys($teorico_baj);

/* Real filtrado */
$real_a      = filtrar_excluidos($real_a_raw,    $BUSES_EXCLUIDOS);
$real_prev   = filtrar_excluidos($real_prev_raw,  $BUSES_EXCLUIDOS);
$real_sub    = filtrar_excluidos($real_sub_raw,   $BUSES_EXCLUIDOS);
$real_baj    = filtrar_excluidos($real_baj_raw,   $BUSES_EXCLUIDOS);
$real_b      = $usar_b ? filtrar_excluidos(get_real_bus($mysqli, $lun_b, $dom_b, $tipo), $BUSES_EXCLUIDOS) : [];

/* Días filtrados */
$dias_data      = filtrar_dias_excluidos($dias_data_raw, $BUSES_EXCLUIDOS);
$dias_data_prev = filtrar_dias_excluidos($dias_prev_raw,  $BUSES_EXCLUIDOS);
$dias_sub       = filtrar_dias_excluidos($dias_sub_raw,   $BUSES_EXCLUIDOS);
$dias_baj       = filtrar_dias_excluidos($dias_baj_raw,   $BUSES_EXCLUIDOS);

/* Días pasados de la semana A */
$dias_lista = [];
for ($i = 0; $i < 7; $i++) {
    $d = date('Y-m-d', strtotime($lun_a) + $i*86400);
    if ($d <= date('Y-m-d')) $dias_lista[] = $d;
}
$dias_pasados = count($dias_lista);

/* ═══════════════════════════════════════════════════════
   CONSTRUIR FILAS — con filtro de excluidos
═══════════════════════════════════════════════════════ */
$todas_keys = array_unique(array_merge(array_keys($teorico), array_keys($real_a)));

$bus_info = [];
foreach ($teorico as $key => $info)
    $bus_info[$key] = ['bus'=>$info['bus'], 'placa'=>$info['placa']];
foreach ($real_a as $key => $n) {
    if (!isset($bus_info[$key])) {
        [$b, $p] = explode('||', $key, 2);
        $bus_info[$key] = ['bus'=>$b, 'placa'=>$p];
    }
}

/* Filtrar excluidos de todas_keys */
$todas_keys = array_filter($todas_keys, fn($k) =>
    !bus_excluido($bus_info[$k]['bus'] ?? '', $BUSES_EXCLUIDOS)
);

usort($todas_keys, fn($a,$b) => strcmp($bus_info[$a]['bus'], $bus_info[$b]['bus']));

$filas = []; $sum_teo = 0; $sum_real_a = 0;
foreach ($todas_keys as $key) {
    $t     = $teorico[$key]['n'] ?? 0;
    $ra    = $real_a[$key]       ?? 0;
    $rb    = $real_b[$key]       ?? 0;
    $bus   = $bus_info[$key]['bus'];
    $placa = $bus_info[$key]['placa'];

    $pct_a = $t > 0 ? round(($ra/$t)*100) : ($ra>0?100:0);
    $pct_b = $t > 0 ? round(($rb/$t)*100) : ($rb>0?100:0);

    $dias_con = 0;
    foreach ($dias_lista as $d)
        if (!empty($dias_data[$d][$key]) && $dias_data[$d][$key] > 0) $dias_con++;
    $dias_sin = max(0, $dias_pasados - $dias_con);
    $alerta   = ($dias_sin >= 3 && $dias_pasados >= 3 && $t > 0);

    $det = [];
    for ($i = 0; $i < 7; $i++) {
        $d = date('Y-m-d', strtotime($lun_a) + $i*86400);
        $det[] = ['fecha'=>$d, 'n'=>$dias_data[$d][$key] ?? 0, 'fut'=>$d>date('Y-m-d')];
    }

    /* Sub/Baj independientes para modo ambos */
    $ts  = $teorico_sub[$key]['n'] ?? 0;
    $tb  = $teorico_baj[$key]['n'] ?? 0;
    $rs  = $real_sub[$key] ?? 0;
    $rb2 = $real_baj[$key] ?? 0;
    $pct_sub = $ts > 0 ? round(($rs/$ts)*100) : ($rs>0?100:0);
    $pct_baj = $tb > 0 ? round(($rb2/$tb)*100) : ($rb2>0?100:0);
    $tt  = $ts + $tb;
    $rt  = $rs + $rb2;
    $pct_tot = $tt > 0 ? round(($rt/$tt)*100) : 0;

    $filas[] = compact('key','bus','placa','t','ra','rb','pct_a','pct_b',
                       'alerta','dias_sin','det',
                       'ts','tb','rs','rb2','pct_sub','pct_baj','tt','rt','pct_tot');
    $sum_teo    += $t;
    $sum_real_a += $ra;
}
/* En modo ambos: $sum_teo es sub+baj (no confundir con $teorico combinado) */
$sum_teo_sub   = array_sum(array_column($filas,'ts'));
$sum_teo_baj   = array_sum(array_column($filas,'tb'));
$sum_real_sub  = array_sum(array_column($filas,'rs'));
$sum_real_baj  = array_sum(array_column($filas,'rb2'));

if ($tipo === 'ambos') {
    /* Avance combinado = (real_sub + real_baj) / (teo_sub + teo_baj) */
    $sum_teo_combinado = $sum_teo_sub + $sum_teo_baj;
    $sum_real_combinado = $sum_real_sub + $sum_real_baj;
    $pct_global = $sum_teo_combinado > 0 ? round(($sum_real_combinado/$sum_teo_combinado)*100) : 0;
    $faltantes  = max(0, $sum_teo_combinado - $sum_real_combinado);
    $sum_teo    = $sum_teo_combinado;
    $sum_real_a = $sum_real_combinado;
} else {
    $pct_global = $sum_teo > 0 ? round(($sum_real_a/$sum_teo)*100) : 0;
    $faltantes  = max(0, $sum_teo - $sum_real_a);
}

/* ═══════════════════════════════════════════════════════
   TOTALES SUBIDA / BAJADA INDEPENDIENTES (modo ambos)
═══════════════════════════════════════════════════════ */
$pct_sub_global = $sum_teo_sub > 0 ? round(($sum_real_sub/$sum_teo_sub)*100) : 0;
$pct_baj_global = $sum_teo_baj > 0 ? round(($sum_real_baj/$sum_teo_baj)*100) : 0;
$falt_sub       = max(0, $sum_teo_sub - $sum_real_sub);
$falt_baj       = max(0, $sum_teo_baj - $sum_real_baj);
$buses_ok_sub   = count(array_filter($filas, fn($f)=>$f['ts']>0&&$f['pct_sub']>=85));
$buses_ok_baj   = count(array_filter($filas, fn($f)=>$f['tb']>0&&$f['pct_baj']>=85));
$brecha_pp      = $pct_sub_global - $pct_baj_global;

/* ═══════════════════════════════════════════════════════
   KPI COMPARATIVA — semana anterior (filtrada)
═══════════════════════════════════════════════════════ */
$sum_real_prev = 0;
$buses_ok_a    = 0; // buses ≥85% sem actual
$buses_ok_prev = 0; // buses ≥85% sem anterior

foreach ($filas as $f) {
    /* real semana anterior para este bus (ya filtrado porque $filas no tiene excluidos) */
    $r_prev = $real_prev[$f['key']] ?? 0;
    $sum_real_prev += $r_prev;

    if ($f['t'] > 0) {
        if ($f['pct_a'] >= 85) $buses_ok_a++;
        $pct_prev_bus = round(($r_prev / $f['t']) * 100);
        if ($pct_prev_bus >= 85) $buses_ok_prev++;
    }
}

$faltantes_prev  = max(0, $sum_teo - $sum_real_prev);
$pct_prev        = $sum_teo > 0 ? round(($sum_real_prev/$sum_teo)*100) : 0;

/* Deltas */
$delta_pct      = $pct_global - $pct_prev;         // pp
$delta_real     = $sum_real_a - $sum_real_prev;
$delta_falt     = $faltantes - $faltantes_prev;     // negativo = mejoró
$delta_buses_ok = $buses_ok_a - $buses_ok_prev;

/* Sparkline día a día para KPI comparativa (sem actual vs anterior) */
$noms_dias = ['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'];
$spark_a   = []; $spark_prev_arr = [];
for ($i = 0; $i < 7; $i++) {
    $d_a    = date('Y-m-d', strtotime($lun_a)    + $i*86400);
    $d_prev = date('Y-m-d', strtotime($lun_prev) + $i*86400);

    /* Filtrar excluidos del sparkline */
    $n_a = 0;
    foreach (($dias_data[$d_a] ?? []) as $k => $v) {
        if (!bus_excluido($bus_info[$k]['bus'] ?? explode('||',$k)[0], $BUSES_EXCLUIDOS))
            $n_a += $v;
    }
    $n_prev = 0;
    foreach (($dias_data_prev[$d_prev] ?? []) as $k => $v) {
        if (!bus_excluido($bus_info[$k]['bus'] ?? explode('||',$k)[0], $BUSES_EXCLUIDOS))
            $n_prev += $v;
    }

    $spark_a[]        = ['d'=>$d_a,   'nom'=>$noms_dias[$i],'n'=>$n_a,   'hoy'=>$d_a===date('Y-m-d'),'fut'=>$d_a>date('Y-m-d')];
    $spark_prev_arr[] = ['d'=>$d_prev,'nom'=>$noms_dias[$i],'n'=>$n_prev,'hoy'=>false,                'fut'=>false];
}
$max_spark      = max(array_column($spark_a,'n')       ?: [1]);
$max_spark_prev = max(array_column($spark_prev_arr,'n') ?: [1]);
$max_spark_all  = max($max_spark, $max_spark_prev, 1);

/* Sparklines para cards escaneados y faltantes */
$spark_esc  = array_column($spark_a, 'n');
$spark_falt = [];
for ($i = 0; $i < 7; $i++) {
    $d    = date('Y-m-d', strtotime($lun_a) + $i*86400);
    $real_dia = 0;
    foreach (($dias_data[$d] ?? []) as $k => $v) {
        if (!bus_excluido($bus_info[$k]['bus'] ?? explode('||',$k)[0], $BUSES_EXCLUIDOS))
            $real_dia += $v;
    }
    $spark_falt[] = max(0, (int)($sum_teo/7) - $real_dia);
}

/* Top 3 críticos (ya filtrado via $filas) */
$criticos = array_filter($filas, fn($f)=>$f['t']>0);
usort($criticos, fn($a,$b)=>$a['pct_a']-$b['pct_a']);
$criticos = array_slice($criticos, 0, 3);

$qs_b = $usar_b ? "&year_b={$year_b}&week_b={$week_b}" : '';

/* ═══════════════════════════════════════════════════════
   TENDENCIA 4 SEMANAS para el modal
   Calcula pct_real/teorico de las últimas 4 semanas por bus
═══════════════════════════════════════════════════════ */
$trend_weeks = [];
for ($tw = 1; $tw <= 4; $tw++) {
    $tw_week = $week_a - $tw;
    $tw_year = $year_a;
    if ($tw_week < 1) { $tw_week += 52; $tw_year--; }
    $tw_lun  = semana_lunes($tw_year, $tw_week);
    $tw_dom  = semana_domingo($tw_lun);
    $trend_weeks[] = [
        'label' => "Sem $tw_week",
        'real'  => filtrar_excluidos(get_real_bus($mysqli, $tw_lun, $tw_dom, $tipo), $BUSES_EXCLUIDOS),
    ];
}

/* Construir mapa bus->tendencia para pasar al JS */
$trend_map = [];
foreach ($filas as $f) {
    $pts = [];
    foreach (array_reverse($trend_weeks) as $tw) {
        $r   = $tw['real'][$f['key']] ?? 0;
        $pct = $f['t'] > 0 ? round(($r/$f['t'])*100) : 0;
        $pts[] = ['sem'=>$tw['label'], 'pct'=>$pct, 'real'=>$r];
    }
    /* Añadir semana actual */
    $pts[] = ['sem'=>"Sem $week_a", 'pct'=>$f['pct_a'], 'real'=>$f['ra']];
    $trend_map[$f['key']] = $pts;
}
$js_trend_map = json_encode($trend_map);

/* ═══════════════════════════════════════════════════════
   ESCANEOS DE HOY por bus (para columna "Hoy" en tabla)
═══════════════════════════════════════════════════════ */
$hoy = date('Y-m-d');
$hoy_data = []; // [ key => n ]
if (isset($dias_data[$hoy])) {
    foreach ($dias_data[$hoy] as $k => $n) {
        $hoy_data[$k] = $n;
    }
}
$hoy_total = array_sum($hoy_data);

/* ═══════════════════════════════════════════════════════
   KPIs GERENCIALES
═══════════════════════════════════════════════════════ */
/* 1. Cobertura de flota — buses que tuvieron al menos 1 escaneo esta semana */
$buses_activos = 0;
$buses_total_flota = count($filas); /* todos los buses en la lista */
foreach ($filas as $f) {
    if ($f['ra'] > 0 || ($f['rb2'] ?? 0) > 0 || $f['rt'] > 0) $buses_activos++;
}
$pct_cobertura = $buses_total_flota > 0 ? round(($buses_activos / $buses_total_flota) * 100) : 0;
$buses_inactivos = $buses_total_flota - $buses_activos;

/* 2. Tendencia semanal — comparar las últimas 4 semanas */
$tend_semanas = []; /* [{sem, pct}] de más antiguo a más reciente */
for ($tw = 4; $tw >= 1; $tw--) {
    $tw_week = $week_a - $tw; $tw_year = $year_a;
    if ($tw_week < 1) { $tw_week += 52; $tw_year--; }
    $tw_lun = semana_lunes($tw_year, $tw_week);
    $tw_dom = semana_domingo($tw_lun);
    $tw_real = array_sum(filtrar_excluidos(get_real_bus($mysqli, $tw_lun, $tw_dom, $tipo), $BUSES_EXCLUIDOS));
    $tw_pct  = $sum_teo > 0 ? round(($tw_real / $sum_teo) * 100) : 0;
    $tend_semanas[] = ['sem' => "Sem $tw_week", 'pct' => $tw_pct];
}
$tend_semanas[] = ['sem' => "Sem $week_a", 'pct' => $pct_global]; /* semana actual */
$tend_direction = 'flat';
$tend_racha = 0;
if (count($tend_semanas) >= 2) {
    $last = end($tend_semanas);
    $prev = $tend_semanas[count($tend_semanas)-2];
    $tend_direction = $last['pct'] > $prev['pct'] ? 'up' : ($last['pct'] < $prev['pct'] ? 'down' : 'flat');
    /* Calcular racha */
    for ($ti = count($tend_semanas)-1; $ti > 0; $ti--) {
        if ($tend_semanas[$ti]['pct'] > $tend_semanas[$ti-1]['pct']) $tend_racha++;
        else break;
    }
}
$js_tend = json_encode(array_column($tend_semanas, 'pct'));
$js_tend_labs = json_encode(array_column($tend_semanas, 'sem'));

/* 3. Top 5 buses con lógica de jerarquía:
   - Si total >= 70% → usar total (más representativo)
   - Si total < 70% pero un modo individual >= 70% → usar ese modo
   - Si ninguno llega a 70% → no aparece en top
*/
$top_buses_raw = [];
foreach ($filas as $f) {
    $pct_sub = $f['pct_sub'] ?? 0;
    $pct_baj = $f['pct_baj'] ?? 0;
    $pct_tot = $f['pct_tot'] ?? 0;
    $tiene_sub = ($f['ts'] ?? 0) > 0;
    $tiene_baj = ($f['tb'] ?? 0) > 0;

    /* Decidir qué métrica usar */
    if ($tipo !== 'ambos') {
        /* En modo individual solo hay un modo */
        $pct_show_top = $f['pct_a'];
        $modo_top = $tipo;
        $real_top = $f['ra']; $teo_top = $f['t'];
    } elseif ($tiene_sub && $tiene_baj && $pct_tot >= 70) {
        /* Ambos modos con datos Y total >= 70% → usar total */
        $pct_show_top = $pct_tot;
        $modo_top = 'total';
        $real_top = $f['rt']; $teo_top = $f['tt'];
    } elseif ($pct_sub >= 70 && $pct_sub >= $pct_baj) {
        /* Solo subida destacable */
        $pct_show_top = $pct_sub;
        $modo_top = 'subida';
        $real_top = $f['rs']; $teo_top = $f['ts'];
    } elseif ($pct_baj >= 70) {
        /* Solo bajada destacable */
        $pct_show_top = $pct_baj;
        $modo_top = 'bajada';
        $real_top = $f['rb2']; $teo_top = $f['tb'];
    } else {
        continue; /* No llega a 70% en ningún modo */
    }

    if ($pct_show_top < 70) continue;

    $top_buses_raw[] = [
        'bus'   => $f['bus'],
        'placa' => $f['placa'],
        'pct'   => $pct_show_top,
        'modo'  => $modo_top,
        'real'  => $real_top,
        'teo'   => $teo_top,
    ];
}
/* Ordenar por pct DESC — total ≥70% ya tiene prioridad por su valor real */
usort($top_buses_raw, fn($a,$b) => $b['pct'] <=> $a['pct']);
$top5_visible = array_slice($top_buses_raw, 0, 5);
$top5_hidden  = array_slice($top_buses_raw, 5);
$js_top5_visible = json_encode($top5_visible);
$js_top5_hidden  = json_encode($top5_hidden);

/* Contar buses >= 85% para el KPI gerencial */
$buses_85_count = count(array_filter($top_buses_raw, fn($b) => $b['pct'] >= 85));
$buses_85_prev  = $buses_ok_prev ?? 0; /* reusar variable existente */
$delta_85 = $buses_85_count - $buses_85_prev;
/* Bus más cercano al 85% si count=0 */
$bus_cercano = '';
if ($buses_85_count === 0 && !empty($top_buses_raw)) {
    $best = $top_buses_raw[0];
    $bus_cercano = "El más cercano: {$best['bus']} con {$best['pct']}%";
}

/* ═══════════════════════════════════════════════════════
   MAPA DE ASIENTOS — pasajeros por asiento (sem. actual)
   Une registros → personal para obtener nombre completo
   Incluye subida + bajada de la semana seleccionada
═══════════════════════════════════════════════════════ */
function get_asientos_bus($mysqli, string $bus, string $ini, string $fin): array {
    $stmt = $mysqli->prepare(
        "SELECT
            r.asiento,
            CONCAT(TRIM(p.nombres), ' ', TRIM(p.apellidos)) AS nombre,
            r.evento,
            DATE(r.fecha) AS dia
         FROM registros r
         LEFT JOIN personal p ON p.dni = r.dni
         WHERE UPPER(TRIM(r.bus)) = ?
           AND DATE(r.fecha) BETWEEN ? AND ?
           AND r.evento IN ('SUBIDA PERMITIDA','BAJADA PERMITIDA')
           AND r.asiento IS NOT NULL AND r.asiento != ''
         ORDER BY r.fecha DESC"
    );
    $bus_upper = strtoupper(trim($bus));
    $stmt->bind_param('sss', $bus_upper, $ini, $fin);
    $stmt->execute();
    $res  = $stmt->get_result();
    $out  = []; // asiento => ['nombre'=>..,'evento'=>..,'dia'=>..]
    while ($r = $res->fetch_assoc()) {
        $asiento = (int)$r['asiento'];
        if ($asiento < 1 || $asiento > 30) continue;
        // Guardar solo el registro más reciente por asiento
        if (!isset($out[$asiento])) {
            $out[$asiento] = [
                'nombre' => $r['nombre'] ?: 'Sin nombre',
                'evento' => $r['evento'],
                'dia'    => $r['dia'],
            ];
        }
    }
    $stmt->close();
    return $out;
}
// Pre-cargar asientos para todos los buses (se llama on-demand desde AJAX)
// No pre-cargamos aquí para no sobrecargar — se llama via fetch al abrir modal


/* Helper JS arrays para barras comparativas */
$js_spark_a    = json_encode(array_column($spark_a, 'n'));
$js_spark_prev = json_encode(array_column($spark_prev_arr, 'n'));
$js_spark_esc  = json_encode($spark_esc);
$js_spark_falt = json_encode($spark_falt);
$js_noms       = json_encode(array_column($spark_a, 'nom'));

/* Sparklines diarios sub/baj para modo ambos */
$spark_sub_arr = []; $spark_baj_arr = [];
$noms_dias = $noms_dias ?? ['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'];
for ($i = 0; $i < 7; $i++) {
    $d = date('Y-m-d', strtotime($lun_a) + $i*86400);
    $spark_sub_arr[] = array_sum($dias_sub[$d] ?? []);
    $spark_baj_arr[] = array_sum($dias_baj[$d] ?? []);
}
$js_spark_sub = json_encode($spark_sub_arr);
$js_spark_baj = json_encode($spark_baj_arr);
$js_ambos_data = json_encode([
    'pct_sub' => $pct_sub_global, 'pct_baj' => $pct_baj_global,
    'teo_sub' => $sum_teo_sub,    'teo_baj' => $sum_teo_baj,
    'real_sub'=> $sum_real_sub,   'real_baj'=> $sum_real_baj,
    'falt_sub'=> $falt_sub,       'falt_baj'=> $falt_baj,
    'ok_sub'  => $buses_ok_sub,   'ok_baj'  => $buses_ok_baj,
    'brecha'  => $brecha_pp,
]);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<title>Control de Pasajeros · KPI Semanal · Hochschild Mining</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Geist:wght@300;400;500;600;700&family=Geist+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<link rel="icon" type="image/png" href="assets/logo4.png"/>
<style>
/* ═══════════════════════════════════════════════════════
   TOKENS — Dashboard system (light) + SITRAN gold accent
═══════════════════════════════════════════════════════ */
:root {
  /* Base palette (dashboard) */
  --page:      #F8FAFC;
  --surface:   #FFFFFF;
  --overlay:   #F1F5F9;
  --overlay-2: #E2E8F0;
  --border:    #E2E8F0;
  --border-2:  #CBD5E1;
  --ink:       #0F172A;
  --ink-2:     #334155;
  --ink-3:     #64748B;
  --ink-4:     #94A3B8;

  /* Gold (SITRAN accent) */
  --gold:    #B8892A;
  --gold-2:  #D4A843;
  --gold-3:  #ECC96E;
  --gold-bg: #FFFBEB;
  --gold-ring: rgba(184,137,42,0.20);

  /* Status */
  --ok:      #16a34a;  --ok-bg:   rgba(22,163,74,.08);   --ok-border:   rgba(22,163,74,.2);
  --warn:    #d97706;  --warn-bg: rgba(217,119,6,.08);    --warn-border: rgba(217,119,6,.2);
  --crit:    #dc2626;  --crit-bg: rgba(220,38,38,.08);    --crit-border: rgba(220,38,38,.2);
  --blue:    #2563EB;  --blue-bg: rgba(37,99,235,.08);    --blue-border: rgba(37,99,235,.2);

  /* Shadows */
  --sh-card:  0 2px 12px -2px rgba(15,23,42,.06), 0 0 2px rgba(15,23,42,.03);
  --sh-hover: 0 8px 24px -4px rgba(15,23,42,.10), 0 0 3px rgba(15,23,42,.04);

  /* Typography */
  --font: 'Geist', 'Inter', system-ui, sans-serif;
  --mono: 'Geist Mono', monospace;

  /* Radii */
  --r3: 6px; --r4: 8px; --r5: 12px; --r6: 16px; --r7: 20px;
  --safe-b: env(safe-area-inset-bottom, 0px);
}

/* ── RESET ── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; }
body {
  font-family: var(--font);
  font-size: 14px; line-height: 1.5;
  background: var(--page); color: var(--ink-2);
  min-height: 100svh;
  -webkit-font-smoothing: antialiased;
}
a { text-decoration: none; color: inherit; }
button { font-family: var(--font); border: none; cursor: pointer; background: none; }
::-webkit-scrollbar { width: 6px; height: 6px; }
::-webkit-scrollbar-track { background: var(--page); }
::-webkit-scrollbar-thumb { background: var(--ink-4); border-radius: 3px; }
::-webkit-scrollbar-thumb:hover { background: var(--ink-3); }

/* ── TOPBAR — estilo buses ── */
.topbar {
  position: sticky; top: 0; z-index: 200;
  background: #fff;
  border-bottom: 1px solid #e8e5e0;
  box-shadow: 0 1px 3px rgba(0,0,0,.06), 0 1px 2px rgba(0,0,0,.04);
  padding-top: env(safe-area-inset-top, 0px);
}
.topbar-row {
  height: 52px; display: flex; align-items: center;
  justify-content: space-between; padding: 0 14px; max-width:none;
}
.topbar-accent {
  height: 2.5px;
  background: linear-gradient(90deg, transparent, #8A6A14 10%, #C49A2C 30%, #EDD98A 55%, #C49A2C 75%, #8A6A14 90%, transparent);
}
.t-brand { display: flex; align-items: center; gap: 9px; text-decoration: none; color: inherit; }
.t-brand img { height: 30px; width: auto; object-fit: contain; flex-shrink: 0; }
.t-brand-text { display: flex; flex-direction: column; }
.t-brand-name { font-size: 12px; font-weight: 800; letter-spacing: .05em; color: #0F172A; text-transform: uppercase; line-height: 1; }
.t-brand-name span { color: #B8892A; }
.t-brand-sub { font-family: var(--mono); font-size: 7px; color: #94A3B8; letter-spacing: .08em; text-transform: uppercase; margin-top: 2px; }
.t-right { display: flex; align-items: center; gap: 7px; }
.t-logo,.t-divider,.t-title,.t-subtitle,.t-spacer { display: none; }
/* Botón volver — mismo estilo buses */
/* Botón volver — con texto y estilo píldora */
.t-back {
  height: 34px; width: auto; border-radius: 17px; padding: 0 14px 0 12px;
  background: #f1f5f9; border: 1px solid #e2e8f0;
  display: flex; align-items: center; justify-content: center; gap: 6px;
  color: #64748B; font-size: 12px; font-weight: 600; text-decoration: none; transition: all .18s;
}
.t-back:hover { background: #fef2f2; color: #dc2626; border-color: #fecaca; }
.t-btn {
  display: inline-flex; align-items: center; gap: 6px;
  font-size: 12px; font-weight: 600;
  padding: 6px 12px; border-radius: 8px;
  border: 1px solid #e2e8f0; background: #fff;
  color: #64748B; cursor: pointer; transition: all .18s; white-space: nowrap;
}
.t-btn:hover { border-color: #cbd5e1; color: #0F172A; }
.t-btn-gold { background: #C49A2C; color: #fff; border-color: #C49A2C; }
.t-btn-gold:hover { background: #8A6A14; border-color: #8A6A14; }
.cache-pill {
  display: inline-flex; align-items: center; gap: 5px;
  font-size: 10px; font-weight: 600; font-family: var(--mono);
  padding: 4px 9px; border-radius: 99px; white-space: nowrap;
}
.cache-pill.hit  { background: rgba(37,99,235,.08); color: #2563EB; border: 1px solid rgba(37,99,235,.2); }
.cache-pill.live { background: rgba(22,163,74,.08);  color: #16a34a; border: 1px solid rgba(22,163,74,.2); }
.btn-txt { display: inline; }

/* ── PAGE ── */
.page { max-width: 1120px; margin: 0 auto; padding: 28px 24px 80px; display: flex; flex-direction: column; gap: 20px; }
@keyframes fadeUp { from{opacity:0;transform:translateY(8px)} to{opacity:1;transform:none} }
.anim { animation: fadeUp .4s cubic-bezier(.34,1.2,.64,1) both; }
.d1{animation-delay:.04s}.d2{animation-delay:.08s}.d3{animation-delay:.12s}
.d4{animation-delay:.16s}.d5{animation-delay:.20s}.d6{animation-delay:.24s}

/* ── SECTION LABEL ── */
.sec-label {
  font-size: 12px; font-weight: 700; letter-spacing: 1px;
  text-transform: uppercase; color: var(--ink-3);
  display: flex; align-items: center; gap: 10px; margin-bottom: 12px;
}
.sec-label::after { content: ''; flex: 1; height: 1px; background: var(--border); }

/* ── CARD BASE ── */
.card {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: var(--r6); overflow: hidden; box-shadow: var(--sh-card);
}
.card-hdr {
  padding: 14px 20px; border-bottom: 1px solid var(--border);
  background: var(--overlay);
  display: flex; align-items: center; justify-content: space-between;
}
.card-title {
  font-size: 13px; font-weight: 600; color: var(--ink-2);
  display: flex; align-items: center; gap: 8px;
  text-transform: uppercase; letter-spacing: .5px;
}
.card-title i { color: var(--gold); font-size: 12px; }
.card-badge {
  font-size: 11px; font-weight: 600; font-family: var(--mono);
  padding: 3px 10px; border-radius: 6px;
  background: var(--gold-bg); color: var(--gold);
  border: 1px solid var(--gold-ring);
}

/* ── CONTROL BAR ── */
.cbar {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: var(--r5); padding: 12px 16px;
  display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
  box-shadow: var(--sh-card);
}
.flbl {
  font-size: 11px; font-weight: 700; letter-spacing: 1px;
  text-transform: uppercase; color: var(--ink-4); white-space: nowrap;
}
.fsel {
  padding: 7px 12px; background: var(--overlay); border: 1px solid var(--border);
  border-radius: var(--r4); font-family: var(--font); font-size: 13px;
  font-weight: 500; color: var(--ink); outline: none; cursor: pointer;
  transition: border-color .2s;
}
.fsel:focus { border-color: var(--gold); }
.vdiv { width: 1px; height: 22px; background: var(--border-2); flex-shrink: 0; }

/* Week nav */
.wnav { display: flex; align-items: center; border: 1px solid var(--border); border-radius: var(--r4); overflow: hidden; }
.wbtn {
  width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;
  background: var(--overlay); color: var(--ink-4); cursor: pointer; text-decoration: none;
  font-size: 11px; transition: all .15s;
}
.wbtn:hover { background: var(--overlay-2); color: var(--gold); }
.wbtn.dis { opacity: .25; pointer-events: none; }
.wtag {
  padding: 0 14px; font-family: var(--mono); font-size: 12px; font-weight: 600;
  color: var(--ink-2); background: var(--overlay); border-left: 1px solid var(--border);
  border-right: 1px solid var(--border); white-space: nowrap; line-height: 32px;
}

/* Buttons */
.btn {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 7px 14px; border-radius: var(--r4);
  font-family: var(--font); font-size: 13px; font-weight: 600;
  cursor: pointer; border: 1px solid var(--border); transition: all .2s;
  text-decoration: none; white-space: nowrap; color: var(--ink-3);
  background: var(--surface);
}
.btn:hover { border-color: var(--border-2); color: var(--ink); background: var(--overlay); }
.btn-gold { background: var(--gold); color: #fff; border-color: var(--gold); }
.btn-gold:hover { background: #9a7222; border-color: #9a7222; color: #fff; }
.btn-danger { background: var(--crit-bg); color: var(--crit); border-color: var(--crit-border); }
.btn-danger:hover { background: var(--crit); color: #fff; }

/* Range bar */
.rbar {
  display: none; flex-wrap: wrap; gap: 10px; align-items: center;
  background: var(--gold-bg); border: 1px solid var(--gold-ring);
  border-radius: var(--r4); padding: 10px 16px;
}
.rbar.open { display: flex; }
.rlab {
  font-size: 11px; font-weight: 700; letter-spacing: 1px;
  padding: 3px 10px; border-radius: 99px;
}
.rlab-a { background: var(--gold); color: #fff; }
.rlab-b { background: var(--overlay); color: var(--ink-3); border: 1px solid var(--border); }

/* ── KPI COMPARATIVA ── */
.kpi-strip-wrap { display: flex; flex-direction: column; gap: 10px; }
.kpi-week-row {
  display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px;
}
.kpi-week-title {
  font-size: 12px; font-weight: 700; color: var(--ink-3);
  letter-spacing: .5px; text-transform: uppercase;
  display: flex; align-items: center; gap: 6px;
}
.kpi-week-title i { color: var(--gold); }
.kpi-week-tags { display: flex; align-items: center; gap: 8px; }
.kpi-tag {
  font-size: 11px; font-weight: 600; font-family: var(--mono);
  padding: 4px 12px; border-radius: 6px;
  background: var(--overlay); border: 1px solid var(--border); color: var(--ink-3);
}
.kpi-tag.act { background: var(--gold-bg); color: var(--gold); border-color: var(--gold-ring); }
.kpi-vs { font-size: 11px; color: var(--ink-4); font-weight: 600; }
.kpi-strip { display: grid; grid-template-columns: repeat(3,1fr); gap: 12px; }
.kpi-card {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: var(--r5); padding: 18px 16px 14px;
  position: relative; overflow: hidden;
  box-shadow: var(--sh-card); transition: transform .2s, box-shadow .2s;
}
.kpi-card:hover { transform: translateY(-2px); box-shadow: var(--sh-hover); }
.kpi-accent { position: absolute; top: 0; left: 0; right: 0; height: 3px; border-radius: var(--r5) var(--r5) 0 0; }
.kpi-label { font-size: 11px; font-weight: 600; color: var(--ink-4); text-transform: uppercase; letter-spacing: .8px; margin-bottom: 10px; margin-top: 4px; }
.kpi-main { display: flex; align-items: baseline; gap: 10px; margin-bottom: 8px; }
.kpi-value { font-size: 32px; font-weight: 700; line-height: 1; color: var(--ink); font-family: var(--font); }
.kpi-delta {
  display: inline-flex; align-items: center; gap: 4px;
  font-size: 12px; font-weight: 600; padding: 3px 8px; border-radius: 99px; font-family: var(--mono);
}
.delta-up   { background: var(--ok-bg);   color: var(--ok);   border: 1px solid var(--ok-border); }
.delta-down { background: var(--crit-bg); color: var(--crit); border: 1px solid var(--crit-border); }
.delta-flat { background: var(--overlay); color: var(--ink-4); border: 1px solid var(--border); }
.kpi-sub { font-size: 11px; color: var(--ink-4); display: flex; align-items: center; gap: 5px; font-family: var(--mono); }
.mini-bar-track { height: 3px; background: var(--border); border-radius: 99px; overflow: hidden; margin-top: 10px; }
.mini-bar-fill  { height: 100%; border-radius: 99px; transition: width .9s cubic-bezier(.4,0,.2,1); }
.spark-row { display: flex; align-items: flex-end; gap: 2px; height: 24px; margin-top: 10px; }
.spark-bar { flex: 1; border-radius: 2px 2px 0 0; min-height: 2px; opacity: .75; }

/* Daybar */
.kpi-daybar {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: var(--r5); padding: 14px 20px;
  display: flex; align-items: center; gap: 0;
  box-shadow: var(--sh-card);
}
.kpi-daybar-title { font-size: 11px; font-weight: 700; letter-spacing: .8px; text-transform: uppercase; color: var(--ink-4); margin-right: 20px; white-space: nowrap; }
.daybar-cols { flex: 1; display: flex; align-items: flex-end; gap: 5px; height: 40px; }
.daybar-col { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 0; height: 100%; justify-content: flex-end; }
.daybar-pair { display: flex; align-items: flex-end; gap: 2px; width: 100%; justify-content: center; }
.daybar-bar { flex: 1; border-radius: 2px 2px 0 0; min-height: 2px; }
.daybar-nom { font-size: 9px; font-family: var(--mono); color: var(--ink-4); margin-top: 3px; text-align: center; }
.kpi-daybar-legend { display: flex; align-items: center; gap: 12px; margin-left: 20px; flex-shrink: 0; }
.dl-item { display: flex; align-items: center; gap: 5px; font-size: 11px; color: var(--ink-4); }
.dl-dot { width: 8px; height: 8px; border-radius: 2px; flex-shrink: 0; }

/* ── EXCL PANEL ── */
.excl-bar {
  display: none; flex-wrap: wrap; gap: 12px; align-items: flex-start;
  background: #FFF5F5; border: 1px solid var(--crit-border);
  border-radius: var(--r4); padding: 14px 18px;
}
.excl-bar.open { display: flex; }
.excl-title { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .8px; color: var(--crit); display: flex; align-items: center; gap: 6px; width: 100%; margin-bottom: 2px; }
.excl-lista { display: flex; flex-wrap: wrap; gap: 6px; flex: 1; align-items: center; min-width: 200px; }
.excl-chip { display: inline-flex; align-items: center; gap: 5px; background: var(--crit-bg); border: 1px solid var(--crit-border); border-radius: 99px; padding: 4px 12px 4px 14px; font-size: 12px; font-weight: 600; color: var(--crit); }
.excl-chip-rm { width: 16px; height: 16px; border-radius: 50%; border: none; background: rgba(220,38,38,.15); color: var(--crit); cursor: pointer; font-size: 10px; display: inline-flex; align-items: center; justify-content: center; transition: background .15s; padding: 0; flex-shrink: 0; }
.excl-chip-rm:hover { background: rgba(220,38,38,.3); }
.excl-empty { font-size: 12px; color: var(--ink-4); font-style: italic; }
.excl-form { display: flex; gap: 8px; align-items: flex-start; flex-direction: column; width: 100%; }
.excl-cb-row { display: flex; align-items: center; gap: 10px; padding: 7px 14px; cursor: pointer; font-size: 12px; font-weight: 500; color: var(--ink); transition: background .1s; }
.excl-cb-row:hover { background: var(--crit-bg); }
.excl-cb-row input[type=checkbox] { width: 14px; height: 14px; cursor: pointer; accent-color: var(--crit); flex-shrink: 0; }
.excl-cb-row.hidden { display: none; }
.excl-hint { font-size: 11px; color: var(--ink-4); width: 100%; margin-top: 2px; }
.excl-reset { font-size: 11px; color: var(--ink-4); background: none; border: none; cursor: pointer; text-decoration: underline; padding: 0; font-family: var(--font); transition: color .15s; }
.excl-reset:hover { color: var(--crit); }

/* ── AMBOS PANEL ── */
.ambos-wrap { display: flex; flex-direction: column; gap: 12px; }
.ambos-global { display: grid; grid-template-columns: repeat(3,1fr); gap: 12px; }
.ag {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: var(--r5); padding: 16px 18px; position: relative; overflow: hidden;
  box-shadow: var(--sh-card);
}
.ag-accent { position: absolute; top: 0; left: 0; right: 0; height: 3px; }
.ag-label { font-size: 11px; font-weight: 600; color: var(--ink-4); text-transform: uppercase; letter-spacing: .8px; margin-bottom: 8px; margin-top: 4px; }
.ag-val { font-size: 30px; font-weight: 700; line-height: 1; margin-bottom: 6px; }
.ag-sub { font-size: 11px; color: var(--ink-4); font-family: var(--mono); }
.ambos-cols { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.mc { background: var(--surface); border: 1px solid var(--border); border-radius: var(--r5); overflow: hidden; box-shadow: var(--sh-card); }
.mc-hdr { padding: 12px 16px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--border); background: var(--overlay); }
.mc-label { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .8px; display: flex; align-items: center; gap: 6px; }
.mc-pct { font-size: 24px; font-weight: 700; line-height: 1; }
.mc-body { padding: 14px 16px; }
.mc-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; font-size: 13px; }
.mc-row-lbl { color: var(--ink-3); }
.mc-row-val { font-family: var(--mono); font-size: 13px; font-weight: 600; color: var(--ink); }
.mc-bar { height: 4px; background: var(--border); border-radius: 99px; overflow: hidden; margin-top: 10px; }
.mc-fill { height: 100%; border-radius: 99px; }
.mc-spark { display: flex; align-items: flex-end; gap: 2px; height: 22px; margin-top: 8px; }
.mc-sbar { flex: 1; border-radius: 2px 2px 0 0; min-height: 2px; opacity: .8; }

/* ── TABLA UNIFICADA ── */
.tbl-uni { width: 100%; border-collapse: collapse; font-size: 13px; }
.tbl-uni th { padding: 9px 14px; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .8px; color: var(--ink-4); background: var(--overlay); border-bottom: 1px solid var(--border); text-align: center; white-space: nowrap; }
.tbl-uni th.left { text-align: left; }
.tbl-uni th.grp-s { color: #0F6E56; border-bottom: 2px solid #1D9E75; }
.tbl-uni th.grp-b { color: #854F0B; border-bottom: 2px solid #BA7517; }
.tbl-uni th.grp-t { color: var(--gold); border-bottom: 2px solid var(--gold); }
.tbl-uni th.grp-h { color: var(--blue); border-bottom: 2px solid var(--blue); }
.tbl-uni td { padding: 10px 14px; border-bottom: 1px solid var(--border); vertical-align: middle; text-align: center; }
.tbl-uni td.left { text-align: left; }
.tbl-uni tbody tr { cursor: pointer; transition: background .1s; }
.tbl-uni tbody tr:hover td { background: var(--overlay); }
.tbl-uni tbody tr:last-child td { border-bottom: none; }
.tbl-uni tfoot td { border-top: 2px solid var(--gold-ring); background: var(--overlay); font-weight: 600; padding: 11px 14px; }
.un { font-family: var(--mono); font-size: 13px; font-weight: 600; }
.un.ok { color: var(--ok); } .un.warn { color: var(--warn); } .un.crit { color: var(--crit); } .un.gold { color: var(--gold); } .un.blue { color: var(--blue); } .un.muted { color: var(--ink-4); }
.u-mini { display: flex; align-items: center; gap: 6px; justify-content: center; }
.u-mt { width: 46px; height: 4px; background: var(--border); border-radius: 99px; overflow: hidden; flex-shrink: 0; }
.u-mf { height: 100%; border-radius: 99px; }
.u-mp { font-family: var(--mono); font-size: 11px; font-weight: 600; width: 30px; text-align: right; }
.u-delta { display: inline-flex; align-items: center; gap: 3px; font-family: var(--mono); font-size: 11px; font-weight: 600; }
.u-delta.up { color: var(--ok); } .u-delta.dn { color: var(--crit); } .u-delta.flat { color: var(--ink-4); }
.u-hoy { display: flex; flex-direction: column; align-items: center; gap: 2px; }
.u-hoy-v { font-family: var(--mono); font-size: 13px; font-weight: 600; color: var(--blue); }
.u-hoy-b { width: 36px; height: 3px; background: var(--border); border-radius: 99px; overflow: hidden; }
.u-hoy-f { height: 100%; border-radius: 99px; background: var(--blue); }

/* Bus dot colors */
.dc { display: flex; align-items: center; gap: 8px; }
.ddot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.dname { font-weight: 600; font-size: 13px; color: var(--ink); }
.dalert { display: inline-flex; align-items: center; gap: 3px; margin-left: 5px; font-size: 10px; font-weight: 700; color: var(--crit); background: var(--crit-bg); border: 1px solid var(--crit-border); padding: 2px 6px; border-radius: 4px; }
.row-hint { font-size: 10px; color: var(--ink-4); margin-top: 2px; display: flex; align-items: center; gap: 4px; }
.dot-0{background:#B8892A}.dot-1{background:#2563EB}.dot-2{background:#16a34a}
.dot-3{background:#dc2626}.dot-4{background:#d97706}.dot-5{background:#7c3aed}
.dot-6{background:#0891b2}.dot-7{background:#db2777}.dot-8{background:#0284c7}.dot-9{background:#ca8a04}

/* Table legend + autorefresh */
.leg { display: flex; gap: 16px; flex-wrap: wrap; align-items: center; padding: 10px 16px; border-top: 1px solid var(--border); background: var(--overlay); }
.li { display: flex; align-items: center; gap: 5px; font-size: 11px; font-weight: 600; color: var(--ink-4); }
.ld { width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; }
.laut { margin-left: auto; display: flex; align-items: center; gap: 8px; }
.ar-btn { display: inline-flex; align-items: center; gap: 5px; background: none; border: 1px solid var(--border); border-radius: var(--r3); padding: 3px 10px; cursor: pointer; font-family: var(--mono); font-size: 10px; font-weight: 700; color: var(--ink-4); transition: all .15s; }
.ar-btn:hover { border-color: var(--border-2); color: var(--ink); }
.ar-txt { font-size: 10px; color: var(--ink-4); font-family: var(--mono); }
.ar-sel { font-family: var(--mono); font-size: 10px; font-weight: 600; color: var(--ink-3); background: var(--overlay); border: 1px solid var(--border); border-radius: var(--r3); padding: 2px 6px; cursor: pointer; outline: none; }

/* Empty state */
.empty { text-align: center; padding: 56px 20px; color: var(--ink-4); }
.empty i { font-size: 32px; opacity: .15; display: block; margin-bottom: 12px; }

/* ── MODAL ── */
.moverlay { display: none; position: fixed; inset: 0; z-index: 500; background: rgba(15,23,42,.5); backdrop-filter: blur(6px); align-items: center; justify-content: center; }
.moverlay.open { display: flex; animation: fadein .2s ease; }
@keyframes fadein { from{opacity:0} to{opacity:1} }
.modal {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: var(--r6); width: min(680px,96vw); max-height: 90vh;
  overflow: hidden; display: flex; flex-direction: column;
  box-shadow: 0 24px 60px rgba(15,23,42,.18);
  animation: modalIn .25s cubic-bezier(.34,1.2,.64,1);
}
@keyframes modalIn { from{opacity:0;transform:scale(.96) translateY(10px)} to{opacity:1;transform:scale(1) translateY(0)} }
.mhdr { display: flex; align-items: center; justify-content: space-between; padding: 16px 20px; border-bottom: 1px solid var(--border); background: var(--overlay); }
.mtitle { font-size: 16px; font-weight: 700; color: var(--ink); }
.mhdr-sub { font-family: var(--mono); font-size: 11px; color: var(--ink-4); margin-top: 2px; }
.mclose { width: 28px; height: 28px; border-radius: 50%; border: 1px solid var(--border); background: var(--surface); color: var(--ink-3); cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 12px; transition: all .2s; }
.mclose:hover { background: var(--overlay-2); color: var(--ink); }
.mbody { padding: 20px; overflow-y: auto; flex: 1; }
.msrow { display: grid; grid-template-columns: repeat(3,1fr); gap: 10px; margin-bottom: 18px; }
.ms { background: var(--overlay); border: 1px solid var(--border); border-radius: var(--r4); padding: 12px; text-align: center; }
.msv { font-size: 22px; font-weight: 700; color: var(--ink); }
.msv.g { color: var(--gold); }
.msl { font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: .8px; color: var(--ink-4); margin-top: 4px; }
.mtabs { display: flex; border-bottom: 1px solid var(--border); margin-bottom: 16px; }
.mtab { flex: 1; padding: 9px 0; text-align: center; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .8px; color: var(--ink-4); cursor: pointer; border-bottom: 2px solid transparent; transition: all .15s; }
.mtab.act { color: var(--gold); border-color: var(--gold); }
.msec-title { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .8px; color: var(--ink-4); margin-bottom: 10px; margin-top: 4px; }
.mday { display: flex; align-items: center; gap: 10px; padding: 8px 0; border-bottom: 1px solid var(--border); }
.mday:last-child { border-bottom: none; }
.mn { font-size: 11px; font-weight: 700; color: var(--ink-3); width: 28px; flex-shrink: 0; }
.mf { font-family: var(--mono); font-size: 10px; color: var(--ink-4); width: 48px; flex-shrink: 0; }
.mbw { flex: 1; height: 5px; background: var(--border); border-radius: 99px; overflow: hidden; }
.mbb { height: 100%; border-radius: 99px; background: var(--gold); }
.mv { font-family: var(--mono); font-size: 11px; font-weight: 600; color: var(--ink); width: 26px; text-align: right; flex-shrink: 0; }
.mv.z { color: var(--ink-4); }
.mfut { font-size: 11px; color: var(--ink-4); opacity: .6; text-align: center; padding: 6px 0; font-style: italic; }
.trend-row { display: flex; align-items: center; gap: 10px; padding: 8px 0; border-bottom: 1px solid var(--border); }
.trend-row:last-child { border-bottom: none; }
.trend-act .trend-sem { color: var(--gold); font-weight: 700; }
.trend-act { background: var(--gold-bg); padding: 8px 10px; border-radius: var(--r3); margin: 0 -10px; }
.trend-sem { font-family: var(--mono); font-size: 11px; color: var(--ink-3); width: 56px; flex-shrink: 0; }
.trend-bar { flex: 1; height: 6px; background: var(--border); border-radius: 99px; overflow: hidden; }
.trend-fill { height: 100%; border-radius: 99px; }
.trend-pct { font-family: var(--mono); font-size: 11px; font-weight: 700; width: 36px; text-align: right; flex-shrink: 0; }
.trend-real { font-family: var(--mono); font-size: 10px; color: var(--ink-4); width: 32px; text-align: right; flex-shrink: 0; }

/* Seat map */
.seat-count { display: grid; grid-template-columns: repeat(3,1fr); gap: 8px; margin-bottom: 14px; }
.sc-item { background: var(--overlay); border: 1px solid var(--border); border-radius: var(--r4); padding: 10px; text-align: center; }
.sc-v { font-size: 22px; font-weight: 700; line-height: 1; margin-bottom: 3px; }
.sc-l { font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: .8px; color: var(--ink-4); }
.dual-wrap { display: grid; grid-template-columns: 1fr 1fr; gap: 0; }
.bus-col { display: flex; flex-direction: column; }
.bus-col:first-child { padding-right: 10px; border-right: 1px solid var(--border); }
.bus-col:last-child { padding-left: 10px; }
.bus-mode-title { text-align: center; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px; padding: 5px 0 8px; display: flex; align-items: center; justify-content: center; gap: 5px; }
.bus-mode-title.sub { color: #0F6E56; } .bus-mode-title.baj { color: #854F0B; }
.bus-mini-stats { display: flex; gap: 6px; justify-content: center; margin-bottom: 8px; }
.bms { border-radius: var(--r3); padding: 3px 8px; font-family: var(--mono); font-size: 10px; font-weight: 700; }
.bms.ok { background: var(--ok-bg); color: var(--ok); } .bms.cr { background: var(--crit-bg); color: var(--crit); }
.bus-shell { background: var(--overlay); border: 1px solid var(--border); border-radius: var(--r4); padding: 10px 8px; margin-bottom: 8px; }
.bus-front { text-align: center; font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px; color: var(--ink-4); margin-bottom: 10px; display: flex; align-items: center; gap: 6px; }
.bus-front::before,.bus-front::after { content: ''; flex: 1; height: 1px; background: var(--border); }
.seat-grid { display: flex; flex-direction: column; gap: 3px; margin: 0 auto; }
.seat-row { display: flex; align-items: center; gap: 3px; }
.seat-fnum { font-family: var(--mono); font-size: 9px; color: var(--ink-4); width: 12px; text-align: right; flex-shrink: 0; }
.seat-aisle { width: 16px; flex-shrink: 0; text-align: center; font-size: 9px; color: var(--border-2); }
.seat { width: 38px; height: 32px; border-radius: 5px 5px 3px 3px; border: 1.5px solid var(--border); cursor: pointer; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 1px; transition: all .15s; flex-shrink: 0; position: relative; background: var(--surface); }
.seat:hover { transform: translateY(-2px); z-index: 2; }
.seat.ocup { background: #F0FDF4; border-color: #16a34a; }
.seat.asig { background: #FEF2F2; border-color: #dc2626; }
.seat.vac  { background: var(--overlay); border-color: var(--border); }
.seat.sel  { outline: 2px solid var(--blue); outline-offset: 1px; }
.seat-lbl { font-family: var(--mono); font-size: 7px; font-weight: 600; color: var(--ink-4); }
.seat.ocup .seat-lbl { color: #15803d; } .seat.asig .seat-lbl { color: #b91c1c; }
.seat-tip { position: absolute; bottom: calc(100%+4px); left: 50%; transform: translateX(-50%); background: var(--ink); color: #fff; border-radius: var(--r3); padding: 4px 8px; font-size: 10px; white-space: nowrap; pointer-events: none; z-index: 10; opacity: 0; transition: opacity .15s; }
.seat:hover .seat-tip { opacity: 1; }
.seat-legend { display: flex; gap: 14px; justify-content: center; margin-top: 10px; }
.sl-item { display: flex; align-items: center; gap: 5px; font-size: 10px; color: var(--ink-3); }
.sl-box { width: 10px; height: 10px; border-radius: 3px; flex-shrink: 0; }
.pax-detail { background: var(--overlay); border: 1px solid var(--border); border-radius: var(--r4); padding: 12px 16px; margin-top: 6px; display: none; }
.pax-detail.show { display: block; }
.pax-name { font-weight: 700; font-size: 14px; color: var(--ink); margin-bottom: 4px; }
.pax-meta { font-family: var(--mono); font-size: 11px; color: var(--ink-4); }
.seat-loading { text-align: center; padding: 28px; color: var(--ink-4); font-size: 13px; }

/* ── ZONA LABEL ── */
.zona-hdr{font-size:11px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:var(--ink-3);display:flex;align-items:center;gap:10px}
.zona-hdr::after{content:'';flex:1;height:1px;background:var(--border)}
/* ── GERENCIA KPIS ── */
.ger-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}
.ger-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--r5);padding:18px 16px 14px;position:relative;overflow:hidden;box-shadow:var(--sh-card);transition:transform .2s,box-shadow .2s}
.ger-card:hover{transform:translateY(-2px);box-shadow:var(--sh-hover)}
.ger-accent{position:absolute;top:0;left:0;right:0;height:3px;border-radius:var(--r5) var(--r5) 0 0}
.ger-lbl{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.8px;color:var(--ink-4);margin-bottom:10px;margin-top:4px}
.ger-main{display:flex;align-items:baseline;gap:10px;margin-bottom:6px}
.ger-val{font-size:30px;font-weight:700;line-height:1}
.ger-sub{font-size:11px;color:var(--ink-4);font-family:var(--mono);margin-bottom:8px}
.fleet-row{display:flex;gap:0;margin-top:10px;border:1px solid var(--border);border-radius:var(--r4);overflow:hidden}
.fleet-cell{flex:1;text-align:center;padding:8px 4px;border-right:1px solid var(--border)}
.fleet-cell:last-child{border-right:none}
.fleet-n{font-size:18px;font-weight:700;line-height:1;font-family:var(--mono)}
.fleet-l{font-size:9px;text-transform:uppercase;letter-spacing:.8px;color:var(--ink-4);margin-top:3px}
.spark-row{display:flex;align-items:flex-end;gap:3px;height:28px;margin-top:10px}
.spark-b{flex:1;border-radius:2px 2px 0 0;min-height:2px}
.spark-labs{display:flex;justify-content:space-between;font-size:9px;font-family:var(--mono);color:var(--ink-4);margin-top:2px}
.ger-note{margin-top:10px;font-size:11px;color:var(--ink-4);padding:8px 10px;background:var(--overlay);border-radius:var(--r3);border:1px solid var(--border)}
/* ── TOP 5 ── */
.top5-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--r5);overflow:hidden;box-shadow:var(--sh-card)}
.top5-hdr{padding:12px 18px;border-bottom:1px solid var(--border);background:var(--overlay);display:flex;align-items:center;justify-content:space-between}
.top5-title{font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--ink-2);display:flex;align-items:center;gap:7px}
.top5-legend{display:flex;gap:10px}
.t5-li{display:flex;align-items:center;gap:4px;font-size:10px}
.t5-dot{width:8px;height:8px;border-radius:2px;flex-shrink:0}
.top5-row{display:flex;align-items:center;gap:12px;padding:10px 18px;border-bottom:1px solid var(--border);cursor:pointer;transition:background .1s}
.top5-row:hover{background:var(--overlay)}
.top5-row:last-child{border-bottom:none}
.t5-rank{width:22px;height:22px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0}
.t5r-1{background:var(--gold-bg);color:var(--gold);border:1px solid var(--gold-ring)}
.t5r-n{background:var(--overlay);color:var(--ink-4);border:0.5px solid var(--border)}
.t5-info{flex:1;min-width:0}
.t5-name{font-size:13px;font-weight:600;color:var(--ink)}
.t5-meta{font-size:10px;color:var(--ink-4);font-family:var(--mono);margin-top:1px}
.t5-mode{font-size:9px;font-weight:700;padding:2px 7px;border-radius:4px;flex-shrink:0;text-transform:uppercase;letter-spacing:.5px}
.t5m-sub{background:rgba(29,158,117,.1);color:#0F6E56}
.t5m-baj{background:rgba(186,117,23,.1);color:#854F0B}
.t5m-tot{background:var(--gold-bg);color:var(--gold)}
.t5-prog{display:flex;align-items:center;gap:8px;width:130px;flex-shrink:0}
.t5-pt{flex:1;height:4px;background:var(--border);border-radius:99px;overflow:hidden}
.t5-pf{height:100%;border-radius:99px}
.t5-pct{font-size:13px;font-weight:700;font-family:var(--mono);width:34px;text-align:right;flex-shrink:0}
.t5-expand{width:100%;padding:9px;text-align:center;font-size:11px;font-weight:600;color:var(--ink-4);background:var(--overlay);border:none;border-top:1px solid var(--border);cursor:pointer;display:flex;align-items:center;justify-content:center;gap:5px;transition:all .15s}
.t5-expand:hover{color:var(--ink);background:var(--overlay-2)}
.t5-hidden{display:none}.t5-hidden.open{display:block}
.t5-ico{transition:transform .2s}.t5-ico.open{transform:rotate(180deg)}
/* ── SECCIONES COLAPSABLES ── */
.collapsible{background:var(--surface);border:1px solid var(--border);border-radius:var(--r5);overflow:hidden;box-shadow:var(--sh-card)}
.coll-hdr{padding:12px 18px;display:flex;align-items:center;justify-content:space-between;cursor:pointer;transition:background .15s;user-select:none}
.coll-hdr:hover{background:var(--overlay)}
.coll-title{font-size:13px;font-weight:600;color:var(--ink);display:flex;align-items:center;gap:8px}
.coll-title i{color:var(--gold);font-size:12px}
.coll-meta{display:flex;align-items:center;gap:10px}
.coll-preview{font-size:11px;font-family:var(--mono);color:var(--ink-4)}
.coll-ico{width:20px;height:20px;border-radius:50%;border:1px solid var(--border);display:flex;align-items:center;justify-content:center;color:var(--ink-4);transition:transform .25s;flex-shrink:0}
.coll-ico.open{transform:rotate(180deg)}
.coll-body{border-top:1px solid var(--border);padding:18px 20px;display:none}
.coll-body.open{display:block}
/* ── TORTAS SUB/BAJ ── */
.pie-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:4px}
.pie-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--r5);padding:20px 22px;display:flex;align-items:center;gap:22px;box-shadow:var(--sh-card);transition:transform .2s,box-shadow .2s}
.pie-card:hover{transform:translateY(-2px);box-shadow:var(--sh-hover)}
.pie-wrap{flex-shrink:0;position:relative;width:100px;height:100px}
.pie-wrap svg{width:100px;height:100px;transform:rotate(-90deg)}
.pie-center{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:1px}
.pie-pct{font-size:20px;font-weight:700;font-family:var(--mono);line-height:1}
.pie-mode{font-size:9px;font-weight:600;text-transform:uppercase;letter-spacing:.8px;color:var(--ink-4);margin-top:2px}
.pie-info{flex:1;min-width:0}
.pie-title{font-size:14px;font-weight:700;margin-bottom:12px;display:flex;align-items:center;gap:7px}
.pie-stat{display:flex;justify-content:space-between;align-items:center;font-size:12px;margin-bottom:6px;padding-bottom:6px;border-bottom:1px solid var(--border)}
.pie-stat:last-child{border-bottom:none;margin-bottom:0;padding-bottom:0}
.pie-lbl{color:var(--ink-4)}.pie-val{font-family:var(--mono);font-weight:600;color:var(--ink)}
/* ── TOP5 COMPACTO dentro de sección ── */
.t5c-row{display:flex;align-items:center;gap:8px;padding:7px 0;border-bottom:1px solid var(--border)}
.t5c-row:last-child{border-bottom:none}
.t5c-rank{width:18px;height:18px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;flex-shrink:0;background:var(--overlay);color:var(--ink-4);border:0.5px solid var(--border)}
.t5c-r1{background:var(--gold-bg);color:var(--gold);border-color:var(--gold-ring)}
.t5c-name{flex:1;font-size:12px;font-weight:600;color:var(--ink)}
.t5c-mode{font-size:9px;font-weight:700;padding:1px 6px;border-radius:3px;text-transform:uppercase;letter-spacing:.4px}
.t5c-sub{background:rgba(29,158,117,.1);color:#0F6E56}
.t5c-baj{background:rgba(186,117,23,.1);color:#854F0B}
.t5c-tot{background:var(--gold-bg);color:var(--gold)}
.t5c-bar{width:64px;height:3px;background:var(--border);border-radius:99px;overflow:hidden;flex-shrink:0}
.t5c-bar-f{height:100%;border-radius:99px}
.t5c-pct{font-size:12px;font-weight:700;font-family:var(--mono);width:32px;text-align:right;flex-shrink:0}
.t5c-expand{width:100%;padding:7px;text-align:center;font-size:11px;font-weight:600;color:var(--ink-4);background:var(--overlay);border:none;border-top:1px solid var(--border);cursor:pointer;display:flex;align-items:center;justify-content:center;gap:5px;transition:all .15s}
.t5c-expand:hover{color:var(--ink)}
.t5c-hidden{display:none}.t5c-hidden.open{display:block}
.t5c-eico{transition:transform .2s}.t5c-eico.open{transform:rotate(180deg)}
/* ── RESPONSIVE ── */
@media(max-width:900px){.kpi-strip{grid-template-columns:repeat(3,1fr)}.ger-grid{grid-template-columns:repeat(2,1fr)}.ambos-global{grid-template-columns:1fr 1fr}}
@media(max-width:700px){
  .topbar-row{padding:0 12px;height:52px;gap:8px}
  .t-title,.t-subtitle,.t-divider{display:none}
  .t-logo img{height:36px}
  .btn-txt{display:none}
  .t-hide-sm{display:none}
  .t-btn{padding:6px 10px;min-width:36px;justify-content:center}
  .t-back{padding:6px 10px}
  .page{padding:12px 10px 60px}
  .kpi-strip{grid-template-columns:repeat(2,1fr)}
  .ger-grid{grid-template-columns:1fr}
  .ambos-cols{grid-template-columns:1fr}
  .cbar{gap:6px;padding:10px 12px}
  .kpi-card{padding:14px 12px}
  .kpi-value{font-size:26px}
  .collapsible .coll-hdr{padding:10px 14px}
  .coll-preview{display:none}
  .pie-row{grid-template-columns:1fr}
}
@media(max-width:480px){
  .kpi-strip{grid-template-columns:1fr 1fr}
  .wnav .wtag{font-size:11px;padding:0 8px}
  .tbl-uni{font-size:11px}
  .tbl-uni th,.tbl-uni td{padding:7px 8px}
  /* Tabla bus — scroll horizontal sin romper layout */
  .collapsible .coll-body { overflow-x: auto; -webkit-overflow-scrolling: touch; }
  .tbl-uni { min-width: 520px; }
}
@media(prefers-reduced-motion:reduce){.anim{animation:none;opacity:1}}

/* ── BOTTOM NAV MONITOREO DE RUTA ── */
.bottom-nav { display:none; }

@media (max-width: 600px) {
  /* 1. CLAVE PARA QUE LOS BOTONES NO SE BLOQUEEN: Darle espacio extra al final */
  .page { padding-bottom: calc(110px + env(safe-area-inset-bottom, 0px)) !important; }
  
  /* 2. El footer normal debe subir para no taparse con el Bottom Nav */
  .footer { 
    bottom: calc(72px + env(safe-area-inset-bottom, 0px)); 
    position: fixed; left: 0; right: 0; z-index: 150; 
    box-shadow: 0 -4px 12px rgba(0,0,0,0.06); 
  }

  .bottom-nav {
    display: flex;
    position: fixed; bottom: 0; left: 0; right: 0; z-index: 999; /* Alto nivel para estar siempre al frente */
    background: rgba(255,255,255,.95);
    backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px);
    border-top: 1px solid var(--border);
    padding: 8px 6px calc(8px + env(safe-area-inset-bottom, 0px));
    align-items: center; justify-content: space-around;
  }
  
  .bn-item {
    display: flex; flex-direction: column; align-items: center; gap: 3px;
    cursor: pointer; padding: 5px 10px; border-radius: 10px; flex: 1;
    transition: background .12s; -webkit-tap-highlight-color: transparent;
    text-decoration: none; background: none; border: none; font-family: var(--f);
  }
  .bn-item:active { background: var(--bg3); transform: scale(.94); }
  
  .bn-ico { width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; }
  .bn-ico svg { width: 22px; height: 22px; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; transition: stroke .15s; }
  .bn-lbl { font-size: 10px; font-weight: 600; transition: color .15s; }
  
/* Estados Activos/Inactivos */
  .bn-item.active .bn-ico svg { stroke: var(--gold); }
  .bn-item.active .bn-lbl   { color: var(--gold); }
  .bn-item:not(.active) .bn-ico svg { stroke: var(--ink-4); } /* Corregido: antes era --muted2 */
  .bn-item:not(.active) .bn-lbl    { color: var(--ink-4); } /* Corregido: antes era --muted2 */

  /* Botón Alerta Central */
  .bn-emg {
    display: flex; flex-direction: column; align-items: center; gap: 3px;
    cursor: pointer; flex-shrink: 0;
    position: relative; top: -14px;
    text-decoration: none; /* Añadido para que el texto no se subraye al ser un link */
    -webkit-tap-highlight-color: transparent;
  }
  .bn-emg-circle {
    width: 52px; height: 52px; border-radius: 50%;
    background: var(--crit); /* Corregido: antes era --err */
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 4px 16px rgba(220,38,38,.4), 0 0 0 4px rgba(220,38,38,.1);
    transition: transform .12s, box-shadow .12s;
  }
  .bn-emg:active .bn-emg-circle { transform: scale(.91); box-shadow: 0 2px 8px rgba(220,38,38,.35); }
  .bn-emg-circle svg { width: 23px; height: 23px; stroke: #fff; stroke-width: 2.5; fill: none; stroke-linecap: round; }
  .bn-emg-lbl { font-size: 10px; font-weight: 700; color: var(--crit); } /* Corregido: antes era --err */

  /* Ocultar botón de volver en la barra superior en móviles */
  .back-btn { display: none; }
}
</style>
</head>
<body>

<header class="topbar">
  <div class="topbar-row">
    <a href="dashboard.php" class="t-brand">
      <img src="assets/logo.png" alt="Hochschild"
        onerror="this.style.display='none'">
      <div class="t-brand-text">
        <div class="t-brand-name">Hochschild <span>Mining</span></div>
        <div class="t-brand-sub">Control de Pasajeros · KPI Semanal</div>
      </div>
    </a>
    <div class="t-right">
      <a href="informe_ejecutivo.php?tipo=<?=h($tipo)?>&year_a=<?=$year_a?>&week_a=<?=$week_a?>"
         target="_blank" class="t-btn t-btn-gold t-hide-sm" title="Informe Gerencia">
        <i class="fas fa-chart-pie" style="font-size:12px"></i>
        <span class="btn-txt">Informe</span>
      </a>
      <button class="t-btn t-hide-sm" id="btnReload" title="Actualizar datos">
        <i class="fas fa-rotate-right" style="font-size:12px"></i>
        <span class="btn-txt">Actualizar <span id="cdown" style="opacity:.55;font-family:var(--mono);font-size:10px"></span></span>
      </button>
      <?php if($cache_hit): ?>
      <span class="cache-pill hit t-hide-sm"><i class="fas fa-bolt" style="font-size:10px"></i>Caché</span>
      <?php else: ?>
      <span class="cache-pill live t-hide-sm"><i class="fas fa-database" style="font-size:10px"></i>En vivo</span>
      <?php endif; ?>
<a href="dashboard.php" class="t-back" title="Volver al dashboard">
        <i class="fas fa-arrow-left"></i> <span>Volver</span>
      </a>
    </div>
  </div>
  <div class="topbar-accent"></div>
</header>

<div class="page">

<div class="cbar anim d1">
  <span class="flbl">Modo</span>
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
  <button class="btn" onclick="toggleRange()">
    <i class="fas fa-code-compare"></i> Comparar semanas
  </button>
  <button class="btn" onclick="exportExcel()">
    <i class="fas fa-file-excel"></i> Excel
  </button>
  <button class="btn btn-danger" id="btnExcl" onclick="toggleExcl()">
    <i class="fas fa-bus-slash"></i> Excluir buses
    <?php if(!empty($BUSES_EXCLUIDOS)): ?>
    <span style="background:var(--crit);color:#fff;border-radius:99px;font-size:8px;padding:1px 6px;margin-left:2px;font-family:var(--mono)"><?=count($BUSES_EXCLUIDOS)?></span>
    <?php endif; ?>
  </button>
</div>

<div class="rbar <?=$usar_b?'open':''?>" id="rangeBar">
  <span class="rlab rlab-a">A — <?=h($label_a)?></span>
  <span style="color:var(--ink-4);font-size:10px;font-weight:700">vs</span>
  <span class="rlab rlab-b">B</span>
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
  <a href="?tipo=<?=h($tipo)?>&year_a=<?=$year_a?>&week_a=<?=$week_a?>" class="btn">
    <i class="fas fa-xmark"></i> Quitar
  </a>
</div>

<div class="excl-bar" id="exclBar">
  <div class="excl-title">
    <i class="fas fa-bus-slash"></i> Buses excluidos del informe
    <span style="font-size:11px;font-weight:400;opacity:.6;text-transform:none;letter-spacing:0;margin-left:4px">— no aparecen en tabla ni KPIs</span>
  </div>
  <div class="excl-lista">
    <?php if(empty($BUSES_EXCLUIDOS)): ?>
    <span class="excl-empty">Ningún bus excluido</span>
    <?php else: ?>
    <?php foreach($BUSES_EXCLUIDOS as $idx => $bex): ?>
    <form method="POST" style="display:inline">
      <input type="hidden" name="accion" value="incluir_bus">
      <input type="hidden" name="bus_idx" value="<?=$idx?>">
      <?php foreach($_GET as $gk=>$gv): ?><input type="hidden" name="<?=h($gk)?>" value="<?=h($gv)?>"> <?php endforeach; ?>
      <span class="excl-chip"><?=h($bex)?><button type="submit" class="excl-chip-rm"><i class="fas fa-xmark" style="font-size:8px"></i></button></span>
    </form>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>
  <form method="POST" class="excl-form" id="exclForm">
    <input type="hidden" name="accion" value="excluir_bus">
    <?php foreach($_GET as $gk=>$gv): ?><input type="hidden" name="<?=h($gk)?>" value="<?=h($gv)?>"> <?php endforeach; ?>
    <div style="display:flex;gap:8px;align-items:center;margin-bottom:8px">
      <div style="position:relative;flex:1">
        <i class="fas fa-search" style="position:absolute;left:9px;top:50%;transform:translateY(-50%);font-size:9px;color:var(--ink-4);pointer-events:none"></i>
        <input type="text" id="exclSearch" placeholder="Buscar bus..."
          style="width:100%;padding:6px 10px 6px 28px;background:var(--surface);border:1px solid rgba(220,38,38,.3);border-radius:var(--r4);font-family:var(--mono);font-size:11px;color:var(--ink);outline:none"
          oninput="filtrarBuses(this.value)">
      </div>
      <button type="button" onclick="seleccionarTodos()" class="btn" style="font-size:11px;padding:5px 10px">
        <i class="fas fa-check-double"></i> Todos
      </button>
      <button type="button" onclick="deseleccionarTodos()" class="btn" style="font-size:11px;padding:5px 10px">
        <i class="fas fa-xmark"></i> Ninguno
      </button>
      <button type="submit" class="btn btn-danger" id="btnExclConfirm" disabled>
        <i class="fas fa-eye-slash"></i> Excluir <span id="exclCount" style="background:rgba(255,255,255,.25);border-radius:99px;padding:0 5px;margin-left:2px">0</span>
      </button>
    </div>
    <div id="exclList" style="max-height:160px;overflow-y:auto;border:1px solid rgba(220,38,38,.2);border-radius:var(--r4);background:var(--surface);padding:4px 0">
      <?php
      $disponibles = array_filter($todos_buses, fn($b) => !bus_excluido($b, $BUSES_EXCLUIDOS));
      if (empty($disponibles)): ?>
        <div style="padding:12px 14px;font-size:11px;color:var(--ink-4);font-style:italic;text-align:center">Todos los buses ya están excluidos</div>
      <?php else: ?>
      <?php foreach($disponibles as $tnb): ?>
      <label class="excl-cb-row" data-bus="<?=h(strtoupper($tnb))?>">
        <input type="checkbox" name="bus_nombres[]" value="<?=h($tnb)?>" onchange="actualizarContador()">
        <span><?=h($tnb)?></span>
      </label>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </form>
  <form method="POST" style="display:flex;align-items:center">
    <input type="hidden" name="accion" value="reset_excluidos">
    <?php foreach($_GET as $gk=>$gv): ?><input type="hidden" name="<?=h($gk)?>" value="<?=h($gv)?>"> <?php endforeach; ?>
    <button type="submit" class="excl-reset" onclick="return confirm('¿Restaurar lista por defecto?')">
      <i class="fas fa-rotate-left" style="font-size:9px;margin-right:3px"></i> Restaurar por defecto
    </button>
  </form>
  <div class="excl-hint">
    <i class="fas fa-circle-info" style="margin-right:4px"></i>
    Los cambios aplican solo durante esta sesión. Al cerrar sesión se restauran los valores por defecto.
  </div>
</div>

<div class="anim d2" style="display:flex;flex-direction:column;gap:10px">
<div class="kpi-strip">
      <?php
        $d1_sign=$delta_pct>0?'+':''; $d1_cls=$delta_pct>0?'delta-up':($delta_pct<0?'delta-down':'delta-flat');
        $d1_ico=$delta_pct>0?'fa-arrow-trend-up':($delta_pct<0?'fa-arrow-trend-down':'fa-minus');
        $bar1_c=$pct_global>=85?'var(--ok)':($pct_global>=50?'var(--gold)':'var(--crit)');
      ?>
      <div class="kpi-card">
        <div class="kpi-accent" style="background:<?=$bar1_c?>"></div>
        <div class="kpi-label"><i class="fas fa-gauge-high" style="margin-right:5px;opacity:.7"></i>Avance global</div>
        <div class="kpi-main"><span class="kpi-value" style="color:<?=$bar1_c?>"><?=$pct_global?>%</span><span class="kpi-delta <?=$d1_cls?>"><i class="fas <?=$d1_ico?>" style="font-size:8px"></i> <?=$d1_sign?><?=$delta_pct?>pp</span></div>
        <div class="kpi-sub"><?=number_format($sum_real_a)?> / <?=number_format($sum_teo)?> pax · sem. ant: <?=$pct_prev?>%</div>
        <div class="mini-bar-track"><div class="mini-bar-fill" style="width:<?=min($pct_global,100)?>%;background:<?=$bar1_c?>"></div></div>
      </div>
      <?php $d2_sign=$delta_real>=0?'+':''; $d2_cls=$delta_real>=0?'delta-up':'delta-down'; $d2_ico=$delta_real>=0?'fa-arrow-trend-up':'fa-arrow-trend-down'; ?>
      <div class="kpi-card">
        <div class="kpi-accent" style="background:var(--ok)"></div>
        <div class="kpi-label"><i class="fas fa-qrcode" style="margin-right:5px;opacity:.7"></i>Escaneados</div>
        <div class="kpi-main"><span class="kpi-value" style="color:var(--ok)"><?=number_format($sum_real_a)?></span><span class="kpi-delta <?=$d2_cls?>"><i class="fas <?=$d2_ico?>" style="font-size:8px"></i> <?=$d2_sign?><?=number_format($delta_real)?></span></div>
        <div class="kpi-sub">Sem. ant: <?=number_format($sum_real_prev)?></div>
        <div class="spark-row" id="sparkEsc"></div>
      </div>
      <?php $d3_sign=$delta_falt>0?'+':''; $d3_cls=$delta_falt<=0?'delta-up':'delta-down'; $d3_ico=$delta_falt<=0?'fa-arrow-trend-down':'fa-arrow-trend-up'; ?>
      <div class="kpi-card">
        <div class="kpi-accent" style="background:var(--crit)"></div>
        <div class="kpi-label"><i class="fas fa-user-clock" style="margin-right:5px;opacity:.7"></i>Faltantes</div>
        <div class="kpi-main"><span class="kpi-value" style="color:var(--crit)"><?=number_format($faltantes)?></span><span class="kpi-delta <?=$d3_cls?>"><i class="fas <?=$d3_ico?>" style="font-size:8px"></i> <?=$d3_sign?><?=number_format($delta_falt)?></span></div>
        <div class="kpi-sub">Sem. ant: <?=number_format($faltantes_prev)?></div>
        <div class="spark-row" id="sparkFalt"></div>
      </div>
</div>

<?php if($tipo==='ambos'): ?>
<?php
  $pie_s_pct=min($pct_sub_global,100);
  $pie_b_pct=min($pct_baj_global,100);
  $pie_s_col=$pct_sub_global>=85?'#16a34a':($pct_sub_global>=50?'#d97706':'#dc2626');
  $pie_b_col=$pct_baj_global>=85?'#16a34a':($pct_baj_global>=50?'#d97706':'#dc2626');
?>
<div class="pie-row">
  <div class="pie-card">
    <div class="pie-wrap">
      <svg viewBox="0 0 36 36">
        <circle cx="18" cy="18" r="14" fill="none" stroke="var(--border)" stroke-width="4.5"/>
        <circle cx="18" cy="18" r="14" fill="none" stroke="<?=$pie_s_col?>" stroke-width="4.5"
          stroke-dasharray="<?=$pie_s_pct?> <?=100-$pie_s_pct?>" stroke-linecap="round"/>
      </svg>
      <div class="pie-center">
        <span class="pie-pct" style="color:<?=$pie_s_col?>"><?=$pct_sub_global?>%</span>
        <span class="pie-mode">Subida</span>
      </div>
    </div>
    <div class="pie-info">
      <div class="pie-title" style="color:#1D9E75">
        <i class="fas fa-arrow-up" style="font-size:12px"></i>Subida
      </div>
      <div class="pie-stat">
        <span class="pie-lbl">Escaneados</span>
        <span class="pie-val" style="color:#1D9E75;font-weight:700;font-family:var(--mono)"><?=number_format($sum_real_sub)?></span>
      </div>
      <div class="pie-stat">
        <span class="pie-lbl">Faltantes</span>
        <span class="pie-val" style="color:var(--crit);font-weight:700;font-family:var(--mono)"><?=number_format($falt_sub)?></span>
      </div>
      <div class="pie-stat">
        <span class="pie-lbl" style="color:var(--ink-4)">Teórico</span>
        <span class="pie-val" style="font-family:var(--mono);font-weight:600;color:var(--ink-3)"><?=number_format($sum_teo_sub)?></span>
      </div>
    </div>
  </div>
  <div class="pie-card">
    <div class="pie-wrap">
      <svg viewBox="0 0 36 36">
        <circle cx="18" cy="18" r="14" fill="none" stroke="var(--border)" stroke-width="4.5"/>
        <circle cx="18" cy="18" r="14" fill="none" stroke="<?=$pie_b_col?>" stroke-width="4.5"
          stroke-dasharray="<?=$pie_b_pct?> <?=100-$pie_b_pct?>" stroke-linecap="round"/>
      </svg>
      <div class="pie-center">
        <span class="pie-pct" style="color:<?=$pie_b_col?>"><?=$pct_baj_global?>%</span>
        <span class="pie-mode">Bajada</span>
      </div>
    </div>
    <div class="pie-info">
      <div class="pie-title" style="color:#BA7517">
        <i class="fas fa-arrow-down" style="font-size:12px"></i>Bajada
      </div>
      <div class="pie-stat">
        <span class="pie-lbl">Escaneados</span>
        <span class="pie-val" style="color:#BA7517;font-weight:700;font-family:var(--mono)"><?=number_format($sum_real_baj)?></span>
      </div>
      <div class="pie-stat">
        <span class="pie-lbl">Faltantes</span>
        <span class="pie-val" style="color:var(--crit);font-weight:700;font-family:var(--mono)"><?=number_format($falt_baj)?></span>
      </div>
      <div class="pie-stat">
        <span class="pie-lbl" style="color:var(--ink-4)">Teórico</span>
        <span class="pie-val" style="font-family:var(--mono);font-weight:600;color:var(--ink-3)"><?=number_format($sum_teo_baj)?></span>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

</div><div class="collapsible anim d3">
  <div class="coll-hdr" onclick="toggleSection('ger')">
    <span class="coll-title"><i class="fas fa-chart-bar"></i>Indicadores gerenciales</span>
    <div class="coll-meta">
      <span class="coll-preview"><?=$pct_cobertura?>% flota · <?=$tend_direction==='up'?'↑ Mejorando':($tend_direction==='down'?'↓ Bajando':'→ Estable')?> · <?=$buses_85_count?> buses ≥85%</span>
      <div class="coll-ico" id="ico-ger"><i class="fas fa-chevron-down" style="font-size:9px"></i></div>
    </div>
  </div>
  <div class="coll-body" id="body-ger">

    <div class="ger-grid">
      <div class="ger-card">
        <div class="ger-accent" style="background:#7c3aed"></div>
        <div class="ger-lbl"><i class="fas fa-bus" style="margin-right:5px;opacity:.7"></i>Cobertura de flota</div>
        <div class="ger-main"><span class="ger-val" style="color:#7c3aed"><?=$pct_cobertura?>%</span><span class="kpi-delta delta-flat"><i class="fas fa-bus" style="font-size:8px"></i> <?=$buses_activos?> activos</span></div>
        <div class="ger-sub"><?=$buses_total_flota?> buses en flota · <?=$buses_inactivos?> sin operar</div>
        <div class="fleet-row">
          <div class="fleet-cell"><div class="fleet-n" style="color:#7c3aed"><?=$pct_cobertura?>%</div><div class="fleet-l">Cobertura</div></div>
          <div class="fleet-cell"><div class="fleet-n" style="color:var(--ok)"><?=$buses_activos?></div><div class="fleet-l">Activos</div></div>
          <div class="fleet-cell"><div class="fleet-n" style="color:var(--crit)"><?=$buses_inactivos?></div><div class="fleet-l">Sin operar</div></div>
        </div>
        <div class="mini-bar-track" style="margin-top:10px"><div class="mini-bar-fill" style="width:<?=$pct_cobertura?>%;background:#7c3aed"></div></div>
      </div>
      <?php
        $tend_lbl=$tend_direction==='up'?'↑ Mejorando':($tend_direction==='down'?'↓ Bajando':'→ Estable');
        $tend_col=$tend_direction==='up'?'var(--ok)':($tend_direction==='down'?'var(--crit)':'var(--ink-4)');
        $tend_dcls=$tend_direction==='up'?'delta-up':($tend_direction==='down'?'delta-down':'delta-flat');
        $racha_txt=$tend_racha>1?"$tend_racha sem. consecutivas":"1 sem.";
        $diff_tend=abs($pct_global-(isset($tend_semanas[count($tend_semanas)-2])?$tend_semanas[count($tend_semanas)-2]['pct']:$pct_global));
      ?>
      <div class="ger-card">
        <div class="ger-accent" style="background:#0891b2"></div>
        <div class="ger-lbl"><i class="fas fa-chart-line" style="margin-right:5px;opacity:.7"></i>Tendencia semanal</div>
        <div class="ger-main"><span class="ger-val" style="color:#0891b2"><?=$tend_lbl?></span><?php if($tend_racha>1): ?><span class="kpi-delta <?=$tend_dcls?>"><?=$racha_txt?></span><?php endif; ?></div>
        <div class="ger-sub">+<?=$diff_tend?>pp vs sem. anterior</div>
        <div class="spark-row" id="sparkTend"></div>
        <div class="spark-labs"><span><?=$tend_semanas[0]['sem']??''?></span><span><?=$tend_semanas[count($tend_semanas)-1]['sem']??''?></span></div>
      </div>
      <?php $b85_col=$buses_85_count>0?'var(--gold)':'var(--crit)'; $b85_dcls=$delta_85>=0?'delta-up':'delta-down'; $b85_sign=$delta_85>0?'+':''; ?>
      <div class="ger-card">
        <div class="ger-accent" style="background:var(--gold)"></div>
        <div class="ger-lbl"><i class="fas fa-star" style="margin-right:5px;opacity:.7"></i>Buses destacados ≥ 85%</div>
        <div class="ger-main"><span class="ger-val" style="color:<?=$b85_col?>"><?=$buses_85_count?></span><span class="kpi-delta <?=$b85_dcls?>"><?=$b85_sign?><?=$delta_85?> bus</span></div>
        <div class="ger-sub">Sem. ant: <?=$buses_85_prev?> · meta: 10+</div>
        <?php if($buses_85_count===0&&$bus_cercano): ?><div class="ger-note"><i class="fas fa-info-circle" style="margin-right:4px;opacity:.6"></i><?=h($bus_cercano)?></div><?php elseif($buses_85_count>0): ?><div class="mini-bar-track" style="margin-top:10px"><div class="mini-bar-fill" style="width:<?=min(round($buses_85_count/10*100),100)?>%;background:var(--gold)"></div></div><div style="font-size:10px;color:var(--ink-4);margin-top:4px"><?=$buses_85_count?> de 10 en meta</div><?php endif; ?>
      </div>
    </div>



    <?php if(!empty($top5_visible)): ?>
    <div style="margin-top:14px;padding-top:14px;border-top:1px solid var(--border)">
      <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:var(--ink-4);margin-bottom:10px;display:flex;align-items:center;gap:6px">
        <i class="fas fa-trophy" style="color:var(--gold)"></i>Top buses ≥ 70%
      </div>
      <div id="t5cVisible">
      <?php foreach($top5_visible as $ti=>$b):
        $tc=$b['modo']==='subida'?'#1D9E75':($b['modo']==='bajada'?'#BA7517':'var(--gold)');
        $tg=$b['modo']==='subida'?'t5c-sub':($b['modo']==='bajada'?'t5c-baj':'t5c-tot');
        $tl=$b['modo']==='subida'?'↑ Sub':($b['modo']==='bajada'?'↓ Baj':'⬡ Tot');
      ?>
      <div class="t5c-row">
        <div class="t5c-rank <?=$ti===0?'t5c-r1':''?>"><?=$ti+1?></div>
        <span class="t5c-name"><i class="fas fa-bus" style="font-size:9px;opacity:.3;margin-right:4px"></i><?=h($b['bus'])?></span>
        <span class="t5c-mode <?=$tg?>"><?=$tl?></span>
        <div class="t5c-bar"><div class="t5c-bar-f" style="width:<?=min($b['pct'],100)?>%;background:<?=$tc?>"></div></div>
        <span class="t5c-pct" style="color:<?=$tc?>"><?=$b['pct']?>%</span>
      </div>
      <?php endforeach; ?>
      </div>
      <?php if(!empty($top5_hidden)): ?>
      <div class="t5c-hidden" id="t5cHidden">
      <?php foreach($top5_hidden as $ti=>$b):
        $tc=$b['modo']==='subida'?'#1D9E75':($b['modo']==='bajada'?'#BA7517':'var(--gold)');
        $tg=$b['modo']==='subida'?'t5c-sub':($b['modo']==='bajada'?'t5c-baj':'t5c-tot');
        $tl=$b['modo']==='subida'?'↑ Sub':($b['modo']==='bajada'?'↓ Baj':'⬡ Tot');
      ?>
      <div class="t5c-row">
        <div class="t5c-rank"><?=$ti+6?></div>
        <span class="t5c-name"><i class="fas fa-bus" style="font-size:9px;opacity:.3;margin-right:4px"></i><?=h($b['bus'])?></span>
        <span class="t5c-mode <?=$tg?>"><?=$tl?></span>
        <div class="t5c-bar"><div class="t5c-bar-f" style="width:<?=min($b['pct'],100)?>%;background:<?=$tc?>"></div></div>
        <span class="t5c-pct" style="color:<?=$tc?>"><?=$b['pct']?>%</span>
      </div>
      <?php endforeach; ?>
      </div>
      <button class="t5c-expand" onclick="toggleTop5c()">
        <svg class="t5c-eico" id="t5cIco" width="11" height="11" viewBox="0 0 11 11" fill="none"><path d="M1.5 3.5l4 4 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
        <span id="t5cTxt">Ver <?=count($top5_hidden)?> buses más que superaron 70%</span>
      </button>
      <?php endif; ?>
    </div>
    <?php endif; ?>

  </div>
</div>

<div class="collapsible anim d4">
  <div class="coll-hdr" onclick="toggleSection('tbl')">
    <span class="coll-title"><i class="fas fa-table"></i>Desglose por bus — A-Z</span>
    <div class="coll-meta">
      <span class="coll-preview"><?=count($filas)?> buses · click en fila para mapa de asientos</span>
      <div class="coll-ico" id="ico-tbl"><i class="fas fa-chevron-down" style="font-size:9px"></i></div>
    </div>
  </div>
  <div class="coll-body" id="body-tbl">
  <div class="card anim d4">
      <div class="card-hdr">
        <span class="card-title"><i class="fas fa-bus"></i> Desglose por bus — A-Z</span>
        <span class="card-badge"><?=h($label_a)?></span>
      </div>

      <?php if(empty($filas)): ?>
      <div class="empty"><i class="fas fa-inbox"></i><p style="font-weight:700">Sin datos para esta semana</p></div>
      <?php else: ?>

      <div class="tscroll">
      <table class="tbl-uni" id="tblData">
        <thead>
          <tr>
            <th class="left" rowspan="2" style="vertical-align:bottom;min-width:160px">Bus</th>
            <th rowspan="2" style="vertical-align:bottom;border-right:1px solid var(--border)">Placa</th>
            <?php if($tipo !== 'bajada'): ?>
            <th colspan="2" class="grp-s" style="border-left:1px solid rgba(29,158,117,.15)">↑ Subida</th>
            <?php endif; ?>
            <?php if($tipo !== 'subida'): ?>
            <th colspan="2" class="grp-b" style="border-left:1px solid rgba(186,117,23,.15)">↓ Bajada</th>
            <?php endif; ?>
            <th class="grp-t" rowspan="2" style="border-left:1px solid var(--gold-ring);vertical-align:bottom;min-width:90px">Avance</th>
            <th class="grp-h" rowspan="2" style="border-left:1px solid rgba(55,138,221,.15);vertical-align:bottom">Hoy</th>
            <th rowspan="2" style="vertical-align:bottom;text-align:center">vs ant.</th>
          </tr>
          <tr>
            <?php if($tipo !== 'bajada'): ?>
            <th class="grp-s" style="border-left:1px solid rgba(29,158,117,.08)">Real</th>
            <th class="grp-s">Teo.</th>
            <?php endif; ?>
            <?php if($tipo !== 'subida'): ?>
            <th class="grp-b" style="border-left:1px solid rgba(186,117,23,.08)">Real</th>
            <th class="grp-b">Teo.</th>
            <?php endif; ?>
          </tr>
        </thead>
        <tbody>
        <?php
        $max_hoy = max(array_values($hoy_data) ?: [1]);
        foreach($filas as $idx => $f):
          $cs=$f['pct_sub']>=85?'ok':($f['pct_sub']>=50?'warn':'crit');
          $cb=$f['pct_baj']>=85?'ok':($f['pct_baj']>=50?'warn':'crit');
          $pct_show=($tipo==='ambos')?$f['pct_tot']:$f['pct_a'];
          $ct_show=$pct_show>=85?'ok':($pct_show>=50?'warn':'crit');
          $xt_show=$pct_show>=85?'var(--ok)':($pct_show>=50?'var(--warn)':'var(--crit)');
          $t_show=($tipo==='ambos')?$f['tt']:$f['t'];
          $r_show=($tipo==='ambos')?$f['rt']:$f['ra'];
          $r_prev_bus=$real_prev[$f['key']]??0;
          $pct_prev_bus=$f['t']>0?round(($r_prev_bus/$f['t'])*100):0;
          $delta_bus=$pct_show-$pct_prev_bus;
          $delta_cls=$delta_bus>0?'up':($delta_bus<0?'dn':'flat');
          $delta_txt=$delta_bus>0?"+{$delta_bus}pp":($delta_bus<0?"{$delta_bus}pp":'—');
          $delta_ico=$delta_bus>0?'fa-arrow-trend-up':($delta_bus<0?'fa-arrow-trend-down':'fa-minus');
          $n_hoy=$hoy_data[$f['key']]??0;
          $hoy_pct=$max_hoy>0?round(($n_hoy/$max_hoy)*100):0;
          $mdata=json_encode(['bus'=>$f['bus'],'placa'=>$f['placa'],'teo'=>$t_show,'real'=>$r_show,'pct'=>$pct_show,'det'=>$f['det'],'key'=>$f['key']]);
        ?>
        <tr onclick='openModal(<?=htmlspecialchars($mdata,ENT_QUOTES,'UTF-8')?>)' style="animation:up .25s ease <?=$idx*.018?>s both">
          <td class="left">
            <div class="dc">
              <span class="ddot dot-<?=$idx%10?>"></span>
              <span class="dname"><i class="fas fa-bus" style="font-size:9px;opacity:.35;margin-right:3px"></i><?=h($f['bus'])?></span>
              <?php if($f['alerta']): ?><span class="dalert"><i class="fas fa-triangle-exclamation"></i> <?=$f['dias_sin']?>d</span><?php endif; ?>
            </div>
            <div class="row-hint"><i class="fas fa-hand-pointer"></i> Mapa de asientos</div>
          </td>
          <td style="border-right:1px solid var(--border)">
            <?php if($f['placa']&&$f['placa']!=='N/A'): ?>
            <span style="font-family:var(--mono);font-size:10px;font-weight:600;color:var(--ink-3);background:var(--overlay);border:1px solid var(--border);padding:1px 6px;border-radius:3px"><?=h($f['placa'])?></span>
            <?php else: ?><span style="color:var(--ink-4);font-size:10px;font-family:var(--mono)">—</span><?php endif; ?>
          </td>
          <?php if($tipo!=='bajada'): ?>
          <td style="border-left:1px solid rgba(29,158,117,.06)"><span class="un <?=$cs?>"><?=$f['rs']?:'-'?></span></td>
          <td><span class="un gold"><?=$f['ts']?:'-'?></span></td>
          <?php endif; ?>
          <?php if($tipo!=='subida'): ?>
          <td style="border-left:1px solid rgba(186,117,23,.06)"><span class="un <?=$cb?>"><?=$f['rb2']?:'-'?></span></td>
          <td><span class="un gold"><?=$f['tb']?:'-'?></span></td>
          <?php endif; ?>
          <td style="border-left:1px solid var(--gold-ring)">
            <div class="u-mini"><div class="u-mt"><div class="u-mf" style="width:<?=min($pct_show,100)?>%;background:<?=$xt_show?>"></div></div><span class="u-mp un <?=$ct_show?>"><?=$pct_show?>%</span></div>
          </td>
          <td style="border-left:1px solid rgba(55,138,221,.12)">
            <?php if($n_hoy>0): ?>
            <div class="u-hoy"><span class="u-hoy-v"><?=$n_hoy?></span><div class="u-hoy-b"><div class="u-hoy-f" style="width:<?=$hoy_pct?>%"></div></div></div>
            <?php else: ?><span style="color:var(--ink-4);font-family:var(--mono);font-size:10px">—</span><?php endif; ?>
          </td>
          <td>
            <span class="u-delta <?=$delta_cls?>">
              <?php if($delta_bus!==0): ?><i class="fas <?=$delta_ico?>" style="font-size:8px"></i><?php endif; ?>
              <?=$delta_txt?>
            </span>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <td class="left" colspan="2" style="font-family:var(--font);font-size:12px;font-weight:700;color:var(--gold);text-transform:uppercase;border-right:1px solid var(--border)">Total general</td>
            <?php if($tipo!=='bajada'): ?>
            <td style="border-left:1px solid rgba(29,158,117,.08)"><span class="un ok"><?=number_format($sum_real_sub)?></span></td>
            <td><span class="un gold"><?=number_format($sum_teo_sub)?></span></td>
            <?php endif; ?>
            <?php if($tipo!=='subida'): ?>
            <td style="border-left:1px solid rgba(186,117,23,.08)"><span class="un warn"><?=number_format($sum_real_baj)?></span></td>
            <td><span class="un gold"><?=number_format($sum_teo_baj)?></span></td>
            <?php endif; ?>
            <td style="border-left:1px solid var(--gold-ring)">
              <?php $xtf=$pct_global>=85?'var(--ok)':($pct_global>=50?'var(--warn)':'var(--crit)'); $ctf=$pct_global>=85?'ok':($pct_global>=50?'warn':'crit'); ?>
              <div class="u-mini"><div class="u-mt"><div class="u-mf" style="width:<?=$pct_global?>%;background:<?=$xtf?>"></div></div><span class="u-mp un <?=$ctf?>"><?=$pct_global?>%</span></div>
            </td>
            <td style="border-left:1px solid rgba(55,138,221,.1)"><span class="un blue"><?=number_format($hoy_total)?></span></td>
            <td>
              <?php $dp_g=$pct_global-$pct_prev; $dp_cls=$dp_g>0?'up':($dp_g<0?'dn':'flat'); $dp_txt=$dp_g>0?"+{$dp_g}pp":($dp_g<0?"{$dp_g}pp":'—'); ?>
              <span class="u-delta <?=$dp_cls?>"><?=$dp_txt?></span>
            </td>
          </tr>
        </tfoot>
      </table>
      </div>

      <div class="leg">
        <div class="li"><span class="ld" style="background:var(--ok)"></span>≥85% Completado</div>
        <div class="li"><span class="ld" style="background:var(--warn)"></span>50–84% Progreso</div>
        <div class="li"><span class="ld" style="background:var(--crit)"></span>&lt;50% Crítico</div>
        <div class="li"><span class="ld" style="background:var(--crit);opacity:.6"></span>⚠ días sin escaneo</div>
        <div class="laut">
          <button id="arToggle" onclick="toggleAR()" class="ar-btn" title="Pausar/reanudar">
            <i class="fas fa-pause" id="arIcon" style="font-size:9px"></i>
            <span id="arLabel">Pausar</span>
          </button>
          <span class="ar-txt">
            <i class="fas fa-rotate" style="color:var(--gold);margin-right:3px"></i>
            <span id="cdown2">2m 0s</span>
          </span>
          <select id="arInterval" onchange="changeInterval(this.value)" class="ar-sel">
            <option value="30">30s</option>
            <option value="60">1 min</option>
            <option value="120" selected>2 min</option>
            <option value="300">5 min</option>
          </select>
        </div>
      </div>

      <?php endif; ?>
    </div></div>
</div>

</div></div><div class="moverlay" id="mOverlay" onclick="if(event.target===this)closeM()">
  <div class="modal">
    <div class="mhdr">
      <div>
        <div class="mtitle" id="mTit">—</div>
        <div class="mhdr-sub" id="mPlaca"></div>
      </div>
      <button class="mclose" onclick="closeM()"><i class="fas fa-xmark"></i></button>
    </div>
    <div class="mbody">
      <div class="msrow">
        <div class="ms"><div class="msv g" id="mTeo">—</div><div class="msl">Teórico</div></div>
        <div class="ms"><div class="msv" id="mReal">—</div><div class="msl">Real</div></div>
        <div class="ms"><div class="msv" id="mPct">—</div><div class="msl">Avance</div></div>
      </div>
      <div class="mtabs">
        <div class="mtab act" id="tabMapa" onclick="switchTab('mapa')">
          <i class="fas fa-bus" style="margin-right:4px;font-size:7px"></i>Mapa asientos
        </div>
        <div class="mtab" id="tabDias" onclick="switchTab('dias')">
          <i class="fas fa-calendar-week" style="margin-right:4px;font-size:7px"></i>Esta semana
        </div>
        <div class="mtab" id="tabTrend" onclick="switchTab('trend')">
          <i class="fas fa-chart-line" style="margin-right:4px;font-size:7px"></i>Tendencia
        </div>
      </div>
      <div id="panelMapa">
        <div id="seatContent"><div class="seat-loading"><i class="fas fa-spinner fa-spin"></i> Cargando asientos...</div></div>
        <div class="pax-detail" id="paxDetail">
          <div class="pax-name" id="paxName">—</div>
          <div class="pax-meta" id="paxMeta">—</div>
        </div>
      </div>
      <div id="panelDias" style="display:none">
        <div class="msec-title">Detalle día a día</div>
        <div id="mDays"></div>
      </div>
      <div id="panelTrend" style="display:none">
        <div class="msec-title">Últimas 5 semanas</div>
        <div id="mTrend"></div>
      </div>
    </div>
  </div>
</div>

<script>
/* ── DATOS PHP → JS ───────────────────────────────────────── */
const SPARK_A    = <?=$js_spark_a?>;
const SPARK_PREV = <?=$js_spark_prev?>;
const SPARK_ESC  = <?=$js_spark_esc?>;
const SPARK_FALT = <?=$js_spark_falt?>;
const NOMS       = <?=$js_noms?>;
const SPARK_SUB  = <?=$js_spark_sub?>;
const SPARK_BAJ  = <?=$js_spark_baj?>;
const AMBOS_DATA = <?=$js_ambos_data?>;
const MODO       = '<?=$tipo?>';
const TREND_MAP  = <?=$js_trend_map?>;
const TEND_DATA  = <?=$js_tend?>;
const TEND_LABS  = <?=$js_tend_labs?>;

/* ── BARRAS COMPARATIVAS DÍA A DÍA ───────────────────────── */
(function(){
  const maxV = Math.max(...SPARK_A, ...SPARK_PREV, 1);
  const cont = document.getElementById('daybarCols');
  if (!cont) return;
  cont.innerHTML = NOMS.map((nom, i) => {
    const hA   = Math.round((SPARK_A[i]    / maxV) * 32);
    const hP   = Math.round((SPARK_PREV[i] / maxV) * 32);
    return `<div class="daybar-col">
      <div class="daybar-pair">
        <div class="daybar-bar" style="height:${Math.max(hA,2)}px;background:var(--gold);border-radius:2px 2px 0 0"></div>
        <div class="daybar-bar" style="height:${Math.max(hP,2)}px;background:var(--overlay-2);border:1px solid var(--border);border-bottom:none;border-radius:2px 2px 0 0"></div>
      </div>
      <div class="daybar-nom">${nom}</div>
    </div>`;
  }).join('');
})();

/* ── SPARKLINES MINI (cards Escaneados y Faltantes) ───────── */
(function(){
  function makeSpark(id, vals, color) {
    const el = document.getElementById(id);
    if (!el) return;
    const mx = Math.max(...vals, 1);
    el.innerHTML = vals.map(v =>
      `<div class="spark-bar" style="height:${Math.max(Math.round((v/mx)*22),2)}px;background:${color}"></div>`
    ).join('');
  }
  makeSpark('sparkEsc',  SPARK_ESC,  'var(--ok)');
  makeSpark('sparkFalt', SPARK_FALT, 'var(--crit)');

  /* Sparkline tendencia semanal */
  (function(){
    const el = document.getElementById('sparkTend');
    if (!el || !TEND_DATA || !TEND_DATA.length) return;
    const mx = Math.max(...TEND_DATA, 1);
    el.innerHTML = TEND_DATA.map((v,i) => {
      const h  = Math.max(Math.round((v/mx)*26), 2);
      const last = i === TEND_DATA.length - 1;
      return `<div class="spark-b" style="height:${h}px;background:${last?'#0891b2':'var(--border)'}"></div>`;
    }).join('');
  })();

  /* Sparklines modo ambos */
  if (MODO === 'ambos') {
    function makeMcSpark(id, vals, color) {
      const el = document.getElementById(id);
      if (!el) return;
      const mx = Math.max(...vals, 1);
      el.innerHTML = vals.map(v =>
        `<div class="mc-sbar" style="height:${Math.max(Math.round((v/mx)*18),2)}px;background:${color}"></div>`
      ).join('');
    }
    makeMcSpark('mcSparkSub', SPARK_SUB, '#1D9E75');
    makeMcSpark('mcSparkBaj', SPARK_BAJ, '#BA7517');
  }
})();

/* ── AUTO-REFRESH — pausable, intervalo persistente ─────── */
const AR = (function(){
  /* Restaurar estado desde sessionStorage para sobrevivir recargas */
  const SS_KEY = 'ar_state';
  const saved  = JSON.parse(sessionStorage.getItem(SS_KEY) || '{}');

  let total  = saved.total  ?? 120;   // default 2 min
  let paused = saved.paused ?? false;
  let secs   = total;
  let timer  = null;

  const cd     = document.getElementById('cdown');
  const cd2    = document.getElementById('cdown2');
  const toggle = document.getElementById('arToggle');
  const icon   = document.getElementById('arIcon');
  const label  = document.getElementById('arLabel');
  const sel    = document.getElementById('arInterval');

  /* Sincronizar el select con el valor guardado */
  if (sel) {
    const opt = sel.querySelector(`option[value="${total}"]`);
    if (opt) opt.selected = true;
  }

  function save() {
    sessionStorage.setItem(SS_KEY, JSON.stringify({ total, paused }));
  }

  function fmt(s) {
    if (s >= 60) return `${Math.floor(s/60)}m ${s%60}s`;
    return `${s}s`;
  }

  function updateUI() {
    const txt = paused ? 'Pausado' : `Refresca en ${fmt(secs)}`;
    if (cd)  cd.textContent  = paused ? '' : `(${fmt(secs)})`;
    if (cd2) cd2.textContent = paused ? '—' : fmt(secs);
    if (icon)   icon.className    = paused ? 'fas fa-play' : 'fas fa-pause';
    if (label)  label.textContent = paused ? 'REANUDAR'    : 'PAUSAR';
    if (toggle) toggle.style.color = paused ? 'var(--warn)' : 'var(--ink-4)';
  }

  function doRefresh() {
    save();
    /* Recargar sin flush para no mostrar el spinner del redirect */
    location.reload();
  }

  function tick() {
    if (paused) return;
    if (secs > 0) secs--;
    updateUI();
    if (secs <= 0) doRefresh();
  }

  function start() {
    if (timer) clearInterval(timer);
    timer = setInterval(tick, 1000);
    updateUI();
  }

  function pause()  { paused = true;  save(); updateUI(); }
  function resume() { paused = false; secs = total; save(); updateUI(); }

  start();

  return {
    toggle()     { paused ? resume() : pause(); },
    setInterval(v) {
      total = parseInt(v, 10);
      secs  = total;
      save();
      updateUI();
    }
  };
})();

function toggleAR()       { AR.toggle(); }
function changeInterval(v){ AR.setInterval(v); }

/* ── BOTÓN RELOAD con animación ──────────────────────────── */
document.getElementById('btnReload').addEventListener('click', function(){
  const icon = this.querySelector('i');
  icon.style.transition = 'transform .4s ease';
  icon.style.transform  = 'rotate(360deg)';
  /* Añadir ?flush=1 para forzar recarga desde BD */
  setTimeout(() => {
    const url = new URL(location.href);
    url.searchParams.set('flush','1');
    location.href = url.toString();
  }, 350);
});

/* ── TOGGLE RANGO ─────────────────────────────────────────── */
function toggleRange(){ document.getElementById('rangeBar').classList.toggle('open'); }

/* ── PANEL BUSES EXCLUIDOS ── */
function toggleExcl(){
  const p = document.getElementById('exclBar');
  const b = document.getElementById('btnExcl');
  p.classList.toggle('open');
  if(p.classList.contains('open')){
    b.style.background = 'var(--crit)';
    b.style.color = '#fff';
    setTimeout(()=>{ const s=document.getElementById('exclSearch'); if(s) s.focus(); },120);
  } else {
    b.style.background = 'var(--crit-bg)';
    b.style.color = 'var(--crit)';
  }
}
function filtrarBuses(q){
  const term = q.toUpperCase().trim();
  document.querySelectorAll('.excl-cb-row').forEach(row => {
    const bus = row.dataset.bus || '';
    row.classList.toggle('hidden', term !== '' && !bus.includes(term));
  });
}
function actualizarContador(){
  const checks = document.querySelectorAll('#exclForm input[type=checkbox]:checked');
  const n = checks.length;
  const cnt = document.getElementById('exclCount');
  const btn = document.getElementById('btnExclConfirm');
  if(cnt) cnt.textContent = n;
  if(btn) btn.disabled = n === 0;
}
function seleccionarTodos(){
  document.querySelectorAll('.excl-cb-row:not(.hidden) input[type=checkbox]')
    .forEach(cb => cb.checked = true);
  actualizarContador();
}
function deseleccionarTodos(){
  document.querySelectorAll('#exclForm input[type=checkbox]')
    .forEach(cb => cb.checked = false);
  actualizarContador();
}


/* ── MODAL ────────────────────────────────────────────────── */
const DIAS = ['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'];
let _activeTab = 'mapa';
let _curBus    = '';
let _seatsLoaded = false;

function switchTab(tab) {
  _activeTab = tab;
  ['mapa','dias','trend'].forEach(t => {
    const btn   = document.getElementById('tab' + t.charAt(0).toUpperCase() + t.slice(1));
    const panel = document.getElementById('panel' + t.charAt(0).toUpperCase() + t.slice(1));
    if (btn)   btn.classList.toggle('act', t === tab);
    if (panel) panel.style.display = t === tab ? '' : 'none';
  });
  if (tab === 'mapa' && _curBus && !_seatsLoaded) loadSeats(_curBus);
}

function colPct(p){ return p>=85?'var(--ok)':p>=50?'var(--warn)':'var(--crit)'; }

/* ── MAPA DE ASIENTOS ─────────────────────────────────────── */
function loadSeats(bus) {
  document.getElementById('seatContent').innerHTML =
    '<div class="seat-loading"><i class="fas fa-spinner fa-spin"></i> Cargando asientos...</div>';
  document.getElementById('paxDetail').className = 'pax-detail';

  const url = `?ajax=asientos&bus=${encodeURIComponent(bus)}&year_a=<?=$year_a?>&week_a=<?=$week_a?>`;
  fetch(url)
    .then(r => {
      if (!r.ok) throw new Error('HTTP ' + r.status);
      return r.text();
    })
    .then(txt => {
      let data;
      try { data = JSON.parse(txt); }
      catch(e) {
        console.error('AJAX response (not JSON):', txt.substring(0,500));
        document.getElementById('seatContent').innerHTML =
          `<div class="seat-loading" style="color:var(--crit)"><i class="fas fa-triangle-exclamation"></i> Error del servidor — ver consola (F12)</div>`;
        return;
      }
      if (data._error) {
        console.error('DB error:', data._error);
        document.getElementById('seatContent').innerHTML =
          `<div class="seat-loading" style="color:var(--crit)"><i class="fas fa-triangle-exclamation"></i> Error BD: ${data._error}</div>`;
        return;
      }
      renderSeats(data);
      _seatsLoaded = true;
    })
    .catch(err => {
      console.error('Fetch error:', err);
      document.getElementById('seatContent').innerHTML =
        `<div class="seat-loading" style="color:var(--crit)"><i class="fas fa-triangle-exclamation"></i> Error: ${err.message}</div>`;
    });
}

// Agrega "extrasCount = 0" al final de los parámetros
function buildBusGrid(data, prefix, modoLabel, modoColor, extrasCount = 0) {
  const cols = ['A','B','C','D'];
  const total    = 30;
  // Sumamos los extras al contador de escaneados verdes
  const escCount = Object.values(data).filter(x => x.escaneado).length + extrasCount; 
  const asigCount= Object.keys(data).length;
  // Los no escaneados siguen siendo solo los asignados que faltan
  const sinEsc   = asigCount - Object.values(data).filter(x => x.escaneado).length; 

  let html = `<div class="bus-mode-title ${prefix}">
    <i class="fas fa-arrow-${prefix==='sub'?'up':'down'}" style="font-size:8px"></i>${modoLabel}
  </div>
  <div class="bus-mini-stats">
    <span class="bms ok">✓ ${escCount}</span>
    <span class="bms cr">✗ ${sinEsc}</span>
  </div>
  <div class="bus-shell">
    <div class="bus-front"><i class="fas fa-steering-wheel" style="font-size:8px"></i> Frente</div>
    <div class="seat-grid">`;

  for (let fila = 1; fila <= 8; fila++) {
    html += `<div class="seat-row"><span class="seat-fnum">${fila}</span>`;
    for (let col = 0; col < 4; col++) {
      const num = (fila-1)*4 + col + 1;
      if (num > 30) break;
      if (col === 2) html += `<span class="seat-aisle">│</span>`;
      const info     = data[num];
      const asignado = !!info;
      const ocupado  = asignado && info.escaneado;
      const nombre   = info ? info.nombre : '';
      const dia      = info ? info.dia    : '';
      const lbl      = `${num}${cols[col]}`;
      const seatCls  = ocupado ? 'ocup' : (asignado ? 'asig' : 'vac');
      const tipTxt   = ocupado
        ? `${nombre} · ✓ ${dia}`
        : asignado
          ? `${nombre} · Sin escanear`
          : `Libre`;
      html += `<div class="seat ${seatCls}" id="seat${num}${prefix}"
          onclick="selectSeat(${num},'${cols[col]}',${JSON.stringify(ocupado)},${JSON.stringify(asignado)},${JSON.stringify(nombre)},${JSON.stringify(dia)},${JSON.stringify(modoLabel)})">
        <span style="font-size:9px">${ocupado?'🟢':asignado?'🔴':'⬜'}</span>
        <span class="seat-lbl">${lbl}</span>
        <div class="seat-tip">${tipTxt}</div>
      </div>`;
    }
    html += `</div>`;
  }
  html += `</div></div>`;
  return html;
}

function renderSeats(data) {
  const sub = data.subida || {};
  const baj = data.bajada || {};
  const exSub = data.extras_sub || [];
  const exBaj = data.extras_baj || [];

  const total   = 30;
  // Sumamos extras a los totales reales
  const escSub  = Object.values(sub).filter(x=>x.escaneado).length + exSub.length;
  const escBaj  = Object.values(baj).filter(x=>x.escaneado).length + exBaj.length;
  const escTot  = escSub + escBaj;
  
  const sinSub  = Object.keys(sub).length - Object.values(sub).filter(x=>x.escaneado).length;
  const sinBaj  = Object.keys(baj).length - Object.values(baj).filter(x=>x.escaneado).length;
  const sinTot  = sinSub + sinBaj;
  
  const pctTot  = Math.round((escTot / (total*2)) * 100);

  let html = `<div class="seat-count">
    <div class="sc-item"><div class="sc-v" style="color:var(--ok)">${escTot}</div><div class="sc-l">Escaneados</div></div>
    <div class="sc-item"><div class="sc-v" style="color:var(--crit)">${sinTot}</div><div class="sc-l">Sin escanear</div></div>
    <div class="sc-item"><div class="sc-v" style="color:${colPct(pctTot)}">${pctTot}%</div><div class="sc-l">Avance</div></div>
  </div>`;

  html += `<div class="dual-wrap">
    <div class="bus-col">${buildBusGrid(sub,'sub','Subida','#1D9E75', exSub.length)}</div>
    <div class="bus-col">${buildBusGrid(baj,'baj','Bajada','#BA7517', exBaj.length)}</div>
  </div>`;

  // --- NUEVA SECCIÓN: Renderizador visual de Pasajeros Extra ---
  const renderEx = (list, title, color) => {
      if(!list.length) return '';
      let h = `<div style="flex:1; background:var(--surface); border:1px solid ${color}40; border-radius:8px; padding:10px; margin-top:10px">
          <div style="font-size:9px; font-weight:800; color:${color}; margin-bottom:8px; text-transform:uppercase">
              <i class="fas fa-user-plus"></i> Extras ${title} (${list.length})
          </div>
          <div style="display:flex; flex-direction:column; gap:5px">`;
      list.forEach(ex => {
          h += `<div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--overlay-2); padding-bottom:4px">
              <div style="display:flex; flex-direction:column">
                  <span style="font-size:10px; font-weight:700; color:var(--ink)">${ex.nombre}</span>
                  <span style="font-size:8px; font-family:var(--mono); color:var(--ink-4)">DNI: ${ex.dni}</span>
              </div>
              <span style="font-size:9px; font-family:var(--mono); color:${color}; background:${color}15; padding:2px 6px; border-radius:4px">✓ ${ex.dia}</span>
          </div>`;
      });
      h += `</div></div>`;
      return h;
  };

  // Si hay extras de subida o bajada, los dibujamos
  if (exSub.length > 0 || exBaj.length > 0) {
      html += `<div style="display:flex; gap:10px; margin-top:5px; flex-wrap:wrap">
          ${renderEx(exSub, 'Subida', '#1D9E75')}
          ${renderEx(exBaj, 'Bajada', '#BA7517')}
      </div>`;
  }

  html += `<div class="seat-legend" style="margin-top:12px">
    <div class="sl-item"><div class="sl-box" style="background:#F0F8F2;border:1.5px solid #2E8B40"></div>Escaneado</div>
    <div class="sl-item"><div class="sl-box" style="background:#FDF2F2;border:1.5px solid #CC3333"></div>Sin escanear</div>
    <div class="sl-item"><div class="sl-box" style="background:var(--surface);border:1.5px solid var(--border)"></div>Libre</div>
  </div>`;

  document.getElementById('seatContent').innerHTML = html;
}

function selectSeat(num, col, ocupado, asignado, nombre, dia, modo) {
  /* Deseleccionar todos */
  document.querySelectorAll('.seat').forEach(s => s.classList.remove('sel'));
  const prefix = modo === 'Subida' ? 'sub' : 'baj';
  const el = document.getElementById(`seat${num}${prefix}`);
  if (el) el.classList.add('sel');
  const panel  = document.getElementById('paxDetail');
  const nameEl = document.getElementById('paxName');
  const metaEl = document.getElementById('paxMeta');
  panel.className = 'pax-detail show';
  const modoIcon = modo === 'Subida' ? '↑' : '↓';
  if (ocupado) {
    nameEl.textContent = nombre || 'Sin nombre';
    nameEl.style.color = 'var(--ok)';
    metaEl.textContent = `${modoIcon} ${modo} · Asiento ${num}${col} · ✓ Escaneado · ${dia}`;
  } else if (asignado) {
    nameEl.textContent = nombre || 'Sin nombre';
    nameEl.style.color = 'var(--crit)';
    metaEl.textContent = `${modoIcon} ${modo} · Asiento ${num}${col} · ✗ No escaneado esta semana`;
  } else {
    nameEl.textContent = `Asiento ${num}${col} — libre`;
    nameEl.style.color = 'var(--ink-4)';
    metaEl.textContent = `${modoIcon} ${modo} · Sin pasajero asignado`;
  }
}

/* ── TENDENCIA ────────────────────────────────────────────── */
function renderTrend(key) {
  const pts = TREND_MAP[key] || [];
  if (!pts.length) {
    document.getElementById('mTrend').innerHTML =
      '<div style="font-size:9px;color:var(--ink-4);padding:12px 0;text-align:center">Sin datos de semanas anteriores</div>';
    return;
  }
  const isLast = (i) => i === pts.length - 1;
  document.getElementById('mTrend').innerHTML = pts.map((pt, i) => {
    const col = colPct(pt.pct);
    const act = isLast(i);
    return `<div class="trend-row${act?' trend-act':''}">
      <span class="trend-sem">${pt.sem}${act?' ★':''}</span>
      <div class="trend-bar"><div class="trend-fill" style="width:${pt.pct}%;background:${col}"></div></div>
      <span class="trend-pct" style="color:${col}">${pt.pct}%</span>
      <span class="trend-real">${pt.real.toLocaleString()}</span>
    </div>`;
  }).join('');
}

/* ── OPEN / CLOSE ─────────────────────────────────────────── */
function openModal(d){
  _curBus      = d.bus;
  _seatsLoaded = false;
  document.getElementById('mTit').textContent = d.bus;
  const pEl = document.getElementById('mPlaca');
  pEl.textContent = (d.placa && d.placa !== 'N/A') ? d.placa : '';
  document.getElementById('mTeo').textContent = d.teo.toLocaleString();
  const col = colPct(d.pct);
  const re = document.getElementById('mReal'); re.textContent = d.real.toLocaleString(); re.style.color = col;
  const pe = document.getElementById('mPct');  pe.textContent = d.pct+'%';                pe.style.color = col;

  /* Tab días */
  const mx = Math.max(...d.det.map(x=>x.n), 1);
  let h = '';
  d.det.forEach((x,i) => {
    if(x.fut){ h += `<div class="mfut">${DIAS[i]} — día futuro</div>`; return; }
    const p = Math.round((x.n/mx)*100);
    h += `<div class="mday">
      <span class="mn">${DIAS[i]}</span>
      <span class="mf">${x.fecha}</span>
      <div class="mbw"><div class="mbb" style="width:${p}%"></div></div>
      <span class="mv${x.n===0?' z':''}">${x.n>0?x.n:'—'}</span>
    </div>`;
  });
  document.getElementById('mDays').innerHTML = h;

  /* Tab tendencia */
  if (d.key) renderTrend(d.key);

  /* Abrir siempre en mapa y cargar asientos */
  switchTab('mapa');
  loadSeats(d.bus);
  document.getElementById('mOverlay').classList.add('open');
}
function closeM(){
  document.getElementById('mOverlay').classList.remove('open');
  _curBus = ''; _seatsLoaded = false;
}

/* ── SECCIONES COLAPSABLES (Acordeón inteligente) ── */
function toggleSection(id) {
  const body = document.getElementById('body-' + id);
  const ico  = document.getElementById('ico-' + id);
  if (!body) return;
  
  const wasOpen = body.classList.contains('open');
  
  // 1. Cerrar TODOS los paneles primero (Efecto Acordeón)
  document.querySelectorAll('.coll-body').forEach(b => b.classList.remove('open'));
  document.querySelectorAll('.coll-ico').forEach(i => i.classList.remove('open'));
  
  // 2. Si el que tocaste NO estaba abierto, ábrelo
  if (!wasOpen) {
    body.classList.add('open');
    if (ico) ico.classList.add('open');
    
    // 3. Hacer auto-scroll suave para que la pantalla no se quede "trabada"
    setTimeout(() => {
      body.parentElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }, 150);
  }
}

/* ── TOP 5 COMPACTO TOGGLE ── */
function toggleTop5c() {
  const hr  = document.getElementById('t5cHidden');
  const ico = document.getElementById('t5cIco');
  const txt = document.getElementById('t5cTxt');
  if (!hr) return;
  const open = hr.classList.toggle('open');
  if (ico) ico.classList.toggle('open', open);
  const count = hr.querySelectorAll('.t5c-row').length;
  if (txt) txt.textContent = open ? 'Ocultar' : 'Ver ' + count + ' buses más que superaron 70%';
}

/* ── TOP 5 TOGGLE ── */
function toggleTop5(){
  const hr  = document.getElementById('top5Hidden');
  const ico = document.getElementById('t5Ico');
  const txt = document.getElementById('t5Txt');
  if (!hr) return;
  const open = hr.classList.toggle('open');
  ico.classList.toggle('open', open);
  const count = hr.querySelectorAll('.top5-row').length;
  txt.textContent = open ? 'Ocultar' : `Ver ${count} buses más que superaron 70%`;
}
document.addEventListener('keydown', e => { if(e.key==='Escape') closeM(); });

/* ── EXPORT EXCEL ─────────────────────────────────────────── */
function exportExcel(){
  const t = document.getElementById('tblData');
  if(!t) return;
  const wb = XLSX.utils.book_new();
  const ws = XLSX.utils.table_to_sheet(t);
  ws['!cols'] = [{wch:30},{wch:12},{wch:12},{wch:14},{wch:14},{wch:14}];
  XLSX.utils.book_append_sheet(wb, ws, 'KPI Buses');
  XLSX.writeFile(wb, `Hochschild_KPI_Sem<?=$week_a?>_<?=$year_a?>.xlsx`);
}

/* ── FUNCIONES BOTONES NAVEGACIÓN INFERIOR ───────────────── */
function confirmarEmergencia() {
  alert("Alerta enviada correctamente.");
  // Aquí puedes colocar tu lógica real de emergencias
}
function generarInforme() {
  // Abre el informe de gerencia en una nueva pestaña manteniendo los parámetros de la URL
  window.open("informe_ejecutivo.php?tipo=<?=h($tipo)?>&year_a=<?=$year_a?>&week_a=<?=$week_a?>", "_blank");
}

</script>

<nav class="bottom-nav">

  <a class="bn-item" href="dashboard.php">
    <div class="bn-ico">
      <svg viewBox="0 0 24 24">
        <rect x="3" y="3" width="7" height="7" rx="1.5"/>
        <rect x="14" y="3" width="7" height="7" rx="1.5"/>
        <rect x="3" y="14" width="7" height="7" rx="1.5"/>
        <rect x="14" y="14" width="7" height="7" rx="1.5"/>
      </svg>
    </div>
    <span class="bn-lbl">Inicio</span>
  </a>

  <a class="bn-item" href="https://hocmind.com/mina/buses.php">
    <div class="bn-ico">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
        <path d="M4 14.5V5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v9.5"/>
        <path d="M4 14.5v3.5a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3.5"/>
        <path d="M4 14.5h16"/>
        <path d="M6 18h.01"/>
        <path d="M18 18h.01"/>
        <path d="M8 22v-2"/>
        <path d="M16 22v-2"/>
      </svg>
    </div>
    <span class="bn-lbl">Buses</span>
  </a>

  <a class="bn-emg" id="btnEmgNav" href="https://hocmind.com/IRIS/">
    <div class="bn-emg-circle">
      <svg viewBox="0 0 24 24">
        <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
        <line x1="12" y1="9"  x2="12"   y2="13"/>
        <line x1="12" y1="17" x2="12.01" y2="17"/>
      </svg>
    </div>
    <span class="bn-emg-lbl">Alerta</span>
  </a>

  <button class="bn-item" onclick="document.getElementById('btnReload').click();">
    <div class="bn-ico">
      <svg viewBox="0 0 24 24">
        <path d="M23 4v6h-6"/>
        <path d="M1 20v-6h6"/>
        <path d="M3.51 9a9 9 0 0114.85-3.36L23 10"/>
        <path d="M1 14l4.64 4.36A9 9 0 0020.49 15"/>
      </svg>
    </div>
    <span class="bn-lbl">Actualizar</span>
  </button>

  <button class="bn-item active" onclick="generarInforme()">
    <div class="bn-ico">
      <svg viewBox="0 0 24 24">
        <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
        <polyline points="14 2 14 8 20 8"/>
        <line x1="16" y1="13" x2="8" y2="13"/>
        <line x1="16" y1="17" x2="8" y2="17"/>
        <polyline points="10 9 9 9 8 9"/>
      </svg>
    </div>
    <span class="bn-lbl">Informe</span>
  </button>

</nav>

</body>
</html>