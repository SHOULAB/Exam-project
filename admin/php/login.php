<?php
session_start();
require_once('../../assets/database.php');

// If already logged in, redirect to dashboard
if (isset($_SESSION['admin_id']) && isset($_SESSION['is_admin'])) {
    header("Location: index.php");
    exit();
}

$error = '';

// ─── Auto-login from remember me cookie ──────────────────────────────────────
if (!isset($_SESSION['admin_id']) && isset($_COOKIE['admin_remember_token'])) {
    $token = $_COOKIE['admin_remember_token'];

    $stmt = mysqli_prepare($savienojums,
        "SELECT a.id, a.username
         FROM BU_admins a
         JOIN BU_remember_tokens t ON t.user_id = a.id
         WHERE t.token = ? AND t.expires_at > NOW() AND t.user_type = 'admin' AND a.is_active = 1");

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "s", $token);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $admin = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);

        if ($admin) {
            $_SESSION['admin_id']       = $admin['id'];
            $_SESSION['admin_username'] = $admin['username']; // ← separate key
            $_SESSION['is_admin']       = true;

            // Refresh cookie for another 30 days
            setcookie('admin_remember_token', $token, time() + (30 * 24 * 60 * 60), '/', '', false, true);

            header("Location: index.php");
            exit();
        } else {
            // Token expired or invalid — clear the cookie
            setcookie('admin_remember_token', '', time() - 3600, '/');
        }
    }
}

// ─── Process login form ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username    = trim($_POST['username'] ?? '');
    $password    = $_POST['password'] ?? '';
    $remember_me = isset($_POST['remember_me']);

    if (empty($username) || empty($password)) {
        $error = 'Lūdzu aizpildiet visus laukus!';
    } else {
        $stmt = mysqli_prepare($savienojums, "SELECT id, username, password, email FROM BU_admins WHERE username = ? AND is_active = 1");
        mysqli_stmt_bind_param($stmt, "s", $username);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($result && mysqli_num_rows($result) === 1) {
            $admin = mysqli_fetch_assoc($result);

            if (password_verify($password, $admin['password'])) {
                $_SESSION['admin_id']       = $admin['id'];
                $_SESSION['admin_username'] = $admin['username']; // ← separate key
                $_SESSION['is_admin']       = true;

                // Update last login
                $update_stmt = mysqli_prepare($savienojums, "UPDATE BU_admins SET last_login = NOW() WHERE id = ?");
                mysqli_stmt_bind_param($update_stmt, "i", $admin['id']);
                mysqli_stmt_execute($update_stmt);
                mysqli_stmt_close($update_stmt);

                // ── Remember me ───────────────────────────────────────────────
                if ($remember_me) {
                    $token   = bin2hex(random_bytes(32));
                    $expires = date('Y-m-d H:i:s', time() + (30 * 24 * 60 * 60));

                    mysqli_query($savienojums,
                        "CREATE TABLE IF NOT EXISTS BU_remember_tokens (
                            id         INT AUTO_INCREMENT PRIMARY KEY,
                            user_id    INT NOT NULL,
                            user_type  ENUM('user', 'admin') NOT NULL DEFAULT 'user',
                            token      VARCHAR(64) NOT NULL UNIQUE,
                            expires_at DATETIME NOT NULL,
                            created_at DATETIME DEFAULT NOW()
                        )");

                    $del = mysqli_prepare($savienojums,
                        "DELETE FROM BU_remember_tokens WHERE user_id = ? AND user_type = 'admin'");
                    mysqli_stmt_bind_param($del, "i", $admin['id']);
                    mysqli_stmt_execute($del);
                    mysqli_stmt_close($del);

                    $ins = mysqli_prepare($savienojums,
                        "INSERT INTO BU_remember_tokens (user_id, user_type, token, expires_at)
                         VALUES (?, 'admin', ?, ?)");
                    mysqli_stmt_bind_param($ins, "iss", $admin['id'], $token, $expires);
                    mysqli_stmt_execute($ins);
                    mysqli_stmt_close($ins);

                    setcookie('admin_remember_token', $token, time() + (30 * 24 * 60 * 60), '/', '', false, true);
                }

                mysqli_stmt_close($stmt);
                header("Location: index.php");
                exit();
            } else {
                $error = 'Nepareizs lietotājvārds vai parole!';
            }
        } else {
            $error = 'Nepareizs lietotājvārds vai parole!';
        }

        mysqli_stmt_close($stmt);
    }
}
?>
<!DOCTYPE html>
<html lang="lv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Pieteikšanās - Budgetar</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="icon" href="../../assets/image/logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <img src="../../assets/image/logo.png" alt="Budgetar Logo" class="login-logo">
                <h1 class="login-title">Admin Panel</h1>
                <p class="login-subtitle">Piesakieties, lai pārvaldītu sistēmu</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" class="login-form">
                <div class="form-group">
                    <label for="username" class="form-label">Lietotājvārds</label>
                    <input
                        type="text"
                        id="username"
                        name="username"
                        class="form-input"
                        placeholder="Ievadiet lietotājvārdu"
                        required
                        autocomplete="username"
                    >
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">Parole</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="form-input"
                        placeholder="Ievadiet paroli"
                        required
                        autocomplete="current-password"
                    >
                </div>

                <div class="form-group" style="display: flex; align-items: center; gap: 0.5rem;">
                    <input
                        type="checkbox"
                        id="remember_me"
                        name="remember_me"
                        style="width: 1rem; height: 1rem; cursor: pointer;"
                    >
                    <label for="remember_me" class="form-label" style="margin: 0; cursor: pointer;">Atcerēties mani</label>
                </div>

                <button type="submit" class="btn btn-primary btn-full btn-large">
                    <i class="fa-solid fa-unlock-keyhole"></i> Pieteikties
                </button>
            </form>

            <div class="auth-footer">
                <a href="../../user/php/index.php" class="link">← Atpakaļ uz sākumlapu</a>
            </div>
        </div>
    </div>

    <script src="../js/script.js"></script>
</body>
</html>