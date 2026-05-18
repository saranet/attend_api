<?php
require_once "../config.php";

if (!isset($_GET['id'], $_GET['compnayID'], $_GET['date'])) {
    die(json_encode(["statusCode" => 0, "status" => false, "data" => [], "errorMsg" => "Missing parameters"]));
}

try {
    $userID   = (int)$_GET['id'];
    $branchID = (int)$_GET['compnayID'];
    $date     = $_GET['date'];

    $stmt = mysqli_prepare($link,
        "SELECT act.* FROM emp_activity act
         JOIN employees emp ON emp.id = act.userID
         WHERE emp.id = ? AND emp.EmployeApproved = 1
           AND act.Branch_id = ? AND DATE(act.createdAt) = DATE(?)");
    mysqli_stmt_bind_param($stmt, 'iis', $userID, $branchID, $date);
    mysqli_stmt_execute($stmt);
    $rows = mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);

    if (empty($rows)) {
        echo json_encode(["statusCode" => 0, "status" => false, "data" => [], "errorMsg" => "Check SQL Query"]);
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
