<?php
/**
 * api/stats.php — Endpoint de estadísticas rápidas para el Dashboard
 * Requiere sesión activa.
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

require __DIR__ . '/../config.php';

$stats = [
    'total_personal' => 0,
    'buses_activos'  => 0,
    'registros_hoy'  => 0,
];

// Total de personal activo
$r = $mysqli->query("SELECT COUNT(*) FROM fuerza_laboral");
if ($r) { [$stats['total_personal']] = $r->fetch_row(); }

// Buses con al menos un asiento asignado hoy
$r2 = $mysqli->query("
    SELECT COUNT(DISTINCT bus) FROM lista_subida
    WHERE DATE(created_at) = CURDATE()
       OR created_at IS NULL
");
if ($r2) { [$stats['buses_activos']] = $r2->fetch_row(); }

// Registros de escaneo de hoy
$r3 = $mysqli->query("SELECT COUNT(*) FROM registros WHERE DATE(fecha) = CURDATE()");
if ($r3) { [$stats['registros_hoy']] = $r3->fetch_row(); }

echo json_encode($stats);
