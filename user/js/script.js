// Password visible toggle
function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    // Find the <i> tag inside the button next to the input
    const icon = input.nextElementSibling.querySelector('i');
    
    if (input.type === 'password') {
        input.type = 'text';
        // Change from "eye" to "eye-slash"
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        // Change back to "eye"
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

// Smooth scroll for anchor links
    document.querySelectorAll('form').forEach(form => {
        form.noValidate = true;
    });

    function getValidationI18n() {
        const lang = window._i18n?.lang || document.documentElement.lang || 'lv';
        const dict = window._i18n?.T?.[lang] || window._i18n?.T?.lv || {};

        const translate = (key, fallback, params = {}) => {
            let text = dict[key] || fallback;
            Object.keys(params).forEach(param => {
                text = text.replace(new RegExp(`\\{${param}\\}`, 'g'), params[param]);
            });
            return text;
        };

        return { lang, translate };
    }

    function getFieldLabel(field) {
        if (!field) return '';

        if (field.labels && field.labels.length > 0) {
            return field.labels[0].textContent.replace(/\s+/g, ' ').trim();
        }

        const group = field.closest('.form-group');
        if (group) {
            const label = group.querySelector('label');
            if (label) return label.textContent.replace(/\s+/g, ' ').trim();
        }

        return field.name ? field.name.replace(/_/g, ' ') : '';
    }

    function getFieldErrorSlot(field) {
        if (!field) return null;

        const group = field.closest('.form-group');
        if (!group) return null;

        return group.querySelector('.form-error, .field-error, [data-validation-message]');
    }

    function getValidationMessage(field) {
        const { translate } = getValidationI18n();
        const label = getFieldLabel(field);
        const cleanLabel = label.replace(/\s*\*+\s*/g, '').replace(/\s*:+\s*$/, '').trim();
        const prefix = cleanLabel && field.type !== 'checkbox' && field.type !== 'radio' ? `${cleanLabel}: ` : '';
        const isAmountField = /amount|summa|budget_amount|transaction_amount/i.test(`${field.name || ''} ${label}`);

        if (field.validity.valueMissing) {
            if (field.type === 'checkbox' || field.type === 'radio') {
                return translate('validation.checkbox', 'Please tick this box.');
            }
            if (field.type === 'file') {
                return translate('validation.file', 'Please choose a file to upload.');
            }
            return `${prefix}${translate('validation.required', 'Please fill in this field.')}`;
        }

        if (field.validity.typeMismatch) {
            if (field.type === 'email') {
                return `${prefix}${translate('validation.email', 'Please enter a valid email address.')}`;
            }
            return `${prefix}${translate('validation.generic', 'Please check this field.')}`;
        }

        if (field.validity.tooShort) {
            return `${prefix}${translate('validation.minlength', 'Please enter at least {min} characters.', { min: field.minLength })}`;
        }

        if (field.validity.tooLong) {
            return `${prefix}${translate('validation.maxlength', 'Please enter no more than {max} characters.', { max: field.maxLength })}`;
        }

        if (field.validity.rangeUnderflow) {
            const min = field.min || field.getAttribute('min') || '';
            if (isAmountField) {
                return translate('validation.amount.positive', 'Please enter a positive amount.');
            }
            return `${prefix}${translate('validation.min', 'Please enter a larger value.', { min })}`;
        }

        if (field.validity.rangeOverflow) {
            const max = field.max || field.getAttribute('max') || '';
            if (isAmountField) {
                return translate('validation.max', 'Please enter a smaller value.');
            }
            return `${prefix}${translate('validation.max', 'Please enter a smaller value.', { max })}`;
        }

        if (field.validity.stepMismatch) {
            if (isAmountField || field.type === 'number' || field.type === 'range') {
                return translate('validation.step.amount', 'Please enter a valid amount.');
            }
            return `${prefix}${translate('validation.step', 'Please enter a valid value.')}`;
        }

        if (field.validity.patternMismatch) {
            return `${prefix}${translate('validation.pattern', 'Please enter a valid value.')}`;
        }

        if (field.validity.badInput) {
            if (field.type === 'number' || field.type === 'range') {
                return translate('validation.number', 'Please enter a valid number.');
            }
            return `${prefix}${translate('validation.number', 'Please enter a valid number.')}`;
        }

        return `${prefix}${translate('validation.generic', 'Please check this field.')}`;
    }

    function setFieldValidationFeedback(field, message) {
        const slot = getFieldErrorSlot(field);
        if (slot) {
            slot.textContent = message;
            slot.hidden = !message;
        } else if (message) {
            showNotification(message, 'error');
        }

        if (field) {
            field.setAttribute('aria-invalid', message ? 'true' : 'false');
        }
    }

    function clearFieldValidationFeedback(field) {
        const slot = getFieldErrorSlot(field);
        if (slot) {
            slot.textContent = '';
            slot.hidden = true;
        }

        if (field) {
            field.removeAttribute('aria-invalid');
        }
    }

    function findFirstInvalidField(form) {
        const controls = Array.from(form.elements || []);
        return controls.find(control => {
            if (!(control instanceof HTMLElement)) return false;
            if (!('willValidate' in control) || !control.willValidate) return false;
            return control.validity && !control.validity.valid;
        }) || null;
    }

    document.addEventListener('invalid', function (e) {
        const field = e.target;
        if (!(field instanceof HTMLElement)) return;
        e.preventDefault();
        setFieldValidationFeedback(field, getValidationMessage(field));
    }, true);

    document.addEventListener('input', function (e) {
        const field = e.target;
        if (!(field instanceof HTMLElement)) return;
        if (!('willValidate' in field) || !field.willValidate) return;

        if (field.validity && field.validity.valid) {
            clearFieldValidationFeedback(field);
        }
    }, true);

    document.addEventListener('change', function (e) {
        const field = e.target;
        if (!(field instanceof HTMLElement)) return;
        if (!('willValidate' in field) || !field.willValidate) return;

        if (field.validity && field.validity.valid) {
            clearFieldValidationFeedback(field);
        }
    }, true);

    document.addEventListener('submit', function (e) {
        const form = e.target;
        if (!(form instanceof HTMLFormElement)) return;
        if (e.defaultPrevented) return;

        const invalidField = findFirstInvalidField(form);
        if (!invalidField) return;

        e.preventDefault();
        e.stopImmediatePropagation();

        const message = getValidationMessage(invalidField);
        setFieldValidationFeedback(invalidField, message);
        if (typeof invalidField.focus === 'function') {
            invalidField.focus({ preventScroll: true });
        }
    }, true);
document.addEventListener('DOMContentLoaded', function() {
    const links = document.querySelectorAll('a[href^="#"]');
    
    links.forEach(link => {
        link.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            if (href !== '#' && href !== '') {
                e.preventDefault();
                const target = document.querySelector(href);
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            }
        });
    });
});

// ─── Logout confirmation modal ────────────────────────────────────────────────
function showLogoutConfirm() {
    let existing = document.getElementById('logoutConfirmModal');
    if (existing) existing.remove();

    const modal = document.createElement('div');
    modal.id = 'logoutConfirmModal';
    modal.className = 'modal modal-open';
    modal.innerHTML = `
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Iziet no konta</h2>
                <button type="button" class="modal-close" aria-label="Aizvērt">✕</button>
            </div>
            <div class="modal-body">
                <p>Vai tiešām vēlies iziet no sava konta?</p>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" id="logoutCancelBtn">Atcelt</button>
                <a href="logout.php" class="btn btn-danger">
                    <i class="fas fa-sign-out-alt"></i> Iziet
                </a>
            </div>
        </div>`;

    document.body.appendChild(modal);
    document.body.style.overflow = 'hidden';

    const close = () => {
        modal.classList.remove('modal-open');
        document.body.style.overflow = 'auto';
        setTimeout(() => modal.remove(), 250);
    };

    modal.querySelector('.modal-close').addEventListener('click', close);
    modal.querySelector('#logoutCancelBtn').addEventListener('click', close);
    modal.addEventListener('click', e => { if (e.target === modal) close(); });
}

// Form validation for login
const loginForm = document.getElementById('loginForm');
if (loginForm) {
    const loginEmail = document.getElementById('email');
    const loginPassword = document.getElementById('password');
    const loginEmailError = document.getElementById('emailError');

    const getLoginCopy = () => {
        const lang = window._i18n?.lang || document.documentElement.lang || 'lv';
        const dict = window._i18n?.T?.[lang] || window._i18n?.T?.lv || {};

        return {
            empty: dict['login.err.empty'] || (lang === 'en' ? 'Please fill in all fields!' : 'Lūdzu aizpildiet visus laukus!'),
            invalidEmail: dict['login.validation.email.invalid'] || (lang === 'en' ? 'Please enter a valid email address!' : 'Lūdzu ievadiet derīgu e-pasta adresi!'),
            loading: dict['login.loading'] || (lang === 'en' ? 'Signing in...' : 'Notiek pieslēgšanās...')
        };
    };

    const setLoginError = (message) => {
        if (!loginEmailError || !loginEmail) return;
        loginEmailError.textContent = message;
        loginEmailError.hidden = !message;
        loginEmail.setAttribute('aria-invalid', message ? 'true' : 'false');
    };

    const validateLoginEmail = () => {
        const copy = getLoginCopy();
        const value = loginEmail.value.trim();

        if (!value) return copy.empty;
        if (loginEmail.validity.typeMismatch) return copy.invalidEmail;

        return '';
    };

    if (loginEmail) {
        loginEmail.addEventListener('input', function () {
            if (loginEmail.validity.valid && loginEmail.value.trim()) {
                setLoginError('');
            }
        });

        loginEmail.addEventListener('blur', function () {
            setLoginError(validateLoginEmail());
        });
    }

    loginForm.addEventListener('submit', function(e) {
        const copy = getLoginCopy();
        const emailError = validateLoginEmail();
        const passwordValue = loginPassword ? loginPassword.value.trim() : '';

        if (!loginEmail.value.trim() || !passwordValue) {
            e.preventDefault();
            setLoginError('');
            showNotification(copy.empty, 'error');
        } else if (emailError) {
            e.preventDefault();
            setLoginError(emailError);
            showNotification(emailError, 'error');
        } else {
            setLoginError('');
            // If fields exist, show loading status but ALLOW submission to PHP
            showNotification(copy.loading, 'info');
        }
    });
}

// Form validation for registration
const registerForm = document.getElementById('registerForm');
if (registerForm) {
    registerForm.addEventListener('submit', function(e) {
        // We only prevent default if validation fails
        const username = document.getElementById('username').value;
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirmPassword').value;
        
        let hasError = false;

        if (password !== confirmPassword) {
            showNotification('Paroles nesakrīt!', 'error');
            hasError = true;
        } else if (username.length < 4) {
            showNotification('Lietotājvārdam jābūt vismaz 4 simboliem!', 'error');
            hasError = true;
        } else if (password.length < 8) {
            showNotification('Parolei jābūt vismaz 8 simboliem!', 'error');
            hasError = true;
        }

        if (hasError) {
            e.preventDefault();
        } else {
            // Allow submission to server
            showNotification('Notiek reģistrācija...', 'info');
        }
    });
}

// Show notification (visual feedback)
function showNotification(message, type = 'info') {
    // Remove existing notification
    const existing = document.querySelector('.notification');
    if (existing) {
        existing.remove();
    }
    
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.textContent = message;
    
    // Add styles dynamically
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 16px 24px;
        background: ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#6366f1'};
        color: white;
        border-radius: 8px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        z-index: 20000;
        animation: slideIn 0.3s ease-out;
        font-weight: 600;
    `;
    
    document.body.appendChild(notification);
    
    // Remove after 3 seconds
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease-out';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// Add animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(400px);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOut {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(400px);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);

// Animate elements on scroll
function animateOnScroll() {
    const elements = document.querySelectorAll('.feature-card, .stat');
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, { threshold: 0.1 });
    
    elements.forEach(el => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(20px)';
        el.style.transition = 'all 0.6s ease-out';
        observer.observe(el);
    });
}

// Run animation on page load
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', animateOnScroll);
} else {
    animateOnScroll();
}

// PWA: Capture the install prompt as early as possible.
// Stored globally so installPrompt.js can use it even if the event fired
// before that script had a chance to register its own listener.
window._pwaPrompt = null;
window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault(); // Prevent the browser's default mini-infobar
    window._pwaPrompt = e;
});

// PWA: Register service worker
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
        navigator.serviceWorker.register('../../sw.js')
            .catch(function (err) { console.error('SW registration failed:', err); });
    });
}