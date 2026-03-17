<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión expirada']);
    exit;
}

require_once 'config.php';
$conn = $mysqli; // Conexión desde config.php

// Recibir datos
$dni = $_POST['dni'] ?? '';
$bus = $_POST['bus'] ?? '';
$tipo = $_POST['tipo'] ?? 'subida';
$asiento = $_POST['asiento'] ?? '';
$destino = strtoupper($_POST['destino'] ?? '');
$modo = $_POST['modo_registro'] ?? 'SOLO_VIAJE';

// Validación básica
if(empty($dni) || empty($bus) || empty($asiento)) {
    echo json_encode(['success' => false, 'message' => 'Faltan datos (DNI, Bus o Asiento)']);
    exit;
}

// 1. REGISTRAR PERSONAL NUEVO (Si es necesario)
if ($modo === 'COMPLETO') {
    $nombres = strtoupper($_POST['nombres'] ?? '');
    $apellidos = strtoupper($_POST['apellidos'] ?? '');
    $empresa = strtoupper($_POST['empresa'] ?? '');

    if(empty($nombres) || empty($empresa)) {
         echo json_encode(['success' => false, 'message' => 'Nombre y Empresa son obligatorios']);
         exit;
    }

    // Verificar si ya existe (Seguridad)
    $stmtCheck = mysqli_prepare($conn, "SELECT dni FROM personal WHERE dni=?");
    mysqli_stmt_bind_param($stmtCheck, "s", $dni);
    mysqli_stmt_execute($stmtCheck);
    mysqli_stmt_store_result($stmtCheck);
    
    if(mysqli_stmt_num_rows($stmtCheck) == 0) {
        // Insertar seguro
        $stmtIns = mysqli_prepare($conn, "INSERT INTO personal (dni, nombres, apellidos, empresa, cargo, estado_validacion) VALUES (?, ?, ?, ?, 'PASAJERO', 'AUTORIZADO')");
        mysqli_stmt_bind_param($stmtIns, "ssss", $dni, $nombres, $apellidos, $empresa);
        if(!mysqli_stmt_execute($stmtIns)) {
            echo json_encode(['success' => false, 'message' => 'Error creando personal']);
            exit;
        }
        mysqli_stmt_close($stmtIns);
    }
    mysqli_stmt_close($stmtCheck);
}

// 2. REGISTRAR EL VIAJE
$tabla = ($tipo === 'subida') ? 'lista_subida' : 'lista_bajada';

// Borrar viaje anterior para evitar duplicados
$stmtDel = mysqli_prepare($conn, "DELETE FROM $tabla WHERE dni=?");
mysqli_stmt_bind_param($stmtDel, "s", $dni);
mysqli_stmt_execute($stmtDel);
mysqli_stmt_close($stmtDel);

// Insertar nuevo viaje
$stmtViaje = mysqli_prepare($conn, "INSERT INTO $tabla (dni, bus, destino, asiento, placa) VALUES (?, ?, ?, ?, '---')");
mysqli_stmt_bind_param($stmtViaje, "ssss", $dni, $bus, $destino, $asiento);

if(mysqli_stmt_execute($stmtViaje)) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Error guardando viaje']);
}

mysqli_close($conn);
?>