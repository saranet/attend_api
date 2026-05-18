<?php
require_once "../config.php";

try {
    $rawJson = file_get_contents("php://input");
    $data    = json_decode($rawJson, true);

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
        throw new Exception("Invalid JSON");
    }

    foreach (['createdBy', 'SH_Name', 'inTime', 'outTime', 'workingDays', 'role'] as $field) {
        if (empty($data[$field])) {
            throw new Exception("Missing fields: $field");
        }
    }

    if (strtolower($data['role']) !== 'admin') {
        echo json_encode(["statusCode" => 0, "status" => false, "errorMsg" => "Please Check Premission: "]);
        exit;
    }

    $createdBy   = (int)$data['createdBy'];
    $shName      = $data['SH_Name'];
    $workingDays = json_encode($data['workingDays']);
    $inTime      = $data['inTime'];
    $outTime     = $data['outTime'];
    $employeeId  = (int)($data['teamID'] ?? 0);

    mysqli_begin_transaction($link);

    $schStmt = mysqli_prepare($link,
        "INSERT INTO schedules (slug, created_by, wrokingDays, time_out, time_in)
         VALUES (?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($schStmt, 'sisss', $shName, $createdBy, $workingDays, $outTime, $inTime);
    if (!mysqli_stmt_execute($schStmt)) {
        mysqli_rollback($link);
        throw new Exception("Failed to create schedule");
    }
    $shId = mysqli_insert_id($link);

    $empStmt = mysqli_prepare($link,
        "INSERT INTO schedule_employees (emp_id, schedule_id, created_by) VALUES (?, ?, ?)");
    mysqli_stmt_bind_param($empStmt, 'iii', $employeeId, $shId, $createdBy);
    if (!mysqli_stmt_execute($empStmt)) {
        mysqli_rollback($link);
        throw new Exception("Failed to assign schedule");
    }

    mysqli_commit($link);

    echo json_encode(["statusCode" => 0, "status" => true, "data" => ["schedule" => $shName], "errorMsg" => null]);

} catch (mysqli_sql_exception $e) {
    mysqli_rollback($link);
    echo json_encode(["statusCode" => 0, "status" => false, "errorMsg" => "Database error: " . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(["statusCode" => 0, "status" => false, "errorMsg" => $e->getMessage()]);
}

mysqli_close($link);
?>
