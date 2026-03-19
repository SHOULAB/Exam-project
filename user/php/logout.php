<?php
session_start();
require_once('../../assets/database.php');

// ─── Clear remember me cookie and DB token ────────────────────────────────────
if (isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];

    $stmt = mysqli_prepare($savienojums,
        "DELETE FROM BU_remember_tokens WHERE token = ?");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "s", $token);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    // Expire the cookie immediately
    setcookie('remember_token', '', time() - 3600, '/', '', false, true);
}

session_unset();
session_destroy();

header('Location: index.php');
exit();