<?php
/**
 * index.php — Formulario de Acceso · SITRAN
 */
session_start();
if (isset($_SESSION['usuario'])) {
    header("Location: dashboard.php");
    exit();
}

// Generar token CSRF si no existe
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Acceso | Hochschild Mining</title>
    <link rel="icon" type="image/png" href="assets/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --h-gold: #c5a059;
            --h-gold-dark: #a68241;
            --h-dark: #1a1c1e;
            --h-gray-bg: #f4f4f7;
            --white: #ffffff;
            --text-main: #334155;
            --radius-md: 16px;
        }

        body {
            font-family: 'Inter', 'Segoe UI', sans-serif;
            background-color: var(--h-dark);
            background-image:
                linear-gradient(rgba(26, 28, 30, 0.55), rgba(26, 28, 30, 0.65)),
                url('index/inmaculada.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
            overflow: hidden;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 48px 32px;
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6);
            width: 90%;
            max-width: 400px;
            text-align: center;
            position: relative;
            border-top: 5px solid var(--h-gold);
            animation: fadeIn 0.6s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .logo-h {
            height: 180px;
            width: auto;
            margin-bottom: 15px;
            object-fit: contain;
        }

        h2 {
            color: var(--h-dark);
            font-size: 13px;
            margin-bottom: 32px;
            text-transform: uppercase;
            letter-spacing: 3px;
            font-weight: 700;
            opacity: 0.8;
        }

        .input-group {
            position: relative;
            margin-bottom: 16px;
        }

        .input-group i {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--h-gold);
            font-size: 16px;
            transition: 0.3s;
        }

        input {
            width: 100%;
            padding: 16px 16px 16px 50px;
            border: 1px solid #e2e8f0;
            border-radius: var(--radius-md);
            box-sizing: border-box;
            font-size: 15px;
            outline: none;
            background-color: #f8fafc;
            color: var(--h-dark);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        input:focus {
            border-color: var(--h-gold);
            background-color: var(--white);
            box-shadow: 0 0 0 4px rgba(197, 160, 89, 0.1);
        }

        button {
            width: 100%;
            padding: 16px;
            background: var(--h-dark);
            border: none;
            color: var(--white);
            border-radius: var(--radius-md);
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            margin-top: 10px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            transition: all 0.3s;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        button:hover {
            background: #2c2e30;
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.3);
            border-bottom: 2px solid var(--h-gold);
        }

        button:active { transform: scale(0.97); }

        button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .error {
            color: #ffffff;
            font-size: 13px;
            margin-top: 24px;
            background: #ef4444;
            padding: 12px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            animation: shake 0.4s;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25%       { transform: translateX(-5px); }
            75%       { transform: translateX(5px); }
        }

        .links-container {
            margin-top: 32px;
            padding-top: 20px;
            border-top: 1px solid #f1f5f9;
        }

        .register-link {
            font-size: 13px;
            color: #64748b;
        }

        .register-link a {
            color: var(--h-gold);
            text-decoration: none;
            font-weight: 700;
            margin-left: 5px;
        }

        .register-link a:hover { text-decoration: underline; }

        .footer-text {
            position: absolute;
            bottom: -50px;
            left: 0; right: 0;
            color: rgba(255,255,255,0.8);
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 2px;
            text-shadow: 0 2px 5px rgba(0,0,0,0.8);
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="logos-header">
        <img src="assets/logo.png" alt="Hochschild Mining" class="logo-h">
    </div>

    <h2>Control de Operaciones</h2>

    <form action="auth.php" method="POST" id="loginForm">
        <!-- Token CSRF (necesario para que auth.php acepte el formulario) -->
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

        <div class="input-group">
            <i class="fas fa-user"></i>
            <input type="text" name="usuario" placeholder="Correo electrónico o DNI"
                   required autocomplete="username">
        </div>

        <div class="input-group">
            <i class="fas fa-lock"></i>
            <input type="password" name="pass" placeholder="Contraseña"
                   required autocomplete="current-password">
        </div>

        <button type="submit" id="btn-login">Entrar al Sistema</button>
    </form>

    <?php if (isset($_GET['error'])): ?>
        <div class="error">
            <i class="fas fa-exclamation-circle"></i>
            <span>
                <?php
                    switch ((int)$_GET['error']) {
                        case 1: echo "Credenciales incorrectas."; break;
                        case 2: echo "Acceso pendiente de aprobación."; break;
                        case 3: echo "Solicitud inválida, intenta de nuevo."; break;
                        default: echo "Error de acceso.";
                    }
                ?>
            </span>
        </div>
    <?php endif; ?>

    <div class="links-container">
        <div class="register-link">
            ¿Nuevo colaborador? <a href="registro.php">Solicitar acceso</a>
        </div>
    </div>

    <div class="footer-text">
        Gestión de Transporte y Personal &nbsp;·&nbsp; v2.5
    </div>
</div>

<script>
// Evitar doble submit
document.getElementById('loginForm').addEventListener('submit', function () {
    const btn = document.getElementById('btn-login');
    btn.textContent = 'Verificando…';
    btn.disabled = true;
});
</script>
</body>
</html>