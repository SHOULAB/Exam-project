<?php
/**
 * mobile_nav.php — Shared mobile bottom navigation include
 *
 * Expects:
 *   $active_page  (string)  — one of: 'calendar', 'parskati', 'budget', 'settings'
 */
?>
<nav class="mobile-bottom-nav">
    <a href="calendar.php" class="mobile-nav-item <?php echo ($active_page === 'calendar')  ? 'active' : ''; ?>">
        <i class="fa-solid fa-calendar"></i>
        <span>Kalendārs</span>
    </a>
    <a href="parskati.php" class="mobile-nav-item <?php echo ($active_page === 'parskati') ? 'active' : ''; ?>">
        <i class="fa-solid fa-chart-pie"></i>
        <span>Pārskati</span>
    </a>
    <a href="budget.php" class="mobile-nav-item <?php echo ($active_page === 'budget')    ? 'active' : ''; ?>">
        <i class="fa-solid fa-wallet"></i>
        <span>Budžets</span>
    </a>
    <a href="settings.php" class="mobile-nav-item <?php echo ($active_page === 'settings')  ? 'active' : ''; ?>">
        <i class="fa-solid fa-gear"></i>
        <span>Iestatījumi</span>
    </a>
    <a href="logout.php" class="mobile-nav-item">
        <i class="fa-solid fa-right-from-bracket"></i>
        <span>Iziet</span>
    </a>
</nav>