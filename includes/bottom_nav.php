<?php
/**
 * includes/bottom_nav.php — Navegación inferior móvil compartida
 * 
 * Variables esperadas:
 *   $bn_active  — string: 'inicio' | 'buses' | 'personal' | 'monitor' | 'kpis'
 *   $es_sup     — bool: si el usuario es supervisor o admin
 *   $rol_sistema — string: rol del usuario
 *
 * Visible solo en pantallas ≤480px via CSS.
 */

$bn_active  = $bn_active  ?? 'inicio';
$es_sup     = $es_sup     ?? false;
$depth      = (strpos($_SERVER['PHP_SELF'], '/includes/') !== false) ? '../' : '';

function bn_class(string $name, string $active): string {
    return 'bn-item' . ($name === $active ? ' active' : '');
}
?>
<!-- ══ BOTTOM NAV (solo visible en móvil ≤480px vía CSS) ══ -->
<nav class="bottom-nav" id="bottomNav" aria-label="Navegación principal">

    <a href="<?= $depth ?>dashboard.php" class="<?= bn_class('inicio', $bn_active) ?>" data-page="inicio">
        <div class="bn-ico">
            <svg viewBox="0 0 24 24">
                <rect x="3"  y="3"  width="7" height="7" rx="1.5"/>
                <rect x="14" y="3"  width="7" height="7" rx="1.5"/>
                <rect x="3"  y="14" width="7" height="7" rx="1.5"/>
                <rect x="14" y="14" width="7" height="7" rx="1.5"/>
            </svg>
        </div>
        <span class="bn-lbl">Inicio</span>
    </a>

    <a href="<?= $depth ?>buses.php" class="<?= bn_class('buses', $bn_active) ?>">
        <div class="bn-ico">
            <svg viewBox="0 0 24 24">
                <rect x="2" y="5" width="20" height="14" rx="3"/>
                <path d="M2 9.5h20M8 5v4.5M16 5v4.5"/>
                <circle cx="6.5"  cy="15" r="1.5" fill="currentColor" stroke="none"/>
                <circle cx="17.5" cy="15" r="1.5" fill="currentColor" stroke="none"/>
            </svg>
        </div>
        <span class="bn-lbl">Buses</span>
    </a>

    <!-- Botón emergencia central elevado -->
    <div class="bn-emg" id="btnEmgNav" role="button" aria-label="Reporte de incidente" tabindex="0">
        <div class="bn-emg-circle">
            <svg viewBox="0 0 24 24">
                <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                <line x1="12" y1="9"  x2="12"   y2="13"/>
                <line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
        </div>
        <span class="bn-emg-lbl">Alerta</span>
    </div>

    <a href="<?= $depth ?>personal.php" class="<?= bn_class('personal', $bn_active) ?>">
        <div class="bn-ico">
            <svg viewBox="0 0 24 24">
                <circle cx="9" cy="7" r="4"/>
                <path d="M2 21c0-3.3 3.1-6 7-6s7 2.7 7 6"/>
                <circle cx="19" cy="8" r="2.5"/>
                <path d="M22 21c0-2.2-1.3-4-3-4.5"/>
            </svg>
        </div>
        <span class="bn-lbl">Personal</span>
    </a>

    <?php if ($es_sup): ?>
    <a href="<?= $depth ?>monitoreoderuta_full.php" class="<?= bn_class('monitor', $bn_active) ?>">
        <div class="bn-ico">
            <svg viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="2"/>
                <path d="M16.24 7.76a6 6 0 010 8.49M7.76 16.24a6 6 0 010-8.49M19.07 4.93a10 10 0 010 14.14M4.93 19.07a10 10 0 010-14.14"/>
            </svg>
        </div>
        <span class="bn-lbl">Monitor</span>
    </a>
    <?php else: ?>
    <a href="<?= $depth ?>kpis_pro.php" class="<?= bn_class('kpis', $bn_active) ?>">
        <div class="bn-ico">
            <svg viewBox="0 0 24 24">
                <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
            </svg>
        </div>
        <span class="bn-lbl">KPIs</span>
    </a>
    <?php endif; ?>

</nav>
