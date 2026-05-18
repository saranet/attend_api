<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");

define("DB_SERVER",   "localhost");
define("DB_USERNAME", "Ama_sys@2025");
define("DB_PASSWORD", "Ama_Pas@2025");
define("DB_NAME",     "attenda");

date_default_timezone_set('Asia/Riyadh');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$link = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
?>
