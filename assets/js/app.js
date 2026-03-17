/**
 * app.js — JavaScript Global · Hochschild Mining SITRAN
 * Incluye: reloj en tiempo real, toasts, modal de emergencia,
 *          pull-to-refresh AJAX real, bottom nav active state.
 */
(function () {
    'use strict';

    /* ──────────────────────────────────────────
       RELOJ EN TIEMPO REAL
    ────────────────────────────────────────── */
    const DIAS  = ['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'];
    const MESES = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];

    function padZ(v) { return String(v).padStart(2, '0'); }

    function tick() {
        const el = document.getElementById('clockEl');
        if (!el) return;
        const n = new Date();
        el.textContent =
            DIAS[n.getDay()] + ', ' + n.getDate() + ' ' + MESES[n.getMonth()] +
            '  ·  ' + padZ(n.getHours()) + ':' + padZ(n.getMinutes()) + ':' + padZ(n.getSeconds());
    }
    tick();
    setInterval(tick, 1000);

    /* ──────────────────────────────────────────
       SISTEMA DE TOASTS
    ────────────────────────────────────────── */
    let toastTimer = null;

    window.showToast = function (msg, type) {
        const t   = document.getElementById('toast');
        const ico = document.querySelector('.toast-ok');
        if (!t) return;

        if (ico) {
            ico.style.background = type === 'error' ? '#EF4444' : '#22C55E';
            ico.textContent = type === 'error' ? '✕' : '✓';
        }
        document.getElementById('toastMsg').textContent = msg;
        t.classList.add('show');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => t.classList.remove('show'), 2800);
    };

    /* ──────────────────────────────────────────
       MODAL DE EMERGENCIA
    ────────────────────────────────────────── */
    const ov          = document.getElementById('modalOverlay');
    const btnEmg      = document.getElementById('btnEmergencia');
    const btnEmgNav   = document.getElementById('btnEmgNav');
    const closeModalB = document.getElementById('closeModal');

    function openModal() {
        if (!ov) return;
        if (navigator.vibrate) navigator.vibrate([50, 25, 100]);
        ov.classList.add('open');
        document.body.style.overflow = 'hidden';
        if (closeModalB) closeModalB.focus();
    }

    function closeModal() {
        if (!ov) return;
        ov.classList.remove('open');
        document.body.style.overflow = '';
    }

    if (btnEmg)    btnEmg.addEventListener('click', openModal);
    if (btnEmgNav) btnEmgNav.addEventListener('click', openModal);
    if (closeModalB) closeModalB.addEventListener('click', closeModal);
    if (ov) {
        ov.addEventListener('click', e => { if (e.target === ov) closeModal(); });
        // Swipe down para cerrar en móvil
        let mStartY = 0;
        ov.addEventListener('touchstart', e => { mStartY = e.touches[0].clientY; }, { passive: true });
        ov.addEventListener('touchend',   e => {
            if (e.changedTouches[0].clientY - mStartY > 80) closeModal();
        }, { passive: true });
    }
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

    /* ──────────────────────────────────────────
       PULL-TO-REFRESH — AJAX REAL
    ────────────────────────────────────────── */
    function showSkeleton() {
        const grid  = document.getElementById('modGrid');
        const skel  = document.getElementById('skelGrid');
        const bar   = document.getElementById('pullBar');
        if (!grid || !skel || !bar) return;

        bar.style.display  = 'flex';
        grid.style.display = 'none';
        skel.style.display = 'grid';

        // Fetch real a la API de stats
        fetch('api/stats.php')
            .then(r => r.ok ? r.json() : null)
            .then(data => {
                if (data) updateKPIs(data);
                setTimeout(() => {
                    bar.style.display  = 'none';
                    skel.style.display = 'none';
                    grid.style.display = 'grid';
                    showToast('Datos actualizados');
                }, 600);
            })
            .catch(() => {
                setTimeout(() => {
                    bar.style.display  = 'none';
                    skel.style.display = 'none';
                    grid.style.display = 'grid';
                    showToast('Sin conexión', 'error');
                }, 600);
            });
    }

    function updateKPIs(data) {
        const fields = {
            'kpiPersonal': data.total_personal,
            'kpiBuses':    data.buses_activos,
            'kpiHoy':      data.registros_hoy,
        };
        for (const [id, val] of Object.entries(fields)) {
            const el = document.getElementById(id);
            if (el && val !== undefined) {
                el.textContent = val.toLocaleString('es-PE');
            }
        }
    }

    // Cargar KPIs al inicio de la página
    if (document.getElementById('kpiPersonal')) {
        fetch('api/stats.php')
            .then(r => r.ok ? r.json() : null)
            .then(data => { if (data) updateKPIs(data); })
            .catch(() => {});
    }

    let startY   = 0;
    let pulling  = false;

    document.addEventListener('touchstart', e => {
        if (window.scrollY === 0) startY = e.touches[0].clientY;
    }, { passive: true });

    document.addEventListener('touchend', e => {
        if (startY && !pulling && (e.changedTouches[0].clientY - startY) > 75) {
            pulling = true;
            showSkeleton();
            setTimeout(() => { pulling = false; }, 2800);
        }
        startY = 0;
    }, { passive: true });

    /* ──────────────────────────────────────────
       BOTTOM NAV — ACTIVE STATE
    ────────────────────────────────────────── */
    document.querySelectorAll('.bn-item').forEach(el => {
        el.addEventListener('click', function () {
            document.querySelectorAll('.bn-item').forEach(x => x.classList.remove('active'));
            this.classList.add('active');
        });
    });

})();
