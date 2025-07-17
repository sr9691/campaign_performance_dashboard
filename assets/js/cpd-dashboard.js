/**
 * Admin-specific JavaScript for the Campaign Performance Dashboard plugin.
 * Consolidates all logic into a single jQuery(document).ready block.
 * FIXED: Eliminates duplicate event handlers and double-calling of functions.
 */

jQuery(document).ready(function($) {
    console.log('cpd-dashboard.js: Script started. jQuery document ready.');

    // ========================================================================
    // GLOBAL VARIABLES AND INITIALIZATION
    // ========================================================================

    // Access localized data from cpd_dashboard_data (public-facing) for nonces needed on public page
    const localizedPublicData = typeof cpd_dashboard_data !== 'undefined' ? cpd_dashboard_data : {};
    const adminAjaxData = typeof cpd_admin_ajax !== 'undefined' ? cpd_admin_ajax : {};

    // Check if we're on the admin page
    const isAdminPage = document.body.classList.contains('campaign-dashboard_page_cpd-dashboard-management');

    // Chart instances
    let impressionsChartInstance = null;
    let impressionsByAdGroupChartInstance = null;

    // DOM elements (cache for performance)
    const dashboardContent = $('#clients-section');
    const clientList = $('.account-list');
    const dateRangeSelect = $('.duration-select select');
    const visitorPanel = $('.visitor-panel');

    // ========================================================================
    // DOM MANIPULATION FOR ADMIN INTERFACE
    // ========================================================================

    // Hide WordPress admin elements for full-screen dashboard
    const elementsToHide = [
        '#adminmenumain',
        '#adminmenuwrap',
        '#adminmenuback',
        '#wpadminbar',
        '#wpfooter'
    ];

    elementsToHide.forEach(selector => {
        const element = document.querySelector(selector);
        if (element) {
            element.style.display = 'none';
            element.style.visibility = 'hidden';
        }
    });
    
    document.body.classList.add('cpd-dashboard-active');
    
    const wpContent = document.getElementById('wpcontent');
    if (wpContent) {
        wpContent.style.marginLeft = '0';
        wpContent.style.padding = '0';
    }
    
    const wpBody = document.getElementById('wpbody');
    if (wpBody) {
        wpBody.style.backgroundColor = '#eef2f6';
        wpBody.style.padding = '0';
        wpBody.style.margin = '0';
    }
    
    const wrap = document.querySelector('.wrap');
    if (wrap) {
        wrap.style.margin = '0';
        wrap.style.padding = '0';
        wrap.style.display = 'flex';
        wrap.style.width = '100%';
        wrap.style.minHeight = '100vh';
        wrap.style.maxWidth = 'none';
    }

    // ========================================================================
    // UTILITY FUNCTIONS
    // ========================================================================

    /**
     * Refresh client table data via AJAX
     */
    function refreshClientTable() {
        console.log('cpd-dashboard.js: Refreshing client table...');
        
        $.ajax({
            url: cpd_admin_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'cpd_get_clients',
                nonce: cpd_admin_ajax.nonce
            },
            success: function(response) {
                if (response.success && response.data.clients) {
                    const clientTableBody = $('#clients-section .data-table tbody');
                    clientTableBody.empty();
                    
                    if (response.data.clients.length > 0) {
                        response.data.clients.forEach(client => {
                            const logoHtml = client.logo_url ? 
                                `<img src="${client.logo_url}" alt="Logo" class="client-logo-thumbnail">` : 
                                '<span class="no-logo">N/A</span>';
                            
                            const webpageHtml = client.webpage_url ? 
                                `<a href="${client.webpage_url}" target="_blank" rel="noopener">${client.webpage_url}</a>` : 
                                '<span class="no-url">N/A</span>';
    
                            const aiStatusBadge = client.ai_intelligence_enabled == 1 ? 
                                '<span class="ai-status-badge ai-enabled">✓ Enabled</span>' : 
                                '<span class="ai-status-badge ai-disabled">✗ Disabled</span>';
                            
                            const contextIndicator = (client.ai_intelligence_enabled == 1 && client.client_context_info) ? 
                                '<i class="fas fa-info-circle" title="Has context information" style="color: #28a745; cursor: help; margin-left: 5px;"></i>' : 
                                (client.ai_intelligence_enabled == 1 ? 
                                    '<i class="fas fa-exclamation-triangle" title="No context information" style="color: #ffc107; cursor: help; margin-left: 5px;"></i>' : 
                                    '');
    
                            clientTableBody.append(`
                                <tr data-client-id="${client.id}"
                                    data-client-name="${client.client_name}"
                                    data-account-id="${client.account_id}"
                                    data-logo-url="${client.logo_url || ''}"
                                    data-webpage-url="${client.webpage_url || ''}"
                                    data-crm-email="${client.crm_feed_email || ''}"
                                    data-ai-intelligence-enabled="${client.ai_intelligence_enabled || 0}"
                                    data-client-context-info="${(client.client_context_info || '').replace(/"/g, '&quot;')}">
                                    <td>${client.client_name}</td>
                                    <td>${client.account_id}</td>
                                    <td>${logoHtml}</td>
                                    <td>${webpageHtml}</td>
                                    <td>${client.crm_feed_email || ''}</td>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 5px;">
                                            ${aiStatusBadge}
                                            ${contextIndicator}
                                        </div>
                                    </td>
                                    <td class="actions-cell">
                                        <button class="action-button edit-client" title="Edit Client">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="action-button delete-client" data-client-id="${client.id}" title="Delete Client">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </td>
                                </tr>
                            `);
                        });
                    } else {
                        clientTableBody.append('<tr><td colspan="7" class="no-data">No clients found.</td></tr>');
                    }
                }
            },
            error: function() {
                console.error('Failed to refresh client table');
            }
        });
    }

    /**
     * Refresh user table data via AJAX
     */
    function refreshUserTable() {
        $.ajax({
            url: cpd_admin_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'cpd_get_users',
                nonce: cpd_admin_ajax.nonce
            },
            success: function(response) {
                if (response.success && response.data.users) {
                    const userTableBody = $('#users-section .data-table tbody');
                    userTableBody.empty();
                    
                    if (response.data.users.length > 0) {
                        response.data.users.forEach(user => {
                            const linkedClientName = user.linked_client_name || 'N/A';
                            const deleteButton = user.can_delete ? 
                                `<button class="action-button delete-user" data-user-id="${user.ID}" title="Delete User">
                                    <i class="fas fa-trash-alt"></i>
                                </button>` : '';

                            userTableBody.append(`
                                <tr data-user-id="${user.ID}"
                                    data-username="${user.user_login}"
                                    data-email="${user.user_email}"
                                    data-role="${user.roles.join(', ')}"
                                    data-client-account-id="${user.client_account_id || ''}">
                                    <td>${user.user_login}</td>
                                    <td>${user.user_email}</td>
                                    <td>${user.roles.join(', ')}</td>
                                    <td>${linkedClientName}</td>
                                    <td class="actions-cell">
                                        <button class="action-button edit-user" title="Edit User">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        ${deleteButton}
                                    </td>
                                </tr>
                            `);
                        });
                    } else {
                        userTableBody.append('<tr><td colspan="5" class="no-data">No users found.</td></tr>');
                    }
                }
            },
            error: function() {
                console.error('Failed to refresh user table');
            }
        });
    }

    /**
     * A simple function to handle AJAX requests to our custom endpoint for visitor updates.
     */
    const sendAjaxRequestForVisitor = async (action, visitorId) => {
        const ajaxUrl = localizedPublicData.ajax_url || adminAjaxData.ajax_url;
        const nonce = localizedPublicData.visitor_nonce;

        if (!ajaxUrl || !nonce) {
            console.error('sendAjaxRequestForVisitorStatus: Localized data (ajax_url or nonce) is missing.');
            return false;
        }

        const formData = new FormData();
        formData.append('action', 'cpd_update_visitor_status');
        formData.append('nonce', nonce);
        formData.append('visitor_id', visitorId);
        formData.append('update_action', action);

        try {
            const response = await fetch(ajaxUrl, {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                const errorText = await response.text();
                console.error(`sendAjaxRequestForVisitorStatus: Server responded with status ${response.status}: ${errorText}`);
                throw new Error(`Network response was not ok. Status: ${response.status}, Details: ${errorText}`);
            }

            const data = await response.json();
            
            if (data.success) {
                console.log(`sendAjaxRequestForVisitorStatus: Visitor ${visitorId} status updated successfully.`);
                return true;
            } else {
                console.error('sendAjaxRequestForVisitorStatus: AJAX error:', data.data);
                return false;
            }
        } catch (error) {
            console.error('sendAjaxRequestForVisitorStatus: Fetch error:', error);
            return false;
        }
    };

    /**
     * Load dashboard data via AJAX
     */
    function loadDashboardData(clientId, duration) {
        console.log('ADMIN loadDashboardData: Called with Client ID:', clientId, 'Duration:', duration);
        if(dashboardContent.length === 0) {
            console.warn("Dashboard content container (#clients-section) not found. Cannot load dashboard data.");
            return;
        }
        dashboardContent.css('opacity', 0.5);

        $.ajax({
            url: cpd_admin_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'cpd_get_dashboard_data',
                nonce: cpd_dashboard_ajax_nonce.nonce,
                client_id: (clientId === 'all') ? null : clientId,
                duration: duration
            },
            success: function(response) {
                console.log('ADMIN loadDashboardData: AJAX Success. Response:', response);
                if (response.success) {
                    const data = response.data;
                    
                    // Update Client Logo in Header
                    if (data.client_logo_url) {
                        $('.dashboard-header .client-logo-container img').attr('src', data.client_logo_url);
                    }
                    
                    // Update Summary Cards
                    $('.summary-card .value').each(function() {
                        const el = $(this);
                        const dataKey = el.next('.label').data('summary-key');

                        if (dataKey && data.summary_metrics && data.summary_metrics[dataKey]) {
                            el.text(data.summary_metrics[dataKey]);
                        } else {
                             el.text('0');
                        }
                    });

                    // Update Ad Group Table
                    const adGroupTableBody = $('.ad-group-table tbody');
                    if (adGroupTableBody.length > 0) {
                        adGroupTableBody.empty();
                        if (data.campaign_data && data.campaign_data.length > 0) {
                            data.campaign_data.forEach(item => {
                                adGroupTableBody.append(`
                                    <tr>
                                        <td>${item.ad_group_name}</td>
                                        <td>${(item.impressions).toLocaleString()}</td>
                                        <td>${(item.reach).toLocaleString()}</td>
                                        <td>${(item.ctr).toLocaleString()}%</td>
                                        <td>${(item.clicks).toLocaleString()}</td>
                                    </tr>
                                `);
                            });
                        } else {
                            adGroupTableBody.append('<tr><td colspan="5" class="no-data">No campaign data found for this period.</td></tr>');
                        }
                    }

                    // Update Visitor Panel
                    const visitorListContainer = $('.visitor-panel .visitor-list');
                    if (visitorListContainer.length > 0) {
                        visitorListContainer.empty();
                        if (data.visitor_data && data.visitor_data.length > 0) {
                            data.visitor_data.forEach(visitor => {
                                const visitorLogoUrl = visitor.logo_url || visitor.referrer_logo_url || localizedPublicData.memo_seal_url || adminAjaxData.memo_seal_url;
                                const visitorAltText = visitor.alt_text || visitor.referrer_alt_text || 'Visitor Logo';
                                const visitorTooltipText = visitor.tooltip_text || visitor.referrer_tooltip || 'No referrer information';
                                
                                const fullName = (visitor.first_name || '') + ' ' + (visitor.last_name || '');
                                const location = [visitor.city, visitor.state, visitor.zipcode].filter(Boolean).join(', ');
                                const email = visitor.email || '';

                                visitorListContainer.append(`
                                    <div class="visitor-card" data-visitor-id="${visitor.visitor_id}">
                                        <div class="visitor-logo">
                                            <img src="${visitorLogoUrl}" 
                                                 alt="${visitorAltText}" 
                                                 title="${visitorTooltipText}">
                                        </div>
                                        <div class="visitor-details">
                                            <p class="visitor-name">${fullName.trim() || 'Unknown Visitor'}</p>
                                            <div class="visitor-info">
                                                <p><i class="fas fa-briefcase"></i> ${visitor.job_title || 'Unknown'}</p>
                                                <p><i class="fas fa-building"></i> ${visitor.company_name || 'Unknown'}</p>
                                                <p><i class="fas fa-map-marker-alt"></i> ${location || 'Unknown'}</p>
                                                <p><i class="fas fa-envelope"></i> ${email || 'Unknown'}</p>
                                            </div>
                                        </div>
                                        <div class="visitor-actions">
                                            <span class="icon add-crm-icon" title="Add to CRM">
                                                <i class="fas fa-plus-square"></i>
                                            </span>
                                            <span class="icon delete-icon" title="Archive">
                                                <i class="fas fa-trash-alt"></i>
                                            </span>
                                        </div>
                                    </div>
                                `);
                            });
                        } else {
                            visitorListContainer.append('<div class="no-data">No visitor data found.</div>');
                        }
                    }

                } else {
                    console.error('loadDashboardData: AJAX Error:', response.data);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('loadDashboardData: AJAX request failed. Status:', textStatus, 'Error:', errorThrown, 'Response Text:', jqXHR.responseText);
            },
            complete: function() {
                if (isAdminPage && dashboardContent.length > 0) {
                    dashboardContent.css('opacity', 1);
                }
                console.log('loadDashboardData: AJAX request complete.');
            }
        });
    }

    /**
     * Load eligible visitors for CRM management
     */
    function loadEligibleVisitors() {
        const eligibleVisitorsTableBody = $('#eligible-visitors-table tbody');
        const crmClientFilter = $('#crm_client_filter');
        
        if (!eligibleVisitorsTableBody.length || !crmClientFilter.length) {
            return;
        }

        eligibleVisitorsTableBody.html('<tr><td colspan="10" class="no-data">Loading eligible visitors...</td></tr>');
        const clientId = crmClientFilter.val();
        
        $.ajax({
            url: cpd_admin_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'cpd_get_eligible_visitors',
                nonce: cpd_admin_ajax.nonce,
                account_id: clientId
            },
            success: function(response) {
                eligibleVisitorsTableBody.empty();
                if (response.success && response.data.visitors.length > 0) {
                    response.data.visitors.forEach(visitor => {
                        const fullName = (visitor.first_name || '') + ' ' + (visitor.last_name || '');
                        const location = [visitor.city, visitor.state, visitor.zipcode].filter(Boolean).join(', ');
                        eligibleVisitorsTableBody.append(`
                            <tr>
                                <td>${fullName.trim() || 'N/A'}</td>
                                <td>${visitor.company_name || 'N/A'}</td>
                                <td><a href="${visitor.linkedin_url}" target="_blank" rel="noopener">${visitor.linkedin_url || 'N/A'}</a></td>
                                <td>${visitor.city || 'N/A'}</td>
                                <td>${visitor.state || 'N/A'}</td>
                                <td>${visitor.zipcode || 'N/A'}</td>
                                <td>${new Date(visitor.last_seen_at).toLocaleString()}</td>
                                <td>${visitor.recent_page_count || 0}</td>
                                <td>${visitor.account_id || 'N/A'}</td>
                                <td class="actions-cell">
                                    <button class="action-button undo-crm-button" data-visitor-internal-id="${visitor.id}" title="Undo CRM Flag">
                                        <i class="fas fa-undo"></i>
                                    </button>
                                </td>
                            </tr>
                        `);
                    });
                } else {
                    eligibleVisitorsTableBody.append('<tr><td colspan="10" class="no-data">No eligible visitors found.</td></tr>');
                }
            },
            error: function() {
                eligibleVisitorsTableBody.html('<tr><td colspan="10" class="no-data">Error loading visitors.</td></tr>');
                alert('Error loading eligible visitors. Please try again.');
            }
        });
    }

    /**
     * Update CRM button state based on client selection
     */
    function updateCRMButtonState() {
        const crmClientFilter = $('#crm_client_filter');
        const triggerOnDemandSendButton = $('#trigger_on_demand_send');
        
        if (!crmClientFilter.length || !triggerOnDemandSendButton.length) {
            return;
        }

        const selectedValue = crmClientFilter.val();
        if (selectedValue === 'all') {
            triggerOnDemandSendButton.prop('disabled', true);
            triggerOnDemandSendButton.attr('title', 'Please select a specific client to send on-demand emails');
        } else {
            triggerOnDemandSendButton.prop('disabled', false);
            triggerOnDemandSendButton.attr('title', '');
        }
    }

    // ========================================================================
    // NAVIGATION MANAGEMENT (ADMIN ONLY)
    // ========================================================================

    if (isAdminPage) {
        console.log('cpd-dashboard.js: Admin-specific UI listeners attaching.');

        // Navigation initialization with delay for DOM readiness
        window.addEventListener('load', function() {
            console.log('cpd-dashboard.js: window.load event fired. Delaying navigation initialization for full DOM readiness.');

            setTimeout(function() {
                console.log('cpd-dashboard.js: Navigation initialization (delayed) starting.');

                const navLinks = document.querySelectorAll('.admin-sidebar nav a[data-target]');
                const sections = document.querySelectorAll('.admin-main-content .section-content');

                if (navLinks.length === 0 || sections.length === 0) {
                    console.error("cpd-dashboard.js: CRITICAL ERROR: Navigation links or sections not found even after delay.");
                    return;
                }

                const initialHash = window.location.hash.substring(1);
                const defaultSectionId = 'clients-section';

                function setActiveSection() {
                    let targetHashId = initialHash;
                    if (!targetHashId || !document.getElementById(targetHashId + '-section')) {
                        targetHashId = defaultSectionId.replace('-section', '');
                    }

                    console.log("setActiveSection: Target Hash ID:", targetHashId);

                    navLinks.forEach(l => {
                        if (l) l.classList.remove('active');
                    });
                    sections.forEach(s => {
                        if (s) s.classList.remove('active');
                    });

                    const targetSection = document.getElementById(targetHashId + '-section');
                    const targetLink = document.querySelector(`.admin-sidebar nav a[data-target="${targetHashId}-section"]`);

                    if (targetSection && targetLink) {
                        targetSection.classList.add('active');
                        targetLink.classList.add('active');
                    } else {
                        console.warn("setActiveSection: Fallback to default");
                        const fallbackSection = document.getElementById(defaultSectionId);
                        const fallbackLink = document.querySelector(`.admin-sidebar nav a[data-target="${defaultSectionId}"]`);

                        if (fallbackSection) fallbackSection.classList.add('active');
                        if (fallbackLink) fallbackLink.classList.add('active');

                        window.location.hash = defaultSectionId.replace('-section', '');
                    }

                    if (targetHashId === 'crm-email-management') {
                        if (typeof loadEligibleVisitors === 'function') {
                            loadEligibleVisitors();
                        }
                    }
                }

                setActiveSection();

                navLinks.forEach(link => {
                    link.addEventListener('click', (event) => {
                        if (link.getAttribute('target') === '_blank') {
                            return;
                        }

                        event.preventDefault();

                        navLinks.forEach(l => l.classList.remove('active'));
                        sections.forEach(s => s.classList.remove('active'));

                        const targetId = link.getAttribute('data-target');
                        const cleanedTargetId = targetId.replace('-section', '');
                        const targetSection = document.getElementById(targetId);

                        if (targetSection) {
                            targetSection.classList.add('active');
                            link.classList.add('active');
                            if (history.pushState) {
                                history.pushState(null, null, '#' + cleanedTargetId);
                            } else {
                                window.location.hash = '#' + cleanedTargetId;
                            }

                            if (cleanedTargetId === 'crm-email-management') {
                                if (typeof loadEligibleVisitors === 'function') {
                                    loadEligibleVisitors();
                                }
                            }
                        }
                    });
                });

                window.addEventListener('hashchange', setActiveSection);

            }, 100);
        });

        // ========================================================================
        // EVENT HANDLERS - PROPERLY BOUND TO PREVENT DUPLICATES
        // ========================================================================

        // Visitor Panel Actions (using event delegation to prevent duplicates)
        if (visitorPanel.length > 0) {
            console.log('cpd-dashboard.js: Attaching click listener to Visitor Panel buttons.');
            
            // Remove any existing handlers first
            visitorPanel.off('click.visitor-actions');
            
            // Bind with namespace to prevent duplicates
            visitorPanel.on('click.visitor-actions', '.add-crm-icon, .delete-icon', async function(event) {
                event.preventDefault();
                console.log('cpd-dashboard.js: Visitor button clicked!');

                const button = $(this);
                const visitorCard = button.closest('.visitor-card');
                const visitorId = visitorCard.data('visitor-id');
                
                let updateAction = '';
                if (button.hasClass('add-crm-icon')) {
                    updateAction = 'add_crm';
                    if (!confirm('Are you sure you want to flag this visitor for CRM addition?')) {
                        return;
                    }
                } else if (button.hasClass('delete-icon')) {
                    updateAction = 'archive';
                    if (!confirm('Are you sure you want to archive this visitor? They will no longer appear in the list.')) {
                        return;
                    }
                }

                button.prop('disabled', true).css('opacity', 0.6);

                try {
                    const success = await sendAjaxRequestForVisitor(updateAction, visitorId);

                    if (success) {
                        const currentClientId = (clientList.length > 0 && clientList.find('li.active').length > 0) ? clientList.find('li.active').data('client-id') : 'all';
                        const currentDuration = dateRangeSelect.val();
                        loadDashboardData(currentClientId, currentDuration);
                    } else {
                        alert('Failed to update visitor status. Please check console for details.');
                    }
                } catch (error) {
                    console.error('cpd-dashboard.js: Error during visitor action AJAX:', error);
                    alert('An unexpected error occurred. Please check console.');
                } finally {
                    button.prop('disabled', false).css('opacity', 1);
                }
            });
        }

        // Client List Actions
        if (clientList.length > 0) {
            clientList.off('click.client-list');
            clientList.on('click.client-list', 'li', function() {
                console.log('cpd-dashboard.js: Client list item clicked!');
                const listItem = $(this);
                const clientId = listItem.data('client-id');
                
                clientList.find('li').removeClass('active');
                listItem.addClass('active');

                const currentUrl = new URL(window.location.href);
                if (clientId === 'all') {
                    currentUrl.searchParams.delete('client_id');
                } else {
                    currentUrl.searchParams.set('client_id', clientId);
                }
                window.history.pushState({}, '', currentUrl.toString());

                loadDashboardData(clientId, dateRangeSelect.val());
            });
        }

        // Date Range Selection
        dateRangeSelect.off('change.date-range');
        dateRangeSelect.on('change.date-range', function() {
            console.log('cpd-dashboard.js: Date range dropdown changed!');
            const activeClientListItem = clientList.find('li.active');
            const clientId = activeClientListItem.length > 0 ? activeClientListItem.data('client-id') : 'all';
            loadDashboardData(clientId, $(this).val());
        });

        // ========================================================================
        // FORM HANDLERS - PREVENT DUPLICATE SUBMISSIONS
        // ========================================================================

        // Add Client Form
        $('#add-client-form').off('submit.add-client').on('submit.add-client', function(event) {
            event.preventDefault();
            console.log('cpd-dashboard.js: Add Client form submitted!');
            
            const form = $(this);
            const submitBtn = form.find('button[type="submit"]');
            
            // Prevent double submission
            if (submitBtn.prop('disabled')) {
                return false;
            }
            
            submitBtn.prop('disabled', true).text('Adding...');
            
            const formData = form.serialize() + `&action=cpd_ajax_add_client&nonce=${cpd_admin_ajax.nonce}`;
        
            $.ajax({
                url: cpd_admin_ajax.ajax_url,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        alert('Client added successfully!');
                        form[0].reset();
                        $('#new-client-context-group').hide();
                        refreshClientTable();
                    } else {
                        alert('Error: ' + response.data.message);
                    }
                },
                error: function() {
                    alert('An error occurred during the request.');
                },
                complete: function() {
                    submitBtn.prop('disabled', false).text('Add Client');
                }
            });
        });

        // Add User Form
        $('#add-user-form').off('submit.add-user').on('submit.add-user', function(event) {
            event.preventDefault();
            console.log('cpd-dashboard.js: Add User form submitted!');
            
            const form = $(this);
            const submitBtn = form.find('button[type="submit"]');
            
            if (submitBtn.prop('disabled')) {
                return false;
            }
            
            submitBtn.prop('disabled', true).text('Adding...');
            
            const formData = form.serialize() + `&action=cpd_ajax_add_user&nonce=${cpd_admin_ajax.nonce}`;

            $.ajax({
                url: cpd_admin_ajax.ajax_url,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        alert('User added successfully!');
                        form[0].reset();
                        refreshUserTable();
                    } else {
                        alert('Error: ' + response.data.message);
                    }
                },
                error: function() {
                    alert('An error occurred during the request.');
                },
                complete: function() {
                    submitBtn.prop('disabled', false).text('Add User');
                }
            });
        });

        // Delete Client Button
        $(document).off('click.delete-client', '#clients-section .data-table .action-button.delete-client');
        $(document).on('click.delete-client', '#clients-section .data-table .action-button.delete-client', function(event) {
            event.preventDefault();
            console.log('cpd-dashboard.js: Delete Client button clicked!');
            
            const row = $(this).closest('tr');
            const clientId = row.data('client-id');
            
            if (confirm('Are you sure you want to delete this client? This action cannot be undone.')) {
                $.ajax({
                    url: cpd_admin_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'cpd_ajax_delete_client',
                        nonce: cpd_admin_ajax.nonce,
                        client_id: clientId
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Client deleted successfully!');
                            refreshClientTable();
                        } else {
                            alert('Error: ' + response.data.message);
                        }
                    },
                    error: function() {
                        alert('An error occurred during the delete request.');
                    }
                });
            }
        });

        // Delete User Button
        $(document).off('click.delete-user', '#users-section .data-table .action-button.delete-user');
        $(document).on('click.delete-user', '#users-section .data-table .action-button.delete-user', function(event) {
            event.preventDefault();
            console.log('cpd-dashboard.js: Delete User button clicked!');
            
            const row = $(this).closest('tr');
            const userId = row.data('user-id');
            
            if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
                $.ajax({
                    url: cpd_admin_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'cpd_ajax_delete_user',
                        nonce: cpd_admin_ajax.nonce,
                        user_id: userId
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('User deleted successfully!');
                            refreshUserTable();
                        } else {
                            alert('Error: ' + response.data.message);
                        }
                    },
                    error: function() {
                        alert('An error occurred during the delete request.');
                    }
                });
            }
        });

        // ========================================================================
        // MODAL HANDLERS - EDIT CLIENT
        // ========================================================================

        const editClientModal = $('#edit-client-modal');
        const editClientForm = $('#edit-client-form');
        
        // Edit Client Button
        $(document).off('click.edit-client', '#clients-section .data-table .action-button.edit-client');
        $(document).on('click.edit-client', '#clients-section .data-table .action-button.edit-client', function() {
            console.log('cpd-dashboard.js: Edit Client button clicked!');
            
            const row = $(this).closest('tr');
            const clientId = row.data('client-id');
            
            // Populate form fields
            const clientName = row.data('client-name');
            const accountId = row.data('account-id');
            const logoUrl = row.data('logo-url');
            const webpageUrl = row.data('webpage-url');
            const crmEmail = row.data('crm-email');
        
            $('#edit_client_id').val(clientId);
            $('#edit_client_name').val(clientName);
            $('#edit_account_id').val(accountId);
            $('#edit_logo_url').val(logoUrl);
            $('#edit_webpage_url').val(webpageUrl);
            $('#edit_crm_feed_email').val(crmEmail);
        
            // AI Intelligence fields
            const aiToggle = $('#edit_ai_intelligence_enabled');
            const contextGroup = $('#edit-client-context-group');
            const contextField = $('#edit_client_context_info');
            
            if (aiToggle.length) {
                const isAiEnabled = row.data('ai-intelligence-enabled') == 1 || row.data('ai-intelligence-enabled') === true;
                aiToggle.prop('checked', isAiEnabled);
                
                if (contextGroup.length) {
                    contextGroup.toggle(isAiEnabled);
                }
            }
            
            if (contextField.length) {
                const contextInfo = row.data('client-context-info') || '';
                contextField.val(contextInfo);
            }
        
            editClientModal.fadeIn();
        });
        
        // Edit Client Form Submit
        editClientForm.off('submit.edit-client').on('submit.edit-client', function(event) {
            event.preventDefault();
            console.log('cpd-dashboard.js: Edit Client form submitted!');
            
            const form = $(this);
            const submitBtn = form.find('button[type="submit"]');
            
            if (submitBtn.prop('disabled')) {
                return false;
            }
            
            submitBtn.prop('disabled', true).text('Saving...');
            
            const formData = form.serialize() + `&action=cpd_ajax_edit_client&nonce=${cpd_admin_ajax.nonce}`;

            $.ajax({
                url: cpd_admin_ajax.ajax_url,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        alert('Client updated successfully!');
                        editClientModal.fadeOut();
                        refreshClientTable();
                    } else {
                        alert('Error: ' + response.data.message);
                    }
                },
                error: function() {
                    alert('An error occurred during the update request.');
                },
                complete: function() {
                    submitBtn.prop('disabled', false).text('Save Changes');
                }
            });
        });

        // ========================================================================
        // MODAL HANDLERS - EDIT USER
        // ========================================================================

        const editUserModal = $('#edit-user-modal');
        const editUserForm = $('#edit-user-form');

        // Edit User Button
        $(document).off('click.edit-user', '#users-section .data-table .action-button.edit-user');
        $(document).on('click.edit-user', '#users-section .data-table .action-button.edit-user', function() {
            console.log('cpd-dashboard.js: Edit User button clicked!');
            
            const row = $(this).closest('tr');
            const userId = row.data('user-id');
            const username = row.data('username');
            const email = row.data('email');
            const role = row.data('role');
            const clientAccountId = row.data('client-account-id');

            $('#edit_user_id').val(userId);
            $('#edit_user_username').val(username);
            $('#edit_user_email').val(email);
            $('#edit_user_role').val(role);
            $('#edit_linked_client').val(clientAccountId);
            
            editUserModal.fadeIn();
        });

        // Edit User Form Submit
        editUserForm.off('submit.edit-user').on('submit.edit-user', function(event) {
            event.preventDefault();
            console.log('cpd-dashboard.js: Edit User form submitted!');
            
            const form = $(this);
            const submitBtn = form.find('button[type="submit"]');
            
            if (submitBtn.prop('disabled')) {
                return false;
            }
            
            submitBtn.prop('disabled', true).text('Saving...');
            
            const formData = form.serialize() + `&action=cpd_ajax_edit_user&nonce=${cpd_admin_ajax.nonce}`;

            $.ajax({
                url: cpd_admin_ajax.ajax_url,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        alert('User updated successfully!');
                        editUserModal.fadeOut();
                        refreshUserTable();
                    } else {
                        alert('Error: ' + response.data.message);
                    }
                },
                error: function() {
                    alert('An error occurred during the request.');
                },
                complete: function() {
                    submitBtn.prop('disabled', false).text('Save Changes');
                }
            });
        });

        // Modal Close Handlers
        $('.modal .close').off('click.modal-close').on('click.modal-close', function() {
            console.log('cpd-dashboard.js: Modal close button clicked!');
            $(this).closest('.modal').fadeOut();
        });

        $('.modal').off('click.modal-background').on('click.modal-background', function(event) {
            if ($(event.target).hasClass('modal')) {
                console.log('cpd-dashboard.js: Modal background clicked!');
                $(this).fadeOut();
            }
        });

        // ========================================================================
        // SELECT2 INITIALIZATION
        // ========================================================================

        if ($.fn.select2) {
            console.log('cpd-dashboard.js: Select2 found, initializing searchable selects.');
            $('.searchable-select').each(function() {
                let dropdownParent = $(this).closest('.modal').length ? $(this).closest('.modal') : $(this).parent();
                if ($(this).attr('id') === 'new_linked_client' || $(this).attr('id') === 'edit_linked_client' || 
                    $(this).attr('id') === 'on_demand_client_select' || $(this).attr('id') === 'eligible_visitors_client_filter') {
                    dropdownParent = $(this).closest('.card').length ? $(this).closest('.card') : $(document.body);
                }

                $(this).select2({
                    dropdownParent: dropdownParent,
                    placeholder: 'Select an option...',
                    allowClear: true
                });
            });
        }

        // ========================================================================
        // API KEY GENERATION
        // ========================================================================

        $('#generate_api_key_button').off('click.api-key').on('click.api-key', function(event) {
            event.preventDefault();
            
            const button = $(this);
            const apiKeyField = $('#cpd_api_key_field');
            const originalText = button.text();

            if (button.prop('disabled')) {
                return false;
            }

            button.prop('disabled', true).text('Generating...');

            $.ajax({
                url: cpd_admin_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'cpd_generate_api_token',
                    nonce: cpd_admin_ajax.nonce
                },
                success: function(response) {
                    if (response.success && response.data.token) {
                        apiKeyField.val(response.data.token);
                        alert('New API Key generated successfully!');
                    } else {
                        alert('Error: ' + (response.data && response.data.message ? response.data.message : 'Failed to generate API key.'));
                        console.error('API Key generation failed:', response);
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    alert('An error occurred during API key generation.');
                    console.error('AJAX error during API key generation:', textStatus, errorThrown, jqXHR.responseText);
                },
                complete: function() {
                    button.prop('disabled', false).text(originalText);
                }
            });
        });

        // ========================================================================
        // CRM EMAIL MANAGEMENT
        // ========================================================================

        const crmClientFilter = $('#crm_client_filter');
        const triggerOnDemandSendButton = $('#trigger_on_demand_send');

        // CRM Client Filter Change
        crmClientFilter.off('change.crm-filter').on('change.crm-filter', function() {
            loadEligibleVisitors();
            updateCRMButtonState();
        });

        // Initialize CRM button state
        updateCRMButtonState();

        // Trigger On-Demand Send
        triggerOnDemandSendButton.off('click.on-demand').on('click.on-demand', function() {
            const button = $(this);
            const selectedAccountId = crmClientFilter.val();
            
            if (selectedAccountId === 'all') {
                alert('Please select a specific client to send on-demand emails.');
                return;
            }

            if (button.prop('disabled')) {
                return false;
            }

            if (confirm(`Are you sure you want to send on-demand CRM emails for client: ${selectedAccountId}?`)) {
                button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Sending...');

                $.ajax({
                    url: cpd_admin_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'cpd_trigger_on_demand_send',
                        nonce: cpd_admin_ajax.nonce,
                        account_id: selectedAccountId
                    },
                    success: function(response) {
                        if (response.success) {
                            alert(response.data.message);
                            loadEligibleVisitors();
                        } else {
                            alert('Error: ' + response.data.message);
                        }
                    },
                    error: function() {
                        alert('An error occurred during the on-demand send request.');
                    },
                    complete: function() {
                        button.prop('disabled', false).html('<i class="fas fa-paper-plane"></i> Send On-Demand CRM Email');
                        updateCRMButtonState();
                    }
                });
            }
        });

        // Undo CRM Button (currently disabled in original code)
        $(document).off('click.undo-crm', '#eligible-visitors-table .undo-crm-button');
        $(document).on('click.undo-crm', '#eligible-visitors-table .undo-crm-button', function() {
            // Note: This functionality is currently disabled in the original code
            return;
        });

        // ========================================================================
        // REFERRER LOGO MAPPING MANAGEMENT
        // ========================================================================

        // Add Referrer Mapping
        $('#add-referrer-mapping').off('click.add-referrer').on('click.add-referrer', function() {
            console.log('cpd-dashboard.js: Add referrer mapping clicked');
            
            const newRow = `
                <div class="referrer-mapping-row">
                    <div class="form-group">
                        <label>Domain</label>
                        <input type="text" name="referrer_domains[]" placeholder="e.g., facebook.com">
                    </div>
                    <div class="form-group">
                        <label>Logo URL</label>
                        <input type="url" name="referrer_logos[]" placeholder="https://example.com/logo.png">
                    </div>
                    <div class="form-group">
                        <button type="button" class="button button-secondary remove-mapping">Remove</button>
                    </div>
                </div>
            `;
            $('#referrer-logo-mappings').append(newRow);
            
            const $newRow = $('#referrer-logo-mappings .referrer-mapping-row:last-child');
            $newRow.hide().fadeIn(300);
        });
        
        // Remove Referrer Mapping
        $(document).off('click.remove-mapping', '.remove-mapping');
        $(document).on('click.remove-mapping', '.remove-mapping', function() {
            console.log('cpd-dashboard.js: Remove referrer mapping clicked');
            const $row = $(this).closest('.referrer-mapping-row');
            
            if (confirm('Are you sure you want to remove this referrer logo mapping?')) {
                $row.fadeOut(300, function() {
                    $(this).remove();
                });
            }
        });
        
        // Domain Input Formatting
        $(document).off('input.domain-format', 'input[name="referrer_domains[]"]');
        $(document).on('input.domain-format', 'input[name="referrer_domains[]"]', function() {
            const $input = $(this);
            let domain = $input.val().toLowerCase().trim();
            
            domain = domain.replace(/^https?:\/\//, '');
            domain = domain.replace(/^www\./, '');
            domain = domain.replace(/\/$/, '');
            
            if (domain !== $input.val()) {
                $input.val(domain);
            }
        });
        
        // Form Validation for Referrer Mappings
        $('#settings-form').off('submit.settings-validation').on('submit.settings-validation', function(e) {
            const domains = $('input[name="referrer_domains[]"]');
            const logos = $('input[name="referrer_logos[]"]');
            let hasError = false;
            
            domains.removeClass('error');
            logos.removeClass('error');
            $('.referrer-error-message').remove();
            
            domains.each(function(index) {
                const domain = $(this).val().trim();
                const logo = logos.eq(index).val().trim();
                
                if (domain && !logo) {
                    $(this).addClass('error');
                    logos.eq(index).addClass('error');
                    logos.eq(index).after('<span class="referrer-error-message" style="color: #dc3545; font-size: 0.8rem;">Logo URL is required when domain is specified</span>');
                    hasError = true;
                } else if (!domain && logo) {
                    $(this).addClass('error');
                    $(this).after('<span class="referrer-error-message" style="color: #dc3545; font-size: 0.8rem;">Domain is required when logo URL is specified</span>');
                    hasError = true;
                }
            });
            
            if (hasError) {
                e.preventDefault();
                $('html, body').animate({
                    scrollTop: $('.error').first().offset().top - 100
                }, 300);
                return false;
            }
        });

        // ========================================================================
        // INTELLIGENCE SETTINGS FUNCTIONALITY
        // ========================================================================

        console.log('CPD Intelligence Settings: JavaScript loaded');
        
        const ajaxUrl = typeof cpd_admin_ajax !== 'undefined' ? cpd_admin_ajax.ajax_url : ajaxurl;
        
        // Webhook Testing
        const testWebhookBtn = $('#test-webhook-btn');
        const testResult = $('#webhook-test-result');
        
        if (testWebhookBtn.length) {
            testWebhookBtn.off('click.test-webhook').on('click.test-webhook', function() {
                const webhookUrl = $('#intelligence_webhook_url').val();
                const apiKey = $('#makecom_api_key').val();
                
                if (!webhookUrl || !apiKey) {
                    testResult.html('<span style="color: #dc3545;">⚠️ Please enter both Webhook URL and API Key</span>');
                    return;
                }
                
                if (testWebhookBtn.prop('disabled')) {
                    return false;
                }
                
                testWebhookBtn.prop('disabled', true).text('Testing...');
                testResult.html('<span style="color: #6c757d;">🔄 Testing connection...</span>');
                
                $.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'cpd_test_intelligence_webhook',
                        nonce: cpd_admin_ajax.nonce,
                        webhook_url: webhookUrl,
                        api_key: apiKey
                    },
                    success: function(response) {
                        if (response.success) {
                            testResult.html('<span style="color: #28a745;">✅ ' + response.data.message + '</span>');
                        } else {
                            testResult.html('<span style="color: #dc3545;">❌ ' + response.data.message + '</span>');
                        }
                    },
                    error: function() {
                        console.error('Webhook test error');
                        testResult.html('<span style="color: #dc3545;">❌ Connection failed</span>');
                    },
                    complete: function() {
                        testWebhookBtn.prop('disabled', false).text('Test Webhook');
                    }
                });
            });
        }
        
        // Intelligence Settings Form
        const intelligenceForm = $('#intelligence-settings-form');
        if (intelligenceForm.length) {
            intelligenceForm.off('submit.intelligence-settings').on('submit.intelligence-settings', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const submitBtn = intelligenceForm.find('button[type="submit"]');
                const originalText = submitBtn.text();
                
                if (submitBtn.prop('disabled')) {
                    return false;
                }
                
                submitBtn.prop('disabled', true).text('Saving...');
                
                const formData = {
                    action: 'cpd_save_intelligence_settings',
                    nonce: cpd_admin_ajax.nonce,
                    intelligence_webhook_url: $('#intelligence_webhook_url').val(),
                    makecom_api_key: $('#makecom_api_key').val(),
                    intelligence_rate_limit: $('#intelligence_rate_limit').val(),
                    intelligence_timeout: $('#intelligence_timeout').val(),
                    intelligence_auto_generate_crm: $('#intelligence_auto_generate_crm').is(':checked') ? '1' : '',
                    intelligence_processing_method: $('#intelligence_processing_method').val(),
                    intelligence_batch_size: $('#intelligence_batch_size').val(),
                    intelligence_crm_timeout: $('#intelligence_crm_timeout').val()
                };
                
                console.log('Sending intelligence settings data:', formData);
                
                $.ajax({
                    url: cpd_admin_ajax.ajax_url,
                    type: 'POST',
                    data: formData,
                    success: function(response) {
                        if (response.success) {
                            alert('✅ ' + response.data.message);
                        } else {
                            alert('❌ Error: ' + response.data.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX error:', error);
                        alert('❌ An error occurred while saving settings');
                    },
                    complete: function() {
                        submitBtn.prop('disabled', false).text(originalText);
                    }
                });
                
                return false;
            });
        }
        
        // Intelligence Defaults Form
        const defaultsForm = $('#intelligence-defaults-form');
        if (defaultsForm.length) {
            defaultsForm.off('submit.intelligence-defaults').on('submit.intelligence-defaults', function(e) {
                e.preventDefault();
                
                const submitBtn = defaultsForm.find('button[type="submit"]');
                const originalText = submitBtn.text();
                
                if (submitBtn.prop('disabled')) {
                    return false;
                }
                
                submitBtn.prop('disabled', true).text('Saving...');
                
                const formData = defaultsForm.serialize() + '&action=cpd_save_intelligence_defaults&nonce=' + cpd_admin_ajax.nonce;
                
                $.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    data: formData,
                    success: function(response) {
                        if (response.success) {
                            alert('✅ ' + response.data.message);
                        } else {
                            alert('❌ Error: ' + response.data.message);
                        }
                    },
                    error: function() {
                        console.error('Save error');
                        alert('❌ An error occurred while saving default settings');
                    },
                    complete: function() {
                        submitBtn.prop('disabled', false).text(originalText);
                    }
                });
            });
        }
        
        // AI Intelligence Toggle Handlers
        $(document).off('change.ai-toggle-new', '#new_ai_intelligence_enabled');
        $(document).on('change.ai-toggle-new', '#new_ai_intelligence_enabled', function() {
            const isChecked = $(this).is(':checked');
            const contextGroup = $('#new-client-context-group');
            
            if (contextGroup.length) {
                contextGroup.toggle(isChecked);
                if (isChecked) {
                    contextGroup[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            }
            
            console.log('cpd-dashboard.js: New client AI toggle changed:', isChecked);
        });    
        
        $(document).off('change.ai-toggle-edit', '#edit_ai_intelligence_enabled');
        $(document).on('change.ai-toggle-edit', '#edit_ai_intelligence_enabled', function() {
            const isChecked = $(this).is(':checked');
            const contextGroup = $('#edit-client-context-group');
            
            if (contextGroup.length) {
                contextGroup.toggle(isChecked);
                if (isChecked) {
                    contextGroup[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            }
            
            console.log('cpd-dashboard.js: Edit modal AI toggle changed:', isChecked);
        });

        // ========================================================================
        // INITIAL DASHBOARD DATA LOAD
        // ========================================================================

        // Initialize dashboard data on page load for #clients-section only
        if ($('#clients-section.active').length > 0) {
            setTimeout(function() {
                const initialClientIdElement = $('.admin-sidebar .account-list li.active');
                let initialClientId;

                if (initialClientIdElement.length > 0) {
                    initialClientId = initialClientIdElement.data('client-id');
                } else {
                    initialClientId = 'all'; 
                    const allClientsListItem = $('.admin-sidebar .account-list li[data-client-id="all"]');
                    if (allClientsListItem.length > 0) {
                        allClientsListItem.addClass('active');
                    }
                }

                const initialDuration = dateRangeSelect.val();
                console.log('cpd-dashboard.js: Initializing dashboard data load for admin clients section.');
                loadDashboardData((initialClientId === 'all') ? null : initialClientId, initialDuration);
            }, 150);
        }

        console.log('CPD Intelligence Settings: All event listeners attached');

    } else {
        console.log('cpd-dashboard.js: Not on admin management page. Admin UI not initialized, but shared functions are available.');
    }

    console.log('cpd-dashboard.js: Script initialization complete.');
});