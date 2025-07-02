<?php
    /**
     * The public-facing functionality of the plugin.
     *
     * @package CPD_Dashboard
     */

    // Exit if accessed directly.
    if ( ! defined( 'ABSPATH' ) ) {
        exit;
    }

class CPD_Public {

    private $plugin_name;
    private $version;
    private $data_provider;
    
    public function __construct( $plugin_name, $version ) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        // Initialize the data provider here, as it's used in enqueue_scripts_data and display_dashboard
        require_once CPD_DASHBOARD_PLUGIN_DIR . 'includes/class-cpd-data-provider.php';
        $this->data_provider = new CPD_Data_Provider(); 
        
        // Add AJAX handlers for front-end.
        add_action( 'wp_ajax_cpd_update_visitor_status', array( $this, 'update_visitor_status_callback' ) );
        add_action( 'wp_ajax_nopriv_cpd_update_visitor_status', array( $this, 'update_visitor_status_callback' ) );
        

        // Add scripts and styles with a high priority to load after the theme's.
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ), 99 );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts_data' ) ); // Localizes script data
        
        // Add login redirect filter.
        add_filter( 'login_redirect', array( $this, 'login_redirect_by_role' ), 10, 3 );
        
        // Hide admin bar and add body class
        add_filter( 'body_class', array( $this, 'add_dashboard_body_class' ) );
        add_filter( 'show_admin_bar', array( $this, 'hide_admin_bar_on_dashboard' ) );
        
        // Force hide admin bar with action
        add_action( 'wp_head', array( $this, 'force_hide_admin_bar' ) );

        // Register shortcode
        add_shortcode( 'campaign_dashboard', array( $this, 'display_dashboard' ) );
    }

    /**
     * Hide admin bar on dashboard pages
     */
    public function hide_admin_bar_on_dashboard( $show ) {
        global $post;
        if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'campaign_dashboard' ) ) {
            return false;
        }
        return $show;
    }

    /**
     * Force hide admin bar with CSS
     */
    public function force_hide_admin_bar() {
        global $post;
        if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'campaign_dashboard' ) ) {
            echo '<style>
                html { margin-top: 0 !important; }
                #wpadminbar { display: none !important; }
                body.admin-bar { margin-top: 0 !important; }
            </style>';
        }
    }

    /**
     * Adds a custom body class to the dashboard page for styling.
     */
    public function add_dashboard_body_class( $classes ) {
        global $post;
        if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'campaign_dashboard' ) ) {
            $classes[] = 'client-dashboard-page';
        }
        return $classes;
    }

    /**
     * Enqueue the public-facing stylesheets and dequeue theme styles on our page.
     */
    public function enqueue_styles() {
        global $post;

        if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'campaign_dashboard' ) ) {
            wp_dequeue_style( 'twentytwentyfive-style' );
            wp_deregister_style( 'twentytwentyfive-style' );
            wp_dequeue_style( 'blocksy-style' );
            wp_deregister_style( 'blocksy-style' );
            wp_dequeue_style( 'theme-style' );
            wp_deregister_style( 'theme-style' );

            wp_enqueue_style( 'google-montserrat', 'https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap', array(), null );
            wp_enqueue_style( 'font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css', array(), '5.15.4', 'all' );
            // UPDATED: Enqueue public-specific stylesheet
            wp_enqueue_style( $this->plugin_name . '-public', CPD_DASHBOARD_PLUGIN_URL . 'assets/css/cpd-public-dashboard.css', array(), $this->version, 'all' );
            
            if ( current_user_can( 'manage_options' ) ) { // Load only for administrators
                $style_path_admin = CPD_DASHBOARD_PLUGIN_DIR . 'assets/css/cpd-admin-dashboard.css';
                $style_version_admin = file_exists( $style_path_admin ) ? filemtime( $style_path_admin ) : $this->version;

                wp_enqueue_style(
                    $this->plugin_name . '-admin', // Use the same handle as in CPD_Admin for consistency
                    CPD_DASHBOARD_PLUGIN_URL . 'assets/css/cpd-admin-dashboard.css',
                    array(), // No specific dependencies, Font Awesome is already loaded
                    $style_version_admin,
                    'all'
                );
            }

            wp_add_inline_style( $this->plugin_name . '-public', '
                body, html { margin: 0 !important; padding: 0 !important; overflow: hidden !important; }
                #wpadminbar { display: none !important; }
                .site-header, .site-footer { display: none !important; }
            ' );
        }
    }

    /**
     * Enqueue the public-facing JavaScript files.
     */
    public function enqueue_scripts() {
        global $post;
        if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'campaign_dashboard' ) ) {
            wp_enqueue_script( 'chart-js', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js', array(), '4.4.1', true );
            
            // Use filemtime() for version to ensure cache busting on file changes
            $script_path = CPD_DASHBOARD_PLUGIN_DIR . 'assets/js/cpd-public-dashboard.js';
            $script_version = file_exists( $script_path ) ? filemtime( $script_path ) : $this->version;

            wp_enqueue_script( $this->plugin_name . '-public', CPD_DASHBOARD_PLUGIN_URL . 'assets/js/cpd-public-dashboard.js', array( 'jquery', 'chart-js' ), $script_version, true );

            if ( current_user_can( 'manage_options' ) ) { // Load only for administrators
                $script_path_admin = CPD_DASHBOARD_PLUGIN_DIR . 'assets/js/cpd-dashboard.js';
                $script_version_admin = file_exists( $script_path_admin ) ? filemtime( $script_path_admin ) : $this->version;

                wp_enqueue_script( $this->plugin_name . '-admin', CPD_DASHBOARD_PLUGIN_URL . 'assets/js/cpd-dashboard.js', array( 'jquery', 'chart-js' ), $script_version_admin, true );
                
                // Also localize the admin AJAX data, which is needed by cpd-dashboard.js
                // Keep cpd_admin_ajax for admin-specific actions handled by cpd-dashboard.js
                wp_localize_script(
                    $this->plugin_name . '-admin',
                    'cpd_admin_ajax', // This matches the name used in cpd-dashboard.js
                    array(
                        'ajax_url' => admin_url( 'admin-ajax.php' ),
                        'nonce' => wp_create_nonce( 'cpd_admin_nonce' ), // Admin specific nonce for admin management actions
                        'memo_seal_url' => CPD_DASHBOARD_PLUGIN_URL . 'assets/images/MEMO_Seal.png',
                    )
                );

                // Add a separate nonce for dashboard data fetching (used by both admin & client JS for dashboard data fetch)
                wp_localize_script(
                    $this->plugin_name . '-admin', // Associate with admin script since it uses this nonce
                    'cpd_dashboard_ajax_nonce',
                    array(
                        'nonce' => wp_create_nonce( 'cpd_get_dashboard_data_nonce' ),
                    )
                );
            }
        }
    }

    /**
     * Localize script with AJAX URL, nonce, and dashboard data for public-facing dashboard.
     */
    public function enqueue_scripts_data() {
        global $post;
        if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'campaign_dashboard' ) ) {
            $client_account = null;
            $current_user = wp_get_current_user(); 
            $is_admin = current_user_can( 'manage_options' ); 
            $selected_client_id_from_url = isset($_GET['client_id']) ? sanitize_text_field($_GET['client_id']) : null; 
            
            if ( $is_admin ) {
                if ( $selected_client_id_from_url && $selected_client_id_from_url !== 'all' ) {
                    $client_account = $this->data_provider->get_client_by_account_id( $selected_client_id_from_url );
                } else {
                    $client_account = null;
                }
            } else { // Client role logic
                $account_id = $this->data_provider->get_account_id_by_user_id( $current_user->ID ); 
                if ( $account_id ) { 
                    $client_account = $this->data_provider->get_client_by_account_id( $account_id ); 
                } 
            }

            $campaign_data_by_ad_group = [];
            $campaign_data_by_date = [];
            $summary_metrics = [];
            $visitor_data = [];

            $target_account_id_for_data = $client_account ? $client_account->account_id : null; 

            // Only fetch initial data if it's NOT an admin OR if an admin is viewing a specific client's data on the public page (not 'all')
            if ( ! $is_admin || ($is_admin && $target_account_id_for_data !== null) ) { 
                if ($target_account_id_for_data !== null) { 
                    $start_date = '2025-01-01'; 
                    $end_date = date('Y-m-d');
                    
                    $campaign_data_by_ad_group = $this->data_provider->get_campaign_data_by_ad_group( $target_account_id_for_data, $start_date, $end_date );
                    $campaign_data_by_date = $this->data_provider->get_campaign_data_by_date( $target_account_id_for_data, $start_date, $end_date );
                    $summary_metrics = $this->data_provider->get_summary_metrics( $target_account_id_for_data, $start_date, $end_date );
                    $visitor_data = $this->data_provider->get_visitor_data( $target_account_id_for_data );
                }
            }

            // Always localize the general dashboard data object.
            // This is crucial for both admins and non-admins on the public dashboard page
            wp_localize_script(
                $this->plugin_name . '-public',
                'cpd_dashboard_data',
                array(
                    'ajax_url' => admin_url( 'admin-ajax.php' ),
                    'visitor_nonce'    => wp_create_nonce( 'cpd_visitor_nonce' ), // Specific nonce for visitor updates
                    'dashboard_nonce'  => wp_create_nonce( 'cpd_get_dashboard_data_nonce' ), // Specific nonce for dashboard data
                    'is_admin_user' => $is_admin,
                    'memo_seal_url' => CPD_DASHBOARD_PLUGIN_URL . 'assets/images/MEMO_Seal.png',
                    // Data for non-admins is included here initially
                    // For admins, these will be empty arrays as they fetch dynamically
                    'campaign_data_by_ad_group' => $campaign_data_by_ad_group,
                    'campaign_data_by_date'     => $campaign_data_by_date,
                    'summary_metrics'           => $summary_metrics,
                    'visitor_data'              => $visitor_data,
                    'current_client_account_id' => $client_account ? $client_account->account_id : null,
                )
            );
        }
    }
    
    /**
     * Renders the campaign dashboard via a shortcode.
     */
    public function display_dashboard() {
        if ( ! is_user_logged_in() ) { return '<p>Please log in to view the dashboard.</p>'; }
        
        $current_user = wp_get_current_user();
        $is_admin = current_user_can( 'manage_options' ); 
        $client_account = null; 
        
        // Define $selected_client_id_from_url
        $selected_client_id_from_url = isset($_GET['client_id']) ? sanitize_text_field($_GET['client_id']) : null; 

        if ( $is_admin ) { 
            // For admins, determine the initial client account.
            // If a client_id is in the URL, use that.
            // Otherwise, if 'all' is requested, set client_account to null to signify 'all'.
            // If no client_id or 'all' is requested, and there are clients, set client_account to null to signify 'all'.
            if ( $selected_client_id_from_url && $selected_client_id_from_url !== 'all' ) {
                $client_account = $this->data_provider->get_client_by_account_id( $selected_client_id_from_url );
            } else {
                $client_account = null; // Set to null to trigger 'all clients' logic in data provider
            }
        } else { 
            $account_id = $this->data_provider->get_account_id_by_user_id( $current_user->ID ); 
            if ( $account_id ) { 
                $client_account = $this->data_provider->get_client_by_account_id( $account_id ); 
            } 
        }

        if ( ! $client_account && ! $is_admin ) { 
             return '<p>Dashboard data is not available for your account. Please contact support.</p>';
        }
        
        $start_date = '2025-01-01'; 
        $end_date = date('Y-m-d');   

        // Determine the target_account_id for data fetching
        $target_account_id_for_data = $client_account ? $client_account->account_id : null; 

        // Fetch data based on the determined client (null for all clients if admin)
        // Note: For admins, initial data might not be displayed directly by PHP
        // but fetched via JS. However, we still need to potentially pass it for
        // the *initially selected* client if the admin is viewing a specific client.
        // For 'all clients', the data provider should handle aggregation.
        $summary_metrics = $this->data_provider->get_summary_metrics( $target_account_id_for_data, $start_date, $end_date );
        $campaign_data = $this->data_provider->get_campaign_data_by_ad_group( $target_account_id_for_data, $start_date, $end_date ); 
        $visitor_data = $this->data_provider->get_visitor_data( $target_account_id_for_data );

        // Pass the plugin name and selected_client_id_from_url to the template
        $plugin_name = $this->plugin_name;
        // Pass it here so it's available in public-dashboard.php
        $passed_selected_client_id_from_url = $selected_client_id_from_url;
        $report_problem_email = get_option('cpd_report_problem_email', 'support@memomarketinggroup.com'); // Get the setting, with a fallback

        ob_start();
        include CPD_DASHBOARD_PLUGIN_DIR . 'public/views/public-dashboard.php';
        return ob_get_clean();
    }
    
    /**
     * Redirects users to the appropriate dashboard page after login.
     */
    public function login_redirect_by_role( $redirect_to, $request, $user ) {
        if ( is_wp_error( $user ) ) { return $redirect_to; }
        if ( in_array( 'administrator', (array) $user->roles ) ) { return admin_url( 'admin.php?page=' . $this->plugin_name ); }
        if ( in_array( 'client', (array) $user->roles ) ) {
            $client_dashboard_url = get_option( 'cpd_client_dashboard_url' );
            if ( $client_dashboard_url ) { return $client_dashboard_url; }
        }
        return $redirect_to;
    }
    
    /**
     * AJAX callback to update the visitor's CRM or archive status.
     */
    public function update_visitor_status_callback() {
        global $wpdb;
        $visitor_table = $wpdb->prefix . 'cpd_visitors';
        $log_table = $wpdb->prefix . 'cpd_action_logs';
        
        // Ensure this nonce check matches 'cpd_visitor_nonce'
        if ( ! check_ajax_referer( 'cpd_visitor_nonce', 'nonce', false ) ) { 
            wp_send_json_error( 'Invalid security nonce.', 403 ); 
        }
        $visitor_id = isset( $_POST['visitor_id'] ) ? sanitize_text_field( $_POST['visitor_id'] ) : '';
        $update_action = isset( $_POST['update_action'] ) ? sanitize_text_field( $_POST['update_action'] ) : '';
        $user_id = get_current_user_id();
        if ( empty( $visitor_id ) || ! in_array( $update_action, array( 'add_crm', 'archive' ) ) ) { wp_send_json_error( 'Invalid data provided.', 400 ); }
        $update_column = ''; $log_description = '';
        if ( 'add_crm' === $update_action ) { $update_column = 'is_crm_added'; $log_description = 'Visitor ID ' . $visitor_id . ' flagged for CRM addition.'; } elseif ( 'archive' === $update_action ) { $update_column = 'is_archived'; $log_description = 'Visitor ID ' . $visitor_id . ' archived.'; }
        $updated = $wpdb->update( $visitor_table, array( $update_column => 1 ), array( 'visitor_id' => $visitor_id ), array( '%d' ), array( '%s' ) );
        if ( $updated === false ) {
            $log_description = 'Failed to update visitor status for ' . $visitor_id . ' (Action: ' . $update_action . ').'; $log_type = 'UPDATE_FAILED';
            wp_send_json_error( 'Database update failed.', 500 );
        } else {
            $log_data = array( 'user_id' => $user_id, 'action_type' => 'VISITOR_' . strtoupper($update_action), 'description' => $log_description, );
            $wpdb->insert( $log_table, $log_data );
            wp_send_json_success( 'Status updated successfully.', 200 );
        }
    }

    /**
     * Hides the WordPress admin bar for logged-in users on the front end.
     */
    public function hide_admin_bar() {
        return false;
    }
}