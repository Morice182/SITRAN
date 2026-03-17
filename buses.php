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
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="theme-color" content="#ffffff">
<script src="https://unpkg.com/html5-qrcode"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<link rel="icon" type="image/png" href="../assets/logo4.png"/>
<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;700&display=swap');

/* ── RESET ── */
* { box-sizing: border-box; margin: 0; padding: 0; }

/* ── VARIABLES ── */
:root {
  --gold: #b8872b; --gold2: #d4a752; --gold3: #e8c06a;
  --goldbg: rgba(184,135,43,0.07); --goldborder: rgba(184,135,43,0.22);
  --bg: #ffffff; --bg2: #f8f9fb; --bg3: #f1f3f7;
  --border: rgba(0,0,0,0.07); --border2: rgba(0,0,0,0.13);
  --ink: #0f1117; --ink2: #1e2330; --muted: #6b7385; --muted2: #9ba3b5;
  --ok: #0d9e6a; --ok-bg: #edf7f3; --ok-bd: rgba(13,158,106,0.2);
  --err: #d92d4a; --err-bg: #fdf0f2; --err-bd: rgba(217,45,74,0.2);
  --warn: #c47f17;
  --sh: 0 1px 3px rgba(0,0,0,0.06),0 1px 2px rgba(0,0,0,0.04);
  --sh2: 0 4px 12px rgba(0,0,0,0.08),0 1px 3px rgba(0,0,0,0.04);
  --f: 'Plus Jakarta Sans', sans-serif;
  --fm: 'JetBrains Mono', monospace;
  --r: 12px; --rl: 18px;
}

html, body {
  height: 100%; height: 100dvh;
  font-family: var(--f); color: var(--ink); background: var(--bg2);
  -webkit-tap-highlight-color: transparent; -webkit-text-size-adjust: 100%;
}
body { display: flex; flex-direction: column; min-height: 100dvh; overflow: hidden; }

/* ── TOPNAV ── */
.topnav {
  background: var(--bg); border-bottom: 1px solid var(--border);
  box-shadow: var(--sh); flex-shrink: 0; z-index: 100;
  padding-top: env(safe-area-inset-top, 0px);
}
.topnav-inner {
  height: 52px; display: flex; align-items: center;
  justify-content: space-between; padding: 0 14px;
}
.brand { display: flex; align-items: center; gap: 9px; text-decoration: none; color: inherit; }
.brand-logo { height: 30px; width: auto; flex-shrink: 0; object-fit: contain; }
.brand-logo-svg { width: 30px; height: 30px; flex-shrink: 0; display: none; }
.brand-name { font-size: 12px; font-weight: 800; letter-spacing: 0.05em; color: var(--ink); text-transform: uppercase; line-height: 1; }
.brand-name span { color: var(--gold); }
.brand-sub { font-family: var(--fm); font-size: 7px; color: var(--muted2); letter-spacing: 0.08em; text-transform: uppercase; margin-top: 2px; }
.topnav-right { display: flex; align-items: center; gap: 7px; }

/* Live pill */
.live-pill { display: flex; align-items: center; gap: 5px; padding: 5px 9px; background: var(--ok-bg); border: 1px solid var(--ok-bd); border-radius: 20px; transition: all .3s; }
.live-dot { width: 5px; height: 5px; border-radius: 50%; background: var(--ok); animation: livepulse 2s infinite; }
@keyframes livepulse { 0%,100%{opacity:1;box-shadow:0 0 0 0 rgba(13,158,106,0.35)} 60%{box-shadow:0 0 0 5px rgba(13,158,106,0)} }
.live-lbl { font-family: var(--fm); font-size: 9px; font-weight: 700; color: var(--ok); letter-spacing: 0.08em; text-transform: uppercase; }
.live-time { font-family: var(--fm); font-size: 9px; color: var(--ok); padding-left: 5px; border-left: 1px solid var(--ok-bd); margin-left: 2px; }
.live-pill.bajada-mode { background: rgba(30,35,48,0.07); border-color: rgba(30,35,48,0.18); }
.live-pill.bajada-mode .live-dot { background: var(--ink2); animation: none; }
.live-pill.bajada-mode .live-lbl, .live-pill.bajada-mode .live-time { color: var(--ink2); }

/* Badge */
.ctr-badge { width: 34px; height: 34px; border-radius: 50%; background: var(--goldbg); border: 1.5px solid var(--goldborder); display: flex; align-items: center; justify-content: center; font-family: var(--fm); font-size: 11px; font-weight: 700; color: var(--gold); }
@keyframes badge-pop { 0%{transform:scale(1)} 40%{transform:scale(1.4)} 70%{transform:scale(0.9)} 100%{transform:scale(1)} }
.ctr-badge.pop { animation: badge-pop .4s cubic-bezier(.36,.07,.19,.97); }

.gold-rule { height: 2.5px; background: linear-gradient(90deg, transparent, var(--gold) 30%, var(--gold2) 60%, var(--gold3) 80%, transparent); }

/* ── LAYOUT ── */
.layout { flex: 1; display: flex; overflow: hidden; width: 100%; }

/* Sidebar oculto en móvil */
.sidebar { width: 60px; background: var(--bg); border-right: 1px solid var(--border); display: none; flex-direction: column; align-items: center; padding: 12px 0; gap: 4px; flex-shrink: 0; }
@media (min-width: 600px) { .sidebar { display: flex; } .layout { max-width: 480px; margin: 0 auto; } .footer { max-width: 480px; margin: 0 auto; } }
.sb-btn { width: 42px; height: 42px; border-radius: 10px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 3px; cursor: pointer; border: none; background: transparent; color: var(--muted2); transition: all .18s; font-size: 16px; }
.sb-btn.active { background: var(--goldbg); color: var(--gold); border: 1px solid var(--goldborder); }
.sb-btn:hover:not(.active) { background: var(--bg3); color: var(--muted); }
.sb-lbl { font-family: var(--fm); font-size: 7px; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; }

/* Main scroll */
.main {
  flex: 1; overflow-y: auto; display: flex; flex-direction: column; background: var(--bg2);
  background-image: radial-gradient(circle, rgba(0,0,0,0.04) 1px, transparent 1px);
  background-size: 20px 20px;
}
.main::-webkit-scrollbar { width: 3px; }
.main::-webkit-scrollbar-thumb { background: var(--border2); border-radius: 2px; }

/* ── SCANNER SECTION ── */
.scanner-sec { background: var(--bg); padding: 12px 14px; border-bottom: 1px solid var(--border); }
.scanner-toprow { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; }
.scanner-lbl { font-family: var(--fm); font-size: 9px; font-weight: 700; color: var(--muted2); letter-spacing: 0.1em; text-transform: uppercase; }

/* Toggle modo */
.mode-toggle { display: flex; background: var(--bg3); border: 1px solid var(--border); border-radius: 8px; padding: 3px; gap: 2px; }
.mt-btn { padding: 6px 16px; border-radius: 6px; font-size: 11px; font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase; border: none; cursor: pointer; background: transparent; color: var(--muted); transition: all .18s; font-family: var(--f); min-height: 34px; display: flex; align-items: center; gap: 5px; }
.mt-btn.on-reg  { background: var(--gold); color: #fff; box-shadow: 0 2px 6px rgba(184,135,43,0.3); }
.mt-btn.on-exit { background: var(--ink);  color: #fff; box-shadow: 0 2px 6px rgba(0,0,0,0.15); }

/* Viewport scanner */
.scan-vp {
  position: relative; width: 100%;
  height: calc(100vw * 0.85); max-height: 360px; min-height: 240px;
  background: #080a0e; border-radius: var(--rl);
  overflow: hidden; border: 1px solid var(--border2); box-shadow: var(--sh2);
}
.vp-sim { position: absolute; inset: 0; background: linear-gradient(145deg,#0d1117,#161b24 50%,#0a0e14); }
.vp-grid { position: absolute; inset: 0; background-image: linear-gradient(rgba(184,135,43,0.04) 1px,transparent 1px),linear-gradient(90deg,rgba(184,135,43,0.04) 1px,transparent 1px); background-size: 22px 22px; }

/* Scan line mejorado con glow dual */
.vp-line { position: absolute; left: 8%; right: 8%; height: 2px; background: linear-gradient(90deg,transparent,rgba(184,135,43,0.1),rgba(184,135,43,1) 40%,#f0d080 50%,rgba(184,135,43,1) 60%,rgba(184,135,43,0.1),transparent); box-shadow: 0 0 18px 4px rgba(184,135,43,0.55), 0 0 4px rgba(255,220,120,0.9); animation: scanmove 2s cubic-bezier(0.4,0,0.6,1) infinite; z-index: 5; border-radius: 2px; }
@keyframes scanmove { 0%{top:10%;opacity:0} 8%{opacity:1} 50%{top:84%} 92%{opacity:1} 100%{top:10%;opacity:0} }

/* Esquinas mejoradas con glow */
.vp-corners { position: absolute; inset: 0; z-index: 6; pointer-events: none; }
.vc { position: absolute; width: 32px; height: 32px; }
.vc svg { width: 32px; height: 32px; }
.vc.tl{top:10px;left:10px} .vc.tr{top:10px;right:10px;transform:scaleX(-1)}
.vc.bl{bottom:10px;left:10px;transform:scaleY(-1)} .vc.br{bottom:10px;right:10px;transform:scale(-1)}
@keyframes cpulse { 0%,100%{opacity:1;filter:drop-shadow(0 0 3px rgba(184,135,43,0.8))} 50%{opacity:.3;filter:drop-shadow(0 0 0px rgba(184,135,43,0))} }
.vc { animation: cpulse 2s ease-in-out infinite; }
.vc.tl{animation-delay:0s} .vc.tr{animation-delay:.5s} .vc.bl{animation-delay:1s} .vc.br{animation-delay:1.5s}
.scan-vp.result-on .vc { animation: none; opacity: 1; filter: drop-shadow(0 0 5px rgba(184,135,43,1)); }
.scan-vp.result-on .vp-line { animation-play-state: paused; opacity: 0; }

.vp-zone { position: absolute; top:50%; left:50%; transform:translate(-50%,-50%); width:82%; height:58%; border:1.5px solid rgba(184,135,43,0.55); border-radius:10px; z-index:4; box-shadow: inset 0 0 20px rgba(184,135,43,0.04), 0 0 0 1px rgba(184,135,43,0.08); }
.vp-zone-in { position:absolute; inset:0; border-radius:8px; background: radial-gradient(ellipse at center, rgba(184,135,43,0.03) 0%, transparent 70%); }
.vp-hint { position:absolute; bottom:38px; left:0; right:0; z-index:8; text-align:center; font-family:var(--fm); font-size:10px; color:rgba(255,255,255,0.4); letter-spacing:0.12em; text-transform:uppercase; }
.vp-qr-art { position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); width:56px; height:56px; opacity:.05; display:grid; grid-template-columns:repeat(7,1fr); gap:1.5px; z-index:3; }
.vp-qr-art div { border-radius:1px; }
.vp-badge { position:absolute; top:10px; right:12px; z-index:8; background:rgba(0,0,0,0.45); border:1px solid rgba(184,135,43,0.15); border-radius:4px; padding:3px 8px; font-family:var(--fm); font-size:8px; color:rgba(184,135,43,0.5); letter-spacing:0.06em; }
.vp-meta { position:absolute; bottom:10px; left:12px; right:12px; z-index:8; display:flex; justify-content:space-between; align-items:center; }
.vp-fps { font-family:var(--fm); font-size:8px; color:rgba(184,135,43,0.6); }
.vp-scan-ind { display:flex; align-items:center; gap:5px; font-family:var(--fm); font-size:8px; color:rgba(255,255,255,0.5); }
.vp-dot { width:6px; height:6px; border-radius:50%; background:var(--ok); box-shadow:0 0 6px rgba(13,158,106,0.8); animation:blink 1s infinite; }
@keyframes blink { 0%,100%{opacity:1} 50%{opacity:.2} }

/* Flash de color más rápido */
.vp-flash { position:absolute; inset:0; z-index:18; opacity:0; pointer-events:none; border-radius:var(--rl); }
@keyframes fok  { 0%{opacity:0} 10%{opacity:0.3} 100%{opacity:0} }
@keyframes ferr { 0%{opacity:0} 10%{opacity:0.35} 100%{opacity:0} }
.vp-flash.ok  { background:#0d9e6a; animation:fok  0.45s ease-out forwards; }
.vp-flash.err { background:#d92d4a; animation:ferr 0.45s ease-out forwards; }

/* Sin cámara */
.vp-nocam { position:absolute; inset:0; z-index:25; display:none; flex-direction:column; align-items:center; justify-content:center; gap:8px; background:rgba(10,14,20,0.92); }
.vp-nocam.show { display:flex; }
.vp-nocam i { font-size:28px; color:var(--muted); }
.vp-nocam span { font-family:var(--fm); font-size:9px; color:var(--muted); text-align:center; padding:0 20px; }

/* Resultado overlay */
.res-ov { position:absolute; inset:0; z-index:20; display:flex; flex-direction:column; align-items:center; justify-content:center; opacity:0; transition:opacity .25s; pointer-events:none; }
.res-ov.show { opacity:1; pointer-events:auto; }
.res-bg-ok  { background:rgba(13,158,106,0.12); }
.res-bg-err { background:rgba(217,45,74,0.12); }
.res-icon { width:64px; height:64px; border-radius:50%; display:flex; align-items:center; justify-content:center; margin-bottom:12px; font-size:28px; }
.res-icon.ok  { background:var(--ok-bg);  border:2px solid var(--ok);  color:var(--ok);  }
.res-icon.err { background:var(--err-bg); border:2px solid var(--err); color:var(--err); }
.res-word { font-size:24px; font-weight:800; letter-spacing:.06em; text-transform:uppercase; }
.res-word.ok{color:var(--ok)} .res-word.err{color:var(--err)}
.res-sub { font-family:var(--fm); font-size:11px; color:rgba(255,255,255,.6); letter-spacing:.06em; margin-top:5px; text-transform:uppercase; }

/* ── LOCATION BAR (bajada) ── */
.loc-bar { display:none; align-items:center; gap:10px; padding:10px 14px; background:rgba(184,135,43,.04); border-bottom:1px solid var(--goldborder); }
.loc-bar.show { display:flex; }
.loc-icon { color:var(--gold); font-size:12px; }
.loc-lbl { font-family:var(--fm); font-size:8px; font-weight:700; color:var(--gold); letter-spacing:.1em; text-transform:uppercase; white-space:nowrap; }
.loc-inp { flex:1; border:none; background:transparent; outline:none; font-family:var(--f); font-size:13px; font-weight:600; color:var(--ink); }
.loc-inp::placeholder { color:var(--muted2); font-weight:400; }

/* ── MANUAL BAR ── */
.manual-bar { display:flex; gap:8px; padding:10px 14px; background:var(--bg); border-bottom:1px solid var(--border); }
.dni-wrap { flex:1; position:relative; display:flex; align-items:center; }
.dni-ico { position:absolute; left:11px; color:var(--muted2); font-size:14px; pointer-events:none; }
.dni-inp { width:100%; padding:12px 11px 12px 36px; background:var(--bg3); border:1px solid var(--border); border-radius:var(--r); font-family:var(--fm); font-size:15px; font-weight:500; color:var(--ink); outline:none; letter-spacing:.06em; transition:border-color .2s; min-height:46px; }
.dni-inp:focus { border-color:var(--goldborder); background:var(--bg); }
.dni-inp::placeholder { font-family:var(--f); font-weight:400; color:var(--muted2); letter-spacing:0; font-size:12px; }
.val-btn { padding:12px 16px; background:var(--gold); border:none; border-radius:var(--r); color:#fff; font-family:var(--f); font-size:12px; font-weight:700; letter-spacing:.04em; text-transform:uppercase; cursor:pointer; white-space:nowrap; display:flex; align-items:center; gap:5px; box-shadow:0 2px 8px rgba(184,135,43,.25); transition:background .2s; min-height:46px; }
.val-btn:active { background:var(--gold2); transform:scale(.97); }
.add-btn { padding:12px 13px; background:var(--bg3); border:1px solid var(--border); border-radius:var(--r); color:var(--muted); font-size:15px; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:all .18s; min-height:46px; min-width:46px; }
.add-btn:active { border-color:var(--goldborder); color:var(--gold); }

/* ── PANEL ── */
.panel { padding:12px 14px; display:flex; flex-direction:column; gap:10px; }

/* Status */
.status-box { border-radius:var(--r); padding:14px 16px; display:flex; align-items:center; justify-content:space-between; }
.status-box.ok  { background:var(--ok-bg);  border:1px solid var(--ok-bd);  }
.status-box.err { background:var(--err-bg); border:1px solid var(--err-bd); }
.status-box.warn    { background:rgba(196,127,23,.06); border:1px solid rgba(196,127,23,.2); }
.status-box.neutral { background:var(--bg3); border:1px solid var(--border); }
.stat-word { font-size:18px; font-weight:800; letter-spacing:.05em; text-transform:uppercase; }
.stat-word.ok{color:var(--ok)} .stat-word.err{color:var(--err)} .stat-word.warn{color:var(--warn)} .stat-word.neutral{color:var(--muted)}
.stat-detail { font-family:var(--fm); font-size:10px; color:var(--muted); margin-top:3px; letter-spacing:.06em; text-transform:uppercase; }
.stat-ico { width:36px; height:36px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:15px; }
.si-ok{background:var(--ok);color:#fff} .si-err{background:var(--err);color:#fff}
.si-warn{background:var(--warn);color:#fff} .si-neutral{background:var(--muted2);color:#fff}

/* Person card */
.pcard { background:var(--bg); border:1px solid var(--border); border-radius:var(--rl); overflow:hidden; box-shadow:var(--sh); animation:slideUp .3s ease-out; }
@keyframes slideUp { from{opacity:0;transform:translateY(14px)} to{opacity:1;transform:none} }

/* ── ID CARD OVERLAY — transición visual al escanear ── */
#idOverlay {
  position: fixed; inset: 0; z-index: 500;
  display: flex; align-items: flex-end; justify-content: center;
  background: rgba(10,13,20,0); pointer-events: none;
  transition: background .3s ease;
}
#idOverlay.show {
  background: rgba(10,13,20,0.72); pointer-events: auto;
}
.id-card {
  width: 100%; max-width: 480px;
  background: var(--bg); border-radius: 24px 24px 0 0;
  padding: 0 0 calc(env(safe-area-inset-bottom,0px) + 20px);
  box-shadow: 0 -8px 40px rgba(0,0,0,0.25);
  transform: translateY(100%); opacity: 0;
  transition: transform .35s cubic-bezier(0.22,1,0.36,1), opacity .25s ease;
  overflow: hidden;
}
#idOverlay.show .id-card {
  transform: translateY(0); opacity: 1;
}
/* Barra de estado coloreada en el top de la card */
.id-card-bar { height: 5px; width: 100%; }
.id-card-bar.ok  { background: linear-gradient(90deg, var(--ok), #1dc98a); }
.id-card-bar.err { background: linear-gradient(90deg, var(--err), #f05070); }
.id-card-bar.warn { background: linear-gradient(90deg, var(--warn), #e8a030); }

.id-card-inner { padding: 18px 20px 14px; }

/* Handle */
.id-handle { width: 36px; height: 4px; background: var(--border2); border-radius: 2px; margin: 0 auto 16px; }

/* Estado badge grande */
.id-estado {
  display: inline-flex; align-items: center; gap: 7px;
  padding: 6px 14px; border-radius: 30px;
  font-family: var(--fm); font-size: 11px; font-weight: 800;
  letter-spacing: .1em; text-transform: uppercase; margin-bottom: 16px;
}
.id-estado.ok  { background: var(--ok-bg);  border: 1.5px solid var(--ok-bd);  color: var(--ok); }
.id-estado.err { background: var(--err-bg); border: 1.5px solid var(--err-bd); color: var(--err); }
.id-estado.warn { background: rgba(196,127,23,.08); border: 1.5px solid rgba(196,127,23,.2); color: var(--warn); }
.id-estado-dot { width: 7px; height: 7px; border-radius: 50%; background: currentColor; }
@keyframes id-dot-pulse { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.4;transform:scale(.7)} }
.id-estado.ok .id-estado-dot { animation: id-dot-pulse .9s ease-in-out infinite; }

/* Persona row */
.id-persona-row { display: flex; align-items: center; gap: 16px; margin-bottom: 16px; }
.id-avatar {
  width: 64px; height: 72px; border-radius: 14px; flex-shrink: 0;
  background: var(--bg3); border: 1.5px solid var(--border);
  display: flex; align-items: center; justify-content: center;
  overflow: hidden; font-size: 28px; color: var(--muted2);
}
.id-avatar img { width: 100%; height: 100%; object-fit: cover; }
.id-avatar.ok-border  { border-color: var(--ok); box-shadow: 0 0 0 3px rgba(13,158,106,.15); }
.id-avatar.err-border { border-color: var(--err); box-shadow: 0 0 0 3px rgba(217,45,74,.15); }
.id-name { font-size: 19px; font-weight: 800; color: var(--ink); line-height: 1.15; }
.id-company { font-size: 12px; color: var(--muted); margin-top: 4px; }
.id-tag { display: inline-flex; align-items: center; gap: 4px; margin-top: 6px; padding: 3px 8px; border-radius: 4px; font-family: var(--fm); font-size: 8px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }

/* Info chips row */
.id-chips { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 6px; }
.id-chip {
  flex: 1; min-width: 0; padding: 10px 12px;
  background: var(--bg3); border: 1px solid var(--border); border-radius: 10px;
  display: flex; flex-direction: column; gap: 3px;
}
.id-chip-lbl { font-family: var(--fm); font-size: 8px; color: var(--muted2); letter-spacing: .1em; text-transform: uppercase; }
.id-chip-val { font-family: var(--fm); font-size: 13px; font-weight: 700; color: var(--gold); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

/* Countdown bar */
.id-countdown { height: 3px; background: var(--border); border-radius: 2px; margin-top: 14px; overflow: hidden; }
.id-countdown-fill { height: 100%; border-radius: 2px; background: var(--gold); width: 100%; transform-origin: left; }
@keyframes countdown-shrink { from{width:100%} to{width:0} }

.pcard-head { padding:14px 16px; border-bottom:1px solid var(--border); display:flex; gap:14px; align-items:flex-start; }
.avatar-box { width:52px; height:60px; border-radius:10px; background:var(--bg3); border:1px solid var(--border); display:flex; align-items:center; justify-content:center; flex-shrink:0; overflow:hidden; }
.avatar-box img { width:100%; height:100%; object-fit:cover; }
.av-ph { font-size:24px; color:var(--muted2); }
.pcard-name { font-size:16px; font-weight:800; color:var(--ink); line-height:1.2; }
.pcard-co { font-size:12px; color:var(--muted); margin-top:3px; }
.pcard-tag { display:inline-flex; align-items:center; gap:4px; margin-top:6px; padding:3px 8px; border-radius:4px; font-family:var(--fm); font-size:8px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; }
.tag-af { background:var(--ok-bg);  border:1px solid var(--ok-bd);  color:var(--ok);  }
.tag-pr { background:rgba(196,127,23,.08); border:1px solid rgba(196,127,23,.2); color:var(--warn); }
.tag-vi { background:#eef4fb; border:1px solid #c4d9ef; color:#2a6ab5; }
.pcard-grid { display:grid; grid-template-columns:1fr 1fr; }
.pcard-cell { padding:11px 16px; border-right:1px solid var(--border); border-bottom:1px solid var(--border); }
.pcard-cell:nth-child(even){border-right:none} .pcard-cell:nth-last-child(-n+2){border-bottom:none}
.pc-lbl { font-family:var(--fm); font-size:8px; color:var(--muted2); letter-spacing:.1em; text-transform:uppercase; margin-bottom:3px; }
.pc-val { font-size:13px; font-weight:600; color:var(--ink); }
.pc-val.mono { font-family:var(--fm); font-size:14px; letter-spacing:.04em; }
.pcard-trip { padding:12px 16px; background:var(--goldbg); border-top:1px solid var(--goldborder); display:flex; justify-content:space-between; align-items:center; }
.trip-lbl { font-family:var(--fm); font-size:8px; color:var(--muted2); letter-spacing:.1em; text-transform:uppercase; margin-bottom:3px; }
.trip-val { font-family:var(--fm); font-size:12px; font-weight:700; color:var(--gold); }
.trip-sep { width:1px; height:28px; background:var(--goldborder); }
.pcard-med { display:none; padding:12px 16px; background:#fff8f8; border-top:1px solid rgba(217,45,74,.1); }
.pcard-med.show { display:block; }
.med-tit { font-size:10px; font-weight:800; color:var(--err); margin-bottom:8px; display:flex; align-items:center; gap:6px; }
.med-grid { display:grid; grid-template-columns:1fr 1fr; gap:8px; }
.med-item label { font-family:var(--fm); font-size:8px; color:var(--muted2); text-transform:uppercase; letter-spacing:.08em; display:block; margin-bottom:2px; }
.med-item span { font-size:12px; font-weight:600; color:var(--ink); }
.btn-med { width:100%; padding:12px; background:none; border:1px solid rgba(217,45,74,.2); border-radius:8px; color:var(--err); font-family:var(--f); font-size:11px; font-weight:700; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:6px; margin-top:10px; min-height:44px; }
.btn-med:active { background:var(--err-bg); }

/* Historial */
.hist-hd { display:flex; align-items:center; justify-content:space-between; }
.hist-tit { font-family:var(--fm); font-size:9px; font-weight:700; color:var(--muted2); letter-spacing:.12em; text-transform:uppercase; }
.hist-clr { font-family:var(--fm); font-size:9px; color:var(--muted2); background:none; border:none; cursor:pointer; letter-spacing:.06em; text-transform:uppercase; padding:4px 8px; }
.hist-clr:active { color:var(--gold); }
.hist-list { display:flex; flex-direction:column; gap:5px; }
.hi { background:var(--bg); border:1px solid var(--border); border-radius:var(--r); padding:11px 14px; display:flex; align-items:center; gap:10px; box-shadow:var(--sh); }
.hi-bar { width:3px; height:34px; border-radius:2px; flex-shrink:0; }
.hi-bar.ok{background:var(--ok)} .hi-bar.err{background:var(--err)} .hi-bar.warn{background:var(--warn)}
.hi-main { flex:1; }
.hi-dni { font-family:var(--fm); font-size:14px; font-weight:700; color:var(--ink); letter-spacing:.04em; }
.hi-name { font-size:12px; color:var(--muted); margin-top:2px; }
.hi-right { text-align:right; flex-shrink:0; }
.hi-chip { display:inline-block; padding:2px 8px; border-radius:4px; font-family:var(--fm); font-size:8px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; }
.hi-chip.ok{background:var(--ok-bg);color:var(--ok)} .hi-chip.err{background:var(--err-bg);color:var(--err)} .hi-chip.warn{background:rgba(196,127,23,.08);color:var(--warn)}
.hi-time { font-family:var(--fm); font-size:9px; color:var(--muted2); margin-top:2px; }

/* ── FOOTER ── */
.footer { background:var(--bg); border-top:1px solid var(--border); padding:10px 14px; padding-bottom:max(10px,env(safe-area-inset-bottom,10px)); flex-shrink:0; display:flex; gap:8px; width:100%; }
.next-btn { flex:1; padding:14px; border-radius:var(--r); border:none; background:var(--gold); color:#fff; font-family:var(--f); font-weight:700; font-size:13px; letter-spacing:.06em; text-transform:uppercase; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:8px; box-shadow:0 3px 10px rgba(184,135,43,.3); transition:all .18s; min-height:48px; }
.next-btn:active { background:var(--gold2); transform:scale(.98); }
.next-btn:disabled { background:var(--bg3); color:var(--muted2); box-shadow:none; cursor:default; transform:none; }

/* ── LOADER ── */
#loader { display:none; position:fixed; inset:0; background:rgba(255,255,255,.85); z-index:999; align-items:center; justify-content:center; flex-direction:column; gap:12px; }
#loader.show { display:flex; }
.ld-ring { width:36px; height:36px; border:3px solid var(--border); border-top-color:var(--gold); border-radius:50%; animation:spin .9s linear infinite; }
@keyframes spin { to{transform:rotate(360deg)} }
.ld-txt { font-family:var(--fm); font-size:10px; color:var(--muted); letter-spacing:.1em; text-transform:uppercase; }

/* ── MODALES (bottom-sheets) ── */
.modal { display:none; position:fixed; inset:0; background:rgba(15,17,23,.5); z-index:400; align-items:flex-end; justify-content:center; }
.modal.open { display:flex; }
.modal-sheet { background:var(--bg); border-radius:20px 20px 0 0; width:100%; max-width:480px; max-height:92vh; overflow-y:auto; animation:sheetup .25s ease-out; padding-bottom:env(safe-area-inset-bottom,0px); }
@keyframes sheetup { from{transform:translateY(30px);opacity:0} to{transform:none;opacity:1} }
.modal-sheet::-webkit-scrollbar { width:3px; }
.modal-sheet::-webkit-scrollbar-thumb { background:var(--border2); border-radius:2px; }
.modal-handle { width:36px; height:4px; border-radius:2px; background:var(--border2); margin:10px auto 0; }
.modal-top { padding:16px 20px 14px; border-bottom:1px solid var(--border); }
.modal-ey { font-family:var(--fm); font-size:8px; color:var(--gold); letter-spacing:.12em; text-transform:uppercase; margin-bottom:3px; }
.modal-tit { font-size:19px; font-weight:800; color:var(--ink); }
.modal-body { padding:16px 20px; display:flex; flex-direction:column; gap:10px; }
.mf-lbl { font-family:var(--fm); font-size:8px; color:var(--muted2); letter-spacing:.1em; text-transform:uppercase; margin-bottom:5px; display:block; }
.mf-inp { width:100%; background:var(--bg3); border:1px solid var(--border); border-radius:var(--r); padding:12px 13px; font-family:var(--f); font-size:14px; font-weight:500; color:var(--ink); outline:none; transition:border-color .2s; }
.mf-inp:focus { border-color:var(--goldborder); background:var(--bg); }
.mf-inp.xl { font-family:var(--fm); font-size:28px; text-align:center; letter-spacing:.12em; font-weight:700; }
.mf-inp[readonly] { color:var(--muted); }
.mf-sel { width:100%; background:var(--bg3); border:1px solid var(--border); border-radius:var(--r); padding:12px 32px 12px 13px; font-family:var(--f); font-size:14px; color:var(--ink); outline:none; appearance:none; background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M1 1l4 4 4-4' stroke='%239ba3b5' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:right 12px center; }
.mf-grid { display:grid; grid-template-columns:1fr 1fr; gap:8px; }
.mf-grp { display:flex; flex-direction:column; }
.extra-box { background:#f0f8ff; border:1px solid #c4d9ef; border-radius:var(--r); padding:12px; display:flex; flex-direction:column; gap:8px; }
.extra-box .mf-lbl { color:#2a6ab5; }
.modal-foot { padding:12px 20px; padding-bottom:max(16px,env(safe-area-inset-bottom,16px)); border-top:1px solid var(--border); display:flex; gap:8px; position:sticky; bottom:0; background:var(--bg); z-index:10; }
.mf-cancel { flex:1; padding:13px; background:transparent; border:1px solid var(--border); border-radius:var(--r); color:var(--muted); font-family:var(--f); font-weight:600; font-size:12px; letter-spacing:.04em; text-transform:uppercase; cursor:pointer; min-height:48px; }
.mf-cancel:active { color:var(--ink); }
.mf-ok { flex:2; padding:13px; background:var(--gold); border:none; border-radius:var(--r); color:#fff; font-family:var(--f); font-weight:700; font-size:12px; letter-spacing:.04em; text-transform:uppercase; cursor:pointer; min-height:48px; box-shadow:0 2px 8px rgba(184,135,43,.25); }
.mf-ok:active { background:var(--gold2); }

/* Mapa de asientos */
.seat-map-wrap { background:var(--bg3); border:1px solid var(--border); border-radius:var(--r); padding:12px; }
.seat-front { background:var(--bg2); border:1px solid var(--border); border-radius:6px 6px 12px 12px; height:26px; display:flex; align-items:center; justify-content:center; font-family:var(--fm); font-size:8px; color:var(--muted2); letter-spacing:.12em; text-transform:uppercase; margin-bottom:8px; }
.seat-grid { display:grid; grid-template-columns:repeat(5,1fr); gap:5px; }
.seat { height:36px; border-radius:6px; display:flex; align-items:center; justify-content:center; font-family:var(--fm); font-size:11px; font-weight:500; cursor:pointer; transition:all .15s; background:var(--bg); border:1px solid var(--border); color:var(--muted); -webkit-tap-highlight-color:transparent; }
.seat:active:not(.occupied) { border-color:var(--gold); color:var(--gold); background:var(--goldbg); }
.seat.occupied { background:var(--err-bg); border-color:var(--err-bd); color:rgba(217,45,74,.4); cursor:not-allowed; }
.seat.selected { background:var(--gold); border-color:var(--gold); color:#fff; box-shadow:0 2px 6px rgba(184,135,43,.25); }
.seat.aisle { background:none; border:none; cursor:default; }
.seat-leg { display:flex; gap:12px; justify-content:center; margin-top:8px; font-family:var(--fm); font-size:8px; color:var(--muted2); }
.sl { display:flex; align-items:center; gap:4px; }
.sl-dot { width:8px; height:8px; border-radius:2px; }
.seat-chosen { text-align:center; font-family:var(--fm); font-size:10px; color:var(--gold); margin-top:6px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; }

/* Pantallas muy pequeñas */
@media (max-width:360px) { .live-time{display:none} .brand-sub{display:none} }

/* Botón volver */
.back-btn { width:34px; height:34px; border-radius:50%; background:var(--bg3); border:1px solid var(--border); display:flex; align-items:center; justify-content:center; color:var(--muted); font-size:14px; text-decoration:none; transition:all .18s; flex-shrink:0; }
.back-btn:active { background:var(--err-bg); color:var(--err); border-color:var(--err-bd); }

/* ── BOTTOM NAV ── */
.bottom-nav { display: none; } /* Oculto por defecto en PC */

@media (max-width: 640px) { /* SOLO aparece en pantallas de celular */
  .bottom-nav {
    display: flex;
    position: fixed; bottom: 0; left: 0; right: 0; z-index: 999; 
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px);
    border-top: 1px solid #E2E2E8;
    padding: 8px 6px calc(8px + env(safe-area-inset-bottom, 0px));
    align-items: center; justify-content: space-around;
  }

  /* Le damos espacio al footer solo en celular para que no tape el botón */
  .footer {
    padding-bottom: calc(76px + env(safe-area-inset-bottom, 10px)) !important;
  }

  .bn-item { display:flex; flex-direction:column; align-items:center; gap:3px; cursor:pointer; padding:5px 10px; border-radius:10px; flex:1; transition:background .12s; -webkit-tap-highlight-color:transparent; text-decoration:none; color:inherit; border:none; background:none; }
  .bn-item:active { background:#F0F0F4; transform:scale(.94); }
  .bn-ico { width:24px; height:24px; display:flex; align-items:center; justify-content:center; }
  .bn-ico svg { width:22px; height:22px; fill:none; stroke-width:2; stroke-linecap:round; stroke-linejoin:round; transition:stroke .15s; }
  .bn-lbl { font-size:10px; font-weight:600; transition:color .15s; }
  .bn-item.active .bn-ico svg { stroke:#C49A2C; }
  .bn-item.active .bn-lbl   { color:#C49A2C; }
  .bn-item:not(.active) .bn-ico svg { stroke:#909090; }
  .bn-item:not(.active) .bn-lbl    { color:#909090; }
  .bn-emg { display:flex; flex-direction:column; align-items:center; gap:3px; cursor:pointer; flex-shrink:0; position:relative; top:-14px; -webkit-tap-highlight-color:transparent; }
  .bn-emg-circle { width:52px; height:52px; border-radius:50%; background:#16a34a; display:flex; align-items:center; justify-content:center; box-shadow:0 4px 16px rgba(22,163,74,.4), 0 0 0 4px rgba(22,163,74,.1); transition:transform .12s; }
  .bn-emg:active .bn-emg-circle { transform:scale(.91); }
  .bn-emg-circle svg { width:23px; height:23px; stroke:#fff; stroke-width:2.5; fill:none; stroke-linecap:round; }
  .bn-emg-lbl { font-size:10px; font-weight:700; color:#16a34a; }

  /* Ocultamos el menú lateral en celular */
  .sidebar { display: none !important; }
}

/* Historial colapsable (fuera del media query para que funcione siempre) */
.hist-hd { display:flex; align-items:center; justify-content:space-between; cursor:pointer; user-select:none; padding:4px 0; }
.hist-hd:active { opacity:.7; }
.hist-tit { font-family:var(--fm); font-size:9px; font-weight:700; color:var(--muted2); letter-spacing:.12em; text-transform:uppercase; display:flex; align-items:center; gap:6px; }
.hist-chevron { font-size:10px; color:var(--muted2); transition:transform .25s ease; }
.hist-hd.collapsed .hist-chevron { transform:rotate(-90deg); }
.hist-actions { display:flex; align-items:center; gap:8px; }
.hist-clr { font-family:var(--fm); font-size:9px; color:var(--muted2); background:none; border:none; cursor:pointer; letter-spacing:.06em; text-transform:uppercase; padding:4px 8px; }
.hist-clr:active { color:var(--gold); }
.hist-list { display:flex; flex-direction:column; gap:5px; overflow:hidden; transition:max-height .3s ease, opacity .25s ease; max-height:600px; opacity:1; }
.hist-list.collapsed { max-height:0; opacity:0; pointer-events:none; }

</style>
</head>
<body>

<!-- LOADER -->
<div id="loader">
  <div class="ld-ring"></div>
  <div class="ld-txt">Procesando...</div>
</div>

<!-- TOPNAV -->
<div class="topnav">
  <div class="topnav-inner">
    <a href="dashboard.php" class="brand">
      <img src="../assets/logo4.png" class="brand-logo" alt="Hochschild"
        onerror="this.style.display='none';this.nextElementSibling.style.display='block'">
      <svg class="brand-logo-svg" viewBox="0 0 32 32" fill="none">
        <polygon points="16,2 28,9 28,23 16,30 4,23 4,9" fill="none" stroke="#b8872b" stroke-width="1.5"/>
        <polygon points="16,8 24,12.5 24,20 16,24 8,20 8,12.5" fill="rgba(184,135,43,0.1)"/>
        <circle cx="16" cy="16" r="2.5" fill="#b8872b"/>
      </svg>
      <div>
        <div class="brand-name">Hochschild <span>Mining</span></div>
        <div class="brand-sub">Control de Embarque</div>
      </div>
    </a>
    <div class="topnav-right">
      <div class="live-pill" id="livePill">
        <div class="live-dot"></div>
        <span class="live-lbl" id="liveLbl">En vivo</span>
        <span class="live-time" id="liveTime"></span>
      </div>
      <div class="ctr-badge" id="sessionCount">0</div>
      <a href="dashboard.php" class="back-btn" title="Volver al dashboard">
        <i class="fas fa-sign-out-alt"></i>
      </a>
    </div>
  </div>
  <div class="gold-rule"></div>
</div>

<!-- LAYOUT -->
<div class="layout">

  <!-- SIDEBAR (solo desktop) -->
  <div class="sidebar">
    <button class="sb-btn active" title="Scanner">
      <i class="fas fa-qrcode"></i><span class="sb-lbl">Scan</span>
    </button>
    <button class="sb-btn" title="Configuración" style="margin-top:auto">
      <i class="fas fa-cog"></i><span class="sb-lbl">Config</span>
    </button>
  </div>

  <!-- MAIN -->
  <div class="main" id="mainScroll">

    <!-- SCANNER -->
    <div class="scanner-sec">
      <div class="scanner-toprow">
        <div class="scanner-lbl">Lector QR / DNI</div>
        <div style="font-family:var(--fm);font-size:9px;font-weight:700;color:var(--ok);background:var(--ok-bg);border:1px solid var(--ok-bd);padding:5px 12px;border-radius:20px;display:flex;align-items:center;gap:5px;">
          <i class="fas fa-arrow-up" style="font-size:9px"></i> SUBIDA
        </div>
      </div>

      <!-- El viewport del scanner -->
      <div class="scan-vp" id="scanVp">
        <div class="vp-sim" id="vpSim"></div>
        <div class="vp-grid" id="vpGrid"></div>
        <div class="vp-line" id="vpLine"></div>
        <div class="vp-corners">
          <div class="vc tl"><svg viewBox="0 0 26 26" fill="none"><path d="M2 16V3a1 1 0 011-1h13" stroke="#b8872b" stroke-width="2.5" stroke-linecap="round"/></svg></div>
          <div class="vc tr"><svg viewBox="0 0 26 26" fill="none"><path d="M2 16V3a1 1 0 011-1h13" stroke="#b8872b" stroke-width="2.5" stroke-linecap="round"/></svg></div>
          <div class="vc bl"><svg viewBox="0 0 26 26" fill="none"><path d="M2 16V3a1 1 0 011-1h13" stroke="#b8872b" stroke-width="2.5" stroke-linecap="round"/></svg></div>
          <div class="vc br"><svg viewBox="0 0 26 26" fill="none"><path d="M2 16V3a1 1 0 011-1h13" stroke="#b8872b" stroke-width="2.5" stroke-linecap="round"/></svg></div>
        </div>
        <div class="vp-zone"><div class="vp-zone-in"></div></div>
        <div class="vp-qr-art" id="qrArt"></div>
        <div class="vp-badge">30 fps · AUTO</div>
        <div class="vp-meta">
          <div class="vp-fps" id="fpsDisplay">30 fps</div>
          <div class="vp-scan-ind"><div class="vp-dot"></div>Listo para escanear</div>
        </div>
        <div class="vp-hint">Acerque el QR o código de barras</div>

        <!-- EL READER REAL (html5-qrcode monta aquí) -->
        <div id="reader-wrapper" style="position:absolute;inset:0;z-index:10;">
          <div id="reader" style="width:100%;height:100%;"></div>
        </div>

        <div class="vp-flash" id="vpFlash"></div>

        <div class="vp-nocam" id="vpNocam">
          <i class="fas fa-video-slash"></i>
          <span>Cámara no disponible.<br>Use ingreso manual.</span>
        </div>

        <div class="res-ov res-bg-ok" id="resOk">
          <div class="res-icon ok"><i class="fas fa-check"></i></div>
          <div class="res-word ok">Autorizado</div>
          <div class="res-sub" id="resOkSub">Bus — · Asiento —</div>
        </div>
        <div class="res-ov res-bg-err" id="resErr">
          <div class="res-icon err"><i class="fas fa-times"></i></div>
          <div class="res-word err">Denegado</div>
          <div class="res-sub" id="resErrSub">Sin autorización</div>
        </div>
      </div>
    </div>

    <!-- BARRA BAJADA — deshabilitada en marcha blanca -->
    <div class="loc-bar" id="div-lugar-bajada" style="display:none !important">
      <i class="fas fa-map-marker-alt loc-icon"></i>
      <span class="loc-lbl">Bajada</span>
      <input type="text" class="loc-inp" id="lugar_bajada" placeholder="">
    </div>

    <!-- Modo fijo en NORMAL durante marcha blanca -->
    <input type="hidden" id="modo_actual" value="NORMAL">

    <!-- BARRA MANUAL -->
    <div class="manual-bar">
      <div class="dni-wrap">
        <i class="fas fa-id-card dni-ico"></i>
      <input type="tel" class="dni-inp" id="dniInputBar" placeholder="Ingrese DNI manualmente..." maxlength="8"
          oninput="if(this.value.length===8){ abrirManualConBar(); }"
          onkeydown="if(event.key==='Enter'){ abrirManualConBar(); }">
      </div>
      <button class="val-btn" onclick="abrirManualConBar()">
        <i class="fas fa-arrow-right"></i> Validar
      </button>
      <button class="add-btn" onclick="abrirAgregarManual()" title="Agregar pasajero">
        <i class="fas fa-user-plus"></i>
      </button>
    </div>

    <!-- PANEL -->
    <div class="panel">

      <!-- STATUS -->
      <div class="status-box neutral" id="statusBox">
        <div>
          <div class="stat-word neutral" id="statusWord">LISTO</div>
          <div class="stat-detail" id="statusDetail">Esperando código QR</div>
        </div>
        <div class="stat-ico si-neutral" id="statusIco">
          <i class="fas fa-clock" style="font-size:14px"></i>
        </div>
      </div>

      <!-- TARJETA -->
      <div id="datos"></div>

      <!-- HISTORIAL -->
      <div class="hist-hd" id="histHd" onclick="toggleHistorial()">
        <div class="hist-tit">
          <i class="fas fa-chevron-down hist-chevron" id="histChevron"></i>
          // Escaneos recientes
        </div>
        <div class="hist-actions" onclick="event.stopPropagation()">
          <button class="hist-clr" onclick="limpiarHistorial()">Limpiar</button>
        </div>
      </div>
      <div class="hist-list" id="historialList"></div>

    </div>
  </div><!-- /main -->
</div><!-- /layout -->

<!-- FOOTER -->
<div class="footer">
  <button class="next-btn" id="btnSiguiente" disabled onclick="iniciarScanner()">
    <i class="fas fa-chevron-right"></i> Siguiente Escaneo
  </button>
</div>

<!-- ═══════════════════════════════════════
     MODALES
═══════════════════════════════════════ -->

<!-- MODAL: VALIDACIÓN MANUAL (estructura original conservada) -->
<div id="modalManual" class="modal">
  <div class="modal-sheet">
    <div class="modal-handle"></div>
    <div class="modal-top" style="display:flex;align-items:center;gap:12px;">
      <button onclick="cerrarManual()" style="flex-shrink:0;width:36px;height:36px;border-radius:50%;background:var(--bg3);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;color:var(--muted);font-size:14px;cursor:pointer;" title="Volver">
        <i class="fas fa-arrow-left"></i>
      </button>
      <div style="flex:1">
        <div class="modal-ey">Validación</div>
        <div class="modal-tit">Ingreso Manual</div>
      </div>
    </div>
    <div class="modal-body">
      <label class="mf-lbl">Número de DNI</label>
      <input type="tel" id="dniManual" class="mf-inp xl" placeholder="00000000" maxlength="8">
    </div>
    <div class="modal-foot">
      <button class="mf-cancel" onclick="cerrarManual()">Cancelar</button>
      <button class="mf-ok" onclick="enviarManual()">Validar →</button>
    </div>
  </div>
</div>

<!-- MODAL: AGREGAR PASAJERO (IDs originales conservados) -->
<div id="modalAgregar" class="modal">
  <div class="modal-sheet">
    <div class="modal-handle"></div>
    <div class="modal-top" style="display:flex;align-items:center;gap:12px;">
      <button onclick="cerrarAgregar()" style="flex-shrink:0;width:36px;height:36px;border-radius:50%;background:var(--bg3);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;color:var(--muted);font-size:14px;cursor:pointer;" title="Volver">
        <i class="fas fa-arrow-left"></i>
      </button>
      <div style="flex:1">
        <div class="modal-ey" id="subtituloAgregar">Nuevo pasajero</div>
        <div class="modal-tit" id="tituloAgregar">Agregar Pasajero</div>
      </div>
    </div>
    <div class="modal-body">

      <div>
        <label class="mf-lbl">DNI</label>
        <input type="tel" id="new_dni" class="mf-inp" maxlength="8" placeholder="Número de DNI" readonly>
      </div>

      <div id="extra_personal_fields" class="extra-box" style="display:none">
        <label class="mf-lbl" style="color:#2a6ab5">Datos personales</label>
        <div>
          <label class="mf-lbl">Nombres</label>
          <input type="text" id="new_nombres" class="mf-inp" placeholder="Nombres completos">
        </div>
        <div>
          <label class="mf-lbl">Apellidos</label>
          <input type="text" id="new_apellidos" class="mf-inp" placeholder="Apellidos">
        </div>
        <div>
          <label class="mf-lbl">Empresa</label>
          <input type="text" id="new_empresa" class="mf-inp" placeholder="Razón social">
        </div>
      </div>

      <div class="mf-grid">
        <div class="mf-grp">
          <label class="mf-lbl">Unidad / Bus</label>
          <select id="new_bus" class="mf-sel" onchange="cargarMapaAsientos()">
            <option value="">-- Bus --</option>
            <?= $buses_opt ?>
          </select>
        </div>
        <div class="mf-grp">
          <label class="mf-lbl">Tipo Mov.</label>
          <select id="new_tipo" class="mf-sel" onchange="cargarMapaAsientos()">
            <option value="subida">INGRESO</option>
            <option value="bajada">SALIDA</option>
          </select>
        </div>
      </div>

      <div>
        <label class="mf-lbl">Destino</label>
        <input type="text" id="new_destino" class="mf-inp" placeholder="Ej: LIMA, GARITA...">
      </div>

      <div>
        <label class="mf-lbl" style="color:var(--gold)">Seleccionar asiento</label>
        <div class="seat-map-wrap">
          <div class="seat-front">↑ Frente del bus</div>
          <div id="busMap" class="seat-grid">
            <p style="grid-column:1/-1;text-align:center;font-size:11px;color:var(--muted2);padding:12px 0">Seleccione un Bus para ver asientos</p>
          </div>
          <div class="seat-leg">
            <div class="sl"><div class="sl-dot" style="background:var(--bg);border:1px solid var(--border)"></div>Libre</div>
            <div class="sl"><div class="sl-dot" style="background:var(--err-bg);border:1px solid var(--err-bd)"></div>Ocupado</div>
            <div class="sl"><div class="sl-dot" style="background:var(--gold)"></div>Elegido</div>
          </div>
          <div class="seat-chosen" id="txtAsientoSeleccionado">Ninguno seleccionado</div>
        </div>
        <input type="hidden" id="new_asiento">
      </div>

    </div>
    <div class="modal-foot">
      <button class="mf-cancel" onclick="cerrarAgregar()">Cancelar</button>
      <button class="mf-ok" onclick="guardarExtra()">Agregar →</button>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════
     JAVASCRIPT — LÓGICA 100% ORIGINAL
     Solo se agregan funciones de UI nuevas
     sin modificar ninguna función existente
═══════════════════════════════════════ -->
<script>
/* ── QR ART DECORATIVO (solo visual) ── */
const QR_PAT=[1,1,1,1,1,1,1,0,1,0,1,1,1,0,1,1,1,1,1,1,1,1,0,0,0,0,0,1,0,0,1,0,0,0,1,1,0,1,1,1,0,1,1,0,1,1,0,1,1,1,0,1,0,0,0,1,0,0,0,1,1,0,1,1,1,0,1,1,0,1,0,0,0,1,0,1,1,0,1,1,1,0,1,1,0,0,0,0,0,1,0,1,0,1,0,0,0,1,1,1,1,1,1,1,0,1,0,1,0,1,1,1,1,1,1,1,0,0,0,0,0,0,0,0,1,0,1,0,0,0,0,0,0,0,1,0,0,0,1,0,1,1,1,0,0,1,0,0,0,1,0,0,1,1,0,0,1,0,1,1,0,0,0,0,1,0,1,1,1,0,0,0,0,1,0,1,0,1,0,0,1,0,0,0,0,1,0,1,0,0,0,0,1,0,1,0,0,0,0,0,0,1,0,1,0,1,0,0,1,1,0,1,1,1,0,1,1,0,0,1,1,1,0,0,0,0,0,0,0,1,0,1,0,0,0,1,0,0,0,1,1,1,1,1,1,1,0,0,0,0,1,0,0,1,1,0,1,1,1,0,1,1,0,0,0,0,0,1,1,1,0,0,1,0,1,0,1,0,1,1,0,1,1,1,0,1,1,0,1,0,1,1,0,0,0,0,1,0,1,0,1,1,1,1,1,1,1,0,0,0,0,0,1,1,0,1,0,0,0,0,0,1,0,1,0,1,1,1,0,1,0,1,0,0,0,1,1,0,1,0,0,0,0,0,0,1,0,1,0,1,1,1,1,1,1,1,0,0,1,0,0,1,0,0,1,1,0,0,0,1,1,0,1,1,0,0,1,0,1,1,1,0,0,1,1,0,0,0,1,0,1,1,0,0,0,0,1,1,0,0,0,1,0,1,1,0,0,1,0,1,1,1,0,1,1,1,0,1,0,0,1,0,0,0,0,1,0,0,0,1,1,1,1,1,1,1,0,1,0,0,1,0,0,1,0,0,1,0,0,0,1,0,0,0,0,0,1,0,1,1,1,0,1,0,1,1,1,0,1,1,0,1,0,0,0,1,0,1,0,0,0,1,0,1,0,0,0,0,0,1,0,1,0,1,1,1,0,1,1,0,1,0,1,1,1,1,1,1,1,0,1,0,1,1,0,1,0,1,0,0,0,1,1,1,1,1,1,1,0,1,1,1,0,0,1,0,1,1];
const qrEl=document.getElementById('qrArt');
QR_PAT.forEach(v=>{const c=document.createElement('div');c.style.background=v?'rgba(184,135,43,0.85)':'transparent';c.style.borderRadius='1px';qrEl.appendChild(c);});

/* ── VARIABLES GLOBALES ORIGINALES ── */
let html5QrCode=null, lastResult=null, countResults=0, sessionCounter=0;
let audioCtx=null;

/* ── INIT ── */
window.onload = () => { renderHistorial(); iniciarScanner(); };

/* ── RELOJ (nuevo, no afecta nada) ── */
function tickClock(){
  const n=new Date();
  const el=document.getElementById('liveTime');
  if(el) el.textContent=String(n.getHours()).padStart(2,'0')+':'+String(n.getMinutes()).padStart(2,'0')+':'+String(n.getSeconds()).padStart(2,'0');
}
tickClock(); setInterval(tickClock,1000);

/* ── LOADER (mismo del original, adaptado) ── */
function showLoader(show) { 
  document.getElementById('loader').style.display = show ? 'flex' : 'none'; 
}

/* ── MODALES (manteniendo nombres originales exactos) ── */
function abrirManual() { 
  document.getElementById('modalManual').classList.add('open');
  document.getElementById('modalManual').style.display='flex';
  document.getElementById('dniManual').focus(); 
}
function cerrarManual() { 
  document.getElementById('modalManual').classList.remove('open');
  document.getElementById('modalManual').style.display='none';
  document.getElementById('dniManual').value=''; 
}

/* Helper para la barra de DNI manual */
async function abrirManualConBar() {
  const val = document.getElementById('dniInputBar').value.trim();
  if(val.length === 8) {
    scanCooldown = true;
    document.getElementById('reader-wrapper').style.display='none';
    validarDNI(val);
  } else {
    abrirManual();
    if(val) document.getElementById('dniManual').value = val;
  }
}

function abrirAgregarManual() { prepararModalAgregar('', 'MANUAL'); }

function abrirAsignarViaje() {
  // Abre el modal con un campo DNI editable para buscar la persona y asignarle viaje
  prepararModalAgregar('', 'ASIGNAR');
}


function cerrarAgregar(skipScanner = false) { 
  document.getElementById('modalAgregar').classList.remove('open');
  document.getElementById('modalAgregar').style.display='none';
  if(!skipScanner) reactivarScanner();
}

/* ── MODO SUBIDA/BAJADA (lógica original exacta) ── */
function setModo(m) {
    document.getElementById('modo_actual').value = m;
    document.querySelectorAll('.mt-btn').forEach(b => { b.className = 'mt-btn'; });
    if(m==='NORMAL') { document.getElementById('btnNormal').classList.add('on-reg'); }
    if(m==='BAJADA') { document.getElementById('btnBajada').classList.add('on-exit'); }
    const divBajada = document.getElementById('div-lugar-bajada');
    const pill = document.getElementById('livePill');
    const lbl  = document.getElementById('liveLbl');
    if(m === 'BAJADA') {
        divBajada.classList.add('show');
        pill.classList.add('bajada-mode');
        lbl.textContent = 'Bajada';
        document.getElementById('lugar_bajada').focus();
    } else {
        divBajada.classList.remove('show');
        pill.classList.remove('bajada-mode');
        lbl.textContent = 'En vivo';
    }
}

/* ── SCANNER ── */
let scanCooldown = false;

/* Detiene y destruye completamente — solo para entrada manual */
async function detenerScanner() {
  if (!html5QrCode) return;
  try { if (html5QrCode._isScanning) await html5QrCode.stop(); } catch(e) {}
  try { html5QrCode.clear(); } catch(e) {}
  html5QrCode = null;
  const rd = document.getElementById('reader');
  if (rd) rd.innerHTML = '';
}

/* Resetea solo la UI para el siguiente escaneo — la cámara sigue corriendo */
function reactivarScanner() {
  cerrarIdOverlay();
  scanCooldown = false;
  lastResult = null; countResults = 0;
  document.getElementById('resOk').classList.remove('show');
  document.getElementById('resErr').classList.remove('show');
  document.getElementById('scanVp').classList.remove('result-on');
  document.getElementById('reader-wrapper').style.display = 'block';
  document.getElementById('dniInputBar').value = '';
  document.getElementById("btnSiguiente").disabled = false;
  document.getElementById('mainScroll').scrollTo({top:0, behavior:'smooth'});
}

/* Arranca la cámara — solo se llama UNA vez al cargar y después de entrada manual */
async function iniciarScanner() {
  document.getElementById("btnSiguiente").disabled = true;
  document.getElementById("datos").innerHTML = "";
  setStatusUI('neutral','LISTO','Apunte el código al lector','fa-qrcode');
  document.getElementById('resOk').classList.remove('show');
  document.getElementById('resErr').classList.remove('show');
  document.getElementById('scanVp').classList.remove('result-on');
  ['vpSim','vpGrid','vpLine'].forEach(id=>{ const el=document.getElementById(id); if(el) el.style.display=''; });
  document.getElementById('reader-wrapper').style.display = 'block';

  // Si la cámara ya está corriendo, solo resetear UI
  if (html5QrCode && html5QrCode._isScanning) {
    reactivarScanner();
    return;
  }

  // Limpiar cualquier instancia anterior
  await detenerScanner();
  scanCooldown = false; lastResult = null; countResults = 0;

  try {
    html5QrCode = new Html5Qrcode("reader");
    const vp  = document.getElementById('scanVp');
    const qrW = Math.min(vp.offsetWidth  * 0.88, 320);
    const qrH = Math.min(vp.offsetHeight * 0.55, 160);

    await html5QrCode.start(
      { facingMode: "environment" },
      {
        fps: 30,
        qrbox: { width: Math.round(qrW), height: Math.round(qrH) },
        aspectRatio: 1.0,
        disableFlip: false,
        experimentalFeatures: { useBarCodeDetectorIfSupported: true }
      },
      (decoded) => {
        if (scanCooldown) return;
        const d = decoded.trim();
        if (d.length < 8) return;
        scanCooldown = true;
        lastResult = d; countResults = 0;
        document.getElementById('reader-wrapper').style.display = 'none';
        validarDNI(d);
      },
      () => {}
    );

    ['vpSim','vpGrid','vpLine'].forEach(id=>{ const el=document.getElementById(id); if(el) el.style.display='none'; });
    document.getElementById('vpNocam').classList.remove('show');
    document.getElementById("btnSiguiente").disabled = false;

  } catch(err) {
    html5QrCode = null;
    document.getElementById('vpNocam').classList.add('show');
    document.getElementById("btnSiguiente").disabled = false;
  }
}

/* ── VALIDAR DNI — CÓDIGO 100% ORIGINAL ── */
async function validarDNI(dni){
  showLoader(true);
  const modo = document.getElementById('modo_actual').value;
  let lugarBajada = '';
  if(modo === 'BAJADA') {
     lugarBajada = document.getElementById('lugar_bajada').value.trim();
     if(lugarBajada === '') { 
       showLoader(false); 
       Swal.fire({icon: 'warning', title: 'Atención', text: 'Ingrese el LUGAR DE DESEMBARQUE antes de escanear', confirmButtonColor: '#000000'}); 
       document.getElementById("btnSiguiente").disabled=false; 
       return; 
     }
  }
  try {
    const response = await fetch("validar.php", { 
      method: "POST", 
      headers: {"Content-Type": "application/x-www-form-urlencoded"}, 
      body: `dni=${dni}&modo=${modo}&ubicacion=${encodeURIComponent(lugarBajada)}` 
    });
    const data = await response.json(); 
    showLoader(false);
    
    if(data.estado === "FALTA_VIAJE") {
        prepararModalAgregar(dni, 'FALTA_VIAJE', data.persona.nombres);
        document.getElementById("btnSiguiente").disabled=false;
        return;  // cooldown queda en true — cerrarAgregar() lo resetea
    }
    if(data.estado === "NO_EXISTE") {
        prepararModalAgregar(dni, 'NO_EXISTE');
        document.getElementById("btnSiguiente").disabled=false;
        return;  // cooldown queda en true — cerrarAgregar() lo resetea
    }
    if(data.estado === "ERROR") { 
      Swal.fire('Error', data.mensaje, 'error'); 
      document.getElementById("btnSiguiente").disabled=false;
      reactivarScanner();
      return; 
    }
    
    const isOk = data.estado==="AUTORIZADO";
    
    // Beep original
    if(isOk) playBeep(880, 200);
    else playBeep(220, 300);

    // Vibración haptica (nuevo, no afecta nada)
    if('vibrate' in navigator) navigator.vibrate(isOk?[80]:[100,80,100]);

    // Flash de color en viewport (nuevo visual)
    mostrarFlash(isOk ? 'ok' : 'err', data);

    // Status (adaptado al nuevo diseño)
    let pillClass = isOk ? 'ok' : 'err';
    if(modo === 'BAJADA' && isOk) pillClass = 'warn';
    setStatusUI(pillClass, data.estado, data.movimiento, isOk?'fa-check':'fa-times');

    // Renderizar tarjeta (función original)
    renderizarTarjeta(data, dni);

    // ── Transición visual de identidad ──
    const overlayTipo = !isOk ? 'err' : (modo === 'BAJADA' ? 'warn' : 'ok');
    mostrarIdOverlay(data, dni, overlayTipo);

    if(isOk) { 
      sessionCounter++; 
      // Actualizar badge con animación pop
      const badge = document.getElementById('sessionCount');
      badge.textContent = sessionCounter;
      badge.classList.remove('pop'); void badge.offsetWidth; badge.classList.add('pop');
      setTimeout(()=>badge.classList.remove('pop'),400);

      let history = JSON.parse(localStorage.getItem('histBus') || '[]');
      history.unshift({ dni, nombre: data.persona?data.persona.nombres:'Desc.', estado: data.estado, hora: new Date().toLocaleTimeString() });
      localStorage.setItem('histBus', JSON.stringify(history.slice(0,8))); 
      renderHistorial(); 
    }
  } catch(e) { 
    showLoader(false); 
    Swal.fire({icon: 'error', title: 'Error de Red', text: 'Revise su conexión'});
    reactivarScanner();
    return;
  }
  document.getElementById("btnSiguiente").disabled=false;
  document.getElementById('dniInputBar').value = '';
  // El overlay dura 5s — reactivar scanner cuando ya cerró
  setTimeout(() => { reactivarScanner(); }, 5200);
}

/* ── FLASH VISUAL ── */
function mostrarFlash(tipo, data) {
  const vp    = document.getElementById('scanVp');
  const flash = document.getElementById('vpFlash');
  flash.className = 'vp-flash'; void flash.offsetWidth; flash.classList.add(tipo);
  vp.classList.add('result-on');
  const ok  = document.getElementById('resOk');
  const err = document.getElementById('resErr');
  if(tipo === 'ok' && data) {
    const bus   = data.persona?.bus   || '—';
    const placa = data.persona?.placa || '';
    document.getElementById('resOkSub').textContent = bus + (placa ? ' · ' + placa : '');
    ok.classList.add('show');
    setTimeout(()=>{ ok.classList.remove('show'); vp.classList.remove('result-on'); }, 1400);
  } else {
    const msg = data?.movimiento || 'Sin autorización';
    document.getElementById('resErrSub').textContent = msg;
    err.classList.add('show');
    setTimeout(()=>{ err.classList.remove('show'); vp.classList.remove('result-on'); }, 1800);
  }
}

/* ── ID CARD OVERLAY — transición visual de identidad ── */
let idOverlayTimer = null;

function mostrarIdOverlay(data, dni, tipo) {
  // Limpiar timer anterior si hay uno corriendo
  if (idOverlayTimer) { clearTimeout(idOverlayTimer); idOverlayTimer = null; }

  const overlay = document.getElementById('idOverlay');
  const bar     = document.getElementById('idCardBar');
  const estado  = document.getElementById('idEstado');
  const estadoTxt = document.getElementById('idEstadoTxt');
  const avatar  = document.getElementById('idAvatar');
  const nombre  = document.getElementById('idNombre');
  const empresa = document.getElementById('idEmpresa');
  const tag     = document.getElementById('idTag');
  const dniEl   = document.getElementById('idDni');
  const busEl   = document.getElementById('idBus');
  const dest    = document.getElementById('idDestino');
  const cntBar  = document.getElementById('idCountdown');

  // Color según estado
  const cls = tipo === 'ok' ? 'ok' : tipo === 'warn' ? 'warn' : 'err';
  bar.className     = 'id-card-bar ' + cls;
  estado.className  = 'id-estado ' + cls;

  // Texto estado
  const textos = { ok: '✓ Autorizado', err: '✕ Denegado', warn: '⬇ Bajada' };
  estadoTxt.textContent = textos[cls] || data.estado;

  // Avatar
  if (data.foto) {
    avatar.innerHTML = `<img src="${data.foto}" alt="">`;
    avatar.className = `id-avatar ${cls}-border`;
  } else {
    avatar.innerHTML = `<i class="fas fa-user"></i>`;
    avatar.className = `id-avatar ${cls}-border`;
  }

  // Persona
  const p = data.persona;
  if (p) {
    nombre.textContent  = (p.nombres || '') + ' ' + (p.apellidos || '');
    empresa.textContent = p.empresa || '—';
    // Tag de validación
    let claseTag = 'tag-vi', est = (p.validacion||'VISITA').toUpperCase();
    if (est.includes('AFILIADO')) claseTag = 'tag-af';
    if (est.includes('PROCESO'))  claseTag = 'tag-pr';
    tag.className   = 'id-tag ' + claseTag;
    tag.textContent = est;
    dniEl.textContent  = p.dni || dni;
    // Asiento viene en data.asiento (raíz) y también en data.persona.asiento
    const asiento = data.asiento || p.asiento || '';
    busEl.textContent  = (p.bus || '—') + (asiento ? ' · Asiento ' + asiento : '');
    dest.textContent   = data.destino || '—';
  } else {
    nombre.textContent  = dni;
    empresa.textContent = '—';
    tag.textContent     = '';
    dniEl.textContent   = dni;
    busEl.textContent   = '—';
    dest.textContent    = '—';
  }

  // Mostrar overlay con animación
  overlay.classList.add('show');
  document.getElementById('mainScroll').scrollTo({top:0, behavior:'smooth'});

  // Barra de countdown animada
  cntBar.style.animation = 'none';
  void cntBar.offsetWidth; // reflow
  cntBar.style.animation = 'countdown-shrink 5s linear forwards';

  // Cerrar automáticamente a los 5s
  idOverlayTimer = setTimeout(() => cerrarIdOverlay(), 5000);
}

function cerrarIdOverlay() {
  if (idOverlayTimer) { clearTimeout(idOverlayTimer); idOverlayTimer = null; }
  const overlay = document.getElementById('idOverlay');
  overlay.classList.remove('show');
}


function setStatusUI(tipo, word, detail, icon) {
  const box = document.getElementById('statusBox');
  const w   = document.getElementById('statusWord');
  const d   = document.getElementById('statusDetail');
  const ic  = document.getElementById('statusIco');
  box.className = 'status-box ' + tipo;
  w.className   = 'stat-word ' + tipo; w.textContent = word;
  d.textContent = detail || '';
  ic.className  = 'stat-ico si-' + tipo;
  ic.innerHTML  = `<i class="fas ${icon}" style="font-size:14px"></i>`;
  // Scroll al top
  document.getElementById('mainScroll').scrollTo({top:0,behavior:'smooth'});
}

/* ── RENDERIZAR TARJETA — FUNCIÓN ORIGINAL EXACTA ── */
function renderizarTarjeta(data, dni){
  const c=document.getElementById("datos");
  if(!data.persona){ 
    c.innerHTML=`<div style="background:var(--bg);border:1px solid var(--border);border-radius:var(--rl);padding:20px;text-align:center;box-shadow:var(--sh)"><i class="fas fa-user-slash" style="font-size:28px;color:var(--muted2);margin-bottom:10px;display:block"></i><div style="font-weight:800">${dni}</div><div style="font-size:12px;color:var(--muted);margin-top:4px">DNI no encontrado</div></div>`; 
    return; 
  }
  let claseEst = "tag-vi";
  let est = data.persona.validacion ? data.persona.validacion.toUpperCase() : "VISITA";
  if(est.includes("AFILIADO")) claseEst = "tag-af";
  if(est.includes("PROCESO"))  claseEst = "tag-pr";

  const foto = data.foto 
    ? `<img src="${data.foto}" class="avatar-box" style="width:52px;height:60px;border-radius:10px;object-fit:cover;border:1px solid var(--border)">` 
    : `<div class="avatar-box"><i class="fas fa-user av-ph"></i></div>`;

  const telEmergencia = data.persona.med_te 
    ? `<a href="tel:${data.persona.med_te}" style="color:var(--err);text-decoration:none;font-weight:800"><i class="fas fa-phone"></i> ${data.persona.med_te}</a>` 
    : '---';

  c.innerHTML=`
  <div class="pcard">
    <div class="pcard-head">
      ${foto}
      <div style="flex:1">
        <div class="pcard-name">${data.persona.nombres}<br>${data.persona.apellidos}</div>
        <div class="pcard-co">${data.persona.empresa}</div>
        <div class="pcard-tag ${claseEst}"><i class="fas fa-check-circle" style="font-size:8px"></i> ${est}</div>
      </div>
    </div>
    <div class="pcard-grid">
      <div class="pcard-cell"><div class="pc-lbl">DNI</div><div class="pc-val mono">${data.persona.dni}</div></div>
      <div class="pcard-cell"><div class="pc-lbl">Empresa</div><div class="pc-val">${data.persona.empresa}</div></div>
      <div class="pcard-cell"><div class="pc-lbl">Área</div><div class="pc-val">${data.persona.area||'—'}</div></div>
      <div class="pcard-cell"><div class="pc-lbl">Cargo</div><div class="pc-val">${data.persona.cargo||'—'}</div></div>
    </div>
    <div class="pcard-trip">
      <div><div class="trip-lbl">Unidad · Placa</div><div class="trip-val">${data.persona.bus||'—'} · ${data.persona.placa||'—'}</div></div>
      <div class="trip-sep"></div>
      <div style="text-align:center"><div class="trip-lbl">Asiento</div><div class="trip-val" style="font-size:18px">${data.asiento||data.persona.asiento||'—'}</div></div>
      <div class="trip-sep"></div>
      <div style="text-align:right"><div class="trip-lbl">Destino</div><div class="trip-val">${data.destino||'—'}</div></div>
    </div>
    ${(data.persona.med_gs||data.persona.med_ce||data.persona.med_al)?`
    <div style="padding:12px 16px;border-top:1px solid var(--border)">
      <button class="btn-med" onclick="document.getElementById('medInfo_${dni}').classList.add('show');this.style.display='none'">
        <i class="fas fa-exclamation-triangle"></i> Ver datos médicos de emergencia
      </button>
      <div class="pcard-med" id="medInfo_${dni}">
        <div class="med-tit"><i class="fas fa-ambulance"></i> Datos de emergencia</div>
        <div class="med-grid">
          <div class="med-item"><label>Grupo Sanguíneo</label><span>${data.persona.med_gs||'---'}</span></div>
          <div class="med-item"><label>Tel. Emergencia</label><span>${telEmergencia}</span></div>
          <div class="med-item"><label>Alergias</label><span>${data.persona.med_al||'NINGUNA'}</span></div>
          <div class="med-item"><label>Enfermedades</label><span>${data.persona.med_en||'NINGUNA'}</span></div>
        </div>
        <div style="margin-top:6px;font-size:11px;color:var(--muted)">Contacto: ${data.persona.med_ce||'---'}</div>
      </div>
    </div>`:''}
  </div>`;
}

/* ── HISTORIAL — ADAPTADO AL NUEVO DISEÑO ── */
function renderHistorial() {
  const history = JSON.parse(localStorage.getItem('histBus') || '[]');
  const el = document.getElementById('historialList');
  if(!history.length) {
    el.innerHTML = `<div style="text-align:center;padding:16px 0;font-family:var(--fm);font-size:9px;color:var(--muted2);letter-spacing:0.1em;text-transform:uppercase">Sin registros aún</div>`;
    return;
  }
  el.innerHTML = history.map(item => {
    const cls = item.estado==='AUTORIZADO' ? 'ok' : item.estado==='BAJADA' ? 'warn' : 'err';
    return `<div class="hi">
      <div class="hi-bar ${cls}"></div>
      <div class="hi-main">
        <div class="hi-dni">${item.dni}</div>
        <div class="hi-name">${item.nombre}</div>
      </div>
      <div class="hi-right">
        <div class="hi-chip ${cls}">${item.estado}</div>
        <div class="hi-time">${item.hora}</div>
      </div>
    </div>`;
  }).join('');
}

function limpiarHistorial() { 
  localStorage.removeItem('histBus'); 
  renderHistorial(); 
}

function toggleHistorial() {
  const hd   = document.getElementById('histHd');
  const list = document.getElementById('historialList');
  const chev = document.getElementById('histChevron');
  const collapsed = list.classList.toggle('collapsed');
  hd.classList.toggle('collapsed', collapsed);
}

/* ── MODAL AGREGAR — FUNCIÓN ORIGINAL EXACTA ── */
function prepararModalAgregar(dni, caso, nombrePre = "") {
    document.getElementById('modalAgregar').classList.add('open');
    document.getElementById('modalAgregar').style.display='flex';
    document.getElementById('new_dni').value = dni;
    
    document.getElementById('new_bus').value = "";
    document.getElementById('new_destino').value = "";
    document.getElementById('new_asiento').value = ""; 
    document.getElementById('txtAsientoSeleccionado').innerText = "Ninguno seleccionado";
    document.getElementById('busMap').innerHTML = '<p style="grid-column:1/-1;text-align:center;color:var(--muted2);font-size:11px;padding:12px 0">Seleccione un Bus para ver asientos</p>';
    
    document.getElementById('new_nombres').value = "";
    document.getElementById('new_apellidos').value = "";
    document.getElementById('new_empresa').value = "";

    const boxPersonal = document.getElementById('extra_personal_fields');
    const title = document.getElementById('tituloAgregar');
    const sub   = document.getElementById('subtituloAgregar');

    if (caso === 'MANUAL' || caso === 'NO_EXISTE') {
        document.getElementById('new_dni').readOnly = (caso !== 'MANUAL'); 
        boxPersonal.style.display = 'flex'; 
        title.innerText = "Pasajero Nuevo / Externo";
        sub.innerText   = "Este DNI no existe en BD. Regístrelo completo.";
        if(caso === 'NO_EXISTE') playBeep(200, 400); 
    } 
    else if (caso === 'FALTA_VIAJE') {
        document.getElementById('new_dni').readOnly = true;
        boxPersonal.style.display = 'none'; 
        title.innerText = "Asignar Viaje a: " + nombrePre;
        sub.innerText   = "Personal validado en BD. Seleccione asiento.";
        playBeep(440, 200); 
    }
    else if (caso === 'ASIGNAR') {
        document.getElementById('new_dni').readOnly = false;
        boxPersonal.style.display = 'none';
        title.innerText = "Asignar Viaje";
        sub.innerText   = "Ingrese el DNI y seleccione bus y asiento.";
    }
}

/* ── ENVIAR MANUAL — FUNCIÓN ORIGINAL EXACTA ── */
async function enviarManual() {
    const d = document.getElementById('dniManual').value;
    if(d.length === 8) { 
      cerrarManual();
      document.getElementById("reader-wrapper").style.display="none"; 
      validarDNI(d); 
    }
}

/* ── MAPA ASIENTOS — FUNCIÓN ORIGINAL EXACTA ── */
async function cargarMapaAsientos() {
    const bus  = document.getElementById('new_bus').value;
    const tipo = document.getElementById('new_tipo').value;
    const mapDiv = document.getElementById('busMap');

    document.getElementById('new_asiento').value = "";
    document.getElementById('txtAsientoSeleccionado').innerText = "Ninguno seleccionado";

    if(!bus) {
        mapDiv.innerHTML = '<p style="grid-column:1/-1;text-align:center;color:var(--muted2);font-size:11px;padding:12px 0">Seleccione un Bus primero</p>';
        return;
    }
    mapDiv.innerHTML = '<p style="grid-column:1/-1;text-align:center;font-size:11px;color:var(--muted2);padding:12px 0"><i class="fas fa-spinner fa-spin"></i> Cargando...</p>';

    try {
        const formData = new FormData();
        formData.append('bus', bus);
        formData.append('tipo', tipo);
        
        const res = await fetch('ver_asientos.php', { method: 'POST', body: formData });
        const ocupados = await res.json(); 
        dibujarBus(ocupados);

    } catch(e) {
        mapDiv.innerHTML = '<p style="grid-column:1/-1;color:var(--err);text-align:center;font-size:11px;padding:12px 0">Error cargando mapa</p>';
    }
}

/* ── DIBUJAR BUS — FUNCIÓN ORIGINAL EXACTA ── */
function dibujarBus(ocupados) {
    const mapDiv = document.getElementById('busMap');
    mapDiv.innerHTML = '';

    for(let i=1; i<=30; i++) {
        const seat = document.createElement('div');
        seat.className = 'seat';
        seat.innerText = i;
        
        if(ocupados.includes(i)) {
            seat.classList.add('occupied');
        } else {
            seat.onclick = function() { selectSeat(i, seat); };
        }

        mapDiv.appendChild(seat);

        if (i % 2 === 0 && i % 4 !== 0) {
            const aisle = document.createElement('div');
            aisle.className = 'seat aisle';
            mapDiv.appendChild(aisle);
        }
    }
}

/* ── SELECT SEAT — FUNCIÓN ORIGINAL EXACTA ── */
function selectSeat(num, element) {
    document.querySelectorAll('.seat.selected').forEach(el => el.classList.remove('selected'));
    element.classList.add('selected');
    document.getElementById('new_asiento').value = num;
    document.getElementById('txtAsientoSeleccionado').innerText = "ASIENTO SELECCIONADO: " + num;
}

/* ── GUARDAR EXTRA — FUNCIÓN ORIGINAL EXACTA ── */
async function guardarExtra() {
    const dni     = document.getElementById('new_dni').value;
    const bus     = document.getElementById('new_bus').value;
    const tipo    = document.getElementById('new_tipo').value;
    const destino = document.getElementById('new_destino').value.toUpperCase();
    const asiento = document.getElementById('new_asiento').value;
    
    const nombres   = document.getElementById('new_nombres').value.toUpperCase();
    const apellidos = document.getElementById('new_apellidos').value.toUpperCase();
    const empresa   = document.getElementById('new_empresa').value.toUpperCase();

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
        const data = await res.json(); 
        showLoader(false);
        
        if(data.success) {
            Swal.fire({icon: 'success', title: '¡Listo!', text: 'Asiento ' + asiento + ' reservado.', timer: 1500, showConfirmButton: false});
            cerrarAgregar(true);
            reactivarScanner();
        } else { 
          Swal.fire({icon: 'error', title: 'Error', text: data.message}); 
        }
    } catch(e) { 
      showLoader(false); 
      Swal.fire({icon: 'error', title: 'Error', text: 'Error de conexión'}); 
    }
}

/* ── PLAY BEEP — FUNCIÓN ORIGINAL EXACTA ── */
function playBeep(freq,dur){
  try{ 
    if(!audioCtx) audioCtx=new AudioContext();
    const osc=audioCtx.createOscillator(); const gain=audioCtx.createGain();
    osc.connect(gain); gain.connect(audioCtx.destination);
    osc.frequency.value=freq; gain.gain.value=.05; 
    osc.start(); osc.stop(audioCtx.currentTime+(dur/1000));
  }catch(e){}
}

/* ── CERRAR MODALES AL TOCAR FUERA ── */
document.querySelectorAll('.modal').forEach(m=>{
  m.addEventListener('click',function(e){ if(e.target===this){ this.classList.remove('open'); this.style.display='none'; } });
});
</script>

<!-- ── ID CARD OVERLAY ── -->
<div id="idOverlay" onclick="cerrarIdOverlay()">
  <div class="id-card" id="idCard" onclick="event.stopPropagation()">
    <div class="id-card-bar" id="idCardBar"></div>
    <div class="id-card-inner">
      <div class="id-handle"></div>
      <div id="idEstado" class="id-estado ok">
        <div class="id-estado-dot"></div>
        <span id="idEstadoTxt">Autorizado</span>
      </div>
      <div class="id-persona-row">
        <div class="id-avatar" id="idAvatar"><i class="fas fa-user"></i></div>
        <div>
          <div class="id-name" id="idNombre">—</div>
          <div class="id-company" id="idEmpresa">—</div>
          <div class="id-tag tag-af" id="idTag"></div>
        </div>
      </div>
      <div class="id-chips">
        <div class="id-chip">
          <div class="id-chip-lbl">DNI</div>
          <div class="id-chip-val" id="idDni">—</div>
        </div>
        <div class="id-chip">
          <div class="id-chip-lbl">Bus · Asiento</div>
          <div class="id-chip-val" id="idBus">—</div>
        </div>
        <div class="id-chip">
          <div class="id-chip-lbl">Destino</div>
          <div class="id-chip-val" id="idDestino">—</div>
        </div>
      </div>
      <div class="id-countdown"><div class="id-countdown-fill" id="idCountdown"></div></div>
    </div>
  </div>
</div>

<!-- ══ BOTTOM NAV (idéntico al dashboard, Buses activo) ══ -->
<nav class="bottom-nav">

  <a class="bn-item" href="dashboard.php">
    <div class="bn-ico">
      <svg viewBox="0 0 24 24">
        <rect x="3" y="3" width="7" height="7" rx="1.5"/>
        <rect x="14" y="3" width="7" height="7" rx="1.5"/>
        <rect x="3" y="14" width="7" height="7" rx="1.5"/>
        <rect x="14" y="14" width="7" height="7" rx="1.5"/>
      </svg>
    </div>
    <span class="bn-lbl">Inicio</span>
  </a>

  <a class="bn-item active" href="buses.php">
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

  <!-- Emergencia central -->
  <div class="bn-emg" id="btnEmgNav" onclick="confirmarEmergencia()">
    <div class="bn-emg-circle">
      <svg viewBox="0 0 24 24">
        <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
        <line x1="12" y1="9"  x2="12"   y2="13"/>
        <line x1="12" y1="17" x2="12.01" y2="17"/>
      </svg>
    </div>
    <span class="bn-emg-lbl">Alerta</span>
  </div>

  <button class="bn-item" onclick="abrirAgregarManual()">
    <div class="bn-ico">
      <svg viewBox="0 0 24 24">
        <circle cx="10" cy="8" r="4"/>
        <path d="M2 21c0-3.3 3.1-6 8-6s8 2.7 8 6"/>
        <line x1="19" y1="8" x2="19" y2="14"/>
        <line x1="16" y1="11" x2="22" y2="11"/>
      </svg>
    </div>
    <span class="bn-lbl">Nuevo</span>
  </button>

  <button class="bn-item" onclick="abrirAsignarViaje()">
    <div class="bn-ico">
      <svg viewBox="0 0 24 24">
        <rect x="2" y="5" width="20" height="14" rx="3"/>
        <path d="M2 9.5h20M8 5v4.5M16 5v4.5"/>
        <circle cx="6.5"  cy="15" r="1.5" fill="currentColor" stroke="none"/>
        <circle cx="17.5" cy="15" r="1.5" fill="currentColor" stroke="none"/>
      </svg>
    </div>
    <span class="bn-lbl">Asignar</span>
  </button>

</nav>

<script>
function confirmarEmergencia() {
  if('vibrate' in navigator) navigator.vibrate([50,25,100]);
  Swal.fire({
    icon: 'warning',
    title: '¿Reportar Alerta?',
    text: 'Se notificará al equipo de supervisión.',
    confirmButtonText: 'Sí, reportar',
    cancelButtonText: 'Cancelar',
    showCancelButton: true,
    confirmButtonColor: '#d92d4a',
    cancelButtonColor: '#9ba3b5',
  }).then(r => {
    if(r.isConfirmed) {
      window.location.href = 'dashboard.php#emergencia';
    }
  });
}
</script>
</body>
</html>