<?php
/**
 * get_buses.php
 * ✅ CREADO: Archivo estaba vacío.
 *    Retorna la lista de buses disponibles en ambas listas (subida + bajada).
 *    Usado por buses.php y otros módulos para poblar los selectores.
 */

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['usuario'])) {
    echo json_encode([]);
    exit();
}

require __DIR__ . "/config.php";

$result = $mysqli->query(
    "SELECT DISTINCT bus FROM lista_bajada WHERE bus IS NOT NULL AND bus != ''
     UNION
     SELECT DISTINCT bus FROM lista_subida WHERE bus IS NOT NULL AND bus != ''
     ORDER BY bus ASC"
);

$buses = [];
while ($row = $result->fetch_assoc()) {
    $buses[] = $row['bus'];
}

echo json_encode($buses);
?>
