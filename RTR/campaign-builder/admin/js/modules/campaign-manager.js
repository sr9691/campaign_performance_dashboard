/**
 * Campaign Manager Module
 * 
 * Handles campaign selection, creation, and UTM validation
 * 
 * @package DirectReach_Campaign_Builder
 * @since 2.0.0
 */

import EventEmitter from '../utils/event-emitter.js';
import APIClient from '../utils/api-client.js';

export default class CampaignManager extends EventEmitter {
    /**
     * Constructor
     * 
     * @param {Object} config - Configuration object
     */
    constructor(config, stateManager) {
        super();
        
        this.config = config;
        this.stateManager = stateManager;
        this.api = new APIClient(config.apiUrl, config.nonce);
        
        this.campaigns = [];
        this.selectedCampaign = null;
        this.selectedClientId = null;
        this.selectedClientName = null;
        this.editingCampaignId = null;
        this.isLoading = false;
        this.utmValidationTimeout = null;
        
        this.elements = {
            stepContainer: document.getElementById('campaign-step'),
            listContainer: document.getElementById('campaign-list-container'),
            formContainer: document.getElementById('campaign-form-container'),
            settingsPreview: document.getElementById('settings-preview-container'),
            campaignList: document.getElementById('campaign-list'),
            createNewBtn: document.getElementById('create-new-campaign-btn'),
            form: document.getElementById('campaign-form'),
            nameInput: document.getElementById('campaign_name'),
            utmInput: document.getElementById('utm_campaign'),
            startDateInput: document.getElementById('start_date'),
            endDateInput: document.getElementById('end_date'),
            descriptionInput: document.getElementById('campaign_description'),
            saveBtn: document.getElementById('save-campaign-btn'),
            cancelBtn: document.getElementById('cancel-campaign-btn'),
            utmValidation: document.querySelector('.utm-validation .validation-message'),
            settingsSourceText: document.getElementById('settings-source-text'),
            customizeLink: document.getElementById('customize-settings-link'),
            thresholdsPreview: document.getElementById('thresholds-preview'),
            scoringPreview: document.getElementById('scoring-preview'),
            clientNameDisplay: document.querySelector('.selected-client-name'),
            loadingState: document.getElementById('campaigns-loading'),
            emptyState: document.getElementById('campaigns-empty'),
            errorState: document.getElementById('campaigns-error')
        };
        
        this.init();
    }
    
    /**
     * Debounce utility
     */
    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
    
    /**
     * Initialize the campaign manager
     */
    init() {
        this.attachEventListeners();
    }
    
    /**
     * Attach event listeners
     */
    attachEventListeners() {
        // Create new campaign button
        if (this.elements.createNewBtn) {
            this.elements.createNewBtn.addEventListener('click', () => this.showCreateForm());
        }
        
        // Form submission
        if (this.elements.form) {
            this.elements.form.addEventListener('submit', (e) => this.handleFormSubmit(e));
        }
        
        // Cancel button
        if (this.elements.cancelBtn) {
            this.elements.cancelBtn.addEventListener('click', () => this.handleCancelForm());
        }
        
        // UTM validation (debounced)
        if (this.elements.utmInput) {
            this.elements.utmInput.addEventListener('input', 
                this.debounce(() => this.validateUTM(), 500)
            );
            this.elements.utmInput.addEventListener('blur', () => this.validateUTM());
        }
        
        // Customize settings link
        if (this.elements.customizeLink) {
            this.elements.customizeLink.addEventListener('click', (e) => {
                e.preventDefault();
                this.openClientSettings();
            });
        }
    }
    
    /**
     * Load campaigns for a client
     * 
     * @param {number} clientId - Client ID
     * @param {string} clientName - Client name
     */
    async loadCampaigns(clientId) {
        // Use provided clientId or get from state
        const targetClientId = clientId 
        || this.selectedClientId 
        || (this.stateManager ? this.stateManager.getState().clientId : null);
        
        if (!targetClientId) {
            console.warn('No client selected, cannot load campaigns');
            this.campaigns = [];
            this.renderCampaigns();
            return this.campaigns;
        }

        // Store the clientId for form submissions
        this.selectedClientId = targetClientId;

        // Update client name display
        if (this.stateManager) {
            const state = this.stateManager.getState();
            
            // Also store client name
            this.selectedClientName = state.clientName || '';
            
            if (state.clientName && this.elements.clientNameDisplay) {
                this.elements.clientNameDisplay.textContent = state.clientName;
            }
        }       
        
        this.showLoadingState();
        this.isLoading = true;
        
        try {
            const response = await this.api.get(`/campaigns?client_id=${targetClientId}`);
            
            if (response.success) {
                this.campaigns = response.data || [];
                this.renderCampaigns();
                this.emit('campaigns:loaded', this.campaigns);
                
                // Show appropriate view
                if (this.campaigns.length > 0) {
                    this.showCampaignsList();
                } else {
                    this.showCreateForm();
                }
            } else {
                throw new Error(response.message || 'Failed to load campaigns');
            }
        } catch (error) {
            console.error('Failed to load campaigns:', error);
            this.showErrorState(error.message);
            this.emit('campaigns:error', error);
        } finally {
            this.isLoading = false;
        }
        
        return this.campaigns;
    }
    
    
    /**
     * Show loading state
     */
    showLoadingState() {
        if (this.elements.loadingState) {
            this.elements.loadingState.style.display = 'flex';
        }
        if (this.elements.listContainer) {
            this.elements.listContainer.style.display = 'none';
        }
        if (this.elements.formContainer) {
            this.elements.formContainer.style.display = 'none';
        }
        if (this.elements.errorState) {
            this.elements.errorState.style.display = 'none';
        }
    }
    
    /**
     * Show error state
     */
    showErrorState(message) {
        if (this.elements.errorState) {
            this.elements.errorState.style.display = 'flex';
            const errorMessage = this.elements.errorState.querySelector('.error-message');
            if (errorMessage) {
                errorMessage.textContent = message;
            }
        }
        if (this.elements.loadingState) {
            this.elements.loadingState.style.display = 'none';
        }
        if (this.elements.listContainer) {
            this.elements.listContainer.style.display = 'none';
        }
        if (this.elements.formContainer) {
            this.elements.formContainer.style.display = 'none';
        }
    }
    
    /**
     * Show campaigns list
     */
    showCampaignsList() {
        if (this.elements.listContainer) {
            this.elements.listContainer.style.display = 'block';
        }
        if (this.elements.formContainer) {
            this.elements.formContainer.style.display = 'none';
        }
        if (this.elements.loadingState) {
            this.elements.loadingState.style.display = 'none';
        }
        if (this.elements.errorState) {
            this.elements.errorState.style.display = 'none';
        }
    }
    
    /**
     * Render campaigns list
     */
    renderCampaigns() {
        if (!this.elements.campaignList) return;
        
        if (this.campaigns.length === 0) {
            this.elements.campaignList.innerHTML = '<p class="no-campaigns">No campaigns yet. Create your first campaign!</p>';
            return;
        }
        
        this.elements.campaignList.innerHTML = this.campaigns.map(campaign => this.renderCampaignCard(campaign)).join('');
        
        // Attach click handlers to campaign cards
        this.attachCampaignClickHandlers();
    }
    
    /**
     * Attach click handlers to campaign cards
     */
    attachCampaignClickHandlers() {
        const cards = this.elements.campaignList.querySelectorAll('.campaign-card');
        
        cards.forEach(card => {
            card.addEventListener('click', (e) => {
                if (!e.target.closest('.campaign-actions')) {
                    const campaignId = parseInt(card.dataset.campaignId);
                    this.selectCampaign(campaignId);
                }
            });
        });
        
        // Attach edit handlers
        this.elements.campaignList.querySelectorAll('.edit-campaign-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const campaignId = parseInt(btn.closest('.campaign-card').dataset.campaignId);
                this.editCampaign(campaignId);
            });
        });
        
        // Attach delete handlers
        this.elements.campaignList.querySelectorAll('.delete-campaign-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const campaignId = parseInt(btn.closest('.campaign-card').dataset.campaignId);
                this.deleteCampaign(campaignId);
            });
        });
    }
    
    /**
     * Render a single campaign card
     * 
     * @param {Object} campaign - Campaign object
     * @return {string} HTML string
     */
    renderCampaignCard(campaign) {
        const isSelected = this.selectedCampaign && this.selectedCampaign.id === campaign.id;
        const selectedClass = isSelected ? 'selected' : '';
        
        const dateRange = this.formatDateRange(campaign.start_date, campaign.end_date);
        const settingsSource = campaign.settings.source_name;
        
        return `
            <div class="campaign-card ${selectedClass}" data-campaign-id="${campaign.id}">
                <div class="campaign-header">
                    <h4 class="campaign-name">${this.escapeHtml(campaign.campaign_name)}</h4>
                    <div class="campaign-actions">
                        <button type="button" class="btn-icon edit-campaign-btn" title="Edit campaign">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button type="button" class="btn-icon delete-campaign-btn" title="Delete campaign">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
                <div class="campaign-body">
                    <div class="campaign-utm">
                        <span class="label">UTM:</span>
                        <code>${campaign.utm_campaign}</code>
                    </div>
                    ${campaign.campaign_description ? `
                        <div class="campaign-description">
                            ${this.escapeHtml(campaign.campaign_description)}
                        </div>
                    ` : ''}
                    ${dateRange ? `
                        <div class="campaign-dates">
                            <i class="fas fa-calendar"></i> ${dateRange}
                        </div>
                    ` : ''}
                    <div class="campaign-settings">
                        <i class="fas fa-cog"></i>
                        <span class="settings-source">Using ${settingsSource}</span>
                    </div>
                </div>
                ${isSelected ? '<div class="selected-indicator"><i class="fas fa-check-circle"></i> Selected</div>' : ''}
            </div>
        `;
    }
    
    /**
     * Format date range for display
     * 
     * @param {string} startDate - Start date
     * @param {string} endDate - End date
     * @return {string} Formatted date range
     */
    formatDateRange(startDate, endDate) {
        if (!startDate && !endDate) return '';
        
        const formatDate = (dateStr) => {
            if (!dateStr) return '';
            const date = new Date(dateStr);
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        };
        
        if (startDate && endDate) {
            return `${formatDate(startDate)} – ${formatDate(endDate)}`;
        } else if (startDate) {
            return `Starting ${formatDate(startDate)}`;
        } else {
            return `Ending ${formatDate(endDate)}`;
        }
    }
    
    /**
     * Select a campaign
     * 
     * @param {number} campaignId - Campaign ID
     */
    selectCampaign(campaignId) {
        const campaign = this.campaigns.find(c => c.id === campaignId);
        if (!campaign) return;
        
        this.selectedCampaign = campaign;
        this.renderCampaigns(); // Re-render to show selection
        this.renderSettingsPreview(campaign.settings);
        
        // UPDATE STATE with campaign data
        if (this.stateManager) {
            this.stateManager.updateState({
                campaignId: campaign.id,
                campaignName: campaign.campaign_name,
                utmCampaign: campaign.utm_campaign
            });
        }
        
        // Emit selection event
        this.emit('campaign:selected', campaign);
        
        // Show settings preview
        if (this.elements.settingsPreview) {
            this.elements.settingsPreview.style.display = 'block';
        }
    }
    
    /**
     * Show create campaign form
     */
    showCreateForm() {
        this.editingCampaignId = null;
        this.resetForm();
        
        if (this.elements.listContainer) {
            this.elements.listContainer.style.display = 'none';
        }
        if (this.elements.formContainer) {
            this.elements.formContainer.style.display = 'block';
        }
        if (this.elements.settingsPreview) {
            this.elements.settingsPreview.style.display = 'none';
        }
        if (this.elements.loadingState) {
            this.elements.loadingState.style.display = 'none';
        }
        if (this.elements.errorState) {
            this.elements.errorState.style.display = 'none';
        }
        
        // Focus on campaign name input
        if (this.elements.nameInput) {
            setTimeout(() => {
                this.elements.nameInput.focus();
            }, 100);
        }
    }
    
    /**
     * Edit existing campaign
     * 
     * @param {number} campaignId - Campaign ID
     */
    editCampaign(campaignId) {
        const campaign = this.campaigns.find(c => c.id === campaignId);
        if (!campaign) return;
        
        this.editingCampaignId = campaignId;
        
        // Populate form
        if (this.elements.nameInput) this.elements.nameInput.value = campaign.campaign_name || '';
        if (this.elements.utmInput) this.elements.utmInput.value = campaign.utm_campaign || '';
        if (this.elements.startDateInput) this.elements.startDateInput.value = campaign.start_date || '';
        if (this.elements.endDateInput) this.elements.endDateInput.value = campaign.end_date || '';
        if (this.elements.descriptionInput) this.elements.descriptionInput.value = campaign.campaign_description || '';
        
        // UPDATE STATE with campaign data for validation
        if (this.stateManager) {
            this.stateManager.updateState({
                campaignId: campaign.id,
                campaignName: campaign.campaign_name,
                utmCampaign: campaign.utm_campaign,
                campaignDescription: campaign.campaign_description,
                startDate: campaign.start_date,
                endDate: campaign.end_date
            });
        }
        
        // Show form
        if (this.elements.listContainer) {
            this.elements.listContainer.style.display = 'none';
        }
        if (this.elements.formContainer) {
            this.elements.formContainer.style.display = 'block';
        }
        
        // Render settings preview
        this.renderSettingsPreview(campaign.settings);
        if (this.elements.settingsPreview) {
            this.elements.settingsPreview.style.display = 'block';
        }
    }
    
    /**
     * Delete campaign
     * 
     * @param {number} campaignId - Campaign ID
     */
    async deleteCampaign(campaignId) {
        const campaign = this.campaigns.find(c => c.id === campaignId);
        if (!campaign) return;
        
        const confirmed = confirm(`Are you sure you want to delete "${campaign.campaign_name}"?`);
        if (!confirmed) return;
        
        try {
            await this.api.delete(`/campaigns/${campaignId}`);
            
            // Remove from local array
            this.campaigns = this.campaigns.filter(c => c.id !== campaignId);
            
            // If deleted campaign was selected, clear selection
            if (this.selectedCampaign && this.selectedCampaign.id === campaignId) {
                this.selectedCampaign = null;
                this.emit('campaign:deselected');
            }
            
            // Re-render
            this.renderCampaigns();
            
            // Show form if no campaigns left
            if (this.campaigns.length === 0) {
                this.showCreateForm();
            }
            
            this.emit('notification', {
                type: 'success',
                message: 'Campaign deleted successfully'
            });
        } catch (error) {
            console.error('Failed to delete campaign:', error);
            this.emit('notification', {
                type: 'error',
                message: error.message || 'Failed to delete campaign'
            });
        }
    }
    
    /**
     * Handle form submission
     * 
     * @param {Event} e - Submit event
     */
    async handleFormSubmit(e) {
        e.preventDefault();
        
        const submitBtn = this.elements.saveBtn;
        const originalText = submitBtn.innerHTML;
        
        // Disable submit button
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        
        const today = new Date().toISOString().split('T')[0];
        const tenYearsFromToday = new Date();
        tenYearsFromToday.setFullYear(tenYearsFromToday.getFullYear() + 10);

        const formData = {
        client_id: this.selectedClientId,
        campaign_name: this.elements.nameInput.value.trim(),
        utm_campaign: this.elements.utmInput.value.trim().toLowerCase(),
        campaign_description: this.elements.descriptionInput.value.trim(),
        start_date: this.elements.startDateInput.value || today,
        end_date: this.elements.endDateInput.value || tenYearsFromToday.toISOString().split('T')[0]
        };

        
        try {
            let response;
            console.log('Submitting campaign data:', formData);
            if (this.editingCampaignId) {
                // Update existing campaign
                response = await this.api.put(`/campaigns/${this.editingCampaignId}`, formData);
            } else {
                // Create new campaign
                response = await this.api.post('/campaigns', formData);
            }
            
            if (response.success) {
                const campaign = response.data;
                
                if (this.editingCampaignId) {
                    // Update in local array
                    const index = this.campaigns.findIndex(c => c.id === this.editingCampaignId);
                    if (index !== -1) {
                        this.campaigns[index] = campaign;
                    }
                    this.emit('campaign:updated', campaign);
                } else {
                    // Add to local array
                    this.campaigns.push(campaign);
                    this.emit('campaign:created', campaign);
                }
                
                // Auto-select the saved campaign
                this.selectedCampaign = campaign;
                
                // Emit events
                this.emit('campaign:selected', campaign);
                this.emit('notification', {
                    type: 'success',
                    message: response.message || 'Campaign saved successfully'
                });
                
                // Show campaign list
                this.renderCampaigns();
                this.showCampaignsList();
                this.renderSettingsPreview(campaign.settings);
                if (this.elements.settingsPreview) {
                    this.elements.settingsPreview.style.display = 'block';
                }
            } else {
                throw new Error(response.message || 'Failed to save campaign');
            }
        } catch (error) {
            console.error('Failed to save campaign:', error);
            this.emit('notification', {
                type: 'error',
                message: error.message || 'Failed to save campaign'
            });
        } finally {
            // Re-enable submit button
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    }
    
    /**
     * Handle cancel form
     */
    handleCancelForm() {
        this.resetForm();
        this.editingCampaignId = null;
        
        if (this.campaigns.length > 0) {
            this.showCampaignsList();
            
            // Show settings preview if campaign selected
            if (this.selectedCampaign && this.elements.settingsPreview) {
                this.renderSettingsPreview(this.selectedCampaign.settings);
                this.elements.settingsPreview.style.display = 'block';
            }
        } else {
            // No campaigns, stay on form but reset it
            if (this.elements.nameInput) {
                setTimeout(() => {
                    this.elements.nameInput.focus();
                }, 100);
            }
        }
    }
    
    /**
     * Reset form
     */
    resetForm() {
        if (this.elements.form) {
            this.elements.form.reset();
        }
        if (this.elements.utmValidation) {
            this.elements.utmValidation.textContent = '';
            this.elements.utmValidation.className = 'validation-message';
        }
    }
    
    /**
     * Validate UTM campaign format and uniqueness
     */
    async validateUTM() {
        const utm = this.elements.utmInput.value.trim().toLowerCase();
        
        if (!utm) {
            this.setUTMValidation('', '');
            return;
        }
        
        // Format validation
        const formatRegex = /^[a-z0-9_-]+$/;
        if (!formatRegex.test(utm)) {
            this.setUTMValidation('error', 'Use lowercase letters, numbers, hyphens and underscores only');
            return;
        }
        
        if (utm.length < 3) {
            this.setUTMValidation('error', 'UTM must be at least 3 characters');
            return;
        }
        
        // Check uniqueness (skip if editing the same campaign)
        const existingCampaign = this.campaigns.find(c => 
            c.utm_campaign === utm && 
            c.id !== this.editingCampaignId
        );
        
        if (existingCampaign) {
            this.setUTMValidation('error', `UTM "${utm}" already used by "${existingCampaign.campaign_name}"`);
            return;
        }
        
        // Valid
        this.setUTMValidation('success', '✓ Available');
    }
    
    /**
     * Set UTM validation message
     * 
     * @param {string} type - 'success' or 'error' or ''
     * @param {string} message - Validation message
     */
    setUTMValidation(type, message) {
        if (!this.elements.utmValidation) return;
        
        this.elements.utmValidation.textContent = message;
        this.elements.utmValidation.className = `validation-message ${type}`;
    }
    
    /**
     * Render settings preview
     * 
     * @param {Object} settings - Campaign settings object
     */
    renderSettingsPreview(settings) {
        if (!settings) return;
        
        // Update source text
        if (this.elements.settingsSourceText) {
            const sourceText = settings.source === 'client' 
                ? `Using ${settings.source_name} Settings`
                : 'Using Global Defaults';
            this.elements.settingsSourceText.textContent = sourceText;
        }
        
        // Show/hide customize link (only show if using global defaults)
        if (this.elements.customizeLink) {
            this.elements.customizeLink.style.display = settings.source === 'global' ? 'inline-block' : 'none';
        }
        
        // Render thresholds
        if (this.elements.thresholdsPreview && settings.room_thresholds) {
            this.elements.thresholdsPreview.innerHTML = this.renderThresholds(settings.room_thresholds);
        }
        
        // Render scoring rules
        if (this.elements.scoringPreview && settings.scoring_rules) {
            this.elements.scoringPreview.innerHTML = this.renderScoringRules(settings.scoring_rules);
        }
    }
    
    /**
     * Render room thresholds HTML
     * 
     * @param {Object} thresholds - Room thresholds object
     * @return {string} HTML string
     */
    renderThresholds(thresholds) {
        if (!thresholds || Object.keys(thresholds).length === 0) {
            return '<p class="empty-state">No thresholds configured</p>';
        }
        
        let html = '<div class="thresholds-list">';
        
        if (thresholds.problem_to_solution) {
            const pts = thresholds.problem_to_solution;
            html += `
                <div class="threshold-item">
                    <strong>Problem → Solution:</strong>
                    Min Score: ${pts.min_score}, 
                    Min Days: ${pts.min_days}
                </div>
            `;
        }
        
        if (thresholds.solution_to_offer) {
            const sto = thresholds.solution_to_offer;
            html += `
                <div class="threshold-item">
                    <strong>Solution → Offer:</strong>
                    Min Score: ${sto.min_score}, 
                    Min Days: ${sto.min_days}
                    ${sto.manual_approval_required ? ' (Manual Approval)' : ''}
                </div>
            `;
        }
        
        html += '</div>';
        return html;
    }
    
    /**
     * Render scoring rules HTML
     * 
     * @param {Object} scoring - Scoring rules object
     * @return {string} HTML string
     */
    renderScoringRules(scoring) {
        if (!scoring || Object.keys(scoring).length === 0) {
            return '<p class="empty-state">No scoring rules configured</p>';
        }
        
        let html = '<div class="scoring-list">';
        
        if (scoring.firmographic) {
            html += '<div class="scoring-category"><strong>Firmographic:</strong> Configured</div>';
        }
        
        if (scoring.engagement) {
            html += '<div class="scoring-category"><strong>Engagement:</strong> Configured</div>';
        }
        
        if (scoring.time_decay) {
            html += '<div class="scoring-category"><strong>Time Decay:</strong> ' + 
                    (scoring.time_decay.enabled ? 'Enabled' : 'Disabled') + '</div>';
        }
        
        html += '</div>';
        return html;
    }
    
    /**
     * Open client settings customization
     */
    openClientSettings() {
        // Navigate to client management page
        const url = `admin.php?page=cpd-dashboard&tab=management&client_id=${this.selectedClientId}`;
        window.open(url, '_blank');
    }
    
    /**
     * Escape HTML
     * 
     * @param {string} text - Text to escape
     * @return {string} Escaped text
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    /**
     * Get selected campaign
     * 
     * @return {Object|null} Selected campaign or null
     */
    getSelectedCampaign() {
        return this.selectedCampaign;
    }
    
    /**
     * Clear selection
     */
    clearSelection() {
        this.selectedCampaign = null;
        this.renderCampaigns();
        this.emit('campaign:deselected');
    }
}