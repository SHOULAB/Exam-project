
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