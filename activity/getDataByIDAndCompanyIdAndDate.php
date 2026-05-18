<?php
require_once "../config.php";

header('Cache-Control: no-cache, must-revalidate');

if (!isset($_GET['id'], $_GET['compnayID'], $_GET['date'])) {
    echo json_encode(["statusCode" => 0, "status" => false, "data" => [], "activity" => [], "errorMsg" => "Missing parameters"]);
    exit;
}

try {
    $userID   = (int)$_GET['id'];        // from mine: correct int binding
    $branchID = (int)$_GET['compnayID']; // from mine: correct int binding
    $date     = $_GET['date'];

    $stmt = mysqli_prepare($link,
        "SELECT act.*, s.time_in AS schedule_time_in, s.time_out AS schedule_time_out
         FROM emp_activity act
         INNER JOIN employees emp ON emp.id = act.userID
         LEFT JOIN schedule_employees se ON se.emp_id = act.userID
         LEFT JOIN schedules          s  ON s.id = se.schedule_id
         WHERE emp.id = ? AND emp.EmployeApproved = 1
           AND act.Branch_id = ? AND DATE(act.createdAt) = DATE(?)
         LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'iis', $userID, $branchID, $date); // from mine: correct types
    mysqli_stmt_execute($stmt);
    $row = mysqli_stmt_get_result($stmt)->fetch_assoc();
    mysqli_stmt_close($stmt);

    if (!$row) {
        echo json_encode(["statusCode" => 0, "status" => false, "data" => [], "activity" => [], "errorMsg" => "No Data"]);
        exit;
    }

    // from 1.php: pre-compute timestamps once instead of inside every loop
    $timeInTs  = $row['schedule_time_in']  ? strtotime($row['schedule_time_in'])  : null;
    $timeOutTs = $row['schedule_time_out'] ? strtotime($row['schedule_time_out']) : null;
    unset($row['schedule_time_in'], $row['schedule_time_out']);

    // from 1.php: cast all fields to string — prevents Flutter type errors
    foreach ($row as $k => $v) {
        if ($v !== null) $row[$k] = (string)$v;
    }

    $emp_act = ['activityID' => $row['id'], 'date' => $row['createdAt']];

    if (!empty($row['inTime'])) {
        $list = json_decode($row['inTime'], true);
        if (is_array($list)) { // from 1.php: null safety check
            $emp_act['checkIn'] = [];
            foreach ($list as $time) {
                $emp_act['checkIn'][] = [
                    'inTime' => $time,
                    'msg'    => ($timeInTs && strtotime($time) > $timeInTs) ? 'Late' : 'On Time',
                ];
            }
        }
    }

    $breakIn = !empty($row['breakInTimes']) ? json_decode($row['breakInTimes'], true) : [];
    $emp_act['breakInTime'] = is_array($breakIn) // from 1.php: null safety check
        ? array_map(function ($t) { return date('H:i:s', strtotime($t)); }, $breakIn)
        : [];

    $breakOut = !empty($row['breakOutTimes']) ? json_decode($row['breakOutTimes'], true) : [];
    $emp_act['breakOutTime'] = is_array($breakOut) // from 1.php: null safety check
        ? array_map(function ($t) { return date('H:i:s', strtotime($t)); }, $breakOut)
        : [];

    if (!empty($row['outTime'])) {
        $list = json_decode($row['outTime'], true);
        if (is_array($list)) { // from 1.php: null safety check
            $createdDate = date('Y-m-d', strtotime($row['createdAt']));
            $emp_act['outTime'] = [];
            foreach ($list as $timeout) {
                $emp_act['outTime'][] = [
                    'outTime' => $timeout,
                    'date'    => $createdDate,
                    'msg'     => ($timeOutTs && strtotime($timeout) > $timeOutTs) ? 'Over Time' : 'On Time',
                ];
            }
        }
    }

    echo json_encode(["statusCode" => 0, "status" => true, "data" => $row, "activity" => $emp_act, "errorMsg" => "empty"]);

} catch (mysqli_sql_exception $e) {
    error_log("DB error in getDataByIDAndCompanyIdAndDate: " . $e->getMessage()); // from 1.php: hide internals
    echo json_encode(["statusCode" => 0, "status" => false, "errorMsg" => "Database error"]);
} catch (Exception $e) {
    error_log("Error in getDataByIDAndCompanyIdAndDate: " . $e->getMessage());
    echo json_encode(["statusCode" => 0, "status" => false, "errorMsg" => "Server error"]);
} finally {
    mysqli_close($link); // from 1.php: always closes connection
}
?>
