/**
 * Content Links Manager Module
 * 
 * Manages content links for campaigns with room-based organization,
 * drag-and-drop reordering, and active/inactive status toggling.
 * 
 * @package DirectReach_Campaign_Builder
 * @since 2.0.0
 */

import EventEmitter from '../utils/event-emitter.js';
import APIClient from '../utils/api-client.js';

export default class ContentLinksManager extends EventEmitter {
    /**
     * Constructor
     * 
     * @param {Object} config - Configuration object
     * @param {Object} stateManager - State manager instance
     */
    constructor(config, stateManager, options = {}) {
        super();
        
        this.config = config;
        this.stateManager = stateManager;
        this.api = new APIClient(config.apiUrl, config.nonce);
        
        // Container scoping
        this.containerSelector = options.containerSelector || '.content-links-step-container';
        this.containerElement = null;
        
        this.links = {
            problem: [],
            solution: [],
            offer: []
        };
        
        this.currentRoom = 'problem';
        this.editingLinkId = null;
        this.isFormVisible = false;
        this.draggedItem = null;
        
        this.elements = {};
        
        this.init();
    }
    
    /**
     * Initialize the manager
     */
    async init() {
        // Find the container element
        this.containerElement = document.querySelector(this.containerSelector);
        if (!this.containerElement) {
            console.error(`ContentLinksManager: Container not found: ${this.containerSelector}`);
            return;
        }
        
        // Cache scoped elements
        this.elements = {
            stepContainer: this.containerElement.querySelector('#content-links-step'),
            roomTabs: this.containerElement.querySelectorAll('.room-tab'),
            linkListContainers: this.containerElement.querySelectorAll('.link-list-container'),
            createBtns: this.containerElement.querySelectorAll('.create-link-btn'),
            form: this.containerElement.querySelector('#content-link-form'),
            formContainer: this.containerElement.querySelector('#link-form-container'),
            listView: this.containerElement.querySelector('#links-list-view'),
            cancelBtn: this.containerElement.querySelector('#cancel-link-btn'),
            saveBtn: this.containerElement.querySelector('#save-link-btn'),
            backBtn: this.containerElement.querySelector('.btn-back-to-list'),
            loadingState: this.containerElement.querySelector('#links-loading'),
            errorState: this.containerElement.querySelector('#links-error')
        };
        
        this.attachEventListeners();
        await this.loadLinks();
    }
    
    /**
     * Attach event listeners
     */
    attachEventListeners() {
        if (!this.containerElement) return;
        
        // Room tabs - use event delegation with scoped query
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
        
        // Form submission
        if (this.elements.form) {
            this.elements.form.addEventListener('submit', (e) => {
                e.preventDefault();
                this.handleFormSubmit();
            });
        }
        
        // Cancel button
        if (this.elements.cancelBtn) {
            this.elements.cancelBtn.addEventListener('click', () => {
                this.hideForm();
            });
        }
        
        // Back button
        if (this.elements.backBtn) {
            this.elements.backBtn.addEventListener('click', () => {
                this.hideForm();
            });
        }
    }
    
    /**
     * Load content links for current campaign
     */
    async loadLinks() {
        const campaignId = this.stateManager?.getState()?.campaignId;
        
        if (!campaignId) {
            console.warn('No campaign selected, cannot load links');
            return;
        }
        
        this.showLoadingState();
        
        try {
            const response = await this.api.get(`/campaigns/${campaignId}/content-links`);
            
            if (response.success) {
                this.links = response.data;
                this.renderAllRooms();
                this.updateTabCounts();
                this.hideLoadingState();
                
                this.emit('links:loaded', this.links);
            }
        } catch (error) {
            console.error('Failed to load content links:', error);
            this.showErrorState(error.message);
            this.emit('notification', {
                type: 'error',
                message: 'Failed to load content links: ' + error.message
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
        
        // Update tabs within this container only
        this.containerElement.querySelectorAll('.room-tab').forEach(tab => {
            tab.classList.toggle('active', tab.dataset.room === room);
        });
        
        // Update list containers within this container only
        this.containerElement.querySelectorAll('.link-list-container').forEach(container => {
            container.classList.toggle('active', container.dataset.room === room);
        });
        
        // Update form room select if visible
        const roomSelect = this.containerElement.querySelector('#room_type');
        if (roomSelect) {
            roomSelect.value = room;
        }
    }
    
    /**
     * Render all room lists
     */
    renderAllRooms() {
        ['problem', 'solution', 'offer'].forEach(room => {
            this.renderRoom(room);
        });
    }
    
    /**
     * Render links for a specific room
     */
    renderRoom(room) {
        const container = this.containerElement?.querySelector(`.link-list-container[data-room="${room}"]`);
        if (!container) return;
        
        const links = this.links[room] || [];
        
        let html = `
            <div class="link-list-header">
                <h3>
                    <i class="fas fa-link"></i>
                    ${links.length} Link${links.length !== 1 ? 's' : ''}
                </h3>
                <button class="btn btn-primary create-link-btn" data-room="${room}">
                    <i class="fas fa-plus"></i> Add Link
                </button>
            </div>
        `;
        
        if (links.length === 0) {
            html += this.renderEmptyState(room);
        } else {
            html += '<div class="link-list" data-room="' + room + '">';
            links.forEach(link => {
                html += this.renderLinkCard(link);
            });
            html += '</div>';
        }
        
        container.innerHTML = html;
        
        // Re-attach event listeners for this room
        this.attachCardListeners(room);
        this.initializeDragAndDrop(room);
    }
    
    /**
     * Render empty state
     */
    renderEmptyState(room) {
        const roomNames = {
            problem: 'Problem',
            solution: 'Solution',
            offer: 'Offer'
        };
        
        return `
            <div class="empty-state">
                <i class="fas fa-link"></i>
                <h4>No content links yet</h4>
                <p>Add links to content that will be shared with prospects in the ${roomNames[room]} room</p>
                <button class="btn btn-primary create-link-btn" data-room="${room}">
                    <i class="fas fa-plus"></i> Add First Link
                </button>
            </div>
        `;
    }
    
    /**
     * Render link card
     */
    renderLinkCard(link) {
        const activeClass = link.is_active ? 'active' : 'inactive';
        const activeIcon = link.is_active ? 'fa-check-circle' : 'fa-times-circle';
        const activeLabel = link.is_active ? 'Active' : 'Inactive';
        
        return `
            <div class="link-card ${activeClass}" 
                 data-link-id="${link.id}"
                 draggable="true">
                <div class="drag-handle">
                    <i class="fas fa-grip-vertical"></i>
                </div>
                <div class="link-card-content">
                    <div class="link-card-header">
                        <h4 class="link-title">
                            <i class="fas fa-file-alt"></i>
                            ${this.escapeHtml(link.link_title)}
                        </h4>
                        <div class="link-badges">
                            <span class="badge badge-${link.is_active ? 'success' : 'inactive'}">
                                <i class="fas ${activeIcon}"></i> ${activeLabel}
                            </span>
                        </div>
                    </div>
                    <div class="link-card-body">
                        <div class="link-url">
                            <i class="fas fa-external-link-alt"></i>
                            <a href="${this.escapeHtml(link.link_url)}" target="_blank" rel="noopener">
                                ${this.truncateUrl(link.link_url)}
                            </a>
                        </div>
                        ${link.url_summary ? `
                            <div class="link-summary">
                                <strong>AI Summary:</strong>
                                ${this.escapeHtml(link.url_summary)}
                            </div>
                        ` : ''}
                        ${link.link_description ? `
                            <div class="link-description">
                                ${this.escapeHtml(link.link_description)}
                            </div>
                        ` : ''}
                    </div>
                    <div class="link-card-actions">
                        <button class="btn btn-secondary btn-sm edit-link-btn" 
                                data-link-id="${link.id}">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <button class="btn btn-secondary btn-sm toggle-active-btn" 
                                data-link-id="${link.id}"
                                data-is-active="${link.is_active}">
                            <i class="fas fa-${link.is_active ? 'eye-slash' : 'eye'}"></i>
                            ${link.is_active ? 'Deactivate' : 'Activate'}
                        </button>
                        <button class="btn btn-danger btn-sm delete-link-btn" 
                                data-link-id="${link.id}">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </div>
                </div>
            </div>
        `;
    }
    
    /**
     * Attach card event listeners
     */
    attachCardListeners(room) {
        const container = this.containerElement?.querySelector(`.link-list-container[data-room="${room}"]`);
        if (!container) return;
        
        // Create link buttons
        container.querySelectorAll('.create-link-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                this.showCreateForm(room);
            });
        });
        
        // Edit buttons
        container.querySelectorAll('.edit-link-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const linkId = parseInt(btn.dataset.linkId);
                this.showEditForm(linkId);
            });
        });
        
        // Toggle active buttons
        container.querySelectorAll('.toggle-active-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const linkId = parseInt(btn.dataset.linkId);
                const isActive = btn.dataset.isActive === 'true';
                this.toggleLinkActive(linkId, !isActive);
            });
        });
        
        // Delete buttons
        container.querySelectorAll('.delete-link-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const linkId = parseInt(btn.dataset.linkId);
                this.deleteLink(linkId);
            });
        });
    }
    
    /**
     * Initialize drag and drop for a room
     */
    initializeDragAndDrop(room) {
        const linkList = this.containerElement?.querySelector(`.link-list[data-room="${room}"]`);
        if (!linkList) return;
        
        const cards = linkList.querySelectorAll('.link-card');
        
        cards.forEach(card => {
            // Drag start
            card.addEventListener('dragstart', (e) => {
                this.draggedItem = card;
                card.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'move';
            });
            
            // Drag end
            card.addEventListener('dragend', (e) => {
                card.classList.remove('dragging');
                this.draggedItem = null;
            });
            
            // Drag over
            card.addEventListener('dragover', (e) => {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                
                if (this.draggedItem && this.draggedItem !== card) {
                    const rect = card.getBoundingClientRect();
                    const midY = rect.top + rect.height / 2;
                    
                    if (e.clientY < midY) {
                        linkList.insertBefore(this.draggedItem, card);
                    } else {
                        linkList.insertBefore(this.draggedItem, card.nextSibling);
                    }
                }
            });
        });
        
        // Drop on list
        linkList.addEventListener('drop', (e) => {
            e.preventDefault();
            this.saveNewOrder(room);
        });
    }
    
    /**
     * Save new order after drag and drop
     */
    async saveNewOrder(room) {
        const linkList = this.containerElement?.querySelector(`.link-list[data-room="${room}"]`);
        if (!linkList) return;
        
        const cards = linkList.querySelectorAll('.link-card');
        const updates = [];
        
        cards.forEach((card, index) => {
            const linkId = parseInt(card.dataset.linkId);
            updates.push({
                id: linkId,
                link_order: index
            });
        });
        
        try {
            await this.api.put('/content-links/reorder', {
                links: updates
            });
            
            // Update local order
            this.links[room].forEach(link => {
                const update = updates.find(u => u.id === link.id);
                if (update) {
                    link.link_order = update.link_order;
                }
            });
            
            // Re-sort
            this.links[room].sort((a, b) => a.link_order - b.link_order);
            
            this.emit('notification', {
                type: 'success',
                message: 'Link order saved'
            });
        } catch (error) {
            console.error('Failed to save order:', error);
            this.emit('notification', {
                type: 'error',
                message: 'Failed to save order'
            });
            
            // Reload to restore original order
            await this.loadLinks();
        }
    }
    
    /**
     * Show create form
     */
    showCreateForm(room) {
        this.editingLinkId = null;
        this.currentRoom = room;
        
        if (this.elements.form) {
            this.elements.form.reset();
        }
        
        const roomSelect = this.containerElement?.querySelector('#room_type');
        if (roomSelect) {
            roomSelect.value = room;
        }
        
        const formTitle = this.containerElement?.querySelector('#form-title');
        if (formTitle) {
            formTitle.textContent = 'Add Content Link';
        }
        
        this.showForm();
    }
    
    /**
     * Show edit form
     */
    showEditForm(linkId) {
        const link = this.findLinkById(linkId);
        if (!link) {
            console.error('Link not found:', linkId);
            return;
        }
        
        this.editingLinkId = linkId;
        this.currentRoom = link.room_type;
        
        const formTitle = this.containerElement?.querySelector('#form-title');
        if (formTitle) {
            formTitle.textContent = 'Edit Content Link';
        }
        
        this.populateForm(link);
        this.showForm();
    }
    
    /**
     * Show form
     */
    showForm() {
        if (this.elements.formContainer) {
            this.elements.formContainer.style.display = 'block';
            this.isFormVisible = true;
        }
        
        if (this.elements.listView) {
            this.elements.listView.style.display = 'none';
        }
        
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
    
    /**
     * Hide form
     */
    hideForm() {
        if (this.elements.formContainer) {
            this.elements.formContainer.style.display = 'none';
            this.isFormVisible = false;
        }
        
        if (this.elements.listView) {
            this.elements.listView.style.display = 'block';
        }
        
        if (this.elements.form) {
            this.elements.form.reset();
        }
        
        this.editingLinkId = null;
    }
    
    /**
     * Populate form with link data
     */
    populateForm(link) {
        const fields = {
            room_type: link.room_type,
            link_title: link.link_title,
            link_url: link.link_url,
            url_summary: link.url_summary,
            link_description: link.link_description,
            is_active: link.is_active
        };
        
        Object.keys(fields).forEach(key => {
            const input = this.containerElement?.querySelector(`#${key}`);
            if (input) {
                if (input.type === 'checkbox') {
                    input.checked = fields[key];
                } else {
                    input.value = fields[key] || '';
                }
            }
        });
    }
    
    /**
     * Handle form submission
     */
    async handleFormSubmit() {
        const campaignId = this.stateManager?.getState()?.campaignId;
        
        if (!campaignId) {
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
        
        const saveBtn = this.elements.saveBtn;
        const originalText = saveBtn.innerHTML;
        
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        
        try {
            let response;
            
            if (this.editingLinkId) {
                // Update existing link
                response = await this.api.put(
                    `/content-links/${this.editingLinkId}`,
                    formData
                );
            } else {
                // Create new link
                response = await this.api.post(
                    `/campaigns/${campaignId}/content-links`,
                    formData
                );
            }
            
            if (response.success) {
                this.emit('notification', {
                    type: 'success',
                    message: this.editingLinkId 
                        ? 'Link updated successfully' 
                        : 'Link created successfully'
                });
                
                await this.loadLinks();
                this.hideForm();
                
                // Switch to the room of the saved link
                this.switchRoom(formData.room_type);
            }
        } catch (error) {
            console.error('Failed to save link:', error);
            this.emit('notification', {
                type: 'error',
                message: 'Failed to save link: ' + error.message
            });
        } finally {
            saveBtn.disabled = false;
            saveBtn.innerHTML = originalText;
        }
    }
    
    /**
     * Gather form data
     */
    gatherFormData() {
        return {
            room_type: this.containerElement?.querySelector('#room_type')?.value,
            link_title: this.containerElement?.querySelector('#link_title')?.value.trim(),
            link_url: this.containerElement?.querySelector('#link_url')?.value.trim(),
            url_summary: this.containerElement?.querySelector('#url_summary')?.value.trim(),
            link_description: this.containerElement?.querySelector('#link_description')?.value.trim(),
            is_active: this.containerElement?.querySelector('#is_active')?.checked ?? true
        };
    }
    
    /**
     * Validate form data
     */
    validateFormData(data) {
        if (!data.link_title) {
            this.emit('notification', {
                type: 'error',
                message: 'Link title is required'
            });
            return false;
        }
        
        if (!data.link_url) {
            this.emit('notification', {
                type: 'error',
                message: 'Link URL is required'
            });
            return false;
        }
        
        if (!data.url_summary) {
            this.emit('notification', {
                type: 'error',
                message: 'URL summary is required for AI'
            });
            return false;
        }
        
        // Basic URL validation
        try {
            new URL(data.link_url);
        } catch (e) {
            this.emit('notification', {
                type: 'error',
                message: 'Invalid URL format'
            });
            return false;
        }
        
        return true;
    }
    
    /**
     * Toggle link active status
     */
    async toggleLinkActive(linkId, isActive) {
        try {
            const response = await this.api.put(`/content-links/${linkId}`, {
                is_active: isActive
            });
            
            if (response.success) {
                // Update local state
                const link = this.findLinkById(linkId);
                if (link) {
                    link.is_active = isActive;
                }
                
                // Re-render room
                const link_obj = this.findLinkById(linkId);
                if (link_obj) {
                    this.renderRoom(link_obj.room_type);
                }
                
                this.emit('notification', {
                    type: 'success',
                    message: `Link ${isActive ? 'activated' : 'deactivated'}`
                });
            }
        } catch (error) {
            console.error('Failed to toggle link:', error);
            this.emit('notification', {
                type: 'error',
                message: 'Failed to update link status'
            });
        }
    }
    
    /**
     * Delete link
     */
    async deleteLink(linkId) {
        const link = this.findLinkById(linkId);
        if (!link) return;
        
        const confirmed = confirm(`Delete "${link.link_title}"? This cannot be undone.`);
        if (!confirmed) return;
        
        try {
            const response = await this.api.delete(`/content-links/${linkId}`);
            
            if (response.success) {
                // Remove from local state
                this.links[link.room_type] = this.links[link.room_type].filter(
                    l => l.id !== linkId
                );
                
                // Re-render room
                this.renderRoom(link.room_type);
                this.updateTabCounts();
                
                this.emit('notification', {
                    type: 'success',
                    message: 'Link deleted successfully'
                });
            }
        } catch (error) {
            console.error('Failed to delete link:', error);
            this.emit('notification', {
                type: 'error',
                message: 'Failed to delete link'
            });
        }
    }
    
    /**
     * Update tab counts
     */
    updateTabCounts() {
        ['problem', 'solution', 'offer'].forEach(room => {
            const tab = this.containerElement?.querySelector(`.room-tab[data-room="${room}"]`);
            const countSpan = tab?.querySelector('.tab-count');
            
            if (countSpan) {
                const count = this.links[room].length;
                countSpan.textContent = count;
                
                if (count > 0) {
                    countSpan.classList.add('has-items');
                } else {
                    countSpan.classList.remove('has-items');
                }
            }
        });
    }
    
    /**
     * Show loading state
     */
    showLoadingState() {
        if (this.elements.loadingState) {
            this.elements.loadingState.style.display = 'flex';
        }
        if (this.elements.listView) {
            this.elements.listView.style.display = 'none';
        }
        if (this.elements.errorState) {
            this.elements.errorState.style.display = 'none';
        }
    }
    
    /**
     * Hide loading state
     */
    hideLoadingState() {
        if (this.elements.loadingState) {
            this.elements.loadingState.style.display = 'none';
        }
        if (this.elements.listView) {
            this.elements.listView.style.display = 'block';
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
        if (this.elements.listView) {
            this.elements.listView.style.display = 'none';
        }
    }
    
    /**
     * Find link by ID
     */
    findLinkById(linkId) {
        for (const room in this.links) {
            const link = this.links[room].find(l => l.id === linkId);
            if (link) return link;
        }
        return null;
    }
    
    /**
     * Get all links (for workflow validation)
     */
    getLinks() {
        return this.links;
    }
    
    /**
     * Check if all rooms have at least one active link
     */
    hasLinksForAllRooms() {
        return ['problem', 'solution', 'offer'].every(room => {
            const roomLinks = this.links[room] || [];
            const activeLinks = roomLinks.filter(link => link.is_active);
            return activeLinks.length > 0;
        });
    }
    
    /**
     * Get link count by room
     */
    getLinkCount(room) {
        return this.links[room]?.length || 0;
    }
    
    /**
     * Get active link count by room
     */
    getActiveLinkCount(room) {
        const roomLinks = this.links[room] || [];
        return roomLinks.filter(link => link.is_active).length;
    }
    
    /**
     * Escape HTML
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    /**
     * Truncate URL for display
     */
    truncateUrl(url, maxLength = 60) {
        if (url.length <= maxLength) return url;
        return url.substring(0, maxLength) + '...';
    }
}