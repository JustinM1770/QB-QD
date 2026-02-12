<?php
// Iniciar sesión
session_start();

// Destruir todas las variables de sesión
$_SESSION = array();

// Si se desea destruir la sesión completamente, borrar también la cookie de sesión
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finalmente, destruir la sesión
session_destroy();

// Redireccionar al login después de cerrar sesión
header("refresh:3;url=login.php");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cierre de sesión</title>
    <link rel="icon" type="image/x-icon" href="assets/img/logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }
        .logout-container {
            text-align: center;
            padding: 2rem;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            max-width: 500px;
            width: 90%;
        }
        .spinner {
            width: 3rem;
            height: 3rem;
            margin: 1rem auto;
        }
        .logout-message {
            font-size: 1.2rem;
            margin-bottom: 1.5rem;
        }
        @media (max-width: 576px) {
            .logout-container {
                padding: 1.5rem;
            }
            .logout-message {
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="logout-container">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Cargando...</span>
        </div>
        <h2 class="mb-3">Cerrando sesión</h2>
        <p class="logout-message">Has cerrado sesión correctamente. Serás redirigido al inicio de sesión en unos segundos.</p>
        <div class="progress">
            <div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 100%"></div>
        </div>
    </div>
</body>
</html>
