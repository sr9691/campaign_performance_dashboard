/**
 * Room Thresholds Manager (Global Configuration)
 * 
 * Handles global thresholds configuration on standalone page
 * Follows Campaign Builder architecture
 * 
 * @package DirectReach
 * @subpackage RTR_Scoring_System
 * @since 2.1.0
 */

class RoomThresholdsManager {
    constructor(config) {
        this.config = config;
        this.apiUrl = config.apiUrl;
        this.nonce = config.nonce;
        this.globalThresholds = config.globalThresholds;
        
        this.init();
    }
    
    /**
     * Initialize the manager
     */
    init() {
        this.attachEventListeners();
        this.updateGlobalVisual();
    }
    
    /**
     * Attach all event listeners
     */
    attachEventListeners() {
        // Global thresholds form
        const globalForm = document.getElementById('global-thresholds-form');
        if (globalForm) {
            globalForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.saveGlobalThresholds();
            });
            
            // Live validation on input
            globalForm.querySelectorAll('input[type="number"]').forEach(input => {
                input.addEventListener('input', () => {
                    this.updateGlobalVisual();
                    this.validateGlobalForm();
                });
            });
        }
    }
    
    /**
     * Update global visual slider
     */
    updateGlobalVisual() {
        const problemMax = parseInt(document.getElementById('global_problem_max').value) || 40;
        const solutionMax = parseInt(document.getElementById('global_solution_max').value) || 60;
        const offerMin = parseInt(document.getElementById('global_offer_min').value) || 61;
        
        // Calculate percentages for visual distribution
        const total = 100; // Represents 0-100 scale
        const problemPercent = (problemMax / total) * 100;
        const solutionPercent = ((solutionMax - problemMax) / total) * 100;
        const offerPercent = 100 - problemPercent - solutionPercent;
        
        // Update visual segments
        const problemSegment = document.getElementById('global-problem-segment');
        const solutionSegment = document.getElementById('global-solution-segment');
        const offerSegment = document.getElementById('global-offer-segment');
        
        if (problemSegment) {
            problemSegment.style.flex = `0 0 ${problemPercent}%`;
            problemSegment.querySelector('.max-value').textContent = problemMax;
        }
        
        if (solutionSegment) {
            solutionSegment.style.flex = `0 0 ${solutionPercent}%`;
            solutionSegment.querySelector('.min-value').textContent = problemMax + 1;
            solutionSegment.querySelector('.max-value').textContent = solutionMax;
        }
        
        if (offerSegment) {
            offerSegment.style.flex = `0 0 ${offerPercent}%`;
            offerSegment.querySelector('.min-value').textContent = offerMin;
        }
    }
    
    /**
     * Validate global thresholds form
     */
    validateGlobalForm() {
        const problemMax = parseInt(document.getElementById('global_problem_max').value);
        const solutionMax = parseInt(document.getElementById('global_solution_max').value);
        const offerMin = parseInt(document.getElementById('global_offer_min').value);
        
        const errors = [];
        
        if (problemMax >= solutionMax) {
            errors.push('Problem Room max must be less than Solution Room max');
        }
        
        if (solutionMax >= offerMin) {
            errors.push('Solution Room max must be less than Offer Room min');
        }
        
        if (problemMax < 1 || solutionMax < 1 || offerMin < 1) {
            errors.push('All thresholds must be positive numbers');
        }
        
        this.showValidationErrors(errors);
        
        return errors.length === 0;
    }
    
    /**
     * Show validation errors
     */
    showValidationErrors(errors) {
        const container = document.querySelector('.validation-messages');
        const messageText = container.querySelector('.message-text');
        
        if (errors.length > 0) {
            messageText.textContent = errors.join('. ');
            container.style.display = 'block';
        } else {
            container.style.display = 'none';
        }
    }
    
    /**
     * Save global thresholds
     */
    async saveGlobalThresholds() {
        if (!this.validateGlobalForm()) {
            this.showNotification('error', 'Please fix validation errors before saving');
            return;
        }
        
        const btn = document.getElementById('save-global-thresholds');
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        
        const formData = {
            problem_max: parseInt(document.getElementById('global_problem_max').value),
            solution_max: parseInt(document.getElementById('global_solution_max').value),
            offer_min: parseInt(document.getElementById('global_offer_min').value)
        };
        
        try {
            const response = await fetch(`${this.apiUrl}room-thresholds`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.nonce
                },
                body: JSON.stringify(formData)
            });
            
            if (!response.ok) {
                throw new Error('Failed to save thresholds');
            }
            
            const data = await response.json();
            
            // Update stored global thresholds
            this.globalThresholds = formData;
            
            this.showNotification('success', 'Thresholds saved successfully');
            
        } catch (error) {
            console.error('Error saving global thresholds:', error);
            this.showNotification('error', 'Failed to save thresholds');
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    }
    
    /**
     * Show notification
     */
    showNotification(type, message) {
        const container = document.querySelector('.notification-container');
        if (!container) return;
        
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        
        const icon = type === 'success' ? 'check-circle' : 'exclamation-circle';
        
        notification.innerHTML = `
            <span>${message}</span>
            <button class="notification-close">
                <i class="fas fa-times"></i>
            </button>
        `;
        
        container.appendChild(notification);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            notification.classList.add('fade-out');
            setTimeout(() => notification.remove(), 300);
        }, 5000);
        
        // Close button
        notification.querySelector('.notification-close').addEventListener('click', () => {
            notification.classList.add('fade-out');
            setTimeout(() => notification.remove(), 300);
        });
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    if (typeof rtrThresholdsConfig !== 'undefined') {
        new RoomThresholdsManager(rtrThresholdsConfig);
    }
});