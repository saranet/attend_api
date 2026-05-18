<?php
require_once "config.php";

if (!isset($_GET['username'], $_GET['oldpassword'], $_GET['newpassword'])) {
    die(json_encode(["statusCode" => 0, "status" => false, "errorMsg" => "Missing parameters"]));
}

try {
    $email       = $_GET['username'];
    $oldPassword = $_GET['oldpassword'];
    $newPassword = password_hash($_GET['newpassword'], PASSWORD_BCRYPT);

    $stmt = mysqli_prepare($link, "SELECT pin_code FROM employees WHERE email = ?");
    mysqli_stmt_bind_param($stmt, 's', $email);
    mysqli_stmt_execute($stmt);
    $user = mysqli_stmt_get_result($stmt)->fetch_assoc();

    if (!$user || !password_verify($oldPassword, $user['pin_code'])) {
        echo json_encode(["statusCode" => 0, "status" => false, "errorMsg" => "Check Employee Detials"]);
        exit;
    }

    $updStmt = mysqli_prepare($link,
        "UPDATE employees SET pin_code = ?, force_password_change = 0 WHERE email = ?");
    mysqli_stmt_bind_param($updStmt, 'ss', $newPassword, $email);
    mysqli_stmt_execute($updStmt);

    if (mysqli_affected_rows($link) > 0) {
        echo json_encode(["statusCode" => 0, "status" => true, "data" => "", "errorMsg" => "empty"]);
    } else {
        echo json_encode(["statusCode" => 0, "status" => false, "errorMsg" => "Update Failed"]);
    }

} catch (mysqli_sql_exception $e) {
    echo json_encode(["statusCode" => 0, "status" => false, "errorMsg" => "Database error: " . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(["statusCode" => 0, "status" => false, "errorMsg" => $e->getMessage()]);
}

mysqli_close($link);
?>
