<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
require_once('../../assets/database.php');

$user_id  = $_SESSION['user_id'];
$username = $_SESSION['username'];

// ── Load current currency from DB - always refresh to ensure latest preference ──────────────────────────
$_SESSION['currency'] = 'EUR'; // Default
$stmt = mysqli_prepare($savienojums,
    "SELECT setting_value FROM BU_user_settings WHERE user_id = ? AND setting_key = 'currency'");
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($row = mysqli_fetch_assoc($res)) {
        $_SESSION['currency'] = $row['setting_value'];
    }
    mysqli_stmt_close($stmt);
}

$currencySymbols = [
    'EUR' => '<i class="fa-solid fa-euro-sign"></i>',
    'USD' => '<i class="fa-solid fa-dollar-sign"></i>',
    'GBP' => '<i class="fa-solid fa-sterling-sign"></i>',
    'JPY' => '<i class="fa-solid fa-yen-sign"></i>',
    'CHF' => '<i class="fa-solid fa-franc-sign"></i>',
    'INR' => '<i class="fa-solid fa-indian-rupee-sign"></i>',
    'RUB' => '<i class="fa-solid fa-ruble-sign"></i>',
    'TRY' => '<i class="fa-solid fa-turkish-lira-sign"></i>',
    'KRW' => '<i class="fa-solid fa-won-sign"></i>'
];
$currSymbol = $currencySymbols[$_SESSION['currency']] ?? '<i class="fa-solid fa-euro-sign"></i>';

// --- 1. Monthly income vs expenses (last 12 months) ---
$monthly_data = [];
for ($i = 11; $i >= 0; $i--) {
    $ts = strtotime("-$i months");
    $m  = date('n', $ts);
    $y  = date('Y', $ts);
    $monthly_data[] = ['month' => $m, 'year' => $y, 'label' => date('M Y', $ts), 'income' => 0, 'expense' => 0];
}

$stmt = mysqli_prepare($savienojums,
    "SELECT MONTH(date) as m, YEAR(date) as y, type, SUM(amount) as total
     FROM BU_transactions
     WHERE user_id = ? AND date >= DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 11 MONTH), '%Y-%m-01')
     GROUP BY YEAR(date), MONTH(date), type");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($res)) {
    foreach ($monthly_data as &$md) {
        if ($md['month'] == $row['m'] && $md['year'] == $row['y']) {
            $md[$row['type']] = floatval($row['total']);
        }
    }
}
mysqli_stmt_close($stmt);
unset($md);

// --- 2. Running balance over last 12 months ---
$running_balance = 0;
$window_start = date('Y-m-01', strtotime('-11 months'));
$stmt2 = mysqli_prepare($savienojums,
    "SELECT type, SUM(amount) as total FROM BU_transactions WHERE user_id = ? AND date < ? GROUP BY type");
mysqli_stmt_bind_param($stmt2, "is", $user_id, $window_start);
mysqli_stmt_execute($stmt2);
$res2 = mysqli_stmt_get_result($stmt2);
while ($row = mysqli_fetch_assoc($res2)) {
    $running_balance += ($row['type'] === 'income' ? 1 : -1) * floatval($row['total']);
}
mysqli_stmt_close($stmt2);

$balance_trend = [];
foreach ($monthly_data as $md) {
    $running_balance += $md['income'] - $md['expense'];
    $balance_trend[] = round($running_balance, 2);
}

// --- 3. Recurring vs one-time ---
$stmt3 = mysqli_prepare($savienojums,
    "SELECT is_recurring, type, SUM(amount) as total FROM BU_transactions WHERE user_id = ? GROUP BY is_recurring, type");
mysqli_stmt_bind_param($stmt3, "i", $user_id);
mysqli_stmt_execute($stmt3);
$res3 = mysqli_stmt_get_result($stmt3);
$rec = ['recurring_income' => 0, 'recurring_expense' => 0, 'onetime_income' => 0, 'onetime_expense' => 0];
while ($row = mysqli_fetch_assoc($res3)) {
    $key = ($row['is_recurring'] ? 'recurring' : 'onetime') . '_' . $row['type'];
    $rec[$key] = floatval($row['total']);
}
mysqli_stmt_close($stmt3);

// --- 4. All-time summary stats ---
$stmt4 = mysqli_prepare($savienojums,
    "SELECT type, SUM(amount) as total, COUNT(*) as cnt FROM BU_transactions WHERE user_id = ? GROUP BY type");
mysqli_stmt_bind_param($stmt4, "i", $user_id);
mysqli_stmt_execute($stmt4);
$res4 = mysqli_stmt_get_result($stmt4);
$alltime = ['income' => 0, 'expense' => 0, 'income_count' => 0, 'expense_count' => 0];
while ($row = mysqli_fetch_assoc($res4)) {
    $alltime[$row['type']]            = floatval($row['total']);
    $alltime[$row['type'] . '_count'] = intval($row['cnt']);
}
mysqli_stmt_close($stmt4);
$alltime_balance = $alltime['income'] - $alltime['expense'];
$savings_rate    = $alltime['income'] > 0 ? round(($alltime_balance / $alltime['income']) * 100, 1) : 0;

// --- 5. Best and worst month ---
$best_month  = $monthly_data[0];
$worst_month = $monthly_data[0];
foreach ($monthly_data as $md) {
    if (($md['income'] - $md['expense']) > ($best_month['income']  - $best_month['expense']))  $best_month  = $md;
    if (($md['income'] - $md['expense']) < ($worst_month['income'] - $worst_month['expense'])) $worst_month = $md;
}

// --- 6. Average monthly income/expense ---
$avg_income  = count($monthly_data) > 0 ? array_sum(array_column($monthly_data, 'income'))  / count($monthly_data) : 0;
$avg_expense = count($monthly_data) > 0 ? array_sum(array_column($monthly_data, 'expense')) / count($monthly_data) : 0;

// --- 7. Income/expense ratio ---
$ratio = $alltime['expense'] > 0 ? round($alltime['income'] / $alltime['expense'], 2) : '∞';
?>
<?php $active_page = 'parskati'; ?>
<!DOCTYPE html>
<html lang="lv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pārskati - Budgetiva</title>
    <link rel="icon" href="../../assets/image/logo.png" type="image/png">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="../css/reports.css">
    <link rel="stylesheet" href="../css/settings.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
</head>
<<body class="<?php echo (($_SESSION['theme'] ?? 'dark') === 'light') ? 'light-mode' : ''; ?>">
<div class="dashboard-container">

    <!-- Sidebar -->
    <?php include __DIR__ . '/sidebar.php'; ?>

    <!-- Main content -->
    <main class="reports-main">
        <div class="reports-header">
            <h1 class="reports-title">Finanšu Pārskati</h1>
            <p class="reports-subtitle">Pēdējo 12 mēnešu analīze un kopsavilkums</p>
        </div>

        <!-- Summary stat cards -->
        <div class="summary-grid">
            <div class="summary-card">
                <div class="summary-card-label">Kopējie ienākumi</div>
                <div class="summary-card-value val-income"><?php echo $currSymbol; ?><?php echo number_format($alltime['income'], 2); ?></div>
                <div class="summary-card-sub"><?php echo $alltime['income_count']; ?> transakcijas</div>
            </div>
            <div class="summary-card">
                <div class="summary-card-label">Kopējie izdevumi</div>
                <div class="summary-card-value val-expense"><?php echo $currSymbol; ?><?php echo number_format($alltime['expense'], 2); ?></div>
                <div class="summary-card-sub"><?php echo $alltime['expense_count']; ?> transakcijas</div>
            </div>
            <div class="summary-card">
                <div class="summary-card-label">Kopējā bilance</div>
                <div class="summary-card-value val-balance"><?php echo $currSymbol; ?><?php echo number_format($alltime_balance, 2); ?></div>
                <div class="summary-card-sub">Visu laiku</div>
            </div>
            <div class="summary-card">
                <div class="summary-card-label">Uzkrājumu koeficients</div>
                <div class="summary-card-value val-savings"><?php echo $savings_rate; ?>%</div>
                <div class="savings-rate-bar">
                    <div class="savings-rate-fill" style="width: <?php echo max(0, min(100, $savings_rate)); ?>%"></div>
                </div>
            </div>
            <div class="summary-card">
                <div class="summary-card-label">Vidējie mēneša ienākumi</div>
                <div class="summary-card-value val-income"><?php echo $currSymbol; ?><?php echo number_format($avg_income, 2); ?></div>
                <div class="summary-card-sub">Pēdējie 12 mēneši</div>
            </div>
            <div class="summary-card">
                <div class="summary-card-label">Vidējie mēneša izdevumi</div>
                <div class="summary-card-value val-expense"><?php echo $currSymbol; ?><?php echo number_format($avg_expense, 2); ?></div>
                <div class="summary-card-sub">Pēdējie 12 mēneši</div>
            </div>
        </div>

        <!-- Key insights -->
        <div class="insights-grid">
            <div class="insight-card">
                <div class="insight-icon green"><i class="fa-solid fa-trophy"></i></div>
                <div>
                    <div class="insight-label">Labākais mēnesis</div>
                    <div class="insight-value"><?php echo $best_month['label']; ?></div>
                    <div class="insight-meta">Uzkrājums: <?php echo $currSymbol; ?><?php echo number_format($best_month['income'] - $best_month['expense'], 2); ?></div>
                </div>
            </div>
            <div class="insight-card">
                <div class="insight-icon red"><i class="fa-solid fa-fire"></i></div>
                <div>
                    <div class="insight-label">Sliktākais mēnesis</div>
                    <div class="insight-value"><?php echo $worst_month['label']; ?></div>
                    <div class="insight-meta">Bilance: <?php echo $currSymbol; ?><?php echo number_format($worst_month['income'] - $worst_month['expense'], 2); ?></div>
                </div>
            </div>
            <div class="insight-card">
                <div class="insight-icon purple"><i class="fa-solid fa-arrow-trend-up"></i></div>
                <div>
                    <div class="insight-label">Ikmēneša transakcijas</div>
                    <div class="insight-value"><?php echo ($rec['recurring_income'] + $rec['recurring_expense']) > 0 ? 'Aktīvas' : 'Nav'; ?></div>
                    <div class="insight-meta">
                        Ienākumi: <?php echo $currSymbol; ?><?php echo number_format($rec['recurring_income'], 2); ?> |
                        Izdevumi: <?php echo $currSymbol; ?><?php echo number_format($rec['recurring_expense'], 2); ?>
                    </div>
                </div>
            </div>
            <div class="insight-card">
                <div class="insight-icon yellow"><i class="fa-solid fa-scale-balanced"></i></div>
                <div>
                    <div class="insight-label">Ienākumi vs Izdevumi</div>
                    <div class="insight-value"><?php echo $ratio; ?>x</div>
                    <div class="insight-meta">Par katru <?php echo $currSymbol; ?>1 izdevumiem nopelnīti <?php echo $currSymbol; ?><?php echo $ratio; ?></div>
                </div>
            </div>
        </div>

        <!-- Charts -->
        <div class="charts-grid">

            <!-- 1. Grouped bar — income vs expense -->
            <div class="chart-card chart-full">
                <div class="chart-card-header">
                    <div>
                        <div class="chart-card-title"><i class="fa-solid fa-chart-bar"></i> Ienākumi pret Izdevumiem</div>
                        <div class="chart-card-subtitle">Mēneša salīdzinājums — pēdējie 12 mēneši</div>
                    </div>
                </div>
                <div class="chart-wrapper">
                    <canvas id="barChart" height="90"></canvas>
                </div>
            </div>

            <!-- 2. Line — running balance -->
            <div class="chart-card">
                <div class="chart-card-header">
                    <div>
                        <div class="chart-card-title"><i class="fa-solid fa-chart-line"></i> Bilances dinamika</div>
                        <div class="chart-card-subtitle">Kumulatīvā bilance pa mēnešiem</div>
                    </div>
                </div>
                <div class="chart-wrapper">
                    <canvas id="lineChart" height="180"></canvas>
                </div>
            </div>

            <!-- 3. Bar — monthly net savings -->
            <div class="chart-card">
                <div class="chart-card-header">
                    <div>
                        <div class="chart-card-title"><i class="fa-solid fa-piggy-bank"></i> Mēneša uzkrājums</div>
                        <div class="chart-card-subtitle">Pozitīvs = uzkrājums, negatīvs = deficīts</div>
                    </div>
                </div>
                <div class="chart-wrapper">
                    <canvas id="savingsChart" height="180"></canvas>
                </div>
            </div>

            <!-- 4. Donut — recurring vs one-time -->
            <div class="chart-card">
                <div class="chart-card-header">
                    <div>
                        <div class="chart-card-title"><i class="fa-solid fa-rotate"></i> Ikmēneša vs Vienreizējas</div>
                        <div class="chart-card-subtitle">Transakciju sadalījums pēc veida</div>
                    </div>
                </div>
                <div class="chart-wrapper" style="max-width:280px;margin:0 auto;">
                    <canvas id="donutChart" height="280"></canvas>
                </div>
                <div class="donut-legend">
                    <div class="legend-item">
                        <span class="legend-label"><span class="legend-dot" style="background:#10b981"></span>Ikmēneša ienākumi</span>
                        <span class="legend-val"><?php echo $currSymbol; ?><?php echo number_format($rec['recurring_income'], 2); ?></span>
                    </div>
                    <div class="legend-item">
                        <span class="legend-label"><span class="legend-dot" style="background:#34d399"></span>Vienreizēji ienākumi</span>
                        <span class="legend-val"><?php echo $currSymbol; ?><?php echo number_format($rec['onetime_income'], 2); ?></span>
                    </div>
                    <div class="legend-item">
                        <span class="legend-label"><span class="legend-dot" style="background:#ef4444"></span>Ikmēneša izdevumi</span>
                        <span class="legend-val"><?php echo $currSymbol; ?><?php echo number_format($rec['recurring_expense'], 2); ?></span>
                    </div>
                    <div class="legend-item">
                        <span class="legend-label"><span class="legend-dot" style="background:#fca5a5"></span>Vienreizēji izdevumi</span>
                        <span class="legend-val"><?php echo $currSymbol; ?><?php echo number_format($rec['onetime_expense'], 2); ?></span>
                    </div>
                </div>
            </div>

            <!-- 5. Area — income vs expense over time -->
            <div class="chart-card chart-full">
                <div class="chart-card-header">
                    <div>
                        <div class="chart-card-title"><i class="fa-solid fa-wave-square"></i> Finanšu plūsma</div>
                        <div class="chart-card-subtitle">Ienākumu un izdevumu apjoms laikā</div>
                    </div>
                </div>
                <div class="chart-wrapper">
                    <canvas id="areaChart" height="90"></canvas>
                </div>
            </div>

        </div>
    </main>
</div>

<?php include __DIR__ . '/mobile_nav.php'; ?>

<!-- Pass PHP data to JS -->
<script>
    const labels        = <?php echo json_encode(array_column($monthly_data, 'label')); ?>;
    const income        = <?php echo json_encode(array_column($monthly_data, 'income')); ?>;
    const expense       = <?php echo json_encode(array_column($monthly_data, 'expense')); ?>;
    const trend         = <?php echo json_encode($balance_trend); ?>;
    const recurringData = <?php echo json_encode($rec); ?>;
</script>
<script src="../js/currency.js"></script>
<script>
    // Initialize currency from PHP session
    if ('<?php echo $_SESSION['currency'] ?? 'EUR'; ?>') {
        localStorage.setItem('budgetiva_currency', '<?php echo $_SESSION['currency'] ?? 'EUR'; ?>');
    }
</script>
<script src="../js/parskati.js"></script>
</body>
</html>