<?php
require_once "../config.php";

if (!isset($_GET['companyID'], $_GET['departmentID'])) {
    die(json_encode(["statusCode" => 0, "status" => false, "errorMsg" => "Missing parameters"]));
}

try {
    $userID   = (int)($_GET['userId'] ?? 0);
    $branchID = (int)$_GET['companyID'];
    $deptID   = (int)$_GET['departmentID'];
    $roleType = $_GET['roleType'] ?? '';

    if ($roleType === 'ADMIN') {
        $stmt = mysqli_prepare($link,
            "SELECT id, name AS fullName, email AS username, position AS roleType, created_at AS createdAt
             FROM employees WHERE departmentID = ? AND Branch_id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $deptID, $branchID);
    } else {
        $stmt = mysqli_prepare($link,
            "SELECT id, name AS fullName, email AS username, position AS roleType, created_at AS createdAt
             FROM employees WHERE departmentID = ? AND Branch_id = ? AND id = ?");
        mysqli_stmt_bind_param($stmt, 'iii', $deptID, $branchID, $userID);
    }

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
