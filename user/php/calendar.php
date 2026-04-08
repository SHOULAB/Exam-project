<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
require_once('../../assets/database.php');

function is_ajax_request() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

$current_month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$current_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

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
$_langIsDefault = true;
$stmt_lang = mysqli_prepare($savienojums,
    "SELECT setting_value FROM BU_user_settings WHERE user_id = ? AND setting_key = 'language'");
if ($stmt_lang) {
    mysqli_stmt_bind_param($stmt_lang, "i", $user_id);
    mysqli_stmt_execute($stmt_lang);
    $res_lang = mysqli_stmt_get_result($stmt_lang);
    if ($row_lang = mysqli_fetch_assoc($res_lang)) {
        $_SESSION['language'] = $row_lang['setting_value'];
        $_langIsDefault = false;
    }
    mysqli_stmt_close($stmt_lang);
}
$_lang = $_SESSION['language'];
$_traw = json_decode(file_get_contents(__DIR__ . '/translate.json'), true) ?? [];
$_t    = $_traw[$_lang] ?? $_traw['lv'] ?? [];

// Month names for PHP rendering and AJAX response
$month_names_php = [
    '', 
    $_t['cal.month.jan'] ?? 'Janvāris',
    $_t['cal.month.feb'] ?? 'Februāris',
    $_t['cal.month.mar'] ?? 'Marts',
    $_t['cal.month.apr'] ?? 'Aprīlis',
    $_t['cal.month.may'] ?? 'Maijs',
    $_t['cal.month.jun'] ?? 'Jūnijs',
    $_t['cal.month.jul'] ?? 'Jūlijs',
    $_t['cal.month.aug'] ?? 'Augusts',
    $_t['cal.month.sep'] ?? 'Septembris',
    $_t['cal.month.oct'] ?? 'Oktobris',
    $_t['cal.month.nov'] ?? 'Novembris',
    $_t['cal.month.dec'] ?? 'Decembris',
];

function ensureRecurringStopDateColumn($conn) {
    $result = mysqli_query($conn, "SHOW COLUMNS FROM BU_transactions LIKE 'recurring_stop_date'");
    if ($result && mysqli_num_rows($result) > 0) {
        return true;
    }
    mysqli_query($conn, "ALTER TABLE BU_transactions ADD COLUMN recurring_stop_date DATE NULL");
    $result2 = mysqli_query($conn, "SHOW COLUMNS FROM BU_transactions LIKE 'recurring_stop_date'");
    return ($result2 && mysqli_num_rows($result2) > 0);
}

$hasRecurringStopDateColumn = ensureRecurringStopDateColumn($savienojums);

// Combined transaction submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_transaction'])) {
    $date = $_POST['transaction_date'];
    $amount = floatval($_POST['transaction_amount']);
    $description = trim($_POST['transaction_description']);
    $type = (isset($_POST['transaction_type']) && $_POST['transaction_type'] === 'expense') ? 'expense' : 'income';
    $is_recurring = isset($_POST['is_recurring_transaction']) ? 1 : 0;

    if (!empty($date) && $amount > 0) {
        $stmt = mysqli_prepare($savienojums, "INSERT INTO BU_transactions (user_id, date, amount, type, description, is_recurring) VALUES (?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "isdssi", $user_id, $date, $amount, $type, $description, $is_recurring);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            if (is_ajax_request()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true]);
                exit();
            }

            header('Location: calendar.php');
            exit();
        }
    }
}

//Income submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_income'])) {
    $date = $_POST['income_date'];
    $amount = floatval($_POST['income_amount']);
    $description = trim($_POST['income_description']);
    $is_recurring = isset($_POST['is_recurring']) ? 1 : 0;
    
    if (!empty($date) && $amount > 0) {
        $stmt = mysqli_prepare($savienojums, "INSERT INTO BU_transactions (user_id, date, amount, type, description, is_recurring) VALUES (?, ?, ?, 'income', ?, ?)");
        
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "isdsi", $user_id, $date, $amount, $description, $is_recurring);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            
            if (is_ajax_request()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true]);
                exit();
            }

            header('Location: calendar.php');
            exit();
        }
    }
}

//Expense submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_expense'])) {
    $date = $_POST['expense_date'];
    $amount = floatval($_POST['expense_amount']);
    $description = trim($_POST['expense_description']);
    $is_recurring = isset($_POST['is_recurring_expense']) ? 1 : 0;
    
    if (!empty($date) && $amount > 0) {
        $stmt = mysqli_prepare($savienojums, "INSERT INTO BU_transactions (user_id, date, amount, type, description, is_recurring) VALUES (?, ?, ?, 'expense', ?, ?)");
        
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "isdsi", $user_id, $date, $amount, $description, $is_recurring);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            
            if (is_ajax_request()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true]);
                exit();
            }

            header('Location: calendar.php');
            exit();
        }
    }
}

// Delete transaction
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_transaction'])) {
    $transaction_id = intval($_POST['transaction_id']);
    
    $stmt = mysqli_prepare($savienojums, "SELECT date, is_recurring FROM BU_transactions WHERE id = ? AND user_id = ?");
    $shouldDelete = true;
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ii", $transaction_id, $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if ($row && intval($row['is_recurring']) === 1) {
            $month_first = sprintf('%04d-%02d-01', $current_year, $current_month);
            $stop_date = date('Y-m-d', strtotime("$month_first -1 day"));
            if ($row['date'] < $month_first && $hasRecurringStopDateColumn) {
                $ustmt = mysqli_prepare($savienojums, "UPDATE BU_transactions SET recurring_stop_date = ? WHERE id = ? AND user_id = ?");
                if ($ustmt) {
                    mysqli_stmt_bind_param($ustmt, "sii", $stop_date, $transaction_id, $user_id);
                    mysqli_stmt_execute($ustmt);
                    mysqli_stmt_close($ustmt);
                    $shouldDelete = false;
                }
            }
        }
    }

    if ($shouldDelete) {
        $stmt = mysqli_prepare($savienojums, "DELETE FROM BU_transactions WHERE id = ? AND user_id = ?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "ii", $transaction_id, $user_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }
    
    if (is_ajax_request()) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit();
    }

    header('Location: calendar.php?month=' . $current_month . '&year=' . $current_year);
    exit();
}

// gets current day
$current_month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$current_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$first_day = "$current_year-$current_month-01";
$last_day = date("Y-m-t", strtotime($first_day));

// Get regular transactions for this month
$stmt = mysqli_prepare($savienojums, "SELECT id, date, amount, type, description, is_recurring FROM BU_transactions WHERE user_id = ? AND date BETWEEN ? AND ? ORDER BY date ASC");
mysqli_stmt_bind_param($stmt, "iss", $user_id, $first_day, $last_day);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$transactions = [];
while ($row = mysqli_fetch_assoc($result)) {
    $day = date('j', strtotime($row['date']));
    if (!isset($transactions[$day])) {
        $transactions[$day] = [];
    }
    $transactions[$day][] = $row;
}
mysqli_stmt_close($stmt);

// Get recurring transactions from previous months and add them to current month
$recurringQuery = "SELECT id, date, amount, type, description FROM BU_transactions WHERE user_id = ? AND is_recurring = 1 AND date < ?";
if ($hasRecurringStopDateColumn) {
    $recurringQuery .= " AND (recurring_stop_date IS NULL OR recurring_stop_date >= ?)";
}
$recurringQuery .= " ORDER BY date ASC";

$stmt_recurring = mysqli_prepare($savienojums, $recurringQuery);
if ($hasRecurringStopDateColumn) {
    mysqli_stmt_bind_param($stmt_recurring, "iss", $user_id, $first_day, $first_day);
} else {
    mysqli_stmt_bind_param($stmt_recurring, "is", $user_id, $first_day);
}
mysqli_stmt_execute($stmt_recurring);
$recurring_result = mysqli_stmt_get_result($stmt_recurring);

while ($row = mysqli_fetch_assoc($recurring_result)) {
    $original_day = date('j', strtotime($row['date']));
    $days_in_current_month = date('t', strtotime($first_day));
    
    $day_to_use = min($original_day, $days_in_current_month);
    
    if (!isset($transactions[$day_to_use])) {
        $transactions[$day_to_use] = [];
    }
    
    $recurring_transaction = $row;
    $recurring_transaction['is_recurring_display'] = true;
    $transactions[$day_to_use][] = $recurring_transaction;
}
mysqli_stmt_close($stmt_recurring);

// Calculate total for entire month
$total_income = 0;
$total_expense = 0;
foreach ($transactions as $day_transactions) {
    foreach ($day_transactions as $transaction) {
        if ($transaction['type'] === 'income') {
            $total_income += $transaction['amount'];
        } else {
            $total_expense += $transaction['amount'];
        }
    }
}
$balance = $total_income - $total_expense;

// Calculate carried balance from ALL months before the current one
$stmt_carried = mysqli_prepare($savienojums, "SELECT type, SUM(amount) as total FROM BU_transactions WHERE user_id = ? AND date < ? GROUP BY type");
mysqli_stmt_bind_param($stmt_carried, "is", $user_id, $first_day);
mysqli_stmt_execute($stmt_carried);
$carried_result = mysqli_stmt_get_result($stmt_carried);

$carried_income = 0;
$carried_expense = 0;
while ($carried_row = mysqli_fetch_assoc($carried_result)) {
    if ($carried_row['type'] === 'income') {
        $carried_income = $carried_row['total'];
    } else {
        $carried_expense = $carried_row['total'];
    }
}
mysqli_stmt_close($stmt_carried);

$carried_balance = $carried_income - $carried_expense;

// Calculate today's balance (only up to today)
$today_income = 0;
$today_expense = 0;
$current_day = date('j');
$is_current_month = ($current_month == date('n') && $current_year == date('Y'));

if ($is_current_month) {
    foreach ($transactions as $day => $day_transactions) {
        if ($day <= $current_day) {
            foreach ($day_transactions as $transaction) {
                if ($transaction['type'] === 'income') {
                    $today_income += $transaction['amount'];
                } else {
                    $today_expense += $transaction['amount'];
                }
            }
        }
    }
}
$today_balance = $carried_balance + $today_income - $today_expense;

// ─── Fetch active budgets for budget-exceed warning ───────────────────────────
// Only load if the is_recurring column exists (migration guard)
$activeBudgets = [];
$col_check = mysqli_query($savienojums, "SHOW COLUMNS FROM BU_budgets LIKE 'is_recurring'");
$budgets_table_ready = ($col_check && mysqli_num_rows($col_check) > 0);

if ($budgets_table_ready) {
    $bstmt = mysqli_prepare($savienojums,
        "SELECT id, budget_name, budget_amount, start_date, end_date,
                warning_threshold, recurring_days
         FROM   BU_budgets
         WHERE  user_id = ? AND end_date >= CURDATE()
         ORDER  BY start_date ASC");
    mysqli_stmt_bind_param($bstmt, "i", $user_id);
    mysqli_stmt_execute($bstmt);
    $bres = mysqli_stmt_get_result($bstmt);
    while ($brow = mysqli_fetch_assoc($bres)) {
        // Calculate how much has already been spent against this budget
        $sstmt = mysqli_prepare($savienojums,
            "SELECT COALESCE(SUM(amount), 0) AS spent
             FROM   BU_transactions
             WHERE  user_id = ? AND type = 'expense'
               AND  date BETWEEN ? AND ?");
        mysqli_stmt_bind_param($sstmt, "iss",
            $user_id, $brow['start_date'], $brow['end_date']);
        mysqli_stmt_execute($sstmt);
        $srow  = mysqli_fetch_assoc(mysqli_stmt_get_result($sstmt));
        mysqli_stmt_close($sstmt);

        $brow['spent']     = floatval($srow['spent']);
        $brow['remaining'] = $brow['budget_amount'] - $brow['spent'];
        $activeBudgets[]   = $brow;
    }
    mysqli_stmt_close($bstmt);
}

// Calendar generator
$first_day_of_month = mktime(0, 0, 0, $current_month, 1, $current_year);
$days_in_month = date('t', $first_day_of_month);
$day_of_week = date('N', $first_day_of_month); // 1 (Monday) to 7 (Sunday)

// previous or next month
$prev_month = $current_month - 1;
$prev_year = $current_year;
if ($prev_month < 1) {
    $prev_month = 12;
    $prev_year--;
}

$next_month = $current_month + 1;
$next_year = $current_year;
if ($next_month > 12) {
    $next_month = 1;
    $next_year++;
}

if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'current_month' => $current_month,
        'current_year'  => $current_year,
        'month_name'    => $month_names_php[$current_month],
        'prev_month'    => $prev_month,
        'prev_year'     => $prev_year,
        'next_month'    => $next_month,
        'next_year'     => $next_year,
        'days_in_month' => $days_in_month,
        'first_weekday' => $day_of_week,
        'transactions'  => $transactions,
        'total_income'  => $total_income,
        'total_expense' => $total_expense,
        'balance'       => $balance,
        'today_balance' => $today_balance,
        'is_current_month' => $is_current_month,
        'currency_symbol' => $currSymbol,
        'activeBudgets' => $activeBudgets,
    ]);
    exit();
}
?>
<?php $active_page = 'calendar'; ?>
<!DOCTYPE html>
<html lang="lv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kalendārs - Budgetar</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="../css/calendar.css">
    <link rel="stylesheet" href="../css/budget.css">
    <link rel="stylesheet" href="../css/settings.css">
    <link rel="icon" href="../../assets/image/logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">
</head>
<body class="<?php echo (($_SESSION['theme'] ?? 'dark') === 'light') ? 'light-mode' : ''; ?>">
    <div class="dashboard-container">
        <?php include __DIR__ . '/sidebar.php'; ?>

        <main class="dashboard-main">
            <div class="dashboard-header">
                <h1 class="dashboard-title" data-i18n="cal.page.title">Finanšu Kalendārs</h1>
                <div class="header-buttons">
                </div>
            </div>

            <div class="stats-grid">
                <div class="stat-card stat-card-today" style="<?php echo $is_current_month ? '' : 'display:none;'; ?>">
                    <div class="stat-card-icon"><i class="fa-solid fa-wallet"></i></div>
                    <div class="stat-card-content">
                        <div class="stat-card-label" data-i18n="cal.stat.balance">Bilance</div>
                        <div class="stat-card-value"><?php echo $currSymbol; ?><?php echo number_format($today_balance, 2); ?></div>
                    </div>
                </div>
                
                <div class="stat-card stat-card-income">
                    <div class="stat-card-icon"><i class="fa-solid fa-sack-dollar"></i></div>
                    <div class="stat-card-content">
                        <div class="stat-card-label" data-i18n="cal.stat.income">Kopējie ienākumi</div>
                        <div class="stat-card-value"><?php echo $currSymbol; ?><?php echo number_format($total_income, 2); ?></div>
                    </div>
                </div>
                <div class="stat-card stat-card-expense">
                    <div class="stat-card-icon"><i class="fa-solid fa-sack-xmark"></i></div>
                    <div class="stat-card-content">
                        <div class="stat-card-label" data-i18n="cal.stat.expense">Kopējie izdevumi</div>
                        <div class="stat-card-value"><?php echo $currSymbol; ?><?php echo number_format($total_expense, 2); ?></div>
                    </div>
                </div>
                <div class="stat-card stat-card-balance">
                    <div class="stat-card-icon"><i class="fa-solid fa-landmark"></i></div>
                    <div class="stat-card-content">
                        <div class="stat-card-label" data-i18n="cal.stat.month.balance">Mēneša bilance</div>
                        <div class="stat-card-value"><?php echo $currSymbol; ?><?php echo number_format($balance, 2); ?></div>
                    </div>
                </div>
            </div>

            <div class="calendar-container">
                <div class="calendar-header">
                    <a href="?month=<?php echo $prev_month; ?>&year=<?php echo $prev_year; ?>" class="calendar-nav" data-month="<?php echo $prev_month; ?>" data-year="<?php echo $prev_year; ?>" data-direction="prev" data-i18n="cal.nav.prev">
                        ← Iepriekšējais
                    </a>
                    <div class="cal-month-picker-wrap">
                        <button type="button" class="cal-picker-btn" id="calPickerToggle" aria-label="Select month and year">
                            <h2 class="calendar-month">
                                <?php 
                                echo $month_names_php[$current_month] . ' ' . $current_year;
                                ?>
                            </h2>
                            <i class="fa-solid fa-chevron-down"></i>
                        </button>
                        <div class="cal-picker-dropdown" id="calPickerDropdown"></div>
                    </div>
                    <a href="?month=<?php echo $next_month; ?>&year=<?php echo $next_year; ?>" class="calendar-nav" data-month="<?php echo $next_month; ?>" data-year="<?php echo $next_year; ?>" data-direction="next" data-i18n="cal.nav.next">
                        Nākamais →
                    </a>
                </div>

                <div class="calendar-grid">
                    <div class="calendar-weekday">P</div>
                    <div class="calendar-weekday">O</div>
                    <div class="calendar-weekday">T</div>
                    <div class="calendar-weekday">C</div>
                    <div class="calendar-weekday">Pk</div>
                    <div class="calendar-weekday">S</div>
                    <div class="calendar-weekday">Sv</div>

                    <?php
                    for ($i = 1; $i < $day_of_week; $i++) {
                        echo '<div class="calendar-day calendar-day-empty"></div>';
                    }

                    for ($day = 1; $day <= $days_in_month; $day++) {
                        $is_today = ($day == date('j') && $current_month == date('n') && $current_year == date('Y'));
                        $has_transactions = isset($transactions[$day]);
                        
                        $class = 'calendar-day';
                        if ($is_today) $class .= ' calendar-day-today';
                        if ($has_transactions) $class .= ' calendar-day-has-data';
                        
                        echo '<div class="' . $class . '" data-day="' . $day . '" onclick="openDayModal(' . $day . ', ' . $current_month . ', ' . $current_year . ')">';
                        echo '<div class="calendar-day-number">' . $day . '</div>';
                        
                        if ($has_transactions) {
                            $day_income = 0;
                            $day_expense = 0;
                            foreach ($transactions[$day] as $transaction) {
                                if ($transaction['type'] === 'income') {
                                    $day_income += $transaction['amount'];
                                } else {
                                    $day_expense += $transaction['amount'];
                                }
                            }
                            
                            echo '<div class="calendar-day-transactions">';
                            if ($day_income > 0) {
                                echo '<div class="calendar-transaction-badge income">+' . $currSymbol . number_format($day_income, 0) . '</div>';
                            }
                            if ($day_expense > 0) {
                                echo '<div class="calendar-transaction-badge expense">-' . $currSymbol . number_format($day_expense, 0) . '</div>';
                            }
                            echo '</div>';
                        }
                        
                        echo '</div>';
                    }
                    ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Transaction Modal -->
    <div id="transactionModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="transactionModalTitle" data-i18n="cal.modal.add.income">Pievienot ienākumu</h2>
                <button class="modal-close" onclick="closeTransactionModal()">✕</button>
            </div>
            <form method="POST" action="" class="modal-form" id="transactionForm">
                <input type="hidden" name="add_transaction" value="1">

                <div class="transaction-type-toggle">
                    <button type="button" id="toggleIncome" class="type-toggle-btn active" onclick="setTransactionType('income')">
                        <i class="fa-solid fa-plus"></i> <span data-i18n="cal.modal.income.btn">Ienākums</span>
                    </button>
                    <button type="button" id="toggleExpense" class="type-toggle-btn" onclick="setTransactionType('expense')">
                        <i class="fa-solid fa-minus"></i> <span data-i18n="cal.modal.expense.btn">Izdevums</span>
                    </button>
                </div>
                <input type="hidden" name="transaction_type" id="transaction_type" value="income">

                <div class="form-group">
                    <label for="transaction_date" class="form-label" data-i18n="cal.modal.date">Datums</label>
                    <input type="date" id="transaction_date" name="transaction_date"
                        class="form-input" required value="<?php echo date('Y-m-d'); ?>">
                </div>

                <div class="form-group">
                    <label for="transaction_amount" class="form-label"><span data-i18n="cal.modal.amount">Summa</span> (<?php echo $currSymbol; ?>)</label>
                    <input type="number" id="transaction_amount" name="transaction_amount"
                        class="form-input" placeholder="0.00" step="0.01" min="0.01" required>
                </div>

                <div class="form-group">
                    <label for="transaction_description" class="form-label" data-i18n="cal.modal.desc">Apraksts</label>
                    <input type="text" id="transaction_description" name="transaction_description"
                        class="form-input" placeholder="Apraksts..." required>
                </div>

                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="is_recurring_transaction" class="checkbox-input">
                        <span id="recurringLabel" data-i18n="cal.modal.recurring.income">Ikmēneša ienākums (atkārtosies katru mēnesi)</span>
                    </label>
                </div>

                <button type="submit" class="btn btn-success btn-full" id="transactionSubmitBtn" data-i18n="cal.modal.add.income">
                    Pievienot ienākumu
                </button>
            </form>
        </div>
    </div>

    <!-- Day Modal -->
    <div id="dayModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="dayModalTitle" data-i18n="cal.day.modal.title">Dienas transakcijas</h2>
                <button class="modal-close" onclick="closeDayModal()">✕</button>
            </div>
            <div id="dayModalContent" class="day-transactions-list"></div>
        </div>
    </div>

    <?php include __DIR__ . '/mobile_nav.php'; ?>

    <script>
        let transactionsData = <?php echo json_encode($transactions); ?>;
        let monthlyIncome    = <?php echo $total_income; ?>;
        let monthlyExpense   = <?php echo $total_expense; ?>;
        let currentMonth     = <?php echo $current_month; ?>;
        let currentYear      = <?php echo $current_year; ?>;
        let currencySymbol   = <?php echo json_encode($currSymbol); ?>;
        // Active budgets with their spent/remaining amounts — used for budget-exceed warning
        let activeBudgets    = <?php echo json_encode($activeBudgets); ?>;
        const calendarStrings = <?php echo json_encode([
            'noEntries'       => $_t['cal.day.no.entries']          ?? 'Nav ierakstu šajā dienā.',
            'addEntry'        => $_t['cal.day.add.btn']             ?? 'Pievienot ierakstu',
            'typeIncome'      => $_t['cal.type.income']             ?? 'Ienākums',
            'typeExpense'     => $_t['cal.type.expense']            ?? 'Izdevums',
            'badgeMonthly'    => $_t['cal.badge.monthly']           ?? 'Ikmēneša',
            'deleteTitle'     => $_t['cal.delete.title']            ?? 'Apstiprināt dzēšanu',
            'deleteMessage'   => $_t['cal.delete.message']          ?? 'Vai tiešām vēlies dzēst šo ierakstu? Šī darbība nevar tikt atsaukta.',
            'deleteCancel'    => $_t['cal.delete.cancel']           ?? 'Atcelt',
            'deleteBtn'       => $_t['cal.delete.btn']              ?? 'Dzēst',
            'addIncome'       => $_t['cal.modal.add.income']        ?? 'Pievienot ienākumu',
            'addExpense'      => $_t['cal.modal.add.expense']       ?? 'Pievienot izdevumu',
            'recurIncome'     => $_t['cal.modal.recurring.income']  ?? 'Ikmēneša ienākums (atkārtosies katru mēnesi)',
            'recurExpense'    => $_t['cal.modal.recurring.expense'] ?? 'Ikmēneša izdevums (atkārtosies katru mēnesi)',
            'warnTitle'       => $_t['cal.warn.title']              ?? '⚠️ Brīdinājums!',
            'warnText'        => $_t['cal.warn.text']               ?? 'Šis izdevums pārsniegs tavus mēneša ienākumus!',
            'warnMonthIncome' => $_t['cal.warn.month.income']       ?? 'Mēneša ienākumi:',
            'warnCurExpense'  => $_t['cal.warn.cur.expense']        ?? 'Pašreizējie izdevumi:',
            'warnNewExpense'  => $_t['cal.warn.new.expense']        ?? 'Jauns izdevums:',
            'warnTotal'       => $_t['cal.warn.total.expense']      ?? 'Kopējie izdevumi:',
            'warnDeficit'     => $_t['cal.warn.deficit']            ?? 'Deficīts:',
            'warnQuestion'    => $_t['cal.warn.question']           ?? 'Vai tiešām vēlies pievienot šo izdevumu?',
            'warnCancel'      => $_t['cal.warn.cancel']             ?? 'Atcelt',
            'warnConfirm'     => $_t['cal.warn.confirm']            ?? 'Jā, pievienot',
            'bwTitle'         => $_t['cal.bw.title']                ?? 'Budžeta brīdinājums',
            'bwSubSingle'     => $_t['cal.bw.subtitle.single']      ?? 'budžetam',
            'bwSubPlural'     => $_t['cal.bw.subtitle.plural']      ?? 'budžetiem',
            'bwBudget'        => $_t['cal.bw.budget']               ?? 'Budžets',
            'bwSpent'         => $_t['cal.bw.spent']                ?? 'Tērēts',
            'bwNewExpense'    => $_t['cal.bw.new.expense']          ?? 'Jauns izdevums',
            'bwOver'          => $_t['cal.bw.over']                 ?? 'Pārtērēts par',
            'bwQuestion'      => $_t['cal.bw.question']             ?? 'Vai tiešām vēlies pievienot šo izdevumu?',
            'bwCancel'        => $_t['cal.bw.cancel']               ?? 'Atcelt',
            'bwConfirm'       => $_t['cal.bw.confirm']              ?? 'Jā, pievienot',
            'bwSubtitleFmt'   => $_t['cal.bw.subtitle.fmt']          ?? 'Šis izdevums pārsniegs %s',
            'pickerToday'    => $_t['cal.picker.today']           ?? 'Today',
            'monthNames'      => $month_names_php,
        ]); ?>;
    </script>
    <script src="../js/currency.js"></script>
    <script>
        // Initialize currency from PHP session
        if ('<?php echo $_SESSION['currency'] ?? 'EUR'; ?>') {
            localStorage.setItem('budgetar_currency', '<?php echo $_SESSION['currency'] ?? 'EUR'; ?>');
        }
    </script>
    <script src="../js/script.js"></script>
    <script>window._i18nData=<?php echo json_encode($_traw); ?>;window._i18nLang=<?php echo json_encode($_lang); ?>;window._i18nIsDefault=<?php echo $_langIsDefault ? 'true' : 'false'; ?>;</script>
    <script src="../js/language.js"></script>
    <script src="../js/calendar.js"></script>
</body>
</html>