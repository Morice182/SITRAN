<?php
require __DIR__ . "/config.php";

$dni = trim($_GET["dni"] ?? "");
if ($dni === "") die("DNI vacío");

$stmt = $mysqli->prepare("DELETE FROM personal WHERE dni=?");
$stmt->bind_param("s",$dni);
$stmt->execute();
$stmt->close();

header("Location: personal.php");
exit;
