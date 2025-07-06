<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @package CPD_Dashboard
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CPD_Admin {

    private $plugin_name;
    private $version;
    private $data_provider;
    private $wpdb;
    private $log_table;
    private $client_table;
    private $user_client_table;
    private static $page_content_rendered = false;

    public function __construct( $plugin_name, $version ) {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->log_table = $this->wpdb->prefix . 'cpd_action_logs';
        $this->client_table = $this->wpdb->prefix . 'cpd_clients';
        $this->user_client_table = $this->wpdb->prefix . 'cpd_client_users';

        // Initialize the data provider
        require_once CPD_DASHBOARD_PLUGIN_DIR . 'includes/class-cpd-data-provider.php'; // Ensure it's loaded
        $this->data_provider = new CPD_Data_Provider();

        // Add hooks for form processing (AJAX and non-AJAX)
        add_action( 'admin_post_cpd_add_client', array( $this, 'handle_add_client_submission' ) );
        add_action( 'admin_post_cpd_delete_client', array( $this, 'handle_delete_client_submission' ) );
        add_action( 'admin_post_cpd_add_user', array( $this, 'handle_add_user_submission' ) );
        add_action( 'admin_post_cpd_delete_user', array( $this, 'handle_delete_user_submission' ) );
        
        // NEW: Hook for manual settings submission
        add_action( 'admin_post_cpd_save_general_settings', array( $this, 'handle_save_general_settings' ) );
        add_action( 'admin_post_nopriv_cpd_save_general_settings', array( $this, 'handle_save_general_settings' ) ); // For logged out users, though settings are admin only

        // AJAX hooks for Admin dashboard interactivity
        add_action( 'wp_ajax_cpd_get_dashboard_data', array( $this, 'ajax_get_dashboard_data' ) );
        add_action( 'wp_ajax_cpd_ajax_add_client', array( $this, 'ajax_handle_add_client' ) );
        add_action( 'wp_ajax_nopriv_cpd_ajax_add_client', array( $this, 'ajax_handle_add_client' ) ); 
        add_action( 'wp_ajax_cpd_ajax_edit_client', array( $this, 'ajax_handle_edit_client' ) );
        add_action( 'wp_ajax_cpd_ajax_delete_client', array( $this, 'ajax_handle_delete_client' ) );
        add_action( 'wp_ajax_cpd_get_clients', array( $this, 'ajax_get_clients' ) );
        add_action( 'wp_ajax_cpd_ajax_add_user', array( $this, 'ajax_handle_add_user' ) );
        add_action( 'wp_ajax_nopriv_cpd_ajax_add_user', array( $this, 'ajax_handle_add_user' ) ); 
        add_action( 'wp_ajax_cpd_ajax_edit_user', array( $this, 'ajax_handle_edit_user' ) );
        add_action( 'wp_ajax_cpd_ajax_delete_user', array( $this, 'ajax_handle_delete_user' ) );
        add_action( 'wp_ajax_cpd_get_users', array( $this, 'ajax_get_users' ) );
        add_action( 'wp_ajax_cpd_get_client_list', array( $this, 'ajax_get_client_list' ) );

        // NEW: AJAX for API token generation
        add_action( 'wp_ajax_cpd_generate_api_token', array( $this, 'ajax_generate_api_token' ) );

        // NEW: CRM Email AJAX actions
        add_action( 'wp_ajax_cpd_get_eligible_visitors', array( 'CPD_Email_Handler', 'ajax_get_eligible_visitors' ) );
        add_action( 'wp_ajax_cpd_trigger_on_demand_send', array( 'CPD_Email_Handler', 'ajax_trigger_on_demand_send_webhook' ) );

        // Enqueue styles/scripts - FIXED: Use global hooks for all admin pages
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        
        // Force admin styles for all our pages
        add_action( 'admin_head', array( $this, 'force_admin_styles' ) );

        // Add admin menus
        add_action( 'admin_menu', array( $this, 'add_plugin_admin_menu' ) );
       
        // Handle dashboard redirect
        add_action( 'admin_init', array( $this, 'handle_dashboard_redirect' ) );
        
        // Hook for scheduling daily CRM emails
        add_action( 'cpd_daily_crm_email_event', array( 'CPD_Email_Handler', 'daily_crm_email_cron_callback' ) );

    }

    /**
     * AJAX handler to get all clients for table refresh
     */
    public function ajax_get_clients() {
        if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'cpd_admin_nonce' ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed.' ) );
        }
        
        $clients = $this->data_provider->get_all_client_accounts();
        
        wp_send_json_success( array( 'clients' => $clients ) );
    }

    /**
     * AJAX handler to get all users for table refresh
     */
    public function ajax_get_users() {
        if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'cpd_admin_nonce' ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed.' ) );
        }
        
        $all_users = get_users( array( 'role__in' => array( 'administrator', 'client' ) ) );
        $current_user_id = get_current_user_id();
        
        // Prepare user data with linked client information
        $formatted_users = array();
        foreach ( $all_users as $user ) {
            $user_client_account_id = $this->data_provider->get_account_id_by_user_id( $user->ID );
            $linked_client_name = 'N/A';
            
            if ( $user_client_account_id ) {
                $linked_client_obj = $this->data_provider->get_client_by_account_id( $user_client_account_id );
                if ( $linked_client_obj ) {
                    $linked_client_name = $linked_client_obj->client_name;
                }
            }
            
            $formatted_users[] = array(
                'ID' => $user->ID,
                'user_login' => $user->user_login,
                'user_email' => $user->user_email,
                'roles' => $user->roles,
                'client_account_id' => $user_client_account_id,
                'linked_client_name' => $linked_client_name,
                'can_delete' => ( $user->ID !== $current_user_id ) // Prevent deleting current user
            );
        }
        
        wp_send_json_success( array( 'users' => $formatted_users ) );
    }

    /**
     * Check if we're on one of our admin pages
     */
    private function is_our_admin_page() {
        $screen = get_current_screen();
        
        // Check by page parameter first (most reliable)
        if ( isset( $_GET['page'] ) ) {
            $page = $_GET['page'];
            $our_pages = array(
                $this->plugin_name,
                $this->plugin_name . '-management',
                $this->plugin_name . '-settings'
            );
            
            if ( in_array( $page, $our_pages ) ) {
                return true;
            }
        }
        
        // Fallback: Check by screen ID
        if ( $screen ) {
            $our_screen_patterns = array(
                'cpd-dashboard',
                'cpd_dashboard',
                'campaign-dashboard'
            );
            
            foreach ( $our_screen_patterns as $pattern ) {
                if ( strpos( $screen->id, $pattern ) !== false ) {
                    return true;
                }
            }
        }
        
        return false;
    }

    /**
     * Enqueue the admin stylesheets for the admin area.
     */
    public function enqueue_styles() {
        // Check if we're on one of our admin pages
        if ( ! $this->is_our_admin_page() ) {
            return;
        }

        // Enqueue external dependencies
        wp_enqueue_style( 'google-montserrat', 'https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap', array(), null );
        wp_enqueue_style( 'font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css', array(), '5.15.4', 'all' );
        
        // Use filemtime() for version to ensure cache busting on CSS file changes
        $style_path = CPD_DASHBOARD_PLUGIN_DIR . 'assets/css/cpd-admin-dashboard.css';
        $style_version = file_exists( $style_path ) ? filemtime( $style_path ) : $this->version;

        // Enqueue admin-specific stylesheet with high priority
        wp_enqueue_style( 
            $this->plugin_name . '-admin', 
            CPD_DASHBOARD_PLUGIN_URL . 'assets/css/cpd-admin-dashboard.css', 
            array(), 
            $style_version, 
            'all' 
        );
        
        // Add inline style to ensure our styles take precedence
        wp_add_inline_style( $this->plugin_name . '-admin', '
            /* Force our admin styles to load */
            body.wp-admin { font-family: "Montserrat", sans-serif !important; }
        ' );
    }

    /**
     * Force admin styles and hide default WP elements directly in admin_head.
     */
    public function force_admin_styles() {
        // Check if we're on one of our admin pages
        if ( ! $this->is_our_admin_page() ) {
            return;
        }

        // Only add minimal critical CSS that absolutely must be inline
        echo '<style type="text/css" id="cpd-admin-critical-styles">
            /* Critical styles that must load immediately */
            body.wp-admin { 
                font-family: "Montserrat", sans-serif !important;
                background-color: #eef2f6 !important;
            }
            
            /* Ensure our CSS file is loaded if it failed to enqueue */
            @import url("' . CPD_DASHBOARD_PLUGIN_URL . 'assets/css/cpd-admin-dashboard.css");
        </style>';
    }

    /**
     * Enqueue the admin JavaScript files for the admin area.
     */
    public function enqueue_scripts() {
        // Check if we're on one of our admin pages
        if ( ! $this->is_our_admin_page() ) {
            return;
        }

        wp_enqueue_script( 'jquery' );
        wp_enqueue_script( 'chart-js', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js', array(), '4.4.1', true );
        
        // Use filemtime() for version to ensure cache busting on file changes
        $script_path = CPD_DASHBOARD_PLUGIN_DIR . 'assets/js/cpd-dashboard.js';
        $script_version = file_exists( $script_path ) ? filemtime( $script_path ) : $this->version;

        wp_enqueue_script( $this->plugin_name . '-admin', CPD_DASHBOARD_PLUGIN_URL . 'assets/js/cpd-dashboard.js', array( 'jquery', 'chart-js' ), $script_version, true );
        
        
        // Localize script to pass data to JS
        wp_localize_script(
            $this->plugin_name . '-admin',
            'cpd_admin_ajax',
            array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'cpd_admin_nonce' ),
                'dashboard_url' => get_option( 'cpd_client_dashboard_url', '' ), // Pass the dashboard URL to JS
            )
        );


        // Add a separate nonce for dashboard data fetching
        wp_localize_script(
            $this->plugin_name . '-admin',
            'cpd_dashboard_ajax_nonce', // New object for dashboard data specific nonce
            array(
                'nonce' => wp_create_nonce( 'cpd_get_dashboard_data_nonce' ), // Specific nonce for dashboard data
            )
        );
    }

    /**
     * Add the top-level menu page for the plugin in the admin dashboard.
     */
    public function add_plugin_admin_menu() {

        // First, register the top-level menu item.
        // The callback is important: it should lead to your main dashboard page.
        // If this menu item is just a placeholder to create submenus, it often needs a specific callback.
        // Since you're redirecting to a public page, '__return_empty_string' is fine here.
        add_menu_page(
            'Campaign Dashboard',             // Page title
            'Campaign Dashboard',             // Menu title
            'read',                           // Capability required to see this menu item
            $this->plugin_name,               // Unique menu slug: 'cpd-dashboard'
            '__return_empty_string',          // This callback is used when clicking the top-level menu item directly
            'dashicons-chart-bar',            // Icon URL or Dashicon class
            6                                 // Position in the menu order
        );

        // Now, add the actual submenu pages.
        // The first submenu often uses the same slug as the parent to appear as the primary link
        // when the parent menu is clicked.
        add_submenu_page(
            $this->plugin_name,                 // Parent slug: 'cpd-dashboard'
            'Campaign Dashboard',                // Page title for this specific submenu
            'Dashboard',                         // Menu title for this submenu (e.g., 'Dashboard')
            'read',                              // Capability (same as parent, or higher if needed)
            $this->plugin_name,                 // !!! IMPORTANT: Use the SAME SLUG as the parent menu here for the first submenu. This makes it the default view when the parent is clicked.
            '__return_empty_string'             // Callback for this submenu (will be handled by redirect)
        );


        add_submenu_page(
            $this->plugin_name,                 // Parent slug: 'cpd-dashboard'
            'Client & User Management',          // Page title
            'Management',                        // Menu title
            'manage_options',                    // Capability - only admins can see this
            $this->plugin_name . '-management',  // Menu slug (e.g., 'cpd-dashboard-management')
            array( $this, 'render_admin_management_page' ) // Callback for management content
        );
        
        /*
        add_submenu_page(
            $this->plugin_name,
            'Dashboard Settings',
            'Settings',
            'manage_options',
            $this->plugin_name . '-settings',
            array( $this, 'render_settings_page' )
        );
        */
    }

    /**
     * Handles redirection from the admin dashboard menu item to the public dashboard.
     * This runs on 'admin_init' to ensure early redirection before headers are sent.
     */
    public function handle_dashboard_redirect() {
        // Check if we are on the correct admin page that should redirect
        if ( is_admin() && isset( $_GET['page'] ) && $_GET['page'] === $this->plugin_name ) {
            $client_dashboard_url = get_option( 'cpd_client_dashboard_url' );
            if ( $client_dashboard_url ) {
                wp_redirect( esc_url_raw( $client_dashboard_url ) );
                exit; // Crucial to exit after redirect
            } else {
                // If URL is not set, display a message on the admin page itself
                add_action( 'admin_notices', function() {
                    echo '<div class="notice notice-error is-dismissible"><p><strong>Campaign Dashboard Error:</strong> Public Dashboard URL is not set. Please go to <a href="' . esc_url( admin_url( 'admin.php?page=' . $this->plugin_name . '-management#settings' ) ) . '">Settings</a> to configure it.</p></div>';
                });
            }
        }
    }

    /**
     * Render the admin management page content from the HTML template.
     * This will now contain ONLY management sections.
     */
    public function render_admin_management_page() {
        
        // Add a unique identifier each time this function is called
        static $call_count = 0;
        $call_count++;
        error_log("render_admin_management_page called. Count: " . $call_count); 
        
        // Ensure user has capability to view this page
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'You do not have sufficient permissions to access this page.' );
        }

        static $template_included = false;

        if ( $template_included ) {
            error_log("render_admin_management_page: Template already included in this request. Skipping.");
            return; // Already included, prevent re-inclusion
        }

        // Set the flag to true before including
        $template_included = true;

        $plugin_name = $this->plugin_name;
        $all_clients = $this->data_provider->get_all_client_accounts();
        $logs = $this->get_all_logs();
        $all_users = get_users( array( 'role__in' => array( 'administrator', 'client' ) ) );
        $all_client_accounts_for_dropdown = $this->data_provider->get_all_client_accounts();
        $data_provider = $this->data_provider;

        // Include the admin page template (this should now contain only management sections)
        include CPD_DASHBOARD_PLUGIN_DIR . 'admin/views/admin-page.php';
    }

    /**
     * Render the admin page content from the HTML template.
     * This method is now effectively deprecated or will be replaced by redirect.
     */
    public function render_admin_page() {
        // This method is now effectively a placeholder. The redirect logic is in handle_dashboard_redirect.
        // It will render an empty page, allowing the admin_notices to display if the URL is not set.
        // WordPress requires a callback function for add_menu_page, even if it does nothing.
        // The actual content is handled by the redirect or the admin_notices.
    }
    
    
    /**
     * Handles the form submission for general settings.
     * This replaces the WordPress Settings API for these options.
     */
/**
     * Handles the form submission for general settings.
     * This replaces the WordPress Settings API for these options.
     * UPDATED: Now includes referrer logo mappings handling
     */
    public function handle_save_general_settings() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'You do not have sufficient permissions to access this page.' );
        }

        // Verify nonce for security
        if ( ! isset( $_POST['_wpnonce_cpd_settings'] ) || ! wp_verify_nonce( $_POST['_wpnonce_cpd_settings'], 'cpd_save_general_settings_nonce' ) ) {
            wp_die( 'Security check failed.' );
        }

        // Retrieve and sanitize each option
        $client_dashboard_url = isset( $_POST['cpd_client_dashboard_url'] ) ? esc_url_raw( $_POST['cpd_client_dashboard_url'] ) : '';
        $report_problem_email = isset( $_POST['cpd_report_problem_email'] ) ? sanitize_email( $_POST['cpd_report_problem_email'] ) : '';
        $api_key = isset( $_POST['cpd_api_key'] ) ? sanitize_text_field( $_POST['cpd_api_key'] ) : ''; // API key is typically managed by the generate button
        $default_campaign_duration = isset( $_POST['default_campaign_duration'] ) ? sanitize_text_field( $_POST['default_campaign_duration'] ) : 'campaign';
        $enable_notifications = isset( $_POST['enable_notifications'] ) ? sanitize_text_field( $_POST['enable_notifications'] ) : 'yes';
        $crm_email_schedule_hour = isset( $_POST['cpd_crm_email_schedule_hour'] ) ? sanitize_text_field( $_POST['cpd_crm_email_schedule_hour'] ) : '09';

        // Handle referrer logo mappings
        $referrer_domains = isset( $_POST['referrer_domains'] ) && is_array( $_POST['referrer_domains'] ) ? array_map( 'sanitize_text_field', $_POST['referrer_domains'] ) : array();
        $referrer_logos = isset( $_POST['referrer_logos'] ) && is_array( $_POST['referrer_logos'] ) ? array_map( 'esc_url_raw', $_POST['referrer_logos'] ) : array();
        $show_direct_logo = isset( $_POST['cpd_show_direct_logo'] ) ? 1 : 0;
        
        // Handle Make.com webhook settings
        $webhook_url = isset( $_POST['cpd_webhook_url'] ) ? esc_url_raw( $_POST['cpd_webhook_url'] ) : '';
        $makecom_api_key = isset( $_POST['cpd_makecom_api_key'] ) ? sanitize_text_field( $_POST['cpd_makecom_api_key'] ) : '';
        
        // Combine domains and logos into associative array
        $referrer_logo_mappings = array();
        for ( $i = 0; $i < count( $referrer_domains ); $i++ ) {
            if ( !empty( $referrer_domains[$i] ) && !empty( $referrer_logos[$i] ) ) {
                // Clean domain (remove www. and protocols)
                $clean_domain = strtolower( trim( $referrer_domains[$i] ) );
                $clean_domain = preg_replace( '/^https?:\/\//', '', $clean_domain );
                $clean_domain = preg_replace( '/^www\./', '', $clean_domain );
                $clean_domain = rtrim( $clean_domain, '/' );
                
                $referrer_logo_mappings[$clean_domain] = $referrer_logos[$i];
            }
        }

        // Update options using WordPress's Options API
        update_option( 'cpd_client_dashboard_url', $client_dashboard_url );
        update_option( 'cpd_report_problem_email', $report_problem_email );
        
        // Do NOT update cpd_api_key here, as it's generated via AJAX, not direct form submission
        update_option( 'default_campaign_duration', $default_campaign_duration );
        update_option( 'enable_notifications', $enable_notifications );
        update_option( 'cpd_crm_email_schedule_hour', $crm_email_schedule_hour );
        
        // Update referrer logo options
        update_option( 'cpd_referrer_logo_mappings', $referrer_logo_mappings );
        update_option( 'cpd_show_direct_logo', $show_direct_logo );

        // Update Make.com webhook settings
        update_option( 'cpd_webhook_url', $webhook_url );
        update_option( 'cpd_makecom_api_key', $makecom_api_key );

        // Log the action
        $this->log_action( get_current_user_id(), 'SETTINGS_UPDATED', 'General dashboard settings were updated including Make.com webhook URL, API key, and referrer logo mappings.' );

        // Redirect back to the settings page with a success message
        wp_redirect( admin_url( 'admin.php?page=' . $this->plugin_name . '-management#settings&message=settings_saved' ) );
        exit;
    }

    /**
     * Fetches all log entries from the database.
     */
    private function get_all_logs() {
        return $this->wpdb->get_results( "SELECT * FROM {$this->log_table} ORDER BY timestamp DESC LIMIT 500" );
    }

    /**
     * Logs an action to the database.
     * This is made public so CPD_API can use it.
     */
    public function log_action( $user_id, $action_type, $description ) {
        $this->wpdb->insert(
            $this->log_table,
            array(
                'user_id'     => $user_id,
                'action_type' => $action_type,
                'description' => $description,
            ),
            array('%d', '%s', '%s')
        );
    }
    /**
     * Handle the form submission for adding a new client.
     */
    public function handle_add_client_submission() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'You do not have sufficient permissions to access this page.' ); }
        if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'cpd_add_client_nonce' ) ) { wp_die( 'Security check failed.' ); }
        $client_name = sanitize_text_field( $_POST['client_name'] ); $account_id = sanitize_text_field( $_POST['account_id'] ); $logo_url = esc_url_raw( $_POST['logo_url'] ); $webpage_url = esc_url_raw( $_POST['webpage_url'] ); $crm_feed_email = sanitize_text_field( $_POST['crm_feed_email'] );
        $result = $this->wpdb->insert( $this->client_table, array( 'account_id' => $account_id, 'client_name' => $client_name, 'logo_url' => $logo_url, 'webpage_url' => $webpage_url, 'crm_feed_email' => $crm_feed_email, ) );
        $user_id = get_current_user_id();
        if ( $result ) { $this->log_action( $user_id, 'CLIENT_ADDED', 'Client "' . $client_name . '" (ID: ' . $account_id . ') was added.' ); wp_redirect( admin_url( 'admin.php?page=' . $this->plugin_name . '-management&message=client_added' ) ); } else { $this->log_action( $user_id, 'CLIENT_ADD_FAILED', 'Failed to add client "' . $client_name . '".' ); wp_redirect( admin_url( 'admin.php?page=' . $this->plugin_name . '-management&error=client_add_failed' ) ); }
        exit;
    }
    
    /**
     * AJAX handler for adding a new client.
     */
    public function ajax_handle_add_client() {
        if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'cpd_admin_nonce' ) ) { wp_send_json_error( array( 'message' => 'Security check failed.' ) ); }
        $this->wpdb->hide_errors();
        $client_name = sanitize_text_field( $_POST['client_name'] ); $account_id = sanitize_text_field( $_POST['account_id'] ); $logo_url = esc_url_raw( $_POST['logo_url'] ); $webpage_url = esc_url_raw( $_POST['webpage_url'] ); $crm_feed_email = sanitize_text_field( $_POST['crm_feed_email'] );
        $result = $this->wpdb->insert( $this->client_table, array( 'account_id' => $account_id, 'client_name' => $client_name, 'logo_url' => $logo_url, 'webpage_url' => $webpage_url, 'crm_feed_email' => $crm_feed_email, ) );
        $user_id = get_current_user_id();
        if ( $result ) { $this->log_action( $user_id, 'CLIENT_ADDED', 'Client "' . $client_name . '" (ID: ' . $account_id . ') was added via AJAX.' ); wp_send_json_success( array( 'message' => 'Client added successfully!' ) ); } else { $this->log_action( $user_id, 'CLIENT_ADD_FAILED', 'Failed to add client "' . $client_name . '" via AJAX.' ); wp_send_json_error( array( 'message' => 'Failed to add client. Account ID might already exist.' ) ); }
    }

    /**
     * Handle the form submission for deleting a client.
     */
    public function handle_delete_client_submission() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'You do not have sufficient permissions to access this page.' ); }
        if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'cpd_delete_client_nonce' ) ) { wp_die( 'Security check failed.' ); }
        $client_id = isset( $_POST['client_id'] ) ? intval( $_POST['client_id'] ) : 0;
        if ( $client_id <= 0 ) { wp_redirect( admin_url( 'admin.php?page=' . $this->plugin_name . '-management&error=invalid_client_id' ) ); exit; }
        $client_name = $this->wpdb->get_var( $this->wpdb->prepare( "SELECT client_name FROM $this->client_table WHERE id = %d", $client_id ) );
        $this->wpdb->delete( $this->client_table, array( 'id' => $client_id ), array( '%d' ) );
        $this->wpdb->delete( $this->user_client_table, array( 'client_id' => $client_id ), array( '%d' ) );
        $user_id = get_current_user_id();
        $this->log_action( $user_id, 'CLIENT_DELETED', 'Client "' . $client_name . '" (ID: ' . $client_id . ') was deleted.' );
        wp_redirect( admin_url( 'admin.php?page=' . $this->plugin_name . '-management&message=client_deleted' ) );
        exit;
    }
    
    /**
     * AJAX handler for deleting a client.
     */
    public function ajax_handle_delete_client() {
        if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'cpd_admin_nonce' ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed.' ) );
        }
        $client_id = isset( $_POST['client_id'] ) ? intval( $_POST['client_id'] ) : 0;
        if ( $client_id <= 0 ) { wp_send_json_error( array( 'message' => 'Invalid client ID.' ) ); }
        $client_name = $this->wpdb->get_var( $this->wpdb->prepare( "SELECT client_name FROM $this->client_table WHERE id = %d", $client_id ) );
        $deleted_client = $this->wpdb->delete( $this->client_table, array( 'id' => $client_id ), array( '%d' ) );
        if ($deleted_client) {
            $this->wpdb->delete( $this->user_client_table, array( 'client_id' => $client_id ), array( '%d' ) );
            $this->log_action( get_current_user_id(), 'CLIENT_DELETED_AJAX', 'Client "' . $client_name . '" (ID: ' . $client_id . ') was deleted via AJAX.' );
            wp_send_json_success( array( 'message' => 'Client deleted successfully!' ) );
        } else {
            $this->log_action( get_current_user_id(), 'CLIENT_DELETE_FAILED_AJAX', 'Failed to delete client "' . $client_name . '" via AJAX.' );
            wp_send_json_error( array( 'message' => 'Failed to delete client.' ) );
        }
    }
    
    /**
     * AJAX handler for editing a client.
     */
    public function ajax_handle_edit_client() {
        if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'cpd_admin_nonce' ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed.' ) );
        }
        
        $client_id = isset( $_POST['client_id'] ) ? intval( $_POST['client_id'] ) : 0;
        if ( $client_id <= 0 ) { wp_send_json_error( array( 'message' => 'Invalid client ID.' ) ); }
        
        $client_name = sanitize_text_field( $_POST['client_name'] );
        $logo_url = esc_url_raw( $_POST['logo_url'] );
        $webpage_url = esc_url_raw( $_POST['webpage_url'] );
        $crm_feed_email = sanitize_text_field( $_POST['crm_feed_email'] );
        
        $updated = $this->wpdb->update(
            $this->client_table,
            array(
                'client_name' => $client_name,
                'logo_url' => $logo_url,
                'webpage_url' => $webpage_url,
                'crm_feed_email' => $crm_feed_email,
            ),
            array( 'id' => $client_id ),
            array( '%s', '%s', '%s', '%s' ),
            array( '%d' )
        );

        $user_id = get_current_user_id();
        if ( $updated !== false ) {
            $this->log_action( $user_id, 'CLIENT_EDITED_AJAX', 'Client ID ' . $client_id . ' was edited via AJAX.' );
            wp_send_json_success( array( 'message' => 'Client updated successfully!' ) );
        } else {
            $this->log_action( $user_id, 'CLIENT_EDIT_FAILED_AJAX', 'Failed to edit client ID ' . $client_id . ' via AJAX.' );
            wp_send_json_error( array( 'message' => 'Failed to update client.' ) );
        }
    }
    
    /**
     * Handle the form submission for adding a new user.
     */
    public function handle_add_user_submission() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'You do not have sufficient permissions to access this page.' ); }
        if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'cpd_add_user_nonce' ) ) { wp_die( 'Security check failed.' ); }
        $username = sanitize_user( $_POST['username'] ); $email = sanitize_email( $_POST['email'] ); $password = $_POST['password']; $role = sanitize_text_field( $_POST['role'] ); $client_account_id = sanitize_text_field( $_POST['client_account_id'] );
        if ( empty( $username ) || empty( $email ) || empty( $password ) ) { wp_redirect( admin_url( 'admin.php?page=' . $this->plugin_name . '-management&error=missing_user_fields' ) ); exit; }
        $user_id = wp_create_user( $username, $password, $email );
        if ( ! is_wp_error( $user_id ) ) {
            $user = new WP_User( $user_id ); $user->set_role( $role );
            if ( ! empty( $client_account_id ) ) { $client = $this->data_provider->get_client_by_account_id( $client_account_id ); if ($client) { $this->wpdb->insert( $this->user_client_table, array( 'user_id' => $user_id, 'client_id' => $client->id ) ); } }
            $this->log_action( get_current_user_id(), 'USER_ADDED', 'User "' . $username . '" (ID: ' . $user_id . ') was created with role "' . $role . '".' );
            wp_redirect( admin_url( 'admin.php?page=' . $this->plugin_name . '-management&message=user_added' ) );
        } else {
            $this->log_action( get_current_user_id(), 'USER_ADD_FAILED', 'Failed to create user "' . $username . '": ' . $user_id->get_error_message() ); wp_redirect( admin_url( 'admin.php?page=' . $this->plugin_name . '-management&error=user_add_failed&reason=' . urlencode($user_id->get_error_message()) ) ); }
        exit;
    }
    
    /**
     * AJAX handler for adding a new user.
     */
    public function ajax_handle_add_user() {
        if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'cpd_admin_nonce' ) ) { wp_send_json_error( array( 'message' => 'Security check failed.' ) ); }
        $username = sanitize_user( $_POST['username'] ); $email = sanitize_email( $_POST['email'] ); $password = $_POST['password']; $role = sanitize_text_field( $_POST['role'] ); $client_account_id = sanitize_text_field( $_POST['client_account_id'] );
        if ( empty( $username ) || empty( $email ) || empty( $password ) ) { wp_send_json_error( array( 'message' => 'All fields are required.' ) ); }
        $user_id = wp_create_user( $username, $password, $email );
        if ( ! is_wp_error( $user_id ) ) {
            $user = new WP_User( $user_id ); $user->set_role( $role );
            if ( ! empty( $client_account_id ) ) { $client = $this->data_provider->get_client_by_account_id( $client_account_id ); if ($client) { $this->wpdb->insert( $this->user_client_table, array( 'user_id' => $user_id, 'client_id' => $client->id ) ); } }
            $this->log_action( get_current_user_id(), 'USER_ADDED', 'User "' . $username . '" (ID: ' . $user_id . ') was created via AJAX.' );
            wp_send_json_success( array( 'message' => 'User added successfully!', 'user_id' => $user_id ) );
        } else {
            $this->log_action( get_current_user_id(), 'USER_ADD_FAILED', 'Failed to create user "' . $username . '" via AJAX: ' . $user_id->get_error_message() ); wp_send_json_error( array( 'message' => $user_id->get_error_message() ) ); }
    }
    
    /**
     * Handle the form submission for deleting a user.
     */
    public function handle_delete_user_submission() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'You do not have sufficient permissions to access this page.' ); }
        if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'cpd_delete_user_nonce' ) ) { wp_die( 'Security check failed.' ); }
        $user_id_to_delete = isset( $_POST['user_id'] ) ? intval( $_POST['user_id'] ) : 0;
        if ( $user_id_to_delete <= 0 || $user_id_to_delete === get_current_user_id() ) { wp_redirect( admin_url( 'admin.php?page=' . $this->plugin_name . '-management&error=invalid_user_id' ) ); exit; }
        require_once( ABSPATH . 'wp-admin/includes/user.php' );
        $deleted = wp_delete_user( $user_id_to_delete );
        if ( $deleted ) {
            $this->wpdb->delete( $this->user_client_table, array( 'user_id' => $user_id_to_delete ), array( '%d' ) );
            $this->log_action( get_current_user_id(), 'USER_DELETED', 'User ID ' . $user_id_to_delete . ' was deleted.' );
            wp_redirect( admin_url( 'admin.php?page=' . $this->plugin_name . '-management&message=user_deleted&tab=users' ) );
        } else {
            $this->log_action( get_current_user_id(), 'USER_DELETE_FAILED', 'Failed to delete user ID ' . $user_id_to_delete . '.' ); wp_redirect( admin_url( 'admin.php?page=' . $this->plugin_name . '-management&error=user_delete_failed&tab=users' ) ); }
        exit;
    }
    
    /**
     * AJAX handler for deleting a user.
     */
    public function ajax_handle_delete_user() {
        if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'cpd_admin_nonce' ) ) { wp_send_json_error( array( 'message' => 'Security check failed.' ) ); }
        $user_id_to_delete = isset( $_POST['user_id'] ) ? intval( $_POST['user_id'] ) : 0;
        if ( $user_id_to_delete <= 0 || $user_id_to_delete === get_current_user_id() ) { wp_send_json_error( array( 'message' => 'Invalid user ID or cannot delete yourself.' ) ); }
        require_once( ABSPATH . 'wp-admin/includes/user.php' );
        $deleted = wp_delete_user( $user_id_to_delete );
        if ($deleted) {
            $this->wpdb->delete( $this->user_client_table, array( 'user_id' => $user_id_to_delete ), array( '%d' ) );
            $this->log_action( get_current_user_id(), 'USER_DELETED_AJAX', 'User ID ' . $user_id_to_delete . ' was deleted via AJAX.' );
            wp_send_json_success( array( 'message' => 'User deleted successfully!' ) );
        } else {
            $this->log_action( get_current_user_id(), 'USER_DELETE_FAILED_AJAX', 'Failed to delete user ID ' . $user_id_to_delete . ' via AJAX.' ); wp_send_json_error( array( 'message' => 'Failed to delete user.' ) ); }
    }

    /**
     * AJAX handler for editing a user.
     */
    public function ajax_handle_edit_user() {
        if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'cpd_admin_nonce' ) ) { wp_send_json_error( array( 'message' => 'Security check failed.' ) ); }
        $user_id = isset( $_POST['user_id'] ) ? intval( $_POST['user_id'] ) : 0;
        if ( $user_id <= 0 ) { wp_send_json_error( array( 'message' => 'Invalid user ID.' ) ); }
        $username = sanitize_user( $_POST['username'] ); $email = sanitize_email( $_POST['email'] ); $role = sanitize_text_field( $_POST['role'] ); $client_account_id = sanitize_text_field( $_POST['client_account_id'] );
        $user = get_user_by( 'id', $user_id );
        if ( ! $user ) { wp_send_json_error( array( 'message' => 'User not found.' ) ); }
        $updated_user_id = wp_update_user( array( 'ID' => $user_id, 'user_login' => $username, 'user_email' => $email, ) );
        if ( is_wp_error( $updated_user_id ) ) { wp_send_json_error( array( 'message' => $updated_user_id->get_error_message() ) ); }
        $user->set_role( $role );
        $this->wpdb->delete( $this->user_client_table, array( 'user_id' => $user_id ), array( '%d' ) );
        if ( ! empty( $client_account_id ) ) { $client = $this->data_provider->get_client_by_account_id( $client_account_id ); if ($client) { $this->wpdb->insert( $this->user_client_table, array( 'user_id' => $user_id, 'client_id' => $client->id ) ); } }
        $this->log_action( get_current_user_id(), 'USER_EDITED_AJAX', 'User ID ' . $user_id . ' was edited via AJAX.' );
        wp_send_json_success( array( 'message' => 'User updated successfully!' ) );
    }
    
    /**
     * AJAX handler for getting dashboard data.
     */

/**
     * AJAX handler for getting dashboard data.
     */
    public function ajax_get_dashboard_data() {
        // Change the nonce check here to match the new specific nonce
        if ( ! current_user_can( 'read' ) || ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'cpd_get_dashboard_data_nonce' ) ) { // ALL users (read cap) can get dashboard data
            wp_send_json_error( 'Security check failed.' );
        }

        // Allow 'all' or null for client_id to indicate aggregation across all clients
        $client_id = isset( $_POST['client_id'] ) && $_POST['client_id'] !== 'all' ? sanitize_text_field( $_POST['client_id'] ) : null;
        
        $duration = isset( $_POST['duration'] ) ? sanitize_text_field( $_POST['duration'] ) : 'campaign'; // Default to 'campaign'
        
        // Add debug logging
        error_log('ADMIN AJAX: Duration received: ' . $duration . ' (type: ' . gettype($duration) . ')');
        
        // Determine start and end dates based on duration
        $start_date = null;
        $end_date = null;

        if ( $duration === 'campaign' ) {
            // For campaign duration, get the actual campaign date range
            $campaign_date_range = $this->data_provider->get_campaign_date_range( $client_id );
            $start_date = $campaign_date_range->min_date ?? '2025-01-01';
            $end_date = $campaign_date_range->max_date ?? date('Y-m-d');
            error_log('ADMIN AJAX: Campaign duration - Start: ' . $start_date . ', End: ' . $end_date);
        } elseif ( $duration === '30' || $duration == 30 ) {
            $start_date = date('Y-m-d', strtotime('-30 days'));
            $end_date = date('Y-m-d');
            error_log('ADMIN AJAX: 30 days - Start: ' . $start_date . ', End: ' . $end_date);
        } elseif ( $duration === '7' || $duration == 7 ) {
            $start_date = date('Y-m-d', strtotime('-7 days'));
            $end_date = date('Y-m-d');
            error_log('ADMIN AJAX: 7 days - Start: ' . $start_date . ', End: ' . $end_date);
        } elseif ( $duration === '1' || $duration == 1 ) {
            $start_date = date('Y-m-d', strtotime('yesterday'));
            $end_date = date('Y-m-d', strtotime('yesterday'));
            error_log('ADMIN AJAX: YESTERDAY - Start: ' . $start_date . ', End: ' . $end_date);
        } else {
            // Default fallback
            error_log('ADMIN AJAX: Unknown duration, falling back to campaign dates');
            $campaign_date_range = $this->data_provider->get_campaign_date_range( $client_id );
            $start_date = $campaign_date_range->min_date ?? '2025-01-01';
            $end_date = $campaign_date_range->max_date ?? date('Y-m-d');
        }

        error_log('ADMIN AJAX: Final dates - Start: ' . $start_date . ', End: ' . $end_date);

        if (!empty($visitor_data)) {
            foreach ($visitor_data as $visitor) {
                // Add the logo URL for each visitor using the existing class
                $visitor->logo_url = CPD_Referrer_Logo::get_logo_for_visitor($visitor);
            }
        }
                
        $summary_metrics = $this->data_provider->get_summary_metrics( $client_id, $start_date, $end_date );
        $campaign_data_by_ad_group = $this->data_provider->get_campaign_data_by_ad_group( $client_id, $start_date, $end_date );
        $campaign_data_by_date = $this->data_provider->get_campaign_data_by_date( $client_id, $start_date, $end_date );
        $visitor_data = $this->data_provider->get_visitor_data( $client_id ); // This one needs special handling if 'all visitors' makes sense.

        // --- Get client logo URL ---
        $client_logo_url = CPD_DASHBOARD_PLUGIN_URL . 'assets/images/MEMO_Logo.png'; // Default
        if ( $client_id !== null ) { // If a specific client is selected
            $client_obj = $this->data_provider->get_client_by_account_id( $client_id );
            if ( $client_obj && ! empty( $client_obj->logo_url ) ) {
                $client_logo_url = esc_url( $client_obj->logo_url );
            }
        }
        
        wp_send_json_success( array(
            'summary_metrics' => $summary_metrics,
            'campaign_data' => $campaign_data_by_ad_group,
            'campaign_data_by_date' => $campaign_data_by_date,
            'visitor_data' => $visitor_data,
            'client_logo_url' => $client_logo_url,
        ) );
    }

    /**
     * AJAX handler to get the updated client list.
     */
    public function ajax_get_client_list() {
        if ( ! current_user_can( 'cpd_view_dashboard' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ) );
        }
        $clients = $this->data_provider->get_all_client_accounts();
        wp_send_json_success( array( 'clients' => $clients ) );
    }

    /**
     * AJAX callback to generate a new API token.
     */
    public function ajax_generate_api_token() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied.' );
        }

        // Generate a random, cryptographically secure token
        $new_token = wp_generate_uuid4(); // WordPress's built-in UUID generator is good for this
        // For a longer/stronger key, you could use bin2hex(random_bytes(32)) for 64 chars

        update_option( 'cpd_api_key', $new_token );

        // Log the token generation
        $this->log_action( get_current_user_id(), 'API_TOKEN_GENERATED', 'New API token generated for data import.' );

        wp_send_json_success( array( 'token' => $new_token ) );
    }

    /**
     * Get the plugin name for use in other classes
     */
    public static function get_plugin_name() {
        return 'cpd-dashboard';
    }

}