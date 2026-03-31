<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
require_once('../../assets/database.php');

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

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
            
            header('Location: calendar.php');
            exit();
        }
    }
}

// Delete transaction
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_transaction'])) {
    $transaction_id = intval($_POST['transaction_id']);
    
    $stmt = mysqli_prepare($savienojums, "DELETE FROM BU_transactions WHERE id = ? AND user_id = ?");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ii", $transaction_id, $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
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
$stmt_recurring = mysqli_prepare($savienojums, "SELECT id, date, amount, type, description FROM BU_transactions WHERE user_id = ? AND is_recurring = 1 AND date < ? ORDER BY date ASC");
mysqli_stmt_bind_param($stmt_recurring, "is", $user_id, $first_day);
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
?>
<?php $active_page = 'calendar'; ?>
<!DOCTYPE html>
<html lang="lv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kalendārs - Budgetiva</title>
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
                <h1 class="dashboard-title">Finanšu Kalendārs</h1>
                <div class="header-buttons">
                    <button class="btn btn-success" onclick="openIncomeModal()">
                        <span><i class="fa-solid fa-plus"></i></span>
                        Pievienot ienākumu
                    </button>
                    <button class="btn btn-danger" onclick="openExpenseModal()">
                        <span><i class="fa-solid fa-minus"></i></span>
                        Pievienot izdevumu
                    </button>
                </div>
            </div>

            <div class="stats-grid">
                <?php if ($is_current_month): ?>
                <div class="stat-card stat-card-today">
                    <div class="stat-card-icon"><i class="fa-solid fa-wallet"></i></div>
                    <div class="stat-card-content">
                        <div class="stat-card-label">Bilance</div>
                        <div class="stat-card-value">€<?php echo number_format($today_balance, 2); ?></div>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="stat-card stat-card-income">
                    <div class="stat-card-icon"><i class="fa-solid fa-sack-dollar"></i></div>
                    <div class="stat-card-content">
                        <div class="stat-card-label">Kopējie ienākumi</div>
                        <div class="stat-card-value">€<?php echo number_format($total_income, 2); ?></div>
                    </div>
                </div>
                <div class="stat-card stat-card-expense">
                    <div class="stat-card-icon"><i class="fa-solid fa-sack-xmark"></i></div>
                    <div class="stat-card-content">
                        <div class="stat-card-label">Kopējie izdevumi</div>
                        <div class="stat-card-value">€<?php echo number_format($total_expense, 2); ?></div>
                    </div>
                </div>
                <div class="stat-card stat-card-balance">
                    <div class="stat-card-icon"><i class="fa-solid fa-landmark"></i></div>
                    <div class="stat-card-content">
                        <div class="stat-card-label">Mēneša bilance</div>
                        <div class="stat-card-value">€<?php echo number_format($balance, 2); ?></div>
                    </div>
                </div>
            </div>

            <div class="calendar-container">
                <div class="calendar-header">
                    <a href="?month=<?php echo $prev_month; ?>&year=<?php echo $prev_year; ?>" class="calendar-nav">
                        ← Iepriekšējais
                    </a>
                    <h2 class="calendar-month">
                        <?php 
                        $month_names = ['', 'Janvāris', 'Februāris', 'Marts', 'Aprīlis', 'Maijs', 'Jūnijs', 
                                        'Jūlijs', 'Augusts', 'Septembris', 'Oktobris', 'Novembris', 'Decembris'];
                        echo $month_names[$current_month] . ' ' . $current_year;
                        ?>
                    </h2>
                    <a href="?month=<?php echo $next_month; ?>&year=<?php echo $next_year; ?>" class="calendar-nav">
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
                                echo '<div class="calendar-transaction-badge income">+€' . number_format($day_income, 0) . '</div>';
                            }
                            if ($day_expense > 0) {
                                echo '<div class="calendar-transaction-badge expense">-€' . number_format($day_expense, 0) . '</div>';
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

    <!-- Income Modal -->
    <div id="incomeModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Pievienot ienākumu</h2>
                <button class="modal-close" onclick="closeIncomeModal()">✕</button>
            </div>
            <form method="POST" action="" class="modal-form">
                <input type="hidden" name="add_income" value="1">
                
                <div class="form-group">
                    <label for="income_date" class="form-label">Datums</label>
                    <input type="date" id="income_date" name="income_date"
                        class="form-input" required value="<?php echo date('Y-m-d'); ?>">
                </div>

                <div class="form-group">
                    <label for="income_amount" class="form-label">Summa (€)</label>
                    <input type="number" id="income_amount" name="income_amount"
                        class="form-input" placeholder="0.00" step="0.01" min="0.01" required>
                </div>

                <div class="form-group">
                    <label for="income_description" class="form-label">Apraksts</label>
                    <input type="text" id="income_description" name="income_description"
                        class="form-input" placeholder="Piemēram: Alga, Prēmija..." required>
                </div>

                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="is_recurring" class="checkbox-input">
                        <span>Ikmēneša ienākums (atkārtosies katru mēnesi)</span>
                    </label>
                </div>

                <button type="submit" class="btn btn-primary btn-full">
                    Pievienot ienākumu
                </button>
            </form>
        </div>
    </div>

    <!-- Expense Modal -->
    <div id="expenseModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Pievienot izdevumu</h2>
                <button class="modal-close" onclick="closeExpenseModal()">✕</button>
            </div>
            <form method="POST" action="" class="modal-form">
                <input type="hidden" name="add_expense" value="1">
                
                <div class="form-group">
                    <label for="expense_date" class="form-label">Datums</label>
                    <input type="date" id="expense_date" name="expense_date"
                        class="form-input" required value="<?php echo date('Y-m-d'); ?>">
                </div>

                <div class="form-group">
                    <label for="expense_amount" class="form-label">Summa (€)</label>
                    <div class="amount-input-wrapper">
                        <span class="amount-prefix">-</span>
                        <input type="number" id="expense_amount" name="expense_amount"
                            class="form-input amount-input" placeholder="0.00"
                            step="0.01" min="0.01" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="expense_description" class="form-label">Apraksts</label>
                    <input type="text" id="expense_description" name="expense_description"
                        class="form-input" placeholder="Piemēram: Īre, Elektrība, Pārtika..." required>
                </div>

                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="is_recurring_expense" class="checkbox-input">
                        <span>Ikmēneša izdevums (atkārtosies katru mēnesi)</span>
                    </label>
                </div>

                <button type="submit" class="btn btn-primary btn-full">
                    Pievienot izdevumu
                </button>
            </form>
        </div>
    </div>

    <!-- Day Modal -->
    <div id="dayModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="dayModalTitle">Dienas transakcijas</h2>
                <button class="modal-close" onclick="closeDayModal()">✕</button>
            </div>
            <div id="dayModalContent" class="day-transactions-list"></div>
        </div>
    </div>

    <?php include __DIR__ . '/mobile_nav.php'; ?>

    <script>
        const transactionsData = <?php echo json_encode($transactions); ?>;
        const monthlyIncome    = <?php echo $total_income; ?>;
        const monthlyExpense   = <?php echo $total_expense; ?>;
        // Active budgets with their spent/remaining amounts — used for budget-exceed warning
        const activeBudgets    = <?php echo json_encode($activeBudgets); ?>;
    </script>
    <script src="../js/script.js"></script>
    <script src="../js/calendar.js"></script>
</body>
</html>