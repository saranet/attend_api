<?php
require_once "../config.php";

try {
    $stmt = mysqli_prepare($link, "SELECT latitude, longitude, distance FROM location_binds WHERE branch_id = 1");
    mysqli_stmt_execute($stmt);
    $rows = mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);

    if (empty($rows)) {
        echo json_encode(["statusCode" => 0, "status" => false, "errorMsg" => "Check SQL Query"]);
        exit;
    }
    echo json_encode(["statusCode" => 0, "status" => true, "data" => $rows, "errorMsg" => "empty"]);

} catch (mysqli_sql_exception $e) {
    echo json_encode(["statusCode" => 0, "status" => false, "errorMsg" => "Database error: " . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(["statusCode" => 0, "status" => false, "errorMsg" => $e->getMessage()]);
}

mysqli_close($link);
?>
