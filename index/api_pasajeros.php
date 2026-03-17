<?php
/**
 * api_pasajeros.php
 * ✅ CORREGIDO:
 *    1. Verificación de sesión
 *    2. SQL Injection → prepared statements
 *    3. Whitelist para tabla
 *    4. Asientos unificados a 30 (era 35, buses.php usa 30)
 *    5. Validación de fecha
 */

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['usuario'])) {
    echo json_encode(["error" => "No autorizado"]);
    exit();
}

require __DIR__ . "/config.php";

$bus          = trim($_GET['bus'] ?? '');
$tipo         = $_GET['tipo'] ?? 'bajada';
$fecha_filtro = $_GET['fecha'] ?? date('Y-m-d');

// Validar formato de fecha para no inyectar en la query
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_filtro)) {
    $fecha_filtro = date('Y-m-d');
}

// ─── WHITELIST ────────────────────────────────────────────────────
$tablas        = ['subida' => 'lista_subida', 'bajada' => 'lista_bajada'];
$tabla_origen  = $tablas[$tipo] ?? 'lista_bajada';

// ─── PASO 1: PASAJEROS PROGRAMADOS ───────────────────────────────
$pasajeros_programados = [];

$stmt = $mysqli->prepare(
    "SELECT l.asiento, l.dni, p.apellidos, p.nombres, p.empresa, p.cargo
     FROM {$tabla_origen} l
     LEFT JOIN personal p ON l.dni = p.dni
     WHERE UPPER(l.bus) = UPPER(?)"
);
$stmt->bind_param("s", $bus);
$stmt->execute();
$q_lista = $stmt->get_result();

while ($row = $q_lista->fetch_assoc()) {
    $num = (int)$row['asiento'];
    if ($num >= 1 && $num <= 30) {
        $row['dni_limpio'] = trim($row['dni']);
        $pasajeros_programados[$num] = $row;
    }
}
$stmt->close();

// ─── PASO 2: ESCANEOS DEL DÍA ────────────────────────────────────
$escaneados_hoy = [];

$stmt2 = $mysqli->prepare("SELECT dni FROM registros WHERE DATE(fecha) = ?");
$stmt2->bind_param("s", $fecha_filtro);
$stmt2->execute();
$q_reg = $stmt2->get_result();

while ($row = $q_reg->fetch_assoc()) {
    $escaneados_hoy[] = trim($row['dni']);
}
$stmt2->close();

// ─── PASO 3: GENERAR HTML ─────────────────────────────────────────
$html        = '';
$total_validos = 0;

// UNIFICADO A 30 ASIENTOS (igual que buses.php)
for ($i = 1; $i <= 30; $i++) {
    $p            = $pasajeros_programados[$i] ?? null;
    $tiene_escaneo = false;

    if ($p && in_array($p['dni_limpio'], $escaneados_hoy)) {
        $tiene_escaneo = true;
        $total_validos++;
    }

    $nombre  = $tiene_escaneo ? h($p['apellidos'] . ' ' . $p['nombres']) : '';
    $dni     = $tiene_escaneo ? h($p['dni'] ?? '') : '';
    $empresa = $tiene_escaneo ? h($p['empresa'] ?? '') : '';
    $cargo   = $tiene_escaneo ? h($p['cargo'] ?? '') : '';

    $html .= '<tr>
        <td class="text-center"><strong>' . $i . '</strong></td>
        <td style="padding-left:5px;">' . $nombre . '</td>
        <td class="text-center">' . $dni . '</td>
        <td class="text-center">' . $empresa . '</td>
        <td class="text-center">' . $cargo . '</td>
    </tr>';
}

echo json_encode(['tabla' => $html, 'total' => $total_validos]);
?>
