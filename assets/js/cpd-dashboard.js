/**
 * Admin-specific JavaScript for the Campaign Performance Dashboard plugin.
 * Consolidates all logic into a single jQuery(document).ready block.
 */

jQuery(document).ready(function($) {
    console.log('cpd-dashboard.js: Script started. jQuery document ready.');

    // Access localized data from cpd_dashboard_data (public-facing) for nonces needed on public page
    const localizedPublicData = typeof cpd_dashboard_data !== 'undefined' ? cpd_dashboard_data : {};

    // --- Force DOM Manipulation (Moved inside document.ready) ---
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
    // --- End Force DOM Manipulation ---


    // --- Navigation and UI Toggles ---
    const navLinks = document.querySelectorAll('.admin-sidebar nav a[data-target]');
    const sections = document.querySelectorAll('.admin-main-content');
    
    const initialHash = window.location.hash.substring(1);
    if (initialHash) {
        navLinks.forEach(l => l.classList.remove('active'));
        sections.forEach(s => s.classList.remove('active'));
        const targetSection = document.getElementById(initialHash);
        const targetLink = document.querySelector(`a[data-target="${initialHash}"]`);
        if (targetSection && targetLink) {
            targetSection.classList.add('active');
            targetLink.classList.add('active');
        }
    } else {
        const firstNavLink = document.querySelector('.admin-sidebar nav a[data-target]');
        if (firstNavLink) {
            const targetId = firstNavLink.getAttribute('data-target');
            document.getElementById(targetId).classList.add('active');
            firstNavLink.classList.add('active');
        }
    }

    navLinks.forEach(link => {
        link.addEventListener('click', (event) => {
            event.preventDefault();
            navLinks.forEach(l => l.classList.remove('active'));
            sections.forEach(s => s.classList.remove('active'));
            const targetId = link.getAttribute('data-target');
            const targetSection = document.getElementById(targetId);
            if (targetSection) {
                targetSection.classList.add('active');
                link.classList.add('active');
                window.location.hash = link.getAttribute('href').split('#')[1];
            }
        });
    });

    // --- Chart Rendering Functionality ---
    let impressionsChartInstance = null;
    let impressionsByAdGroupChartInstance = null;

    /**
     * Renders or updates the Impressions Line Chart.
     * @param {Array} data The campaign data from the AJAX response.
     */
    function renderImpressionsChart(data) {
        const ctx = $('#impressions-chart-canvas');
        if (ctx.length === 0) return;

        const parent = ctx.parent();
        const parentWidth = parent.innerWidth();
        const parentHeight = parent.innerHeight();
        ctx.attr('width', parentWidth);
        ctx.attr('height', parentHeight);

        const labels = data.map(item => new Date(item.date).toLocaleDateString());
        const impressions = data.map(item => item.impressions);

        if (impressionsChartInstance) {
            impressionsChartInstance.destroy();
        }

        impressionsChartInstance = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Impressions',
                    data: impressions,
                    borderColor: 'rgb(66, 148, 204)',
                    backgroundColor: 'rgba(66, 148, 204, 0.2)',
                    fill: true,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { display: true, title: { display: true, text: 'Date' } },
                    y: { display: true, title: { display: true, text: 'Impressions' } }
                }
            }
        });
    }

    /**
     * Renders or updates the Impressions by Ad Group Pie Chart.
     * @param {Array} data The campaign data from the AJAX response.
     */
    function renderImpressionsByAdGroupChart(data) {
        const ctx = $('#ad-group-chart-canvas');
        if (ctx.length === 0) return;

        const parent = ctx.parent();
        const parentWidth = parent.innerWidth();
        const parentHeight = parent.innerHeight();
        ctx.attr('width', parentWidth);
        ctx.attr('height', parentHeight);

        const labels = data.map(item => item.ad_group_name);
        const impressions = data.map(item => item.impressions);
        
        const backgroundColors = [
            '#2c435d', '#4294cc', '#a8d2e8', '#e8a8d2', '#d2e8a8', '#88a8d2', '#5d2c43'
        ];

        if (impressionsByAdGroupChartInstance) {
            impressionsByAdGroupChartInstance.destroy();
        }

        impressionsByAdGroupChartInstance = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: impressions,
                    backgroundColor: backgroundColors,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                    },
                    tooltip: {
                        enabled: false
                    }
                }
            }
        });
    }

    // A simple function to handle AJAX requests to our custom endpoint for visitor updates.
    const sendAjaxRequestForVisitor = async (action, visitorId) => {
        const ajaxUrl = localizedPublicData.ajax_url;
        const nonce = localizedPublicData.visitor_nonce;

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
                throw new Error('Network response was not ok.');
            }

            const data = await response.json();
            
            if (data.success) {
                console.log(`Visitor ${visitorId} status updated successfully via AJAX.`);
                return true;
            } else {
                console.error('AJAX error for visitor update:', data.data);
                return false;
            }
        } catch (error) {
            console.error('Fetch error for visitor update:', error);
            return false;
        }
    };

    // --- AJAX for Dashboard Filtering (Client list and date range) ---
    const dashboardContent = $('#clients-section');
    const clientList = $('.account-list');
    const dateRangeSelect = $('.duration-select select');

    // Function to load dashboard data via AJAX
    function loadDashboardData(clientId, duration) {
        console.log('loadDashboardData: Called with Client ID:', clientId, 'Duration:', duration);
        dashboardContent.css('opacity', 0.5);

        $.ajax({
            url: cpd_admin_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'cpd_get_dashboard_data',
                nonce: cpd_dashboard_ajax_nonce.nonce,
                client_id: clientId,
                duration: duration
            },
            success: function(response) {
                console.log('loadDashboardData: AJAX Success. Response:', response);
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
                             // console.warn(`Summary data not found for key: ${dataKey}`);
                        }
                    });
                    
                    // Update Charts
                    renderImpressionsChart(data.campaign_data_by_date);
                    renderImpressionsByAdGroupChart(data.campaign_data);

                    // Update Ad Group Table
                    const adGroupTableBody = $('.ad-group-table tbody');
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

                    // Update Visitor Panel
                    const visitorListContainer = $('.visitor-panel .visitor-list');
                    visitorListContainer.empty();
                    if (data.visitor_data && data.visitor_data.length > 0) {
                        data.visitor_data.forEach(visitor => {
                            const memoSealUrl = localizedPublicData.memo_seal_url; 
                            const fullName = (visitor.first_name || '') + ' ' + (visitor.last_name || '');
                            const location = [visitor.city, visitor.state, visitor.zipcode].filter(Boolean).join(', ');
                            const email = visitor.email || '';

                            visitorListContainer.append(`
                                <div class="visitor-card" data-visitor-id="${visitor.visitor_id}">
                                    <div class="visitor-logo">
                                        <img src="${memoSealUrl}" alt="Referrer Logo">
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

                } else {
                    console.error('loadDashboardData: AJAX Error:', response.data);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('loadDashboardData: AJAX request failed. Status:', textStatus, 'Error:', errorThrown, 'Response Text:', jqXHR.responseText);
            },
            complete: function() {
                dashboardContent.css('opacity', 1);
                console.log('loadDashboardData: AJAX request complete.');
            }
        });
    }

    const visitorPanel = $('.visitor-panel');
    console.log('cpd-dashboard.js: Visitor Panel element found (jQuery):', visitorPanel.length > 0 ? 'Yes' : 'No', visitorPanel);

    if (visitorPanel.length > 0) {
        console.log('cpd-dashboard.js: Attaching click listener to Visitor Panel buttons.');
        visitorPanel.on('click', '.add-crm-icon, .delete-icon', async function(event) {
            event.preventDefault();
            console.log('cpd-dashboard.js: Visitor button clicked!');

            const button = $(this);
            const visitorCard = button.closest('.visitor-card');
            const visitorId = visitorCard.data('visitor-id');
            console.log('cpd-dashboard.js: Visitor ID:', visitorId);
            
            let updateAction = '';
            if (button.hasClass('add-crm-icon')) {
                updateAction = 'add_crm';
                if (!confirm('Are you sure you want to flag this visitor for CRM addition?')) {
                    console.log('cpd-dashboard.js: CRM Add confirmation cancelled.');
                    return;
                }
            } else if (button.hasClass('delete-icon')) {
                updateAction = 'archive';
                if (!confirm('Are you sure you want to archive this visitor? They will no longer appear in the list.')) {
                    console.log('cpd-dashboard.js: Archive confirmation cancelled.');
                    return;
                }
            }
            console.log('cpd-dashboard.js: Update action:', updateAction);

            button.prop('disabled', true).css('opacity', 0.6);
            console.log('cpd-dashboard.js: Button disabled.');

            try {
                const success = await sendAjaxRequestForVisitor(updateAction, visitorId);
                console.log('cpd-dashboard.js: sendAjaxRequestForVisitor success status:', success);

                if (success) {
                    const activeClientListItem = clientList.find('li.active');
                    const currentClientId = activeClientListItem.length > 0 ? activeClientListItem.data('client-id') : 'all';
                    const currentDuration = dateRangeSelect.val();

                    loadDashboardData(currentClientId, currentDuration);
                    console.log(`cpd-dashboard.js: Visitor ${visitorId} action "${updateAction}" processed. Dashboard reloaded.`);
                } else {
                    alert('Failed to update visitor status. Please check console for details.');
                    console.error('cpd-dashboard.js: Failed to update visitor status.');
                }
            } catch (error) {
                console.error('cpd-dashboard.js: Error during visitor action AJAX:', error);
                alert('An unexpected error occurred. Please check console.');
            } finally {
                button.prop('disabled', false).css('opacity', 1);
                console.log('cpd-dashboard.js: Button re-enabled.');
            }
        });
    }

    clientList.on('click', 'li', function() {
        console.log('cpd-dashboard.js: Client list item clicked!');
        const clientId = $(this).data('client-id');
        console.log('cpd-dashboard.js: Clicked Client ID:', clientId);
        clientList.find('li').removeClass('active');
        $(this).addClass('active');

        const currentUrl = new URL(window.location.href);
        if (clientId === 'all') {
            currentUrl.searchParams.delete('client_id');
        } else {
            currentUrl.searchParams.set('client_id', clientId);
        }
        window.history.pushState({}, '', currentUrl.toString());

        loadDashboardData(clientId, dateRangeSelect.val());
    });

    dateRangeSelect.on('change', function() {
        console.log('cpd-dashboard.js: Date range dropdown changed!');
        const activeClientListItem = clientList.find('li.active');
        const clientId = activeClientListItem.length > 0 ? activeClientListItem.data('client-id') : 'all';
        loadDashboardData(clientId, $(this).val());
    });

    // --- AJAX for Management Forms ---
    $('#add-client-form').on('submit', function(event) {
        event.preventDefault();
        console.log('cpd-dashboard.js: Add Client form submitted!');
        const form = $(this);
        const submitBtn = form.find('button[type="submit"]');
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

    $('#add-user-form').on('submit', function(event) {
        event.preventDefault();
        console.log('cpd-dashboard.js: Add User form submitted!');
        const form = $(this);
        const submitBtn = form.find('button[type="submit"]');
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

    $('#clients-section .data-table').on('click', '.action-button.delete-client', function(event) {
        event.preventDefault();
        console.log('cpd-dashboard.js: Delete Client button clicked!');
        const row = $(this).closest('tr');
        const clientId = $(this).data('client-id');
        
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
                        row.fadeOut(300, function() {
                            $(this).remove();
                            alert('Client deleted successfully!');
                        });
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
    
    $('#users-section .data-table').on('click', '.action-button.delete-user', function(event) {
        event.preventDefault();
        console.log('cpd-dashboard.js: Delete User button clicked!');
        const row = $(this).closest('tr');
        const userId = $(this).data('user-id');
        
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
                        row.fadeOut(300, function() {
                            $(this).remove();
                            alert('User deleted successfully!');
                        });
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

    const editClientModal = $('#edit-client-modal');
    const editClientForm = $('#edit-client-form');
    
    $('#clients-section .data-table').on('click', '.action-button.edit-client', function() {
        console.log('cpd-dashboard.js: Edit Client button clicked!');
        const row = $(this).closest('tr');
        const clientId = $(this).data('client-id');
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

        editClientModal.fadeIn();
    });

    editClientForm.on('submit', function(event) {
        event.preventDefault();
        console.log('cpd-dashboard.js: Edit Client form submitted!');
        const form = $(this);
        const submitBtn = form.find('button[type="submit"]');
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

    $('.modal .close').on('click', function() {
        console.log('cpd-dashboard.js: Modal close button clicked!');
        $(this).closest('.modal').fadeOut();
    });

    $('.modal').on('click', function(event) {
        if ($(event.target).hasClass('modal')) {
            console.log('cpd-dashboard.js: Modal background clicked!');
            $(this).fadeOut();
        }
    });

    const editUserModal = $('#edit-user-modal');
    const editUserForm = $('#edit-user-form');

    $('#users-section .data-table').on('click', '.action-button.edit-user', function() {
        console.log('cpd-dashboard.js: Edit User button clicked!');
        const row = $(this).closest('tr');
        const userId = $(this).data('user-id');
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

    editUserForm.on('submit', function(event) {
        event.preventDefault();
        console.log('cpd-dashboard.js: Edit User form submitted!');
        const form = $(this);
        const submitBtn = form.find('button[type="submit"]');
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

    if ($.fn.select2) {
        console.log('cpd-dashboard.js: Select2 found, initializing searchable selects.');
        $('.searchable-select').select2({
            dropdownParent: $('.card'),
            placeholder: 'Select a client...',
            allowClear: true
        });
    }

    // NEW: API Key Generation Logic
    $('#generate_api_key_button').on('click', function(event) {
        event.preventDefault();
        const button = $(this);
        const apiKeyField = $('#cpd_api_key_field');
        const originalText = button.text();

        button.prop('disabled', true).text('Generating...');

        $.ajax({
            url: cpd_admin_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'cpd_generate_api_token',
                nonce: cpd_admin_ajax.nonce // Using the general admin nonce
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

    // --- Final Step: Initialize dashboard data on page load ---
    const initialClientIdElement = $('.admin-sidebar .account-list li.active');
    let initialClientId;

    if (initialClientIdElement.length > 0) {
        initialClientId = initialClientIdElement.data('client-id');
    } else {
        initialClientId = 'all'; 
        $('.admin-sidebar .account-list li[data-client-id="all"]').addClass('active');
    }

    const initialDuration = dateRangeSelect.val();

    if ($('#clients-section.active').length > 0) {
        console.log('cpd-dashboard.js: Initializing dashboard data load.');
        loadDashboardData(initialClientId, initialDuration);
    }
});