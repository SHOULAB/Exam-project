<?php
session_start();

// If already logged in, redirect
if (isset($_SESSION['user_id'])) {
    header('Location: calendar.php');
    exit();
}

require_once '../../assets/database.php';

// ─── Language detection ───────────────────────────────────────────────────────
$_supported   = ['lv', 'en'];
$_browserLang = 'lv';
if (!empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
    $code = strtolower(substr(trim($_SERVER['HTTP_ACCEPT_LANGUAGE']), 0, 2));
    if (in_array($code, $_supported)) $_browserLang = $code;
}
$_traw = json_decode(file_get_contents(__DIR__ . '/translate.json'), true) ?? [];
$_t    = $_traw[$_browserLang] ?? $_traw['lv'];

$error       = '';
$success     = '';
$valid_token = false;
$token_value = '';

/**
 * Look up a valid, unused, non-expired reset token.
 * Returns the row array or null.
 */
function get_reset_record(mysqli $db, string $token): ?array {
    $stmt = mysqli_prepare($db,
        "SELECT pr.id, pr.user_id, u.username
         FROM BU_password_resets pr
         JOIN BU_users u ON u.id = pr.user_id
         WHERE pr.token = ? AND pr.used = 0 AND pr.expires_at > NOW()");
    if (!$stmt) return null;
    mysqli_stmt_bind_param($stmt, "s", $token);
    mysqli_stmt_execute($stmt);
    $res    = mysqli_stmt_get_result($stmt);
    $record = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);
    return $record ?: null;
}

// ─── GET: validate token from URL ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $token_value = trim($_GET['token'] ?? '');
    if (empty($token_value)) {
        $error = $_t['reset.err.invalid.link'] ?? 'Nederīga paroles atjaunošanas saite.';
    } else {
        $record = get_reset_record($savienojums, $token_value);
        if ($record) {
            $valid_token = true;
        } else {
            $error = $_t['reset.err.expired'] ?? 'Šī paroles atjaunošanas saite ir nederīga vai ir beigusies.';
        }
    }
}

// ─── POST: set new password ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token_value      = trim($_POST['token'] ?? '');
    $new_password     = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($token_value)) {
        $error = $_t['reset.err.invalid.req'] ?? 'Nederīgs pieprasījums.';
    } else {
        $record = get_reset_record($savienojums, $token_value);
        if (!$record) {
            $error = $_t['reset.err.expired'] ?? 'Šī paroles atjaunošanas saite ir nederīga vai ir beigusies.';
        } elseif (strlen($new_password) < 8) {
            $valid_token = true;
            $error = $_t['reset.err.password.short'] ?? 'Parolei jābūt vismāz 8 rakstzīmēm!';
        } elseif ($new_password !== $confirm_password) {
            $valid_token = true;
            $error = $_t['reset.err.password.match'] ?? 'Paroles nesakrīt!';
        } else {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);

            // Update user's password
            $upd = mysqli_prepare($savienojums, "UPDATE BU_users SET password = ? WHERE id = ?");
            mysqli_stmt_bind_param($upd, "si", $hashed, $record['user_id']);
            mysqli_stmt_execute($upd);
            mysqli_stmt_close($upd);

            // Mark token as used so it cannot be reused
            $mark = mysqli_prepare($savienojums, "UPDATE BU_password_resets SET used = 1 WHERE token = ?");
            mysqli_stmt_bind_param($mark, "s", $token_value);
            mysqli_stmt_execute($mark);
            mysqli_stmt_close($mark);

            $success = $_t['reset.success'] ?? 'Parole veiksmīgi atjaunota! Tūlīt tiksiet novirzīts uz ielogošanās lapu...';
            header("refresh:2;url=login.php");
        }
    }
}
?>
<!DOCTYPE html>
<html lang="lv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-i18n="reset.page.title">Atjaunot paroli - Budgetar</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="icon" href="../../assets/image/logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <a href="login.php" class="back-link" data-i18n="reset.back">← Atpakaļ</a>
                <h1 class="auth-title" data-i18n="reset.title">Jauna parole</h1>
                <p class="auth-subtitle" data-i18n="reset.subtitle">Ievadiet savu jauno paroli</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($error); ?>
                    <?php if (!$valid_token): ?>
                        <br><a href="forgot_password.php" class="link" data-i18n="reset.new.link">Pieprasīt jaunu saiti</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <?php if ($valid_token && !$success): ?>
            <form class="auth-form" method="POST" action="">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token_value); ?>">

                <div class="form-group">
                    <label for="new_password" class="form-label" data-i18n="reset.new.label">Jaunā parole</label>
                    <div class="password-input-wrapper">
                        <input
                            type="password"
                            id="new_password"
                            name="new_password"
                            class="form-input"
                            placeholder="••••••••"
                            required
                            minlength="8"
                        >
                        <button type="button" class="password-toggle" onclick="togglePassword('new_password')">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                    <span class="form-hint" data-i18n="reset.hint">Vismāz 8 rakstzīmes</span>
                </div>

                <div class="form-group">
                    <label for="confirm_password" class="form-label" data-i18n="reset.confirm.label">Apstipriniāt paroli</label>
                    <div class="password-input-wrapper">
                        <input
                            type="password"
                            id="confirm_password"
                            name="confirm_password"
                            class="form-input"
                            placeholder="••••••••"
                            required
                        >
                        <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-full" data-i18n="reset.btn">
                    Saglabāt jauno paroli
                </button>
            </form>
            <?php endif; ?>

            <div class="auth-footer">
                <p><span data-i18n="reset.remembered">Atcerējāties paroli?</span> <a href="login.php" class="link" data-i18n="reset.login.link">Ielogojieties</a></p>
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
