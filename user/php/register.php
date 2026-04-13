<?php
// register.php - Registration Page
session_start();

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
$success = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirmPassword'];
    
    // Validation
    if (empty($username) || empty($email) || empty($password) || empty($confirmPassword)) {
        $error = $_t['reg.err.empty'] ?? 'Visi lauki ir obligāti!';
    } elseif (strlen($username) < 4) {
        $error = $_t['reg.err.username.short'] ?? 'Lietotājvārdam jābūt vismāz 4 simboliem!';
    } elseif (strlen($password) < 8) {
        $error = $_t['reg.err.password.short'] ?? 'Parolei jābūt vismāz 8 simboliem!';
    } elseif ($password !== $confirmPassword) {
        $error = $_t['reg.err.password.match'] ?? 'Paroles nesakrīt!';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = $_t['reg.err.email.invalid'] ?? 'Nederīgs e-pasta formāts!';
    } else {
        // --- 1. Check if username already exists ---
        $stmt_user = mysqli_prepare($savienojums, "SELECT id FROM BU_users WHERE username = ?");
        
        if ($stmt_user === false) {
             $error = $_t['reg.err.system'] ?? 'Sistēmas kļūda. Lūdzu mēģinājiet vēlāk.';
        } else {
            mysqli_stmt_bind_param($stmt_user, "s", $username);
            mysqli_stmt_execute($stmt_user);
            mysqli_stmt_store_result($stmt_user);
            
            if (mysqli_stmt_num_rows($stmt_user) > 0) {
                $error = $_t['reg.err.username.taken'] ?? 'Lietotājvārds jau ir aizņemts!';
            }
            mysqli_stmt_close($stmt_user);
        }

        // --- 2. Check if email already exists (Only proceed if no error yet) ---
        if (empty($error)) {
            $stmt_email = mysqli_prepare($savienojums, "SELECT id FROM BU_users WHERE email = ?");
            
            if ($stmt_email === false) {
                 $error = $_t['reg.err.system'] ?? 'Sistēmas kļūda. Lūdzu mēģinājiet vēlāk.';
            } else {
                mysqli_stmt_bind_param($stmt_email, "s", $email);
                mysqli_stmt_execute($stmt_email);
                mysqli_stmt_store_result($stmt_email);
                
                if (mysqli_stmt_num_rows($stmt_email) > 0) {
                    $error = $_t['reg.err.email.taken'] ?? 'E-pasts jau ir reģistrēts!';
                }
                mysqli_stmt_close($stmt_email);
            }
        }

        // --- 3. Insert new user (Only proceed if no error yet) ---
        if (empty($error)) {
            // Hash password securely
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt_insert = mysqli_prepare($savienojums, "INSERT INTO BU_users (username, email, password) VALUES (?, ?, ?)");
            
            if ($stmt_insert === false) {
                 $error = $_t['reg.err.system'] ?? 'Sistēmas kļūda. Lūdzu mēģinājiet vēlāk.';
            } else {
                mysqli_stmt_bind_param($stmt_insert, "sss", $username, $email, $hashedPassword);
                
                if (mysqli_stmt_execute($stmt_insert)) {
                    $new_user_id = mysqli_insert_id($savienojums);

                    // Auto-login the new user
                    $_SESSION['user_id']  = $new_user_id;
                    $_SESSION['username'] = $username;
                    $_SESSION['email']    = $email;
                    $_SESSION['role']     = 'user';
                    $_SESSION['theme']    = 'dark';

                    // Update last login
                    $ll = mysqli_prepare($savienojums, "UPDATE BU_users SET last_login = NOW() WHERE id = ?");
                    if ($ll) { mysqli_stmt_bind_param($ll, "i", $new_user_id); mysqli_stmt_execute($ll); mysqli_stmt_close($ll); }

                    header('Location: calendar.php');
                    exit();
                } else {
                    $error = $_t['reg.err.insert'] ?? 'Kļūda reģistrācijas laikā. Lūdzu mēģinājiet vēlāk.';
                }
                mysqli_stmt_close($stmt_insert);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="lv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-i18n="reg.page.title">Reģistrēties - Budgetar</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="icon" href="../../assets/image/logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <a href="index.php" class="back-link" data-i18n="reg.back">← Atpakaļ</a>
                <h1 class="auth-title" data-i18n="reg.title">Izveido kontu</h1>
                <p class="auth-subtitle" data-i18n="reg.subtitle">Sāc pārvaldīt savas finanses šodien</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <form class="auth-form" id="registerForm" method="POST" action="">
                <div class="form-group">
                    <label for="username" class="form-label" data-i18n="reg.username.label">Lietotājvārds</label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username"
                        class="form-input" 
                        placeholder="lietotajs123"
                        data-i18n-placeholder="reg.username.placeholder"
                        required
                        minlength="4"
                        value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                    >
                    <span class="form-hint" data-i18n="reg.username.hint">Vismāz 4 simboli</span>
                </div>

                <div class="form-group">
                    <label for="email" class="form-label" data-i18n="reg.email.label">E-pasts</label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email"
                        class="form-input" 
                        placeholder="tavs@epasts.lv"
                        data-i18n-placeholder="reg.email.placeholder"
                        required
                        value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                    >
                </div>

                <div class="form-group">
                    <label for="password" class="form-label" data-i18n="reg.password.label">Parole</label>
                    <div class="password-input-wrapper">
                        <input 
                            type="password" 
                            id="password" 
                            name="password"
                            class="form-input" 
                            placeholder="••••••••"
                            required
                            minlength="8"
                        >
                        <button type="button" class="password-toggle" onclick="togglePassword('password')">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                    <span class="form-hint" data-i18n="reg.password.hint">Vismāz 8 simboli</span>
                </div>

                <div class="form-group">
                    <label for="confirmPassword" class="form-label" data-i18n="reg.confirm.label">Apstipriniēt paroli</label>
                    <div class="password-input-wrapper">
                        <input 
                            type="password" 
                            id="confirmPassword" 
                            name="confirmPassword"
                            class="form-input" 
                            placeholder="••••••••"
                            required
                        >
                        <button type="button" class="password-toggle" onclick="togglePassword('confirmPassword')">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="form-options form-options-stack">
                    <label class="checkbox-label">
                        <input type="checkbox" name="privacy" class="checkbox-input" required>
                        <span><span data-i18n="reg.privacy">Piekrītu</span> <a href="privacy_policy.php" class="link" data-i18n="reg.privacy.link">privātuma politikai</a></span>
                    </label>
                    <label class="checkbox-label">
                        <input type="checkbox" name="terms" class="checkbox-input" required>
                        <span><span data-i18n="reg.terms">Piekrītu</span> <a href="lietosanas_noteikumi.php" class="link" data-i18n="reg.terms.link">lietošanas noteikumiem</a></span>
                    </label>
                </div>

                <button type="submit" class="btn btn-primary btn-full" data-i18n="reg.btn">
                    Reģistrēties
                </button>
            </form>

            <div class="auth-footer">
                <p><span data-i18n="reg.has.account">Jau ir konts?</span> <a href="login.php" class="link" data-i18n="reg.login.link">Ieiet</a></p>
            </div>
        </div>

        <div class="auth-visual">
            <div class="visual-content">
                <h2 class="visual-title" data-i18n="reg.visual.title">Sāc gudri pārvaldīt savas finanses</h2>
                <div class="visual-features">
                    <div class="visual-feature">
                        <span class="visual-icon"><i class="fa-solid fa-shield-halved"></i></span>
                        <span data-i18n="reg.visual.feat1">Bezmaksas un droša reģistrācija</span>
                    </div>
                    <div class="visual-feature">
                        <span class="visual-icon"><i class="fa-solid fa-lock"></i></span>
                        <span data-i18n="reg.visual.feat2">Visi dati šifrēti</span>
                    </div>
                    <div class="visual-feature">
                        <span class="visual-icon"><i class="fa-solid fa-mobile-screen"></i></span>
                        <span data-i18n="reg.visual.feat3">Pieejams no jebkuras ierīces</span>
                    </div>
                    <div class="visual-feature">
                        <span class="visual-icon"><i class="fa-solid fa-clock"></i></span>
                        <span data-i18n="reg.visual.feat4">24/7 piekļuve savām finansēm</span>
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