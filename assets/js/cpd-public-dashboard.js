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
     * @param {Array} data The campaign data from the PHP template.
     */
    function renderImpressionsChart(data) {
        const ctx = document.getElementById('impressions-chart-canvas');
        if (!ctx) return;

        const labels = data.map(item => new Date(item.last_updated).toLocaleDateString());
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
     * @param {Array} data The campaign data from the PHP template.
     */
    function renderImpressionsByAdGroupChart(data) {
        const ctx = document.getElementById('ad-group-chart-canvas');
        if (!ctx) return;

        const labels = data.map(item => item.ad_group_name);
        const impressions = data.map(item => item.impressions);
        
        const backgroundColors = [
            '#2c435d', '#4294cc', '#a8d2e8', '#e8a8d2', '#d2e8a8', '#88a8d2', '#5d2c43'
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
    // The PHP template will already have the data available. We just need to render it.
    // We can fetch the data from a hidden element or a data attribute.
    // For this implementation, the PHP will be responsible for providing the data directly to the JS.
    // So we'll assume a global variable is set by the PHP code (e.g., cpd_dashboard_data).
    // You will need to add a wp_localize_script call for the public dashboard in class-cpd-public.php.
    // This is placeholder logic, assuming the data is available.
    
    // Example of a local data source for rendering.
    // In a real scenario, this data would come from the server via a localized script.
    const chartData = [
        {"ad_group_name": "Apptio", "impressions": 1131, "reach": 479, "clicks": 9, "ctr": 0.80, "last_updated": "2025-06-01"},
        {"ad_group_name": "Attorneys", "impressions": 7188, "reach": 5890, "clicks": 83, "ctr": 1.15, "last_updated": "2025-06-05"},
        {"ad_group_name": "Bar/Pub Goers", "impressions": 3375, "reach": 2815, "clicks": 56, "ctr": 1.66, "last_updated": "2025-06-10"},
        {"ad_group_name": "Bars - Legends", "impressions": 4289, "reach": 3602, "clicks": 78, "ctr": 1.82, "last_updated": "2025-06-15"},
        {"ad_group_name": "Children Shoppers", "impressions": 9913, "reach": 7228, "clicks": 92, "ctr": 0.93, "last_updated": "2025-06-20"},
        {"ad_group_name": "Club Works Competitors", "impressions": 1693, "reach": 1417, "clicks": 49, "ctr": 2.89, "last_updated": "2025-06-25"},
    ];
    
    // Render the charts on load.
    renderImpressionsChart(chartData);
    renderImpressionsByAdGroupChart(chartData);
});