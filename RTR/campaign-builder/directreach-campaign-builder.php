<?php
/**
 * Plugin Name: DirectReach Campaign Builder
 * Description: Campaign creation and management for DirectReach v2 Premium
 * Version: 2.0.0
 * Author: DirectReach
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define constants ONLY ONCE with type safety
if (!defined('DR_CB_VERSION')) {
    define('DR_CB_VERSION', '2.0.0');
}

if (!defined('DR_CB_PLUGIN_DIR')) {
    define('DR_CB_PLUGIN_DIR', plugin_dir_path(__FILE__));
}

if (!defined('DR_CB_PLUGIN_URL')) {
    define('DR_CB_PLUGIN_URL', plugin_dir_url(__FILE__));
}

/**
 * Initialize Campaign Builder
 * This is called by the main plugin at the appropriate time
 */
function dr_campaign_builder_init() {
    // Prevent double initialization
    if (defined('DR_CB_INITIALIZED')) {
        return;
    }
    define('DR_CB_INITIALIZED', true);
    
    // Verify constants are strings (PHP 8.1+ compatibility)
    if (!is_string(DR_CB_PLUGIN_DIR) || !is_string(DR_CB_PLUGIN_URL)) {
        error_log('DR_CB Bootstrap: ERROR - Plugin constants are invalid types');
        return;
    }
    
    // Check if class file exists
    $class_file = DR_CB_PLUGIN_DIR . 'includes/class-campaign-builder.php';
    
    if (!file_exists($class_file)) {
        error_log('DR_CB Bootstrap: ERROR - Class file not found: ' . $class_file);
        return;
    }
    
    // Load the core Campaign Builder class
    require_once $class_file;
    
    // Verify class was loaded
    if (!class_exists('DR_Campaign_Builder')) {
        error_log('DR_CB Bootstrap: ERROR - Class does not exist after require!');
        return;
    }
    
    // Load Global Templates Admin (if in admin context)
    if (is_admin()) {
        $admin_file = DR_CB_PLUGIN_DIR . 'includes/admin/class-global-templates-admin.php';
        if (file_exists($admin_file)) {
            require_once $admin_file;
        }
    }

    if (is_admin()) {
        // Load Scoring System
        $scoring_system_file = DR_CB_PLUGIN_DIR . '../scoring-system/directreach-scoring-system.php';
        if (file_exists($scoring_system_file)) {
            require_once $scoring_system_file;
        } else {
            error_log('DR_CB Bootstrap: ERROR - Scoring system file not found: ' . $scoring_system_file);
        }
    }

    // Initialize the plugin (singleton pattern)
    DR_Campaign_Builder::get_instance();

}

// NOTE: This file is loaded by the main plugin, which calls dr_campaign_builder_init()
// DO NOT add any hooks here - the main plugin controls timing