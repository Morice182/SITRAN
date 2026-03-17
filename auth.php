<?php
/**
 * auth.php — Controlador de Autenticación SITRAN
 * Soporta login por EMAIL o por DNI.
 * v2.0 — Validación CSRF añadida.
 */
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit();
}

// ── VALIDACIÓN CSRF ──────────────────────────────────────────────────
$csrf_enviado = $_POST['csrf_token'] ?? '';
$csrf_sesion  = $_SESSION['csrf_token'] ?? '';

if (empty($csrf_sesion) || !hash_equals($csrf_sesion, $csrf_enviado)) {
    header("Location: index.php?error=3");
    exit();
}

// Regenerar token CSRF para la próxima petición
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

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

// ── CONSULTA: busca por EMAIL o por DNI ─────────────────────────────
// Nota: la tabla no tiene columna 'nombre_usuario', se usa email como identificador
$query = "SELECT id, password, nombre, rol, cargo_real, estado, email
          FROM usuarios
          WHERE email = ? OR dni = ?
          LIMIT 1";

$stmt = $mysqli->prepare($query);
if (!$stmt) {
    error_log("auth.php prepare error: " . $mysqli->error);
    header("Location: index.php?error=1");
    exit();
}

$stmt->bind_param("ss", $usuario_input, $usuario_input);
$stmt->execute();
$stmt->bind_result($id, $hash, $nombre, $rol, $cargo_real, $estado, $email_usuario);
$found = $stmt->fetch();
$stmt->close();

if (!$found) {
    header("Location: index.php?error=1");
    exit();
}

// Verificación de estado (0 = pendiente de aprobación)
if ($estado == 0) {
    header("Location: index.php?error=2");
    exit();
}

// ── VERIFICACIÓN DE CONTRASEÑA ───────────────────────────────────────
$ok = false;
if (password_verify($pass, $hash)) {
    $ok = true;
} elseif ($pass === $hash) {
    // Migración a bcrypt para contraseñas en texto plano antiguas
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

// ── SESIÓN SEGURA ────────────────────────────────────────────────────
session_regenerate_id(true);
$_SESSION['usuario']    = $email_usuario; // email del usuario autenticado
$_SESSION['nombre']     = $nombre;
$_SESSION['rol']        = $rol;
$_SESSION['cargo_real'] = $cargo_real;

header("Location: dashboard.php");
exit();
?>