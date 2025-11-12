<?php
/**
 * Plugin Name: DirectReach - Reading the Room
 * Description: RTR Dashboard and REST APIs for the Reading the Room module.
 * Version: 2.0.0
 * Author: Your Team
 * Text Domain: directreach
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Constants
 */
define('DR_RTR_VERSION', '2.0.0');
define('DR_RTR_PLUGIN_FILE', __FILE__);
define('DR_RTR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('DR_RTR_PLUGIN_URL', plugin_dir_url(__FILE__));
define('DR_RTR_ADMIN_DIR', DR_RTR_PLUGIN_DIR . 'admin/');
define('DR_RTR_ADMIN_VIEWS', DR_RTR_ADMIN_DIR . 'views/');
define('DR_RTR_ASSETS_URL', DR_RTR_PLUGIN_URL . 'assets/');


/**
 * Load all required files
 */
function dr_rtr_require_files(): void
{
    static $loaded = false;
    
    if ($loaded) {
        return;
    }
    
    $includes_dir = DR_RTR_PLUGIN_DIR . 'includes/';
    $api_dir = $includes_dir . 'api/';
    
    $files = [
        $includes_dir . 'class-reading-room-database.php',
        $includes_dir . 'class-campaign-matcher.php',
        
    ];
    
    $api_files = [
        $api_dir . 'class-reading-room-controller.php',
        $api_dir . 'class-jobs-controller.php',
        $api_dir . 'class-aleads-enrichment.php',
    ];
    
    foreach (array_merge($files, $api_files) as $file) {
        if (file_exists($file)) {
            require_once $file;
        } else {
            error_log('[RTR] ERROR: File not found: ' . $file);
        }
    }
    
    
    $loaded = true;
}

/**
 * Activation hook
 */
register_activation_hook(DR_RTR_PLUGIN_FILE, function (): void {
    global $wpdb;
    
    dr_rtr_require_files();
    dr_rtr_register_rewrite();
    flush_rewrite_rules(false);

    if (class_exists('DirectReach\\ReadingTheRoom\\Reading_Room_Database')) {
        $db = new \DirectReach\ReadingTheRoom\Reading_Room_Database($wpdb);
        $result = $db->install_schema();
        
        if ($result) {
            error_log('[RTR Activation] Successfully installed database schema');
        } else {
            error_log('[RTR Activation] ERROR: Failed to install database schema');
        }
    } else {
        error_log('[RTR Activation] ERROR: Reading_Room_Database class not found');
    }
});

/**
 * Admin action to force schema installation
 * Access via: /wp-admin/admin.php?page=dr-rtr-force-schema
 */
add_action('admin_menu', function() {
    add_submenu_page(
        null, // Hidden from menu
        'RTR Force Schema',
        'RTR Force Schema',
        'manage_options',
        'dr-rtr-force-schema',
        'dr_rtr_force_schema_page'
    );
});

function dr_rtr_force_schema_page(): void {
    if (!current_user_can('manage_options')) {
        wp_die('You do not have permission to access this page.');
    }
    
    global $wpdb;
    dr_rtr_require_files();
    
    echo '<div class="wrap">';
    echo '<h1>DirectReach RTR - Force Schema Installation</h1>';
    
    if (class_exists('DirectReach\\ReadingTheRoom\\Reading_Room_Database')) {
        $db = new \DirectReach\ReadingTheRoom\Reading_Room_Database($wpdb);
        
        // Delete the version option to force reinstall
        delete_option('rtr_db_version');
        
        $result = $db->install_schema();
        
        if ($result) {
            echo '<div class="notice notice-success"><p><strong>SUCCESS:</strong> Schema installed successfully!</p></div>';
            
            // Verify tables
            $tables = $db->tables();
            echo '<h2>Table Verification:</h2><ul>';
            
            foreach ($tables as $name => $table_name) {
                $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));
                $status = ($exists === $table_name) ? '✓' : '✗';
                $class = ($exists === $table_name) ? 'notice-success' : 'notice-error';
                echo "<li>$status <code>$table_name</code></li>";
            }
            
            echo '</ul>';
        } else {
            echo '<div class="notice notice-error"><p><strong>ERROR:</strong> Schema installation failed. Check debug log.</p></div>';
        }
    } else {
        echo '<div class="notice notice-error"><p><strong>ERROR:</strong> Database class not found.</p></div>';
    }
    
    echo '<p><a href="' . admin_url('admin.php?page=dr-reading-room') . '" class="button">← Back to RTR Dashboard</a></p>';
    echo '</div>';
}

/**
 * Init lifecycle
 */
add_action('plugins_loaded', 'dr_rtr_init', 5);
function dr_rtr_init() {
    dr_rtr_require_files();

}

/**
 * Register REST routes
 */
add_action('rest_api_init', function() {
    dr_rtr_require_files();
    
    global $wpdb;
    
    if (!class_exists('DirectReach\\ReadingTheRoom\\Reading_Room_Database')) {
        error_log('[RTR] ERROR: Reading_Room_Database class not found');
        return;
    }
    
    $db = new \DirectReach\ReadingTheRoom\Reading_Room_Database($wpdb);
    
    // Reading Room Controller
    if (class_exists('DirectReach\\ReadingTheRoom\\API\\Reading_Room_Controller')) {
        try {
            $controller = new \DirectReach\ReadingTheRoom\API\Reading_Room_Controller($db);
            $controller->register_routes();
            error_log('[RTR] Reading_Room_Controller routes registered successfully');
        } catch (\Exception $e) {
            error_log('[RTR] ERROR registering Reading_Room_Controller: ' . $e->getMessage());
        }
    } else {
        error_log('[RTR] ERROR: Reading_Room_Controller class not found');
    }
    
    // Jobs Controller
    if (class_exists('DirectReach\\ReadingTheRoom\\API\\Jobs_Controller')) {
        try {
            $controller = new \DirectReach\ReadingTheRoom\API\Jobs_Controller();
            $controller->register_routes();
            error_log('[RTR] Jobs_Controller routes registered successfully');
        } catch (\Exception $e) {
            error_log('[RTR] ERROR registering Jobs_Controller: ' . $e->getMessage());
        }
    }
}, 10);




/**
 * Rewrite rules
 */
function dr_rtr_register_rewrite(): void
{
    add_rewrite_rule('^reading-the-room/?$', 'index.php?dr_rtr_dashboard=1', 'top');
}
add_action('init', 'dr_rtr_register_rewrite');

add_filter('query_vars', function($vars) {
    return array_merge($vars, ['dr_rtr_dashboard']);
});

add_action('template_redirect', function (): void {
    if ((int) get_query_var('dr_rtr_dashboard') === 1) {
        if (!is_user_logged_in()) {
            $return_url = home_url('/reading-the-room/');
            wp_redirect(wp_login_url($return_url));
            exit;
        }
        dr_rtr_render_dashboard();
        exit;
    }
});

/**
 * Admin menu
 */
add_action('admin_menu', function () {
    add_menu_page(
        __('Reading the Room', 'directreach'),
        __('Reading the Room', 'directreach'),
        'read',
        'dr-reading-room',
        'dr_rtr_admin_redirect',
        'dashicons-chart-area',
        3
    );
});

function dr_rtr_admin_redirect(): void
{
    if (!headers_sent()) {
        wp_safe_redirect(esc_url_raw(home_url('/reading-the-room/')));
        exit;
    }
    echo '<script>window.location.href="' . esc_url(home_url('/reading-the-room/')) . '";</script>';
    exit;
}

/**
 * Render dashboard
 */
function dr_rtr_render_dashboard(): void
{
    $view = DR_RTR_ADMIN_VIEWS . 'reading-room-dashboard.php';
    if (!file_exists($view)) {
        wp_die(
            esc_html__('Reading Room dashboard view not found.', 'directreach'),
            esc_html__('DirectReach', 'directreach'),
            ['response' => 500]
        );
    }
    
    // Output complete HTML with inline scripts
    ?>
    <!DOCTYPE html>
    <html <?php language_attributes(); ?>>
    <head>
        <meta charset="<?php bloginfo('charset'); ?>">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?php echo esc_html(get_bloginfo('name')); ?> - Reading the Room</title>
        
        <?php
        // Enqueue CSS
        $css_url = DR_RTR_PLUGIN_URL . 'admin/css/main.css';
        if (file_exists(DR_RTR_ADMIN_DIR . 'css/main.css')) {
            echo '<link rel="stylesheet" href="' . esc_url($css_url) . '?ver=' . DR_RTR_VERSION . '">';
        }
        
        // Config object
        $config = [
            'siteUrl' => get_site_url(),
            'nonce'   => wp_create_nonce('wp_rest'),
            'restUrl' => esc_url_raw(rest_url('directreach/v1/reading-room')),
            'apiUrl'  => esc_url_raw(rest_url('directreach/v1/reading-room')),
            'showWelcome' => true,
            'trackingEnabled' => false,
            'assets'  => [
                'logo' => esc_url_raw(DR_RTR_PLUGIN_URL . 'assets/images/MEMO_Logo.png'),
                'seal' => esc_url_raw(DR_RTR_PLUGIN_URL . 'assets/images/MEMO_Seal.png'),
            ],
        ];
        ?>
        
        <script type="text/javascript">
        window.rtrDashboardConfig = <?php echo wp_json_encode($config); ?>;
        console.log('✅ RTR Config loaded:', window.rtrDashboardConfig);
        </script>
        
        <?php wp_head(); ?>
    </head>
    <body class="rtr-dashboard-page">
        <?php include $view; ?>
        
        <script type="module" src="<?php echo esc_url(DR_RTR_PLUGIN_URL . 'admin/js/main.js?ver=' . DR_RTR_VERSION); ?>"></script>
        
        <?php wp_footer(); ?>
    </body>
    </html>
    <?php
}
