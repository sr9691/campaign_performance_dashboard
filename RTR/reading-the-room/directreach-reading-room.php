<?php
/**
 * Plugin Name: DirectReach - Reading the Room
 * Description: RTR Dashboard and REST APIs for the Reading the Room module.
 * Version: 2.0.1
 * Author: Your Team
 * Text Domain: directreach
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('DR_RTR_VERSION', '2.0.1');
define('DR_RTR_PLUGIN_FILE', __FILE__);
define('DR_RTR_PLUGIN_DIR', plugin_dir_path(__FILE__) ?: __DIR__ . '/');
define('DR_RTR_PLUGIN_URL', plugin_dir_url(__FILE__) ?: plugins_url('/', __FILE__));
define('DR_RTR_ADMIN_DIR', DR_RTR_PLUGIN_DIR . 'admin/');
define('DR_RTR_ADMIN_VIEWS', DR_RTR_ADMIN_DIR . 'views/');
define('DR_RTR_ASSETS_URL', DR_RTR_PLUGIN_URL . 'assets/');

function dr_rtr_require_files(): void
{
    static $loaded = false;
    if ($loaded) return;
    
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

$activation_file = defined('DR_RTR_PLUGIN_FILE') && DR_RTR_PLUGIN_FILE ? DR_RTR_PLUGIN_FILE : __FILE__;
register_activation_hook($activation_file, function () use ($activation_file): void {
    global $wpdb;
    
    error_log('[RTR Activation] Starting...');
    dr_rtr_require_files();
    dr_rtr_register_rewrite();
    
    delete_option('rewrite_rules');
    flush_rewrite_rules(true);
    error_log('[RTR Activation] Rewrite rules flushed');

    if (class_exists('DirectReach\\ReadingTheRoom\\Reading_Room_Database')) {
        $db = new \DirectReach\ReadingTheRoom\Reading_Room_Database($wpdb);
        $result = $db->install_schema();
        error_log('[RTR Activation] Schema: ' . ($result ? 'SUCCESS' : 'FAILED'));
    }
    
    error_log('[RTR Activation] Complete');
});

$deactivation_file = defined('DR_RTR_PLUGIN_FILE') && DR_RTR_PLUGIN_FILE ? DR_RTR_PLUGIN_FILE : __FILE__;
register_deactivation_hook($deactivation_file, function (): void {
    flush_rewrite_rules(false);
});

add_action('admin_menu', function() {
    add_submenu_page(
        null,
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
    
    echo '<div class="wrap"><h1>DirectReach RTR - Force Schema Installation</h1>';
    
    if (class_exists('DirectReach\\ReadingTheRoom\\Reading_Room_Database')) {
        $db = new \DirectReach\ReadingTheRoom\Reading_Room_Database($wpdb);
        delete_option('rtr_db_version');
        $result = $db->install_schema();
        
        if ($result) {
            echo '<div class="notice notice-success"><p><strong>SUCCESS:</strong> Schema installed successfully!</p></div>';
            
            $tables = $db->tables();
            echo '<h2>Table Verification:</h2><ul>';
            
            foreach ($tables as $name => $table_name) {
                $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));
                $status = ($exists === $table_name) ? '✓' : '✗';
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

add_action('plugins_loaded', 'dr_rtr_init', 5);
function dr_rtr_init() {
    dr_rtr_require_files();
}

add_action('rest_api_init', function() {
    dr_rtr_require_files();
    global $wpdb;
    
    if (!class_exists('DirectReach\\ReadingTheRoom\\Reading_Room_Database')) {
        error_log('[RTR] ERROR: Reading_Room_Database class not found');
        return;
    }
    
    $db = new \DirectReach\ReadingTheRoom\Reading_Room_Database($wpdb);
    
    if (class_exists('DirectReach\\ReadingTheRoom\\API\\Reading_Room_Controller')) {
        try {
            $controller = new \DirectReach\ReadingTheRoom\API\Reading_Room_Controller($db);
            $controller->register_routes();
            error_log('[RTR] Reading_Room_Controller routes registered');
        } catch (\Exception $e) {
            error_log('[RTR] ERROR: ' . $e->getMessage());
        }
    }
    
    if (class_exists('DirectReach\\ReadingTheRoom\\API\\Jobs_Controller')) {
        try {
            $controller = new \DirectReach\ReadingTheRoom\API\Jobs_Controller($db);
            $controller->register_routes();
            error_log('[RTR] Jobs_Controller routes registered');
        } catch (\Exception $e) {
            error_log('[RTR] ERROR: ' . $e->getMessage());
        }
    }
}, 10);

add_action('init', 'dr_rtr_register_rewrite', 1);
function dr_rtr_register_rewrite(): void
{
    dr_rtr_require_files();
    
    // Production URL pattern
    add_rewrite_rule(
        '^directreach/reading-the-room/?$',
        'index.php?dr_rtr_dashboard=1',
        'top'
    );
    
    // Dev URL pattern
    add_rewrite_rule(
        '^dashboarddev/reading-the-room/?$',
        'index.php?dr_rtr_dashboard=1',
        'top'
    );
    
    // Generic fallback
    add_rewrite_rule(
        '^reading-the-room/?$',
        'index.php?dr_rtr_dashboard=1',
        'top'
    );
    
    error_log('[RTR] All rewrite rules registered');
}

add_filter('query_vars', function($vars) {
    $vars[] = 'dr_rtr_dashboard';
    return $vars;
}, 10, 1);

add_action('template_redirect', function (): void {
    $dashboard_var = get_query_var('dr_rtr_dashboard');
    
    if ((int) $dashboard_var === 1) {
        if (!is_user_logged_in()) {
            wp_redirect(wp_login_url($_SERVER['REQUEST_URI']));
            exit;
        }
        
        dr_rtr_render_dashboard();
        exit;
    }
    
    // Fallback: Check multiple URL patterns
    global $wp;
    $request = isset($wp->request) ? $wp->request : '';
    
    $valid_patterns = [
        'directreach/reading-the-room',
        'dashboarddev/reading-the-room',
        'reading-the-room'
    ];
    
    if (in_array($request, $valid_patterns, true)) {
        if (!is_user_logged_in()) {
            wp_redirect(wp_login_url($_SERVER['REQUEST_URI']));
            exit;
        }
        
        dr_rtr_render_dashboard();
        exit;
    }
}, 10);

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
    // Auto-detect environment from REQUEST_URI
    $uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
    
    if ($uri !== '' && strpos($uri, 'dashboarddev') !== false) {
        $url = home_url('/dashboarddev/reading-the-room/');
    } else {
        $url = home_url('/directreach/reading-the-room/');
    }
    
    if (!headers_sent()) {
        wp_safe_redirect(esc_url_raw($url));
        exit;
    }
    echo '<script>window.location.href="' . esc_url($url) . '";</script>';
    exit;
}

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
    ?>
    <!DOCTYPE html>
    <html <?php language_attributes(); ?>>
    <head>
        <meta charset="<?php bloginfo('charset'); ?>">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?php echo esc_html(get_bloginfo('name')); ?> - Reading the Room</title>
        
        <?php
        $css_url = DR_RTR_PLUGIN_URL . 'admin/css/main.css';
        if (file_exists(DR_RTR_ADMIN_DIR . 'css/main.css')) {
            echo '<link rel="stylesheet" href="' . esc_url($css_url) . '?ver=' . DR_RTR_VERSION . '">';
        }
        
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