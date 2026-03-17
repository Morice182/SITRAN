<?php
require __DIR__ . "/config.php";

$buscar  = isset($_GET["q"]) ? trim($_GET["q"]) : "";
$evento  = isset($_GET["evento"]) ? trim($_GET["evento"]) : "";
$fecha_d = isset($_GET["d"]) ? trim($_GET["d"]) : "";
$fecha_h = isset($_GET["h"]) ? trim($_GET["h"]) : "";

if ($fecha_d === "" && $fecha_h === "") {
  $hoy = date("Y-m-d");
  $fecha_d = $hoy;
  $fecha_h = $hoy;
}

$where = [];
$params = [];
$types = "";

if ($fecha_d !== "") { $where[] = "fecha >= CONCAT(?, ' 00:00:00')"; $params[] = $fecha_d; $types.="s"; }
if ($fecha_h !== "") { $where[] = "fecha <= CONCAT(?, ' 23:59:59')"; $params[] = $fecha_h; $types.="s"; }
if ($evento !== "")  { $where[] = "evento = ?"; $params[] = $evento; $types.="s"; }
if ($buscar !== "")  {
  $where[] = "(dni LIKE ? OR nombre LIKE ?)";
  $like = "%".$buscar."%";
  $params[] = $like; $params[] = $like;
  $types.="ss";
}

$whereSql = count($where) ? ("WHERE ".implode(" AND ", $where)) : "";

$sql = "SELECT 
          id, dni, nombre, evento, bus, destino, fecha,
          DATE_FORMAT(fecha, '%Y-%m-%d') AS dia_marcado,
          DATE_FORMAT(fecha, '%H:%i:%s') AS hora_marcado
        FROM registros
        $whereSql
        ORDER BY fecha DESC, id DESC
        LIMIT 5000";

$stmt = $mysqli->prepare($sql);
if ($types !== "") $stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="historial_registros.csv"');

$out = fopen('php://output', 'w');
fputcsv($out, ["id","dni","nombre","evento","bus","destino","fecha","dia_marcado","hora_marcado"]);

while ($row = $res->fetch_assoc()) {
  fputcsv($out, [
    $row["id"], $row["dni"], $row["nombre"], $row["evento"],
    $row["bus"], $row["destino"], $row["fecha"],
    $row["dia_marcado"], $row["hora_marcado"]
  ]);
}

fclose($out);
$stmt->close();
exit;
