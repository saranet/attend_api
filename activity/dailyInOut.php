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
    mysqli_stmt_close($checkStmt);

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
        mysqli_stmt_close($updStmt);
        echo json_encode(fetchActivityResponse($link, $activityID, $userID, $companyID, date('Y-m-d')));

    } else {
        $userStmt = mysqli_prepare($link,
            "SELECT id FROM employees WHERE id = ? AND Branch_id = ? AND EmployeApproved = 1");
        mysqli_stmt_bind_param($userStmt, 'ii', $userID, $companyID);
        mysqli_stmt_execute($userStmt);
        $validUser = mysqli_stmt_get_result($userStmt)->fetch_assoc();
        mysqli_stmt_close($userStmt);

        if (!$validUser) {
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
        mysqli_stmt_close($insStmt);
        echo json_encode(fetchActivityResponse($link, $newID, $userID, $companyID, date('Y-m-d')));
    }

} catch (mysqli_sql_exception $e) {
    error_log("DB error in dailyInOut: " . $e->getMessage());
    echo json_encode(["status" => false, "errorMsg" => "Database error"]);
} catch (Exception $e) {
    error_log("Error in dailyInOut: " . $e->getMessage());
    echo json_encode(["status" => false, "errorMsg" => "Server error"]);
} finally {
    mysqli_close($link);
}

function fetchActivityResponse($link, $activityID, $userID, $companyID, $date) {
    $response = ["statusCode" => 0, "status" => false, "data" => [], "activity" => [], "errorMsg" => "No Data"];

    $stmt = mysqli_prepare($link,
        "SELECT act.*, s.time_in AS schedule_time_in, s.time_out AS schedule_time_out
         FROM emp_activity act
         LEFT JOIN schedule_employees se ON se.emp_id = act.userID
         LEFT JOIN schedules          s  ON s.id = se.schedule_id
         WHERE act.id = ? AND act.userID = ? AND act.Branch_id = ? AND DATE(act.createdAt) = ?
         LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'iiis', $activityID, $userID, $companyID, $date);
    mysqli_stmt_execute($stmt);
    $row = mysqli_stmt_get_result($stmt)->fetch_assoc();
    mysqli_stmt_close($stmt);

    if (!$row) return $response;

    // Pre-compute timestamps once outside loops
    $timeInTs  = $row['schedule_time_in']  ? strtotime($row['schedule_time_in'])  : null;
    $timeOutTs = $row['schedule_time_out'] ? strtotime($row['schedule_time_out']) : null;
    unset($row['schedule_time_in'], $row['schedule_time_out']);

    // Cast all fields to string — prevents Flutter type errors
    foreach ($row as $k => $v) {
        if ($v !== null) $row[$k] = (string)$v;
    }

    $emp_act = ['activityID' => $row['id'], 'date' => $row['createdAt']];

    if (!empty($row['inTime'])) {
        $list = json_decode($row['inTime'], true);
        if (is_array($list)) {
            $emp_act['checkIn'] = [];
            foreach ($list as $t) {
                $emp_act['checkIn'][] = [
                    'inTime' => $t,
                    'msg'    => ($timeInTs && strtotime($t) > $timeInTs) ? 'Late' : 'On Time',
                ];
            }
        }
    }

    $breakIn = !empty($row['breakInTimes']) ? json_decode($row['breakInTimes'], true) : [];
    $emp_act['breakInTime'] = is_array($breakIn)
        ? array_map(function ($t) { return date('H:i:s', strtotime($t)); }, $breakIn)
        : [];

    $breakOut = !empty($row['breakOutTimes']) ? json_decode($row['breakOutTimes'], true) : [];
    $emp_act['breakOutTime'] = is_array($breakOut)
        ? array_map(function ($t) { return date('H:i:s', strtotime($t)); }, $breakOut)
        : [];

    if (!empty($row['outTime'])) {
        $list = json_decode($row['outTime'], true);
        if (is_array($list)) {
            $createdDate = date('Y-m-d', strtotime($row['createdAt']));
            $emp_act['outTime'] = [];
            foreach ($list as $t) {
                $emp_act['outTime'][] = [
                    'outTime' => $t,
                    'date'    => $createdDate,
                    'msg'     => ($timeOutTs && strtotime($t) > $timeOutTs) ? 'Over Time' : 'On Time',
                ];
            }
        }
    }

    $response['status']   = true;
    $response['data']     = $row;
    $response['activity'] = $emp_act;
    $response['errorMsg'] = 'empty';
    return $response;
}
?>
