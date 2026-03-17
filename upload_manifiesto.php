<?php
header('Content-Type: application/json');

// Recibir datos
$tipo = isset($_POST['tipo']) ? $_POST['tipo'] : 'general'; // subida o bajada
$bus = isset($_POST['bus']) ? preg_replace('/[^A-Za-z0-9]/', '', $_POST['bus']) : 'desconocido';

// Carpeta donde se guardará (crea la carpeta si no existe)
$uploadDir = 'uploads/manifiestos/' . $tipo . '/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

if (isset($_FILES['file'])) {
    $extension = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
    // Nombre del archivo: FECHA_HORA_UNIDAD.ext
    $fileName = date('Ymd_His') . '_' . $bus . '.' . $extension;
    $targetPath = $uploadDir . $fileName;

    if (move_uploaded_file($_FILES['file']['tmp_name'], $targetPath)) {
        // ÉXITO
        echo json_encode(['success' => true, 'message' => 'Archivo guardado correctamente']);
    } else {
        // ERROR AL MOVER
        echo json_encode(['success' => false, 'message' => 'Error al mover el archivo']);
    }
} else {
    // NO HAY ARCHIVO
    echo json_encode(['success' => false, 'message' => 'No se recibió ningún archivo']);
}
?>