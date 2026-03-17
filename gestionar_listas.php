<?php
/**
 * gestionar_listas.php
 * ✅ CORREGIDO:
 *    1. Verificación de sesión y rol (solo admin/supervisor pueden limpiar listas)
 *    2. Whitelist de tablas permitidas
 *    3. Validación de DNI mejorada
 */

session_start();
require __DIR__ . "/config.php";

// ─── SOLO SUPERVISORES Y ADMINISTRADORES ──────────────────────────
if (!isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit();
}
$rol = $_SESSION['rol'] ?? 'agente';
if (!in_array($rol, ['supervisor', 'administrador'])) {
    header("Location: dashboard.php");
    exit();
}

$mensaje    = "";
$tipo_alerta = "info";

// ─── WHITELIST DE TABLAS ──────────────────────────────────────────
$tablas_permitidas = ['lista_subida', 'lista_bajada'];

if (isset($_POST['importar'])) {
    $tabla   = $_POST['tabla'] ?? '';
    $archivo = $_FILES['csv_file']['tmp_name'] ?? '';

    // Validar que la tabla esté en la whitelist
    if (!in_array($tabla, $tablas_permitidas)) {
        $mensaje    = "Tabla no permitida.";
        $tipo_alerta = "danger";
    } elseif (empty($archivo)) {
        $mensaje    = "Por favor, selecciona un archivo CSV.";
        $tipo_alerta = "danger";
    } else {
        if (($handle = fopen($archivo, "r")) !== false) {
            // Limpiar la tabla seleccionada antes de cargar
            $mysqli->query("TRUNCATE TABLE `$tabla`");

            $insertados = 0;
            $fila       = 0;
            $stmt       = $mysqli->prepare("INSERT IGNORE INTO `$tabla` (dni, destino) VALUES (?, ?)");

            while (($data = fgetcsv($handle, 1000, ";")) !== false) {
                $fila++;
                if ($fila === 1) continue; // Saltar encabezado

                $dni     = trim($data[0] ?? '');
                $destino = strtoupper(trim($data[1] ?? 'NO ESPECIFICADO'));

                // Solo DNIs de exactamente 8 dígitos
                if (preg_match('/^\d{8}$/', $dni)) {
                    $stmt->bind_param("ss", $dni, $destino);
                    $stmt->execute();
                    $insertados++;
                }
            }
            $stmt->close();
            fclose($handle);

            $nombre_tabla = str_replace(['lista_', '_'], ['', ' '], $tabla);
            $mensaje      = "Se han cargado $insertados registros en la lista de $nombre_tabla.";
            $tipo_alerta  = "success";
        }
    }
}

// Conteos actuales
$countSubida = $mysqli->query("SELECT COUNT(*) as total FROM lista_subida")->fetch_assoc()['total'];
$countBajada = $mysqli->query("SELECT COUNT(*) as total FROM lista_bajada")->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestionar Listas Semanales | Hochschild</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body { background: #f4f6f8; padding: 20px; }
        .card { border-radius: 15px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .stats { font-size: 1.2rem; font-weight: bold; color: #b8872b; }
    </style>
</head>
<body>
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <h2 class="text-center mb-4">Control de Listas Semanales</h2>

            <?php if ($mensaje): ?>
                <div class="alert alert-<?= h($tipo_alerta) ?>"><?= h($mensaje) ?></div>
            <?php endif; ?>

            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card p-3 text-center">
                        <h5>En Lista Subida</h5>
                        <p class="stats"><?= (int)$countSubida ?> Personas</p>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card p-3 text-center">
                        <h5>En Lista Bajada</h5>
                        <p class="stats"><?= (int)$countBajada ?> Personas</p>
                    </div>
                </div>
            </div>

            <div class="card p-4">
                <form action="" method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label">1. Seleccionar Destino:</label>
                        <select name="tabla" class="form-select" required>
                            <option value="lista_subida">LISTA DE SUBIDA (AUTORIZAR INGRESO)</option>
                            <option value="lista_bajada">LISTA DE BAJADA (AUTORIZAR SALIDA)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">2. Seleccionar Archivo CSV (Separado por ;):</label>
                        <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                    </div>
                    <div class="mb-3 form-text text-muted">
                        ⚠️ Esta acción limpiará la lista anterior y cargará la nueva. Solo ejecutar al inicio de cada semana.
                    </div>
                    <button type="submit" name="importar" class="btn btn-primary w-100">
                        LIMPIAR LISTA ANTERIOR Y CARGAR NUEVA
                    </button>
                </form>
            </div>

            <div class="text-center mt-4">
                <a href="dashboard.php" class="btn btn-secondary">Volver al Dashboard</a>
            </div>
        </div>
    </div>
</div>
</body>
</html>
