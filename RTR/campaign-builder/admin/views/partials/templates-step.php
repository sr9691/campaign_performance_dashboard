<?php
/**
 * Templates Step - Campaign Builder
 * 
 * @package DirectReach_Campaign_Builder
 * @since 2.5.0
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<!-- Step Header -->
<div class="step-header">
    <h2>
        <i class="fas fa-robot"></i>
        Email Templates
    </h2>
    <p class="step-description">
        Create AI prompt templates for 
        <span class="selected-campaign-name"></span>
    </p>
</div>

<!-- AI Info Banner with Global Templates Link -->
<div class="ai-info-banner">
    <div class="banner-icon">
        <i class="fas fa-robot"></i>
    </div>
    <div class="banner-content">
        <div class="banner-text">
            <h4>AI-Powered Email Generation</h4>
            <p>Create structured prompts that guide AI to generate personalized emails. Global templates are automatically available to all campaigns as fallbacks.</p>
        </div>
        <a href="<?php echo esc_url(admin_url('admin.php?page=dr-global-templates')); ?>" class="btn btn-light">
            <i class="fas fa-globe"></i>
            Manage Global Templates
        </a>
    </div>
</div>

<!-- Room Tabs -->
<div class="room-tabs">
    <button class="room-tab active" data-room="problem">
        <i class="fas fa-question-circle"></i>
        Problem Room
        <span class="tab-status"></span>
    </button>
    <button class="room-tab" data-room="solution">
        <i class="fas fa-lightbulb"></i>
        Solution Room
        <span class="tab-status"></span>
    </button>
    <button class="room-tab" data-room="offer">
        <i class="fas fa-handshake"></i>
        Offer Room
        <span class="tab-status"></span>
    </button>
</div>

<!-- Loading State -->
<div id="templates-loading" style="display: none;">
    <div class="loading-spinner">
        <i class="fas fa-spinner fa-spin"></i>
        <p>Loading templates...</p>
    </div>
</div>

<!-- Error State -->
<div id="templates-error" style="display: none;">
    <div class="error-state">
        <i class="fas fa-exclamation-triangle"></i>
        <h3>Failed to Load Templates</h3>
        <p class="error-message"></p>
        <button class="btn btn-primary" id="retry-load-templates">
            <i class="fas fa-redo"></i> Retry
        </button>
    </div>
</div>

<!-- Content -->
<div id="templates-content" style="display: none;">
    <!-- Template Lists (one per room) -->
    <div class="template-list-container active" data-room="problem"></div>
    <div class="template-list-container" data-room="solution"></div>
    <div class="template-list-container" data-room="offer"></div>

    <!-- Include Template Form Partial (SHARED WITH GLOBAL TEMPLATES!) -->
    <?php 
    $partial_path = __DIR__ . '/template-form.php';
    if (file_exists($partial_path)) {
        include $partial_path;
    } else {
        echo '<!-- Template form partial not found: ' . esc_html($partial_path) . ' -->';
    }
    ?>
</div>