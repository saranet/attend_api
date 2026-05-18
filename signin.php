<?php
require_once "config.php";

try {
    $rawJson = file_get_contents("php://input");
    $data    = json_decode($rawJson, true);

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
        throw new Exception("Invalid JSON");
    }

    foreach (['username', 'password', 'deviceId'] as $field) {
        if (empty($data[$field])) {
            throw new Exception("Missing fields: $field");
        }
    }

    $email    = $data['username'];
    $password = $data['password'];
    $deviceId = $data['deviceId'];

    // Query by email only — device check handled in PHP to support admin device reset
    $stmt = mysqli_prepare($link,
        "SELECT e.*,
                d.title AS departmentName,
                b.name  AS branchName,
                se.schedule_id AS schedule,
                s.wrokingDays, s.time_in, s.time_out,
                COUNT(l.id)                                              AS totalLeaveBalance,
                SUM(CASE WHEN l.status = 'Approved'  THEN 1 ELSE 0 END) AS totalLeaveApproved,
                SUM(CASE WHEN l.status = 'Cancelled' THEN 1 ELSE 0 END) AS totalLeaveCancelled,
                SUM(CASE WHEN l.status = 'Pending'   THEN 1 ELSE 0 END) AS totalLeavePending
         FROM employees e
         LEFT JOIN departments        d  ON d.id  = e.departmentID
         LEFT JOIN branches           b  ON b.id  = e.Branch_id
         LEFT JOIN schedule_employees se ON se.emp_id = e.id
         LEFT JOIN schedules          s  ON s.id  = se.schedule_id
         LEFT JOIN leaves             l  ON l.emp_id  = e.id
         WHERE e.email = ?
         GROUP BY e.id");
    mysqli_stmt_bind_param($stmt, 's', $email);
    mysqli_stmt_execute($stmt);
    $user = mysqli_stmt_get_result($stmt)->fetch_assoc();

    if ($user && password_verify($password, $user['pin_code'])) {
        // Device reset by admin: deviceId is empty → update it with the current device
        if (empty($user['deviceId'])) {
            $upd = mysqli_prepare($link, "UPDATE employees SET deviceId = ? WHERE id = ?");
            mysqli_stmt_bind_param($upd, 'si', $deviceId, $user['id']);
            mysqli_stmt_execute($upd);
            $user['deviceId'] = $deviceId;
        } elseif ($user['deviceId'] !== $deviceId) {
            echo json_encode(["statusCode" => 0, "status" => false, "errorMsg" => "Invalid email or password"]);
            exit;
        }

        if ($user['EmployeApproved'] != 1) {
            echo json_encode(["statusCode" => 0, "status" => false, "errorMsg" => "User Not Approved , Please Contact Adminstrator"]);
        } elseif (!$user['schedule']) {
            echo json_encode(["statusCode" => 0, "status" => false, "errorMsg" => "User Not Signed To Schedule "]);
        } else {
            unset($user['pin_code'], $user['remember_token']);
            if ($user['force_password_change']) {
                echo json_encode(["statusCode" => 0, "status" => true, "data" => $user, "errorMsg" => "empty", "message" => "Please set new password."]);
            } else {
                echo json_encode(["statusCode" => 0, "status" => true, "data" => $user, "errorMsg" => "empty"]);
            }
        }
    } else {
        echo json_encode(["statusCode" => 0, "status" => false, "errorMsg" => "Invalid email or password"]);
    }

} catch (mysqli_sql_exception $e) {
    echo json_encode(["statusCode" => 0, "status" => false, "errorMsg" => "Database error: " . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(["statusCode" => 0, "status" => false, "errorMsg" => $e->getMessage()]);
}

mysqli_close($link);
?>
