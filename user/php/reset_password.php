<?php
session_start();

// If already logged in, redirect
if (isset($_SESSION['user_id'])) {
    header('Location: calendar.php');
    exit();
}

require_once '../../assets/database.php';

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
        $error = 'Nederīga paroles atjaunošanas saite.';
    } else {
        $record = get_reset_record($savienojums, $token_value);
        if ($record) {
            $valid_token = true;
        } else {
            $error = 'Šī paroles atjaunošanas saite ir nederīga vai ir beigusies.';
        }
    }
}

// ─── POST: set new password ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token_value      = trim($_POST['token'] ?? '');
    $new_password     = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($token_value)) {
        $error = 'Nederīgs pieprasījums.';
    } else {
        $record = get_reset_record($savienojums, $token_value);
        if (!$record) {
            $error = 'Šī paroles atjaunošanas saite ir nederīga vai ir beigusies.';
        } elseif (strlen($new_password) < 8) {
            $valid_token = true;
            $error = 'Parolei jābūt vismaz 8 rakstzīmēm!';
        } elseif ($new_password !== $confirm_password) {
            $valid_token = true;
            $error = 'Paroles nesakrīt!';
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

            $success = 'Parole veiksmīgi atjaunota! Tūlīt tiksiet novirzīts uz ielogošanās lapu...';
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
    <title>Atjaunot paroli - Budgetar</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="icon" href="../../assets/image/logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <a href="login.php" class="back-link">← Atpakaļ</a>
                <h1 class="auth-title">Jauna parole</h1>
                <p class="auth-subtitle">Ievadiet savu jauno paroli</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($error); ?>
                    <?php if (!$valid_token): ?>
                        <br><a href="forgot_password.php" class="link">Pieprasīt jaunu saiti</a>
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
                    <label for="new_password" class="form-label">Jaunā parole</label>
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
                    <span class="form-hint">Vismaz 8 rakstzīmes</span>
                </div>

                <div class="form-group">
                    <label for="confirm_password" class="form-label">Apstiprināt paroli</label>
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

                <button type="submit" class="btn btn-primary btn-full">
                    Saglabāt jauno paroli
                </button>
            </form>
            <?php endif; ?>

            <div class="auth-footer">
                <p>Atcerējāties paroli? <a href="login.php" class="link">Ielogojieties</a></p>
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
