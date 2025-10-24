<?php
/**
 * Reading the Room Dashboard Bootstrap
 * 
 * Handles initialization, menu registration, and asset loading for RTR Dashboard
 * 
 * @package DirectReach
 * @subpackage ReadingTheRoom
 * @since 1.0.0
 */

namespace DirectReach\ReadingTheRoom;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ReadingRoomDashboard {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Database manager
     */
    private $database;
    
    /**
     * REST controllers
     */
    private $rest_controller;
    private $jobs_controller;
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    /**
     * Load required files
     */
    private function load_dependencies() {
        $base_path = dirname(__FILE__);
        
        // Database
        require_once $base_path . '/includes/class-reading-room-database.php';
        
        // API Controllers
        require_once $base_path . '/includes/api/class-reading-room-controller.php';
        require_once $base_path . '/includes/api/class-jobs-controller.php';
        
        
        // Phase 3: Campaign Attribution Classes
        require_once $base_path . '/includes/class-campaign-matcher.php';
        require_once $base_path . '/includes/class-visitor-campaign-manager.php';
        
        // Initialize
        $this->database = new Reading_Room_Database();
        $this->rest_controller = new API\Reading_Room_Controller();
        $this->jobs_controller = new API\Jobs_Controller();
        error_log('RTR: Dependencies loaded');
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Admin menu
        add_action('admin_menu', array($this, 'register_menu'));
        
        // REST API
        add_action('rest_api_init', array($this, 'register_rest_routes'), 10);
        
        // Custom page rendering
        add_action('admin_init', array($this, 'maybe_render_custom_page'), 10);
        
        // Assets
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }
    
    /**
     * Register admin menu
     */
    public function register_menu() {
        add_menu_page(
            'Reading the Room',
            'Reading the Room',
            'manage_options',
            'dr-reading-room',
            array($this, 'render_page_fallback'),
            'dashicons-visibility',
            28
        );
    }
    
    /**
     * Fallback render (should be intercepted)
     */
    public function render_page_fallback() {
        echo '<div class="wrap"><h1>Reading the Room Dashboard</h1></div>';
    }
    
    /**
     * Intercept page rendering for custom HTML
     */
    public function maybe_render_custom_page() {
        if (!isset($_GET['page']) || $_GET['page'] !== 'dr-reading-room') {
            return;
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        // Check premium access
        if (!$this->check_premium_access()) {
            $this->render_upgrade_notice();
            exit;
        }
        
        // Enqueue assets before rendering
        $this->enqueue_custom_assets();
        
        // Render custom page
        require_once dirname(__FILE__) . '/admin/views/reading-room-dashboard.php';
        exit;
    }
    
    /**
     * Check if user has premium access
     */
    private function check_premium_access() {
        global $wpdb;
        
        $user_id = get_current_user_id();
        
        // Admins always have access
        if (current_user_can('administrator')) {
            return true;
        }
        
        // Check if user is associated with premium client
        $client = $wpdb->get_row($wpdb->prepare(
            "SELECT subscription_tier, rtr_enabled 
             FROM {$wpdb->prefix}cpd_clients 
             WHERE id IN (
                 SELECT client_id FROM {$wpdb->prefix}cpd_client_users 
                 WHERE user_id = %d
             ) 
             AND subscription_tier = 'premium' 
             AND rtr_enabled = 1
             LIMIT 1",
            $user_id
        ));
        
        return !empty($client);
    }
    
    /**
     * Render upgrade notice for non-premium users
     */
    private function render_upgrade_notice() {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Reading the Room - Premium Required</title>
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f0f0f1; }
                .upgrade-notice { max-width: 600px; margin: 100px auto; background: white; padding: 40px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); text-align: center; }
                .upgrade-notice h1 { color: #2c435d; margin-bottom: 20px; }
                .upgrade-notice p { color: #666; line-height: 1.6; margin-bottom: 30px; }
                .upgrade-btn { display: inline-block; padding: 12px 30px; background: #4294cc; color: white; text-decoration: none; border-radius: 6px; font-weight: 600; }
                .upgrade-btn:hover { background: #357abd; }
            </style>
        </head>
        <body>
            <div class="upgrade-notice">
                <h1>🚀 Premium Feature</h1>
                <p>Reading the Room Dashboard is a premium feature that requires an active DirectReach Premium subscription.</p>
                <p>Upgrade to access:</p>
                <ul style="text-align: left; display: inline-block; margin-bottom: 30px;">
                    <li>AI-powered email generation</li>
                    <li>Multi-room prospect management</li>
                    <li>Advanced engagement tracking</li>
                    <li>Campaign analytics</li>
                </ul>
                <br>
            </div>
        </body>
        </html>
        <?php
    }
    
    /**
     * Enqueue custom assets for custom page
     */
    private function enqueue_custom_assets() {
        $base_url = plugins_url('', __FILE__);
        $version = '1.0.0';
        
        // CSS Files (in order)
        wp_enqueue_style('rtr-variables', $base_url . '/admin/css/variables.css', array(), $version);
        wp_enqueue_style('rtr-base', $base_url . '/admin/css/base.css', array('rtr-variables'), $version);
        wp_enqueue_style('rtr-header', $base_url . '/admin/css/header.css', array('rtr-base'), $version);
        wp_enqueue_style('rtr-room-cards', $base_url . '/admin/css/room-cards.css', array('rtr-base'), $version);
        wp_enqueue_style('rtr-room-details', $base_url . '/admin/css/room-details.css', array('rtr-base'), $version);
        wp_enqueue_style('rtr-modals', $base_url . '/admin/css/modals.css', array('rtr-base'), $version);
        wp_enqueue_style('rtr-email-modal', $base_url . '/admin/css/email-modal.css', array('rtr-modals'), $version);
        wp_enqueue_style('rtr-email-history', $base_url . '/admin/css/email-history-modal.css', array('rtr-modals'), $version);
        wp_enqueue_style('rtr-responsive', $base_url . '/admin/css/responsive.css', array('rtr-base'), $version);

        
        // External dependencies
        wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css', array(), '6.0.0');
        wp_enqueue_style('google-fonts', 'https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap', array(), null);
        
        // Chart.js
        wp_enqueue_script('chartjs', 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js', array(), '3.9.1', true);
        
        // JavaScript Modules
        wp_enqueue_script('rtr-main', $base_url . '/admin/js/main.js', array('jquery'), $version, true);
        
        // Configuration
        wp_add_inline_script('rtr-main', $this->get_js_config(), 'before');
    }
    
    /**
     * Standard WordPress asset enqueuing
     */
    public function enqueue_assets($hook) {
        // Only load on our page
        if ($hook !== 'toplevel_page_dr-reading-room') {
            return;
        }
        
        // Assets already loaded via custom rendering
    }
    
    /**
     * Get JavaScript configuration
     */
    private function get_js_config() {
        $config = array(
            'apiUrl' => rest_url('directreach/rtr/v1'),
            'siteUrl' => get_site_url(), // NEW
            'nonce' => wp_create_nonce('wp_rest'),
            'userId' => get_current_user_id(),
            'userIsAdmin' => current_user_can('administrator'),
            'ajaxUrl' => admin_url('admin-ajax.php')
        );
        
        return 'const RTR_CONFIG = ' . wp_json_encode($config) . ';';
    }
    
    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        error_log('RTR: Registering REST routes');
        
        // Register Reading Room Controller routes
        if ($this->rest_controller) {
            $this->rest_controller->register_routes();
            error_log('RTR: Reading Room controller routes registered');
        } else {
            error_log('RTR: ERROR - rest_controller is null');
        }
        
        // Register Jobs Controller routes (MISSING!)
        if ($this->jobs_controller) {
            $this->jobs_controller->register_routes();
            error_log('RTR: Jobs controller routes registered');
        } else {
            error_log('RTR: ERROR - jobs_controller is null');
        }
    }
}

// Initialize
function init_reading_room_dashboard() {
    return ReadingRoomDashboard::get_instance();
}

add_action('plugins_loaded', __NAMESPACE__ . '\init_reading_room_dashboard');