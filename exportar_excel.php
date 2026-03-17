<?php
// Configuración para descargar como Excel
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=Manifiesto_" . date('Y-m-d_H-i') . ".xls");
header("Pragma: no-cache");
header("Expires: 0");

$conn = mysqli_connect("localhost", "root", "", "mina");
$conn->set_charset("utf8mb4");

// 1. RECIBIR DATOS
$bus_seleccionado = $_GET['bus'];
$fecha_filtro     = $_GET['fecha'];
$tipo_lista       = $_GET['tipo'];
$tabla_origen     = ($tipo_lista == 'subida') ? 'lista_subida' : 'lista_bajada';

// 2. INICIALIZAR VARIABLES
$datos = [
    'razon'=>'', 'ruc'=>'', 'dir'=>'', 'placa'=>'', 'origen'=>'', 'destino'=>'', 'hora'=>'', 'manifiesto'=>'',
    'c1'=>'', 'b1'=>'', 'c2'=>'', 'b2'=>'', 'c3'=>'', 'b3'=>'', 'c4'=>'', 'b4'=>'',
    'pp'=>'', 'pm'=>'', 'pc1'=>'', 'pb1'=>'', 'pc2'=>'', 'pb2'=>'', 'pc3'=>'', 'pb3'=>''
];

// 3. CONSULTAR CABECERA (Conductores Bus + Ploteo)
$q = mysqli_query($conn, "SELECT * FROM cabecera_viaje WHERE nombre_bus='$bus_seleccionado' AND fecha_viaje='$fecha_filtro' AND tipo_lista='$tipo_lista'");

if ($row = mysqli_fetch_assoc($q)) {
    // Generales
    $datos['razon'] = $row['razon_social_transporte'];
    $datos['ruc']   = $row['ruc_empresa'];
    $datos['dir']   = $row['direccion_empresa'];
    $datos['placa'] = $row['placa_rodaje'];
    $datos['manifiesto'] = $row['nro_manifiesto'];
    $datos['origen']= $row['origen'];
    $datos['destino']= $row['destino'];
    $datos['hora']  = substr($row['hora_salida'], 0, 5);
    
    // Conductores Bus (4 espacios)
    $datos['c1'] = $row['conductor_1']; $datos['b1'] = $row['brevete_1'];
    $datos['c2'] = $row['conductor_2']; $datos['b2'] = $row['brevete_2'];
    $datos['c3'] = $row['conductor_3']; $datos['b3'] = $row['brevete_3'];
    $datos['c4'] = $row['conductor_4']; $datos['b4'] = $row['brevete_4'];

    // Datos Ploteo
    $datos['pp'] = $row['ploteo_placa'];  $datos['pm'] = $row['ploteo_modelo'];
    $datos['pc1'] = $row['ploteo_c1'];    $datos['pb1'] = $row['ploteo_b1'];
    $datos['pc2'] = $row['ploteo_c2'];    $datos['pb2'] = $row['ploteo_b2'];
    $datos['pc3'] = $row['ploteo_c3'];    $datos['pb3'] = $row['ploteo_b3'];
}

// 4. CONSULTAR PASAJEROS
$res = mysqli_query($conn, "SELECT l.asiento, l.dni, p.apellidos, p.nombres, p.empresa, p.cargo 
                            FROM $tabla_origen l LEFT JOIN personal p ON l.dni = p.dni 
                            WHERE l.bus = '$bus_seleccionado' ORDER BY CAST(l.asiento AS UNSIGNED) ASC");
?>

<meta charset="utf-8">
<style>
    /* Estilos CSS para el Excel */
    .header-main { background-color: #ffffff; color: #000; font-size: 16px; font-weight: bold; text-align: center; border: none; }
    .header-sub { background-color: #ffffff; color: #000; font-weight: bold; text-align: center; border: none; }
    
    .label-gray { background-color: #e0e0e0; font-weight: bold; border: 1px solid #000; }
    .data-cell { background-color: #ffffff; border: 1px solid #000; }
    
    .header-dark { background-color: #333333; color: #ffffff; font-weight: bold; text-align: center; border: 1px solid #000; }
    .header-gold { background-color: #b8872b; color: #ffffff; font-weight: bold; text-align: center; border: 1px solid #000; }
    
    .text-center { text-align: center; }
    .text-left { text-align: left; }
    .text-red { color: red; font-weight: bold; }
</style>

<table border="1" cellpadding="3" cellspacing="0">
    
    <tr>
        <td colspan="5" class="header-main" height="30">MANIFIESTO DE PASAJEROS</td>
    </tr>
    <tr>
        <td colspan="5" class="header-sub"><?= $datos['razon'] ?> - RUC: <?= $datos['ruc'] ?></td>
    </tr>

    <tr>
        <td class="label-gray">N° MANIFIESTO:</td>
        <td colspan="4" class="data-cell text-red" align="right"><?= $datos['manifiesto'] ?></td>
    </tr>
    <tr>
        <td class="label-gray">FECHA:</td>
        <td colspan="2" class="data-cell"><?= date('d/m/Y', strtotime($fecha_filtro)) ?></td>
        <td class="label-gray">HORA SALIDA:</td>
        <td class="data-cell"><?= $datos['hora'] ?></td>
    </tr>
    <tr>
        <td class="label-gray">ORIGEN:</td>
        <td colspan="2" class="data-cell"><?= $datos['origen'] ?></td>
        <td class="label-gray">DESTINO:</td>
        <td class="data-cell"><?= $datos['destino'] ?></td>
    </tr>
    <tr>
        <td class="label-gray">UNIDAD:</td>
        <td colspan="2" class="data-cell"><b><?= $bus_seleccionado ?></b></td>
        <td class="label-gray">PLACA:</td>
        <td class="data-cell"><?= $datos['placa'] ?></td>
    </tr>
    <tr>
        <td class="label-gray">DIRECCIÓN:</td>
        <td colspan="4" class="data-cell"><?= $datos['dir'] ?></td>
    </tr>

    <tr><td colspan="5" style="border:none;"></td></tr>

    <tr>
        <th colspan="3" class="header-dark">CONDUCTORES BUS</th>
        <th colspan="2" class="header-dark">LICENCIA</th>
    </tr>
    <tr>
        <td class="label-gray">PILOTO:</td>
        <td colspan="2" class="data-cell"><?= $datos['c1'] ?></td>
        <td colspan="2" class="data-cell text-center"><?= $datos['b1'] ?></td>
    </tr>
    <tr>
        <td class="label-gray">COPILOTO:</td>
        <td colspan="2" class="data-cell"><?= $datos['c2'] ?></td>
        <td colspan="2" class="data-cell text-center"><?= $datos['b2'] ?></td>
    </tr>
    <tr>
        <td class="label-gray">RELEVO 1:</td>
        <td colspan="2" class="data-cell"><?= $datos['c3'] ?></td>
        <td colspan="2" class="data-cell text-center"><?= $datos['b3'] ?></td>
    </tr>
    <tr>
        <td class="label-gray">RELEVO 2:</td>
        <td colspan="2" class="data-cell"><?= $datos['c4'] ?></td>
        <td colspan="2" class="data-cell text-center"><?= $datos['b4'] ?></td>
    </tr>

    <tr><td colspan="5" style="border:none;"></td></tr>

    <tr>
        <th class="header-gold" width="40">N°</th>
        <th class="header-gold" width="250">APELLIDOS Y NOMBRES</th>
        <th class="header-gold" width="100">DNI</th>
        <th class="header-gold" width="200">EMPRESA</th>
        <th class="header-gold" width="150">CARGO</th>
    </tr>

    <?php 
    $total = 0;
    while ($p = mysqli_fetch_assoc($res)): 
        $total++;
    ?>
    <tr>
        <td class="data-cell text-center"><b><?= $p['asiento'] ?></b></td>
        <td class="data-cell"><?= $p['apellidos'] . " " . $p['nombres'] ?></td>
        <td class="data-cell text-center" style='mso-number-format:"@"'>
            <?= $p['dni'] ?>
        </td>
        <td class="data-cell text-center"><?= $p['empresa'] ?></td>
        <td class="data-cell text-center"><?= $p['cargo'] ?></td>
    </tr>
    <?php endwhile; ?>
    
    <tr>
        <td colspan="5" class="data-cell" align="right" style="background:#eee;">
            <b>TOTAL PASAJEROS: <?= $total ?></b>
        </td>
    </tr>

    <tr><td colspan="5" style="border:none;"></td></tr>

    <tr>
        <th colspan="5" class="header-dark" align="left">DATOS DE PLOTEO - UNIDAD EXTERNA</th>
    </tr>
    <tr>
        <td class="label-gray">PLACA PLOTEO:</td>
        <td colspan="4" class="data-cell"><b><?= $datos['pp'] ?></b></td>
    </tr>
    <tr>
        <td class="label-gray">MODELO:</td>
        <td colspan="4" class="data-cell"><?= $datos['pm'] ?></td>
    </tr>
    
    <tr>
        <td class="label-gray text-center">ROL</td>
        <td colspan="2" class="label-gray text-center">CONDUCTOR</td>
        <td colspan="2" class="label-gray text-center">LICENCIA</td>
    </tr>
    
    <tr>
        <td class="data-cell">PILOTO:</td>
        <td colspan="2" class="data-cell"><?= $datos['pc1'] ?></td>
        <td colspan="2" class="data-cell text-center"><?= $datos['pb1'] ?></td>
    </tr>
    <tr>
        <td class="data-cell">COPILOTO:</td>
        <td colspan="2" class="data-cell"><?= $datos['pc2'] ?></td>
        <td colspan="2" class="data-cell text-center"><?= $datos['pb2'] ?></td>
    </tr>
    <tr>
        <td class="data-cell">RELEVO:</td>
        <td colspan="2" class="data-cell"><?= $datos['pc3'] ?></td>
        <td colspan="2" class="data-cell text-center"><?= $datos['pb3'] ?></td>
    </tr>

</table>