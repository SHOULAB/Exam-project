<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
require_once('../../assets/database.php');

$user_id  = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? '';

// Load theme, language from DB
$current_theme    = 'dark';
$current_language = 'lv';
$_langIsDefault   = true;

$stmt = mysqli_prepare($savienojums,
    "SELECT setting_key, setting_value FROM BU_user_settings
     WHERE user_id = ? AND setting_key IN ('theme', 'language')");
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        if ($row['setting_key'] === 'theme')    $current_theme    = $row['setting_value'];
        if ($row['setting_key'] === 'language') { $current_language = $row['setting_value']; $_langIsDefault = false; }
    }
    mysqli_stmt_close($stmt);
}
$_SESSION['theme']    = $current_theme;
$_SESSION['language'] = $current_language;

$_traw_settings = json_decode(file_get_contents(__DIR__ . '/translate.json'), true) ?? [];
?>
<?php $active_page = 'settings'; ?>
<!DOCTYPE html>
<html lang="lv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privātuma politika - Budgetar</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="../css/settings.css">
    <link rel="icon" href="../../assets/image/logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">
    <style>
        .privacy-body {
            max-width: 780px;
        }
        .privacy-body h2 {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-primary);
            margin: 28px 0 10px;
        }
        .privacy-body h2:first-child {
            margin-top: 0;
        }
        .privacy-body p,
        .privacy-body ul {
            font-size: 14px;
            color: var(--text-secondary);
            line-height: 1.75;
            margin-bottom: 12px;
        }
        .privacy-body ul {
            padding-left: 20px;
        }
        .privacy-body ul li {
            margin-bottom: 6px;
        }
        .privacy-updated {
            font-size: 12px;
            color: var(--text-secondary);
            margin-bottom: 28px;
        }
    </style>
</head>
<?php $en = ($current_language === 'en'); ?>
<body class="<?php echo $current_theme === 'light' ? 'light-mode' : ''; ?>">
    <div class="dashboard-container">
        <?php include __DIR__ . '/sidebar.php'; ?>

        <main class="dashboard-main">
            <div class="dashboard-header">
                <h1 class="dashboard-title"><?php echo $en ? 'Privacy Policy' : 'Privātuma politika'; ?></h1>
                <a href="settings.php" class="btn btn-secondary">
                    <i class="fa-solid fa-arrow-left"></i> <?php echo $en ? 'Back to settings' : 'Atpakaļ uz iestatījumiem'; ?>
                </a>
            </div>

            <section class="settings-section privacy-body">
                <p class="privacy-updated"><i class="fa-regular fa-calendar"></i> <?php echo $en ? 'Last updated: April 9, 2026' : 'Pēdējo reizi atjaunināts: 2026. gada 9. aprīlī'; ?></p>

                <h2><?php echo $en ? '1. Types of Data Collected' : '1. Ievākto datu veidi'; ?></h2>
                <p><?php echo $en ? 'Budgetar only collects data necessary to provide the service:' : 'Budgetar apkopo tikai tos datus, kas nepieciešami pakalpojuma sniegšanai:'; ?></p>
                <ul>
                    <li><?php echo $en ? 'Email address and username (for registration and login)' : 'E-pasta adrese un lietotājvārds (reģistrācijai un pieteikumam)'; ?></li>
                    <li><?php echo $en ? 'Encrypted password (stored as a hash — the original password is never accessible)' : 'Šifrēta parole (tiek glabāta kā hash — oriģinālā parole nav pieejama)'; ?></li>
                    <li><?php echo $en ? 'Financial data: transactions, budgets and settings you enter yourself' : 'Finanšu dati: darījumi, budžeti un iestatījumi, ko jūs pats ievadāt'; ?></li>
                </ul>

                <h2><?php echo $en ? '2. How We Use Your Data' : '2. Kā mēs izmantojam jūsu datus'; ?></h2>
                <p><?php echo $en ? 'Your data is used only for the following purposes:' : 'Jūsu dati tiek izmantoti tikai šādiem mērķiem:'; ?></p>
                <ul>
                    <li><?php echo $en ? 'Account authentication and security' : 'Konta autentifikācijai un drošībai'; ?></li>
                    <li><?php echo $en ? 'Displaying personalised financial reports and budgets' : 'Personalizētu finanšu pārskatu un budžetu attēlošanai'; ?></li>
                    <li><?php echo $en ? 'Ensuring the functioning of the application' : 'Lietotnes darbības nodrošināšanai'; ?></li>
                </ul>
                <p><?php echo $en ? 'Your data is never sold, rented or shared with third parties.' : 'Jūsu dati netiek pārdoti, iznomāti vai kopīgoti ar trešajām pusēm.'; ?></p>

                <h2><?php echo $en ? '3. Data Storage' : '3. Datu glabāšana'; ?></h2>
                <p><?php echo $en ? 'All data is stored in a secure database. Access is restricted and protected by authentication. When an account is deleted, all associated data is permanently removed.' : 'Visi dati tiek glabāti drošā datubāzē. Piekļuve ir ierobežota un aizsargāta ar autentifikāciju. Dzēšot kontu, visi ar to saistītie dati tiek neatgriezeniski dzēsti.'; ?></p>

                <h2><?php echo $en ? '4. Cookies' : '4. Sīkdatnes'; ?></h2>
                <p><?php echo $en ? 'Budgetar uses session cookies to maintain your login state. If you choose the "Remember me" option, a secure remember-me token cookie is stored. Cookies are not used for tracking or advertising purposes.' : 'Budgetar izmanto sesijas sīkdatnes, lai uzturētu jūsu pieteikšanās stāvokli. Ja izvēlaties opciju "Atcerēties mani", tiek uzglabāta drošas atcerēšanās pilnvaras sīkdatne. Sīkdatnes netiek izmantotas izsekošanai vai reklāmas nolūkiem.'; ?></p>

                <h2><?php echo $en ? '5. Your Rights' : '5. Jūsu tiesības'; ?></h2>
                <p><?php echo $en ? 'You have the right to:' : 'Jums ir tiesības:'; ?></p>
                <ul>
                    <li><?php echo $en ? 'Delete your account and all associated data at any time from the settings page' : 'Jebkurā laikā dzēst savu kontu un visus ar to saistītos datus no iestatījumu lapas'; ?></li>
                    <li><?php echo $en ? 'Reset all financial data while keeping your account' : 'Atiestatīt visus finanšu datus, saglabājot kontu'; ?></li>
                    <li><?php echo $en ? 'Change your email, username and password' : 'Mainīt savu e-pastu, lietotājvārdu un paroli'; ?></li>
                </ul>

                <h2><?php echo $en ? '6. Policy Changes' : '6. Izmaiņas politikā'; ?></h2>
                <p><?php echo $en ? 'We reserve the right to update this privacy policy. You will be notified of significant changes. By continuing to use Budgetar after changes are published, you agree to the updated policy.' : 'Mēs paturam tiesības atjaunināt šo privātuma politiku. Par būtiskām izmaiņām jūs tiks informēts. Turpinot lietot Budgetar pēc izmaiņu publicēšanas, jūs piekrītat jaunajai politikai.'; ?></p>

                <h2><?php echo $en ? '7. Contact' : '7. Saziņa'; ?></h2>
                <p><?php echo $en ? 'If you have questions about this privacy policy, please contact us using the email address listed in your account settings.' : 'Ja jums ir jautājumi par šo privātuma politiku, sazinieties ar mums, izmantojot konta iestatījumos norādīto e-pasta adresi.'; ?></p>
            </section>
        </main>
    </div>

    <?php include __DIR__ . '/mobile_nav.php'; ?>

    <script src="../js/script.js"></script>
    <script>window._i18nData=<?php echo json_encode($_traw_settings); ?>;window._i18nLang=<?php echo json_encode($current_language); ?>;window._i18nIsDefault=<?php echo $_langIsDefault ? 'true' : 'false'; ?>;</script>
    <script src="../js/language.js"></script>
</body>
</html>
