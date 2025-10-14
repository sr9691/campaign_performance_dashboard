<?php
/**
 * Campaign Builder Admin Page Template
 * 
 * @package DirectReach
 * @subpackage Campaign_Builder
 */

if (!defined('ABSPATH')) {
    exit;
}

$current_user = wp_get_current_user();
$user_initials = strtoupper(substr($current_user->display_name, 0, 2));
$user_role = !empty($current_user->roles) ? ucfirst($current_user->roles[0]) : 'User';
?>

<div class="campaign-builder-wrap">
    
    <!-- Header -->
    <div class="admin-header">
        <div class="header-content">
            <div class="header-left">
                <img src="<?php echo esc_url(plugins_url('assets/MEMO_Seal.png', dirname(dirname(__FILE__)))); ?>" 
                     alt="DirectReach" 
                     class="header-logo">
                <div class="admin-badge">Campaign Builder</div>
            </div>
            
            <div class="header-right">
                <!-- Settings Dropdown -->
                <div class="settings-dropdown" id="settings-dropdown">
                    <button class="settings-toggle" id="settings-toggle-btn">
                        <i class="fas fa-cog"></i>
                        <span>Settings</span>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="settings-menu" id="settings-menu">
                        <a href="<?php echo admin_url('admin.php?page=dr-room-thresholds'); ?>" class="settings-item">
                            <i class="fas fa-sliders-h"></i>
                            <span>Room Thresholds</span>
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=dr-scoring-rules'); ?>" class="settings-item">
                            <i class="fas fa-calculator"></i>
                            <span>Scoring Rules</span>
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=dr-global-templates'); ?>" class="settings-item">
                            <i class="fas fa-envelope-open-text"></i>
                            <span>Global Email Templates</span>
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=dr-ai-settings'); ?>" class="settings-item">
                            <i class="fas fa-robot"></i>
                            <span>AI Configuration</span>
                        </a>
                    </div>
                </div>
                
                <!-- User Info -->
                <div class="admin-user-info">
                    <div class="user-avatar"><?php echo esc_html($user_initials); ?></div>
                    <div class="user-details">
                        <div class="user-name"><?php echo esc_html($current_user->display_name); ?></div>
                        <div class="user-role"><?php echo esc_html($user_role); ?></div>
                    </div>
                </div>
                
                <!-- Logout -->
                <button class="logout-btn" onclick="window.location.href='<?php echo esc_url(wp_logout_url()); ?>'">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Breadcrumb Navigation -->
    <div class="workflow-breadcrumb">
        <div class="breadcrumb-container">
            <div class="breadcrumb-steps">
                <!-- Step 1: Client -->
                <div class="breadcrumb-item active" data-step="client">
                    <div class="step-icon">
                        <i class="fas fa-building"></i>
                    </div>
                    <div class="step-info">
                        <div class="step-label">Client</div>
                        <div class="step-status">Select or create client</div>
                    </div>
                </div>
                
                <div class="breadcrumb-separator">
                    <i class="fas fa-chevron-right"></i>
                </div>
                
                <!-- Step 2: Campaign -->
                <div class="breadcrumb-item" data-step="campaign">
                    <div class="step-icon">
                        <i class="fas fa-bullhorn"></i>
                    </div>
                    <div class="step-info">
                        <div class="step-label">Campaign</div>
                        <div class="step-status">Configure campaign</div>
                    </div>
                </div>
                
                <div class="breadcrumb-separator">
                    <i class="fas fa-chevron-right"></i>
                </div>
                
                <!-- Step 3: Templates -->
                <div class="breadcrumb-item" data-step="templates">
                    <div class="step-icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div class="step-info">
                        <div class="step-label">Email Templates</div>
                        <div class="step-status">Create AI prompts</div>
                    </div>
                </div>
            </div>
            
            <!-- Save Indicator -->
            <div class="save-indicator">
                <i class="fas fa-circle"></i>
                <span>All changes saved</span>
            </div>
        </div>
    </div>

    <!-- Main Content Area -->
    <div class="workflow-main">
        <div class="workflow-container">
            
            <!-- Step Content -->
            <div class="step-content-wrapper">
                
                <!-- Client Step -->
                <div class="step-content" data-step-content="client">
                    <?php 
                    $client_step_file = DR_CB_PLUGIN_DIR . 'admin/views/partials/client-step.php';
                    if (file_exists($client_step_file)) {
                        include $client_step_file;
                    } else {
                        echo '<div class="step-placeholder">';
                        echo '<p>Client step template not found: ' . esc_html($client_step_file) . '</p>';
                        echo '</div>';
                    }
                    ?>
                </div>
                
                <!-- Campaign Step -->
                <div class="step-content" data-step-content="campaign" style="display: none;">
                    <h2 class="step-title">Configure Campaign</h2>
                    <p class="step-description">Set up your campaign details and UTM parameters.</p>
                    
                    <div class="step-body">
                        <?php include DR_CB_PLUGIN_DIR . 'admin/views/partials/campaign-step.php'; ?>
                    </div>
                </div>
                
                <!-- Templates Step -->
                <div class="step-content" data-step-content="templates" style="display: none;">
                    <div class="step-body">
                        <?php include DR_CB_PLUGIN_DIR . 'admin/views/partials/templates-step.php'; ?>
                    </div>
                </div>
                
            </div>

            <!-- Navigation Buttons -->
            <div class="workflow-navigation">
                <button type="button" class="btn btn-secondary" data-action="previous-step" style="display: none;">
                    <i class="fas fa-arrow-left"></i>
                    <span>Previous Step</span>
                </button>
                
                <div class="nav-spacer"></div>
                
                <button type="button" class="btn btn-ghost" data-action="save-draft">
                    <i class="fas fa-save"></i>
                    <span>Save Draft</span>
                </button>
                
                <button type="button" class="btn btn-primary" data-action="next-step">
                    <span>Next Step</span>
                    <i class="fas fa-arrow-right"></i>
                </button>
            </div>
            
        </div>
    </div>

</div>

<!-- Notification Container -->
<div class="notification-container"></div>

<?php
/**
 * Include Client Settings Side Panel
 * This file is in the scoring-system directory
 */
$panel_template = DR_CB_PLUGIN_DIR . '../scoring-system/admin/views/partials/client-settings-panel.php';

if (file_exists($panel_template)) {
    include $panel_template;
} else {
    // Debug: Show the path being checked
    if (defined('WP_DEBUG') && WP_DEBUG) {
        echo '<!-- Client Settings Panel not found at: ' . esc_html($panel_template) . ' -->';
    }
}
?>

<script>
// Settings Dropdown Toggle - Vanilla JavaScript
(function() {
    'use strict';
    
    const dropdown = document.getElementById('settings-dropdown');
    const toggleBtn = document.getElementById('settings-toggle-btn');
    const menu = document.getElementById('settings-menu');
    
    if (toggleBtn && dropdown) {
        // Toggle dropdown on button click
        toggleBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            dropdown.classList.toggle('active');
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!dropdown.contains(e.target)) {
                dropdown.classList.remove('active');
            }
        });
        
        // Prevent closing when clicking inside menu
        if (menu) {
            menu.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        }
    }
})();
</script>