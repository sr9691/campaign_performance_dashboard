<?php
/**
 * Score Calculator REST API Controller
 *
 * Handles REST API endpoints for lead score calculation.
 * Calculates scores based on scoring rules and assigns rooms.
 *
 * @package    DirectReach
 * @subpackage RTR/ScoringSystem
 * @since      2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class RTR_Score_Calculator_Controller extends WP_REST_Controller {

    /**
     * Namespace for REST routes
     *
     * @var string
     */
    protected $namespace = 'directreach/v2';

    /**
     * Base route for score calculator
     *
     * @var string
     */
    protected $rest_base = 'calculate-score';

    /**
     * Score calculator instance
     *
     * @var RTR_Score_Calculator
     */
    private $calculator;

    /**
     * Constructor
     */
    public function __construct() {
        // Score calculator will be initialized when needed
        // to avoid loading dependencies unnecessarily
    }

    /**
     * Register REST routes
     */
    public function register_routes() {
        
        // POST /calculate-score - Calculate score for visitor
        register_rest_route($this->namespace, '/' . $this->rest_base, array(
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array($this, 'calculate_visitor_score'),
                'permission_callback' => array($this, 'check_admin_permissions'),
                'args'                => array(
                    'visitor_id' => array(
                        'required'          => true,
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                        'validate_callback' => array($this, 'validate_visitor_id'),
                    ),
                    'client_id' => array(
                        'required'          => true,
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                        'validate_callback' => array($this, 'validate_client_id'),
                    ),
                    'force_recalculate' => array(
                        'required'          => false,
                        'type'              => 'boolean',
                        'default'           => false,
                        'sanitize_callback' => 'rest_sanitize_boolean',
                    ),
                ),
            ),
        ));

        // POST /recalculate-all/{client_id} - Recalculate all visitors for client
        register_rest_route($this->namespace, '/recalculate-all/(?P<client_id>\d+)', array(
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array($this, 'recalculate_all_visitors'),
                'permission_callback' => array($this, 'check_admin_permissions'),
                'args'                => array(
                    'client_id' => array(
                        'required'          => true,
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                        'validate_callback' => array($this, 'validate_client_id'),
                    ),
                    'batch_size' => array(
                        'required'          => false,
                        'type'              => 'integer',
                        'default'           => 50,
                        'sanitize_callback' => 'absint',
                        'validate_callback' => array($this, 'validate_batch_size'),
                    ),
                ),
            ),
        ));

        // GET /score-status/{client_id} - Get scoring status for client
        register_rest_route($this->namespace, '/score-status/(?P<client_id>\d+)', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_scoring_status'),
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
    }

    /**
     * Calculate score for a single visitor
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function calculate_visitor_score($request) {
        $visitor_id        = $request->get_param('visitor_id');
        $client_id         = $request->get_param('client_id');
        $force_recalculate = $request->get_param('force_recalculate');

        try {
            // Initialize calculator if needed
            if (!$this->calculator) {
                $this->calculator = new RTR_Score_Calculator();
            }

            // Check if visitor belongs to client
            if (!$this->verify_visitor_client_relationship($visitor_id, $client_id)) {
                return new WP_Error(
                    'invalid_relationship',
                    'Visitor does not belong to specified client',
                    array('status' => 400)
                );
            }

            // Check if we need to recalculate or can use cached score
            $cached_score = $this->get_cached_score($visitor_id);
            
            if (!$force_recalculate && $cached_score && $this->is_score_fresh($cached_score)) {
                return rest_ensure_response(array(
                    'success'      => true,
                    'data'         => $cached_score,
                    'from_cache'   => true,
                    'calculated_at' => $cached_score['score_calculated_at'],
                ));
            }

            // Calculate new score
            $start_time = microtime(true);
            $result = $this->calculator->calculate_score($visitor_id, $client_id);
            $calculation_time = (microtime(true) - $start_time) * 1000; // Convert to milliseconds

            if (!$result) {
                throw new Exception('Score calculation failed');
            }

            // Update cached score in database
            $this->update_cached_score($visitor_id, $result);

            return rest_ensure_response(array(
                'success'           => true,
                'data'              => $result,
                'from_cache'        => false,
                'calculation_time_ms' => round($calculation_time, 2),
            ));

        } catch (Exception $e) {
            return new WP_Error(
                'calculation_error',
                $e->getMessage(),
                array('status' => 500)
            );
        }
    }

    /**
     * Recalculate scores for all visitors of a client
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function recalculate_all_visitors($request) {
        $client_id  = $request->get_param('client_id');
        $batch_size = $request->get_param('batch_size');

        try {
            // Initialize calculator if needed
            if (!$this->calculator) {
                $this->calculator = new RTR_Score_Calculator();
            }

            // Get all visitors for this client
            global $wpdb;
            $visitor_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT v.id 
                FROM {$wpdb->prefix}cpd_visitors v
                WHERE v.client_id = %d
                AND v.is_active = 1
                ORDER BY v.last_visit_date DESC",
                $client_id
            ));

            if (empty($visitor_ids)) {
                return rest_ensure_response(array(
                    'success' => true,
                    'message' => 'No visitors found for this client',
                    'data'    => array(
                        'total'     => 0,
                        'processed' => 0,
                        'failed'    => 0,
                    ),
                ));
            }

            // Process in batches
            $total     = count($visitor_ids);
            $processed = 0;
            $failed    = 0;
            $results   = array();
            $start_time = microtime(true);

            foreach (array_chunk($visitor_ids, $batch_size) as $batch) {
                foreach ($batch as $visitor_id) {
                    try {
                        $result = $this->calculator->calculate_score($visitor_id, $client_id);
                        
                        if ($result) {
                            $this->update_cached_score($visitor_id, $result);
                            $processed++;
                        } else {
                            $failed++;
                            error_log("Failed to calculate score for visitor: {$visitor_id}");
                        }

                    } catch (Exception $e) {
                        $failed++;
                        error_log("Error calculating score for visitor {$visitor_id}: " . $e->getMessage());
                    }
                }

                // Add a small delay between batches to avoid overwhelming the server
                if ($batch_size > 50) {
                    usleep(100000); // 0.1 seconds
                }
            }

            $total_time = (microtime(true) - $start_time) * 1000; // Convert to milliseconds

            return rest_ensure_response(array(
                'success' => true,
                'message' => sprintf(
                    'Recalculated scores for %d visitors (%d failed)',
                    $processed,
                    $failed
                ),
                'data' => array(
                    'total'                => $total,
                    'processed'            => $processed,
                    'failed'               => $failed,
                    'success_rate'         => $total > 0 ? round(($processed / $total) * 100, 2) : 0,
                    'total_time_ms'        => round($total_time, 2),
                    'avg_time_per_visitor' => $processed > 0 ? round($total_time / $processed, 2) : 0,
                ),
            ));

        } catch (Exception $e) {
            return new WP_Error(
                'recalculation_error',
                $e->getMessage(),
                array('status' => 500)
            );
        }
    }

    /**
     * Get scoring status for a client
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function get_scoring_status($request) {
        $client_id = $request->get_param('client_id');

        try {
            global $wpdb;

            // Get visitor counts by room
            $room_counts = $wpdb->get_results($wpdb->prepare(
                "SELECT 
                    current_room,
                    COUNT(*) as count
                FROM {$wpdb->prefix}cpd_visitors
                WHERE client_id = %d
                AND is_active = 1
                GROUP BY current_room",
                $client_id
            ), ARRAY_A);

            $counts = array(
                'none'     => 0,
                'problem'  => 0,
                'solution' => 0,
                'offer'    => 0,
            );

            foreach ($room_counts as $row) {
                $counts[$row['current_room']] = (int) $row['count'];
            }

            // Get score statistics
            $score_stats = $wpdb->get_row($wpdb->prepare(
                "SELECT 
                    COUNT(*) as total_visitors,
                    AVG(lead_score) as avg_score,
                    MIN(lead_score) as min_score,
                    MAX(lead_score) as max_score,
                    COUNT(CASE WHEN score_calculated_at IS NULL THEN 1 END) as uncalculated,
                    COUNT(CASE WHEN score_calculated_at < DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as stale
                FROM {$wpdb->prefix}cpd_visitors
                WHERE client_id = %d
                AND is_active = 1",
                $client_id
            ), ARRAY_A);

            // Get recent score calculations
            $recent_calculations = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*)
                FROM {$wpdb->prefix}cpd_visitors
                WHERE client_id = %d
                AND is_active = 1
                AND score_calculated_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)",
                $client_id
            ));

            return rest_ensure_response(array(
                'success' => true,
                'data'    => array(
                    'room_distribution' => $counts,
                    'total_visitors'    => (int) $score_stats['total_visitors'],
                    'average_score'     => round((float) $score_stats['avg_score'], 2),
                    'min_score'         => (int) $score_stats['min_score'],
                    'max_score'         => (int) $score_stats['max_score'],
                    'uncalculated'      => (int) $score_stats['uncalculated'],
                    'stale_scores'      => (int) $score_stats['stale'],
                    'recent_calculations' => (int) $recent_calculations,
                    'needs_recalculation' => (int) $score_stats['uncalculated'] + (int) $score_stats['stale'],
                ),
            ));

        } catch (Exception $e) {
            return new WP_Error(
                'status_error',
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
     * Validate visitor ID exists
     *
     * @param int $visitor_id Visitor ID.
     * @return bool
     */
    public function validate_visitor_id($visitor_id) {
        global $wpdb;
        
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}cpd_visitors WHERE id = %d",
            $visitor_id
        ));

        return $exists > 0;
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
     * Validate batch size
     *
     * @param int $batch_size Batch size.
     * @return bool
     */
    public function validate_batch_size($batch_size) {
        return $batch_size > 0 && $batch_size <= 200;
    }

    /**
     * Verify visitor belongs to client
     *
     * @param int $visitor_id Visitor ID.
     * @param int $client_id  Client ID.
     * @return bool
     */
    private function verify_visitor_client_relationship($visitor_id, $client_id) {
        global $wpdb;
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) 
            FROM {$wpdb->prefix}cpd_visitors 
            WHERE id = %d AND client_id = %d",
            $visitor_id,
            $client_id
        ));

        return $count > 0;
    }

    /**
     * Get cached score for visitor
     *
     * @param int $visitor_id Visitor ID.
     * @return array|null
     */
    private function get_cached_score($visitor_id) {
        global $wpdb;
        
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                lead_score,
                current_room,
                score_calculated_at
            FROM {$wpdb->prefix}cpd_visitors
            WHERE id = %d",
            $visitor_id
        ), ARRAY_A);

        if (!$result || $result['lead_score'] === null) {
            return null;
        }

        return $result;
    }

    /**
     * Check if cached score is still fresh (within 24 hours)
     *
     * @param array $cached_score Cached score data.
     * @return bool
     */
    private function is_score_fresh($cached_score) {
        if (!isset($cached_score['score_calculated_at']) || !$cached_score['score_calculated_at']) {
            return false;
        }

        $calculated_time = strtotime($cached_score['score_calculated_at']);
        $current_time    = current_time('timestamp');
        $age_hours       = ($current_time - $calculated_time) / 3600;

        // Consider score fresh if less than 24 hours old
        return $age_hours < 24;
    }

    /**
     * Update cached score in database
     *
     * @param int   $visitor_id Visitor ID.
     * @param array $score_data Score calculation result.
     * @return bool
     */
    private function update_cached_score($visitor_id, $score_data) {
        global $wpdb;
        
        $result = $wpdb->update(
            $wpdb->prefix . 'cpd_visitors',
            array(
                'lead_score'          => $score_data['total_score'],
                'current_room'        => $score_data['current_room'],
                'score_calculated_at' => current_time('mysql'),
            ),
            array('id' => $visitor_id),
            array('%d', '%s', '%s'),
            array('%d')
        );

        return $result !== false;
    }
}