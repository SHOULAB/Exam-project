<?php
/**
 * sidebar.php — Shared admin sidebar include
 *
 * Expects this variable to already be set by the including page:
 *   $active_page  (string)  — one of: 'dashboard', 'users'
 */
?>
<aside class="sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <img src="../../assets/image/logo.png" alt="Budgetar Logo" class="logo-img">
            <span class="logo-text">Admin Panel</span>
        </div>
    </div>

    <nav class="sidebar-nav">
        <a href="index.php" class="nav-item <?php echo ($active_page === 'dashboard') ? 'active' : ''; ?>">
            <span class="nav-icon"><i class="fa-solid fa-chart-pie"></i></span>
            <span class="nav-text">Dashboard</span>
        </a>
        <a href="users.php" class="nav-item <?php echo ($active_page === 'users') ? 'active' : ''; ?>">
            <span class="nav-icon"><i class="fa-solid fa-users"></i></span>
            <span class="nav-text">Lietotāji</span>
        </a>
        <a href="settings.php" class="nav-item <?php echo ($active_page === 'settings') ? 'active' : ''; ?>">
            <span class="nav-icon"><i class="fa-solid fa-gear"></i></span>
            <span class="nav-text">Iestatījumi</span>
        </a>
        <a href="../../user/php/calendar.php" class="nav-item nav-item--bottom">
            <span class="nav-icon"><i class="fa-solid fa-calendar"></i></span>
            <span class="nav-text">User Panel</span>
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="user-info">
            <div class="user-avatar"><?php echo strtoupper(substr($_SESSION['username'] ?? 'A', 0, 1)); ?></div>
            <div class="user-details">
                <div class="user-name"><?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></div>
                <div class="user-role"><?php echo htmlspecialchars($_SESSION['role'] ?? 'administrator'); ?></div>
            </div>
            <a href="../../user/php/calendar.php" class="user-logout" title="Atpakaļ uz lietotni">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
    </div>
</aside>
