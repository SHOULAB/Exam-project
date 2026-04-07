<?php
// index.php - Homepage
?>
<!DOCTYPE html>
<html lang="lv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Budgetar</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/index.css">
    <link rel="icon" href="../../assets/image/logo.png" type="image/png">
</head>
<body>
    <div class="container">
        <nav class="navbar">
            <div class="logo">
                <img src="../../assets/image/logo.png" alt="Budgetar Logo" class="logo-img">
                <span class="logo-text">Budgetar</span>
            </div>
            <div class="nav-buttons">
                <a href="login.php" class="btn btn-secondary">Ieiet</a>
                <a href="register.php" class="btn btn-primary">Reģistrēties</a>
            </div>
        </nav>

        <main class="hero">
            <div class="hero-content">
                <h1 class="hero-title">
                    Pārskati savas finanses
                    <span class="switching-text-wrapper">
                        <b class="word-placeholder gradient-text">vienkārši un efektīvi</b>
                        <span class="switching-text">
                            <b class="word gradient-text">vienkārši un efektīvi</b>
                            <b class="word gradient-text">gudri un pārskatāmi</b>
                            <b class="word gradient-text">ātri un droši</b>
                        </span>
                    </span>
                </h1>
                <p class="hero-description">
                    Budgetar palīdz tev sekot līdzi ienākumiem un izdevumiem, 
                    analizēt finanšu plūsmas un sasniegt savus finanšu mērķus.
                </p>
                <div class="hero-buttons">
                    <a href="register.php" class="btn btn-large btn-primary">
                        Sākt tagad
                        <span class="arrow">→</span>
                    </a>
                    <a href="#features" class="btn btn-large btn-outline">
                        Uzzināt vairāk
                    </a>
                </div>
            </div>
            
            <div class="hero-image">
                <div class="card-demo">
                    <div class="card-demo-header">
                        <div class="card-demo-title">Šī mēneša pārskats</div>
                        <div class="card-demo-date">Decembris 2024</div>
                    </div>
                    <div class="card-demo-stats">
                        <div class="stat stat-income">
                            <div class="stat-label">Ienākumi</div>
                            <div class="stat-value">+€2,450</div>
                        </div>
                        <div class="stat stat-expense">
                            <div class="stat-label">Izdevumi</div>
                            <div class="stat-value">-€1,680</div>
                        </div>
                        <div class="stat stat-balance">
                            <div class="stat-label">Bilance</div>
                            <div class="stat-value">€770</div>
                        </div>
                    </div>
                    <div class="card-demo-chart">
                        <div class="chart-bar" style="height: 60%"></div>
                        <div class="chart-bar" style="height: 75%"></div>
                        <div class="chart-bar" style="height: 45%"></div>
                        <div class="chart-bar" style="height: 85%"></div>
                        <div class="chart-bar" style="height: 55%"></div>
                    </div>
                </div>
            </div>
        </main>

        <footer class="footer">
            <div class="footer-content">
                <p class="footer-text">2025 Budgetar | Dagnis Janeks 4PT</p>
            </div>
        </footer>
    </div>
    
    <script src="../js/script.js"></script>
    <script src="../js/index.js"></script>
</body>
</html>