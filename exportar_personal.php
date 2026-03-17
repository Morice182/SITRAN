<?php
session_start();
require __DIR__ . "/config.php";

if (!isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit();
}
if ($_SESSION['rol'] !== 'administrador') {
    header("Location: personal.php");
    exit();
}

// Filtros (mismos que personal.php)
$q              = isset($_GET['q'])       ? trim($_GET['q'])                  : '';
$filtro_guardia = isset($_GET['guardia']) ? strtoupper(trim($_GET['guardia'])) : '';

// WHERE dinámico
$where  = [];
$params = [];
$types  = '';

if ($q !== '') {
    $where[]  = "(dni LIKE ? OR nombres LIKE ? OR apellidos LIKE ? OR empresa LIKE ?)";
    $lk       = "%$q%";
    $params   = array_merge($params, [$lk, $lk, $lk, $lk]);
    $types   .= 'ssss';
}
if ($filtro_guardia !== '') {
    $where[]  = "UPPER(TRIM(GUARDIA)) = ?";
    $params[] = $filtro_guardia;
    $types   .= 's';
}

$sql = "SELECT * FROM personal";
if ($where) $sql .= " WHERE " . implode(" AND ", $where);
$sql .= " ORDER BY apellidos ASC";

$stmt = $mysqli->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$lista = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Nombre del archivo
$sufijo   = '';
if ($filtro_guardia !== '') $sufijo .= "_Guardia$filtro_guardia";
if ($q !== '')              $sufijo .= "_" . preg_replace('/[^a-zA-Z0-9]/', '', $q);
$filename = "Personal_Hochschild{$sufijo}_" . date('Y-m-d') . ".xls";

// Headers descarga
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

// Título del reporte
$titulo = "DIRECTORIO DE PERSONAL · HOCHSCHILD MINING";
if ($filtro_guardia !== '') $titulo .= "  —  GUARDIA $filtro_guardia";
if ($q !== '')              $titulo .= "  —  Búsqueda: " . strtoupper($q);
?>
<meta charset="utf-8">
<style>
body { font-family: Arial, sans-serif; font-size: 10px; }
.t-main { background-color: #111111; color: #ffffff; font-size: 13px; font-weight: bold; text-align: center; }
.t-sub  { background-color: #C49A2C; color: #ffffff; font-size: 10px; font-weight: bold; text-align: center; }
.t-meta { background-color: #F4F4F6; color: #6B6B6B; font-size: 9px; text-align: center; }
.h-col  { background-color: #1A1A1A; color: #ffffff; font-weight: bold; text-align: center; border: 1px solid #000; font-size: 10px; }
.h-gold { background-color: #C49A2C; color: #ffffff; font-weight: bold; text-align: center; border: 1px solid #C49A2C; font-size: 10px; }
.d-cell { background-color: #ffffff; border: 1px solid #d1d1d1; font-size: 10px; vertical-align: middle; }
.d-alt  { background-color: #F8F8FA; border: 1px solid #d1d1d1; font-size: 10px; vertical-align: middle; }
.d-mono { font-family: 'Courier New', monospace; text-align: center; }
.d-name { font-weight: bold; }
.st-afiliado { background-color: #DCFCE7; color: #166534; font-weight: bold; text-align: center; border: 1px solid #86efac; font-size: 9px; }
.st-proceso  { background-color: #FEF3C7; color: #92400E; font-weight: bold; text-align: center; border: 1px solid #fcd34d; font-size: 9px; }
.st-visita   { background-color: #DBEAFE; color: #1e40af; font-weight: bold; text-align: center; border: 1px solid #93c5fd; font-size: 9px; }
.grd-A  { background-color: #FDF6E3; color: #8A6A14; font-weight: bold; text-align: center; border: 1px solid #C49A2C; font-size: 9px; }
.grd-B  { background-color: #F0F0F4; color: #1A1A1A; font-weight: bold; text-align: center; border: 1px solid #484848; font-size: 9px; }
.grd-C  { background-color: #ECEDF0; color: #4A5568; font-weight: bold; text-align: center; border: 1px solid #B8BEC4; font-size: 9px; }
.grd-nil { color: #909090; text-align: center; border: 1px solid #d1d1d1; font-size: 9px; }
.total-row { background-color: #111111; color: #ffffff; font-weight: bold; text-align: right; font-size: 10px; border: 1px solid #000; }
</style>

<table border="0" cellpadding="4" cellspacing="0" width="100%">

    <tr>
        <td colspan="9" class="t-main" height="32"><?= $titulo ?></td>
    </tr>
    <tr>
        <td colspan="9" class="t-sub" height="18">
            Sistema Integral de Transporte &nbsp;&middot;&nbsp;
            Generado el <?= date('d/m/Y \a \l\a\s H:i') ?> &nbsp;&middot;&nbsp;
            <?= count($lista) ?> registros
        </td>
    </tr>
    <?php if ($filtro_guardia !== '' || $q !== ''): ?>
    <tr>
        <td colspan="9" class="t-meta" height="14">
            Filtros activos:
            <?= $filtro_guardia !== '' ? "Guardia $filtro_guardia" : '' ?>
            <?= ($filtro_guardia !== '' && $q !== '') ? ' &middot; ' : '' ?>
            <?= $q !== '' ? "B&uacute;squeda &laquo;$q&raquo;" : '' ?>
        </td>
    </tr>
    <?php endif; ?>
    <tr><td colspan="9" height="6" style="border:none;"></td></tr>

    <tr>
        <th class="h-col" width="15">#</th>
        <th class="h-col" width="75">DNI</th>
        <th class="h-col" width="190">APELLIDOS Y NOMBRES</th>
        <th class="h-col" width="110">EMPRESA</th>
        <th class="h-col" width="90">ÁREA</th>
        <th class="h-col" width="130">CARGO</th>
        <th class="h-col" width="75">CELULAR</th>
        <th class="h-gold" width="60">GUARDIA</th>
        <th class="h-col" width="85">ESTADO</th>
    </tr>

    <?php
    $n = 0;
    foreach ($lista as $p):
        $n++;
        $alt = ($n % 2 === 0) ? 'd-alt' : 'd-cell';

        // Estado
        $est = $p['estado_validacion'] ?? '';
        if (strpos($est, 'PROCESO') !== false)     { $est_label = 'EN PROCESO'; $est_class = 'st-proceso'; }
        elseif (strpos($est, 'VISITA') !== false)  { $est_label = 'VISITA';     $est_class = 'st-visita'; }
        elseif ($est !== '')                        { $est_label = 'AFILIADO';   $est_class = 'st-afiliado'; }
        else                                        { $est_label = '—';          $est_class = $alt; }

        // Guardia
        $grd = strtoupper(trim($p['GUARDIA'] ?? $p['guardia'] ?? ''));
        if ($grd === 'A')     { $grd_label = 'GUARDIA A'; $grd_class = 'grd-A'; }
        elseif ($grd === 'B') { $grd_label = 'GUARDIA B'; $grd_class = 'grd-B'; }
        elseif ($grd === 'C') { $grd_label = 'GUARDIA C'; $grd_class = 'grd-C'; }
        else                  { $grd_label = '—';         $grd_class = 'grd-nil'; }

        $nombre = trim(($p['apellidos'] ?? '') . ', ' . ($p['nombres'] ?? ''));
    ?>
    <tr>
        <td class="<?= $alt ?> d-mono"><?= $n ?></td>
        <td class="<?= $alt ?> d-mono" style='mso-number-format:"@"'><?= htmlspecialchars($p['dni'] ?? '') ?></td>
        <td class="<?= $alt ?> d-name"><?= htmlspecialchars($nombre) ?></td>
        <td class="<?= $alt ?>"><?= htmlspecialchars($p['empresa'] ?? '') ?></td>
        <td class="<?= $alt ?>"><?= htmlspecialchars($p['area']    ?? '') ?></td>
        <td class="<?= $alt ?>"><?= htmlspecialchars($p['cargo']   ?? '') ?></td>
        <td class="<?= $alt ?> d-mono"><?= htmlspecialchars($p['celular'] ?? '') ?></td>
        <td class="<?= $grd_class ?>"><?= $grd_label ?></td>
        <td class="<?= $est_class ?>"><?= $est_label ?></td>
    </tr>
    <?php endforeach; ?>

    <tr>
        <td colspan="9" class="total-row" height="20">
            TOTAL: <?= $n ?> colaborador<?= $n !== 1 ? 'es' : '' ?>
            <?= $filtro_guardia !== '' ? "  &middot;  Guardia $filtro_guardia" : '' ?>
            &nbsp;&nbsp;
        </td>
    </tr>

</table>
