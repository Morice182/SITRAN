<?php
/**
 * includes/footer.php — Pie de página compartido + scripts globales
 * Cierra: </main> + footer + toast + scripts
 */

// Detectar profundidad de ruta
$depth  = (strpos($_SERVER['PHP_SELF'], '/includes/') !== false) ? '../' : '';
$assets = $depth . 'assets/';
?>

<!-- ══ FOOTER ══ -->
<div class="page-foot fi d5">
    <svg width="14" height="18" viewBox="0 0 14 18" fill="none" aria-hidden="true">
        <path d="M.5 4.5L4.5 2l3 4.5-3 4.5-4-2.5z" fill="#C49A2C" opacity=".6"/>
        <path d="M7.5 6.5L11 4l2.5 4-2.5 4-3-2z"   fill="#B8BEC4" opacity=".6"/>
        <path d="M1 11l3.5 2L7 17l-3-1z"             fill="#C49A2C" opacity=".35"/>
        <path d="M7 11l3 2 2.5 4-3-1z"               fill="#B8BEC4" opacity=".35"/>
    </svg>
    © <?= date('Y') ?> Hochschild Mining · Sistema Integral de Transporte
</div>

</main><!-- /.main-wrap -->

<!-- ══ TOAST ══ -->
<div class="toast" id="toast" role="status" aria-live="polite">
    <div class="toast-ok">✓</div>
    <span id="toastMsg">Actualizado</span>
</div>

<script src="<?= $assets ?>js/app.js"></script>
<?php if (!empty($extra_js)): ?>
<script src="<?= h($extra_js) ?>"></script>
<?php endif; ?>
</body>
</html>
