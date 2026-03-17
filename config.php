<?php
/**
 * ARCHIVO DE CONFIGURACIÓN GLOBAL - SITRAN
 */

mysqli_report(MYSQLI_REPORT_OFF);

// ─── CREDENCIALES DE HOSTINGER ────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_USER', 'u480700204_hocadmin'); 
define('DB_PASS', 'Mina_2026'); 
define('DB_NAME', 'u480700204_hocadmin'); 

// ─── CONEXIÓN ─────────────────────────────────────────────────────
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($mysqli->connect_error) {
    error_log("DB Connect Error: " . $mysqli->connect_error);
    die(json_encode(["estado" => "ERROR", "mensaje" => "Error de base de datos"]));
}

$mysqli->set_charset("utf8mb4");

// ─── ZONA HORARIA PERÚ ─────────────────────────────────────────────
date_default_timezone_set('America/Lima');

// ─── FUNCIÓN HELPER GLOBAL ────────────────────────────────────────
if (!function_exists('h')) {
    function h($s) {
        return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
    }
}

// ─── FUNCIONES DE SESIÓN Y ROL ─────────────────────────────────────
if (!function_exists('requireSession')) {
    function requireSession() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['usuario'])) {
            header("Location: index.php");
            exit();
        }
    }
}

if (!function_exists('requireRol')) {
    function requireRol(array $roles) {
        requireSession();
        if (!in_array($_SESSION['rol'] ?? '', $roles)) {
            header("Location: dashboard.php");
            exit();
        }
    }
}
?>