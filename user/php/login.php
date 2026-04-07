<?php
// login.php - Login Page
session_start();

// If user is already logged in, redirect to calendar
if (isset($_SESSION['user_id'])) {
    header('Location: calendar.php');
    exit();
}

// Include database connection
require_once('../../assets/database.php');

$error = '';

// ─── Auto-login from remember me cookie ──────────────────────────────────────
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];

    $stmt = mysqli_prepare($savienojums,
        "SELECT u.id, u.username, u.email
         FROM BU_users u
         JOIN BU_remember_tokens t ON t.user_id = u.id
         WHERE t.token = ? AND t.expires_at > NOW()");

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "s", $token);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);

        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email']    = $user['email'];

            // Fetch user theme setting
            $theme_stmt = mysqli_prepare($savienojums, "SELECT setting_value FROM BU_user_settings WHERE user_id = ? AND setting_key = 'theme'");
            if ($theme_stmt) {
                mysqli_stmt_bind_param($theme_stmt, "i", $user['id']);
                mysqli_stmt_execute($theme_stmt);
                $theme_res = mysqli_stmt_get_result($theme_stmt);
                if ($theme_row = mysqli_fetch_assoc($theme_res)) {
                    $_SESSION['theme'] = $theme_row['setting_value'];
                } else {
                    $_SESSION['theme'] = 'dark';
                }
                mysqli_stmt_close($theme_stmt);
            }

            // Refresh cookie for another 30 days
            setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/', '', false, true);

            header('Location: calendar.php');
            exit();
        } else {
            // Token expired or invalid — clear the cookie
            setcookie('remember_token', '', time() - 3600, '/');
        }
    }
}

// ─── Process login form ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email       = trim($_POST['email']);
    $password    = $_POST['password'];
    $remember_me = isset($_POST['remember']);

    if (empty($email) || empty($password)) {
        $error = 'Lūdzu aizpildiet visus laukus!';
    } else {
        $stmt = mysqli_prepare($savienojums,
            "SELECT id, username, email, password FROM BU_users WHERE email = ?");

        if ($stmt === false) {
            $error = 'Sistēmas kļūda. Lūdzu mēģiniet vēlāk.';
        } else {
            mysqli_stmt_bind_param($stmt, "s", $email);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);

            if (mysqli_stmt_num_rows($stmt) === 1) {
                mysqli_stmt_bind_result($stmt, $user_id, $username, $user_email, $hashed_password);
                mysqli_stmt_fetch($stmt);

                if (password_verify($password, $hashed_password)) {
                    // ── Set session ───────────────────────────────────────────
                    $_SESSION['user_id'] = $user_id;
                    $_SESSION['username'] = $username;
                    $_SESSION['email']    = $user_email;

                    // Fetch user theme setting
                    $theme_stmt = mysqli_prepare($savienojums, "SELECT setting_value FROM BU_user_settings WHERE user_id = ? AND setting_key = 'theme'");
                    if ($theme_stmt) {
                        mysqli_stmt_bind_param($theme_stmt, "i", $user_id);
                        mysqli_stmt_execute($theme_stmt);
                        $theme_res = mysqli_stmt_get_result($theme_stmt);
                        if ($theme_row = mysqli_fetch_assoc($theme_res)) {
                            $_SESSION['theme'] = $theme_row['setting_value'];
                        } else {
                            $_SESSION['theme'] = 'dark';
                        }
                        mysqli_stmt_close($theme_stmt);
                    }

                    // ── Remember me cookie ────────────────────────────────────
                    if ($remember_me) {
                        $token   = bin2hex(random_bytes(32));
                        $expires = date('Y-m-d H:i:s', time() + (30 * 24 * 60 * 60));

                        // Ensure the tokens table exists
                        mysqli_query($savienojums,
                            "CREATE TABLE IF NOT EXISTS BU_remember_tokens (
                                id         INT AUTO_INCREMENT PRIMARY KEY,
                                user_id    INT NOT NULL,
                                token      VARCHAR(64) NOT NULL UNIQUE,
                                expires_at DATETIME NOT NULL,
                                created_at DATETIME DEFAULT NOW(),
                                FOREIGN KEY (user_id) REFERENCES BU_users(id) ON DELETE CASCADE
                            )");

                        // Remove any old tokens for this user (one active token per user)
                        $del = mysqli_prepare($savienojums,
                            "DELETE FROM BU_remember_tokens WHERE user_id = ?");
                        mysqli_stmt_bind_param($del, "i", $user_id);
                        mysqli_stmt_execute($del);
                        mysqli_stmt_close($del);

                        // Insert new token
                        $ins = mysqli_prepare($savienojums,
                            "INSERT INTO BU_remember_tokens (user_id, token, expires_at)
                             VALUES (?, ?, ?)");
                        mysqli_stmt_bind_param($ins, "iss", $user_id, $token, $expires);
                        mysqli_stmt_execute($ins);
                        mysqli_stmt_close($ins);

                        // Set cookie for 30 days (httponly for security)
                        setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/', '', false, true);
                    }

                    mysqli_stmt_close($stmt);
                    header('Location: calendar.php');
                    exit();
                } else {
                    $error = 'Nepareizs e-pasts vai parole!';
                }
            } else {
                $error = 'Nepareizs e-pasts vai parole!';
            }
            mysqli_stmt_close($stmt);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="lv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ieiet - Budgetiva</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="icon" href="../../assets/image/logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <a href="index.php" class="back-link">← Atpakaļ</a>
                <h1 class="auth-title">Laipni lūdzam atpakaļ!</h1>
                <p class="auth-subtitle">Ielogojieties, lai turpinātu</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form class="auth-form" id="loginForm" method="POST" action="">
                <div class="form-group">
                    <label for="email" class="form-label">E-pasts</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        class="form-input"
                        placeholder="tavs@epasts.lv"
                        required
                        value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                    >
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">Parole</label>
                    <div class="password-input-wrapper">
                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="form-input"
                            placeholder="••••••••"
                            required
                        >
                        <button type="button" class="password-toggle" onclick="togglePassword('password')">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="form-options">
                    <label class="checkbox-label">
                        <input type="checkbox" name="remember" id="remember" class="checkbox-input">
                        <span>Atcerēties mani</span>
                    </label>
                    <a href="#" class="link">Aizmirsi paroli?</a>
                </div>

                <button type="submit" class="btn btn-primary btn-full">
                    Ieiet
                </button>
            </form>

            <div class="auth-footer">
                <p>Nav konta? <a href="register.php" class="link">Reģistrēties</a></p>
            </div>
        </div>

        <div class="auth-visual">
            <div class="visual-content">
                <h2 class="visual-title">Tavi finanšu mērķi gaida tevi</h2>
                <div class="visual-features">
                    <div class="visual-feature">
                        <span class="visual-icon"><i class="fa-solid fa-chart-simple"></i></span>
                        <span>Detalizēti ienākumu un izdevumu pārskati</span>
                    </div>
                    <div class="visual-feature">
                        <span class="visual-icon"><i class="fa-solid fa-calendar"></i></span>
                        <span>Kalendāra skats visām transakcijām</span>
                    </div>
                    <div class="visual-feature">
                        <span class="visual-icon"><i class="fa-solid fa-sack-dollar"></i></span>
                        <span>Izseko savu budžetu reāllaikā</span>
                    </div>
                    <div class="visual-feature">
                        <span class="visual-icon"><i class="fa-solid fa-bullseye"></i></span>
                        <span>Sasniedz savus finanšu mērķus</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../js/script.js"></script>
</body>
</html>