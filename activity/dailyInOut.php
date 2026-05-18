<?php
require_once "../config.php";

try {
    $rawInput = file_get_contents("php://input");
    $data     = json_decode($rawInput, true);

    if (!$data) {
        http_response_code(400);
        echo json_encode(["status" => false, "errorMsg" => "Invalid JSON"]);
        exit;
    }

    $now          = date('H:i:s');
    $activityID   = isset($data['activityID'])  ? (int)$data['activityID']          : 0;
    $userID       = isset($data['userID'])       ? (int)$data['userID']              : 0;
    $companyID    = isset($data['companyID'])    ? (int)$data['companyID']           : 0;
    $activityType = isset($data['activityType']) ? strtoupper($data['activityType']) : '';

    if (!$userID || !$companyID || !$activityType) {
        http_response_code(400);
        echo json_encode(["status" => false, "errorMsg" => "Missing required fields"]);
        exit;
    }

    if (!in_array($activityType, ['IN', 'OUT', 'BREAKIN', 'BREAKOUT'], true)) {
        http_response_code(400);
        echo json_encode(["status" => false, "errorMsg" => "Invalid activityType"]);
        exit;
    }

    $checkStmt = mysqli_prepare($link, "SELECT id FROM emp_activity WHERE id = ?");
    mysqli_stmt_bind_param($checkStmt, 'i', $activityID);
    mysqli_stmt_execute($checkStmt);
    $exists = mysqli_stmt_get_result($checkStmt)->fetch_assoc();

    if ($exists) {
        switch ($activityType) {
            case 'IN':       $col = 'inTime';        break;
            case 'OUT':      $col = 'outTime';       break;
            case 'BREAKIN':  $col = 'breakInTimes';  break;
            case 'BREAKOUT': $col = 'breakOutTimes'; break;
        }
        $updStmt = mysqli_prepare($link,
            "UPDATE emp_activity SET $col = JSON_ARRAY_APPEND(IFNULL($col, JSON_ARRAY()), '$', ?) WHERE id = ?");
        mysqli_stmt_bind_param($updStmt, 'si', $now, $activityID);
        mysqli_stmt_execute($updStmt);
        echo json_encode(fetchActivityResponse($link, $activityID, $userID, $companyID, date('Y-m-d')));

    } else {
        $userStmt = mysqli_prepare($link,
            "SELECT id FROM employees WHERE id = ? AND Branch_id = ? AND EmployeApproved = 1");
        mysqli_stmt_bind_param($userStmt, 'ii', $userID, $companyID);
        mysqli_stmt_execute($userStmt);
        if (!mysqli_stmt_get_result($userStmt)->fetch_assoc()) {
            echo json_encode(["status" => false, "errorMsg" => "Your account is not valid"]);
            exit;
        }

        if ($activityType === 'IN') {
            $insStmt = mysqli_prepare($link,
                "INSERT INTO emp_activity (userID, Branch_id, inTime, breakInTimes, breakOutTimes)
                 VALUES (?, ?, JSON_ARRAY(?), JSON_ARRAY(), JSON_ARRAY())");
            mysqli_stmt_bind_param($insStmt, 'iis', $userID, $companyID, $now);
        } elseif ($activityType === 'OUT') {
            $insStmt = mysqli_prepare($link,
                "INSERT INTO emp_activity (userID, Branch_id, outTime, breakInTimes, breakOutTimes)
                 VALUES (?, ?, JSON_ARRAY(?), JSON_ARRAY(), JSON_ARRAY())");
            mysqli_stmt_bind_param($insStmt, 'iis', $userID, $companyID, $now);
        } else {
            echo json_encode(["status" => false, "errorMsg" => "Insert failed"]);
            exit;
        }

        if (!mysqli_stmt_execute($insStmt)) {
            echo json_encode(["status" => false, "errorMsg" => "Insert failed"]);
            exit;
        }
        $newID = mysqli_insert_id($link);
        echo json_encode(fetchActivityResponse($link, $newID, $userID, $companyID, date('Y-m-d')));
    }

} catch (mysqli_sql_exception $e) {
    echo json_encode(["status" => false, "errorMsg" => "Database error: " . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(["status" => false, "errorMsg" => "Error: " . $e->getMessage()]);
}

function fetchActivityResponse($link, $activityID, $userID, $companyID, $date) {
    $response = ["statusCode" => 0, "status" => false, "data" => [], "activity" => [], "errorMsg" => "No Data"];

    // Merged query: activity + schedule in one JOIN (was 2 queries)
    $stmt = mysqli_prepare($link,
        "SELECT act.*, s.time_in, s.time_out
         FROM emp_activity act
         LEFT JOIN schedule_employees se ON se.emp_id = act.userID
         LEFT JOIN schedules          s  ON s.id = se.schedule_id
         WHERE act.id = ? AND act.userID = ? AND act.Branch_id = ? AND DATE(act.createdAt) = ?");
    mysqli_stmt_bind_param($stmt, 'iiis', $activityID, $userID, $companyID, $date);
    mysqli_stmt_execute($stmt);
    $row = mysqli_stmt_get_result($stmt)->fetch_assoc();

    if (!$row) return $response;

    $timeIn  = $row['time_in'];
    $timeOut = $row['time_out'];
    unset($row['time_in'], $row['time_out']);

    $emp_act = ['activityID' => $row['id'], 'date' => $row['createdAt']];

    if (!empty($row['inTime'])) {
        $emp_act['checkIn'] = array_map(function ($t) use ($timeIn) {
            return ['inTime' => $t, 'msg' => strtotime($t) > strtotime($timeIn) ? 'Late' : 'On Time'];
        }, json_decode($row['inTime'], true));
    }

    $emp_act['breakInTime'] = array_map(function ($t) {
        return date('H:i:s', strtotime($t));
    }, !empty($row['breakInTimes']) ? json_decode($row['breakInTimes'], true) : []);

    $emp_act['breakOutTime'] = array_map(function ($t) {
        return date('H:i:s', strtotime($t));
    }, !empty($row['breakOutTimes']) ? json_decode($row['breakOutTimes'], true) : []);

    if (!empty($row['outTime'])) {
        $emp_act['outTime'] = array_map(function ($t) use ($timeOut, $row) {
            return [
                'outTime' => $t,
                'date'    => date('Y-m-d', strtotime($row['createdAt'])),
                'msg'     => strtotime($t) > strtotime($timeOut) ? 'Over Time' : 'On Time',
            ];
        }, json_decode($row['outTime'], true));
    }

    $response['status']   = true;
    $response['data']     = $row;
    $response['activity'] = $emp_act;
    $response['errorMsg'] = 'empty';
    return $response;
}

mysqli_close($link);
?>
