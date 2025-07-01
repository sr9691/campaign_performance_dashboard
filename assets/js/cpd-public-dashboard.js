/**
 * Public-facing JavaScript for the Campaign Performance Dashboard.
 */

document.addEventListener('DOMContentLoaded', () => {
    // Check if the dashboard container exists before running the script.
    const dashboardContainer = document.querySelector('.dashboard-container');
    if (!dashboardContainer) {
        return;
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

        // Data is already aggregated by date from get_campaign_data_by_date.
        const labels = data.map(item => new Date(item.date).toLocaleDateString());
        const impressions = data.map(item => item.impressions);

        // Destroy the old chart instance if it exists
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
                    borderColor: 'rgb(66, 148, 204)', // Secondary color
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

        // Data is already aggregated by ad group from get_campaign_data_by_ad_group.
        const labels = data.map(item => item.ad_group_name);
        const impressions = data.map(item => item.impressions);
        
        const backgroundColors = [
            '#2c435d', '#4294cc', '#a8d2e8', '#e8a8d2', '#d2e8a8', '#88a8d2', '#5d2c43' // Use a palette based on your brand colors
        ];

        // Destroy the old chart instance if it exists
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
                        enabled: false // No tooltips as per the user's request
                    }
                }
            }
        });
    }

    // A simple function to handle AJAX requests to our custom endpoint.
    const sendAjaxRequest = async (action, visitorId) => {
        const ajaxUrl = ajax_object.ajax_url; 
        const nonce = ajax_object.nonce;

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
                console.log(`Visitor ${visitorId} status updated successfully.`);
                return true;
            } else {
                console.error('AJAX error:', data.data);
                return false;
            }
        } catch (error) {
            console.error('Fetch error:', error);
            return false;
        }
    };

    // Event listener for the "Add CRM" and "Delete" buttons.
    const visitorPanel = document.querySelector('.visitor-panel');
    if (visitorPanel) {
        visitorPanel.addEventListener('click', async (event) => {
            const button = event.target.closest('.add-crm-icon, .delete-icon');

            if (button) {
                const visitorCard = button.closest('.visitor-card');
                const visitorId = visitorCard.dataset.visitorId;
                
                let success = false;
                if (button.classList.contains('add-crm-icon')) {
                    success = await sendAjaxRequest('add_crm', visitorId);
                } else if (button.classList.contains('delete-icon')) {
                    success = await sendAjaxRequest('archive', visitorId);
                }

                if (success) {
                    visitorCard.style.display = 'none';
                }
            }
        });
    }

    // --- Fetch data and render charts on page load ---
    // Only render charts if NOT an admin, as admin will handle via cpd-dashboard.js
    // Access it as a property of the localized object
    const isAdminUser = typeof cpd_dashboard_data !== 'undefined' && cpd_dashboard_data.is_admin_user;
    if (!isAdminUser) {
        const campaignDataByDate = typeof cpd_dashboard_data !== 'undefined' ? cpd_dashboard_data.campaign_data_by_date : [];
        const campaignDataByAdGroup = typeof cpd_dashboard_data !== 'undefined' ? cpd_dashboard_data.campaign_data_by_ad_group : [];
        
        renderImpressionsChart(campaignDataByDate);
        renderImpressionsByAdGroupChart(campaignDataByAdGroup);
    }
});
