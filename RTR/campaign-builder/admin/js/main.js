/**
 * Campaign Builder - Main Entry Point
 * 
 * @package DirectReach_Campaign_Builder
 * @since 2.0.0
 */

import StateManager from './modules/state-manager.js';
import WorkflowManager from './modules/workflow.js';
import ClientManager from './modules/client-manager.js';
import CampaignManager from './modules/campaign-manager.js';
import TemplateManager from './modules/template-manager.js';

import NotificationSystem from './modules/notifications.js';


class CampaignBuilderApp {
    constructor(config) {
        this.config = config;
        
        this.stateManager = new StateManager(config);
        this.notifications = new NotificationSystem();

        // Initialize feature managers
        this.clientManager = new ClientManager(config);
        this.campaignManager = new CampaignManager(config, this.stateManager);
        this.templateManager = new TemplateManager(config, this.stateManager);

        // Initialize workflow WITH all managers
        this.workflowManager = new WorkflowManager(
            config, 
            this.stateManager,
            this.clientManager,
            this.campaignManager,
            this.templateManager
        );
        
        // Setup event listeners
        this.setupEventListeners();
    }
    
    /**
     * Initialize application
     */
    async init() {
        try {
            // Initialize workflow first
            await this.workflowManager.init();
            
            // Load clients BEFORE restoring state
            await this.clientManager.loadClients();
            
            // Load campaigns (will be filtered by selected client if any)
            const campaigns = await this.campaignManager.loadCampaigns();
            console.log('Loaded campaigns:', campaigns.length);
            
            // NOW restore state (clients are loaded, so selection will work)
            this.restoreState();
            
            // Setup event listeners
            this.setupEventListeners();
            
            console.log('Campaign Builder initialized successfully');
        } catch (error) {
            console.error('Error initializing Campaign Builder:', error);
        }
    }
    
    /**
     * Setup all event listeners
     */
    setupEventListeners() {

        // Client manager events
        this.clientManager.on('client:selected', (client) => {
            this.handleClientSelected(client);
        });
        
        this.clientManager.on('client:created', (client) => {
            this.showNotification(`Client "${client.name}" created successfully`, 'success');
        });
        
        this.clientManager.on('notification', (notification) => {
            this.showNotification(notification.message, notification.type);
        });
        
        // Campaign manager events
        this.campaignManager.on('campaign:selected', (campaign) => {
            this.handleCampaignSelected(campaign);
        });
        
        this.campaignManager.on('campaign:created', (campaign) => {
            console.log('Campaign created:', campaign.campaign_name);
        });
        
        this.campaignManager.on('campaign:updated', (campaign) => {
            console.log('Campaign updated:', campaign.campaign_name);
        });
        
        this.campaignManager.on('campaign:deselected', () => {
            this.handleCampaignDeselected();
        });
        
        this.campaignManager.on('campaigns:loaded', (campaigns) => {
            console.log(`Loaded ${campaigns.length} campaigns`);
        });
        
        this.campaignManager.on('campaigns:error', (error) => {
            this.showNotification('Failed to load campaigns', 'error');
        });
        
        this.campaignManager.on('notification', (notification) => {
            this.showNotification(notification.message, notification.type);
        });

        // Template manager events
        this.templateManager.on('template:saved', (data) => {
            console.log('Template saved:', data.room);
        });

        this.templateManager.on('templates:complete', () => {
            this.handleTemplatesComplete();
        });

        this.templateManager.on('notification', (notification) => {
            this.showNotification(notification.message, notification.type);
        });        
        
        // Workflow manager events
        this.workflowManager.on('notification:show', (data) => {
            this.showNotification(data.message, data.type);
        });
        
        this.workflowManager.on('step:changed', (step) => {
            this.handleStepChanged(step);
        });

        this.setupSettingsDropdown();        
    }
    
    
    /**
     * Handle client selection
     * 
     * @param {Object} client - Selected client object
     */
    handleClientSelected(client) {
        console.log('Client selected:', client.name);
        
        // Update workflow state
        this.workflowManager.updateState({
            clientId: client.id,
            clientName: client.name,

        });
        
        // Update breadcrumb
        this.workflowManager.updateBreadcrumbText('client', client.name);
        
        // Enable navigation to campaign step
        this.workflowManager.enableNavigation('campaign');
        
        // Load campaigns for this client
        this.campaignManager.loadCampaigns(client.id, client.name);
        
        // Move to campaign step
        this.workflowManager.renderCurrentStep('campaign');
        
        this.showNotification(`Selected client: ${client.name}`, 'success');
    }
    
    /**
     * Handle campaign selection
     * 
     * @param {Object} campaign - Selected campaign object
     */
    handleCampaignSelected(campaign) {
        // Update state
        this.stateManager.updateState({
            campaignId: campaign.id,
            campaignName: campaign.campaign_name,
            utmCampaign: campaign.utm_campaign
        });
        
        // Update workflow
        this.workflowManager.updateState({
            campaignId: campaign.id,
            campaignName: campaign.campaign_name,
            utmCampaign: campaign.utm_campaign
        });
        
        // Enable next step button
        this.workflowManager.enableNavigation('next');
    }
    
    /**
     * Handle campaign deselection
     */
    handleCampaignDeselected() {
        console.log('Campaign deselected');
        
        // Update workflow state
        this.workflowManager.updateState({
            campaignId: null,
            campaignName: null,
            utmCampaign: null
        });
        
        // Update breadcrumb
        this.workflowManager.updateBreadcrumbText('campaign', 'Select Campaign');
        
        // Disable navigation to templates step
        this.workflowManager.disableNavigation('templates');
    }
    
    /**
     * Handle templates load complete
     */
    handleTemplatesComplete() {
        this.stateManager.updateState({
            templates: this.templateManager.getTemplates()
        });
    }

    /**
     * Handle step changes
     * 
     * @param {string} step - New step name
     */
    handleStepChanged(step) {
        console.log('Step changed to:', step);
        
        // If navigating back to client step, clear campaign selection
        if (step === 'client') {
            this.campaignManager.clearSelection();
        }
        
        // If navigating to campaign step, ensure campaigns are loaded
        if (step === 'campaign') {
            const state = this.stateManager.getState();
            if (state.clientId) {
                this.campaignManager.selectedClientId = state.clientId;
                this.campaignManager.selectedClientName = state.clientName;
                
                // Update client name display
                const clientNameDisplay = document.querySelector('.selected-client-name');
                if (clientNameDisplay) {
                    clientNameDisplay.textContent = state.clientName;
                }
                
                // Load campaigns for this client
                this.campaignManager.loadCampaigns(state.clientId);
            }
        }

        // Listen for campaign selection
        this.campaignManager.on('campaign:selected', (campaign) => {
            this.handleCampaignSelected(campaign);
        });

        // If navigating to templates step, load templates for selected campaign
        if (step === 'templates') {
            const state = this.stateManager.getState();
            if (state.campaignId) {
                const campaignNameDisplay = document.querySelector('.selected-campaign-name');
                if (campaignNameDisplay) {
                    campaignNameDisplay.textContent = state.campaignName;
                }
                this.templateManager.loadTemplates(state.campaignId);
            }
        }        
    }
    
    /**
     * Restore state from previous session
     */
    async restoreState() {
        const state = this.stateManager.getState();
        
        if (!state || !state.clientId) {
            // No saved state, start at beginning
            this.workflowManager.renderCurrentStep('client');
            return;
        }
        
        console.log('Restoring state:', state);
        
        // Restore client selection
        if (state.clientId) {
            this.clientManager.setSelectedClient(state.clientId);
            this.workflowManager.updateBreadcrumbText('client', state.clientName);
            this.workflowManager.enableNavigation('campaign');
        }
        
        // Restore campaign selection
        if (state.campaignId) {
            // Load campaigns first, then select
            await this.campaignManager.loadCampaigns(state.clientId, state.clientName);
            this.campaignManager.selectCampaign(state.campaignId);
            this.workflowManager.updateBreadcrumbText('campaign', state.campaignName);
            this.workflowManager.enableNavigation('templates');
        }
        
        // Show appropriate step
        if (state.current_step) {
            this.workflowManager.renderCurrentStep(state.current_step);
        }
    }
    
    /**
     * Show notification toast
     * 
     * @param {string} message - Notification message
     * @param {string} type - Type (success, error, warning, info)
     */
    showNotification(message, type = 'info') {
        const container = document.querySelector('.notification-container');
        if (!container) return;
        
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        
        const icon = this.getNotificationIcon(type);
        notification.innerHTML = `
            <i class="fas fa-${icon}"></i>
            <span>${message}</span>
            <button class="notification-close">
                <i class="fas fa-times"></i>
            </button>
        `;
        
        container.appendChild(notification);
        
        // Auto dismiss after 5 seconds
        setTimeout(() => {
            notification.classList.add('fade-out');
            setTimeout(() => notification.remove(), 300);
        }, 5000);
        
        // Close button
        notification.querySelector('.notification-close').addEventListener('click', () => {
            notification.classList.add('fade-out');
            setTimeout(() => notification.remove(), 300);
        });
    }
    
    /**
     * Get icon for notification type
     * 
     * @param {string} type - Notification type
     * @returns {string} Icon class
     */
    getNotificationIcon(type) {
        const icons = {
            success: 'check-circle',
            error: 'exclamation-circle',
            warning: 'exclamation-triangle',
            info: 'info-circle'
        };
        return icons[type] || 'info-circle';
    }
    
    /**
     * Setup save indicator
     */
    setupSaveIndicator() {
        setInterval(() => {
            const indicator = document.querySelector('.save-indicator');
            if (!indicator) return;
            
            const lastSaved = this.stateManager.getLastSaved();
            if (lastSaved) {
                const timeAgo = this.getTimeAgo(lastSaved);
                indicator.textContent = `Last saved: ${timeAgo}`;
                indicator.classList.remove('unsaved');
            } else if (this.stateManager.hasUnsavedChanges()) {
                indicator.textContent = 'Unsaved changes';
                indicator.classList.add('unsaved');
            }
        }, 10000); // Update every 10 seconds
    }
    
    /**
     * Setup settings dropdown
     * 
     * ADDED: Handle clicks on Settings menu items
     */
    setupSettingsDropdown() {
        const settingsBtn = document.querySelector('[data-toggle="settings-dropdown"]');
        const dropdown = document.querySelector('.settings-dropdown');
        const settingsMenu = document.querySelector('.settings-menu');
        
        if (!settingsBtn || !dropdown) return;
        
        // Toggle dropdown on button click
        settingsBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            dropdown.classList.toggle('active');
        });
        
        // Close on outside click
        document.addEventListener('click', () => {
            dropdown.classList.remove('active');
        });
        
        // Prevent closing when clicking inside dropdown
        dropdown.addEventListener('click', (e) => {
            e.stopPropagation();
        });
        
        // Handle menu item clicks
        if (settingsMenu) {
            settingsMenu.addEventListener('click', (e) => {
                const settingsItem = e.target.closest('.settings-item');
                
                if (!settingsItem) return;
                
                const action = settingsItem.dataset.action;
                
                switch(action) {
                    case 'room-thresholds':
                        // TODO: Handle room thresholds (existing functionality)
                        console.log('Room thresholds clicked');
                        break;
                        
                    case 'scoring-rules':
                        // TODO: Handle scoring rules (existing functionality)
                        console.log('Scoring rules clicked');
                        break;
                        
                    case 'global-templates':
                        // Navigate to Global Templates page
                        window.location.href = 'admin.php?page=dr-global-templates';
                        break;
                }
                
                // Close dropdown after selection
                dropdown.classList.remove('active');
            });
        }
    }
    
    /**
     * Get time ago string
     * 
     * @param {Date} date - Date object
     * @returns {string}
     */
    getTimeAgo(date) {
        const now = new Date();
        const diff = now - date;
        const seconds = Math.floor(diff / 1000);
        
        if (seconds < 60) return 'just now';
        if (seconds < 3600) return `${Math.floor(seconds / 60)}m ago`;
        if (seconds < 86400) return `${Math.floor(seconds / 3600)}h ago`;
        return date.toLocaleDateString();
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', async () => {
    if (typeof drCampaignBuilderConfig !== 'undefined') {
        const app = new CampaignBuilderApp(drCampaignBuilderConfig);
        await app.init(); // AWAIT the async init
        window.campaignBuilderApp = app;
    } else {
        console.error('Campaign Builder configuration not found. Make sure drCampaignBuilderConfig is localized.');
    }
});