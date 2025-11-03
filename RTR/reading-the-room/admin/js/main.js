/**
 * RTR Dashboard Main Entry Point
 * 
 * Coordinates all dashboard modules and handles global events
 */

import UIManager from './modules/ui-manager.js';
import RoomManager from './modules/room-manager.js';
import ProspectManager from './modules/prospect-manager.js';
import RTRApiClient from './modules/api-client.js';
import EmailModalManager from './modules/email-modal-manager.js';
import EmailHistoryManager from './modules/email-history-manager.js';
import AnalyticsManager from './modules/analytics-manager.js';
import EmailStatusPoller from './modules/email-status-poller.js';

class RTRDashboard {
    constructor() {
        this.config = window.rtrDashboardConfig || {};
        this.managers = {};
        this.isInitialized = false;
    }

    async init() {
        if (this.isInitialized) {
            console.warn('RTR Dashboard already initialized');
            return;
        }

        try {
            // Initialize UI Manager first
            this.managers.ui = new UIManager();

            // Initialize core managers
            this.managers.room = new RoomManager(this.config);
            this.managers.prospect = new ProspectManager(this.config);
            this.managers.prospect.setUIManager(this.managers.ui); // Pass UI manager
            this.managers.apiClient = new RTRApiClient(this.config);
            this.managers.emailModal = new EmailModalManager(this.managers.apiClient, this.config);
            this.managers.emailHistory = new EmailHistoryManager(this.config);
            this.managers.analytics = new AnalyticsManager(this.config);
            
            // NEW: Initialize email status poller
            this.managers.emailPoller = new EmailStatusPoller(this.config);

            // Set up global event listeners
            this.setupGlobalEvents();

            // Apply keyboard shortcuts
            this.setupKeyboardShortcuts();

            this.isInitialized = true;
            console.log('RTR Dashboard initialized successfully');

            // Show welcome message
            if (this.config.showWelcome !== false) {
                this.showWelcomeMessage();
            }

        } catch (error) {
            console.error('Failed to initialize RTR Dashboard:', error);
            if (this.managers.ui) {
                this.managers.ui.notify('Failed to initialize dashboard. Please refresh the page.', 'error');
            }
        }
    }

    setupGlobalEvents() {
        // Handle window visibility changes
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                this.onPageVisible();
            }
        });

        // Handle online/offline status
        window.addEventListener('online', () => {
            this.managers.ui.notify('Connection restored', 'success');
            this.refreshData();
        });

        window.addEventListener('offline', () => {
            this.managers.ui.notify('Connection lost. Changes may not be saved.', 'error');
        });

        // Handle analytics modal open
        document.addEventListener('rtr:openAnalytics', (e) => {
            const { room } = e.detail;
            if (this.managers.analytics) {
                this.managers.analytics.open(room);
            }
        });

        // Handle filter changes
        document.addEventListener('rtr:filterChanged', () => {
            this.onFilterChanged();
        });

        // Handle prospect updates
        document.addEventListener('rtr:prospectUpdated', (e) => {
            this.onProspectUpdated(e.detail);
        });

        // Handle prospect archived
        document.addEventListener('rtr:prospectArchived', (e) => {
            this.onProspectArchived(e.detail);
        });

        // Handle sales handoff
        document.addEventListener('rtr:salesHandoff', (e) => {
            this.onSalesHandoff(e.detail);
        });

        // Handle email generated
        document.addEventListener('rtr:emailGenerated', (e) => {
            this.onEmailGenerated(e.detail);
        });

        // NEW: Handle email state updates from poller
        document.addEventListener('rtr:email-states-updated', (e) => {
            this.onEmailStatesUpdated(e.detail);
        });

        // Handle error display requests
        document.addEventListener('rtr:showError', (e) => {
            this.managers.ui.notify(e.detail.message, 'error');
        });

        // Global click handler for modals
        document.addEventListener('click', (e) => {
            // Close dropdowns
            if (!e.target.closest('.rtr-dropdown')) {
                this.closeAllDropdowns();
            }

            // Close analytics modal
            const modal = document.getElementById('analytics-modal');
            if (modal && (e.target === modal || e.target.closest('.modal-close'))) {
                this.managers.analytics.close();
            }
        });

        // Handle escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.handleEscape();
            }
        });
    }

    setupKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Skip if user is typing
            if (e.target.matches('input, textarea, select')) {
                return;
            }

            // Ctrl/Cmd + R: Refresh data
            if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
                e.preventDefault();
                this.refreshData();
            }

            // ?: Show keyboard shortcuts help
            if (e.key === '?' && !e.shiftKey) {
                this.showKeyboardShortcuts();
            }
        });
    }

    onFilterChanged() {
        if (this.managers.room) {
            this.managers.room.loadRoomCounts();
        }
        if (this.managers.prospect) {
            this.managers.prospect.refreshAllRooms();
        }
    }

    onProspectUpdated(data) {
        if (this.managers.room) {
            this.managers.room.loadRoomCounts();
        }
        this.managers.ui.notify('Prospect updated successfully', 'success');
        if (this.config.trackingEnabled) {
            this.trackEvent('prospect_updated', data);
        }
    }

    onProspectArchived(data) {
        if (this.managers.room) {
            this.managers.room.loadRoomCounts();
        }
        if (this.managers.prospect) {
            this.managers.prospect.removeProspect(data.visitorId, data.room);
        }
        this.managers.ui.notify(`Prospect archived: ${data.reason}`, 'success');
        if (this.config.trackingEnabled) {
            this.trackEvent('prospect_archived', data);
        }
    }

    onSalesHandoff(data) {
        if (this.managers.room) {
            this.managers.room.loadRoomCounts();
        }
        if (this.managers.prospect) {
            this.managers.prospect.removeProspect(data.visitorId, 'offer');
        }
        this.managers.ui.notify('Prospect handed off to sales team', 'success');
        if (this.config.trackingEnabled) {
            this.trackEvent('sales_handoff', data);
        }
    }

    onEmailGenerated(data) {
        if (this.managers.prospect) {
            this.managers.prospect.updateProspectEmailStatus(data.visitorId, data.room);
        }
        if (this.config.trackingEnabled) {
            this.trackEvent('email_generated', data);
        }
    }

    /**
     * NEW: Handle email state updates from poller
     * @param {Object} data - { changes: Array of state change objects }
     */
    onEmailStatesUpdated(data) {
        if (!data || !data.changes || !Array.isArray(data.changes)) {
            console.warn('Invalid email states update data');
            return;
        }

        console.log(`[Main] Processing ${data.changes.length} email state changes`);

        data.changes.forEach(change => {
            const { visitorId, emailNumber, oldState, newState, emailTrackingId } = change;

            // Update UI for this specific email button
            if (this.managers.prospect) {
                this.managers.prospect.updateButtonState(visitorId, emailNumber, newState);
            }

            // Notify user based on state transition
            if (oldState === 'pending' && newState === 'ready') {
                this.managers.ui.notify(`Email #${emailNumber} is ready! Click to view.`, 'success');
            } else if (newState === 'opened') {
                this.managers.ui.notify(`Email #${emailNumber} was opened!`, 'info');
            } else if (newState === 'failed') {
                this.managers.ui.notify(`Email #${emailNumber} generation failed. Click to retry.`, 'error');
            }

            // Track state changes
            if (this.config.trackingEnabled) {
                this.trackEvent('email_state_changed', {
                    visitorId,
                    emailNumber,
                    oldState,
                    newState,
                    emailTrackingId
                });
            }
        });

        // Optionally refresh room counts if any emails changed
        if (data.changes.length > 0 && this.managers.room) {
            this.managers.room.loadRoomCounts();
        }
    }

    onPageVisible() {
        const lastRefresh = localStorage.getItem('rtr_last_refresh');
        const now = Date.now();
        const fiveMinutes = 5 * 60 * 1000;

        if (!lastRefresh || now - parseInt(lastRefresh) > fiveMinutes) {
            this.refreshData(true);
        }
    }

    refreshData(silent = false) {
        if (!silent) {
            this.managers.ui.showLoader('Refreshing data...');
        }

        const promises = [];

        if (this.managers.room) {
            promises.push(this.managers.room.loadRoomCounts());
        }

        if (this.managers.prospect) {
            promises.push(this.managers.prospect.refreshAllRooms());
        }

        Promise.all(promises)
            .then(() => {
                if (!silent) {
                    this.managers.ui.hideLoader();
                    this.managers.ui.notify('Data refreshed', 'success');
                }
                localStorage.setItem('rtr_last_refresh', Date.now().toString());
            })
            .catch((error) => {
                console.error('Failed to refresh data:', error);
                this.managers.ui.hideLoader();
                this.managers.ui.notify('Failed to refresh data', 'error');
            });
    }

    closeAllDropdowns() {
        document.querySelectorAll('.rtr-dropdown.is-open').forEach(dropdown => {
            dropdown.classList.remove('is-open');
        });
    }

    handleEscape() {
        if (this.managers.emailModal && this.managers.emailModal.isOpen) {
            this.managers.emailModal.close();
            return;
        }

        if (this.managers.emailHistory && this.managers.emailHistory.isOpen) {
            this.managers.emailHistory.close();
            return;
        }

        if (this.managers.analytics && this.managers.analytics.isOpen) {
            this.managers.analytics.close();
            return;
        }

        this.closeAllDropdowns();
    }

    showWelcomeMessage() {
        const hasSeenWelcome = localStorage.getItem('rtr_welcome_seen');
        if (!hasSeenWelcome) {
            setTimeout(() => {
                this.managers.ui.notify('Welcome to the Reading the Room Dashboard!', 'info');
                localStorage.setItem('rtr_welcome_seen', 'true');
            }, 1000);
        }
    }

    showKeyboardShortcuts() {
        const shortcuts = [
            { keys: 'Ctrl/Cmd + R', description: 'Refresh data' },
            { keys: 'Esc', description: 'Close modals/dropdowns' },
            { keys: '?', description: 'Show this help' }
        ];

        const html = `
            <div class="rtr-shortcuts-modal">
                <h3>Keyboard Shortcuts</h3>
                <table class="rtr-shortcuts-table">
                    ${shortcuts.map(s => `
                        <tr>
                            <td class="rtr-shortcut-keys"><kbd>${s.keys}</kbd></td>
                            <td class="rtr-shortcut-desc">${s.description}</td>
                        </tr>
                    `).join('')}
                </table>
                <button class="rtr-btn-secondary" onclick="this.closest('.rtr-modal-overlay').remove()">
                    Close
                </button>
            </div>
        `;

        const overlay = document.createElement('div');
        overlay.className = 'rtr-modal-overlay';
        overlay.innerHTML = html;
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) {
                overlay.remove();
            }
        });
        document.body.appendChild(overlay);
        
        setTimeout(() => {
            overlay.classList.add('rtr-modal-show');
        }, 10);
    }

    trackEvent(eventName, data = {}) {
        if (typeof gtag !== 'undefined') {
            gtag('event', eventName, {
                event_category: 'rtr_dashboard',
                ...data
            });
        }

        if (this.config.trackingEndpoint) {
            fetch(this.config.trackingEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.config.nonce
                },
                body: JSON.stringify({
                    event: eventName,
                    data,
                    timestamp: new Date().toISOString()
                })
            }).catch(console.error);
        }
    }

    isElementInViewport(el) {
        const rect = el.getBoundingClientRect();
        return (
            rect.top >= 0 &&
            rect.left >= 0 &&
            rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
            rect.right <= (window.innerWidth || document.documentElement.clientWidth)
        );
    }

    getManager(name) {
        return this.managers[name];
    }
}

// Initialize dashboard when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.rtrDashboard = new RTRDashboard();
        window.rtrDashboard.init();
    });
} else {
    window.rtrDashboard = new RTRDashboard();
    window.rtrDashboard.init();
}

export default RTRDashboard;