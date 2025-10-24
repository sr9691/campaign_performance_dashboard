/**
 * Workflow Module - COMPLETE WITH CONTENT LINKS
 * Manages 4-step workflow navigation and validation
 * 
 * @package DirectReach_Campaign_Builder
 * @since 2.0.0
 */

export default class WorkflowManager {
    constructor(config, stateManager, clientManager, campaignManager, contentLinksManager, templateManager) {
        this.config = config;
        this.stateManager = stateManager;
        this.clientManager = clientManager;
        this.campaignManager = campaignManager;
        this.contentLinksManager = contentLinksManager;
        this.templateManager = templateManager;
        this.steps = ['client', 'campaign', 'content-links', 'templates'];
        this.currentStep = 'client';
        this.listeners = {};
    }
    

    /**
     * Initialize workflow
     */
    async init() {
        try {
            // Load saved workflow state
            await this.loadState();
            
            // Show initial step
            this.renderCurrentStep();
            
            // Update breadcrumb on init
            this.updateBreadcrumbClasses();
            
            // Setup navigation listeners
            this.setupNavigation();
            this.setupBreadcrumbNavigation();
            
            this.emit('workflow:initialized');
        } catch (error) {
            console.error('Workflow initialization failed:', error);
            // Still initialize even if state loading fails
            this.currentStep = 'client';
            this.renderCurrentStep();
            this.setupNavigation();
            this.setupBreadcrumbNavigation();
            this.emit('workflow:error', error);
        }
    }
    
    /**
     * Load workflow state
     */
    async loadState() {
        try {
            const state = await this.stateManager.loadFromDatabase();
            
            console.log('Loaded state from DB:', state); 
            this.currentStep = 'client';
            
            // CRITICAL: Validate the step
            if (!state.currentStep || !this.steps.includes(state.currentStep)) {
                console.warn('Invalid or missing currentStep, resetting to client');
                this.currentStep = 'client';
                await this.stateManager.updateState({ currentStep: 'client' });
            } else {
                this.currentStep = state.currentStep;
            }
            
            // Verify step has required data
            if (this.currentStep === 'campaign' && !state.clientId) {
                console.warn('On campaign step but no client selected, going back to client step');
                this.currentStep = 'client';
                await this.stateManager.updateState({ currentStep: 'client' });
            }
            
            if (this.currentStep === 'content-links' && !state.campaignId) {
                console.warn('On content-links step but no campaign selected, going back to campaign step');
                this.currentStep = 'campaign';
                await this.stateManager.updateState({ currentStep: 'campaign' });
            }
            
            if (this.currentStep === 'templates' && !state.campaignId) {
                console.warn('On templates step but no campaign selected, going back to campaign step');
                this.currentStep = 'campaign';
                await this.stateManager.updateState({ currentStep: 'campaign' });
            }
            
            this.emit('state:loaded', state);
        } catch (error) {
            console.error('Error loading workflow state:', error);
            this.currentStep = 'client';
            await this.stateManager.updateState({ currentStep: 'client' });
        }
    }
    
    /**
     * Setup navigation event listeners
     */
    setupNavigation() {
        // Previous/Next buttons
        const prevBtn = document.querySelector('[data-action="previous-step"]');
        const nextBtn = document.querySelector('[data-action="next-step"]');
        
        if (prevBtn) {
            prevBtn.addEventListener('click', () => this.goToPreviousStep());
        }
        
        if (nextBtn) {
            nextBtn.addEventListener('click', () => this.goToNextStep());
        }
        
        // Save draft button
        const saveDraftBtn = document.querySelector('[data-action="save-draft"]');
        if (saveDraftBtn) {
            saveDraftBtn.addEventListener('click', () => this.saveDraft());
        }
    }
    
    /**
     * Setup breadcrumb navigation
     */
    setupBreadcrumbNavigation() {
        document.querySelectorAll('.breadcrumb-item').forEach(item => {
            item.addEventListener('click', (e) => {
                const targetStep = item.dataset.step;
                
                // Only allow clicking on completed or active steps
                if (item.classList.contains('completed') || item.classList.contains('active')) {
                    this.goToStep(targetStep);
                }
            });
        });
    }
    
    /**
     * Update breadcrumb classes based on current step
     */
    updateBreadcrumbClasses() {
        const steps = ['client', 'campaign', 'content-links', 'templates'];
        const currentIndex = steps.indexOf(this.currentStep);
        
        steps.forEach((step, index) => {
            const breadcrumbItem = document.querySelector(`.breadcrumb-item[data-step="${step}"]`);
            if (!breadcrumbItem) return;
            
            breadcrumbItem.classList.remove('active', 'completed', 'disabled');
            
            if (index < currentIndex) {
                breadcrumbItem.classList.add('completed');
            } else if (index === currentIndex) {
                breadcrumbItem.classList.add('active');
            } else {
                breadcrumbItem.classList.add('disabled');
            }
        });
    }
    
    /**
     * Update breadcrumb text display
     */
    updateBreadcrumbText(step, text) {
        const breadcrumbStatus = document.querySelector(`.breadcrumb-item[data-step="${step}"] .step-status`);
        if (breadcrumbStatus) {
            breadcrumbStatus.textContent = text;
        }
        
        // If no step specified, update all from state
        if (arguments.length === 0) {
            const state = this.stateManager.getState();
            
            // Update Client step status
            if (state.clientName) {
                const clientStatus = document.querySelector('.breadcrumb-item[data-step="client"] .step-status');
                if (clientStatus) {
                    clientStatus.textContent = state.clientName;
                }
            }
            
            // Update Campaign step status
            const campaignStatus = document.querySelector('.breadcrumb-item[data-step="campaign"] .step-status');
            if (campaignStatus) {
                if (state.campaignName) {
                    campaignStatus.textContent = state.campaignName;
                } else {
                    campaignStatus.textContent = 'Configure Campaign';
                }
            }
            
            // Update Content Links step status
            const contentLinksStatus = document.querySelector('.breadcrumb-item[data-step="content-links"] .step-status');
            if (contentLinksStatus && state.contentLinks) {
                const total = (state.contentLinks.problem || 0) + 
                              (state.contentLinks.solution || 0) + 
                              (state.contentLinks.offer || 0);
                if (total > 0) {
                    contentLinksStatus.textContent = `${total} Links Added`;
                } else {
                    contentLinksStatus.textContent = 'Add Content';
                }
            }
        }
    }

    /**
     * Go to specific step
     * 
     * @param {string} step - Step name
     */
    goToStep(step) {
        if (!this.steps.includes(step)) {
            console.error('Invalid step:', step);
            return;
        }
        
        this.currentStep = step;
        
        // Update state
        this.stateManager.updateState({ currentStep: step });
        
        // Update UI
        this.renderCurrentStep();
        this.updateBreadcrumbClasses();
        this.updateNavigationButtons();
        
        // Emit event
        this.emit('step:changed', step);
        
        // Scroll to top
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
    
    /**
     * Go to next step
     */
    async goToNextStep() {
        const currentIndex = this.steps.indexOf(this.currentStep);
        
        if (currentIndex >= this.steps.length - 1) {
            // Last step - complete workflow
            await this.completeWorkflow();
            return;
        }
        
        // Validate current step
        const state = this.stateManager.getState();
        const validation = await this.validateStep(this.currentStep, state);
        
        if (!validation.valid) {
            this.showValidationErrors(validation.errors);
            return;
        }
        
        // Mark step as complete
        const completedSteps = state.completedSteps || [];
        if (!completedSteps.includes(this.currentStep)) {
            completedSteps.push(this.currentStep);
            this.stateManager.updateState({ completedSteps });
        }
        
        // Move to next step
        const nextStep = this.steps[currentIndex + 1];
        this.goToStep(nextStep);
    }
    
    /**
     * Go to previous step
     */
    goToPreviousStep() {
        const currentIndex = this.steps.indexOf(this.currentStep);
        
        if (currentIndex <= 0) {
            return;
        }
        
        const previousStep = this.steps[currentIndex - 1];
        this.goToStep(previousStep);
    }
    
    /**
     * Render current step content
     */
    renderCurrentStep() {
        // Validate current step before rendering
        if (!this.steps.includes(this.currentStep)) {
            console.error('Invalid step:', this.currentStep);
            this.currentStep = 'client';
            this.stateManager.updateState({ currentStep: 'client' });
        }
        
        console.log('Rendering step:', this.currentStep);
        
        // Map steps to their container classes
        const containerMap = {
            'client': '.client-step-container',
            'campaign': '.campaign-step-container',
            'content-links': '.content-links-step-container',
            'templates': '.templates-step-container'
        };
        
        // Hide all step containers
        Object.values(containerMap).forEach(selector => {
            const container = document.querySelector(selector);
            if (container) {
                container.style.display = 'none';
            }
        });
        
        // Show current step container
        const currentSelector = containerMap[this.currentStep];
        const currentContent = document.querySelector(currentSelector);
        
        if (currentContent) {
            currentContent.style.display = 'block';
        } else {
            console.error('Step content not found for:', this.currentStep, 'Selector:', currentSelector);
        }
        
        // Update step title
        const stepTitle = document.querySelector('.step-title');
        if (stepTitle) {
            stepTitle.textContent = this.getStepTitle(this.currentStep);
        }

        // Update breadcrumb classes (active/completed/disabled)
        this.updateBreadcrumbClasses();
        
        // Update breadcrumb text (client name, campaign name)
        this.updateBreadcrumbText();

        // Update navigation buttons
        this.updateNavigationButtons();

        // Emit step change event
        this.emit('step:changed', this.currentStep);      
    }
    
    /**
     * Update navigation buttons
     */
    updateNavigationButtons() {
        const currentIndex = this.steps.indexOf(this.currentStep);
        
        // Previous button
        const prevBtn = document.querySelector('[data-action="previous-step"]');
        if (prevBtn) {
            prevBtn.disabled = currentIndex === 0;
            prevBtn.style.display = currentIndex === 0 ? 'none' : 'inline-flex';
        }
        
        // Next button
        const nextBtn = document.querySelector('[data-action="next-step"]');
        if (nextBtn) {
            const isLastStep = currentIndex === this.steps.length - 1;
            nextBtn.textContent = isLastStep ? 'Complete' : 'Next Step';
            nextBtn.innerHTML = isLastStep 
                ? '<i class="fas fa-check"></i> Complete'
                : 'Next Step <i class="fas fa-arrow-right"></i>';
        }
    }
    
    /**
     * Validate step
     * 
     * @param {string} step - Step name
     * @param {Object} state - Current state
     * @returns {Promise<Object>} Validation result
     */
    async validateStep(step, state) {
        const errors = [];
        
        console.log('Validating step:', step, 'State:', state);

        switch (step) {
            case 'client':
                if (!state.clientId) {
                    errors.push('Please select a client');
                }
                break;
                
            case 'campaign':
                // Get values from form if available, otherwise from state
                const nameInput = document.getElementById('campaign_name');
                const utmInput = document.getElementById('utm_campaign');
                
                const campaignName = nameInput?.value.trim() || state.campaignName;
                const utmCampaign = utmInput?.value.trim() || state.utmCampaign;
                
                if (!campaignName) {
                    errors.push('Campaign name is required');
                }
                if (!utmCampaign) {
                    errors.push('UTM campaign parameter is required');
                }
                
                // Check UTM uniqueness
                if (utmCampaign) {
                    const utmExists = await this.checkUtmExists(utmCampaign, state.campaignId);
                    if (utmExists) {
                        errors.push('This UTM campaign parameter is already in use');
                    }
                }
                break;
                
            case 'content-links':
                if (!this.contentLinksManager) {
                    errors.push('Content links manager not available');
                    break;
                }
                
                const links = this.contentLinksManager.getLinks();
                const requiredRooms = ['problem', 'solution', 'offer'];
                
                // Check if each room has at least one active link
                const missingRooms = requiredRooms.filter(room => {
                    const roomLinks = links[room] || [];
                    const activeLinks = roomLinks.filter(link => link.is_active);
                    return activeLinks.length === 0;
                });
                
                if (missingRooms.length > 0) {
                    errors.push(`Please add at least one active link for: ${missingRooms.map(r => {
                        return r.charAt(0).toUpperCase() + r.slice(1);
                    }).join(', ')} Room(s)`);
                }
                break;
                
            case 'templates':
                if (!this.templateManager) {
                    errors.push('Template manager not available');
                    break;
                }
                
                const templates = this.templateManager.getTemplates();
                const requiredTemplateRooms = ['problem', 'solution', 'offer'];
                
                // Check if each room has at least one template
                const missingTemplateRooms = requiredTemplateRooms.filter(room => 
                    !templates[room] || templates[room].length === 0
                );
                
                if (missingTemplateRooms.length > 0) {
                    errors.push(`Please create at least one template for: ${missingTemplateRooms.map(r => {
                        return r.charAt(0).toUpperCase() + r.slice(1);
                    }).join(', ')} Room(s)`);
                }
                break;
        }
        
        return {
            valid: errors.length === 0,
            errors: errors
        };
    }
    
    /**
     * Check if step is complete
     * 
     * @param {string} step - Step name
     * @param {Object} state - Current state
     * @returns {boolean}
     */
    isStepComplete(step, state) {
        return state.completedSteps && state.completedSteps.includes(step);
    }

    /**
     * Disable navigation button
     * 
     * @param {string} direction - 'next' or 'previous'
     */
    disableNavigation(direction = 'next') {
        this.enableNavigation(direction, false);
    }    

    /**
     * Save draft
     */
    async saveDraft() {
        try {
            this.showNotification('Saving draft...', 'info');
            await this.stateManager.forceSave();
            this.showNotification('Draft saved successfully', 'success');
            this.updateSaveIndicator();
        } catch (error) {
            this.showNotification('Failed to save draft', 'error');
        }
    }

    /**
     * Update workflow state
     * 
     * @param {Object} updates - State updates
     */
    updateState(updates) {
        const newState = this.stateManager.updateState(updates);
        
        // Update UI based on state changes
        if (updates.currentStep) {
            this.goToStep(updates.currentStep);
        }
        
        // Update breadcrumb text if client or campaign changed
        if (updates.clientName || updates.campaignName) {
            this.updateBreadcrumbText();
        }
        
        return newState;
    }

    /**
     * Enable/disable navigation buttons
     * 
     * @param {string} direction - 'next' or 'previous'
     * @param {boolean} enabled - Enable or disable (default true)
     */
    enableNavigation(direction = 'next', enabled = true) {
        const button = direction === 'next' 
            ? document.querySelector('[data-action="next-step"]')
            : document.querySelector('[data-action="previous-step"]');
        
        if (button) {
            button.disabled = !enabled;
            if (enabled) {
                button.classList.remove('disabled');
            } else {
                button.classList.add('disabled');
            }
        }
    }

    /**
     * Complete workflow
     */
    async completeWorkflow() {
        try {
            const state = this.stateManager.getState();
            
            // Validate all steps
            for (const step of this.steps) {
                const validation = await this.validateStep(step, state);
                if (!validation.valid) {
                    this.showValidationErrors(validation.errors);
                    return;
                }
            }
            
            this.showNotification('Publishing campaign...', 'info');
            
            // Call complete endpoint
            const response = await fetch(`${this.config.apiUrl}/workflow/complete`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.config.nonce
                },
                body: JSON.stringify(state)
            });
            
            if (!response.ok) {
                throw new Error('Failed to complete workflow');
            }
            
            const data = await response.json();
            
            this.showNotification('Campaign published successfully!', 'success');
            this.stateManager.clearState();
            
            // Redirect after delay
            setTimeout(() => {
                window.location.href = this.config.dashboardUrl || '/wp-admin/admin.php?page=dr-campaign-builder';
            }, 2000);
            
        } catch (error) {
            console.error('Error completing workflow:', error);
            this.showNotification('Failed to publish campaign', 'error');
        }
    }
    
    /**
     * Check if UTM campaign exists
     * 
     * @param {string} utmCampaign - UTM parameter
     * @param {number|null} excludeId - Campaign ID to exclude
     * @returns {Promise<boolean>}
     */
    async checkUtmExists(utmCampaign, excludeId = null) {
        try {
            const url = new URL(`${this.config.apiUrl}/campaigns/check-utm`);
            url.searchParams.append('utm_campaign', utmCampaign);
            if (excludeId) {
                url.searchParams.append('exclude_id', excludeId);
            }
            
            const response = await fetch(url, {
                headers: {
                    'X-WP-Nonce': this.config.nonce
                }
            });
            
            const data = await response.json();
            return data.exists || false;
            
        } catch (error) {
            console.error('Error checking UTM:', error);
            return false;
        }
    }
    
    /**
     * Show validation errors
     * 
     * @param {Array<string>} errors - Error messages
     */
    showValidationErrors(errors) {
        errors.forEach(error => {
            this.showNotification(error, 'error');
        });
    }
    
    /**
     * Show notification
     * 
     * @param {string} message - Notification message
     * @param {string} type - Notification type (success, error, warning, info)
     */
    showNotification(message, type) {
        this.emit('notification:show', { message, type });
    }
    
    /**
     * Update save indicator
     */
    updateSaveIndicator() {
        const indicator = document.querySelector('.save-indicator');
        if (indicator) {
            const lastSaved = this.stateManager.getLastSaved();
            if (lastSaved) {
                indicator.textContent = `Last saved: ${this.formatTime(lastSaved)}`;
                indicator.classList.add('saved');
            }
        }
    }
    
    /**
     * Get step title
     * 
     * @param {string} step - Step name
     * @returns {string}
     */
    getStepTitle(step) {
        const titles = {
            client: 'Select Client',
            campaign: 'Configure Campaign',
            'content-links': 'Add Content Links',
            templates: 'Create Email Templates'
        };
        return titles[step] || step;
    }
    
    /**
     * Format time for display
     * 
     * @param {Date} date - Date object
     * @returns {string}
     */
    formatTime(date) {
        const now = new Date();
        const diff = now - date;
        const seconds = Math.floor(diff / 1000);
        
        if (seconds < 60) return 'just now';
        if (seconds < 3600) return `${Math.floor(seconds / 60)}m ago`;
        if (seconds < 86400) return `${Math.floor(seconds / 3600)}h ago`;
        return date.toLocaleDateString();
    }
    
    /**
     * Event emitter - register listener
     * 
     * @param {string} event - Event name
     * @param {Function} callback - Callback function
     */
    on(event, callback) {
        if (!this.listeners[event]) {
            this.listeners[event] = [];
        }
        this.listeners[event].push(callback);
    }
    
    /**
     * Event emitter - trigger event
     * 
     * @param {string} event - Event name
     * @param {*} data - Event data
     */
    emit(event, data) {
        if (this.listeners[event]) {
            this.listeners[event].forEach(callback => callback(data));
        }
    }
}