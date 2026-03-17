<?php
/**
 * ver_asientos.php
 * ✅ CORREGIDO:
 *    1. Verificación de sesión agregada
 *    2. SQL Injection → prepared statement
 *    3. Valor de $tabla ahora viene de whitelist (no del usuario)
 *    4. Número de asientos unificado a 30 (consistente con buses.php)
 */

session_start();
header('Content-Type: application/json');

// ─── SEGURIDAD: REQUIERE SESIÓN ───────────────────────────────────
if (!isset($_SESSION['usuario'])) {
    echo json_encode([]);
    exit();
}

require __DIR__ . "/config.php";

$bus  = trim($_POST['bus'] ?? '');
$tipo = $_POST['tipo'] ?? 'subida';

if (empty($bus)) {
    echo json_encode([]);
    exit();
}

// ─── WHITELIST DE TABLAS (no se usa input del usuario directamente) ─
$tablas_permitidas = ['subida' => 'lista_subida', 'bajada' => 'lista_bajada'];
$tabla = $tablas_permitidas[$tipo] ?? 'lista_subida';

// ─── PREPARED STATEMENT ───────────────────────────────────────────
$stmt = $mysqli->prepare(
    "SELECT asiento FROM {$tabla} WHERE bus = ? AND asiento REGEXP '^[0-9]+$'"
);
$stmt->bind_param("s", $bus);
$stmt->execute();
$result = $stmt->get_result();

$ocupados = [];
while ($row = $result->fetch_assoc()) {
    $num = (int)$row['asiento'];
    // Solo asientos válidos del 1 al 30
    if ($num >= 1 && $num <= 30) {
        $ocupados[] = $num;
    }
}

$stmt->close();

echo json_encode(array_values(array_unique($ocupados)));
?>
