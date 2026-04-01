// Month names used across calendar rendering and day modal titles
const monthNames = ['', 'Janvāris', 'Februāris', 'Marts', 'Aprīlis', 'Maijs', 'Jūnijs',
                    'Jūlijs', 'Augusts', 'Septembris', 'Oktobris', 'Novembris', 'Decembris'];

// open income modal
function openIncomeModal() {
    const modal = document.getElementById('incomeModal');
    modal.classList.add('modal-open');
    document.body.style.overflow = 'hidden';
}

// close income modal
function closeIncomeModal() {
    const modal = document.getElementById('incomeModal');
    modal.classList.remove('modal-open');
    document.body.style.overflow = 'auto';
}

// open expense modal
function openExpenseModal() {
    const modal = document.getElementById('expenseModal');
    modal.classList.add('modal-open');
    document.body.style.overflow = 'hidden';
}

// close expense modal
function closeExpenseModal() {
    const modal = document.getElementById('expenseModal');
    modal.classList.remove('modal-open');
    document.body.style.overflow = 'auto';
}

// open day details
function openDayModal(day, month, year) {
    const modal   = document.getElementById('dayModal');
    const title   = document.getElementById('dayModalTitle');
    const content = document.getElementById('dayModalContent');
    const monthNames = ['', 'Janvāris', 'Februāris', 'Marts', 'Aprīlis', 'Maijs', 'Jūnijs', 
                        'Jūlijs', 'Augusts', 'Septembris', 'Oktobris', 'Novembris', 'Decembris'];
    title.textContent = `${day}. ${monthNames[month]}, ${year}`;
    
    const transactions = Array.isArray(transactionsData[day]) ? transactionsData[day] : (Array.isArray(transactionsData[String(day)]) ? transactionsData[String(day)] : []);
    let html = '';
    
    if (transactions.length === 0) {
        html = '<div class="no-transactions">Nav ierakstu šajā dienā.</div>';
    } else {
        transactions.forEach(transaction => {
            const typeClass = transaction.type === 'income' ? 'income' : 'expense';
            const typeLabel = transaction.type === 'income' ? 'Ienākums' : 'Izdevums';
            const sign      = transaction.type === 'income' ? '+' : '-';
            const recurringBadge = transaction.is_recurring_display
                ? '<span class="recurring-badge"><i class="fa-solid fa-rotate"></i> Ikmēneša</span>' : '';
            const recurringInfo = transaction.is_recurring_display
                ? '<div class="transaction-note"></div>'
                : '';
            
            const deleteBtn = `<form method="POST" action="" class="delete-form">
                       <input type="hidden" name="delete_transaction" value="1">
                       <input type="hidden" name="transaction_id" value="${transaction.id}">
                       <button type="submit" class="delete-btn" title="Dzēst">
                           <i class="fa-solid fa-trash"></i>
                       </button>
                   </form>`;
            
            html += `
                <div class="transaction-item ${typeClass}">
                    <div class="transaction-info">
                        <div class="transaction-description">${transaction.description} ${recurringBadge}</div>
                        <div class="transaction-type">${typeLabel}</div>
                        ${recurringInfo}
                    </div>
                    <div class="transaction-right">
                        <div class="transaction-amount">${sign}€${parseFloat(transaction.amount).toFixed(2)}</div>
                        ${deleteBtn}
                    </div>
                </div>
            `;
        });
    }
    
    content.innerHTML = html;
    modal.classList.add('modal-open');
    document.body.style.overflow = 'hidden';
}

// close day modal
function closeDayModal() {
    const modal = document.getElementById('dayModal');
    modal.classList.remove('modal-open');
    document.body.style.overflow = 'auto';
}

function formatNumber(value) {
    return Number(value).toLocaleString('lv-LV', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

function closeDeleteConfirm() {
    const modal = document.getElementById('deleteConfirmModal');
    if (!modal) return;
    modal.classList.remove('modal-open');
    document.body.style.overflow = 'auto';
    setTimeout(() => modal.remove(), 250);
}

function showDeleteConfirm(onConfirm) {
    let existing = document.getElementById('deleteConfirmModal');
    if (existing) existing.remove();

    const modal = document.createElement('div');
    modal.id = 'deleteConfirmModal';
    modal.className = 'modal modal-open';
    modal.innerHTML = `
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Apstiprināt dzēšanu</h2>
                <button type="button" class="modal-close" aria-label="Aizvērt">✕</button>
            </div>
            <div class="modal-body">
                <p>Vai tiešām vēlies dzēst šo ierakstu? Šī darbība nevar tikt atsaukta.</p>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" id="deleteCancelBtn">Atcelt</button>
                <button type="button" class="btn btn-danger" id="deleteConfirmBtn">
                    <i class="fa-solid fa-trash"></i> Dzēst
                </button>
            </div>
        </div>`;

    document.body.appendChild(modal);
    document.body.style.overflow = 'hidden';

    modal.querySelector('.modal-close').addEventListener('click', closeDeleteConfirm);
    modal.querySelector('#deleteCancelBtn').addEventListener('click', closeDeleteConfirm);
    modal.querySelector('#deleteConfirmBtn').addEventListener('click', function() {
        closeDeleteConfirm();
        onConfirm();
    });
    modal.addEventListener('click', function(event) {
        if (event.target === modal) closeDeleteConfirm();
    });
}

function submitDeleteForm(form) {
    const formData = new FormData(form);
    formData.append('ajax', 1);

    fetch('calendar.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
        body: formData
    })
    .then(response => {
        if (!response.ok) throw new Error('Failed to delete transaction');
        return response.json();
    })
    .then(data => {
        if (data.success) {
            closeDeleteConfirm();
            closeDayModal();
            loadCalendarMonth(currentMonth, currentYear, false);
        } else {
            form.dataset.skipAjax = '1';
            form.submit();
        }
    })
    .catch(() => {
        form.dataset.skipAjax = '1';
        form.submit();
    });
}

function updateCalendarHeader(data) {
    const monthTitle = document.querySelector('.calendar-month');
    if (monthTitle) {
        monthTitle.textContent = `${data.month_name} ${data.current_year}`;
    }

    document.querySelectorAll('.calendar-nav').forEach(link => {
        if (link.dataset.direction === 'prev') {
            link.href = `?month=${data.prev_month}&year=${data.prev_year}`;
            link.dataset.month = data.prev_month;
            link.dataset.year = data.prev_year;
        } else if (link.dataset.direction === 'next') {
            link.href = `?month=${data.next_month}&year=${data.next_year}`;
            link.dataset.month = data.next_month;
            link.dataset.year = data.next_year;
        }
    });
}

function renderSummary(data) {
    const incomeValue = document.querySelector('.stat-card-income .stat-card-value');
    const expenseValue = document.querySelector('.stat-card-expense .stat-card-value');
    const balanceValue = document.querySelector('.stat-card-balance .stat-card-value');
    const todayCard = document.querySelector('.stat-card-today');

    if (incomeValue) incomeValue.innerHTML = currencySymbol + formatNumber(data.total_income);
    if (expenseValue) expenseValue.innerHTML = currencySymbol + formatNumber(data.total_expense);
    if (balanceValue) balanceValue.innerHTML = currencySymbol + formatNumber(data.balance);

    if (todayCard) {
        const todayValue = todayCard.querySelector('.stat-card-value');
        todayCard.style.display = data.is_current_month ? '' : 'none';
        if (todayValue) {
            todayValue.innerHTML = currencySymbol + formatNumber(data.today_balance);
        }
    }
}

function renderCalendarGrid(data) {
    const calendarGrid = document.querySelector('.calendar-grid');
    if (!calendarGrid) return;

    while (calendarGrid.children.length > 7) {
        calendarGrid.removeChild(calendarGrid.lastChild);
    }

    const daysInMonth = parseInt(data.days_in_month, 10);
    const firstWeekday = parseInt(data.first_weekday, 10) || 1;
    const today = new Date();
    const todayDay = today.getDate();
    const todayMonth = today.getMonth() + 1;
    const todayYear = today.getFullYear();

    for (let i = 1; i < firstWeekday; i++) {
        const emptyDay = document.createElement('div');
        emptyDay.className = 'calendar-day calendar-day-empty';
        calendarGrid.appendChild(emptyDay);
    }

    for (let day = 1; day <= daysInMonth; day++) {
        const dayTransactions = Array.isArray(data.transactions?.[day]) ? data.transactions[day] : (data.transactions?.[String(day)] || []);
        const hasTransactions = dayTransactions.length > 0;

        const dayDiv = document.createElement('div');
        let classes = 'calendar-day';

        if (day === todayDay && data.current_month === todayMonth && data.current_year === todayYear) {
            classes += ' calendar-day-today';
        }
        if (hasTransactions) {
            classes += ' calendar-day-has-data';
        }

        dayDiv.className = classes;
        dayDiv.dataset.day = day;
        dayDiv.addEventListener('click', function() {
            openDayModal(day, data.current_month, data.current_year);
        });

        dayDiv.innerHTML = `<div class="calendar-day-number">${day}</div>`;

        if (hasTransactions) {
            let transactionsHtml = '<div class="calendar-day-transactions">';
            const dayIncome = dayTransactions.reduce((sum, transaction) => {
                return sum + (transaction.type === 'income' ? parseFloat(transaction.amount) : 0);
            }, 0);
            const dayExpense = dayTransactions.reduce((sum, transaction) => {
                return sum + (transaction.type === 'expense' ? parseFloat(transaction.amount) : 0);
            }, 0);

            if (dayIncome > 0) {
                transactionsHtml += `<div class="calendar-transaction-badge income">+${currencySymbol}${formatNumber(dayIncome)}</div>`;
            }
            if (dayExpense > 0) {
                transactionsHtml += `<div class="calendar-transaction-badge expense">-${currencySymbol}${formatNumber(dayExpense)}</div>`;
            }
            transactionsHtml += '</div>';
            dayDiv.innerHTML += transactionsHtml;
        }

        calendarGrid.appendChild(dayDiv);
    }
}

function refreshCalendar(data, pushState = false) {
    transactionsData = data.transactions || {};
    monthlyIncome = parseFloat(data.total_income) || 0;
    monthlyExpense = parseFloat(data.total_expense) || 0;
    currentMonth = parseInt(data.current_month, 10) || currentMonth;
    currentYear = parseInt(data.current_year, 10) || currentYear;
    currencySymbol = data.currency_symbol || currencySymbol;
    activeBudgets = data.activeBudgets || [];

    renderSummary(data);
    renderCalendarGrid(data);
    updateCalendarHeader(data);
    attachCalendarNavHandlers();
    attachCalendarDayHandlers();

    if (pushState && window.history && window.history.pushState) {
        window.history.pushState({ month: currentMonth, year: currentYear }, '', `?month=${currentMonth}&year=${currentYear}`);
        document.title = `Kalendārs - ${data.month_name} ${currentYear} - Budgetiva`;
    }
}

function loadCalendarMonth(month, year, pushState = true) {
    fetch(`calendar.php?month=${month}&year=${year}&ajax=1`, {
        credentials: 'same-origin'
    })
    .then(response => {
        if (!response.ok) throw new Error('Network response was not ok');
        return response.json();
    })
    .then(data => {
        if (!data.success) throw new Error('Server did not return JSON success');
        refreshCalendar(data, pushState);
    })
    .catch(() => {
        window.location.href = `calendar.php?month=${month}&year=${year}`;
    });
}

function handleCalendarNavClick(event) {
    event.preventDefault();
    const month = parseInt(this.dataset.month, 10);
    const year = parseInt(this.dataset.year, 10);
    if (Number.isFinite(month) && Number.isFinite(year)) {
        loadCalendarMonth(month, year);
    }
}

function onCalendarDayClick() {
    const day = parseInt(this.dataset.day, 10);
    if (Number.isFinite(day)) {
        openDayModal(day, currentMonth, currentYear);
    }
}

function attachCalendarDayHandlers() {
    document.querySelectorAll('.calendar-day:not(.calendar-day-empty)').forEach(day => {
        day.removeEventListener('click', onCalendarDayClick);
        day.addEventListener('click', onCalendarDayClick);
    });
}

function attachCalendarNavHandlers() {
    document.querySelectorAll('.calendar-nav').forEach(link => {
        link.removeEventListener('click', handleCalendarNavClick);
        link.addEventListener('click', handleCalendarNavClick);
    });
}

function handleTransactionFormSubmit(e) {
    if (e.defaultPrevented) return;
    const form = e.target;
    if (!(form instanceof HTMLFormElement)) return;
    if (form.dataset.skipAjax === '1') return;
    if (form.querySelector('input[name="add_income"], input[name="add_expense"]')) {
        e.preventDefault();
        const formData = new FormData(form);
        formData.append('ajax', 1);

        fetch('calendar.php', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            body: formData
        })
        .then(response => {
            if (!response.ok) throw new Error('Failed to submit transaction');
            return response.json();
        })
        .then(data => {
            if (data.success) {
                closeIncomeModal();
                closeExpenseModal();
                loadCalendarMonth(currentMonth, currentYear, false);
            } else {
                form.dataset.skipAjax = '1';
                form.submit();
            }
        })
        .catch(() => {
            form.dataset.skipAjax = '1';
            form.submit();
        });
        return;
    }

    if (form.querySelector('input[name="delete_transaction"]')) {
        e.preventDefault();
        showDeleteConfirm(() => submitDeleteForm(form));
        return;
    }

    return;
}

window.addEventListener('DOMContentLoaded', function() {
    attachCalendarNavHandlers();
    attachCalendarDayHandlers();
    document.addEventListener('submit', handleTransactionFormSubmit);
});

// close modal if click outside
window.addEventListener('click', function(e) {
    if (e.target === document.getElementById('incomeModal'))  closeIncomeModal();
    if (e.target === document.getElementById('expenseModal')) closeExpenseModal();
    if (e.target === document.getElementById('dayModal'))     closeDayModal();
    const wm = document.getElementById('warningModal');
    if (wm && e.target === wm) closeWarningModal();
    const bm = document.getElementById('budgetWarningModal');
    if (bm && e.target === bm) closeBudgetWarningModal();
    const dm = document.getElementById('deleteConfirmModal');
    if (dm && e.target === dm) closeDeleteConfirm();
});

// close modal with esc
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeIncomeModal();
        closeExpenseModal();
        closeDayModal();
        closeWarningModal();
        closeBudgetWarningModal();
        closeDeleteConfirm();
    }
});


// ─── Expense form validation ──────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', function() {
    const expenseForm = document.querySelector('#expenseModal form');
    
    if (expenseForm) {
        expenseForm.addEventListener('submit', function(e) {
            const expenseAmount = parseFloat(document.getElementById('expense_amount').value) || 0;
            const expenseDate   = document.getElementById('expense_date').value; // 'YYYY-MM-DD'

            // 1. Check monthly income exceed (existing behaviour)
            const newTotalExpense = monthlyExpense + expenseAmount;
            if (newTotalExpense > monthlyIncome) {
                e.preventDefault();
                showWarningModal(expenseAmount, newTotalExpense);
                return;
            }

            // 2. Check if any active budget would be exceeded by this expense
            const breachedBudgets = getBudgetBreaches(expenseDate, expenseAmount);
            if (breachedBudgets.length > 0) {
                e.preventDefault();
                showBudgetWarningModal(expenseAmount, expenseDate, breachedBudgets);
            }
        });
    }
});

/**
 * Returns an array of budget objects that would be breached by adding
 * `amount` as an expense on `dateStr` (YYYY-MM-DD).
 * Only budgets whose date range covers `dateStr` are considered.
 */
function getBudgetBreaches(dateStr, amount) {
    if (!activeBudgets || activeBudgets.length === 0) return [];

    return activeBudgets.filter(b => {
        if (dateStr < b.start_date || dateStr > b.end_date) return false;
        return (b.spent + amount) > b.budget_amount;
    });
}


// ─── Budget-exceed warning modal ──────────────────────────────────────────────

function showBudgetWarningModal(expenseAmount, expenseDate, breachedBudgets) {
    let existing = document.getElementById('budgetWarningModal');
    if (existing) existing.remove();

    let budgetRows = '';
    breachedBudgets.forEach(b => {
        const newSpent = b.spent + expenseAmount;
        const over     = newSpent - b.budget_amount;
        const pct      = Math.min((newSpent / b.budget_amount) * 100, 999).toFixed(0);
        const fillPct  = Math.min((b.spent / b.budget_amount) * 100, 100).toFixed(1);
        const overPct  = Math.min((over / b.budget_amount) * 100, 100).toFixed(1);

        budgetRows += `
            <div class="bw-budget-row">
                <div class="bw-budget-name">
                    <i class="fa-solid fa-wallet"></i>
                    <strong>${escHtml(b.budget_name)}</strong>
                </div>
                <div class="bw-budget-stats">
                    <div class="bw-stat">
                        <span class="bw-stat-label">Budžets</span>
                        <span class="bw-stat-val">€${parseFloat(b.budget_amount).toFixed(2)}</span>
                    </div>
                    <div class="bw-stat">
                        <span class="bw-stat-label">Tērēts</span>
                        <span class="bw-stat-val expense">€${parseFloat(b.spent).toFixed(2)}</span>
                    </div>
                    <div class="bw-stat">
                        <span class="bw-stat-label">Jauns izdevums</span>
                        <span class="bw-stat-val expense">€${expenseAmount.toFixed(2)}</span>
                    </div>
                    <div class="bw-stat bw-stat-over">
                        <span class="bw-stat-label">Pārtērēts par</span>
                        <span class="bw-stat-val deficit">€${over.toFixed(2)}</span>
                    </div>
                </div>
                <div class="bw-progress-wrap">
                    <div class="bw-progress-track">
                        <div class="bw-progress-fill" style="width:${fillPct}%"></div>
                        <div class="bw-progress-over" style="width:${overPct}%"></div>
                    </div>
                    <span class="bw-pct">${pct}%</span>
                </div>
            </div>`;
    });

    const plural = breachedBudgets.length > 1 ? 'budžetiem' : 'budžetam';

    const modal = document.createElement('div');
    modal.id = 'budgetWarningModal';
    modal.className = 'modal modal-open';
    modal.innerHTML = `
        <div class="modal-content bw-modal-content">
            <div class="bw-modal-header">
                <div class="bw-title-wrap">
                    <div class="bw-title-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
                    <div>
                        <h2 class="modal-title bw-modal-title">Budžeta brīdinājums</h2>
                        <p class="bw-subtitle">Šis izdevums pārsniegs ${plural}</p>
                    </div>
                </div>
                <button class="modal-close" onclick="closeBudgetWarningModal()">✕</button>
            </div>
            <div class="bw-body">
                ${budgetRows}
                <p class="bw-question">Vai tiešām vēlies pievienot šo izdevumu?</p>
            </div>
            <div class="bw-actions">
                <button class="btn btn-secondary" onclick="closeBudgetWarningModal()">
                    <i class="fa-solid fa-xmark"></i> Atcelt
                </button>
                <button class="btn btn-danger" onclick="confirmBudgetExpense()">
                    <i class="fa-solid fa-check"></i> Jā, pievienot
                </button>
            </div>
        </div>`;

    document.body.appendChild(modal);
    document.body.style.overflow = 'hidden';
}

function closeBudgetWarningModal() {
    const modal = document.getElementById('budgetWarningModal');
    if (modal) {
        modal.classList.remove('modal-open');
        document.body.style.overflow = 'auto';
        setTimeout(() => modal.remove(), 300);
    }
}

function confirmBudgetExpense() {
    closeBudgetWarningModal();
    const expenseForm = document.querySelector('#expenseModal form');
    if (expenseForm) {
        const newForm = expenseForm.cloneNode(true);
        expenseForm.parentNode.replaceChild(newForm, expenseForm);
        newForm.submit();
    }
}

/** Simple HTML escape helper */
function escHtml(str) {
    return String(str)
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}


// ─── Income-exceed warning modal ──────────────────────────────────────────────

function showWarningModal(expenseAmount, newTotalExpense) {
    const deficit = newTotalExpense - monthlyIncome;
    
    let warningModal = document.getElementById('warningModal');
    if (!warningModal) {
        warningModal = document.createElement('div');
        warningModal.id = 'warningModal';
        warningModal.className = 'modal';
        warningModal.innerHTML = `
            <div class="modal-content warning-modal-content">
                <div class="modal-header">
                    <h2 class="modal-title warning-title">⚠️ Brīdinājums!</h2>
                </div>
                <div class="warning-message">
                    <p class="warning-text">Šis izdevums pārsniegs tavus mēneša ienākumus!</p>
                    <div class="warning-details">
                        <div class="warning-stat">
                            <span class="warning-label">Mēneša ienākumi:</span>
                            <span class="warning-value income">+€${monthlyIncome.toFixed(2)}</span>
                        </div>
                        <div class="warning-stat">
                            <span class="warning-label">Pašreizējie izdevumi:</span>
                            <span class="warning-value expense">-€${monthlyExpense.toFixed(2)}</span>
                        </div>
                        <div class="warning-stat">
                            <span class="warning-label">Jauns izdevums:</span>
                            <span class="warning-value expense">-€${expenseAmount.toFixed(2)}</span>
                        </div>
                        <div class="warning-divider"></div>
                        <div class="warning-stat total">
                            <span class="warning-label">Kopējie izdevumi:</span>
                            <span class="warning-value expense">-€${newTotalExpense.toFixed(2)}</span>
                        </div>
                        <div class="warning-stat deficit">
                            <span class="warning-label">Deficīts:</span>
                            <span class="warning-value deficit-value">-€${deficit.toFixed(2)}</span>
                        </div>
                    </div>
                    <p class="warning-question">Vai tiešām vēlies pievienot šo izdevumu?</p>
                </div>
                <div class="warning-actions">
                    <button class="btn btn-secondary" onclick="closeWarningModal()">Atcelt</button>
                    <button class="btn btn-danger" onclick="confirmExpense()">Jā, pievienot</button>
                </div>
            </div>
        `;
        document.body.appendChild(warningModal);
    } else {
        warningModal.querySelector('.warning-value.income').textContent = `+€${monthlyIncome.toFixed(2)}`;
        warningModal.querySelectorAll('.warning-value.expense')[0].textContent = `-€${monthlyExpense.toFixed(2)}`;
        warningModal.querySelectorAll('.warning-value.expense')[1].textContent = `-€${expenseAmount.toFixed(2)}`;
        warningModal.querySelectorAll('.warning-value.expense')[2].textContent = `-€${newTotalExpense.toFixed(2)}`;
        warningModal.querySelector('.warning-value.deficit-value').textContent = `-€${deficit.toFixed(2)}`;
    }
    
    warningModal.classList.add('modal-open');
    document.body.style.overflow = 'hidden';
}

function closeWarningModal() {
    const modal = document.getElementById('warningModal');
    if (modal) {
        modal.classList.remove('modal-open');
        document.body.style.overflow = 'auto';
    }
}

function confirmExpense() {
    closeWarningModal();
    const expenseForm = document.querySelector('#expenseModal form');
    if (expenseForm) {
        const newForm = expenseForm.cloneNode(true);
        expenseForm.parentNode.replaceChild(newForm, expenseForm);
        newForm.submit();
    }
}


// ─── Animations ───────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', function() {
    const statCards = document.querySelectorAll('.stat-card');
    statCards.forEach((card, index) => {
        setTimeout(() => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            setTimeout(() => {
                card.style.transition = 'all 0.5s ease-out';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, 50);
        }, index * 100);
    });
});

const calendarDays = document.querySelectorAll('.calendar-day:not(.calendar-day-empty)');
calendarDays.forEach(day => {
    day.addEventListener('mouseenter', function() {
        if (!this.classList.contains('calendar-day-empty')) {
            this.style.transform = 'scale(1.05)';
        }
    });
    day.addEventListener('mouseleave', function() {
        this.style.transform = 'scale(1)';
    });
});

document.addEventListener('DOMContentLoaded', function() {
    if (window.innerWidth <= 480) {
        const todayCard = document.querySelector('.calendar-day-today');
        if (todayCard) {
            setTimeout(() => {
                todayCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }, 150);
        }
    }
});