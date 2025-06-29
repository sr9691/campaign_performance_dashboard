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
        $this->data_provider = new CPD_Data_Provider(); // Initialize data provider.
        
        // Add AJAX handlers for front-end.
        add_action( 'wp_ajax_cpd_update_visitor_status', array( $this, 'update_visitor_status_callback' ) );
        add_action( 'wp_ajax_nopriv_cpd_update_visitor_status', array( $this, 'update_visitor_status_callback' ) );

        // Add scripts and styles with a high priority to load after the theme's.
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ), 99 );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts_data' ) );
        
        // Add login redirect filter.
        add_filter( 'login_redirect', array( $this, 'login_redirect_by_role' ), 10, 3 );
        // Hide admin bar on the front end.
        add_filter( 'body_class', array( $this, 'add_dashboard_body_class' ) );
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

        // Check if the current page contains our shortcode.
        if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'campaign_dashboard' ) ) {
            // Dequeue the theme's main stylesheet.
            wp_dequeue_style( 'twentytwentyfive-style' );
            wp_deregister_style( 'twentytwentyfive-style' );

            // Enqueue our custom dashboard styles for the public page.
            wp_enqueue_style( 'google-montserrat', 'https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap', array(), null );
            wp_enqueue_style( 'font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css', array(), '5.15.4', 'all' );
            wp_enqueue_style( $this->plugin_name . '-public', CPD_DASHBOARD_PLUGIN_URL . 'assets/css/cpd-dashboard.css', array(), $this->version, 'all' );
        }
    }

    /**
     * Enqueue the public-facing JavaScript files.
     */
    public function enqueue_scripts() {
        global $post;
        if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'campaign_dashboard' ) ) {
            // Enqueue Chart.js library for the public dashboard.
            wp_enqueue_script( 'chart-js', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js', array(), '4.4.1', true );

            // Enqueue scripts for the front-end dashboard.
            wp_enqueue_script( $this->plugin_name . '-public', CPD_DASHBOARD_PLUGIN_URL . 'assets/js/cpd-public-dashboard.js', array( 'jquery', 'chart-js' ), $this->version, true );
        }
    }

    /**
     * Localize script with AJAX URL and nonce for public-facing dashboard.
     */
    public function enqueue_scripts_data() {
        global $post;
        if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'campaign_dashboard' ) ) {
            wp_localize_script(
                $this->plugin_name . '-public',
                'ajax_object',
                array(
                    'ajax_url' => admin_url( 'admin-ajax.php' ),
                    'nonce'    => wp_create_nonce( 'cpd_visitor_nonce' ),
                )
            );
        }
    }
    
    /**
     * Renders the campaign dashboard via a shortcode.
     */
    public function display_dashboard() {
        if ( ! is_user_logged_in() ) { return '<p>Please log in to view the dashboard.</p>'; }
        $current_user = wp_get_current_user(); $is_admin = current_user_can( 'manage_options' ); $client_account = null; $client_data = null; $dashboard_data = null; $visitor_data = null;
        if ( $is_admin ) { $client_account = $this->data_provider->get_client_by_account_id( '316578' ); } else { $account_id = $this->data_provider->get_account_id_by_user_id( $current_user->ID ); if ( $account_id ) { $client_account = $this->data_provider->get_client_by_account_id( $account_id ); } }
        if ( ! $client_account ) { return '<p>Dashboard data is not available for your account. Please contact support.</p>'; }
        $start_date = '2025-01-01'; $end_date = date('Y-m-d');
        $summary_metrics = $this->data_provider->get_summary_metrics( $client_account->account_id, $start_date, $end_date );
        $campaign_data = $this->data_provider->get_campaign_data( $client_account->account_id, $start_date, $end_date );
        $visitor_data = $this->data_provider->get_visitor_data( $client_account->account_id );
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
        if ( ! check_ajax_referer( 'cpd_visitor_nonce', 'nonce', false ) ) { wp_send_json_error( 'Invalid security nonce.', 403 ); }
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