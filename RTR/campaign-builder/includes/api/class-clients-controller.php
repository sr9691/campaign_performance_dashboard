<?php
/**
 * Clients REST API Controller
 *
 * Handles client listing and creation for Campaign Builder
 *
 * @package DirectReach_Campaign_Builder
 * @since 2.0.0
 */

namespace DirectReach\CampaignBuilder\API;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Clients Controller Class
 */
class Clients_Controller extends REST_Controller {
    
    /**
     * Register routes
     */
    public function register_routes() {
        // GET /clients - List all premium clients
        register_rest_route($this->namespace, '/clients', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'get_clients'],
                'permission_callback' => [$this, 'check_permissions'],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'create_client'],
                'permission_callback' => [$this, 'check_permissions'],
                'args'                => $this->get_create_client_args(),
            ],
        ]);
        
        // GET /clients/{id} - Get single client
        register_rest_route($this->namespace, '/clients/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_client'],
            'permission_callback' => [$this, 'check_permissions'],
            'args'                => [
                'id' => [
                    'required'          => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    },
                ],
            ],
        ]);
    }
    
    /**
     * Get all premium clients
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function get_clients($request) {
        global $wpdb;
        
        $search = $request->get_param('search');
        $table = $wpdb->prefix . 'cpd_clients';
        
        // Build query
        $query = "SELECT id, account_id, client_name, logo_url, webpage_url, 
                         subscription_tier, rtr_enabled, rtr_activated_at
                  FROM {$table}
                  WHERE subscription_tier = 'premium'";
        
        $query_params = [];
        
        // Add search filter if provided
        if (!empty($search)) {
            $query .= " AND (client_name LIKE %s OR account_id LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($search) . '%';
            $query_params[] = $search_term;
            $query_params[] = $search_term;
        }
        
        // Order by name
        $query .= " ORDER BY client_name ASC";
        
        // Prepare and execute
        if (!empty($query_params)) {
            $query = $wpdb->prepare($query, $query_params);
        }
        
        $clients = $wpdb->get_results($query);
        
        if ($wpdb->last_error) {
            return new WP_Error(
                'db_error',
                'Database error: ' . $wpdb->last_error,
                ['status' => 500]
            );
        }
        
        // Format response
        $formatted_clients = array_map(function($client) {
            return [
                'id'                => (int) $client->id,
                'accountId'         => $client->account_id,
                'name'              => $client->client_name,
                'logoUrl'           => $client->logo_url,
                'webpageUrl'        => $client->webpage_url,
                'subscriptionTier'  => $client->subscription_tier,
                'rtrEnabled'        => (bool) $client->rtr_enabled,
                'rtrActivatedAt'    => $client->rtr_activated_at,
            ];
        }, $clients);
        
        return new WP_REST_Response([
            'success' => true,
            'data'    => $formatted_clients,
            'total'   => count($formatted_clients),
        ], 200);
    }
    
    /**
     * Get single client
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function get_client($request) {
        global $wpdb;
        
        $client_id = (int) $request->get_param('id');
        $table = $wpdb->prefix . 'cpd_clients';
        
        $client = $wpdb->get_row($wpdb->prepare(
            "SELECT id, account_id, client_name, logo_url, webpage_url,
                    subscription_tier, rtr_enabled, rtr_activated_at
             FROM {$table}
             WHERE id = %d",
            $client_id
        ));
        
        if (!$client) {
            return new WP_Error(
                'client_not_found',
                'Client not found',
                ['status' => 404]
            );
        }
        
        return new WP_REST_Response([
            'success' => true,
            'data'    => [
                'id'               => (int) $client->id,
                'accountId'        => $client->account_id,
                'name'             => $client->client_name,
                'logoUrl'          => $client->logo_url,
                'webpageUrl'       => $client->webpage_url,
                'subscriptionTier' => $client->subscription_tier,
                'rtrEnabled'       => (bool) $client->rtr_enabled,
                'rtrActivatedAt'   => $client->rtr_activated_at,
            ],
        ], 200);
    }
    
    /**
     * Create new client
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function create_client($request) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'cpd_clients';
        
        // Get and sanitize input
        $client_name = sanitize_text_field($request->get_param('clientName'));
        $account_id = sanitize_text_field($request->get_param('accountId'));
        $logo_url = esc_url_raw($request->get_param('logoUrl'));
        $webpage_url = esc_url_raw($request->get_param('webpageUrl'));
        $crm_email = sanitize_email($request->get_param('crmEmail'));
        
        // Validate required fields
        if (empty($client_name)) {
            return new WP_Error(
                'missing_client_name',
                'Client name is required',
                ['status' => 400]
            );
        }
        
        if (empty($account_id)) {
            return new WP_Error(
                'missing_account_id',
                'Account ID is required',
                ['status' => 400]
            );
        }
        
        // Check if account_id already exists
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE account_id = %s",
            $account_id
        ));
        
        if ($existing) {
            return new WP_Error(
                'duplicate_account_id',
                'Account ID already exists',
                ['status' => 409]
            );
        }
        
        // Insert new client (always premium with RTR enabled)
        $result = $wpdb->insert(
            $table,
            [
                'account_id'         => $account_id,
                'client_name'        => $client_name,
                'logo_url'           => $logo_url,
                'webpage_url'        => $webpage_url,
                'crm_feed_email'     => $crm_email,
                'subscription_tier'  => 'premium',
                'rtr_enabled'        => 1,
                'rtr_activated_at'   => current_time('mysql'),
            ],
            [
                '%s', // account_id
                '%s', // client_name
                '%s', // logo_url
                '%s', // webpage_url
                '%s', // crm_feed_email
                '%s', // subscription_tier
                '%d', // rtr_enabled
                '%s', // rtr_activated_at
            ]
        );
        
        if ($result === false) {
            return new WP_Error(
                'db_insert_error',
                'Failed to create client: ' . $wpdb->last_error,
                ['status' => 500]
            );
        }
        
        $new_client_id = $wpdb->insert_id;
        
        // Fetch the created client
        $new_client = $wpdb->get_row($wpdb->prepare(
            "SELECT id, account_id, client_name, logo_url, webpage_url,
                    subscription_tier, rtr_enabled, rtr_activated_at
             FROM {$table}
             WHERE id = %d",
            $new_client_id
        ));
        
        return new WP_REST_Response([
            'success' => true,
            'message' => 'Client created successfully',
            'data'    => [
                'id'               => (int) $new_client->id,
                'accountId'        => $new_client->account_id,
                'name'             => $new_client->client_name,
                'logoUrl'          => $new_client->logo_url,
                'webpageUrl'       => $new_client->webpage_url,
                'subscriptionTier' => $new_client->subscription_tier,
                'rtrEnabled'       => (bool) $new_client->rtr_enabled,
                'rtrActivatedAt'   => $new_client->rtr_activated_at,
            ],
        ], 201);
    }
    
    /**
     * Get arguments for create client endpoint
     *
     * @return array
     */
    private function get_create_client_args() {
        return [
            'clientName' => [
                'required'          => true,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'accountId' => [
                'required'          => true,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'logoUrl' => [
                'required'          => false,
                'type'              => 'string',
                'sanitize_callback' => 'esc_url_raw',
            ],
            'webpageUrl' => [
                'required'          => false,
                'type'              => 'string',
                'sanitize_callback' => 'esc_url_raw',
            ],
            'crmEmail' => [
                'required'          => false,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_email',
            ],
        ];
    }
    
    /**
     * Check if user has admin permission
     *
     * @return bool
     */
    public function check_permissions() {
        return current_user_can('manage_options');
    }
}