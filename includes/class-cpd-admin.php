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

    public function __construct( $plugin_name, $version ) {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->log_table = $this->wpdb->prefix . 'cpd_action_logs';
        $this->client_table = $this->wpdb->prefix . 'cpd_clients';
        $this->user_client_table = $this->wpdb->prefix . 'cpd_client_users';
        
        
        // Initialize the data provider
        require_once CPD_DASHBOARD_PLUGIN_DIR . 'includes/class-cpd-data-provider.php';
        $this->data_provider = new CPD_Data_Provider();

        // Add hooks for form processing (AJAX and non-AJAX)
        add_action( 'admin_post_cpd_add_client', array( $this, 'handle_add_client_submission' ) );
        add_action( 'admin_post_cpd_delete_client', array( $this, 'handle_delete_client_submission' ) );
        add_action( 'admin_post_cpd_add_user', array( $this, 'handle_add_user_submission' ) );
        add_action( 'admin_post_cpd_delete_user', array( $this, 'handle_delete_user_submission' ) );
        
        // AJAX hooks for Admin dashboard interactivity
        add_action( 'wp_ajax_cpd_get_dashboard_data', array( $this, 'ajax_get_dashboard_data' ) );
        add_action( 'wp_ajax_cpd_ajax_add_client', array( $this, 'ajax_handle_add_client' ) );
        add_action( 'wp_ajax_nopriv_cpd_ajax_add_client', array( $this, 'ajax_handle_add_client' ) ); 
        add_action( 'wp_ajax_cpd_ajax_edit_client', array( $this, 'ajax_handle_edit_client' ) );
        add_action( 'wp_ajax_cpd_ajax_delete_client', array( $this, 'ajax_handle_delete_client' ) );
        add_action( 'wp_ajax_cpd_ajax_add_user', array( $this, 'ajax_handle_add_user' ) );
        add_action( 'wp_ajax_nopriv_cpd_ajax_add_user', array( $this, 'ajax_handle_add_user' ) ); 
        add_action( 'wp_ajax_cpd_ajax_edit_user', array( $this, 'ajax_handle_edit_user' ) );
        add_action( 'wp_ajax_cpd_ajax_delete_user', array( $this, 'ajax_handle_delete_user' ) );
        add_action( 'wp_ajax_cpd_get_client_list', array( $this, 'ajax_get_client_list' ) );

        // Enqueue styles/scripts
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

        // Add admin menus
        add_action( 'admin_menu', array( $this, 'add_plugin_admin_menu' ) );

        // --- ADD THIS LINE TO REGISTER THE SETTINGS ---
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    /**
     * Enqueue the admin stylesheets for the admin area.
     */
    public function enqueue_styles() {
        $screen = get_current_screen();
        
        // More specific check for our admin page
        if ( $screen && ($screen->id === 'toplevel_page_cpd-dashboard' || $screen->id === 'toplevel_page_cpd_dashboard') ) {
            wp_enqueue_style( 'google-montserrat', 'https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap', array(), null );
            wp_enqueue_style( 'font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css', array(), '5.15.4', 'all' );
            wp_enqueue_style( $this->plugin_name, CPD_DASHBOARD_PLUGIN_URL . 'assets/css/cpd-dashboard.css', array(), $this->version, 'all' );
        }
    }

    /**
     * Enqueue the admin JavaScript files for the admin area.
     */
    public function enqueue_scripts() {
        $screen = get_current_screen();
        
        // More specific check for our admin page
        if ( $screen && ($screen->id === 'toplevel_page_cpd-dashboard' || $screen->id === 'toplevel_page_cpd_dashboard') ) {
            wp_enqueue_script( 'jquery' );
            wp_enqueue_script( 'chart-js', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js', array(), '4.4.1', true );
            wp_enqueue_script( $this->plugin_name . '-admin', CPD_DASHBOARD_PLUGIN_URL . 'assets/js/cpd-dashboard.js', array( 'jquery', 'chart-js' ), $this->version, true );
            
            // Localize script to pass data to JS
            wp_localize_script(
                $this->plugin_name . '-admin',
                'cpd_admin_ajax',
                array(
                    'ajax_url' => admin_url( 'admin-ajax.php' ),
                    'nonce' => wp_create_nonce( 'cpd_admin_nonce' ),
                )
            );
        }
    }


    
    /**
     * Add the top-level menu page for the plugin in the admin dashboard.
     */
    public function add_plugin_admin_menu() {
    // Use 'manage_options' capability instead of custom capability for main menu
    $page_hook = add_menu_page(
        'Campaign Dashboard',           // Page title
        'Campaign Dashboard',           // Menu title
        'manage_options',              // Capability - use standard capability
        $this->plugin_name,            // Menu slug
        array( $this, 'render_admin_page' ), // Callback function
        'dashicons-chart-bar',         // Icon
        6                             // Position
    );
    
    // Add a settings page as a submenu
    add_submenu_page(
        $this->plugin_name,
        'Dashboard Settings',
        'Settings',
        'manage_options',
        $this->plugin_name . '-settings',
        array( $this, 'render_settings_page' )
    );
    
    // Add action to enqueue scripts only on our admin page
    add_action( 'admin_print_scripts-' . $page_hook, array( $this, 'enqueue_scripts' ) );
    add_action( 'admin_print_styles-' . $page_hook, array( $this, 'enqueue_styles' ) );
    }


    /**
     * Render the admin page content from the HTML template.
     */
    public function render_admin_page() {
 
        /** Start actual code here */
        $plugin_name = $this->plugin_name;
        $all_clients = $this->data_provider->get_all_client_accounts();
        $selected_client_id = isset( $_GET['client_id'] ) ? sanitize_text_field( $_GET['client_id'] ) : ( !empty($all_clients) ? $all_clients[0]->account_id : '' );
        $selected_client = $selected_client_id ? $this->data_provider->get_client_by_account_id( $selected_client_id ) : null;
        $summary_metrics = null; $campaign_data = null; $visitor_data = null;
        if ( $selected_client ) {
            $start_date = '2025-01-01'; $end_date = date('Y-m-d');
            $summary_metrics = $this->data_provider->get_summary_metrics( $selected_client->account_id, $start_date, $end_date );
            $campaign_data = $this->data_provider->get_campaign_data( $selected_client->account_id, $start_date, $end_date );
            $visitor_data = $this->data_provider->get_visitor_data( $selected_client->account_id );
        }
        $logs = $this->get_all_logs();

        // Force styles inline as backup
        echo '<style id="cpd-force-styles">
        body { overflow: hidden !important; }
        #wpcontent { margin-left: 0 !important; padding: 0 !important; }
        #wpbody { background: #eef2f6 !important; padding: 0 !important; }
        .wrap { margin: 0 !important; padding: 0 !important; display: flex !important; width: 100% !important; min-height: 100vh !important; }
        #adminmenumain, #adminmenuwrap, #adminmenuback, #wpadminbar, #wpfooter { display: none !important; }
        </style>';

        // Include the admin page template
        include CPD_DASHBOARD_PLUGIN_DIR . 'admin/views/admin-page.php';
    }
    /**
     * Gets the plugin name.
     *
     * @return string The plugin name.
     */
    public static function get_plugin_name() {
        return 'cpd-dashboard'; // Hardcode the plugin name
    }
    
    /**
     * Renders the settings page content.
     */
    public function render_settings_page() {
        // This is a placeholder for the settings page content.
        // It will contain a form to set the client dashboard URL.
        $dashboard_page_url = get_option( 'cpd_client_dashboard_url', '' );
        ?>
        <div class="wrap">
            <h1>Dashboard Settings</h1>
            <form method="post" action="options.php">
                <?php settings_fields( 'cpd-dashboard-settings-group' ); ?>
                <?php do_settings_sections( 'cpd-dashboard-settings-group' ); ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Client Dashboard URL</th>
                        <td>
                            <input type="url" name="cpd_client_dashboard_url" value="<?php echo esc_url( $dashboard_page_url ); ?>" size="50" />
                            <p class="description">Enter the full URL of the page where you added the <code>[campaign_dashboard]</code> shortcode.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Register settings for the plugin.
     */
    public function register_settings() {
        register_setting( 'cpd-dashboard-settings-group', 'cpd_client_dashboard_url', 'esc_url_raw' );
    }

    /**
     * Fetches all log entries from the database.
     */
    private function get_all_logs() {
        return $this->wpdb->get_results( "SELECT * FROM {$this->log_table} ORDER BY timestamp DESC LIMIT 500" );
    }

    /**
     * Logs an action to the database.
     */
    private function log_action( $user_id, $action_type, $description ) {
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
        if ( $result ) { $this->log_action( $user_id, 'CLIENT_ADDED', 'Client "' . $client_name . '" (ID: ' . $account_id . ') was added.' ); wp_redirect( admin_url( 'admin.php?page=' . $this->plugin_name . '&message=client_added' ) ); } else { $this->log_action( $user_id, 'CLIENT_ADD_FAILED', 'Failed to add client "' . $client_name . '".' ); wp_redirect( admin_url( 'admin.php?page=' . $this->plugin_name . '&error=client_add_failed' ) ); }
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
        if ( $client_id <= 0 ) { wp_redirect( admin_url( 'admin.php?page=' . $this->plugin_name . '&error=invalid_client_id' ) ); exit; }
        $client_name = $this->wpdb->get_var( $this->wpdb->prepare( "SELECT client_name FROM $this->client_table WHERE id = %d", $client_id ) );
        $this->wpdb->delete( $this->client_table, array( 'id' => $client_id ), array( '%d' ) );
        $this->wpdb->delete( $this->user_client_table, array( 'client_id' => $client_id ), array( '%d' ) );
        $user_id = get_current_user_id();
        $this->log_action( $user_id, 'CLIENT_DELETED', 'Client "' . $client_name . '" (ID: ' . $client_id . ') was deleted.' );
        wp_redirect( admin_url( 'admin.php?page=' . $this->plugin_name . '&message=client_deleted' ) );
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
        if ( empty( $username ) || empty( $email ) || empty( $password ) ) { wp_redirect( admin_url( 'admin.php?page=' . $this->plugin_name . '&error=missing_user_fields' ) ); exit; }
        $user_id = wp_create_user( $username, $password, $email );
        if ( ! is_wp_error( $user_id ) ) {
            $user = new WP_User( $user_id ); $user->set_role( $role );
            if ( ! empty( $client_account_id ) ) { $client = $this->data_provider->get_client_by_account_id( $client_account_id ); if ($client) { $this->wpdb->insert( $this->user_client_table, array( 'user_id' => $user_id, 'client_id' => $client->id ) ); } }
            $this->log_action( get_current_user_id(), 'USER_ADDED', 'User "' . $username . '" (ID: ' . $user_id . ') was created with role "' . $role . '".' );
            wp_redirect( admin_url( 'admin.php?page=' . $this->plugin_name . '&message=user_added' ) );
        } else {
            $this->log_action( get_current_user_id(), 'USER_ADD_FAILED', 'Failed to create user "' . $username . '": ' . $user_id->get_error_message() ); wp_redirect( admin_url( 'admin.php?page=' . $this->plugin_name . '&error=user_add_failed&reason=' . urlencode($user_id->get_error_message()) ) ); }
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
    public function handle_delete_user_submission() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'You do not have sufficient permissions to access this page.' ); }
        if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'cpd_delete_user_nonce' ) ) { wp_die( 'Security check failed.' ); }
        $user_id_to_delete = isset( $_POST['user_id'] ) ? intval( $_POST['user_id'] ) : 0;
        if ( $user_id_to_delete <= 0 || $user_id_to_delete === get_current_user_id() ) { wp_redirect( admin_url( 'admin.php?page=' . $this->plugin_name . '&error=invalid_user_id' ) ); exit; }
        require_once( ABSPATH . 'wp-admin/includes/user.php' );
        $deleted = wp_delete_user( $user_id_to_delete );
        if ( $deleted ) {
            $this->wpdb->delete( $this->user_client_table, array( 'user_id' => $user_id_to_delete ), array( '%d' ) );
            $this->log_action( get_current_user_id(), 'USER_DELETED', 'User ID ' . $user_id_to_delete . ' was deleted.' );
            wp_redirect( admin_url( 'admin.php?page=' . $this->plugin_name . '&message=user_deleted&tab=users' ) );
        } else {
            $this->log_action( get_current_user_id(), 'USER_DELETE_FAILED', 'Failed to delete user ID ' . $user_id_to_delete . '.' ); wp_redirect( admin_url( 'admin.php?page=' . $this->plugin_name . '&error=user_delete_failed&tab=users' ) ); }
        exit;
    }
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
    public function ajax_get_dashboard_data() {
        if ( ! current_user_can( 'cpd_view_dashboard' ) || ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'cpd_admin_nonce' ) ) { wp_send_json_error( 'Security check failed.' ); }
        $client_id = isset( $_POST['client_id'] ) ? sanitize_text_field( $_POST['client_id'] ) : null;
        $duration = isset( $_POST['duration'] ) ? sanitize_text_field( $_POST['duration'] ) : 'Campaign Duration';
        $end_date = date('Y-m-d');
        switch ($duration) { case '7 days': $start_date = date('Y-m-d', strtotime('-7 days')); break; case '30 days': $start_date = date('Y-m-d', strtotime('-30 days')); break; case 'Campaign Duration': default: $start_date = '2025-01-01'; break; }
        if ( ! $client_id ) { wp_send_json_error( 'No client ID provided.' ); }
        $summary_metrics = $this->data_provider->get_summary_metrics( $client_id, $start_date, $end_date );
        $campaign_data = $this->data_provider->get_campaign_data( $client_id, $start_date, $end_date );
        $visitor_data = $this->data_provider->get_visitor_data( $client_id );
        wp_send_json_success( array( 'summary_metrics' => $summary_metrics, 'campaign_data' => $campaign_data, 'visitor_data' => $visitor_data, ) );
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
}