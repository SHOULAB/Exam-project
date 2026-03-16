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
    <style>
        .budget-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .budget-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .budget-title {
            font-size: 32px;
            font-weight: 700;
            color: var(--text-primary);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 24px;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            border-color: var(--primary);
        }

        .stat-label {
            color: var(--text-secondary);
            font-size: 14px;
            margin-bottom: 8px;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: var(--text-primary);
        }

        .budgets-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }

        .budget-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 24px;
            transition: all 0.3s ease;
        }

        .budget-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .budget-card-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 16px;
        }

        .budget-card-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 4px;
        }

        .budget-card-period {
            font-size: 12px;
            color: var(--text-secondary);
        }

        .budget-status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }

        .status-active {
            background: rgba(16, 185, 129, 0.2);
            color: var(--success);
        }

        .status-expired {
            background: rgba(239, 68, 68, 0.2);
            color: var(--danger);
        }

        .status-warning {
            background: rgba(245, 158, 11, 0.2);
            color: var(--warning);
        }

        .budget-amounts {
            margin: 20px 0;
        }

        .budget-amount-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .amount-label {
            color: var(--text-secondary);
        }

        .amount-value {
            font-weight: 600;
        }

        .amount-spent {
            color: var(--danger);
        }

        .amount-remaining {
            color: var(--success);
        }

        .budget-progress {
            width: 100%;
            height: 8px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 4px;
            overflow: hidden;
            margin: 16px 0;
        }

        .budget-progress-bar {
            height: 100%;
            transition: width 0.3s ease;
            border-radius: 4px;
        }

        .progress-safe {
            background: linear-gradient(135deg, var(--success) 0%, #34d399 100%);
        }

        .progress-warning {
            background: linear-gradient(135deg, var(--warning) 0%, #f97316 100%);
        }

        .progress-danger {
            background: linear-gradient(135deg, var(--danger) 0%, #dc2626 100%);
        }

        .budget-actions {
            display: flex;
            gap: 8px;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid var(--border);
        }

        .btn-small {
            padding: 8px 16px;
            font-size: 13px;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            animation: fadeIn 0.3s ease;
        }

        .modal.modal-open {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: var(--bg-dark);
            border-radius: 16px;
            padding: 32px;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            border: 1px solid var(--border);
            animation: slideUp 0.3s ease;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .modal-title {
            font-size: 24px;
            font-weight: 700;
        }

        .modal-close {
            background: none;
            border: none;
            color: var(--text-secondary);
            font-size: 24px;
            cursor: pointer;
            padding: 8px;
            transition: color 0.3s ease;
        }

        .modal-close:hover {
            color: var(--text-primary);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
        }

        .empty-icon {
            font-size: 64px;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        @media (max-width: 768px) {
            .budgets-grid {
                grid-template-columns: 1fr;
            }

            .budget-header {
                flex-direction: column;
                gap: 16px;
            }

            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="budget-container">
        <nav class="navbar">
            <div class="logo">
                <img src="../../assets/image/logo.png" alt="Budgetar Logo" class="logo-img">
                <span class="logo-text">Budgetar</span>
            </div>
            <div class="nav-links">
                <a href="calendar.php">Kalendārs</a>
                <a href="parskati.php">Pārskati</a>
                <a href="budget.php" class="active">Budžeti</a>
            </div>
            <div class="nav-user">
                <span class="user-name"><?php echo htmlspecialchars($username); ?></span>
                <a href="logout.php" class="btn btn-secondary">Iziet</a>
            </div>
        </nav>

        <div class="budget-header">
            <h1 class="budget-title">Mani budžeti</h1>
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
                <div class="stat-label">Aktīvie budžeti</div>
                <div class="stat-value"><?php echo $active_budgets; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Kopējais budžets</div>
                <div class="stat-value">€<?php echo number_format($total_budget_amount, 2); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Tērēts</div>
                <div class="stat-value" style="color: var(--danger);">€<?php echo number_format($total_spent, 2); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Atlikums</div>
                <div class="stat-value" style="color: var(--success);">€<?php echo number_format($total_remaining, 2); ?></div>
            </div>
        </div>

        <?php if (empty($budgets)): ?>
            <div class="empty-state">
                <div class="empty-icon"><i class="fa-solid fa-wallet"></i></div>
                <h3>Nav izveidoti budžeti</h3>
                <p>Sāc pārvaldīt savus izdevumus, izveidojot savu pirmo budžetu!</p>
                <button class="btn btn-primary" onclick="openAddModal()" style="margin-top: 20px;">
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
    </div>

    <!-- Add Budget Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Pievienot jaunu budžetu</h2>
                <button class="modal-close" onclick="closeAddModal()"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form method="POST" class="auth-form">
                <input type="hidden" name="action" value="add">
                
                <div class="form-group">
                    <label class="form-label">Budžeta nosaukums *</label>
                    <input type="text" name="budget_name" class="form-input" placeholder="piem. Nedēļas nogales budžets" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Summa (€) *</label>
                        <input type="number" name="budget_amount" class="form-input" step="0.01" min="0" placeholder="100.00" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Periods *</label>
                        <select name="budget_period" class="form-input" required>
                            <option value="daily">Dienas</option>
                            <option value="weekly">Nedēļas</option>
                            <option value="monthly" selected>Mēneša</option>
                            <option value="custom">Pielāgots</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Sākuma datums *</label>
                        <input type="date" name="start_date" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Beigu datums *</label>
                        <input type="date" name="end_date" class="form-input" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Brīdinājuma slieksnis (%) *</label>
                    <input type="number" name="warning_threshold" class="form-input" min="0" max="100" value="80" required>
                    <span class="form-hint">Tu saņemsi brīdinājumu, kad tērējumi sasniegs šo procentu</span>
                </div>

                <div style="display: flex; gap: 12px; margin-top: 24px;">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">
                        <i class="fa-solid fa-save"></i> Saglabāt
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeAddModal()">
                        Atcelt
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Budget Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Rediģēt budžetu</h2>
                <button class="modal-close" onclick="closeEditModal()"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form method="POST" class="auth-form">
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

                <div style="display: flex; gap: 12px; margin-top: 24px;">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">
                        <i class="fa-solid fa-save"></i> Atjaunināt
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">
                        Atcelt
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="../js/script.js"></script>
    <script>
        function openAddModal() {
            document.getElementById('addModal').classList.add('modal-open');
            document.body.style.overflow = 'hidden';
        }

        function closeAddModal() {
            document.getElementById('addModal').classList.remove('modal-open');
            document.body.style.overflow = 'auto';
        }

        function openEditModal(budget) {
            document.getElementById('edit_budget_id').value = budget.id;
            document.getElementById('edit_budget_name').value = budget.budget_name;
            document.getElementById('edit_budget_amount').value = budget.budget_amount;
            document.getElementById('edit_warning_threshold').value = budget.warning_threshold;
            document.getElementById('editModal').classList.add('modal-open');
            document.body.style.overflow = 'hidden';
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.remove('modal-open');
            document.body.style.overflow = 'auto';
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('modal-open');
                document.body.style.overflow = 'auto';
            }
        }

        // Close with ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeAddModal();
                closeEditModal();
            }
        });
    </script>
</body>
</html>