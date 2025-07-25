/**
 * Admin-specific JavaScript for the Campaign Performance Dashboard plugin.
 * Consolidates all logic into a single jQuery(document).ready block.
 */

jQuery(document).ready(function($) {
    console.log('cpd-dashboard.js: Script started. jQuery document ready.');

    // Access localized data from cpd_dashboard_data (public-facing) for nonces needed on public page
    const localizedPublicData = typeof cpd_dashboard_data !== 'undefined' ? cpd_dashboard_data : {};
    const adminAjaxData = typeof cpd_admin_ajax !== 'undefined' ? cpd_admin_ajax : {};

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

    // --- Chart Rendering Functionality ---
    let impressionsChartInstance = null;
    let impressionsByAdGroupChartInstance = null;
 
    const dashboardContent = $('#clients-section'); // Main dashboard content container (admin-only HTML)
    const clientList = $('.account-list'); // Left sidebar client list (admin-only HTML)
    const dateRangeSelect = $('.duration-select select'); // Date range selector (exists on both)

    // NEW: Function to refresh client table
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

                            clientTableBody.append(`
                                <tr data-client-id="${client.id}"
                                    data-client-name="${client.client_name}"
                                    data-account-id="${client.account_id}"
                                    data-logo-url="${client.logo_url || ''}"
                                    data-webpage-url="${client.webpage_url || ''}"
                                    data-crm-email="${client.crm_feed_email || ''}">
                                    <td>${client.client_name}</td>
                                    <td>${client.account_id}</td>
                                    <td>${logoHtml}</td>
                                    <td>${webpageHtml}</td>
                                    <td>${client.crm_feed_email || ''}</td>
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
                        clientTableBody.append('<tr><td colspan="6" class="no-data">No clients found.</td></tr>');
                    }
                }
            },
            error: function() {
                console.error('Failed to refresh client table');
            }
        });
    }

    // NEW: Function to refresh user table
    function refreshUserTable() {
        // console.log('cpd-dashboard.js: Refreshing user table...');
        
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
     * Renders or updates the Impressions Line Chart.
     * @param {Array} data The campaign data from the AJAX response.
     */
    function renderImpressionsChart(data) {
        console.log ("Impressions Chart in Admin JS. Should not be here");
        return;
        
        const ctx = $('#impressions-chart-canvas');
        if (ctx.length === 0) {
            console.warn("Canvas #impressions-chart-canvas not found.");
            return;
        }

        // IMPORTANT: Destroy existing chart instance before creating a new one
        if (impressionsChartInstance) {
            impressionsChartInstance.destroy();
            impressionsChartInstance = null; // Clear the reference
            console.log("Destroyed existing Impressions Chart instance.");
        }

        const parent = ctx.parent();
        const parentWidth = parent.innerWidth();
        const parentHeight = parent.innerHeight();
        ctx.attr('width', parentWidth);
        ctx.attr('height', parentHeight);

        const labels = data.map(item => new Date(item.date).toLocaleDateString());
        const impressions = data.map(item => item.impressions);

        impressionsChartInstance = new Chart(ctx[0].getContext('2d'), { // Get the native DOM element and its 2D context
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
                },
                 plugins: { // Add this to handle tooltip in admin dashboard charts
                    tooltip: {
                        enabled: true // Enable tooltips for better data inspection in admin
                    },
                    legend: {
                        display: true // Display legend for clarity
                    }
                }
            }
        });
        // console.log("Created new Impressions Chart instance.");
    }

    /**
     * Renders or updates the Impressions by Ad Group Pie Chart.
     * @param {Array} data The campaign data from the AJAX response.
     */
    function renderImpressionsByAdGroupChart(data) {
        console.log ("Impressions Chart in Admin JS. Should not be here");
        return;
        
        const ctx = $('#ad-group-chart-canvas');
        if (ctx.length === 0) {
            console.warn("Canvas #ad-group-chart-canvas not found.");
            return;
        }

        // IMPORTANT: Destroy existing chart instance before creating a new one
        if (impressionsByAdGroupChartInstance) {
            impressionsByAdGroupChartInstance.destroy();
            impressionsByAdGroupChartInstance = null; // Clear the reference
            console.log("Destroyed existing Impressions By Ad Group Chart instance.");
        }

        const parent = ctx.parent();
        const parentWidth = parent.innerWidth();
        const parentHeight = parent.innerHeight();
        ctx.attr('width', parentWidth);
        ctx.attr('height', parentHeight);

        // Filter out ad groups with zero impressions to avoid display issues in pie chart
        const filteredData = data.filter(item => item.impressions > 0);
        if (filteredData.length === 0) {
             console.log("No ad group data with impressions > 0 to render pie chart.");
             // Optionally, clear the canvas or display a "No Data" message on the canvas itself
             const canvas = ctx[0];
             const context = canvas.getContext('2d');
             context.clearRect(0, 0, canvas.width, canvas.height); // Clear previous drawing
             context.fillStyle = '#555';
             context.font = '14px Montserrat, sans-serif';
             context.textAlign = 'center';
             context.fillText('No Ad Group Data Available', canvas.width / 2, canvas.height / 2);
             return;
        }

        const labels = filteredData.map(item => item.ad_group_name);
        const impressions = filteredData.map(item => item.impressions);
        
        const backgroundColors = [
            '#2c435d', '#4294cc', '#a8d2e8', '#e8a8d2', '#d2e8a8', '#88a8d2', '#5d2c43',
            '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40', '#E7E9ED'
        ]; // Added more colors for variety

        impressionsByAdGroupChartInstance = new Chart(ctx[0].getContext('2d'), { // Get the native DOM element and its 2D context
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
                        // For doughnut charts, tooltips should generally be enabled
                        // unless you have a custom tooltip solution.
                        enabled: true,
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.parsed !== null) {
                                    label += new Intl.NumberFormat('en-US', { style: 'decimal' }).format(context.parsed) + ' impressions';
                                }
                                return label;
                            }
                        }
                    }
                }
            }
        });
        // console.log("Created new Impressions By Ad Group Chart instance.");
    }

    // A simple function to handle AJAX requests to our custom endpoint for visitor updates.
    // This is primarily for the *dashboard* visitor actions.
    const sendAjaxRequestForVisitor = async (action, visitorId) => {
        const ajaxUrl = localizedPublicData.ajax_url || adminAjaxData.ajax_url;
        const nonce = localizedPublicData.visitor_nonce; // This nonce is for public-facing visitor actions

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
                // If response.ok is false, try to read the error from the response body if it's JSON
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
    
    
    
    
    
    

    // Function to load dashboard data via AJAX
    function loadDashboardData(clientId, duration) {
        console.log('ADMIN loadDashboardData: Called with Client ID:', clientId, 'Duration:', duration);
        if(dashboardContent.length === 0) { // Defensive check
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
                             el.text('0'); // Default to 0 if no data
                        }
                    });
                    
                    // Update Charts
                    console.log('ADMIN loadDashboardData: Rendering charts with campaign data:', data.campaign_data_by_date);
                    renderImpressionsChart(data.campaign_data_by_date);
                    renderImpressionsByAdGroupChart(data.campaign_data);

                    // Update Ad Group Table
                    const adGroupTableBody = $('.ad-group-table tbody');
                    if (adGroupTableBody.length > 0) { // Defensive check
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
                    if (visitorListContainer.length > 0) { // Defensive check
                        visitorListContainer.empty();
                        if (data.visitor_data && data.visitor_data.length > 0) {
                            data.visitor_data.forEach(visitor => {
                                // Use the new properties from AJAX response for alt text and tooltips
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
                dashboardContent.css('opacity', 1);
                // Only manipulate dashboardContent (opacity) if we are on the actual admin page AND it was found.
                if (isAdminPage && dashboardContent.length > 0) {
                    dashboardContent.css('opacity', 1);
                }
                console.log('loadDashboardData: AJAX request complete.');
            }
        });
    }

    const isAdminPage = document.body.classList.contains('campaign-dashboard_page_cpd-dashboard-management'); 
    
    if (isAdminPage) {
        console.log('cpd-dashboard.js: Admin-specific UI listeners attaching.');
        // --- AJAX for Dashboard Filtering (Client list and date range) ---

        window.addEventListener('load', function() {
            console.log('cpd-dashboard.js: window.load event fired. Delaying navigation initialization for full DOM readiness.');

            // Use a small setTimeout to ensure all DOM elements are rendered.
            // Adjust delay if necessary, but 100ms is usually sufficient for this type of issue.
            setTimeout(function() {
                console.log('cpd-dashboard.js: Navigation initialization (delayed) starting.');

                const navLinks = document.querySelectorAll('.admin-sidebar nav a[data-target]');
                const sections = document.querySelectorAll('.admin-main-content .section-content');

                // Defensive check: If navLinks or sections are still empty after delay. If so, there's a deeper structural issue.
                if (navLinks.length === 0 || sections.length === 0) {
                    console.error("cpd-dashboard.js: CRITICAL ERROR: Navigation links or sections not found even after delay. Check admin-page.php structure and IDs.");
                    return; // Stop execution to prevent further errors
                }

                const initialHash = window.location.hash.substring(1);
                const defaultSectionId = 'clients-section'; // Default to clients section

                // Function to set active section based on hash or default
                function setActiveSection() {
                    let targetHashId = initialHash;
                    // If hash is empty or doesn't correspond to an existing section ID, default to 'clients-section'
                    // Ensure the element with targetHashId + '-section' exists.
                    if (!targetHashId || !document.getElementById(targetHashId + '-section')) {
                        targetHashId = defaultSectionId.replace('-section', ''); // e.g., 'clients'
                    }

                    console.log("setActiveSection: Target Hash ID (from URL or default):", targetHashId);

                    // Remove active classes from all nav links and sections
                    navLinks.forEach(l => {
                        if (l) l.classList.remove('active'); // Defensive check
                    });
                    sections.forEach(s => {
                        if (s) s.classList.remove('active'); // Defensive check
                    });

                    // Try to find the target section and link based on the determined targetHashId
                    const targetSection = document.getElementById(targetHashId + '-section');
                    console.log("setActiveSection: Looking for targetSection ID:", targetHashId + '-section', "Found:", targetSection);

                    const targetLink = document.querySelector(`.admin-sidebar nav a[data-target="${targetHashId}-section"]`);
                    console.log("setActiveSection: Looking for targetLink selector:", `.admin-sidebar nav a[data-target="${targetHashId}-section"]`, "Found:", targetLink);

                    if (targetSection && targetLink) {
                        targetSection.classList.add('active');
                        targetLink.classList.add('active');
                    } else {
                        console.warn("setActiveSection: Fallback to default, targetSection or targetLink not found for:", targetHashId);
                        // Fallback to default if the determined target (from hash or original default) is not found
                        const fallbackSection = document.getElementById(defaultSectionId);
                        const fallbackLink = document.querySelector(`.admin-sidebar nav a[data-target="${defaultSectionId}"]`);

                        if (fallbackSection) fallbackSection.classList.add('active');
                        if (fallbackLink) fallbackLink.classList.add('active');

                        window.location.hash = defaultSectionId.replace('-section', ''); // Ensure URL reflects default
                    }

                    // Load eligible visitors if CRM email management section is active
                    // This function will be defined later in the CRM section update
                    // console.log("setActiveSection: Checking if CRM Emails section is active for loading visitors.");
                    if (targetHashId === 'crm-email-management') {
                        // Make sure loadEligibleVisitors is defined within scope or globally accessible if called here
                        if (typeof loadEligibleVisitors === 'function') {
                            console.log("setActiveSection: Activating CRM Emails section, calling loadEligibleVisitors.");
                            loadEligibleVisitors();
                        } else {
                            console.warn("loadEligibleVisitors function not found when trying to activate CRM Emails section.");
                        }
                    }
                }

                // Call it once after delay
                setActiveSection();

                navLinks.forEach(link => {
                    link.addEventListener('click', (event) => {
                        // Check if the clicked link is NOT the "View Dashboard" link, which should open in new tab
                        if (link.getAttribute('target') === '_blank') {
                            return; // Let the default link behavior happen (open in new tab)
                        }

                        event.preventDefault();

                        navLinks.forEach(l => l.classList.remove('active'));
                        sections.forEach(s => s.classList.remove('active'));

                        const targetId = link.getAttribute('data-target'); // This includes '-section'
                        const cleanedTargetId = targetId.replace('-section', ''); // For hash
                        const targetSection = document.getElementById(targetId);

                        if (targetSection) {
                            targetSection.classList.add('active');
                            link.classList.add('active');
                            if (history.pushState) {
                                history.pushState(null, null, '#' + cleanedTargetId);
                            } else {
                                window.location.hash = '#' + cleanedTargetId;
                            }

                            // Load eligible visitors if CRM email management section is selected
                            if (cleanedTargetId === 'crm-email-management') {
                                if (typeof loadEligibleVisitors === 'function') {
                                    loadEligibleVisitors();
                                } else {
                                    console.warn("loadEligibleVisitors function not found when trying to load CRM Emails section via click.");
                                }
                            }
                        }
                    });
                });

                // Handle hash changes from browser back/forward or manual hash entry
                window.addEventListener('hashchange', setActiveSection);

            }, 100); // 100ms delay. Adjust if needed, but start here.
        }); // End window.onload for navigation
            
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
                        // Check if clientList exists and has an active item before trying to access its data
                        const currentClientId = (clientList.length > 0 && clientList.find('li.active').length > 0) ? clientList.find('li.active').data('client-id') : 'all';
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

        // Only attach click listener if clientList element exists
        if (clientList.length > 0) {
            clientList.on('click', 'li', function() {
                console.log('cpd-dashboard.js: Client list item clicked!');
                const listItem = $(this); // Reference to the clicked <li>
                const clientId = listItem.data('client-id'); 
                console.log('cpd-dashboard.js: Clicked Client ID:', clientId);
                clientList.find('li').removeClass('active');
                listItem.addClass('active'); // Apply active class to the clicked item

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
                        // NEW: Refresh the client table
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
                        // NEW: Refresh the user table
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

        $('#clients-section .data-table').on('click', '.action-button.delete-client', function(event) {
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
                            // NEW: Refresh the client table instead of just removing the row
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
        
        $('#users-section .data-table').on('click', '.action-button.delete-user', function(event) {
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
                            // NEW: Refresh the user table instead of just removing the row
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

        const editClientModal = $('#edit-client-modal');
        const editClientForm = $('#edit-client-form');
        
        $('#clients-section .data-table').on('click', '.action-button.edit-client', function() {
            console.log('cpd-dashboard.js: Edit Client button clicked!');
            const row = $(this).closest('tr');
            const clientId = row.data('client-id'); // Use data attribute for consistency
            console.log('cpd-dashboard.js: Edit Client ID:', clientId);
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

            // NEW: AI Intelligence fields
            const aiToggle = $('#edit_ai_intelligence_enabled');
            const contextGroup = $('#edit-client-context-group');
            const contextField = $('#edit_client_context_info');
            
            if (aiToggle.length) {
                const isAiEnabled = row.data('ai-intelligence-enabled') == 1 || row.data('ai-intelligence-enabled') === true;
                aiToggle.prop('checked', isAiEnabled);
                console.log('cpd-dashboard.js: AI Intelligence enabled:', isAiEnabled);
            }
            
            if (contextField.length) {
                contextField.val(row.data('client-context-info') || '');
            }
            
            if (contextGroup.length) {
                const shouldShow = row.data('ai-intelligence-enabled') == 1 || row.data('ai-intelligence-enabled') === true;
                contextGroup.toggle(shouldShow);
            }

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
                        // NEW: Refresh the client table
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
                        // NEW: Refresh the user table
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

        if ($.fn.select2) {
            console.log('cpd-dashboard.js: Select2 found, initializing searchable selects.');
            // Apply Select2 to all .searchable-select elements
            $('.searchable-select').each(function() {
                // Determine the dropdown parent to avoid clipping issues with modals
                let dropdownParent = $(this).closest('.modal').length ? $(this).closest('.modal') : $(this).parent();
                if ($(this).attr('id') === 'new_linked_client' || $(this).attr('id') === 'edit_linked_client' || $(this).attr('id') === 'on_demand_client_select' || $(this).attr('id') === 'eligible_visitors_client_filter') {
                    // For dropdowns that are not necessarily in a modal, target the specific card or section for a better visual fit
                    dropdownParent = $(this).closest('.card').length ? $(this).closest('.card') : $(document.body);
                }

                $(this).select2({
                    dropdownParent: dropdownParent,
                    placeholder: 'Select an option...',
                    allowClear: true
                });
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

        // NEW: CRM Email Management Logic
        const crmClientFilter = $('#crm_client_filter');
        const triggerOnDemandSendButton = $('#trigger_on_demand_send');
        const eligibleVisitorsTableBody = $('#eligible-visitors-table tbody');

        function loadEligibleVisitors() {
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

        // Update button state and tooltip based on selection
        function updateButtonState() {
            const selectedValue = crmClientFilter.val();
            if (selectedValue === 'all') {
                triggerOnDemandSendButton.prop('disabled', true);
                triggerOnDemandSendButton.attr('title', 'Please select a specific client to send on-demand emails');
            } else {
                triggerOnDemandSendButton.prop('disabled', false);
                triggerOnDemandSendButton.attr('title', '');
            }
        }
        
        // Event handlers
        crmClientFilter.on('change', function() {
            loadEligibleVisitors();
            updateButtonState();
        });

        // Initialize button state
        updateButtonState();

        triggerOnDemandSendButton.off('click').on('click', function() {
            const button = $(this);
            const selectedAccountId = crmClientFilter.val();
            if (selectedAccountId === 'all') {
                alert('Please select a specific client to send on-demand emails.');
                return;
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
                            loadEligibleVisitors(); // Reload the eligible visitors list after send
                        } else {
                            alert('Error: ' + response.data.message);
                        }
                    },
                    error: function() {
                        alert('An error occurred during the on-demand send request.');
                    },
                    complete: function() {
                        button.prop('disabled', false).html('<i class="fas fa-paper-plane"></i> Send On-Demand CRM Email');
                        updateButtonState(); // Recheck button state
                    }
                });
            }
        });

        eligibleVisitorsTableBody.on('click', '.undo-crm-button', function() {
            return;
            const button = $(this);
            const visitorInternalId = button.data('visitor-internal-id');

            if (confirm('Are you sure you want to undo the CRM flag for this visitor? They will reappear in the dashboard visitor list if not archived.')) {
                button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

                $.ajax({
                    url: cpd_admin_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'cpd_undo_crm_added',
                        nonce: cpd_admin_ajax.nonce,
                        visitor_id_internal: visitorInternalId
                    },
                    success: function(response) {
                        if (response.success) {
                            alert(response.data.message);
                            loadEligibleVisitors(); // Reload the list
                        } else {
                            alert('Error: ' + response.data.message);
                        }
                    },
                    error: function() {
                        alert('An error occurred while undoing the CRM flag.');
                    },
                    complete: function() {
                        button.prop('disabled', false).html('<i class="fas fa-undo"></i>');
                    }
                });
            }
        });

        // --- Final Step: Initialize dashboard data on page load for #clients-section only ---
        // This is for the admin's main dashboard view (if they navigate to it).
        // The public dashboard JS handles data for the client-facing page.
        // Ensure dashboardContent and clientList are correctly scoped/defined if used here.
        if ($('#clients-section.active').length > 0) { // Check if clients-section is the active tab initially
            // Delay this part as well, or ensure elements are ready
            setTimeout(function() {
                const initialClientIdElement = $('.admin-sidebar .account-list li.active');
                let initialClientId;

                if (initialClientIdElement.length > 0) {
                    initialClientId = initialClientIdElement.data('client-id');
                } else {
                    initialClientId = 'all'; 
                    // Only try to add class if the element actually exists
                    const allClientsListItem = $('.admin-sidebar .account-list li[data-client-id="all"]');
                    if (allClientsListItem.length > 0) {
                        allClientsListItem.addClass('active');
                    }
                }

                const initialDuration = dateRangeSelect.val();
                console.log('cpd-dashboard.js: Initializing dashboard data load for admin clients section.');
                loadDashboardData((initialClientId === 'all') ? null : initialClientId, initialDuration);
            }, 150); // Small delay to ensure all DOM elements are rendered
        }
    } else {
        console.log('cpd-dashboard.js: Not on admin management page. Admin UI not initialized, but shared functions are available.');
    }

    // Referrer Logo Mapping Management
    $('#add-referrer-mapping').on('click', function() {
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
        
        // Add a subtle animation to the new row
        const $newRow = $('#referrer-logo-mappings .referrer-mapping-row:last-child');
        $newRow.hide().fadeIn(300);
    });
    
    // Remove referrer mapping row
    $(document).on('click', '.remove-mapping', function() {
        console.log('cpd-dashboard.js: Remove referrer mapping clicked');
        const $row = $(this).closest('.referrer-mapping-row');
        
        // Add confirmation for removing mapping
        if (confirm('Are you sure you want to remove this referrer logo mapping?')) {
            $row.fadeOut(300, function() {
                $(this).remove();
            });
        }
    });
    
    // Domain input formatting helper
    $(document).on('input', 'input[name="referrer_domains[]"]', function() {
        const $input = $(this);
        let domain = $input.val().toLowerCase().trim();
        
        // Remove protocol if user typed it
        domain = domain.replace(/^https?:\/\//, '');
        // Remove www. if user typed it
        domain = domain.replace(/^www\./, '');
        // Remove trailing slash
        domain = domain.replace(/\/$/, '');
        
        if (domain !== $input.val()) {
            $input.val(domain);
        }
    });
    
    // Form validation for referrer mappings
    $('#settings-form').on('submit', function(e) {
        const domains = $('input[name="referrer_domains[]"]');
        const logos = $('input[name="referrer_logos[]"]');
        let hasError = false;
        
        // Remove previous error styling
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

    // ==================================================================
    // INTELLIGENCE SETTINGS FUNCTIONALITY
    // ==================================================================
    
    // Intelligence Settings JavaScript - Add after existing code
    console.log('CPD Intelligence Settings: JavaScript loaded');
    
    // 1. AJAX URL (use the existing global)
    const ajaxUrl = typeof cpd_admin_ajax !== 'undefined' ? cpd_admin_ajax.ajax_url : ajaxurl;
    
    // 2. Webhook testing functionality
    const testWebhookBtn = $('#test-webhook-btn');
    const testResult = $('#webhook-test-result');
    
    if (testWebhookBtn.length) {
        testWebhookBtn.on('click', function() {
            const webhookUrl = $('#intelligence_webhook_url').val();
            const apiKey = $('#makecom_api_key').val();
            
            if (!webhookUrl || !apiKey) {
                testResult.html('<span style="color: #dc3545;">⚠️ Please enter both Webhook URL and API Key</span>');
                return;
            }
            
            testWebhookBtn.prop('disabled', true).text('Testing...');
            testResult.html('<span style="color: #6c757d;">🔄 Testing connection...</span>');
            
            // AJAX request to test webhook
            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'cpd_test_intelligence_webhook',
                    nonce: cpd_admin_ajax.nonce, // Use existing nonce system
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
    
    // 3. Intelligence Settings Form Submission
    const intelligenceForm = $('#intelligence-settings-form');
    if (intelligenceForm.length) {
        intelligenceForm.off('submit').on('submit', function(e) { // Add .off('submit') to prevent double binding
            e.preventDefault();
            e.stopPropagation(); // Prevent event bubbling
            
            const submitBtn = intelligenceForm.find('button[type="submit"]');
            const originalText = submitBtn.text();
            
            // Prevent double submission
            if (submitBtn.prop('disabled')) {
                return false;
            }
            
            submitBtn.prop('disabled', true).text('Saving...');
            
            // Get form data manually to ensure proper serialization
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
            
            return false; // Ensure form doesn't submit normally
        });
    }
    
    // 4. Intelligence Defaults Form Submission
    const defaultsForm = $('#intelligence-defaults-form');
    if (defaultsForm.length) {
        defaultsForm.on('submit', function(e) {
            e.preventDefault();
            
            const submitBtn = defaultsForm.find('button[type="submit"]');
            const originalText = submitBtn.text();
            
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
    
    // 5. AI Intelligence Toggle for Add Client Form
    const newAiToggle = $('#new_ai_intelligence_enabled');
    const newContextGroup = $('#new-client-context-group');
    
    if (newAiToggle.length && newContextGroup.length) {
        // Initial state
        newContextGroup.toggle(newAiToggle.is(':checked'));
        
        newAiToggle.on('change', function() {
            const isChecked = $(this).is(':checked');
            newContextGroup.toggle(isChecked);
            if (isChecked) {
                newContextGroup[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        });
    }
    
    // 6. AI Intelligence Toggle for Edit Client Form
    const editAiToggle = $('#edit_ai_intelligence_enabled');
    const editContextGroup = $('#edit-client-context-group');
    
    if (editAiToggle.length && editContextGroup.length) {
        editAiToggle.on('change', function() {
            const isChecked = $(this).is(':checked');
            editContextGroup.toggle(isChecked);
            if (isChecked) {
                editContextGroup[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        });
    }
    
    console.log('CPD Intelligence Settings: All event listeners attached');

});