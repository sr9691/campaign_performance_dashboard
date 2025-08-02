<?php
/**
 * CPD Admin Intelligence Settings
 * Handles all admin interface for AI Intelligence configuration
 *
 * @package CPD_Dashboard
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CPD_Admin_Intelligence_Settings {

    /**
     * Initialize the intelligence settings
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_intelligence_settings_menu' ) );
        add_action( 'admin_init', array( $this, 'register_intelligence_settings' ) );
        add_action( 'wp_ajax_cpd_test_intelligence_webhook', array( $this, 'ajax_test_intelligence_webhook' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
    }

    /**
     * Add Intelligence Settings submenu to CPD Dashboard
     */
    public function add_intelligence_settings_menu() {
        add_submenu_page(
            'cpd-dashboard',
            __( 'Intelligence Settings', 'cpd-dashboard' ),
            __( 'Intelligence Settings', 'cpd-dashboard' ),
            'manage_options',
            'cpd-intelligence-settings',
            array( $this, 'intelligence_settings_page' )
        );
    }

    /**
     * Register intelligence settings
     */
    public function register_intelligence_settings() {
        // Intelligence API Settings
        register_setting( 'cpd_intelligence_settings', 'cpd_intelligence_webhook_url' );
        register_setting( 'cpd_intelligence_settings', 'cpd_makecom_api_key' );
        register_setting( 'cpd_intelligence_settings', 'cpd_intelligence_rate_limit' );
        register_setting( 'cpd_intelligence_settings', 'cpd_intelligence_timeout' );
        register_setting( 'cpd_intelligence_settings', 'cpd_intelligence_auto_generate_crm' );
        register_setting( 'cpd_intelligence_settings', 'cpd_intelligence_processing_method' );
        register_setting( 'cpd_intelligence_settings', 'cpd_intelligence_batch_size' );
        register_setting( 'cpd_intelligence_settings', 'cpd_intelligence_crm_timeout' );
        
        // Default client settings
        register_setting( 'cpd_intelligence_settings', 'cpd_intelligence_default_enabled' );
        register_setting( 'cpd_intelligence_settings', 'cpd_intelligence_require_context' );
        
        // Add settings sections
        add_settings_section(
            'cpd_intelligence_api_section',
            __( 'API Configuration', 'cpd-dashboard' ),
            array( $this, 'intelligence_api_section_callback' ),
            'cpd_intelligence_settings'
        );
        
        add_settings_section(
            'cpd_intelligence_behavior_section',
            __( 'Intelligence Behavior', 'cpd-dashboard' ),
            array( $this, 'intelligence_behavior_section_callback' ),
            'cpd_intelligence_settings'
        );
        
        add_settings_section(
            'cpd_intelligence_defaults_section',
            __( 'Default Client Settings', 'cpd-dashboard' ),
            array( $this, 'intelligence_defaults_section_callback' ),
            'cpd_intelligence_settings'
        );
        
        // API Configuration Fields
        add_settings_field(
            'cpd_intelligence_webhook_url',
            __( 'Make.com Webhook URL', 'cpd-dashboard' ),
            array( $this, 'intelligence_webhook_url_callback' ),
            'cpd_intelligence_settings',
            'cpd_intelligence_api_section'
        );
        
        add_settings_field(
            'cpd_makecom_api_key',
            __( 'Make.com API Key', 'cpd-dashboard' ),
            array( $this, 'makecom_api_key_callback' ),
            'cpd_intelligence_settings',
            'cpd_intelligence_api_section'
        );
        
        // Behavior Settings Fields
        add_settings_field(
            'cpd_intelligence_rate_limit',
            __( 'Rate Limit (per visitor per day)', 'cpd-dashboard' ),
            array( $this, 'intelligence_rate_limit_callback' ),
            'cpd_intelligence_settings',
            'cpd_intelligence_behavior_section'
        );
        
        add_settings_field(
            'cpd_intelligence_timeout',
            __( 'API Timeout (seconds)', 'cpd-dashboard' ),
            array( $this, 'intelligence_timeout_callback' ),
            'cpd_intelligence_settings',
            'cpd_intelligence_behavior_section'
        );
        
        add_settings_field(
            'cpd_intelligence_auto_generate_crm',
            __( 'Auto-generate for CRM Export', 'cpd-dashboard' ),
            array( $this, 'intelligence_auto_generate_crm_callback' ),
            'cpd_intelligence_settings',
            'cpd_intelligence_behavior_section'
        );
        
        add_settings_field(
            'cpd_intelligence_processing_method',
            __( 'Processing Method', 'cpd-dashboard' ),
            array( $this, 'intelligence_processing_method_callback' ),
            'cpd_intelligence_settings',
            'cpd_intelligence_behavior_section'
        );
        
        add_settings_field(
            'cpd_intelligence_batch_size',
            __( 'Batch Size', 'cpd-dashboard' ),
            array( $this, 'intelligence_batch_size_callback' ),
            'cpd_intelligence_settings',
            'cpd_intelligence_behavior_section'
        );
        
        add_settings_field(
            'cpd_intelligence_crm_timeout',
            __( 'CRM Processing Timeout (seconds)', 'cpd-dashboard' ),
            array( $this, 'intelligence_crm_timeout_callback' ),
            'cpd_intelligence_settings',
            'cpd_intelligence_behavior_section'
        );
        
        // Default Settings Fields
        add_settings_field(
            'cpd_intelligence_default_enabled',
            __( 'Enable AI for New Clients', 'cpd-dashboard' ),
            array( $this, 'intelligence_default_enabled_callback' ),
            'cpd_intelligence_settings',
            'cpd_intelligence_defaults_section'
        );
        
        add_settings_field(
            'cpd_intelligence_require_context',
            __( 'Require Client Context', 'cpd-dashboard' ),
            array( $this, 'intelligence_require_context_callback' ),
            'cpd_intelligence_settings',
            'cpd_intelligence_defaults_section'
        );
    }

    /**
     * Intelligence Settings Page
     */
    public function intelligence_settings_page() {
        // Handle form submission
        if ( isset( $_POST['submit'] ) && check_admin_referer( 'cpd_intelligence_settings_nonce' ) ) {
            $this->handle_intelligence_settings_save();
        }
        
        // Get current statistics
        if ( class_exists( 'CPD_Intelligence' ) ) {
            $intelligence = new CPD_Intelligence();
            $stats = $intelligence->get_intelligence_statistics();
            $is_configured = $intelligence->is_intelligence_configured();
        } else {
            $stats = array();
            $is_configured = false;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            
            <?php settings_errors(); ?>
            
            <!-- Intelligence Status Dashboard -->
            <div class="cpd-intelligence-dashboard" style="margin-bottom: 20px;">
                <h2><?php _e( 'Intelligence Status', 'cpd-dashboard' ); ?></h2>
                <div style="display: flex; gap: 20px; margin-bottom: 15px; flex-wrap: wrap;">
                    <div class="cpd-stat-card" style="background: #f0f0f1; padding: 15px; border-radius: 4px; min-width: 150px;">
                        <strong><?php _e( 'Configuration', 'cpd-dashboard' ); ?></strong><br>
                        <span style="color: <?php echo $is_configured ? '#46b450' : '#dc3232'; ?>;">
                            <?php echo $is_configured ? '✓ ' . __( 'Configured', 'cpd-dashboard' ) : '✗ ' . __( 'Not Configured', 'cpd-dashboard' ); ?>
                        </span>
                    </div>
                    <div class="cpd-stat-card" style="background: #f0f0f1; padding: 15px; border-radius: 4px; min-width: 150px;">
                        <strong><?php _e( 'Total Requests', 'cpd-dashboard' ); ?></strong><br>
                        <span style="font-size: 18px; color: #2271b1;">
                            <?php echo isset( $stats['total_requests'] ) ? number_format( $stats['total_requests'] ) : '0'; ?>
                        </span>
                    </div>
                    <div class="cpd-stat-card" style="background: #f0f0f1; padding: 15px; border-radius: 4px; min-width: 150px;">
                        <strong><?php _e( "Today's Requests", 'cpd-dashboard' ); ?></strong><br>
                        <span style="font-size: 18px; color: #2271b1;">
                            <?php echo isset( $stats['today_requests'] ) ? number_format( $stats['today_requests'] ) : '0'; ?>
                        </span>
                    </div>
                    <div class="cpd-stat-card" style="background: #f0f0f1; padding: 15px; border-radius: 4px; min-width: 150px;">
                        <strong><?php _e( 'Success Rate', 'cpd-dashboard' ); ?></strong><br>
                        <span style="font-size: 18px; color: #46b450;">
                            <?php echo isset( $stats['success_rate'] ) ? $stats['success_rate'] . '%' : '0%'; ?>
                        </span>
                    </div>
                </div>
                
                <?php if ( isset( $stats['by_status'] ) && ! empty( $stats['by_status'] ) ): ?>
                <div style="margin-top: 15px;">
                    <strong><?php _e( 'Requests by Status:', 'cpd-dashboard' ); ?></strong>
                    <div style="display: flex; gap: 15px; margin-top: 5px;">
                        <?php foreach ( $stats['by_status'] as $status => $count ): ?>
                            <span style="background: #e5e5e5; padding: 5px 10px; border-radius: 3px; font-size: 12px;">
                                <?php echo esc_html( ucfirst( $status ) ) . ': ' . number_format( $count ); ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <form method="post" action="">
                <?php wp_nonce_field( 'cpd_intelligence_settings_nonce' ); ?>
                <?php settings_fields( 'cpd_intelligence_settings' ); ?>
                <?php do_settings_sections( 'cpd_intelligence_settings' ); ?>
                
                <div style="margin-top: 20px;">
                    <?php submit_button( __( 'Save Settings', 'cpd-dashboard' ) ); ?>
                    <button type="button" id="test-webhook-btn" class="button button-secondary" style="margin-left: 10px;">
                        <?php _e( 'Test Webhook', 'cpd-dashboard' ); ?>
                    </button>
                </div>
            </form>
            
            <div id="webhook-test-result" style="margin-top: 15px;"></div>
        </div>
        <?php
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts( $hook ) {
        if ( $hook !== 'cpd-dashboard_page_cpd-intelligence-settings' ) {
            return;
        }
        
        wp_enqueue_script( 'jquery' );
        
        // Inline script for webhook testing
        $script = "
        jQuery(document).ready(function($) {
            $('#test-webhook-btn').click(function() {
                var button = $(this);
                var resultDiv = $('#webhook-test-result');
                
                button.prop('disabled', true).text('" . esc_js( __( 'Testing...', 'cpd-dashboard' ) ) . "');
                resultDiv.html('');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'cpd_test_intelligence_webhook',
                        nonce: '" . wp_create_nonce( 'cpd_test_webhook_nonce' ) . "',
                        webhook_url: $('#cpd_intelligence_webhook_url').val(),
                        api_key: $('#cpd_makecom_api_key').val()
                    },
                    success: function(response) {
                        if (response.success) {
                            resultDiv.html('<div class=\"notice notice-success\"><p>✓ ' + response.data.message + '</p></div>');
                        } else {
                            resultDiv.html('<div class=\"notice notice-error\"><p>✗ ' + response.data.message + '</p></div>');
                        }
                    },
                    error: function() {
                        resultDiv.html('<div class=\"notice notice-error\"><p>✗ " . esc_js( __( 'Failed to test webhook connection', 'cpd-dashboard' ) ) . "</p></div>');
                    },
                    complete: function() {
                        button.prop('disabled', false).text('" . esc_js( __( 'Test Webhook', 'cpd-dashboard' ) ) . "');
                    }
                });
            });
        });
        ";
        
        wp_add_inline_script( 'jquery', $script );
    }

    /**
     * Section callbacks
     */
    public function intelligence_api_section_callback() {
        echo '<p>' . __( 'Configure the Make.com API connection for intelligence processing.', 'cpd-dashboard' ) . '</p>';
    }

    public function intelligence_behavior_section_callback() {
        echo '<p>' . __( 'Control how intelligence requests are processed and rate limited.', 'cpd-dashboard' ) . '</p>';
    }

    public function intelligence_defaults_section_callback() {
        echo '<p>' . __( 'Set default AI intelligence settings for new clients.', 'cpd-dashboard' ) . '</p>';
    }

    /**
     * Field callbacks
     */
    public function intelligence_webhook_url_callback() {
        $value = get_option( 'cpd_intelligence_webhook_url', '' );
        echo '<input type="url" id="cpd_intelligence_webhook_url" name="cpd_intelligence_webhook_url" value="' . esc_attr( $value ) . '" class="regular-text" required>';
        echo '<p class="description">' . __( 'The Make.com webhook URL for processing intelligence requests.', 'cpd-dashboard' ) . '</p>';
    }

    public function makecom_api_key_callback() {
        $value = get_option( 'cpd_makecom_api_key', '' );
        echo '<input type="password" id="cpd_makecom_api_key" name="cpd_makecom_api_key" value="' . esc_attr( $value ) . '" class="regular-text" required>';
        echo '<p class="description">' . __( 'Your Make.com API key for authentication.', 'cpd-dashboard' ) . '</p>';
    }

    public function intelligence_rate_limit_callback() {
        $value = get_option( 'cpd_intelligence_rate_limit', 5 );
        echo '<input type="number" name="cpd_intelligence_rate_limit" value="' . esc_attr( $value ) . '" min="1" max="10" class="small-text">';
        echo '<p class="description">' . __( 'Maximum intelligence requests per visitor per day (1-10).', 'cpd-dashboard' ) . '</p>';
    }

    public function intelligence_timeout_callback() {
        $value = get_option( 'cpd_intelligence_timeout', 30 );
        echo '<input type="number" name="cpd_intelligence_timeout" value="' . esc_attr( $value ) . '" min="10" max="120" class="small-text">';
        echo '<p class="description">' . __( 'API request timeout in seconds (10-120).', 'cpd-dashboard' ) . '</p>';
    }

    public function intelligence_auto_generate_crm_callback() {
        $value = get_option( 'cpd_intelligence_auto_generate_crm', 1 );
        echo '<input type="checkbox" name="cpd_intelligence_auto_generate_crm" value="1"' . checked( 1, $value, false ) . '>';
        echo '<label for="cpd_intelligence_auto_generate_crm">' . __( 'Automatically generate intelligence for CRM exports', 'cpd-dashboard' ) . '</label>';
    }

    public function intelligence_processing_method_callback() {
        $value = get_option( 'cpd_intelligence_processing_method', 'batch' );
        echo '<select name="cpd_intelligence_processing_method">';
        echo '<option value="batch"' . selected( 'batch', $value, false ) . '>' . __( 'Batch Processing', 'cpd-dashboard' ) . '</option>';
        echo '<option value="serial"' . selected( 'serial', $value, false ) . '>' . __( 'Serial Processing', 'cpd-dashboard' ) . '</option>';
        echo '</select>';
        echo '<p class="description">' . __( 'How to process multiple intelligence requests.', 'cpd-dashboard' ) . '</p>';
    }

    public function intelligence_batch_size_callback() {
        $value = get_option( 'cpd_intelligence_batch_size', 5 );
        echo '<input type="number" name="cpd_intelligence_batch_size" value="' . esc_attr( $value ) . '" min="1" max="20" class="small-text">';
        echo '<p class="description">' . __( 'Number of requests to process in each batch (1-20).', 'cpd-dashboard' ) . '</p>';
    }

    public function intelligence_crm_timeout_callback() {
        $value = get_option( 'cpd_intelligence_crm_timeout', 300 );
        echo '<input type="number" name="cpd_intelligence_crm_timeout" value="' . esc_attr( $value ) . '" min="60" max="900" class="small-text">';
        echo '<p class="description">' . __( 'Maximum time for CRM batch processing in seconds (60-900).', 'cpd-dashboard' ) . '</p>';
    }

    public function intelligence_default_enabled_callback() {
        $value = get_option( 'cpd_intelligence_default_enabled', 0 );
        echo '<input type="checkbox" name="cpd_intelligence_default_enabled" value="1"' . checked( 1, $value, false ) . '>';
        echo '<label for="cpd_intelligence_default_enabled">' . __( 'Enable AI intelligence for new clients by default', 'cpd-dashboard' ) . '</label>';
    }

    public function intelligence_require_context_callback() {
        $value = get_option( 'cpd_intelligence_require_context', 0 );
        echo '<input type="checkbox" name="cpd_intelligence_require_context" value="1"' . checked( 1, $value, false ) . '>';
        echo '<label for="cpd_intelligence_require_context">' . __( 'Require client context information for AI intelligence', 'cpd-dashboard' ) . '</label>';
    }

    /**
     * Handle settings save with validation
     */
    private function handle_intelligence_settings_save() {
        if ( ! class_exists( 'CPD_Intelligence' ) ) {
            require_once CPD_DASHBOARD_PLUGIN_DIR . 'includes/class-cpd-intelligence.php';
        }
        
        $intelligence = new CPD_Intelligence();
        
        // Prepare settings for validation
        $settings = array(
            'webhook_url' => sanitize_url( $_POST['cpd_intelligence_webhook_url'] ),
            'rate_limit' => intval( $_POST['cpd_intelligence_rate_limit'] ),
            'timeout' => intval( $_POST['cpd_intelligence_timeout'] )
        );
        
        // Validate settings
        $errors = $intelligence->validate_intelligence_settings( $settings );
        
        if ( ! empty( $errors ) ) {
            foreach ( $errors as $error ) {
                add_settings_error( 'cpd_intelligence_settings', 'settings_error', $error );
            }
            return;
        }
        
        // Save settings if validation passes
        add_settings_error( 'cpd_intelligence_settings', 'settings_saved', 
            __( 'Intelligence settings saved successfully.', 'cpd-dashboard' ), 'updated' );
    }

    /**
     * AJAX handler to test webhook connection
     */
    public function ajax_test_intelligence_webhook() {
        if ( ! check_ajax_referer( 'cpd_test_webhook_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid nonce', 'cpd-dashboard' ) ) );
        }
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'cpd-dashboard' ) ) );
        }
        
        $webhook_url = sanitize_url( $_POST['webhook_url'] );
        $api_key = sanitize_text_field( $_POST['api_key'] );
        
        if ( empty( $webhook_url ) || empty( $api_key ) ) {
            wp_send_json_error( array( 'message' => __( 'Webhook URL and API key are required', 'cpd-dashboard' ) ) );
        }
        
        // Test the webhook with a minimal payload
        $test_data = array(
            'test' => true,
            'timestamp' => current_time( 'mysql' ),
            'source' => 'cpd_intelligence_test'
        );
        
        $response = wp_remote_post( $webhook_url, array(
            'timeout' => 15,
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key
            ),
            'body' => wp_json_encode( $test_data )
        ) );
        
        if ( is_wp_error( $response ) ) {
            wp_send_json_error( array( 
                'message' => __( 'Connection failed: ', 'cpd-dashboard' ) . $response->get_error_message() 
            ) );
        }
        
        $status_code = wp_remote_retrieve_response_code( $response );
        
        if ( $status_code >= 200 && $status_code < 300 ) {
            wp_send_json_success( array( 
                'message' => sprintf( __( 'Webhook connection successful (Status: %d)', 'cpd-dashboard' ), $status_code )
            ) );
        } else {
            wp_send_json_error( array( 
                'message' => sprintf( __( 'Webhook returned error status: %d', 'cpd-dashboard' ), $status_code )
            ) );
        }
    }
}