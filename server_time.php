<?php
require_once "config.php";
echo json_encode([
    "status"          => true,
    "timezone_offset" => (int)date('Z'),   // UTC offset in seconds, e.g. 10800 for UTC+3
    "timezone"        => date_default_timezone_get(),
]);
