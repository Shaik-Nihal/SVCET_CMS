/* ============================================================
   Main JS - Global helpers
   ============================================================ */

// CSRF token from meta tag (for AJAX POSTs)
function getCSRFToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
}

// POST helper with CSRF token
async function postJSON(url, data = {}) {
    data.csrf_token = getCSRFToken();
    const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify(data),
    });
    return res.json();
}

// Tooltip init
document.addEventListener('DOMContentLoaded', () => {
    const tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltips.forEach(el => new bootstrap.Tooltip(el));

    // Auto-dismiss alerts after 5 seconds
    document.querySelectorAll('.alert.alert-dismissible').forEach(alert => {
        setTimeout(() => {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            if (bsAlert) bsAlert.close();
        }, 5000);
    });

    // Mobile sidebar toggle
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', () => {
            sidebar.classList.toggle('show');
        });
    }
});

// Password strength meter
function checkPasswordStrength(password) {
    let score = 0;
    if (password.length >= 8)                score++;
    if (/[A-Z]/.test(password))             score++;
    if (/\d/.test(password))                score++;
    if (/[\W_]/.test(password))             score++;
    return score; // 0-4
}

function updateStrengthMeter(inputId, barId, textId) {
    const input = document.getElementById(inputId);
    const bar   = document.getElementById(barId);
    const text  = document.getElementById(textId);
    if (!input || !bar) return;

    input.addEventListener('input', () => {
        const score = checkPasswordStrength(input.value);
        bar.className = 'pwd-strength-bar';
        if (input.value.length === 0) {
            bar.style.width = '0';
            if (text) text.textContent = '';
            return;
        }
        if (score <= 1) {
            bar.classList.add('weak'); bar.style.width = '33%';
            if (text) { text.textContent = 'Weak'; text.className = 'pwd-strength-text text-danger'; }
        } else if (score <= 2) {
            bar.classList.add('fair'); bar.style.width = '66%';
            if (text) { text.textContent = 'Fair'; text.className = 'pwd-strength-text text-warning'; }
        } else {
            bar.classList.add('strong'); bar.style.width = '100%';
            if (text) { text.textContent = 'Strong'; text.className = 'pwd-strength-text text-success'; }
        }
    });
}
