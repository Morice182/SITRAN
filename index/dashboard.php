<?php
/**
 * dashboard.php — Panel Principal
 * ✅ MEJORAS:
 * 1. KPI cards ahora visibles (antes calculados y nunca mostrados)
 * 2. Saludo dinámico según la hora del día
 * 3. SQL injection fix → prepared statement con email
 * 4. Animación escalonada en las tarjetas del menú
 * 5. Botón y Modal de Emergencia para reporte preliminar
 */

session_start();

if (!isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit();
}

require __DIR__ . "/config.php";

// ─── DATOS DEL USUARIO (prepared statement) ──────────────────────
$email_sesion  = $_SESSION['usuario'];
$nombre_sesion = $_SESSION['nombre'];

$stmt = $mysqli->prepare("SELECT cargo_real, rol FROM usuarios WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email_sesion);
$stmt->execute();
$stmt->bind_result($cargo_real, $rol_sistema);
if (!$stmt->fetch()) {
    $cargo_real  = $_SESSION['cargo_real'] ?? 'Colaborador';
    $rol_sistema = $_SESSION['rol'] ?? 'agente';
}
$stmt->close();

$hoy = date('Y-m-d');

// ─── ESTADÍSTICAS ─────────────────────────────────────────────────
$total_ingresos = 0;
$total_salidas  = 0;
$en_mina        = 0;
$total_personal = 0;

if ($rol_sistema === 'supervisor' || $rol_sistema === 'administrador') {
    $s1 = $mysqli->prepare("SELECT COUNT(*) FROM registros WHERE DATE(fecha)=? AND evento='SUBIDA PERMITIDA'");
    $s1->bind_param("s", $hoy); $s1->execute(); $s1->bind_result($total_ingresos); $s1->fetch(); $s1->close();

    $s2 = $mysqli->prepare("SELECT COUNT(*) FROM registros WHERE DATE(fecha)=? AND evento='BAJADA PERMITIDA'");
    $s2->bind_param("s", $hoy); $s2->execute(); $s2->bind_result($total_salidas); $s2->fetch(); $s2->close();

    $s3 = $mysqli->prepare("SELECT COUNT(*) FROM personal");
    $s3->execute(); $s3->bind_result($total_personal); $s3->fetch(); $s3->close();

    $en_mina = max(0, $total_ingresos - $total_salidas);
}

// ─── SALUDO POR HORA ─────────────────────────────────────────────
$hora = (int)date('H');
if ($hora < 12)      $saludo = 'Buenos días';
elseif ($hora < 19)  $saludo = 'Buenas tardes';
else                 $saludo = 'Buenas noches';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Dashboard | Hochschild Mining</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --h-gold: #c5a059;
            --h-gold-dark: #a68241;
            --h-dark: #1a1c1e;
            --h-gray-bg: #f0f2f5;
            --h-white: #ffffff;
            --text-700: #1e293b;
            --text-500: #475569;
            --text-300: #94a3b8;
            --radius-lg: 24px;
            --radius-md: 16px;
            --radius-sm: 8px;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background-color: var(--h-gray-bg);
            margin: 0;
            color: var(--text-500);
            -webkit-font-smoothing: antialiased;
        }

        /* ── HEADER ── */
        .header-main {
            background: var(--h-white);
            padding: 28px 16px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            position: relative;
        }
        .header-main img { height: 80px; margin-bottom: 10px; }
        .system-subtitle {
            display: block; font-size: 10px; font-weight: 700;
            color: var(--text-300); text-transform: uppercase; letter-spacing: 4px;
        }
        .logout-link {
            position: absolute; top: 20px; right: 20px;
            color: var(--text-300); font-size: 20px; transition: 0.2s ease;
            text-decoration: none;
        }
        .logout-link:hover { color: #ef4444; transform: rotate(90deg); }

        .container { max-width: 560px; margin: 0 auto; padding: 28px 16px 48px; }

        /* ── WELCOME CARD ── */
        .welcome-card {
            background: var(--h-dark);
            background-image: radial-gradient(ellipse at top right, rgba(197,160,89,0.15) 0%, transparent 60%);
            border-radius: var(--radius-lg);
            padding: 32px 28px;
            color: var(--h-white);
            text-align: center;
            margin-bottom: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }
        .saludo { font-size: 12px; color: var(--h-gold); font-weight: 600; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 8px; }
        .welcome-card h1 { margin: 0; font-size: 22px; font-weight: 700; color: var(--h-white); }
        .badge-group { margin-top: 20px; display: flex; justify-content: center; gap: 8px; flex-wrap: wrap; }
        .badge-item {
            font-size: 10px; font-weight: 700; padding: 5px 14px;
            border-radius: var(--radius-sm); text-transform: uppercase;
            border: 1px solid rgba(255,255,255,0.15); color: rgba(255,255,255,0.7);
        }
        <?php if ($rol_sistema === 'administrador'): ?>
        .role-badge { background: rgba(197,160,89,0.2); color: var(--h-gold); border-color: var(--h-gold); }
        <?php else: ?>
        .role-badge { background: rgba(255,255,255,0.05); color: var(--h-gold); }
        <?php endif; ?>

        /* ── KPI CARDS ── */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 28px;
        }
        .kpi-card {
            background: var(--h-white);
            border-radius: var(--radius-md);
            padding: 18px 12px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            border-bottom: 3px solid transparent;
            transition: transform 0.2s;
        }
        .kpi-card:hover { transform: translateY(-2px); }
        .kpi-card.entrada { border-color: var(--success); }
        .kpi-card.salida  { border-color: var(--danger); }
        .kpi-card.activo  { border-color: var(--h-gold); }
        .kpi-num  { font-size: 26px; font-weight: 800; color: var(--text-700); line-height: 1; }
        .kpi-label { font-size: 10px; font-weight: 700; color: var(--text-300); text-transform: uppercase; margin-top: 6px; letter-spacing: 0.5px; }
        .kpi-icon  { font-size: 14px; margin-bottom: 6px; }
        .kpi-card.entrada .kpi-icon { color: var(--success); }
        .kpi-card.salida  .kpi-icon { color: var(--danger); }
        .kpi-card.activo  .kpi-icon { color: var(--h-gold); }

        /* ── BOTÓN DE EMERGENCIA ── */
        .btn-emergencia {
            display: flex; align-items: center; justify-content: center; width: 100%;
            background: var(--danger); color: white; border: none; padding: 18px;
            border-radius: var(--radius-md); font-size: 15px; font-weight: 700;
            cursor: pointer; transition: all 0.3s ease; margin-bottom: 24px;
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
            animation: pulse-red 2s infinite; text-transform: uppercase; letter-spacing: 1px;
        }
        .btn-emergencia i { margin-right: 12px; font-size: 18px; }
        .btn-emergencia:hover { background: #dc2626; transform: translateY(-2px); box-shadow: 0 6px 20px rgba(239, 68, 68, 0.4); }
        .btn-emergencia:active { transform: translateY(0); }
        
        @keyframes pulse-red {
            0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.6); }
            70% { box-shadow: 0 0 0 10px rgba(239, 68, 68, 0); }
            100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
        }

        /* ── MODAL DE EMERGENCIA ── */
        .modal-emergencia {
            display: none; position: fixed; z-index: 1000; left: 0; top: 0;
            width: 100%; height: 100%; background-color: rgba(0,0,0,0.6);
            backdrop-filter: blur(4px); justify-content: center; align-items: center;
            opacity: 0; transition: opacity 0.3s ease;
        }
        .modal-emergencia.show { display: flex; opacity: 1; }
        .modal-content {
            background-color: var(--h-white); padding: 32px 24px; border-radius: var(--radius-lg);
            width: 90%; max-width: 480px; position: relative;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); border-top: 6px solid var(--danger);
            transform: translateY(-20px); transition: transform 0.3s ease;
        }
        .modal-emergencia.show .modal-content { transform: translateY(0); }
        .close-modal {
            position: absolute; top: 16px; right: 20px; color: var(--text-300);
            font-size: 28px; font-weight: bold; cursor: pointer; transition: 0.2s; line-height: 1;
        }
        .close-modal:hover { color: var(--danger); }
        
        .modal-header h2 {
            color: var(--danger); margin: 0 0 20px 0; font-size: 18px; 
            text-align: center; text-transform: uppercase; font-weight: 800;
        }
        .reporte-texto {
            background: #fef2f2; border: 1px solid #fecaca; border-radius: var(--radius-sm);
            padding: 20px; color: var(--text-700); font-size: 14px;
        }
        .reporte-texto p { margin: 12px 0; line-height: 1.5; }
        .reporte-texto p:first-child { margin-top: 0; }
        .reporte-texto p:last-child { margin-bottom: 0; }
        .reporte-texto strong { color: var(--danger); display: inline-block; width: 150px; }

        /* ── SECCIÓN LABEL ── */
        .section-label {
            text-align: center; margin-bottom: 16px;
            font-size: 10px; font-weight: 800; color: var(--text-300);
            text-transform: uppercase; letter-spacing: 2px;
        }

        /* ── MENÚ ── */
        .menu-list { display: flex; flex-direction: column; gap: 10px; }

        .menu-link {
            display: flex; align-items: center;
            background: var(--h-white); padding: 18px 20px;
            border-radius: var(--radius-md);
            text-decoration: none; border: 1px solid #edf2f7;
            transition: all 0.2s cubic-bezier(0.4,0,0.2,1);
            opacity: 0;
            animation: slideUp 0.4s ease-out forwards;
        }

        /* Animación escalonada */
        .menu-link:nth-child(1) { animation-delay: 0.05s; }
        .menu-link:nth-child(2) { animation-delay: 0.10s; }
        .menu-link:nth-child(3) { animation-delay: 0.15s; }
        .menu-link:nth-child(4) { animation-delay: 0.20s; }
        .menu-link:nth-child(5) { animation-delay: 0.25s; }
        .menu-link:nth-child(6) { animation-delay: 0.30s; }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(12px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .menu-link:hover { border-color: var(--h-gold); box-shadow: 0 8px 20px rgba(0,0,0,0.05); transform: translateY(-2px); }
        .menu-link:active { transform: scale(0.98); }

        .link-icon {
            width: 46px; height: 46px; background: #f8fafc; border-radius: 12px;
            display: flex; justify-content: center; align-items: center;
            margin-right: 18px; color: var(--h-dark); font-size: 17px;
            flex-shrink: 0; transition: 0.2s;
        }
        .menu-link:hover .link-icon { background: var(--h-gold); color: white; }

        .link-text h3 { margin: 0; font-size: 15px; color: var(--text-700); font-weight: 700; }
        .link-text p  { margin: 3px 0 0; font-size: 12px; color: var(--text-500); line-height: 1.4; }

        /* Entrada principal (Monitoreo) */
        .monitor-primary {
            background: var(--h-dark); border: none; padding: 22px 20px;
            box-shadow: 0 10px 25px rgba(26,28,30,0.2);
        }
        .monitor-primary .link-text h3 { color: white; font-size: 16px; }
        .monitor-primary .link-text p  { color: var(--text-300); }
        .monitor-primary .link-icon    { background: rgba(255,255,255,0.08); color: var(--h-gold); }
        .monitor-primary:hover         { border-color: transparent; }
        .monitor-primary:hover .link-icon { background: var(--h-gold); }

        /* ── FOOTER BRANDS ── */
        .footer-brands { margin-top: 48px; text-align: center; }
        .footer-brands p { font-size: 10px; font-weight: 700; color: #cbd5e1; text-transform: uppercase; margin-bottom: 20px; }
        .brand-logos { display: flex; justify-content: center; gap: 20px; flex-wrap: wrap; align-items: center; filter: grayscale(1); opacity: 0.5; }
        .brand-logos img { height: 32px; width: auto; object-fit: contain; }

        /* ── FECHA HOY ── */
        .fecha-hoy { font-size: 11px; color: var(--text-300); text-align: center; margin-bottom: 20px; }

        @media (prefers-reduced-motion: reduce) { .menu-link { animation: none; opacity: 1; transition: none; } }
        
        @media (max-width: 400px) {
            .reporte-texto strong { display: block; width: auto; margin-bottom: 4px; }
        }
    </style>
</head>
<body>

<header class="header-main">
    <img src="assets/logo.png" alt="Hochschild Mining">
    <span class="system-subtitle">SISTEMA INTEGRAL DE TRANSPORTE</span>
    <a href="logout.php" class="logout-link" title="Cerrar Sesión">
        <i class="fas fa-power-off"></i>
    </a>
</header>

<div class="container">

    <section class="welcome-card">
        <div class="saludo"><i class="fas fa-sun" style="margin-right:6px"></i><?= h($saludo) ?></div>
        <h1><?= h(strtoupper($nombre_sesion)) ?></h1>
        <div class="badge-group">
            <span class="badge-item"><?= h($cargo_real) ?></span>
            <span class="badge-item role-badge"><?= h($rol_sistema) ?></span>
        </div>
    </section>

    <?php if ($rol_sistema === 'supervisor' || $rol_sistema === 'administrador'): ?>
    <div class="fecha-hoy">
        <i class="fas fa-calendar-day"></i>
        Resumen del <?= date('d \d\e F \d\e Y') ?>
    </div>
    <div class="kpi-grid">
        <div class="kpi-card entrada">
            <div class="kpi-icon"><i class="fas fa-arrow-right-to-bracket"></i></div>
            <div class="kpi-num"><?= $total_ingresos ?></div>
            <div class="kpi-label">Ingresos hoy</div>
        </div>
        <div class="kpi-card activo">
            <div class="kpi-icon"><i class="fas fa-hard-hat"></i></div>
            <div class="kpi-num"><?= $en_mina ?></div>
            <div class="kpi-label">En mina</div>
        </div>
        <div class="kpi-card salida">
            <div class="kpi-icon"><i class="fas fa-arrow-right-from-bracket"></i></div>
            <div class="kpi-num"><?= $total_salidas ?></div>
            <div class="kpi-label">Salidas hoy</div>
        </div>
    </div>
    <?php endif; ?>

    <button id="btnEmergencia" class="btn-emergencia">
        <i class="fas fa-exclamation-triangle"></i> Reporte de Incidente
    </button>

    <div class="section-label">PANEL DE CONTROL</div>

    <nav class="menu-list">

        <?php if ($rol_sistema === 'supervisor' || $rol_sistema === 'administrador'): ?>
        <a href="monitoreoderuta_full.php" class="menu-link monitor-primary">
            <div class="link-icon"><i class="fas fa-chart-line"></i></div>
            <div class="link-text">
                <h3>Monitoreo de Ruta en Tiempo Real</h3>
                <p>Indicadores críticos y avance de embarque.</p>
            </div>
        </a>
        <?php endif; ?>

        <a href="buses.php" class="menu-link">
            <div class="link-icon"><i class="fas fa-bus"></i></div>
            <div class="link-text">
                <h3>Embarque de Buses</h3>
                <p>Control de ascensos y descensos.</p>
            </div>
        </a>

        <a href="index_manifiesto.php" class="menu-link">
            <div class="link-icon"><i class="fas fa-file-invoice"></i></div>
            <div class="link-text">
                <h3>Manifiesto de Pasajeros</h3>
                <p>Reportes de ruta y control de ocupación.</p>
            </div>
        </a>

        <a href="personal.php" class="menu-link">
            <div class="link-icon"><i class="fas fa-users-cog"></i></div>
            <div class="link-text">
                <h3>Base de Personal</h3>
                <p>Gestión de trabajadores registrados<?php if ($total_personal): ?> · <strong><?= $total_personal ?></strong> activos<?php endif; ?>.</p>
            </div>
        </a>

        <?php if ($rol_sistema === 'supervisor' || $rol_sistema === 'administrador'): ?>
        <a href="kpis_pro.php" class="menu-link">
            <div class="link-icon"><i class="fas fa-chart-pie"></i></div>
            <div class="link-text">
                <h3>Centro de Control Gerencial</h3>
                <p>KPIs, estadísticas y reportes históricos.</p>
            </div>
        </a>
        <?php endif; ?>

        <?php if ($rol_sistema === 'administrador'): ?>
        <a href="usuarios_admin.php" class="menu-link" style="border-right: 4px solid var(--h-gold);">
            <div class="link-icon"><i class="fas fa-shield-alt"></i></div>
            <div class="link-text">
                <h3>Gestión de Usuarios</h3>
                <p>Niveles de acceso y seguridad del sistema.</p>
            </div>
        </a>
        <?php endif; ?>

    </nav>

    <footer class="footer-brands">
        <p>Alianzas Estratégicas</p>
        <div class="brand-logos">
            <img src="Logos Transporte/dajor.png" alt="DAJOR">
            <img src="Logos Transporte/tpp.png" alt="TPP">
            <img src="Logos Transporte/caleb.png" alt="CALEB">
            <img src="Logos Transporte/new_road.png" alt="NEW ROAD">
        </div>
    </footer>

</div>

<div id="modalEmergencia" class="modal-emergencia">
    <div class="modal-content">
        <span class="close-modal" id="closeEmergencia">&times;</span>
        <div class="modal-header">
            <h2><i class="fas fa-exclamation-circle" style="margin-right: 8px;"></i>Reporte Preliminar<br>de Incidente</h2>
        </div>
        <div class="reporte-texto">
            <p><strong>Tipo de ocurrencia:</strong> Despiste y caída a desnivel (>45 metros).</p>
            <p><strong>Fecha y hora:</strong> 15 de enero, 16:00 h aprox.</p>
            <p><strong>Ubicación:</strong> Ruta a Cochapampa (sectores Tinya y Chipito), distrito de Cotahuasi, provincia de La Unión, Arequipa.</p>
            <p><strong>Datos del equipo:</strong> Bus interprovincial, placa F5Q-966.</p>
            <p><strong>Empresa operadora:</strong> Dajor Sur E.I.R.L.</p>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const btnEmergencia = document.getElementById('btnEmergencia');
        const modalEmergencia = document.getElementById('modalEmergencia');
        const closeEmergencia = document.getElementById('closeEmergencia');

        // Mostrar modal
        btnEmergencia.addEventListener('click', function() {
            modalEmergencia.classList.add('show');
        });

        // Cerrar modal con la 'X'
        closeEmergencia.addEventListener('click', function() {
            modalEmergencia.classList.remove('show');
        });

        // Cerrar modal al hacer clic fuera del contenido
        window.addEventListener('click', function(event) {
            if (event.target === modalEmergencia) {
                modalEmergencia.classList.remove('show');
            }
        });
    });
</script>

</body>
</html>