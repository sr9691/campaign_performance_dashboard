<?php
/**
 * DirectReach Room Scoring System Bootstrap
 * 
 * Initializes the Room Thresholds & Scoring Rules system
 * Custom page rendering following Campaign Builder architecture
 * 
 * @package DirectReach
 * @subpackage RTR_Scoring_System
 * @since 2.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define scoring system version
define('RTR_SCORING_VERSION', '1.0.0');

// Define paths
define('RTR_SCORING_PATH', plugin_dir_path(__FILE__));
define('RTR_SCORING_URL', plugin_dir_url(__FILE__));

/**
 * Main Scoring System Class
 */
class DirectReach_Scoring_System {
    
    /**
     * Single instance
     * @var DirectReach_Scoring_System
     */
    private static $instance = null;
    
    /**
     * Database handlers
     */
    public $scoring_rules_db;
    public $room_thresholds_db;
    
    /**
     * Plugin configuration
     * @var array
     */
    private $config = array();
    
    /**
     * Page data to pass to views
     * @var array
     */
    private $page_data = array();
    
    /**
     * Get singleton instance
     * 
     * @return DirectReach_Scoring_System
     */
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_config();
        $this->load_dependencies();
        $this->init_database_handlers();
        $this->init_hooks();
    }
    
    /**
     * Initialize configuration
     */
    private function init_config() {
        $this->config = array(
            'menu_capability' => 'manage_options',
            'rest_namespace' => 'directreach/v2',
        );
    }
    
    /**
     * Load required files
     */
    private function load_dependencies() {
        // Industry taxonomy
        require_once RTR_SCORING_PATH . 'includes/industry-config.php';
        
        // Database classes
        require_once RTR_SCORING_PATH . 'includes/class-scoring-rules-database.php';
        require_once RTR_SCORING_PATH . 'includes/class-room-thresholds-database.php';
        
        // Score Calculator 
        if (file_exists(RTR_SCORING_PATH . 'includes/class-score-calculator.php')) {
            require_once RTR_SCORING_PATH . 'includes/class-score-calculator.php';
        }
        
        // Hot List Migrator 
        require_once RTR_SCORING_PATH . 'includes/class-hotlist-migrator.php';
        
        // API controllers 
        require_once RTR_SCORING_PATH . 'includes/api/class-room-thresholds-controller.php';
        require_once RTR_SCORING_PATH . 'includes/api/class-scoring-rules-controller.php';
        require_once RTR_SCORING_PATH . 'includes/api/class-score-calculator-controller.php';
    }

    /**
     * Initialize database handler instances
     */
    private function init_database_handlers() {
        $this->scoring_rules_db = new RTR_Scoring_Rules_Database();
        $this->room_thresholds_db = new RTR_Room_Thresholds_Database();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Admin menu registration
        add_action('admin_menu', array($this, 'register_admin_menus'), 20);
        
        // Custom page rendering (intercept early like Campaign Builder)
        add_action('admin_init', array($this, 'maybe_render_custom_pages'), 10);
        
        // Register API routes
        add_action('rest_api_init', function() {
            error_log('RTR Scoring: Registering REST API routes');
            
            // Room Thresholds Controller
            if (class_exists('RTR_Room_Thresholds_Controller')) {
                $thresholds_controller = new RTR_Room_Thresholds_Controller();
                $thresholds_controller->register_routes();
                error_log('RTR Scoring: Room Thresholds routes registered');
            } else {
                error_log('RTR Scoring: ERROR - RTR_Room_Thresholds_Controller class not found');
            }
            
            // Scoring Rules Controller
            if (class_exists('RTR_Scoring_Rules_Controller')) {
                $rules_controller = new RTR_Scoring_Rules_Controller();
                $rules_controller->register_routes();
                error_log('RTR Scoring: Scoring Rules routes registered');
            } else {
                error_log('RTR Scoring: ERROR - RTR_Scoring_Rules_Controller class not found');
            }
            
            // Score Calculator Controller (Iteration 6)
            if (class_exists('RTR_Score_Calculator_Controller')) {
                $calculator_controller = new RTR_Score_Calculator_Controller();
                $calculator_controller->register_routes();
                error_log('RTR Scoring: Score Calculator routes registered');
            } else {
                error_log('RTR Scoring: ERROR - RTR_Score_Calculator_Controller class not found');
            }
        });

        // Script modifications
        add_filter('script_loader_tag', array($this, 'add_module_type_attribute'), 10, 2);
    }
    
    /**
     * Register admin menu items
     */
    public function register_admin_menus() {
        if (!current_user_can($this->config['menu_capability'])) {
            return;
        }
        
        // Room Thresholds (standalone page)
        add_menu_page(
            __('Room Thresholds', 'directreach'),
            __('Room Thresholds', 'directreach'),
            $this->config['menu_capability'],
            'dr-room-thresholds',
            array($this, 'render_page_fallback'),
            'dashicons-chart-line',
            27
        );
        
        // Scoring Rules (standalone page)
        add_menu_page(
            __('Scoring Rules', 'directreach'),
            __('Scoring Rules', 'directreach'),
            $this->config['menu_capability'],
            'dr-scoring-rules',
            array($this, 'render_page_fallback'),
            'dashicons-calculator',
            28
        );
        
        // Hide from menu (accessed via Settings dropdown)
        remove_menu_page('dr-room-thresholds');
        remove_menu_page('dr-scoring-rules');
    }
    
    /**
     * Fallback render method (should rarely be called)
     */
    public function render_page_fallback() {
        echo '<div style="padding: 40px;"><p>Loading...</p></div>';
    }
    
    /**
     * Intercept page load and render custom full-page interface
     * Follows Campaign Builder architecture
     */
    public function maybe_render_custom_pages() {
        // Check if this is one of our pages
        if (!isset($_GET['page'])) {
            return;
        }
        
        $page = sanitize_text_field($_GET['page']);
        $our_pages = array('dr-room-thresholds', 'dr-scoring-rules');
        
        if (!in_array($page, $our_pages)) {
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
        $this->render_full_page($page);
        exit;
    }
    
    /**
     * Render the complete custom page
     * 
     * @param string $page Page slug
     */
    private function render_full_page($page) {
        // Set page-specific data
        $this->set_page_data($page);
        
        // Enqueue assets
        $this->enqueue_page_assets($page);
        
        // Start output
        $this->render_html_head($page);
        $this->render_html_body($page);
    }
    
    /**
     * Set page-specific data to pass to views
     * 
     * @param string $page Page slug
     */
    private function set_page_data($page) {
        switch ($page) {
            case 'dr-room-thresholds':
                $this->page_data = array(
                    'page_title' => __('Room Thresholds', 'directreach'),
                    'page_description' => __('Configure score thresholds that determine room assignments.', 'directreach')
                );
                break;
                
            case 'dr-scoring-rules':
                // Get rule counts for the UI
                $global_rules = $this->scoring_rules_db->get_all_global_rules();
                
                // Count enabled rules per room
                $problem_count = 0;
                $solution_count = 0;
                $offer_count = 0;
                
                if ($global_rules && is_array($global_rules)) {
                    if (isset($global_rules['problem']) && is_array($global_rules['problem'])) {
                        $problem_count = count(array_filter($global_rules['problem'], function($rule) {
                            return isset($rule['enabled']) && $rule['enabled'];
                        }));
                    }
                    
                    if (isset($global_rules['solution']) && is_array($global_rules['solution'])) {
                        $solution_count = count(array_filter($global_rules['solution'], function($rule) {
                            return isset($rule['enabled']) && $rule['enabled'];
                        }));
                    }
                    
                    if (isset($global_rules['offer']) && is_array($global_rules['offer'])) {
                        $offer_count = count(array_filter($global_rules['offer'], function($rule) {
                            return isset($rule['enabled']) && $rule['enabled'];
                        }));
                    }
                }
                
                $this->page_data = array(
                    'page_title' => __('Global Scoring Rules', 'directreach'),
                    'page_description' => __('Configure how prospects earn points based on firmographics, engagement, and purchase signals. These rules determine lead scores and room assignments.', 'directreach'),
                    'problem_rules_count' => $problem_count,
                    'solution_rules_count' => $solution_count,
                    'offer_rules_count' => $offer_count
                );
                break;
                
            default:
                $this->page_data = array(
                    'page_title' => __('Settings', 'directreach'),
                    'page_description' => ''
                );
        }
    }
    
    /**
     * Render HTML head section
     * 
     * @param string $page Page slug
     */
    private function render_html_head($page) {
        $title = isset($this->page_data['page_title']) ? $this->page_data['page_title'] : 'Settings';
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?php echo esc_html($title); ?> | <?php bloginfo('name'); ?></title>
            
            <?php
            // Print enqueued styles
            wp_print_styles();
            
            // Print head scripts
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
     * 
     * @param string $page Page slug
     */
    private function render_html_body($page) {
        ?>
        <body class="rtr-page <?php echo esc_attr($page); ?>">
            <?php $this->render_page_content($page); ?>
            
            <?php
            // Print footer scripts
            wp_print_footer_scripts();
            ?>
        </body>
        </html>
        <?php
    }
    
    /**
     * Render the main page content from template
     * 
     * @param string $page Page slug
     */
    private function render_page_content($page) {
        // Extract page data for use in template
        extract($this->page_data);
        
        $template_files = array(
            'dr-room-thresholds' => RTR_SCORING_PATH . 'admin/views/room-thresholds.php',
            'dr-scoring-rules' => RTR_SCORING_PATH . 'admin/views/scoring-rules.php'
        );
        
        $template_file = isset($template_files[$page]) ? $template_files[$page] : null;
        
        if ($template_file && file_exists($template_file)) {
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
                <?php _e('The template file could not be found.', 'directreach'); ?>
            </p>
            <code style="display: block; padding: 10px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; font-size: 12px; color: #495057; word-break: break-all;">
                <?php echo esc_html($template_file); ?>
            </code>
        </div>
        <?php
    }
    
    /**
     * Enqueue page-specific assets
     * 
     * @param string $page Page slug
     */
    private function enqueue_page_assets($page) {
        // External dependencies
        wp_enqueue_style(
            'font-awesome',
            'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css',
            array(),
            '5.15.4'
        );
        
        // Calculate Campaign Builder CSS URL correctly
        $rtr_base_url = dirname(RTR_SCORING_URL); 
        $cb_css_url = $rtr_base_url . '/campaign-builder/admin/css/';
        
        // Campaign Builder base styles (shared)
        wp_enqueue_style(
            'dr-cb-variables',
            $cb_css_url . 'variables.css',
            array(),
            RTR_SCORING_VERSION
        );
        
        wp_enqueue_style(
            'dr-cb-base',
            $cb_css_url . 'base.css',
            array('dr-cb-variables'),
            RTR_SCORING_VERSION
        );
        
        // Page-specific assets
        if ($page === 'dr-room-thresholds') {
            wp_enqueue_style(
                'rtr-room-thresholds',
                RTR_SCORING_URL . 'admin/css/room-thresholds.css',
                array('dr-cb-base'),
                RTR_SCORING_VERSION
            );
            
            wp_enqueue_script(
                'rtr-room-thresholds',
                RTR_SCORING_URL . 'admin/js/modules/room-thresholds-manager.js',
                array(),
                RTR_SCORING_VERSION,
                true
            );
            
            $this->inject_thresholds_config();
        }
        
        if ($page === 'dr-scoring-rules') {
            wp_enqueue_style(
                'rtr-scoring-rules',
                RTR_SCORING_URL . 'admin/css/scoring-rules.css',
                array('dr-cb-base'),
                RTR_SCORING_VERSION
            );
            
            wp_enqueue_script(
                'rtr-scoring-rules',
                RTR_SCORING_URL . 'admin/js/modules/scoring-rules-manager.js',
                array(),
                RTR_SCORING_VERSION,
                true
            );
            
            $this->inject_scoring_config();
        }
    }
    
    /**
     * Inject Room Thresholds configuration
     */
    private function inject_thresholds_config() {
        $config = array(
            'apiUrl' => rest_url($this->config['rest_namespace']),
            'nonce' => wp_create_nonce('wp_rest'),
            'globalThresholds' => $this->room_thresholds_db->get_global_thresholds(),
        );
        
        wp_add_inline_script(
            'rtr-room-thresholds',
            'window.rtrThresholdsConfig = ' . wp_json_encode($config) . ';',
            'before'
        );
    }
    
    /**
     * Inject Scoring Rules configuration
     */
    private function inject_scoring_config() {
        $global_rules = $this->scoring_rules_db->get_all_global_rules();
        
        // Get industries taxonomy
        $industries = array();
        if (function_exists('rtr_get_industries_taxonomy')) {
            $industries = rtr_get_industries_taxonomy();
        }
        
        $config = array(
            'apiUrl' => rest_url($this->config['rest_namespace']),
            'nonce' => wp_create_nonce('wp_rest'),
            'globalRules' => $global_rules ? $global_rules : array(
                'problem' => array(),
                'solution' => array(),
                'offer' => array()
            ),
            'industries' => $industries,
            'strings' => array(
                'saveSuccess' => __('Rules saved successfully', 'directreach'),
                'saveError' => __('Failed to save rules', 'directreach'),
                'validationError' => __('Please fix validation errors before saving', 'directreach'),
                'resetConfirm' => __('Reset rules to global defaults? This cannot be undone.', 'directreach')
            )
        );
        
        wp_add_inline_script(
            'rtr-scoring-rules',
            'window.rtrScoringConfig = ' . wp_json_encode($config) . ';',
            'before'
        );
    }
    
    /**
     * Add type="module" attribute to script tags
     * 
     * @param string $tag Script tag HTML
     * @param string $handle Script handle
     * @return string Modified script tag
     */
    public function add_module_type_attribute($tag, $handle) {
        $module_scripts = array('rtr-room-thresholds', 'rtr-scoring-rules');
        
        if (!in_array($handle, $module_scripts)) {
            return $tag;
        }
        
        if (!is_string($tag)) {
            return $tag;
        }
        
        return str_replace(' src', ' type="module" src', $tag);
    }
    
    /**
     * Get scoring rules database handler
     * 
     * @return RTR_Scoring_Rules_Database
     */
    public function get_scoring_rules_db() {
        return $this->scoring_rules_db;
    }
    
    /**
     * Get room thresholds database handler
     * 
     * @return RTR_Room_Thresholds_Database
     */
    public function get_room_thresholds_db() {
        return $this->room_thresholds_db;
    }
}

/**
 * Initialize the scoring system
 * 
 * @return DirectReach_Scoring_System
 */
function directreach_scoring_system() {
    return DirectReach_Scoring_System::instance();
}

// Initialize on plugins_loaded
add_action('plugins_loaded', 'directreach_scoring_system', 15);