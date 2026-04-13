<?php
session_start();
require_once('../../assets/database.php');

if (!isset($_SESSION['user_id']) || !in_array(strtolower($_SESSION['role'] ?? ''), ['administrator', 'moderator'])) {
    header("Location: ../../user/php/login.php");
    exit();
}

$stats = [
    'total_users'        => 0,
    'total_budget_count' => 0,
    'total_transactions' => 0,
    'tx_this_month'      => 0,
    'total_income'       => 0,
    'total_expenses'     => 0
];

$result = mysqli_query($savienojums, "SELECT COUNT(*) as count FROM BU_users");
if ($result) {
    $row = mysqli_fetch_assoc($result);
    $stats['total_users'] = $row['count'];
}

$result = mysqli_query($savienojums, "SELECT COUNT(*) as count FROM BU_budgets");
if ($result) {
    $row = mysqli_fetch_assoc($result);
    $stats['total_budget_count'] = $row['count'];
}

$result = mysqli_query($savienojums, "SELECT COUNT(*) as count FROM BU_transactions");
if ($result) {
    $row = mysqli_fetch_assoc($result);
    $stats['total_transactions'] = $row['count'];
}

$result = mysqli_query($savienojums, "SELECT COUNT(*) as count FROM BU_transactions WHERE date >= DATE_FORMAT(NOW(), '%Y-%m-01')");
if ($result) {
    $row = mysqli_fetch_assoc($result);
    $stats['tx_this_month'] = $row['count'];
}

// Recently registered accounts
$recent_registered = [];
$result = mysqli_query($savienojums, "SELECT username, email, role, created_at FROM BU_users ORDER BY created_at DESC LIMIT 10");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $recent_registered[] = $row;
    }
}

// Recently logged in accounts
$recent_logins = [];
$result = mysqli_query($savienojums, "SELECT username, email, role, last_login FROM BU_users WHERE last_login IS NOT NULL ORDER BY last_login DESC LIMIT 10");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $recent_logins[] = $row;
    }
}

$system_info = [
    'php_version'      => phpversion(),
    'server_software'  => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'database_version' => mysqli_get_server_info($savienojums)
];

// DB latency
$_t0 = microtime(true);
mysqli_query($savienojums, 'SELECT 1');
$db_latency_ms = round((microtime(true) - $_t0) * 1000, 2);
if ($db_latency_ms >= 100) {
    $latency_level = 'critical';
} elseif ($db_latency_ms >= 40) {
    $latency_level = 'warning';
} else {
    $latency_level = 'online';
}

// ── Load language + translations ──────────────────────────────────────────────
$_lang = $_SESSION['language'] ?? 'lv';
$_traw = json_decode(file_get_contents(__DIR__ . '/translate.json'), true) ?? [];
$_t    = $_traw[$_lang] ?? $_traw['lv'] ?? [];
?>
<!DOCTYPE html>
<html lang="lv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Budgetar</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="icon" href="../../assets/image/logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">
</head>
<body class="<?php echo (($_SESSION['theme'] ?? 'dark') === 'light') ? 'light-mode' : ''; ?>">
    <div class="admin-container">
        <?php $active_page = 'dashboard'; include 'sidebar.php'; ?>

        <main class="admin-content">
            <div class="admin-header">
                <h1 class="admin-title" data-i18n="dashboard.page.title"><?php echo $_t['dashboard.page.title'] ?? 'Dashboard'; ?></h1>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div>
                            <div class="stat-card-label" data-i18n="dashboard.stat.total.users"><?php echo $_t['dashboard.stat.total.users'] ?? 'Kopā lietotāji'; ?></div>
                            <div class="stat-card-value"><?php echo number_format($stats['total_users']); ?></div>
                        </div>
                        <div class="stat-card-icon"><i class="fa-solid fa-user"></i></div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-header">
                        <div>
                            <div class="stat-card-label" data-i18n="dashboard.stat.total.budget"><?php echo $_t['dashboard.stat.total.budget'] ?? 'Kopējie budžeti'; ?></div>
                            <div class="stat-card-value"><?php echo number_format($stats['total_budget_count']); ?></div>
                        </div>
                        <div class="stat-card-icon"><i class="fa-solid fa-wallet"></i></div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-header">
                        <div>
                            <div class="stat-card-label" data-i18n="dashboard.stat.tx"><?php echo $_t['dashboard.stat.tx'] ?? 'Transakcijas'; ?></div>
                            <div class="stat-card-value"><?php echo number_format($stats['total_transactions']); ?></div>
                        </div>
                        <div class="stat-card-icon"><i class="fa-solid fa-credit-card"></i></div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-header">
                        <div>
                            <div class="stat-card-label" data-i18n="dashboard.stat.tx.month"><?php echo $_t['dashboard.stat.tx.month'] ?? 'Transakcijas šomēneš'; ?></div>
                            <div class="stat-card-value"><?php echo number_format($stats['tx_this_month']); ?></div>
                        </div>
                        <div class="stat-card-icon"><i class="fa-solid fa-calendar-day"></i></div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-header">
                        <div>
                            <div class="stat-card-label" data-i18n="dashboard.stat.status"><?php echo $_t['dashboard.stat.status'] ?? 'Sistēmas statuss'; ?></div>
                            <div class="stat-card-value stat-status--<?php echo $latency_level; ?>" style="font-size: 26px;">
                                <span class="status-dot"></span><?php echo $db_latency_ms; ?> ms
                            </div>
                            <div class="stat-card-subinfo">PHP <?php echo htmlspecialchars($system_info['php_version']); ?> &middot; MySQL <?php echo htmlspecialchars($system_info['database_version']); ?></div>
                        </div>
                        <div class="stat-card-icon"><i class="fa-solid fa-database"></i></div>
                    </div>
                </div>
            </div>

            <div class="info-panels">
                <!-- Recently registered -->
                <div class="info-panel">
                    <div class="info-panel-header">
                        <i class="fa-solid fa-user-plus"></i>
                        <h2 class="info-panel-title" data-i18n="dashboard.recent.registered"><?php echo $_t['dashboard.recent.registered'] ?? 'Jaunākie lietotāji'; ?></h2>
                    </div>
                    <?php if (empty($recent_registered)): ?>
                        <div class="info-panel-empty" data-i18n="dashboard.recent.empty"><?php echo $_t['dashboard.recent.empty'] ?? 'Nav datu'; ?></div>
                    <?php else: ?>
                    <table class="info-table">
                        <thead>
                            <tr>
                                <th data-i18n="users.table.col.user"><?php echo $_t['users.table.col.user'] ?? 'Lietotājs'; ?></th>
                                <th data-i18n="users.table.col.created"><?php echo $_t['users.table.col.created'] ?? 'Reģistrācijas datums'; ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_registered as $u): ?>
                            <tr>
                                <td>
                                    <div class="info-user">
                                        <div class="info-avatar"><?php echo strtoupper(substr($u['username'], 0, 1)); ?></div>
                                        <div>
                                            <div class="info-username"><?php echo htmlspecialchars($u['username']); ?></div>
                                            <div class="info-email"><?php echo htmlspecialchars($u['email']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="info-date"><?php echo date('d.m.Y H:i', strtotime($u['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>

                <!-- Recently logged in -->
                <div class="info-panel">
                    <div class="info-panel-header">
                        <i class="fa-solid fa-right-to-bracket"></i>
                        <h2 class="info-panel-title" data-i18n="dashboard.recent.logins"><?php echo $_t['dashboard.recent.logins'] ?? 'Pēdējās pieslēgšanās'; ?></h2>
                    </div>
                    <?php if (empty($recent_logins)): ?>
                        <div class="info-panel-empty" data-i18n="dashboard.recent.empty"><?php echo $_t['dashboard.recent.empty'] ?? 'Nav datu'; ?></div>
                    <?php else: ?>
                    <table class="info-table">
                        <thead>
                            <tr>
                                <th data-i18n="users.table.col.user"><?php echo $_t['users.table.col.user'] ?? 'Lietotājs'; ?></th>
                                <th data-i18n="users.table.col.last.login"><?php echo $_t['users.table.col.last.login'] ?? 'Pēdējā pieslēgšanās'; ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_logins as $u): ?>
                            <tr>
                                <td>
                                    <div class="info-user">
                                        <div class="info-avatar"><?php echo strtoupper(substr($u['username'], 0, 1)); ?></div>
                                        <div>
                                            <div class="info-username"><?php echo htmlspecialchars($u['username']); ?></div>
                                            <div class="info-email"><?php echo htmlspecialchars($u['email']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="info-date"><?php echo date('d.m.Y H:i', strtotime($u['last_login'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script src="../js/script.js"></script>
    <script>window._i18nData=<?php echo json_encode($_traw); ?>;window._i18nLang=<?php echo json_encode($_lang); ?>;window._i18nIsDefault=false;</script>
    <script src="../../user/js/language.js"></script>
</body>
</html>