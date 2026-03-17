<?php
/**
 * procesar_registro.php
 * ✅ CREADO: Faltaba este archivo (registro.php apuntaba a él).
 *    - Guarda el nuevo usuario con estado=0 (pendiente de aprobación)
 *    - La contraseña se guarda con password_hash (nunca texto plano)
 *    - Validación de DNI y campos requeridos
 */

session_start();
require __DIR__ . "/config.php";

// Solo aceptar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: registro.php");
    exit();
}

$usuario = trim($_POST['usuario'] ?? '');
$dni     = preg_replace('/[^0-9]/', '', $_POST['dni'] ?? '');
$pass    = $_POST['password'] ?? '';

// ─── VALIDACIONES ─────────────────────────────────────────────────
if (empty($usuario) || strlen($dni) !== 8 || strlen($pass) < 6) {
    header("Location: registro.php?error=datos");
    exit();
}

// El "usuario" en el registro sirve como email/nombre de acceso
// Usamos el DNI como email único si no se pide email en el formulario
$email = $dni . "@hochschild.interno"; // Puedes cambiar esto si tienes campo email

// Verificar que el DNI no esté ya registrado
$check = $mysqli->prepare("SELECT id FROM usuarios WHERE email = ? LIMIT 1");
$check->bind_param("s", $email);
$check->execute();
$check->store_result();

if ($check->num_rows > 0) {
    $check->close();
    header("Location: registro.php?error=existe");
    exit();
}
$check->close();

// ─── GUARDAR CON HASH ─────────────────────────────────────────────
$hash      = password_hash($pass, PASSWORD_BCRYPT);
$rol       = 'agente';       // Rol por defecto, el admin lo cambia
$cargo     = 'Colaborador';
$estado    = 0;              // 0 = pendiente de aprobación

$stmt = $mysqli->prepare(
    "INSERT INTO usuarios (nombre, email, password, rol, cargo_real, estado) 
     VALUES (?, ?, ?, ?, ?, ?)"
);
$stmt->bind_param("sssssi", $usuario, $email, $hash, $rol, $cargo, $estado);
$ok = $stmt->execute();
$stmt->close();

if ($ok) {
    // Redirigir al login con mensaje de éxito
    header("Location: index.php?msg=registro_ok");
} else {
    header("Location: registro.php?error=db");
}
exit();
?>
