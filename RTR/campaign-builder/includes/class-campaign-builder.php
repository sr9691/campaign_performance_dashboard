<?php
/**
 * Campaign Builder Core Class
 * 
 * Main plugin class that handles initialization, admin interface,
 * asset management, and REST API registration.
 * 
 * @package DirectReach_Campaign_Builder
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class DR_Campaign_Builder {
    
    /**
     * Singleton instance
     * 
     * @var DR_Campaign_Builder|null
     */
    private static $instance = null;
    
    /**
     * Plugin configuration
     * 
     * @var array
     */
    private $config = array();
    
    // ============================================================================
    // SINGLETON PATTERN
    // ============================================================================
    
    /**
     * Get singleton instance
     * 
     * @return DR_Campaign_Builder
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct() {
        $this->init_config();
        $this->init_hooks();
    }
    
    /**
     * Prevent cloning
     */
    private function __clone() {}
    
    /**
     * Prevent unserialization
     */
    public function __wakeup() {
        throw new \Exception('Cannot unserialize singleton');
    }
    
    // ============================================================================
    // INITIALIZATION
    // ============================================================================
    
    /**
     * Initialize plugin configuration
     */
    private function init_config() {
        $this->config = array(
            'menu_capability' => 'manage_options',
            'menu_slug' => 'dr-campaign-builder',
            'menu_position' => 26,
            'rest_namespace' => 'directreach/v2',
        );
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Admin interface
        add_action('admin_menu', array($this, 'register_admin_menu'));
        add_action('admin_init', array($this, 'maybe_render_custom_page'), 10);
        
        // REST API
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        
        // Script modifications
        add_filter('script_loader_tag', array($this, 'add_module_type_attribute'), 10, 2);
    }
    
    // ============================================================================
    // ADMIN MENU & PAGE RENDERING
    // ============================================================================
    
    /**
     * Register admin menu page
     */
    public function register_admin_menu() {
        if (!current_user_can($this->config['menu_capability'])) {
            return;
        }
        
        add_menu_page(
            __('Campaign Builder', 'directreach'),
            __('Campaign Builder', 'directreach'),
            $this->config['menu_capability'],
            $this->config['menu_slug'],
            array($this, 'render_page_fallback'),
            'dashicons-megaphone',
            $this->config['menu_position']
        );
    }
    
    /**
     * Fallback render method (should rarely be called)
     */
    public function render_page_fallback() {
        $this->render_full_page();
    }
    
    /**
     * Intercept page load and render custom full-page interface
     * 
     * This runs early in admin_init to take over page rendering
     * before WordPress loads the standard admin interface.
     */
    public function maybe_render_custom_page() {
        // Check if this is our page
        if (!isset($_GET['page']) || $_GET['page'] !== $this->config['menu_slug']) {
            return;
        }
        
        // Verify permissions
        if (!current_user_can($this->config['menu_capability'])) {
            wp_die(
                __('Sorry, you are not allowed to access this page.', 'directreach'),
                __('Access Denied', 'directreach'),
                array('response' => 403)
            );
        }
        
        // Render and exit to prevent WordPress admin from loading
        $this->render_full_page();
        exit;
    }
    
    /**
     * Render the complete custom page (full HTML document)
     */
    private function render_full_page() {
        // Enqueue all assets first
        $this->enqueue_all_assets();
        
        // Start output
        $this->render_html_head();
        $this->render_html_body();
    }
    
    /**
     * Render HTML head section
     */
    private function render_html_head() {
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?php echo esc_html__('Campaign Builder', 'directreach'); ?> | <?php bloginfo('name'); ?></title>
            
            <?php
            // Print enqueued styles
            wp_print_styles();
            
            // Print head scripts (includes inline config via wp_add_inline_script)
            wp_print_head_scripts();
            ?>
            
            <style>
                /* Remove WordPress admin chrome */
                #wpadminbar { 
                    display: none !important; 
                }
                html { 
                    margin-top: 0 !important; 
                }
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }
                body { 
                    margin: 0 !important;
                    padding: 0 !important;
                    background: #f5f5f5;
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                    -webkit-font-smoothing: antialiased;
                    -moz-osx-font-smoothing: grayscale;
                }
            </style>
        </head>
        <?php
    }
    
    /**
     * Render HTML body section
     */
    private function render_html_body() {
        ?>
        <body class="campaign-builder-page">
            <?php $this->render_page_content(); ?>
            
            <?php
            // Print footer scripts (main.js module runs here, but config is already loaded)
            wp_print_footer_scripts();
            ?>
        </body>
        </html>
        <?php
    }
    
    /**
     * Render the main page content from template
     */
    private function render_page_content() {
        $template_file = DR_CB_PLUGIN_DIR . 'admin/views/campaign-builder.php';
        
        if (file_exists($template_file)) {
            include $template_file;
        } else {
            $this->render_template_error($template_file);
        }
    }
    
    /**
     * Render error message when template is missing
     * 
     * @param string $template_file Path to missing template
     */
    private function render_template_error($template_file) {
        ?>
        <div style="padding: 40px; text-align: center; max-width: 600px; margin: 100px auto; background: white; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
            <h1 style="color: #dc3545; margin-bottom: 20px;">
                <i class="fas fa-exclamation-triangle"></i>
                <?php _e('Template Error', 'directreach'); ?>
            </h1>
            <p style="color: #666; margin-bottom: 10px;">
                <?php _e('The campaign builder template file could not be found.', 'directreach'); ?>
            </p>
            <code style="display: block; padding: 10px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; font-size: 12px; color: #495057; word-break: break-all;">
                <?php echo esc_html($template_file); ?>
            </code>
            <p style="margin-top: 20px;">
                <a href="<?php echo esc_url(admin_url()); ?>" class="button button-primary">
                    <?php _e('Return to Dashboard', 'directreach'); ?>
                </a>
            </p>
        </div>
        <?php
    }
    
    // ============================================================================
    // ASSET MANAGEMENT
    // ============================================================================
    
    /**
     * Enqueue all CSS and JavaScript assets
     */
    private function enqueue_all_assets() {
        $this->enqueue_external_dependencies();
        $this->enqueue_css_files();
        $this->enqueue_js_files();
        $this->inject_js_config();
    }
    
    /**
     * Enqueue external dependencies (CDN resources)
     */
    private function enqueue_external_dependencies() {
        // Font Awesome
        wp_enqueue_style(
            'font-awesome',
            'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css',
            array(),
            '5.15.4'
        );
    }
    
    /**
     * Enqueue all CSS files
     */
    private function enqueue_css_files() {
        $css_files = array(
            // Base styles (loaded first)
            'dr-cb-variables' => array(
                'file' => 'variables.css',
                'deps' => array(),
            ),
            'dr-cb-base' => array(
                'file' => 'base.css',
                'deps' => array('dr-cb-variables'),
            ),
            
            // Step-specific styles
            'dr-cb-client-step' => array(
                'file' => 'client-step.css',
                'deps' => array('dr-cb-base'),
            ),
            'dr-cb-campaign-step' => array(
                'file' => 'campaign-step.css',
                'deps' => array('dr-cb-base'),
            ),
            'dr-cb-templates-step' => array(
                'file' => 'templates-step.css',
                'deps' => array('dr-cb-base'),
            ),
            
            // Additional feature styles
            'dr-cb-global-templates' => array(
                'file' => 'global-templates.css',
                'deps' => array('dr-cb-base'),
            ),
        );
        
        foreach ($css_files as $handle => $config) {
            wp_enqueue_style(
                $handle,
                DR_CB_PLUGIN_URL . 'admin/css/' . $config['file'],
                $config['deps'],
                DR_CB_VERSION
            );
        }
    }
    
    /**
     * Enqueue JavaScript files
     */
    private function enqueue_js_files() {
        // jQuery (WordPress core)
        wp_enqueue_script('jquery');
        
        // Main JavaScript module
        wp_enqueue_script(
            'dr-campaign-builder',
            DR_CB_PLUGIN_URL . 'admin/js/main.js',
            array('jquery'),
            DR_CB_VERSION,
            true // Load in footer
        );
    }
    
    /**
     * Inject JavaScript configuration
     * 
     * Uses wp_add_inline_script to ensure config is available
     * BEFORE the main.js ES6 module executes.
     */
    private function inject_js_config() {
        $config = array(
            'apiUrl' => rest_url($this->config['rest_namespace']),
            'nonce' => wp_create_nonce('wp_rest'),
            'userId' => get_current_user_id(),
            'dashboardUrl' => admin_url('admin.php?page=' . $this->config['menu_slug']),
            'pluginUrl' => DR_CB_PLUGIN_URL,
            'version' => DR_CB_VERSION,
            'debug' => defined('WP_DEBUG') && WP_DEBUG,
        );
        
        wp_add_inline_script(
            'dr-campaign-builder',
            'window.drCampaignBuilderConfig = ' . wp_json_encode($config) . ';',
            'before' // Critical: runs BEFORE main.js
        );
    }
    
    /**
     * Add type="module" attribute to main script tag
     * 
     * Allows use of ES6 import/export syntax
     * 
     * @param string $tag Script tag HTML
     * @param string $handle Script handle
     * @return string Modified script tag
     */
    public function add_module_type_attribute($tag, $handle) {
        if ($handle !== 'dr-campaign-builder') {
            return $tag;
        }
        
        if (!is_string($tag)) {
            return $tag;
        }
        
        // Add type="module" before src attribute
        return str_replace(' src', ' type="module" src', $tag);
    }
    
    // ============================================================================
    // REST API REGISTRATION
    // ============================================================================
    
    /**
     * Register all REST API routes
     * 
     * Loads controller files and initializes their routes
     */
    public function register_rest_routes() {
        // Load Phase 2 controllers
        $this->load_rest_controllers();
        
        // Load Phase 2.5 AI classes (if needed)
        $this->load_ai_classes();
        
        // Initialize controllers
        $this->init_rest_controllers();
    }
    
    /**
     * Load REST API controller files
     */
    private function load_rest_controllers() {
        $controllers = array(
            'class-rest-controller.php',
            'class-clients-controller.php',
            'class-workflow-controller.php',
            'class-campaigns-controller.php',
            'class-templates-controller.php',
            'class-email-generation-controller.php',
        );
        
        foreach ($controllers as $controller) {
            $file = DR_CB_PLUGIN_DIR . 'includes/api/' . $controller;
            if (file_exists($file)) {
                require_once $file;
            } else {
                $this->log_error('REST controller file not found: ' . $controller);
            }
        }
    }
    
    /**
     * Load AI-related classes (Phase 2.5)
     */
    private function load_ai_classes() {
        $ai_classes = array(
            'class-prompt-template.php',
            'class-template-resolver.php',
            'class-ai-settings-manager.php',
            'class-ai-rate-limiter.php',
            'class-ai-email-generator.php',
            'class-email-tracking-manager.php',
        );
        
        foreach ($ai_classes as $class_file) {
            $file = DR_CB_PLUGIN_DIR . 'includes/' . $class_file;
            if (file_exists($file)) {
                require_once $file;
            }
            // Note: These are optional for Phase 2, so don't log errors
        }
    }
    
    /**
     * Initialize and register REST controllers
     */
    private function init_rest_controllers() {
        $controllers = array(
            '\DirectReach\CampaignBuilder\API\Clients_Controller',
            '\DirectReach\CampaignBuilder\API\Workflow_Controller',
            '\DirectReach\CampaignBuilder\API\Campaigns_Controller',
            '\DirectReach\CampaignBuilder\API\Templates_Controller',
            '\DirectReach\CampaignBuilder\API\Email_Generation_Controller',
        );
        
        foreach ($controllers as $controller_class) {
            if (class_exists($controller_class)) {
                $controller = new $controller_class();
                if (method_exists($controller, 'register_routes')) {
                    $controller->register_routes();
                }
            } else {
                $this->log_error('REST controller class not found: ' . $controller_class);
            }
        }
    }
    
    // ============================================================================
    // UTILITY METHODS
    // ============================================================================
    
    /**
     * Get plugin configuration value
     * 
     * @param string $key Configuration key
     * @param mixed $default Default value if key doesn't exist
     * @return mixed Configuration value
     */
    public function get_config($key, $default = null) {
        return isset($this->config[$key]) ? $this->config[$key] : $default;
    }
    
    /**
     * Log error message
     * 
     * @param string $message Error message
     */
    private function log_error($message) {
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('DR_Campaign_Builder: ' . $message);
        }
    }
    
    /**
     * Check if user has required capability
     * 
     * @return bool
     */
    public function user_can_access() {
        return current_user_can($this->config['menu_capability']);
    }
    
    /**
     * Get REST API base URL
     * 
     * @return string
     */
    public function get_rest_url() {
        return rest_url($this->config['rest_namespace']);
    }
}