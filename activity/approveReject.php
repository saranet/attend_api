<?php
require_once "../config.php";

try {
    $rawJson = file_get_contents("php://input");
    $input   = json_decode($rawJson, true);

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($input)) {
        throw new Exception("Invalid JSON");
    }

    foreach (["leaveID", "userID", "companyID", "leaveStatus", "employeeID"] as $field) {
        if (!isset($input[$field]) || $input[$field] === "") {
            echo json_encode(["statusCode" => 0, "status" => false, "errorMsg" => "Missing required field: $field"]);
            exit;
        }
    }

    $leaveID      = (int)$input['leaveID'];
    $userID       = (int)$input['userID'];
    $companyID    = (int)$input['companyID'];
    $leaveStatus  = strtoupper($input['leaveStatus']);
    $rejectReason = $input['rejectReason'] ?? "";
    $employeeID   = (int)$input['employeeID'];

    $userStmt = mysqli_prepare($link,
        "SELECT id FROM employees WHERE id = ? AND Branch_id = ? AND EmployeApproved = 1");
    mysqli_stmt_bind_param($userStmt, 'ii', $employeeID, $companyID);
    mysqli_stmt_execute($userStmt);
    if (!mysqli_stmt_get_result($userStmt)->fetch_assoc()) {
        echo json_encode(["statusCode" => 0, "status" => false, "errorMsg" => "User not found"]);
        exit;
    }

    if ($leaveStatus === "REJECTED" && empty($rejectReason)) {
        echo json_encode(["statusCode" => 0, "status" => false, "errorMsg" => "Please enter reject reason"]);
        exit;
    }

    $leaveStmt = mysqli_prepare($link, "SELECT * FROM leaves WHERE id = ? AND status = 'Pending'");
    mysqli_stmt_bind_param($leaveStmt, 'i', $leaveID);
    mysqli_stmt_execute($leaveStmt);
    $leaveData = mysqli_stmt_get_result($leaveStmt)->fetch_assoc();
    if (!$leaveData) {
        echo json_encode(["statusCode" => 0, "status" => false, "errorMsg" => "Invalid Leave Data "]);
        exit;
    }

    if ($leaveData['approvalTo'] != $userID) {
        echo json_encode(["statusCode" => 0, "status" => false, "errorMsg" => "You are not the right person to approve this."]);
        exit;
    }

    mysqli_begin_transaction($link);

    $updLeaveStmt = mysqli_prepare($link,
        "UPDATE leaves SET status = ?, rejectedReason = ?, updated_at = NOW() WHERE id = ?");
    mysqli_stmt_bind_param($updLeaveStmt, 'ssi', $leaveStatus, $rejectReason, $leaveID);
    if (!mysqli_stmt_execute($updLeaveStmt)) {
        mysqli_rollback($link);
        echo json_encode(["statusCode" => 0, "status" => false, "errorMsg" => "Failed to update leave"]);
        exit;
    }

    if ($leaveStatus === "APPROVED") {
        $updEmpStmt = mysqli_prepare($link,
            "UPDATE employees
             SET totalLeaveApproved = totalLeaveApproved + 1,
                 totalLeavePending  = IF(totalLeavePending > 0, totalLeavePending - 1, 0)
             WHERE id = ? AND Branch_id = ?");
    } else {
        $updEmpStmt = mysqli_prepare($link,
            "UPDATE employees
             SET totalLeaveCancelled = totalLeaveCancelled + 1,
                 totalLeavePending   = IF(totalLeavePending > 0, totalLeavePending - 1, 0)
             WHERE id = ? AND Branch_id = ?");
    }
    mysqli_stmt_bind_param($updEmpStmt, 'ii', $employeeID, $companyID);
    mysqli_stmt_execute($updEmpStmt);

    mysqli_commit($link);

    $selStmt = mysqli_prepare($link, "SELECT * FROM leaves WHERE id = ?");
    mysqli_stmt_bind_param($selStmt, 'i', $leaveID);
    mysqli_stmt_execute($selStmt);
    $updatedLeave = mysqli_stmt_get_result($selStmt)->fetch_assoc();

    echo json_encode(["statusCode" => 0, "status" => true, "data" => $updatedLeave, "errorMsg" => null]);

} catch (mysqli_sql_exception $e) {
    mysqli_rollback($link);
    echo json_encode(["statusCode" => 0, "status" => false, "errorMsg" => "Database error: " . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(["statusCode" => 0, "status" => false, "errorMsg" => $e->getMessage()]);
}

mysqli_close($link);
?>
