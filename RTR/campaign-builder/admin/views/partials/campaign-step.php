<?php
/**
 * Campaign Configuration Step
 * 
 * Step 2 of 3 in Campaign Builder workflow
 * 
 * @package DirectReach_Campaign_Builder
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="campaign-step-container">
    <div class="step-header">
        <h2>Configure Campaign</h2>
        <p class="step-description">Create or select a campaign for <span class="selected-client-name"></span></p>
    </div>

    <!-- Loading State -->
    <div id="campaigns-loading" class="loading-state" style="display: none;">
        <div class="spinner"></div>
        <p>Loading campaigns...</p>
    </div>

    <!-- Error State -->
    <div id="campaigns-error" class="error-state" style="display: none;">
        <i class="fas fa-exclamation-circle"></i>
        <h3>Error Loading Campaigns</h3>
        <p class="error-message"></p>
        <button type="button" id="retry-load-campaigns" class="btn btn-primary">
            <i class="fas fa-redo"></i> Retry
        </button>
    </div>

    <!-- Campaign List (shows if client has campaigns) -->
    <div id="campaign-list-container" style="display: none;">
        <div class="campaigns-header">
            <h3>Existing Campaigns</h3>
            <button type="button" id="create-new-campaign-btn" class="btn btn-secondary">
                <i class="fas fa-plus"></i> Create New Campaign
            </button>
        </div>
        <div id="campaign-list" class="campaign-grid">
            <!-- Campaign cards rendered here by JavaScript -->
        </div>
    </div>

    <!-- Campaign Form (create/edit) -->
    <div id="campaign-form-container" style="display: none;">
        <form id="campaign-form" class="campaign-form">
            <div class="form-group">
                <label for="campaign_name">
                    Campaign Name <span class="required">*</span>
                </label>
                <input 
                    type="text" 
                    id="campaign_name" 
                    name="campaign_name" 
                    class="form-control"
                    placeholder="e.g., Summer Sale 2025"
                    required
                    maxlength="255"
                />
                <span class="field-hint">Internal name for this campaign</span>
            </div>

            <div class="form-group">
                <label for="utm_campaign">
                    UTM Campaign <span class="required">*</span>
                </label>
                <input 
                    type="text" 
                    id="utm_campaign" 
                    name="utm_campaign" 
                    class="form-control"
                    placeholder="e.g., summer-sale-2025"
                    required
                    maxlength="255"
                />
                <span class="field-hint">
                    Lowercase letters, numbers, hyphens and underscores only. 
                    Must be unique for this client.
                </span>
                <div class="utm-validation">
                    <span class="validation-message"></span>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="start_date">Start Date</label>
                    <input 
                        type="date" 
                        id="start_date" 
                        name="start_date" 
                        class="form-control"
                    />
                </div>

                <div class="form-group">
                    <label for="end_date">End Date</label>
                    <input 
                        type="date" 
                        id="end_date" 
                        name="end_date" 
                        class="form-control"
                    />
                </div>
            </div>

            <div class="form-group">
                <label for="campaign_description">Description</label>
                <textarea 
                    id="campaign_description" 
                    name="campaign_description" 
                    class="form-control"
                    rows="3"
                    placeholder="Optional campaign description..."
                    maxlength="1000"
                ></textarea>
            </div>

            <div class="form-actions">
                <button type="button" id="cancel-campaign-btn" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" id="save-campaign-btn" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Campaign
                </button>
            </div>
        </form>
    </div>

    <!-- Settings Preview -->
    <div id="settings-preview-container" class="settings-preview" style="display: none;">
        <h3>Campaign Settings</h3>
        <div class="settings-source-info">
            <i class="fas fa-info-circle"></i>
            <span id="settings-source-text">Using Global Defaults</span>
            <a href="#" id="customize-settings-link" class="customize-link" style="display: none;">
                Customize for this client
            </a>
        </div>

        <div class="settings-preview-content">
            <div class="settings-section">
                <h4>Room Thresholds</h4>
                <div id="thresholds-preview">
                    <p class="empty-state">No thresholds configured</p>
                </div>
            </div>

            <div class="settings-section">
                <h4>Scoring Rules</h4>
                <div id="scoring-preview">
                    <p class="empty-state">No scoring rules configured</p>
                </div>
            </div>
        </div>
    </div>
</div>