<?php
/**
 * control_bus_add.php
 * ✅ CORREGIDO:
 *    1. Verificación de sesión
 *    2. SQL Injection → prepared statement
 *    3. Whitelist para la tabla
 *    4. Validación básica de DNI
 *    5. Respuesta JSON correcta
 */

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['usuario'])) {
    echo json_encode(["ok" => false, "msg" => "No autorizado"]);
    exit();
}

require __DIR__ . "/config.php";

$dni  = preg_replace('/[^0-9]/', '', $_POST['dni'] ?? '');
$bus  = trim($_POST['bus'] ?? '');
$tipo = $_POST['tipo'] ?? 'subida';

// ─── VALIDACIONES ─────────────────────────────────────────────────
if (strlen($dni) !== 8 || empty($bus)) {
    echo json_encode(["ok" => false, "msg" => "Datos incompletos"]);
    exit();
}

// ─── WHITELIST ────────────────────────────────────────────────────
$tablas = ['subida' => 'lista_subida', 'bajada' => 'lista_bajada'];
$tabla  = $tablas[$tipo] ?? 'lista_subida';

// ─── PREPARED STATEMENT ───────────────────────────────────────────
$stmt = $mysqli->prepare(
    "INSERT INTO {$tabla} (dni, bus, fecha_registro) VALUES (?, ?, NOW())"
);
$stmt->bind_param("ss", $dni, $bus);
$ok = $stmt->execute();
$stmt->close();

echo json_encode(["ok" => $ok]);
?>
