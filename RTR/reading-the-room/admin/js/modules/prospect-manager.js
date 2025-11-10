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
                this.handleEditContact(visitorId, room);
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

    async handleEditContact(visitorId, room) {
        if (!this.uiManager) {
            console.error('UI Manager not set');
            return;
        }

        // Create modal HTML
        const modalHtml = `
            <div class="rtr-contact-edit-modal">
                <h4>Add Contact Information</h4>
                <form id="contact-edit-form">
                    <div class="form-group">
                        <label for="contact-name">Name *</label>
                        <input type="text" id="contact-name" name="contact_name" required 
                               placeholder="John Doe" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="contact-email">Email</label>
                        <input type="email" id="contact-email" name="contact_email" 
                               placeholder="john@example.com" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="job-title">Job Title</label>
                        <input type="text" id="job-title" name="job_title" 
                               placeholder="Marketing Director" class="form-control">
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-cancel">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Contact</button>
                    </div>
                </form>
            </div>
        `;

        // Create modal container
        const modal = document.createElement('div');
        modal.className = 'rtr-modal-overlay active';
        modal.innerHTML = modalHtml;
        document.body.appendChild(modal);

        // Handle form submission
        const form = modal.querySelector('#contact-edit-form');
        const cancelBtn = modal.querySelector('.btn-cancel');

        const closeModal = () => {
            modal.classList.remove('active');
            setTimeout(() => modal.remove(), 300);
        };

        cancelBtn.onclick = closeModal;
        modal.onclick = (e) => {
            if (e.target === modal) closeModal();
        };

        form.onsubmit = async (e) => {
            e.preventDefault();
            
            const formData = {
                contact_name: form.querySelector('#contact-name').value.trim(),
                contact_email: form.querySelector('#contact-email').value.trim(),
                job_title: form.querySelector('#job-title').value.trim()
            };

            if (!formData.contact_name) {
                this.uiManager.notify('Name is required', 'error');
                return;
            }

            try {
                this.uiManager.showLoader('Saving contact information...');

                const response = await fetch(`${this.apiUrl}/prospects/${visitorId}/update-contact`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': this.nonce
                    },
                    body: JSON.stringify(formData)
                });

                const data = await response.json();

                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'Failed to update contact');
                }

                this.uiManager.notify('Contact information saved successfully', 'success');
                closeModal();
                
                // Reload the room to show updated data
                await this.loadRoomProspects(room);

            } catch (error) {
                console.error('Failed to update contact:', error);
                this.uiManager.notify(error.message || 'Failed to save contact information', 'error');
            } finally {
                this.uiManager.hideLoader();
            }
        };

        // Focus on name field
        setTimeout(() => {
            modal.querySelector('#contact-name').focus();
        }, 100);
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