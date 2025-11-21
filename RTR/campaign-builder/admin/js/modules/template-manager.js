/**
 * Template Manager - Structured Prompt Format (Phase 2.5)
 * 
 * Handles AI prompt template CRUD operations with structured sections
 * Supports both campaign-specific and global templates
 * 
 * @package DirectReach_Campaign_Builder
 * @since 2.5.0
 */

import EventEmitter from '../utils/event-emitter.js';
import APIClient from '../utils/api-client.js';

export default class TemplateManager extends EventEmitter {
    constructor(config, stateManager, options = {}) {  
        super();
        
        this.config = config;
        this.api = new APIClient(config.apiUrl, config.nonce);  
        this.stateManager = stateManager;
        this.isGlobal = options.isGlobal || false;
        
        // NEW: Accept a container selector or element
        this.containerSelector = options.containerSelector || '.templates-step-container';
        this.containerElement = null; // Will be set in init
        
        this.templates = {
            problem: [],
            solution: [],
            offer: []
        };
        
        this.currentRoom = 'problem';
        this.editingTemplateId = null;
        this.editingGlobalTemplate = false;
        this.isFormVisible = false;
        
        this.init();
    }
    
    /**
     * Initialize the manager
     */
    async init() {
        console.log('TemplateManager: Initializing...', this.isGlobal ? '(Global)' : '(Campaign)');
        
        // Find the container element
        this.containerElement = document.querySelector(this.containerSelector);
        if (!this.containerElement) {
            console.error(`TemplateManager: Container not found: ${this.containerSelector}`);
            return;
        }
        
        this.attachEventListeners();
        
        const retryBtn = this.containerElement.querySelector('#retry-load-templates');
        if (retryBtn) {
            retryBtn.addEventListener('click', () => this.loadTemplates());
        }
        
        await this.loadTemplates();
    }
    
    /**
     * Attach all event listeners
     */
    attachEventListeners() {
        if (!this.containerElement) return;
        
        // Use scoped query within this container
        const tabsContainer = this.containerElement.querySelector('.room-tabs');
        if (tabsContainer) {
            tabsContainer.addEventListener('click', (e) => {
                const tab = e.target.closest('.room-tab');
                if (tab) {
                    const room = tab.dataset.room;
                    this.switchRoom(room);
                }
            });
        }
        
        this.attachFormListeners();
        this.attachTestListeners();
        
        const retryBtn = this.containerElement.querySelector('#retry-load-templates');
        if (retryBtn) {
            retryBtn.addEventListener('click', () => this.loadTemplates());
        }
    }
    
    /**
     * Attach form-related listeners
     */
    attachFormListeners() {
        const backBtn = this.containerElement.querySelector('.btn-back-to-list');
        if (backBtn) {
            backBtn.addEventListener('click', () => this.hideForm());
        }
        
        this.containerElement.querySelectorAll('.btn-cancel-form').forEach(btn => {
            btn.addEventListener('click', () => this.hideForm());
        });
        
        const form = this.containerElement.querySelector('#template-form');
        if (form) {
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                this.handleFormSubmit();
            });
        }
    }
    
    /**
     * Attach test functionality listeners
     */
    attachTestListeners() {
        const testBtn = this.containerElement.querySelector('#test-prompt-btn');
        if (testBtn) {
            testBtn.addEventListener('click', () => this.handleTestPrompt());
        }
        
        const generateBtn = this.containerElement.querySelector('#generate-test-email-btn');
        if (generateBtn) {
            generateBtn.addEventListener('click', () => this.handleGenerateTestEmail());
        }
        
        const regenerateBtn = this.containerElement.querySelector('#regenerate-email-btn');
        if (regenerateBtn) {
            regenerateBtn.addEventListener('click', () => this.handleGenerateTestEmail());
        }
    }
    
    /**
     * Load templates - merged view for campaigns, global-only for global mode
     */
    async loadTemplates(campaignId = null) {
        let targetCampaignId;
        
        if (this.isGlobal) {
            targetCampaignId = 0;
        } else {
            if (campaignId) {
                targetCampaignId = campaignId;
            } else if (this.stateManager) {
                const state = this.stateManager.getState();
                targetCampaignId = state.campaignId;
            }
        }
        
        if (!targetCampaignId && targetCampaignId !== 0) {
            console.log('TemplateManager: No campaign selected yet');
            return;
        }
        
        const loadingDiv = this.containerElement.querySelector('#templates-loading');
        const contentDiv = this.containerElement.querySelector('#templates-content');
        const errorDiv = this.containerElement.querySelector('#templates-error');
        
        if (loadingDiv) loadingDiv.style.display = 'flex';
        if (contentDiv) contentDiv.style.display = 'none';
        if (errorDiv) errorDiv.style.display = 'none';
        
        try {
            // Use merged endpoint for campaigns, regular for global
            const endpoint = this.isGlobal 
                ? `/templates?is_global=1`
                : `/campaigns/${targetCampaignId}/templates`;
            
            const response = await this.api.get(endpoint);
            
            if (response.success) {
                this.templates = {
                    problem: [],
                    solution: [],
                    offer: []
                };
                
                response.data.forEach(template => {
                    if (this.templates[template.room_type]) {
                        this.templates[template.room_type].push(template);
                    }
                });
                
                if (loadingDiv) loadingDiv.style.display = 'none';
                if (contentDiv) contentDiv.style.display = 'block';
                
                this.renderAllRooms();
                this.updateTabStatuses();
            }
        } catch (error) {
            console.error('TemplateManager: Failed to load templates:', error);
            
            if (loadingDiv) loadingDiv.style.display = 'none';
            if (errorDiv) {
                errorDiv.style.display = 'flex';
                const errorMsg = errorDiv.querySelector('.error-message');
                if (errorMsg) {
                    errorMsg.textContent = error.message || 'Unknown error occurred';
                }
            }
            
            this.emit('notification', {
                type: 'error',
                message: 'Failed to load templates: ' + error.message
            });
        }
    }
    
    /**
     * Switch active room
     */
    switchRoom(room) {
        if (this.currentRoom === room || !this.containerElement) return;
        
        this.currentRoom = room;
        
        // Hide form if it's visible when switching rooms
        if (this.isFormVisible) {
            this.hideForm();
        }
        
        // Only update tabs and lists within this container
        this.containerElement.querySelectorAll('.room-tab').forEach(tab => {
            tab.classList.toggle('active', tab.dataset.room === room);
        });
        
        this.containerElement.querySelectorAll('.template-list-container, [class*="-list-container"]').forEach(container => {
            container.classList.toggle('active', container.dataset.room === room);
        });
        
        const roomSelect = this.containerElement.querySelector('#room_type, [name="room_type"]');
        if (roomSelect) {
            roomSelect.value = room;
        }
    }
    
    /**
     * Render all room template lists
     */
    renderAllRooms() {
        ['problem', 'solution', 'offer'].forEach(room => {
            this.renderRoom(room);
        });
    }
    
    /**
     * Render templates for a specific room
     */
    renderRoom(room) {
        const container = this.containerElement.querySelector(`.template-list-container[data-room="${room}"]`);
        if (!container) return;
        
        const templates = this.templates[room] || [];
        const maxTemplates = 5;
        
        // Total count includes both campaign and global
        const totalCount = templates.length;
        const canAddMore = totalCount < maxTemplates;
        
        let html = `
            <div class="template-list-header">
                <h3>
                    <i class="fas fa-robot"></i>
                    ${totalCount} Template${totalCount !== 1 ? 's' : ''}
                </h3>
                ${canAddMore ? `
                    <button class="btn btn-primary create-template-btn" data-room="${room}">
                        <i class="fas fa-plus"></i> Create Template
                    </button>
                ` : `
                    <div class="max-templates-notice">
                        <i class="fas fa-info-circle"></i>
                        Maximum templates reached (5)
                    </div>
                `}
            </div>
        `;
        
        if (templates.length === 0) {
            html += this.renderEmptyState(room);
        } else {
            html += '<div class="template-list">';
            templates.forEach(template => {
                html += this.renderTemplateCard(template);
            });
            html += '</div>';
        }
        
        container.innerHTML = html;
        this.attachCardListeners(room);
    }
    
    /**
     * Render empty state
     */
    renderEmptyState(room) {
        return `
            <div class="empty-state">
                <i class="fas fa-robot"></i>
                <h4>No templates available</h4>
                <p>Select a template to modify/manage for the ${room} room</p>
                <button class="btn btn-primary create-template-btn" data-room="${room}">
                    <i class="fas fa-plus"></i> Create First Template
                </button>
            </div>
        `;
    }
    
    /**
     * Render template card
     */
    renderTemplateCard(template) {
        const promptPreview = template.prompt_template?.persona 
            ? template.prompt_template.persona.substring(0, 200) + '...'
            : 'No persona defined';
        
        const isGlobal = template.is_global;
        const isGlobalInCampaignMode = isGlobal && !this.isGlobal;
        
        // For global templates in campaign mode: Different button text and behavior
        const editButtonHtml = isGlobalInCampaignMode
            ? `<button class="btn btn-primary btn-sm edit-template-btn" 
                    data-template-id="${template.id}"
                    data-is-global="1">
                <i class="fas fa-copy"></i> Create Campaign Copy
            </button>`
            : `<button class="btn btn-secondary btn-sm edit-template-btn" 
                    data-template-id="${template.id}"
                    data-is-global="0">
                <i class="fas fa-edit"></i> Edit
            </button>`;
        
        // Duplicate button: Hide for global templates in campaign mode (redundant with "Create Campaign Copy")
        const duplicateButtonHtml = isGlobalInCampaignMode
            ? ''
            : `<button class="btn btn-secondary btn-sm duplicate-template-btn" 
                    data-template-id="${template.id}">
                <i class="fas fa-copy"></i> Duplicate
            </button>`;
        
        const deleteDisabled = isGlobalInCampaignMode ? 'disabled' : '';
        
        return `
            <div class="template-card ${isGlobalInCampaignMode ? 'template-card-global' : ''}" data-template-id="${template.id}">
                <div class="template-card-header">
                    <h4>
                        <i class="fas fa-file-alt"></i>
                        ${this.escapeHtml(template.template_name)}
                    </h4>
                    <div class="template-badges">
                        ${template.template_order >= 0 ? `
                            <span class="badge-default">Order: ${template.template_order}</span>
                        ` : ''}
                    </div>
                </div>
                <div class="template-card-body">
                    <div class="template-prompt-preview">
                        ${this.escapeHtml(promptPreview)}
                    </div>
                    <div class="template-meta">
                        <span>
                            <i class="fas fa-calendar"></i>
                            ${this.formatDate(template.created_at)}
                        </span>
                        ${template.updated_at !== template.created_at ? `
                            <span>
                                <i class="fas fa-edit"></i>
                                Updated ${this.formatDate(template.updated_at)}
                            </span>
                        ` : ''}
                    </div>
                </div>
                <div class="template-card-actions">
                    ${editButtonHtml}
                    ${duplicateButtonHtml}
                    <button class="btn btn-danger btn-sm delete-template-btn" 
                            data-template-id="${template.id}"
                            ${deleteDisabled}>
                        <i class="fas fa-trash"></i> Delete
                    </button>
                </div>
            </div>
        `;
    }
    
    /**
     * Attach card event listeners
     */
    attachCardListeners(room) {
        const container = this.containerElement.querySelector(`.template-list-container[data-room="${room}"]`);
        if (!container) return;
        
        container.querySelectorAll('.create-template-btn').forEach(btn => {
            btn.addEventListener('click', () => this.showCreateForm(room));
        });
        
        container.querySelectorAll('.edit-template-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const templateId = parseInt(e.currentTarget.dataset.templateId);
                const isGlobal = e.currentTarget.dataset.isGlobal === '1';
                this.showEditForm(templateId, isGlobal);
            });
        });
        
        container.querySelectorAll('.duplicate-template-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const templateId = parseInt(e.currentTarget.dataset.templateId);
                this.duplicateTemplate(templateId);
            });
        });
        
        container.querySelectorAll('.delete-template-btn').forEach(btn => {
            if (!btn.disabled) {
                btn.addEventListener('click', (e) => {
                    const templateId = parseInt(e.currentTarget.dataset.templateId);
                    this.deleteTemplate(templateId);
                });
            }
        });
    }
    
    /**
     * Show create form
     */
    showCreateForm(room) {
        this.editingTemplateId = null;
        this.editingGlobalTemplate = false;
        this.currentRoom = room;
        
        const form = this.containerElement.querySelector('#template-form');
        if (form) {
            form.reset();
        }
        
        const roomSelect = this.containerElement.querySelector('#room_type');
        if (roomSelect) {
            roomSelect.value = room;
        }
        
        const formTitle = this.containerElement.querySelector('.template-form-title');
        if (formTitle) {
            const templateType = this.isGlobal ? 'Global' : 'Campaign';
            formTitle.innerHTML = `<i class="fas fa-robot"></i> Create ${templateType} AI Prompt Template`;
        }
        
        this.showForm();
    }
    
    /**
     * Show edit form
     * If editing a global template in campaign mode, this will create a campaign copy
     */
    showEditForm(templateId, isGlobal = false) {
        const template = this.findTemplateById(templateId);
        if (!template) {
            console.error('Template not found:', templateId);
            return;
        }
        
        // If editing global in campaign mode, treat as "create copy"
        if (isGlobal && !this.isGlobal) {
            this.editingTemplateId = null;
            this.editingGlobalTemplate = true;
            this.currentRoom = template.room_type;
            
            this.populateForm(template);
            
            // Update name to indicate it's a copy
            const nameInput = this.containerElement.querySelector('#template_name');
            if (nameInput) {
                nameInput.value = template.template_name + ' (Campaign)';
            }
            
            const formTitle = this.containerElement.querySelector('.template-form-title');
            if (formTitle) {
                formTitle.innerHTML = `<i class="fas fa-copy"></i> Create Campaign Copy of Global Template`;
            }
        } else {
            // Normal edit
            this.editingTemplateId = templateId;
            this.editingGlobalTemplate = false;
            this.currentRoom = template.room_type;
            
            this.populateForm(template);
            
            const formTitle = this.containerElement.querySelector('.template-form-title');
            if (formTitle) {
                const templateType = this.isGlobal ? 'Global' : 'Campaign';
                formTitle.innerHTML = `<i class="fas fa-edit"></i> Edit ${templateType} AI Prompt Template`;
            }
        }
        
        this.showForm();
    }
    
    /**
     * Show form
     */
    showForm() {
        const formContainer = this.containerElement.querySelector('#template-form-container');
        const listContainers = this.containerElement.querySelectorAll('.template-list-container');
        
        if (formContainer) {
            formContainer.style.display = 'block';
            this.isFormVisible = true;
        }
        
        // Hide all list containers by removing active class
        listContainers.forEach(container => {
            container.classList.remove('active');
        });
        
        const testSection = this.containerElement.querySelector('#test-results-section');
        if (testSection) {
            testSection.style.display = 'none';
        }
        
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
    
    /**
     * Hide form
     */
    hideForm() {
        const formContainer = this.containerElement.querySelector('#template-form-container');
        const listContainers = this.containerElement.querySelectorAll('.template-list-container');
        
        if (formContainer) {
            formContainer.style.display = 'none';
            this.isFormVisible = false;
        }
        
        // DON'T manipulate inline styles - let CSS classes handle visibility
        // Just ensure the active class is set on the current room
        listContainers.forEach(container => {
            container.classList.remove('active');
            if (container.dataset.room === this.currentRoom) {
                container.classList.add('active');
            }
        });
        
        const form = this.containerElement.querySelector('#template-form');
        if (form) {
            form.reset();
        }
        
        this.editingTemplateId = null;
        this.editingGlobalTemplate = false;
    }
    
    /**
     * Populate form with template data
     */
    populateForm(template) {
        const fields = {
            template_name: template.template_name,
            room_type: template.room_type,
            template_order: template.template_order || 0
        };
        
        Object.keys(fields).forEach(key => {
            const input = this.containerElement.querySelector(`#${key}`);
            if (input) {
                input.value = fields[key];
            }
        });
        
        if (template.prompt_template) {
            const promptFields = [
                'persona',
                'style',
                'output',
                'personalization',
                'constraints',
                'examples',
                'context'
            ];
            
            promptFields.forEach(field => {
                const textarea = this.containerElement.querySelector(`#prompt_${field}`);
                if (textarea && template.prompt_template[field]) {
                    textarea.value = template.prompt_template[field];
                }
            });
        }
    }
    
    /**
     * Handle form submission
     */
    async handleFormSubmit() {
        const campaignId = this.isGlobal ? 0 : this.stateManager?.getState()?.campaignId;
        
        if (!campaignId && campaignId !== 0) {
            this.emit('notification', {
                type: 'error',
                message: 'No campaign selected'
            });
            return;
        }
        
        const formData = this.gatherFormData();
        
        if (!this.validateFormData(formData)) {
            return;
        }
        
        try {
            let response;
            
            // If editing global template in campaign mode, create new (don't update)
            if (this.editingTemplateId && !this.editingGlobalTemplate) {
                response = await this.api.put(
                    `/templates/${this.editingTemplateId}`,
                    formData
                );
            } else {
                // Create new template
                const data = {
                    ...formData,
                    campaign_id: campaignId,
                    is_global: this.isGlobal ? 1 : 0
                };
                
                response = await this.api.post(`/templates`, data);
            }
            
            if (response.success) {
                const message = this.editingGlobalTemplate
                    ? 'Campaign template created from global template'
                    : this.editingTemplateId 
                        ? 'Template updated successfully' 
                        : 'Template created successfully';
                
                this.emit('notification', {
                    type: 'success',
                    message: message
                });
                
                await this.loadTemplates();
                this.hideForm();
            }
        } catch (error) {
            console.error('Failed to save template:', error);
            this.emit('notification', {
                type: 'error',
                message: 'Failed to save template: ' + error.message
            });
        }
    }
    
    /**
     * Gather form data
     */
    gatherFormData() {
        return {
            template_name: this.containerElement.querySelector('#template_name')?.value.trim(),
            room_type: this.containerElement.querySelector('#room_type')?.value,
            template_order: parseInt(this.containerElement.querySelector('#template_order')?.value) || 0,
            prompt_template: {
                persona: this.containerElement.querySelector('#prompt_persona')?.value.trim(),
                style: this.containerElement.querySelector('#prompt_style')?.value.trim(),
                output: this.containerElement.querySelector('#prompt_output')?.value.trim(),
                personalization: this.containerElement.querySelector('#prompt_personalization')?.value.trim(),
                constraints: this.containerElement.querySelector('#prompt_constraints')?.value.trim(),
                examples: this.containerElement.querySelector('#prompt_examples')?.value.trim(),
                context: this.containerElement.querySelector('#prompt_context')?.value.trim()
            }
        };
    }
    
    /**
     * Validate form data
     */
    validateFormData(data) {
        if (!data.template_name) {
            this.emit('notification', {
                type: 'error',
                message: 'Template name is required'
            });
            return false;
        }
        
        const hasContent = Object.values(data.prompt_template).some(val => val && val.length > 0);
        
        if (!hasContent) {
            this.emit('notification', {
                type: 'error',
                message: 'Please fill out at least one prompt section'
            });
            return false;
        }
        
        return true;
    }
    
    /**
     * Handle test prompt - shows assembled prompt
     */
    handleTestPrompt() {
        const testSection = this.containerElement.querySelector('#test-results-section');
        const testContent = this.containerElement.querySelector('#test-results-content');
        
        if (testSection) {
            testSection.style.display = 'block';
            if (testContent) {
                testContent.style.display = 'block';
            }
            
            this.assemblePromptPreview();
            testSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }
    
    /**
     * Assemble prompt preview
     */
    assemblePromptPreview() {
        const preview = this.containerElement.querySelector('#assembled-prompt-preview');
        if (!preview) return;
        
        const sections = [
            { id: 'prompt_persona', title: 'PERSONA' },
            { id: 'prompt_style', title: 'STYLE RULES' },
            { id: 'prompt_output', title: 'OUTPUT SPECIFICATION' },
            { id: 'prompt_personalization', title: 'PERSONALIZATION' },
            { id: 'prompt_constraints', title: 'CONSTRAINTS' },
            { id: 'prompt_examples', title: 'EXAMPLES' },
            { id: 'prompt_context', title: 'CONTEXT INSTRUCTIONS' }
        ];
        
        let assembly = '';
        
        sections.forEach(section => {
            const value = this.containerElement.querySelector(`#${section.id}`)?.value.trim();
            if (value) {
                assembly += `\n=== ${section.title} ===\n${value}\n`;
            }
        });
        
        if (assembly) {
            preview.innerHTML = `<pre class="prompt-assembly">${this.escapeHtml(assembly)}</pre>`;
        } else {
            preview.innerHTML = '<p class="text-muted">Fill out the prompt sections above to see the assembled prompt.</p>';
        }
    }
    
    /**
     * Handle generate test email - calls Gemini API
     */
    async handleGenerateTestEmail() {
        const generateBtn = this.containerElement.querySelector('#generate-test-email-btn');
        const emailSection = this.containerElement.querySelector('#generated-email-section');
        
        if (!generateBtn) return;
        
        // Get campaign ID from state
        const campaignId = this.isGlobal ? 0 : this.stateManager?.getState()?.campaignId;
        
        if (!campaignId && campaignId !== 0) {
            this.emit('notification', {
                type: 'error',
                message: 'No campaign selected'
            });
            return;
        }
        
        // Gather prompt template data
        const promptTemplate = this.gatherFormData().prompt_template;
        
        // Validate at least one section has content
        if (!Object.values(promptTemplate).some(v => v && v.length > 0)) {
            this.emit('notification', {
                type: 'error',
                message: 'Please fill out at least one prompt section'
            });
            return;
        }
        
        // Update button state
        generateBtn.disabled = true;
        generateBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
        
        try {
            // Call the email generation test endpoint
            const response = await this.api.post('/emails/test-prompt', {
                prompt_template: promptTemplate,
                campaign_id: campaignId,
                room_type: this.currentRoom
            });
            
            if (response.success && emailSection) {
                emailSection.style.display = 'block';
                
                const output = this.containerElement.querySelector('#generated-email-output');
                const stats = this.containerElement.querySelector('#generation-stats');
                
                if (output) {
                    // Display generated email with mock prospect info
                    let mockProspectInfo = '';
                    if (response.data.mock_prospect) {
                        mockProspectInfo = `
                            <div class="mock-prospect-info">
                                <small>
                                    <i class="fas fa-user"></i>
                                    Mock prospect: ${this.escapeHtml(response.data.mock_prospect.contact_name)} 
                                    at ${this.escapeHtml(response.data.mock_prospect.company_name)}
                                    (${this.escapeHtml(response.data.mock_prospect.job_title)})
                                </small>
                            </div>
                        `;
                    }
                    
                    output.innerHTML = `
                        ${mockProspectInfo}
                        <div class="email-subjects">
                            <div class="subject-line primary">
                                <strong>Subject:</strong> ${this.escapeHtml(response.data.subject)}
                            </div>
                        </div>
                        <div class="email-body">
                            ${response.data.body_html}
                        </div>
                    `;
                }
                
                if (stats) {
                    // Display token usage and cost
                    const cost = response.data.usage.cost;
                    stats.innerHTML = `
                        <div class="stat-item">
                            <span class="stat-label">Prompt Tokens:</span>
                            <span class="stat-value">${response.data.usage.prompt_tokens}</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Completion Tokens:</span>
                            <span class="stat-value">${response.data.usage.completion_tokens}</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Total:</span>
                            <span class="stat-value">${response.data.usage.total_tokens}</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Cost:</span>
                            <span class="stat-value">$${cost.toFixed(4)}</span>
                        </div>
                    `;
                }
                
                // Scroll to results
                emailSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
                
                this.emit('notification', {
                    type: 'success',
                    message: 'Test email generated successfully'
                });
            }
        } catch (error) {
            console.error('Test email generation error:', error);
            
            this.emit('notification', {
                type: 'error',
                message: 'Failed to generate test email: ' + error.message
            });
        } finally {
            // Reset button state
            generateBtn.disabled = false;
            generateBtn.innerHTML = '<i class="fas fa-magic"></i> Generate Test Email';
        }
    }
    
    /**
     * Duplicate template
     */
    async duplicateTemplate(templateId) {
        const template = this.findTemplateById(templateId);
        if (!template) return;
        
        const campaignId = this.isGlobal ? 0 : this.stateManager?.getState()?.campaignId;
        
        try {
            const duplicateData = {
                template_name: template.template_name + ' (Copy)',
                room_type: template.room_type,
                prompt_template: template.prompt_template,
                template_order: template.template_order,
                campaign_id: campaignId,
                is_global: this.isGlobal ? 1 : 0
            };
            
            const response = await this.api.post(`/templates`, duplicateData);
            
            if (response.success) {
                this.emit('notification', {
                    type: 'success',
                    message: 'Template duplicated successfully'
                });
                
                await this.loadTemplates();
            }
        } catch (error) {
            this.emit('notification', {
                type: 'error',
                message: 'Failed to duplicate template: ' + error.message
            });
        }
    }
    
    /**
     * Delete template
     */
    async deleteTemplate(templateId) {
        if (!confirm('Are you sure you want to delete this template? This action cannot be undone.')) {
            return;
        }
        
        try {
            const response = await this.api.delete(`/templates/${templateId}`);
            
            if (response.success) {
                this.emit('notification', {
                    type: 'success',
                    message: 'Template deleted successfully'
                });
                
                await this.loadTemplates();
            }
        } catch (error) {
            this.emit('notification', {
                type: 'error',
                message: 'Failed to delete template: ' + error.message
            });
        }
    }
    
    /**
     * Update tab statuses
     */
    updateTabStatuses() {
        ['problem', 'solution', 'offer'].forEach(room => {
            const tab = this.containerElement.querySelector(`.room-tab[data-room="${room}"]`);
            const statusSpan = tab?.querySelector('.tab-status');
            
            if (statusSpan) {
                const count = this.templates[room].length;
                if (count > 0) {
                    statusSpan.innerHTML = `<i class="fas fa-check-circle"></i> ${count}`;
                    statusSpan.classList.add('complete');
                } else {
                    statusSpan.innerHTML = '';
                    statusSpan.classList.remove('complete');
                }
            }
        });
    }
    
    /**
     * Find template by ID
     */
    findTemplateById(templateId) {
        for (const room in this.templates) {
            const template = this.templates[room].find(t => t.id === templateId);
            if (template) return template;
        }
        return null;
    }
    
    /**
     * Get all templates (for workflow validation)
     */
    getTemplates() {
        return this.templates;
    }

    /**
     * Check if all rooms have at least one template
     */
    hasTemplatesForAllRooms() {
        return this.templates.problem.length > 0 &&
            this.templates.solution.length > 0 &&
            this.templates.offer.length > 0;
    }

    /**
     * Get template count by room
     */
    getTemplateCount(room) {
        return this.templates[room]?.length || 0;
    }

    /**
     * Utility: Escape HTML
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    /**
     * Utility: Format date
     */
    formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', { 
            month: 'short', 
            day: 'numeric', 
            year: 'numeric' 
        });
    }
}