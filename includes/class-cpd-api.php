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
        global $wpdb; // Ensure wpdb is accessible within the constructor context if needed
        $this->plugin_name = $plugin_name;
        // Hook the register_routes method into the rest_api_init action
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );

        // Initialize data provider here, as it's used by several methods
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
        $namespace = $this->plugin_name . '/v1'; // e.g., 'cpd-dashboard/v1'

        // Endpoint for general data import (e.g., a simple webhook ping, if needed)
        // If not needed, this endpoint can be removed entirely.
        register_rest_route( $namespace, '/data-import', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'handle_data_import_ping' ), // Changed callback name
            'permission_callback' => array( $this, 'verify_api_key' ),
        ) );

        // NEW: Endpoint for Campaign Data Import (POST method)
        register_rest_route( $namespace, '/campaign-data-import', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'handle_campaign_data_import' ),
            'permission_callback' => array( $this, 'verify_api_key' ),
        ) );

        // NEW: Endpoint for Visitor Data Import (POST method)
        register_rest_route( $namespace, '/visitor-data-import', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'handle_visitor_data_import' ),
            'permission_callback' => array( $this, 'verify_api_key' ),
        ) );
        
        // Existing: Endpoints for Campaign and Visitor Data (GET methods)
        register_rest_route( $namespace, '/campaign-data', array(
            'methods'             => WP_REST_Server::READABLE, // GET requests
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
            'methods'             => WP_REST_Server::READABLE, // GET requests
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
    }

    /**
     * Verifies the custom API key sent in the request header.
     * This is a simple verification method; for production, consider a more robust system.
     *
     * @param WP_REST_Request $request The REST API request.
     * @return bool|WP_Error True if the key is valid, WP_Error otherwise.
     */
    public function verify_api_key( WP_REST_Request $request ) {
        $api_key_stored = get_option( 'cpd_api_key', '' ); // Get key from settings
        $api_key_header = $request->get_header( 'X-API-Key' );

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
        $account_id = sanitize_text_field( $json_payload['account_id'] ?? 'N/A' ); // Safely get account_id if sent

        $log_description = 'General API Import Ping received for Account ID: ' . $account_id . '. Payload size: ' . strlen( $request->get_body() ) . ' bytes.';
        $this->log_api_action( 0, 'API_PING_RECEIVED', $log_description );

        return new WP_REST_Response( array( 'message' => 'General data import ping received and logged.' ), 200 );
    }


    /**
     * Handles the campaign data import via REST API (POST method).
     * This method expects a COMPLETE daily feed for a given date and account.
     * It will DELETE existing data for that account and date before inserting new data.
     *
     * @param WP_REST_Request $request The REST API request.
     * @return WP_REST_Response The API response.
     */
    public function handle_campaign_data_import( WP_REST_Request $request ) {
        global $wpdb;
        $json_payload = $request->get_json_params();

        if ( empty( $json_payload ) ) {
            $this->log_api_action( 0, 'API_CAMPAIGN_IMPORT_FAILED', 'Empty JSON payload for campaign data. Request from IP: ' . ( $_SERVER['REMOTE_ADDR'] ?? 'N/A' ) );
            return new WP_REST_Response( array( 'message' => 'Empty JSON payload for campaign data.', 'status' => 400 ), 400 );
        }

        $campaign_data_table = 'dashdev_cpd_campaign_data'; // Fixed table name as per your setup

        $account_id = sanitize_text_field( $json_payload['account_id'] ?? '' );
        $campaign_data = $json_payload['campaign_data'] ?? []; // Array of campaign rows
        $feed_date = sanitize_text_field( $json_payload['feed_date'] ?? null ); // Expecting a specific date for the feed

        if ( empty( $account_id ) || empty( $feed_date ) ) {
            $this->log_api_action( 0, 'API_CAMPAIGN_IMPORT_FAILED', 'Missing account_id or feed_date in payload. Request from IP: ' . ( $_SERVER['REMOTE_ADDR'] ?? 'N/A' ) );
            return new WP_REST_Response( array( 'message' => 'Missing account_id or feed_date in campaign data payload.', 'status' => 400 ), 400 );
        }

        $import_status = 'success';
        $import_messages = [];
        $campaign_rows_processed = 0;
        $rows_deleted = 0;

        try {
            // STEP 1: Delete existing data for this account_id and feed_date
            $deleted = $wpdb->delete(
                $campaign_data_table,
                array(
                    'account_id' => $account_id,
                    'date'       => $feed_date
                ),
                array( '%s', '%s' )
            );

            if ( $deleted === false ) {
                // If deletion itself fails, it's a critical error for a complete feed.
                throw new Exception('Failed to delete existing campaign data for account_id ' . $account_id . ' and date ' . $feed_date . '. Error: ' . $wpdb->last_error);
            }
            $rows_deleted = $deleted;
            $import_messages[] = $rows_deleted . ' existing rows deleted for ' . $account_id . ' on ' . $feed_date . '.';


            // STEP 2: Insert new data from the payload
            if ( ! empty( $campaign_data ) ) {
                foreach ( $campaign_data as $row ) {
                    $data_to_insert = array(
                        'account_id'      => $account_id, // Use the top-level account_id from the payload
                        'date'            => sanitize_text_field( $row['date'] ?? $feed_date ), // Use row date if present, else feed_date
                        'organization_name' => sanitize_text_field( $row['organization_name'] ?? null ),
                        'account_name'    => sanitize_text_field( $row['account_name'] ?? null ),
                        'campaign_id'     => sanitize_text_field( $row['campaign_id'] ?? null ),
                        'campaign_name'   => sanitize_text_field( $row['campaign_name'] ?? null ),
                        'campaign_start_date' => sanitize_text_field( $row['campaign_start_date'] ?? null ),
                        'campaign_end_date' => sanitize_text_field( $row['campaign_end_date'] ?? null ),
                        'campaign_budget' => floatval( $row['campaign_budget'] ?? 0 ),
                        'ad_group_id'     => sanitize_text_field( $row['ad_group_id'] ?? null ),
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
                $import_status = 'success'; // It's still successful if an empty feed is sent for a date, just means no data for that date.
                $import_messages[] = 'No campaign_data array provided or it was empty, after ' . $rows_deleted . ' rows deleted.';
            }

        } catch (Exception $e) {
            $import_status = 'failed';
            $import_messages[] = 'Critical error during campaign data import: ' . $e->getMessage();
            $response_status = 500; // Server error
            $this->log_api_action( 0, 'API_CAMPAIGN_IMPORT_FATAL', 'Fatal error for Account ID: ' . $account_id . '. ' . $e->getMessage() . ' Request from IP: ' . ( $_SERVER['REMOTE_ADDR'] ?? 'N/A' ) );
            return new WP_REST_Response( array( 'message' => 'Campaign data import failed critically.', 'details' => $import_messages ), $response_status );
        }

        $log_description = 'API Campaign Data Import for Account ID: ' . $account_id . '. Status: ' . $import_status . '. Rows deleted: ' . $rows_deleted . '. Rows processed: ' . $campaign_rows_processed . '.';
        if ( ! empty( $import_messages ) ) {
            $log_description .= ' Messages: ' . implode('; ', $import_messages);
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
     * This method performs an UPSERT (update or insert) based on visitor_id and account_id.
     *
     * @param WP_REST_Request $request The REST API request.
     * @return WP_REST_Response The API response.
     */
    public function handle_visitor_data_import( WP_REST_Request $request ) {
        global $wpdb;
        $json_payload = $request->get_json_params();

        if ( empty( $json_payload ) ) {
            $this->log_api_action( 0, 'API_VISITOR_IMPORT_FAILED', 'Empty JSON payload for visitor data. Request from IP: ' . ( $_SERVER['REMOTE_ADDR'] ?? 'N/A' ) );
            return new WP_REST_Response( array( 'message' => 'Empty JSON payload for visitor data.', 'status' => 400 ), 400 );
        }

        $visitor_data_table = $wpdb->prefix . 'cpd_visitors';

        $account_id = sanitize_text_field( $json_payload['account_id'] ?? '' );
        $visitor_data = $json_payload['visitor_data'] ?? [];

        if ( empty( $account_id ) ) {
            $this->log_api_action( 0, 'API_VISITOR_IMPORT_FAILED', 'Missing account_id in payload. Request from IP: ' . ( $_SERVER['REMOTE_ADDR'] ?? 'N/A' ) );
            return new WP_REST_Response( array( 'message' => 'Missing account_id in visitor data payload.', 'status' => 400 ), 400 );
        }

        $import_status = 'success';
        $import_messages = [];
        $visitor_rows_processed = 0;

        if ( ! empty( $visitor_data ) ) {
            foreach ( $visitor_data as $row ) {
                $visitor_id = sanitize_text_field( $row['visitor_id'] ?? '' );
                $visit_time_formatted = sanitize_text_field( $row['visit_time'] ?? current_time('mysql') );
                $last_seen_at = sanitize_text_field( $row['last_seen_at'] ?? current_time('mysql') );
                $first_seen_at = sanitize_text_field( $row['first_seen_at'] ?? current_time('mysql') );


                // Check for existing visitor using unique key (visitor_id, account_id)
                $existing_id = $wpdb->get_var( $wpdb->prepare(
                    "SELECT id FROM %i WHERE visitor_id = %s AND account_id = %s",
                    $visitor_data_table,
                    $visitor_id,
                    $account_id
                ) );

                $data_to_insert = array(
                    'visitor_id'    => $visitor_id,
                    'account_id'    => $account_id,
                    'linkedin_url'  => sanitize_text_field( $row['linkedin_url'] ?? '' ),
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
                    'last_seen_at'  => $last_seen_at, // Use sanitized value
                    'first_seen_at' => $first_seen_at, // Use sanitized value
                    'new_profile'   => intval( $row['new_profile'] ?? 0 ),
                    'email'         => sanitize_email( $row['email'] ?? '' ),
                    'website'       => sanitize_text_field( $row['website'] ?? '' ),
                    'industry'      => sanitize_text_field( $row['industry'] ?? '' ),
                    'state'         => sanitize_text_field( $row['state'] ?? '' ),
                    'filter_matches' => sanitize_text_field( $row['filter_matches'] ?? '' ),
                    'profile_type'  => sanitize_text_field( $row['profile_type'] ?? '' ),
                    'status'        => sanitize_text_field( $row['status'] ?? 'active' ),
                    'is_crm_added'  => intval( $row['is_crm_added'] ?? 0 ),
                    'is_archived'   => intval( $row['is_archived'] ?? 0 ),
                    'visit_time'    => $visit_time_formatted, // Use the sanitized and formatted time
                );
                
                if ( $existing_id ) {
                    // Update existing row
                    $updated = $wpdb->update(
                        $visitor_data_table,
                        $data_to_insert,
                        array( 'id' => $existing_id )
                    );
                    if ( $updated === false ) {
                        $import_status = 'partial_success';
                        $import_messages[] = 'Failed to update visitor ID ' . $visitor_id . ' for account_id ' . $account_id . '. Error: ' . $wpdb->last_error;
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
                        $import_messages[] = 'Failed to insert visitor ID ' . $visitor_id . ' for account_id ' . $account_id . '. Error: ' . $wpdb->last_error;
                    } else {
                        $visitor_rows_processed++;
                    }
                }
            }
        } else {
            $import_status = 'partial_success';
            $import_messages[] = 'No visitor_data array provided or it was empty.';
        }

        $log_description = 'API Visitor Data Import for Account ID: ' . $account_id . '. Status: ' . $import_status . '. Rows processed: ' . $visitor_rows_processed . '.';
        if ( ! empty( $import_messages ) ) {
            $log_description .= ' Messages: ' . implode('; ', $import_messages);
        }
        $this->log_api_action( 0, 'API_VISITOR_IMPORT', $log_description );

        $response_status = 200;
        if ( $import_status === 'partial_success' ) {
            $response_status = 202; // Accepted, but with some non-fatal issues
        }

        return new WP_REST_Response( array( 'message' => 'Visitor data import process completed with status: ' . $import_status . '.', 'details' => $import_messages ), $response_status );
    }

    /**
     * Permission callback for REST API endpoints.
     * Ensures only logged-in users with 'read' capability can access,
     * and further restricts client users to their own account_id.
     *
     * @param WP_REST_Request $request The request object.
     * @return bool|WP_Error True if access is granted, WP_Error otherwise.
     */
    public function get_rest_permissions_check( WP_REST_Request $request ) {
        // Data provider is initialized in the constructor, so it's always ready.

        if ( ! is_user_logged_in() ) {
            return new WP_Error( 'rest_not_logged_in', __( 'You are not currently logged in.', 'cpd-dashboard' ), array( 'status' => 401 ) );
        }

        $current_user = wp_get_current_user();
        $is_admin = current_user_can( 'manage_options' );

        // Admins can access all data
        if ( $is_admin ) {
            return true;
        }

        // Clients can only access their own data
        $requested_account_id = $request->get_param( 'account_id' );
        $user_account_id = $this->data_provider->get_account_id_by_user_id( $current_user->ID );

        if ( ! $user_account_id ) {
            return new WP_Error( 'rest_no_client_link', __( 'Your user account is not linked to a client.', 'cpd-dashboard' ), array( 'status' => 403 ) );
        }

        // If no specific account_id is requested, and it's a client, assume their own account.
        // If a specific account_id is requested, ensure it matches the user's linked account.
        if ( $requested_account_id && $requested_account_id !== $user_account_id ) {
            return new WP_Error( 'rest_access_denied', __( 'You do not have permission to access data for this client.', 'cpd-dashboard' ), array( 'status' => 403 ) );
        }

        return true;
    }

    /**
     * Callback for the /campaign-data REST API endpoint.
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response The response object.
     */
    public function get_campaign_data_rest( WP_REST_Request $request ) {
        $current_user = wp_get_current_user();
        $is_admin = current_user_can( 'manage_options' );
        
        $account_id = $request->get_param( 'account_id' );
        $start_date = $request->get_param( 'start_date' ) ?: '2025-01-01'; // Default
        $end_date = $request->get_param( 'end_date' ) ?: date('Y-m-d'); // Default to current date
        $group_by = $request->get_param( 'group_by' ) ?: 'ad_group';

        // If client, ensure they only get their own data, even if they requested 'all' or a different ID.
        if ( ! $is_admin ) {
            $user_account_id = $this->data_provider->get_account_id_by_user_id( $current_user->ID );
            if ( ! $user_account_id ) {
                return new WP_REST_Response( array( 'message' => 'User not linked to a client account.' ), 403 );
            }
            $account_id = $user_account_id; // Override requested account_id with user's own
        } else {
            // For admins, if 'all' is explicitly requested, pass null to data provider
            if ( $account_id === 'all' ) {
                $account_id = null;
            }
        }

        try {
            if ( $group_by === 'ad_group' ) {
                $data = $this->data_provider->get_campaign_data_by_ad_group( $account_id, $start_date, $end_date );
            } else { // group_by === 'date'
                $data = $this->data_provider->get_campaign_data_by_date( $account_id, $start_date, $end_date );
            }
            
            return new WP_REST_Response( $data, 200 );
        } catch ( Exception $e ) {
            error_log( 'CPD REST API Error (campaign-data): ' . $e->getMessage() );
            return new WP_REST_Response( array( 'message' => 'Error fetching campaign data.', 'details' => $e->getMessage() ), 500 );
        }
    }

    /**
     * Callback for the /visitor-data REST API endpoint.
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response The response object.
     */
    public function get_visitor_data_rest( WP_REST_Request $request ) {
        $current_user = wp_get_current_user();
        $is_admin = current_user_can( 'manage_options' );

        $account_id = $request->get_param( 'account_id' );

        // If client, ensure they only get their own data, even if they requested a different ID.
        if ( ! $is_admin ) {
            $user_account_id = $this->data_provider->get_account_id_by_user_id( $current_user->ID );
            if ( ! $user_account_id ) {
                return new WP_REST_Response( array( 'message' => 'User not linked to a client account.' ), 403 );
            }
            $account_id = $user_account_id; // Override requested account_id with user's own
        } else {
            // For admins, if 'all' is explicitly requested, pass null to data provider
            if ( $account_id === 'all' ) {
                $account_id = null;
            }
        }

        try {
            $data = $this->data_provider->get_visitor_data( $account_id );
            return new WP_REST_Response( $data, 200 );
        } catch ( Exception $e ) {
            error_log( 'CPD REST API Error (visitor-data): ' . $e->getMessage() );
            return new WP_REST_Response( array( 'message' => 'Error fetching visitor data.', 'details' => $e->getMessage() ), 500 );
        }
    }
}