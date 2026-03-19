<?php
session_start();
require_once('../../assets/database.php');

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: login.php");
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
        <aside class="admin-sidebar">
            <div class="admin-logo">
                <img src="../../assets/image/logo.png" alt="Budgetar Logo" class="logo-img">
                <span style="font-size: 20px; font-weight: 700;">Admin Panel</span>
            </div>

            <nav class="admin-nav">
                <a href="index.php" class="admin-nav-item active">
                    <span class="admin-nav-icon"><i class="fa-solid fa-chart-pie"></i></span>
                    <span>Dashboard</span>
                </a>
                <a href="users.php" class="admin-nav-item">
                    <span class="admin-nav-icon"><i class="fa-solid fa-user"></i></span>
                    <span>Lietotāji</span>
                </a>
                <a href="settings.php" class="admin-nav-item">
                    <span class="admin-nav-icon"><i class="fa-solid fa-gear"></i></span>
                    <span>Iestatījumi</span>
                </a>
            </nav>

            <div style="margin-top: auto;">
                <a href="logout.php" class="admin-nav-item" style="color: var(--danger);">
                    <span class="admin-nav-icon"><i class="fa-solid fa-door-closed"></i></span>
                    <span>Iziet</span>
                </a>
            </div>
        </aside>

        <main class="admin-content">
            <div class="admin-header">
                <h1 class="admin-title">Dashboard</h1>
                <div class="admin-user">
                    <span><i class="fa-solid fa-user-tie"></i></span>
                    <span><?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Admin'); ?></span>
                </div>
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