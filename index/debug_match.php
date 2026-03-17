<?php
// debug_match.php
// HERRAMIENTA DE DIAGNÓSTICO VISUAL
// Nos mostrará qué DNIs espera el bus y qué DNIs hay escaneados realmente.

require_once 'config.php';
$conn = $mysqli; // Conexión desde config.php

// 1. CONFIGURACIÓN FORZADA (Para probar lo que fallaba en tus fotos)
$bus_objetivo = "AREQUIPA 3"; 
$fecha_objetivo = "2026-02-04"; // La fecha donde están los datos reales

echo "<h1>🕵️ DIAGNÓSTICO DE CRUCE DE DATOS</h1>";
echo "<h3>Buscando coincidencias para: <span style='color:blue'>$bus_objetivo</span> en fecha <span style='color:blue'>$fecha_objetivo</span></h3>";
echo "<hr>";

// --- PARTE A: ¿QUIÉN DEBERÍA SUBIR? (Lista Programada) ---
echo "<h3>A. Lista Programada (Desde BD 'lista_subida')</h3>";
$lista_dnis = [];
$sql_lista = "SELECT asiento, dni, bus FROM lista_subida WHERE UPPER(bus) = UPPER('$bus_objetivo')";
$q1 = mysqli_query($conn, $sql_lista);

if (mysqli_num_rows($q1) > 0) {
    echo "<table border='1' cellpadding='5'><tr><th>Asiento</th><th>DNI en BD</th><th>Longitud</th><th>Bus en BD</th></tr>";
    while ($row = mysqli_fetch_assoc($q1)) {
        $dni_limpio = trim($row['dni']);
        $len = strlen($dni_limpio);
        $lista_dnis[] = $dni_limpio;
        
        echo "<tr>";
        echo "<td>".$row['asiento']."</td>";
        echo "<td>'<strong>".$dni_limpio."</strong>'</td>"; // Comillas para ver espacios
        echo "<td>".$len." chars</td>";
        echo "<td>".$row['bus']."</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<h2 style='color:red'>⚠️ ALERTA: No se encontraron pasajeros programados para '$bus_objetivo'.</h2>";
    echo "Revisa si el nombre del bus en 'lista_subida' está escrito diferente (ej. 'Arequipa 03', 'Bus Arequipa 3').";
}

// --- PARTE B: ¿QUIÉN ESCANEÓ HOY? (Tabla Registros) ---
echo "<hr><h3>B. Escaneos Registrados HOY (Sin importar Bus)</h3>";
$escaneados = [];
$sql_scan = "SELECT dni, fecha, bus FROM registros WHERE DATE(fecha) = '$fecha_objetivo'";
$q2 = mysqli_query($conn, $sql_scan);

if (mysqli_num_rows($q2) > 0) {
    echo "<table border='1' cellpadding='5'><tr><th>DNI Escaneado</th><th>Longitud</th><th>Hora</th><th>Bus Registrado</th><th>¿ESTÁ EN LISTA?</th></tr>";
    while ($row = mysqli_fetch_assoc($q2)) {
        $dni_scan = trim($row['dni']);
        $escaneados[] = $dni_scan;
        
        // VERIFICAMOS AQUÍ MISMO EL CRUCE
        $match = in_array($dni_scan, $lista_dnis) ? "<span style='color:green; font-weight:bold;'>SÍ (COINCIDE)</span>" : "<span style='color:red'>NO</span>";
        
        echo "<tr>";
        echo "<td>'<strong>".$dni_scan."</strong>'</td>";
        echo "<td>".strlen($dni_scan)." chars</td>";
        echo "<td>".substr($row['fecha'], 11)."</td>";
        echo "<td>".$row['bus']."</td>";
        echo "<td>$match</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<h2 style='color:red'>⚠️ ALERTA: No hay NINGÚN escaneo para la fecha $fecha_objetivo.</h2>";
}

echo "<hr>";
echo "<h3>CONCLUSIÓN RÁPIDA:</h3>";
if (empty($lista_dnis)) {
    echo "❌ <strong>PROBLEMA 1:</strong> El sistema no encuentra la lista del bus. Revisa el nombre del bus.<br>";
}
if (empty($escaneados)) {
    echo "❌ <strong>PROBLEMA 2:</strong> No hay escaneos para esta fecha. Revisa si tus datos son de hoy o ayer.<br>";
}
if (!empty($lista_dnis) && !empty($escaneados)) {
    echo "ℹ️ Si ves ambas tablas pero la columna '¿ESTÁ EN LISTA?' dice <strong>NO</strong>, compara los DNIs. ¿Son distintos? ¿Unos tienen ceros delante y otros no?<br>";
}
?>