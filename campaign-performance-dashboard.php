<?php
/**
 * Plugin Name:       Campaign Performance Dashboard
 * Plugin URI:        https://memomarketing.com/campaign-performance-dashboard
 * Description:       A custom dashboard for clients to view their campaign performance and visitor data.
 * Version:           1.0.0
 * Author:            ANSA Solutions
 * Author URI:        https://ansa.solutions/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       cpd-dashboard
 * Domain Path:       /languages
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'CPD_DASHBOARD_VERSION', '1.0.0' );
define( 'CPD_DASHBOARD_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CPD_DASHBOARD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require_once CPD_DASHBOARD_PLUGIN_DIR . 'includes/class-cpd-dashboard.php';

// Include the admin class as well
require_once CPD_DASHBOARD_PLUGIN_DIR . 'admin/class-cpd-admin.php';

/**
 * Register activation and deactivation hooks.
 * This is the activation hook for the plugin, which will create the database tables and roles.
 */
function cpd_dashboard_activate() {
    require_once CPD_DASHBOARD_PLUGIN_DIR . 'includes/class-cpd-database.php';
    $cpd_database = new CPD_Database();
    $cpd_database->create_tables();

    // Register the custom client role on activation.
    cpd_dashboard_register_roles();

    // Add custom capabilities to existing roles
    cpd_dashboard_add_capabilities();

    // Schedule the daily CRM email event on activation
    require_once CPD_DASHBOARD_PLUGIN_DIR . 'includes/class-cpd-email-handler.php';
    CPD_Email_Handler::schedule_daily_crm_email();
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
}

register_activation_hook( __FILE__, 'cpd_dashboard_activate' );
register_deactivation_hook( __FILE__, 'cpd_dashboard_deactivate' );

/**
 * Registers the custom 'client' role with specific capabilities.
 */
function cpd_dashboard_register_roles() {
    add_role(
        'client',
        'Client',
        array(
            'read'                => true,   // Clients can read posts.
            'upload_files'        => false,  // Don't allow file uploads.
            'edit_posts'          => false,  // Don't allow editing posts.
            'cpd_view_dashboard'  => true,   // Custom capability for viewing dashboard
        )
    );
}

function cpd_dashboard_add_capabilities() {
    // Give administrators the dashboard capability
    $admin_role = get_role('administrator');
    if ($admin_role) {
        $admin_role->add_cap('cpd_view_dashboard');
    }

    // Give the client role the dashboard capability (in case role already exists)
    $client_role = get_role('client');
    if ($client_role) {
        $client_role->add_cap('cpd_view_dashboard');
    }
}

/**
 * The main function responsible for initializing the plugin.
 */
function cpd_dashboard_run() {
    $plugin = new CPD_Dashboard();
    $plugin->run();

    // Instantiate your admin class here
    $plugin_name = 'cpd-dashboard';
    $version = CPD_DASHBOARD_VERSION;
    $cpd_admin = new CPD_Admin( $plugin_name, $version );
    
    // Load and initialize the email handler hooks
    require_once CPD_DASHBOARD_PLUGIN_DIR . 'includes/class-cpd-email-handler.php';
    
    // Register the cron hook - THIS IS THE KEY ADDITION
    add_action( 'cpd_daily_crm_email_event', array( 'CPD_Email_Handler', 'daily_crm_email_cron_callback' ) );
}
add_action( 'plugins_loaded', 'cpd_dashboard_run' );

/**
 * DEBUG FUNCTIONS - Remove these after testing
 * Visit /wp-admin/?debug_cron=1 to see cron status
 * Visit /wp-admin/?test_cron=1 to manually trigger cron
 */
function debug_cpd_cron() {
    if ( current_user_can( 'manage_options' ) && isset( $_GET['debug_cron'] ) ) {
        $next_run = wp_next_scheduled( 'cpd_daily_crm_email_event' );
        echo '<div style="background: #fff; padding: 20px; margin: 20px; border: 1px solid #ccc;">';
        echo '<h3>CPD Cron Debug</h3>';
        echo '<strong>Next scheduled run:</strong> ' . ( $next_run ? date( 'Y-m-d H:i:s', $next_run ) : '<span style="color:red;">NOT SCHEDULED!</span>' ) . '<br>';
        echo '<strong>Current time:</strong> ' . current_time( 'Y-m-d H:i:s' ) . '<br>';
        echo '<strong>Scheduled hour setting:</strong> ' . get_option( 'cpd_crm_email_schedule_hour', '09' ) . '<br>';
        echo '<strong>Notifications enabled:</strong> ' . get_option( 'enable_notifications', 'not_set' ) . '<br>';
        echo '<strong>Webhook URL:</strong> ' . ( get_option( 'cpd_webhook_url', '' ) ? 'Configured' : '<span style="color:red;">NOT CONFIGURED</span>' ) . '<br>';
        echo '<strong>API Key:</strong> ' . ( get_option( 'cpd_makecom_api_key', '' ) ? 'Configured' : '<span style="color:red;">NOT CONFIGURED</span>' ) . '<br>';
        echo '</div>';
    }
}
add_action( 'admin_notices', 'debug_cpd_cron' );

function test_cpd_cron_manual() {
    if ( current_user_can( 'manage_options' ) && isset( $_GET['test_cron'] ) ) {
        echo '<div style="background: #fff; padding: 20px; margin: 20px; border: 1px solid #ccc;">';
        echo '<h3>Manual Cron Test</h3>';
        echo 'Executing cron callback...<br>';
        
        // Load the email handler if not already loaded
        require_once CPD_DASHBOARD_PLUGIN_DIR . 'includes/class-cpd-email-handler.php';
        CPD_Email_Handler::daily_crm_email_cron_callback();
        
        echo '<strong style="color:green;">Cron callback executed!</strong> Check your action logs for results.';
        echo '</div>';
    }
}
add_action( 'admin_notices', 'test_cpd_cron_manual' );