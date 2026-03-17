<?php
session_start();
require_once __DIR__ . "/config.php";

// SEGURIDAD: Solo el administrador entra aquí
if (!isset($_SESSION['usuario']) || $_SESSION['rol'] !== 'administrador') {
    header("Location: dashboard.php");
    exit();
}

$conn = $mysqli;

// 1. CAMBIAR ROL (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'cambiar_rol') {
    $id = intval($_POST['id']);
    $nuevo_rol = $_POST['nuevo_rol'];
    
    $stmt = $conn->prepare("UPDATE usuarios SET rol = ? WHERE id = ?");
    $stmt->bind_param("si", $nuevo_rol, $id);
    $stmt->execute();
    $stmt->close();
}

// 2. CREAR NUEVO USUARIO (POST) - ¡Nueva función integrada!
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'crear') {
    $nombre = trim($_POST['nombre']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $cargo_real = trim($_POST['cargo_real']);
    $rol = $_POST['rol'];
    
    // Encriptamos la contraseña para mantener la seguridad
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $estado = 1; // Activo por defecto
    $verificado = 1; // Verificado por defecto al ser creado por el admin
    
    $stmt = $conn->prepare("INSERT INTO usuarios (email, password, nombre, cargo_real, rol, estado, verificado) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssii", $email, $hash, $nombre, $cargo_real, $rol, $estado, $verificado);
    $stmt->execute();
    $stmt->close();
}

// 3. ACTIVAR O ELIMINAR (GET)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['accion']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $accion = $_GET['accion'];

    if ($accion === 'activar') {
        $stmt = $conn->prepare("UPDATE usuarios SET estado = 1 WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
    } elseif ($accion === 'eliminar') {
        $stmt = $conn->prepare("DELETE FROM usuarios WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
    }
}

// Volver al panel de administración silenciosamente
header("Location: usuarios_admin.php");
exit();
?>