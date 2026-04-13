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

// ─── Language detection ───────────────────────────────────────────────────────
$_supported   = ['lv', 'en'];
$_browserLang = 'lv';
if (!empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
    $code = strtolower(substr(trim($_SERVER['HTTP_ACCEPT_LANGUAGE']), 0, 2));
    if (in_array($code, $_supported)) $_browserLang = $code;
}
$_traw = json_decode(file_get_contents(__DIR__ . '/translate.json'), true) ?? [];
$_t    = $_traw[$_browserLang] ?? $_traw['lv'];

$error = '';

// ─── Auto-login from remember me cookie ──────────────────────────────────────
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];

    $stmt = mysqli_prepare($savienojums,
        "SELECT u.id, u.username, u.email, u.role
         FROM BU_users u
         JOIN BU_remember_tokens t ON t.user_id = u.id
         WHERE t.token = ? AND t.expires_at > NOW() AND u.is_active = 1");

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
            $_SESSION['role']     = $user['role'] ?? 'user';

            // Update last login
            $ll = mysqli_prepare($savienojums, "UPDATE BU_users SET last_login = NOW() WHERE id = ?");
            if ($ll) { mysqli_stmt_bind_param($ll, "i", $user['id']); mysqli_stmt_execute($ll); mysqli_stmt_close($ll); }

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
        $error = $_t['login.err.empty'] ?? 'Lūdzu aizpildiet visus laukus!';
    } else {
        $stmt = mysqli_prepare($savienojums,
            "SELECT id, username, email, password, role, is_active FROM BU_users WHERE email = ?");

        if ($stmt === false) {
            $error = $_t['login.err.system'] ?? 'Sistēmas kļūda. Lūdzu mēģinājiet vēlāk.';
        } else {
            mysqli_stmt_bind_param($stmt, "s", $email);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);

            if (mysqli_stmt_num_rows($stmt) === 1) {
                mysqli_stmt_bind_result($stmt, $user_id, $username, $user_email, $hashed_password, $user_role, $user_is_active);
                mysqli_stmt_fetch($stmt);

                if (!$user_is_active) {
                    $error = $_t['login.err.deactivated'] ?? 'Jūsu konts ir deāktivēts. Sazinieties ar administrātoru.';
                } elseif (password_verify($password, $hashed_password)) {
                    // ── Set session ───────────────────────────────────────────
                    $_SESSION['user_id'] = $user_id;
                    $_SESSION['username'] = $username;
                    $_SESSION['email']    = $user_email;
                    $_SESSION['role']     = $user_role ?? 'user';

                    // Update last login
                    $ll = mysqli_prepare($savienojums, "UPDATE BU_users SET last_login = NOW() WHERE id = ?");
                    if ($ll) { mysqli_stmt_bind_param($ll, "i", $user_id); mysqli_stmt_execute($ll); mysqli_stmt_close($ll); }

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
                    $error = $_t['login.err.invalid'] ?? 'Nepareizs e-pasts vai parole!';
                }
            } else {
                $error = $_t['login.err.invalid'] ?? 'Nepareizs e-pasts vai parole!';
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
    <title data-i18n="login.page.title">Ieiet - Budgetar</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="icon" href="../../assets/image/logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <a href="index.php" class="back-link" data-i18n="login.back">← Atpakaļ</a>
                <h1 class="auth-title" data-i18n="login.title">Laipni lūdzam atpakaļ!</h1>
                <p class="auth-subtitle" data-i18n="login.subtitle">Ielogojieties, lai turpinātu</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form class="auth-form" id="loginForm" method="POST" action="">
                <div class="form-group">
                    <label for="email" class="form-label" data-i18n="login.email.label">E-pasts</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        class="form-input"
                        placeholder="tavs@epasts.lv"
                        data-i18n-placeholder="login.email.placeholder"
                        required
                        value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                    >
                </div>

                <div class="form-group">
                    <label for="password" class="form-label" data-i18n="login.password.label">Parole</label>
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
                        <span data-i18n="login.remember">Atcerēties mani</span>
                    </label>
                    <a href="forgot_password.php" class="link" data-i18n="login.forgot">Aizmirsi paroli?</a>
                </div>

                <button type="submit" class="btn btn-primary btn-full" data-i18n="login.btn">
                    Ieiet
                </button>
            </form>

            <div class="auth-footer">
                <p><span data-i18n="login.no.account">Nav konta?</span> <a href="register.php" class="link" data-i18n="login.register.link">Reģistrēties</a></p>
            </div>
        </div>

        <div class="auth-visual">
            <div class="visual-content">
                <h2 class="visual-title" data-i18n="login.visual.title">Tavi finanšu mērķi gaida tevi</h2>
                <div class="visual-features">
                    <div class="visual-feature">
                        <span class="visual-icon"><i class="fa-solid fa-chart-simple"></i></span>
                        <span data-i18n="login.visual.feat1">Detalizēti ienākumu un izdevumu pārskati</span>
                    </div>
                    <div class="visual-feature">
                        <span class="visual-icon"><i class="fa-solid fa-calendar"></i></span>
                        <span data-i18n="login.visual.feat2">Kalendāra skats visām transakcijām</span>
                    </div>
                    <div class="visual-feature">
                        <span class="visual-icon"><i class="fa-solid fa-sack-dollar"></i></span>
                        <span data-i18n="login.visual.feat3">Izseko savu budžetu reāllaikā</span>
                    </div>
                    <div class="visual-feature">
                        <span class="visual-icon"><i class="fa-solid fa-bullseye"></i></span>
                        <span data-i18n="login.visual.feat4">Sasniedz savus finanšu mērķus</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>window._i18nData=<?php echo json_encode($_traw); ?>;window._i18nIsDefault=true;</script>
    <script src="../js/language.js"></script>
    <script src="../js/script.js"></script>
</body>
</html>