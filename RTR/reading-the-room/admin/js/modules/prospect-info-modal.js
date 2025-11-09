/**
 * Prospect Info Modal Manager
 * 
 * Displays detailed information about a prospect from both
 * rtr_prospects and cpd_visitors tables
 */

export default class ProspectInfoModal {
    constructor(config) {
        this.config = config;
        this.apiUrl = config?.restUrl || config?.apiUrl || window.rtrDashboardConfig?.restUrl;
        this.nonce = config?.nonce || window.rtrDashboardConfig?.nonce;
        this.modal = null;
        this.isOpen = false;
        this.listenersAttached = false;
        
        this.init();
    }

    init() {
        this.createModal();
        if (!this.listenersAttached) {
            this.attachEventListeners();
            this.listenersAttached = true;
        }
    }

    createModal() {
        // Check if modal already exists
        let existingModal = document.getElementById('prospect-info-modal');
        if (existingModal) {
            this.modal = existingModal;
            return;
        }
        
        // Create modal structure
        const modal = document.createElement('div');
        modal.id = 'prospect-info-modal';
        modal.className = 'rtr-modal';
        modal.innerHTML = `
            <div class="rtr-modal-overlay"></div>
            <div class="rtr-modal-content prospect-info-modal-content">
                <div class="rtr-modal-header">
                    <h3><i class="fas fa-user-circle"></i> Prospect Details</h3>
                    <button class="rtr-modal-close" aria-label="Close">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="rtr-modal-body prospect-info-body">
                    <div class="prospect-info-loading">
                        <i class="fas fa-spinner fa-spin"></i>
                        <p>Loading prospect details...</p>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        this.modal = modal;
    }

    attachEventListeners() {
        // Listen for open requests
        document.addEventListener('rtr:showProspectInfo', (e) => {
            const { visitorId, room } = e.detail;
            this.open(visitorId, room);
        });

        // Close on overlay click
        if (this.modal) {
            const overlay = this.modal.querySelector('.rtr-modal-overlay');
            overlay.addEventListener('click', () => this.close());

            // Close on X button
            const closeBtn = this.modal.querySelector('.rtr-modal-close');
            closeBtn.addEventListener('click', () => this.close());
        }

        // Close on Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.isOpen) {
                this.close();
            }
        });
    }

    async open(visitorId, room) {
        if (!visitorId) {
            console.error('No visitor ID provided');
            return;
        }

        this.modal.classList.add('active');
        this.isOpen = true;
        document.body.style.overflow = 'hidden';

        // Show loading state
        const body = this.modal.querySelector('.rtr-modal-body');
        body.innerHTML = `
            <div class="prospect-info-loading">
                <i class="fas fa-spinner fa-spin"></i>
                <p>Loading prospect details...</p>
            </div>
        `;

        try {
            // Fetch prospect details
            const prospectData = await this.fetchProspectDetails(visitorId);
            
            // Render the data
            this.renderProspectInfo(prospectData);
        } catch (error) {
            console.error('Failed to load prospect details:', error);
            this.showError(error.message);
        }
    }

    close() {
        this.modal.classList.remove('active');
        this.isOpen = false;
        document.body.style.overflow = '';
    }

    async fetchProspectDetails(visitorId) {
        const url = `${this.apiUrl}/prospects/${visitorId}/details`;
        
        const response = await fetch(url, {
            method: 'GET',
            headers: {
                'X-WP-Nonce': this.nonce,
                'Content-Type': 'application/json'
            }
        });

        if (!response.ok) {
            throw new Error(`Failed to fetch prospect details: ${response.statusText}`);
        }

        const data = await response.json();
        return data.data || data;
    }

    renderProspectInfo(data) {
        const body = this.modal.querySelector('.rtr-modal-body');
        
        // Extract prospect, visitor, and intelligence data
        const prospect = data.prospect || {};
        const visitor = data.visitor || {};
        const intelligence = data.intelligence || {};
        
        body.innerHTML = `
            <div class="prospect-info-container">
                <!-- Contact Information Section -->
                <div class="info-section">
                    <h4 class="info-section-title">
                        <i class="fas fa-user"></i> Contact Information
                    </h4>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Name:</span>
                            <span class="info-value">${this.escapeHtml(prospect.contact_name || visitor.first_name + ' ' + visitor.last_name || 'N/A')}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Email:</span>
                            <span class="info-value">${this.escapeHtml(prospect.contact_email || visitor.email || 'N/A')}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Job Title:</span>
                            <span class="info-value">${this.escapeHtml(visitor.job_title || 'N/A')}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">LinkedIn:</span>
                            <span class="info-value">
                                ${visitor.linkedin_url ? `<a href="${this.escapeHtml(visitor.linkedin_url)}" target="_blank" rel="noopener">View Profile <i class="fas fa-external-link-alt"></i></a>` : 'N/A'}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Company Information Section -->
                <div class="info-section">
                    <h4 class="info-section-title">
                        <i class="fas fa-building"></i> Company Information
                    </h4>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Company:</span>
                            <span class="info-value">${this.escapeHtml(prospect.company_name || visitor.company_name || 'N/A')}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Website:</span>
                            <span class="info-value">
                                ${visitor.website ? `<a href="${this.escapeHtml(visitor.website)}" target="_blank" rel="noopener">${this.escapeHtml(visitor.website)} <i class="fas fa-external-link-alt"></i></a>` : 'N/A'}
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Industry:</span>
                            <span class="info-value">${this.escapeHtml(visitor.industry || 'N/A')}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Company Size:</span>
                            <span class="info-value">${this.escapeHtml(visitor.estimated_employee_count || 'N/A')}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Revenue:</span>
                            <span class="info-value">${this.escapeHtml(visitor.estimated_revenue || 'N/A')}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Location:</span>
                            <span class="info-value">${this.formatLocation(visitor)}</span>
                        </div>
                    </div>
                </div>

                <!-- Engagement Information Section -->
                <div class="info-section">
                    <h4 class="info-section-title">
                        <i class="fas fa-chart-line"></i> Engagement Information
                    </h4>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Current Room:</span>
                            <span class="info-value">
                                <span class="room-badge room-badge-${this.escapeHtml(visitor.current_room || 'none')}">${this.formatRoom(visitor.current_room)}</span>
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Lead Score:</span>
                            <span class="info-value">
                                <span class="lead-score-badge" style="background-color: ${this.getScoreColor(visitor.lead_score)}">${visitor.lead_score || 0}</span>
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Days in Room:</span>
                            <span class="info-value">${prospect.days_in_room || 0} days</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Email Position:</span>
                            <span class="info-value">${prospect.email_sequence_position || 0} / 5</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Total Page Views:</span>
                            <span class="info-value">${visitor.all_time_page_views || 0}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Recent Page Views:</span>
                            <span class="info-value">${visitor.recent_page_count || 0}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">First Seen:</span>
                            <span class="info-value">${this.formatDate(visitor.first_seen_at)}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Last Seen:</span>
                            <span class="info-value">${this.formatDate(visitor.last_seen_at)}</span>
                        </div>
                    </div>
                </div>

                <!-- Campaign Information Section -->
                ${prospect.campaign_id ? `
                <div class="info-section">
                    <h4 class="info-section-title">
                        <i class="fas fa-bullhorn"></i> Campaign Information
                    </h4>
                    <div class="info-grid">
                        <div class="info-item full-width">
                            <span class="info-label">Campaign:</span>
                            <span class="info-value">${this.escapeHtml(prospect.campaign_name || 'N/A')}</span>
                        </div>
                    </div>
                </div>
                ` : ''}

                <!-- Recent Pages Visited Section -->
                ${this.renderRecentPages(visitor.recent_page_urls)}

                <!-- Email States Section -->
                ${this.renderEmailStates(prospect.email_states)}

                <!-- AI Intelligence Section -->
                ${this.renderIntelligence(intelligence)}

                <!-- Additional Data Section -->
                <div class="info-section">
                    <h4 class="info-section-title">
                        <i class="fas fa-info-circle"></i> Additional Information
                    </h4>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Status:</span>
                            <span class="info-value">${this.escapeHtml(visitor.status || 'N/A')}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">CRM Added:</span>
                            <span class="info-value">${visitor.is_crm_added === '1' ? 'Yes' : 'No'}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Archived:</span>
                            <span class="info-value">${visitor.is_archived === '1' ? 'Yes' : 'No'}</span>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    renderRecentPages(pagesJson) {
        if (!pagesJson) return '';
        
        let pages;
        try {
            pages = JSON.parse(pagesJson);
        } catch (e) {
            return '';
        }
        
        if (!Array.isArray(pages) || pages.length === 0) return '';
        
        return `
            <div class="info-section">
                <h4 class="info-section-title">
                    <i class="fas fa-history"></i> Recent Pages Visited
                </h4>
                <div class="recent-pages-list">
                    ${pages.slice(0, 10).map(url => `
                        <div class="recent-page-item">
                            <i class="fas fa-link"></i>
                            <a href="${this.escapeHtml(url)}" target="_blank" rel="noopener" title="${this.escapeHtml(url)}">
                                ${this.truncateUrl(url)}
                                <i class="fas fa-external-link-alt"></i>
                            </a>
                        </div>
                    `).join('')}
                </div>
            </div>
        `;
    }

    renderEmailStates(emailStates) {
        if (!emailStates) return '';
        
        let states;
        try {
            states = typeof emailStates === 'string' ? JSON.parse(emailStates) : emailStates;
        } catch (e) {
            return '';
        }
        
        if (!states || Object.keys(states).length === 0) return '';
        
        return `
            <div class="info-section">
                <h4 class="info-section-title">
                    <i class="fas fa-envelope"></i> Email Sequence Status
                </h4>
                <div class="email-states-grid">
                    ${Object.keys(states).sort().map(key => {
                        const emailData = states[key];
                        const emailNum = key.replace('email_', '');
                        return `
                            <div class="email-state-item">
                                <span class="email-number">Email ${emailNum}</span>
                                <span class="email-status ${emailData.state || 'pending'}">${this.formatEmailState(emailData.state)}</span>
                                ${emailData.timestamp ? `<span class="email-timestamp">${this.formatDate(emailData.timestamp)}</span>` : ''}
                            </div>
                        `;
                    }).join('')}
                </div>
            </div>
        `;
    }

    renderIntelligence(intelligence) {
        if (!intelligence || !intelligence.response_data) return '';
        
        const responseData = intelligence.response_data;
        
        // Format the response data as a readable structure
        let formattedData = '';
        
        if (typeof responseData === 'object') {
            formattedData = this.formatIntelligenceObject(responseData);
        } else if (typeof responseData === 'string') {
            formattedData = `<p class="intelligence-text">${this.escapeHtml(responseData)}</p>`;
        }
        
        return `
            <div class="info-section">
                <h4 class="info-section-title">
                    <i class="fas fa-brain"></i> AI Intelligence Insights
                </h4>
                <div class="intelligence-content">
                    ${formattedData}
                    ${intelligence.processing_time ? `<div class="intelligence-meta">Generated in ${intelligence.processing_time}ms</div>` : ''}
                </div>
            </div>
        `;
    }

    formatIntelligenceObject(obj, level = 0) {
        if (!obj || typeof obj !== 'object') return '';
        
        const indent = '  '.repeat(level);
        let html = '';
        
        for (const [key, value] of Object.entries(obj)) {
            const formattedKey = key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
            
            if (value === null || value === undefined) {
                continue;
            } else if (typeof value === 'object' && !Array.isArray(value)) {
                html += `
                    <div class="intelligence-section" style="margin-left: ${level * 20}px;">
                        <strong class="intelligence-key">${this.escapeHtml(formattedKey)}:</strong>
                        ${this.formatIntelligenceObject(value, level + 1)}
                    </div>
                `;
            } else if (Array.isArray(value)) {
                html += `
                    <div class="intelligence-item" style="margin-left: ${level * 20}px;">
                        <strong class="intelligence-key">${this.escapeHtml(formattedKey)}:</strong>
                        <ul class="intelligence-list">
                            ${value.map(item => `<li>${typeof item === 'object' ? this.formatIntelligenceObject(item, level + 1) : this.escapeHtml(String(item))}</li>`).join('')}
                        </ul>
                    </div>
                `;
            } else {
                html += `
                    <div class="intelligence-item" style="margin-left: ${level * 20}px;">
                        <strong class="intelligence-key">${this.escapeHtml(formattedKey)}:</strong>
                        <span class="intelligence-value">${this.escapeHtml(String(value))}</span>
                    </div>
                `;
            }
        }
        
        return html;
    }

    showError(message) {
        const body = this.modal.querySelector('.rtr-modal-body');
        body.innerHTML = `
            <div class="prospect-info-error">
                <i class="fas fa-exclamation-triangle"></i>
                <p>Failed to load prospect details</p>
                <small>${this.escapeHtml(message)}</small>
            </div>
        `;
    }

    // Helper methods
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }

    formatRoom(room) {
        const rooms = {
            'problem': 'Problem Room',
            'solution': 'Solution Room',
            'offer': 'Offer Room',
            'sales': 'Sales Room',
            'none': 'Not Assigned'
        };
        return rooms[room] || room || 'N/A';
    }

    formatLocation(visitor) {
        const parts = [];
        if (visitor.city) parts.push(visitor.city);
        if (visitor.state) parts.push(visitor.state);
        if (visitor.country) parts.push(visitor.country);
        return parts.length > 0 ? parts.join(', ') : 'N/A';
    }

    formatDate(dateString) {
        if (!dateString) return 'N/A';
        const date = new Date(dateString);
        return date.toLocaleString();
    }

    formatEmailState(state) {
        const states = {
            'pending': 'Pending',
            'generating': 'Generating',
            'ready': 'Ready',
            'sent': 'Sent',
            'opened': 'Opened',
            'failed': 'Failed'
        };
        return states[state] || state || 'Unknown';
    }

    getScoreColor(score) {
        const s = parseInt(score) || 0;
        if (s >= 70) return '#10b981';
        if (s >= 40) return '#f59e0b';
        return '#ef4444';
    }

    truncateUrl(url, maxLength = 60) {
        if (!url) return '';
        if (url.length <= maxLength) return url;
        return url.substring(0, maxLength - 3) + '...';
    }
}