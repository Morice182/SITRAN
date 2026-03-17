<?php
/**
 * control_bus.php
 * ✅ CORREGIDO:
 *    1. Verificación de sesión
 *    2. SQL Injection → prepared statement
 *    3. Whitelist para la tabla
 */

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['usuario'])) {
    echo json_encode(["error" => "No autorizado"]);
    exit();
}

require __DIR__ . "/config.php";

$bus  = trim($_GET['bus'] ?? '');
$tipo = $_GET['tipo'] ?? 'subida';

if (empty($bus)) {
    echo json_encode([]);
    exit();
}

// ─── WHITELIST ────────────────────────────────────────────────────
$tablas = ['subida' => 'lista_subida', 'bajada' => 'lista_bajada'];
$tabla  = $tablas[$tipo] ?? 'lista_subida';

// ─── PREPARED STATEMENT ───────────────────────────────────────────
$stmt = $mysqli->prepare("SELECT dni FROM {$tabla} WHERE bus = ?");
$stmt->bind_param("s", $bus);
$stmt->execute();
$result = $stmt->get_result();

$data = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo json_encode($data);
?>
