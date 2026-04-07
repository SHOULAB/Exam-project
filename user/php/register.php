<?php
// register.php - Registration Page
session_start();

// Include database connection
require_once('../../assets/database.php');

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
        $error = 'Visi lauki ir obligāti!';
    } elseif (strlen($username) < 4) {
        $error = 'Lietotājvārdam jābūt vismaz 4 simboliem!';
    } elseif (strlen($password) < 8) {
        $error = 'Parolei jābūt vismaz 8 simboliem!';
    } elseif ($password !== $confirmPassword) {
        $error = 'Paroles nesakrīt!';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Nederīgs e-pasta formāts!';
    } else {
        // --- 1. Check if username already exists ---
        $stmt_user = mysqli_prepare($savienojums, "SELECT id FROM BU_users WHERE username = ?");
        
        if ($stmt_user === false) {
             $error = 'Sistēmas kļūda (username check). Lūdzu mēģiniet vēlāk.';
        } else {
            mysqli_stmt_bind_param($stmt_user, "s", $username);
            mysqli_stmt_execute($stmt_user);
            mysqli_stmt_store_result($stmt_user);
            
            if (mysqli_stmt_num_rows($stmt_user) > 0) {
                $error = 'Lietotājvārds jau ir aizņemts!';
            }
            mysqli_stmt_close($stmt_user);
        }

        // --- 2. Check if email already exists (Only proceed if no error yet) ---
        if (empty($error)) {
            $stmt_email = mysqli_prepare($savienojums, "SELECT id FROM BU_users WHERE email = ?");
            
            if ($stmt_email === false) {
                 $error = 'Sistēmas kļūda (email check). Lūdzu mēģiniet vēlāk.';
            } else {
                mysqli_stmt_bind_param($stmt_email, "s", $email);
                mysqli_stmt_execute($stmt_email);
                mysqli_stmt_store_result($stmt_email);
                
                if (mysqli_stmt_num_rows($stmt_email) > 0) {
                    $error = 'E-pasts jau ir reģistrēts!';
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
                 $error = 'Sistēmas kļūda (insert). Lūdzu mēģiniet vēlāk.';
            } else {
                mysqli_stmt_bind_param($stmt_insert, "sss", $username, $email, $hashedPassword);
                
                if (mysqli_stmt_execute($stmt_insert)) {
                    $success = 'Reģistrācija veiksmīga! Tagad vari ielogoties.';
                    // Redirect to login after 2 seconds
                    header("refresh:2;url=login.php");
                } else {
                    $error = 'Kļūda reģistrācijas laikā. Lūdzu mēģiniet vēlāk.';
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
    <title>Reģistrēties - Budgetiva</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="icon" href="../../assets/image/logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <a href="index.php" class="back-link">← Atpakaļ</a>
                <h1 class="auth-title">Izveido kontu</h1>
                <p class="auth-subtitle">Sāc pārvaldīt savas finanses šodien</p>
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
                    <label for="username" class="form-label">Lietotājvārds</label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username"
                        class="form-input" 
                        placeholder="lietotajs123"
                        required
                        minlength="4"
                        value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                    >
                    <span class="form-hint">Vismaz 4 simboli</span>
                </div>

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
                            minlength="8"
                        >
                        <button type="button" class="password-toggle" onclick="togglePassword('password')">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                    <span class="form-hint">Vismaz 8 simboli</span>
                </div>

                <div class="form-group">
                    <label for="confirmPassword" class="form-label">Apstiprināt paroli</label>
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

                <div class="form-options">
                    <label class="checkbox-label">
                        <input type="checkbox" name="privacy" class="checkbox-input" required>
                        <span>Piekrītu <a href="#" class="link">privātuma politikai</a></span>
                    </label>
                </div>

                <button type="submit" class="btn btn-primary btn-full">
                    Reģistrēties
                </button>
            </form>

            <div class="auth-footer">
                <p>Jau ir konts? <a href="login.php" class="link">Ieiet</a></p>
            </div>
        </div>

        <div class="auth-visual">
            <div class="visual-content">
                <h2 class="visual-title">Sāc gudri pārvaldīt savas finanses</h2>
                <div class="visual-features">
                    <div class="visual-feature">
                        <span class="visual-icon"><i class="fa-solid fa-shield-halved"></i></span>
                        <span>Bezmaksas un droša reģistrācija</span>
                    </div>
                    <div class="visual-feature">
                        <span class="visual-icon"><i class="fa-solid fa-lock"></i></span>
                        <span>Visi dati šifrēti</span>
                    </div>
                    <div class="visual-feature">
                        <span class="visual-icon"><i class="fa-solid fa-mobile-screen"></i></span>
                        <span>Pieejams no jebkuras ierīces</span>
                    </div>
                    <div class="visual-feature">
                        <span class="visual-icon"><i class="fa-solid fa-clock"></i></span>
                        <span>24/7 piekļuve savām finansēm</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../js/script.js"></script>
</body>
</html>