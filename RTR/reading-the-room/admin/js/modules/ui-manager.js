/**
 * UI Manager
 *
 * Shared UI helpers for modals, notifications, and loaders.
 *
 * @package DirectReach
 * @subpackage ReadingTheRoom
 * @since 1.1.0
 */

export default class UIManager {
    constructor() {
        this.notificationTimeout = null;
        this._ensureNotificationContainer();
    }

    /**
     * Show notification toast
     * @param {string} message - Text to show
     * @param {'success'|'error'|'info'} type - Notification style
     */
    notify(message, type = 'info') {
        if (!message) return;
        const container = document.querySelector('.notification-container');
        const toast = document.createElement('div');
        toast.className = `notification notification-${type}`;
        toast.textContent = message;
        container.appendChild(toast);

        requestAnimationFrame(() => toast.classList.add('active'));

        clearTimeout(this.notificationTimeout);
        this.notificationTimeout = setTimeout(() => {
            toast.classList.remove('active');
            setTimeout(() => toast.remove(), 300);
        }, 3500);
    }

    /**
     * Show fullscreen loader overlay
     */
    showLoader(text = 'Loading...') {
        let overlay = document.querySelector('.ui-loader-overlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.className = 'ui-loader-overlay';
            overlay.innerHTML = `
                <div class="ui-loader">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p>${text}</p>
                </div>`;
            document.body.appendChild(overlay);
        }
        overlay.querySelector('p').textContent = text;
        overlay.classList.add('active');
    }

    /**
     * Hide loader overlay
     */
    hideLoader() {
        const overlay = document.querySelector('.ui-loader-overlay');
        if (overlay) overlay.classList.remove('active');
    }

    /**
     * Confirm action modal (generic)
     */
    async confirmAction(title, message, confirmLabel = 'Confirm', cancelLabel = 'Cancel') {
        return new Promise((resolve) => {
            const modal = document.createElement('div');
            modal.className = 'ui-confirm-modal';
            modal.innerHTML = `
                <div class="ui-overlay"></div>
                <div class="ui-modal">
                    <h3>${this._escapeHtml(title)}</h3>
                    <p>${this._escapeHtml(message)}</p>
                    <div class="ui-actions">
                        <button class="btn btn-secondary cancel-btn">${cancelLabel}</button>
                        <button class="btn btn-primary confirm-btn">${confirmLabel}</button>
                    </div>
                </div>`;
            document.body.appendChild(modal);

            const close = (result) => {
                modal.classList.remove('active');
                setTimeout(() => modal.remove(), 250);
                resolve(result);
            };

            modal.querySelector('.cancel-btn').onclick = () => close(false);
            modal.querySelector('.confirm-btn').onclick = () => close(true);
            modal.querySelector('.ui-overlay').onclick = () => close(false);

            requestAnimationFrame(() => modal.classList.add('active'));
        });
    }

    /**
     * Ensure notification container exists
     */
    _ensureNotificationContainer() {
        if (!document.querySelector('.notification-container')) {
            const div = document.createElement('div');
            div.className = 'notification-container';
            document.body.appendChild(div);
        }
    }

    /**
     * Safe escape
     */
    _escapeHtml(text) {
        const d = document.createElement('div');
        d.textContent = text || '';
        return d.innerHTML;
    }
}
