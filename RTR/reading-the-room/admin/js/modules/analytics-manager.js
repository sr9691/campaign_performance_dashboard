/**
 * Analytics Manager
 * Handles analytics charts and statistics display
 */

class AnalyticsManager {
    constructor(config) {
        this.config = config || window.rtrDashboardConfig || {};
        // Handle both string and object config
        if (typeof config === 'string') {
            this.apiUrl = config;
        } else {
            this.apiUrl = config?.restUrl || config?.apiUrl || window.rtrDashboardConfig?.restUrl || window.rtrDashboardConfig?.apiUrl || '';
        }
        this.nonce = config?.nonce || window.rtrDashboardConfig?.nonce || '';
        this.modal = null;
        this.chart = null;
        this.isOpen = false;
        this.init();
    }

    init() {
        this.initModal();
    }

    initModal() {
        // Find or create modal element
        this.modal = document.getElementById('analytics-modal');
        if (!this.modal) {
            console.warn('Analytics modal not found in DOM');
            this.createModal();
        }
    }

    createModal() {
        // Create modal if it doesn't exist
        const modalHTML = `
            <div id="analytics-modal" class="rtr-modal" style="display: none;">
                <div class="modal-overlay"></div>
                <div class="modal-content">
                    <div class="modal-header">
                        <h3 class="modal-title">Room Analytics</h3>
                        <button class="modal-close" type="button" aria-label="Close">
                            <span>&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="summary-stats"></div>
                        <div class="chart-container" style="height: 400px;"></div>
                    </div>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', modalHTML);
        this.modal = document.getElementById('analytics-modal');
    }

    /**
     * Open analytics modal for a specific room
     * @param {string} room - Room name (problem, solution, offer, sales)
     * @param {number|null} campaignId - Optional campaign ID for filtering
     */
    open(room, campaignId = null) {
        if (!this.modal) {
            console.error('Analytics modal not initialized');
            return;
        }

        this.isOpen = true;

        // Show modal
        this.modal.classList.add('active');
        this.modal.style.display = 'flex';

        // Update modal title
        const modalTitle = this.modal.querySelector('.modal-title');
        if (modalTitle) {
            modalTitle.textContent = `${this.capitalizeRoom(room)} Room Analytics`;
        }

        // Show loading state
        this.showLoadingState();

        // Fetch and display analytics data
        this.loadAnalytics(room, campaignId);
    }

    /**
     * Close the analytics modal
     */
    close() {
        if (this.modal) {
            this.modal.classList.remove('active');
            this.modal.style.display = 'none';
            this.isOpen = false;
        }
    }

    /**
     * Show loading state in modal
     */
    showLoadingState() {
        const chartContainer = this.modal.querySelector('.chart-container');
        if (chartContainer) {
            chartContainer.innerHTML = `
                <div class="loading-spinner" style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; gap: 1rem;">
                    <div style="width: 40px; height: 40px; border: 4px solid #e5e7eb; border-top-color: #3b82f6; border-radius: 50%; animation: spin 1s linear infinite;"></div>
                    <p style="color: #6b7280; margin: 0;">Loading analytics...</p>
                </div>
            `;
        }
    }

    /**
     * Load analytics data from API
     * @param {string} room
     * @param {number|null} campaignId
     */
    async loadAnalytics(room, campaignId = null) {
        try {
            const params = new URLSearchParams({ room: room });

            if (campaignId) {
                params.append('campaign_id', campaignId);
            }

            const response = await fetch(`${this.apiUrl}/analytics/room-trends?${params}`, {
                headers: {
                    'X-WP-Nonce': this.nonce
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const data = await response.json();

            // Check for successful response
            if (data.success) {
                // Transform API data format
                const chartData = {
                    dates: (data.data || []).map(item => item.date),
                    counts: (data.data || []).map(item => item.count),
                    room: data.room
                };
                
                // Render chart with transformed data
                this.renderChart(chartData);

                // Update summary stats
                this.updateSummaryStats(data.summary || {});
            }

        } catch (error) {
            console.error('Failed to load analytics:', error);
            this.showErrorState(error.message);
        }
    }

    /**
     * Render chart with data
     * @param {object} data
     */
    renderChart(data) {
        const chartContainer = this.modal.querySelector('.chart-container');
        
        if (!window.Chart) {
            chartContainer.innerHTML = `
                <div style="text-align: center; padding: 2rem; color: #6b7280;">
                    <p>Chart.js library not loaded.</p>
                    <p style="font-size: 0.875rem; margin-top: 0.5rem;">Please ensure Chart.js is included in your page.</p>
                </div>
            `;
            return;
        }

        // Destroy existing chart
        if (this.chart) {
            this.chart.destroy();
            this.chart = null;
        }
        
        // Clear container
        chartContainer.innerHTML = '';
        
        // Create new canvas
        const canvas = document.createElement('canvas');
        chartContainer.appendChild(canvas);
        
        const ctx = canvas.getContext('2d');

        this.chart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.dates || [],
                datasets: [{
                    label: 'Prospects',
                    data: data.counts || [],
                    borderColor: this.getRoomColor(data.room),
                    backgroundColor: this.getRoomColor(data.room, 0.1),
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });
    }

    /**
     * Show error state
     * @param {string} message
     */
    showErrorState(message) {
        const chartContainer = this.modal.querySelector('.chart-container');
        if (chartContainer) {
            chartContainer.innerHTML = `
                <div class="error-state" style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; gap: 1rem; text-align: center;">
                    <svg style="width: 48px; height: 48px; color: #ef4444;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="12" y1="8" x2="12" y2="12"/>
                        <line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>
                    <p style="margin: 0; color: #374151; font-weight: 500;">Failed to load analytics</p>
                    <p class="error-message" style="margin: 0; font-size: 0.875rem; color: #6b7280;">${this.escapeHtml(message)}</p>
                    <button class="retry-btn" onclick="location.reload()" style="padding: 0.5rem 1.5rem; background: #3b82f6; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 500;">
                        Retry
                    </button>
                </div>
            `;
        }
    }

    /**
     * Update summary statistics
     * @param {object} summary
     */
    updateSummaryStats(summary) {
        const statsContainer = this.modal.querySelector('.summary-stats');
        if (!statsContainer) return;

        statsContainer.innerHTML = `
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
                <div style="padding: 1rem; background: #f9fafb; border-radius: 8px; text-align: center;">
                    <div style="font-size: 0.875rem; color: #6b7280; margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.05em;">Total Prospects</div>
                    <div style="font-size: 1.75rem; font-weight: 700; color: #111827;">${summary.total || 0}</div>
                </div>
                <div style="padding: 1rem; background: #f9fafb; border-radius: 8px; text-align: center;">
                    <div style="font-size: 0.875rem; color: #6b7280; margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.05em;">Avg Score</div>
                    <div style="font-size: 1.75rem; font-weight: 700; color: #111827;">${summary.avg_score || 0}</div>
                </div>
                <div style="padding: 1rem; background: #f9fafb; border-radius: 8px; text-align: center;">
                    <div style="font-size: 0.875rem; color: #6b7280; margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.05em;">Conversion Rate</div>
                    <div style="font-size: 1.75rem; font-weight: 700; color: #111827;">${summary.conversion_rate || 0}%</div>
                </div>
            </div>
        `;
    }

    /**
     * Get room-specific color
     * @param {string} room
     * @param {number} alpha - Optional opacity (0-1)
     * @returns {string}
     */
    getRoomColor(room, alpha = 1) {
        const colors = {
            problem: `rgba(239, 68, 68, ${alpha})`,    // Red
            solution: `rgba(251, 146, 60, ${alpha})`,  // Orange
            offer: `rgba(34, 197, 94, ${alpha})`,      // Green
            sales: `rgba(168, 85, 247, ${alpha})`      // Purple
        };
        return colors[room] || `rgba(100, 116, 139, ${alpha})`;
    }

    /**
     * Capitalize room name
     * @param {string} room
     * @returns {string}
     */
    capitalizeRoom(room) {
        if (!room) return '';
        return room.charAt(0).toUpperCase() + room.slice(1);
    }

    /**
     * Escape HTML to prevent XSS
     * @param {string} text
     * @returns {string}
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Add CSS for animations
const style = document.createElement('style');
style.textContent = `
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
    
    .rtr-modal {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        z-index: 9999;
        display: none;
        align-items: center;
        justify-content: center;
    }
    
    .rtr-modal.active {
        display: flex !important;
    }
    
    .modal-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        cursor: pointer;
    }
    
    .modal-content {
        position: relative;
        background: white;
        border-radius: 12px;
        max-width: 900px;
        width: 90%;
        max-height: 80vh;
        overflow: auto;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        z-index: 10;
    }
    
    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1.5rem;
        border-bottom: 1px solid #e5e7eb;
    }
    
    .modal-title {
        margin: 0;
        font-size: 1.25rem;
        font-weight: 600;
        color: #111827;
    }
    
    .modal-close {
        background: none;
        border: none;
        font-size: 1.5rem;
        cursor: pointer;
        padding: 0.25rem 0.5rem;
        color: #6b7280;
        transition: color 0.2s;
    }
    
    .modal-close:hover {
        color: #111827;
    }
    
    .modal-body {
        padding: 1.5rem;
    }
`;
document.head.appendChild(style);

// CRITICAL: Export the class as default
export default AnalyticsManager;