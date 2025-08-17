<?php
/**
 * Plugin Name:       DirectReach Reports
 * Plugin URI:        https://memomarketing.com/directreach/reports
 * Description:       A custom dashboard for clients to view their campaign performance and visitor data.
 * Version:           1.1.0
 * Author:            ANSA Solutions
 * Author URI:        https://ansa.solutions/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       reports
 * Domain Path:       /languages
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'CPD_DASHBOARD_VERSION', '1.1.0' ); // UPDATED VERSION
define( 'CPD_DASHBOARD_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CPD_DASHBOARD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require_once CPD_DASHBOARD_PLUGIN_DIR . 'includes/class-cpd-dashboard.php';

// Include the admin class as well
require_once CPD_DASHBOARD_PLUGIN_DIR . 'admin/class-cpd-admin.php';

// Include AI Intelligence classes
require_once CPD_DASHBOARD_PLUGIN_DIR . 'includes/class-cpd-intelligence.php';
require_once CPD_DASHBOARD_PLUGIN_DIR . 'admin/class-cpd-admin-intelligence-settings.php';

/**
 * Register activation and deactivation hooks.
 * This is the activation hook for the plugin, which will create the database tables and roles.
 */
function cpd_dashboard_activate() {
    require_once CPD_DASHBOARD_PLUGIN_DIR . 'includes/class-cpd-database.php';
    $cpd_database = new CPD_Database();
    $cpd_database->create_tables();
    
    // Handle database migrations for AI Intelligence features
    $cpd_database->migrate_database();

    // Register the custom client role on activation.
    cpd_dashboard_register_roles();

    // Add custom capabilities to existing roles
    cpd_dashboard_add_capabilities();

    // Schedule the daily CRM email event on activation
    require_once CPD_DASHBOARD_PLUGIN_DIR . 'includes/class-cpd-email-handler.php';
    CPD_Email_Handler::schedule_daily_crm_email();
    
    // Flush rewrite rules for hot list settings
    flush_rewrite_rules();
}

/**
 * Deactivation hook to remove custom roles and clean up.
 */
function cpd_dashboard_deactivate() {
    // Remove the custom client role upon deactivation.
    remove_role( 'client' );

    // Clear scheduled cron events on deactivation
    require_once CPD_DASHBOARD_PLUGIN_DIR . 'includes/class-cpd-email-handler.php';
    CPD_Email_Handler::unschedule_daily_crm_email();
    
    // Clear intelligence cleanup schedule
    wp_clear_scheduled_hook( 'cpd_intelligence_cleanup' );
    
    error_log( 'CPD Dashboard deactivated' );
}

register_activation_hook( __FILE__, 'cpd_dashboard_activate' );
register_deactivation_hook( __FILE__, 'cpd_dashboard_deactivate' );

// Handle plugin updates and migrations
add_action( 'plugins_loaded', 'cpd_dashboard_check_version' );

/**
 * Check if plugin version has changed and run migrations if needed
 */
function cpd_dashboard_check_version() {
    $installed_version = get_option( 'cpd_dashboard_version', '1.0.0' );
    
    if ( version_compare( $installed_version, CPD_DASHBOARD_VERSION, '<' ) ) {
        // Plugin has been updated, run migrations
        require_once CPD_DASHBOARD_PLUGIN_DIR . 'includes/class-cpd-database.php';
        $cpd_database = new CPD_Database();
        $cpd_database->migrate_database();
        
        // Update the stored version
        update_option( 'cpd_dashboard_version', CPD_DASHBOARD_VERSION );
        
        error_log( 'CPD Dashboard updated from ' . $installed_version . ' to ' . CPD_DASHBOARD_VERSION );
    }
}

/**
 * Registers the custom 'client' role with specific capabilities.
 * PRESERVED from v1.0.0 + NEW AI Intelligence capabilities
 */
function cpd_dashboard_register_roles() {
    add_role(
        'client',
        'Client',
        array(
            'read'                => true,   // Clients can read posts.
            'upload_files'        => false,  // Don't allow file uploads.
            'edit_posts'          => false,  // Don't allow editing posts.
            'cpd_view_dashboard'  => true,   // Custom capability for viewing dashboard (PRESERVED)
            // AI Intelligence capability
            'cpd_request_intelligence' => true, // Allow clients to request intelligence for their visitors
        )
    );
}

/**
 * Add custom capabilities to existing roles
 * PRESERVED from v1.0.0 + NEW AI Intelligence capabilities
 */
function cpd_dashboard_add_capabilities() {
    // Give administrators the dashboard capability (PRESERVED)
    $admin_role = get_role('administrator');
    if ($admin_role) {
        $admin_role->add_cap('cpd_view_dashboard');
        // AI Intelligence capabilities for admins
        $admin_role->add_cap('cpd_manage_intelligence');
        $admin_role->add_cap('cpd_configure_intelligence');
        $admin_role->add_cap('cpd_view_intelligence_stats');
        $admin_role->add_cap('cpd_manage_clients');
        $admin_role->add_cap('cpd_view_all_data');
        $admin_role->add_cap('cpd_export_data');
        $admin_role->add_cap('cpd_manage_users');
    }

    // Give the client role the dashboard capability (PRESERVED)
    $client_role = get_role('client');
    if ($client_role) {
        $client_role->add_cap('cpd_view_dashboard');
        // AI Intelligence and other capabilities for clients
        $client_role->add_cap('cpd_request_intelligence');
        $client_role->add_cap('cpd_view_own_data');
        $client_role->add_cap('cpd_export_own_data');
    }
}

/**
 * The main function responsible for initializing the plugin.
 * PRESERVED from v1.0.0 + NEW AI Intelligence initialization
 */
function cpd_dashboard_run() {
    $plugin = new CPD_Dashboard();
    $plugin->run();

    // Instantiate your admin class here (PRESERVED)
    $plugin_name = 'reports';
    $version = CPD_DASHBOARD_VERSION;
    $cpd_admin = new CPD_Admin( $plugin_name, $version );
    
    // Load and initialize the email handler hooks (PRESERVED)
    require_once CPD_DASHBOARD_PLUGIN_DIR . 'includes/class-cpd-email-handler.php';
    
    // Register the cron hook - THIS IS THE KEY ADDITION (PRESERVED)
    add_action( 'cpd_daily_crm_email_event', array( 'CPD_Email_Handler', 'daily_crm_email_cron_callback' ) );
    
    // Initialize AI Intelligence admin settings (only in admin)
    if ( is_admin() && class_exists( 'CPD_Admin_Intelligence_Settings' ) ) {
        new CPD_Admin_Intelligence_Settings();
    }
}

add_action( 'plugins_loaded', 'cpd_dashboard_run' );
/**
 * Add URL rewrite rules for Hot List Settings
 */
add_action( 'init', 'cpd_add_hot_list_rewrite_rules' );
add_filter( 'query_vars', 'cpd_add_hot_list_query_vars' );
add_action( 'template_redirect', 'cpd_handle_hot_list_settings_template' );

function cpd_add_hot_list_rewrite_rules() {
    // Add rewrite rule for hot-list-settings
    add_rewrite_rule(
        '^campaign-dashboard/hot-list-settings/?$',
        'index.php?cpd_hot_list_settings=1',
        'top'
    );
}

function cpd_add_hot_list_query_vars( $vars ) {
    $vars[] = 'cpd_hot_list_settings';
    return $vars;
}

function cpd_handle_hot_list_settings_template() {
    if ( get_query_var( 'cpd_hot_list_settings' ) ) {
        // Load the hot list settings page
        include CPD_DASHBOARD_PLUGIN_DIR . 'public/hot-list-settings.php';
        exit;
    }
}

/**
 * Plugin uninstall cleanup
 */
register_uninstall_hook( __FILE__, 'cpd_dashboard_uninstall' );

function cpd_dashboard_uninstall() {
    // Clean up options
    delete_option( 'cpd_dashboard_version' );
    delete_option( 'cpd_database_version' );
    
    // Clean up intelligence settings
    delete_option( 'cpd_intelligence_webhook_url' );
    delete_option( 'cpd_makecom_api_key' );
    delete_option( 'cpd_intelligence_rate_limit' );
    delete_option( 'cpd_intelligence_timeout' );
    delete_option( 'cpd_intelligence_auto_generate_crm' );
    delete_option( 'cpd_intelligence_processing_method' );
    delete_option( 'cpd_intelligence_batch_size' );
    delete_option( 'cpd_intelligence_crm_timeout' );
    delete_option( 'cpd_intelligence_default_enabled' );
    delete_option( 'cpd_intelligence_require_context' );
    
    // Clear scheduled events
    wp_clear_scheduled_hook( 'cpd_intelligence_cleanup' );
    
    // Note: We don't delete database tables on uninstall to preserve data
    // Tables should only be deleted if the admin explicitly chooses to do so
}

/**
 * Add admin notices for intelligence feature
 */
add_action( 'admin_notices', 'cpd_intelligence_admin_notices' );

function cpd_intelligence_admin_notices() {
    // Only show on CPD pages
    $screen = get_current_screen();
    if ( ! $screen || strpos( $screen->id, 'cpd' ) === false ) {
        return;
    }
    
    // Check if intelligence is configured
    if ( class_exists( 'CPD_Intelligence' ) ) {
        $intelligence = new CPD_Intelligence();
        
        if ( ! $intelligence->is_intelligence_configured() ) {
            echo '<div class="notice notice-warning is-dismissible">';
            echo '<p><strong>' . __( 'AI Intelligence Feature:', 'cpd-dashboard' ) . '</strong> ';
            echo sprintf( 
                __( 'Configure the intelligence settings to enable AI features. <a href="%s">Go to Intelligence Settings</a>', 'cpd-dashboard' ),
                admin_url( 'admin.php?page=cpd-intelligence-settings' )
            );
            echo '</p>';
            echo '</div>';
        }
    }
}

/**
 * Load plugin text domain for internationalization
 */
add_action( 'plugins_loaded', 'cpd_dashboard_load_textdomain' );

function cpd_dashboard_load_textdomain() {
    load_plugin_textdomain( 'cpd-dashboard', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}

/**
 * Enqueue intelligence-related scripts and styles
 */
add_action( 'admin_enqueue_scripts', 'cpd_enqueue_intelligence_assets' );

function cpd_enqueue_intelligence_assets( $hook ) {
    // Only load on CPD dashboard pages
    if ( strpos( $hook, 'cpd-dashboard' ) === false && strpos( $hook, 'cpd-intelligence' ) === false ) {
        return;
    }
    
    // Enqueue jQuery for AJAX functionality
    wp_enqueue_script( 'jquery' );
    
    // Add localization for AJAX
    wp_localize_script( 'jquery', 'cpd_ajax', array(
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce' => wp_create_nonce( 'cpd_dashboard_nonce' ),
        'strings' => array(
            'requesting_intelligence' => __( 'Requesting Intelligence...', 'cpd-dashboard' ),
            'intelligence_requested' => __( 'Intelligence Requested', 'cpd-dashboard' ),
            'intelligence_failed' => __( 'Intelligence Request Failed', 'cpd-dashboard' ),
            'intelligence_processing' => __( 'Processing...', 'cpd-dashboard' ),
            'intelligence_completed' => __( 'Intelligence Available', 'cpd-dashboard' ),
            'intelligence_error' => __( 'Error', 'cpd-dashboard' ),
        )
    ) );
}