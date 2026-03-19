<?php
session_start();
require_once('../../assets/database.php');

// ─── Clear admin remember me cookie and DB token ──────────────────────────────
if (isset($_COOKIE['admin_remember_token'])) {
    $token = $_COOKIE['admin_remember_token'];

    $stmt = mysqli_prepare($savienojums,
        "DELETE FROM BU_remember_tokens WHERE token = ? AND user_type = 'admin'");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "s", $token);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    setcookie('admin_remember_token', '', time() - 3600, '/', '', false, true);
}

$_SESSION = array();

if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}
session_destroy();

header("Location: login.php");
exit();