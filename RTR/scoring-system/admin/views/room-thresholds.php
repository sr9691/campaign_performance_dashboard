<?php
/**
 * Room Thresholds Page Content (Standalone - Global Configuration)
 * 
 * @package DirectReach
 * @subpackage RTR_Scoring_System
 * @since 2.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get database handler
$system = directreach_scoring_system();
$thresholds_db = $system->get_room_thresholds_db();

// Get global thresholds
$global_thresholds = $thresholds_db->get_global_thresholds();

// Get statistics
$stats = $thresholds_db->get_statistics();
?>

<!-- Header -->
<header class="admin-header">
    <div class="header-content">
        <div class="header-left">
            <img src="<?php echo esc_url(plugin_dir_url(dirname(dirname(__FILE__))) . 'assets/MEMO_Seal.png'); ?>" 
                 alt="DirectReach" 
                 class="header-logo">
            <span class="admin-badge">Room Thresholds</span>
        </div>
        
        <div class="header-right">
            <!-- Back to Campaign Builder -->
            <a href="<?php echo esc_url(admin_url('admin.php?page=dr-campaign-builder')); ?>" 
               class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Campaign Builder
            </a>
            
            <!-- Settings Dropdown -->
            <div class="settings-dropdown" id="settings-dropdown-thresholds">
                <button class="settings-toggle" id="settings-toggle-thresholds">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                    <i class="fas fa-chevron-down"></i>
                </button>
                <div class="settings-menu">
                    <a href="<?php echo admin_url('admin.php?page=dr-room-thresholds'); ?>" class="settings-item active">
                        <i class="fas fa-sliders-h"></i>
                        <span>Room Thresholds</span>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=dr-scoring-rules'); ?>" class="settings-item">
                        <i class="fas fa-calculator"></i>
                        <span>Scoring Rules</span>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=dr-global-templates'); ?>" class="settings-item">
                        <i class="fas fa-robot"></i>
                        <span>Global Email Templates</span>
                    </a>
                </div>
            </div>
            
            <!-- User Info -->
            <div class="admin-user-info">
                <div class="user-avatar">
                    <?php echo esc_html(strtoupper(substr(wp_get_current_user()->display_name, 0, 2))); ?>
                </div>
                <div class="user-details">
                    <div class="user-name"><?php echo esc_html(wp_get_current_user()->display_name); ?></div>
                    <div class="user-role">Administrator</div>
                </div>
            </div>
            
            <!-- Logout -->
            <a href="<?php echo esc_url(wp_logout_url()); ?>" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>
</header>

<!-- Main Content -->
<main class="workflow-main">
    <div class="workflow-container">
        
        <div id="room-thresholds-app" class="thresholds-container">
            
            <!-- Page Header -->
            <div class="step-header">
                <h2>
                    <i class="fas fa-chart-line"></i>
                    Global Room Thresholds
                </h2>
                <p class="step-description">
                    Configure default score ranges that determine when prospects move between Problem, Solution, and Offer rooms.
                    These defaults apply to all clients unless overridden.
                </p>
            </div>

            <!-- Statistics Summary -->
            <div class="stats-summary">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-building"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo esc_html($stats['clients_with_custom_thresholds']); ?></div>
                        <div class="stat-label">Clients with Custom Thresholds</div>
                    </div>
                </div>
                
                <div class="stat-card problem">
                    <div class="stat-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value">
                            0-<?php echo esc_html($global_thresholds['problem_max']); ?>
                        </div>
                        <div class="stat-label">Problem Room Range</div>
                    </div>
                </div>
                
                <div class="stat-card solution">
                    <div class="stat-icon">
                        <i class="fas fa-lightbulb"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value">
                            <?php echo esc_html($global_thresholds['problem_max'] + 1); ?>-<?php echo esc_html($global_thresholds['solution_max']); ?>
                        </div>
                        <div class="stat-label">Solution Room Range</div>
                    </div>
                </div>
                
                <div class="stat-card offer">
                    <div class="stat-icon">
                        <i class="fas fa-gift"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value">
                            <?php echo esc_html($global_thresholds['offer_min']); ?>+
                        </div>
                        <div class="stat-label">Offer Room Range</div>
                    </div>
                </div>
            </div>

            <!-- Visual Slider Representation -->
            <div class="threshold-visual-section">
                <div class="section-header">
                    <h3>
                        <i class="fas fa-chart-bar"></i>
                        Score Distribution
                    </h3>
                </div>
                <div class="threshold-slider-container">
                    <div class="threshold-track">
                        <div class="room-segment problem" id="global-problem-segment">
                            <span class="room-label">Problem</span>
                            <span class="room-range">0-<span class="max-value"><?php echo esc_html($global_thresholds['problem_max']); ?></span></span>
                        </div>
                        <div class="room-segment solution" id="global-solution-segment">
                            <span class="room-label">Solution</span>
                            <span class="room-range">
                                <span class="min-value"><?php echo esc_html($global_thresholds['problem_max'] + 1); ?></span>-<span class="max-value"><?php echo esc_html($global_thresholds['solution_max']); ?></span>
                            </span>
                        </div>
                        <div class="room-segment offer" id="global-offer-segment">
                            <span class="room-label">Offer</span>
                            <span class="room-range"><span class="min-value"><?php echo esc_html($global_thresholds['offer_min']); ?></span>+</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Input Form -->
            <form id="global-thresholds-form" class="threshold-form">
                
                <div class="form-section">
                    <div class="threshold-input-group">
                        <div class="input-icon problem">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="input-content">
                            <label for="global_problem_max">
                                Problem Room Maximum
                                <span class="required">*</span>
                            </label>
                            <input 
                                type="number" 
                                id="global_problem_max" 
                                name="problem_max" 
                                value="<?php echo esc_attr($global_thresholds['problem_max']); ?>"
                                min="1" 
                                max="100" 
                                required
                            />
                            <span class="help-text">
                                Prospects with scores 0 to this value qualify for Problem Room
                            </span>
                        </div>
                    </div>

                    <div class="threshold-input-group">
                        <div class="input-icon solution">
                            <i class="fas fa-lightbulb"></i>
                        </div>
                        <div class="input-content">
                            <label for="global_solution_max">
                                Solution Room Maximum
                                <span class="required">*</span>
                            </label>
                            <input 
                                type="number" 
                                id="global_solution_max" 
                                name="solution_max" 
                                value="<?php echo esc_attr($global_thresholds['solution_max']); ?>"
                                min="1" 
                                max="200" 
                                required
                            />
                            <span class="help-text">
                                Prospects above Problem max and up to this value qualify for Solution Room
                            </span>
                        </div>
                    </div>

                    <div class="threshold-input-group">
                        <div class="input-icon offer">
                            <i class="fas fa-gift"></i>
                        </div>
                        <div class="input-content">
                            <label for="global_offer_min">
                                Offer Room Minimum
                                <span class="required">*</span>
                            </label>
                            <input 
                                type="number" 
                                id="global_offer_min" 
                                name="offer_min" 
                                value="<?php echo esc_attr($global_thresholds['offer_min']); ?>"
                                min="1" 
                                max="300" 
                                required
                            />
                            <span class="help-text">
                                Prospects with this score or higher qualify for Offer Room
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Validation Messages -->
                <div class="validation-messages" style="display: none;">
                    <div class="validation-message error">
                        <i class="fas fa-exclamation-circle"></i>
                        <span class="message-text"></span>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-large" id="save-global-thresholds">
                        <i class="fas fa-save"></i>
                        Save Global Thresholds
                    </button>
                </div>

            </form>
            
        </div>
        
    </div>
</main>

<!-- Notification Container -->
<div class="notification-container"></div>

<!-- Settings dropdown toggle script -->
<script>
(function() {
    'use strict';
    const dropdown = document.getElementById('settings-dropdown-thresholds');
    const toggleBtn = document.getElementById('settings-toggle-thresholds');
    
    if (toggleBtn && dropdown) {
        toggleBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            dropdown.classList.toggle('active');
        });
        
        document.addEventListener('click', function(e) {
            if (!dropdown.contains(e.target)) {
                dropdown.classList.remove('active');
            }
        });
    }
})();
</script>

<?php
// Localize script data
wp_localize_script('rtr-room-thresholds', 'rtrThresholdsData', [
    'nonce' => wp_create_nonce('rtr_thresholds_nonce'),
    'apiUrl' => rest_url('directreach/v2/'),
    'globalThresholds' => $global_thresholds,
    'strings' => [
        'saveSuccess' => __('Thresholds saved successfully', 'directreach'),
        'saveError' => __('Failed to save thresholds', 'directreach'),
        'validationError' => __('Please fix validation errors before saving', 'directreach'),
    ]
]);