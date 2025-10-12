<?php
/**
 * Client Selection Step
 *
 * First step in Campaign Builder workflow - select or create client
 *
 * @package DirectReach_Campaign_Builder
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="client-step-container">
    <!-- Search and Actions Bar -->
    <div class="client-step-header">
        <div class="search-container">
            <input 
                type="text" 
                id="client-search" 
                class="client-search-input" 
                placeholder="Search clients by name or account ID..."
                autocomplete="off"
            />
            <span class="search-icon">
                <i class="fas fa-search"></i>
            </span>
        </div>
        
        <button type="button" id="toggle-create-client" class="btn btn-primary">
            <i class="fas fa-plus"></i>
            Create New Client
        </button>
    </div>
    
    <!-- Create Client Form (Hidden by default) -->
    <div id="create-client-form" class="create-client-form" style="display: none;">
        <div class="form-card">
            <div class="form-card-header">
                <h3>Create New Client</h3>
                <button type="button" id="cancel-create-client" class="btn-icon">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form id="new-client-form">
                <div class="form-row">
                    <div class="form-group">
                        <label for="new-client-name">
                            Client Name <span class="required">*</span>
                        </label>
                        <input 
                            type="text" 
                            id="new-client-name" 
                            name="clientName" 
                            class="form-control"
                            required
                        />
                    </div>
                    
                    <div class="form-group">
                        <label for="new-account-id">
                            Account ID <span class="required">*</span>
                        </label>
                        <input 
                            type="text" 
                            id="new-account-id" 
                            name="accountId" 
                            class="form-control"
                            required
                        />
                        <small class="form-help">Unique identifier for this client</small>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="new-logo-url">Logo URL</label>
                        <input 
                            type="url" 
                            id="new-logo-url" 
                            name="logoUrl" 
                            class="form-control"
                            placeholder="https://..."
                        />
                    </div>
                    
                    <div class="form-group">
                        <label for="new-webpage-url">Website URL</label>
                        <input 
                            type="url" 
                            id="new-webpage-url" 
                            name="webpageUrl" 
                            class="form-control"
                            placeholder="https://..."
                        />
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="new-crm-email">CRM Feed Email</label>
                    <input 
                        type="email" 
                        id="new-crm-email" 
                        name="crmEmail" 
                        class="form-control"
                    />
                    <small class="form-help">Email address for CRM integration</small>
                </div>
                
                <div class="form-actions">
                    <button type="button" id="cancel-create-client-bottom" class="btn btn-secondary">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-plus"></i>
                        Create Client
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Loading State -->
    <div id="clients-loading" class="loading-state">
        <div class="spinner"></div>
        <p>Loading clients...</p>
    </div>
    
    <!-- Empty State -->
    <div id="clients-empty" class="empty-state" style="display: none;">
        <i class="fas fa-building"></i>
        <h3>No Clients Found</h3>
        <p>Create your first client to get started.</p>
    </div>
    
    <!-- Client List -->
    <div id="clients-list" class="clients-list" style="display: none;">
        <!-- Client cards will be rendered here by JavaScript -->
    </div>
    
    <!-- Error State -->
    <div id="clients-error" class="error-state" style="display: none;">
        <i class="fas fa-exclamation-triangle"></i>
        <h3>Error Loading Clients</h3>
        <p id="error-message"></p>
        <button type="button" id="retry-load-clients" class="btn btn-primary">
            <i class="fas fa-redo"></i>
            Retry
        </button>
    </div>
</div>