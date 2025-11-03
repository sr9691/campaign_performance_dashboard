/**
 * Email Status Poller
 * 
 * Smart poller that monitors email generation status for prospects.
 * Only runs when emails are in "generating" state, stops automatically
 * when all emails are stable, and respects visibility/resource constraints.
 * 
 * @package DirectReach
 * @subpackage ReadingTheRoom
 * @since 2.5.0
 */

export default class EmailStatusPoller {
    constructor(config) {
        this.config = config;
        this.apiUrl = config?.restUrl || window.rtrDashboardConfig?.restUrl || '';
        this.nonce = config?.nonce || window.rtrDashboardConfig?.nonce || '';
        
        this.isPolling = false;
        this.pollInterval = 60000; // 60 seconds
        this.maxPolls = 30; // 30 minutes maximum
        this.pollCount = 0;
        this.pollTimer = null;
        
        this.generatingProspects = new Set();
        this.lastStates = new Map(); // Track last known states to detect changes
        
        this.init();
    }

    /**
     * Initialize poller and event listeners
     */
    init() {
        // Listen for generation start events
        document.addEventListener('rtr:email-generation-started', (e) => {
            this.addGeneratingProspect(e.detail.visitorId, e.detail.emailNumber);
        });

        // Listen for visibility changes (pause when tab hidden)
        document.addEventListener('visibilitychange', () => {
            if (document.hidden && this.isPolling) {
                this.pausePolling();
            } else if (!document.hidden && this.generatingProspects.size > 0) {
                this.resumePolling();
            }
        });

        // Listen for manual refresh requests
        document.addEventListener('rtr:refresh-email-states', () => {
            this.checkEmailStates();
        });
    }

    /**
     * Add a prospect to the generating list and start polling
     * 
     * @param {string} visitorId - Prospect visitor ID
     * @param {number} emailNumber - Email sequence number
     */
    addGeneratingProspect(visitorId, emailNumber) {
        const key = `${visitorId}-${emailNumber}`;
        this.generatingProspects.add(key);
        
        console.log(`[EmailStatusPoller] Added generating prospect: ${key}`);
        
        if (!this.isPolling) {
            this.startPolling();
        }
    }

    /**
     * Remove a prospect from generating list
     * 
     * @param {string} visitorId - Prospect visitor ID  
     * @param {number} emailNumber - Email sequence number
     */
    removeGeneratingProspect(visitorId, emailNumber) {
        const key = `${visitorId}-${emailNumber}`;
        this.generatingProspects.delete(key);
        
        console.log(`[EmailStatusPoller] Removed generating prospect: ${key}`);
        
        if (this.generatingProspects.size === 0) {
            this.stopPolling();
        }
    }

    /**
     * Start polling for status updates
     */
    startPolling() {
        if (this.isPolling) {
            console.warn('[EmailStatusPoller] Already polling');
            return;
        }

        console.log('[EmailStatusPoller] Starting poller');
        this.isPolling = true;
        this.pollCount = 0;
        
        // Immediate first check
        this.checkEmailStates();
        
        // Set up recurring poll
        this.pollTimer = setInterval(() => {
            this.pollCount++;
            
            if (this.pollCount >= this.maxPolls) {
                console.warn('[EmailStatusPoller] Max poll count reached, stopping');
                this.stopPolling();
                this.notifyTimeout();
                return;
            }
            
            this.checkEmailStates();
        }, this.pollInterval);
    }

    /**
     * Stop polling
     */
    stopPolling() {
        if (!this.isPolling) return;
        
        console.log('[EmailStatusPoller] Stopping poller');
        
        if (this.pollTimer) {
            clearInterval(this.pollTimer);
            this.pollTimer = null;
        }
        
        this.isPolling = false;
        this.pollCount = 0;
        this.generatingProspects.clear();
    }

    /**
     * Pause polling temporarily (e.g., when tab hidden)
     */
    pausePolling() {
        if (!this.isPolling) return;
        
        console.log('[EmailStatusPoller] Pausing poller');
        
        if (this.pollTimer) {
            clearInterval(this.pollTimer);
            this.pollTimer = null;
        }
    }

    /**
     * Resume polling after pause
     */
    resumePolling() {
        if (!this.isPolling || this.pollTimer) return;
        
        console.log('[EmailStatusPoller] Resuming poller');
        
        // Immediate check on resume
        this.checkEmailStates();
        
        // Restart interval
        this.pollTimer = setInterval(() => {
            this.pollCount++;
            
            if (this.pollCount >= this.maxPolls) {
                this.stopPolling();
                this.notifyTimeout();
                return;
            }
            
            this.checkEmailStates();
        }, this.pollInterval);
    }

    /**
     * Check email states for all prospects currently in view
     */
    async checkEmailStates() {
        if (this.generatingProspects.size === 0) {
            console.log('[EmailStatusPoller] No generating prospects to check');
            return;
        }

        console.log(`[EmailStatusPoller] Checking states for ${this.generatingProspects.size} prospects`);
        
        try {
            // Get all visible prospect IDs
            const visitorIds = Array.from(this.generatingProspects).map(key => key.split('-')[0]);
            const uniqueVisitorIds = [...new Set(visitorIds)];
            
            // Batch fetch email states for all prospects
            const stateChanges = [];
            
            // Extract base URL (everything up to /wp-json)
            let baseUrl = this.apiUrl;
            if (baseUrl.includes('/wp-json')) {
                baseUrl = baseUrl.split('/wp-json')[0];
            }
            
            for (const visitorId of uniqueVisitorIds) {
                try {
                    const apiEndpoint = `${baseUrl}/wp-json/directreach/v2/emails/states/${visitorId}`;
                    const response = await fetch(apiEndpoint, {
                        method: 'GET',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-WP-Nonce': this.nonce
                        }
                    });
                    
                    if (!response.ok) {
                        console.error(`[EmailStatusPoller] Failed to fetch states for ${visitorId}:`, response.status);
                        continue;
                    }
                    
                    const data = await response.json();
                    
                    if (!data.success || !data.data || !data.data.email_states) {
                        console.warn(`[EmailStatusPoller] Invalid response for ${visitorId}`);
                        continue;
                    }
                    
                    // Check each email for this prospect
                    data.data.email_states.forEach(email => {
                        const key = `${visitorId}-${email.email_number}`;
                        
                        // Only track if we're monitoring this email
                        if (!this.generatingProspects.has(key)) {
                            return;
                        }
                        
                        const lastState = this.lastStates.get(key);
                        const currentState = email.status;
                        
                        // State changed from what we last knew
                        if (lastState && lastState !== currentState) {
                            console.log(`[EmailStatusPoller] State change detected: ${key} ${lastState} → ${currentState}`);
                            
                            stateChanges.push({
                                visitorId,
                                emailNumber: email.email_number,
                                oldState: lastState,
                                newState: currentState,
                                emailTrackingId: email.email_tracking_id
                            });
                        }
                        
                        // Update last known state
                        this.lastStates.set(key, currentState);
                        
                        // If no longer generating, remove from tracking
                        if (currentState !== 'pending' && currentState !== 'generating') {
                            this.removeGeneratingProspect(visitorId, email.email_number);
                        }
                    });
                    
                } catch (error) {
                    console.error(`[EmailStatusPoller] Error checking ${visitorId}:`, error);
                }
            }
            
            // Emit state change event if any changes detected
            if (stateChanges.length > 0) {
                console.log(`[EmailStatusPoller] Emitting ${stateChanges.length} state changes`);
                
                document.dispatchEvent(new CustomEvent('rtr:email-states-updated', {
                    detail: { changes: stateChanges }
                }));
            }
            
        } catch (error) {
            console.error('[EmailStatusPoller] Error checking email states:', error);
        }
    }

    /**
     * Notify user that polling has timed out
     */
    notifyTimeout() {
        document.dispatchEvent(new CustomEvent('rtr:showNotification', {
            detail: {
                message: 'Email generation taking longer than expected. Please refresh the page to check status.',
                type: 'warning'
            }
        }));
    }

    /**
     * Get current polling status
     * 
     * @returns {Object} Status object
     */
    getStatus() {
        return {
            isPolling: this.isPolling,
            pollCount: this.pollCount,
            maxPolls: this.maxPolls,
            generatingCount: this.generatingProspects.size,
            generatingProspects: Array.from(this.generatingProspects)
        };
    }

    /**
     * Force an immediate status check
     */
    forceCheck() {
        console.log('[EmailStatusPoller] Force check requested');
        this.checkEmailStates();
    }

    /**
     * Reset poller state
     */
    reset() {
        this.stopPolling();
        this.lastStates.clear();
        console.log('[EmailStatusPoller] Reset complete');
    }
}