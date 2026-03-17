<?php
/**
 * historial.php
 * ✅ CORREGIDO:
 *    1. Div .top no cerrado → corregido
 *    2. La lógica y estilos se mantienen intactos
 */

require __DIR__ . "/config.php";

session_start();
if (!isset($_SESSION['usuario'])) { header("Location: index.php"); exit(); }

function h($s) { return htmlspecialchars($s ?? "", ENT_QUOTES, "UTF-8"); }

// --- FILTROS ---
$buscar  = isset($_GET["q"]) ? trim($_GET["q"]) : "";
$evento  = isset($_GET["evento"]) ? trim($_GET["evento"]) : "";
$fecha_d = isset($_GET["d"]) ? trim($_GET["d"]) : "";
$fecha_h = isset($_GET["h"]) ? trim($_GET["h"]) : "";

if ($fecha_d === "" && $fecha_h === "") {
    $hoy     = date("Y-m-d");
    $fecha_d = $hoy;
    $fecha_h = $hoy;
}

// --- PAGINACIÓN ---
$page    = isset($_GET["p"]) ? max(1, (int)$_GET["p"]) : 1;
$perPage = 50;
$offset  = ($page - 1) * $perPage;

// --- WHERE DINÁMICO ---
$where  = ["1=1"];
$params = [];
$types  = "";

if ($fecha_d !== "") {
    $where[]  = "fecha >= CONCAT(?, ' 00:00:00')";
    $params[] = $fecha_d;
    $types   .= "s";
}
if ($fecha_h !== "") {
    $where[]  = "fecha <= CONCAT(?, ' 23:59:59')";
    $params[] = $fecha_h;
    $types   .= "s";
}
if ($evento !== "") {
    $where[]  = "evento = ?";
    $params[] = $evento;
    $types   .= "s";
}
if ($buscar !== "") {
    $where[]  = "(dni LIKE ? OR nombre LIKE ? OR usuario_scanner LIKE ?)";
    $lk       = "%$buscar%";
    $params[] = $lk; $params[] = $lk; $params[] = $lk;
    $types   .= "sss";
}

$whereSql = "WHERE " . implode(" AND ", $where);

// Total para paginación
$sqlCount = "SELECT COUNT(*) FROM registros $whereSql";
$stmtC    = $mysqli->prepare($sqlCount);
if ($types !== "") { $stmtC->bind_param($types, ...$params); }
$stmtC->execute();
$stmtC->bind_result($total);
$stmtC->fetch();
$stmtC->close();

$totalPages = max(1, (int)ceil($total / $perPage));

// Registros
$sql = "SELECT *, 
        DATE_FORMAT(fecha, '%Y-%m-%d') as dia_marcado, 
        DATE_FORMAT(fecha, '%H:%i:%s') as hora_marcado 
        FROM registros 
        $whereSql 
        ORDER BY fecha DESC, id DESC 
        LIMIT ? OFFSET ?";

$stmt       = $mysqli->prepare($sql);
$finalTypes  = $types . "ii";
$finalParams = array_merge($params, [$perPage, $offset]);
$stmt->bind_param($finalTypes, ...$finalParams);
$stmt->execute();
$res  = $stmt->get_result();
$rows = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Historial - Control Hochschild</title>
    <style>
        body { font-family: Arial, sans-serif; background:#f4f6f8; margin:0; color:#111827; }
        .top { background:#fff; border-bottom:1px solid #e5e7eb; padding:12px; }
        .wrap { max-width:1100px; margin:0 auto; padding:14px; }
        .nav a { margin-right:12px; font-weight:900; text-decoration:none; color:#374151; }
        .card { background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:14px; box-shadow:0 6px 18px rgba(0,0,0,0.06); }
        .grid { display:grid; grid-template-columns: 1.2fr 0.8fr 0.6fr 0.6fr auto; gap:10px; align-items:end; }
        label { font-size:12px; color:#374151; font-weight:700; display:block; margin-bottom:6px; }
        input, select { width:100%; padding:10px; border:1px solid #d1d5db; border-radius:10px; font-size:14px; background:#fff; box-sizing:border-box; }
        .btn { padding:10px 14px; border:none; border-radius:10px; font-weight:900; cursor:pointer; text-decoration:none; display:inline-block; }
        .btn-primary { background:#b8872b; color:#fff; }
        .btn-light { background:#f3f4f6; color:#111827; }
        table { width:100%; border-collapse:collapse; margin-top:12px; }
        th, td { border-bottom:1px solid #e5e7eb; padding:10px; font-size:13px; text-align:left; vertical-align:top; }
        th { background:#fafafa; }
        .pill { display:inline-block; padding:4px 8px; border-radius:999px; font-size:12px; background:#f3f4f6; }
        .small { color:#6b7280; font-size:12px; }
        .pager { display:flex; gap:8px; align-items:center; justify-content:flex-end; margin-top:10px; flex-wrap:wrap; }
        .guardia-badge { color: #b8872b; font-weight: bold; }
        @media(max-width:800px){ .grid { grid-template-columns:1fr 1fr; } }
    </style>
</head>
<body>

<!-- ✅ FIX: Div .top correctamente cerrado -->
<div class="top">
    <div class="wrap nav">
        <a href="buses.php">⟵ Scanner</a>
        <a href="personal.php">👷 Personal</a>
        <a href="historial.php">📋 Historial</a>
        <a href="manifiesto.php" style="color: #ef4444; font-weight: bold;">⚠️ MANIFIESTO</a>
    </div>
</div><!-- ← Este cierre faltaba -->

<div class="wrap">
    <div class="card">
        <h2 style="margin:0 0 10px 0;">Historial de Movimientos</h2>

        <form method="GET">
            <div class="grid">
                <div>
                    <label>Buscar</label>
                    <input name="q" value="<?= h($buscar) ?>" placeholder="DNI, Nombre o Guardia...">
                </div>
                <div>
                    <label>Evento</label>
                    <select name="evento">
                        <option value="" <?= $evento === "" ? "selected" : "" ?>>Todos</option>
                        <option value="SUBIDA PERMITIDA" <?= $evento === "SUBIDA PERMITIDA" ? "selected" : "" ?>>SUBIDA</option>
                        <option value="BAJADA PERMITIDA" <?= $evento === "BAJADA PERMITIDA" ? "selected" : "" ?>>BAJADA</option>
                    </select>
                </div>
                <div>
                    <label>Desde</label>
                    <input type="date" name="d" value="<?= h($fecha_d) ?>">
                </div>
                <div>
                    <label>Hasta</label>
                    <input type="date" name="h" value="<?= h($fecha_h) ?>">
                </div>
                <div style="display:flex; gap:10px;">
                    <button class="btn btn-primary" type="submit">FILTRAR</button>
                    <a class="btn btn-light" href="historial.php">HOY</a>
                </div>
            </div>
        </form>

        <div style="margin-top:15px;" class="small">
            Mostrando <b><?= count($rows) ?></b> registros de un total de <b><?= $total ?></b>.
        </div>

        <div style="overflow-x:auto">
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Hora</th>
                        <th>DNI</th>
                        <th>Nombre</th>
                        <th>Evento</th>
                        <th>Unidad / Placa</th>
                        <th>Escaneado Por</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><span class="pill"><?= h($r["dia_marcado"]) ?></span></td>
                        <td><span class="pill"><?= h($r["hora_marcado"]) ?></span></td>
                        <td><?= h($r["dni"]) ?></td>
                        <td><?= h($r["nombre"]) ?></td>
                        <td><b><?= h($r["evento"]) ?></b></td>
                        <td>
                            <?= h($r["bus"]) ?><br>
                            <span class="small"><?= h($r["placa"]) ?></span>
                        </td>
                        <td><span class="guardia-badge"><?= h($r["usuario_scanner"]) ?></span></td>
                    </tr>
                    <?php endforeach; ?>

                    <?php if (count($rows) === 0): ?>
                        <tr><td colspan="7" class="small" style="text-align:center; padding:20px;">No se encontraron registros.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="pager">
            <?php
                $base = ["q" => $buscar, "evento" => $evento, "d" => $fecha_d, "h" => $fecha_h];
                if ($page > 1) {
                    echo '<a class="btn btn-light" href="historial.php?' . http_build_query(array_merge($base, ["p" => $page - 1])) . '">← Anterior</a>';
                }
                for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++) {
                    $active = ($i == $page) ? "background:#b8872b; color:#fff;" : "";
                    echo '<a class="btn btn-light" style="' . $active . '" href="historial.php?' . http_build_query(array_merge($base, ["p" => $i])) . '">' . $i . '</a>';
                }
                if ($page < $totalPages) {
                    echo '<a class="btn btn-light" href="historial.php?' . http_build_query(array_merge($base, ["p" => $page + 1])) . '">Siguiente →</a>';
                }
            ?>
        </div>
    </div>
</div>

</body>
</html>
