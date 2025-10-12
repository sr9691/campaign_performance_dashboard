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
     * Singleton instance
     */
    private static $instance = null;

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
     * Get singleton instance
     * 
     * @return CPD_Dashboard Singleton instance
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Define the core functionality of the plugin.
     */
    public function __construct() {
        $this->plugin_name = 'cpd-dashboard';
        $this->version = CPD_DASHBOARD_VERSION;

        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        $this->define_api_hooks();

        // Add hot list rewrite rules
        add_action( 'init', array( $this, 'add_hot_list_rewrite_rules' ) );
        add_filter( 'query_vars', array( $this, 'add_hot_list_query_vars' ) );
        add_action( 'template_redirect', array( $this, 'handle_hot_list_settings_template' ) );
    }

    /**
     * Load the required dependencies for this plugin.
     */
    private function load_dependencies() {
        // The database class is loaded by the activation hook and the main plugin file.
        require_once CPD_DASHBOARD_PLUGIN_DIR . 'includes/class-cpd-database.php';
        // The admin menu and page handler.
        require_once CPD_DASHBOARD_PLUGIN_DIR . 'admin/class-cpd-admin.php';
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
     * Register all of the hooks related to the REST API functionality.
     */
    private function define_api_hooks() {
        // Instantiate CPD_API
        $cpd_api = new CPD_API( $this->plugin_name );

        // Instantiate CPD_Admin temporarily to get its log_action method.
        $admin_instance_for_logging = new CPD_Admin( $this->plugin_name, $this->version );
        $cpd_api->set_log_action_callback( array( $admin_instance_for_logging, 'log_action' ) );

        // Register REST API routes.
        add_action( 'rest_api_init', array( $cpd_api, 'register_routes' ) );
    }

    /**
     * Add rewrite rules for hot list settings page
     */
    public function add_hot_list_rewrite_rules() {
        // Get the dashboard URL base from settings
        $dashboard_url = get_option( 'cpd_client_dashboard_url', '' );
        
        if ( $dashboard_url ) {
            // Extract the path from the full URL
            $parsed_url = parse_url( $dashboard_url );
            $path = trim( $parsed_url['path'], '/' );
            
            if ( $path ) {
                // Add rewrite rule for hot-list-settings
                add_rewrite_rule(
                    '^' . $path . '/hot-list-settings/?$',
                    'index.php?cpd_hot_list_settings=1',
                    'top'
                );
            }
        }
    }

    /**
     * Add query vars for hot list settings
     */
    public function add_hot_list_query_vars( $vars ) {
        $vars[] = 'cpd_hot_list_settings';
        return $vars;
    }

    /**
     * Handle hot list settings page template
     */
    public function handle_hot_list_settings_template() {
        if ( get_query_var( 'cpd_hot_list_settings' ) ) {
            // Load the hot list settings page
            include CPD_DASHBOARD_PLUGIN_DIR . 'public/hot-list-settings.php';
            exit;
        }
    }

    // ========================================
    // V2 PREMIUM ACCESS CONTROL WRAPPER METHODS
    // ========================================
    
    /**
     * These methods delegate to CPD_Access_Control class
     * for consistent tier-based access management
     */

    /**
     * Check if current user has v1 access
     *
     * @return bool
     */
    public function has_v1_access() {
        return CPD_Access_Control::has_v1_access( get_current_user_id() );
    }

    /**
     * Check if current user has v2/RTR access
     *
     * @return bool
     */
    public function has_rtr_access() {
        return CPD_Access_Control::has_v2_access( get_current_user_id() );
    }

    /**
     * Get current user's subscription tier
     *
     * @return string|null 'basic', 'premium', or null
     */
    public function get_user_subscription_tier() {
        return CPD_Access_Control::get_user_tier( get_current_user_id() );
    }

    /**
     * Get current client ID for logged-in user
     *
     * @return string|null Account ID or null
     */
    public function get_current_client_id() {
        return CPD_Access_Control::get_user_account_id( get_current_user_id() );
    }

    /**
     * Get subscription tier for current client
     * Alias for get_user_subscription_tier() for backward compatibility
     *
     * @return string|null 'basic', 'premium', or null
     */
    public function get_client_subscription_tier() {
        return $this->get_user_subscription_tier();
    }

    /**
     * Check if RTR is enabled for current client
     * Equivalent to checking v2 access
     *
     * @return bool
     */
    public function is_rtr_enabled() {
        return $this->has_rtr_access();
    }

    /**
     * Check if current user is admin
     *
     * @return bool
     */
    public function is_current_user_admin() {
        return CPD_Access_Control::is_admin_user( get_current_user_id() );
    }

    /**
     * Check if current user's subscription is expired
     *
     * @return bool
     */
    public function is_user_subscription_expired() {
        return CPD_Access_Control::is_subscription_expired( get_current_user_id() );
    }

    /**
     * Check if subscription is expired (alias)
     *
     * @return bool
     */
    public function is_subscription_expired() {
        return $this->is_user_subscription_expired();
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

/**
 * Show tier-appropriate admin notices
 * This function runs outside the class to properly hook into WordPress
 */
function cpd_show_tier_notices() {
    $screen = get_current_screen();
    if ( ! $screen ) {
        return;
    }
    
    $current_user_id = get_current_user_id();
    
    // Skip for admins
    if ( CPD_Access_Control::is_admin_user( $current_user_id ) ) {
        return;
    }
    
    // Premium required notice (from URL protection redirect)
    if ( isset( $_GET['premium_required'] ) && $_GET['premium_required'] === '1' ) {
        ?>
        <div class="notice notice-error is-dismissible">
            <h3><?php esc_html_e( 'Premium Feature Required', 'cpd' ); ?></h3>
            <p><?php esc_html_e( 'This feature requires a premium subscription. Please contact your administrator to upgrade your account.', 'cpd' ); ?></p>
        </div>
        <?php
    }
    
    // V1 not available for premium users
    if ( isset( $_GET['v1_not_available'] ) && $_GET['v1_not_available'] === '1' ) {
        ?>
        <div class="notice notice-info is-dismissible">
            <h3><?php esc_html_e( 'You Have Premium Access', 'cpd' ); ?></h3>
            <p><?php esc_html_e( 'Your account has been upgraded to premium. Please use the Reading the Room and Campaign Builder features.', 'cpd' ); ?></p>
        </div>
        <?php
    }
    
    // Upgrade prompt for basic users on v1 dashboard
    if ( strpos( $screen->id, 'cpd-dashboard' ) !== false && 
         CPD_Access_Control::has_v1_access( $current_user_id ) ) {
        ?>
        <div class="notice notice-info is-dismissible">
            <h3><?php esc_html_e( 'Upgrade to Premium', 'cpd' ); ?></h3>
            <p><?php esc_html_e( 'Unlock Reading the Room and Campaign Builder features with a premium subscription.', 'cpd' ); ?></p>
            <p>
                <strong><?php esc_html_e( 'Premium features include:', 'cpd' ); ?></strong>
            </p>
            <ul style="list-style: disc; margin-left: 20px;">
                <li><?php esc_html_e( 'Automatic prospect qualification', 'cpd' ); ?></li>
                <li><?php esc_html_e( '3-stage nurture workflow (Problem → Solution → Offer)', 'cpd' ); ?></li>
                <li><?php esc_html_e( 'Email template system', 'cpd' ); ?></li>
                <li><?php esc_html_e( 'Real-time engagement tracking', 'cpd' ); ?></li>
                <li><?php esc_html_e( 'Advanced analytics', 'cpd' ); ?></li>
            </ul>
        </div>
        <?php
    }
}
add_action( 'admin_notices', 'cpd_show_tier_notices' );