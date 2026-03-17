// ─── Modal helpers ───────────────────────────────────────────────────────────

function openAddModal() {
    document.getElementById('addModal').classList.add('modal-open');
    document.body.style.overflow = 'hidden';
    // Reset recurring section state when opening fresh
    resetRecurringSection();
}

function closeAddModal() {
    document.getElementById('addModal').classList.remove('modal-open');
    document.body.style.overflow = 'auto';
}

function openEditModal(budget) {
    document.getElementById('edit_budget_id').value        = budget.id;
    document.getElementById('edit_budget_name').value      = budget.budget_name;
    document.getElementById('edit_budget_amount').value    = budget.budget_amount;
    document.getElementById('edit_warning_threshold').value = budget.warning_threshold;

    // Populate recurring days if this budget has them
    const editDaysContainer = document.getElementById('edit_recurring_days_container');
    const editToggle        = document.getElementById('edit_recurring_toggle');
    const editPreview       = document.getElementById('edit_recurring_preview');
    const editHidden        = document.getElementById('edit_recurring_days');

    if (budget.recurring_days && budget.recurring_days !== '') {
        const selectedDays = budget.recurring_days.split(',').map(Number);
        editDaysContainer.querySelectorAll('.day-pill').forEach(pill => {
            pill.classList.toggle('selected', selectedDays.includes(parseInt(pill.dataset.day)));
        });
        editHidden.value = budget.recurring_days;
        editDaysContainer.style.display = 'block';
        editToggle.checked = true;
        if (editPreview) updateRecurringPreview(selectedDays, editPreview, null, null);
    } else {
        editDaysContainer.style.display = 'none';
        editToggle.checked = false;
        editHidden.value = '';
        editDaysContainer.querySelectorAll('.day-pill').forEach(p => p.classList.remove('selected'));
        if (editPreview) { editPreview.textContent = ''; editPreview.style.display = 'none'; }
    }

    document.getElementById('editModal').classList.add('modal-open');
    document.body.style.overflow = 'hidden';
}

function closeEditModal() {
    document.getElementById('editModal').classList.remove('modal-open');
    document.body.style.overflow = 'auto';
}

// Close modals when clicking outside
window.onclick = function (event) {
    if (event.target.classList.contains('modal')) {
        event.target.classList.remove('modal-open');
        document.body.style.overflow = 'auto';
    }
};

// Close with ESC key
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        closeAddModal();
        closeEditModal();
    }
});


// ─── Recurring weekday feature ────────────────────────────────────────────────

/**
 * Day index mapping (JS Date.getDay(): 0 = Sunday … 6 = Saturday)
 */
const DAY_NAMES_SHORT = ['Sv', 'P', 'O', 'T', 'C', 'Pk', 'S'];

/**
 * Given an array of day indices (0–6), calculates the start and end dates
 * for the current (or next) weekly period that contains all selected days.
 *
 * Strategy: anchor to the Monday of the current week. Map each selected day
 * into that week. If all mapped dates are in the past, shift the entire set
 * forward by 7 days so we always show an upcoming period.
 *
 * Returns { start: 'YYYY-MM-DD', end: 'YYYY-MM-DD' } or null.
 */
function calcRecurringDates(selectedDays) {
    if (!selectedDays || selectedDays.length === 0) return null;

    const today = new Date();
    today.setHours(0, 0, 0, 0);

    // Monday of current week
    const monday = new Date(today);
    const dow = today.getDay();                          // 0 Sun … 6 Sat
    const diffToMonday = (dow === 0) ? -6 : 1 - dow;
    monday.setDate(monday.getDate() + diffToMonday);

    // Build Date for each selected day within this Mon-anchored week
    // Convert JS day index to Mon-anchored offset:
    //   Sun (0) → 6,  Mon (1) → 0,  Tue (2) → 1, …  Sat (6) → 5
    let candidates = selectedDays.map(d => {
        const offset = (d === 0) ? 6 : d - 1;
        const date   = new Date(monday);
        date.setDate(monday.getDate() + offset);
        return date;
    });

    // If every candidate is already past, roll the whole set forward a week
    if (candidates.every(d => d < today)) {
        candidates = candidates.map(d => {
            const shifted = new Date(d);
            shifted.setDate(shifted.getDate() + 7);
            return shifted;
        });
    }

    candidates.sort((a, b) => a - b);

    return {
        start: toYMD(candidates[0]),
        end:   toYMD(candidates[candidates.length - 1])
    };
}

/** Formats a Date object as 'YYYY-MM-DD'. */
function toYMD(date) {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
}

/** Returns e.g. "Mon, Fri, Sat" for [1, 5, 6]. */
function friendlyDayList(days) {
    return days.slice().sort((a, b) => a - b).map(d => DAY_NAMES_SHORT[d]).join(', ');
}

/**
 * Wires click-toggle behaviour onto every .day-pill inside `container`.
 * Calls onChangeCallback(selectedDayIndices) on every change.
 */
function initDayPicker(container, onChangeCallback) {
    container.querySelectorAll('.day-pill').forEach(pill => {
        pill.addEventListener('click', function () {
            this.classList.toggle('selected');
            onChangeCallback(getSelectedDays(container));
        });
    });
}

/** Returns the array of currently selected day indices within `container`. */
function getSelectedDays(container) {
    return Array.from(container.querySelectorAll('.day-pill.selected'))
                .map(p => parseInt(p.dataset.day));
}

/**
 * Updates the preview banner text and optionally auto-fills + locks
 * the start/end date inputs.
 *
 * @param {number[]} selectedDays   - Array of selected JS day indices (0–6)
 * @param {Element}  previewEl      - The element that shows the preview text
 * @param {Element|null} startInput - <input type="date"> for start (or null)
 * @param {Element|null} endInput   - <input type="date"> for end   (or null)
 */
function updateRecurringPreview(selectedDays, previewEl, startInput, endInput) {
    if (selectedDays.length === 0) {
        previewEl.innerHTML = '';
        previewEl.style.display = 'none';
        if (startInput) { startInput.readOnly = false; startInput.value = ''; }
        if (endInput)   { endInput.readOnly   = false; endInput.value   = ''; }
        return;
    }

    const dates = calcRecurringDates(selectedDays);
    if (!dates) return;

    const label = friendlyDayList(selectedDays);
    previewEl.innerHTML =
        `<i class="fa-solid fa-rotate" style="color:var(--primary)"></i>` +
        `<strong>${label}</strong> — ` +
        `<strong>${dates.start}</strong> → <strong>${dates.end}</strong>`;
    previewEl.style.display = 'flex';

    // Auto-fill and lock the date pickers
    if (startInput) { startInput.value = dates.start; startInput.readOnly = true; }
    if (endInput)   { endInput.value   = dates.end;   endInput.readOnly   = true; }
}

/** Resets the Add modal's recurring section to its pristine off-state. */
function resetRecurringSection() {
    const toggle     = document.getElementById('add_recurring_toggle');
    const container  = document.getElementById('add_recurring_days_container');
    const preview    = document.getElementById('add_recurring_preview');
    const hidden     = document.getElementById('add_recurring_days');
    const startInput = document.getElementById('add_start_date');
    const endInput   = document.getElementById('add_end_date');
    const datesGroup = document.getElementById('add_dates_group');

    if (toggle)    toggle.checked = false;
    if (container) {
        container.style.display = 'none';
        container.querySelectorAll('.day-pill').forEach(p => p.classList.remove('selected'));
    }
    if (preview)    { preview.innerHTML = ''; preview.style.display = 'none'; }
    if (hidden)     hidden.value = '';
    // Always restore the date fields when resetting
    if (datesGroup) datesGroup.style.display = 'block';
    if (startInput) { startInput.readOnly = false; startInput.required = true; startInput.value = ''; }
    if (endInput)   { endInput.readOnly   = false; endInput.required   = true; endInput.value   = ''; }
}


// ─── DOM-ready wiring ─────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', function () {

    // ── ADD MODAL ──────────────────────────────────────────────────────────────

    const addToggle    = document.getElementById('add_recurring_toggle');
    const addContainer = document.getElementById('add_recurring_days_container');
    const addPreview   = document.getElementById('add_recurring_preview');
    const addHidden    = document.getElementById('add_recurring_days');
    const addStart     = document.getElementById('add_start_date');
    const addEnd       = document.getElementById('add_end_date');

    if (addToggle && addContainer) {
        const addDatesGroup = document.getElementById('add_dates_group');

        addToggle.addEventListener('change', function () {
            if (this.checked) {
                addContainer.style.display = 'block';
                // Hide the manual date fields — PHP calculates dates from recurring_days
                if (addDatesGroup) addDatesGroup.style.display = 'none';
                if (addStart) { addStart.required = false; addStart.value = ''; }
                if (addEnd)   { addEnd.required   = false; addEnd.value   = ''; }
            } else {
                addContainer.style.display = 'none';
                addContainer.querySelectorAll('.day-pill').forEach(p => p.classList.remove('selected'));
                if (addPreview) { addPreview.innerHTML = ''; addPreview.style.display = 'none'; }
                if (addHidden)  addHidden.value = '';
                // Restore manual date fields
                if (addDatesGroup) addDatesGroup.style.display = 'block';
                if (addStart) { addStart.required = true;  addStart.readOnly = false; addStart.value = ''; }
                if (addEnd)   { addEnd.required   = true;  addEnd.readOnly   = false; addEnd.value   = ''; }
            }
        });

        initDayPicker(addContainer, function (selected) {
            if (addHidden) addHidden.value = selected.join(',');
            updateRecurringPreview(selected, addPreview, addStart, addEnd);
        });
    }

    // ── EDIT MODAL ─────────────────────────────────────────────────────────────

    const editToggle    = document.getElementById('edit_recurring_toggle');
    const editContainer = document.getElementById('edit_recurring_days_container');
    const editPreview   = document.getElementById('edit_recurring_preview');
    const editHidden    = document.getElementById('edit_recurring_days');

    if (editToggle && editContainer) {
        editToggle.addEventListener('change', function () {
            if (this.checked) {
                editContainer.style.display = 'block';
            } else {
                editContainer.style.display = 'none';
                editContainer.querySelectorAll('.day-pill').forEach(p => p.classList.remove('selected'));
                if (editPreview) { editPreview.innerHTML = ''; editPreview.style.display = 'none'; }
                if (editHidden)  editHidden.value = '';
            }
        });

        initDayPicker(editContainer, function (selected) {
            if (editHidden) editHidden.value = selected.join(',');
            if (editPreview) updateRecurringPreview(selected, editPreview, null, null);
        });
    }
});