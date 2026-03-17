<?php
session_start(); 
require __DIR__ . "/config.php";

// SEGURIDAD: Si no hay sesión, redirigir al login
if (!isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit();
}

$rol_actual = $_SESSION['rol'] ?? 'agente'; 

// --- TU LÓGICA ORIGINAL (INTACTA) ---
$q = isset($_GET["q"]) ? trim($_GET["q"]) : "";

$edit = null;
if (isset($_GET["dni"]) && $_GET["dni"] !== "") {
    $dniEdit = trim($_GET["dni"]);
    $st = $mysqli->prepare("SELECT * FROM personal WHERE dni=? LIMIT 1");
    $st->bind_param("s", $dniEdit);
    $st->execute();
    $edit = $st->get_result()->fetch_assoc();
    $st->close();
}

// LÓGICA DE LISTADO (MEJORADA PARA BÚSQUEDA REAL)
$lista = [];
if ($rol_actual === 'administrador') {
    if ($q !== "") {
        // Busca en toda la BD
        $sql = "SELECT * FROM personal WHERE dni LIKE ? OR nombres LIKE ? OR apellidos LIKE ? OR empresa LIKE ? LIMIT 500";
        $stmt = $mysqli->prepare($sql);
        $lk = "%$q%";
        $stmt->bind_param("ssss", $lk, $lk, $lk, $lk);
    } else {
        // Carga inicial
        $sql = "SELECT * FROM personal ORDER BY apellidos ASC LIMIT 500";
        $stmt = $mysqli->prepare($sql);
    }
    $stmt->execute();
    $lista = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

if (!function_exists('h')) {
    function h($str) { return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8'); }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Gestión de Personal | Hochschild</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --h-gold: #b8872b;
            --h-gold-light: #d4a752;
            --h-dark: #111827;
            --h-lead: #374151;
            --h-bg: #f3f4f6;
            --h-card: #ffffff;
            --h-text: #1e293b;
            --h-muted: #64748b;
            --h-red: #be123c;
            --shadow: 0 10px 25px rgba(0,0,0,0.05);
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background-color: var(--h-bg);
            color: var(--h-text);
            margin: 0;
            padding-bottom: 40px;
            line-height: 1.5;
        }

        /* HEADER PREMIUM */
        .topbar {
            background: var(--h-card);
            border-bottom: 4px solid var(--h-gold);
            padding: 15px 20px;
            box-shadow: var(--shadow);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        .topbar-inner {
            max-width: 1100px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .topbar img { height: 40px; }

        .container {
            max-width: 1100px;
            margin: 20px auto;
            padding: 0 15px;
        }

        /* TARJETAS / CARDS */
        .card {
            background: var(--h-card);
            border-radius: 20px;
            padding: 25px;
            box-shadow: var(--shadow);
            margin-bottom: 25px;
            border: 1px solid rgba(0,0,0,0.05);
        }
        .card-title {
            font-size: 14px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--h-gold);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* FORMULARIO MEJORADO */
        .grid-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 15px;
        }
        .field-group { display: flex; flex-direction: column; gap: 5px; }
        label {
            font-size: 11px;
            font-weight: 800;
            color: var(--h-muted);
            text-transform: uppercase;
            margin-left: 5px;
        }
        input, select {
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 14px;
            background: #f8fafc;
            transition: 0.3s;
            color: var(--h-dark);
            width: 100%;
            box-sizing: border-box;
        }
        input:focus { border-color: var(--h-gold); outline: none; background: #fff; }

        .section-divider {
            grid-column: 1 / -1;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px dashed #cbd5e1;
            color: var(--h-red);
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-main {
            background: var(--h-dark);
            color: white;
            padding: 15px 30px;
            border-radius: 14px;
            border: none;
            font-weight: 800;
            text-transform: uppercase;
            cursor: pointer;
            transition: 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            border-bottom: 4px solid var(--h-gold);
            text-decoration: none; /* Para enlaces que parecen botones */
        }
        .btn-main:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .btn-excel { background: #10b981; border-bottom-color: #059669; }

        /* LISTA / TABLA */
        .toolbar { display: flex; gap: 10px; margin-bottom: 20px; align-items: center; }
        
        .search-container { 
            position: relative; 
            flex-grow: 1; /* Ocupa el espacio disponible */
        }
        .search-input {
            padding-left: 45px;
            border-radius: 50px;
            border: none;
            box-shadow: var(--shadow);
            height: 50px;
            width: 100%;
        }
        .search-icon { position: absolute; left: 18px; top: 17px; color: var(--h-muted); }
        
        /* Botón limpiar búsqueda */
        .search-clear { position:absolute; right:15px; top:15px; color:var(--h-muted); text-decoration:none; font-size:12px; }

        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th {
            text-align: left;
            padding: 15px;
            background: #f8fafc;
            font-size: 10px;
            text-transform: uppercase;
            color: var(--h-muted);
            letter-spacing: 1px;
        }
        td { padding: 15px; border-bottom: 1px solid #f1f5f9; font-size: 14px; }

        .badge {
            padding: 4px 10px;
            border-radius: 8px;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
        }
        .st-AFILIADO { background: #dcfce7; color: #166534; }
        .st-PROCESO { background: #fef9c3; color: #854d0e; }
        .st-VISITA { background: #e0f2fe; color: #0369a1; }

        /* INFO BOX PARA AGENTES */
        .info-box {
            text-align: center;
            color: var(--h-muted);
            font-size: 13px;
            padding: 40px;
            background: rgba(255,255,255,0.5);
            border-radius: 20px;
            border: 2px dashed #e2e8f0;
        }
        
        /* ALERTAS DE ESTADO */
        .alert-box {
            padding: 15px; border-radius: 12px; margin-bottom: 20px; 
            font-weight: bold; display: flex; align-items: center; gap: 10px;
        }
        .alert-success { background:#dcfce7; color:#166534; }
        .alert-error { background:#fee2e2; color:#991b1b; }

        @media (max-width: 768px) {
            .toolbar { flex-direction: column; align-items: stretch; }
            thead { display: none; }
            tr {
                display: block;
                background: white;
                margin-bottom: 15px;
                border-radius: 20px;
                padding: 15px;
                box-shadow: 0 4px 10px rgba(0,0,0,0.03);
                border-left: 5px solid var(--h-gold);
            }
            td {
                display: flex;
                justify-content: space-between;
                padding: 8px 0;
                border: none;
            }
            td::before {
                content: attr(data-label);
                font-weight: 800;
                color: var(--h-muted);
                text-transform: uppercase;
                font-size: 10px;
            }
        }
    </style>
</head>
<body>

<header class="topbar">
    <div class="topbar-inner">
        <a href="dashboard.php" style="color: var(--h-dark);"><i class="fas fa-arrow-left fa-lg"></i></a>
        <img src="assets/logo.png" alt="Hochschild">
        <div style="width: 30px;"></div> </div>
</header>

<div class="container">
    
    <?php if(isset($_GET['msg'])): ?>
        <div class="alert-box <?php echo ($_GET['msg']=='ok') ? 'alert-success' : 'alert-error'; ?>">
            <?php if($_GET['msg']=='ok'): ?>
                <i class="fas fa-check-circle"></i> Operación realizada con éxito.
            <?php else: ?>
                <i class="fas fa-exclamation-circle"></i> Ocurrió un error o el DNI ya existe.
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-title">
            <i class="fas fa-user-plus"></i> <?= $edit ? "Actualizar Colaborador" : "Registro de Personal" ?>
        </div>
        <form action="personal_guardar.php" method="POST">
            <div class="grid-form">
                <div class="field-group">
                    <label>DNI</label>
                    <input type="text" name="dni" maxlength="8" value="<?= h($edit['dni'] ?? '') ?>" <?= $edit ? 'readonly' : 'required' ?>>
                </div>
                <div class="field-group">
                    <label>Código Fotocheck</label>
                    <input type="text" name="codigo" value="<?= h($edit['codigo'] ?? '') ?>">
                </div>
                <div class="field-group">
                    <label>Nombres</label>
                    <input type="text" name="nombres" value="<?= h($edit['nombres'] ?? '') ?>" required>
                </div>
                <div class="field-group">
                    <label>Apellidos</label>
                    <input type="text" name="apellidos" value="<?= h($edit['apellidos'] ?? '') ?>" required>
                </div>
                <div class="field-group">
                    <label>Celular</label>
                    <input type="text" name="celular" value="<?= h($edit['celular'] ?? '') ?>">
                </div>
                <div class="field-group">
                    <label>Empresa</label>
                    <input type="text" name="empresa" value="<?= h($edit['empresa'] ?? '') ?>">
                </div>
                <div class="field-group">
                    <label>Área</label>
                    <input type="text" name="area" value="<?= h($edit['area'] ?? '') ?>">
                </div>
                <div class="field-group">
                    <label>Cargo</label>
                    <input type="text" name="cargo" value="<?= h($edit['cargo'] ?? '') ?>">
                </div>
                <div class="field-group">
                    <label>Estado de Validación</label>
                    <select name="estado_validacion">
                        <option value="AFILIADO" <?= ($edit['estado_validacion']??'') == 'AFILIADO' ? 'selected' : '' ?>>AFILIADO (VERDE)</option>
                        <option value="EN PROCESO DE AFILIACION" <?= ($edit['estado_validacion']??'') == 'EN PROCESO DE AFILIACION' ? 'selected' : '' ?>>EN PROCESO (AMARILLO)</option>
                        <option value="VISITA" <?= ($edit['estado_validacion']??'') == 'VISITA' ? 'selected' : '' ?>>VISITA (AZUL)</option>
                    </select>
                </div>

                <div class="section-divider">
                    <i class="fas fa-ambulance"></i> Información Médica y de Emergencia
                </div>

                <div class="field-group">
                    <label>Grupo Sanguíneo</label>
                    <select name="grupo_sanguineo">
                        <option value="">Seleccionar...</option>
                        <?php $gs = $edit['grupo_sanguineo'] ?? ''; ?>
                        <option value="O+" <?= $gs == 'O+' ? 'selected' : '' ?>>O+</option>
                        <option value="O-" <?= $gs == 'O-' ? 'selected' : '' ?>>O-</option>
                        <option value="A+" <?= $gs == 'A+' ? 'selected' : '' ?>>A+</option>
                        <option value="A-" <?= $gs == 'A-' ? 'selected' : '' ?>>A-</option>
                        <option value="B+" <?= $gs == 'B+' ? 'selected' : '' ?>>B+</option>
                        <option value="AB+" <?= $gs == 'AB+' ? 'selected' : '' ?>>AB+</option>
                    </select>
                </div>
                <div class="field-group">
                    <label>Contacto Emergencia</label>
                    <input type="text" name="contacto_emergencia" placeholder="Nombre de contacto" value="<?= h($edit['contacto_emergencia'] ?? '') ?>">
                </div>
                <div class="field-group">
                    <label>Teléfono Emergencia</label>
                    <input type="tel" name="telefono_emergencia" placeholder="Número" value="<?= h($edit['telefono_emergencia'] ?? '') ?>">
                </div>
                <div class="field-group">
                    <label>Enfermedades</label>
                    <input type="text" name="enfermedades" placeholder="Crónicas / Pre-existentes" value="<?= h($edit['enfermedades'] ?? '') ?>">
                </div>
                <div class="field-group">
                    <label>Alergias</label>
                    <input type="text" name="alergias" placeholder="Medicamentos / Alimentos" value="<?= h($edit['alergias'] ?? '') ?>">
                </div>
            </div>

            <div style="margin-top: 30px; display: flex; gap: 10px;">
                <button type="submit" class="btn-main">
                    <i class="fas fa-save"></i> <?= $edit ? "Guardar Cambios" : "Registrar Personal" ?>
                </button>
                <?php if($edit): ?>
                    <a href="personal.php" class="btn-main" style="background:#e2e8f0; color:#64748b; border-bottom-color:#cbd5e1; text-decoration:none; display:flex; align-items:center;">CANCELAR</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <?php if ($rol_actual === 'administrador'): ?>
        
        <div class="toolbar">
            <form method="GET" action="personal.php" class="search-container">
                <i class="fas fa-search search-icon"></i>
                <input type="text" name="q" class="search-input" placeholder="Buscar DNI, Nombre o Empresa..." value="<?= h($q) ?>">
                <?php if($q != ""): ?>
                    <a href="personal.php" class="search-clear"><i class="fas fa-times"></i> Limpiar</a>
                <?php endif; ?>
            </form>

            <a href="exportar_personal.php" class="btn-main btn-excel" style="padding: 12px 20px; font-size: 13px;">
                <i class="fas fa-file-excel"></i> Exportar
            </a>
        </div>

        <div class="card" style="padding: 10px;">
            <div class="table-responsive">
                <table id="personalTable">
                    <thead>
                        <tr>
                            <th>DNI / Fotocheck</th>
                            <th>Colaborador</th>
                            <th>Estado</th>
                            <th>Empresa / Área</th>
                            <th style="text-align: center;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($lista)): ?>
                            <tr><td colspan="5" style="text-align:center; padding:30px;">No se encontraron resultados</td></tr>
                        <?php else: ?>
                            <?php foreach($lista as $p): 
                                $est = $p['estado_validacion'] ?? 'VISITA';
                                $clase = (strpos($est, 'AFILIADO') !== false && strpos($est, 'PROCESO') === false) ? "st-AFILIADO" : (strpos($est, 'PROCESO') !== false ? "st-PROCESO" : "st-VISITA");
                            ?>
                            <tr>
                                <td data-label="DNI / Cód">
                                    <b style="font-family: monospace;"><?= h($p['dni']) ?></b><br>
                                    <small class="text-muted"><?= h($p['codigo']) ?></small>
                                </td>
                                <td data-label="Colaborador">
                                    <div style="font-weight: 700;"><?= h($p['apellidos'] . ", " . $p['nombres']) ?></div>
                                    <small style="color: var(--h-gold);"><?= h($p['celular']) ?></small>
                                </td>
                                <td data-label="Estado">
                                    <span class="badge <?= $clase ?>"><?= $est ?></span>
                                </td>
                                <td data-label="Empresa / Área">
                                    <div><?= h($p['empresa']) ?></div>
                                    <small class="text-muted"><?= h($p['area']) ?></small>
                                </td>
                                <td data-label="Acciones" style="text-align: center;">
                                    <a href="personal.php?dni=<?= $p['dni'] ?>" style="color: var(--h-gold); margin-right: 15px; font-size: 18px;"><i class="fas fa-edit"></i></a>
                                    <button onclick="confirmarBorrado('<?= $p['dni'] ?>')" style="border:none; background:none; cursor:pointer; color: var(--h-red); font-size: 18px;">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php else: ?>
        <div class="info-box">
            <i class="fas fa-database fa-2x" style="margin-bottom: 10px; display:block; opacity:0.3"></i>
            Base de datos protegida.<br>
            Solo los administradores tienen acceso al listado completo.
        </div>
    <?php endif; ?>

</div>

<script>
    function confirmarBorrado(dni) {
        Swal.fire({
            title: '¿Estás seguro?',
            text: "Se eliminará el colaborador y su historial. Esta acción no se puede deshacer.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#111827', // --h-dark
            cancelButtonColor: '#be123c',  // --h-red
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'personal_eliminar.php?dni=' + dni;
            }
        })
    }
</script>

</body>
</html>