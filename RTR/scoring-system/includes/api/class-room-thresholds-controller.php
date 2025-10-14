<?php
/**
 * Room Thresholds REST API Controller
 *
 * Handles REST API endpoints for room threshold management.
 * Manages global defaults and client-specific threshold overrides.
 *
 * @package    DirectReach
 * @subpackage RTR/ScoringSystem
 * @since      2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class RTR_Room_Thresholds_Controller extends WP_REST_Controller {

    /**
     * Namespace for REST routes
     *
     * @var string
     */
    protected $namespace = 'directreach/v2';

    /**
     * Base route for thresholds
     *
     * @var string
     */
    protected $rest_base = 'room-thresholds';

    /**
     * Database handler instance
     *
     * @var RTR_Room_Thresholds_Database
     */
    private $db;

    /**
     * Constructor
     */
    public function __construct() {
        $this->db = new RTR_Room_Thresholds_Database();
    }

    /**
     * Register REST routes
     */
    public function register_routes() {
        
        // GET /room-thresholds - Get global defaults
        register_rest_route($this->namespace, '/' . $this->rest_base, array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_global_thresholds'),
                'permission_callback' => array($this, 'check_admin_permissions'),
            ),
        ));

        // GET /room-thresholds/{client_id} - Get client thresholds
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<client_id>\d+)', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_client_thresholds'),
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

        // PUT /room-thresholds/{client_id} - Save client thresholds
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<client_id>\d+)', array(
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array($this, 'update_client_thresholds'),
                'permission_callback' => array($this, 'check_admin_permissions'),
                'args'                => array(
                    'client_id' => array(
                        'required'          => true,
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                        'validate_callback' => array($this, 'validate_client_id'),
                    ),
                    'problem_max' => array(
                        'required'          => true,
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                        'validate_callback' => array($this, 'validate_threshold_value'),
                    ),
                    'solution_max' => array(
                        'required'          => true,
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                        'validate_callback' => array($this, 'validate_threshold_value'),
                    ),
                    'offer_min' => array(
                        'required'          => true,
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                        'validate_callback' => array($this, 'validate_threshold_value'),
                    ),
                ),
            ),
        ));

        // DELETE /room-thresholds/{client_id} - Reset to global defaults
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<client_id>\d+)', array(
            array(
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => array($this, 'delete_client_thresholds'),
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

        // PUT /room-thresholds - Update global defaults (admin only)
        register_rest_route($this->namespace, '/' . $this->rest_base, array(
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array($this, 'update_global_thresholds'),
                'permission_callback' => array($this, 'check_admin_permissions'),
                'args'                => array(
                    'problem_max' => array(
                        'required'          => true,
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                        'validate_callback' => array($this, 'validate_threshold_value'),
                    ),
                    'solution_max' => array(
                        'required'          => true,
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                        'validate_callback' => array($this, 'validate_threshold_value'),
                    ),
                    'offer_min' => array(
                        'required'          => true,
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                        'validate_callback' => array($this, 'validate_threshold_value'),
                    ),
                ),
            ),
        ));
    }

    /**
     * Get global threshold defaults
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function get_global_thresholds($request) {
        try {
            $thresholds = $this->db->get_global_thresholds();
            
            if (!$thresholds) {
                // Return default values if none exist
                $thresholds = array(
                    'problem_max'  => 40,
                    'solution_max' => 60,
                    'offer_min'    => 61,
                    'is_global'    => true,
                );
            } else {
                $thresholds['is_global'] = true;
            }

            return rest_ensure_response(array(
                'success' => true,
                'data'    => $thresholds,
            ));

        } catch (Exception $e) {
            return new WP_Error(
                'threshold_error',
                $e->getMessage(),
                array('status' => 500)
            );
        }
    }

    /**
     * Get client-specific thresholds with global indicators
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function get_client_thresholds($request) {
        $client_id = $request->get_param('client_id');

        try {
            // Get global defaults first
            $global = $this->db->get_global_thresholds();
            if (!$global) {
                $global = array(
                    'problem_max'  => 40,
                    'solution_max' => 60,
                    'offer_min'    => 61,
                );
            }

            // Try to get client-specific overrides
            $client = $this->db->get_client_thresholds($client_id);
            
            // Check if client has custom thresholds by comparing with global
            $has_custom = false;
            if ($client) {
                $has_custom = (
                    $client['problem_max'] != $global['problem_max'] ||
                    $client['solution_max'] != $global['solution_max'] ||
                    $client['offer_min'] != $global['offer_min']
                );
            }

            $response = array(
                'client_id'       => $client_id,
                'problem_max'     => $client['problem_max'],
                'solution_max'    => $client['solution_max'],
                'offer_min'       => $client['offer_min'],
                'is_custom'       => $has_custom,
                'global_defaults' => $global,
            );

            return rest_ensure_response(array(
                'success' => true,
                'data'    => $response,
            ));

        } catch (Exception $e) {
            return new WP_Error(
                'threshold_error',
                $e->getMessage(),
                array('status' => 500)
            );
        }
    }

    /**
     * Update client-specific thresholds
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function update_client_thresholds($request) {
        $client_id = $request->get_param('client_id');
        
        $data = array(
            'problem_max'  => $request->get_param('problem_max'),
            'solution_max' => $request->get_param('solution_max'),
            'offer_min'    => $request->get_param('offer_min'),
        );

        // Validate threshold logic
        $validation = $this->validate_threshold_logic($data);
        if (is_wp_error($validation)) {
            return $validation;
        }

        try {
            // save_client_thresholds handles both insert and update (upsert)
            $result = $this->db->save_client_thresholds($client_id, $data);

            if ($result === false) {
                throw new Exception('Failed to save client thresholds');
            }

            // Get the updated thresholds
            $updated = $this->db->get_client_thresholds($client_id);

            return rest_ensure_response(array(
                'success' => true,
                'message' => 'Client thresholds updated successfully',
                'data'    => array_merge($updated, array('client_id' => $client_id)),
            ));

        } catch (Exception $e) {
            return new WP_Error(
                'threshold_update_error',
                $e->getMessage(),
                array('status' => 500)
            );
        }
    }

    /**
     * Delete client-specific thresholds (reset to global)
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function delete_client_thresholds($request) {
        $client_id = $request->get_param('client_id');

        try {
            $result = $this->db->delete_client_thresholds($client_id);

            if ($result === false) {
                throw new Exception('Failed to delete client thresholds');
            }

            // Get global defaults to return
            $global = $this->db->get_global_thresholds();
            if (!$global) {
                $global = array(
                    'problem_max'  => 40,
                    'solution_max' => 60,
                    'offer_min'    => 61,
                );
            }

            return rest_ensure_response(array(
                'success' => true,
                'message' => 'Client thresholds reset to global defaults',
                'data'    => array_merge($global, array(
                    'client_id' => $client_id,
                    'is_custom' => false,
                )),
            ));

        } catch (Exception $e) {
            return new WP_Error(
                'threshold_delete_error',
                $e->getMessage(),
                array('status' => 500)
            );
        }
    }

    /**
     * Update global threshold defaults
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function update_global_thresholds($request) {
        $data = array(
            'problem_max'  => $request->get_param('problem_max'),
            'solution_max' => $request->get_param('solution_max'),
            'offer_min'    => $request->get_param('offer_min'),
        );

        // Validate threshold logic
        $validation = $this->validate_threshold_logic($data);
        if (is_wp_error($validation)) {
            return $validation;
        }

        try {
            // Check if global thresholds exist
            $existing = $this->db->get_global_thresholds();

            // Use save method which handles both create and update (upsert)
            if ($existing) {
                $result = $this->db->update_global_thresholds($data);
            } else {
                // For global, we still need update_global_thresholds as it handles NULL client_id
                $result = $this->db->update_global_thresholds($data);
            }

            if ($result === false) {
                throw new Exception('Failed to save global thresholds');
            }

            // Get the updated thresholds
            $updated = $this->db->get_global_thresholds();

            return rest_ensure_response(array(
                'success' => true,
                'message' => 'Global thresholds updated successfully',
                'data'    => array_merge($updated, array('is_global' => true)),
            ));

        } catch (Exception $e) {
            return new WP_Error(
                'threshold_update_error',
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
     * Validate threshold value is positive
     *
     * @param int $value Threshold value.
     * @return bool
     */
    public function validate_threshold_value($value) {
        return is_numeric($value) && $value >= 0 && $value <= 1000;
    }

    /**
     * Validate threshold logic (problem < solution < offer)
     *
     * @param array $data Threshold data.
     * @return bool|WP_Error
     */
    private function validate_threshold_logic($data) {
        $problem_max  = $data['problem_max'];
        $solution_max = $data['solution_max'];
        $offer_min    = $data['offer_min'];

        if ($problem_max >= $solution_max) {
            return new WP_Error(
                'invalid_thresholds',
                'Problem max must be less than Solution max',
                array('status' => 400)
            );
        }

        if ($solution_max >= $offer_min) {
            return new WP_Error(
                'invalid_thresholds',
                'Solution max must be less than Offer min',
                array('status' => 400)
            );
        }

        // Recommended: offer_min should be solution_max + 1
        if ($offer_min !== $solution_max + 1) {
            // This is a warning, not an error - we allow it but could log it
            error_log(sprintf(
                'Room threshold gap detected: solution_max=%d, offer_min=%d (expected %d)',
                $solution_max,
                $offer_min,
                $solution_max + 1
            ));
        }

        return true;
    }
}