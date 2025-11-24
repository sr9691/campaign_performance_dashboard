/**
 * Enrichment Manager Module
 * 
 * Handles prospect contact enrichment functionality including:
 * - Searching for contacts via enrichment API
 * - Manual contact information entry
 * - Email finding for contacts
 * - Contact selection and saving
 */

export default class EnrichmentManager {
    constructor(config) {
        this.config = config;
        if (typeof config === 'string') {
            this.apiUrl = config;
        } else {
            this.apiUrl = config?.restUrl || config?.apiUrl || window.rtrDashboardConfig?.restUrl || window.rtrDashboardConfig?.apiUrl || '';
        }
        this.nonce = config.nonce;
        this.uiManager = null; // Will be set from outside
        this.prospectManager = null; // Will be set from outside
        
        this.init();
    }

    init() {
        this.attachEventListeners();
    }

    setUIManager(uiManager) {
        this.uiManager = uiManager;
    }

    setProspectManager(prospectManager) {
        this.prospectManager = prospectManager;
    }

    attachEventListeners() {
        // Delegate click events for enrichment actions
        document.addEventListener('click', (e) => {
            // Edit contact button
            const editContactBtn = e.target.closest('.rtr-edit-contact-btn');
            if (editContactBtn) {
                e.preventDefault();
                e.stopPropagation();
                const visitorId = editContactBtn.dataset.visitorId;
                const room = editContactBtn.dataset.room;
                this.showEnrichmentModal(visitorId, room);
            }
        });
    }

    showEnrichmentModal(visitorId, room) {
        if (!this.prospectManager) {
            console.error('ProspectManager not set on EnrichmentManager');
            return;
        }

        // Remove any existing enrichment modals first
        document.querySelectorAll('#enrichment-modal').forEach(m => m.remove());        

        // Get prospect data from ProspectManager
        const prospect = this.prospectManager.prospects[room]?.find(p => 
            p.visitor_id == visitorId || p.id == visitorId
        );
        
        if (!prospect) return;

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
        console.log('Finding email for contact:', contactData);
        
        // Show loading state on button
        const originalHtml = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Finding...';
        
        try {
            const url = `${this.apiUrl}/prospects/${visitorId}/find-email`;
            
            // Build request body with contact data
            const body = {
                member_id: contactData.document_id,
                first_name: contactData.first_name,
                last_name: contactData.last_name,
                company_domain: contactData.domain
            };
            
            console.log('Find email request body:', body);
            
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'X-WP-Nonce': this.nonce,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(body)
            });

            console.log('Find email response status:', response.status);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const result = await response.json();
            console.log('Find email response data:', result);
            
            if (result.success && result.data && result.data.email) {
                // Update the contact card to show the email
                const card = button.closest('.contact-card');
                const emailDisplay = card.querySelector('.contact-email');
                if (emailDisplay) {
                    emailDisplay.innerHTML = `
                        <i class="fas fa-envelope"></i> 
                        <strong>${this.escapeHtml(result.data.email)}</strong>
                        <span class="email-verified-badge" title="Found"><i class="fas fa-check-circle"></i></span>
                    `;
                }
                
                // Update contact data
                contactData.email = result.data.email;
                card.dataset.contact = JSON.stringify(contactData);
                
                // Change button to "Select Contact"
                button.outerHTML = `
                    <button class="btn btn-primary select-contact-btn" style="flex: 1;">
                        <i class="fas fa-check"></i> Select Contact
                    </button>
                `;
                
                // Re-attach handler for new select button
                const newSelectBtn = card.querySelector('.select-contact-btn');
                if (newSelectBtn) {
                    newSelectBtn.addEventListener('click', async (e) => {
                        e.stopPropagation();
                        const parentModal = document.getElementById('enrichment-modal');
                        const room = parentModal.querySelector('[data-room]')?.dataset.room;
                        await this.handleSelectContact(parentModal, visitorId, room, contactData);
                    });
                }
                
                if (this.uiManager) {
                    this.uiManager.notify(`Email found: ${result.data.email}`, 'success');
                }
            } else {
                throw new Error(result.message || 'Email not found');
            }
            
        } catch (error) {
            console.error('Find email failed:', error);
            button.disabled = false;
            button.innerHTML = originalHtml;
            if (this.uiManager) {
                this.uiManager.notify(error.message || 'Failed to find email', 'error');
            }
        }
    }

    async handleSelectContact(modal, visitorId, room, contactData) {
        console.log('Selecting contact:', contactData);
        
        try {
            const url = `${this.apiUrl}/prospects/${visitorId}/save-enrichment`;
            
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'X-WP-Nonce': this.nonce,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    contact_name: contactData.name,
                    contact_email: contactData.email,
                    job_title: contactData.job_title,
                    company_name: contactData.company_name,
                    linkedin_url: contactData.linkedin,
                    aleads_member_id: contactData.member_id
                })
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const result = await response.json();
            
            if (result.success) {
                if (this.uiManager) {
                    this.uiManager.notify('Contact information saved successfully', 'success');
                }
                
                // Dispatch event to update prospect list
                document.dispatchEvent(new CustomEvent('rtr:contactUpdated', {
                    detail: { 
                        visitorId,
                        contactData
                    }
                }));
                
                // Close modal
                modal.classList.remove('active');
                setTimeout(() => modal.remove(), 300);
                
                // Reload prospects if ProspectManager is available
                if (this.prospectManager && this.prospectManager.loadProspects) {
                    await this.prospectManager.loadProspects();
                }
            } else {
                throw new Error(result.message || 'Failed to save contact');
            }
            
        } catch (error) {
            console.error('Save contact failed:', error);
            if (this.uiManager) {
                this.uiManager.notify(error.message || 'Failed to save contact information', 'error');
            }
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
            if (this.uiManager) {
                this.uiManager.notify('Name is required', 'error');
            }
            return;
        }

        try {
            if (this.uiManager) {
                this.uiManager.showLoader('Saving contact information...');
            }

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

            if (this.uiManager) {
                this.uiManager.hideLoader();
                this.uiManager.notify('Contact information saved successfully', 'success');
            }
            
            // Close modal
            modal.classList.remove('active');
            setTimeout(() => modal.remove(), 300);
            
            // Reload the room to show updated data
            if (this.prospectManager) {
                await this.prospectManager.loadRoomProspects(room);
            }

        } catch (error) {
            console.error('Failed to save contact:', error);
            if (this.uiManager) {
                this.uiManager.hideLoader();
                this.uiManager.notify(error.message || 'Failed to save contact information', 'error');
            }
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
            if (this.uiManager) {
                this.uiManager.notify(error.message || 'Failed to search for contacts', 'error');
            }
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

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}