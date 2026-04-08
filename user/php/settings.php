<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
require_once('../../assets/database.php');

$user_id  = $_SESSION['user_id'];
$username = $_SESSION['username'];
$current_email = '';

$success_message = '';
$error_message   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_account'])) {
    $delete_password = trim($_POST['delete_password'] ?? '');

    if ($delete_password === '') {
        $error_message = 'Lūdzu ievadiet savu paroli, lai apstiprinātu konta dzēšanu.';
    } else {
        $stmt_pass = mysqli_prepare($savienojums, "SELECT password FROM BU_users WHERE id = ?");
        if ($stmt_pass) {
            mysqli_stmt_bind_param($stmt_pass, "i", $user_id);
            mysqli_stmt_execute($stmt_pass);
            mysqli_stmt_bind_result($stmt_pass, $current_password_hash);
            mysqli_stmt_fetch($stmt_pass);
            mysqli_stmt_close($stmt_pass);

            if (!password_verify($delete_password, $current_password_hash)) {
                $error_message = 'Parole ir nepareiza. Kontu netika dzēsts.';
            }
        } else {
            $error_message = 'Kļūda pārbaudot paroli. Lūdzu mēģiniet vēlāk.';
        }
    }

    if ($error_message === '') {
        if (isset($_COOKIE['remember_token'])) {
            $token = $_COOKIE['remember_token'];
            $stmt = mysqli_prepare($savienojums, "DELETE FROM BU_remember_tokens WHERE token = ?");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "s", $token);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
            setcookie('remember_token', '', time() - 3600, '/', '', false, true);
        }

        $stmt = mysqli_prepare($savienojums, "DELETE FROM BU_remember_tokens WHERE user_id = ?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $user_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }

        $stmt = mysqli_prepare($savienojums, "DELETE FROM BU_user_settings WHERE user_id = ?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $user_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }

        $stmt = mysqli_prepare($savienojums, "DELETE FROM BU_users WHERE id = ?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $user_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }

        session_unset();
        session_destroy();
        header('Location: index.php');
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_account'])) {
    $reset_password = trim($_POST['reset_password'] ?? '');

    if ($reset_password === '') {
        $error_message = 'Lūdzu ievadiet savu paroli, lai apstiprinātu konta atiestatīšanu.';
    } else {
        $stmt_pass = mysqli_prepare($savienojums, "SELECT password FROM BU_users WHERE id = ?");
        if ($stmt_pass) {
            mysqli_stmt_bind_param($stmt_pass, "i", $user_id);
            mysqli_stmt_execute($stmt_pass);
            mysqli_stmt_bind_result($stmt_pass, $current_password_hash);
            mysqli_stmt_fetch($stmt_pass);
            mysqli_stmt_close($stmt_pass);

            if (!password_verify($reset_password, $current_password_hash)) {
                $error_message = 'Parole ir nepareiza. Konts netika atiestatīts.';
            }
        } else {
            $error_message = 'Kļūda pārbaudot paroli. Lūdzu mēģiniet vēlāk.';
        }
    }

    if ($error_message === '') {
        if (isset($_COOKIE['remember_token'])) {
            $token = $_COOKIE['remember_token'];
            $stmt = mysqli_prepare($savienojums, "DELETE FROM BU_remember_tokens WHERE token = ?");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "s", $token);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
            setcookie('remember_token', '', time() - 3600, '/', '', false, true);
        }

        $stmt = mysqli_prepare($savienojums, "DELETE FROM BU_remember_tokens WHERE user_id = ?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $user_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }

        $stmt = mysqli_prepare($savienojums, "DELETE FROM BU_transactions WHERE user_id = ?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $user_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }

        $stmt = mysqli_prepare($savienojums, "DELETE FROM BU_budgets WHERE user_id = ?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $user_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }

        $stmt = mysqli_prepare($savienojums, "DELETE FROM BU_user_settings WHERE user_id = ?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $user_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }

        $success_message = 'Jūsu konta dati ir atiestatīti. Varat sākt no jauna.';
    }
}

// ── Handle preference saves ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings']) && !isset($_POST['delete_account']) && !isset($_POST['reset_account'])) {
    $theme = $_POST['theme'] === 'light' ? 'light' : 'dark';
    $allowed_currencies = ['EUR', 'USD', 'GBP', 'JPY', 'CHF', 'INR', 'RUB', 'TRY', 'KRW'];
    $currency = isset($_POST['currency']) && in_array($_POST['currency'], $allowed_currencies) 
                ? $_POST['currency'] 
                : 'EUR';

    $success = true;

    // Save username
    if (isset($_POST['username'])) {
        $new_username = trim($_POST['username']);

        if ($new_username === '') {
            $success = false;
            $error_message = 'Lietotājvārds nevar būt tukšs.';
        } elseif (strlen($new_username) < 4) {
            $success = false;
            $error_message = 'Lietotājvārdam jābūt vismaz 4 simbolus garam.';
        } elseif ($new_username !== $username) {
            $stmt = mysqli_prepare($savienojums, "SELECT id FROM BU_users WHERE username = ? AND id != ?");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "si", $new_username, $user_id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_store_result($stmt);

                if (mysqli_stmt_num_rows($stmt) > 0) {
                    $success = false;
                    $error_message = 'Šāds lietotājvārds jau ir aizņemts.';
                }
                mysqli_stmt_close($stmt);
            }

            if ($success) {
                $stmt = mysqli_prepare($savienojums, "UPDATE BU_users SET username = ? WHERE id = ?");
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "si", $new_username, $user_id);
                    if (mysqli_stmt_execute($stmt)) {
                        $_SESSION['username'] = $new_username;
                        $username = $new_username;
                    } else {
                        $success = false;
                        $error_message = 'Kļūda atjauninot lietotājvārdu. Lūdzu mēģiniet vēlāk.';
                    }
                    mysqli_stmt_close($stmt);
                } else {
                    $success = false;
                    $error_message = 'Kļūda atjauninot lietotājvārdu. Lūdzu mēģiniet vēlāk.';
                }
            }
        }
    }

    // Save email
    if (isset($_POST['email'])) {
        $new_email = trim($_POST['email']);

        if ($new_email === '') {
            $success = false;
            $error_message = 'E-pasts nevar būt tukšs.';
        } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            $success = false;
            $error_message = 'Lūdzu ievadiet derīgu e-pasta adresi.';
        } else {
            // Load current email if not already loaded
            if ($current_email === '') {
                $stmt_email = mysqli_prepare($savienojums, "SELECT email FROM BU_users WHERE id = ?");
                if ($stmt_email) {
                    mysqli_stmt_bind_param($stmt_email, "i", $user_id);
                    mysqli_stmt_execute($stmt_email);
                    mysqli_stmt_bind_result($stmt_email, $current_email);
                    mysqli_stmt_fetch($stmt_email);
                    mysqli_stmt_close($stmt_email);
                }
            }

            if ($success && $new_email !== $current_email) {
                $stmt = mysqli_prepare($savienojums, "SELECT id FROM BU_users WHERE email = ? AND id != ?");
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "si", $new_email, $user_id);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_store_result($stmt);
                    if (mysqli_stmt_num_rows($stmt) > 0) {
                        $success = false;
                        $error_message = 'Šī e-pasta adrese jau ir aizņemta.';
                    }
                    mysqli_stmt_close($stmt);
                }

                if ($success) {
                    $stmt = mysqli_prepare($savienojums, "UPDATE BU_users SET email = ? WHERE id = ?");
                    if ($stmt) {
                        mysqli_stmt_bind_param($stmt, "si", $new_email, $user_id);
                        if (mysqli_stmt_execute($stmt)) {
                            $current_email = $new_email;
                        } else {
                            $success = false;
                            $error_message = 'Kļūda atjauninot e-pastu. Lūdzu mēģiniet vēlāk.';
                        }
                        mysqli_stmt_close($stmt);
                    } else {
                        $success = false;
                        $error_message = 'Kļūda atjauninot e-pastu. Lūdzu mēģiniet vēlāk.';
                    }
                }
            }
        }
    }

    // Save password
    $password_current = trim($_POST['password_current'] ?? '');
    $password_new     = trim($_POST['password_new'] ?? '');
    $password_confirm = trim($_POST['password_confirm'] ?? '');

    if ($password_current !== '' || $password_new !== '' || $password_confirm !== '') {
        if ($password_current === '' || $password_new === '' || $password_confirm === '') {
            $success = false;
            $error_message = 'Lūdzu aizpildiet visus paroles laukus.';
        } elseif ($password_new !== $password_confirm) {
            $success = false;
            $error_message = 'Jaunā parole un tās apstiprinājums nesakrīt.';
        } elseif (strlen($password_new) < 8) {
            $success = false;
            $error_message = 'Jaunajai parolei jābūt vismaz 8 simbolus garai.';
        } elseif ($success) {
            $stmt_pass = mysqli_prepare($savienojums, "SELECT password FROM BU_users WHERE id = ?");
            if ($stmt_pass) {
                mysqli_stmt_bind_param($stmt_pass, "i", $user_id);
                mysqli_stmt_execute($stmt_pass);
                mysqli_stmt_bind_result($stmt_pass, $current_password_hash);
                mysqli_stmt_fetch($stmt_pass);
                mysqli_stmt_close($stmt_pass);

                if (!password_verify($password_current, $current_password_hash)) {
                    $success = false;
                    $error_message = 'Pašreizējā parole ir nepareiza.';
                } else {
                    $new_password_hash = password_hash($password_new, PASSWORD_DEFAULT);
                    $stmt_update_pass = mysqli_prepare($savienojums, "UPDATE BU_users SET password = ? WHERE id = ?");
                    if ($stmt_update_pass) {
                        mysqli_stmt_bind_param($stmt_update_pass, "si", $new_password_hash, $user_id);
                        if (!mysqli_stmt_execute($stmt_update_pass)) {
                            $success = false;
                            $error_message = 'Kļūda saglabājot jauno paroli. Lūdzu mēģiniet vēlāk.';
                        }
                        mysqli_stmt_close($stmt_update_pass);
                    } else {
                        $success = false;
                        $error_message = 'Kļūda saglabājot jauno paroli. Lūdzu mēģiniet vēlāk.';
                    }
                }
            } else {
                $success = false;
                $error_message = 'Kļūda pārbaudot paroli. Lūdzu mēģiniet vēlāk.';
            }
        }
    }

    // Save theme
    $stmt = mysqli_prepare($savienojums,
        "INSERT INTO BU_user_settings (user_id, setting_key, setting_value)
         VALUES (?, 'theme', ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "is", $user_id, $theme);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $_SESSION['theme'] = $theme;
    } else {
        $success = false;
    }

    // Save currency
    $stmt = mysqli_prepare($savienojums,
        "INSERT INTO BU_user_settings (user_id, setting_key, setting_value)
         VALUES (?, 'currency', ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "is", $user_id, $currency);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $_SESSION['currency'] = $currency;
    } else {
        $success = false;
    }

    // Save language
    $allowed_languages = ['lv', 'en'];
    $language = isset($_POST['language']) && in_array($_POST['language'], $allowed_languages) ? $_POST['language'] : 'lv';
    $stmt = mysqli_prepare($savienojums,
        "INSERT INTO BU_user_settings (user_id, setting_key, setting_value)
         VALUES (?, 'language', ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "is", $user_id, $language);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $_SESSION['language'] = $language;
    } else {
        $success = false;
    }

    if ($success) {
        $success_message = 'Iestatījumi saglabāti veiksmīgi!';
    } elseif ($error_message === '') {
        $error_message = 'Kļūda saglabājot iestatījumus.';
    }
}

// ── Load current settings ─────────────────────────────────────────────────────
$current_theme = $_SESSION['theme'] ?? 'dark';
$current_currency = $_SESSION['currency'] ?? 'EUR';
$current_language = $_SESSION['language'] ?? 'lv';

// Try to pull from DB (in case session is stale)
$stmt = mysqli_prepare($savienojums,
    "SELECT setting_key, setting_value FROM BU_user_settings WHERE user_id = ? AND setting_key IN ('theme', 'currency', 'language')");
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        if ($row['setting_key'] === 'theme') {
            $current_theme = $row['setting_value'];
            $_SESSION['theme'] = $current_theme;
        } elseif ($row['setting_key'] === 'currency') {
            $current_currency = $row['setting_value'];
            $_SESSION['currency'] = $current_currency;
        } elseif ($row['setting_key'] === 'language') {
            $current_language = $row['setting_value'];
            $_SESSION['language'] = $current_language;
        }
    }
    mysqli_stmt_close($stmt);
}
$_traw_settings = json_decode(file_get_contents(__DIR__ . '/translate.json'), true) ?? [];

// Load current email from database
$stmt = mysqli_prepare($savienojums, "SELECT email FROM BU_users WHERE id = ?");
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $current_email);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
}
?>
<?php $active_page = 'settings'; ?>
<!DOCTYPE html>
<html lang="lv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iestatījumi - Budgetar</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="../css/settings.css">
    <link rel="icon" href="../../assets/image/logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/lipis/flag-icons@7.2.3/css/flag-icons.min.css">
</head>
<body class="<?php echo $current_theme === 'light' ? 'light-mode' : ''; ?>">
    <div class="dashboard-container">
        <?php include __DIR__ . '/sidebar.php'; ?>

        <main class="dashboard-main">
            <div class="dashboard-header">
                <h1 class="dashboard-title" data-i18n="page.title">Iestatiājumi</h1>
            </div>

            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <i class="fa-solid fa-circle-check"></i>
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>
            <?php if ($error_message): ?>
                <div class="alert alert-error">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <!-- ── Appearance & Currency ────────────────────────────────────────── -->
            <section class="settings-section">
                <div class="settings-section-header">
                    <div class="settings-section-icon">
                        <i class="fa-solid fa-palette"></i>
                    </div>
                    <div>
                        <h2 class="settings-section-title" data-i18n="appearance.title">Izskats</h2>
                        <p class="settings-section-subtitle" data-i18n="appearance.subtitle">Pielāgojiet lietotnes vizuālo stilu un valūtu</p>
                    </div>
                </div>

                <form method="POST" action="" id="settingsForm">
                    <input type="hidden" name="save_settings" value="1">

                    <div class="settings-card">
                        <!-- Theme ──────────────────────────────────────────────────── -->
                        <div class="settings-row">
                            <div class="settings-row-info">
                                <span class="settings-row-label" data-i18n="theme.label">Krāsu shēma</span>
                                <span class="settings-row-desc" data-i18n="theme.desc">Izvēlieties tumšo vai gaišo režīmu</span>
                            </div>
                            <div class="theme-toggle-group">
                                <label class="theme-option <?php echo $current_theme === 'dark' ? 'active' : ''; ?>" id="theme-dark-label">
                                    <input type="radio" name="theme" value="dark"
                                        <?php echo $current_theme === 'dark' ? 'checked' : ''; ?>>
                                    <div class="theme-preview theme-preview-dark">
                                        <div class="preview-sidebar"></div>
                                        <div class="preview-content">
                                            <div class="preview-bar"></div>
                                            <div class="preview-bar short"></div>
                                        </div>
                                    </div>
                                    <span class="theme-label">
                                        <i class="fa-solid fa-moon"></i> <span data-i18n="theme.dark">Tumšais</span>
                                    </span>
                                </label>

                                <label class="theme-option <?php echo $current_theme === 'light' ? 'active' : ''; ?>" id="theme-light-label">
                                    <input type="radio" name="theme" value="light"
                                        <?php echo $current_theme === 'light' ? 'checked' : ''; ?>>
                                    <div class="theme-preview theme-preview-light">
                                        <div class="preview-sidebar"></div>
                                        <div class="preview-content">
                                            <div class="preview-bar"></div>
                                            <div class="preview-bar short"></div>
                                        </div>
                                    </div>
                                    <span class="theme-label">
                                        <i class="fa-solid fa-sun"></i> <span data-i18n="theme.light">Gaišais</span>
                                    </span>
                                </label>
                            </div>
                        </div>

                        <div class="settings-divider"></div>

                        <!-- Currency ────────────────────────────────────────────────── -->
                        <div class="settings-row">
                            <div class="settings-row-info">
                                <span class="settings-row-label" data-i18n="currency.label">Valūta</span>
                                <span class="settings-row-desc" data-i18n="currency.desc">Izvēlieties valūtu budžeta parādīšanai. Šīs izmaiņas ir tikai kosmētiskas un neietekmēs vērtības.</span>
                            </div>
                            <div class="currency-selector">
                                <select name="currency" id="currencySelect" class="currency-select" style="display: none;">
                                    <option value="EUR" <?php echo $current_currency === 'EUR' ? 'selected' : ''; ?>>EUR</option>
                                    <option value="USD" <?php echo $current_currency === 'USD' ? 'selected' : ''; ?>>USD</option>
                                    <option value="GBP" <?php echo $current_currency === 'GBP' ? 'selected' : ''; ?>>GBP</option>
                                    <option value="JPY" <?php echo $current_currency === 'JPY' ? 'selected' : ''; ?>>JPY</option>
                                    <option value="CHF" <?php echo $current_currency === 'CHF' ? 'selected' : ''; ?>>CHF</option>
                                    <option value="INR" <?php echo $current_currency === 'INR' ? 'selected' : ''; ?>>INR</option>
                                    <option value="RUB" <?php echo $current_currency === 'RUB' ? 'selected' : ''; ?>>RUB</option>
                                    <option value="TRY" <?php echo $current_currency === 'TRY' ? 'selected' : ''; ?>>TRY</option>
                                    <option value="KRW" <?php echo $current_currency === 'KRW' ? 'selected' : ''; ?>>KRW</option>
                                </select>
                                <div class="custom-select" id="customCurrencySelect">
                                    <div class="custom-select-trigger">
                                        <span class="custom-select-value" id="customSelectValue">
                                            <i class="fa-solid fa-euro-sign"></i> EUR - Eiro
                                        </span>
                                        <i class="fa-solid fa-chevron-down custom-select-arrow"></i>
                                    </div>
                                    <ul class="custom-options" id="customOptions">
                                        <li class="custom-option" data-value="EUR">
                                            <i class="fa-solid fa-euro-sign"></i> EUR - Eiro
                                        </li>
                                        <li class="custom-option" data-value="USD">
                                            <i class="fa-solid fa-dollar-sign"></i> USD - Dolārs
                                        </li>
                                        <li class="custom-option" data-value="GBP">
                                            <i class="fa-solid fa-sterling-sign"></i> GBP - Sterliņu mārciņa
                                        </li>
                                        <li class="custom-option" data-value="JPY">
                                            <i class="fa-solid fa-yen-sign"></i> JPY - Japānas Jena
                                        </li>
                                        <li class="custom-option" data-value="CHF">
                                            <i class="fa-solid fa-franc-sign"></i> CHF - Šveices Franks
                                        </li>
                                        <li class="custom-option" data-value="INR">
                                            <i class="fa-solid fa-indian-rupee-sign"></i> INR - Indijas Rupija
                                        </li>
                                        <li class="custom-option" data-value="RUB">
                                            <i class="fa-solid fa-ruble-sign"></i> RUB - Krievijas Rublis
                                        </li>
                                        <li class="custom-option" data-value="TRY">
                                            <i class="fa-solid fa-turkish-lira-sign"></i> TRY - Turcijas Lira
                                        </li>
                                        <li class="custom-option" data-value="KRW">
                                            <i class="fa-solid fa-won-sign"></i> KRW - Korejas Vona
                                        </li>
                                    </ul>
                                </div>
                                <div class="currency-preview">
                                    <span class="currency-symbol" id="currencySymbol"></span>
                                </div>
                            </div>
                        </div>
                        <div class="settings-divider"></div>
                        <div class="settings-row">
                            <div class="settings-row-info">
                                <span class="settings-row-label" data-i18n="language.label">Valoda</span>
                                <span class="settings-row-desc" data-i18n="language.desc">Izvēlieties lietotnes valodu</span>
                            </div>
                            <div class="lang-toggle-group">
                                <button type="button" class="lang-btn <?php echo $current_language === 'lv' ? 'active' : ''; ?>" data-lang="lv"><span class="fi fi-lv"></span></button>
                                <button type="button" class="lang-btn <?php echo $current_language === 'en' ? 'active' : ''; ?>" data-lang="en"><span class="fi fi-us"></span></button>
                            </div>
                            <input type="hidden" name="language" id="languageInput" value="<?php echo htmlspecialchars($current_language); ?>">
                        </div>
                    </div>

                    <section class="settings-section">
                        <div class="settings-section-header">
                            <div class="settings-section-icon">
                                <i class="fa-solid fa-user"></i>
                            </div>
                            <div>
                                <h2 class="settings-section-title" data-i18n="account.title">Konts</h2>
                                <p class="settings-section-subtitle" data-i18n="account.subtitle">Mainiet konta lietotājvārdu, kas tiek parādīts visā lietotnē.</p>
                            </div>
                        </div>

                        <div class="settings-card">
                            <div class="settings-row">
                                <div class="settings-row-info">
                                    <span class="settings-row-label" data-i18n="username.label">Lietotājvārds</span>
                                    <span class="settings-row-desc" data-i18n="username.desc">Lietotājvārds jābūt unikālam un vismāz 4 simbolus garam.</span>
                                </div>
                                <div class="settings-row-field">
                                    <input type="text" name="username" id="accountUsername" class="form-input" value="<?php echo htmlspecialchars($username); ?>" required minlength="4" placeholder="Lietotājvārds">
                                </div>
                            </div>
                            <div class="settings-divider"></div>
                            <div class="settings-row">
                                <div class="settings-row-info">
                                    <span class="settings-row-label" data-i18n="email.label">E-pasts</span>
                                    <span class="settings-row-desc" data-i18n="email.desc">Jūsu konta e-pasta adrese. Tā tiek izmantota pieteiķanās un saziņai.</span>
                                </div>
                                <div class="settings-row-field">
                                    <input type="email" name="email" id="accountEmail" class="form-input" value="<?php echo htmlspecialchars($current_email); ?>" required placeholder="E-pasts">
                                </div>
                            </div>
                            <div class="settings-divider"></div>
                            <div class="settings-row">
                                <div class="settings-row-info">
                                    <span class="settings-row-label" data-i18n="password.current.label">Pašreizējā parole</span>
                                    <span class="settings-row-desc" data-i18n="password.current.desc">Ievadiet pašreizējo paroli, lai varētu to mainīt.</span>
                                </div>
                                <div class="settings-row-field">
                                    <input type="password" name="password_current" id="passwordCurrent" class="form-input" placeholder="Pašreizējā parole">
                                </div>
                            </div>
                            <div class="settings-row">
                                <div class="settings-row-info">
                                    <span class="settings-row-label" data-i18n="password.new.label">Jaunā parole</span>
                                    <span class="settings-row-desc" data-i18n="password.new.desc">Parolei jābūt vismāz 8 simbolus garai.</span>
                                </div>
                                <div class="settings-row-field">
                                    <input type="password" name="password_new" id="passwordNew" class="form-input" placeholder="Jaunā parole">
                                </div>
                            </div>
                            <div class="settings-row">
                                <div class="settings-row-info">
                                    <span class="settings-row-label" data-i18n="password.confirm.label">Apstipriniet paroli</span>
                                    <span class="settings-row-desc" data-i18n="password.confirm.desc">Ievadiet jauno paroli vēlreiz, lai apstiprinātu.</span>
                                </div>
                                <div class="settings-row-field">
                                    <input type="password" name="password_confirm" id="passwordConfirm" class="form-input" placeholder="Apstipriniet paroli">
                                </div>
                            </div>
                            <div class="settings-divider"></div>
                                    <div class="settings-row">
                                <div class="settings-row-info">
                                    <span class="settings-row-label" data-i18n="account.reset.label">Atiestatīt kontu</span>
                                    <span class="settings-row-desc" data-i18n="account.reset.desc">Notīra visus budžetus, darījumus un iestatiājumus, bet saglabā jūsu konta informāciju.</span>
                                </div>
                                <div class="settings-row-field">
                                    <button type="button" id="resetAccountBtn" class="btn btn-danger">
                                        <i class="fa-solid fa-rotate-right"></i>
                                        <span data-i18n="account.reset.btn">Atiestatīt kontu</span>
                                    </button>
                                </div>
                            </div>
                            <div class="settings-divider"></div>
                            <div class="settings-row">
                                <div class="settings-row-info">
                                    <span class="settings-row-label" data-i18n="account.delete.label">Dzēst kontu</span>
                                    <span class="settings-row-desc" data-i18n="account.delete.desc">Šī darbība ir neatgriezenīska — visi dati tiks dzēsti.</span>
                                </div>
                                <div class="settings-row-field">
                                    <button type="button" id="deleteAccountBtn" class="btn btn-danger">
                                        <i class="fa-solid fa-trash-can"></i>
                                        <span data-i18n="account.delete.btn">Dzēst kontu</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </section>

                    <div class="settings-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa-solid fa-floppy-disk"></i>
                            <span data-i18n="btn.save">Saglabāt izmaiņas</span>
                        </button>
                    </div>
                </form>
            </section>
        </main>
    </div>

    <?php include __DIR__ . '/mobile_nav.php'; ?>

    <script src="../js/currency.js"></script>
    <script>
        // Initialize currency from PHP session
        if ('<?php echo $current_currency; ?>') {
            localStorage.setItem('budgetar_currency', '<?php echo $current_currency; ?>');
        }
    </script>
    <script src="../js/script.js"></script>
    <script>window._i18nData=<?php echo json_encode($_traw_settings); ?>;window._i18nLang=<?php echo json_encode($current_language); ?>;</script>
    <script src="../js/language.js"></script>
    <script src="../js/settings.js"></script>
</body>
</html>