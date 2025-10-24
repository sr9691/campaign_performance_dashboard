/**
 * Email Modal Manager
 * 
 * Handles AI-powered email generation workflow
 * 
 * @package DirectReach
 * @subpackage ReadingTheRoom
 * @since 1.0.0
 */

export default class EmailModalManager {
    constructor(APIClientClass, config) {
        // Create v2 API client for email generation
        this.api = new APIClientClass(
            `${config.siteUrl}/wp-json/directreach/v2`,
            config.nonce
        );
        
        this.config = config;
        this.modal = null;
        this.currentProspect = null;
        this.currentRoom = null;
        this.currentEmailNumber = null;
        this.generatedEmail = null;
        this.trackingId = null;
        
        this.init();
    }
    
    /**
     * Initialize modal
     */
    init() {
        this.createModal();
        this.attachEventListeners();
    }
    
    /**
     * Create modal HTML structure
     */
    createModal() {
        const modalHTML = `
            <div class="email-generation-modal" id="email-generation-modal">
                <div class="email-modal-overlay"></div>
                <div class="email-modal-content">
                    <!-- Header -->
                    <div class="email-modal-header">
                        <h3>
                            <i class="fas fa-robot"></i>
                            Generate Email - <span class="prospect-name"></span>
                        </h3>
                        <button class="modal-close" aria-label="Close modal">&times;</button>
                    </div>
                    
                    <!-- Body -->
                    <div class="email-modal-body">
                        <!-- Loading State -->
                        <div class="modal-body-section loading-state">
                            <div class="loading-spinner">
                                <i class="fas fa-spinner"></i>
                            </div>
                            <p class="loading-text">
                                Generating personalized email with AI...
                            </p>
                            <div class="loading-progress">
                                <div class="progress-bar"></div>
                            </div>
                            <small class="loading-hint">
                                This typically takes 5-10 seconds
                            </small>
                        </div>
                        
                        <!-- Email Preview State -->
                        <div class="modal-body-section email-preview">
                            <div class="email-metadata">
                                <div class="meta-item">
                                    <i class="fas fa-layer-group"></i>
                                    <span class="meta-label">Template:</span>
                                    <span class="meta-value template-name"></span>
                                    <span class="badge global-badge" style="display: none;">Global</span>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-link"></i>
                                    <span class="meta-label">Content Link:</span>
                                    <span class="meta-value link-title"></span>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-coins"></i>
                                    <span class="meta-label">Cost:</span>
                                    <span class="meta-value email-cost"></span>
                                </div>
                            </div>
                            
                            <div class="email-subject-section">
                                <label class="email-label">Subject:</label>
                                <div class="email-subject" contenteditable="true"></div>
                            </div>
                            
                            <div class="email-body-section">
                                <label class="email-label">Email Body:</label>
                                <div class="email-body" contenteditable="true"></div>
                            </div>
                            
                            <div class="email-tracking-info">
                                <i class="fas fa-info-circle"></i>
                                Email includes tracking pixel to confirm opens
                            </div>
                        </div>
                        
                        <!-- Error State -->
                        <div class="modal-body-section error-state">
                            <div class="error-icon">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <h4>AI Generation Failed</h4>
                            <p class="error-message"></p>
                            <p class="fallback-message">
                                Using template with basic personalization instead.
                            </p>
                        </div>
                    </div>
                    
                    <!-- Footer -->
                    <div class="email-modal-footer">
                        <button class="btn btn-secondary regenerate-btn">
                            <i class="fas fa-redo"></i> Regenerate
                        </button>
                        <button class="btn btn-primary copy-btn">
                            <i class="fas fa-copy"></i> Copy to Clipboard
                        </button>
                    </div>
                    
                    <!-- Success Toast -->
                    <div class="copy-success">
                        <i class="fas fa-check-circle"></i>
                        Email copied! Paste into your email client.
                    </div>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', modalHTML);
        this.modal = document.getElementById('email-generation-modal');
    }
    
    /**
     * Attach event listeners
     */
    attachEventListeners() {
        // Close button
        const closeBtn = this.modal.querySelector('.modal-close');
        closeBtn.addEventListener('click', () => this.hideModal());
        
        // Overlay click
        const overlay = this.modal.querySelector('.email-modal-overlay');
        overlay.addEventListener('click', () => this.hideModal());
        
        // Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.modal.classList.contains('active')) {
                this.hideModal();
            }
        });
        
        // Regenerate button
        const regenerateBtn = this.modal.querySelector('.regenerate-btn');
        regenerateBtn.addEventListener('click', () => this.regenerateEmail());
        
        // Copy button
        const copyBtn = this.modal.querySelector('.copy-btn');
        copyBtn.addEventListener('click', () => this.copyToClipboard());
        
        // Listen for email generation requests
        document.addEventListener('rtr:generate-email', (e) => {
            this.generateEmail(e.detail);
        });
    }
    
    /**
     * Generate email for prospect
     * 
     * @param {Object} data - { prospectId, room, emailNumber, prospectName }
     */
    async generateEmail(data) {
        const { prospectId, room, emailNumber, prospectName } = data;
        
        this.currentProspect = prospectId;
        this.currentRoom = room;
        this.currentEmailNumber = emailNumber;
        this.generatedEmail = null;
        this.trackingId = null;
        
        // Update prospect name in header
        const prospectNameEl = this.modal.querySelector('.prospect-name');
        prospectNameEl.textContent = prospectName || `Prospect ${prospectId}`;
        
        // Show modal in loading state
        this.showModal('loading');
        
        // Update email icon to generating state
        this.updateEmailIcon(prospectId, emailNumber, 'generating');
        
        try {
            const response = await this.api.post('/emails/generate', {
                prospect_id: prospectId,
                room_type: room,
                email_number: emailNumber
            });
            
            if (response.success && response.data) {
                this.generatedEmail = response.data;
                this.trackingId = response.data.email_tracking_id;
                
                this.displayEmail(response.data);
                this.showModal('preview');
                
                // Update email icon to ready state
                this.updateEmailIcon(prospectId, emailNumber, 'ready');
                
                // Emit event for analytics
                this.emitEvent('email:generated', {
                    prospectId,
                    emailData: response.data,
                    generationTime: response.meta?.generation_time_ms
                });
            } else {
                throw new Error(response.message || 'Generation failed');
            }
        } catch (error) {
            console.error('Email generation error:', error);
            this.showError(error.message);
            
            // Update email icon back to pending
            this.updateEmailIcon(prospectId, emailNumber, 'pending');
            
            // Emit error event
            this.emitEvent('email:error', { 
                prospectId,
                error: error.message 
            });
        }
    }
    
    /**
     * Display generated email in modal
     */
    displayEmail(emailData) {
        // Update metadata
        const templateName = this.modal.querySelector('.template-name');
        templateName.textContent = emailData.template_used?.name || 'Unknown Template';
        
        const globalBadge = this.modal.querySelector('.global-badge');
        if (emailData.template_used?.is_global) {
            globalBadge.style.display = 'inline-block';
        } else {
            globalBadge.style.display = 'none';
        }
        
        const linkTitle = this.modal.querySelector('.link-title');
        linkTitle.textContent = emailData.selected_url?.title || 'No link';
        
        const emailCost = this.modal.querySelector('.email-cost');
        const cost = emailData.tokens_used?.cost || 0;
        emailCost.textContent = `$${cost.toFixed(4)}`;
        
        // Update subject
        const subjectEl = this.modal.querySelector('.email-subject');
        subjectEl.textContent = emailData.subject || '';
        
        // Update body
        const bodyEl = this.modal.querySelector('.email-body');
        bodyEl.innerHTML = emailData.body_html || '';
        
        // Show metadata section
        const metadataSection = this.modal.querySelector('.email-metadata');
        metadataSection.style.display = 'flex';
    }
    
    /**
     * Copy email to clipboard
     */
    async copyToClipboard() {
        if (!this.generatedEmail) {
            console.error('No email to copy');
            return;
        }
        
        const copyBtn = this.modal.querySelector('.copy-btn');
        const originalHTML = copyBtn.innerHTML;
        
        try {
            // Get current content (may have been edited)
            const subjectEl = this.modal.querySelector('.email-subject');
            const bodyEl = this.modal.querySelector('.email-body');
            
            const subject = subjectEl.textContent;
            const bodyHTML = bodyEl.innerHTML;
            const bodyText = bodyEl.textContent;
            
            // Build email with tracking pixel using dynamic site URL
            const trackingToken = this.generatedEmail.tracking_token;
            const trackingPixelUrl = `${this.config.siteUrl}/wp-json/directreach/v2/emails/track-open/${trackingToken}`;
            
            const trackingPixel = trackingToken 
                ? `<img src="${trackingPixelUrl}" width="1" height="1" style="display:none;" alt="" />`
                : '';
            
            const emailHTMLWithTracking = bodyHTML + trackingPixel;
            
            // Copy to clipboard (HTML + plain text)
            await navigator.clipboard.write([
                new ClipboardItem({
                    'text/html': new Blob([emailHTMLWithTracking], { type: 'text/html' }),
                    'text/plain': new Blob([bodyText], { type: 'text/plain' })
                })
            ]);
            
            // Track as copied/sent
            await this.trackCopy();
            
            // Show success
            this.showCopySuccess();
            
            // Update button
            copyBtn.innerHTML = '<i class="fas fa-check"></i> Copied!';
            copyBtn.disabled = true;
            
            // Update email icon to sent state
            this.updateEmailIcon(this.currentProspect, this.currentEmailNumber, 'sent');
            
            // Emit event
            this.emitEvent('email:copied', {
                prospectId: this.currentProspect,
                emailNumber: this.currentEmailNumber,
                trackingId: this.trackingId
            });
            
            // Log tracking pixel URL for debugging
            console.log('[RTR Email] Tracking pixel URL:', trackingPixelUrl);
            
            // Close modal after short delay
            setTimeout(() => {
                copyBtn.innerHTML = originalHTML;
                copyBtn.disabled = false;
                this.hideModal();
            }, 2000);
            
        } catch (error) {
            console.error('Copy failed:', error);
            alert('Failed to copy email. Please try again or copy manually.');
            
            copyBtn.innerHTML = originalHTML;
        }
    }
    
    /**
     * Track email copy/send
     */
    async trackCopy() {
        if (!this.trackingId) {
            console.warn('No tracking ID available');
            return;
        }
        
        try {
            const response = await this.api.post('/emails/track-copy', {
                email_tracking_id: this.trackingId,
                prospect_id: this.currentProspect,
                url_included: this.generatedEmail.selected_url?.url || null
            });
            
            if (response.success) {
                console.log('Email tracked as copied:', response.data);
            }
        } catch (error) {
            console.error('Failed to track copy:', error);
            // Don't fail the copy operation
        }
    }
    
    /**
     * Regenerate email
     */
    async regenerateEmail() {
        if (!this.currentProspect || !this.currentRoom || !this.currentEmailNumber) {
            console.error('Missing regeneration data');
            return;
        }
        
        const prospectNameEl = this.modal.querySelector('.prospect-name');
        const prospectName = prospectNameEl.textContent;
        
        await this.generateEmail({
            prospectId: this.currentProspect,
            room: this.currentRoom,
            emailNumber: this.currentEmailNumber,
            prospectName: prospectName
        });
    }
    
    /**
     * Show modal in specific state
     * 
     * @param {string} state - 'loading', 'preview', or 'error'
     */
    showModal(state) {
        // Hide all sections
        this.modal.querySelectorAll('.modal-body-section').forEach(section => {
            section.classList.remove('active');
        });
        
        // Show requested section
        const stateMap = {
            'loading': '.loading-state',
            'preview': '.email-preview',
            'error': '.error-state'
        };
        
        const section = this.modal.querySelector(stateMap[state]);
        if (section) {
            section.classList.add('active');
        }
        
        // Show/hide footer buttons
        const footer = this.modal.querySelector('.email-modal-footer');
        if (state === 'loading') {
            footer.style.display = 'none';
        } else {
            footer.style.display = 'flex';
        }
        
        // Show modal
        this.modal.classList.add('active');
        
        // Focus management
        if (state === 'preview') {
            const subjectEl = this.modal.querySelector('.email-subject');
            if (subjectEl) {
                subjectEl.focus();
            }
        }
    }
    
    /**
     * Hide modal
     */
    hideModal() {
        this.modal.classList.remove('active');
        
        // Reset state
        this.currentProspect = null;
        this.currentRoom = null;
        this.currentEmailNumber = null;
        this.generatedEmail = null;
        this.trackingId = null;
        
        // Hide success toast
        const successToast = this.modal.querySelector('.copy-success');
        successToast.classList.remove('active');
    }
    
    /**
     * Show error state
     * 
     * @param {string} message - Error message
     */
    showError(message) {
        const errorMsg = this.modal.querySelector('.error-message');
        errorMsg.textContent = message || 'An unexpected error occurred.';
        
        this.showModal('error');
    }
    
    /**
     * Show copy success toast
     */
    showCopySuccess() {
        const successToast = this.modal.querySelector('.copy-success');
        successToast.classList.add('active');
        
        setTimeout(() => {
            successToast.classList.remove('active');
        }, 3000);
    }
    
    /**
     * Update email icon state in prospect row
     * 
     * @param {number} prospectId - Prospect ID
     * @param {number} emailNumber - Email sequence number
     * @param {string} state - Icon state (pending, generating, ready, sent, opened)
     */
    updateEmailIcon(prospectId, emailNumber, state) {
        const prospectRow = document.querySelector(`.prospect-row[data-prospect-id="${prospectId}"]`);
        if (!prospectRow) return;
        
        const emailIcon = prospectRow.querySelector(`.email-icons i[data-email-number="${emailNumber}"]`);
        if (!emailIcon) return;
        
        // Remove all state classes
        emailIcon.classList.remove(
            'email-not-sent',
            'email-pending',
            'email-generating',
            'email-ready',
            'email-sent',
            'email-opened'
        );
        
        // Remove any badges
        const existingBadge = emailIcon.querySelector('.sent-badge, .opened-badge');
        if (existingBadge) {
            existingBadge.remove();
        }
        
        // Add new state
        switch (state) {
            case 'pending':
                emailIcon.classList.add('email-pending');
                emailIcon.className = 'fas fa-envelope email-pending';
                emailIcon.title = `Email ${emailNumber}: Ready to generate`;
                emailIcon.style.cursor = 'pointer';
                break;
                
            case 'generating':
                emailIcon.classList.add('email-generating');
                emailIcon.className = 'fas fa-spinner email-generating';
                emailIcon.title = `Email ${emailNumber}: Generating...`;
                emailIcon.style.cursor = 'wait';
                break;
                
            case 'ready':
                emailIcon.classList.add('email-ready');
                emailIcon.className = 'fas fa-envelope-open email-ready';
                emailIcon.title = `Email ${emailNumber}: Ready to copy`;
                emailIcon.style.cursor = 'pointer';
                break;
                
            case 'sent':
                emailIcon.classList.add('email-sent');
                emailIcon.className = 'fas fa-paper-plane email-sent';
                emailIcon.title = `Email ${emailNumber}: Sent`;
                
                // Add checkmark badge
                const sentBadge = document.createElement('span');
                sentBadge.className = 'sent-badge';
                sentBadge.innerHTML = '✓';
                emailIcon.appendChild(sentBadge);
                break;
                
            case 'opened':
                emailIcon.classList.add('email-opened');
                emailIcon.className = 'fas fa-envelope-open-text email-opened';
                emailIcon.title = `Email ${emailNumber}: Opened`;
                
                // Add eye badge
                const openedBadge = document.createElement('span');
                openedBadge.className = 'opened-badge';
                openedBadge.innerHTML = '👁';
                emailIcon.appendChild(openedBadge);
                break;
                
            default:
                emailIcon.classList.add('email-not-sent');
                emailIcon.className = 'fas fa-envelope email-not-sent';
                emailIcon.title = `Email ${emailNumber}: Not sent`;
        }
    }
    
    /**
     * Emit custom event
     * 
     * @param {string} eventName - Event name
     * @param {Object} detail - Event detail data
     */
    emitEvent(eventName, detail) {
        document.dispatchEvent(new CustomEvent(`rtr:${eventName}`, { detail }));
    }
    
    /**
     * Show notification (helper)
     * 
     * @param {string} message - Notification message
     * @param {string} type - Notification type (success, error, info)
     */
    showNotification(message, type = 'info') {
        // Delegate to main dashboard notification system
        this.emitEvent('notification', { message, type });
    }
}