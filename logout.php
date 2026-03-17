<?php
session_start();
session_destroy(); // Borra la sesión del servidor
header("Location: index.php"); // Te manda de regreso al login
exit();
?>