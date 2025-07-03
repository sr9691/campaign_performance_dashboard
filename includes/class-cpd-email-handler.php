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
     * Can be triggered by cron or on-demand.
     *
     * @param string|null $account_id_filter Optional. Specific account_id to send for. Null to send for all eligible.
     * @param int $user_id The ID of the user triggering the send (0 for cron).
     */
    public static function send_crm_feed_emails( $account_id_filter = null, $user_id = 0 ) {
        self::initialize();

        $account_ids_to_email = [];
        if ( $account_id_filter ) {
            // Validate account_id_filter against existing clients
            $client_exists = self::$wpdb->get_var( self::$wpdb->prepare( "SELECT account_id FROM %i WHERE account_id = %s", self::$client_table, $account_id_filter ) );
            if ( $client_exists ) {
                $account_ids_to_email[] = $client_exists;
            } else {
                self::log_action( $user_id, 'EMAIL_SEND_FAILED', 'On-demand CRM email send failed: Invalid Account ID provided: ' . esc_html($account_id_filter) );
                return false; // Invalid account ID
            }
        } else {
            // Get all unique account IDs that have visitors flagged for CRM addition but not yet sent.
            $account_ids_to_email = self::$wpdb->get_col(
                self::$wpdb->prepare(
                    "SELECT DISTINCT account_id FROM %i WHERE is_crm_added = %d AND crm_sent IS NULL",
                    self::$visitor_table,
                    1
                )
            );
        }

        if ( empty( $account_ids_to_email ) ) {
            self::log_action( $user_id, 'EMAIL_NO_DATA', 'CRM email feed: No eligible visitors found for any account.' . ( $account_id_filter ? ' (Filtered by Account ID: ' . $account_id_filter . ')' : '' ) );
            return true; // No emails to send, but not a failure.
        }

        $email_sent_count = 0;
        $failed_email_count = 0;

        foreach ( $account_ids_to_email as $account_id ) {
            // Get the client's CRM email address.
            $client_info = self::$wpdb->get_row(
                self::$wpdb->prepare( "SELECT client_name, crm_feed_email FROM %i WHERE account_id = %s", self::$client_table, $account_id )
            );

            if ( ! $client_info || empty( $client_info->crm_feed_email ) ) {
                self::log_action( $user_id, 'EMAIL_FAILED', 'CRM email feed failed: No email configured for Account ID: ' . $account_id . ' (Client: ' . ($client_info->client_name ?? 'N/A') . ')' );
                $failed_email_count++;
                continue;
            }

            // Get eligible visitor data for this client.
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
                self::log_action( $user_id, 'EMAIL_NO_DATA_CLIENT', 'CRM email feed: No eligible visitors found for Account ID: ' . $account_id );
                continue;
            }

            // Define columns for Company and Individual CSVs
            $company_columns = [
                'company_name',
                'estimated_employee_count',
                'estimated_revenue',
                'industry',
                'website',
                'city',
                'state',
                'zipcode',
            ];

            $individual_columns = [
                'first_name',
                'last_name',
                'email',
                'job_title',
                'linkedin_url',
                'city',
                'state',
                'zipcode',
                'most_recent_referrer',
                'recent_page_count',
                'recent_page_urls',
                'all_time_page_views',
                'first_seen_at',
                'last_seen_at',
                'tags',
                'filter_matches',
            ];

            $attachments = [];
            $total_records_in_email = 0;

            // Generate Company CSV
            $company_csv_content = self::generate_csv( $visitors_data, $company_columns );
            if ( $company_csv_content ) {
                $attachments[] = array(
                    'name'    => 'crm_feed_company_' . $account_id . '_' . date( 'Y-m-d' ) . '.csv',
                    'content' => $company_csv_content,
                    'type'    => 'text/csv',
                );
                $total_records_in_email += count($visitors_data); // All visitors contribute to both, if applicable
            } else {
                 self::log_action( $user_id, 'CSV_GEN_FAILED', 'Failed to generate Company CSV for Account ID: ' . $account_id );
            }

            // Generate Individual CSV
            $individual_csv_content = self::generate_csv( $visitors_data, $individual_columns );
            if ( $individual_csv_content ) {
                $attachments[] = array(
                    'name'    => 'crm_feed_individual_' . $account_id . '_' . date( 'Y-m-d' ) . '.csv',
                    'content' => $individual_csv_content,
                    'type'    => 'text/csv',
                );
            } else {
                 self::log_action( $user_id, 'CSV_GEN_FAILED', 'Failed to generate Individual CSV for Account ID: ' . $account_id );
            }

            if ( empty( $attachments ) ) {
                self::log_action( $user_id, 'EMAIL_FAILED', 'CRM email feed failed: No CSV attachments generated for Account ID: ' . $account_id );
                $failed_email_count++;
                continue;
            }

            // Prepare and send the email
            $to = array_map( 'sanitize_email', explode( ',', $client_info->crm_feed_email ) ); // Supports comma-separated emails.
            $subject = 'Daily CRM Feed for ' . $client_info->client_name . ' - ' . date( 'Y-m-d' );
            $message = 'Attached are the daily visitor data files you marked for CRM integration (Company and Individual leads).';

            // Temporarily add filter for attachments
            $filter_id = 'cpd_mail_attachments_' . uniqid();
            add_filter( 'wp_mail_attachments', function( $attachments_list ) use ( $attachments ) {
                return array_merge( $attachments_list, $attachments );
            }, 10, 1, $filter_id ); // Add priority and acceptance to remove specific instance

            $email_sent = wp_mail( $to, $subject, $message );

            // Remove the attachment filter to prevent side effects
            remove_filter( 'wp_mail_attachments', function( $attachments_list ) use ( $attachments ) {
                return array_merge( $attachments_list, $attachments );
            }, 10, 1, $filter_id );


            // Log the outcome and update records
            if ( $email_sent ) {
                // Update crm_sent for sent visitors
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

                self::log_action( $user_id, 'EMAIL_SENT_SUCCESS', 'CRM feed sent to ' . $client_info->client_name . ' (' . implode(', ', $to) . '). ' . count($visitors_data) . ' records processed (ID: ' . $account_id . ').' );
                $email_sent_count++;

            } else {
                self::log_action( $user_id, 'EMAIL_SENT_FAILED', 'Failed to send CRM feed to ' . $client_info->client_name . ' (' . implode(', ', $to) . ') for Account ID: ' . $account_id . '. WordPress mail error: ' . ( print_r(error_get_last(), true) ) );
                $failed_email_count++;
            }
        }
        
        return $email_sent_count > 0; // Return true if at least one email was sent
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
     * Triggers the send_crm_feed_emails function for all eligible clients.
     */
    public static function daily_crm_email_cron_callback() {
        self::initialize();
        $scheduled_time = get_option( 'cpd_crm_email_schedule_hour', '09' ); // Default 9 AM
        $current_hour = (int) current_time( 'H' );

        // Only send if the current hour matches the scheduled hour
        if ( $current_hour === (int) $scheduled_time ) {
            self::log_action( 0, 'CRON_TRIGGERED', 'Attempting daily CRM email send via cron.' );
            self::send_crm_feed_emails( null, 0 ); // Null account_id_filter for all clients, user_id 0 for cron
        } else {
            self::log_action( 0, 'CRON_SKIPPED', 'Daily CRM email cron triggered, but current hour (' . $current_hour . ') does not match scheduled hour (' . $scheduled_time . ').' );
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
}