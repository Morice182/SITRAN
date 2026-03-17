<?php
/**
 * validar.php — API de validación DNI
 * ✅ CORRECCIÓN: Se agrega campo "asiento" en SELECT de lista_subida / lista_bajada
 *    y se expone en la respuesta JSON (persona.asiento y raíz destino)
 */
ob_start();
header('Content-Type: application/json; charset=utf-8');

$origen_permitido = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
header("Access-Control-Allow-Origin: " . $origen_permitido);

session_start();
error_reporting(0);
ini_set('display_errors', 0);

function respond($data) {
    if (ob_get_length() > 0) { ob_clean(); }
    echo json_encode($data);
    exit;
}

if (!isset($_SESSION['usuario'])) {
    respond(["estado" => "ERROR", "mensaje" => "SESION_EXPIRADA"]);
}

require __DIR__ . "/config.php";

$dni              = preg_replace('/[^0-9]/', '', $_POST["dni"] ?? "");
$modo             = $_POST['modo'] ?? 'NORMAL';
$ubicacion_bajada = strtoupper(trim($_POST['ubicacion'] ?? ''));

if (strlen($dni) !== 8) { respond(["estado" => "ERROR", "mensaje" => "DNI INVÁLIDO"]); }

$autorizado = 0;
$evento     = "NO AUTORIZADO";
$destino    = "N/A"; $bus = "SIN ASIGNAR"; $placa = "---"; $asiento = "";

// 1. BUSCAR EN LISTAS (SUBIDA / BAJADA)
// ✅ Se agrega "asiento" al SELECT
$stmt = $mysqli->prepare("SELECT destino, bus, placa, asiento FROM lista_subida WHERE dni=? LIMIT 1");
$stmt->bind_param("s", $dni); $stmt->execute(); $stmt->bind_result($d1, $b1, $p1, $a1);
if ($stmt->fetch()) { $autorizado = 1; $evento = "SUBIDA PERMITIDA"; $destino = $d1; $bus = $b1; $placa = $p1; $asiento = $a1 ?? ""; }
$stmt->close();

if (!$autorizado) {
    // ✅ Se agrega "asiento" al SELECT
    $stmt = $mysqli->prepare("SELECT destino, bus, placa, asiento FROM lista_bajada WHERE dni=? LIMIT 1");
    $stmt->bind_param("s", $dni); $stmt->execute(); $stmt->bind_result($d2, $b2, $p2, $a2);
    if ($stmt->fetch()) { $autorizado = 1; $evento = "BAJADA PERMITIDA"; $destino = $d2; $bus = $b2; $placa = $p2; $asiento = $a2 ?? ""; }
    $stmt->close();
}

// Lógica Bajada Manual
if ($autorizado && $modo === 'BAJADA') {
    $evento  = "DESEMBARQUE";
    $destino = !empty($ubicacion_bajada) ? $ubicacion_bajada : "PUNTO DE BAJADA";
}

// 2. BUSCAR DATOS PERSONALES
$persona   = null;
$nombreLog = "DESCONOCIDO";

$stmt = $mysqli->prepare(
    "SELECT dni, nombres, apellidos, empresa, area, cargo, estado_validacion,
            grupo_sanguineo, contacto_emergencia, telefono_emergencia
     FROM personal WHERE dni=? LIMIT 1"
);
$stmt->bind_param("s", $dni); $stmt->execute();
$stmt->bind_result($pdni, $pnom, $pape, $pemp, $pare, $pcar, $pestado, $pgs, $pce, $pte);

if ($stmt->fetch()) {
    $nombreLog = strtoupper($pnom . " " . $pape);
    $persona   = [
        "dni"        => $pdni,
        "nombres"    => strtoupper($pnom),
        "apellidos"  => strtoupper($pape),
        "empresa"    => strtoupper($pemp),
        "area"       => strtoupper($pare),
        "cargo"      => strtoupper($pcar),
        "validacion" => !empty($pestado) ? strtoupper($pestado) : "VISITA",
        "bus"        => strtoupper($bus),
        "placa"      => strtoupper($placa),
        "asiento"    => strtoupper($asiento ?? ""),   // ✅ NUEVO
        "med_gs"     => $pgs, "med_ce" => $pce, "med_te" => $pte,
    ];
} else {
    if ($autorizado) {
        $nombreLog = "PASAJERO EXTRA ($dni)";
        $persona   = [
            "dni"        => $dni,
            "nombres"    => "PASAJERO",
            "apellidos"  => "EXTRA / NO REGISTRADO",
            "empresa"    => "EXTERNO",
            "area"       => "---",
            "cargo"      => "VIAJERO",
            "validacion" => "AUTORIZADO",
            "bus"        => strtoupper($bus),
            "placa"      => strtoupper($placa),
            "asiento"    => strtoupper($asiento ?? ""),  // ✅ NUEVO
        ];
    }
}
$stmt->close();

// 3. ESTADO FINAL
$estado_final = "DENEGADO";

if ($autorizado) {
    $estado_final        = "AUTORIZADO";
    $usuario_que_escanea = $_SESSION['usuario'] ?? 'SISTEMA';

    $ins = $mysqli->prepare(
        "INSERT INTO registros (dni, nombre, evento, destino, bus, placa, usuario_scanner)
         SELECT ?,?,?,?,?,?,?
         WHERE NOT EXISTS (
             SELECT 1 FROM registros WHERE dni=? AND evento=? AND TIMESTAMPDIFF(SECOND, fecha, NOW()) < 10
         )"
    );
    if ($ins) {
        $ins->bind_param("sssssssss", $dni, $nombreLog, $evento, $destino, $bus, $placa, $usuario_que_escanea, $dni, $evento);
        $ins->execute();
        $ins->close();
    }
} else {
    $estado_final = $persona ? "FALTA_VIAJE" : "NO_EXISTE";
}

$fotoPath = file_exists("fotos/$dni.jpg") ? "fotos/$dni.jpg" : null;

respond([
    "estado"    => $estado_final,
    "movimiento" => $evento,
    "destino"   => strtoupper($destino),
    "asiento"   => strtoupper($asiento ?? ""),   // ✅ NUEVO — disponible en raíz también
    "persona"   => $persona,
    "foto"      => $fotoPath,
]);
?>