<?php
/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used to
 * set up the plugin's functionality.
 *
 * @package CPD_Dashboard
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CPD_Dashboard {

    /**
     * The unique identifier of this plugin.
     *
     * @var string
     */
    protected $plugin_name;

    /**
     * The current version of the plugin.
     *
     * @var string
     */
    protected $version;

    /**
     * Define the core functionality of the plugin.
     */
    public function __construct() {
        $this->plugin_name = 'cpd-dashboard';
        $this->version = CPD_DASHBOARD_VERSION;

        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        $this->define_api_hooks(); // NEW: Define API hooks separately
    }

    /**
     * Load the required dependencies for this plugin.
     */
    private function load_dependencies() {
        // The database class is loaded by the activation hook and the main plugin file.
        require_once CPD_DASHBOARD_PLUGIN_DIR . 'includes/class-cpd-database.php';
        // The admin menu and page handler.
        require_once CPD_DASHBOARD_PLUGIN_DIR . 'admin/class-cpd-admin.php'; // Correct path to admin class
        // The public-facing dashboard display handler.
        require_once CPD_DASHBOARD_PLUGIN_DIR . 'includes/class-cpd-public.php';
        // The REST API handler for data ingestion.
        require_once CPD_DASHBOARD_PLUGIN_DIR . 'includes/class-cpd-api.php';
        // The email handler for CRM feeds.
        require_once CPD_DASHBOARD_PLUGIN_DIR . 'includes/class-cpd-email-handler.php';
        // The data provider class
        require_once CPD_DASHBOARD_PLUGIN_DIR . 'includes/class-cpd-data-provider.php';
    }

    /**
     * Register all of the hooks related to the admin area functionality.
     */
    private function define_admin_hooks() {
        // Instantiate CPD_Admin
        $plugin_admin = new CPD_Admin( $this->plugin_name, $this->version );

        // Enqueue admin-specific stylesheets and scripts.
        add_action( 'admin_enqueue_scripts', array( $plugin_admin, 'enqueue_styles' ) );
        add_action( 'admin_enqueue_scripts', array( $plugin_admin, 'enqueue_scripts' ) );

        // Add admin menus for dashboard and management pages.
        add_action( 'admin_menu', array( $plugin_admin, 'add_plugin_admin_menu' ) );

        // Handle dashboard redirect (hooked in CPD_Admin constructor now, removed duplicate)
        // add_action( 'admin_init', array( $plugin_admin, 'handle_dashboard_redirect' ) );

        // All other admin_actions (AJAX, post handlers, settings) are defined within CPD_Admin's constructor
        // as they are typically tied directly to its instantiation.
    }

    /**
     * Register all of the hooks related to the public-facing functionality.
     */
    private function define_public_hooks() {
        // Pass the plugin name to the public class
        $plugin_public = new CPD_Public( $this->plugin_name, $this->version );

        // Enqueue public-facing stylesheets and scripts.
        add_action( 'wp_enqueue_scripts', array( $plugin_public, 'enqueue_styles' ) );
        add_action( 'wp_enqueue_scripts', array( $plugin_public, 'enqueue_scripts' ) );

        // Add a shortcode to display the client dashboard.
        add_shortcode( 'campaign_dashboard', array( $plugin_public, 'display_dashboard' ) );

        // Set up the daily cron job for sending CRM emails.
        add_action( 'cpd_daily_email_event', array( 'CPD_Email_Handler', 'send_daily_crm_feed' ) );
        if ( ! wp_next_scheduled( 'cpd_daily_email_event' ) ) {
            wp_schedule_event( time(), 'daily', 'cpd_daily_email_event' );
        }

        // Add login redirect filter.
        add_filter( 'login_redirect', array( $plugin_public, 'login_redirect_by_role' ), 10, 3 );
    }

    /**
     * NEW: Register all of the hooks related to the REST API functionality.
     */
    private function define_api_hooks() {
        // Instantiate CPD_API
        $cpd_api = new CPD_API( $this->plugin_name ); // Pass plugin name to API class

        // Instantiate CPD_Admin temporarily to get its log_action method.
        // This avoids CPD_API having a direct dependency on the full CPD_Admin instance,
        // while still allowing it to use CPD_Admin's logging mechanism.
        // The admin_instance_for_logging is not hooked into the main plugin run loop.
        $admin_instance_for_logging = new CPD_Admin( $this->plugin_name, $this->version );
        $cpd_api->set_log_action_callback( array( $admin_instance_for_logging, 'log_action' ) );

        // Register REST API routes.
        add_action( 'rest_api_init', array( $cpd_api, 'register_routes' ) );
    }

    /**
     * Run the loader to execute all of the hooks with WordPress.
     */
    public function run() {
        // The define_admin_hooks, define_public_hooks, and define_api_hooks methods
        // already instantiate their respective classes and register all necessary hooks.
        // No additional code is needed here for running the hooks.
    }
}