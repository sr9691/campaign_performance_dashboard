/**
 * Campaign Builder Main Entry Point
 * 
 * @package DirectReach_Campaign_Builder
 * @since 2.0.0
 */

import WorkflowManager from './modules/workflow.js';
import StateManager from './modules/state-manager.js';
import ClientManager from './modules/client-manager.js';
import CampaignManager from './modules/campaign-manager.js';
import ContentLinksManager from './modules/content-links-manager.js';
import TemplateManager from './modules/template-manager.js';
import ClientSettingsManager from '../../../scoring-system/admin/js/modules/client-settings-manager.js';

class CampaignBuilder {
    constructor(config) {
        this.config = config;
        this.managers = {};
        this.init();
    }
    
    /**
     * Initialize the campaign builder
     */
    async init() {
        try {
            console.log('Campaign Builder: Initializing...', this.config);
            
            // Initialize managers in order
            this.initializeStateManager();
            this.initializeSettingsManager();
            this.initializeClientManager();
            this.initializeCampaignManager();
            this.initializeContentLinksManager();
            this.initializeTemplateManager();
            this.initializeWorkflowManager();
            
            // Setup global event listeners
            this.setupGlobalListeners();
            
            // Initialize workflow (loads state and renders initial step)
            await this.managers.workflow.init();
            
            console.log('Campaign Builder: Initialized successfully');
            
        } catch (error) {
            console.error('Campaign Builder: Initialization failed', error);
            this.showFatalError(error);
        }
    }
    
    /**
     * Initialize State Manager
     */
    initializeStateManager() {
        this.managers.state = new StateManager(this.config);
        this.managers.state.init();
        
        console.log('Campaign Builder: State Manager initialized');
    }
    
    /**
     * Initialize Settings Manager
     */
    initializeSettingsManager() {
        this.managers.settings = new ClientSettingsManager(this.config);
        
        this.managers.settings.on('notification', (data) => {
            this.showNotification(data.message, data.type);
        });
        
        console.log('Campaign Builder: Settings Manager initialized');
    }

    /**
     * Initialize Client Manager
     */
    initializeClientManager() {
        this.managers.client = new ClientManager(this.config, this.managers.settings);
        
        // Listen for client selection
        this.managers.client.on('client:selected', (client) => {
            console.log('Client selected:', client);
            
            // Update state
            this.managers.state.updateState({
                clientId: client.id,
                clientName: client.name
            });
            
            // Update breadcrumb
            this.managers.workflow?.updateBreadcrumbText('client', client.name);
        });
        
        this.managers.client.on('notification', (data) => {
            this.showNotification(data.message, data.type);
        });
        
        console.log('Campaign Builder: Client Manager initialized');
    }
    
    /**
     * Initialize Campaign Manager
     */
    initializeCampaignManager() {
        this.managers.campaign = new CampaignManager(this.config, this.managers.state);
        
        // Listen for campaign selection
        this.managers.campaign.on('campaign:selected', (campaign) => {
            console.log('Campaign selected:', campaign);
            
            // Update state
            this.managers.state.updateState({
                campaignId: campaign.id,
                campaignName: campaign.campaign_name,
                utmCampaign: campaign.utm_campaign
            });
            
            // Update breadcrumb
            this.managers.workflow?.updateBreadcrumbText('campaign', campaign.campaign_name);
            
            // Load content links for this campaign
            if (this.managers.contentLinks) {
                this.managers.contentLinks.loadLinks();
            }
            
            // Load templates for this campaign
            if (this.managers.template) {
                this.managers.template.loadTemplates();
            }
        });
        
        this.managers.campaign.on('campaign:created', (campaign) => {
            console.log('Campaign created:', campaign);
        });
        
        this.managers.campaign.on('campaign:updated', (campaign) => {
            console.log('Campaign updated:', campaign);
        });
        
        this.managers.campaign.on('campaign:deselected', () => {
            console.log('Campaign deselected');
        });
        
        this.managers.campaign.on('notification', (data) => {
            this.showNotification(data.message, data.type);
        });
        
        console.log('Campaign Builder: Campaign Manager initialized');
    }
    
    /**
     * Initialize Content Links Manager
     */
    initializeContentLinksManager() {
        this.managers.contentLinks = new ContentLinksManager(this.config, this.managers.state);
                
        // Listen for links loaded
        this.managers.contentLinks.on('links:loaded', (links) => {
            console.log('Content links loaded:', links);
            
            // Update state with link counts
            this.managers.state.updateState({
                contentLinks: {
                    problem: links.problem.length,
                    solution: links.solution.length,
                    offer: links.offer.length
                }
            });
        });
        
        this.managers.contentLinks.on('notification', (data) => {
            this.showNotification(data.message, data.type);
        });
        
        console.log('Campaign Builder: Content Links Manager initialized');
    }
    
    /**
     * Initialize Template Manager
     */
    initializeTemplateManager() {
        this.managers.template = new TemplateManager(this.config, this.managers.state, {
            containerSelector: '.templates-step-container',
            isGlobal: false
        });
        
        console.log('Campaign Builder: Template Manager initialized');
    }
    
    /**
     * Initialize Workflow Manager
     */
    initializeWorkflowManager() {
        this.managers.workflow = new WorkflowManager(
            this.config,
            this.managers.state,
            this.managers.client,
            this.managers.campaign,
            this.managers.contentLinks,
            this.managers.template
        );
        
        // Listen for step changes
        this.managers.workflow.on('step:changed', (step) => {
            console.log('Step changed to:', step);
            
            // Load data when entering specific steps
            if (step === 'client') {
                // Client step - load clients if needed
                const state = this.managers.state.getState();
                if (!state.clientId) {
                    this.managers.client.loadClients();
                }
            } else if (step === 'campaign') {
                // Campaign step - load campaigns for selected client
                const state = this.managers.state.getState();
                if (state.clientId) {
                    this.managers.campaign.loadCampaigns(state.clientId);
                }
            } else if (step === 'content-links') {
                // Content links step - load links for selected campaign
                const state = this.managers.state.getState();
                if (state.campaignId) {
                    this.managers.contentLinks.loadLinks();
                }
            } else if (step === 'templates') {
                // Templates step - load templates for selected campaign
                const state = this.managers.state.getState();
                if (state.campaignId) {
                    this.managers.template.loadTemplates();
                }
            }
        });
        
        this.managers.workflow.on('workflow:initialized', () => {
            console.log('Workflow initialized');
        });
        
        this.managers.workflow.on('workflow:error', (error) => {
            console.error('Workflow error:', error);
            this.showNotification('Workflow error: ' + error.message, 'error');
        });
        
        this.managers.workflow.on('state:loaded', (state) => {
            console.log('State loaded:', state);
        });
        
        this.managers.workflow.on('notification:show', (data) => {
            this.showNotification(data.message, data.type);
        });
        
        console.log('Campaign Builder: Workflow Manager initialized');
    }
    
    /**
     * Setup global event listeners
     */
    setupGlobalListeners() {
        // Settings dropdown toggle - ONLY if not already initialized by inline script
        const settingsDropdown = document.querySelector('.settings-dropdown');
        const settingsToggle = document.querySelector('.settings-toggle');
        
        // Check if inline script already initialized it (prevents duplicate listeners)
        if (settingsDropdown && !settingsDropdown.hasAttribute('data-initialized')) {
            if (settingsToggle) {
                console.log('Campaign Builder: Settings dropdown initialized by main.js');
                
                settingsToggle.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const wasActive = settingsDropdown.classList.contains('active');
                    settingsDropdown.classList.toggle('active');
                    console.log('Campaign Builder: Dropdown toggled from', wasActive, 'to', settingsDropdown.classList.contains('active'));
                });
                
                // Close dropdown when clicking outside
                document.addEventListener('click', (e) => {
                    if (!settingsDropdown.contains(e.target) && settingsDropdown.classList.contains('active')) {
                        settingsDropdown.classList.remove('active');
                        console.log('Campaign Builder: Dropdown closed (clicked outside)');
                    }
                });
                
                // Mark as initialized to prevent duplicate listeners
                settingsDropdown.setAttribute('data-initialized', 'true');
            }
        } else if (settingsDropdown && settingsDropdown.hasAttribute('data-initialized')) {
            console.log('Campaign Builder: Settings dropdown already initialized by inline script, skipping');
        }
        
        // Logout button
        const logoutBtn = document.querySelector('.logout-btn');
        if (logoutBtn) {
            logoutBtn.addEventListener('click', () => {
                if (confirm('Are you sure you want to log out?')) {
                    window.location.href = this.config.dashboardUrl + '&action=logout';
                }
            });
        }
        
        // Handle browser back/forward buttons
        window.addEventListener('popstate', (e) => {
            if (e.state && e.state.step) {
                this.managers.workflow?.goToStep(e.state.step);
            }
        });
        
        // Save draft button (if exists)
        const saveDraftBtn = document.querySelector('[data-action="save-draft"]');
        if (saveDraftBtn) {
            saveDraftBtn.addEventListener('click', async () => {
                try {
                    await this.managers.state.forceSave();
                    this.showNotification('Draft saved successfully', 'success');
                } catch (error) {
                    console.error('Failed to save draft:', error);
                    this.showNotification('Failed to save draft', 'error');
                }
            });
        }
    }
    
    /**
     * Show notification
     * 
     * @param {string} message - Notification message
     * @param {string} type - Notification type (success, error, warning, info)
     */
    showNotification(message, type = 'info') {
        const container = document.querySelector('.notification-container');
        if (!container) {
            console.warn('Notification container not found');
            return;
        }
        
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        
        const iconMap = {
            success: 'fa-check-circle',
            error: 'fa-times-circle',
            warning: 'fa-exclamation-triangle',
            info: 'fa-info-circle'
        };
        
        notification.innerHTML = `
            <i class="fas ${iconMap[type]}"></i>
            <span>${this.escapeHtml(message)}</span>
            <button class="notification-close">
                <i class="fas fa-times"></i>
            </button>
        `;
        
        container.appendChild(notification);
        
        // Auto-dismiss after 5 seconds
        const autoDismissTimeout = setTimeout(() => {
            this.dismissNotification(notification);
        }, 5000);
        
        // Manual dismiss
        const closeBtn = notification.querySelector('.notification-close');
        closeBtn.addEventListener('click', () => {
            clearTimeout(autoDismissTimeout);
            this.dismissNotification(notification);
        });
    }
    
    /**
     * Dismiss notification
     * 
     * @param {HTMLElement} notification - Notification element
     */
    dismissNotification(notification) {
        notification.classList.add('fade-out');
        setTimeout(() => {
            notification.remove();
        }, 300);
    }
    
    /**
     * Show fatal error
     * 
     * @param {Error} error - Error object
     */
    showFatalError(error) {
        const container = document.querySelector('.workflow-container');
        if (!container) {
            console.error('Fatal error but no container found to display it');
            return;
        }
        
        container.innerHTML = `
            <div style="padding: 60px 20px; text-align: center; max-width: 800px; margin: 0 auto;">
                <i class="fas fa-exclamation-triangle" style="font-size: 64px; color: #e74c3c; margin-bottom: 20px;"></i>
                <h2 style="color: #333; margin-bottom: 12px; font-size: 24px;">Initialization Error</h2>
                <p style="color: #666; margin-bottom: 20px; font-size: 16px;">
                    The Campaign Builder failed to initialize. Please refresh the page or contact support if the problem persists.
                </p>
                <details style="text-align: left; margin-bottom: 24px;">
                    <summary style="cursor: pointer; color: #666; font-weight: 600; margin-bottom: 12px;">
                        Error Details
                    </summary>
                    <pre style="background: #f8f9fa; padding: 20px; border-radius: 4px; overflow: auto; font-size: 12px; line-height: 1.5;">
${error.message}

${error.stack ? error.stack : ''}
                    </pre>
                </details>
                <button class="btn btn-primary" onclick="window.location.reload()" style="padding: 12px 24px; font-size: 16px;">
                    <i class="fas fa-redo"></i> Reload Page
                </button>
            </div>
        `;
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
     * Get manager instance
     * 
     * @param {string} name - Manager name
     * @return {Object|null} Manager instance
     */
    getManager(name) {
        return this.managers[name] || null;
    }
    
    /**
     * Get current state
     * 
     * @return {Object} Current state
     */
    getState() {
        return this.managers.state?.getState() || {};
    }
}

// Initialize when DOM is ready and config is available
document.addEventListener('DOMContentLoaded', () => {
    if (typeof window.drCampaignBuilderConfig !== 'undefined') {
        console.log('Campaign Builder: Config found, initializing...');
        window.drCampaignBuilder = new CampaignBuilder(window.drCampaignBuilderConfig);
    } else {
        console.error('Campaign Builder: Configuration not found. Ensure drCampaignBuilderConfig is defined.');
    }
});

// Export for use in other scripts if needed
export default CampaignBuilder;