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

    /**
     * Register the REST API routes for the plugin.
     */
    public function register_routes() {
        register_rest_route( 'cpd-dashboard/v1', '/data-import', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'handle_data_import' ),
            'permission_callback' => array( $this, 'verify_api_key' ),
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
        // In a real-world scenario, you would store and retrieve this key from WP options.
        $api_key_stored = 'YOUR_MAKE_COM_SECRET_KEY'; // **IMPORTANT: CHANGE THIS TO A SECURE KEY**
        $api_key_header = $request->get_header( 'X-API-Key' );

        if ( ! $api_key_header || $api_key_header !== $api_key_stored ) {
            return new WP_Error( 'rest_forbidden', 'Invalid API key.', array( 'status' => 401 ) );
        }

        return true;
    }

    /**
     * Handles the data import from the Make.com JSON payload.
     *
     * @param WP_REST_Request $request The REST API request.
     * @return WP_REST_Response The API response.
     */
    public function handle_data_import( WP_REST_Request $request ) {
        global $wpdb;

        $json_payload = $request->get_json_params();

        if ( empty( $json_payload ) ) {
            return new WP_REST_Response( array( 'message' => 'Empty JSON payload.', 'status' => 400 ), 400 );
        }

        $campaign_data_table = $wpdb->prefix . 'cpd_campaign_data';
        $visitor_data_table = $wpdb->prefix . 'cpd_visitors';
        $log_table = $wpdb->prefix . 'cpd_action_logs';

        $account_id = sanitize_text_field( $json_payload['account_id'] );
        $campaign_data = $json_payload['campaign_data'];
        $visitor_data = $json_payload['visitor_data'];

        // --- 1. Process Campaign Data (from XLSX) ---
        if ( ! empty( $campaign_data ) ) {
            foreach ( $campaign_data as $row ) {
                $data_to_insert = array(
                    'account_id'      => $account_id,
                    'campaign_start'  => sanitize_text_field( $row['campaign_start'] ),
                    'campaign_end'    => sanitize_text_field( $row['campaign_end'] ),
                    'ad_group_name'   => sanitize_text_field( $row['ad_group_name'] ),
                    'impressions'     => intval( $row['impressions'] ),
                    'reach'           => intval( $row['reach'] ),
                    'clicks'          => intval( $row['clicks'] ),
                    'ctr'             => floatval( $row['ctr'] ),
                    'last_updated'    => current_time( 'mysql' ),
                );

                $wpdb->replace( $campaign_data_table, $data_to_insert ); // replace() will update if unique key exists.
            }
        }

        // --- 2. Process Visitor Data (from CSV) ---
        if ( ! empty( $visitor_data ) ) {
            foreach ( $visitor_data as $row ) {
                $data_to_insert = array(
                    'visitor_id'    => sanitize_text_field( $row['visitor_id'] ),
                    'account_id'    => $account_id,
                    'name'          => sanitize_text_field( $row['name'] ),
                    'job_title'     => sanitize_text_field( $row['job_title'] ),
                    'company_name'  => sanitize_text_field( $row['company_name'] ),
                    'location'      => sanitize_text_field( $row['location'] ),
                    'email'         => sanitize_email( $row['email'] ),
                    'referrer_url'  => esc_url_raw( $row['referrer_url'] ),
                    'visit_time'    => sanitize_text_field( $row['visit_time'] ),
                );
                
                // Use INSERT IGNORE to prevent duplicate entries based on unique key.
                $wpdb->insert( $visitor_data_table, $data_to_insert, array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ) );
            }
        }

        // --- 3. Log the import action ---
        $log_data = array(
            'user_id' => 0, // No specific user for API import
            'action_type' => 'API_IMPORT',
            'description' => 'Data imported via Make.com API for Account ID: ' . $account_id,
        );
        $wpdb->insert( $log_table, $log_data );

        return new WP_REST_Response( array( 'message' => 'Data imported successfully.', 'status' => 200 ), 200 );
    }
}