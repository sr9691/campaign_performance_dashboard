<?php
/**
 * Scoring Rules Page Content (Standalone - Global Configuration)
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
$rules_db = $system->get_scoring_rules_db();

// Get global rules for all rooms
$global_rules = [
    'problem' => $rules_db->get_global_rules('problem'),
    'solution' => $rules_db->get_global_rules('solution'),
    'offer' => $rules_db->get_global_rules('offer')
];

// Use page data passed from bootstrap (already includes rule counts)
// If not set, calculate them here as fallback
if (!isset($problem_rules_count)) {
    $problem_rules_count = 0;
}
if (!isset($solution_rules_count)) {
    $solution_rules_count = 0;
}
if (!isset($offer_rules_count)) {
    $offer_rules_count = 0;
}

// Get general statistics for clients with custom rules
$stats = $rules_db->get_statistics();
$clients_with_custom = isset($stats['clients_with_custom_rules']) ? $stats['clients_with_custom_rules'] : 0;

// Load industry config - go up from admin/views/ to scoring-system/includes/
require_once dirname(dirname(dirname(__FILE__))) . '/includes/industry-config.php';
$industries = rtr_get_industry_taxonomy();
?>

<!-- Header -->
<?php 
$args = [
    'page_badge' => 'Scoring Rules',
    'active_page' => 'dr-scoring-rules',
    'show_back_btn' => true
];
include __DIR__ . '/../../../campaign-builder/admin/views/partials/admin-header.php';
?>

<!-- Main Content -->
<main class="workflow-main">
    <div class="workflow-container">
        
        <div id="scoring-rules-app" class="scoring-rules-container">
            
            <!-- Page Header -->
            <div class="step-header">
                <h2>
                    <i class="fas fa-calculator"></i>
                    Global Scoring Rules
                </h2>
                <p class="step-description">
                    Configure how prospects earn points based on firmographics, engagement, and purchase signals.
                    These rules determine lead scores and room assignments.
                </p>
            </div>

            <!-- Statistics Summary -->
            <div class="stats-summary">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-building"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo esc_html($clients_with_custom); ?></div>
                        <div class="stat-label">Clients with Custom Rules</div>
                    </div>
                </div>
                
                <div class="stat-card problem">
                    <div class="stat-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo esc_html($problem_rules_count); ?></div>
                        <div class="stat-label">Problem Room Rules</div>
                    </div>
                </div>
                
                <div class="stat-card solution">
                    <div class="stat-icon">
                        <i class="fas fa-lightbulb"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo esc_html($solution_rules_count); ?></div>
                        <div class="stat-label">Solution Room Rules</div>
                    </div>
                </div>
                
                <div class="stat-card offer">
                    <div class="stat-icon">
                        <i class="fas fa-gift"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo esc_html($offer_rules_count); ?></div>
                        <div class="stat-label">Offer Room Rules</div>
                    </div>
                </div>
            </div>

            <!-- Room Tabs -->
            <div class="room-tabs-container">
                <div class="room-tabs">
                    <button class="room-tab active" data-room="problem">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span>Problem Room</span>
                        <small>Firmographics</small>
                    </button>
                    <button class="room-tab" data-room="solution">
                        <i class="fas fa-lightbulb"></i>
                        <span>Solution Room</span>
                        <small>Engagement</small>
                    </button>
                    <button class="room-tab" data-room="offer">
                        <i class="fas fa-gift"></i>
                        <span>Offer Room</span>
                        <small>Purchase Signals</small>
                    </button>
                </div>
            </div>

            <!-- Room Content Sections -->
            <div class="room-content-container">
                
                <!-- Problem Room -->
                <div class="room-content active" data-room-content="problem">
                    <div class="room-content-header">
                        <h3>Problem Room Rules</h3>
                        <p>Firmographic-based qualification rules that determine initial prospect eligibility</p>
                    </div>
                    
                    <form id="problem-rules-form" class="rules-form">
                        <input type="hidden" name="room_type" value="problem" />
                        
                        <div class="rules-section" id="problem-rules">
                            <!-- Rules will be rendered by JavaScript -->
                        </div>
                        
                        <!-- Validation Messages -->
                        <div class="validation-messages" id="problem-validation" style="display: none;">
                            <i class="fas fa-exclamation-circle"></i>
                            <span class="message-text"></span>
                        </div>
                        
                        <!-- Form Actions -->
                        <div class="form-actions">
                            <button type="button" class="btn btn-ghost" id="reset-problem-btn">
                                <i class="fas fa-undo"></i>
                                Reset to Defaults
                            </button>
                            <button type="submit" class="btn btn-primary btn-large">
                                <i class="fas fa-save"></i>
                                Save Problem Room Rules
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Solution Room -->
                <div class="room-content" data-room-content="solution" style="display: none;">
                    <div class="room-content-header">
                        <h3>Solution Room Rules</h3>
                        <p>Engagement tracking rules that measure prospect interest and activity</p>
                    </div>
                    
                    <form id="solution-rules-form" class="rules-form">
                        <input type="hidden" name="room_type" value="solution" />
                        
                        <div class="rules-section" id="solution-rules">
                            <!-- Rules will be rendered by JavaScript -->
                        </div>
                        
                        <!-- Validation Messages -->
                        <div class="validation-messages" id="solution-validation" style="display: none;">
                            <i class="fas fa-exclamation-circle"></i>
                            <span class="message-text"></span>
                        </div>
                        
                        <!-- Form Actions -->
                        <div class="form-actions">
                            <button type="button" class="btn btn-ghost" id="reset-solution-btn">
                                <i class="fas fa-undo"></i>
                                Reset to Defaults
                            </button>
                            <button type="submit" class="btn btn-primary btn-large">
                                <i class="fas fa-save"></i>
                                Save Solution Room Rules
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Offer Room -->
                <div class="room-content" data-room-content="offer" style="display: none;">
                    <div class="room-content-header">
                        <h3>Offer Room Rules</h3>
                        <p>Purchase signal rules that identify high-intent prospects ready for sales engagement</p>
                    </div>
                    
                    <form id="offer-rules-form" class="rules-form">
                        <input type="hidden" name="room_type" value="offer" />
                        
                        <div class="rules-section" id="offer-rules">
                            <!-- Rules will be rendered by JavaScript -->
                        </div>
                        
                        <!-- Validation Messages -->
                        <div class="validation-messages" id="offer-validation" style="display: none;">
                            <i class="fas fa-exclamation-circle"></i>
                            <span class="message-text"></span>
                        </div>
                        
                        <!-- Form Actions -->
                        <div class="form-actions">
                            <button type="button" class="btn btn-ghost" id="reset-offer-btn">
                                <i class="fas fa-undo"></i>
                                Reset to Defaults
                            </button>
                            <button type="submit" class="btn btn-primary btn-large">
                                <i class="fas fa-save"></i>
                                Save Offer Room Rules
                            </button>
                        </div>
                    </form>
                </div>
                
            </div>
            
        </div>
        
    </div>
</main>

<!-- Notification Container -->
<div class="notification-container"></div>

<!-- Industry Selector Modal -->
<div class="industry-modal" id="industry-modal" style="display: none;">
    <div class="modal-overlay"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h3>Select Industries</h3>
            <button type="button" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <div class="industry-search">
                <input type="text" id="industry-search-input" placeholder="Search industries..." />
                <i class="fas fa-search"></i>
            </div>
            <div class="industry-list" id="industry-list">
                <?php foreach ($industries as $category => $subcategories): ?>
                <div class="industry-category">
                    <div class="category-header">
                        <label class="category-checkbox">
                            <input type="checkbox" data-category="<?php echo esc_attr($category); ?>" />
                            <span class="category-name"><?php echo esc_html($category); ?></span>
                        </label>
                    </div>
                    <div class="subcategory-list">
                        <?php foreach ($subcategories as $subcategory): ?>
                        <label class="subcategory-checkbox">
                            <input type="checkbox" 
                                   data-value="<?php echo esc_attr($category . '|' . $subcategory); ?>" 
                                   data-category="<?php echo esc_attr($category); ?>" />
                            <span><?php echo esc_html($subcategory); ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" id="industry-cancel-btn">Cancel</button>
            <button type="button" class="btn btn-primary" id="industry-save-btn">
                <i class="fas fa-check"></i> Apply Selection
            </button>
        </div>
    </div>
</div>

<?php
// Localize script data
wp_localize_script('rtr-scoring-rules', 'rtrScoringConfig', [
    'nonce' => wp_create_nonce('rtr_scoring_nonce'),
    'apiUrl' => rest_url('directreach/v2/'),
    'globalRules' => $global_rules,
    'industries' => $industries,
    'strings' => [
        'saveSuccess' => __('Rules saved successfully', 'directreach'),
        'saveError' => __('Failed to save rules', 'directreach'),
        'validationError' => __('Please fix validation errors before saving', 'directreach'),
        'resetConfirm' => __('Reset rules to global defaults? This cannot be undone.', 'directreach'),
    ]
]);
?>