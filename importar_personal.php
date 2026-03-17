<?php
require __DIR__ . "/config.php";

$archivo = __DIR__ . "/personal_lista.csv";

if (!file_exists($archivo)) die("Archivo no encontrado: $archivo");

function up($s){ return strtoupper(trim($s ?? "")); }

$row = 0;
$insertados = 0;
$actualizados = 0;

if (($handle = fopen($archivo, "r")) !== false) {
    while (($data = fgetcsv($handle, 0, "\t")) !== false) {
        $row++;
        if ($row == 1) continue; // cabecera

        $dni = trim($data[0]);
        if (!preg_match('/^\d{8}$/', $dni)) continue;

        $nombres   = up($data[1] ?? "");
        $apellidos = up($data[2] ?? "");
        $empresa   = up($data[3] ?? "");
        $area      = up($data[4] ?? "");
        $cargo     = up($data[5] ?? "");
        $codigo    = up($data[6] ?? "");
        $celular   = trim($data[7] ?? "");

        $grupo_sanguineo = null;
        $fecha_ingreso = null;

        // verificar
        $st = $mysqli->prepare("SELECT dni FROM personal WHERE dni=? LIMIT 1");
        $st->bind_param("s", $dni);
        $st->execute();
        $st->store_result();
        $existe = ($st->num_rows > 0);
        $st->close();

        if ($existe) {
            $stmt = $mysqli->prepare("
                UPDATE personal 
                SET nombres=?, apellidos=?, empresa=?, area=?, cargo=?, 
                    grupo_sanguineo=?, codigo=?, fecha_ingreso=?, celular=? 
                WHERE dni=?
            ");
            $stmt->bind_param(
                "ssssssssss",
                $nombres,$apellidos,$empresa,$area,$cargo,
                $grupo_sanguineo,$codigo,$fecha_ingreso,$celular,$dni
            );
            $stmt->execute();
            $stmt->close();
            $actualizados++;
        } else {
            $stmt = $mysqli->prepare("
                INSERT INTO personal 
                (dni,nombres,apellidos,empresa,area,cargo,
                 grupo_sanguineo,codigo,fecha_ingreso,celular)
                VALUES (?,?,?,?,?,?,?,?,?,?)
            ");
            $stmt->bind_param(
                "ssssssssss",
                $dni,$nombres,$apellidos,$empresa,$area,$cargo,
                $grupo_sanguineo,$codigo,$fecha_ingreso,$celular
            );
            $stmt->execute();
            $stmt->close();
            $insertados++;
        }
    }
    fclose($handle);
}

echo "Importación completada ✅ <br>
Insertados: $insertados <br>
Actualizados: $actualizados";
