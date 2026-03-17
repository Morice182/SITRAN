<?php
session_start();
// ✅ CORRECCIÓN ERROR 500 APLICADA
require_once __DIR__ . "/config.php";
$conn = $mysqli;

// SEGURIDAD: Solo el administrador entra aquí
if (!isset($_SESSION['usuario']) || $_SESSION['rol'] !== 'administrador') {
    header("Location: dashboard.php");
    exit();
}

// Consultar todos los usuarios
$res = mysqli_query($conn, "SELECT * FROM usuarios ORDER BY estado ASC, nombre ASC");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="../assets/logo4.png"/>
    <title>Gestión de Usuarios - Hochschild</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root { --brandGold: #b8872b; --brandGray: #374151; }
        body { font-family: 'Segoe UI', sans-serif; background: #f4f6f8; margin: 20px; }
        .container { max-width: 900px; margin: auto; background: white; padding: 25px; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        
        /* Modificado sutilmente para acomodar el botón fantasma */
        h2 { color: var(--brandGray); border-bottom: 2px solid var(--brandGold); padding-bottom: 10px; display: flex; justify-content: space-between; align-items: center; } 
        
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { background: #f8fafc; text-align: left; padding: 12px; color: #64748b; font-size: 13px; text-transform: uppercase; }
        td { padding: 12px; border-bottom: 1px solid #eee; }
        .badge { padding: 5px 10px; border-radius: 20px; font-size: 11px; font-weight: bold; }
        .bg-pend { background: #fff3cd; color: #856404; }
        .bg-active { background: #d1e7dd; color: #0a58ca; }
        .btn { padding: 6px 12px; border: none; border-radius: 8px; cursor: pointer; text-decoration: none; font-size: 12px; }
        .btn-approve { background: var(--brandGold); color: white; }
        .btn-delete { background: #fee2e2; color: #dc2626; }
        .select-rol { padding: 5px; border-radius: 5px; border: 1px solid #ddd; }

        /* ESTILOS DEL MODAL FANTASMA */
        .modal-fantasma { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:1000; align-items:center; justify-content:center; backdrop-filter:blur(3px); }
        .modal-caja { background:white; padding:30px; border-radius:15px; width:90%; max-width:400px; box-shadow:0 10px 25px rgba(0,0,0,0.2); }
        .input-fantasma { width:100%; padding:10px; margin:5px 0 15px; border:1px solid #ddd; border-radius:5px; box-sizing:border-box; font-family:inherit; }
    </style>
</head>
<body>

<div class="container">
    <a href="dashboard.php" style="text-decoration: none; color: var(--brandGold);">← Volver al Dashboard</a>
    
    <h2>
        <span><i class="fas fa-users-cog"></i> Gestión de Usuarios</span>
        <button onclick="document.getElementById('modalNuevo').style.display='flex'" style="background:transparent; border:1px dashed var(--brandGold); color:var(--brandGold); padding:4px 10px; border-radius:5px; cursor:pointer; font-size:12px; transition:0.2s;" title="Añadir Nuevo Usuario"><i class="fas fa-plus"></i></button>
    </h2>

    <table>
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Correo</th>
                <th>Rol</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php while($row = mysqli_fetch_assoc($res)): ?>
            <tr>
                <td><b><?php echo htmlspecialchars($row['nombre']); ?></b></td>
                <td><?php echo htmlspecialchars($row['email']); ?></td>
                <td>
                    <form action="usuarios_procesar.php" method="POST" style="display:inline;">
                        <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                        <select name="nuevo_rol" class="select-rol" onchange="this.form.submit()">
                            <option value="agente" <?php if($row['rol']=='agente') echo 'selected'; ?>>Agente</option>
                            <option value="supervisor" <?php if($row['rol']=='supervisor') echo 'selected'; ?>>Supervisor</option>
                            <option value="administrador" <?php if($row['rol']=='administrador') echo 'selected'; ?>>Administrador</option>
                        </select>
                        <input type="hidden" name="accion" value="cambiar_rol">
                    </form>
                </td>
                <td>
                    <?php if($row['estado'] == 0): ?>
                        <span class="badge bg-pend">PENDIENTE</span>
                    <?php else: ?>
                        <span class="badge bg-active">ACTIVO</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if($row['estado'] == 0): ?>
                        <a href="usuarios_procesar.php?id=<?php echo $row['id']; ?>&accion=activar" class="btn btn-approve">Aprobar</a>
                    <?php endif; ?>
                    
                    <?php if($row['email'] !== $_SESSION['usuario']): // No dejar borrarse a sí mismo ?>
                        <a href="usuarios_procesar.php?id=<?php echo $row['id']; ?>&accion=eliminar" class="btn btn-delete" onclick="return confirm('¿Eliminar cuenta?')"><i class="fas fa-trash"></i></a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<div id="modalNuevo" class="modal-fantasma">
    <div class="modal-caja">
        <h3 style="color:var(--brandGray); margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px;">Añadir Nuevo Usuario</h3>
        <form action="usuarios_procesar.php" method="POST">
            <input type="hidden" name="accion" value="crear">
            
            <label style="font-size:10px; font-weight:bold; color:#666;">NOMBRE COMPLETO</label>
            <input type="text" name="nombre" required class="input-fantasma">

            <label style="font-size:10px; font-weight:bold; color:#666;">CORREO ELECTRÓNICO</label>
            <input type="email" name="email" required class="input-fantasma">

            <label style="font-size:10px; font-weight:bold; color:#666;">CONTRASEÑA</label>
            <input type="text" name="password" required class="input-fantasma" placeholder="Será encriptada automáticamente">

            <div style="display:flex; gap:10px;">
                <div style="flex:1">
                    <label style="font-size:10px; font-weight:bold; color:#666;">CARGO</label>
                    <input type="text" name="cargo_real" value="Colaborador" class="input-fantasma">
                </div>
                <div style="flex:1">
                    <label style="font-size:10px; font-weight:bold; color:#666;">ROL</label>
                    <select name="rol" class="input-fantasma" style="padding:9px;">
                        <option value="agente">Agente</option>
                        <option value="supervisor">Supervisor</option>
                        <option value="administrador">Administrador</option>
                    </select>
                </div>
            </div>

            <div style="display:flex; gap:10px; margin-top:10px;">
                <button type="button" onclick="document.getElementById('modalNuevo').style.display='none'" style="flex:1; padding:10px; background:#eee; border:none; border-radius:5px; cursor:pointer; font-weight:bold; color:#666;">Cancelar</button>
                <button type="submit" style="flex:1; padding:10px; background:var(--brandGold); border:none; border-radius:5px; cursor:pointer; font-weight:bold; color:white;">Crear Usuario</button>
            </div>
        </form>
    </div>
</div>

</body>
</html>