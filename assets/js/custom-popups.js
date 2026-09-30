(() => {
    const typeConfig = {
        success: {
            title: 'Success',
            icon: 'bi-check-lg',
            button: 'Continue'
        },
        danger: {
            title: 'Something went wrong',
            icon: 'bi-exclamation-lg',
            button: 'Close'
        },
        warning: {
            title: 'Please check this',
            icon: 'bi-exclamation-lg',
            button: 'Close'
        }
    };

    function closePopup(backdrop) {
        if (backdrop) backdrop.remove();
    }

    function showCustomPopup(message, type = 'warning', title = '') {
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

        const button = document.createElement('button');
        button.type = 'button';
        button.className = `btn ${type === 'success' ? 'btn-success' : type === 'danger' ? 'btn-danger' : 'btn-warning'} px-4`;
        button.textContent = config.button;
        button.addEventListener('click', () => closePopup(backdrop));

        card.append(icon, heading, body, button);
        backdrop.appendChild(card);
        backdrop.addEventListener('click', (event) => {
            if (event.target === backdrop) closePopup(backdrop);
        });
        document.body.appendChild(backdrop);
        button.focus();
        return backdrop;
    }

    window.showCustomPopup = showCustomPopup;

    // Replace native browser alerts throughout the application.
    window.alert = (message) => showCustomPopup(message, 'warning');

    document.addEventListener('DOMContentLoaded', () => {
        // The scanner has a more specific success popup; do not duplicate it.
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
