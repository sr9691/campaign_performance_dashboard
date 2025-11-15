/**
 * Prospect Manager Module - Phase 3A Enhanced
 * 
 * Manages prospect list display, filtering, and actions
 * Now includes 5-state independent email button system
 */

export default class ProspectManager {
    constructor(config) {
        this.config = config;
        if (typeof config === 'string') {
            this.apiUrl = config;
        } else {
            this.apiUrl = config?.restUrl || config?.apiUrl || window.rtrDashboardConfig?.restUrl || window.rtrDashboardConfig?.apiUrl || '';
        }
        this.nonce = config.nonce;
        this.uiManager = null; // Will be set by main.js
        this.currentFilters = {
            campaign_id: '',
            room: null
        };
        this.prospects = {
            problem: [],
            solution: [],
            offer: [],
        };

        this.campaigns = new Map();
        this.isLoading = {};
        
        // Debounce tracking for button clicks
        this.buttonDebounce = new Map();
        
        this.init();
    }

    init() {
        this.attachEventListeners();
        this.loadAllRooms();

        // Listen for filter changes
        document.addEventListener('rtr:filterChanged', () => {
            this.refreshAllRooms();
        });

        // Listen for email state updates (from modal copy actions)
        document.addEventListener('rtr:email-state-update', (e) => {
            const { visitorId, emailNumber, newState } = e.detail;
            this.updateButtonState(visitorId, emailNumber, newState);
        });
    }

    setUIManager(uiManager) {
        this.uiManager = uiManager;
    }

    attachEventListeners() {
        // Campaign filter
        const campaignFilter = document.getElementById('rtr-campaign-filter');
        if (campaignFilter) {
            campaignFilter.addEventListener('change', (e) => {
                this.currentFilters.campaign_id = e.target.value;
                this.refreshAllRooms();
                document.dispatchEvent(new CustomEvent('rtr:filterChanged'));
            });
        }

        ['problem', 'solution', 'offer'].forEach(room => {
            const campaignFilter = document.getElementById(`${room}-room-campaign-filter`);
            if (campaignFilter) {
                campaignFilter.addEventListener('change', (e) => {
                    this.loadRoomProspects(room);
                });
            }
        });
        
        document.addEventListener('click', (e) => {
            const badge = e.target.closest('.rtr-campaign-badge');
            if (badge && !badge.classList.contains('rtr-more-badge')) {
                const campaignId = badge.dataset.campaignId;
                const room = badge.closest('.room-detail-container')?.id?.replace('rtr-room-', '');
                
                if (campaignId && room) {
                    const campaignFilter = document.getElementById(`${room}-room-campaign-filter`);
                    if (campaignFilter) {
                        campaignFilter.value = campaignId;
                        this.loadRoomProspects(room);
                    }
                }
            }
        });        

        // Delegate click events for prospect actions
        document.addEventListener('click', (e) => {
            // Email button
            const emailBtn = e.target.closest('.rtr-email-btn');
            if (emailBtn) {
                e.preventDefault();
                const prospectId = emailBtn.dataset.prospectId;
                const visitorId = emailBtn.dataset.visitorId;
                const room = emailBtn.dataset.room;
                const emailNumber = parseInt(emailBtn.dataset.emailNumber);
                const emailState = emailBtn.dataset.emailState;
                this.handleEmailClick(visitorId, room, emailNumber, emailState);
            }

            // Info button
            const infoBtn = e.target.closest('.rtr-info-btn');
            if (infoBtn) {
                e.preventDefault();
                const visitorId = infoBtn.dataset.visitorId;
                const room = infoBtn.dataset.room;
                this.handleInfoClick(visitorId, room);
            }

            // Archive/Delete button
            const archiveBtn = e.target.closest('.rtr-archive-btn');
            if (archiveBtn) {
                e.preventDefault();
                const visitorId = archiveBtn.dataset.visitorId;
                const room = archiveBtn.dataset.room;
                this.handleArchiveClick(visitorId, room);
            }

            // Sales handoff button
            const handoffBtn = e.target.closest('.rtr-handoff-btn');
            if (handoffBtn) {
                e.preventDefault();
                const visitorId = handoffBtn.dataset.visitorId;
                this.handleSalesHandoff(visitorId);
            }

            // Email history button
            const historyBtn = e.target.closest('.rtr-email-history-btn');
            if (historyBtn) {
                e.preventDefault();
                const visitorId = historyBtn.dataset.visitorId;
                const room = historyBtn.dataset.room;
                this.handleEmailHistoryClick(visitorId, room);
            }

            // Edit contact button
            const editContactBtn = e.target.closest('.rtr-edit-contact-btn');
            if (editContactBtn) {
                e.preventDefault();
                e.stopPropagation();
                const visitorId = editContactBtn.dataset.visitorId;
                const room = editContactBtn.dataset.room;
                this.showEnrichmentModal(visitorId, room);
            }

            // Event Delegation - Lead Score Click
            const scoreValue = e.target.closest('.rtr-score-clickable');
            if (scoreValue) {
                e.preventDefault();
                const visitorId = scoreValue.dataset.visitorId;
                const clientId = scoreValue.dataset.clientId;
                const prospectName = scoreValue.dataset.prospectName;
                this.handleScoreClick(visitorId, clientId, prospectName);
            }

        });
    }

    async loadAllRooms() {
        const rooms = ['problem', 'solution', 'offer'];
        console.log('Loading all rooms:', rooms);
        await Promise.all(rooms.map(room => this.loadRoomProspects(room)));
        console.log('All rooms loaded');
    }

    async refreshAllRooms() {
        const rooms = ['problem', 'solution', 'offer'];
        await Promise.all(rooms.map(room => this.loadRoomProspects(room, this.currentFilters.campaign_id)));
    }

    async loadRoomProspects(room, campaignId = null) {
        console.log(`Loading prospects for room: ${room} with campaign filter: ${campaignId}`);
        if (this.isLoading[room]) {
            return;
        }

        this.isLoading[room] = true;
        console.log(`Fetching prospects for room: ${room}...`);
        const container = document.querySelector(`#rtr-room-${room} .rtr-prospect-list`);
        
        if (!container) {
            this.isLoading[room] = false;
            return;
        }

        this.showLoadingState(container, room);

        try {
            const url = new URL(`${this.apiUrl}/prospects`, window.location.origin);
            url.searchParams.append('room', room);

            const clientFilter = document.getElementById('client-select');

            if (clientFilter && clientFilter.value) {
                url.searchParams.append('client_id', clientFilter.value);
            }

            const dateFilter = document.getElementById('date-filter');

            if (dateFilter && dateFilter.value) {
                url.searchParams.append('days', dateFilter.value);
            }            

            const campaignFilter = document.getElementById(`${room}-room-campaign-filter`);

            if (campaignFilter && campaignFilter.value) {
                url.searchParams.append('campaign_id', campaignFilter.value);
            }

            const response = await fetch(url, {
                headers: {
                    'X-WP-Nonce': this.nonce
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const data = await response.json();
            this.prospects[room] = data.data || [];
            
            // Initialize email states for prospects that don't have them
            this.prospects[room].forEach(prospect => {
                this.initializeEmailStates(prospect);
            });
            
            this.populateCampaignDropdowns(room, this.prospects[room]);
            this.renderProspects(room, this.prospects[room]);

        } catch (error) {
            console.error(`Failed to load ${room} room prospects:`, error);
            this.showErrorState(container, room, error.message);
        } finally {
            this.isLoading[room] = false;
        }
    }

    populateCampaignDropdowns(room, prospects) {
        const campaignFilter = document.getElementById(`${room}-room-campaign-filter`);
        if (!campaignFilter) return;

        // Extract unique campaigns from prospects
        const campaigns = new Map();
        prospects.forEach(prospect => {
            // Campaign data is directly on the prospect object
            if (prospect.campaign_id && prospect.campaign_name) {
                campaigns.set(prospect.campaign_id, prospect.campaign_name);
            }
        });

        console.log(`Found ${campaigns.size} campaigns for ${room}:`, Array.from(campaigns.entries()));

        // Store current selection
        const currentValue = campaignFilter.value;

        // Clear existing options except "All Campaigns"
        campaignFilter.innerHTML = '<option value="">All Campaigns</option>';

        // Add campaign options sorted by name
        Array.from(campaigns.entries())
            .sort((a, b) => a[1].localeCompare(b[1]))
            .forEach(([id, name]) => {
                const option = document.createElement('option');
                option.value = id;
                option.textContent = name;
                campaignFilter.appendChild(option);
            });

        // Restore previous selection if it still exists
        if (currentValue && campaigns.has(currentValue)) {
            campaignFilter.value = currentValue;
        }
    }

    showLoadingState(container, room) {
        container.innerHTML = `
            <div class="rtr-loading-state">
                <i class="fas fa-spinner fa-spin"></i>
                <p>Loading ${room} room prospects...</p>
            </div>
        `;
    }

    showErrorState(container, room, errorMessage) {
        container.innerHTML = `
            <div class="rtr-error-state">
                <i class="fas fa-exclamation-triangle"></i>
                <p>Failed to load ${room} room prospects</p>
                <small>${this.escapeHtml(errorMessage)}</small>
            </div>
        `;
    }

    renderProspects(room, prospects) {
        const container = document.querySelector(`#rtr-room-${room} .rtr-prospect-list`);
        if (!container) return;

        if (!prospects || prospects.length === 0) {
            container.innerHTML = `
                <div class="rtr-empty-state">
                    <i class="fas fa-inbox"></i>
                    <p>No prospects in ${room} room</p>
                </div>
            `;
            this.updateRoomBadge(room, 0);
            return;
        }

        // Clear container
        container.innerHTML = '';
        
        // Append each prospect row as a DOM element
        prospects.forEach(prospect => {
            const row = this.renderProspectRow(prospect, room);
            container.appendChild(row);
        });

        this.updateRoomBadge(room, prospects.length);
    }

    renderProspectRow(prospect, room) {
        const row = document.createElement('div');
        row.className = 'rtr-prospect-row';
        row.dataset.prospectId = prospect.id;
        row.dataset.visitorId = prospect.visitor_id || prospect.id;

        // Left Section: Prospect Info
        const infoSection = document.createElement('div');
        infoSection.className = 'rtr-prospect-info';

        // Name - handle multiple field formats with edit button for Unknown
        const nameEl = document.createElement('h3');
        nameEl.className = 'rtr-prospect-name';
        const prospectName = prospect.contact_name || 
                            `${prospect.first_name || ''} ${prospect.last_name || ''}`.trim() ||
                            prospect.name ||
                            'Name Unknown';
        nameEl.textContent = prospectName;
        
        // Add edit button for Unknown prospects
        if (prospectName === 'Name Unknown') {
            const editBtn = document.createElement('button');
            editBtn.className = 'rtr-edit-contact-btn';
            editBtn.innerHTML = '<i class="fas fa-user-edit"></i>';
            editBtn.title = 'Add contact information';
            editBtn.dataset.visitorId = prospect.visitor_id || prospect.id;
            editBtn.dataset.room = room;
            nameEl.appendChild(editBtn);
        }
        
        infoSection.appendChild(nameEl);

        // Job Title
        const jobTitle = prospect.job_title || prospect.title || '';
        if (jobTitle) {
            const titleEl = document.createElement('p');
            titleEl.className = 'rtr-prospect-title';
            titleEl.textContent = jobTitle;
            infoSection.appendChild(titleEl);
        }

        // Company (with ellipsis)
        const companyName = prospect.company_name || prospect.company || 'Company Unknown';
        if (companyName) {
            const companyEl = document.createElement('p');
            companyEl.className = 'rtr-company';
            companyEl.textContent = companyName;
            companyEl.title = companyName; // Show full name on hover
            infoSection.appendChild(companyEl);
        }
        // Campaign Badges
        const campaignName = prospect.campaign_name || '';
        if (campaignName) {
            const badgesContainer = document.createElement('div');
            badgesContainer.className = 'rtr-campaign-badges';
            
            const badge = document.createElement('span');
            badge.className = 'rtr-campaign-badge';
            badge.textContent = campaignName;
            badge.title = campaignName;
            badge.dataset.campaignId = prospect.campaign_id || '';
            badgesContainer.appendChild(badge);
            
            infoSection.appendChild(badgesContainer);
        }

        row.appendChild(infoSection);

        // Right Section: Score, Email Sequence, Actions
        const rightSection = document.createElement('div');
        rightSection.className = 'rtr-prospect-right';

        // Lead Score
        const scoreContainer = document.createElement('div');
        scoreContainer.className = 'rtr-lead-score-container';
        
        const scoreLabel = document.createElement('span');
        scoreLabel.className = 'rtr-score-label';
        scoreLabel.textContent = 'Lead Score:';
        
        const scoreValue = document.createElement('span');
        scoreValue.className = 'rtr-score-value rtr-score-clickable';
        scoreValue.textContent = prospect.lead_score || '0';
        scoreValue.title = 'Click to view score breakdown';
        scoreValue.style.cursor = 'pointer';
        scoreValue.dataset.visitorId = prospect.visitor_id || prospect.id;
        scoreValue.dataset.clientId = prospect.client_id || '';
        scoreValue.dataset.prospectName = prospect.contact_name || 
                            `${prospect.first_name || ''} ${prospect.last_name || ''}`.trim() ||
                            prospect.name ||
                            'Unknown';
                                    
        scoreContainer.appendChild(scoreLabel);
        scoreContainer.appendChild(scoreValue);
        rightSection.appendChild(scoreContainer);

        // Email Sequence
        const emailSequence = document.createElement('div');
        emailSequence.className = 'rtr-email-sequence';

        const emailStates = prospect.email_states || {};
        const emailCount = 5;

        for (let i = 1; i <= emailCount; i++) {
            const emailKey = `email_${i}`;
            const emailData = emailStates[emailKey] || { state: 'pending', timestamp: null };
            const state = emailData.state || 'pending';
            
            const emailBtn = document.createElement('button');
            emailBtn.className = 'rtr-email-btn';
            emailBtn.dataset.visitorId = prospect.visitor_id || prospect.id;
            emailBtn.dataset.room = room;
            emailBtn.dataset.emailNumber = i;
            emailBtn.dataset.emailState = state;
            emailBtn.title = `Email ${i}`;

            const isNextInSequence = this.isNextInSequence(emailStates, i);
            if (isNextInSequence && (state === 'ready' || state === 'pending')) {
                emailBtn.classList.add('rtr-email-pulse');
            }

            const isDisabled = !this.isEmailEnabled(emailStates, i);
            if (isDisabled) {
                emailBtn.classList.add('rtr-email-disabled');
                emailBtn.disabled = true;
            }            

            const icon = document.createElement('i');
            icon.className = 'fas';

            switch (state) {
                case 'sent':
                    icon.classList.add('fa-check');
                    emailBtn.classList.add('rtr-email-sent');
                    break;
                case 'opened':
                    icon.classList.add('fa-envelope-open');
                    emailBtn.classList.add('rtr-email-opened');
                    break;
                case 'generating':
                    icon.classList.add('fa-spinner', 'fa-spin');
                    emailBtn.classList.add('rtr-email-generating');
                    emailBtn.disabled = true;
                    break;
                case 'ready':
                    icon.classList.add('fa-envelope');
                    emailBtn.classList.add('rtr-email-ready');
                    break;
                default:
                    icon.classList.add('fa-envelope');
                    emailBtn.classList.add('rtr-email-pending');
            }

            emailBtn.appendChild(icon);
            emailSequence.appendChild(emailBtn);
        }

        rightSection.appendChild(emailSequence);

        // Actions
        const actionsContainer = document.createElement('div');
        actionsContainer.className = 'rtr-prospect-actions';

        // Info Button
        const infoBtn = document.createElement('button');
        infoBtn.className = 'rtr-action-btn rtr-info-btn';
        infoBtn.innerHTML = '<i class="fas fa-info-circle"></i>';
        infoBtn.title = 'View Prospect Details';
        infoBtn.dataset.visitorId = prospect.visitor_id || prospect.id;
        infoBtn.dataset.prospectId = prospect.id;
        infoBtn.dataset.room = room;
        actionsContainer.appendChild(infoBtn);

        // Archive Button
        const archiveBtn = document.createElement('button');
        archiveBtn.className = 'rtr-action-btn rtr-archive-btn';
        archiveBtn.innerHTML = '<i class="fas fa-archive"></i>';
        archiveBtn.title = 'Archive Prospect';
        archiveBtn.dataset.visitorId = prospect.id;
        archiveBtn.dataset.room = room;
        actionsContainer.appendChild(archiveBtn);

        // Sales Handoff Button (Offer Room only)
        if (room === 'offer') {
            const handoffBtn = document.createElement('button');
            handoffBtn.className = 'rtr-action-btn rtr-handoff-btn';
            handoffBtn.innerHTML = '<i class="fas fa-handshake"></i>';
            handoffBtn.title = 'Hand Off to Sales';
            handoffBtn.dataset.visitorId = prospect.id;
            actionsContainer.appendChild(handoffBtn);
        }

        rightSection.appendChild(actionsContainer);
        row.appendChild(rightSection);

        return row;
    }

    isNextInSequence(emailStates, emailNumber) {
        // Email 1 is always next if it's pending or ready
        if (emailNumber === 1) {
            const email1 = emailStates['email_1'];
            return email1?.state === 'pending' || email1?.state === 'ready';
        }
        
        // Check if all previous emails are sent/opened
        for (let i = 1; i < emailNumber; i++) {
            const emailKey = `email_${i}`;
            const state = emailStates[emailKey]?.state;
            if (state !== 'sent' && state !== 'opened') {
                return false;
            }
        }
        
        // This email is next if it's pending or ready
        const currentEmail = emailStates[`email_${emailNumber}`];
        return currentEmail?.state === 'pending' || currentEmail?.state === 'ready';
    }

    isEmailEnabled(emailStates, emailNumber) {
        // Email 1 is always enabled
        if (emailNumber === 1) return true;
        
        // Check if all previous emails are sent/opened
        for (let i = 1; i < emailNumber; i++) {
            const emailKey = `email_${i}`;
            const state = emailStates[emailKey]?.state;
            if (state !== 'sent' && state !== 'opened') {
                return false;
            }
        }
        
        return true;
    }  

    /**
     * Initialize email states if missing
     * @param {Object} prospect - Prospect object
     */
    initializeEmailStates(prospect) {
        if (!prospect.email_states) {
            // Initialize with default states
            prospect.email_states = {
                email_1: { state: 'pending', timestamp: null },
                email_2: { state: 'pending', timestamp: null },
                email_3: { state: 'pending', timestamp: null },
                email_4: { state: 'pending', timestamp: null },
                email_5: { state: 'pending', timestamp: null }
            };
        }
    }


    /**
     * Get CSS class for email button based on state
     * @param {String} state - Email state (pending|generating|ready|sent|opened)
     * @returns {String} CSS class name
     */
    getEmailButtonClass(state) {
        const classMap = {
            'pending': 'rtr-email-pending',
            'generating': 'rtr-email-generating',
            'ready': 'rtr-email-ready',
            'sent': 'rtr-email-sent',
            'opened': 'rtr-email-opened',
            'failed': 'rtr-email-failed'
        };
        return classMap[state] || 'rtr-email-pending';
    }

    /**
     * Get icon class for email button based on state
     * @param {String} state - Email state
     * @returns {String} Font Awesome icon class
     */
    getEmailButtonIcon(state) {
        const iconMap = {
            'pending': 'fas fa-envelope',
            'generating': 'fas fa-spinner fa-spin',
            'ready': 'fas fa-envelope-open',
            'sent': 'fas fa-paper-plane',
            'opened': 'fas fa-envelope-open-text',
            'failed': 'fas fa-exclamation-triangle'
        };
        return iconMap[state] || 'fas fa-envelope';
    }

    /**
     * Get tooltip text for email button
     * @param {String} state - Email state
     * @param {Number} emailNumber - Email sequence number (1-5)
     * @param {String|null} timestamp - State timestamp
     * @returns {String} Tooltip text
     */
    getEmailButtonTooltip(state, emailNumber, timestamp) {
        const tooltipMap = {
            'pending': `Email ${emailNumber}: Click to generate`,
            'generating': `Email ${emailNumber}: Generating...`,
            'ready': `Email ${emailNumber}: Ready - Click to view`,
            'sent': `Email ${emailNumber}: Sent ${timestamp ? this.formatDate(timestamp) : ''}`,
            'opened': `Email ${emailNumber}: Opened ${timestamp ? this.formatDate(timestamp) : ''}`,
            'failed': `Email ${emailNumber}: Failed - Click to retry`
        };
        return tooltipMap[state] || `Email ${emailNumber}`;
    }

    /**
     * Format date for tooltip display
     * @param {String} dateString - ISO date string
     * @returns {String} Formatted date
     */
    formatDate(dateString) {
        if (!dateString) return '';
        
        const date = new Date(dateString);
        const now = new Date();
        const diffMs = now - date;
        const diffMins = Math.floor(diffMs / 60000);
        const diffHours = Math.floor(diffMs / 3600000);
        const diffDays = Math.floor(diffMs / 86400000);

        if (diffMins < 1) return 'just now';
        if (diffMins < 60) return `${diffMins}m ago`;
        if (diffHours < 24) return `${diffHours}h ago`;
        if (diffDays < 7) return `${diffDays}d ago`;
        
        return date.toLocaleDateString();
    }

    /**
     * Handle email button click - route based on state
     * Modified to handle email number and state
     * @param {String} visitorId - Visitor ID
     * @param {String} room - Room name
     * @param {Number} emailNumber - Email sequence number
     * @param {String} emailState - Current email state
     */
    handleEmailClick(visitorId, room, emailNumber, emailState) {
        // Debounce rapid clicks (500ms)
        const debounceKey = `${visitorId}-${emailNumber}`;
        const now = Date.now();
        const lastClick = this.buttonDebounce.get(debounceKey) || 0;
        
        if (now - lastClick < 500) {
            console.log('Debouncing rapid click');
            return;
        }
        this.buttonDebounce.set(debounceKey, now);

        // Route based on email state
        switch (emailState) {
            case 'pending':
            case 'failed':
                this.generateNewEmail(visitorId, room, emailNumber);
                break;
                
            case 'generating':
                // Do nothing - button should be disabled
                break;
                
            case 'ready':
                this.viewReadyEmail(visitorId, room, emailNumber);
                break;
                
            case 'sent':
            case 'opened':
                this.viewEmailHistory(visitorId, room, emailNumber);
                break;
                
            default:
                console.warn('Unknown email state:', emailState);
        }
    }

    /**
     * Generate a new email (pending or failed state)
     * NEW: Async generation - no modal, just notification
     * @param {String} visitorId - Visitor ID
     * @param {String} room - Room name
     * @param {Number} emailNumber - Email sequence number
     */
    async generateNewEmail(visitorId, room, emailNumber) {
        console.log(`Generating new email ${emailNumber} for visitor ${visitorId}`);
        
        // Update button to generating state immediately
        this.updateButtonState(visitorId, emailNumber, 'generating');
        
        // Notify user that generation started
        if (this.uiManager) {
            this.uiManager.notify('Email generation started. You\'ll be notified when ready.', 'info');
        }
        
        // Emit event to start polling
        document.dispatchEvent(new CustomEvent('rtr:email-generation-started', {
            detail: { visitorId, emailNumber, room }
        }));
        
        try {
            let baseUrl = this.apiUrl;
            if (baseUrl.includes('/wp-json')) {
                baseUrl = baseUrl.split('/wp-json')[0];
            }
            const apiEndpoint = `${baseUrl}/wp-json/directreach/v2/emails/generate`;
            
            const response = await fetch(apiEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.nonce
                },
                body: JSON.stringify({
                    prospect_id: parseInt(visitorId, 10),
                    room_type: room,
                    email_number: parseInt(emailNumber, 10)
                })
            });
            
            if (!response.ok) {
                const errorText = await response.text();
                console.error('Server response:', errorText);
                throw new Error(`Generation failed: ${response.status} - ${errorText}`);
            }
            
            const data = await response.json();
            
            if (!data.success) {
                throw new Error(data.message || 'Email generation failed');
            }
            
            // Success! Update button to ready state
            this.updateButtonState(visitorId, emailNumber, 'ready');
            
            if (this.uiManager) {
                this.uiManager.notify('Email ready! Click to view.', 'success');
            }
            
            // Emit event for any listeners
            document.dispatchEvent(new CustomEvent('rtr:email-generated', {
                detail: { visitorId, emailNumber, room }
            }));
            
        } catch (error) {
            console.error('Email generation failed:', error);
            
            // Update button to failed state
            this.updateButtonState(visitorId, emailNumber, 'failed');
            
            if (this.uiManager) {
                this.uiManager.notify('Email generation failed. Click to retry.', 'error');
            }
        }
    }

    /**
     * View ready email (ready state)
     * NEW: Dispatch rtr:view-ready-email instead of rtr:openEmailModal
     * @param {String} visitorId - Visitor ID
     * @param {String} room - Room name
     * @param {Number} emailNumber - Email sequence number
     */
    viewReadyEmail(visitorId, room, emailNumber) {
        // Get prospect data to pass name and room
        const prospectCard = document.querySelector(`[data-visitor-id="${visitorId}"]`);
        let prospectName = 'Prospect';
        
        if (prospectCard) {
            // Get name
            const nameElement = prospectCard.querySelector('.rtr-prospect-name');
            if (nameElement) {
                prospectName = nameElement.textContent.trim();
            }
            
            // Get room from the card's parent section
            const roomSection = prospectCard.closest('[data-room]');
            if (roomSection) {
                room = roomSection.getAttribute('data-room');
            }
        }
        
        document.dispatchEvent(new CustomEvent('rtr:view-ready-email', {
            detail: {
                prospectId: visitorId,
                emailNumber: emailNumber,
                prospectName: prospectName,
                room: room
            }
        }));
    }

    /**
     * View email history (sent or opened state)
     * @param {String} visitorId - Visitor ID
     * @param {String} room - Room name
     * @param {Number} emailNumber - Email sequence number
     */
    viewEmailHistory(visitorId, room, emailNumber) {
        console.log(`Viewing email history ${emailNumber} for visitor ${visitorId}`);
        
        // Get prospect name from the card
        const prospectCard = document.querySelector(`[data-visitor-id="${visitorId}"]`);
        let prospectName = 'Prospect';
        
        if (prospectCard) {
            const nameElement = prospectCard.querySelector('.rtr-prospect-name');
            if (nameElement) {
                prospectName = nameElement.textContent.trim();
            }
        }
        
        // Dispatch event to open email history modal
        document.dispatchEvent(new CustomEvent('rtr:openEmailHistory', {
            detail: { 
                visitorId, 
                room,
                emailNumber,
                prospectName
            }
        }));
    }

    /**
     * Update button state in UI
     * @param {String} visitorId - Visitor ID
     * @param {Number} emailNumber - Email sequence number
     * @param {String} newState - New state to set
     * @param {String|null} timestamp - Optional timestamp
     */
    updateButtonState(visitorId, emailNumber, newState, timestamp = null) {
        const button = document.querySelector(
            `.rtr-email-btn[data-visitor-id="${visitorId}"][data-email-number="${emailNumber}"]`
        );
        
        if (!button) {
            console.warn(`Button not found for visitor ${visitorId}, email ${emailNumber}`);
            return;
        }
        
        // Update button attributes
        button.dataset.emailState = newState;
        
        // Update button class
        button.className = `rtr-email-btn ${this.getEmailButtonClass(newState)}`;
        
        // Update icon
        const icon = button.querySelector('i');
        if (icon) {
            icon.className = this.getEmailButtonIcon(newState);
        }
        
        // Update badges
        button.querySelectorAll('.rtr-sent-badge, .rtr-opened-badge').forEach(b => b.remove());
        if (newState === 'sent') {
            button.insertAdjacentHTML('beforeend', '<span class="rtr-sent-badge">✓</span>');
        } else if (newState === 'opened') {
            button.insertAdjacentHTML('beforeend', '<span class="rtr-opened-badge">👁</span>');
        }
        
        // Update tooltip
        button.title = this.getEmailButtonTooltip(newState, emailNumber, timestamp);
        
        // Update disabled state
        if (newState === 'generating') {
            button.disabled = true;
        } else {
            button.disabled = false;
        }
        
        console.log(`Updated button state: visitor ${visitorId}, email ${emailNumber}, state ${newState}`);
    }

    // ... [LINES 372-527 UNCHANGED - All other methods remain the same]

    getLeadScoreColor(score) {
        if (score >= 70) return '#10b981'; // Green
        if (score >= 40) return '#f59e0b'; // Yellow/Orange
        return '#ef4444'; // Red
    }

    formatRelativeTime(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diffMs = now - date;
        const diffMins = Math.floor(diffMs / 60000);
        const diffHours = Math.floor(diffMs / 3600000);
        const diffDays = Math.floor(diffMs / 86400000);

        if (diffMins < 1) return 'Just now';
        if (diffMins < 60) return `${diffMins} min ago`;
        if (diffHours < 24) return `${diffHours}h ago`;
        if (diffDays < 7) return `${diffDays}d ago`;
        
        return date.toLocaleDateString();
    }

    updateRoomBadge(room, count) {
        const badge = document.querySelector(`#rtr-room-${room} .room-count-badge`);
        if (badge) {
            badge.textContent = count;
        }
    }

    handleInfoClick(visitorId, room) {
        document.dispatchEvent(new CustomEvent('rtr:showProspectInfo', {
            detail: { visitorId, room }
        }));
    }

    async handleArchiveClick(visitorId, room) {
        if (!this.uiManager) {
            console.error('UI Manager not set');
            return;
        }

        const confirmed = await this.uiManager.confirmAction(
            'Archive Prospect',
            'Are you sure you want to archive this prospect?',
            'Archive',
            'Cancel'
        );

        if (!confirmed) return;

        // For now, use a default reason. In future, could add custom reason dialog
        const reason = 'Archived by user';

        try {
            const response = await fetch(`${this.apiUrl}/prospects/${visitorId}/archive`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.nonce
                },
                body: JSON.stringify({ reason })
            });

            if (!response.ok) {
                throw new Error('Failed to archive prospect');
            }

            document.dispatchEvent(new CustomEvent('rtr:prospectArchived', {
                detail: { visitorId, room, reason }
            }));

        } catch (error) {
            console.error('Failed to archive prospect:', error);
            if (this.uiManager) {
                this.uiManager.notify('Failed to archive prospect', 'error');
            }
        }
    }

    async handleSalesHandoff(visitorId) {
        if (!this.uiManager) {
            console.error('UI Manager not set');
            return;
        }

        const confirmed = await this.uiManager.confirmAction(
            'Hand off to Sales?',
            'This will move the prospect to the Sales Room.',
            'Confirm',
            'Cancel'
        );

        if (!confirmed) return;

        try {
            const response = await fetch(`${this.apiUrl}/prospects/${visitorId}/handoff`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.nonce
                },
                body: JSON.stringify({ notes: '' })
            });

            if (!response.ok) {
                throw new Error('Failed to hand off to sales');
            }

            document.dispatchEvent(new CustomEvent('rtr:salesHandoff', {
                detail: { visitorId }
            }));

        } catch (error) {
            console.error('Failed to hand off to sales:', error);
            if (this.uiManager) {
                this.uiManager.notify('Failed to hand off prospect', 'error');
            }
        }
    }

    handleEmailHistoryClick(visitorId, room) {
        document.dispatchEvent(new CustomEvent('rtr:openEmailHistory', {
            detail: { visitorId, room }
        }));
    }

    /**
     * Handle score breakdown click
     * Opens the score breakdown modal with detailed scoring criteria
     */
    handleScoreClick(visitorId, clientId, prospectName) {
        console.log(`Opening score breakdown for visitor ${visitorId}, client ${clientId}`);
        
        // Dispatch event to open score breakdown modal
        document.dispatchEvent(new CustomEvent('rtr:openScoreBreakdown', {
            detail: { 
                visitorId, 
                clientId,
                prospectName 
            }
        }));
    }    


    showEnrichmentModal(visitorId, room) {
        
        // Remove existing modal if present
        const existingModal = document.getElementById('enrichment-modal');
        if (existingModal) {
            existingModal.remove();
        }

        // Get prospect data
        const prospect = this.prospects[room]?.find(p => 
            p.visitor_id == visitorId || p.id == visitorId
        );        if (!prospect) return;

        const modal = document.createElement('div');
        modal.className = 'rtr-modal rtr-enrichment-modal active';
        modal.dataset.visitorId = visitorId;
        modal.id = 'enrichment-modal';
        
        modal.innerHTML = `
            <div class="rtr-modal-overlay"></div>
            <div class="rtr-modal-content" style="max-width: 900px; width: 90%;">
                <div class="rtr-modal-header">
                    <h3>
                        <i class="fas fa-user-edit"></i>
                        Update Contact Information
                    </h3>
                    <button class="rtr-modal-close" aria-label="Close">×</button>
                </div>
                <div class="rtr-modal-body">
                    <div class="enrichment-search-section">
                        <p class="enrichment-search-description">
                            <i class="fas fa-search"></i>
                            Search for contacts at <strong>${this.escapeHtml(prospect.company_name)}</strong> using a Leads enrichment service
                        </p>
                        <button id="search-contact-btn" class="btn btn-primary btn-block">
                            <i class="fas fa-building"></i> Search Company Contacts
                        </button>
                    </div>
                    <div class="enrichment-divider">
                        <span>OR MANUALLY ENTER</span>
                    </div>
                    <div id="manual-form-container">
                        ${this.renderManualContactForm(prospect)}
                    </div>
                    <div id="enrichment-results" class="enrichment-results" style="display: none;"></div>
                </div>
                <div class="rtr-modal-footer">
                    <button class="btn btn-secondary close-modal-btn">Cancel</button>
                </div>
            </div>
        `;

        document.body.appendChild(modal);

        // Attach handlers
        this.attachEnrichmentHandlers(modal, visitorId, room, prospect.company_name);

        // Close handlers
        const closeModal = () => {
            modal.classList.remove('active');
            setTimeout(() => modal.remove(), 300);
        };

        modal.querySelector('.rtr-modal-close').onclick = closeModal;
        modal.querySelector('.close-modal-btn').onclick = closeModal;
        modal.querySelector('.rtr-modal-overlay').onclick = closeModal;
    }

    renderManualContactForm(prospect) {
        return `
            <div class="manual-contact-section">
                <h4><i class="fas fa-user-edit"></i> Contact Information</h4>
                <form id="manual-contact-form" class="manual-contact-form">
                    <div class="form-group">
                        <label for="manual-name">Name *</label>
                        <input type="text" id="manual-name" name="contact_name" required 
                               value="${this.escapeHtml(prospect.contact_name || '')}"
                               placeholder="John Doe">
                    </div>
                    <div class="form-group">
                        <label for="manual-email">Email</label>
                        <input type="email" id="manual-email" name="contact_email" 
                               value="${this.escapeHtml(prospect.contact_email || '')}"
                               placeholder="john@example.com">
                    </div>
                    <div class="form-group">
                        <label for="manual-title">Job Title</label>
                        <input type="text" id="manual-title" name="job_title" 
                               value="${this.escapeHtml(prospect.job_title || '')}"
                               placeholder="Marketing Director">
                    </div>
                    <div class="form-group">
                        <label for="manual-company">Company</label>
                        <input type="text" id="manual-company" name="company_name" 
                               value="${this.escapeHtml(prospect.company_name || '')}"
                               placeholder="Acme Corporation">
                    </div>
                    <div class="form-group">
                        <label for="manual-linkedin">LinkedIn Profile</label>
                        <input type="url" id="manual-linkedin" name="linkedin_url" 
                               value="${this.escapeHtml(prospect.linkedin_url || '')}"
                               placeholder="https://linkedin.com/in/johndoe">
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Contact Information
                    </button>
                </form>
            </div>
        `;
    }

    renderContactsList(contacts, visitorId, room) {
        return `
            <div class="contacts-list">
                ${contacts.map(contact => `
                    <div class="contact-card" data-contact='${JSON.stringify(contact).replace(/'/g, '&apos;')}'>
                        <div class="contact-info">
                            <div class="contact-header">
                                <h4 class="contact-name">${this.escapeHtml(contact.name)}</h4>
                                ${contact.seniority ? `<span class="contact-seniority">${this.escapeHtml(contact.seniority)}</span>` : ''}
                            </div>
                            <p class="contact-title">${this.escapeHtml(contact.job_title || 'No title')}</p>
                            <p class="contact-company">${this.escapeHtml(contact.company_name)}</p>
                            ${contact.department ? `<p class="contact-department"><i class="fas fa-building"></i> ${this.escapeHtml(contact.department)}</p>` : ''}
                            ${contact.linkedin ? `<a href="${contact.linkedin}" target="_blank" class="contact-linkedin"><i class="fab fa-linkedin"></i> View LinkedIn</a>` : ''}
                        </div>
                        <div class="contact-actions">
                            ${contact.email ? 
                                `<div class="contact-email-status">
                                    <i class="fas fa-check-circle"></i>
                                    <span>Email: ${this.escapeHtml(contact.email)}</span>
                                </div>` : 
                                `<button class="btn btn-sm btn-secondary find-email-btn" data-member-id="${contact.member_id}">
                                    <i class="fas fa-search"></i> Find Email
                                </button>`
                            }
                            <button class="btn btn-sm btn-primary select-contact-btn">
                                <i class="fas fa-check"></i> Select
                            </button>
                        </div>
                    </div>
                `).join('')}
            </div>
        `;
    }

    attachEnrichmentHandlers(modal, visitorId, room, companyName) {
        // Manual contact form
        const manualForm = modal.querySelector('#manual-contact-form');
        if (manualForm) {
            manualForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                await this.handleManualContactSave(modal, visitorId, room, manualForm);
            });
        }

        // Search button
        const searchBtn = modal.querySelector('#search-contact-btn');
        if (searchBtn) {
            searchBtn.addEventListener('click', async () => {
                await this.handleEnrichmentSearch(modal, companyName, visitorId, room);
            });
        }
    }

    async handleFindEmail(button, visitorId, contactData) {
        const originalHTML = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Finding...';

        try {
            const response = await fetch(`${this.apiUrl}/prospects/${visitorId}/find-email`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.nonce
                },
                body: JSON.stringify({
                    member_id: contactData.member_id,
                    first_name: contactData.first_name,
                    last_name: contactData.last_name,
                    company_domain: contactData.domain
                })
            });

            const data = await response.json();

            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Email not found');
            }

            // Update contact data with email
            contactData.email = data.data.email;

            // Update UI to show found email
            const card = button.closest('.contact-card');
            const actionsDiv = card.querySelector('.contact-actions');
            actionsDiv.innerHTML = `
                <div class="contact-email-status">
                    <i class="fas fa-check-circle"></i>
                    <span>Email: ${this.escapeHtml(data.data.email)}</span>
                </div>
                <button class="btn btn-sm btn-primary select-contact-btn">
                    <i class="fas fa-check"></i> Select
                </button>
            `;

            // Re-attach select handler
            const selectBtn = actionsDiv.querySelector('.select-contact-btn');
            selectBtn.addEventListener('click', async (e) => {
                e.stopPropagation();
                const modal = card.closest('.rtr-enrichment-modal');
                const visitorId = card.closest('[data-visitor-id]')?.dataset.visitorId || visitorId;
                await this.handleSelectContact(modal, visitorId, room, contactData);
            });

            this.uiManager.notify('Email found successfully', 'success');

        } catch (error) {
            console.error('Failed to find email:', error);
            button.disabled = false;
            button.innerHTML = originalHTML;
            this.uiManager.notify(error.message || 'Failed to find email', 'error');
        }
    }

    async handleSelectContact(modal, visitorId, room, contactData) {
        try {
            this.uiManager.showLoader('Saving contact information...');

            const response = await fetch(`${this.apiUrl}/prospects/${visitorId}/save-enrichment`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.nonce
                },
                body: JSON.stringify({
                    contact_name: contactData.name,
                    contact_email: contactData.email || '',
                    job_title: contactData.job_title || '',
                    company_name: contactData.company_name || '',
                    linkedin_url: contactData.linkedin || ''
                })
            });

            const data = await response.json();

            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Failed to save contact');
            }

            this.uiManager.hideLoader();
            this.uiManager.notify('Contact information saved successfully', 'success');
            
            // Close modal
            modal.classList.remove('active');
            setTimeout(() => modal.remove(), 300);
            
            // Reload the room to show updated data
            await this.loadRoomProspects(room);

        } catch (error) {
            console.error('Failed to save contact:', error);
            this.uiManager.hideLoader();
            this.uiManager.notify(error.message || 'Failed to save contact information', 'error');
        }
    }

    async handleManualContactSave(modal, visitorId, room, form) {
        const formData = {
            contact_name: form.querySelector('[name="contact_name"]').value.trim(),
            contact_email: form.querySelector('[name="contact_email"]').value.trim(),
            job_title: form.querySelector('[name="job_title"]').value.trim(),
            company_name: form.querySelector('[name="company_name"]').value.trim(),
            linkedin_url: form.querySelector('[name="linkedin_url"]').value.trim()
        };

        if (!formData.contact_name) {
            this.uiManager.notify('Name is required', 'error');
            return;
        }

        try {
            this.uiManager.showLoader('Saving contact information...');

            const response = await fetch(`${this.apiUrl}/prospects/${visitorId}/save-enrichment`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.nonce
                },
                body: JSON.stringify(formData)
            });

            const data = await response.json();

            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Failed to save contact');
            }

            this.uiManager.hideLoader();
            this.uiManager.notify('Contact information saved successfully', 'success');
            
            // Close modal
            modal.classList.remove('active');
            setTimeout(() => modal.remove(), 300);
            
            // Reload the room to show updated data
            await this.loadRoomProspects(room);

        } catch (error) {
            console.error('Failed to save contact:', error);
            this.uiManager.hideLoader();
            this.uiManager.notify(error.message || 'Failed to save contact information', 'error');
        }
    } 

    async handleEnrichmentSearch(parentModal, companyName, visitorId, room) {
        try {
            // Show loading state
            const searchBtn = parentModal.querySelector('#search-contact-btn');
            const resultsDiv = parentModal.querySelector('#enrichment-results');
            const originalBtnText = searchBtn.innerHTML;
            searchBtn.disabled = true;
            searchBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Searching...';

            // Call API to search contacts
            const response = await fetch(`${this.apiUrl}/prospects/${visitorId}/search-contacts`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.nonce
                },
                body: JSON.stringify({
                    company_name: companyName
                })
            });

            const data = await response.json();

            // Reset button
            searchBtn.disabled = false;
            searchBtn.innerHTML = originalBtnText;

            if (!response.ok) {
                throw new Error(data.message || 'Failed to search for contacts');
            }

            // Display results
            const contacts = data.data?.contacts || [];
            const formContainer = parentModal.querySelector('#manual-form-container');
            const divider = parentModal.querySelector('.enrichment-divider');
            
            if (contacts.length === 0) {
                resultsDiv.style.display = 'block';
                resultsDiv.innerHTML = `
                    <div class="no-results-message">
                        <i class="fas fa-info-circle"></i>
                        <p>No contacts found at this company. Please use the manual form above.</p>
                    </div>
                `;
            } else {
                // Hide form and divider, show results
                formContainer.style.display = 'none';
                divider.style.display = 'none';
                resultsDiv.style.display = 'block';
                resultsDiv.innerHTML = this.renderContactsList(contacts, visitorId, room);
                
                // Re-attach handlers for the new contact cards
                this.attachContactCardHandlers(parentModal, visitorId, room);
            }

        } catch (error) {
            console.error('Failed to search contacts:', error);
            const searchBtn = parentModal.querySelector('#search-contact-btn');
            searchBtn.disabled = false;
            searchBtn.innerHTML = '<i class="fas fa-building"></i> Search Company Contacts';
            this.uiManager.notify(error.message || 'Failed to search for contacts', 'error');
        }
    }

    attachContactCardHandlers(modal, visitorId, room) {
        // Find Email buttons
        modal.querySelectorAll('.find-email-btn').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                e.stopPropagation();
                const card = btn.closest('.contact-card');
                const contactData = JSON.parse(card.dataset.contact);
                await this.handleFindEmail(btn, visitorId, contactData);
            });
        });

        // Select Contact buttons
        modal.querySelectorAll('.select-contact-btn').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                e.stopPropagation();
                const card = btn.closest('.contact-card');
                const contactData = JSON.parse(card.dataset.contact);
                await this.handleSelectContact(modal, visitorId, room, contactData);
            });
        });
    }

    showContactSelectorModal(contacts, parentModal, visitorId) {
        // Filter contacts with valid business emails (backend already filters, but double-check)
        const validContacts = contacts.filter(c => c.email && c.email.includes('@'));

        if (validContacts.length === 0) {
            if (this.uiManager) {
                this.uiManager.notify('No contacts with valid business emails found', 'warning');
            }
            return;
        }

        // Create selector modal HTML
        const selectorHtml = `
            <div class="rtr-contact-selector-modal">
                <div class="selector-header">
                    <h4>Select Contact</h4>
                    <button class="selector-close">&times;</button>
                </div>
                <div class="selector-info">
                    Found ${validContacts.length} contact${validContacts.length > 1 ? 's' : ''} with valid business email addresses
                </div>
                <div class="contacts-list">
                    ${validContacts.map((contact, index) => `
                        <div class="contact-item" data-index="${index}">
                            <div class="contact-main">
                                <div class="contact-info">
                                    <div class="contact-name">${this.escapeHtml(contact.name)}</div>
                                    <div class="contact-title">${this.escapeHtml(contact.job_title || 'No title available')}</div>
                                    ${contact.department ? `<div class="contact-department"><i class="fas fa-building"></i> ${this.escapeHtml(contact.department)}</div>` : ''}
                                    ${contact.seniority ? `<div class="contact-seniority"><i class="fas fa-user-tie"></i> ${this.escapeHtml(contact.seniority)}</div>` : ''}
                                    <div class="contact-email">
                                        <i class="fas fa-envelope"></i> ${this.escapeHtml(contact.email)}
                                    </div>
                                    ${contact.linkedin ? `
                                        <div class="contact-linkedin">
                                            <i class="fab fa-linkedin"></i> 
                                            <a href="${this.escapeHtml(contact.linkedin)}" target="_blank" rel="noopener">LinkedIn Profile</a>
                                        </div>
                                    ` : ''}
                                </div>
                                <div class="contact-actions">
                                    <button class="btn btn-select" data-index="${index}">
                                        Select
                                    </button>
                                </div>
                            </div>
                        </div>
                    `).join('')}
                </div>
            </div>
        `;

        // Create selector overlay
        const selectorOverlay = document.createElement('div');
        selectorOverlay.className = 'rtr-modal-overlay rtr-selector-overlay active';
        selectorOverlay.innerHTML = selectorHtml;
        document.body.appendChild(selectorOverlay);

        // Handle contact selection
        const selectBtns = selectorOverlay.querySelectorAll('.btn-select');
        selectBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const index = parseInt(btn.dataset.index);
                const selectedContact = validContacts[index];
                this.applySelectedContact(selectedContact, parentModal);
                
                // Close selector modal
                selectorOverlay.classList.remove('active');
                setTimeout(() => selectorOverlay.remove(), 300);
            });
        });

        // Handle close button
        const closeBtn = selectorOverlay.querySelector('.selector-close');
        closeBtn.addEventListener('click', () => {
            selectorOverlay.classList.remove('active');
            setTimeout(() => selectorOverlay.remove(), 300);
        });

        // Close on overlay click
        selectorOverlay.addEventListener('click', (e) => {
            if (e.target === selectorOverlay) {
                selectorOverlay.classList.remove('active');
                setTimeout(() => selectorOverlay.remove(), 300);
            }
        });
    }

    applySelectedContact(contact, parentModal) {
        // Auto-fill the form fields
        const nameInput = parentModal.querySelector('#contact-name');
        const emailInput = parentModal.querySelector('#contact-email');
        const titleInput = parentModal.querySelector('#job-title');

        if (nameInput) nameInput.value = contact.name;
        if (emailInput) emailInput.value = contact.email;
        if (titleInput) titleInput.value = contact.job_title;

        // Show success notification
        if (this.uiManager) {
            this.uiManager.notify('Contact information filled. Review and save.', 'success');
        }

        // Highlight the fields briefly
        [nameInput, emailInput, titleInput].forEach(input => {
            if (input) {
                input.style.transition = 'background-color 0.3s ease';
                input.style.backgroundColor = '#dbeafe';
                setTimeout(() => {
                    input.style.backgroundColor = '';
                }, 2000);
            }
        });
    }    

    removeProspect(visitorId, room) {
        const card = document.querySelector(`#rtr-room-${room} .rtr-prospect-row[data-prospect-id="${visitorId}"]`);
        if (card) {
            card.style.transition = 'all 0.3s ease';
            card.style.opacity = '0';
            card.style.transform = 'translateX(-20px)';
            setTimeout(() => card.remove(), 300);
        }

        if (this.prospects[room]) {
            this.prospects[room] = this.prospects[room].filter(p => p.id != visitorId);
            this.updateRoomBadge(room, this.prospects[room].length);
        }
    }

    updateProspectEmailStatus(visitorId, room) {
        this.loadRoomProspects(room);
    }

    /**
     * NEW: Update prospect email buttons based on state changes
     * @param {String} visitorId - Visitor ID
     * @param {Array} emailStates - Array of email state objects
     */
    updateProspectEmailButtons(visitorId, emailStates) {
        if (!emailStates || !Array.isArray(emailStates)) {
            console.warn('Invalid email states provided');
            return;
        }

        emailStates.forEach(emailState => {
            if (emailState.status) {
                this.updateButtonState(
                    visitorId, 
                    emailState.email_number, 
                    emailState.status
                );
            }
        });
    }

    /**
     * NEW: Find prospect by ID across all rooms
     * @param {String} visitorId - Visitor ID to search for
     * @returns {Object|null} Prospect object or null if not found
     */
    findProspectById(visitorId) {
        for (const room of ['problem', 'solution', 'offer']) {
            if (this.prospects[room]) {
                const prospect = this.prospects[room].find(p => p.visitor_id == visitorId);
                if (prospect) {
                    return prospect;
                }
            }
        }
        return null;
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}