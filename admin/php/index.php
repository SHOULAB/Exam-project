<?php
session_start();
require_once('../../assets/database.php');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['administrator', 'moderator'])) {
    header("Location: ../../user/php/login.php");
    exit();
}

$stats = [
    'total_users' => 0,
    'active_users' => 0,
    'total_transactions' => 0,
    'total_income' => 0,
    'total_expenses' => 0
];

$result = mysqli_query($savienojums, "SELECT COUNT(*) as count FROM BU_users");
if ($result) {
    $row = mysqli_fetch_assoc($result);
    $stats['total_users'] = $row['count'];
}

$system_info = [
    'php_version' => phpversion(),
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'database_version' => mysqli_get_server_info($savienojums)
];
?>
<!DOCTYPE html>
<html lang="lv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Budgetar</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="icon" href="../../assets/image/logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">
</head>
<body>
    <div class="admin-container">
        <?php $active_page = 'dashboard'; include 'sidebar.php'; ?>

        <main class="admin-content">
            <div class="admin-header">
                <h1 class="admin-title">Dashboard</h1>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div>
                            <div class="stat-card-label">Kopā lietotāji</div>
                            <div class="stat-card-value"><?php echo number_format($stats['total_users']); ?></div>
                        </div>
                        <div class="stat-card-icon"><i class="fa-solid fa-user"></i></div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-header">
                        <div>
                            <div class="stat-card-label">Aktīvi lietotāji</div>
                            <div class="stat-card-value"><?php echo number_format($stats['active_users']); ?></div>
                        </div>
                        <div class="stat-card-icon"><i class="fa-solid fa-check"></i></div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-header">
                        <div>
                            <div class="stat-card-label">Transakcijas</div>
                            <div class="stat-card-value"><?php echo number_format($stats['total_transactions']); ?></div>
                        </div>
                        <div class="stat-card-icon"><i class="fa-solid fa-credit-card"></i></div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-header">
                        <div>
                            <div class="stat-card-label">Sistēmas statuss</div>
                            <div class="stat-card-value" style="font-size: 20px;">Online</div>
                        </div>
                        <div class="stat-card-icon"><i class="fa-solid fa-globe"></i></div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="../js/script.js"></script>
</body>
</html>