<?php
/**
 * Scoring Rules REST API Controller
 *
 * Handles REST API endpoints for scoring rules management.
 * Manages global defaults and client-specific rule overrides.
 *
 * @package    DirectReach
 * @subpackage RTR/ScoringSystem
 * @since      2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class RTR_Scoring_Rules_Controller extends WP_REST_Controller {

    /**
     * Namespace for REST routes
     *
     * @var string
     */
    protected $namespace = 'directreach/v2';

    /**
     * Base route for scoring rules
     *
     * @var string
     */
    protected $rest_base = 'scoring-rules';

    /**
     * Database handler instance
     *
     * @var RTR_Scoring_Rules_Database
     */
    private $db;

    /**
     * Valid room types
     *
     * @var array
     */
    private $valid_rooms = array('problem', 'solution', 'offer');

    /**
     * Constructor
     */
    public function __construct() {
        $this->db = new RTR_Scoring_Rules_Database();
    }

    /**
     * Register REST routes
     */
    public function register_routes() {
        
        // GET /scoring-rules - Get all global rules
        register_rest_route($this->namespace, '/' . $this->rest_base, array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_all_global_rules'),
                'permission_callback' => array($this, 'check_admin_permissions'),
            ),
        ));

        // GET /scoring-rules/{room} - Get global rules for specific room
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<room>problem|solution|offer)', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_global_rules_by_room'),
                'permission_callback' => array($this, 'check_admin_permissions'),
                'args'                => array(
                    'room' => array(
                        'required'          => true,
                        'type'              => 'string',
                        'enum'              => $this->valid_rooms,
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                ),
            ),
        ));

        // PUT /scoring-rules/{room} - Update global rules for specific room
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<room>problem|solution|offer)', array(
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array($this, 'update_global_rules'),
                'permission_callback' => array($this, 'check_admin_permissions'),
                'args'                => array(
                    'room' => array(
                        'required'          => true,
                        'type'              => 'string',
                        'enum'              => $this->valid_rooms,
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'rules_config' => array(
                        'required'          => true,
                        'type'              => 'object',
                        'validate_callback' => array($this, 'validate_rules_config'),
                    ),
                ),
            ),
        ));

        // GET /scoring-rules/client/{client_id} - Get all client rules
        register_rest_route($this->namespace, '/' . $this->rest_base . '/client/(?P<client_id>\d+)', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_all_client_rules'),
                'permission_callback' => array($this, 'check_admin_permissions'),
                'args'                => array(
                    'client_id' => array(
                        'required'          => true,
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                        'validate_callback' => array($this, 'validate_client_id'),
                    ),
                ),
            ),
        ));

        // GET /scoring-rules/client/{client_id}/{room} - Get client rules for specific room
        register_rest_route($this->namespace, '/' . $this->rest_base . '/client/(?P<client_id>\d+)/(?P<room>problem|solution|offer)', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_client_rules_by_room'),
                'permission_callback' => array($this, 'check_admin_permissions'),
                'args'                => array(
                    'client_id' => array(
                        'required'          => true,
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                        'validate_callback' => array($this, 'validate_client_id'),
                    ),
                    'room' => array(
                        'required'          => true,
                        'type'              => 'string',
                        'enum'              => $this->valid_rooms,
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                ),
            ),
        ));

        // PUT /scoring-rules/client/{client_id} - Save client rules (one room at a time)
        register_rest_route($this->namespace, '/' . $this->rest_base . '/client/(?P<client_id>\d+)', array(
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array($this, 'update_client_rules'),
                'permission_callback' => array($this, 'check_admin_permissions'),
                'args'                => array(
                    'client_id' => array(
                        'required'          => true,
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                        'validate_callback' => array($this, 'validate_client_id'),
                    ),
                    'room' => array(
                        'required'          => true,
                        'type'              => 'string',
                        'enum'              => $this->valid_rooms,
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'rules_config' => array(
                        'required'          => true,
                        'type'              => 'object',
                        'validate_callback' => array($this, 'validate_rules_config'),
                    ),
                ),
            ),
        ));

        // DELETE /scoring-rules/client/{client_id} - Reset to global defaults
        register_rest_route($this->namespace, '/' . $this->rest_base . '/client/(?P<client_id>\d+)', array(
            array(
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => array($this, 'delete_client_rules'),
                'permission_callback' => array($this, 'check_admin_permissions'),
                'args'                => array(
                    'client_id' => array(
                        'required'          => true,
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                        'validate_callback' => array($this, 'validate_client_id'),
                    ),
                    'room' => array(
                        'required'          => false,
                        'type'              => 'string',
                        'enum'              => $this->valid_rooms,
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                ),
            ),
        ));
       
    }

    /**
     * Get all global rules (all rooms)
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function get_all_global_rules($request) {
        try {
            $rules = array();

            foreach ($this->valid_rooms as $room) {
                $room_rules = $this->db->get_global_rules($room);
                $rules[$room] = $room_rules ? $room_rules : $this->get_default_rules($room);
            }

            return rest_ensure_response(array(
                'success' => true,
                'data'    => $rules,
            ));

        } catch (Exception $e) {
            return new WP_Error(
                'rules_error',
                $e->getMessage(),
                array('status' => 500)
            );
        }
    }

    /**
     * Get global rules for specific room
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function get_global_rules_by_room($request) {
        $room = $request->get_param('room');

        try {
            $rules = $this->db->get_global_rules($room);

            if (!$rules) {
                $rules = $this->get_default_rules($room);
            }

            return rest_ensure_response(array(
                'success'   => true,
                'data'      => $rules,
                'is_global' => true,
            ));

        } catch (Exception $e) {
            return new WP_Error(
                'rules_error',
                $e->getMessage(),
                array('status' => 500)
            );
        }
    }

    /**
     * Update global rules for specific room
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function update_global_rules($request) {
        $room         = $request->get_param('room');
        $rules_config = $request->get_param('rules_config');

        try {
            // update_global_rules() handles both insert and update
            $result = $this->db->update_global_rules($room, $rules_config);

            if ($result === false) {
                throw new Exception('Failed to save global rules');
            }

            // Get the updated rules
            $updated = $this->db->get_global_rules($room);

            return rest_ensure_response(array(
                'success' => true,
                'message' => sprintf('Global %s room rules updated successfully', $room),
                'data'    => $updated,
            ));

        } catch (Exception $e) {
            return new WP_Error(
                'rules_update_error',
                $e->getMessage(),
                array('status' => 500)
            );
        }
    }

    /**
     * Get all client rules (all rooms) with global fallback
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function get_all_client_rules($request) {
        $client_id = $request->get_param('client_id');

        try {
            $client_rules = $this->db->get_client_rules($client_id);
            $has_custom = ($client_rules !== false);
            
            $response_data = array();

            foreach ($this->valid_rooms as $room) {
                $global_rules = $this->db->get_global_rules($room);
                $global_rules = $global_rules ? $global_rules : $this->get_default_rules($room);
                
                if ($has_custom && isset($client_rules[$room])) {
                    $response_data[$room] = array(
                        'rules'     => $client_rules[$room],
                        'is_custom' => true,
                        'global'    => $global_rules,
                    );
                } else {
                    $response_data[$room] = array(
                        'rules'     => $global_rules,
                        'is_custom' => false,
                        'global'    => $global_rules,
                    );
                }
            }

            return rest_ensure_response(array(
                'success'   => true,
                'data'      => array(
                    'rules'     => $response_data,
                    'is_custom' => $has_custom,
                ),
                'client_id' => $client_id,
            ));

        } catch (Exception $e) {
            return new WP_Error(
                'rules_error',
                $e->getMessage(),
                array('status' => 500)
            );
        }
    }

    /**
     * Get client rules for specific room with global fallback
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function get_client_rules_by_room($request) {
        $client_id = $request->get_param('client_id');
        $room      = $request->get_param('room');

        try {
            $client_rules = $this->db->get_client_rules_for_room($client_id, $room);
            $global_rules = $this->db->get_global_rules($room);
            $global_rules = $global_rules ? $global_rules : $this->get_default_rules($room);

            // Check if this specific room has custom rules
            $all_client_rules = $this->db->get_client_rules($client_id);
            $has_custom = ($all_client_rules !== false && isset($all_client_rules[$room]));

            $response = array(
                'rules'     => $client_rules,
                'is_custom' => $has_custom,
                'global'    => $global_rules,
            );

            return rest_ensure_response(array(
                'success'   => true,
                'data'      => $response,
                'client_id' => $client_id,
                'room'      => $room,
            ));

        } catch (Exception $e) {
            return new WP_Error(
                'rules_error',
                $e->getMessage(),
                array('status' => 500)
            );
        }
    }

    /**
     * Update client rules for specific room
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function update_client_rules($request) {
        $client_id    = $request->get_param('client_id');
        $room         = $request->get_param('room');
        $rules_config = $request->get_param('rules_config');

        try {
            // save_client_rules() already handles upsert (insert or update)
            $result = $this->db->save_client_rules($client_id, $room, $rules_config);

            if ($result === false) {
                throw new Exception('Failed to save client rules');
            }

            // Get the updated rules to return
            $updated = $this->db->get_client_rules_for_room($client_id, $room);

            return rest_ensure_response(array(
                'success'   => true,
                'message'   => sprintf('Client %s room rules updated successfully', $room),
                'data'      => $updated,
                'client_id' => $client_id,
                'room'      => $room,
            ));

        } catch (Exception $e) {
            return new WP_Error(
                'rules_update_error',
                $e->getMessage(),
                array('status' => 500)
            );
        }
    }

    /**
     * Delete all client scoring rules (reset to global)
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function delete_client_rules($request) {
        $client_id = $request->get_param('client_id');
        $room      = $request->get_param('room');
        
        try {
            if ($room) {
                // Delete specific room
                $result = $this->db->delete_client_rules_for_room($client_id, $room);
            } else {
                // Delete all rooms
                $result = $this->db->delete_client_rules($client_id);
            }
            
            if ($result === false) {
                throw new Exception('Failed to delete client rules');
            }
            
            // Return global defaults
            $rules = array();
            foreach ($this->valid_rooms as $r) {
                $global = $this->db->get_global_rules($r);
                $rules[$r] = $global ? $global : $this->get_default_rules($r);
            }
            
            return rest_ensure_response(array(
                'success' => true,
                'message' => 'Client rules reset to global defaults',
                'data'    => array(
                    'rules'     => $rules,
                    'is_custom' => false,
                ),
            ));

        } catch (Exception $e) {
            return new WP_Error(
                'delete_error',
                $e->getMessage(),
                array('status' => 500)
            );
        }
    }

    /**
     * Check if user has admin permissions
     *
     * @param WP_REST_Request $request Request object.
     * @return bool
     */
    public function check_admin_permissions($request) {
        return current_user_can('manage_options');
    }

    /**
     * Validate client ID exists
     *
     * @param int $client_id Client ID.
     * @return bool
     */
    public function validate_client_id($client_id) {
        global $wpdb;
        
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}cpd_clients WHERE id = %d",
            $client_id
        ));

        return $exists > 0;
    }

    /**
     * Validate rules configuration structure
     *
     * @param mixed $rules_config Rules configuration.
     * @return bool
     */
    public function validate_rules_config($rules_config) {
        // Must be an array or object
        if (!is_array($rules_config) && !is_object($rules_config)) {
            return false;
        }

        // Convert to array for validation
        $rules = (array) $rules_config;

        // Must have at least one rule
        if (empty($rules)) {
            return false;
        }

        // Each rule should have expected structure
        // Basic validation - detailed validation happens in database layer
        foreach ($rules as $rule_name => $rule_data) {
            if (!is_array($rule_data) && !is_object($rule_data)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get default rules for a room
     *
     * @param string $room Room type.
     * @return array
     */
    private function get_default_rules($room) {
        $defaults = array(
            'problem' => array(
                'revenue' => array(
                    'enabled' => true,
                    'points'  => 10,
                    'values'  => array(),
                ),
                'company_size' => array(
                    'enabled' => true,
                    'points'  => 10,
                    'values'  => array(),
                ),
                'industry_alignment' => array(
                    'enabled' => true,
                    'points'  => 15,
                    'values'  => array(),
                ),
                'target_states' => array(
                    'enabled' => true,
                    'points'  => 5,
                    'values'  => array(),
                ),
                'visited_target_pages' => array(
                    'enabled'    => false,
                    'points'     => 10,
                    'max_points' => 30,
                ),
                'multiple_visits' => array(
                    'enabled'        => true,
                    'points'         => 5,
                    'minimum_visits' => 2,
                ),
                'role_match' => array(
                    'enabled'      => false,
                    'points'       => 5,
                    'target_roles' => array(
                        'decision_makers' => array('CEO', 'President', 'Director', 'VP', 'Chief'),
                        'technical'       => array('Engineer', 'Developer', 'CTO'),
                        'marketing'       => array('Marketing', 'CMO', 'Brand'),
                        'sales'           => array('Sales', 'Business Development'),
                    ),
                    'match_type' => 'contains',
                ),
                'minimum_threshold' => array(
                    'enabled'        => true,
                    'required_score' => 20,
                ),
            ),
            'solution' => array(
                'email_open' => array(
                    'enabled' => true,
                    'points'  => 2,
                ),
                'email_click' => array(
                    'enabled' => true,
                    'points'  => 5,
                ),
                'email_multiple_click' => array(
                    'enabled'        => true,
                    'points'         => 8,
                    'minimum_clicks' => 2,
                ),
                'page_visit' => array(
                    'enabled'          => true,
                    'points_per_visit' => 3,
                    'max_points'       => 15,
                ),
                'key_page_visit' => array(
                    'enabled'   => true,
                    'points'    => 10,
                    'key_pages' => array('/pricing', '/demo', '/contact'),
                ),
                'ad_engagement' => array(
                    'enabled'     => true,
                    'points'      => 5,
                    'utm_sources' => array('google', 'linkedin', 'facebook'),
                ),
            ),
            'offer' => array(
                'demo_request' => array(
                    'enabled'          => true,
                    'points'           => 25,
                    'detection_method' => 'url_pattern',
                    'patterns'         => array('/demo/requested', '/demo/confirmation'),
                ),
                'contact_form' => array(
                    'enabled'          => true,
                    'points'           => 20,
                    'detection_method' => 'utm_parameter',
                    'utm_content'      => 'form_submitted',
                ),
                'pricing_page' => array(
                    'enabled'   => true,
                    'points'    => 15,
                    'page_urls' => array('/pricing', '/plans'),
                ),
                'pricing_question' => array(
                    'enabled'          => true,
                    'points'           => 20,
                    'detection_method' => 'utm_parameter',
                    'utm_content'      => 'pricing_inquiry',
                ),
                'partner_referral' => array(
                    'enabled'          => true,
                    'points'           => 15,
                    'detection_method' => 'utm_source',
                    'utm_sources'      => array('partner_referral'),
                ),
                'webinar_attendance' => array(
                    'enabled'          => false,
                    'points'           => 0,
                    'detection_method' => 'utm_parameter',
                ),
            ),
        );

        return isset($defaults[$room]) ? $defaults[$room] : array();
    }
}