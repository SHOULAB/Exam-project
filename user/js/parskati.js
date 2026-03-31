// parskati.js — Reports page charts
// Expects: labels, income, expense, trend, recurringData
// injected as JSON by parskati.php before this script is loaded.
// Requires: currency.js (loaded before this file)

const savings = income.map((v, i) => parseFloat((v - expense[i]).toFixed(2)));

const gridColor  = 'rgba(255,255,255,0.07)';
const textColor  = '#94a3b8';
const fontFamily = "'Segoe UI', sans-serif";
const isMobile   = window.innerWidth <= 480;

// Helper to get current currency symbol dynamically for charts
function getCurrSymbolDynamic() {
    return getCurrencyTextSymbol(getCurrentCurrency());
}

Chart.defaults.color = textColor;
Chart.defaults.font.family = fontFamily;

const tooltipDefaults = {
    backgroundColor: 'rgba(15,23,42,0.95)',
    borderColor: 'rgba(255,255,255,0.1)',
    borderWidth: 1,
    padding: 12,
    cornerRadius: 10,
    titleFont: { weight: '700', size: 13 },
    bodyFont: { size: 12 },
    callbacks: {
        label: ctx => ` ${getCurrSymbolDynamic()}${parseFloat(ctx.raw).toFixed(2)}`
    }
};

// On mobile show fewer x-axis ticks so labels don't overlap
const xTicksMobile = isMobile
    ? { color: textColor, maxTicksLimit: 4, maxRotation: 0 }
    : { color: textColor };

const scalesDefaults = {
    x: { grid: { color: gridColor }, ticks: isMobile ? xTicksMobile : { color: textColor } },
    y: { grid: { color: gridColor }, ticks: { color: textColor, callback: v => getCurrSymbolDynamic() + v } }
};

// 1. Grouped bar chart — income vs expense per month
new Chart(document.getElementById('barChart'), {
    type: 'bar',
    data: {
        labels,
        datasets: [
            {
                label: 'Ienākumi',
                data: income,
                backgroundColor: 'rgba(16,185,129,0.8)',
                borderRadius: 6,
                borderSkipped: false,
            },
            {
                label: 'Izdevumi',
                data: expense,
                backgroundColor: 'rgba(239,68,68,0.8)',
                borderRadius: 6,
                borderSkipped: false,
            }
        ]
    },
    options: {
        responsive: true,
        plugins: { legend: { labels: { color: textColor } }, tooltip: tooltipDefaults },
        scales: scalesDefaults
    }
});

// 2. Line chart — running balance
new Chart(document.getElementById('lineChart'), {
    type: 'line',
    data: {
        labels,
        datasets: [{
            label: 'Bilance',
            data: trend,
            borderColor: '#8b5cf6',
            backgroundColor: 'rgba(139,92,246,0.15)',
            borderWidth: 2.5,
            pointBackgroundColor: '#8b5cf6',
            pointRadius: isMobile ? 2 : 4,
            fill: true,
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false }, tooltip: tooltipDefaults },
        scales: scalesDefaults
    }
});

// 3. Savings bar chart (positive green, negative red)
new Chart(document.getElementById('savingsChart'), {
    type: 'bar',
    data: {
        labels,
        datasets: [{
            label: 'Uzkrājums',
            data: savings,
            backgroundColor: savings.map(v => v >= 0 ? 'rgba(16,185,129,0.8)' : 'rgba(239,68,68,0.8)'),
            borderRadius: 6,
            borderSkipped: false,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false }, tooltip: tooltipDefaults },
        scales: {
            x: { grid: { color: gridColor }, ticks: { color: textColor } },
            y: {
                grid: { color: gridColor },
                ticks: { color: textColor, callback: v => '€' + v },
                border: { dash: [4, 4] }
            }
        }
    }
});

// 4. Donut chart — recurring vs one-time
new Chart(document.getElementById('donutChart'), {
    type: 'doughnut',
    data: {
        labels: ['Ikmēneša ienākumi', 'Vienreizēji ienākumi', 'Ikmēneša izdevumi', 'Vienreizēji izdevumi'],
        datasets: [{
            data: [
                recurringData.recurring_income,
                recurringData.onetime_income,
                recurringData.recurring_expense,
                recurringData.onetime_expense
            ],
            backgroundColor: ['#10b981','#34d399','#ef4444','#fca5a5'],
            borderColor: 'rgba(15,23,42,0.8)',
            borderWidth: 3,
            hoverOffset: 8
        }]
    },
    options: {
        responsive: true,
        cutout: '65%',
        plugins: {
            legend: { display: false },
            tooltip: {
                ...tooltipDefaults,
                callbacks: { label: ctx => ` ${ctx.label}: ${getCurrSymbolDynamic()}${parseFloat(ctx.raw).toFixed(2)}` }
            }
        }
    }
});

// 5. Area chart — income and expense as filled areas
new Chart(document.getElementById('areaChart'), {
    type: 'line',
    data: {
        labels,
        datasets: [
            {
                label: 'Ienākumi',
                data: income,
                borderColor: '#10b981',
                backgroundColor: 'rgba(16,185,129,0.15)',
                borderWidth: 2,
                pointBackgroundColor: '#10b981',
                pointRadius: isMobile ? 2 : 3,
                fill: true,
                tension: 0.4
            },
            {
                label: 'Izdevumi',
                data: expense,
                borderColor: '#ef4444',
                backgroundColor: 'rgba(239,68,68,0.12)',
                borderWidth: 2,
                pointBackgroundColor: '#ef4444',
                pointRadius: isMobile ? 2 : 3,
                fill: true,
                tension: 0.4
            }
        ]
    },
    options: {
        responsive: true,
        plugins: { legend: { labels: { color: textColor } }, tooltip: tooltipDefaults },
        scales: scalesDefaults
    }
});

// ── Listen for currency changes and reload the page to apply new currency ─────
if (typeof onCurrencyChange === 'function') {
    onCurrencyChange((newCurrency, symbol) => {
        // Reload the page to apply the new currency to all charts
        window.location.reload();
    });
}