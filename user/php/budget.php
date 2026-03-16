<?php
// budget.php - User Budget Management
session_start();

// Include database first
require_once('../../assets/database.php');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$error = '';
$success = '';

// Handle budget operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        // Add new budget
        if ($action === 'add') {
            $budget_name = trim($_POST['budget_name']);
            $budget_amount = floatval($_POST['budget_amount']);
            $budget_period = $_POST['budget_period'];
            $start_date = $_POST['start_date'];
            $end_date = $_POST['end_date'];
            $warning_threshold = floatval($_POST['warning_threshold']);

            if (empty($budget_name) || $budget_amount <= 0) {
                $error = 'Lūdzu aizpildiet visus obligātos laukus!';
            } else {
                $stmt = mysqli_prepare($savienojums, 
                    "INSERT INTO BU_budgets (user_id, budget_name, budget_amount, budget_period, start_date, end_date, warning_threshold, created_at) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                mysqli_stmt_bind_param($stmt, "isdsssd", $user_id, $budget_name, $budget_amount, $budget_period, $start_date, $end_date, $warning_threshold);
                
                if (mysqli_stmt_execute($stmt)) {
                    $success = 'Budžets veiksmīgi pievienots!';
                } else {
                    $error = 'Kļūda pievienojot budžetu!';
                }
                mysqli_stmt_close($stmt);
            }
        }

        // Delete budget
        if ($action === 'delete' && isset($_POST['budget_id'])) {
            $budget_id = intval($_POST['budget_id']);
            $stmt = mysqli_prepare($savienojums, "DELETE FROM BU_budgets WHERE id = ? AND user_id = ?");
            mysqli_stmt_bind_param($stmt, "ii", $budget_id, $user_id);
            
            if (mysqli_stmt_execute($stmt)) {
                $success = 'Budžets veiksmīgi dzēsts!';
            } else {
                $error = 'Kļūda dzēšot budžetu!';
            }
            mysqli_stmt_close($stmt);
        }

        // Update budget
        if ($action === 'update' && isset($_POST['budget_id'])) {
            $budget_id = intval($_POST['budget_id']);
            $budget_name = trim($_POST['budget_name']);
            $budget_amount = floatval($_POST['budget_amount']);
            $warning_threshold = floatval($_POST['warning_threshold']);

            $stmt = mysqli_prepare($savienojums, 
                "UPDATE BU_budgets SET budget_name = ?, budget_amount = ?, warning_threshold = ? WHERE id = ? AND user_id = ?");
            mysqli_stmt_bind_param($stmt, "sddii", $budget_name, $budget_amount, $warning_threshold, $budget_id, $user_id);
            
            if (mysqli_stmt_execute($stmt)) {
                $success = 'Budžets veiksmīgi atjaunināts!';
            } else {
                $error = 'Kļūda atjauninot budžetu!';
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// Get user's budgets
$budgets_query = "SELECT * FROM BU_budgets WHERE user_id = ? ORDER BY created_at DESC";
$stmt = mysqli_prepare($savienojums, $budgets_query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$budgets_result = mysqli_stmt_get_result($stmt);
$budgets = [];
if ($budgets_result) {
    while ($row = mysqli_fetch_assoc($budgets_result)) {
        // Calculate spent amount for this budget
        $spent = 0;
        $spent_query = "SELECT SUM(amount) as total FROM BU_transactions 
                       WHERE user_id = ? AND type = 'expense' 
                       AND date BETWEEN ? AND ?";
        
        $spent_stmt = mysqli_prepare($savienojums, $spent_query);
        mysqli_stmt_bind_param($spent_stmt, "iss", $user_id, $row['start_date'], $row['end_date']);
        mysqli_stmt_execute($spent_stmt);
        $spent_result = mysqli_stmt_get_result($spent_stmt);
        $spent_row = mysqli_fetch_assoc($spent_result);
        $spent = $spent_row['total'] ?? 0;
        mysqli_stmt_close($spent_stmt);
        
        $row['spent'] = $spent;
        $row['remaining'] = $row['budget_amount'] - $spent;
        $row['percentage'] = ($spent / $row['budget_amount']) * 100;
        
        $budgets[] = $row;
    }
}
mysqli_stmt_close($stmt);

// Calculate stats
$total_budgets = count($budgets);
$active_budgets = 0;
$total_budget_amount = 0;
$total_spent = 0;

foreach ($budgets as $budget) {
    $end_date = strtotime($budget['end_date']);
    if ($end_date >= time()) {
        $active_budgets++;
    }
    $total_budget_amount += $budget['budget_amount'];
    $total_spent += $budget['spent'];
}

$total_remaining = $total_budget_amount - $total_spent;
?>
<!DOCTYPE html>
<html lang="lv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Budžeti - Budgetar</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="icon" href="../../assets/image/logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <img src="../../assets/image/logo.png" alt="Budgetar Logo" class="logo-img">
                    <span class="logo-text">Budgetar</span>
                </div>
            </div>

            <nav class="sidebar-nav">
                <a href="calendar.php" class="nav-item">
                    <span class="nav-icon"><i class="fa-solid fa-calendar"></i></span>
                    <span class="nav-text">Kalendārs</span>
                </a>
                <a href="parskati.php" class="nav-item">
                    <span class="nav-icon"><i class="fa-solid fa-chart-pie"></i></span>
                    <span class="nav-text">Pārskati</span>
                </a>
                <a href="budget.php" class="nav-item active">
                    <span class="nav-icon"><i class="fa-solid fa-wallet"></i></span>
                    <span class="nav-text">Budžets</span>
                </a>
                <a href="#" class="nav-item">
                    <span class="nav-icon"><i class="fa-solid fa-gear"></i></span>
                    <span class="nav-text">Iestatījumi</span>
                </a>
            </nav>

            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="user-avatar"><?php echo strtoupper(substr($username, 0, 1)); ?></div>
                    <div class="user-details">
                        <div class="user-name"><?php echo htmlspecialchars($username); ?></div>
                        <a href="logout.php" class="user-logout">Iziet</a>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="dashboard-main">
            <div class="dashboard-header">
                <h1 class="dashboard-title">Budžetu pārvaldība</h1>
                <button class="btn btn-primary" onclick="openAddModal()">
                    <i class="fa-solid fa-plus"></i> Pievienot budžetu
                </button>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error" style="margin-bottom: 24px;">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success" style="margin-bottom: 24px;">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-card-icon"><i class="fa-solid fa-list-check"></i></div>
                    <div class="stat-card-content">
                        <div class="stat-card-label">Aktīvie budžeti</div>
                        <div class="stat-card-value"><?php echo $active_budgets; ?></div>
                    </div>
                </div>
                <div class="stat-card stat-card-income">
                    <div class="stat-card-icon"><i class="fa-solid fa-euro-sign"></i></div>
                    <div class="stat-card-content">
                        <div class="stat-card-label">Kopējais budžets</div>
                        <div class="stat-card-value">€<?php echo number_format($total_budget_amount, 2); ?></div>
                    </div>
                </div>
                <div class="stat-card stat-card-expense">
                    <div class="stat-card-icon"><i class="fa-solid fa-credit-card"></i></div>
                    <div class="stat-card-content">
                        <div class="stat-card-label">Tērēts</div>
                        <div class="stat-card-value">€<?php echo number_format($total_spent, 2); ?></div>
                    </div>
                </div>
                <div class="stat-card stat-card-balance">
                    <div class="stat-card-icon"><i class="fa-solid fa-piggy-bank"></i></div>
                    <div class="stat-card-content">
                        <div class="stat-card-label">Atlikums</div>
                        <div class="stat-card-value">€<?php echo number_format($total_remaining, 2); ?></div>
                    </div>
                </div>
            </div>

            <?php if (empty($budgets)): ?>
                <div class="calendar-container" style="text-align: center; padding: 80px 40px;">
                    <div style="font-size: 64px; margin-bottom: 20px; opacity: 0.3;">
                        <i class="fa-solid fa-wallet"></i>
                    </div>
                    <h3 style="font-size: 24px; margin-bottom: 12px;">Nav izveidoti budžeti</h3>
                    <p style="color: var(--text-secondary); margin-bottom: 30px;">Sāc pārvaldīt savus izdevumus, izveidojot savu pirmo budžetu!</p>
                    <button class="btn btn-primary" onclick="openAddModal()">
                        <i class="fa-solid fa-plus"></i> Izveidot budžetu
                    </button>
                </div>
            <?php else: ?>
                <div class="budgets-grid">
                    <?php foreach ($budgets as $budget): 
                        $end_date = strtotime($budget['end_date']);
                        $is_active = $end_date >= time();
                        $percentage = min($budget['percentage'], 100);
                        
                        // Determine status and color
                        if (!$is_active) {
                            $status_class = 'status-expired';
                            $status_text = 'Beidzies';
                            $progress_class = 'progress-danger';
                        } elseif ($percentage >= $budget['warning_threshold']) {
                            $status_class = 'status-warning';
                            $status_text = 'Brīdinājums';
                            $progress_class = 'progress-warning';
                        } else {
                            $status_class = 'status-active';
                            $status_text = 'Aktīvs';
                            $progress_class = 'progress-safe';
                        }
                    ?>
                        <div class="budget-card">
                            <div class="budget-card-header">
                                <div>
                                    <div class="budget-card-title">
                                        <i class="fa-solid fa-wallet"></i>
                                        <?php echo htmlspecialchars($budget['budget_name']); ?>
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
                                    <span class="amount-label">Budžets:</span>
                                    <span class="amount-value">€<?php echo number_format($budget['budget_amount'], 2); ?></span>
                                </div>
                                <div class="budget-amount-row">
                                    <span class="amount-label">Tērēts:</span>
                                    <span class="amount-value amount-spent">€<?php echo number_format($budget['spent'], 2); ?></span>
                                </div>
                                <div class="budget-amount-row">
                                    <span class="amount-label">Atlikums:</span>
                                    <span class="amount-value amount-remaining">€<?php echo number_format($budget['remaining'], 2); ?></span>
                                </div>
                            </div>

                            <div class="budget-progress">
                                <div class="budget-progress-bar <?php echo $progress_class; ?>" 
                                     style="width: <?php echo min($percentage, 100); ?>%">
                                </div>
                            </div>

                            <div style="text-align: center; color: var(--text-secondary); font-size: 12px; margin-top: 8px;">
                                <?php echo number_format($percentage, 1); ?>% izmantots
                            </div>

                            <div class="budget-actions">
                                <button class="btn btn-secondary btn-small" style="flex: 1;" 
                                        onclick='openEditModal(<?php echo json_encode($budget); ?>)'>
                                    <i class="fa-solid fa-pencil"></i> Rediģēt
                                </button>
                                <form method="POST" style="flex: 1;" onsubmit="return confirm('Vai tiešām vēlies dzēst šo budžetu?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="budget_id" value="<?php echo $budget['id']; ?>">
                                    <button type="submit" class="btn btn-danger btn-small" style="width: 100%;">
                                        <i class="fa-solid fa-trash"></i> Dzēst
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Add Budget Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Pievienot jaunu budžetu</h2>
                <button class="modal-close" onclick="closeAddModal()">✕</button>
            </div>
            <form method="POST" class="modal-form">
                <input type="hidden" name="action" value="add">
                
                <div class="form-group">
                    <label class="form-label">Budžeta nosaukums *</label>
                    <input type="text" name="budget_name" class="form-input" placeholder="piem. Nedēļas nogales budžets" required>
                </div>

                <div class="form-group">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div>
                            <label class="form-label">Summa (€) *</label>
                            <input type="number" name="budget_amount" class="form-input" step="0.01" min="0" placeholder="100.00" required>
                        </div>
                        <div>
                            <label class="form-label">Periods *</label>
                            <select name="budget_period" class="form-input" required>
                                <option value="daily">Dienas</option>
                                <option value="weekly">Nedēļas</option>
                                <option value="monthly" selected>Mēneša</option>
                                <option value="custom">Pielāgots</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div>
                            <label class="form-label">Sākuma datums *</label>
                            <input type="date" name="start_date" class="form-input" required>
                        </div>
                        <div>
                            <label class="form-label">Beigu datums *</label>
                            <input type="date" name="end_date" class="form-input" required>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Brīdinājuma slieksnis (%) *</label>
                    <input type="number" name="warning_threshold" class="form-input" min="0" max="100" value="80" required>
                    <small style="color: var(--text-secondary); font-size: 12px;">Tu saņemsi brīdinājumu, kad tērējumi sasniegs šo procentu</small>
                </div>

                <button type="submit" class="btn btn-primary btn-full">
                    Pievienot budžetu
                </button>
            </form>
        </div>
    </div>

    <!-- Edit Budget Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Rediģēt budžetu</h2>
                <button class="modal-close" onclick="closeEditModal()">✕</button>
            </div>
            <form method="POST" class="modal-form">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="budget_id" id="edit_budget_id">
                
                <div class="form-group">
                    <label class="form-label">Budžeta nosaukums *</label>
                    <input type="text" name="budget_name" id="edit_budget_name" class="form-input" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Summa (€) *</label>
                    <input type="number" name="budget_amount" id="edit_budget_amount" class="form-input" step="0.01" min="0" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Brīdinājuma slieksnis (%) *</label>
                    <input type="number" name="warning_threshold" id="edit_warning_threshold" class="form-input" min="0" max="100" required>
                </div>

                <button type="submit" class="btn btn-primary btn-full">
                    Atjaunināt budžetu
                </button>
            </form>
        </div>
    </div>

    <!-- Mobile bottom navigation -->
    <nav class="mobile-bottom-nav">
        <a href="calendar.php" class="mobile-nav-item">
            <i class="fa-solid fa-calendar"></i>
            <span>Kalendārs</span>
        </a>
        <a href="parskati.php" class="mobile-nav-item">
            <i class="fa-solid fa-chart-pie"></i>
            <span>Pārskati</span>
        </a>
        <a href="budget.php" class="mobile-nav-item active">
            <i class="fa-solid fa-wallet"></i>
            <span>Budžets</span>
        </a>
        <a href="#" class="mobile-nav-item">
            <i class="fa-solid fa-gear"></i>
            <span>Iestatījumi</span>
        </a>
        <a href="logout.php" class="mobile-nav-item">
            <i class="fa-solid fa-right-from-bracket"></i>
            <span>Iziet</span>
        </a>
    </nav>
                    
    <script src="../js/script.js"></script>
    <script src="../js/budget.js"></script>
</body>
</html>