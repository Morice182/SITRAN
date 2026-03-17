<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Registro | Hochschild Mining</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --h-gold: #c5a059;
            --h-dark: #1a1c1e;
            --white: #ffffff;
            --radius-md: 16px;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--h-dark);
            background-image: radial-gradient(circle at center, #2c2e30 0%, var(--h-dark) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
        }

        .bg-glow {
            position: fixed;
            width: 40vw;
            height: 40vw;
            background: var(--h-gold);
            filter: blur(150px);
            opacity: 0.1;
            z-index: -1;
            border-radius: 50%;
        }

        .login-card {
            background: var(--white);
            padding: 40px 32px;
            border-radius: 28px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            width: 90%;
            max-width: 400px;
            text-align: center;
            border-top: 6px solid var(--h-gold);
            animation: slideUp 0.7s ease-out;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(40px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .logo-container img { 
            max-width: 220px;
            height: auto;
            margin-bottom: 15px; 
        }

        h2 {
            color: var(--h-dark);
            font-size: 11px;
            margin-bottom: 35px;
            text-transform: uppercase;
            letter-spacing: 4px;
            font-weight: 800;
            opacity: 0.7;
        }

        .input-group {
            position: relative;
            margin-bottom: 18px;
        }
        
        .input-group i.main-icon {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--h-gold);
        }

        input {
            width: 100%;
            padding: 16px 45px 16px 52px;
            border: 1.5px solid #e2e8f0;
            border-radius: var(--radius-md);
            box-sizing: border-box;
            font-size: 16px;
            background-color: #f8fafc;
        }

        button {
            width: 100%;
            padding: 18px;
            background: var(--h-dark);
            border: none;
            color: var(--white);
            border-radius: var(--radius-md);
            font-size: 14px;
            font-weight: 700;
            text-transform: uppercase;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .footer-link {
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #f1f5f9;
        }

        .footer-link a {
            color: #64748b;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
        }
    </style>
</head>
<body>

    <div class="bg-glow" style="top: -10%; left: -10%;"></div>
    <div class="bg-glow" style="bottom: -10%; right: -10%;"></div>

    <div class="login-card">
        <div class="logo-container">
            <img src="http://localhost:8080/mina/assets/logo.png" alt="Hochschild Logo">
        </div>
        
        <h2>Registro de Usuario</h2>

        <form action="procesar_registro.php" method="POST">
            <div class="input-group">
                <i class="fas fa-user main-icon"></i>
                <input type="text" name="usuario" placeholder="Nombre completo" required>
            </div>

            <div class="input-group">
                <i class="fas fa-id-card main-icon"></i>
                <input type="text" name="dni" placeholder="Número de DNI" required>
            </div>

            <div class="input-group">
                <i class="fas fa-lock main-icon"></i>
                <input type="password" name="password" placeholder="Contraseña" required>
            </div>

            <button type="submit">Registrar y Finalizar</button>
        </form>

        <div class="footer-link">
            <a href="http://localhost:8080/mina/">
                <i class="fas fa-arrow-left"></i> Volver a la página principal
            </a>
        </div>
    </div>

</body>
</html>