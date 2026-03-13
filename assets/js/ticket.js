/* ============================================================
   Ticket JS - Raise Ticket page logic
   ============================================================ */

document.addEventListener('DOMContentLoaded', () => {

    // ── Category card selection ──────────────────────────────
    const categoryCards = document.querySelectorAll('.category-card');
    const categoryInput = document.getElementById('category_id');
    const customSection = document.getElementById('custom-description-section');

    categoryCards.forEach(card => {
        card.addEventListener('click', () => {
            categoryCards.forEach(c => c.classList.remove('selected'));
            card.classList.add('selected');
            const catId = card.dataset.id;
            const catName = card.dataset.name;

            if (categoryInput) categoryInput.value = catId;

            // Show custom textarea only for "Other" category
            if (customSection) {
                if (catName === 'Other' || catId === '0') {
                    customSection.classList.remove('d-none');
                    customSection.querySelector('textarea').required = true;
                } else {
                    customSection.classList.add('d-none');
                    customSection.querySelector('textarea').required = false;
                }
            }
        });
    });

    // ── Staff card selection ─────────────────────────────────
    const staffCards = document.querySelectorAll('.staff-card');
    const staffInput = document.getElementById('assigned_to');

    staffCards.forEach(card => {
        card.addEventListener('click', () => {
            staffCards.forEach(c => c.classList.remove('selected'));
            card.classList.add('selected');
            if (staffInput) staffInput.value = card.dataset.id;
        });
    });

    // ── Form validation before submit ────────────────────────
    const ticketForm = document.getElementById('raise-ticket-form');
    if (ticketForm) {
        ticketForm.addEventListener('submit', e => {
            if (!categoryInput || !categoryInput.value) {
                e.preventDefault();
                showToast('Please select a problem category.', 'warning');
                return;
            }
            if (!staffInput || !staffInput.value) {
                e.preventDefault();
                showToast('Please select a staff member to assign the ticket to.', 'warning');
                return;
            }
        });
    }

    // ── Priority selection highlight ─────────────────────────
    document.querySelectorAll('.priority-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.priority-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
        });
    });
});

function showToast(message, type = 'info') {
    // Create a Bootstrap toast dynamically
    const toastId = 'toast_' + Date.now();
    const colors = { success: 'bg-success', error: 'bg-danger', warning: 'bg-warning', info: 'bg-info' };
    const html = `
        <div id="${toastId}" class="toast align-items-center text-white ${colors[type] || 'bg-secondary'} border-0"
             role="alert" aria-live="assertive" style="min-width:280px;">
          <div class="d-flex">
            <div class="toast-body">${message}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
          </div>
        </div>`;

    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        container.style.zIndex = '9999';
        document.body.appendChild(container);
    }
    container.insertAdjacentHTML('beforeend', html);

    const toastEl = document.getElementById(toastId);
    const toast = new bootstrap.Toast(toastEl, { delay: 4000 });
    toast.show();
    toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
}
