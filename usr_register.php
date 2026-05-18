<?php
require_once "config.php";

try {
    $rawJson = file_get_contents("php://input");
    $data    = json_decode($rawJson, true);

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
        throw new Exception("Invalid JSON");
    }

    foreach (['username', 'password', 'fullName', 'roleType', 'companyID', 'departmentID', 'deviceId'] as $field) {
        if (empty($data[$field])) {
            throw new Exception("Missing fields: $field");
        }
    }

    $email        = $data['username'];
    $companyID    = (int)$data['companyID'];
    $name         = $data['fullName'];
    $position     = $data['roleType'];
    $departmentID = (int)$data['departmentID'];
    $deviceId     = $data['deviceId'];
    $hashedPwd    = password_hash($data['password'], PASSWORD_BCRYPT);
    $token        = bin2hex(random_bytes(16));

    if ($position === 'Employee') {
        $deptStmt = mysqli_prepare($link, "SELECT status_id FROM departments WHERE id = ?");
        mysqli_stmt_bind_param($deptStmt, 'i', $departmentID);
        mysqli_stmt_execute($deptStmt);
        $dept = mysqli_stmt_get_result($deptStmt)->fetch_assoc();
        if (!$dept || $dept['status_id'] != 1) {
            throw new Exception("department not Active.");
        }
    }

    $chkStmt = mysqli_prepare($link, "SELECT id FROM employees WHERE email = ?");
    mysqli_stmt_bind_param($chkStmt, 's', $email);
    mysqli_stmt_execute($chkStmt);
    if (mysqli_stmt_get_result($chkStmt)->fetch_assoc()) {
        throw new Exception("Username already taken!");
    }

    $insStmt = mysqli_prepare($link,
        "INSERT INTO employees (email, Branch_id, departmentID, pin_code, deviceId, name, permissions, position, remember_token)
         VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?)");
    mysqli_stmt_bind_param($insStmt, 'siississ', $email, $companyID, $departmentID, $hashedPwd, $deviceId, $name, $position, $token);
    mysqli_stmt_execute($insStmt);

    echo json_encode(["statusCode" => 0, "status" => true, "data" => ["email" => $email, "name" => $name], "errorMsg" => null]);

} catch (mysqli_sql_exception $e) {
    echo json_encode(["statusCode" => 0, "status" => false, "errorMsg" => "Database error: " . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(["statusCode" => 0, "status" => false, "errorMsg" => $e->getMessage()]);
}

mysqli_close($link);
?>
