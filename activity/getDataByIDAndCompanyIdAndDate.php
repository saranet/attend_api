<?php
require_once "../config.php";

if (!isset($_GET['id'], $_GET['compnayID'], $_GET['date'])) {
    die(json_encode(["statusCode" => 0, "status" => false, "data" => [], "activity" => [], "errorMsg" => "Missing parameters"]));
}

try {
    $userID   = (int)$_GET['id'];
    $branchID = (int)$_GET['compnayID'];
    $date     = $_GET['date'];

    // Merged query: activity + schedule in one JOIN (was 2 queries)
    $stmt = mysqli_prepare($link,
        "SELECT act.*, s.time_in, s.time_out
         FROM emp_activity act
         JOIN employees emp ON emp.id = act.userID
         LEFT JOIN schedule_employees se ON se.emp_id = act.userID
         LEFT JOIN schedules          s  ON s.id = se.schedule_id
         WHERE emp.id = ? AND emp.EmployeApproved = 1
           AND act.Branch_id = ? AND DATE(act.createdAt) = DATE(?)");
    mysqli_stmt_bind_param($stmt, 'iis', $userID, $branchID, $date);
    mysqli_stmt_execute($stmt);
    $row = mysqli_stmt_get_result($stmt)->fetch_assoc();

    if (!$row) {
        echo json_encode(["statusCode" => 0, "status" => false, "data" => [], "activity" => [], "errorMsg" => "No Data"]);
        exit;
    }

    $timeIn  = $row['time_in'];
    $timeOut = $row['time_out'];
    unset($row['time_in'], $row['time_out']);

    $emp_act = ['activityID' => $row['id'], 'date' => $row['createdAt']];

    if (!empty($row['inTime'])) {
        $emp_act['checkIn'] = [];
        foreach (json_decode($row['inTime'], true) as $time) {
            $emp_act['checkIn'][] = [
                'inTime' => $time,
                'msg'    => strtotime($time) > strtotime($timeIn) ? 'Late' : 'On Time',
            ];
        }
    }

    $emp_act['breakInTime'] = array_map(function ($t) {
        return date('H:i:s', strtotime($t));
    }, !empty($row['breakInTimes']) ? json_decode($row['breakInTimes'], true) : []);

    $emp_act['breakOutTime'] = array_map(function ($t) {
        return date('H:i:s', strtotime($t));
    }, !empty($row['breakOutTimes']) ? json_decode($row['breakOutTimes'], true) : []);

    if (!empty($row['outTime'])) {
        $emp_act['outTime'] = [];
        foreach (json_decode($row['outTime'], true) as $timeout) {
            $emp_act['outTime'][] = [
                'outTime' => $timeout,
                'date'    => date('Y-m-d', strtotime($row['createdAt'])),
                'msg'     => strtotime($timeout) > strtotime($timeOut) ? 'Over Time' : 'On Time',
            ];
        }
    }

    echo json_encode(["statusCode" => 0, "status" => true, "data" => $row, "activity" => $emp_act, "errorMsg" => "empty"]);

} catch (mysqli_sql_exception $e) {
    echo json_encode(["statusCode" => 0, "status" => false, "errorMsg" => "Database error: " . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(["statusCode" => 0, "status" => false, "errorMsg" => $e->getMessage()]);
}

mysqli_close($link);
?>
