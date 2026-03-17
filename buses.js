/**
 * buses.js — Lógica de embarque de buses
 * Hochschild Mining
 *
 * Mejoras aplicadas:
 * - Estado encapsulado en AppState (sin variables globales sueltas)
 * - CSRF token en todas las peticiones fetch
 * - Funciones pequeñas y con responsabilidad única
 * - Mapa de asientos dinámico según capacidad del bus
 * - Historial ampliado a 20 registros
 * - Accesibilidad: aria-pressed en botones de modo
 */

'use strict';

// ─── ESTADO GLOBAL ENCAPSULADO ────────────────────────────────────────────────
const AppState = {
    html5QrCode:    null,
    lastResult:     null,
    countResults:   0,
    sessionCounter: 0,
    audioCtx:       null,
    csrfToken:      document.querySelector('meta[name="csrf-token"]')?.content ?? '',
    HIST_MAX:       20,   // cantidad máxima de registros en historial
    SCAN_CONFIRMS:  2,    // lecturas consecutivas para confirmar escaneo
};

// ─── INICIALIZACIÓN ───────────────────────────────────────────────────────────
window.addEventListener('load', () => {
    aplicarDarkModeGuardado();
    renderHistorial();
    iniciarScanner();
});

// ─── HELPERS GENERALES ────────────────────────────────────────────────────────

function showLoader(show) {
    document.getElementById('loader').style.display = show ? 'flex' : 'none';
}

/**
 * Construye los headers base para fetch con CSRF token.
 * @param {boolean} jsonContent - true si Content-Type es application/x-www-form-urlencoded
 */
function buildHeaders(jsonContent = false) {
    const headers = { 'X-CSRF-Token': AppState.csrfToken };
    if (jsonContent) headers['Content-Type'] = 'application/x-www-form-urlencoded';
    return headers;
}

/**
 * Wrapper centralizado para fetch con manejo de errores.
 * @param {string} url
 * @param {RequestInit} options
 * @returns {Promise<any>} JSON parseado
 */
async function apiFetch(url, options = {}) {
    // Inyectar CSRF en headers
    options.headers = { ...buildHeaders(), ...(options.headers ?? {}) };

    const res = await fetch(url, options);
    if (!res.ok) throw new Error(`HTTP ${res.status}: ${res.statusText}`);
    return res.json();
}

/** Muestra un beep de feedback sonoro. */
function playBeep(freq, dur) {
    try {
        if (!AppState.audioCtx) AppState.audioCtx = new AudioContext();
        const osc  = AppState.audioCtx.createOscillator();
        const gain = AppState.audioCtx.createGain();
        osc.connect(gain);
        gain.connect(AppState.audioCtx.destination);
        osc.frequency.value = freq;
        gain.gain.value = 0.05;
        osc.start();
        osc.stop(AppState.audioCtx.currentTime + dur / 1000);
    } catch (e) { /* silencioso si el navegador bloquea audio */ }
}

// ─── DARK MODE ────────────────────────────────────────────────────────────────

function aplicarDarkModeGuardado() {
    if (localStorage.getItem('darkBus') === 'true') toggleDarkMode();
}

function toggleDarkMode() {
    document.body.classList.toggle('dark-mode');
    const isDark = document.body.classList.contains('dark-mode');
    localStorage.setItem('darkBus', isDark);
    document.getElementById('darkIcon').className = isDark ? 'fas fa-sun' : 'fas fa-moon';
}

// ─── MODO (NORMAL / BAJADA) ───────────────────────────────────────────────────

function setModo(m) {
    document.getElementById('modo_actual').value = m;

    // Actualizar clases sin sobreescribir className completo
    document.getElementById('btnNormal').classList.toggle('active-normal', m === 'NORMAL');
    document.getElementById('btnNormal').classList.toggle('active-bajada', false);
    document.getElementById('btnBajada').classList.toggle('active-bajada', m === 'BAJADA');
    document.getElementById('btnBajada').classList.toggle('active-normal', false);

    // Accesibilidad
    document.getElementById('btnNormal').setAttribute('aria-pressed', m === 'NORMAL');
    document.getElementById('btnBajada').setAttribute('aria-pressed', m === 'BAJADA');

    const divBajada = document.getElementById('div-lugar-bajada');
    const visible   = m === 'BAJADA';
    divBajada.style.display = visible ? 'block' : 'none';
    if (visible) document.getElementById('lugar_bajada').focus();
}

// ─── MODALES: HELPERS ─────────────────────────────────────────────────────────

function abrirModal(id)  { document.getElementById(id).style.display = 'flex'; }
function cerrarModal(id) { document.getElementById(id).style.display = 'none'; }

// ─── MODAL: VALIDACIÓN MANUAL ─────────────────────────────────────────────────

function abrirManual() {
    abrirModal('modalManual');
    document.getElementById('dniManual').focus();
}

function cerrarManual() {
    cerrarModal('modalManual');
    document.getElementById('dniManual').value = '';
}

async function enviarManual() {
    const d = document.getElementById('dniManual').value.trim();
    if (d.length !== 8) return;
    cerrarManual();
    await detenerScanner();
    document.getElementById('reader-wrapper').style.display = 'none';
    validarDNI(d);
}

// ─── MODAL: AGREGAR PASAJERO ──────────────────────────────────────────────────

function abrirAgregarManual() {
    prepararModalAgregar('', 'MANUAL');
}

function cerrarAgregar() {
    cerrarModal('modalAgregar');
}

/**
 * Resetea y configura el modal de agregar pasajero según el caso.
 * @param {string} dni
 * @param {'MANUAL'|'NO_EXISTE'|'FALTA_VIAJE'} caso
 * @param {string} [nombrePre]
 */
function prepararModalAgregar(dni, caso, nombrePre = '') {
    // Resetear campos
    const campos = ['new_dni','new_bus','new_destino','new_asiento','new_nombres','new_apellidos','new_empresa'];
    campos.forEach(id => { document.getElementById(id).value = ''; });
    document.getElementById('new_tipo').value = 'subida';
    document.getElementById('txtAsientoSeleccionado').innerText = 'Ninguno seleccionado';
    resetearMapaAsientos();

    // Configurar según caso
    const boxPersonal = document.getElementById('extra_personal_fields');
    const title       = document.getElementById('tituloAgregar');
    const sub         = document.getElementById('subtituloAgregar');
    const inputDni    = document.getElementById('new_dni');

    inputDni.value    = dni;

    if (caso === 'MANUAL' || caso === 'NO_EXISTE') {
        inputDni.readOnly        = caso !== 'MANUAL';
        boxPersonal.style.display = 'block';
        title.innerText          = 'Pasajero Nuevo / Externo';
        sub.innerText            = 'Este DNI no existe en BD. Regístrelo completo.';
        if (caso === 'NO_EXISTE') playBeep(200, 400);
    } else if (caso === 'FALTA_VIAJE') {
        inputDni.readOnly        = true;
        boxPersonal.style.display = 'none';
        title.innerText          = `Asignar Viaje a: ${nombrePre}`;
        sub.innerText            = 'Personal validado en BD. Seleccione asiento.';
        playBeep(440, 200);
    }

    abrirModal('modalAgregar');
}

function resetearMapaAsientos() {
    document.getElementById('busMap').innerHTML =
        '<p class="bus-placeholder">Seleccione un Bus para ver asientos</p>';
}

// ─── MAPA DE ASIENTOS ─────────────────────────────────────────────────────────

async function cargarMapaAsientos() {
    const bus    = document.getElementById('new_bus').value;
    const tipo   = document.getElementById('new_tipo').value;
    const mapDiv = document.getElementById('busMap');

    document.getElementById('new_asiento').value             = '';
    document.getElementById('txtAsientoSeleccionado').innerText = 'Ninguno seleccionado';

    if (!bus) {
        mapDiv.innerHTML = '<p class="bus-placeholder">Seleccione un Bus primero</p>';
        return;
    }

    mapDiv.innerHTML = '<p class="bus-placeholder"><i class="fas fa-spinner fa-spin"></i> Cargando asientos...</p>';

    try {
        const formData = new FormData();
        formData.append('bus',  bus);
        formData.append('tipo', tipo);

        // Se espera que ver_asientos.php devuelva { ocupados: [1,3,5,...], total: 30 }
        const data = await apiFetch('ver_asientos.php', { method: 'POST', body: formData });
        dibujarBus(data.ocupados ?? data, data.total ?? 30);
    } catch (e) {
        console.error('Error cargando mapa:', e);
        mapDiv.innerHTML = '<p class="bus-placeholder error">Error cargando mapa. Intente de nuevo.</p>';
    }
}

/**
 * Dibuja el mapa visual de asientos.
 * @param {number[]} ocupados - array de números de asientos ocupados
 * @param {number}   total    - capacidad total del bus
 */
function dibujarBus(ocupados, total) {
    const mapDiv = document.getElementById('busMap');
    mapDiv.innerHTML = '';

    for (let i = 1; i <= total; i++) {
        const seat      = document.createElement('div');
        seat.className  = 'seat-item';
        seat.innerText  = i;
        seat.setAttribute('role', 'button');
        seat.setAttribute('aria-label', `Asiento ${i}`);

        if (ocupados.includes(i)) {
            seat.classList.add('occupied');
            seat.setAttribute('aria-disabled', 'true');
            seat.title = 'Ocupado';
        } else {
            seat.setAttribute('tabindex', '0');
            seat.onclick   = () => seleccionarAsiento(i, seat);
            seat.onkeydown = (e) => { if (e.key === 'Enter' || e.key === ' ') seleccionarAsiento(i, seat); };
        }

        mapDiv.appendChild(seat);

        // Pasillo visual cada 2 asientos (columna de pasillo = posición impar dentro del par)
        if (i % 2 === 0 && i % 4 !== 0) {
            const aisle       = document.createElement('div');
            aisle.className   = 'aisle';
            aisle.setAttribute('aria-hidden', 'true');
            mapDiv.appendChild(aisle);
        }
    }
}

function seleccionarAsiento(num, element) {
    document.querySelectorAll('.seat-item.selected').forEach(el => {
        el.classList.remove('selected');
        el.setAttribute('aria-pressed', 'false');
    });
    element.classList.add('selected');
    element.setAttribute('aria-pressed', 'true');
    document.getElementById('new_asiento').value             = num;
    document.getElementById('txtAsientoSeleccionado').innerText = `ASIENTO SELECCIONADO: ${num}`;
}

// ─── GUARDAR PASAJERO EXTRA ───────────────────────────────────────────────────

async function guardarExtra() {
    const dni     = document.getElementById('new_dni').value.trim();
    const bus     = document.getElementById('new_bus').value;
    const tipo    = document.getElementById('new_tipo').value;
    const destino = document.getElementById('new_destino').value.toUpperCase().trim();
    const asiento = document.getElementById('new_asiento').value;
    const isFullRegister = document.getElementById('extra_personal_fields').style.display !== 'none';

    // Validaciones
    if (!dni || !bus) {
        return Swal.fire({ icon: 'warning', title: 'Faltan Datos', text: 'Seleccione DNI y Bus' });
    }
    if (!asiento) {
        return Swal.fire({ icon: 'warning', title: 'Asiento', text: 'Por favor seleccione un asiento en el mapa' });
    }
    if (isFullRegister) {
        const nombres   = document.getElementById('new_nombres').value.trim();
        const apellidos = document.getElementById('new_apellidos').value.trim();
        const empresa   = document.getElementById('new_empresa').value.trim();
        if (!nombres || !apellidos || !empresa) {
            return Swal.fire({ icon: 'warning', title: 'Faltan Datos', text: 'Nombre y Empresa requeridos' });
        }
    }

    showLoader(true);
    try {
        const formData = new FormData();
        formData.append('dni',     dni);
        formData.append('bus',     bus);
        formData.append('tipo',    tipo);
        formData.append('destino', destino);
        formData.append('asiento', asiento);

        if (isFullRegister) {
            formData.append('nombres',        document.getElementById('new_nombres').value.toUpperCase().trim());
            formData.append('apellidos',      document.getElementById('new_apellidos').value.toUpperCase().trim());
            formData.append('empresa',        document.getElementById('new_empresa').value.toUpperCase().trim());
            formData.append('modo_registro',  'COMPLETO');
        } else {
            formData.append('modo_registro', 'SOLO_VIAJE');
        }

        const data = await apiFetch('guardar_extra.php', { method: 'POST', body: formData });
        showLoader(false);

        if (data.success) {
            Swal.fire({ icon: 'success', title: '¡Listo!', text: `Asiento ${asiento} reservado.`, timer: 1500, showConfirmButton: false });
            cerrarAgregar();
            await detenerScanner();
            document.getElementById('reader-wrapper').style.display = 'none';
            validarDNI(dni);
        } else {
            Swal.fire({ icon: 'error', title: 'Error', text: data.message });
        }
    } catch (e) {
        showLoader(false);
        Swal.fire({ icon: 'error', title: 'Error de Red', text: 'No se pudo conectar con el servidor' });
    }
}

// ─── CARGA FÍSICO ─────────────────────────────────────────────────────────────

function abrirCargaFisico() { abrirModal('modalCarga'); }
function cerrarCarga()      { cerrarModal('modalCarga'); }

async function procesarCarga() {
    const tipo = document.getElementById('doc_tipo').value;
    const bus  = document.getElementById('doc_bus').value;
    const file = document.getElementById('doc_file').files[0];

    if (!bus || !file) {
        return Swal.fire('Atención', 'Seleccione Unidad y Archivo', 'warning');
    }

    showLoader(true);
    const formData = new FormData();
    formData.append('tipo', tipo);
    formData.append('bus',  bus);
    formData.append('file', file);

    try {
        const data = await apiFetch('upload_manifiesto.php', { method: 'POST', body: formData });
        showLoader(false);
        if (data.success) {
            Swal.fire('Éxito', 'Manifiesto guardado correctamente', 'success');
            cerrarCarga();
        } else {
            Swal.fire('Error', data.message, 'error');
        }
    } catch (e) {
        showLoader(false);
        Swal.fire('Error', 'No hay conexión con el servidor', 'error');
    }
}

// ─── SCANNER QR ───────────────────────────────────────────────────────────────

/** Detiene el scanner de forma segura. */
async function detenerScanner() {
    if (AppState.html5QrCode) {
        try { await AppState.html5QrCode.stop(); } catch (e) { /* ignorar si ya estaba detenido */ }
    }
}

async function iniciarScanner() {
    document.getElementById('reader-wrapper').style.display = 'block';
    document.getElementById('btnSiguiente').disabled = true;
    document.getElementById('datos').innerHTML = '';
    document.getElementById('status').innerHTML =
        `<div class="big">LISTO</div><div class="pill neutral">ESPERANDO CÓDIGO</div>`;

    await detenerScanner();

    try {
        AppState.html5QrCode   = new Html5Qrcode('reader');
        AppState.lastResult    = null;
        AppState.countResults  = 0;

        const config = { fps: 20, qrbox: { width: 300, height: 150 }, aspectRatio: 1.0 };

        await AppState.html5QrCode.start(
            { facingMode: 'environment' },
            config,
            onScanSuccess,
            () => { /* errores de frame: ignorar */ }
        );
    } catch (err) {
        document.getElementById('reader-wrapper').style.display = 'none';
        document.getElementById('status').innerHTML =
            `<div class="pill no" style="width:100%">CÁMARA NO DISPONIBLE<br>USE INGRESO MANUAL</div>`;
    }
}

/** Callback de escaneo exitoso — requiere lecturas consecutivas para confirmar. */
function onScanSuccess(rawDni) {
    const d = rawDni.trim();
    if (d.length < 8) return;

    if (d === AppState.lastResult) {
        AppState.countResults++;
    } else {
        AppState.lastResult   = d;
        AppState.countResults = 0;
        return;
    }

    if (AppState.countResults >= AppState.SCAN_CONFIRMS) {
        AppState.html5QrCode.stop()
            .finally(() => {
                document.getElementById('reader-wrapper').style.display = 'none';
                validarDNI(d);
            });
    }
}

// ─── VALIDAR DNI ──────────────────────────────────────────────────────────────

async function validarDNI(dni) {
    showLoader(true);

    const modo        = document.getElementById('modo_actual').value;
    let   lugarBajada = '';

    if (modo === 'BAJADA') {
        lugarBajada = document.getElementById('lugar_bajada').value.trim();
        if (!lugarBajada) {
            showLoader(false);
            Swal.fire({ icon: 'warning', title: 'Atención', text: 'Ingrese el LUGAR DE DESEMBARQUE antes de escanear', confirmButtonColor: '#000000' });
            document.getElementById('btnSiguiente').disabled = false;
            return;
        }
    }

    try {
        const body = new URLSearchParams({ dni, modo, ubicacion: lugarBajada }).toString();
        const data = await apiFetch('validar.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body,
        });

        showLoader(false);

        // ── Casos especiales ──
        if (data.estado === 'FALTA_VIAJE') {
            prepararModalAgregar(dni, 'FALTA_VIAJE', data.persona?.nombres ?? '');
            document.getElementById('btnSiguiente').disabled = false;
            return;
        }
        if (data.estado === 'NO_EXISTE') {
            prepararModalAgregar(dni, 'NO_EXISTE');
            document.getElementById('btnSiguiente').disabled = false;
            return;
        }
        if (data.estado === 'ERROR') {
            Swal.fire('Error', data.mensaje, 'error');
            document.getElementById('btnSiguiente').disabled = false;
            return;
        }

        // ── Resultado normal ──
        const isOk = data.estado === 'AUTORIZADO';
        actualizarColorFondo(modo, isOk);
        playBeep(isOk ? 880 : 220, isOk ? 200 : 300);

        let pillClass = isOk ? 'ok' : 'no';
        if (modo === 'BAJADA' && isOk) pillClass = 'warn';

        document.getElementById('status').innerHTML =
            `<div class="big">${data.estado}</div>
             <div style="font-weight:600">${data.movimiento}</div>
             <div class="pill ${pillClass}">${isOk ? 'ACCESO VÁLIDO' : 'DENEGADO'}</div>`;

        renderizarTarjeta(data, dni);

        if (isOk) {
            AppState.sessionCounter++;
            document.getElementById('sessionCount').innerText = `Escaneados: ${AppState.sessionCounter}`;
            guardarEnHistorial(dni, data);
        }

    } catch (e) {
        showLoader(false);
        Swal.fire({ icon: 'error', title: 'Error de Red', text: 'Revise su conexión' });
    }

    document.getElementById('btnSiguiente').disabled = false;
}

function actualizarColorFondo(modo, isOk) {
    if (modo === 'BAJADA') {
        document.body.style.background = '#fff3e0';
    } else {
        document.body.style.background = isOk ? '#dcfce7' : '#fee2e2';
    }
}

// ─── RENDERIZAR TARJETA DE PERSONA ────────────────────────────────────────────

function renderizarTarjeta(data, dni) {
    const c = document.getElementById('datos');

    if (!data.persona) {
        c.innerHTML = `<div class="card" style="text-align:center"><h2>${dni}</h2><p>DNI NO ENCONTRADO</p></div>`;
        return;
    }

    const p          = data.persona;
    const fotoHTML   = construirFoto(data.foto);
    const badgeClass = obtenerClaseBadge(p.validacion);
    const est        = (p.validacion ?? 'VISITA').toUpperCase();
    const telHTML    = construirTelEmergencia(p.med_te);
    const medId      = `medInfo_${dni}`;

    c.innerHTML = `
    <div class="card">
        ${fotoHTML}
        <div class="card-name">
            <div class="nombre-completo">${p.nombres}<br>${p.apellidos}</div>
            <div class="badge ${badgeClass}">${est}</div>
        </div>
        <div class="info-grid">
            <div class="info-item"><span class="label">DNI</span>    <span class="value mono">${p.dni}</span></div>
            <div class="info-item"><span class="label">Empresa</span><span class="value">${p.empresa}</span></div>
            <div class="info-item"><span class="label">Área</span>   <span class="value">${p.area}</span></div>
            <div class="info-item"><span class="label">Cargo</span>  <span class="value">${p.cargo}</span></div>
        </div>
        <div id="${medId}" class="med-panel" style="display:none;">
            <div class="med-title"><i class="fas fa-ambulance"></i> DATOS DE EMERGENCIA</div>
            <b>G.S.:</b> ${p.med_gs || '---'}<br>
            <b>Contacto:</b> ${p.med_ce || '---'} (${telHTML})<br>
            <b>Alergias/Enfermedades:</b> ${p.med_al || 'NINGUNA'} / ${p.med_en || 'NINGUNA'}
        </div>
        <button onclick="mostrarDatosMedicos('${medId}', this)" class="btn-med">
            <i class="fas fa-exclamation-triangle"></i> VER DATOS MÉDICOS
        </button>
        <div class="trip-box">
            <div class="trip-row">
                <span class="label">UNIDAD / PLACA</span><br>
                <span class="trip-highlight trip-mono">${p.bus} • ${p.placa}</span>
            </div>
            <div class="trip-row trip-row-sep">
                <span class="label">SUBIDA/DESTINO</span><br>
                <span class="trip-highlight">${data.destino}</span>
            </div>
        </div>
    </div>`;
}

function construirFoto(fotoUrl) {
    return fotoUrl
        ? `<img src="${fotoUrl}" class="foto" alt="Foto del pasajero">`
        : `<div class="foto foto-placeholder"><i class="fas fa-user fa-4x"></i></div>`;
}

function obtenerClaseBadge(validacion) {
    const v = (validacion ?? '').toUpperCase();
    if (v.includes('AFILIADO')) return 'st-afiliado';
    if (v.includes('PROCESO'))  return 'st-proceso';
    return 'st-visita';
}

function construirTelEmergencia(tel) {
    return tel
        ? `<a href="tel:${tel}" class="tel-emergencia"><i class="fas fa-phone"></i> ${tel}</a>`
        : '---';
}

function mostrarDatosMedicos(panelId, btn) {
    document.getElementById(panelId).style.display = 'block';
    btn.style.display = 'none';
}

// ─── HISTORIAL ────────────────────────────────────────────────────────────────

function guardarEnHistorial(dni, data) {
    let history = obtenerHistorial();
    history.unshift({
        dni,
        nombre: data.persona?.nombres ?? 'Desc.',
        estado: data.estado,
        hora:   new Date().toLocaleTimeString(),
    });
    localStorage.setItem('histBus', JSON.stringify(history.slice(0, AppState.HIST_MAX)));
    renderHistorial();
}

function obtenerHistorial() {
    try {
        return JSON.parse(localStorage.getItem('histBus') ?? '[]');
    } catch {
        return [];
    }
}

function renderHistorial() {
    const history = obtenerHistorial();
    const html    = history.map(item => `
        <div class="hist-item ${item.estado.toLowerCase()}" role="listitem">
            <div>
                <b class="mono">${item.dni}</b><br>
                <small class="text-muted">${item.nombre}</small>
            </div>
            <div style="text-align:right">
                <b>${item.estado}</b><br>
                <small>${item.hora}</small>
            </div>
        </div>`).join('');

    document.getElementById('historialList').innerHTML = html || '<p class="bus-placeholder">Sin registros aún</p>';
}
