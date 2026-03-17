<?php
session_start();
require __DIR__ . "/config.php";

$mensaje = "";
$tipo_msg = "";

if (isset($_POST['importar'])) {
    $archivo = $_FILES['archivo']['tmp_name'];
    
    if (empty($archivo)) {
        $mensaje = "Por favor selecciona un archivo CSV.";
        $tipo_msg = "error";
    } else {
        $handle = fopen($archivo, "r");
        $contador = 0;
        
        // Saltamos la primera fila si tiene encabezados (DNI;NOMBRE;EVENTO...)
        fgetcsv($handle, 1000, ";"); 

        while (($data = fgetcsv($handle, 1000, ";")) !== false) {
            // MAPEO DE COLUMNAS (Ajusta estos índices según tu Excel)
            // Ejemplo: Columna A=0 (DNI), B=1 (Nombre), C=2 (Evento), etc.
            $dni = $data[1]; // Asumiendo DNI en columna B
            $nombre = $data[2];
            $evento = $data[3];
            $bus = $data[6];
            $fecha = $data[9]; // Formato YYYY-MM-DD HH:MM:SS
            $scanner = 'subida_manual@excel'; // Marca para saber que vino de Excel

            // Insertamos sin borrar lo anterior
            $sql = "INSERT INTO registros (dni, nombre, evento, bus, fecha, usuario_scanner) 
                    VALUES ('$dni', '$nombre', '$evento', '$bus', '$fecha', '$scanner')";
            mysqli_query($mysqli, $sql);
            $contador++;
        }
        fclose($handle);
        $mensaje = "¡Éxito! Se agregaron $contador registros nuevos al historial.";
        $tipo_msg = "success";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Importar Historial</title>
    <style>
        body { font-family: sans-serif; background: #f4f4f7; display: flex; justify-content: center; padding-top: 50px; }
        .card { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); width: 400px; text-align: center; }
        .btn { background: #b8872b; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; width: 100%; margin-top: 15px; }
        .input-file { border: 2px dashed #ccc; padding: 20px; width: 100%; box-sizing: border-box; margin: 15px 0; }
        .msg { padding: 10px; margin-bottom: 15px; border-radius: 5px; font-weight: bold; }
        .success { background: #dcfce7; color: #166534; }
        .error { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
    <div class="card">
        <h2>Subir Registros Nuevos</h2>
        <p style="color:#666; font-size:13px;">Agrega datos al historial sin borrar lo anterior.</p>
        
        <?php if($mensaje): ?>
            <div class="msg <?=$tipo_msg?>"><?= $mensaje ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <input type="file" name="archivo" class="input-file" accept=".csv" required>
            <button type="submit" name="importar" class="btn">PROCESAR ARCHIVO</button>
        </form>
        
        <br>
        <a href="kpis_pro.php" style="color:#b8872b; text-decoration:none; font-size:12px;">← Volver al Dashboard</a>
    </div>
</body>
</html>