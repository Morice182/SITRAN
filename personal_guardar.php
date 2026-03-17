<?php
/**
 * PROCESADOR DE DATOS DEL PERSONAL - CORREGIDO
 * Soluciona error "Server has gone away" limpiando correctamente los resultados.
 */

session_start();
require __DIR__ . "/config.php"; 

if (!isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Recibir datos
    $dni = trim($_POST['dni']);
    $nombres = trim($_POST['nombres']);
    $apellidos = trim($_POST['apellidos']);
    
    if (empty($dni) || empty($nombres) || empty($apellidos)) {
        header("Location: personal.php?msg=error");
        exit();
    }

    // Resto de datos
    $codigo = trim($_POST['codigo'] ?? '');
    $celular = trim($_POST['celular'] ?? '');
    $empresa = trim($_POST['empresa'] ?? '');
    $area = trim($_POST['area'] ?? '');
    $cargo = trim($_POST['cargo'] ?? '');
    $estado_validacion = $_POST['estado_validacion'] ?? 'VISITA';
    $guardia = strtoupper(trim($_POST['GUARDIA'] ?? ''));

    $grupo = $_POST['grupo_sanguineo'] ?? '';
    $contacto = trim($_POST['contacto_emergencia'] ?? '');
    $telefono_em = trim($_POST['telefono_emergencia'] ?? '');
    $enfermedades = trim($_POST['enfermedades'] ?? '');
    $alergias = trim($_POST['alergias'] ?? '');

    // --- CORRECCIÓN AQUÍ ---
    // Usamos store_result() que es más ligero y estable para chequear existencia
    $existe = false;
    
    $check = $mysqli->prepare("SELECT dni FROM personal WHERE dni = ?");
    if ($check) {
        $check->bind_param("s", $dni);
        $check->execute();
        $check->store_result(); // Guardamos el resultado en memoria
        if ($check->num_rows > 0) {
            $existe = true;
        }
        $check->free_result(); // ¡IMPORTANTE! Liberamos la memoria
        $check->close();       // Cerramos la sentencia
    }

    // Ahora la conexión está 100% limpia para la siguiente consulta
    $stmt = false;

    if ($existe) {
        // ACTUALIZAR
        $sql = "UPDATE personal SET 
                codigo=?, nombres=?, apellidos=?, celular=?, empresa=?, area=?, cargo=?, 
                estado_validacion=?, GUARDIA=?, grupo_sanguineo=?, contacto_emergencia=?, 
                telefono_emergencia=?, enfermedades=?, alergias=? 
                WHERE dni=?";
        
        $stmt = $mysqli->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("sssssssssssssss", 
                $codigo, $nombres, $apellidos, $celular, $empresa, $area, $cargo, 
                $estado_validacion, $guardia, $grupo, $contacto, 
                $telefono_em, $enfermedades, $alergias, 
                $dni
            );
        }
    } else {
        // INSERTAR
        $sql = "INSERT INTO personal 
                (dni, codigo, nombres, apellidos, celular, empresa, area, cargo, 
                estado_validacion, GUARDIA, grupo_sanguineo, contacto_emergencia, 
                telefono_emergencia, enfermedades, alergias) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $mysqli->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("sssssssssssssss", 
                $dni, $codigo, $nombres, $apellidos, $celular, $empresa, $area, $cargo, 
                $estado_validacion, $guardia, $grupo, $contacto, 
                $telefono_em, $enfermedades, $alergias
            );
        }
    }

    // Ejecutar
    if ($stmt && $stmt->execute()) {
        $stmt->close();
        header("Location: personal.php?msg=ok");
    } else {
        // Si falló, puede ser un error de SQL.
        // Opcional: ver error con $mysqli->error en un log
        header("Location: personal.php?msg=error");
    }

} else {
    header("Location: personal.php");
}
?>