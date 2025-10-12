<?php
/**
 * Global Templates Standalone Page
 * 
 * @package DirectReach_Campaign_Builder
 * @since 2.5.0
 */

namespace DirectReach\CampaignBuilder\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class Global_Templates_Page {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'register_page'));
        add_action('admin_init', array($this, 'maybe_render_custom_page'), 10);
        add_filter('script_loader_tag', array($this, 'add_module_type_attribute'), 10, 2);
    }
    
    /**
     * Register the page (but hide it from menu)
     */
    public function register_page() {
        add_menu_page(
            'Global Email Templates',
            'Global Templates',
            'manage_options',
            'dr-global-templates',
            array($this, 'render_page_fallback'),
            'dashicons-email-alt',
            30
        );
        
        // Hide from admin menu
        add_action('admin_head', function() {
            remove_menu_page('dr-global-templates');
        });
    }
    
    /**
     * Fallback render (should rarely be called)
     */
    public function render_page_fallback() {
        $this->render_full_page();
    }
    
    /**
     * Intercept page load and render custom full-page interface
     */
    public function maybe_render_custom_page() {
        if (!isset($_GET['page']) || $_GET['page'] !== 'dr-global-templates') {
            return;
        }
        
        if (!current_user_can('manage_options')) {
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
     * Render the complete custom page
     */
    private function render_full_page() {
        $this->enqueue_assets();
        $this->render_html_head();
        $this->render_html_body();
    }
    
    /**
     * Render HTML head
     */
    private function render_html_head() {
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?php echo esc_html__('Global Templates', 'directreach'); ?> | <?php bloginfo('name'); ?></title>
            
            <!-- Font Awesome (direct link to avoid enqueue issues) -->
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" />
            
            <?php
            wp_print_styles();
            wp_print_head_scripts();
            ?>
            
            <style>
                #wpadminbar { display: none !important; }
                html { margin-top: 0 !important; }
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { 
                    margin: 0 !important;
                    padding: 0 !important;
                    background: #f5f5f5;
                    -webkit-font-smoothing: antialiased;
                }
            </style>
        </head>
        <?php
    }
    
    /**
     * Render HTML body
     */
    private function render_html_body() {
        ?>
        <body class="global-templates-page">
            <?php $this->render_page_content(); ?>
            <?php wp_print_footer_scripts(); ?>
        </body>
        </html>
        <?php
    }
    
    /**
     * Render page content
     */
    private function render_page_content() {
        $template_file = DR_CB_PLUGIN_DIR . 'admin/views/global-templates-page.php';
        
        if (file_exists($template_file)) {
            include $template_file;
        } else {
            echo '<div style="padding: 40px;">Template file not found: ' . esc_html($template_file) . '</div>';
        }
    }
    
    /**
     * Enqueue assets
     */
    private function enqueue_assets() {
        // Nuclear option: remove ALL WordPress head actions
        remove_all_actions('wp_head');
        remove_all_actions('wp_print_styles');
        remove_all_actions('wp_print_head_scripts');
        
        // Disable admin bar completely
        show_admin_bar(false);
        
        // Re-add ONLY what we need
        add_action('wp_head', 'wp_enqueue_scripts', 1);
        add_action('wp_head', 'wp_print_styles', 8);
        add_action('wp_head', 'wp_print_head_scripts', 9);
        
        // Now enqueue our stuff
        wp_enqueue_script('jquery');
        
        wp_enqueue_style('dr-variables', DR_CB_PLUGIN_URL . 'admin/css/variables.css', array(), DR_CB_VERSION);
        wp_enqueue_style('dr-base', DR_CB_PLUGIN_URL . 'admin/css/base.css', array('dr-variables'), DR_CB_VERSION);
        wp_enqueue_style('dr-templates-step', DR_CB_PLUGIN_URL . 'admin/css/templates-step.css', array('dr-base'), DR_CB_VERSION);
        
        wp_enqueue_script('dr-global-templates', DR_CB_PLUGIN_URL . 'admin/js/global-templates-main.js', array('jquery'), DR_CB_VERSION, true);
        
        wp_add_inline_script('dr-global-templates', 'window.drGlobalTemplatesConfig = ' . wp_json_encode(array(
            'apiUrl' => rest_url('directreach/v2'),
            'nonce' => wp_create_nonce('wp_rest'),
            'campaignBuilderUrl' => admin_url('admin.php?page=dr-campaign-builder'),
        )) . ';', 'before');
    }


    /**
     * Add type="module" to script tag
     */
    public function add_module_type_attribute($tag, $handle) {
        if ($handle !== 'dr-global-templates') {
            return $tag;
        }
        
        if (!is_string($tag)) {
            return $tag;
        }
        
        return str_replace(' src', ' type="module" src', $tag);
    }
}

// Initialize
new Global_Templates_Page();