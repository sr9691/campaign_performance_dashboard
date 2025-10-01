<?php
/**
 * REST API handler for the Campaign Performance Dashboard plugin.
 *
 * @package CPD_Dashboard
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CPD_API {

    private $plugin_name;
    private $log_action_callback; // To store the callable for logging
    private $data_provider; // Instance of CPD_Data_Provider

    public function __construct( $plugin_name ) {
        global $wpdb;
        $this->plugin_name = $plugin_name;
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
        require_once CPD_DASHBOARD_PLUGIN_DIR . 'includes/class-cpd-data-provider.php';
        $this->data_provider = new CPD_Data_Provider();
    }

    /**
     * Sets the log_action callback from the CPD_Admin class.
     * This allows CPD_API to log actions without direct dependency on CPD_Admin instance.
     * @param callable $callback The callback function for logging.
     */
    public function set_log_action_callback( $callback ) {
        if ( is_callable( $callback ) ) {
            $this->log_action_callback = $callback;
        }
    }

    /**
     * Helper to log an action using the provided callback.
     * @param int $user_id The user ID performing the action (0 for API).
     * @param string $action_type Type of action.
     * @param string $description Description of the action.
     */
    private function log_api_action( $user_id, $action_type, $description ) {
        if ( $this->log_action_callback ) {
            call_user_func( $this->log_action_callback, $user_id, $action_type, $description );
        }
    }

    /**
     * Register the REST API routes for the plugin.
     */
    public function register_routes() {
        $namespace = $this->plugin_name . '/v1';
        
        register_rest_route( $namespace, '/data-import', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'handle_data_import_ping' ),
            'permission_callback' => array( $this, 'verify_api_key' ),
        ) );

        register_rest_route( $namespace, '/campaign-data-import', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'handle_campaign_data_import' ),
            'permission_callback' => array( $this, 'verify_api_key' ),
        ) );

        register_rest_route( $namespace, '/visitor-data-import', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'handle_visitor_data_import' ),
            'permission_callback' => array( $this, 'verify_api_key' ),
        ) );
        
        register_rest_route( $namespace, '/campaign-data', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_campaign_data_rest' ),
            'permission_callback' => array( $this, 'get_rest_permissions_check' ),
            'args'                => array(
                'account_id' => array(
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => 'rest_validate_request_arg',
                    'required'          => false,
                    'description'       => 'Filter by client account ID. Use "all" for all clients if admin.',
                ),
                'start_date' => array(
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => 'rest_validate_request_arg',
                    'required'          => false,
                    'description'       => 'Start date for data (YYYY-MM-DD). Defaults to 2025-01-01.',
                ),
                'end_date' => array(
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => 'rest_validate_request_arg',
                    'required'          => false,
                    'description'       => 'End date for data (YYYY-MM-DD). Defaults to current date.',
                ),
                'group_by' => array(
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => function($param, $request, $key) {
                        return in_array($param, ['ad_group', 'date']);
                    },
                    'required'          => false,
                    'default'           => 'ad_group',
                    'description'       => 'Group data by "ad_group" or "date".',
                ),
            ),
        ) );

        register_rest_route( $namespace, '/visitor-data', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_visitor_data_rest' ),
            'permission_callback' => array( $this, 'get_rest_permissions_check' ),
            'args'                => array(
                'account_id' => array(
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => 'rest_validate_request_arg',
                    'required'          => false,
                    'description'       => 'Filter by client account ID. Only shows unarchived and non-CRM added.',
                ),
            ),
        ) );

        register_rest_route( $namespace, '/campaign-data-csv-import', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'handle_campaign_csv_import' ),
            'permission_callback' => array( $this, 'verify_api_key' ),
            'args'                => array(
                'csv_url' => array(
                    'required'          => true,
                    'sanitize_callback' => 'esc_url_raw',
                    'validate_callback' => function($param, $request, $key) {
                        return filter_var($param, FILTER_VALIDATE_URL) !== false;
                    },
                    'description'       => 'URL of the CSV file to download and import',
                ),
            ),
        ) );
        
        register_rest_route( $this->plugin_name . '/v1', '/trigger-email/', array(
            'methods'             => 'GET', // Or 'POST' for more security
            'callback'            => array( $this, 'handle_email_trigger' ),
            // Use a secure permission callback to protect this endpoint
            'permission_callback' => array( $this, 'verify_api_key' ) 
        ) );        

    }

    /**
     * Handles the API request to trigger the daily CRM email webhook.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_REST_Response The API response.
     */
    public function handle_email_trigger( WP_REST_Request $request ) {
        // You should use the log_action_callback here
        if ( is_callable( $this->log_action_callback ) ) {
            call_user_func( $this->log_action_callback, 0, 'API_TRIGGERED', 'Daily CRM webhook send triggered via API.' );
        }
    
        // Include the CPD_Email_Handler class if it's not already loaded
        if ( ! class_exists( 'CPD_Email_Handler' ) ) {
            require_once CPD_DASHBOARD_PLUGIN_DIR . 'includes/class-cpd-email-handler.php';
        }
    
        // Call the static method from the email handler class
        CPD_Email_Handler::send_crm_webhook_data( null, 0 );
    
        return new WP_REST_Response( array( 'success' => true, 'message' => 'Email webhook triggered successfully.' ), 200 );
    }


    /**
     * Verifies the custom API key sent in the request header.
     * This is a simple verification method; for production, consider a more robust system.
     *
     * @param WP_REST_Request $request The REST API request.
     * @return bool|WP_Error True if the key is valid, WP_Error otherwise.
     */
    public function verify_api_key( WP_REST_Request $request ) {
        $api_key_stored = get_option( 'cpd_api_key', '' );
        $api_key_header = $request->get_header( 'X-API-Key' );

        error_log('CPD_API: Verifying API Key. Provided: ' . ($api_key_header ?? 'N/A') . ', Stored: ' . ($api_key_stored ? $api_key_stored : 'NOT SET'));
        
        if ( empty( $api_key_stored ) ) {
            $this->log_api_action( 0, 'API_KEY_CHECK', 'API Key not set in plugin settings. Request from IP: ' . ( $_SERVER['REMOTE_ADDR'] ?? 'N/A' ) . ' Path: ' . $request->get_route() );
            return new WP_Error( 'rest_forbidden', 'API key not configured.', array( 'status' => 401 ) );
        }

        if ( ! $api_key_header || $api_key_header !== $api_key_stored ) {
            $this->log_api_action( 0, 'API_AUTH_FAILED', 'Invalid API key provided. Request from IP: ' . ( $_SERVER['REMOTE_ADDR'] ?? 'N/A' ) . ' Path: ' . $request->get_route() );
            return new WP_Error( 'rest_forbidden', 'Invalid API key.', array( 'status' => 401 ) );
        }

        return true;
    }

    /**
     * Handles a general data import ping. Can be removed if not needed.
     * This now only logs the ping and returns success.
     *
     * @param WP_REST_Request $request The REST API request.
     * @return WP_REST_Response The API response.
     */
    public function handle_data_import_ping( WP_REST_Request $request ) {
        $json_payload = $request->get_json_params();
        $account_id = sanitize_text_field( $json_payload['account_id'] ?? 'N/A' );

        $log_description = 'General API Import Ping received for Account ID: ' . $account_id . '. Payload size: ' . strlen( $request->get_body() ) . ' bytes.';
        $this->log_api_action( 0, 'API_PING_RECEIVED', $log_description );

        return new WP_REST_Response( array( 'message' => 'General data import ping received and logged.' ), 200 );
    }


    /**
     * Handles the campaign data import via REST API (POST method).
     * This method expects a COMPLETE daily feed for a given date.
     * It will DELETE existing data for that account and date before inserting new data.
     *
     * @param WP_REST_Request $request The REST API request.
     * @return WP_REST_Response The API response.
     */
    public function handle_campaign_data_import( WP_REST_Request $request ) {
        global $wpdb;
        $json_payload = $request->get_json_params(); // This is now expected to be an array of campaign data objects

        if ( empty( $json_payload ) || ! is_array($json_payload) ) {
            $this->log_api_action( 0, 'API_CAMPAIGN_IMPORT_FAILED', 'Empty or invalid JSON payload for campaign data (expected array). Request from IP: ' . ( $_SERVER['REMOTE_ADDR'] ?? 'N/A' ) );
            return new WP_REST_Response( array( 'message' => 'Empty or invalid JSON payload for campaign data (expected array of items).', 'status' => 400 ), 400 );
        }

        $campaign_data_table = $this->wpdb->prefix . 'cpd_campaign_data';

        $import_status = 'success';
        $import_messages = [];
        $campaign_rows_processed = 0;
        $rows_deleted = 0;
        $processed_dates_accounts = []; // To track which date/account pairs were processed in this batch

        try {
            // Iterate through the payload to collect unique account_id/date combinations for deletion
            // and to group the data for insertion.
            $data_for_insertion = []; // Collect all valid data rows here
            foreach ($json_payload as $row) {
                // Ensure critical fields for unique identification and deletion are present
                $account_id = sanitize_text_field($row['account_id'] ?? '');
                $date = sanitize_text_field($row['date'] ?? '');
                $ad_group_id = sanitize_text_field($row['ad_group_id'] ?? ''); // ad_group_id needed for uniqueness

                if (empty($account_id) || empty($date) || empty($ad_group_id)) {
                    $import_status = 'partial_success';
                    $import_messages[] = 'Skipped campaign data row due to missing REQUIRED account_id, date, or ad_group_id: ' . json_encode($row);
                    continue; // Skip invalid rows
                }
                
                $key = $account_id . '_' . $date; // Deletion is by account_id and date
                if (!isset($processed_dates_accounts[$key])) {
                    // STEP 1: Delete existing data for this account_id and date
                    // This is done once per unique account_id/date combination encountered in the feed.
                    $deleted = $wpdb->delete(
                        $campaign_data_table,
                        array(
                            'account_id' => $account_id,
                            'date'       => $date
                        ),
                        array( '%s', '%s' )
                    );

                    if ( $deleted === false ) {
                        $import_status = 'partial_success';
                        $import_messages[] = 'Failed to delete existing campaign data for account_id ' . $account_id . ' and date ' . $date . '. Error: ' . $wpdb->last_error;
                    } else {
                        $rows_deleted += $deleted;
                        $import_messages[] = $deleted . ' existing rows deleted for ' . $account_id . ' on ' . $date . '.';
                    }
                    $processed_dates_accounts[$key] = true; // Mark this account_id/date pair as having had its data deleted
                }
                // Add the current row to the list for insertion
                $data_for_insertion[] = $row;
            }

            // STEP 2: Insert new data from the payload (all collected valid rows)
            if ( ! empty( $data_for_insertion ) ) {
                foreach ( $data_for_insertion as $row ) {
                    // Re-extract account_id, date, ad_group_id for current row for insertion context (for safety/clarity)
                    $account_id = sanitize_text_field($row['account_id'] ?? '');
                    $date = sanitize_text_field($row['date'] ?? '');
                    $ad_group_id = sanitize_text_field($row['ad_group_id'] ?? '');

                    // Ensure these crucial fields are still present before insertion
                    if (empty($account_id) || empty($date) || empty($ad_group_id)) {
                        // This case should ideally not be reached if the first loop correctly filters.
                        // However, it's a fail-safe.
                        $import_status = 'partial_success';
                        $import_messages[] = 'Skipped campaign data row during insertion due to missing account_id, date, or ad_group_id: ' . json_encode($row);
                        continue;
                    }

                    $data_to_insert = array(
                        'account_id'      => $account_id,
                        'date'            => $date,
                        'organization_name' => sanitize_text_field( $row['organization_name'] ?? null ),
                        'account_name'    => sanitize_text_field( $row['account_name'] ?? null ),
                        'campaign_id'     => sanitize_text_field( $row['campaign_id'] ?? null ),
                        'campaign_name'   => sanitize_text_field( $row['campaign_name'] ?? null ),
                        'campaign_start_date' => sanitize_text_field( $row['campaign_start_date'] ?? null ),
                        'campaign_end_date' => sanitize_text_field( $row['campaign_end_date'] ?? null ),
                        'campaign_budget' => floatval( $row['campaign_budget'] ?? 0 ),
                        'ad_group_id'     => $ad_group_id, // Use the extracted and validated ad_group_id
                        'ad_group_name'   => sanitize_text_field( $row['ad_group_name'] ?? null ),
                        'creative_id'     => sanitize_text_field( $row['creative_id'] ?? null ),
                        'creative_name'   => sanitize_text_field( $row['creative_name'] ?? null ),
                        'creative_size'   => sanitize_text_field( $row['creative_size'] ?? null ),
                        'creative_url'    => esc_url_raw( $row['creative_url'] ?? null ),
                        'advertiser_bid_type' => sanitize_text_field( $row['advertiser_bid_type'] ?? null ),
                        'budget_type'     => sanitize_text_field( $row['budget_type'] ?? null ),
                        'cpm'             => floatval( $row['cpm'] ?? 0 ),
                        'cpv'             => floatval( $row['cpv'] ?? 0 ),
                        'market'          => sanitize_text_field( $row['market'] ?? null ),
                        'contact_number'  => sanitize_text_field( $row['contact_number'] ?? null ),
                        'external_ad_group_id' => sanitize_text_field( $row['external_ad_group_id'] ?? null ),
                        'total_impressions_contracted' => intval( $row['total_impressions_contracted'] ?? 0 ),
                        'impressions'     => intval( $row['impressions'] ?? 0 ),
                        'clicks'          => intval( $row['clicks'] ?? 0 ),
                        'ctr'             => floatval( $row['ctr'] ?? 0 ),
                        'visits'          => intval( $row['visits'] ?? 0 ),
                        'total_spent'     => floatval( $row['total_spent'] ?? 0 ),
                        'secondary_actions' => intval( $row['secondary_actions'] ?? 0 ),
                        'secondary_action_rate' => floatval( $row['secondary_action_rate'] ?? 0 ),
                        'website'         => sanitize_text_field( $row['website'] ?? null ),
                        'direction'       => sanitize_text_field( $row['direction'] ?? null ),
                        'click_to_call'   => intval( $row['click_to_call'] ?? 0 ),
                        'cta_more_info'   => intval( $row['cta_more_info'] ?? 0 ),
                        'coupon'          => intval( $row['coupon'] ?? 0 ),
                        'daily_reach'     => intval( $row['daily_reach'] ?? 0 ),
                        'video_start'     => intval( $row['video_start'] ?? 0 ),
                        'first_quartile'  => intval( $row['first_quartile'] ?? 0 ),
                        'midpoint'        => intval( $row['midpoint'] ?? 0 ),
                        'third_quartile'  => intval( $row['third_quartile'] ?? 0 ),
                        'video_complete'  => intval( $row['video_complete'] ?? 0 ),
                    );
                    
                    $inserted = $wpdb->insert( $campaign_data_table, $data_to_insert );
                                                                                   
                    if ( $inserted === false ) {
                        $import_status = 'partial_success';
                        $import_messages[] = 'Failed to insert campaign data row for account_id ' . $account_id . ' ad_group ' . ($row['ad_group_id'] ?? 'N/A') . ' on date ' . ($row['date'] ?? 'N/A') . '. Error: ' . $wpdb->last_error;
                    } else {
                        $campaign_rows_processed++;
                    }
                }
            } else {
                $import_status = 'success'; // An empty array is a valid complete feed for a date/account.
                $import_messages[] = 'No valid campaign data items found in payload for insertion.';
            }

        } catch (Exception $e) {
            $import_status = 'failed';
            $import_messages[] = 'Critical error during campaign data import: ' . $e->getMessage();
            $response_status = 500; // Server error
            $this->log_api_action( 0, 'API_CAMPAIGN_IMPORT_FATAL', 'Fatal error for Campaign Data Import. ' . $e->getMessage() . ' Request from IP: ' . ( $_SERVER['REMOTE_ADDR'] ?? 'N/A' ) );
            return new WP_REST_Response( array( 'message' => 'Campaign data import failed critically.', 'details' => $import_messages ), $response_status );
        }

        $log_description = 'API Campaign Data Import. Status: ' . $import_status . '. Total rows deleted: ' . $rows_deleted . '. Total rows processed: ' . $campaign_rows_processed . '.';
        if ( ! empty( $import_messages ) ) {
            // $log_description .= ' Messages: ' . implode('; ', $import_messages);
        }
        $this->log_api_action( 0, 'API_CAMPAIGN_IMPORT', $log_description );

        $response_status = 200;
        if ( $import_status === 'partial_success' ) {
            $response_status = 202; // Accepted, but with some non-fatal issues
        }

        return new WP_REST_Response( array( 'message' => 'Campaign data import process completed with status: ' . $import_status . '.', 'details' => $import_messages ), $response_status );
    }

    /**
     * Handles the visitor data import via REST API (POST method).
     * This method performs an UPSERT (update or insert) based on linkedin_url and account_id.
     * `visitor_id` is an AUTO_INCREMENT internal ID, not expected in payload.
     *
     * @param WP_REST_Request $request The REST API request.
     * @return WP_REST_Response The API response.
     */
    public function handle_visitor_data_import( WP_REST_Request $request ) {
        global $wpdb;
        $json_payload = $request->get_json_params();

        if ( empty( $json_payload ) || ! is_array($json_payload) ) {
            $this->log_api_action( 0, 'API_VISITOR_IMPORT_FAILED', 'Empty or invalid JSON payload for visitor data (expected array). Request from IP: ' . ( $_SERVER['REMOTE_ADDR'] ?? 'N/A' ) );
            return new WP_REST_Response( array( 'message' => 'Empty or invalid JSON payload for visitor data (expected array of items).', 'status' => 400 ), 400 );
        }

        $visitor_data_table = $wpdb->prefix . 'cpd_visitors';

        $import_status = 'success';
        $import_messages = [];
        $visitor_rows_processed = 0;

        if ( ! empty( $json_payload ) ) {
            foreach ( $json_payload as $row ) {
                // For UPSERT, we now need account_id and linkedin_url to uniquely identify a visitor
                $account_id = sanitize_text_field( $row['account_id'] ?? '' );
                $linkedin_url = sanitize_text_field( $row['linkedin_url'] ?? '' );
                
                // CRITICAL: linkedin_url and account_id are REQUIRED for upsert identification.
                // If linkedin_url is empty, this row cannot be reliably upserted.
                if ( empty( $account_id ) || empty( $linkedin_url ) ) {
                    $import_status = 'partial_success';
                    $import_messages[] = 'Skipped visitor data row due to missing REQUIRED account_id or linkedin_url (for upsert identification): ' . json_encode($row);
                    continue; // Skip invalid rows
                }

                // visitor_id is AUTO_INCREMENT, so it's NOT expected from payload.
                // It will be generated by the DB for new inserts, or implicitly ignored for updates.
                
                $visit_time_formatted = sanitize_text_field( $row['visit_time'] ?? current_time('mysql') );
                $last_seen_at = sanitize_text_field( $row['last_seen_at'] ?? current_time('mysql') );
                $first_seen_at = sanitize_text_field( $row['first_seen_at'] ?? current_time('mysql') );

                // Check for existing visitor using the new UNIQUE KEY (linkedin_url, account_id)
                $existing_id_db = $wpdb->get_var( $wpdb->prepare(
                    "SELECT id FROM %i WHERE linkedin_url = %s AND account_id = %s",
                    $visitor_data_table,
                    $linkedin_url, // Use linkedin_url for lookup
                    $account_id
                ) );

                $data_to_insert = array(
                    'account_id'    => $account_id,
                    'linkedin_url'  => $linkedin_url, // Use extracted and validated linkedin_url
                    'company_name'  => sanitize_text_field( $row['company_name'] ?? '' ),
                    'all_time_page_views' => intval( $row['all_time_page_views'] ?? 0 ),
                    'first_name'    => sanitize_text_field( $row['first_name'] ?? '' ),
                    'last_name'     => sanitize_text_field( $row['last_name'] ?? '' ),
                    'job_title'     => sanitize_text_field( $row['job_title'] ?? '' ),
                    'most_recent_referrer' => sanitize_text_field( $row['most_recent_referrer'] ?? '' ),
                    'recent_page_count' => intval( $row['recent_page_count'] ?? 0 ),
                    'recent_page_urls' => sanitize_text_field( $row['recent_page_urls'] ?? '' ),
                    'tags'          => sanitize_text_field( $row['tags'] ?? '' ),
                    'estimated_employee_count' => sanitize_text_field( $row['estimated_employee_count'] ?? '' ),
                    'estimated_revenue' => sanitize_text_field( $row['estimated_revenue'] ?? '' ),
                    'city'          => sanitize_text_field( $row['city'] ?? '' ),
                    'zipcode'       => sanitize_text_field( $row['zipcode'] ?? '' ),
                    'last_seen_at'  => $last_seen_at,
                    'first_seen_at' => $first_seen_at,
                    'new_profile'   => intval( $row['new_profile'] ?? 0 ),
                    'email'         => sanitize_email( $row['email'] ?? '' ),
                    'website'       => sanitize_text_field( $row['website'] ?? '' ),
                    'industry'      => sanitize_text_field( $row['industry'] ?? '' ),
                    'state'         => sanitize_text_field( $row['state'] ?? '' ),
                    'filter_matches' => sanitize_text_field( $row['filter_matches'] ?? '' ),
                    'profile_type'  => sanitize_text_field( $row['profile_type'] ?? '' ),
                    'status'        => sanitize_text_field( $row['status'] ?? 'active' ),
                    'visit_time'    => $visit_time_formatted,
                );
                
                if ( $existing_id_db ) {
                    // Update existing row identified by linkedin_url + account_id
                    $updated = $wpdb->update(
                        $visitor_data_table,
                        $data_to_insert,
                        array( 'id' => $existing_id_db ) // Update using the primary key ID
                    );
                    if ( $updated === false ) {
                        $import_status = 'partial_success';
                        $import_messages[] = 'Failed to update visitor with linkedin_url ' . $linkedin_url . ' for account_id ' . $account_id . '. Error: ' . $wpdb->last_error;
                    } else {
                        $visitor_rows_processed++;
                    }
                } else {
                    // Insert new row
                    $inserted = $wpdb->insert(
                        $visitor_data_table,
                        $data_to_insert
                    );
                    if ( $inserted === false ) {
                        $import_status = 'partial_success';
                        $import_messages[] = 'Failed to insert visitor with linkedin_url ' . $linkedin_url . ' for account_id ' . $account_id . '. Error: ' . $wpdb->last_error;
                    } else {
                        $visitor_rows_processed++;
                    }
                }
            } 
        } else {
            $import_status = 'success';
            $import_messages[] = 'No visitor data items provided in payload.';
        }

        $log_description = 'API Visitor Data Import. Status: ' . $import_status . '. Rows processed: ' . $visitor_rows_processed . '.';
        if ( ! empty( $import_messages ) ) {
            // $log_description .= ' Messages: ' . implode('; ', $import_messages);
        }
        $this->log_api_action( 0, 'API_VISITOR_IMPORT', $log_description );

        $response_status = 200;
        if ( $import_status === 'partial_success' ) {
            $response_status = 202;
        }

        return new WP_REST_Response( array( 'message' => 'Visitor data import process completed with status: ' . $import_status . '.', 'details' => $import_messages ), $response_status );
    }

    /**
     * NEW - Handles campaign data import from a CSV file URL.
     * Downloads the CSV, parses it, and appends all rows to the database.
     *
     * @param WP_REST_Request $request The REST API request.
     * @return WP_REST_Response The API response.
     */
    public function handle_campaign_csv_import( WP_REST_Request $request ) {
        global $wpdb;
        
        $csv_url = $request->get_param('csv_url');
        // Use the fixed table name that matches your actual database table
        $table_name = 'dr_cpd_campaign_data';
        
        $import_status = 'success';
        $import_messages = [];
        $rows_processed = 0;
        $rows_succeeded = 0;
        $rows_failed = 0;
        
        try {
            // Step 1: Download CSV file
            $csv_content = $this->download_csv_file($csv_url);
            if (!$csv_content) {
                $this->log_api_action(0, 'API_CSV_IMPORT_FAILED', 'Failed to download CSV file from URL: ' . $csv_url);
                return new WP_REST_Response(array(
                    'message' => 'Failed to download CSV file from provided URL.',
                    'status' => 400
                ), 400);
            }
            
            // Step 2: Parse CSV content
            $csv_data = $this->parse_csv_content($csv_content);
            if (empty($csv_data)) {
                $this->log_api_action(0, 'API_CSV_IMPORT_FAILED', 'No valid data found in CSV file from URL: ' . $csv_url);
                return new WP_REST_Response(array(
                    'message' => 'No valid data found in CSV file.',
                    'status' => 400
                ), 400);
            }
            
            // Step 3: Process each row and append to database
            foreach ($csv_data as $row_index => $row) {
                $rows_processed++;
                
                try {
                    $success = $this->insert_campaign_row($table_name, $row, $row_index);
                    if ($success) {
                        $rows_succeeded++;
                    } else {
                        $rows_failed++;
                        $import_status = 'partial_success';
                    }
                } catch (Exception $e) {
                    $rows_failed++;
                    $import_status = 'partial_success';
                    $import_messages[] = "Row " . ($row_index + 2) . " failed: " . $e->getMessage(); // +2 because 0-indexed + header row
                }
            }
            
        } catch (Exception $e) {
            $import_status = 'failed';
            $import_messages[] = 'Critical error during CSV import: ' . $e->getMessage();
            $this->log_api_action(0, 'API_CSV_IMPORT_FATAL', 'Fatal error for CSV Import. ' . $e->getMessage() . ' URL: ' . $csv_url);
            return new WP_REST_Response(array(
                'message' => 'CSV import failed critically.',
                'details' => $import_messages
            ), 500);
        }
        
        // Log the import results
        $log_description = 'API CSV Campaign Data Import. Status: ' . $import_status . 
                        '. URL: ' . $csv_url . 
                        '. Total rows: ' . $rows_processed . 
                        '. Succeeded: ' . $rows_succeeded . 
                        '. Failed: ' . $rows_failed;
        
        $this->log_api_action(0, 'API_CSV_IMPORT', $log_description);
        
        // Determine response status
        $response_status = 200;
        if ($import_status === 'partial_success') {
            $response_status = 202; // Accepted, but with some issues
        }
        
        return new WP_REST_Response(array(
            'message' => 'CSV import completed with status: ' . $import_status,
            'summary' => array(
                'total_rows' => $rows_processed,
                'succeeded' => $rows_succeeded,
                'failed' => $rows_failed,
                'source_url' => $csv_url
            ),
            'details' => $import_messages
        ), $response_status);
    }

    /**
     * Downloads CSV content from a given URL.
     *
     * @param string $url The URL to download from.
     * @return string|false The CSV content or false on failure.
     */
    private function download_csv_file($url) {
        // Use WordPress HTTP API
        $response = wp_remote_get($url, array(
            'timeout' => 30,
            'headers' => array(
                'User-Agent' => 'WordPress/CPD-Dashboard'
            )
        ));
        
        if (is_wp_error($response)) {
            error_log('CPD_API: Failed to download CSV. Error: ' . $response->get_error_message());
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            error_log('CPD_API: Failed to download CSV. HTTP Status: ' . $response_code);
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        if (empty($body)) {
            error_log('CPD_API: Downloaded CSV file is empty.');
            return false;
        }
        
        return $body;
    }

    /**
     * Parses CSV content into an array of associative arrays.
     *
     * @param string $csv_content The raw CSV content.
     * @return array Array of parsed rows with column headers as keys.
     */
    private function parse_csv_content($csv_content) {
        $lines = explode("\n", trim($csv_content));
        
        if (empty($lines)) {
            return array();
        }
        
        // Parse header row
        $headers = str_getcsv($lines[0]);
        $parsed_data = array();
        
        // Parse data rows
        for ($i = 1; $i < count($lines); $i++) {
            $line = trim($lines[$i]);
            if (empty($line)) {
                continue; // Skip empty lines
            }
            
            $row = str_getcsv($line);
            
            // Skip rows that don't have the expected number of columns
            if (count($row) !== count($headers)) {
                error_log('CPD_API: Skipping malformed row ' . ($i + 1) . '. Expected ' . count($headers) . ' columns, got ' . count($row));
                continue;
            }
            
            // Combine headers with row data
            $parsed_data[] = array_combine($headers, $row);
        }
        
        return $parsed_data;
    }

    /**
     * Inserts a single campaign row into the database.
     *
     * @param string $table_name The table name to insert into.
     * @param array $row The row data as associative array.
     * @param int $row_index The row index for error reporting.
     * @return bool True on success, false on failure.
     */
    private function insert_campaign_row($table_name, $row, $row_index) {
        global $wpdb;
        
        // Debug: Log the first few rows to understand the data structure
        if ($row_index < 3) {
            error_log('CPD_API Debug - Row ' . ($row_index + 2) . ' keys: ' . implode(', ', array_keys($row)));
            error_log('CPD_API Debug - Row ' . ($row_index + 2) . ' account id value: "' . ($row['account id'] ?? 'NOT_FOUND') . '"');
            error_log('CPD_API Debug - Row ' . ($row_index + 2) . ' date value: "' . ($row['date'] ?? 'NOT_FOUND') . '"');
        }
        
        // Map CSV columns to database columns and sanitize data
        $data_to_insert = array(
            'account_id'                    => sanitize_text_field($row['account id'] ?? ''),
            'date'                          => sanitize_text_field($row['date'] ?? null),
            'organization_name'             => sanitize_text_field($row['organization name'] ?? null),
            'account_name'                  => sanitize_text_field($row['account name'] ?? null),
            'campaign_id'                   => sanitize_text_field($row['campaign id'] ?? null),
            'campaign_name'                 => sanitize_text_field($row['campaign name'] ?? null),
            'campaign_start_date'           => sanitize_text_field($row['campaign start date'] ?? null),
            'campaign_end_date'             => sanitize_text_field($row['campaign end date'] ?? null),
            'campaign_budget'               => floatval($row['campaign budget'] ?? 0),
            'ad_group_id'                   => sanitize_text_field($row['adgroup id'] ?? null),
            'ad_group_name'                 => sanitize_text_field($row['adgroup name'] ?? null),
            'creative_id'                   => sanitize_text_field($row['creative id'] ?? null),
            'creative_name'                 => sanitize_text_field($row['creative_name'] ?? null),
            'creative_size'                 => sanitize_text_field($row['creative size'] ?? null),
            'creative_url'                  => esc_url_raw($row['creative url'] ?? null),
            'advertiser_bid_type'           => sanitize_text_field($row['advertiser bid type'] ?? null),
            'budget_type'                   => sanitize_text_field($row['budget type'] ?? null),
            'cpm'                           => floatval($row['cpm'] ?? 0),
            'cpv'                           => floatval($row['cpv'] ?? 0),
            'market'                        => sanitize_text_field($row['market'] ?? null),
            'contact_number'                => sanitize_text_field($row['contract number'] ?? null),
            'external_ad_group_id'          => sanitize_text_field($row['external adgroup id'] ?? null),
            'total_impressions_contracted'  => intval($row['total impressions contracted'] ?? 0),
            'impressions'                   => intval($row['impressions'] ?? 0),
            'clicks'                        => intval($row['clicks'] ?? 0),
            'ctr'                           => floatval($row['ctr'] ?? 0),
            'visits'                        => intval($row['visits'] ?? 0),
            'total_spent'                   => floatval($row['total spent'] ?? 0),
            'secondary_actions'             => intval($row['secondary action'] ?? 0),
            'secondary_action_rate'         => floatval($row['secondary action rate'] ?? 0),
            'website'                       => sanitize_text_field($row['website'] ?? null),
            'direction'                     => sanitize_text_field($row['directions'] ?? null),
            'click_to_call'                 => intval($row['click to call'] ?? 0),
            'cta_more_info'                 => intval($row['more info'] ?? 0),
            'coupon'                        => intval($row['coupon'] ?? 0),
            'daily_reach'                   => intval($row['daily reach'] ?? 0),
            'video_start'                   => intval($row['video start'] ?? 0),
            'first_quartile'                => intval($row['first quartile'] ?? 0),
            'midpoint'                      => intval($row['midpoint'] ?? 0),
            'third_quartile'                => intval($row['third quartile'] ?? 0),
            'video_complete'                => intval($row['video end'] ?? 0),
        );
        
        // Enhanced validation with detailed logging
        $account_id = trim($data_to_insert['account_id']);
        $date = trim($data_to_insert['date']);
        
        if (empty($account_id) || empty($date)) {
            error_log('CPD_API: Skipping row ' . ($row_index + 2) . ' - validation failed. account_id: "' . $account_id . '", date: "' . $date . '"');
            error_log('CPD_API: Available columns in row: ' . implode(', ', array_keys($row)));
            return false;
        }
        
        // Validate date format (should be YYYY-MM-DD)
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            error_log('CPD_API: Skipping row ' . ($row_index + 2) . ' - invalid date format: "' . $date . '"');
            return false;
        }
        
        // Validate that account_id is numeric (based on your CSV data)
        if (!is_numeric($account_id)) {
            error_log('CPD_API: Skipping row ' . ($row_index + 2) . ' - account_id is not numeric: "' . $account_id . '"');
            return false;
        }
        
        // Log successful validation for first few rows
        if ($row_index < 3) {
            error_log('CPD_API Debug - Row ' . ($row_index + 2) . ' passed validation. account_id: "' . $account_id . '", date: "' . $date . '"');
            error_log('CPD_API Debug - Using table: ' . $table_name);
        }
        
        // Insert the row
        $result = $wpdb->insert($table_name, $data_to_insert);
        
        if ($result === false) {
            error_log('CPD_API: Failed to insert row ' . ($row_index + 2) . '. Database error: ' . $wpdb->last_error);
            error_log('CPD_API: SQL Last Query: ' . $wpdb->last_query);
            return false;
        }
        
        // Log success for first few rows
        if ($row_index < 3) {
            error_log('CPD_API Debug - Row ' . ($row_index + 2) . ' inserted successfully. Insert ID: ' . $wpdb->insert_id);
        }
        
        return true;
    }
}