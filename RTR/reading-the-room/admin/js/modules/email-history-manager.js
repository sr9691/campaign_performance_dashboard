/**
 * Email History Manager
 *
 * Handles read-only display of sent email details
 *
 * @package DirectReach
 * @subpackage ReadingTheRoom
 * @since 1.0.0
 */

export default class EmailHistoryManager {
    constructor(config) {
        this.config = config;
        this.nonce = config?.nonce || window.rtrDashboardConfig?.nonce || '';
        this.siteUrl = config?.siteUrl || window.rtrDashboardConfig?.siteUrl || '';
        this.modal = null;
        this.currentTracking = null;
        this._isListening = false;
        this.init();
    }

    /**
     * Initialize modal and listeners
     */
    init() {
        if (this._isListening) return;
        this._createModal();
        this._attachEventListeners();
        this._isListening = true;
    }

    /**
     * Build modal HTML once
     */
    _createModal() {
        if (document.getElementById('email-history-modal')) {
            this.modal = document.getElementById('email-history-modal');
            return;
        }

        const html = `
            <div class="email-history-modal" id="email-history-modal" role="dialog" aria-modal="true">
                <div class="email-modal-overlay" data-rtr="overlay"></div>
                <div class="email-modal-content">
                    <div class="email-modal-header">
                        <h3>
                            <i class="fas fa-envelope-open-text" aria-hidden="true"></i>
                            Email <span class="email-number-badge"></span> - <span class="prospect-name"></span>
                        </h3>
                        <button class="modal-close" aria-label="Close modal">&times;</button>
                    </div>

                    <div class="email-modal-body">
                        <div class="modal-body-section loading-state" aria-live="polite">
                            <div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i></div>
                            <p class="loading-text">Loading email details...</p>
                        </div>

                        <div class="modal-body-section history-display">
                            <div class="email-subject-section">
                                <label class="email-label">Subject:</label>
                                <div class="email-subject-readonly"></div>
                            </div>

                            <div class="email-body-section">
                                <label class="email-label">Email Body:</label>
                                <div class="email-body-readonly"></div>
                            </div>

                            <div class="email-details-section">
                                <h4>Email Details</h4>
                                <div class="details-grid">
                                    <div class="detail-item"><i class="fas fa-calendar-alt"></i><span>Sent:</span><span class="sent-date"></span></div>
                                    <div class="detail-item"><i class="fas fa-check-circle"></i><span>Status:</span><span class="email-status"></span></div>
                                    <div class="detail-item"><i class="fas fa-layer-group"></i><span>Template:</span><span class="template-name"></span></div>
                                    <div class="detail-item url-item"><i class="fas fa-link"></i><span>Content Link:</span><span class="url-included"></span></div>
                                </div>
                            </div>
                        </div>

                        <div class="modal-body-section error-state" role="alert">
                            <div class="error-icon"><i class="fas fa-exclamation-triangle"></i></div>
                            <h4>Failed to Load Email</h4>
                            <p class="error-message"></p>
                        </div>
                    </div>

                    <div class="email-modal-footer">
                        <button class="btn btn-secondary close-btn"><i class="fas fa-times"></i> Close</button>
                    </div>
                </div>
            </div>`;
        document.body.insertAdjacentHTML('beforeend', html);
        this.modal = document.getElementById('email-history-modal');
    }

    /**
     * Attach event listeners safely
     */
    _attachEventListeners() {
        const overlay = this.modal.querySelector('[data-rtr="overlay"]');
        const closeBtns = this.modal.querySelectorAll('.modal-close, .close-btn');

        [...closeBtns].forEach(btn => btn.addEventListener('click', () => this.hideModal()));
        overlay.addEventListener('click', () => this.hideModal());

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.modal.classList.contains('active')) {
                this.hideModal();
            }
        });

        document.addEventListener('rtr:openEmailHistory', (e) => {
            this.showEmailHistory(e.detail);
        });
    }

    /**
     * Show email history modal
     */
    async showEmailHistory({ visitorId, emailNumber, prospectName, room }) {
        if (!visitorId || !emailNumber) {
            console.error('EmailHistoryManager: Missing visitorId or emailNumber');
            return;
        }

        this.currentTracking = null;
        this._showSection('loading');
        this.modal.classList.add('active');

        // Use prospectName if provided, otherwise default
        const displayName = prospectName || `Prospect ${visitorId}`;
        this.modal.querySelector('.prospect-name').textContent = displayName;
        this.modal.querySelector('.email-number-badge').textContent = `#${emailNumber}`;

        try {
            // Construct the v2 API endpoint
            let baseUrl = this.siteUrl;
            if (baseUrl.includes('/wp-json')) {
                baseUrl = baseUrl.split('/wp-json')[0];
            }
            const apiEndpoint = `${baseUrl}/wp-json/directreach/v2/emails/tracking/prospect/${visitorId}/email/${emailNumber}`;
            
            const response = await fetch(apiEndpoint, {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.nonce
                }
            });
            
            if (!response.ok) {
                const errorText = await response.text();
                console.error('Email history API error:', errorText);
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const data = await response.json();
            
            if (!data.success) {
                throw new Error(data.message || 'Failed to load email history');
            }
            
            this.currentTracking = data.data;
            this.displayEmailHistory(data.data);
            this._showSection('history');
            
        } catch (error) {
            console.error('Failed to load email history:', error);
            this._showError(error.message || 'Failed to load email history.');
        }
    }

    /**
     * Populate modal with data
     */
    displayEmailHistory(data) {
        const subjectEl = this.modal.querySelector('.email-subject-readonly');
        const bodyEl = this.modal.querySelector('.email-body-readonly');
        const sentEl = this.modal.querySelector('.sent-date');
        const statusEl = this.modal.querySelector('.email-status');
        const templateEl = this.modal.querySelector('.template-name');
        const urlEl = this.modal.querySelector('.url-included');

        subjectEl.textContent = data.subject || 'No subject';
        bodyEl.innerHTML = data.body_html || '<p>No content</p>';
        sentEl.textContent = this._formatDate(data.copied_at || data.sent_at);
        statusEl.innerHTML = this._statusBadge(data.status, data.opened_at);

        if (data.template_used) {
            const badge = data.template_used.is_global
                ? '<span class="badge global-badge">Global</span>'
                : '<span class="badge campaign-badge">Campaign</span>';
            templateEl.innerHTML = `${this._escapeHtml(data.template_used.name)} ${badge}`;
        } else {
            templateEl.textContent = 'No template';
        }

        // Optional fields
        const urlItem = this.modal.querySelector('.url-item');
        if (data.url_included) {
            urlEl.innerHTML = `<a href="${this._escapeHtml(data.url_included)}" target="_blank" rel="noopener">${this._truncateUrl(data.url_included)}</a>`;
            urlItem.style.display = 'flex';
        } else {
            urlItem.style.display = 'none';
        }

    }

    /**
     * Create status badge HTML
     */
    _statusBadge(status, openedAt) {
        if (openedAt) return '<span class="status-badge status-opened"><i class="fas fa-envelope-open"></i> Opened</span>';

        const map = {
            copied: ['status-sent', 'fa-paper-plane', 'Sent'],
            sent: ['status-sent', 'fa-paper-plane', 'Sent'],
            opened: ['status-opened', 'fa-envelope-open', 'Opened'],
            clicked: ['status-clicked', 'fa-mouse-pointer', 'Clicked']
        };
        const [cls, icon, text] = map[status] || ['status-pending', 'fa-clock', 'Pending'];
        return `<span class="status-badge ${cls}"><i class="fas ${icon}"></i> ${text}</span>`;
    }

    /**
     * Hide modal
     */
    hideModal() {
        if (!this.modal) return;
        this.modal.classList.remove('active');
        this.currentTracking = null;
        this._showSection(null);
    }

    /**
     * Show one section (loading, history, error)
     */
    _showSection(section) {
        this.modal.querySelectorAll('.modal-body-section').forEach(s => s.classList.remove('active'));
        if (section) {
            const target = this.modal.querySelector(`.${section}-state`) ||
                           this.modal.querySelector(`.${section}-display`);
            if (target) target.classList.add('active');
        }
    }

    /**
     * Show error UI
     */
    _showError(message) {
        const msg = this.modal.querySelector('.error-message');
        msg.textContent = message || 'An unexpected error occurred.';
        this._showSection('error');
    }

    /**
     * Date formatting helper
     */
    _formatDate(str) {
        if (!str) return 'Unknown';
        try {
            const date = new Date(str);
            return date.toLocaleString('en-US', {
                year: 'numeric', month: 'short', day: 'numeric',
                hour: '2-digit', minute: '2-digit'
            });
        } catch {
            return 'Invalid date';
        }
    }

    /**
     * URL truncation helper
     */
    _truncateUrl(url) {
        return url.length <= 50 ? url : `${url.slice(0, 47)}...`;
    }

    /**
     * Escape HTML safely
     */
    _escapeHtml(text) {
        const d = document.createElement('div');
        d.textContent = text || '';
        return d.innerHTML;
    }
}