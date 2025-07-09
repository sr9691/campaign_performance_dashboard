<?php
/**
 * Plugin Name:       Direct Reach
 * Plugin URI:        https://memomarketing.com/directreach
 * Description:       A custom dashboard for clients to view their directreach campaign performance and visitor data.
 * Version:           1.0.0
 * Author:            ANSA Solutions
 * Author URI:        https://ansa.solutions/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       directreach
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

