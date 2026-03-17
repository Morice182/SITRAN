<?php
/**
 * includes/header.php — Cabecera compartida del sistema SITRAN
 * 
 * Variables esperadas (deben estar definidas antes de incluir):
 *   $page_title     — string: título específico de la página
 *   $extra_css      — string (opcional): ruta a CSS adicional
 *   $iniciales      — string: iniciales del usuario para el avatar
 *   $nombre_sesion  — string: nombre completo del usuario
 *
 * Este archivo genera el <head> completo más la barra de navegación superior.
 */

// Asegurar que la sesión esté activa
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: " . (strpos($_SERVER['PHP_SELF'], '/') !== false ? '../' : '') . "index.php");
    exit();
}

if (!function_exists('h')) {
    function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
}

// Valores por defecto
$page_title    = $page_title    ?? 'Sistema SITRAN · Hochschild Mining';
$extra_css     = $extra_css     ?? '';
$iniciales     = $iniciales     ?? strtoupper(substr($_SESSION['nombre'] ?? 'U', 0, 1));
$nombre_sesion = $nombre_sesion ?? ($_SESSION['nombre'] ?? $_SESSION['usuario'] ?? '');

// Detectar profundidad de ruta para assets
$depth  = (strpos($_SERVER['PHP_SELF'], '/includes/') !== false) ? '../' : '';
$assets = $depth . 'assets/';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="theme-color" content="#FFFFFF">
    <title><?= h($page_title) ?></title>
    <link rel="icon" type="image/png" href="<?= $assets ?>logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= $assets ?>css/base.css">
    <?php if ($extra_css): ?>
    <link rel="stylesheet" href="<?= h($extra_css) ?>">
    <?php endif; ?>
</head>
<body>

<!-- ══ TOPBAR ══ -->
<header class="topbar">
    <div class="topbar-inner">
        <div class="logo-block">
            <img class="logo-img" src="<?= $assets ?>logo.png" alt="Hochschild Mining"
                 onerror="this.style.display='none';this.nextElementSibling.style.display='block'">
            <svg class="logo-fallback" width="44" height="54" viewBox="0 0 44 54" fill="none">
                <path d="M1 11L12 5l6 7.5L12 20Z"     fill="#C49A2C"/>
                <path d="M1 25L12 17l6 9L12 35Z"       fill="#C49A2C" opacity=".78"/>
                <path d="M20 17l11-7 7 7.5-7 7.5Z"     fill="#B8BEC4"/>
                <path d="M20 33l11-7.5 7 7.5-7 8.5Z"   fill="#B8BEC4" opacity=".74"/>
            </svg>
            <div class="logo-wordmark">
                <span class="logo-name">HOCHSCHILD</span>
                <span class="logo-sub">Sistema de Transporte</span>
            </div>
        </div>
        <div class="top-space"></div>
        <span class="top-clock" id="clockEl">--:--:--</span>
        <div class="top-user">
            <div class="top-av"><?= h($iniciales) ?></div>
            <span class="top-name"><?= h($nombre_sesion) ?></span>
        </div>
        <a href="<?= $depth ?>logout.php" class="top-logout">
            <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            Salir
        </a>
    </div>
    <div class="topbar-accent"></div>
</header>

<div class="pull-bar" id="pullBar">
    <div class="pull-spin"></div>
    Actualizando datos…
</div>
