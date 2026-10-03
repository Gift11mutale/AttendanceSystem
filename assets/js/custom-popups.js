(() => {
    const typeConfig = {
        success: {
            title: 'Success',
            icon: 'bi-check-lg',
            button: 'Continue'
        },
        danger: {
            title: 'Something went wrong',
            icon: 'bi-exclamation-triangle-fill',
            button: 'Close'
        },
        warning: {
            title: 'Please check this',
            icon: 'bi-exclamation-lg',
            button: 'Close'
        },
        question: {
            title: 'Please Confirm',
            icon: 'bi-question-lg',
            button: 'OK'
        }
    };

    function showCustomPopup(message, type = 'warning', title = '', options = {}) {
        return new Promise((resolve) => {
            const config = typeConfig[type] || typeConfig.warning;
            const backdrop = document.createElement('div');
            backdrop.className = 'custom-popup-backdrop';
            backdrop.setAttribute('role', 'dialog');
            backdrop.setAttribute('aria-modal', 'true');
            backdrop.setAttribute('aria-label', title || config.title);

            const card = document.createElement('div');
            card.className = 'custom-popup-card';
            card.dataset.type = type;

            const icon = document.createElement('div');
            icon.className = 'custom-popup-icon';
            icon.innerHTML = `<i class="bi ${config.icon}"></i>`;

            const heading = document.createElement('h3');
            heading.textContent = title || config.title;

            const body = document.createElement('div');
            body.className = 'custom-popup-message';
            body.textContent = String(message || '');

            const actions = document.createElement('div');
            actions.className = 'custom-popup-actions';

            const button = document.createElement('button');
            button.type = 'button';
            button.className = `btn ${type === 'success' ? 'btn-success' : type === 'danger' ? 'btn-danger' : 'btn-warning'} px-4`;
            button.textContent = options.buttonText || config.button;

            function close() {
                backdrop.remove();
                document.removeEventListener('keydown', keyHandler);
                resolve();
            }

            button.addEventListener('click', close);

            actions.appendChild(button);

            if (options.secondaryButton) {
                const secBtn = document.createElement('a');
                secBtn.href = options.secondaryButton.href || '#';
                secBtn.className = options.secondaryButton.className || 'btn btn-outline-secondary px-3';
                secBtn.innerHTML = options.secondaryButton.html || options.secondaryButton.text || 'Back';
                if (options.secondaryButton.onClick) {
                    secBtn.addEventListener('click', (e) => {
                        options.secondaryButton.onClick(e);
                        close();
                    });
                }
                actions.appendChild(secBtn);
            }

            card.append(icon, heading, body, actions);
            backdrop.appendChild(card);

            backdrop.addEventListener('click', (event) => {
                if (event.target === backdrop) close();
            });

            const keyHandler = (event) => {
                if (event.key === 'Escape') close();
            };
            document.addEventListener('keydown', keyHandler);

            document.body.appendChild(backdrop);
            button.focus();
        });
    }

    function showCustomConfirm(message, options = {}) {
        return new Promise((resolve) => {
            const title = options.title || 'Please Confirm';
            const confirmText = options.confirmText || 'Confirm';
            const cancelText = options.cancelText || 'Cancel';
            const type = options.type || 'danger';
            const icon = options.icon || (type === 'danger' ? 'bi-exclamation-triangle-fill' : 'bi-question-lg');
            const confirmClass = options.confirmBtnClass || (type === 'danger' ? 'btn-danger' : 'btn-primary');

            const backdrop = document.createElement('div');
            backdrop.className = 'custom-popup-backdrop';
            backdrop.setAttribute('role', 'dialog');
            backdrop.setAttribute('aria-modal', 'true');
            backdrop.setAttribute('aria-label', title);

            const card = document.createElement('div');
            card.className = 'custom-popup-card';
            card.dataset.type = type;

            const iconEl = document.createElement('div');
            iconEl.className = 'custom-popup-icon';
            iconEl.innerHTML = `<i class="bi ${icon}"></i>`;

            const heading = document.createElement('h3');
            heading.textContent = title;

            const body = document.createElement('div');
            body.className = 'custom-popup-message';
            body.textContent = String(message || '');

            const actions = document.createElement('div');
            actions.className = 'custom-popup-actions';

            const cancelBtn = document.createElement('button');
            cancelBtn.type = 'button';
            cancelBtn.className = 'btn btn-outline-secondary px-4';
            cancelBtn.textContent = cancelText;

            const confirmBtn = document.createElement('button');
            confirmBtn.type = 'button';
            confirmBtn.className = `btn ${confirmClass} px-4`;
            confirmBtn.textContent = confirmText;

            function finish(result) {
                backdrop.remove();
                document.removeEventListener('keydown', keyHandler);
                resolve(result);
            }

            cancelBtn.addEventListener('click', () => finish(false));
            confirmBtn.addEventListener('click', () => finish(true));

            backdrop.addEventListener('click', (event) => {
                if (event.target === backdrop) finish(false);
            });

            const keyHandler = (event) => {
                if (event.key === 'Escape') finish(false);
            };
            document.addEventListener('keydown', keyHandler);

            actions.append(cancelBtn, confirmBtn);
            card.append(iconEl, heading, body, actions);
            backdrop.appendChild(card);
            document.body.appendChild(backdrop);
            confirmBtn.focus();
        });
    }

    window.showCustomPopup = showCustomPopup;
    window.showCustomConfirm = showCustomConfirm;

    // Replace native browser alert
    window.alert = (message) => showCustomPopup(message, 'warning');

    // Intercept form submissions that have confirm() or data-confirm
    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!form || form.tagName !== 'FORM') return;

        if (form.dataset.popupConfirmed === 'true') {
            form.dataset.popupConfirmed = 'false';
            return;
        }

        let confirmMsg = form.dataset.confirm;
        let confirmTitle = form.dataset.confirmTitle;
        let confirmBtn = form.dataset.confirmBtn;
        let confirmType = form.dataset.confirmType || 'danger';

        if (!confirmMsg) {
            const onsubmitAttr = form.getAttribute('onsubmit') || '';
            const match = onsubmitAttr.match(/confirm\s*\(\s*(['"])(.*?)\1\s*\)/i);
            if (match) {
                confirmMsg = match[2];
            }
        }

        if (confirmMsg) {
            event.preventDefault();
            event.stopImmediatePropagation();

            if (!confirmTitle) {
                if (/end.*session/i.test(confirmMsg)) {
                    confirmTitle = 'End Attendance Session?';
                    confirmBtn = 'End Session';
                    confirmType = 'danger';
                } else if (/delete|remove/i.test(confirmMsg)) {
                    confirmTitle = 'Are You Sure?';
                    confirmBtn = 'Delete';
                    confirmType = 'danger';
                } else {
                    confirmTitle = 'Please Confirm';
                    confirmBtn = 'Confirm';
                }
            }

            showCustomConfirm(confirmMsg, {
                title: confirmTitle,
                confirmText: confirmBtn,
                type: confirmType
            }).then((confirmed) => {
                if (confirmed) {
                    form.dataset.popupConfirmed = 'true';
                    if (typeof form.requestSubmit === 'function') {
                        form.requestSubmit();
                    } else {
                        HTMLFormElement.prototype.submit.call(form);
                    }
                }
            });
        }
    }, true);

    // Intercept clicks on buttons or links that have confirm()
    document.addEventListener('click', (event) => {
        const target = event.target.closest('a[data-confirm], button[data-confirm], a[onclick*="confirm"], button[onclick*="confirm"]');
        if (!target) return;

        if (target.dataset.popupConfirmed === 'true') {
            target.dataset.popupConfirmed = 'false';
            return;
        }

        let confirmMsg = target.dataset.confirm;
        let confirmTitle = target.dataset.confirmTitle || 'Please Confirm';
        let confirmBtn = target.dataset.confirmBtn || 'Confirm';
        let confirmType = target.dataset.confirmType || 'danger';

        if (!confirmMsg) {
            const onclickAttr = target.getAttribute('onclick') || '';
            const match = onclickAttr.match(/confirm\s*\(\s*(['"])(.*?)\1\s*\)/i);
            if (match) {
                confirmMsg = match[2];
            }
        }

        if (confirmMsg) {
            event.preventDefault();
            event.stopImmediatePropagation();

            showCustomConfirm(confirmMsg, {
                title: confirmTitle,
                confirmText: confirmBtn,
                type: confirmType
            }).then((confirmed) => {
                if (confirmed) {
                    target.dataset.popupConfirmed = 'true';
                    target.click();
                }
            });
        }
    }, true);

    // Auto-convert standard flash alerts to custom popups on page load
    document.addEventListener('DOMContentLoaded', () => {
        const hasScannerSuccessPopup = Boolean(document.getElementById('attendanceSuccessPopup'));
        document.querySelectorAll('.alert.alert-success, .alert.alert-danger, .alert.alert-warning').forEach((alert) => {
            if (hasScannerSuccessPopup && alert.classList.contains('alert-success')) return;
            if (alert.dataset.popupIgnore === 'true') return;
            const type = alert.classList.contains('alert-success')
                ? 'success'
                : alert.classList.contains('alert-danger') ? 'danger' : 'warning';
            const message = alert.textContent.replace(/\s+/g, ' ').trim();
            if (!message) return;
            alert.hidden = true;
            showCustomPopup(message, type);
        });
    });
})();
