<?php
/**
 * auth.php - Control de Acceso SITRAN
 * Soporta login por EMAIL o por DNI
 */
session_start();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit();
}

require_once __DIR__ . "/config.php";

if (!$mysqli) { 
    header("Location: index.php?error=1"); 
    exit(); 
}

$usuario_input = trim($_POST['usuario'] ?? ''); 
$pass          = $_POST['pass'] ?? '';

if (empty($usuario_input) || empty($pass)) {
    header("Location: index.php?error=1");
    exit();
}

// ── CONSULTA: busca por EMAIL o por DNI ──────────────────────────
$query = "SELECT id, password, nombre, rol, cargo_real, estado 
          FROM usuarios 
          WHERE email = ? OR dni = ? 
          LIMIT 1";

$stmt = $mysqli->prepare($query);
if (!$stmt) {
    die("Error en la consulta: " . $mysqli->error);
}

$stmt->bind_param("ss", $usuario_input, $usuario_input);
$stmt->execute();
$stmt->bind_result($id, $hash, $nombre, $rol, $cargo_real, $estado);
$found = $stmt->fetch();
$stmt->close();

if (!$found) {
    header("Location: index.php?error=1");
    exit();
}

// Verificación de estado
if ($estado == 0) {
    header("Location: index.php?error=2");
    exit();
}

// ── VERIFICACIÓN DE CONTRASEÑA ────────────────────────────────────
$ok = false;
if (password_verify($pass, $hash)) {
    $ok = true;
} elseif ($pass === $hash) {
    $ok        = true;
    $nuevoHash = password_hash($pass, PASSWORD_BCRYPT);
    $upd       = $mysqli->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
    if ($upd) {
        $upd->bind_param("si", $nuevoHash, $id);
        $upd->execute();
        $upd->close();
    }
}

if (!$ok) {
    header("Location: index.php?error=1");
    exit();
}

// ── SESIÓN SEGURA ─────────────────────────────────────────────────
session_regenerate_id(true);
$_SESSION['usuario']    = $usuario_input; 
$_SESSION['nombre']     = $nombre;
$_SESSION['rol']        = $rol;
$_SESSION['cargo_real'] = $cargo_real;

header("Location: dashboard.php");
exit();
?>