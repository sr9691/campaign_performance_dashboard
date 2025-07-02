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
                        enabled: false
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

    // --- Client-specific function to load all dashboard data via AJAX ---
    async function loadClientDashboardData() {
        const clientId = localizedData.current_client_account_id;
        const duration = 'Campaign Duration'; // Default for client view, or from a selector if implemented

        if (!clientId) {
            console.warn('loadClientDashboardData: No client ID available for client view.');
            return;
        }

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
                    client_id: clientId,
                    duration: duration
                }).toString()
            });

            if (!response.ok) {
                throw new Error('Network response was not ok.');
            }

            const responseData = await response.json();

            if (responseData.success) {
                const data = responseData.data;

                // Update Summary Cards (MODIFIED TO USE data-summary-key)
                document.querySelectorAll('.summary-card .value').forEach(el => {
                    const dataKey = el.nextElementSibling.dataset.summaryKey; // Get from new data-key attribute
                    if (dataKey && data.summary_metrics && data.summary_metrics[dataKey]) {
                        el.textContent = data.summary_metrics[dataKey];
                    } else {
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
                                    <td>${(item.ctr).toLocaleString()}%</td>
                                    <td>${(item.clicks).toLocaleString()}</td>
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
                            const location = [visitor.city, visitor.state, visitor.zipcode].filter(Boolean).join(', ');
                            const email = visitor.email || '';

                            visitorListContainer.insertAdjacentHTML('beforeend', `
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


    // Event listener for the "Add CRM" and "Delete" buttons on Visitor Panel.
    // ONLY attach if NOT an admin, as cpd-dashboard.js handles admin events.
    if (!isAdminUser) {
        const visitorPanel = document.querySelector('.visitor-panel');
        if (visitorPanel) {
            visitorPanel.addEventListener('click', async (event) => {
                const button = event.target.closest('.add-crm-icon, .delete-icon');

                if (button) {
                    const visitorCard = button.closest('.visitor-card');
                    const visitorId = visitorCard.dataset.visitorId;
                    
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
                        await loadClientDashboardData();
                    } else {
                        alert('Failed to update visitor status. Please check console for details.');
                    }
                    button.style.pointerEvents = 'auto';
                    button.style.opacity = '1';
                }
            });
        }

    }

    // --- Fetch initial data and render charts on page load for CLIENTS ONLY ---
    if (!isAdminUser) {
        const campaignDataByDate = localizedData.campaign_data_by_date || [];
        const campaignDataByAdGroup = localizedData.campaign_data_by_ad_group || [];
        const summaryMetrics = localizedData.summary_metrics || {};
        const visitorData = localizedData.visitor_data || [];

        renderImpressionsChart(campaignDataByDate);
        renderImpressionsByAdGroupChart(campaignDataByAdGroup);
        
        // Initial update of summary cards from localized data (MODIFIED TO USE data-summary-key)
        document.querySelectorAll('.summary-card .value').forEach(el => {
            const dataKey = el.nextElementSibling.dataset.summaryKey; // Get from new data-key attribute
            if (dataKey && summaryMetrics[dataKey]) {
                el.textContent = summaryMetrics[dataKey];
                console.log(`Summary card updated for key: ${dataKey} with value: ${summaryMetrics[dataKey]}`);
            }
        });

        // Initial update of visitor list from localized data
        const visitorListContainer = document.querySelector('.visitor-panel .visitor-list');
        if (visitorListContainer) {
            visitorListContainer.innerHTML = '';
            if (visitorData.length > 0) {
                visitorData.forEach(visitor => {
                    const memoSealUrl = localizedData.memo_seal_url; 
                    const fullName = (visitor.first_name || '') + ' ' + (visitor.last_name || '');
                    const location = [visitor.city, visitor.state, visitor.zipcode].filter(Boolean).join(', ');
                    const email = visitor.email || '';

                    visitorListContainer.insertAdjacentHTML('beforeend', `
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
                visitorListContainer.insertAdjacentHTML('beforeend', '<div class="no-data">No visitor data found.</div>');
            }
        }
    }
});