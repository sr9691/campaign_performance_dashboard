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
    constructor(api, config) {
        this.api = api;
        this.config = config;
        this.modal = null;
        this.currentTracking = null;
        
        this.init();
    }
    
    /**
     * Initialize modal
     */
    init() {
        this.createModal();
        this.attachEventListeners();
    }
    
    /**
     * Create modal HTML structure
     */
    createModal() {
        const modalHTML = `
            <div class="email-history-modal" id="email-history-modal">
                <div class="email-modal-overlay"></div>
                <div class="email-modal-content">
                    <!-- Header -->
                    <div class="email-modal-header">
                        <h3>
                            <i class="fas fa-envelope-open-text"></i>
                            Email <span class="email-number-badge"></span> - <span class="prospect-name"></span>
                        </h3>
                        <button class="modal-close" aria-label="Close modal">&times;</button>
                    </div>
                    
                    <!-- Body -->
                    <div class="email-modal-body">
                        <!-- Loading State -->
                        <div class="modal-body-section loading-state">
                            <div class="loading-spinner">
                                <i class="fas fa-spinner"></i>
                            </div>
                            <p class="loading-text">Loading email details...</p>
                        </div>
                        
                        <!-- History Display -->
                        <div class="modal-body-section history-display">
                            <!-- Subject (Read-Only) -->
                            <div class="email-subject-section">
                                <label class="email-label">Subject:</label>
                                <div class="email-subject-readonly"></div>
                            </div>
                            
                            <!-- Body (Read-Only) -->
                            <div class="email-body-section">
                                <label class="email-label">Email Body:</label>
                                <div class="email-body-readonly"></div>
                            </div>
                            
                            <!-- Email Details -->
                            <div class="email-details-section">
                                <h4>Email Details</h4>
                                <div class="details-grid">
                                    <div class="detail-item">
                                        <i class="fas fa-calendar-alt"></i>
                                        <span class="detail-label">Sent:</span>
                                        <span class="detail-value sent-date"></span>
                                    </div>
                                    <div class="detail-item">
                                        <i class="fas fa-check-circle"></i>
                                        <span class="detail-label">Status:</span>
                                        <span class="detail-value email-status"></span>
                                    </div>
                                    <div class="detail-item">
                                        <i class="fas fa-layer-group"></i>
                                        <span class="detail-label">Template:</span>
                                        <span class="detail-value template-name"></span>
                                    </div>
                                    <div class="detail-item url-item">
                                        <i class="fas fa-link"></i>
                                        <span class="detail-label">Content Link:</span>
                                        <span class="detail-value url-included"></span>
                                    </div>
                                    <div class="detail-item tokens-item">
                                        <i class="fas fa-robot"></i>
                                        <span class="detail-label">AI Tokens:</span>
                                        <span class="detail-value tokens-used"></span>
                                    </div>
                                    <div class="detail-item cost-item">
                                        <i class="fas fa-coins"></i>
                                        <span class="detail-label">Cost:</span>
                                        <span class="detail-value email-cost"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Error State -->
                        <div class="modal-body-section error-state">
                            <div class="error-icon">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <h4>Failed to Load Email</h4>
                            <p class="error-message"></p>
                        </div>
                    </div>
                    
                    <!-- Footer -->
                    <div class="email-modal-footer">
                        <button class="btn btn-secondary close-btn">
                            <i class="fas fa-times"></i> Close
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', modalHTML);
        this.modal = document.getElementById('email-history-modal');
    }
    
    /**
     * Attach event listeners
     */
    attachEventListeners() {
        // Close buttons
        const closeBtn = this.modal.querySelector('.modal-close');
        const footerCloseBtn = this.modal.querySelector('.close-btn');
        
        closeBtn.addEventListener('click', () => this.hideModal());
        footerCloseBtn.addEventListener('click', () => this.hideModal());
        
        // Overlay click
        const overlay = this.modal.querySelector('.email-modal-overlay');
        overlay.addEventListener('click', () => this.hideModal());
        
        // Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.modal.classList.contains('active')) {
                this.hideModal();
            }
        });
        
        // Listen for history requests
        document.addEventListener('rtr:show-email-history', (e) => {
            this.showEmailHistory(e.detail);
        });
    }
    
    /**
     * Show email history
     * 
     * @param {Object} data - { prospectId, emailNumber, prospectName }
     */
    async showEmailHistory(data) {
        const { prospectId, emailNumber, prospectName } = data;
        
        this.currentTracking = null;
        
        // Update header
        const prospectNameEl = this.modal.querySelector('.prospect-name');
        const emailBadge = this.modal.querySelector('.email-number-badge');
        
        prospectNameEl.textContent = prospectName || `Prospect ${prospectId}`;
        emailBadge.textContent = `#${emailNumber}`;
        
        // Show modal in loading state
        this.showModal('loading');
        
        try {
            // Fetch tracking data from API (v2 endpoint)
            const emailApi = new APIClient(
                `${this.config.siteUrl}/wp-json/directreach/v2`,
                this.config.nonce
            );
            
            const response = await emailApi.get(
                `/emails/tracking/prospect/${prospectId}/email/${emailNumber}`
            );
            
            if (response.success && response.data) {
                this.currentTracking = response.data;
                this.displayEmailHistory(response.data);
                this.showModal('history');
            } else {
                throw new Error(response.message || 'Failed to load email history');
            }
        } catch (error) {
            console.error('Failed to load email history:', error);
            this.showError(error.message);
        }
    }
    
    /**
     * Display email history data
     * 
     * @param {Object} data - Tracking data from API
     */
    displayEmailHistory(data) {
        // Subject
        const subjectEl = this.modal.querySelector('.email-subject-readonly');
        subjectEl.textContent = data.subject || 'No subject';
        
        // Body
        const bodyEl = this.modal.querySelector('.email-body-readonly');
        bodyEl.innerHTML = data.body_html || '<p>No content</p>';
        
        // Sent date
        const sentDateEl = this.modal.querySelector('.sent-date');
        sentDateEl.textContent = this.formatDate(data.copied_at || data.sent_at);
        
        // Status
        const statusEl = this.modal.querySelector('.email-status');
        const status = this.getStatusDisplay(data.status, data.opened_at);
        statusEl.innerHTML = status;
        
        // Template
        const templateEl = this.modal.querySelector('.template-name');
        if (data.template_used) {
            const badge = data.template_used.is_global 
                ? '<span class="badge global-badge">Global</span>' 
                : '<span class="badge campaign-badge">Campaign</span>';
            templateEl.innerHTML = `${this.escapeHtml(data.template_used.name)} ${badge}`;
        } else {
            templateEl.textContent = 'No template';
        }
        
        // URL
        const urlItem = this.modal.querySelector('.url-item');
        const urlEl = this.modal.querySelector('.url-included');
        if (data.url_included) {
            urlEl.innerHTML = `<a href="${this.escapeHtml(data.url_included)}" target="_blank" rel="noopener">${this.truncateUrl(data.url_included)}</a>`;
            urlItem.style.display = 'flex';
        } else {
            urlItem.style.display = 'none';
        }
        
        // Tokens (only for AI-generated emails)
        const tokensItem = this.modal.querySelector('.tokens-item');
        const tokensEl = this.modal.querySelector('.tokens-used');
        if (data.generated_by_ai && data.usage) {
            tokensEl.textContent = `${data.usage.total_tokens} tokens`;
            tokensItem.style.display = 'flex';
        } else {
            tokensItem.style.display = 'none';
        }
        
        // Cost (only for AI-generated emails)
        const costItem = this.modal.querySelector('.cost-item');
        const costEl = this.modal.querySelector('.email-cost');
        if (data.generated_by_ai && data.usage) {
            costEl.textContent = `$${data.usage.cost.toFixed(4)}`;
            costItem.style.display = 'flex';
        } else {
            costItem.style.display = 'none';
        }
    }
    
    /**
     * Get status display HTML
     */
    getStatusDisplay(status, openedAt) {
        if (openedAt) {
            return '<span class="status-badge status-opened"><i class="fas fa-envelope-open"></i> Opened</span>';
        }
        
        switch (status) {
            case 'copied':
            case 'sent':
                return '<span class="status-badge status-sent"><i class="fas fa-paper-plane"></i> Sent</span>';
            case 'opened':
                return '<span class="status-badge status-opened"><i class="fas fa-envelope-open"></i> Opened</span>';
            case 'clicked':
                return '<span class="status-badge status-clicked"><i class="fas fa-mouse-pointer"></i> Clicked</span>';
            default:
                return '<span class="status-badge status-pending"><i class="fas fa-clock"></i> Pending</span>';
        }
    }
    
    /**
     * Format date for display
     */
    formatDate(dateStr) {
        if (!dateStr) return 'Unknown';
        
        const date = new Date(dateStr);
        const options = {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        };
        
        return date.toLocaleDateString('en-US', options);
    }
    
    /**
     * Truncate URL for display
     */
    truncateUrl(url) {
        if (url.length <= 50) return url;
        return url.substring(0, 47) + '...';
    }
    
    /**
     * Show modal in specific state
     */
    showModal(state) {
        // Hide all sections
        this.modal.querySelectorAll('.modal-body-section').forEach(section => {
            section.classList.remove('active');
        });
        
        // Show requested section
        const stateMap = {
            'loading': '.loading-state',
            'history': '.history-display',
            'error': '.error-state'
        };
        
        const section = this.modal.querySelector(stateMap[state]);
        if (section) {
            section.classList.add('active');
        }
        
        // Show modal
        this.modal.classList.add('active');
    }
    
    /**
     * Hide modal
     */
    hideModal() {
        this.modal.classList.remove('active');
        this.currentTracking = null;
    }
    
    /**
     * Show error state
     */
    showError(message) {
        const errorMsg = this.modal.querySelector('.error-message');
        errorMsg.textContent = message || 'An unexpected error occurred.';
        
        this.showModal('error');
    }
    
    /**
     * Escape HTML
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }
}

// Make APIClient available (imported from main.js context)
class APIClient {
    constructor(baseUrl, nonce) {
        this.baseUrl = baseUrl;
        this.nonce = nonce;
    }
    
    async request(endpoint, options = {}) {
        const url = `${this.baseUrl}${endpoint}`;
        const headers = {
            'Content-Type': 'application/json',
            'X-WP-Nonce': this.nonce
        };
        
        const config = {
            ...options,
            headers: {
                ...headers,
                ...options.headers
            }
        };
        
        try {
            const response = await fetch(url, config);
            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.message || 'Request failed');
            }
            
            return data;
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    }
    
    get(endpoint, params = {}) {
        const query = new URLSearchParams(params).toString();
        const url = query ? `${endpoint}?${query}` : endpoint;
        return this.request(url, { method: 'GET' });
    }
}