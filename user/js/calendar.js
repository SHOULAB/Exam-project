// Month names used across calendar rendering and day modal titles
const monthNames = (typeof calendarStrings !== 'undefined' && calendarStrings.monthNames)
    ? calendarStrings.monthNames
    : ['', 'Janvāris', 'Februāris', 'Marts', 'Aprīlis', 'Maijs', 'Jūnijs',
       'Jūlijs', 'Augusts', 'Septembris', 'Oktobris', 'Novembris', 'Decembris'];

function openTransactionModal(date = null, type = 'income') {
    const modal = document.getElementById('transactionModal');
    const dateInput = document.getElementById('transaction_date');
    const amountInput = document.getElementById('transaction_amount');
    const descInput = document.getElementById('transaction_description');
    const recurringCheck = modal.querySelector('input[name="is_recurring_transaction"]');

    dateInput.value = date || new Date().toISOString().split('T')[0];
    amountInput.value = '';
    descInput.value = '';
    if (recurringCheck) recurringCheck.checked = false;

    setTransactionType(type);
    modal.classList.add('modal-open');
    document.body.style.overflow = 'hidden';
}

function closeTransactionModal() {
    const modal = document.getElementById('transactionModal');
    modal.classList.remove('modal-open');
    document.body.style.overflow = 'auto';
}

function setTransactionType(type) {
    document.getElementById('transaction_type').value = type;
    const incomeBtn  = document.getElementById('toggleIncome');
    const expenseBtn = document.getElementById('toggleExpense');
    const submitBtn  = document.getElementById('transactionSubmitBtn');
    const recurringLabel = document.getElementById('recurringLabel');
    const modalTitle = document.getElementById('transactionModalTitle');
    const ignoreBudgetGroup = document.getElementById('ignoreBudgetGroup');
    const ignoreBudgetCheck = document.getElementById('ignoreBudgetCheck');

    if (type === 'income') {
        incomeBtn.classList.add('active');
        expenseBtn.classList.remove('active');
        submitBtn.textContent = calendarStrings.addIncome;
        submitBtn.className = 'btn btn-success btn-full';
        recurringLabel.textContent = calendarStrings.recurIncome;
        modalTitle.textContent = calendarStrings.addIncome;
        if (ignoreBudgetGroup) ignoreBudgetGroup.style.display = 'none';
        if (ignoreBudgetCheck) ignoreBudgetCheck.checked = false;
    } else {
        expenseBtn.classList.add('active');
        incomeBtn.classList.remove('active');
        submitBtn.textContent = calendarStrings.addExpense;
        submitBtn.className = 'btn btn-danger btn-full';
        recurringLabel.textContent = calendarStrings.recurExpense;
        modalTitle.textContent = calendarStrings.addExpense;
        if (ignoreBudgetGroup) ignoreBudgetGroup.style.display = '';
    }
}

// open day details
function openDayModal(day, month, year) {
    const modal   = document.getElementById('dayModal');
    const title   = document.getElementById('dayModalTitle');
    const content = document.getElementById('dayModalContent');
    const monthNames = (typeof calendarStrings !== 'undefined' && calendarStrings.monthNames)
        ? calendarStrings.monthNames
        : ['', 'Janvāris', 'Februāris', 'Marts', 'Aprīlis', 'Maijs', 'Jūnijs', 
           'Jūlijs', 'Augusts', 'Septembris', 'Oktobris', 'Novembris', 'Decembris'];
    title.textContent = `${day}. ${monthNames[month]}, ${year}`;
    window._dayModalOpenDay = day;
    
    const transactions = Array.isArray(transactionsData[day]) ? transactionsData[day] : (Array.isArray(transactionsData[String(day)]) ? transactionsData[String(day)] : []);
    let html = '';
    
    if (transactions.length === 0) {
        html = `<div class="no-transactions">${calendarStrings.noEntries}</div>`;
    } else {
        transactions.forEach(transaction => {
            const typeClass = transaction.type === 'income' ? 'income' : 'expense';
            const typeLabel = transaction.type === 'income' ? calendarStrings.typeIncome : calendarStrings.typeExpense;
            const sign      = transaction.type === 'income' ? '+' : '-';
            const recurringBadge = transaction.is_recurring_display
                ? `<span class="recurring-badge"><i class="fa-solid fa-rotate"></i> ${calendarStrings.badgeMonthly}</span>` : '';
            const ignoreBudgetBadge = (transaction.ignore_budget == 1 && transaction.type === 'expense')
                ? `<span class="ignore-budget-badge"><i class="fa-solid fa-eye-slash"></i> ${calendarStrings.badgeIgnoreBudget}</span>` : '';
            const recurringInfo = transaction.is_recurring_display
                ? '<div class="transaction-note"></div>'
                : '';
            
            const deleteBtn = `<button type="button" class="delete-btn" title="Dzēst"
                       onclick="handleDeleteClick(${parseInt(transaction.id, 10)})">
                       <i class="fa-solid fa-trash"></i>
                   </button>`;
            
            const currSymbol = getCurrencyTextSymbol(getCurrentCurrency());
            html += `
                <div class="transaction-item ${typeClass}">
                    <div class="transaction-info">
                        <div class="transaction-description">${transaction.description} ${recurringBadge}${ignoreBudgetBadge}</div>
                        <div class="transaction-type">${typeLabel}</div>
                        ${recurringInfo}
                    </div>
                    <div class="transaction-right">
                        <div class="transaction-amount">${sign}${currSymbol}${parseFloat(transaction.amount).toFixed(2)}</div>
                        ${deleteBtn}
                    </div>
                </div>
            `;
        });
    }
    
    const dateStr = `${year}-${String(month).padStart(2,'0')}-${String(day).padStart(2,'0')}`;
    html += `
        <div class="day-modal-add-btn">
            <button type="button" class="btn btn-primary" onclick="openTransactionModal('${dateStr}')">
                <i class="fa-solid fa-plus"></i> ${calendarStrings.addEntry}
            </button>
        </div>
    `;
    content.innerHTML = html;
    modal.classList.add('modal-open');
    document.body.style.overflow = 'hidden';
}

// close day modal
function closeDayModal() {
    const modal = document.getElementById('dayModal');
    modal.classList.remove('modal-open');
    document.body.style.overflow = 'auto';
    window._dayModalOpenDay = null;
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
                <h2 class="modal-title">${calendarStrings.deleteTitle}</h2>
                <button type="button" class="modal-close" aria-label="Aizvērt">✕</button>
            </div>
            <div class="modal-body">
                <p>${calendarStrings.deleteMessage}</p>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" id="deleteCancelBtn">${calendarStrings.deleteCancel}</button>
                <button type="button" class="btn btn-danger" id="deleteConfirmBtn">
                    <i class="fa-solid fa-trash"></i> ${calendarStrings.deleteBtn}
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

function handleDeleteClick(transactionId) {
    showDeleteConfirm(function () {
        const formData = new FormData();
        formData.append('delete_transaction', '1');
        formData.append('transaction_id', transactionId);
        formData.append('view_month', currentMonth);
        formData.append('view_year', currentYear);

        fetch('calendar.php', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            body: formData
        })
        .then(function (response) {
            if (!response.ok) throw new Error('Failed to delete transaction');
            return response.json();
        })
        .then(function (data) {
            if (data.success) {
                closeDeleteConfirm();
                const savedDay = window._dayModalOpenDay;
                loadCalendarMonth(currentMonth, currentYear, false, function() {
                    if (savedDay) openDayModal(savedDay, currentMonth, currentYear);
                });
            } else {
                window.location.href = 'calendar.php?month=' + currentMonth + '&year=' + currentYear;
            }
        })
        .catch(function () {
            window.location.href = 'calendar.php?month=' + currentMonth + '&year=' + currentYear;
        });
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
            const savedDay = window._dayModalOpenDay;
            loadCalendarMonth(currentMonth, currentYear, false, function() {
                if (savedDay) openDayModal(savedDay, currentMonth, currentYear);
            });
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
    }
}

function loadCalendarMonth(month, year, pushState = true, onDone = null) {
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
        if (onDone) onDone();
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

function submitTransactionAjax(form) {
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
            closeTransactionModal();
            const savedDay = window._dayModalOpenDay;
            loadCalendarMonth(currentMonth, currentYear, false, function() {
                if (savedDay) openDayModal(savedDay, currentMonth, currentYear);
            });
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

function handleTransactionFormSubmit(e) {
    if (e.defaultPrevented) return;
    const form = e.target;
    if (!(form instanceof HTMLFormElement)) return;
    if (form.dataset.skipAjax === '1') return;

    if (form.querySelector('input[name="add_income"], input[name="add_expense"], input[name="add_transaction"]')) {
        e.preventDefault();

        // Run expense-specific validation before submitting
        const typeField = document.getElementById('transaction_type');
        if (typeField && typeField.value === 'expense') {
            const ignoreBudget = document.getElementById('ignoreBudgetCheck');
            const expenseAmount = parseFloat(document.getElementById('transaction_amount').value) || 0;
            const expenseDate   = document.getElementById('transaction_date').value;

            const newTotalExpense = monthlyExpense + expenseAmount;
            if (monthlyIncome > 0 && newTotalExpense > monthlyIncome) {
                closeTransactionModal();
                showWarningModal(expenseAmount, newTotalExpense);
                return;
            }

            if (!(ignoreBudget && ignoreBudget.checked)) {
                const breachedBudgets = getBudgetBreaches(expenseDate, expenseAmount);
                if (breachedBudgets.length > 0) {
                    closeTransactionModal();
                    showBudgetWarningModal(expenseAmount, expenseDate, breachedBudgets);
                    return;
                }
            }
        }

        submitTransactionAjax(form);
        return;
    }

    if (form.querySelector('input[name="delete_transaction"]')) {
        e.preventDefault();
        showDeleteConfirm(() => submitDeleteForm(form));
        return;
    }
}

window.addEventListener('DOMContentLoaded', function() {
    attachCalendarNavHandlers();
    attachCalendarDayHandlers();
    document.addEventListener('submit', handleTransactionFormSubmit);
});

// close modal if click outside
window.addEventListener('click', function(e) {
    if (e.target === document.getElementById('transactionModal')) closeTransactionModal();
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
        closeTransactionModal();
        closeDayModal();
        closeWarningModal();
        closeBudgetWarningModal();
        closeDeleteConfirm();
    }
});

/**
 * Returns an array of budget objects that would be breached by adding
 * `amount` as an expense on `dateStr` (YYYY-MM-DD).
 * Only budgets whose date range covers `dateStr` are considered.
 * For recurring budgets with specific weekdays, only those days are in scope.
 */
function getBudgetBreaches(dateStr, amount) {
    if (!activeBudgets || activeBudgets.length === 0) return [];

    return activeBudgets.filter(b => {
        if (dateStr < b.start_date || dateStr > b.end_date) return false;

        // Recurring budgets only apply on their configured days of the week.
        if (b.recurring_days && b.recurring_days !== '') {
            const allowedDays  = b.recurring_days.split(',').map(Number);
            const expenseDow   = new Date(dateStr + 'T00:00:00').getDay(); // 0=Sun … 6=Sat
            if (!allowedDays.includes(expenseDow)) return false;
        }

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
                        <span class="bw-stat-label">${calendarStrings.bwBudget}</span>
                        <span class="bw-stat-val">€${parseFloat(b.budget_amount).toFixed(2)}</span>
                    </div>
                    <div class="bw-stat">
                        <span class="bw-stat-label">${calendarStrings.bwSpent}</span>
                        <span class="bw-stat-val expense">€${parseFloat(b.spent).toFixed(2)}</span>
                    </div>
                    <div class="bw-stat">
                        <span class="bw-stat-label">${calendarStrings.bwNewExpense}</span>
                        <span class="bw-stat-val expense">€${expenseAmount.toFixed(2)}</span>
                    </div>
                    <div class="bw-stat bw-stat-over">
                        <span class="bw-stat-label">${calendarStrings.bwOver}</span>
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

    const plural = breachedBudgets.length > 1 ? calendarStrings.bwSubPlural : calendarStrings.bwSubSingle;

    const modal = document.createElement('div');
    modal.id = 'budgetWarningModal';
    modal.className = 'modal modal-open';
    modal.innerHTML = `
        <div class="modal-content bw-modal-content">
            <div class="bw-modal-header">
                <div class="bw-title-wrap">
                    <div class="bw-title-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
                    <div>
                        <h2 class="modal-title bw-modal-title">${calendarStrings.bwTitle}</h2>
                        <p class="bw-subtitle">${(calendarStrings.bwSubtitleFmt || '%s').replace('%s', plural)}</p>
                    </div>
                </div>
                <button class="modal-close" onclick="closeBudgetWarningModal()">✕</button>
            </div>
            <div class="bw-body">
                ${budgetRows}
                <p class="bw-question">${calendarStrings.bwQuestion}</p>
            </div>
            <div class="bw-actions">
                <button class="btn btn-secondary" onclick="closeBudgetWarningModal()">
                    <i class="fa-solid fa-xmark"></i> ${calendarStrings.bwCancel}
                </button>
                <button class="btn btn-danger" onclick="confirmBudgetExpense()">
                    <i class="fa-solid fa-check"></i> ${calendarStrings.bwConfirm}
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
    const form = document.getElementById('transactionForm');
    if (!form) return;
    submitTransactionAjax(form);
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
                    <h2 class="modal-title warning-title">${calendarStrings.warnTitle}</h2>
                </div>
                <div class="warning-message">
                    <p class="warning-text">${calendarStrings.warnText}</p>
                    <div class="warning-details">
                        <div class="warning-stat">
                            <span class="warning-label">${calendarStrings.warnMonthIncome}</span>
                            <span class="warning-value income">+€${monthlyIncome.toFixed(2)}</span>
                        </div>
                        <div class="warning-stat">
                            <span class="warning-label">${calendarStrings.warnCurExpense}</span>
                            <span class="warning-value expense">-€${monthlyExpense.toFixed(2)}</span>
                        </div>
                        <div class="warning-stat">
                            <span class="warning-label">${calendarStrings.warnNewExpense}</span>
                            <span class="warning-value expense">-€${expenseAmount.toFixed(2)}</span>
                        </div>
                        <div class="warning-divider"></div>
                        <div class="warning-stat total">
                            <span class="warning-label">${calendarStrings.warnTotal}</span>
                            <span class="warning-value expense">-€${newTotalExpense.toFixed(2)}</span>
                        </div>
                        <div class="warning-stat deficit">
                            <span class="warning-label">${calendarStrings.warnDeficit}</span>
                            <span class="warning-value deficit-value">-€${deficit.toFixed(2)}</span>
                        </div>
                    </div>
                    <p class="warning-question">${calendarStrings.warnQuestion}</p>
                </div>
                <div class="warning-actions">
                    <button class="btn btn-secondary" onclick="closeWarningModal()">${calendarStrings.warnCancel}</button>
                    <button class="btn btn-danger" onclick="confirmExpense()">${calendarStrings.warnConfirm}</button>
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
    const form = document.getElementById('transactionForm');
    if (!form) return;
    submitTransactionAjax(form);
}


// ─── Animations ───────────────────────────────────────────────────────────────

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


// ─── Month / Year picker ──────────────────────────────────────────────────────

let _cpOpen        = false;
let _cpMode        = 'month'; // 'month' | 'year'
let _cpDisplayYear = null;    // year shown in the month grid header
let _cpYearBase    = null;    // first year of the visible year page

const _CP_YEAR_PAGE = 12; // 4 cols × 3 rows

function _cpDropdown()  { return document.getElementById('calPickerDropdown'); }
function _cpToggleBtn() { return document.getElementById('calPickerToggle'); }

function _cpOpen_picker() {
    _cpDisplayYear = currentYear;
    _cpYearBase    = currentYear - Math.floor(_CP_YEAR_PAGE / 2);
    _cpMode        = 'month';
    _cpRenderMonth();
    const d = _cpDropdown(), b = _cpToggleBtn();
    if (d) d.classList.add('open');
    if (b) b.classList.add('open');
    _cpOpen = true;
}

function _cpClose() {
    const d = _cpDropdown(), b = _cpToggleBtn();
    if (d) d.classList.remove('open');
    if (b) b.classList.remove('open');
    _cpOpen = false;
}

function _cpRenderMonth() {
    const dropdown = _cpDropdown();
    if (!dropdown) return;
    const names = (typeof calendarStrings !== 'undefined' && calendarStrings.monthNames)
        ? calendarStrings.monthNames.slice(1)
        : ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    const todayYear  = new Date().getFullYear();
    const todayMonth = new Date().getMonth() + 1;

    let cells = '';
    for (let m = 1; m <= 12; m++) {
        let cls = 'cp-cell';
        if (m === currentMonth && _cpDisplayYear === currentYear) cls += ' cp-current';
        else if (m === todayMonth && _cpDisplayYear === todayYear) cls += ' cp-today';
        cells += `<button type="button" class="${cls}" data-cp-month="${m}">${names[m - 1]}</button>`;
    }

    const todayLabel = (typeof calendarStrings !== 'undefined' && calendarStrings.pickerToday)
        ? calendarStrings.pickerToday : 'Today';

    dropdown.innerHTML = `
        <div class="cp-nav-row">
            <button type="button" class="cp-nav-btn" data-cp-action="year-step" data-cp-delta="-1">
                <i class="fa-solid fa-chevron-left"></i>
            </button>
            <button type="button" class="cp-nav-label" data-cp-action="switch-year">${_cpDisplayYear}</button>
            <button type="button" class="cp-nav-btn" data-cp-action="year-step" data-cp-delta="1">
                <i class="fa-solid fa-chevron-right"></i>
            </button>
        </div>
        <div class="cp-month-grid">${cells}</div>
        <div class="cp-footer">
            <button type="button" class="cp-today-btn" data-cp-action="go-today">${todayLabel}</button>
        </div>`;
}

function _cpRenderYear() {
    const dropdown = _cpDropdown();
    if (!dropdown) return;
    const todayYear = new Date().getFullYear();
    const endYear   = _cpYearBase + _CP_YEAR_PAGE - 1;

    let cells = '';
    for (let i = 0; i < _CP_YEAR_PAGE; i++) {
        const y = _cpYearBase + i;
        let cls = 'cp-cell';
        if (y === currentYear) cls += ' cp-current';
        else if (y === todayYear) cls += ' cp-today';
        cells += `<button type="button" class="${cls}" data-cp-year="${y}">${y}</button>`;
    }

    dropdown.innerHTML = `
        <div class="cp-nav-row">
            <button type="button" class="cp-nav-btn" data-cp-action="year-page" data-cp-delta="-1">
                <i class="fa-solid fa-chevron-left"></i>
            </button>
            <span class="cp-nav-label cp-label-static">${_cpYearBase}&ndash;${endYear}</span>
            <button type="button" class="cp-nav-btn" data-cp-action="year-page" data-cp-delta="1">
                <i class="fa-solid fa-chevron-right"></i>
            </button>
        </div>
        <div class="cp-year-grid">${cells}</div>`;
}

function _cpHandleDropdownClick(e) {
    const target = e.target.closest('[data-cp-action],[data-cp-month],[data-cp-year]');
    if (!target) return;
    e.stopPropagation();

    const action = target.dataset.cpAction;
    const month  = target.dataset.cpMonth;
    const year   = target.dataset.cpYear;

    if (action === 'year-step') {
        _cpDisplayYear += parseInt(target.dataset.cpDelta, 10);
        _cpRenderMonth();
    } else if (action === 'switch-year') {
        _cpMode     = 'year';
        _cpYearBase = _cpDisplayYear - Math.floor(_CP_YEAR_PAGE / 2);
        _cpRenderYear();
    } else if (action === 'year-page') {
        _cpYearBase += parseInt(target.dataset.cpDelta, 10) * _CP_YEAR_PAGE;
        _cpRenderYear();
    } else if (year !== undefined) {
        _cpDisplayYear = parseInt(year, 10);
        _cpMode        = 'month';
        _cpRenderMonth();
    } else if (month !== undefined) {
        const m = parseInt(month, 10);
        _cpClose();
        loadCalendarMonth(m, _cpDisplayYear);
    } else if (action === 'go-today') {
        const tYear  = new Date().getFullYear();
        const tMonth = new Date().getMonth() + 1;
        _cpClose();
        loadCalendarMonth(tMonth, tYear);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const btn = document.getElementById('calPickerToggle');
    const dropdown = document.getElementById('calPickerDropdown');
    if (btn) {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            if (_cpOpen) _cpClose();
            else _cpOpen_picker();
        });
    }
    if (dropdown) {
        dropdown.addEventListener('click', _cpHandleDropdownClick);
    }
});

document.addEventListener('click', function(e) {
    if (!_cpOpen) return;
    const wrap = document.querySelector('.cal-month-picker-wrap');
    if (wrap && !wrap.contains(e.target)) _cpClose();
});

// Hook into existing Escape key handler by closing the picker there too
(function() {
    const _orig = document.onkeydown;
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && _cpOpen) _cpClose();
    });
})();