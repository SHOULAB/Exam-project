<?php
session_start();
if (!isset($_SESSION['user_id']) || !in_array(strtolower($_SESSION['role'] ?? ''), ['administrator', 'moderator'])) {
    header('Location: ../../user/php/login.php');
    exit();
}
require_once('../../assets/database.php');

$user_id  = $_SESSION['user_id'];
$username = $_SESSION['username'];
$current_email = '';

$success_message = '';
$error_message   = '';

// ── AJAX: Change username or email (password-verified) ────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_field'])) {
    header('Content-Type: application/json');

    $field       = $_POST['field']            ?? '';
    $password    = trim($_POST['verify_password'] ?? '');
    $new_value   = trim($_POST['new_value']   ?? '');
    $verify_only = !empty($_POST['verify_only']);

    if (!in_array($field, ['username', 'email'], true)) {
        echo json_encode(['success' => false, 'error' => 'invalid_field']);
        exit();
    }
    if ($password === '') {
        echo json_encode(['success' => false, 'error' => 'empty_fields']);
        exit();
    }
    if (!$verify_only && $new_value === '') {
        echo json_encode(['success' => false, 'error' => 'empty_fields']);
        exit();
    }

    // Verify password
    $stmt = mysqli_prepare($savienojums, "SELECT password FROM BU_users WHERE id = ?");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $hash);
        mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);
    }
    if (empty($hash) || !password_verify($password, $hash)) {
        echo json_encode(['success' => false, 'error' => 'wrong_password']);
        exit();
    }

    if ($verify_only) {
        echo json_encode(['success' => true, 'verified' => true]);
        exit();
    }

    if ($field === 'username') {
        if (strlen($new_value) < 4) {
            echo json_encode(['success' => false, 'error' => 'username_short']);
            exit();
        }
        $stmt = mysqli_prepare($savienojums, "SELECT id FROM BU_users WHERE username = ? AND id != ?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "si", $new_value, $user_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            $taken = mysqli_stmt_num_rows($stmt) > 0;
            mysqli_stmt_close($stmt);
        }
        if ($taken) {
            echo json_encode(['success' => false, 'error' => 'username_taken']);
            exit();
        }
        $stmt = mysqli_prepare($savienojums, "UPDATE BU_users SET username = ? WHERE id = ?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "si", $new_value, $user_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
        $_SESSION['username'] = $new_value;
    } else {
        if (!filter_var($new_value, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'error' => 'invalid_email']);
            exit();
        }
        $stmt = mysqli_prepare($savienojums, "SELECT id FROM BU_users WHERE email = ? AND id != ?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "si", $new_value, $user_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            $taken = mysqli_stmt_num_rows($stmt) > 0;
            mysqli_stmt_close($stmt);
        }
        if ($taken) {
            echo json_encode(['success' => false, 'error' => 'email_taken']);
            exit();
        }
        $stmt = mysqli_prepare($savienojums, "UPDATE BU_users SET email = ? WHERE id = ?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "si", $new_value, $user_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }

    echo json_encode(['success' => true, 'new_value' => htmlspecialchars($new_value)]);
    exit();
}

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
        header('Location: ../../user/php/login.php');
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

        $success_message = 'settings.reset';
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
        $success_message = 'settings.saved';
    } elseif ($error_message === '') {
        $error_message = 'Kļūda saglabājot iestatījumus.';
    }
}

// ── Load current settings ─────────────────────────────────────────────────────
$current_theme    = $_SESSION['theme']    ?? 'dark';
$current_currency = $_SESSION['currency'] ?? 'EUR';
$current_language = $_SESSION['language'] ?? 'lv';
$_langIsDefault   = true;

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
            $_langIsDefault = false;
        }
    }
    mysqli_stmt_close($stmt);
}
$_traw_settings = json_decode(file_get_contents(__DIR__ . '/translate.json'), true) ?? [];
$_t             = $_traw_settings[$current_language] ?? $_traw_settings['lv'] ?? [];

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
    <title>Budgetar</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../../user/css/settings.css">
    <link rel="icon" href="../../assets/image/logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/lipis/flag-icons@7.2.3/css/flag-icons.min.css">
</head>
<body class="<?php echo $current_theme === 'light' ? 'light-mode' : ''; ?>">
    <div class="admin-container">
        <?php include __DIR__ . '/sidebar.php'; ?>

        <main class="admin-content">
            <div class="admin-header">
                <h1 class="admin-title" data-i18n="page.title">Iestatījumi</h1>
            </div>

            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <i class="fa-solid fa-circle-check"></i>
                    <span data-i18n="<?php echo htmlspecialchars($success_message); ?>"><?php echo htmlspecialchars($success_message); ?></span>
                </div>
            <?php endif; ?>
            <?php if ($error_message): ?>
                <div class="alert alert-error">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <!-- ── Appearance & Currency ────────────────────────────────────── -->
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
                        <!-- Theme -->
                        <div class="settings-row">
                            <div class="settings-row-info">
                                <span class="settings-row-label" data-i18n="theme.label">Krāsu shēma</span>
                                <span class="settings-row-desc" data-i18n="theme.desc">Izvēlieties tumšo vai gaišo režīmu</span>
                            </div>
                            <div class="theme-toggle-group">
                                <label class="theme-option <?php echo $current_theme === 'dark' ? 'active' : ''; ?>" id="theme-dark-label">
                                    <input type="radio" name="theme" value="dark" <?php echo $current_theme === 'dark' ? 'checked' : ''; ?>>
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
                                    <input type="radio" name="theme" value="light" <?php echo $current_theme === 'light' ? 'checked' : ''; ?>>
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

                        <!-- Currency -->
                        <div class="settings-row">
                            <div class="settings-row-info">
                                <span class="settings-row-label" data-i18n="currency.label">Valūta</span>
                                <span class="settings-row-desc" data-i18n="currency.desc">Izvēlieties valūtu budžeta parādīšanai.</span>
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
                                        <li class="custom-option" data-value="EUR"><i class="fa-solid fa-euro-sign"></i> EUR - Eiro</li>
                                        <li class="custom-option" data-value="USD"><i class="fa-solid fa-dollar-sign"></i> USD - Dolārs</li>
                                        <li class="custom-option" data-value="GBP"><i class="fa-solid fa-sterling-sign"></i> GBP - Sterliņu mārciņa</li>
                                        <li class="custom-option" data-value="JPY"><i class="fa-solid fa-yen-sign"></i> JPY - Japānas Jena</li>
                                        <li class="custom-option" data-value="CHF"><i class="fa-solid fa-franc-sign"></i> CHF - Šveices Franks</li>
                                        <li class="custom-option" data-value="INR"><i class="fa-solid fa-indian-rupee-sign"></i> INR - Indijas Rupija</li>
                                        <li class="custom-option" data-value="RUB"><i class="fa-solid fa-ruble-sign"></i> RUB - Krievijas Rublis</li>
                                        <li class="custom-option" data-value="TRY"><i class="fa-solid fa-turkish-lira-sign"></i> TRY - Turcijas Lira</li>
                                        <li class="custom-option" data-value="KRW"><i class="fa-solid fa-won-sign"></i> KRW - Korejas Vona</li>
                                    </ul>
                                </div>
                                <div class="currency-preview">
                                    <span class="currency-symbol" id="currencySymbol"></span>
                                </div>
                            </div>
                        </div>

                        <div class="settings-divider"></div>

                        <!-- Language -->
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

                    <!-- ── Account ────────────────────────────────────────────── -->
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
                            <!-- Username -->
                            <div class="settings-row">
                                <div class="settings-row-info">
                                    <span class="settings-row-label" data-i18n="username.label">Lietotājvārds</span>
                                    <span class="settings-row-desc" data-i18n="username.desc">Lietotājvārds jābūt unikālam un vismāz 4 simbolus garam.</span>
                                </div>
                                <div class="settings-row-field settings-row-field--locked">
                                    <button type="button" class="btn btn-change-field" data-field="username" data-i18n="btn.change">Mainīt</button>
                                    <span class="settings-field-value" id="displayUsername"><?php echo htmlspecialchars($username); ?></span>
                                </div>
                            </div>
                            <div class="settings-divider"></div>
                            <!-- Email -->
                            <div class="settings-row">
                                <div class="settings-row-info">
                                    <span class="settings-row-label" data-i18n="email.label">E-pasts</span>
                                    <span class="settings-row-desc" data-i18n="email.desc">Jūsu konta e-pasta adrese.</span>
                                </div>
                                <div class="settings-row-field settings-row-field--locked">
                                    <button type="button" class="btn btn-change-field" data-field="email" data-i18n="btn.change">Mainīt</button>
                                    <span class="settings-field-value" id="displayEmail"><?php echo htmlspecialchars($current_email); ?></span>
                                </div>
                            </div>
                            <div class="settings-divider"></div>
                            <!-- Password collapsed -->
                            <div class="settings-row" id="passwordCollapsedRow">
                                <div class="settings-row-info">
                                    <span class="settings-row-label" data-i18n="password.label">Parole</span>
                                    <span class="settings-row-desc" data-i18n="password.desc">Mainiet sava konta paroli.</span>
                                </div>
                                <div class="settings-row-field settings-row-field--locked">
                                    <button type="button" class="btn btn-change-field" id="expandPasswordBtn" data-i18n="btn.change" data-field="password">Mainīt</button>
                                    <span class="settings-field-value">••••••••</span>
                                </div>
                            </div>
                            <!-- Password expanded -->
                            <div id="passwordExpandedRows" style="display:none;">
                                <div class="settings-divider"></div>
                                <div class="settings-row">
                                    <div class="settings-row-info">
                                        <span class="settings-row-label" data-i18n="password.current.label">Pašreizējā parole</span>
                                        <span class="settings-row-desc" data-i18n="password.current.desc">Ievadiet pašreizējo paroli, lai varētu to mainīt.</span>
                                    </div>
                                    <div class="settings-row-field">
                                        <input type="password" name="password_current" id="passwordCurrent" class="form-input" placeholder="Pašreizējā parole" data-i18n-placeholder="password.current.placeholder">
                                    </div>
                                </div>
                                <div class="settings-row">
                                    <div class="settings-row-info">
                                        <span class="settings-row-label" data-i18n="password.new.label">Jaunā parole</span>
                                        <span class="settings-row-desc" data-i18n="password.new.desc">Parolei jābūt vismāz 8 simbolus garai.</span>
                                    </div>
                                    <div class="settings-row-field">
                                        <input type="password" name="password_new" id="passwordNew" class="form-input" placeholder="Jaunā parole" data-i18n-placeholder="password.new.placeholder">
                                    </div>
                                </div>
                                <div class="settings-row">
                                    <div class="settings-row-info">
                                        <span class="settings-row-label" data-i18n="password.confirm.label">Apstipriniet paroli</span>
                                        <span class="settings-row-desc" data-i18n="password.confirm.desc">Ievadiet jauno paroli vēlreiz, lai apstiprinātu.</span>
                                    </div>
                                    <div class="settings-row-field">
                                        <input type="password" name="password_confirm" id="passwordConfirm" class="form-input" placeholder="Apstipriniet paroli" data-i18n-placeholder="password.confirm.placeholder">
                                    </div>
                                </div>
                                <div class="settings-row" style="padding-top:4px;padding-bottom:20px;">
                                    <div style="flex:1;"></div>
                                    <div class="settings-row-field" style="display:flex;gap:10px;justify-content:flex-end;">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fa-solid fa-floppy-disk"></i>
                                            <span data-i18n="change.modal.save">Saglabāt</span>
                                        </button>
                                        <button type="button" class="btn btn-secondary" id="collapsePasswordBtn" data-i18n="cal.delete.cancel">Atcelt</button>
                                    </div>
                                </div>
                            </div>
                            <div class="settings-divider"></div>
                            <div class="settings-row">
                                <div class="settings-row-info">
                                    <span class="settings-row-label" data-i18n="account.reset.label">Atiestatīt kontu</span>
                                    <span class="settings-row-desc" data-i18n="account.reset.desc">Notīra visus budžetus, darījumus un iestatījumus, bet saglabā konta informāciju.</span>
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

                    <div class="settings-actions" style="display:none;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa-solid fa-floppy-disk"></i>
                            <span data-i18n="btn.save">Saglabāt izmaiņas</span>
                        </button>
                    </div>
                </form>

        </main>
    </div>

    <!-- ── Unsaved changes bar ─────────────────────────────────────────────── -->
    <div id="unsavedBar" class="unsaved-bar" aria-live="polite">
        <span class="unsaved-bar-text" data-i18n="unsaved.warning">Uzmanību — jums ir nesaglabātas izmaiņas!</span>
        <div class="unsaved-bar-actions">
            <button type="button" id="unsavedResetBtn" class="unsaved-bar-reset" data-i18n="unsaved.reset">Atiestatīt</button>
            <button type="button" id="unsavedSaveBtn" class="btn btn-primary unsaved-bar-save" data-i18n="unsaved.save">Saglabāt izmaiņas</button>
        </div>
    </div>

    <!-- ── Change field modal ──────────────────────────────────────────────── -->
    <div id="changeFieldModal" class="modal" role="dialog" aria-modal="true" aria-labelledby="changeFieldTitle">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="changeFieldTitle" data-i18n="change.modal.title">Mainīt</h2>
                <button type="button" class="modal-close" id="changeFieldClose" aria-label="Aizvērt">✕</button>
            </div>
            <div id="cfStep1">
                <div class="modal-body">
                    <p class="cf-hint" data-i18n="change.modal.password.hint">Ievadiet pašreizējo paroli, lai turpinātu.</p>
                    <input type="password" id="cfPassword" class="form-input" autocomplete="current-password"
                           data-i18n-placeholder="password.current.placeholder" placeholder="Pašreizējā parole">
                    <p class="cf-error" id="cfStep1Error" style="display:none;"></p>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" id="cfCancelBtn" data-i18n="cal.delete.cancel">Atcelt</button>
                    <button type="button" class="btn btn-primary" id="cfVerifyBtn">
                        <span data-i18n="change.modal.verify">Turpināt</span>
                    </button>
                </div>
            </div>
            <div id="cfStep2" style="display:none;">
                <div class="modal-body">
                    <p class="cf-hint" id="cfStep2Hint"></p>
                    <input type="text" id="cfNewValue" class="form-input" autocomplete="off">
                    <p class="cf-error" id="cfStep2Error" style="display:none;"></p>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" id="cfBackBtn" data-i18n="change.modal.back">Atpakaļ</button>
                    <button type="button" class="btn btn-primary" id="cfSaveBtn">
                        <span data-i18n="change.modal.save">Saglabāt</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="../../user/js/currency.js"></script>
    <script>
        if ('<?php echo $current_currency; ?>') {
            localStorage.setItem('budgetar_currency', '<?php echo $current_currency; ?>');
        }
        localStorage.setItem('budgetar_theme', '<?php echo $current_theme; ?>');
    </script>
    <script src="../../user/js/script.js"></script>
    <script>window._i18nData=<?php echo json_encode($_traw_settings); ?>;window._i18nLang=<?php echo json_encode($current_language); ?>;window._i18nIsDefault=<?php echo $_langIsDefault ? 'true' : 'false'; ?>;</script>
    <script src="../../user/js/language.js"></script>
    <script src="../../user/js/settings.js"></script>
    <script>
    // ── Password expand/collapse ──────────────────────────────────────────────
    (function () {
        var expandBtn   = document.getElementById('expandPasswordBtn');
        var collapseBtn = document.getElementById('collapsePasswordBtn');
        var collapsed   = document.getElementById('passwordCollapsedRow');
        var expanded    = document.getElementById('passwordExpandedRows');
        if (!expandBtn) return;
        expandBtn.addEventListener('click', function () {
            collapsed.style.display = 'none';
            expanded.style.display  = '';
            var first = document.getElementById('passwordCurrent');
            if (first) setTimeout(function () { first.focus(); }, 40);
        });
        collapseBtn.addEventListener('click', function () {
            expanded.style.display  = 'none';
            collapsed.style.display = '';
            document.getElementById('passwordCurrent').value = '';
            document.getElementById('passwordNew').value     = '';
            document.getElementById('passwordConfirm').value = '';
        });
    })();

    // ── Unsaved changes bar ───────────────────────────────────────────────────
    (function () {
        'use strict';
        var form     = document.getElementById('settingsForm');
        var bar      = document.getElementById('unsavedBar');
        var resetBtn = document.getElementById('unsavedResetBtn');
        var saveBtn  = document.getElementById('unsavedSaveBtn');
        if (!form || !bar) return;

        function snapshot() {
            var map = {};
            Array.from(form.elements).forEach(function (el) {
                if (!el.name) return;
                if (el.type === 'radio') { if (el.checked) map[el.name] = el.value; }
                else if (el.type === 'checkbox') { map[el.name] = el.checked; }
                else { map[el.name] = el.value; }
            });
            return map;
        }
        var original = snapshot();

        function isDirty() {
            var current = snapshot();
            for (var k in original) { if (original[k] !== current[k]) return true; }
            return false;
        }
        function setBar(show) {
            if (show) bar.classList.add('visible');
            else      bar.classList.remove('visible');
        }
        function checkDirty() { setBar(isDirty()); }

        form.addEventListener('input',  checkDirty);
        form.addEventListener('change', checkDirty);

        var customOptions = document.getElementById('customOptions');
        if (customOptions) {
            customOptions.addEventListener('click', function (e) {
                if (e.target.closest('.custom-option')) setTimeout(checkDirty, 0);
            });
        }
        document.querySelectorAll('.lang-btn').forEach(function (btn) {
            btn.addEventListener('click', function () { setTimeout(checkDirty, 0); });
        });

        resetBtn.addEventListener('click', function () {
            Array.from(form.elements).forEach(function (el) {
                if (!el.name || !(el.name in original)) return;
                if (el.type === 'radio') { el.checked = (el.value === original[el.name]); }
                else if (el.type === 'checkbox') { el.checked = original[el.name]; }
                else { el.value = original[el.name]; }
            });
            var themeRadio = form.querySelector('input[name="theme"]:checked');
            if (themeRadio && typeof applyTheme === 'function') applyTheme(themeRadio.value);
            if (typeof window._currencyRestoreDisplay === 'function') window._currencyRestoreDisplay();
            setBar(false);
        });

        saveBtn.addEventListener('click', function () {
            var langInput = document.getElementById('languageInput');
            if (langInput) localStorage.setItem('budgetar_language', langInput.value);
            var themeSelected = form.querySelector('input[name="theme"]:checked');
            if (themeSelected) localStorage.setItem('budgetar_theme', themeSelected.value);
            var currencySelect = document.getElementById('currencySelect');
            if (currencySelect) localStorage.setItem('budgetar_currency', currencySelect.value);
            form.submit();
        });
    })();

    // ── Change-field modal ────────────────────────────────────────────────────
    (function () {
        'use strict';
        var modal     = document.getElementById('changeFieldModal');
        var step1     = document.getElementById('cfStep1');
        var step2     = document.getElementById('cfStep2');
        var titleEl   = document.getElementById('changeFieldTitle');
        var pwdInput  = document.getElementById('cfPassword');
        var newInput  = document.getElementById('cfNewValue');
        var step1Err  = document.getElementById('cfStep1Error');
        var step2Err  = document.getElementById('cfStep2Error');
        var step2Hint = document.getElementById('cfStep2Hint');
        var verifyBtn = document.getElementById('cfVerifyBtn');
        var saveBtn   = document.getElementById('cfSaveBtn');
        var cancelBtn = document.getElementById('cfCancelBtn');
        var backBtn   = document.getElementById('cfBackBtn');
        var closeBtn  = document.getElementById('changeFieldClose');

        var currentField = '', verifiedPassword = '';

        function t(key) {
            if (window._i18n && window._i18n.T) {
                var dict = window._i18n.T[window._i18n.lang] || window._i18n.T['lv'];
                if (dict && dict[key] !== undefined) return dict[key];
            }
            return key;
        }

        function openModal(field) {
            currentField = field; verifiedPassword = '';
            pwdInput.value = ''; newInput.value = '';
            step1Err.style.display = 'none'; step2Err.style.display = 'none';
            step1.style.display = ''; step2.style.display = 'none';
            titleEl.textContent = field === 'username' ? t('change.modal.title.username') : t('change.modal.title.email');
            modal.classList.add('modal-open');
            document.body.style.overflow = 'hidden';
            setTimeout(function () { pwdInput.focus(); }, 60);
        }
        function closeModal() {
            modal.classList.remove('modal-open');
            document.body.style.overflow = '';
        }
        function showError(el, key) { el.textContent = t(key); el.style.display = ''; }
        function hideError(el) { el.style.display = 'none'; }

        document.querySelectorAll('.btn-change-field').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var field = this.dataset.field;
                if (field === 'password') return;
                openModal(field);
            });
        });

        closeBtn.addEventListener('click', closeModal);
        cancelBtn.addEventListener('click', closeModal);
        modal.addEventListener('click', function (e) { if (e.target === modal) closeModal(); });

        function doVerify() {
            hideError(step1Err);
            var pwd = pwdInput.value.trim();
            if (!pwd) { showError(step1Err, 'change.err.empty_password'); return; }
            verifyBtn.disabled = true;
            verifyBtn.querySelector('span').textContent = t('change.modal.checking');
            var fd = new FormData();
            fd.append('change_field', '1'); fd.append('field', currentField);
            fd.append('verify_password', pwd); fd.append('verify_only', '1');
            fetch('settings.php', { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    verifyBtn.disabled = false;
                    verifyBtn.querySelector('span').textContent = t('change.modal.verify');
                    if (!data.success) { showError(step1Err, 'change.err.wrong_password'); return; }
                    verifiedPassword = pwd;
                    step1.style.display = 'none'; step2.style.display = '';
                    step2Hint.textContent = currentField === 'username' ? t('change.modal.new.username') : t('change.modal.new.email');
                    newInput.type = currentField === 'email' ? 'email' : 'text';
                    newInput.placeholder = currentField === 'username' ? t('username.placeholder') : t('email.placeholder');
                    hideError(step2Err); newInput.value = '';
                    setTimeout(function () { newInput.focus(); }, 60);
                })
                .catch(function () {
                    verifyBtn.disabled = false;
                    verifyBtn.querySelector('span').textContent = t('change.modal.verify');
                    showError(step1Err, 'change.err.server');
                });
        }

        verifyBtn.addEventListener('click', doVerify);
        pwdInput.addEventListener('keydown', function (e) { if (e.key === 'Enter') doVerify(); });
        backBtn.addEventListener('click', function () {
            step2.style.display = 'none'; step1.style.display = '';
            setTimeout(function () { pwdInput.focus(); }, 60);
        });

        function doSave() {
            hideError(step2Err);
            var val = newInput.value.trim();
            if (!val) { showError(step2Err, 'change.err.empty_value'); return; }
            saveBtn.disabled = true;
            saveBtn.querySelector('span').textContent = t('change.modal.saving');
            var fd = new FormData();
            fd.append('change_field', '1'); fd.append('field', currentField);
            fd.append('verify_password', verifiedPassword); fd.append('new_value', val);
            fetch('settings.php', { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    saveBtn.disabled = false;
                    saveBtn.querySelector('span').textContent = t('change.modal.save');
                    if (data.success) {
                        if (currentField === 'username') {
                            document.getElementById('displayUsername').textContent = data.new_value;
                            document.querySelectorAll('.user-avatar').forEach(function (el) { el.textContent = data.new_value.charAt(0).toUpperCase(); });
                            document.querySelectorAll('.user-name').forEach(function (el) { el.textContent = data.new_value; });
                        } else {
                            document.getElementById('displayEmail').textContent = data.new_value;
                        }
                        closeModal();
                        showNotification(t('change.success'), 'success');
                    } else {
                        var errMap = { 'username_taken': 'change.err.username_taken', 'email_taken': 'change.err.email_taken', 'invalid_email': 'change.err.invalid_email', 'username_short': 'change.err.username_short', 'wrong_password': 'change.err.wrong_password', 'empty_fields': 'change.err.empty_value' };
                        showError(step2Err, errMap[data.error] || 'change.err.server');
                    }
                })
                .catch(function () {
                    saveBtn.disabled = false;
                    saveBtn.querySelector('span').textContent = t('change.modal.save');
                    showError(step2Err, 'change.err.server');
                });
        }

        saveBtn.addEventListener('click', doSave);
        newInput.addEventListener('keydown', function (e) { if (e.key === 'Enter') doSave(); });
    })();
    </script>
</body>
</html>
