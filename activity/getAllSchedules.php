<?php
require_once "../config.php";

if (!isset($_GET['userID'], $_GET['companyID'], $_GET['roleType'], $_GET['departmentID'])) {
    die(json_encode(["statusCode" => 0, "resp_status" => false, "errorMsg" => "Missing parameters"]));
}

try {
    $userID   = (int)$_GET['userID'];
    $branchID = (int)$_GET['companyID'];
    $roleCode = $_GET['roleType'];
    $deptID   = (int)$_GET['departmentID'];
    $myData   = ($_GET['mySchedule'] ?? 'false') === 'true';

    $selectCols = "SELECT s.id AS schedule_id, s.slug, s.time_in, s.time_out, s.wrokingDays,
                          s.created_by, s.created_at,
                          JSON_ARRAYAGG(e.id)   AS emp_id,
                          JSON_ARRAYAGG(e.name) AS emp_name,
                          c.name                AS created_by
                   FROM schedules s
                   LEFT JOIN schedule_employees se ON se.schedule_id = s.id
                   LEFT JOIN employees e           ON e.id  = se.emp_id
                   LEFT JOIN employees c           ON c.id  = s.created_by";

    if ($roleCode === 'ADMIN') {
        $inOrNot = $myData ? "IN" : "NOT IN";
        $stmt = mysqli_prepare($link,
            "$selectCols
             WHERE e.Branch_id = ? AND e.departmentID = ?
               AND s.id $inOrNot (SELECT schedule_id FROM schedule_employees WHERE emp_id = ?)
             GROUP BY s.id");
        mysqli_stmt_bind_param($stmt, 'iii', $branchID, $deptID, $userID);
    } else {
        $stmt = mysqli_prepare($link,
            "$selectCols
             WHERE e.Branch_id = ? AND e.id = ? AND e.departmentID = ?
             GROUP BY s.id");
        mysqli_stmt_bind_param($stmt, 'iii', $branchID, $userID, $deptID);
    }

    mysqli_stmt_execute($stmt);
    $schedules = mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);

    if (empty($schedules)) {
        echo json_encode(["statusCode" => 0, "resp_status" => false, "errorMsg" => "No Data"]);
        exit;
    }
    echo json_encode(["statusCode" => 0, "resp_status" => true, "data" => $schedules, "errorMsg" => "empty"]);

} catch (mysqli_sql_exception $e) {
    echo json_encode(["statusCode" => 0, "resp_status" => false, "errorMsg" => "Database error: " . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(["statusCode" => 0, "resp_status" => false, "errorMsg" => $e->getMessage()]);
}

mysqli_close($link);
?>
