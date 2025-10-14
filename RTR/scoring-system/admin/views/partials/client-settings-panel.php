<?php
/**
 * Client Settings Panel (Slide-in/Modal)
 * 
 * Reusable panel for client-specific configuration:
 * - Room Thresholds
 * - Scoring Rules
 * 
 * @package DirectReach_Campaign_Builder
 * @since 2.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<!-- Settings Panel Overlay -->
<div class="client-settings-overlay" id="client-settings-overlay" style="display: none;">
    
    <!-- Settings Panel -->
    <div class="client-settings-panel" id="client-settings-panel">
        
        <!-- Panel Header -->
        <div class="panel-header">
            <div class="panel-title">
                <i class="fas fa-cog"></i>
                <h3>Client Settings: <span id="panel-client-name">--</span></h3>
            </div>
            <button type="button" class="panel-close-btn" id="close-settings-panel">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <!-- Panel Tabs -->
        <div class="panel-tabs">
            <button type="button" class="panel-tab active" data-tab="thresholds">
                <i class="fas fa-sliders-h"></i>
                <span>Room Thresholds</span>
            </button>
            <button type="button" class="panel-tab" data-tab="scoring">
                <i class="fas fa-calculator"></i>
                <span>Scoring Rules</span>
            </button>
        </div>
        
        <!-- Panel Content -->
        <div class="panel-content">
            
            <!-- Loading State -->
            <div class="panel-loading" id="panel-loading">
                <div class="spinner"></div>
                <p>Loading client settings...</p>
            </div>
            
            <!-- Thresholds Tab Content -->
            <div class="panel-tab-content active" data-tab-content="thresholds">
                
                <!-- Source Indicator -->
                <div class="source-indicator">
                    <div class="indicator-badge global" id="thresholds-indicator-global">
                        <i class="fas fa-globe"></i>
                        <span>Using Global Defaults</span>
                    </div>
                    <div class="indicator-badge custom" id="thresholds-indicator-custom" style="display: none;">
                        <i class="fas fa-user-cog"></i>
                        <span>Custom Client Settings</span>
                    </div>
                </div>
                
                <!-- Visual Slider -->
                <div class="threshold-visual">
                    <h4>Score Distribution</h4>
                    <div class="threshold-track">
                        <div class="room-segment problem" id="client-problem-segment">
                            <span class="room-label">Problem</span>
                            <span class="room-range">0-<span class="max-value">40</span></span>
                        </div>
                        <div class="room-segment solution" id="client-solution-segment">
                            <span class="room-label">Solution</span>
                            <span class="room-range"><span class="min-value">41</span>-<span class="max-value">60</span></span>
                        </div>
                        <div class="room-segment offer" id="client-offer-segment">
                            <span class="room-label">Offer</span>
                            <span class="room-range"><span class="min-value">61</span>+</span>
                        </div>
                    </div>
                </div>
                
                <!-- Threshold Form -->
                <form id="client-thresholds-form" class="settings-form">
                    
                    <input type="hidden" id="threshold-client-id" name="client_id" value="" />
                    
                    <div class="form-group">
                        <label for="client_problem_max">
                            <i class="fas fa-exclamation-triangle problem-icon"></i>
                            Problem Room Maximum
                            <span class="required">*</span>
                        </label>
                        <div class="input-with-hint">
                            <input 
                                type="number" 
                                id="client_problem_max" 
                                name="problem_max" 
                                min="1" 
                                max="100" 
                                required
                            />
                            <span class="global-value-hint">Global: <span id="global-problem-max">40</span></span>
                        </div>
                        <small class="help-text">Prospects with scores 0 to this value qualify for Problem Room</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="client_solution_max">
                            <i class="fas fa-lightbulb solution-icon"></i>
                            Solution Room Maximum
                            <span class="required">*</span>
                        </label>
                        <div class="input-with-hint">
                            <input 
                                type="number" 
                                id="client_solution_max" 
                                name="solution_max" 
                                min="1" 
                                max="200" 
                                required
                            />
                            <span class="global-value-hint">Global: <span id="global-solution-max">60</span></span>
                        </div>
                        <small class="help-text">Prospects above Problem max and up to this value qualify for Solution Room</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="client_offer_min">
                            <i class="fas fa-gift offer-icon"></i>
                            Offer Room Minimum
                            <span class="required">*</span>
                        </label>
                        <div class="input-with-hint">
                            <input 
                                type="number" 
                                id="client_offer_min" 
                                name="offer_min" 
                                min="1" 
                                max="300" 
                                required
                            />
                            <span class="global-value-hint">Global: <span id="global-offer-min">61</span></span>
                        </div>
                        <small class="help-text">Prospects with this score or higher qualify for Offer Room</small>
                    </div>
                    
                    <!-- Validation Messages -->
                    <div class="validation-messages" id="thresholds-validation" style="display: none;">
                        <i class="fas fa-exclamation-circle"></i>
                        <span class="message-text"></span>
                    </div>
                    
                </form>
                
            </div>
            
            <!-- Scoring Rules Tab Content -->
            <div class="panel-tab-content" data-tab-content="scoring" style="display: none;">
                
                <!-- Source Indicator -->
                <div class="source-indicator">
                    <div class="indicator-badge global" id="scoring-indicator-global">
                        <i class="fas fa-globe"></i>
                        <span>Using Global Defaults</span>
                    </div>
                    <div class="indicator-badge custom" id="scoring-indicator-custom" style="display: none;">
                        <i class="fas fa-user-cog"></i>
                        <span>Custom Client Settings</span>
                    </div>
                </div>
                
                <!-- Mini Room Tabs -->
                <div class="panel-mini-tabs">
                    <button type="button" class="panel-mini-tab active" data-panel-room="problem">
                        <i class="fas fa-exclamation-triangle"></i>
                        Problem
                    </button>
                    <button type="button" class="panel-mini-tab" data-panel-room="solution">
                        <i class="fas fa-lightbulb"></i>
                        Solution
                    </button>
                    <button type="button" class="panel-mini-tab" data-panel-room="offer">
                        <i class="fas fa-gift"></i>
                        Offer
                    </button>
                </div>
                
                <!-- Rules Container -->
                <div class="panel-rules-container">
                    
                    <!-- Problem Room Rules -->
                    <div class="panel-room-rules active" data-panel-room-content="problem">
                        <div class="rules-help-text">
                            <i class="fas fa-info-circle"></i>
                            Configure firmographic qualification rules for this client
                        </div>
                        <div class="panel-rules-list" id="client-problem-rules">
                            <!-- Rendered by JavaScript -->
                        </div>
                    </div>
                    
                    <!-- Solution Room Rules -->
                    <div class="panel-room-rules" data-panel-room-content="solution" style="display: none;">
                        <div class="rules-help-text">
                            <i class="fas fa-info-circle"></i>
                            Configure engagement tracking rules for this client
                        </div>
                        <div class="panel-rules-list" id="client-solution-rules">
                            <!-- Rendered by JavaScript -->
                        </div>
                    </div>
                    
                    <!-- Offer Room Rules -->
                    <div class="panel-room-rules" data-panel-room-content="offer" style="display: none;">
                        <div class="rules-help-text">
                            <i class="fas fa-info-circle"></i>
                            Configure purchase signal rules for this client
                        </div>
                        <div class="panel-rules-list" id="client-offer-rules">
                            <!-- Rendered by JavaScript -->
                        </div>
                    </div>
                    
                </div>
                
                <!-- Quick Edit Notice -->
                <div class="quick-edit-notice">
                    <i class="fas fa-lightbulb"></i>
                    <span>Quick adjustments only. For advanced configuration, use <a href="<?php echo admin_url('admin.php?page=dr-scoring-rules'); ?>" target="_blank">Global Scoring Rules</a>.</span>
                </div>
                
            </div>
            
        </div>
        
        <!-- Panel Footer -->
        <div class="panel-footer">
            <div class="footer-left">
                <button type="button" class="btn btn-ghost" id="reset-to-global-btn">
                    <i class="fas fa-undo"></i>
                    Reset to Global
                </button>
            </div>
            <div class="footer-right">
                <button type="button" class="btn btn-secondary" id="cancel-settings-btn">
                    Cancel
                </button>
                <button type="button" class="btn btn-primary" id="save-settings-btn">
                    <i class="fas fa-save"></i>
                    Save Changes
                </button>
            </div>
        </div>
        
    </div>
    
</div>