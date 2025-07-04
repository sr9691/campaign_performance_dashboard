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
                //console.log(`sendAjaxRequestForVisitorStatus: Visitor ${visitorId} status updated successfully.`);
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
                console.log("Data received for update:", data);

                // Update Client Logo in Header (Only applicable if a specific client is selected)
                if (actualClientIdForAjax  !== 'all' && data.client_logo_url) {
                    const clientLogoImg = document.querySelector('.dashboard-header .client-logo-container img');
                    console.log("Updating client logo with URL:", data.client_logo_url);
                    if (clientLogoImg) {
                        clientLogoImg.src = data.client_logo_url;
                    }
                } else if (actualClientIdForAjax  === 'all') {
                    // If "All Clients" is selected, revert to default logo
                    const clientLogoImg = document.querySelector('.dashboard-header .client-logo-container img');
                    if (clientLogoImg) {
                        clientLogoImg.src = localizedData.memo_seal_url; // Or a generic "all clients" logo
                    }
                }


                // Update Summary Cards 
                document.querySelectorAll('.summary-card .value').forEach(el => {
                    const dataKey = el.nextElementSibling.dataset.summaryKey; // Get from new data-key attribute
                    console.log("Updating summary cards with metrics:", data.summary_metrics);
                    if (dataKey && data.summary_metrics && data.summary_metrics[dataKey]) {
                        el.textContent = data.summary_metrics[dataKey];
                    } else {
                        el.textContent = '0'; // Default to 0 if no data
                        // console.warn(`Summary data not found for key: ${dataKey}`); // Optional debug
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
                            const memoSealUrl = localizedData.memo_seal_url;
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


                            visitorListContainer.insertAdjacentHTML('beforeend', `
                                <div class="visitor-card"
                                    data-visitor-id="${visitor.id}"
                                    data-last-seen-at="${visitor.last_seen_at || 'N/A'}"
                                    data-recent-page-count="${visitor.recent_page_count || '0'}"
                                    data-recent-page-urls='${safeRecentPageUrlsForAttr}' >
                                    <div class="visitor-top-row">
                                        <div class="visitor-logo">
                                            <img src="${memoSealUrl}" alt="Referrer Logo">
                                        </div>
                                        <div class="visitor-actions">
                                            <span class="icon add-crm-icon" title="Add to CRM">
                                                <i class="fas fa-plus-square"></i>
                                            </span>
                                            ${hasLinkedIn ? `<a href="${linkedinUrl}" target="_blank" class="icon linkedin-icon" title="View LinkedIn Profile"><i class="fab fa-linkedin"></i></a>` : ''}
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
                console.error('loadClientDashboardData: AJAX response success is false:', data.data);
            }
        } catch (error) {
            console.error('loadClientDashboardData: Fetch error:', error);
        } finally {
            if (mainContent) mainContent.style.opacity = '1';
        }
    }


    // Event listener for the "Add CRM" and "Delete" buttons on Visitor Panel.
    const visitorPanel = document.querySelector('.visitor-panel');
    const visitorInfoModal = document.getElementById('visitor-info-modal');
    const modalCloseButton = visitorInfoModal ? visitorInfoModal.querySelector('.close') : null;

    if (visitorPanel) {
        visitorPanel.addEventListener('click', async (event) => {
            const button = event.target.closest('.add-crm-icon, .delete-icon, .info-icon, .linkedin-icon');

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

                // Handle Info icon click
                if (button.classList.contains('info-icon')) {
                    const lastSeenAt = visitorCard.dataset.lastSeenAt || 'N/A';
                    const recentPageCount = visitorCard.dataset.recentPageCount || '0';
                    
                    let recentPageUrls = [];
                    // Get the recent page URLs from the data attribute
                    const recentPageUrlsStringFromAttr = visitorCard.dataset.recentPageUrls; 
                    
                    // Parse the JSON string from the data attribute
                    if (recentPageUrlsStringFromAttr && recentPageUrlsStringFromAttr !== 'None' && recentPageUrlsStringFromAttr.trim() !== '') {
                        try {
                            const parsedUrls = JSON.parse(recentPageUrlsStringFromAttr);
                            
                            // Use duck typing instead of Array.isArray() which seems to be failing
                            if (parsedUrls && typeof parsedUrls === 'object' && parsedUrls.length !== undefined) {
                                // Convert to proper array and filter
                                recentPageUrls = Array.from(parsedUrls).filter(url => url && url.trim() !== '');
                            } else if (typeof parsedUrls === 'string') {
                                // Single URL string
                                recentPageUrls = [parsedUrls];
                            }
                        } catch (e) {
                            console.error('JSON Parse Error:', e);
                            
                            // Fallback: extract URLs using regex
                            if (recentPageUrlsStringFromAttr.includes('http')) {
                                const urlMatches = recentPageUrlsStringFromAttr.match(/https?:\/\/[^\s"',\]]+/g);
                                if (urlMatches) {
                                    recentPageUrls = urlMatches;
                                }
                            }
                        }
                    }

                    document.getElementById('modal-last-seen-at').textContent = lastSeenAt;
                    document.getElementById('modal-recent-page-count').textContent = recentPageCount;

                    const pageUrlsList = document.getElementById('modal-recent-page-urls');
                    pageUrlsList.innerHTML = ''; // Clear previous URLs
                    
                    if (recentPageUrls.length > 0) {
                        
                        recentPageUrls.forEach((url, index) => {
                            // Ensure url is a string and clean it up
                            let cleanUrl = String(url).trim();
                            
                            // Remove any remaining brackets or quotes that might be attached
                            cleanUrl = cleanUrl.replace(/^[\[\"]|[\]\"]$/g, '');
                            
                            const li = document.createElement('li');
                            const a = document.createElement('a');
                            a.href = cleanUrl;
                            a.textContent = cleanUrl;
                            a.target = '_blank';
                            li.appendChild(a);
                            pageUrlsList.appendChild(li);
                        });
                        document.getElementById('modal-recent-page-urls-container').style.display = 'block';
                    } else if (recentPageCount > 0) {
                        // We have a count but no URLs - likely a data issue
                        const li = document.createElement('li');
                        li.textContent = `Data issue: ${recentPageCount} pages recorded but URLs not available`;
                        li.style.color = '#ff6b6b';
                        li.style.fontStyle = 'italic';
                        pageUrlsList.appendChild(li);
                        document.getElementById('modal-recent-page-urls-container').style.display = 'block';
                    } else {
                        // If no URLs and no count, display a "No recent pages" message
                        const li = document.createElement('li');
                        li.textContent = 'No recent pages.';
                        pageUrlsList.appendChild(li);
                        document.getElementById('modal-recent-page-urls-container').style.display = 'block';
                    }

                    if (visitorInfoModal) {
                        visitorInfoModal.style.display = 'flex'; // Use flex to center the modal
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
    // This is the primary function call when the public dashboard loads.
    // It will fetch data based on whether it's an admin viewing "all" or a specific client,
    // or a regular client.
    console.log('cpd-public-dashboard.js: Initializing dashboard data load.');
    loadClientDashboardData();
});