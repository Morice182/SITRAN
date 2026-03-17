<?php
session_start();
if (!isset($_SESSION['usuario'])) { header("Location: index.php"); exit(); }
$rol_usuario = $_SESSION['rol']; 

require_once __DIR__ . "/config.php";
$conn = $mysqli;
$conn->set_charset("utf8mb4");

// CARGAR LISTA DE BUSES
$buses_opt = "";
$q_bus = mysqli_query($conn, "SELECT DISTINCT bus FROM lista_bajada UNION SELECT DISTINCT bus FROM lista_subida ORDER BY bus");
while($r = mysqli_fetch_array($q_bus)) { 
    if(!empty($r['bus'])) $buses_opt .= "<option value='".$r['bus']."'>".$r['bus']."</option>"; 
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Control - Hochschild</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">

<!-- LIBRERÍAS -->
<script src="https://unpkg.com/html5-qrcode"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<style>
/* --- ESTILOS BASE --- */
:root { --brandGold: #b8872b; --brandGoldLight: #d4a752; --bg: #f8fafc; --okColor: #10b981; --noColor: #ef4444; --card: #ffffff; --textMain: #1e293b; --textMuted: #64748b; --shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
body.dark-mode { --bg: #0f172a; --card: #1e293b; --textMain: #f8fafc; --textMuted: #94a3b8; --shadow: 0 20px 25px -5px rgba(0,0,0,0.5); }
body { font-family: 'Segoe UI', 'Inter', sans-serif; margin: 0; background: var(--bg); color: var(--textMain); height: 100vh; display: flex; flex-direction: column; overflow: hidden; transition: 0.3s; }

.topbar { background: var(--card); border-bottom: 1px solid rgba(0,0,0,0.05); z-index: 100; }
.topbar-inner { max-width: 520px; margin: 0 auto; display: grid; grid-template-columns: 60px 1fr 60px; align-items: center; padding: 12px; }
.headerTitle { text-align: center; color: var(--brandGold); font-weight: 800; font-size: 13px; letter-spacing: 1px; }
.goldLine { height: 4px; background: linear-gradient(90deg, var(--brandGold), var(--brandGoldLight)); }

.wrap { max-width: 520px; margin: 0 auto; padding: 20px; flex-grow: 1; overflow-y: auto; width: 100%; box-sizing: border-box; }

.action-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 15px; }
.btn-quick { background: var(--card); border: 1px solid #e2e8f0; border-radius: 16px; padding: 15px 10px; text-align: center; text-decoration: none; color: var(--textMain); font-weight: 700; font-size: 11px; display: flex; flex-direction: column; align-items: center; gap: 8px; box-shadow: var(--shadow); transition: transform 0.1s; }
.btn-quick:active { transform: scale(0.98); }
.btn-quick i { font-size: 20px; }

#reader-wrapper { position: relative; width: 100%; border-radius: 24px; overflow: hidden; background: #000; box-shadow: var(--shadow); border: 4px solid var(--card); min-height: 250px; }
#reader-wrapper::before { content: ""; position: absolute; top: 50%; left: 0; width: 100%; height: 3px; background: red; animation: scan 2s linear infinite; z-index: 99; box-shadow: 0 0 5px red; opacity: 0.7; }
@keyframes scan { 0% {top: 10%} 50% {top: 90%} 100% {top: 10%} }
#reader { width: 100%; height: 100%; object-fit: cover; }

.status { margin: 20px 0; text-align: center; }
.status .big { font-size: 32px; font-weight: 800; }
.pill { display: inline-flex; padding: 6px 14px; border-radius: 100px; font-size: 11px; font-weight: 700; text-transform: uppercase; margin-top: 8px; }
.pill.neutral { background: var(--card); color: var(--textMuted); border: 1px solid rgba(0,0,0,0.1); }
.pill.ok { background: #dcfce7; color: #166534; }
.pill.no { background: #fee2e2; color: #991b1b; }
.pill.warn { background: #000; color: #fff; border: 1px solid #b8872b; }

.card { background: var(--card); border-radius: 28px; padding: 25px; box-shadow: var(--shadow); animation: slideUp 0.4s ease-out; }
@keyframes slideUp { from {opacity: 0; transform: translateY(20px)} to {opacity: 1; transform: translateY(0)} }
.foto { width: 120px; height: 140px; border-radius: 20px; object-fit: cover; display: block; margin: 0 auto 20px; border: 4px solid var(--bg); box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
.info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px 15px; border-top: 1px solid rgba(0,0,0,0.05); padding-top: 20px; }
.info-item { display: flex; flex-direction: column; gap: 4px; }
.label { font-size: 10px; color: var(--textMuted); text-transform: uppercase; font-weight: 800; letter-spacing: 0.5px; }
.value { font-size: 14px; font-weight: 600; color: var(--textMain); }
.value.mono { font-family: 'Courier New', monospace; font-size: 16px; letter-spacing: -0.5px; }
.trip-box { margin-top: 20px; padding: 18px; border-radius: 20px; background: rgba(184, 135, 43, 0.08); border: 1.5px dashed var(--brandGold); text-align: center; }
.trip-highlight { color: var(--brandGold); font-size: 18px; font-weight: 800; display: block; margin-top: 5px; }

.mode-bar { display: flex; gap: 5px; margin-bottom: 15px; }
.btn-mode { flex: 1; padding: 10px 2px; border: none; border-radius: 8px; font-weight: 700; font-size: 10px; cursor: pointer; transition: 0.2s; display: flex; flex-direction: column; align-items: center; gap: 4px; color: #64748b; background: #e2e8f0; text-decoration: none; }
.btn-mode i { font-size: 14px; }
.btn-mode.active-normal { background: var(--brandGold); color: white; }
.btn-mode.active-bajada { background: #000000; color: white; }
#div-lugar-bajada { display: none; background: #222; padding: 10px; border-radius: 10px; border-left: 4px solid var(--brandGold); margin-bottom: 15px; animation: fadeIn 0.3s; }
.input-bajada { width: 100%; padding: 10px; border: none; border-radius: 6px; font-size: 14px; font-weight: bold; color: #333; outline: none; box-sizing: border-box; background: white; }

/* MODALES */
.modal { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); backdrop-filter:blur(5px); z-index:200; align-items:center; justify-content:center; }
.modal-content { background:var(--card); padding:25px; border-radius:30px; width:90%; max-width:400px; text-align:center; max-height:90vh; overflow-y:auto; }
.input-dni { width:100%; padding:15px; font-size:28px; text-align:center; border-radius:18px; border:2px solid var(--brandGold); margin:20px 0; font-weight:800; background: var(--bg); color: var(--textMain); outline: none; }
.input-form { width:100%; padding:12px; font-size:14px; border-radius:12px; border:1px solid #ccc; margin:5px 0 15px; background: var(--bg); color: var(--textMain); box-sizing: border-box; }
.label-form { text-align: left; display: block; font-size: 11px; font-weight: 800; color: var(--textMuted); text-transform: uppercase; margin-left: 5px; }
.btn-action { width:100%; padding:14px; border-radius:18px; border:none; font-weight:800; cursor:pointer; margin-bottom:10px; font-size: 13px; display: flex; align-items: center; justify-content: center; gap: 8px; background: var(--card); color: var(--textMain); border: 1px solid #ddd; }
.btn-add { width:100%; padding:14px; border-radius:18px; border:2px dashed var(--brandGold); background: transparent; color: var(--brandGold); font-weight:800; margin-bottom:20px; cursor:pointer; font-size:13px; }

/* --- ESTILOS PARA EL MAPA DEL BUS --- */
.bus-container { background: #e2e8f0; border-radius: 20px; padding: 15px; margin-bottom: 15px; border: 2px solid #cbd5e1; position: relative; }
.bus-front { height: 40px; background: #94a3b8; margin-bottom: 10px; border-radius: 10px 10px 20px 20px; display: flex; align-items: center; justify-content: center; color: white; font-size: 10px; font-weight: bold; position: relative; overflow: hidden; }
.driver-icon { position: absolute; left: 15px; bottom: 5px; font-size: 20px; opacity: 0.5; color: #1e293b; }

.bus-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 8px; justify-content: center; } 
.seat-item { height: 35px; background: white; border: 1px solid #94a3b8; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 800; cursor: pointer; transition: 0.2s; position: relative; box-shadow: 0 2px 0 #cbd5e1; }
.seat-item:active { transform: translateY(2px); box-shadow: none; }
.seat-item.occupied { background: #fee2e2; color: #ef4444; border-color: #fca5a5; cursor: not-allowed; box-shadow: none; opacity: 0.6; }
.seat-item.selected { background: var(--brandGold); color: white; border-color: #b45309; box-shadow: 0 2px 0 #92400e; transform: scale(1.05); }
.aisle { grid-column: 3; pointer-events: none; } /* Pasillo */
.legend { display: flex; gap: 10px; justify-content: center; margin-top: 10px; font-size: 10px; color: #64748b; }
.dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; margin-right: 3px; }

/* Botón refrescar */
.btn-refresh-abs { position: absolute; top: 10px; right: 10px; background: white; border: 1px solid #cbd5e1; border-radius: 50%; width: 25px; height: 25px; display: flex; align-items: center; justify-content: center; color: var(--brandGold); cursor: pointer; font-size: 12px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); z-index: 10; }
.btn-refresh-abs:active { transform: rotate(180deg); }

.footer { background: var(--card); padding: 20px; border-top: 1px solid rgba(0,0,0,0.05); position: sticky; bottom: 0; }
#btnSiguiente { width: 100%; padding: 18px; border-radius: 20px; border: none; background: var(--brandGold); color: white; font-weight: 800; text-transform: uppercase; cursor: pointer; box-shadow: 0 4px 15px rgba(184, 135, 43, 0.3); }
#btnSiguiente:disabled { background: #e2e8f0; color: #94a3b8; box-shadow: none; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
.hist-title { margin-top: 30px; font-size: 12px; font-weight: 800; color: var(--textMuted); text-transform: uppercase; }
.hist-item { background: var(--card); padding: 12px 16px; border-radius: 16px; margin-top: 10px; display: flex; justify-content: space-between; align-items: center; font-size: 13px; border-left: 5px solid #cbd5e1; }
.hist-item.autorizado { border-left-color: var(--okColor); }
.hist-item.denegado { border-left-color: var(--noColor); }
.badge { display: inline-block; padding: 4px 12px; border-radius: 8px; font-size: 11px; font-weight: 800; margin-top: 8px; text-transform: uppercase; letter-spacing: 0.5px; }
.st-afiliado { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
.st-proceso { background: #fef9c3; color: #854d0e; border: 1px solid #fef08a; }
.st-visita { background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }
</style>
</head>
<body class="state-neutral">

<div id="loader" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(255,255,255,0.8); z-index:300; align-items:center; justify-content:center; flex-direction:column;">
    <div style="width: 40px; height: 40px; border: 4px solid #ccc; border-top: 4px solid #b8872b; border-radius: 50%; animation: spin 1s linear infinite;"></div>
    <p style="margin-top:10px; font-weight:bold; color:var(--brandGold)">Procesando...</p>
</div>

<div class="topbar">
 <div class="topbar-inner">
  <a href="dashboard.php" style="color:var(--textMuted); text-align:center"><i class="fas fa-sign-out-alt fa-lg"></i></a>
  <div class="headerTitle">
      EMBARQUE DE BUSES<br>
      <span style="font-size:9px; color:var(--textMuted); font-weight:normal" id="sessionCount">Escaneados: 0</span>
  </div>
  <div style="text-align:right">
   <button onclick="toggleDarkMode()" style="background:none; border:none; color:var(--textMuted); font-size:20px; cursor:pointer"><i class="fas fa-moon" id="darkIcon"></i></button>
  </div>
 </div>
 <div class="goldLine"></div>
</div>

<div class="wrap">
    
    <div class="action-grid">
        <a href="manifiesto.php" class="btn-quick">
            <i class="fas fa-file-invoice" style="color:#004a99"></i>
            MANIFIESTO DIGITAL
        </a>
        <div onclick="abrirCargaFisico()" class="btn-quick" style="cursor:pointer">
            <i class="fas fa-cloud-upload-alt" style="color:#10b981"></i>
            CARGAR FÍSICO
        </div>
    </div>

    <div class="mode-bar">
        <button onclick="setModo('NORMAL')" id="btnNormal" class="btn-mode active-normal">
            <i class="fas fa-qrcode"></i> REGISTRO
        </button>
        <button onclick="setModo('BAJADA')" id="btnBajada" class="btn-mode">
            <i class="fas fa-map-marker-alt"></i> BAJADA
        </button>
    </div>

    <input type="hidden" id="modo_actual" value="NORMAL">

    <div id="div-lugar-bajada">
        <label style="font-size:10px; font-weight:800; color:#b8872b; display:block; margin-bottom:5px;">LUGAR DE DESEMBARQUE (ESCRIBIR):</label>
        <input type="text" id="lugar_bajada" class="input-bajada" placeholder="Ej: KM 40, COMEDOR...">
    </div>

 <button onclick="abrirManual()" class="btn-action">
    <i class="fas fa-keyboard" style="color:var(--brandGold); margin-right:8px"></i> INGRESO MANUAL DNI
 </button>

 <button onclick="abrirAgregarManual()" class="btn-add">
    <i class="fas fa-user-plus"></i> AGREGAR PASAJERO NUEVO
 </button>

 <div id="reader-wrapper"><div id="reader"></div></div>
 <div id="status" class="status"></div>
 <div id="datos"></div>

 <div class="hist-title">Escaneos Recientes</div>
 <div id="historialList"></div>
</div>

<!-- MODAL VALIDACIÓN MANUAL -->
<div id="modalManual" class="modal">
    <div class="modal-content">
        <h3 style="color:#b8872b">Validación Manual</h3>
        <input type="tel" id="dniManual" class="input-dni" placeholder="DNI" maxlength="8">
        <div style="display:flex; gap:12px; margin-top:20px;">
            <button onclick="cerrarManual()" class="btn-action" style="background:#eee; color:#666;">Cerrar</button>
            <button onclick="enviarManual()" class="btn-action" style="background:var(--brandGold); color:white;">Validar</button>
        </div>
    </div>
</div>

<!-- MODAL AGREGAR INTELIGENTE (CON MAPA VISUAL 30 ASIENTOS) -->
<div id="modalAgregar" class="modal">
    <div class="modal-content" style="text-align:left;">
        <h3 style="text-align:center; color:var(--brandGold); margin-bottom:5px;" id="tituloAgregar">Agregar Pasajero</h3>
        <p style="text-align:center; font-size:11px; margin-bottom:15px; color:#666" id="subtituloAgregar">Complete los datos</p>
        
        <label class="label-form">DNI</label>
        <input type="tel" id="new_dni" class="input-form" maxlength="8" placeholder="Ingrese DNI" readonly>

        <div id="extra_personal_fields" style="display:none; background:#f0f9ff; padding:10px; border-radius:10px; border:1px solid #bae6fd; margin-bottom:10px;">
            <label class="label-form" style="color:#0284c7">NOMBRES</label>
            <input type="text" id="new_nombres" class="input-form" placeholder="Nombres">
            <label class="label-form" style="color:#0284c7">APELLIDOS</label>
            <input type="text" id="new_apellidos" class="input-form" placeholder="Apellidos">
            <label class="label-form" style="color:#0284c7">EMPRESA</label>
            <input type="text" id="new_empresa" class="input-form" placeholder="Empresa">
        </div>

        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
            <div>
                <label class="label-form">UNIDAD / BUS</label>
                <select id="new_bus" class="input-form" onchange="cargarMapaAsientos()">
                    <option value="">-- Bus --</option>
                    <?= $buses_opt ?>
                </select>
            </div>
            <div>
                <label class="label-form">TIPO MOV.</label>
                <select id="new_tipo" class="input-form" onchange="cargarMapaAsientos()">
                    <option value="subida">INGRESO</option>
                    <option value="bajada">SALIDA</option>
                </select>
            </div>
        </div>

        <label class="label-form">DESTINO</label>
        <input type="text" id="new_destino" class="input-form" placeholder="Ej: LIMA, GARITA...">

        <!-- MAPA DE ASIENTOS -->
        <label class="label-form" style="text-align:center; margin-top:10px; color:#b8872b">SELECCIONE ASIENTO:</label>
        
        <div class="bus-container">
            <button onclick="cargarMapaAsientos()" class="btn-refresh-abs" title="Actualizar Mapa"><i class="fas fa-sync-alt"></i></button>

            <div class="bus-front">
                <i class="fas fa-steering-wheel driver-icon"></i>
                FRENTE
            </div>
            <div id="busMap" class="bus-grid">
                <p style="grid-column:1/-1; text-align:center; color:#64748b; font-size:11px;">Seleccione un Bus para ver asientos</p>
            </div>
            <div class="legend">
                <span><span class="dot" style="background:#fff; border:1px solid #999"></span>Libre</span>
                <span><span class="dot" style="background:#ef4444"></span>Ocupado</span>
                <span><span class="dot" style="background:#b8872b"></span>Tuyo</span>
            </div>
        </div>

        <input type="hidden" id="new_asiento"> 
        <p id="txtAsientoSeleccionado" style="text-align:center; font-weight:bold; color:var(--brandGold); margin-bottom:10px;">Ninguno seleccionado</p>

        <div style="display:flex; gap:12px; margin-top:10px;">
            <button onclick="cerrarAgregar()" class="btn-action" style="background:#eee; color:#666;">Cancelar</button>
            <button onclick="guardarExtra()" class="btn-action" style="background:var(--brandGold); color:white;">AGREGAR</button>
        </div>
    </div>
</div>

<div id="modalCarga" class="modal">
    <div class="modal-content" style="text-align:left;">
        <h3 style="text-align:center; color:var(--brandGold); margin-bottom:15px;">Cargar Manifiesto Físico</h3>
        <label class="label-form">TIPO DE VIAJE</label>
        <select id="doc_tipo" class="input-form">
            <option value="subida">INGRESO (SUBIDA)</option>
            <option value="bajada">SALIDA (BAJADA)</option>
        </select>
        <label class="label-form">SELECCIONAR UNIDAD</label>
        <select id="doc_bus" class="input-form">
            <option value="">-- Seleccione Bus --</option>
            <?= $buses_opt ?>
        </select>
        <label class="label-form">FOTO O PDF</label>
        <input type="file" id="doc_file" class="input-form" accept="image/*,application/pdf">
        <div style="display:flex; gap:10px; margin-top:10px;">
            <button onclick="cerrarCarga()" class="btn-action" style="background:#eee; color:#666;">Cerrar</button>
            <button onclick="procesarCarga()" class="btn-action" style="background:var(--okColor); color:white;">SUBIR</button>
        </div>
    </div>
</div>

<div class="footer">
 <button id="btnSiguiente" disabled onclick="iniciarScanner()">SIGUIENTE ESCANEO ➜</button>
</div>

<script>
let html5QrCode=null, lastResult=null, countResults=0, sessionCounter=0;
let audioCtx = null;

window.onload=()=>{ renderHistorial(); iniciarScanner(); };

async function cargarMapaAsientos() {
    const bus = document.getElementById('new_bus').value;
    const tipo = document.getElementById('new_tipo').value;
    const mapDiv = document.getElementById('busMap');

    document.getElementById('new_asiento').value = "";
    document.getElementById('txtAsientoSeleccionado').innerText = "Ninguno seleccionado";

    if(!bus) {
        mapDiv.innerHTML = '<p style="grid-column:1/-1; text-align:center; color:#64748b; font-size:11px;">Seleccione un Bus primero</p>';
        return;
    }

    mapDiv.innerHTML = '<p style="grid-column:1/-1; text-align:center;">Cargando...</p>';

    try {
        const formData = new FormData();
        formData.append('bus', bus);
        formData.append('tipo', tipo);
        
        const res = await fetch('ver_asientos.php', { method: 'POST', body: formData });
        const ocupados = await res.json(); 

        dibujarBus(ocupados);

    } catch(e) {
        console.error(e);
        mapDiv.innerHTML = '<p style="grid-column:1/-1; color:red">Error cargando mapa</p>';
    }
}

function dibujarBus(ocupados) {
    const mapDiv = document.getElementById('busMap');
    mapDiv.innerHTML = '';

    // MAPA DE 30 ASIENTOS
    for(let i=1; i<=30; i++) {
        const seat = document.createElement('div');
        seat.className = 'seat-item';
        seat.innerText = i;
        
        if(ocupados.includes(i)) {
            seat.classList.add('occupied');
            seat.title = "Ocupado";
        } else {
            seat.onclick = function() { selectSeat(i, seat); };
        }

        mapDiv.appendChild(seat);

        // Pasillo cada 2 asientos
        if (i % 2 === 0 && i % 4 !== 0) {
            const aisle = document.createElement('div');
            aisle.className = 'aisle';
            mapDiv.appendChild(aisle);
        }
    }
}

function selectSeat(num, element) {
    document.querySelectorAll('.seat-item.selected').forEach(el => el.classList.remove('selected'));
    element.classList.add('selected');
    document.getElementById('new_asiento').value = num;
    document.getElementById('txtAsientoSeleccionado').innerText = "ASIENTO SELECCIONADO: " + num;
}

function setModo(m) {
    document.getElementById('modo_actual').value = m;
    document.querySelectorAll('.btn-mode').forEach(b => b.className = 'btn-mode');
    if(m==='NORMAL') document.getElementById('btnNormal').classList.add('active-normal');
    if(m==='BAJADA') document.getElementById('btnBajada').classList.add('active-bajada');
    const divBajada = document.getElementById('div-lugar-bajada');
    if(m === 'BAJADA') {
        divBajada.style.display = 'block';
        document.getElementById('lugar_bajada').focus();
    } else {
        divBajada.style.display = 'none';
    }
}

function abrirCargaFisico() { document.getElementById('modalCarga').style.display='flex'; }
function cerrarCarga() { document.getElementById('modalCarga').style.display='none'; }

async function procesarCarga() {
    const tipo = document.getElementById('doc_tipo').value;
    const bus = document.getElementById('doc_bus').value;
    const file = document.getElementById('doc_file').files[0];
    if(!bus || !file) { Swal.fire('Atención', 'Seleccione Unidad y Archivo', 'warning'); return; }
    showLoader(true);
    const formData = new FormData();
    formData.append('tipo', tipo); formData.append('bus', bus); formData.append('file', file);
    try {
        const res = await fetch('upload_manifiesto.php', { method: 'POST', body: formData });
        const data = await res.json();
        showLoader(false);
        if(data.success) { Swal.fire('Éxito', 'Manifiesto guardado correctamente', 'success'); cerrarCarga(); } 
        else { Swal.fire('Error', data.message, 'error'); }
    } catch(e) { showLoader(false); Swal.fire('Error', 'No hay conexión con el servidor', 'error'); }
}

function toggleDarkMode() {
    document.body.classList.toggle('dark-mode');
    const isDark = document.body.classList.contains('dark-mode');
    localStorage.setItem('darkBus', isDark);
    document.getElementById('darkIcon').className = isDark ? 'fas fa-sun' : 'fas fa-moon';
}
if(localStorage.getItem('darkBus') === 'true') toggleDarkMode();
function showLoader(show) { document.getElementById('loader').style.display = show ? 'flex' : 'none'; }
function abrirManual() { document.getElementById('modalManual').style.display='flex'; document.getElementById('dniManual').focus(); }
function cerrarManual() { document.getElementById('modalManual').style.display='none'; document.getElementById('dniManual').value=''; }

function abrirAgregarManual() { prepararModalAgregar('', 'MANUAL'); }

function prepararModalAgregar(dni, caso, nombrePre = "") {
    document.getElementById('modalAgregar').style.display='flex';
    document.getElementById('new_dni').value = dni;
    
    document.getElementById('new_bus').value = "";
    document.getElementById('new_destino').value = "";
    document.getElementById('new_asiento').value = ""; 
    document.getElementById('txtAsientoSeleccionado').innerText = "Ninguno seleccionado";
    document.getElementById('busMap').innerHTML = '<p style="grid-column:1/-1; text-align:center; color:#64748b; font-size:11px;">Seleccione un Bus para ver asientos</p>';
    
    document.getElementById('new_nombres').value = "";
    document.getElementById('new_apellidos').value = "";
    document.getElementById('new_empresa').value = "";

    const boxPersonal = document.getElementById('extra_personal_fields');
    const title = document.getElementById('tituloAgregar');
    const sub = document.getElementById('subtituloAgregar');

    if (caso === 'MANUAL' || caso === 'NO_EXISTE') {
        document.getElementById('new_dni').readOnly = (caso !== 'MANUAL'); 
        boxPersonal.style.display = 'block'; 
        title.innerText = "Pasajero Nuevo / Externo";
        sub.innerText = "Este DNI no existe en BD. Regístrelo completo.";
        if(caso === 'NO_EXISTE') playBeep(200, 400); 
    } 
    else if (caso === 'FALTA_VIAJE') {
        document.getElementById('new_dni').readOnly = true;
        boxPersonal.style.display = 'none'; 
        title.innerText = "Asignar Viaje a: " + nombrePre;
        sub.innerText = "Personal validado en BD. Seleccione asiento.";
        playBeep(440, 200); 
    }
}

function cerrarAgregar() { document.getElementById('modalAgregar').style.display='none'; }

async function enviarManual() {
    const d = document.getElementById('dniManual').value;
    if(d.length === 8) { cerrarManual(); if(html5QrCode) { try { await html5QrCode.stop(); } catch(e){} }
        document.getElementById("reader-wrapper").style.display="none"; validarDNI(d); }
}

async function guardarExtra() {
    const dni = document.getElementById('new_dni').value;
    const bus = document.getElementById('new_bus').value;
    const tipo = document.getElementById('new_tipo').value;
    const destino = document.getElementById('new_destino').value.toUpperCase();
    const asiento = document.getElementById('new_asiento').value;
    
    const nombres = document.getElementById('new_nombres').value.toUpperCase();
    const apellidos = document.getElementById('new_apellidos').value.toUpperCase();
    const empresa = document.getElementById('new_empresa').value.toUpperCase();

    const isFullRegister = document.getElementById('extra_personal_fields').style.display !== 'none';

    if(!dni || !bus) { Swal.fire({icon: 'warning', title: 'Faltan Datos', text: 'Seleccione DNI y Bus'}); return; }
    
    if(!asiento) { Swal.fire({icon: 'warning', title: 'Asiento', text: 'Por favor seleccione un asiento en el mapa'}); return; }

    if(isFullRegister && (!nombres || !apellidos || !empresa)) {
         Swal.fire({icon: 'warning', title: 'Faltan Datos', text: 'Nombre y Empresa requeridos'}); return; 
    }

    showLoader(true);
    try {
        const formData = new FormData();
        formData.append('dni', dni); formData.append('bus', bus); formData.append('tipo', tipo);
        formData.append('destino', destino); formData.append('asiento', asiento);
        
        if(isFullRegister) {
            formData.append('nombres', nombres);
            formData.append('apellidos', apellidos);
            formData.append('empresa', empresa);
            formData.append('modo_registro', 'COMPLETO');
        } else {
            formData.append('modo_registro', 'SOLO_VIAJE');
        }

        const res = await fetch('guardar_extra.php', { method: 'POST', body: formData });
        const data = await res.json(); showLoader(false);
        
        if(data.success) {
            Swal.fire({icon: 'success', title: '¡Listo!', text: 'Asiento ' + asiento + ' reservado.', timer: 1500, showConfirmButton: false});
            cerrarAgregar();
            if(html5QrCode) { try { await html5QrCode.stop(); } catch(e){} }
            document.getElementById("reader-wrapper").style.display="none"; 
            validarDNI(dni); 
        } else { Swal.fire({icon: 'error', title: 'Error', text: data.message}); }
    } catch(e) { showLoader(false); Swal.fire({icon: 'error', title: 'Error', text: 'Error de conexión'}); }
}

async function iniciarScanner(){
 document.getElementById("reader-wrapper").style.display="block";
 document.getElementById("btnSiguiente").disabled=true;
 document.getElementById("datos").innerHTML="";
 document.getElementById("status").innerHTML=`<div class="big">LISTO</div><div class="pill neutral">ESPERANDO CÓDIGO</div>`;
 if(html5QrCode) try{await html5QrCode.stop()}catch(e){}
 try {
     html5QrCode = new Html5Qrcode("reader");
     const config = { fps: 20, qrbox: { width: 300, height: 150 }, aspectRatio: 1.0 };
     await html5QrCode.start({ facingMode: "environment" }, config, (dni) => {
            const d = dni.trim(); if(d.length < 8) return;
            if(d === lastResult){ countResults++; } else { lastResult = d; countResults = 0; return; }
            if(countResults >= 2){ html5QrCode.stop().then(() => { document.getElementById("reader-wrapper").style.display = "none"; validarDNI(d); }).catch(() => { document.getElementById("reader-wrapper").style.display = "none"; validarDNI(d); }); }
        }, (errorMessage) => {});
 } catch (err) {
     document.getElementById("reader-wrapper").style.display="none";
     document.getElementById("status").innerHTML=`<div class="pill no" style="width:100%">CÁMARA NO DISPONIBLE<br>USE INGRESO MANUAL</div>`;
 }
}

async function validarDNI(dni){
 showLoader(true);
 const modo = document.getElementById('modo_actual').value;
 let lugarBajada = '';
 if(modo === 'BAJADA') {
    lugarBajada = document.getElementById('lugar_bajada').value.trim();
    if(lugarBajada === '') { showLoader(false); Swal.fire({icon: 'warning', title: 'Atención', text: 'Ingrese el LUGAR DE DESEMBARQUE antes de escanear', confirmButtonColor: '#000000'}); document.getElementById("btnSiguiente").disabled=false; return; }
 }
 try {
  const response = await fetch("validar.php", { method: "POST", headers: {"Content-Type": "application/x-www-form-urlencoded"}, body: `dni=${dni}&modo=${modo}&ubicacion=${encodeURIComponent(lugarBajada)}` });
  const data=await response.json(); showLoader(false);
  
  if(data.estado === "FALTA_VIAJE") {
      prepararModalAgregar(dni, 'FALTA_VIAJE', data.persona.nombres);
      document.getElementById("btnSiguiente").disabled=false;
      return; 
  }
  if(data.estado === "NO_EXISTE") {
      prepararModalAgregar(dni, 'NO_EXISTE');
      document.getElementById("btnSiguiente").disabled=false;
      return;
  }

  if(data.estado === "ERROR") { Swal.fire('Error', data.mensaje, 'error'); document.getElementById("btnSiguiente").disabled=false; return; }
  
  const isOk = data.estado==="AUTORIZADO";
  if(modo === 'BAJADA') document.body.style.background = "#fff3e0";
  else document.body.style.background = isOk ? "#dcfce7" : "#fee2e2";
  
  if(isOk) playBeep(880, 200);
  else playBeep(220, 300);

  let pillClass = isOk ? 'ok' : 'no';
  if(modo === 'BAJADA' && isOk) pillClass = 'warn';
  document.getElementById("status").innerHTML=`<div class="big">${data.estado}</div><div style="font-weight:600">${data.movimiento}</div><div class="pill ${pillClass}">${isOk?'ACCESO VÁLIDO':'DENEGADO'}</div>`;
  renderizarTarjeta(data, dni);
  if(isOk) { sessionCounter++; document.getElementById('sessionCount').innerText = "Escaneados: " + sessionCounter; 
      let history = JSON.parse(localStorage.getItem('histBus') || '[]');
      history.unshift({ dni, nombre: data.persona?data.persona.nombres:'Desc.', estado: data.estado, hora: new Date().toLocaleTimeString() });
      localStorage.setItem('histBus', JSON.stringify(history.slice(0,5))); renderHistorial(); }
 } catch(e) { showLoader(false); Swal.fire({icon: 'error', title: 'Error de Red', text: 'Revise su conexión'}); }
 document.getElementById("btnSiguiente").disabled=false;
}

function renderizarTarjeta(data, dni){
 const c=document.getElementById("datos");
 if(!data.persona){ c.innerHTML=`<div class="card" style="text-align:center"><h2>${dni}</h2><p>DNI NO ENCONTRADO</p></div>`; return; }
 let claseEst = "st-visita";
 let est = data.persona.validacion ? data.persona.validacion.toUpperCase() : "VISITA";
 if(est.includes("AFILIADO")) claseEst = "st-afiliado";
 if(est.includes("PROCESO")) claseEst = "st-proceso";
 const foto = data.foto ? `<img src="${data.foto}" class="foto">` : `<div class="foto" style="display:flex;align-items:center;justify-content:center;background:#eee"><i class="fas fa-user fa-4x" style="color:#ccc"></i></div>`;
 const telEmergencia = data.persona.med_te ? `<a href="tel:${data.persona.med_te}" style="color:#be123c; text-decoration:none; font-weight:800;"><i class="fas fa-phone"></i> ${data.persona.med_te}</a>` : '---';
 c.innerHTML=`<div class="card">${foto}<div style="text-align:center; margin-bottom:20px;"><div style="font-weight:800; font-size:20px; line-height:1.2">${data.persona.nombres}<br>${data.persona.apellidos}</div><div class="badge ${claseEst}">${est}</div></div><div class="info-grid"><div class="info-item"><span class="label">DNI</span><span class="value mono">${data.persona.dni}</span></div><div class="info-item"><span class="label">Empresa</span><span class="value">${data.persona.empresa}</span></div><div class="info-item"><span class="label">Área</span><span class="value">${data.persona.area}</span></div><div class="info-item"><span class="label">Cargo</span><span class="value">${data.persona.cargo}</span></div></div><div id="medInfo_${dni}" style="display:none; margin-top:15px; padding:15px; background:#fff1f2; border:1px solid #fda4af; border-radius:15px; text-align:left; font-size:12px;"><div style="color:#e11d48; font-weight:800; margin-bottom:5px;"><i class="fas fa-ambulance"></i> DATOS DE EMERGENCIA</div><b>G.S.:</b> ${data.persona.med_gs || '---'}<br><b>Contacto:</b> ${data.persona.med_ce || '---'} (${telEmergencia})<br><b>Alergias/Enfermedades:</b> ${data.persona.med_al || 'NINGUNA'} / ${data.persona.med_en || 'NINGUNA'}</div><button onclick="document.getElementById('medInfo_${dni}').style.display='block'; this.style.display='none'" style="width:100%; margin-top:15px; background:#be123c; color:white; border:none; padding:10px; border-radius:12px; font-weight:800; font-size:11px; cursor:pointer;"><i class="fas fa-exclamation-triangle"></i> VER DATOS MÉDICOS</button><div class="trip-box"><div style="margin-bottom:10px"><span class="label">UNIDAD / PLACA</span><br><span class="trip-highlight" style="color:var(--textMain); font-family:monospace">${data.persona.bus} • ${data.persona.placa}</span></div><div style="border-top:1px solid rgba(184, 135, 43, 0.2); padding-top:10px"><span class="label">SUBIDA/DESTINO</span><br><span class="trip-highlight">${data.destino}</span></div></div></div>`;
}

function renderHistorial() {
    const history = JSON.parse(localStorage.getItem('histBus') || '[]');
    document.getElementById('historialList').innerHTML = history.map(item => `<div class="hist-item ${item.estado.toLowerCase()}"><div><b style="font-family:monospace">${item.dni}</b><br><small style="color:var(--textMuted)">${item.nombre}</small></div><div style="text-align:right"><b>${item.estado}</b><br><small>${item.hora}</small></div></div>`).join('');
}
function playBeep(freq,dur){
 try{ if(!audioCtx) audioCtx=new AudioContext();
  const osc=audioCtx.createOscillator(); const gain=audioCtx.createGain();
  osc.connect(gain); gain.connect(audioCtx.destination);
  osc.frequency.value=freq; gain.gain.value=.05; osc.start(); osc.stop(audioCtx.currentTime+(dur/1000));
 }catch(e){}
}
</script>
</body>
</html>