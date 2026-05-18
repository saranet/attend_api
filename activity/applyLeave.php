<?php
require_once "../config.php";

try {
    $rawJson = file_get_contents("php://input");
    $data    = json_decode($rawJson, true);

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
        throw new Exception("Invalid JSON");
    }

    foreach (['userID', 'companyID', 'approvalTo', 'fromdate', 'todate', 'leaveReason', 'leaveType'] as $field) {
        if (empty($data[$field])) {
            throw new Exception("Missing fields: $field");
        }
    }

    $userID      = (int)$data['userID'];
    $companyID   = (int)$data['companyID'];
    $approvalTo  = (int)$data['approvalTo'];
    $fromDate    = $data['fromdate'];
    $toDate      = $data['todate'];
    $leaveReason = $data['leaveReason'];
    $leaveType   = $data['leaveType'];

    $userStmt = mysqli_prepare($link, "SELECT * FROM employees WHERE id = ? AND Branch_id = ?");
    mysqli_stmt_bind_param($userStmt, 'ii', $userID, $companyID);
    mysqli_stmt_execute($userStmt);
    $userRow = mysqli_stmt_get_result($userStmt)->fetch_assoc();
    if (!$userRow) {
        echo json_encode(["statusCode" => 0, "status" => false, "errorMsg" => "User not found"]);
        exit;
    }

    mysqli_begin_transaction($link);

    $updStmt = mysqli_prepare($link,
        "UPDATE employees SET totalLeavePending = totalLeavePending + 1 WHERE id = ? AND Branch_id = ?");
    mysqli_stmt_bind_param($updStmt, 'ii', $userID, $companyID);
    if (!mysqli_stmt_execute($updStmt)) {
        mysqli_rollback($link);
        echo json_encode(["statusCode" => 0, "status" => false, "errorMsg" => "Failed to update user leave count"]);
        exit;
    }

    $insStmt = mysqli_prepare($link,
        "INSERT INTO leaves (emp_id, Branch_id, approvalTo, status, leaveReason, fromDate, toDate, type)
         VALUES (?, ?, ?, 'Pending', ?, ?, ?, ?)");
    mysqli_stmt_bind_param($insStmt, 'iiissss', $userID, $companyID, $approvalTo, $leaveReason, $fromDate, $toDate, $leaveType);
    if (!mysqli_stmt_execute($insStmt)) {
        mysqli_rollback($link);
        echo json_encode(["statusCode" => 0, "status" => false, "errorMsg" => "Failed to save Data."]);
        exit;
    }

    $savedId = mysqli_insert_id($link);
    mysqli_commit($link);

    $selStmt = mysqli_prepare($link, "SELECT * FROM leaves WHERE id = ?");
    mysqli_stmt_bind_param($selStmt, 'i', $savedId);
    mysqli_stmt_execute($selStmt);
    $savedData = mysqli_stmt_get_result($selStmt)->fetch_assoc();
    $savedData['user'] = $userRow;

    echo json_encode(["statusCode" => 1, "status" => true, "data" => $savedData, "errorMsg" => null]);

} catch (mysqli_sql_exception $e) {
    mysqli_rollback($link);
    echo json_encode(["statusCode" => 0, "status" => false, "errorMsg" => "Database error: " . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(["statusCode" => 0, "status" => false, "errorMsg" => $e->getMessage()]);
}

mysqli_close($link);
?>
