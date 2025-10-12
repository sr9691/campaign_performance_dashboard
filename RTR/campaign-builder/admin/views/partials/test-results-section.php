<?php
/**
 * Test Results Section Partial
 * 
 * @package DirectReach_Campaign_Builder
 * @since 2.5.0
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<!-- Test Results Section -->
<div id="test-results-section" class="test-results-section" style="display: none;">
    <div class="test-header">
        <h3>
            <i class="fas fa-vial"></i>
            Test Prompt Preview
        </h3>
    </div>

    <div id="test-results-content">
        <!-- Assembled Prompt Preview -->
        <div class="test-result-box">
            <div class="test-result-header">
                <h4>
                    <i class="fas fa-code"></i>
                    Assembled Prompt
                </h4>
                <!-- ADD THIS BUTTON -->
                <button type="button" class="btn btn-primary" id="generate-test-email-btn">
                    <i class="fas fa-magic"></i> Generate Test Email
                </button>
            </div>
            <div id="assembled-prompt-preview"></div>
        </div>

        <!-- Sample Email Preview (if test generation enabled) -->
        <div id="generated-email-section" style="display: none;">
            <div class="test-result-box">
                <div class="test-result-header">
                    <h4>
                        <i class="fas fa-envelope"></i>
                        Generated Test Email
                    </h4>
                    <button type="button" class="btn btn-secondary btn-sm" id="regenerate-email-btn">
                        <i class="fas fa-redo"></i> Regenerate
                    </button>
                </div>
                <div id="generated-email-output"></div>
                <div id="generation-stats" class="generation-stats"></div>
            </div>
        </div>
    </div>
</div>