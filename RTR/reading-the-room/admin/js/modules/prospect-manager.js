/**
 * Prospect Manager Module
 * 
 * Handles prospect list rendering, filtering, and interactions
 * 
 * @package DirectReach
 * @subpackage ReadingTheRoom
 * @since 1.0.0
 */

export default class ProspectManager {
    constructor(api, config) {
        this.api = api;
        this.config = config;
        this.prospects = {
            problem: [],
            solution: [],
            offer: []
        };
        this.campaigns = [];
        this.currentFilters = {
            problem: 'all',
            solution: 'all',
            offer: 'all'
        };
    }
    
    /**
     * Load all prospects by room
     */
    async loadAllProspects(clientId = null) {
        const rooms = ['problem', 'solution', 'offer'];
        
        for (const room of rooms) {
            await this.loadProspectsForRoom(room, clientId);
        }
    }
    
    /**
     * Load prospects for specific room
     */
    async loadProspectsForRoom(room, clientId = null) {
        try {
            const params = { room, limit: 100 };
            if (clientId) {
                params.campaign_id = clientId;
            }
            
            const response = await this.api.get('/prospects', params);
            
            if (response.success) {
                this.prospects[room] = response.data;
                this.renderRoomSection(room);
                await this.loadCampaignsForRoom(room);
            }
        } catch (error) {
            console.error(`Failed to load ${room} prospects:`, error);
            throw error;
        }
    }
    
    /**
     * Load campaigns for room dropdown
     */
    async loadCampaignsForRoom(room) {
        const prospects = this.prospects[room];
        const campaignIds = [...new Set(prospects.map(p => p.campaign_id))];
        
        // Get campaign names
        const campaigns = [];
        for (const id of campaignIds) {
            try {
                const campaign = await this.getCampaignInfo(id);
                if (campaign) {
                    campaigns.push(campaign);
                }
            } catch (error) {
                console.error('Failed to load campaign:', error);
            }
        }
        
        this.updateCampaignDropdown(room, campaigns);
    }
    
    /**
     * Get campaign info (with caching)
     */
    async getCampaignInfo(campaignId) {
        // Check cache first
        const cached = this.campaigns.find(c => c.id === campaignId);
        if (cached) return cached;
        
        try {
            // Load from API (would be a real endpoint in production)
            // For now, return placeholder
            const campaign = {
                id: campaignId,
                name: `Campaign ${campaignId}`,
                utm_campaign: `campaign-${campaignId}`
            };
            
            this.campaigns.push(campaign);
            return campaign;
        } catch (error) {
            console.error('Failed to get campaign info:', error);
            return null;
        }
    }
    
    /**
     * Render complete room section
     */
    renderRoomSection(room) {
        const detailsSection = document.querySelector('.room-details-section');
        if (!detailsSection) return;
        
        const existingContainer = detailsSection.querySelector(`[data-room="${room}"]`);
        const container = this.createRoomDetailContainer(room);
        
        if (existingContainer) {
            existingContainer.replaceWith(container);
        } else {
            detailsSection.appendChild(container);
        }
        
        this.attachRoomEventListeners(room);
    }
    
    /**
     * Create room detail container
     */
    createRoomDetailContainer(room) {
        const container = document.createElement('div');
        container.className = 'room-detail-container';
        container.dataset.room = room;
        
        const roomInfo = this.getRoomInfo(room);
        const prospects = this.getFilteredProspects(room);
        
        container.innerHTML = `
            <div class="room-detail-header">
                <h3>${roomInfo.name} <span class="room-count-badge">${prospects.length}</span></h3>
                <div class="campaign-filter">
                    <select class="campaign-dropdown" data-room="${room}">
                        <option value="all">All Campaigns</option>
                    </select>
                </div>
            </div>
            <div class="prospect-list" data-room="${room}">
                ${this.renderProspectList(prospects, room)}
            </div>
        `;
        
        return container;
    }
    
    /**
     * Render prospect list
     */
    renderProspectList(prospects, room) {
        if (prospects.length === 0) {
            return `
                <div style="padding: 40px; text-align: center; color: #999;">
                    <i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: 15px; opacity: 0.3;"></i>
                    <p>No prospects in this room</p>
                </div>
            `;
        }
        
        return prospects.map(prospect => this.createProspectRow(prospect, room)).join('');
    }
    
    /**
     * Create single prospect row
     */
    createProspectRow(prospect, room) {
        const scoreClass = `${room}-score`;
        const showHandoff = room === 'offer';
        const contactName = prospect.contact_name || 'Unknown Contact';
        const companyName = prospect.company_name || 'Unknown Company';
        
        // Parse engagement data
        const engagement = this.parseEngagementData(prospect);
        
        // Determine campaign name
        const campaign = this.campaigns.find(c => c.id === prospect.campaign_id);
        const campaignName = campaign ? campaign.name : `Campaign ${prospect.campaign_id}`;
        
        return `
            <div class="prospect-row" data-prospect-id="${prospect.id}" data-campaign-id="${prospect.campaign_id}">
                <div class="prospect-name">
                    <strong>${this.escapeHtml(contactName)}</strong>
                    <div class="company-name">${this.escapeHtml(companyName)}</div>
                    <div class="campaign-tag">${this.escapeHtml(campaignName)}</div>
                </div>
                <div class="prospect-metrics">
                    <div class="lead-score">
                        <span>Lead Score: </span>
                        <span class="score-value ${scoreClass}">${prospect.lead_score || 0}</span>
                    </div>
                    <div class="email-progress">
                        <div class="email-icons">
                            ${this.createEmailIcons(prospect.email_sequence_position || 0, engagement.emails)}
                        </div>
                    </div>
                </div>
                <div class="prospect-actions">
                    ${showHandoff ? `
                        <button class="handoff-btn ${prospect.lead_score >= 85 ? 'handoff-ready' : ''}" 
                                title="Hand off to Sales">
                            <i class="fas fa-handshake"></i>
                        </button>
                    ` : ''}
                    <button class="archive-btn" title="Archive prospect">
                        <i class="fas fa-archive"></i>
                    </button>
                </div>
            </div>
        `;
    }
    
    /**
     * Create email sequence icons
     */
    createEmailIcons(sequencePosition, emails = []) {
        const icons = [];
        
        for (let i = 1; i <= 5; i++) {
            let iconClass = 'email-not-sent';
            let iconType = 'fa-envelope';
            let title = `Email ${i}: Not Sent`;
            
            if (i <= sequencePosition) {
                // Check if we have tracking data for this email
                const emailData = emails.find(e => e.number === i);
                
                if (emailData) {
                    if (emailData.opened) {
                        iconClass = 'email-opened';
                        iconType = 'fa-envelope-open';
                        title = `Email ${i}: Opened`;
                    } else {
                        iconClass = 'email-sent';
                        iconType = 'fa-envelope';
                        title = `Email ${i}: Sent`;
                    }
                } else {
                    iconClass = 'email-sent';
                    iconType = 'fa-envelope';
                    title = `Email ${i}: Sent`;
                }
            }
            
            icons.push(`<i class="fas ${iconType} ${iconClass}" 
                           data-email-number="${i}" 
                           title="${title}"></i>`);
        }
        
        return icons.join('');
    }
    
    /**
     * Parse engagement data from prospect
     */
    parseEngagementData(prospect) {
        const data = {
            emails: [],
            recentPages: [],
            pageViewCount: 0
        };
        
        if (prospect.engagement_data) {
            try {
                const parsed = typeof prospect.engagement_data === 'string' 
                    ? JSON.parse(prospect.engagement_data) 
                    : prospect.engagement_data;
                
                data.emails = parsed.emails || [];
                data.recentPages = parsed.recent_pages || [];
                data.pageViewCount = parsed.page_view_count || 0;
            } catch (error) {
                console.error('Failed to parse engagement data:', error);
            }
        }
        
        return data;
    }
    
    /**
     * Get filtered prospects for room
     */
    getFilteredProspects(room) {
        const allProspects = this.prospects[room] || [];
        const filter = this.currentFilters[room];
        
        if (filter === 'all') {
            return allProspects;
        }
        
        return allProspects.filter(p => p.campaign_id === parseInt(filter));
    }
    
    /**
     * Update campaign dropdown
     */
    updateCampaignDropdown(room, campaigns) {
        const dropdown = document.querySelector(`.campaign-dropdown[data-room="${room}"]`);
        if (!dropdown) return;
        
        // Keep "All Campaigns" option
        const allOption = dropdown.querySelector('option[value="all"]');
        dropdown.innerHTML = '';
        
        if (allOption) {
            dropdown.appendChild(allOption);
        } else {
            dropdown.innerHTML = '<option value="all">All Campaigns</option>';
        }
        
        // Add campaign options
        campaigns.forEach(campaign => {
            const option = document.createElement('option');
            option.value = campaign.id;
            option.textContent = campaign.name;
            dropdown.appendChild(option);
        });
    }
    
    /**
     * Filter prospects by campaign
     */
    filterProspectsByCampaign(room, campaignId) {
        this.currentFilters[room] = campaignId;
        
        const prospectList = document.querySelector(`.prospect-list[data-room="${room}"]`);
        const countBadge = document.querySelector(`[data-room="${room}"] .room-count-badge`);
        
        if (prospectList) {
            const filteredProspects = this.getFilteredProspects(room);
            prospectList.innerHTML = this.renderProspectList(filteredProspects, room);
            
            if (countBadge) {
                countBadge.textContent = filteredProspects.length;
            }
        }
    }
    
    /**
     * Attach event listeners for room
     */
    attachRoomEventListeners(room) {
        // Campaign filter
        const dropdown = document.querySelector(`.campaign-dropdown[data-room="${room}"]`);
        if (dropdown) {
            dropdown.addEventListener('change', (e) => {
                this.filterProspectsByCampaign(room, e.target.value);
            });
        }
        
        // Prospect row clicks
        const rows = document.querySelectorAll(`[data-room="${room}"] .prospect-row`);
        rows.forEach(row => {
            row.addEventListener('click', (e) => {
                // Don't trigger if clicking action buttons
                if (e.target.closest('.prospect-actions')) {
                    return;
                }
                
                const prospectId = row.dataset.prospectId;
                this.showProspectDetails(prospectId);
            });
        });
        
        // Email icon clicks
        const emailIcons = document.querySelectorAll(`[data-room="${room}"] .email-icons i`);
        emailIcons.forEach(icon => {
            icon.addEventListener('click', (e) => {
                e.stopPropagation();
                const prospectRow = icon.closest('.prospect-row');
                const prospectId = prospectRow.dataset.prospectId;
                const emailNumber = icon.dataset.emailNumber;
                this.handleEmailIconClick(prospectId, emailNumber);
            });
        });

        this.attachActionListeners();
    }
    
    /**
     * Show prospect details modal
     */
    showProspectDetails(prospectId) {
        // Find prospect in any room
        let prospect = null;
        for (const room in this.prospects) {
            prospect = this.prospects[room].find(p => p.id === parseInt(prospectId));
            if (prospect) break;
        }
        
        if (!prospect) {
            console.error('Prospect not found:', prospectId);
            return;
        }
        
        // Emit event for modal display
        document.dispatchEvent(new CustomEvent('rtr:show-prospect-details', {
            detail: { prospect }
        }));
    }
    
    /**
     * Handle email icon click
     */
    handleEmailIconClick(prospectId, emailNumber) {
        // Emit event for email modal
        document.dispatchEvent(new CustomEvent('rtr:show-email-details', {
            detail: { 
                prospectId: parseInt(prospectId),
                emailNumber: parseInt(emailNumber)
            }
        }));
    }
    
    /**
     * Remove prospect from UI
     */
    removeProspect(prospectId, animated = true) {
        const row = document.querySelector(`.prospect-row[data-prospect-id="${prospectId}"]`);
        if (!row) return;
        
        if (animated) {
            row.style.transition = 'all 0.3s ease';
            row.style.opacity = '0';
            row.style.transform = 'translateX(-20px)';
            
            setTimeout(() => {
                this.removeProspectElement(row);
            }, 300);
        } else {
            this.removeProspectElement(row);
        }
    }
    
    /**
     * Remove prospect element and update counts
     */
    removeProspectElement(row) {
        const container = row.closest('.room-detail-container');
        const room = container?.dataset.room;
        
        row.remove();
        
        if (container && room) {
            const badge = container.querySelector('.room-count-badge');
            const remainingRows = container.querySelectorAll('.prospect-row');
            
            if (badge) {
                badge.textContent = remainingRows.length;
            }
            
            // Update internal data
            const prospectId = parseInt(row.dataset.prospectId);
            if (this.prospects[room]) {
                this.prospects[room] = this.prospects[room].filter(p => p.id !== prospectId);
            }
            
            // Show empty state if no prospects left
            if (remainingRows.length === 0) {
                const prospectList = container.querySelector('.prospect-list');
                if (prospectList) {
                    prospectList.innerHTML = this.renderProspectList([], room);
                }
            }
        }
    }
    
    /**
     * Update prospect score in UI
     */
    updateProspectScore(prospectId, newScore) {
        const row = document.querySelector(`.prospect-row[data-prospect-id="${prospectId}"]`);
        if (!row) return;
        
        const scoreEl = row.querySelector('.score-value');
        if (scoreEl) {
            scoreEl.textContent = newScore;
            
            // Add animation
            scoreEl.style.transition = 'all 0.3s ease';
            scoreEl.style.transform = 'scale(1.2)';
            scoreEl.style.fontWeight = '700';
            
            setTimeout(() => {
                scoreEl.style.transform = 'scale(1)';
            }, 300);
        }
        
        // Update handoff button if in offer room
        const handoffBtn = row.querySelector('.handoff-btn');
        if (handoffBtn && newScore >= 85) {
            handoffBtn.classList.add('handoff-ready');
        } else if (handoffBtn) {
            handoffBtn.classList.remove('handoff-ready');
        }
    }
    
    /**
     * Get room info
     */
    getRoomInfo(room) {
        const info = {
            problem: { 
                name: 'Problem Room', 
                color: '#e74c3c',
                icon: 'fa-exclamation-triangle'
            },
            solution: { 
                name: 'Solution Room', 
                color: '#f39c12',
                icon: 'fa-lightbulb'
            },
            offer: { 
                name: 'Offer Room', 
                color: '#27ae60',
                icon: 'fa-handshake'
            },
            sales: { 
                name: 'Sales Room', 
                color: '#9b59b6',
                icon: 'fa-dollar-sign'
            }
        };
        return info[room] || info.problem;
    }
    
    /**
     * Escape HTML
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }


/**
     * Archive prospect with confirmation
     */
    async archiveProspect(prospectId, campaignId, companyName) {
        const reason = await this.showArchiveDialog(companyName);
        
        if (reason === null) return; // Cancelled
        
        try {
            const response = await this.api.post(
                `/prospects/${prospectId}/archive`,
                { reason }
            );
            
            if (response.success) {
                this.removeProspect(prospectId, true);
                
                // Emit event for count update
                document.dispatchEvent(new CustomEvent('rtr:prospect-archived', {
                    detail: { prospectId, room: this.getProspectRoom(prospectId) }
                }));
                
                // Show notification
                document.dispatchEvent(new CustomEvent('rtr:notification', {
                    detail: { type: 'success', message: `${companyName} archived` }
                }));
            }
        } catch (error) {
            document.dispatchEvent(new CustomEvent('rtr:notification', {
                detail: { type: 'error', message: 'Failed to archive prospect' }
            }));
        }
    }
    
    /**
     * Hand off to sales
     */
    async handoffToSales(prospectId, campaignId, companyName) {
        const notes = await this.showHandoffDialog(companyName);
        
        if (notes === null) return; // Cancelled
        
        try {
            const response = await this.api.post(
                `/prospects/${prospectId}/handoff-sales`,
                { notes }
            );
            
            if (response.success) {
                this.removeProspect(prospectId, true);
                
                // Emit event for count update
                document.dispatchEvent(new CustomEvent('rtr:prospect-handoff', {
                    detail: { prospectId }
                }));
                
                // Show notification
                document.dispatchEvent(new CustomEvent('rtr:notification', {
                    detail: { type: 'success', message: `${companyName} handed off to sales` }
                }));
            }
        } catch (error) {
            document.dispatchEvent(new CustomEvent('rtr:notification', {
                detail: { type: 'error', message: 'Failed to hand off prospect' }
            }));
        }
    }
    
    /**
     * Show archive dialog
     */
    showArchiveDialog(companyName) {
        return new Promise((resolve) => {
            const modal = document.createElement('div');
            modal.className = 'action-modal';
            modal.innerHTML = `
                <div class="modal-overlay"></div>
                <div class="modal-content">
                    <div class="modal-header">
                        <h3><i class="fas fa-archive"></i> Archive Prospect</h3>
                        <button class="modal-close">&times;</button>
                    </div>
                    <div class="modal-body">
                        <p>Archive <strong>${this.escapeHtml(companyName)}</strong>?</p>
                        <div class="form-group">
                            <label>Reason (optional)</label>
                            <textarea id="archive-reason" rows="3" 
                                placeholder="Why are you archiving this prospect?"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary cancel-btn">Cancel</button>
                        <button class="btn btn-danger confirm-btn">
                            <i class="fas fa-archive"></i> Archive
                        </button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            
            const closeModal = (result) => {
                modal.classList.remove('active');
                setTimeout(() => modal.remove(), 300);
                resolve(result);
            };
            
            modal.querySelector('.cancel-btn').onclick = () => closeModal(null);
            modal.querySelector('.modal-close').onclick = () => closeModal(null);
            modal.querySelector('.modal-overlay').onclick = () => closeModal(null);
            
            modal.querySelector('.confirm-btn').onclick = () => {
                const reason = document.getElementById('archive-reason').value.trim();
                closeModal(reason || '');
            };
            
            requestAnimationFrame(() => modal.classList.add('active'));
        });
    }
    
    /**
     * Show handoff dialog
     */
    showHandoffDialog(companyName) {
        return new Promise((resolve) => {
            const modal = document.createElement('div');
            modal.className = 'action-modal';
            modal.innerHTML = `
                <div class="modal-overlay"></div>
                <div class="modal-content">
                    <div class="modal-header">
                        <h3><i class="fas fa-handshake"></i> Hand Off to Sales</h3>
                        <button class="modal-close">&times;</button>
                    </div>
                    <div class="modal-body">
                        <p>Hand off <strong>${this.escapeHtml(companyName)}</strong> to sales?</p>
                        <div class="form-group">
                            <label>Notes for Sales Team</label>
                            <textarea id="handoff-notes" rows="4" 
                                placeholder="Context or next steps for sales..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary cancel-btn">Cancel</button>
                        <button class="btn btn-primary confirm-btn">
                            <i class="fas fa-handshake"></i> Hand Off
                        </button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            
            const closeModal = (result) => {
                modal.classList.remove('active');
                setTimeout(() => modal.remove(), 300);
                resolve(result);
            };
            
            modal.querySelector('.cancel-btn').onclick = () => closeModal(null);
            modal.querySelector('.modal-close').onclick = () => closeModal(null);
            modal.querySelector('.modal-overlay').onclick = () => closeModal(null);
            
            modal.querySelector('.confirm-btn').onclick = () => {
                const notes = document.getElementById('handoff-notes').value.trim();
                closeModal(notes || '');
            };
            
            requestAnimationFrame(() => modal.classList.add('active'));
        });
    }
    
    /**
     * Get prospect room from DOM
     */
    getProspectRoom(prospectId) {
        const row = document.querySelector(`.prospect-row[data-prospect-id="${prospectId}"]`);
        return row?.closest('.room-detail-container')?.dataset.room || null;
    }
    
    /**
     * Attach action button listeners
     */
    attachActionListeners() {
        // Archive buttons
        document.querySelectorAll('.archive-btn').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                e.stopPropagation();
                const row = btn.closest('.prospect-row');
                const prospectId = parseInt(row.dataset.prospectId);
                const campaignId = parseInt(row.dataset.campaignId);
                const companyName = row.querySelector('.company-name')?.textContent || 'Unknown';
                
                await this.archiveProspect(prospectId, campaignId, companyName);
            });
        });
        
        // Handoff buttons
        document.querySelectorAll('.handoff-btn').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                e.stopPropagation();
                const row = btn.closest('.prospect-row');
                const prospectId = parseInt(row.dataset.prospectId);
                const campaignId = parseInt(row.dataset.campaignId);
                const companyName = row.querySelector('.company-name')?.textContent || 'Unknown';
                
                await this.handoffToSales(prospectId, campaignId, companyName);
            });
        });
    }    
}