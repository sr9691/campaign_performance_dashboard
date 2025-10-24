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
?>

<div class="campaign-builder-wrap">
    
    <!-- Header -->
    <?php 
    $args = [
        'page_badge' => 'Campaign Builder',
        'active_page' => 'dr-campaign-builder',
        'show_back_btn' => false  // Campaign Builder doesn't need a back button
    ];
    include __DIR__ . '/partials/admin-header.php';
    ?>

    <!-- Breadcrumb Navigation -->
    <div class="workflow-breadcrumb">
        <div class="breadcrumb-container">
            <div class="breadcrumb-steps">
                <!-- Step 1: Client -->
                <div class="breadcrumb-item active" data-step="client">
                    <i class="fas fa-building step-icon"></i>
                    <div class="step-info">
                        <div class="step-label">Step 1: Client</div>
                        <div class="step-status">Select Client</div>
                    </div>
                </div>
                
                <span class="breadcrumb-separator">
                    <i class="fas fa-chevron-right"></i>
                </span>
                
                <!-- Step 2: Campaign -->
                <div class="breadcrumb-item disabled" data-step="campaign">
                    <i class="fas fa-bullhorn step-icon"></i>
                    <div class="step-info">
                        <div class="step-label">Step 2: Campaign</div>
                        <div class="step-status">Configure Campaign</div>
                    </div>
                </div>
                
                <span class="breadcrumb-separator">
                    <i class="fas fa-chevron-right"></i>
                </span>
                
                <!-- Step 3: Content Links -->
                <div class="breadcrumb-item disabled" data-step="content-links">
                    <i class="fas fa-link step-icon"></i>
                    <div class="step-info">
                        <div class="step-label">Step 3: Content Links</div>
                        <div class="step-status">Add Content</div>
                    </div>
                </div>
                
                <span class="breadcrumb-separator">
                    <i class="fas fa-chevron-right"></i>
                </span>
                
                <!-- Step 4: Templates -->
                <div class="breadcrumb-item disabled" data-step="templates">
                    <i class="fas fa-envelope step-icon"></i>
                    <div class="step-info">
                        <div class="step-label">Step 4: Templates</div>
                        <div class="step-status">Create Templates</div>
                    </div>
                </div>
            </div>
            
            <!-- Save Indicator -->
            <div class="save-indicator">
                <i class="fas fa-check-circle"></i>
                <span>All changes saved</span>
            </div>
        </div>
    </div>
    <!-- END BREADCRUMB - CLOSING DIV ADDED HERE -->
    <!-- Main Content Area -->
    <div class="workflow-main">
        <div class="workflow-container">
            <!-- Step 1: Client Selection -->
            <?php include DR_CB_PLUGIN_DIR . 'admin/views/partials/client-step.php'; ?>
            
            <!-- Step 2: Campaign Configuration -->
            <?php include DR_CB_PLUGIN_DIR . 'admin/views/partials/campaign-step.php'; ?>
            
            <!-- Step 3: Content Links - NEW -->
            <?php include DR_CB_PLUGIN_DIR . 'admin/views/partials/content-links-step.php'; ?>
            
            <!-- Step 4: Email Templates - PREVIOUSLY STEP 3 -->
            <?php include DR_CB_PLUGIN_DIR . 'admin/views/partials/templates-step.php'; ?>
            
            <!-- Navigation Buttons -->
            <div class="workflow-navigation">
                <button class="btn btn-secondary" data-action="previous-step" style="display:none;">
                    <i class="fas fa-arrow-left"></i>
                    Previous Step
                </button>
                
                <div class="nav-spacer"></div>
                
                <button class="btn btn-ghost" data-action="save-draft">
                    <i class="fas fa-save"></i>
                    Save Draft
                </button>
                
                <button class="btn btn-primary" data-action="next-step">
                    Next Step
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