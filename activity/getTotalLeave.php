<?php
require_once "../config.php";

if (!isset($_GET['userID'], $_GET['companyID'])) {
    die(json_encode(["statusCode" => 0, "resp_status" => false, "errorMsg" => "Missing parameters"]));
}

try {
    $userID   = (int)$_GET['userID'];
    $branchID = (int)$_GET['companyID'];

    $stmt = mysqli_prepare($link,
        "SELECT id, emp_id AS userID, approvalTo, created_at, status, fromdate, todate, leaveReason, rejectedReason
         FROM leaves WHERE emp_id = ? AND Branch_id = ?");
    mysqli_stmt_bind_param($stmt, 'ii', $userID, $branchID);
    mysqli_stmt_execute($stmt);
    $rows = mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);

    if (empty($rows)) {
        echo json_encode(["statusCode" => 0, "resp_status" => false, "data" => [], "errorMsg" => "Check SQL Query"]);
        exit;
    }
    echo json_encode(["statusCode" => 0, "resp_status" => true, "data" => $rows, "errorMsg" => "empty"]);

} catch (mysqli_sql_exception $e) {
    echo json_encode(["statusCode" => 0, "resp_status" => false, "errorMsg" => "Database error: " . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(["statusCode" => 0, "resp_status" => false, "errorMsg" => $e->getMessage()]);
}

mysqli_close($link);
?>
