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
    if (!transactionsData[day]) {
        return; 
    }
    
    const modal = document.getElementById('dayModal');
    const title = document.getElementById('dayModalTitle');
    const content = document.getElementById('dayModalContent');
    const monthNames = ['', 'Janvāris', 'Februāris', 'Marts', 'Aprīlis', 'Maijs', 'Jūnijs', 
                        'Jūlijs', 'Augusts', 'Septembris', 'Oktobris', 'Novembris', 'Decembris'];
    title.textContent = `${day}. ${monthNames[month]}, ${year}`;
    
    const transactions = transactionsData[day];
    let html = '';
    
    transactions.forEach(transaction => {
        const typeClass = transaction.type === 'income' ? 'income' : 'expense';
        const typeLabel = transaction.type === 'income' ? 'Ienākums' : 'Izdevums';
        const sign = transaction.type === 'income' ? '+' : '-';
        const recurringBadge = transaction.is_recurring_display ? '<span class="recurring-badge">🔄 Ikmēneša</span>' : '';
        
        html += `
            <div class="transaction-item ${typeClass}">
                <div class="transaction-info">
                    <div class="transaction-description">${transaction.description} ${recurringBadge}</div>
                    <div class="transaction-type">${typeLabel}</div>
                </div>
                <div class="transaction-amount">${sign}€${parseFloat(transaction.amount).toFixed(2)}</div>
            </div>
        `;
    });
    
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

// close modal if click outside
window.addEventListener('click', function(e) {
    const incomeModal = document.getElementById('incomeModal');
    const expenseModal = document.getElementById('expenseModal');
    const dayModal = document.getElementById('dayModal');
    const warningModal = document.getElementById('warningModal');
    
    if (e.target === incomeModal) {
        closeIncomeModal();
    }
    if (e.target === expenseModal) {
        closeExpenseModal();
    }
    if (e.target === dayModal) {
        closeDayModal();
    }
    if (e.target === warningModal) {
        closeWarningModal();
    }
});

// close modal with esc
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeIncomeModal();
        closeExpenseModal();
        closeDayModal();
        closeWarningModal();
    }
});

// Validation for expense form
document.addEventListener('DOMContentLoaded', function() {
    const expenseForm = document.querySelector('#expenseModal form');
    
    if (expenseForm) {
        expenseForm.addEventListener('submit', function(e) {
            const expenseAmount = parseFloat(document.getElementById('expense_amount').value) || 0;
            const newTotalExpense = monthlyExpense + expenseAmount;
            
            // Check if new expense would exceed income
            if (newTotalExpense > monthlyIncome) {
                e.preventDefault();
                showWarningModal(expenseAmount, newTotalExpense);
            }
        });
    }
});

// Show warning modal
function showWarningModal(expenseAmount, newTotalExpense) {
    const deficit = newTotalExpense - monthlyIncome;
    
    // Create modal if it doesn't exist
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
        // Update values in existing modal
        warningModal.querySelector('.warning-value.income').textContent = `+€${monthlyIncome.toFixed(2)}`;
        warningModal.querySelectorAll('.warning-value.expense')[0].textContent = `-€${monthlyExpense.toFixed(2)}`;
        warningModal.querySelectorAll('.warning-value.expense')[1].textContent = `-€${expenseAmount.toFixed(2)}`;
        warningModal.querySelectorAll('.warning-value.expense')[2].textContent = `-€${newTotalExpense.toFixed(2)}`;
        warningModal.querySelector('.warning-value.deficit-value').textContent = `-€${deficit.toFixed(2)}`;
    }
    
    warningModal.classList.add('modal-open');
    document.body.style.overflow = 'hidden';
}

// Close warning modal
function closeWarningModal() {
    const modal = document.getElementById('warningModal');
    if (modal) {
        modal.classList.remove('modal-open');
        document.body.style.overflow = 'auto';
    }
}

// Confirm expense submission
function confirmExpense() {
    closeWarningModal();
    const expenseForm = document.querySelector('#expenseModal form');
    if (expenseForm) {
        // Remove event listener temporarily
        const newForm = expenseForm.cloneNode(true);
        expenseForm.parentNode.replaceChild(newForm, expenseForm);
        newForm.submit();
    }
}

// animations for cards
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

// animations for days
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
// On mobile: scroll to today's day card automatically
document.addEventListener('DOMContentLoaded', function() {
    if (window.innerWidth <= 480) {
        const todayCard = document.querySelector('.calendar-day-today');
        if (todayCard) {
            // Small delay so the page has rendered fully
            setTimeout(() => {
                todayCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }, 150);
        }
    }
});