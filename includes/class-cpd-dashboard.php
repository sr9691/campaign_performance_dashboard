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
    }

    /**
     * Load the required dependencies for this plugin.
     */
    private function load_dependencies() {
        // The database class is loaded by the activation hook and the main plugin file.
        require_once CPD_DASHBOARD_PLUGIN_DIR . 'includes/class-cpd-database.php';
        // The admin menu and page handler.
        require_once CPD_DASHBOARD_PLUGIN_DIR . 'includes/class-cpd-admin.php';
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
        $plugin_admin = new CPD_Admin( $this->plugin_name, $this->version );

        // Enqueue admin-specific stylesheets and scripts.
        add_action( 'admin_enqueue_scripts', array( $plugin_admin, 'enqueue_styles' ) );
        add_action( 'admin_enqueue_scripts', array( $plugin_admin, 'enqueue_scripts' ) );

        // Add admin menus for dashboard and management pages.
        add_action( 'admin_menu', array( $plugin_admin, 'add_plugin_admin_menu' ) );
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
     * Run the loader to execute all of the hooks with WordPress.
     */
    public function run() {
        // Initialize REST API endpoints.
        $cpd_api = new CPD_API();
        add_action( 'rest_api_init', array( $cpd_api, 'register_routes' ) );
    }
}