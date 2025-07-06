<?php
/**
 * Handles sending daily CRM feed emails to clients.
 *
 * @package CPD_Dashboard
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CPD_Email_Handler {

    private static $wpdb;
    private static $visitor_table;
    private static $client_table;
    private static $log_table;
    private static $admin_instance; // To access log_action method

    /**
     * Initializes the static properties.
     */
    private static function initialize() {
        if ( ! self::$wpdb ) {
            global $wpdb;
            self::$wpdb = $wpdb;
            self::$visitor_table = self::$wpdb->prefix . 'cpd_visitors';
            self::$client_table = self::$wpdb->prefix . 'cpd_clients';
            self::$log_table = self::$wpdb->prefix . 'cpd_action_logs';

            // Instantiate CPD_Admin to use its log_action method.
            // This is a minimal instantiation just to get the logger.
            if ( ! class_exists( 'CPD_Admin' ) ) {
                require_once CPD_DASHBOARD_PLUGIN_DIR . 'admin/class-cpd-admin.php';
            }
            self::$admin_instance = new CPD_Admin( 'cpd-dashboard', CPD_DASHBOARD_VERSION );
        }
    }

    /**
     * Helper to log an action.
     * @param int $user_id The user ID performing the action (0 for cron/system).
     * @param string $action_type Type of action.
     * @param string $description Description of the action.
     */
    private static function log_action( $user_id, $action_type, $description ) {
        self::initialize();
        if ( self::$admin_instance && method_exists( self::$admin_instance, 'log_action' ) ) {
            self::$admin_instance->log_action( $user_id, $action_type, $description );
        } else {
            // Fallback logging if CPD_Admin instance is not available.
            self::$wpdb->insert(
                self::$log_table,
                array(
                    'user_id'     => $user_id,
                    'action_type' => $action_type,
                    'description' => $description,
                ),
                array( '%d', '%s', '%s' )
            );
        }
    }

    /**
     * Generates CSV content from an array of data.
     *
     * @param array $data The data rows to include in the CSV.
     * @param array $columns The specific columns to include.
     * @return string|false CSV content on success, false on failure.
     */
    private static function generate_csv( $data, $columns ) {
        if ( empty( $data ) || empty( $columns ) ) {
            return false;
        }

        $csv_output = fopen( 'php://temp', 'r+' );
        fputcsv( $csv_output, $columns ); // Headers

        foreach ( $data as $row ) {
            $row_data = [];
            foreach ( $columns as $col ) {
                $row_data[] = $row[ $col ] ?? ''; // Get column value, default to empty string if not found
            }
            fputcsv( $csv_output, $row_data );
        }

        rewind( $csv_output );
        $csv_content = stream_get_contents( $csv_output );
        fclose( $csv_output );

        return $csv_content;
    }

    /**
     * Sends CRM feed emails for visitors marked for CRM addition.
     * Now uses webhook instead of email.
     * Can be triggered by cron or on-demand.
     *
     * @param string|null $account_id_filter Optional. Specific account_id to send for. Null to send for all eligible.
     * @param int $user_id The ID of the user triggering the send (0 for cron).
     */
    public static function send_crm_feed_emails( $account_id_filter = null, $user_id = 0 ) {
        // Replace email functionality with webhook calls
        return self::send_crm_webhook_data( $account_id_filter, $user_id );
    }


    /**
     * Schedules the daily CRM email event.
     * This method should be called during plugin activation.
     */
    public static function schedule_daily_crm_email() {
        if ( ! wp_next_scheduled( 'cpd_daily_crm_email_event' ) ) {
            wp_schedule_event( time(), 'daily', 'cpd_daily_crm_email_event' );
        }
    }

    /**
     * Unchedules the daily CRM email event.
     * This method should be called during plugin deactivation.
     */
    public static function unschedule_daily_crm_email() {
        wp_clear_scheduled_hook( 'cpd_daily_crm_email_event' );
    }

    /**
     * Callback for the daily cron event.
     * Now triggers webhook calls instead of emails.
     */
    public static function daily_crm_email_cron_callback() {
        self::initialize();
        $scheduled_time = get_option( 'cpd_crm_email_schedule_hour', '09' ); // Default 9 AM
        $current_hour = (int) current_time( 'H' );

        // Only send if the current hour matches the scheduled hour
        if ( $current_hour === (int) $scheduled_time ) {
            self::log_action( 0, 'CRON_TRIGGERED', 'Attempting daily CRM webhook send via cron.' );
            self::send_crm_webhook_data( null, 0 ); // Null account_id_filter for all clients, user_id 0 for cron
        } else {
            self::log_action( 0, 'CRON_SKIPPED', 'Daily CRM webhook cron triggered, but current hour (' . $current_hour . ') does not match scheduled hour (' . $scheduled_time . ').' );
        }
    }
    /**
     * AJAX callback to undo the is_crm_added flag for a visitor.
     */
    public static function ajax_undo_crm_added() {
        self::initialize();
        if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'cpd_admin_nonce' ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed.' ) );
        }

        $visitor_id_internal = isset( $_POST['visitor_id_internal'] ) ? intval( $_POST['visitor_id_internal'] ) : 0; // Use internal 'id'

        if ( $visitor_id_internal <= 0 ) {
            wp_send_json_error( array( 'message' => 'Invalid visitor ID.' ) );
        }

        $updated = self::$wpdb->update(
            self::$visitor_table,
            array( 'is_crm_added' => 0 ), // Set back to 0
            array( 'id' => $visitor_id_internal ), // Use internal 'id' for update
            array( '%d' ),
            array( '%d' )
        );

        if ( $updated !== false ) {
            self::log_action( get_current_user_id(), 'CRM_FLAG_UNDO', 'Visitor ID ' . $visitor_id_internal . ' CRM added flag undone via AJAX.' );
            wp_send_json_success( array( 'message' => 'CRM flag undone successfully.' ) );
        } else {
            self::log_action( get_current_user_id(), 'CRM_FLAG_UNDO_FAILED', 'Failed to undo CRM added flag for visitor ID ' . $visitor_id_internal . '.' );
            wp_send_json_error( array( 'message' => 'Failed to undo CRM flag.' ) );
        }
    }

    /**
     * AJAX callback to get eligible visitors for the settings page list.
     */
    public static function ajax_get_eligible_visitors() {
        self::initialize();
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ) );
        }

        // Validate nonce for this specific AJAX action if needed, otherwise use general admin nonce
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'cpd_admin_nonce' ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed.' ) );
        }

        $account_id_filter = isset( $_POST['account_id'] ) && $_POST['account_id'] !== 'all' ? sanitize_text_field( $_POST['account_id'] ) : null;

        $where_clauses = ["is_crm_added = %d", "crm_sent IS NULL"];
        $prepare_args = [1];

        if ( $account_id_filter ) {
            $where_clauses[] = "account_id = %s";
            $prepare_args[] = $account_id_filter;
        }

        $sql = "SELECT id, first_name, last_name, company_name, linkedin_url, city, state, zipcode, last_seen_at, recent_page_count, account_id
                FROM %i WHERE " . implode(" AND ", $where_clauses) . " ORDER BY last_seen_at DESC";

        $results = self::$wpdb->get_results( self::$wpdb->prepare( $sql, self::$visitor_table, ...$prepare_args ) );

        if ( self::$wpdb->last_error ) {
            self::log_action( get_current_user_id(), 'GET_ELIGIBLE_VISITORS_FAILED', 'Database error fetching eligible visitors: ' . self::$wpdb->last_error );
            wp_send_json_error( array( 'message' => 'Database error fetching visitors.' ) );
        }

        wp_send_json_success( array( 'visitors' => $results ) );
    }

    /**
     * AJAX callback to trigger on-demand CRM email send.
     */
    public static function ajax_trigger_on_demand_send() {
        self::initialize();
        if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'cpd_admin_nonce' ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed.' ) );
        }

        $account_id = isset( $_POST['account_id'] ) && $_POST['account_id'] !== 'all' ? sanitize_text_field( $_POST['account_id'] ) : null;

        if ( empty( $account_id ) ) {
            wp_send_json_error( array( 'message' => 'Please select a specific client to send on-demand emails for.' ) );
        }

        $user_id = get_current_user_id();
        $success = self::send_crm_feed_emails( $account_id, $user_id );

        if ( $success ) {
            wp_send_json_success( array( 'message' => 'On-demand CRM email sent successfully to client: ' . $account_id . ' (if eligible data existed).' ) );
        } else {
            wp_send_json_error( array( 'message' => 'Failed to send on-demand CRM email for client: ' . $account_id . '. Check logs for details.' ) );
        }
    }


    /**
     * AJAX callback to trigger on-demand CRM webhook send (replaces email version).
     */
    public static function ajax_trigger_on_demand_send_webhook() {
        self::initialize();
        if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'cpd_admin_nonce' ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed.' ) );
        }

        $account_id = isset( $_POST['account_id'] ) && $_POST['account_id'] !== 'all' ? sanitize_text_field( $_POST['account_id'] ) : null;

        if ( empty( $account_id ) ) {
            wp_send_json_error( array( 'message' => 'Please select a specific client to send on-demand emails for.' ) );
        }

        $user_id = get_current_user_id();
        $success = self::send_crm_webhook_data( $account_id, $user_id );

        if ( $success ) {
            wp_send_json_success( array( 'message' => 'On-demand CRM webhook sent successfully to client: ' . $account_id . ' (if eligible data existed).' ) );
        } else {
            wp_send_json_error( array( 'message' => 'Failed to send on-demand CRM webhook for client: ' . $account_id . '. Check logs for details.' ) );
        }
    }



    /**
     * Sends CRM data to Make.com webhook instead of email.
     * This is the new webhook-based approach.
     *
     * @param string|null $account_id_filter Optional. Specific account_id to send for. Null to send for all eligible.
     * @param int $user_id The ID of the user triggering the send (0 for cron).
     * @return bool True if at least one webhook was sent successfully, false otherwise.
     */
    public static function send_crm_webhook_data( $account_id_filter = null, $user_id = 0 ) {
        self::initialize();

        $webhook_url = get_option( 'cpd_webhook_url', '' );
        $api_key = get_option( 'cpd_makecom_api_key', '' );

        if ( empty( $webhook_url ) ) {
            self::log_action( $user_id, 'WEBHOOK_FAILED', 'CRM webhook send failed: Webhook URL not configured.' );
            return false;
        }

        if ( empty( $api_key ) ) {
            self::log_action( $user_id, 'WEBHOOK_FAILED', 'CRM webhook send failed: API key not configured.' );
            return false;
        }

        $account_ids_to_process = [];
        if ( $account_id_filter ) {
            // Validate account_id_filter against existing clients
            $client_exists = self::$wpdb->get_var( self::$wpdb->prepare( "SELECT account_id FROM %i WHERE account_id = %s", self::$client_table, $account_id_filter ) );
            if ( $client_exists ) {
                $account_ids_to_process[] = $client_exists;
            } else {
                self::log_action( $user_id, 'WEBHOOK_FAILED', 'Webhook send failed: Invalid Account ID provided: ' . esc_html($account_id_filter) );
                return false;
            }
        } else {
            // Get all unique account IDs that have visitors flagged for CRM addition but not yet sent.
            $account_ids_to_process = self::$wpdb->get_col(
                self::$wpdb->prepare(
                    "SELECT DISTINCT account_id FROM %i WHERE is_crm_added = %d AND crm_sent IS NULL",
                    self::$visitor_table,
                    1
                )
            );
        }

        if ( empty( $account_ids_to_process ) ) {
            self::log_action( $user_id, 'WEBHOOK_NO_DATA', 'CRM webhook: No eligible visitors found for any account.' . ( $account_id_filter ? ' (Filtered by Account ID: ' . $account_id_filter . ')' : '' ) );
            return true; // No data to send, but not a failure.
        }

        $webhook_sent_count = 0;
        $failed_webhook_count = 0;

        foreach ( $account_ids_to_process as $account_id ) {
            // Get the client's CRM email address and info
            $client_info = self::$wpdb->get_row(
                self::$wpdb->prepare( "SELECT client_name, crm_feed_email FROM %i WHERE account_id = %s", self::$client_table, $account_id )
            );

            if ( ! $client_info || empty( $client_info->crm_feed_email ) ) {
                self::log_action( $user_id, 'WEBHOOK_FAILED', 'CRM webhook failed: No email configured for Account ID: ' . $account_id . ' (Client: ' . ($client_info->client_name ?? 'N/A') . ')' );
                $failed_webhook_count++;
                continue;
            }

            // Get eligible visitor data for this client
            $visitors_data = self::$wpdb->get_results(
                self::$wpdb->prepare(
                    "SELECT * FROM %i WHERE account_id = %s AND is_crm_added = %d AND crm_sent IS NULL",
                    self::$visitor_table,
                    $account_id,
                    1
                ),
                ARRAY_A
            );

            if ( empty( $visitors_data ) ) {
                self::log_action( $user_id, 'WEBHOOK_NO_DATA_CLIENT', 'CRM webhook: No eligible visitors found for Account ID: ' . $account_id );
                continue;
            }

            // Transform data into the required JSON structure
            $webhook_payload = array(
                'client_email' => $client_info->crm_feed_email,
                'visitors' => array()
            );

            foreach ( $visitors_data as $visitor ) {
                $visitor_payload = array(
                    'company_data' => array(
                        'company_name' => $visitor['company_name'] ?? '',
                        'estimated_employee_count' => $visitor['estimated_employee_count'] ?? '',
                        'estimated_revenue' => $visitor['estimated_revenue'] ?? '',
                        'industry' => $visitor['industry'] ?? '',
                        'website' => $visitor['website'] ?? '',
                        'city' => $visitor['city'] ?? '',
                        'state' => $visitor['state'] ?? '',
                        'zipcode' => $visitor['zipcode'] ?? ''
                    ),
                    'individual_data' => array(
                        'first_name' => $visitor['first_name'] ?? '',
                        'last_name' => $visitor['last_name'] ?? '',
                        'email' => $visitor['email'] ?? '',
                        'job_title' => $visitor['job_title'] ?? '',
                        'linkedin_url' => $visitor['linkedin_url'] ?? '',
                        'city' => $visitor['city'] ?? '',
                        'state' => $visitor['state'] ?? '',
                        'zipcode' => $visitor['zipcode'] ?? '',
                        'most_recent_referrer' => $visitor['most_recent_referrer'] ?? '',
                        'recent_page_count' => $visitor['recent_page_count'] ?? 0,
                        'recent_page_urls' => $visitor['recent_page_urls'] ?? '',
                        'all_time_page_views' => $visitor['all_time_page_views'] ?? 0,
                        'first_seen_at' => $visitor['first_seen_at'] ?? '',
                        'last_seen_at' => $visitor['last_seen_at'] ?? '',
                        'tags' => $visitor['tags'] ?? '',
                        'filter_matches' => $visitor['filter_matches'] ?? ''
                    )
                );
                
                $webhook_payload['visitors'][] = $visitor_payload;
            }

            // Send webhook request
            $headers = array(
                'Content-Type' => 'application/json',
                'x-make-apikey' => $api_key,
                'Client-Email' => $client_info->crm_feed_email
            );

            $response = wp_remote_post( $webhook_url, array(
                'method' => 'POST',
                'timeout' => 30,
                'headers' => $headers,
                'body' => wp_json_encode( $webhook_payload )
            ) );

            // Handle webhook response
            if ( is_wp_error( $response ) ) {
                self::log_action( $user_id, 'WEBHOOK_FAILED', 'CRM webhook failed for ' . $client_info->client_name . ' (Account ID: ' . $account_id . '). Error: ' . $response->get_error_message() );
                $failed_webhook_count++;
                continue;
            }

            $response_code = wp_remote_retrieve_response_code( $response );
            
            if ( $response_code >= 200 && $response_code < 300 ) {
                // Success - Update crm_sent for sent visitors
                $visitors_to_update_ids = wp_list_pluck( $visitors_data, 'id' );
                if ( ! empty( $visitors_to_update_ids ) ) {
                    $ids_string = implode( ',', array_map( 'intval', $visitors_to_update_ids ) );
                    $current_timestamp = current_time( 'mysql' );
                    
                    self::$wpdb->query(
                        self::$wpdb->prepare(
                            "UPDATE %i SET crm_sent = %s WHERE id IN ($ids_string)",
                            self::$visitor_table,
                            $current_timestamp
                        )
                    );
                }

                self::log_action( $user_id, 'WEBHOOK_SENT_SUCCESS', 'CRM webhook sent successfully to ' . $client_info->client_name . ' (' . $client_info->crm_feed_email . '). ' . count($visitors_data) . ' records processed (Account ID: ' . $account_id . ').' );
                $webhook_sent_count++;
            } else {
                $response_body = wp_remote_retrieve_body( $response );
                self::log_action( $user_id, 'WEBHOOK_FAILED', 'CRM webhook failed for ' . $client_info->client_name . ' (Account ID: ' . $account_id . '). HTTP Code: ' . $response_code . '. Response: ' . $response_body );
                $failed_webhook_count++;
            }
        }

        return $webhook_sent_count > 0; // Return true if at least one webhook was sent
    }

}