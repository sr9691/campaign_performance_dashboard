<?php
/**
 * Content Links Step View
 *
 * Step 3 in the Campaign Builder workflow.
 * Manages content links for each room with drag-and-drop reordering.
 *
 * @package DirectReach_Campaign_Builder
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="content-links-step-container">
    <!-- Step Header -->
    <div class="step-header">
        <h2>
            <i class="fas fa-link"></i>
            Add Content Links
        </h2>
        <p class="step-description">
            Add content resources that will be shared with prospects in each room.
            The AI will intelligently select the most relevant link for each email.
        </p>
    </div>

    <!-- Loading State -->
    <div id="links-loading" class="loading-state" style="display:none;">
        <div class="spinner"></div>
        <p>Loading content links...</p>
    </div>

    <!-- Error State -->
    <div id="links-error" class="error-state" style="display:none;">
        <i class="fas fa-exclamation-triangle"></i>
        <h3>Failed to Load Links</h3>
        <p class="error-message">An error occurred</p>
        <button class="btn btn-primary" id="retry-load-links">
            <i class="fas fa-redo"></i> Retry
        </button>
    </div>

    <!-- Room Tabs -->
    <div class="room-tabs">
        <button class="room-tab active" data-room="problem">
            <i class="fas fa-question-circle"></i>
            <span class="tab-label">Problem Room</span>
            <span class="tab-count">0</span>
        </button>
        <button class="room-tab" data-room="solution">
            <i class="fas fa-lightbulb"></i>
            <span class="tab-label">Solution Room</span>
            <span class="tab-count">0</span>
        </button>
        <button class="room-tab" data-room="offer">
            <i class="fas fa-handshake"></i>
            <span class="tab-label">Offer Room</span>
            <span class="tab-count">0</span>
        </button>
    </div>

    <!-- Links List View -->
    <div id="links-list-view">
        <!-- Problem Room -->
        <div class="link-list-container active" data-room="problem">
            <!-- Rendered by JavaScript -->
        </div>

        <!-- Solution Room -->
        <div class="link-list-container" data-room="solution">
            <!-- Rendered by JavaScript -->
        </div>

        <!-- Offer Room -->
        <div class="link-list-container" data-room="offer">
            <!-- Rendered by JavaScript -->
        </div>
    </div>

    <!-- Link Form Container -->
    <div id="link-form-container" style="display:none;">
        <button class="btn-back-to-list">
            <i class="fas fa-arrow-left"></i> Back to Links
        </button>

        <div class="link-form-header">
            <h3>
                <i class="fas fa-link"></i>
                <span id="form-title">Add Content Link</span>
            </h3>
        </div>

        <form id="content-link-form" class="content-link-form">
            <!-- Room Selection -->
            <div class="form-group">
                <label for="room_type">
                    Room <span class="required">*</span>
                </label>
                <select id="room_type" name="room_type" class="form-control" required>
                    <option value="problem">Problem Room</option>
                    <option value="solution">Solution Room</option>
                    <option value="offer">Offer Room</option>
                </select>
                <span class="field-hint">
                    Select which room this content link belongs to
                </span>
            </div>

            <!-- Link Title -->
            <div class="form-group">
                <label for="link_title">
                    Link Title <span class="required">*</span>
                </label>
                <input 
                    type="text" 
                    id="link_title" 
                    name="link_title" 
                    class="form-control"
                    placeholder="e.g., Complete Guide to Marketing ROI"
                    maxlength="255"
                    required
                />
                <span class="field-hint">
                    A descriptive title for the content (max 255 characters)
                </span>
            </div>

            <!-- Link URL -->
            <div class="form-group">
                <label for="link_url">
                    URL <span class="required">*</span>
                </label>
                <input 
                    type="url" 
                    id="link_url" 
                    name="link_url" 
                    class="form-control"
                    placeholder="https://example.com/blog/marketing-roi"
                    required
                />
                <span class="field-hint">
                    Full URL to the content resource (must be valid HTTP/HTTPS URL)
                </span>
            </div>

            <!-- URL Summary (for AI) -->
            <div class="form-group">
                <label for="url_summary">
                    URL Summary (for AI) <span class="required">*</span>
                </label>
                <textarea 
                    id="url_summary" 
                    name="url_summary" 
                    class="form-control"
                    rows="4"
                    placeholder="Describe what this content is about and what problems it solves..."
                    required
                ></textarea>
                <span class="field-hint">
                    <i class="fas fa-info-circle"></i>
                    This helps the AI select the most relevant link for each prospect.
                    Be specific about topics covered, problems addressed, and key takeaways.
                    Example: "This article explains common marketing challenges including low conversion rates, 
                    attribution issues, and budget optimization. It provides practical frameworks for improving ROI."
                </span>
            </div>

            <!-- Link Description (Optional) -->
            <div class="form-group">
                <label for="link_description">
                    Description <span class="optional-badge">Optional</span>
                </label>
                <textarea 
                    id="link_description" 
                    name="link_description" 
                    class="form-control"
                    rows="3"
                    placeholder="Additional notes about this content..."
                ></textarea>
                <span class="field-hint">
                    Optional internal notes about this link (not used by AI)
                </span>
            </div>

            <!-- Active Status -->
            <div class="form-group">
                <label class="checkbox-label">
                    <input 
                        type="checkbox" 
                        id="is_active" 
                        name="is_active"
                        checked
                    />
                    <span>Active (AI can select this link for emails)</span>
                </label>
                <span class="field-hint">
                    Inactive links are not considered by the AI when generating emails
                </span>
            </div>

            <!-- Form Actions -->
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" id="cancel-link-btn">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="btn btn-primary" id="save-link-btn">
                    <i class="fas fa-save"></i> Save Link
                </button>
            </div>
        </form>
    </div>
</div>