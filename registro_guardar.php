<?php
$conn = mysqli_connect("localhost", "root", "", "mina");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Recibimos los datos, incluido el nuevo cargo
    $nombre = mysqli_real_escape_string($conn, $_POST['nombre']);
    $cargo_real = mysqli_real_escape_string($conn, $_POST['cargo_real']); // <--- NUEVO
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $pass = $_POST['password'];

    $pass_segura = password_hash($pass, PASSWORD_BCRYPT);

    // Insertamos el cargo_real en la base de datos
    $sql = "INSERT INTO usuarios (email, password, nombre, cargo_real, rol, estado, verificado) 
            VALUES ('$email', '$pass_segura', '$nombre', '$cargo_real', 'agente', 0, 1)";

    if (mysqli_query($conn, $sql)) {
        echo "<script>
            alert('Solicitud enviada correctamente. Espere la aprobación del Administrador.');
            window.location='index.php';
        </script>";
    } else {
        echo "<script>
            alert('Error: No se pudo completar el registro. Verifique si el correo ya existe.');
            window.history.back();
        </script>";
    }
}
?>