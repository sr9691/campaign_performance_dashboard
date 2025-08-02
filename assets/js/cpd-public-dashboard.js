// assets/js/cpd-public-dashboard.js
console.log('*** cpd-public-dashboard.js file HAS LOADED AND IS EXECUTING! ***');

/**
 * Public-facing JavaScript for the Campaign Performance Dashboard.
 */

document.addEventListener('DOMContentLoaded', () => {
    // Check if the dashboard container exists before running the script.
    const dashboardContainer = document.querySelector('.dashboard-container');
    if (!dashboardContainer) {
        return;
    }

    // Access localized data
    const localizedData = typeof cpd_dashboard_data !== 'undefined' ? cpd_dashboard_data : {};
    const isAdminUser = localizedData.is_admin_user;

    // Function to prepare URL data for insertion into a data attribute
    function prepareUrlsForDataAttribute(urlsData) {
        if (!urlsData) {
            return '[]'; // Return an empty JSON array string if no data
        }

        let urlArray = [];
        if (typeof urlsData === 'string') {
            // Attempt to parse as JSON first (handles cases where PHP might accidentally JSON encode,
            // or if it's a malformed JSON string like `[" url "]`)
            try {
                const parsed = JSON.parse(urlsData);
                if (Array.isArray(parsed)) {
                    // If it's a valid JSON array, use it directly. Trim each URL.
                    urlArray = parsed.map(url => String(url).trim()).filter(url => url !== '');
                } else {
                    // Not an array after JSON.parse, or it was just a string, treat as comma-separated
                    urlArray = urlsData.split(',').map(url => url.trim()).filter(url => url !== '');
                }
            } catch (e) {
                // Not valid JSON, assume it's a plain comma-separated string
                urlArray = urlsData.split(',').map(url => url.trim()).filter(url => url !== '');
            }
        } else if (Array.isArray(urlsData)) {
            // If it's already a JS array (unlikely from DB string, but good for robustness)
            urlArray = urlsData.map(url => String(url).trim()).filter(url => url !== '');
        }

        // Filter out any "None" strings or empty strings that might have snuck in
        urlArray = urlArray.filter(url => url !== 'None' && url.trim() !== '');

        // Finally, JSON.stringify the array. This creates a valid string for the data attribute
        // and correctly escapes any internal quotes or special characters.
        return JSON.stringify(urlArray);
    }

    // --- Chart Rendering Functionality ---
    let impressionsChartInstance = null;
    let impressionsByAdGroupChartInstance = null;

    /**
     * Renders or updates the Impressions Line Chart.
     * @param {Array} data The campaign data aggregated by date.
     */
    function renderImpressionsChart(data) {
        const ctx = document.getElementById('impressions-chart-canvas');
        if (!ctx) return;

        const parent = ctx.parentElement;
        const parentWidth = parent ? parent.offsetWidth : 0;
        const parentHeight = parent ? parent.offsetHeight : 0;
        
        ctx.width = parentWidth;
        ctx.height = parentHeight;

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
     * @param {Array} data The campaign data aggregated by ad group.
     */
    function renderImpressionsByAdGroupChart(data) {
        const ctx = document.getElementById('ad-group-chart-canvas');
        if (!ctx) return;

        const parent = ctx.parentElement;
        const parentWidth = parent ? parent.offsetWidth : 0;
        const parentHeight = parent ? parent.offsetHeight : 0;
        
        ctx.width = parentWidth;
        ctx.height = parentHeight;

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
                        callbacks: {
                            label: function(context) {
                                let label = context.label || ''; // context.label is the ad_group_name
                                if (label) {
                                    label += ': ';
                                }
                                if (context.parsed !== null) {
                                    label += new Intl.NumberFormat().format(context.parsed) + ' Impressions';
                                }
                                return label;
                            }
                        }
                    }
                }
            }
        });
    }

    // A simple function to handle AJAX requests for visitor status updates
    const sendAjaxRequestForVisitorStatus = async (action, visitorId) => {
        const ajaxUrl = localizedData.ajax_url;
        const nonce = localizedData.visitor_nonce;

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
                throw new Error('Network response was not ok.');
            }

            const data = await response.json();
            
            if (data.success) {
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


    // AI Intelligence Request Function
    const sendAjaxRequestForVisitorIntelligence = async (visitorId) => {
        const ajaxUrl = localizedData.ajax_url;
        const nonce = localizedData.intelligence_nonce || localizedData.visitor_nonce;
        

        if (!ajaxUrl || !nonce) {
            console.error('sendAjaxRequestForVisitorIntelligence: Localized data missing.');
            return { success: false, error: 'Configuration error' };
        }

        const formData = new FormData();
        formData.append('action', 'cpd_request_visitor_intelligence');
        formData.append('nonce', nonce);
        formData.append('visitor_id', visitorId);

        try {
            const response = await fetch(ajaxUrl, {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                throw new Error(`Network response was not ok: ${response.status}`);
            }

            const data = await response.json();
            console.log('AI Intelligence Response:', data); // Debug logging
            
            // FIXED: Proper WordPress AJAX response handling
            if (data.success) {
                return {
                    success: true,
                    status: data.data.status,
                    intelligence_id: data.data.intelligence_id,
                    message: data.data.message,
                    intelligence_data: data.data.intelligence_data || null
                };
            } else {
                // WordPress sends errors in data.data when using wp_send_json_error
                let errorMessage = 'Unknown error occurred';
                
                if (typeof data.data === 'string') {
                    errorMessage = data.data;
                } else if (data.data && data.data.message) {
                    errorMessage = data.data.message;
                } else if (data.message) {
                    errorMessage = data.message;
                }
                
                console.error('sendAjaxRequestForVisitorIntelligence: Server error:', errorMessage);
                return { 
                    success: false, 
                    error: errorMessage 
                };
            }
            
        } catch (error) {
            console.error('sendAjaxRequestForVisitorIntelligence: Fetch error:', error);
            return { 
                success: false, 
                error: `Network error: ${error.message}` 
            };
        }
    };
    
    // Function to get visitor intelligence status
    const getVisitorIntelligenceStatus = async (visitorId) => {
        const ajaxUrl = localizedData.ajax_url;
        const nonce = localizedData.intelligence_nonce || localizedData.visitor_nonce;

        if (!ajaxUrl || !nonce) {
            return { success: false, error: 'Configuration error' };
        }

        const formData = new FormData();
        formData.append('action', 'cpd_get_visitor_intelligence_status');
        formData.append('nonce', nonce);
        formData.append('visitor_id', visitorId);

        try {
            const response = await fetch(ajaxUrl, {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                throw new Error('Network response was not ok.');
            }

            const data = await response.json();
            return data;
            
        } catch (error) {
            console.error('getVisitorIntelligenceStatus: Fetch error:', error);
            return { success: false, error: 'Network error' };
        }
    };

    // Function to update AI intelligence icon state
    function updateIntelligenceIconState(visitorCard, status) {
        const intelligenceIcon = visitorCard.querySelector('.ai-intelligence-icon');
        if (!intelligenceIcon) {
            console.log('No intelligence icon found in visitor card');
            return;
        }
    
        console.log('Updating intelligence icon state to:', status);
    
        // Remove all status classes
        intelligenceIcon.classList.remove('status-none', 'status-pending', 'status-completed', 'status-failed');
        
        // Add appropriate status class
        intelligenceIcon.classList.add(`status-${status}`);
        
        // Update data attribute
        intelligenceIcon.dataset.intelligenceStatus = status;
        
        // Update title attribute for tooltip
        let title = '';
        switch (status) {
            case 'none':
                title = 'Generate AI Intelligence';
                break;
            case 'pending':
                title = 'AI Intelligence Processing...';
                break;
            case 'completed':
                title = 'AI Intelligence Available (click for details)';
                break;
            case 'failed':
                title = 'AI Intelligence Failed (click to retry)';
                break;
        }
        intelligenceIcon.setAttribute('title', title);
    }

    function isClientAiEnabled(visitorCard) {
        const enabled = visitorCard.dataset.clientAiEnabled === 'true';
        console.log('Client AI enabled check:', enabled);
        return enabled;
    }


    // --- Client-specific function to load all dashboard data via AJAX ---
    async function loadClientDashboardData(explicitClientId = null, explicitDuration = null) {
        // 1. Determine the Client ID to use for the AJAX call
        let clientIdToUse;
        if (explicitClientId !== null) {
            clientIdToUse = explicitClientId; // Prioritize explicitly passed ID (from client list click)
        } else {
            // Fallback: Check URL parameter first (for page loads/refreshes where admin sets client_id)
            const urlParams = new URLSearchParams(window.location.search);
            let selectedClientIdFromUrl = urlParams.get('client_id');

            // If admin, use URL param if present, otherwise localizedData.current_client_account_id (for client users)
            clientIdToUse = isAdminUser && selectedClientIdFromUrl ? selectedClientIdFromUrl : localizedData.current_client_account_id;
        }

        // 2. Determine the Duration to use for the AJAX call
        const durationToUse = explicitDuration !== null ? explicitDuration : document.getElementById('duration-selector').value;

        // Defensive check: If no client ID is determined and it's not an admin, warn and exit.
        if (!clientIdToUse && !isAdminUser) {
            console.warn('loadClientDashboardData: No client ID available for client view. Cannot load data.');
            return;
        }

        // 3. Handle "All Clients" selection: Convert 'all' string to null for the data provider
        const actualClientIdForAjax = (clientIdToUse === 'all') ? null : clientIdToUse;

        const mainContent = document.querySelector('.main-content');
        if (mainContent) mainContent.style.opacity = '0.5';

        try {
            const response = await fetch(localizedData.ajax_url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'cpd_get_dashboard_data',
                    nonce: localizedData.dashboard_nonce,
                    client_id: actualClientIdForAjax,
                    duration: durationToUse
                }).toString()
            });

            if (!response.ok) {
                throw new Error('Network response was not ok.');
            }

            const responseData = await response.json();

            if (responseData.success) {
                const data = responseData.data;
                const clientLogoImg = document.querySelector('.dashboard-header .client-logo-container img');

                // Update Client Logo in Header (Only applicable if a specific client is selected)
                if (actualClientIdForAjax !== null && data.client_logo_url) {
                    if (clientLogoImg) {
                        clientLogoImg.src = data.client_logo_url;
                    }
                } else if (actualClientIdForAjax === null) {
                    // If "All Clients" is selected, revert to default logo
                    if (clientLogoImg) {
                        clientLogoImg.src = localizedData.memo_logo_url; // Or a generic "all clients" logo
                    }
                }

                // Update Summary Cards 
                document.querySelectorAll('.summary-card .value').forEach(el => {
                    const dataKey = el.nextElementSibling.dataset.summaryKey; // Get from new data-key attribute
                    if (dataKey && data.summary_metrics && data.summary_metrics[dataKey]) {
                        el.textContent = data.summary_metrics[dataKey];
                    } else {
                        el.textContent = '0'; // Default to 0 if no data
                    }
                });

                // Update Charts
                renderImpressionsChart(data.campaign_data_by_date);
                renderImpressionsByAdGroupChart(data.campaign_data);

                // Update Ad Group Table
                const adGroupTableBody = document.querySelector('.ad-group-table tbody');
                if (adGroupTableBody) {
                    adGroupTableBody.innerHTML = '';
                    if (data.campaign_data && data.campaign_data.length > 0) {
                        data.campaign_data.forEach(item => {
                            adGroupTableBody.insertAdjacentHTML('beforeend', `
                                <tr>
                                    <td>${item.ad_group_name}</td>
                                    <td>${(item.impressions).toLocaleString()}</td>
                                    <td>${(item.reach).toLocaleString()}</td>
                                    <td>${(item.clicks).toLocaleString()}</td>
                                    <td>${(item.ctr !== null ? parseFloat(item.ctr) : 0).toFixed(2)}%</td>
                                </tr>
                            `);
                        });
                    } else {
                        adGroupTableBody.insertAdjacentHTML('beforeend', '<tr><td colspan="5" class="no-data">No campaign data found for this period.</td></tr>');
                    }
                }

                // Update Visitor Panel
                const visitorListContainer = document.querySelector('.visitor-panel .visitor-list');
                if (visitorListContainer) {
                    visitorListContainer.innerHTML = '';
                    if (data.visitor_data && data.visitor_data.length > 0) {

                        data.visitor_data.forEach(visitor => {
                            // Get logo URL, alt text, and tooltip from AJAX response
                            const visitorLogoUrl = visitor.logo_url || visitor.referrer_logo_url || localizedData.memo_seal_url;
                            const visitorAltText = visitor.alt_text || visitor.referrer_alt_text || 'Visitor Logo';
                            const visitorTooltipText = visitor.tooltip_text || visitor.referrer_tooltip || 'No referrer information';
                            
                            const fullName = (visitor.first_name || '') + ' ' + (visitor.last_name || '');
                            const companyName = visitor.company_name || 'Unknown Company';
                            const jobTitle = visitor.job_title || 'Unknown Title';
                            const email = visitor.email || 'Unknown Email';
                            const linkedinUrl = visitor.linkedin_url || '#';
                            const hasLinkedIn = visitor.linkedin_url && visitor.linkedin_url.trim() !== '';
                            const location = [visitor.city, visitor.state, visitor.zipcode].filter(Boolean).join(', ');

                            // Use the AJAX data directly - it's already in the correct format
                            const recentPageUrls = visitor.recent_page_urls || [];
                            const safeRecentPageUrlsForAttr = JSON.stringify(recentPageUrls);

                            // Check if client has AI intelligence enabled
                            // const clientAiEnabled = visitor.ai_intelligence_enabled === true || visitor.ai_intelligence_enabled === 1;
                            const clientAiEnabled = visitor.ai_intelligence_enabled === true || 
                                visitor.ai_intelligence_enabled === 1 || 
                                visitor.ai_intelligence_enabled === "1" || 
                                visitor.ai_intelligence_enabled === "true";
                            
                            // Get intelligence status for this visitor
                            const intelligenceStatus = visitor.intelligence_status || 'none';

                            // Store additional fields in data attributes for the modal
                            const additionalDataAttrs = {
                                'data-first-name': visitor.first_name || '',
                                'data-last-name': visitor.last_name || '',
                                'data-title': visitor.job_title || '',
                                'data-work-email': visitor.email || '',
                                'data-website': visitor.website || '',
                                'data-industry': visitor.industry || '',
                                'data-employee-count': visitor.estimated_employee_count || '',
                                'data-revenue': visitor.estimated_revenue || '',
                                'data-first-seen': visitor.first_seen_at || '',
                                'data-page-views': visitor.all_time_page_views || '0',
                                'data-client-ai-enabled': clientAiEnabled ? 'true' : 'false'
                            };

                            const additionalAttrsString = Object.entries(additionalDataAttrs)
                                .map(([key, value]) => `${key}="${value}"`)
                                .join(' ');

                            // Generate AI intelligence icon HTML if enabled
                            let aiIntelligenceIconHTML = '';
                            if (clientAiEnabled) {
                                aiIntelligenceIconHTML = `
                                    <span class="icon ai-intelligence-icon status-${intelligenceStatus}" 
                                        title="${getIntelligenceIconTitle(intelligenceStatus)}"
                                        data-intelligence-status="${intelligenceStatus}">
                                        <i class="fas fa-brain"></i>
                                    </span>
                                `;
                            }


                            visitorListContainer.insertAdjacentHTML('beforeend', `
                                <div class="visitor-card"
                                    data-visitor-id="${visitor.id}"
                                    data-last-seen-at="${visitor.last_seen_at || 'N/A'}"
                                    data-recent-page-count="${visitor.recent_page_count || '0'}"
                                    data-recent-page-urls='${safeRecentPageUrlsForAttr}'
                                    ${additionalAttrsString} >
                                    <div class="visitor-top-row">
                                        <div class="visitor-logo">
                                            <img src="${visitorLogoUrl}" 
                                                 alt="${visitorAltText}" 
                                                 title="${visitorTooltipText}">
                                        </div>
                                        <div class="visitor-actions">
                                            <span class="icon add-crm-icon" title="Add to CRM">
                                                <i class="fas fa-plus-square"></i>
                                            </span>
                                            ${hasLinkedIn ? `<a href="${linkedinUrl}" target="_blank" class="icon linkedin-icon" title="View LinkedIn Profile"><i class="fab fa-linkedin"></i></a>` : ''}
                                            ${aiIntelligenceIconHTML}
                                            <span class="icon info-icon" title="More Info">
                                                <i class="fas fa-info-circle"></i>
                                            </span>
                                            <span class="icon delete-icon" title="Archive">
                                                <i class="fas fa-trash-alt"></i>
                                            </span>
                                        </div>
                                    </div>

                                    <p class="visitor-name">${fullName.trim() || 'Company Visit'}</p>
                                    <p class="visitor-company-main">${companyName}</p>

                                    <div class="visitor-details-body">
                                        <p><i class="fas fa-briefcase"></i> ${jobTitle}</p>
                                        <p><i class="fas fa-building"></i> ${companyName}</p>
                                        <p><i class="fas fa-map-marker-alt"></i> ${location}</p>
                                        <p><i class="fas fa-envelope"></i> ${email}</p>
                                    </div>
                                </div>
                            `);
                        });
                    } else {
                        visitorListContainer.insertAdjacentHTML('beforeend', '<div class="no-data">No visitor data found.</div>');
                    }
                }

                console.log('loadClientDashboardData: Dashboard updated successfully.');
            } else {
                console.error('loadClientDashboardData: AJAX response success is false:', responseData.data);
            }
        } catch (error) {
            console.error('loadClientDashboardData: Fetch error:', error);
        } finally {
            if (mainContent) mainContent.style.opacity = '1';
        }
    }
    
    // Helper function to get intelligence icon title
    function getIntelligenceIconTitle(status) {
        switch (status) {
            case 'none':
                return 'Generate AI Intelligence';
            case 'pending':
                return 'AI Intelligence Processing...';
            case 'completed':
                return 'AI Intelligence Available (click for details)';
            case 'failed':
                return 'AI Intelligence Failed (click to retry)';
            default:
                return 'AI Intelligence';
        }
    }    

    // Event listener for the "Add CRM" and "Delete" buttons on Visitor Panel.
    const visitorPanel = document.querySelector('.visitor-panel');
    const visitorInfoModal = document.getElementById('visitor-info-modal');
    const modalCloseButton = visitorInfoModal ? visitorInfoModal.querySelector('.close') : null;

    if (visitorPanel) {
        visitorPanel.addEventListener('click', async (event) => {
            const button = event.target.closest('.add-crm-icon, .delete-icon, .info-icon, .linkedin-icon, .ai-intelligence-icon');

            if (button) {
                const visitorCard = button.closest('.visitor-card');
                const visitorId = visitorCard.dataset.visitorId;

                // Handle LinkedIn icon click
                if (button.classList.contains('linkedin-icon')) {
                    const linkedinUrl = button.getAttribute('href');
                    if (linkedinUrl && linkedinUrl !== '#') {
                        window.open(linkedinUrl, '_memo');
                    }
                    return; // Exit to prevent further processing for this click
                }

                // Handle AI Intelligence icon click
                if (button.classList.contains('ai-intelligence-icon')) {
                    console.log('AI Intelligence icon clicked for visitor:', visitorId);
                    
                    // Check if client has AI enabled
                    if (!isClientAiEnabled(visitorCard)) {
                        alert('AI Intelligence is not enabled for this client.');
                        return;
                    }
            
                    const currentStatus = button.dataset.intelligenceStatus;
                    console.log('Current intelligence status:', currentStatus);
                    
                    // If completed, show intelligence data in info modal instead
                    if (currentStatus === 'completed') {
                        console.log('Intelligence completed, triggering info modal...');
                        // Find and click the info icon to show modal with intelligence data
                        const infoIcon = visitorCard.querySelector('.info-icon');
                        if (infoIcon) {
                            infoIcon.click();
                        }
                        return;
                    }
            
                    // If pending, show message
                    if (currentStatus === 'pending') {
                        alert('Intelligence request is already being processed. Please wait...');
                        return;
                    }
            
                    // For 'none' or 'failed' status, request intelligence
                    if (currentStatus === 'none' || currentStatus === 'failed') {
                        const confirmMessage = currentStatus === 'failed' 
                            ? 'Previous intelligence request failed. Would you like to retry?'
                            : 'Request AI intelligence analysis for this visitor?';
                            
                        if (!confirm(confirmMessage)) {
                            return;
                        }
            
                        console.log('Requesting intelligence for visitor:', visitorId);
            
                        // Set to pending state and disable button
                        updateIntelligenceIconState(visitorCard, 'pending');
                        button.style.pointerEvents = 'none';
                        
                        // Show processing indicator in button
                        const originalTitle = button.title;
                        button.title = 'Processing intelligence request...';
            
                        try {
                            const response = await sendAjaxRequestForVisitorIntelligence(visitorId);
                            console.log('Intelligence request response:', response);
                            
                            if (response.success) {
                                // UPDATED: For synchronous calls, intelligence should be completed immediately
                                if (response.status === 'completed') {
                                    console.log('Intelligence completed synchronously!');
                                    updateIntelligenceIconState(visitorCard, 'completed');
                                    
                                    // Show success message
                                    alert('✅ AI Intelligence generated successfully! Click the info icon to view details.');
                                    
                                    // Reload dashboard data to get the updated visitor with intelligence
                                    await loadClientDashboardData();
                                    
                                } else if (response.status === 'pending') {
                                    // Fallback for any async scenarios (shouldn't happen with sync Make.com)
                                    console.log('Intelligence still pending - will check status periodically');
                                    // Poll for completion
                                    let checkCount = 0;
                                    const maxChecks = 10; // Max 30 seconds (3 second intervals)
                                    
                                    const checkStatus = async () => {
                                        checkCount++;
                                        console.log(`Checking intelligence status (attempt ${checkCount}/${maxChecks})`);
                                        
                                        const statusResponse = await getVisitorIntelligenceStatus(visitorId);
                                        
                                        if (statusResponse.success && statusResponse.data.status === 'completed') {
                                            console.log('Intelligence completed!');
                                            updateIntelligenceIconState(visitorCard, 'completed');
                                            alert('✅ AI Intelligence generated successfully! Click the info icon to view details.');
                                            await loadClientDashboardData();
                                        } else if (statusResponse.success && statusResponse.data.status === 'failed') {
                                            console.log('Intelligence failed');
                                            updateIntelligenceIconState(visitorCard, 'failed');
                                            alert('❌ Intelligence generation failed. Please try again.');
                                        } else if (checkCount < maxChecks) {
                                            // Continue checking
                                            setTimeout(checkStatus, 3000); // Check every 3 seconds
                                        } else {
                                            // Max checks reached
                                            console.log('Max status checks reached');
                                            updateIntelligenceIconState(visitorCard, 'failed');
                                            alert('⏱️ Intelligence request timed out. Please try again.');
                                        }
                                    };
                                    
                                    // Start checking in 3 seconds
                                    setTimeout(checkStatus, 3000);
                                }
                            } else {
                                console.error('Intelligence request failed:', response.error);
                                updateIntelligenceIconState(visitorCard, 'failed');
                                
                                // Show user-friendly error message
                                let userMessage = 'Failed to generate AI intelligence.';
                                if (response.error.includes('not enabled')) {
                                    userMessage = 'AI Intelligence is not enabled for this client.';
                                } else if (response.error.includes('rate limit')) {
                                    userMessage = 'Rate limit exceeded. Please try again later.';
                                } else if (response.error.includes('not configured')) {
                                    userMessage = 'AI Intelligence service is not configured. Please contact support.';
                                }
                                
                                alert('❌ ' + userMessage);
                            }
                        } catch (error) {
                            console.error('Intelligence request error:', error);
                updateIntelligenceIconState(visitorCard, 'failed');
                alert('❌ Failed to request intelligence: Network error');
            } finally {
                // Re-enable button and restore title
                button.style.pointerEvents = 'auto';
                button.title = originalTitle;
            }
        }
        
        return; // Exit to prevent further processing for this click
    }

                // Handle Info icon click
                if (button.classList.contains('info-icon')) {
                    // Get all visitor data from data attributes and displayed content
                    const firstName = visitorCard.dataset.firstName || '';
                    const lastName = visitorCard.dataset.lastName || '';
                    const title = visitorCard.dataset.title || 'N/A';
                    const workEmail = visitorCard.dataset.workEmail || 'N/A';
                    const website = visitorCard.dataset.website || '';
                    const industry = visitorCard.dataset.industry || 'N/A';
                    const employeeCount = visitorCard.dataset.employeeCount || 'N/A';
                    const revenue = visitorCard.dataset.revenue || 'N/A';
                    const firstSeen = visitorCard.dataset.firstSeen || 'N/A';
                    const pageViews = visitorCard.dataset.pageViews || '0';
                    const lastSeenAt = visitorCard.dataset.lastSeenAt || 'N/A';
                    const recentPageCount = visitorCard.dataset.recentPageCount || '0';
                    const clientAiEnabled = visitorCard.dataset.clientAiEnabled === 'true';
                    
                    // Get company and location from displayed content
                    const visitorCompanyElement = visitorCard.querySelector('.visitor-company-main');
                    const company = visitorCompanyElement ? visitorCompanyElement.textContent.trim() : 'Unknown Company';
                    
                    // Extract location from the displayed details
                    let location = 'N/A';
                    const visitorDetailsElements = visitorCard.querySelectorAll('.visitor-details-body p');
                    visitorDetailsElements.forEach(p => {
                        const icon = p.querySelector('i');
                        if (icon && icon.classList.contains('fa-map-marker-alt')) {
                            location = p.textContent.replace(/.*?\s+/, '').trim();
                        }
                    });
                    
                    // Determine if visitor has a name
                    const fullName = [firstName, lastName].filter(Boolean).join(' ');
                    const hasName = fullName.trim() !== '';
                    
                    // Get recent page URLs from data attribute
                    let recentPageUrls = [];
                    const recentPageUrlsStringFromAttr = visitorCard.dataset.recentPageUrls; 
                    
                    if (recentPageUrlsStringFromAttr && recentPageUrlsStringFromAttr !== 'None' && recentPageUrlsStringFromAttr.trim() !== '') {
                        try {
                            const parsedUrls = JSON.parse(recentPageUrlsStringFromAttr);
                            if (parsedUrls && typeof parsedUrls === 'object' && parsedUrls.length !== undefined) {
                                recentPageUrls = Array.from(parsedUrls).filter(url => url && url.trim() !== '');
                            } else if (typeof parsedUrls === 'string') {
                                recentPageUrls = [parsedUrls];
                            }
                        } catch (e) {
                            console.error('JSON Parse Error:', e);
                            if (recentPageUrlsStringFromAttr.includes('http')) {
                                const urlMatches = recentPageUrlsStringFromAttr.match(/https?:\/\/[^\s"',\]]+/g);
                                if (urlMatches) {
                                    recentPageUrls = urlMatches;
                                }
                            }
                        }
                    }
                    
                    
                    // Generate AI Intelligence section if available
                    let intelligenceSection = '';
                    if (clientAiEnabled) {
                        const intelligenceIcon = visitorCard.querySelector('.ai-intelligence-icon');
                        const intelligenceStatus = intelligenceIcon ? intelligenceIcon.dataset.intelligenceStatus : 'none';
                        
                        if (intelligenceStatus === 'completed') {
                            intelligenceSection = `
                                <div class="visitor-modal-section intelligence-section">
                                    <h3><i class="fas fa-brain"></i> AI Intelligence</h3>
                                    <div class="intelligence-loading">
                                        <p><i class="fas fa-spinner fa-spin"></i> Loading intelligence data...</p>
                                    </div>
                                </div>
                            `;
                        } else if (intelligenceStatus === 'pending') {
                            intelligenceSection = `
                                <div class="visitor-modal-section intelligence-section">
                                    <h3><i class="fas fa-brain"></i> AI Intelligence</h3>
                                    <div class="intelligence-pending">
                                        <p><i class="fas fa-clock"></i> Intelligence analysis in progress...</p>
                                    </div>
                                </div>
                            `;
                        } else if (intelligenceStatus === 'failed') {
                            intelligenceSection = `
                                <div class="visitor-modal-section intelligence-section">
                                    <h3><i class="fas fa-brain"></i> AI Intelligence</h3>
                                    <div class="intelligence-failed">
                                        <p><i class="fas fa-exclamation-triangle"></i> Intelligence generation failed. Click the brain icon to retry.</p>
                                    </div>
                                </div>
                            `;
                        } else {
                            intelligenceSection = `
                                <div class="visitor-modal-section intelligence-section">
                                    <h3><i class="fas fa-brain"></i> AI Intelligence</h3>
                                    <div class="intelligence-prompt">
                                        <p><i class="fas fa-lightbulb"></i> Click the bulb icon to generate AI insights for this visitor.</p>
                                    </div>
                                </div>
                            `;
                        }
                    }
                    
                    
                    
                    // Build the new modal HTML with simplified structure (no action buttons, no logo)
                    const modalHTML = `
                        <div class="modal-content">
                            <div class="modal-header">
                                <div class="modal-visitor-info">
                                    <div class="modal-visitor-name">${hasName ? fullName : company}</div>
                                    ${hasName ? `<div class="modal-visitor-company">${company}</div>` : '<div class="modal-visitor-company">Company Visit</div>'}
                                </div>
                                <span class="close">&times;</span>
                            </div>
                            <div class="modal-body">
                                ${hasName ? `
                                <div class="visitor-modal-section">
                                    <h3><i class="fas fa-user"></i> Personal Information</h3>
                                    <div class="visitor-detail-row">
                                        <i class="fas fa-id-card"></i>
                                        <div class="visitor-detail-content"><strong>Name:</strong> ${fullName}</div>
                                    </div>
                                    <div class="visitor-detail-row">
                                        <i class="fas fa-briefcase"></i>
                                        <div class="visitor-detail-content"><strong>Title:</strong> ${title}</div>
                                    </div>
                                    <div class="visitor-detail-row">
                                        <i class="fas fa-envelope"></i>
                                        <div class="visitor-detail-content"><strong>Email:</strong> ${workEmail}</div>
                                    </div>
                                    <div class="visitor-detail-row">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <div class="visitor-detail-content"><strong>Location:</strong> ${location}</div>
                                    </div>
                                </div>
                                ` : ''}
                                
                                <div class="visitor-modal-section">
                                    <h3><i class="fas fa-building"></i> Company Information</h3>
                                    <div class="visitor-detail-row">
                                        <i class="fas fa-building"></i>
                                        <div class="visitor-detail-content"><strong>Company:</strong> ${company}</div>
                                    </div>
                                    <div class="visitor-detail-row">
                                        <i class="fas fa-users"></i>
                                        <div class="visitor-detail-content"><strong>Employees:</strong> ${employeeCount}</div>
                                    </div>
                                    <div class="visitor-detail-row">
                                        <i class="fas fa-dollar-sign"></i>
                                        <div class="visitor-detail-content"><strong>Revenue:</strong> ${revenue}</div>
                                    </div>
                                    <div class="visitor-detail-row">
                                        <i class="fas fa-industry"></i>
                                        <div class="visitor-detail-content"><strong>Industry:</strong> ${industry}</div>
                                    </div>
                                    <div class="visitor-detail-row">
                                        <i class="fas fa-globe"></i>
                                        <div class="visitor-detail-content">
                                            <strong>Website:</strong> 
                                            ${website && website.trim() !== '' ? 
                                                `<a href="${website.startsWith('http') ? website : 'https://' + website}" target="_blank">${website}</a>` : 
                                                'N/A'
                                            }
                                        </div>
                                    </div>
                                </div>
                                
                                ${intelligenceSection}
                                
                                <div class="visitor-modal-section">
                                    <h3><i class="fas fa-clock"></i> Activity Information</h3>
                                    <div class="visitor-detail-row">
                                        <i class="fas fa-clock"></i>
                                        <div class="visitor-detail-content"><strong>Last Seen:</strong> ${lastSeenAt}</div>
                                    </div>
                                    <div class="visitor-detail-row">
                                        <i class="fas fa-calendar"></i>
                                        <div class="visitor-detail-content"><strong>First Visit:</strong> ${firstSeen}</div>
                                    </div>
                                    <div class="visitor-detail-row">
                                        <i class="fas fa-eye"></i>
                                        <div class="visitor-detail-content"><strong>Total Page Views:</strong> ${pageViews}</div>
                                    </div>
                                </div>
                                
                                <div class="recent-pages-section">
                                    <h3><i class="fas fa-file-alt"></i> Recent Pages Visited</h3>
                                    <div class="page-count-info">
                                        <strong>Recent Pages:</strong> ${recentPageCount}
                                    </div>
                                    <div class="recent-page-urls-container">
                                        <ul id="modal-recent-page-urls">
                                            ${recentPageUrls.length > 0 ? 
                                                recentPageUrls.map(url => {
                                                    let cleanUrl = String(url).trim();
                                                    cleanUrl = cleanUrl.replace(/^[\[\"]|[\]\"]$/g, '');
                                                    return `<li><a href="${cleanUrl}" target="_blank">${cleanUrl}</a></li>`;
                                                }).join('') :
                                                recentPageCount > 0 ? 
                                                    '<li><div class="no-data-message" style="color: #ff6b6b; font-style: italic;">Data issue: ' + recentPageCount + ' pages recorded but URLs not available</div></li>' :
                                                    '<li><div class="no-data-message">No recent pages visited</div></li>'
                                            }
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                    
                    // Update the modal content
                    if (visitorInfoModal) {
                        visitorInfoModal.innerHTML = modalHTML;
                        visitorInfoModal.style.display = 'flex';
                        
                        // If intelligence is completed, load the actual intelligence data
                        if (clientAiEnabled && intelligenceSection.includes('intelligence-loading')) {
                            loadIntelligenceDataForModal(visitorId);
                        }


                        // Add event listeners for modal close only
                        const modalClose = visitorInfoModal.querySelector('.close');
                        
                        // Close button handler
                        if (modalClose) {
                            modalClose.addEventListener('click', () => {
                                visitorInfoModal.style.display = 'none';
                            });
                        }
                        
                        // Close modal if clicked outside
                        visitorInfoModal.addEventListener('click', (event) => {
                            if (event.target === visitorInfoModal) {
                                visitorInfoModal.style.display = 'none';
                            }
                        });
                    }
                    
                    return; // Exit to prevent further processing for this click
                }

                let updateAction = '';
                if (button.classList.contains('add-crm-icon')) {
                    updateAction = 'add_crm';
                    if (!confirm('Are you sure you want to flag this visitor for CRM addition?')) return;
                } else if (button.classList.contains('delete-icon')) {
                    updateAction = 'archive';
                    if (!confirm('Are you sure you want to archive this visitor? They will no longer appear in the list.')) return;
                }

                button.style.pointerEvents = 'none';
                button.style.opacity = '0.6';

                const success = await sendAjaxRequestForVisitorStatus(updateAction, visitorId);

                if (success) {
                    await loadClientDashboardData(); // Reload data after update
                } else {
                    alert('Failed to update visitor status. Please check console for details.');
                }
                button.style.pointerEvents = 'auto';
                button.style.opacity = '1';
            }
        });
    }


    // NEW: Function to load intelligence data for modal
    async function loadIntelligenceDataForModal(visitorId) {
        const intelligenceSection = document.querySelector('.intelligence-section .intelligence-loading');
        if (!intelligenceSection) return;

        try {
            const response = await fetch(localizedData.ajax_url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'cpd_get_visitor_intelligence_data',
                    nonce: localizedData.intelligence_nonce || localizedData.visitor_nonce,
                    visitor_id: visitorId
                }).toString()
            });

            if (!response.ok) {
                throw new Error('Network response was not ok.');
            }

            const data = await response.json();
            console.log('Intelligence modal data response:', data);

            if (data.success && data.data && data.data.intelligence) {
                const intelligence = data.data.intelligence;
                const intelligenceHTML = `
                    <div class="intelligence-data">
                        ${intelligence.person_summary ? `
                            <div class="intelligence-item">
                                <h4><i class="fas fa-user-circle"></i> Person Summary</h4>
                                <p>${intelligence.person_summary}</p>
                            </div>
                        ` : ''}
                        
                        ${intelligence.company_summary ? `
                            <div class="intelligence-item">
                                <h4><i class="fas fa-building"></i> Company Analysis</h4>
                                <p>${intelligence.company_summary}</p>
                            </div>
                        ` : ''}
                        
                        ${intelligence.industry_insights ? `
                            <div class="intelligence-item">
                                <h4><i class="fas fa-chart-line"></i> Industry Insights</h4>
                                <p>${intelligence.industry_insights}</p>
                            </div>
                        ` : ''}
                        
                        ${intelligence.key_insights && intelligence.key_insights.length > 0 ? `
                            <div class="intelligence-item">
                                <h4><i class="fas fa-lightbulb"></i> Key Insights</h4>
                                <ul>
                                    ${intelligence.key_insights.map(insight => `<li>${insight}</li>`).join('')}
                                </ul>
                            </div>
                        ` : ''}
                        
                        ${intelligence.recommended_approach ? `
                            <div class="intelligence-item">
                                <h4><i class="fas fa-route"></i> Recommended Approach</h4>
                                <p>${intelligence.recommended_approach}</p>
                            </div>
                        ` : ''}
                        
                        ${intelligence.lead_qualification_score !== undefined ? `
                            <div class="intelligence-item">
                                <h4><i class="fas fa-star"></i> Lead Quality Score</h4>
                                <div class="score-bar">
                                    <div class="score-fill" style="width: ${intelligence.lead_qualification_score}%"></div>
                                    <span class="score-text">${intelligence.lead_qualification_score}/100</span>
                                </div>
                            </div>
                        ` : ''}
                        
                        <div class="intelligence-meta">
                            <small><i class="fas fa-info-circle"></i> Generated with client-specific context</small>
                        </div>
                    </div>
                `;
                
                intelligenceSection.innerHTML = intelligenceHTML;
            } else {
                intelligenceSection.innerHTML = `
                    <div class="intelligence-error">
                        <p><i class="fas fa-exclamation-triangle"></i> Failed to load intelligence data.</p>
                    </div>
                `;
            }
        } catch (error) {
            console.error('Failed to load intelligence data:', error);
            intelligenceSection.innerHTML = `
                <div class="intelligence-error">
                    <p><i class="fas fa-exclamation-triangle"></i> Error loading intelligence data.</p>
                </div>
            `;
        }
    }



    // Modal close functionality
    if (modalCloseButton) {
        modalCloseButton.addEventListener('click', () => {
            if (visitorInfoModal) {
                visitorInfoModal.style.display = 'none';
            }
        });
    }

    // Close modal if clicked outside
    if (visitorInfoModal) {
        visitorInfoModal.addEventListener('click', (event) => {
            if (event.target === visitorInfoModal) {
                visitorInfoModal.style.display = 'none';
            }
        });
    }

    // Event listener for Client List clicks (Admin view only)
    if (isAdminUser) {
        const clientList = document.querySelector('.account-list');
        if (clientList) {
            clientList.addEventListener('click', async (event) => {
                const listItem = event.target.closest('.account-list-item');
                if (listItem) {
                    const clientId = listItem.dataset.clientId;
                    
                    // Update active class
                    document.querySelectorAll('.account-list-item').forEach(item => item.classList.remove('active'));
                    listItem.classList.add('active');

                    // Update URL
                    const currentUrl = new URL(window.location.href);
                    if (clientId === 'all') {
                        currentUrl.searchParams.delete('client_id');
                    } else {
                        currentUrl.searchParams.set('client_id', clientId);
                    }
                    window.history.pushState({}, '', currentUrl.toString());

                    // Load data for the selected client
                    const duration = document.getElementById('duration-selector').value; // Get current duration
                    await loadClientDashboardData(clientId, duration); // Pass explicit clientId and duration
                }
            });
        }
    }
    
    // Event listener for Date Range Select changes (both Admin and Client views)
    const durationSelector = document.getElementById('duration-selector');
    if (durationSelector) {
        durationSelector.addEventListener('change', async () => {
            // When duration changes, use current client from URL/localized data, but use new duration
            const urlParams = new URLSearchParams(window.location.search);
            let currentClientIdFromUrl = urlParams.get('client_id');
            const currentClientId = isAdminUser && currentClientIdFromUrl ? currentClientIdFromUrl : localizedData.current_client_account_id;

            await loadClientDashboardData(currentClientId, durationSelector.value); // Pass explicit clientId and new duration
        });
    }

    // --- Initial Data Load ---
    console.log('cpd-public-dashboard.js: Initializing dashboard data load.');
    loadClientDashboardData();
});