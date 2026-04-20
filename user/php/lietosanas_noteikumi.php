<?php
session_start();
require_once('../../assets/database.php');

$is_guest = !isset($_SESSION['user_id']);
$user_id  = $_SESSION['user_id'] ?? null;
$username = $_SESSION['username'] ?? '';

// Load theme and language
$current_theme    = 'dark';
$current_language = 'lv';
$_langIsDefault   = true;

if (!$is_guest) {
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
} else {
    // Detect language from browser for guests
    $_supported = ['lv', 'en'];
    if (!empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
        $code = strtolower(substr(trim($_SERVER['HTTP_ACCEPT_LANGUAGE']), 0, 2));
        if (in_array($code, $_supported)) $current_language = $code;
    }
}

$_traw_settings = json_decode(file_get_contents(__DIR__ . '/translate.json'), true) ?? [];
$en = ($current_language === 'en');
?>
<?php $active_page = 'settings'; ?>
<!DOCTYPE html>
<html lang="<?php echo $en ? 'en' : 'lv'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Budgetar</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="../css/settings.css">
    <link rel="icon" href="../../assets/image/logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">
</head>
<body class="<?php echo $current_theme === 'light' ? 'light-mode' : ''; ?>">
    <div class="<?php echo $is_guest ? '' : 'dashboard-container'; ?>">
        <?php if (!$is_guest): ?><?php include __DIR__ . '/sidebar.php'; ?><?php endif; ?>

        <main class="<?php echo $is_guest ? '' : 'dashboard-main'; ?>" <?php if ($is_guest): ?>style="max-width:860px; margin:40px auto; padding:0 24px;"<?php endif; ?>>
            <div class="dashboard-header">
                <h1 class="dashboard-title"><?php echo $en ? 'Terms of Use' : 'Lietošanas noteikumi'; ?></h1>
                <?php if ($is_guest): ?>
                <a href="register.php" class="btn btn-secondary">
                    <i class="fa-solid fa-arrow-left"></i> <?php echo $en ? 'Back to registration' : 'Atpakaļ uz reģistrāciju'; ?>
                </a>
                <?php else: ?>
                <a href="settings.php" class="btn btn-secondary">
                    <i class="fa-solid fa-arrow-left"></i> <?php echo $en ? 'Back to settings' : 'Atpakaļ uz iestatījumiem'; ?>
                </a>
                <?php endif; ?>
            </div>

            <section class="settings-section privacy-body">
                <p class="privacy-updated"><i class="fa-regular fa-calendar"></i> <?php echo $en ? 'Last updated: April 9, 2026' : 'Pēdējo reizi atjaunināts: 2026. gada 9. aprīlī'; ?></p>

                <h2><?php echo $en ? '1. Acceptance of Terms' : '1. Noteikumu pieņemšana'; ?></h2>
                <p><?php echo $en
                    ? 'By registering and using Budgetar, you agree to these Terms of Use. If you do not agree, please do not use the service.'
                    : 'Reģistrējoties un izmantojot Budgetar, jūs piekrītat šiem lietošanas noteikumiem. Ja nepiekrītat, lūdzu neizmantojiet pakalpojumu.'; ?></p>

                <h2><?php echo $en ? '2. Service Description' : '2. Pakalpojuma apraksts'; ?></h2>
                <p><?php echo $en
                    ? 'Budgetar is a personal finance management web application that allows you to track income, expenses and budgets. The service is provided free of charge for personal, non-commercial use.'
                    : 'Budgetar ir personālo finanšu pārvaldības tīmekļa lietotne, kas ļauj izsekot ienākumiem, izdevumiem un budžetiem. Pakalpojums tiek nodrošināts bez maksas personiskai, nekomerciālai lietošanai.'; ?></p>

                <h2><?php echo $en ? '3. User Accounts' : '3. Lietotāju konti'; ?></h2>
                <p><?php echo $en ? 'You are responsible for:' : 'Jūs esat atbildīgs par:'; ?></p>
                <ul>
                    <li><?php echo $en ? 'Maintaining the confidentiality of your account credentials' : 'Sava konta pieteikšanās datu konfidencialitātes saglabāšanu'; ?></li>
                    <li><?php echo $en ? 'All activity that occurs under your account' : 'Visām darbībām, kas notiek jūsu kontā'; ?></li>
                    <li><?php echo $en ? 'Providing accurate and up-to-date information during registration' : 'Precīzas un aktuālas informācijas sniegšanu reģistrācijas laikā'; ?></li>
                </ul>
                <p><?php echo $en
                    ? 'You must notify us immediately of any unauthorised use of your account.'
                    : 'Jums nekavējoties jāpaziņo mums par jebkādu neatļautu jūsu konta izmantošanu.'; ?></p>

                <h2><?php echo $en ? '4. Acceptable Use' : '4. Pieļaujamā lietošana'; ?></h2>
                <p><?php echo $en ? 'You agree not to:' : 'Jūs piekrītat nedarīt sekojošo:'; ?></p>
                <ul>
                    <li><?php echo $en ? 'Use the service for any unlawful purpose' : 'Izmantot pakalpojumu jebkādiem nelikumīgiem mērķiem'; ?></li>
                    <li><?php echo $en ? 'Attempt to gain unauthorised access to any part of the service' : 'Mēģināt iegūt neatļautu piekļuvi jebkurai pakalpojuma daļai'; ?></li>
                    <li><?php echo $en ? 'Interfere with or disrupt the integrity or performance of the service' : 'Traucēt vai pārtraukt pakalpojuma integritāti vai darbību'; ?></li>
                    <li><?php echo $en ? 'Upload or transmit any malicious code or content' : 'Augšupielādēt vai pārsūtīt ļaunprātīgu kodu vai saturu'; ?></li>
                </ul>

                <h2><?php echo $en ? '5. Data and Privacy' : '5. Dati un privātums'; ?></h2>
                <p><?php echo $en
                    ? 'Your use of the service is also governed by our Privacy Policy, which is incorporated into these Terms of Use by reference. Please review it to understand our data practices.'
                    : 'Jūsu pakalpojuma lietošanu reglamentē arī mūsu Privātuma politika, kas ar atsauci ir iekļauta šajos Lietošanas noteikumos. Lūdzu iepazīstieties ar to, lai izprastu mūsu datu praksi.'; ?></p>

                <h2><?php echo $en ? '6. Disclaimer of Warranties' : '6. Garantiju atteikums'; ?></h2>
                <p><?php echo $en
                    ? 'Budgetar is provided "as is" without warranties of any kind. We do not guarantee that the service will be uninterrupted, error-free, or that the financial information displayed will be used for any specific purpose. The service is a tool to assist with personal budgeting and does not constitute financial advice.'
                    : 'Budgetar tiek nodrošināts "tāds, kāds ir" bez jebkādām garantijām. Mēs negarantējam, ka pakalpojums darbosies bez pārtraukumiem vai kļūdām, kā arī to, ka parādītā finanšu informācija tiks izmantota kādam īpašam mērķim. Pakalpojums ir rīks personīgā budžeta plānošanas palīdzībai un nav uzskatāms par finanšu padomu.'; ?></p>

                <h2><?php echo $en ? '7. Limitation of Liability' : '7. Atbildības ierobežojums'; ?></h2>
                <p><?php echo $en
                    ? 'To the fullest extent permitted by law, Budgetar shall not be liable for any indirect, incidental, or consequential damages arising from your use of or inability to use the service.'
                    : 'Cik tālu to pieļauj spēkā esošie tiesību akti, Budgetar neatbild par netiešiem, nejaušiem vai izrietošiem zaudējumiem, kas radušies no pakalpojuma izmantošanas vai nespējas to izmantot.'; ?></p>

                <h2><?php echo $en ? '8. Changes to Terms' : '8. Noteikumu izmaiņas'; ?></h2>
                <p><?php echo $en
                    ? 'We reserve the right to modify these Terms of Use at any time. Continued use of the service after changes are posted constitutes acceptance of the revised terms.'
                    : 'Mēs paturam tiesības jebkurā laikā mainīt šos Lietošanas noteikumus. Turpinot izmantot pakalpojumu pēc izmaiņu publicēšanas, jūs piekrītat pārskatītajiem noteikumiem.'; ?></p>

                <h2><?php echo $en ? '9. Contact' : '9. Saziņa'; ?></h2>
                <p><?php echo $en
                    ? 'If you have questions about these Terms of Use, please contact us using the email address linked to your account.'
                    : 'Ja jums ir jautājumi par šiem Lietošanas noteikumiem, sazinieties ar mums, izmantojot ar jūsu kontu saistīto e-pasta adresi.'; ?></p>
            </section>
        </main>
    </div>

    <?php if (!$is_guest): ?><?php include __DIR__ . '/mobile_nav.php'; ?><?php endif; ?>

    <script src="../js/script.js"></script>
    <script>window._i18nData=<?php echo json_encode($_traw_settings); ?>;window._i18nLang=<?php echo json_encode($current_language); ?>;window._i18nIsDefault=<?php echo $_langIsDefault ? 'true' : 'false'; ?>;</script>
    <script src="../js/language.js"></script>
</body>
</html>
