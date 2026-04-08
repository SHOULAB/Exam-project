<?php
// budget.php - User Budget Management
session_start();

require_once('../../assets/database.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id  = $_SESSION['user_id'];
$username = $_SESSION['username'];
$error    = '';
$success  = '';

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

// ── Load language + translations ──────────────────────────────────────────────
$_SESSION['language'] = $_SESSION['language'] ?? 'lv';
$stmt_lang = mysqli_prepare($savienojums,
    "SELECT setting_value FROM BU_user_settings WHERE user_id = ? AND setting_key = 'language'");
if ($stmt_lang) {
    mysqli_stmt_bind_param($stmt_lang, "i", $user_id);
    mysqli_stmt_execute($stmt_lang);
    $res_lang = mysqli_stmt_get_result($stmt_lang);
    if ($row_lang = mysqli_fetch_assoc($res_lang)) {
        $_SESSION['language'] = $row_lang['setting_value'];
    }
    mysqli_stmt_close($stmt_lang);
}
$_lang = $_SESSION['language'];
$_traw = json_decode(file_get_contents(__DIR__ . '/translate.json'), true) ?? [];
$_t    = $_traw[$_lang] ?? $_traw['lv'] ?? [];

// Flash messages from Post-Redirect-Get
$msg_map = [
    'added'   => 'Budžets veiksmīgi pievienots!',
    'deleted' => 'Budžets veiksmīgi dzēsts!',
    'updated' => 'Budžets veiksmīgi atjaunināts!',
];
if (isset($_GET['msg']) && array_key_exists($_GET['msg'], $msg_map)) {
    $success = $msg_map[$_GET['msg']];
}

// ─── Helper: "1,5,6" → "Mon, Fri, Sat" ──────────────────────────────────────
function recurringDayLabel(string $csv): string {
    $names = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    $days  = array_filter(explode(',', $csv), fn($d) => $d !== '');
    sort($days);
    return implode(', ', array_map(fn($d) => $names[(int)$d] ?? '?', $days));
}

// ─── Helper: calculate start/end dates from day CSV (mirrors JS logic) ───────
// Given "5,6,0" (Fri, Sat, Sun), returns the upcoming Mon-anchored week dates.
function calcRecurringDatesPHP(string $csv): array {
    $days = array_map('intval', array_filter(explode(',', $csv), fn($d) => $d !== ''));
    if (empty($days)) {
        return ['start' => date('Y-m-d'), 'end' => date('Y-m-d')];
    }

    // Monday of the current week (Mon-anchored)
    $today  = new DateTime('today');
    $dow    = (int)$today->format('N'); // 1=Mon … 7=Sun
    $monday = (clone $today)->modify('-' . ($dow - 1) . ' days');

    // Map JS day indices (0=Sun,1=Mon…6=Sat) to Mon-anchored offsets (Mon=0…Sun=6)
    $candidates = [];
    foreach ($days as $d) {
        $offset = ($d === 0) ? 6 : $d - 1;   // Sun→6, Mon→0, …, Sat→5
        $candidates[] = (clone $monday)->modify("+{$offset} days");
    }

    // If all dates are in the past, shift the whole set forward 7 days
    $allPast = true;
    foreach ($candidates as $c) {
        if ($c >= $today) { $allPast = false; break; }
    }
    if ($allPast) {
        $candidates = array_map(fn($c) => (clone $c)->modify('+7 days'), $candidates);
    }

    usort($candidates, fn($a, $b) => $a <=> $b);

    return [
        'start' => $candidates[0]->format('Y-m-d'),
        'end'   => end($candidates)->format('Y-m-d'),
    ];
}

// ─── Auto-refresh expired recurring budgets ───────────────────────────────────
function refreshRecurringBudgets($conn, $uid): void {
    // Guard: silently skip if migration columns don't exist yet
    $check = mysqli_query($conn, "SHOW COLUMNS FROM BU_budgets LIKE 'is_recurring'");
    if (!$check || mysqli_num_rows($check) === 0) return;
    $check2 = mysqli_query($conn, "SHOW COLUMNS FROM BU_budgets LIKE 'quarter_label'");
    $has_quarter = ($check2 && mysqli_num_rows($check2) > 0);

    $today = date('Y-m-d');

    $quarter_filter = $has_quarter ? " AND (quarter_label IS NULL OR quarter_label = '')" : '';

    $stmt = mysqli_prepare($conn,
        "SELECT id, start_date, end_date, recurring_days
         FROM   BU_budgets
         WHERE  user_id = ? AND is_recurring = 1 AND end_date < ?
                {$quarter_filter}");
    mysqli_stmt_bind_param($stmt, "is", $uid, $today);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $toUpdate = [];
    while ($row = mysqli_fetch_assoc($result)) { $toUpdate[] = $row; }
    mysqli_stmt_close($stmt);

    foreach ($toUpdate as $budget) {
        // Recalculate from the day pattern rather than blindly +7
        $dates    = calcRecurringDatesPHP($budget['recurring_days']);
        $newStart = $dates['start'];
        $newEnd   = $dates['end'];

        $upd = mysqli_prepare($conn,
            "UPDATE BU_budgets SET start_date = ?, end_date = ? WHERE id = ?");
        mysqli_stmt_bind_param($upd, "ssi", $newStart, $newEnd, $budget['id']);
        mysqli_stmt_execute($upd);
        mysqli_stmt_close($upd);
    }
}

refreshRecurringBudgets($savienojums, $user_id);

// ─── Ensure quarterly columns exist (safe migration) ─────────────────────────
mysqli_query($savienojums, "ALTER TABLE BU_budgets ADD COLUMN IF NOT EXISTS quarter_label VARCHAR(2) NULL DEFAULT NULL");
mysqli_query($savienojums, "ALTER TABLE BU_budgets ADD COLUMN IF NOT EXISTS recurring_group_id VARCHAR(36) NULL DEFAULT NULL");

// ─── Handle POST actions ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // ── ADD ──────────────────────────────────────────────────────────────────
    if ($action === 'add') {
        $budget_name       = trim($_POST['budget_name'] ?? '');
        $budget_amount     = floatval($_POST['budget_amount'] ?? 0);
        $budget_period     = $_POST['budget_period'] ?? 'monthly';
        $warning_threshold = floatval($_POST['warning_threshold'] ?? 80);

        $recurring_days = trim($_POST['recurring_days'] ?? '');
        $is_recurring   = ($recurring_days !== '') ? 1 : 0;

        if ($recurring_days !== '' && !preg_match('/^[0-6](,[0-6])*$/', $recurring_days)) {
            $recurring_days = '';
            $is_recurring   = 0;
        }

        if (empty($budget_name) || $budget_amount <= 0) {
            $error = 'Lūdzu aizpildiet visus obligātos laukus!';
        } elseif ($is_recurring) {
            // ── Create 4 quarterly instances for the current year ─────────────
            $year     = date('Y');
            $quarters = [
                'Q1' => [$year . '-01-01', $year . '-03-31'],
                'Q2' => [$year . '-04-01', $year . '-06-30'],
                'Q3' => [$year . '-07-01', $year . '-09-30'],
                'Q4' => [$year . '-10-01', $year . '-12-31'],
            ];
            $group_id = uniqid('qg_', true);
            $all_ok   = true;

            foreach ($quarters as $q_label => [$q_start, $q_end]) {
                $period = 'quarterly';
                $stmt = mysqli_prepare($savienojums,
                    "INSERT INTO BU_budgets
                        (user_id, budget_name, budget_amount, budget_period,
                         start_date, end_date, warning_threshold,
                         recurring_days, is_recurring, quarter_label,
                         recurring_group_id, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                mysqli_stmt_bind_param($stmt, "isdsssdsiss",
                    $user_id, $budget_name, $budget_amount, $period,
                    $q_start, $q_end, $warning_threshold,
                    $recurring_days, $is_recurring, $q_label, $group_id);

                if (!mysqli_stmt_execute($stmt)) $all_ok = false;
                mysqli_stmt_close($stmt);
            }

            if ($all_ok) {
                header('Location: budget.php?msg=added');
                exit();
            } else {
                $error = 'Kļūda pievienojot budžetu!';
            }
        } else {
            $start_date = trim($_POST['start_date'] ?? '');
            $end_date   = trim($_POST['end_date']   ?? '');

            if (empty($start_date) || empty($end_date)) {
                $error = 'Lūdzu norādiet sākuma un beigu datumus!';
            } elseif ($end_date < $start_date) {
                $error = 'Beigu datums nevar būt pirms sākuma datuma!';
            } else {
                $stmt = mysqli_prepare($savienojums,
                    "INSERT INTO BU_budgets
                        (user_id, budget_name, budget_amount, budget_period,
                         start_date, end_date, warning_threshold,
                         recurring_days, is_recurring, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                mysqli_stmt_bind_param($stmt, "isdsssdsi",
                    $user_id, $budget_name, $budget_amount, $budget_period,
                    $start_date, $end_date, $warning_threshold,
                    $recurring_days, $is_recurring);

                if (mysqli_stmt_execute($stmt)) {
                    mysqli_stmt_close($stmt);
                    header('Location: budget.php?msg=added');
                    exit();
                } else {
                    $error = 'Kļūda pievienojot budžetu!';
                    mysqli_stmt_close($stmt);
                }
            }
        }
    }

    // ── DELETE ────────────────────────────────────────────────────────────────
    if ($action === 'delete' && isset($_POST['budget_id'])) {
        $budget_id = intval($_POST['budget_id']);

        // Check if this budget belongs to a quarterly group
        $fetch = mysqli_prepare($savienojums,
            "SELECT recurring_group_id FROM BU_budgets WHERE id = ? AND user_id = ?");
        mysqli_stmt_bind_param($fetch, "ii", $budget_id, $user_id);
        mysqli_stmt_execute($fetch);
        $budget_info = mysqli_fetch_assoc(mysqli_stmt_get_result($fetch));
        mysqli_stmt_close($fetch);

        if (!empty($budget_info['recurring_group_id'])) {
            $gid  = $budget_info['recurring_group_id'];
            $stmt = mysqli_prepare($savienojums,
                "DELETE FROM BU_budgets WHERE recurring_group_id = ? AND user_id = ?");
            mysqli_stmt_bind_param($stmt, "si", $gid, $user_id);
        } else {
            $stmt = mysqli_prepare($savienojums,
                "DELETE FROM BU_budgets WHERE id = ? AND user_id = ?");
            mysqli_stmt_bind_param($stmt, "ii", $budget_id, $user_id);
        }

        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            header('Location: budget.php?msg=deleted');
            exit();
        } else {
            $error = 'Kļūda dzēšot budžetu!';
            mysqli_stmt_close($stmt);
        }
    }

    // ── UPDATE ────────────────────────────────────────────────────────────────
    if ($action === 'update' && isset($_POST['budget_id'])) {
        $budget_id         = intval($_POST['budget_id']);
        $budget_name       = trim($_POST['budget_name'] ?? '');
        $budget_amount     = floatval($_POST['budget_amount'] ?? 0);
        $warning_threshold = floatval($_POST['warning_threshold'] ?? 80);

        $recurring_days = trim($_POST['recurring_days'] ?? '');
        $is_recurring   = ($recurring_days !== '') ? 1 : 0;

        if ($recurring_days !== '' && !preg_match('/^[0-6](,[0-6])*$/', $recurring_days)) {
            $recurring_days = '';
            $is_recurring   = 0;
        }

        // Check if this budget belongs to a quarterly group
        $fetch = mysqli_prepare($savienojums,
            "SELECT recurring_group_id, is_recurring FROM BU_budgets WHERE id = ? AND user_id = ?");
        mysqli_stmt_bind_param($fetch, "ii", $budget_id, $user_id);
        mysqli_stmt_execute($fetch);
        $budget_info = mysqli_fetch_assoc(mysqli_stmt_get_result($fetch));
        mysqli_stmt_close($fetch);

        // For recurring/quarterly budgets, require at least one day
        if (!empty($budget_info['is_recurring']) && empty($recurring_days)) {
            $error = 'Vismaz viena nedēļas diena ir obligāta!';
        } else {
        if (!empty($budget_info['recurring_group_id'])) {
            $gid  = $budget_info['recurring_group_id'];
            $stmt = mysqli_prepare($savienojums,
                "UPDATE BU_budgets
                 SET budget_name = ?, budget_amount = ?, warning_threshold = ?,
                     recurring_days = ?
                 WHERE recurring_group_id = ? AND user_id = ?");
            mysqli_stmt_bind_param($stmt, "sddssi",
                $budget_name, $budget_amount, $warning_threshold,
                $recurring_days, $gid, $user_id);
        } else {
            $stmt = mysqli_prepare($savienojums,
                "UPDATE BU_budgets
                 SET budget_name = ?, budget_amount = ?, warning_threshold = ?,
                     recurring_days = ?, is_recurring = ?
                 WHERE id = ? AND user_id = ?");
            mysqli_stmt_bind_param($stmt, "sddsiii",
                $budget_name, $budget_amount, $warning_threshold,
                $recurring_days, $is_recurring,
                $budget_id, $user_id);
        }

        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            header('Location: budget.php?msg=updated');
            exit();
        } else {
            $error = 'Kļūda atjauninot budžetu!';
            mysqli_stmt_close($stmt);
        }
        } // end else (at least one day check)
    }
}

// ─── Fetch budgets ────────────────────────────────────────────────────────────
$stmt = mysqli_prepare($savienojums,
    "SELECT * FROM BU_budgets WHERE user_id = ? ORDER BY created_at DESC");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$budgets_result = mysqli_stmt_get_result($stmt);

$budgets = [];
if ($budgets_result) {
    while ($row = mysqli_fetch_assoc($budgets_result)) {
        $spent_stmt = mysqli_prepare($savienojums,
            "SELECT SUM(amount) as total FROM BU_transactions
             WHERE user_id = ? AND type = 'expense'
             AND date BETWEEN ? AND ?");
        mysqli_stmt_bind_param($spent_stmt, "iss", $user_id, $row['start_date'], $row['end_date']);
        mysqli_stmt_execute($spent_stmt);
        $spent_row = mysqli_fetch_assoc(mysqli_stmt_get_result($spent_stmt));
        mysqli_stmt_close($spent_stmt);

        $spent             = $spent_row['total'] ?? 0;
        $row['spent']      = $spent;
        $row['remaining']  = $row['budget_amount'] - $spent;
        $row['percentage'] = $row['budget_amount'] > 0
            ? ($spent / $row['budget_amount']) * 100 : 0;

        $budgets[] = $row;
    }
}
mysqli_stmt_close($stmt);

// ─── Summary stats ────────────────────────────────────────────────────────────
$total_budgets       = count($budgets);
$active_budgets      = 0;
$total_budget_amount = 0;
$total_spent         = 0;

foreach ($budgets as $budget) {
    if (strtotime($budget['end_date']) >= time()) $active_budgets++;
    $total_budget_amount += $budget['budget_amount'];
    $total_spent         += $budget['spent'];
}

$total_remaining = $total_budget_amount - $total_spent;
?>
<?php $active_page = 'budget'; ?>
<!DOCTYPE html>
<html lang="lv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Budžeti - Budgetar</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="../css/budget.css">
    <link rel="stylesheet" href="../css/settings.css">
    <link rel="icon" href="../../assets/image/logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">
</head>
<body class="<?php echo (($_SESSION['theme'] ?? 'dark') === 'light') ? 'light-mode' : ''; ?>">
    <div class="dashboard-container">

        <!-- Sidebar -->
        <?php include __DIR__ . '/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="dashboard-main">
            <div class="dashboard-header">
                <h1 class="dashboard-title" data-i18n="budget.page.title">Budžetu pārvaldība</h1>
                <button class="btn btn-primary" onclick="openAddModal()">
                    <i class="fa-solid fa-plus"></i> <span data-i18n="budget.add.btn">Pievienot budžetu</span>
                </button>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error" style="margin-bottom:24px;">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success" style="margin-bottom:24px;">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <!-- Summary stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-card-icon"><i class="fa-solid fa-list-check"></i></div>
                    <div class="stat-card-content">
                        <div class="stat-card-label" data-i18n="budget.stat.active">Aktīvie budžeti</div>
                        <div class="stat-card-value"><?php echo $active_budgets; ?></div>
                    </div>
                </div>
                <div class="stat-card stat-card-income">
                    <div class="stat-card-icon"><i class="fa-solid fa-money-bill"></i></div>
                    <div class="stat-card-content">
                        <div class="stat-card-label" data-i18n="budget.stat.total">Kopējais budžets</div>
                        <div class="stat-card-value"><?php echo $currSymbol; ?><?php echo number_format($total_budget_amount, 2); ?></div>
                    </div>
                </div>
                <div class="stat-card stat-card-expense">
                    <div class="stat-card-icon"><i class="fa-solid fa-credit-card"></i></div>
                    <div class="stat-card-content">
                        <div class="stat-card-label" data-i18n="budget.stat.spent">Tērēts</div>
                        <div class="stat-card-value"><?php echo $currSymbol; ?><?php echo number_format($total_spent, 2); ?></div>
                    </div>
                </div>
                <div class="stat-card stat-card-balance">
                    <div class="stat-card-icon"><i class="fa-solid fa-piggy-bank"></i></div>
                    <div class="stat-card-content">
                        <div class="stat-card-label" data-i18n="budget.stat.remaining">Atlikums</div>
                        <div class="stat-card-value"><?php echo $currSymbol; ?><?php echo number_format($total_remaining, 2); ?></div>
                    </div>
                </div>
            </div>

            <?php if (empty($budgets)): ?>
                <div class="calendar-container" style="text-align:center; padding:80px 40px;">
                    <div style="font-size:64px; margin-bottom:20px; opacity:0.3;">
                        <i class="fa-solid fa-wallet"></i>
                    </div>
                    <h3 style="font-size:24px; margin-bottom:12px;" data-i18n="budget.empty.title">Nav izveidoti budžeti</h3>
                    <p style="color:var(--text-secondary); margin-bottom:30px;" data-i18n="budget.empty.desc">
                        Sāc pārvaldīt savus izdevumus, izveidojot savu pirmo budžetu!
                    </p>
                    <button class="btn btn-primary" onclick="openAddModal()">
                        <i class="fa-solid fa-plus"></i> <span data-i18n="budget.empty.btn">Izveidot budžetu</span>
                    </button>
                </div>
            <?php else: ?>
                <div class="budgets-grid">
                    <?php foreach ($budgets as $budget):
                        $is_active   = strtotime($budget['end_date'])   >= time();
                        $is_upcoming = strtotime($budget['start_date']) >  time();
                        $percentage  = min($budget['percentage'], 100);

                        if ($is_upcoming) {
                            $status_class   = 'status-upcoming';
                            $status_text    = 'Gaidāmais';
                            $progress_class = 'progress-safe';
                        } elseif (!$is_active) {
                            $status_class   = 'status-expired';
                            $status_text    = 'Beidzies';
                            $progress_class = 'progress-danger';
                        } elseif ($percentage >= $budget['warning_threshold']) {
                            $status_class   = 'status-warning';
                            $status_text    = 'Brīdinājums';
                            $progress_class = 'progress-warning';
                        } else {
                            $status_class   = 'status-active';
                            $status_text    = 'Aktīvs';
                            $progress_class = 'progress-safe';
                        }
                    ?>
                        <div class="budget-card">
                            <div class="budget-card-header">
                                <div>
                                    <div class="budget-card-title">
                                        <i class="fa-solid fa-wallet"></i>
                                        <?php echo htmlspecialchars($budget['budget_name']); ?>

                                        <?php if (!empty($budget['quarter_label'])): ?>
                                            <span class="quarter-badge quarter-<?php echo strtolower($budget['quarter_label']); ?>">
                                                <?php echo htmlspecialchars($budget['quarter_label']); ?>
                                            </span>
                                        <?php elseif (!empty($budget['recurring_days'])): ?>
                                            <span class="recurring-card-badge"
                                                  title="Recurring: <?php echo htmlspecialchars(recurringDayLabel($budget['recurring_days'])); ?>">
                                                <i class="fa-solid fa-arrows-rotate"></i>
                                                <?php echo htmlspecialchars(recurringDayLabel($budget['recurring_days'])); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="budget-card-period">
                                        <?php echo date('d.m.Y', strtotime($budget['start_date'])); ?> -
                                        <?php echo date('d.m.Y', strtotime($budget['end_date'])); ?>
                                    </div>
                                </div>
                                <span class="budget-status <?php echo $status_class; ?>">
                                    <?php echo $status_text; ?>
                                </span>
                            </div>

                            <div class="budget-amounts">
                                <div class="budget-amount-row">
                                    <span class="amount-label" data-i18n="budget.label.budget">Budžets:</span>
                                    <span class="amount-value"><?php echo $currSymbol; ?><?php echo number_format($budget['budget_amount'], 2); ?></span>
                                </div>
                                <div class="budget-amount-row">
                                    <span class="amount-label" data-i18n="budget.label.spent">Tērēts:</span>
                                    <span class="amount-value amount-spent"><?php echo $currSymbol; ?><?php echo number_format($budget['spent'], 2); ?></span>
                                </div>
                                <div class="budget-amount-row">
                                    <span class="amount-label" data-i18n="budget.label.remaining">Atlikums:</span>
                                    <span class="amount-value amount-remaining"><?php echo $currSymbol; ?><?php echo number_format($budget['remaining'], 2); ?></span>
                                </div>
                            </div>

                            <div class="budget-progress">
                                <div class="budget-progress-bar <?php echo $progress_class; ?>"
                                     style="width:<?php echo $percentage; ?>%"></div>
                            </div>
                            <div style="text-align:center; color:var(--text-secondary); font-size:12px; margin-top:8px;">
                                <?php echo number_format($percentage, 1); ?>% <span data-i18n="budget.label.used">izmantots</span>
                            </div>

                            <div class="budget-actions">
                                <div style="flex:1;">
                                    <button class="btn btn-secondary btn-small" style="width:100%;"
                                            onclick='openEditModal(<?php echo json_encode($budget); ?>)'>
                                        <i class="fa-solid fa-pencil"></i> <span data-i18n="budget.btn.edit">Rediģēt</span>
                                    </button>
                                </div>
                                <form method="POST" style="flex:1;"
                                      id="deleteForm_<?php echo $budget['id']; ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="budget_id" value="<?php echo $budget['id']; ?>">
                                    <button type="button" class="btn btn-danger btn-small" style="width:100%;"
                                            onclick="showBudgetDeleteConfirm(this.closest('form'), <?php echo !empty($budget['recurring_group_id']) ? 'true' : 'false'; ?>)">
                                        <i class="fa-solid fa-trash"></i> <span data-i18n="budget.btn.delete">Dzēst</span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>


    <!-- ══ ADD BUDGET MODAL ══════════════════════════════════════════════════ -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" data-i18n="budget.add.modal.title">Pievienot jaunu budžetu</h2>
                <button class="modal-close" onclick="closeAddModal()">✕</button>
            </div>
            <form method="POST" class="modal-form">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="budget_period" value="custom">

                <div class="form-group">
                    <label class="form-label" data-i18n="budget.add.name.label">Budžeta nosaukums *</label>
                    <input type="text" name="budget_name" class="form-input"
                           placeholder="piem. Nedēļas nogales budžets" required>
                </div>

                <div class="form-group">
                    <label class="form-label"><span data-i18n="budget.add.amount.label">Summa</span> (<?php echo $currSymbol; ?>) *</label>
                    <input type="number" name="budget_amount" class="form-input"
                           step="0.01" min="0" placeholder="100.00" required>
                </div>

                <!-- ── RECURRING SCHEDULE ─────────────────────────────────── -->
                <div class="form-group">
                    <div class="recurring-toggle-row">
                        <div class="recurring-toggle-label">
                            <div class="recurring-toggle-icon">
                                <i class="fa-solid fa-arrows-rotate"></i>
                            </div>
                            <div>
                                <div class="recurring-toggle-title" data-i18n="budget.add.recurring.title">Regulārs nedēļas grafiks</div>
                                <div class="recurring-toggle-sub" data-i18n="budget.add.recurring.sub">Automātiska atsvaidzināšana katru nedēļu atlasītajās dienās</div>
                            </div>
                        </div>
                        <label class="custom-toggle">
                            <input type="checkbox" id="add_recurring_toggle">
                            <span class="custom-toggle-track">
                                <span class="custom-toggle-thumb"></span>
                            </span>
                        </label>
                    </div>

                    <div id="add_recurring_days_container" style="display:none; margin-top:12px;">
                        <div class="day-picker">
                            <button type="button" class="day-pill" data-day="1">P</button>
                            <button type="button" class="day-pill" data-day="2">O</button>
                            <button type="button" class="day-pill" data-day="3">T</button>
                            <button type="button" class="day-pill" data-day="4">C</button>
                            <button type="button" class="day-pill" data-day="5">Pk</button>
                            <button type="button" class="day-pill" data-day="6">S</button>
                            <button type="button" class="day-pill" data-day="0">Sv</button>
                        </div>
                        <div id="add_recurring_preview" class="recurring-preview"
                             style="display:none;"></div>
                    </div>
                    <input type="hidden" name="recurring_days" id="add_recurring_days">
                </div>
                <!-- ── END RECURRING ──────────────────────────────────────── -->

                <div class="form-group" id="add_dates_group">
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                        <div>
                            <label class="form-label" data-i18n="budget.add.start.label">Sākuma datums *</label>
                            <input type="date" name="start_date" id="add_start_date"
                                   class="form-input">
                        </div>
                        <div>
                            <label class="form-label" data-i18n="budget.add.end.label">Beigu datums *</label>
                            <input type="date" name="end_date" id="add_end_date"
                                   class="form-input">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" data-i18n="budget.add.warning.label">Brīdinājuma slieksnis (%) *</label>
                    <input type="number" name="warning_threshold" class="form-input"
                           min="0" max="100" value="80" required>
                    <small style="color:var(--text-secondary); font-size:12px;" data-i18n="budget.add.warning.hint">
                        Tu saņemsi brīdinājumu, kad tērējumi sasniegs šo procentu
                    </small>
                </div>

                <button type="submit" class="btn btn-primary btn-full">
                    <span data-i18n="budget.add.submit">Pievienot budžetu</span>
                </button>
            </form>
        </div>
    </div>


    <!-- ══ EDIT BUDGET MODAL ═════════════════════════════════════════════════ -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" data-i18n="budget.edit.modal.title">Rediģēt budžetu</h2>
                <button class="modal-close" onclick="closeEditModal()">✕</button>
            </div>
            <form method="POST" class="modal-form">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="budget_id" id="edit_budget_id">

                <div class="form-group">
                    <label class="form-label" data-i18n="budget.edit.name.label">Budžeta nosaukums *</label>
                    <input type="text" name="budget_name" id="edit_budget_name"
                           class="form-input" required>
                </div>

                <div class="form-group">
                    <label class="form-label"><span data-i18n="budget.edit.amount.label">Summa</span> (<?php echo $currSymbol; ?>) *</label>
                    <input type="number" name="budget_amount" id="edit_budget_amount"
                           class="form-input" step="0.01" min="0" required>
                </div>

                <div class="form-group">
                    <label class="form-label" data-i18n="budget.edit.warning.label">Brīdinājuma slieksnis (%) *</label>
                    <input type="number" name="warning_threshold" id="edit_warning_threshold"
                           class="form-input" min="0" max="100" required>
                </div>

                <!-- ── RECURRING DAYS (edit) ───────────────────────────── -->
                <div class="form-group" id="edit_recurring_section" style="display:none;">
                    <div style="padding: 14px 16px; background: rgba(20,184,166,0.06); border: 1px solid rgba(20,184,166,0.18); border-radius: 8px;">
                        <div class="day-picker" id="edit_recurring_days_container">
                            <button type="button" class="day-pill" data-day="1">P</button>
                            <button type="button" class="day-pill" data-day="2">O</button>
                            <button type="button" class="day-pill" data-day="3">T</button>
                            <button type="button" class="day-pill" data-day="4">C</button>
                            <button type="button" class="day-pill" data-day="5">Pk</button>
                            <button type="button" class="day-pill" data-day="6">S</button>
                            <button type="button" class="day-pill" data-day="0">Sv</button>
                        </div>
                        <small style="color:var(--text-secondary); font-size:12px; margin-top:10px; display:block;" data-i18n="budget.edit.day.hint">
                            Vismaz viena diena ir obligāta
                        </small>
                    </div>
                </div>
                <input type="hidden" name="recurring_days" id="edit_recurring_days">
                <!-- ── END RECURRING ──────────────────────────────────────── -->

                <button type="submit" class="btn btn-primary btn-full">
                    <span data-i18n="budget.edit.submit">Atjaunināt budžetu</span>
                </button>
            </form>
        </div>
    </div>


    <?php include __DIR__ . '/mobile_nav.php'; ?>

    <script src="../js/currency.js"></script>
    <script>
        // Initialize currency from PHP session
        if ('<?php echo $_SESSION['currency'] ?? 'EUR'; ?>') {
            localStorage.setItem('budgetar_currency', '<?php echo $_SESSION['currency'] ?? 'EUR'; ?>');
        }
    </script>
    <script src="../js/script.js"></script>
    <script>window._i18nData=<?php echo json_encode($_traw); ?>;window._i18nLang=<?php echo json_encode($_lang); ?>;</script>
    <script src="../js/language.js"></script>
    <script src="../js/budget.js"></script>
</body>
</html>