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
    $myData   = ($_GET['myLeave'] ?? 'false') === 'true';

    if ($roleCode === 'ADMIN') {
        $empFilter = $myData ? "la.emp_id = ?" : "la.emp_id <> ?";
        $stmt = mysqli_prepare($link,
            "SELECT la.*,
                    JSON_OBJECT('id', m.id, 'fullName', m.name) AS approvalTo,
                    JSON_OBJECT('id', emp.id, 'fullName', emp.name) AS employee
             FROM leaves la
             LEFT JOIN employees m   ON la.approvalTo = m.id
             LEFT JOIN employees emp ON la.emp_id = emp.id
             WHERE la.Branch_id = ? AND $empFilter AND m.departmentID = ?");
        mysqli_stmt_bind_param($stmt, 'iii', $branchID, $userID, $deptID);
    } else {
        $stmt = mysqli_prepare($link,
            "SELECT la.*,
                    JSON_OBJECT('id', m.id, 'userName', m.name) AS approvalTo,
                    JSON_OBJECT('id', emp.id, 'fullName', emp.name) AS employee
             FROM leaves la
             LEFT JOIN employees m   ON la.approvalTo = m.id
             LEFT JOIN employees emp ON la.emp_id = emp.id
             WHERE la.emp_id = ? AND la.Branch_id = ? AND m.departmentID = ?");
        mysqli_stmt_bind_param($stmt, 'iii', $userID, $branchID, $deptID);
    }

    mysqli_stmt_execute($stmt);
    $leaves = mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);

    if (empty($leaves)) {
        echo json_encode(["statusCode" => 0, "resp_status" => false, "errorMsg" => "No Data"]);
        exit;
    }

    foreach ($leaves as &$leave) {
        $leave['approvalTo'] = !empty($leave['approvalTo']) ? json_decode($leave['approvalTo'], true) : null;
        $leave['employee']   = !empty($leave['employee'])   ? json_decode($leave['employee'],   true) : null;
    }
    unset($leave);

    $response = ['status' => true];
    if ($myData) {
        $bal = mysqli_prepare($link,
            "SELECT
                SUM(CASE WHEN type = 'PAID_LEAVE'            THEN 1 ELSE 0 END) AS paidLeaveBalance,
                SUM(CASE WHEN type = 'WFH'                   THEN 1 END)        AS totalWFHbalance,
                SUM(CASE WHEN type = 'CASUAL_AND_SICK_LEAVE' THEN 1 END)        AS casualAndSickLeaveBalance
             FROM leaves WHERE emp_id = ? AND Branch_id = ?");
        mysqli_stmt_bind_param($bal, 'ii', $userID, $branchID);
        mysqli_stmt_execute($bal);
        $balRow = mysqli_stmt_get_result($bal)->fetch_assoc();
        $response['data'] = [
            "paidLeaveBalance"          => (int)($balRow['paidLeaveBalance']          ?? 0),
            "totalWFHbalance"           => (int)($balRow['totalWFHbalance']           ?? 0),
            "casualAndSickLeaveBalance" => (int)($balRow['casualAndSickLeaveBalance'] ?? 0),
            "data"                      => $leaves,
        ];
    } else {
        $response['data'] = $leaves;
    }

    echo json_encode(["statusCode" => 0, "resp_status" => true, "data" => $response, "errorMsg" => "empty"]);

} catch (mysqli_sql_exception $e) {
    echo json_encode(["statusCode" => 0, "resp_status" => false, "errorMsg" => "Database error: " . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(["statusCode" => 0, "resp_status" => false, "errorMsg" => $e->getMessage()]);
}

mysqli_close($link);
?>
