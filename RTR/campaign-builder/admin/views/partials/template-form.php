<?php
/**
 * Template Form Partial (Shared between Campaign and Global)
 * 
 * @package DirectReach_Campaign_Builder
 * @since 2.5.0
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<!-- Template Form (Structured Format) -->
<div id="template-form-container" class="template-form-container" style="display: none;">
    
    <!-- Form Header with Back Button -->
    <div class="template-form-header">
        <button type="button" class="btn btn-text btn-back-to-list">
            <i class="fas fa-arrow-left"></i> Back to Templates List
        </button>
    </div>
    
    <div id="global-template-selector" class="global-template-selector" style="display: none;">
        <!-- Will be populated by JavaScript -->
    </div>

    <form id="template-form" class="template-form-structured">
        
        <!-- Basic Info Section -->
        <div class="form-section form-section-basic">
            <div class="form-row">
                <div class="form-group form-group-flex-2">
                    <label for="template_name">
                        Template Name <span class="required">*</span>
                    </label>
                    <input 
                        type="text" 
                        id="template_name" 
                        name="template_name" 
                        class="form-control"
                        placeholder="Pain Discovery - Initial"
                        required
                        maxlength="255"
                    >
                </div>
                <div class="form-group">
                    <label for="room_type">Room Type</label>
                    <select id="room_type" name="room_type" class="form-control" disabled>
                        <option value="problem">Problem</option>
                        <option value="solution">Solution</option>
                        <option value="offer">Offer</option>
                    </select>
                </div>
                <div class="form-group form-group-narrow">
                    <label for="template_order">Order</label>
                    <input 
                        type="number" 
                        id="template_order" 
                        name="template_order" 
                        class="form-control"
                        min="0"
                        max="5"
                        value="1"
                    >
                </div>
            </div>
        </div>

        <!-- ALL 7 PROMPT SECTIONS (same as before) -->
        <?php include dirname(__FILE__) . '/prompt-sections.php'; ?>

        <!-- Form Actions -->
        <div class="form-actions">
            <button type="button" class="btn btn-secondary" id="test-prompt-btn">
                <i class="fas fa-flask"></i> Test Prompt
            </button>
            <div class="actions-spacer"></div>
            <button type="button" class="btn btn-secondary btn-cancel-form">
                <i class="fas fa-times"></i> Cancel
            </button>
            <button type="submit" class="btn btn-primary save-template-btn">
                <i class="fas fa-save"></i> Save Template
            </button>
        </div>
        
    </form>

    <!-- Test Results Section -->
    <?php include dirname(__FILE__) . '/test-results-section.php'; ?>

</div>