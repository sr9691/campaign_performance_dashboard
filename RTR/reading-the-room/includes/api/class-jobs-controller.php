<?php
/**
 * Jobs Controller
 *
 * REST API controller for automated job operations.
 * Handles nightly jobs, campaign matching, prospect creation, and room assignments.
 *
 * @package DirectReach
 * @subpackage ReadingTheRoom
 * @since 2.0.0
 * @version 2.4.0 - FIXED VERSION
 */

namespace DirectReach\ReadingTheRoom\API;

// FIXED: Added proper namespace imports
use DirectReach\ReadingTheRoom\Campaign_Matcher;
use DirectReach\ReadingTheRoom\Reading_Room_Database;
use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Jobs REST API Controller
 * FIXED: Added type declarations, constants, and improved structure
 */
class Jobs_Controller extends WP_REST_Controller {

    // FIXED: Added class constants for magic values
    private const LOG_PREFIX = '[RTR]';
    private const LOG_PREFIX_JOB = '[RTR JOB';
    
    private const MODE_INCREMENTAL = 'incremental';
    private const MODE_FULL = 'full';
    
    private const ROOM_HIERARCHY = [
        'none'     => 0,
        'problem'  => 1,
        'solution' => 2,
        'offer'    => 3,
    ];
    
    private const CACHE_TTL = 300; // 5 minutes
    
    /**
     * Namespace for REST routes
     *
     * @var string
     */
    protected $namespace = 'directreach/v2';

    /**
     * Database instance
     * FIXED: Added proper type declaration
     *
     * @var Reading_Room_Database
     */
    private Reading_Room_Database $db;

    /**
     * Campaign matcher instance
     * FIXED: Added proper type declaration
     *
     * @var Campaign_Matcher|null
     */
    private ?Campaign_Matcher $campaign_matcher = null;

    /**
     * Job start time
     *
     * @var float
     */
    private float $job_start_time;

    /**
     * Job statistics
     *
     * @var array<string,int>
     */
    private array $job_stats = [
        'campaigns_matched'   => 0,
        'prospects_created'   => 0,
        'prospects_updated'   => 0,
        'prospects_skipped'   => 0,
        'room_transitions'    => 0,
        'room_transitions_delayed' => 0,
        'scores_calculated'   => 0,
        'errors'              => 0,
        'visitors_processed'  => 0,
    ];

    /**
     * Scoring rules cache
     *
     * @var array<int,array>
     */
    private array $scoring_rules_cache = [];

    /**
     * Room thresholds cache with timestamps
     * FIXED: Added cache timestamps for TTL
     *
     * @var array<int,array>
     */
    private array $thresholds_cache = [];
    
    /**
     * Cache timestamps for TTL management
     *
     * @var array<int,int>
     */
    private array $cache_timestamps = [];

    /**
     * Constructor with dependency injection
     * FIXED: Removed global $dr_rtr_db usage, using dependency injection
     * 
     * @param Reading_Room_Database|null $db Database instance (required)
     * @throws \RuntimeException If database instance is invalid
     */
    public function __construct(?Reading_Room_Database $db = null) {
        // FIXED: Proper dependency injection instead of global variable
        if (!$db instanceof Reading_Room_Database) {
            // Fallback: try to instantiate with global $wpdb
            global $wpdb;
            if (isset($wpdb) && $wpdb instanceof \wpdb) {
                $db = new Reading_Room_Database($wpdb);
            } else {
                throw new \RuntimeException('Database instance required for Jobs_Controller');
            }
        }
        
        $this->db = $db;
        
        // Initialize campaign matcher if available
        if (class_exists(Campaign_Matcher::class)) {
            $this->campaign_matcher = new Campaign_Matcher($this->db);
        }
        
        error_log(self::LOG_PREFIX . ' Jobs_Controller instantiated');
    }

    /**
     * Register REST API routes
     * 
     * @return void
     */
    public function register_routes(): void {
        error_log(self::LOG_PREFIX . ' Registering Jobs_Controller routes...');

        // POST /jobs/run-nightly - Main nightly job (all operations)
        register_rest_route($this->namespace, '/jobs/run-nightly', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'run_nightly_job'],
                'permission_callback' => [$this, 'check_api_key'],
                'args'                => [
                    'mode' => [
                        'required'          => false,
                        'type'              => 'string',
                        'enum'              => [self::MODE_INCREMENTAL, self::MODE_FULL],
                        'default'           => self::MODE_INCREMENTAL,
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'force_full' => [
                        'required'          => false,
                        'type'              => 'boolean',
                        'default'           => false,
                    ],
                ],
            ],
        ]);

        // POST /jobs/match-campaigns - Campaign attribution (Phase 3)
        register_rest_route($this->namespace, '/jobs/match-campaigns', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'match_campaigns'],
                'permission_callback' => [$this, 'check_api_key'],
                'args'                => [
                    'mode' => [
                        'required'          => false,
                        'type'              => 'string',
                        'enum'              => [self::MODE_INCREMENTAL, self::MODE_FULL],
                        'default'           => self::MODE_INCREMENTAL,
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
            ],
        ]);

        // POST /jobs/create-prospects - Create prospect records
        register_rest_route($this->namespace, '/jobs/create-prospects', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'create_prospects'],
                'permission_callback' => [$this, 'check_api_key'],
                'args'                => [
                    'client_id' => [
                        'required'          => false,
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ],
        ]);

        // POST /jobs/calculate-scores - Calculate visitor scores
        register_rest_route($this->namespace, '/jobs/calculate-scores', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'calculate_scores'],
                'permission_callback' => [$this, 'check_api_key'],
            ],
        ]);

        // POST /jobs/assign-rooms - Assign rooms based on scores
        register_rest_route($this->namespace, '/jobs/assign-rooms', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'assign_rooms'],
                'permission_callback' => [$this, 'check_api_key'],
            ],
        ]);

        error_log(self::LOG_PREFIX . ' Jobs_Controller routes registered successfully');
    }

    /**
     * Run nightly job (all operations in sequence)
     * ENHANCED: Comprehensive logging with system state
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response with stats or error
     */
    public function run_nightly_job($request): WP_REST_Response|WP_Error {
        $this->job_start_time = microtime(true);
        
        // Get mode from request, check force_full parameter
        $mode = $request->get_param('mode') ?: self::MODE_INCREMENTAL;
        $force_full = $request->get_param('force_full');
        
        // Override mode if force_full is true
        if ($force_full === true || $force_full === 'true' || $force_full === '1') {
            $mode = self::MODE_FULL;
        }
        
        // ENHANCEMENT: Log comprehensive job start with system state
        global $wpdb;
        $system_stats = [
            'total_visitors' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}cpd_visitors"),
            'visitors_with_campaigns' => $wpdb->get_var("SELECT COUNT(DISTINCT visitor_id) FROM {$wpdb->prefix}cpd_visitor_campaigns"),
            'visitors_with_scores' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}cpd_visitors WHERE lead_score > 0"),
            'existing_prospects' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}rtr_prospects WHERE archived_at IS NULL"),
            'active_campaigns' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}dr_campaign_settings WHERE end_date > CURDATE()"),
        ];
        
        $this->log_job('nightly_job_start', sprintf(
            'Starting nightly job in %s mode at %s. System state: %s. Parameters: %s',
            $mode,
            current_time('mysql'),
            json_encode($system_stats),
            json_encode($request->get_params())
        ));

        try {
            // Step 1: Match campaigns (Phase 3)
            $this->log_job('step_1_start', 'Starting campaign matching...');
            $match_result = $this->match_campaigns_internal($mode);
            $this->job_stats['campaigns_matched'] = $match_result['matched'] ?? 0;
            $this->log_job('step_1_complete', sprintf(
                'Campaign matching complete. Matched: %d visitors to campaigns, Skipped: %d',
                $match_result['matched'] ?? 0,
                $match_result['skipped'] ?? 0
            ));

            // Step 2: Calculate scores (Scoring System)
            $this->log_job('step_2_start', 'Starting score calculation...');
            $score_result = $this->calculate_scores_internal();
            $this->job_stats['scores_calculated'] = $score_result['calculated'] ?? 0;
            $this->log_job('step_2_complete', sprintf(
                'Score calculation complete. Calculated: %d scores out of %d visitors',
                $score_result['calculated'] ?? 0,
                $score_result['total'] ?? 0
            ));

            // Step 3: Create/update prospects
            $this->log_job('step_3_start', 'Starting prospect creation/update...');
            $prospect_result = $this->create_prospects_internal();
            $this->job_stats['prospects_created'] = $prospect_result['created'] ?? 0;
            $this->job_stats['prospects_updated'] = $prospect_result['updated'] ?? 0;
            $this->job_stats['prospects_skipped'] = $prospect_result['skipped'] ?? 0;
            $this->log_job('step_3_complete', sprintf(
                'Prospect creation/update complete. Created: %d, Updated: %d, Skipped: %d',
                $prospect_result['created'] ?? 0,
                $prospect_result['updated'] ?? 0,
                $prospect_result['skipped'] ?? 0
            ));

            // Step 4: Assign rooms
            $this->log_job('step_4_start', 'Starting room assignments...');
            $room_result = $this->assign_rooms_internal();
            $this->job_stats['room_transitions'] = $room_result['transitions'] ?? 0;
            $this->job_stats['room_transitions_delayed'] = $room_result['delayed'] ?? 0;
            $this->log_job('step_4_complete', sprintf(
                'Room assignment complete. Transitions: %d, Delayed: %d, Total: %d',
                $room_result['transitions'] ?? 0,
                $room_result['delayed'] ?? 0,
                $room_result['total'] ?? 0
            ));

            // Calculate job duration
            $duration = round(microtime(true) - $this->job_start_time, 2);

            // ENHANCEMENT: Add final system state snapshot
            $final_stats = [
                'total_visitors' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}cpd_visitors"),
                'visitors_with_campaigns' => $wpdb->get_var("SELECT COUNT(DISTINCT visitor_id) FROM {$wpdb->prefix}cpd_visitor_campaigns"),
                'visitors_with_scores' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}cpd_visitors WHERE lead_score > 0"),
                'existing_prospects' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}rtr_prospects WHERE archived_at IS NULL"),
            ];

            $this->log_job('nightly_job_complete', sprintf(
                'Nightly job completed in %s seconds. Final state: %s. Stats: %s',
                $duration,
                json_encode($final_stats),
                json_encode($this->job_stats)
            ));

            return new WP_REST_Response([
                'success'  => true,
                'duration' => $duration,
                'stats'    => $this->job_stats,
                'mode'     => $mode,
            ], 200);

        } catch (\Exception $e) {
            $this->job_stats['errors']++;
            
            $this->log_job('nightly_job_error', sprintf(
                'Fatal error in nightly job: %s. Stack trace: %s',
                $e->getMessage(),
                $e->getTraceAsString()
            ), 'error');

            return new WP_Error(
                'job_failed',
                'Nightly job failed: ' . $e->getMessage(),
                ['status' => 500, 'stats' => $this->job_stats]
            );
        }
    }

    /**
     * Match campaigns to visitors
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response with match stats or error
     */
    public function match_campaigns($request): WP_REST_Response|WP_Error {
        $mode = $request->get_param('mode') ?: self::MODE_INCREMENTAL;

        try {
            $result = $this->match_campaigns_internal($mode);

            return new WP_REST_Response([
                'success' => true,
                'mode'    => $mode,
                'matched' => $result['matched'] ?? 0,
                'skipped' => $result['skipped'] ?? 0,
            ], 200);

        } catch (\Exception $e) {
            return new WP_Error(
                'campaign_match_failed',
                'Campaign matching failed: ' . $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Create prospects from visitors
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response with creation stats or error
     */
    public function create_prospects($request): WP_REST_Response|WP_Error {
        $client_id = $request->get_param('client_id');

        try {
            $result = $this->create_prospects_internal($client_id);

            return new WP_REST_Response([
                'success' => true,
                'created' => $result['created'] ?? 0,
                'updated' => $result['updated'] ?? 0,
                'skipped' => $result['skipped'] ?? 0,
            ], 200);

        } catch (\Exception $e) {
            return new WP_Error(
                'prospect_creation_failed',
                'Prospect creation failed: ' . $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Calculate visitor scores
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response with calculation stats or error
     */
    public function calculate_scores($request): WP_REST_Response|WP_Error {
        try {
            $result = $this->calculate_scores_internal();

            return new WP_REST_Response([
                'success'    => true,
                'calculated' => $result['calculated'] ?? 0,
                'total'      => $result['total'] ?? 0,
            ], 200);

        } catch (\Exception $e) {
            return new WP_Error(
                'score_calculation_failed',
                'Score calculation failed: ' . $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Assign rooms based on scores
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response with assignment stats or error
     */
    public function assign_rooms($request): WP_REST_Response|WP_Error {
        try {
            $result = $this->assign_rooms_internal();

            return new WP_REST_Response([
                'success'     => true,
                'transitions' => $result['transitions'] ?? 0,
                'delayed'     => $result['delayed'] ?? 0,
                'total'       => $result['total'] ?? 0,
            ], 200);

        } catch (\Exception $e) {
            return new WP_Error(
                'room_assignment_failed',
                'Room assignment failed: ' . $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /* =========================================================================
     * INTERNAL JOB METHODS
     * ======================================================================= */

    /**
     * Internal campaign matching logic
     * ENHANCED: Better logging and error context
     *
     * @param string $mode Processing mode (incremental or full).
     * @return array{matched: int, skipped: int, total: int} Results.
     */
    private function match_campaigns_internal(string $mode = self::MODE_INCREMENTAL): array {
        if (!$this->campaign_matcher) {
            $this->log_job('campaign_match_error', 'Campaign matcher not initialized', 'error');
            throw new \Exception('Campaign matcher not available');
        }

        $matched = 0;
        $skipped = 0;

        global $wpdb;

        // Get unmatched visitors (those without campaign assignments)
        $where_clause = $mode === self::MODE_FULL
            ? "" 
            : "WHERE v.visitor_id NOT IN (SELECT visitor_id FROM {$wpdb->prefix}cpd_visitor_campaigns)";

        $visitors = $wpdb->get_results("
            SELECT v.* 
            FROM {$wpdb->prefix}cpd_visitors v
            {$where_clause}
            ORDER BY v.last_seen_at DESC
        ");

        $this->log_job('campaign_match_batch_start', sprintf(
            'Starting %s campaign matching for %d visitors',
            $mode,
            count($visitors)
        ));

        foreach ($visitors as $visitor) {
            try {
                // Use the campaign matcher to find matching campaigns
                $match = $this->campaign_matcher->match(['visitor_id' => (int) $visitor->id]);

                if ($match !== null) {
                    $wpdb->query($wpdb->prepare(
                        "INSERT IGNORE INTO {$wpdb->prefix}cpd_visitor_campaigns 
                        (visitor_id, campaign_id) VALUES (%d, %d)",
                        $visitor->id,
                        $match['id']
                    ));
                    $matched++;
                } else {
                    $skipped++;
                }

            } catch (\Exception $e) {
                $this->job_stats['errors']++;
                $this->log_job('campaign_match_visitor_error', sprintf(
                    'Campaign matching failed for visitor %d: %s',
                    $visitor->id,
                    $e->getMessage()
                ), 'error');
            }
        }

        return [
            'matched' => $matched,
            'skipped' => $skipped,
            'total'   => count($visitors),
        ];
    }

    /**
     * Internal score calculation logic
     * PHASE 2.1: Database-driven scoring using rtr_client_scoring_rules
     *
     * @return array{calculated: int, total: int} Results.
     */
    private function calculate_scores_internal(): array {
        global $wpdb;

        $calculated = 0;
        $total = 0;

        // Get visitors with campaign assignments
        $visitors = $wpdb->get_results("
            SELECT DISTINCT v.*, vc.campaign_id, cs.client_id
            FROM {$wpdb->prefix}cpd_visitors v
            INNER JOIN {$wpdb->prefix}cpd_visitor_campaigns vc ON v.id = vc.visitor_id
            INNER JOIN {$wpdb->prefix}dr_campaign_settings cs ON vc.campaign_id = cs.id
            WHERE v.lead_score IS NULL OR v.lead_score = 0
        ");

        $total = count($visitors);

        $this->log_job('score_calculation_start', sprintf(
            'Starting score calculation for %d visitors',
            $total
        ));

        foreach ($visitors as $visitor) {
            try {
                $client_id = $visitor->client_id;
                
                // Get scoring rules for this client (cached)
                $rules = $this->get_scoring_rules($client_id);
                
                if (empty($rules)) {
                    $this->log_job('score_calculation_no_rules', sprintf(
                        'No scoring rules found for client %d (visitor %d)',
                        $client_id,
                        $visitor->id
                    ), 'warning');
                    continue;
                }

                // Calculate base score from visitor data
                $score = $this->calculate_visitor_score($visitor, $rules);

                // Update visitor with calculated score
                $wpdb->update(
                    $wpdb->prefix . 'cpd_visitors',
                    [
                        'lead_score' => $score,
                        'updated_at' => current_time('mysql'),
                    ],
                    ['id' => $visitor->id],
                    ['%d', '%s'],
                    ['%d']
                );

                $calculated++;

            } catch (\Exception $e) {
                $this->job_stats['errors']++;
                $this->log_job('score_calculation_visitor_error', sprintf(
                    'Score calculation failed for visitor %d: %s',
                    $visitor->id,
                    $e->getMessage()
                ), 'error');
            }
        }

        return [
            'calculated' => $calculated,
            'total'      => $total,
        ];
    }

    /**
     * Get scoring rules for a client
     * PHASE 2.1: Query rtr_client_scoring_rules table
     * FIXED: Added return type
     *
     * @param int $client_id Client ID.
     * @return array<int,array> Scoring rules.
     */
    private function get_scoring_rules(int $client_id): array {
        // FIXED: Check cache with TTL
        if (isset($this->scoring_rules_cache[$client_id])) {
            $cached_at = $this->cache_timestamps['rules_' . $client_id] ?? 0;
            if (time() - $cached_at < self::CACHE_TTL) {
                return $this->scoring_rules_cache[$client_id];
            }
        }

        global $wpdb;

        // Query scoring rules for this client
        $rules = $wpdb->get_results($wpdb->prepare("
            SELECT *
            FROM {$wpdb->prefix}rtr_client_scoring_rules
            WHERE client_id = %d
            ORDER BY priority ASC
        ", $client_id), ARRAY_A);

        // Cache the rules with timestamp
        $this->scoring_rules_cache[$client_id] = $rules;
        $this->cache_timestamps['rules_' . $client_id] = time();

        return $rules;
    }

    /**
     * Calculate visitor score based on rules
     * PHASE 2.1: Apply database-driven scoring rules
     * FIXED: Added return type and parameter types
     *
     * @param object $visitor Visitor data.
     * @param array<int,array>  $rules   Scoring rules.
     * @return int Calculated score.
     */
    private function calculate_visitor_score(object $visitor, array $rules): int {
        $score = 0;

        foreach ($rules as $rule) {
            $rule_type = $rule['rule_type'];
            $points = (int) $rule['points'];

            switch ($rule_type) {
                case 'page_visit':
                    // Award points for page visits
                    $visit_count = (int) ($visitor->visit_count ?? 0);
                    $score += $visit_count * $points;
                    break;

                case 'email_open':
                    // Award points for email opens
                    if (!empty($visitor->email_opened)) {
                        $score += $points;
                    }
                    break;

                case 'email_click':
                    // Award points for email clicks
                    if (!empty($visitor->email_clicked)) {
                        $score += $points;
                    }
                    break;

                case 'form_submission':
                    // Award points for form submissions
                    if (!empty($visitor->form_submitted)) {
                        $score += $points;
                    }
                    break;

                case 'recency':
                    // Recency decay - recent visits score higher
                    if (!empty($visitor->last_visit)) {
                        $days_ago = (strtotime('now') - strtotime($visitor->last_visit)) / DAY_IN_SECONDS;
                        
                        if ($days_ago <= 7) {
                            $score += $points;
                        } elseif ($days_ago <= 30) {
                            $score += floor($points * 0.5);
                        }
                    }
                    break;

                case 'engagement_multiplier':
                    // Apply multiplier based on engagement level
                    $engagement_level = $visitor->engagement_level ?? 0;
                    $multiplier = (float) ($rule['multiplier'] ?? 1);
                    
                    if ($engagement_level > 0) {
                        $score = floor($score * $multiplier);
                    }
                    break;

                default:
                    // Unknown rule type - log warning
                    error_log(self::LOG_PREFIX . " Unknown scoring rule type: {$rule_type}");
                    break;
            }
        }

        return max(0, $score); // Ensure score is never negative
    }

    /**
     * Internal prospect creation logic
     * PHASE 2.2: Clean pipeline for cpd_visitors -> rtr_prospects
     * FIXED: Added return type and parameter type
     *
     * @param int|null $client_id Optional client filter.
     * @return array{created: int, updated: int, skipped: int, total: int} Results.
     */
    private function create_prospects_internal(?int $client_id = null): array {
        global $wpdb;

        $created = 0;
        $updated = 0;
        $skipped = 0;

        // Build WHERE clause for client filtering
        $where_client = '';
        if ($client_id) {
            $where_client = $wpdb->prepare('AND cs.client_id = %d', $client_id);
        }

        // Get eligible visitors
        $visitors = $wpdb->get_results("
            SELECT v.*, vc.campaign_id, cs.client_id
            FROM {$wpdb->prefix}cpd_visitors v
            INNER JOIN {$wpdb->prefix}cpd_visitor_campaigns vc ON v.id = vc.visitor_id
            INNER JOIN {$wpdb->prefix}dr_campaign_settings cs ON vc.campaign_id = cs.id
            WHERE v.email IS NOT NULL
            AND v.email != ''
            AND v.lead_score > 0
            {$where_client}
            ORDER BY v.lead_score DESC, v.last_seen_at DESC
        ");

        $this->log_job('prospect_creation_batch_start', sprintf(
            'Starting prospect creation/update for %d eligible visitors',
            count($visitors)
        ));

        foreach ($visitors as $visitor) {
            try {
                // Check if prospect already exists
                $existing = $wpdb->get_row($wpdb->prepare("
                    SELECT *
                    FROM {$wpdb->prefix}rtr_prospects
                    WHERE visitor_id = %d
                    AND campaign_id = %d
                    AND archived_at IS NULL
                    LIMIT 1
                ", $visitor->id, $visitor->campaign_id));

                if (!$existing) {
                    // Create new prospect
                    $thresholds = $this->get_room_thresholds($visitor->campaign_id);
                    $initial_room = $this->calculate_room_assignment($visitor->lead_score ?? 0, $thresholds);

                    $wpdb->insert(
                        $wpdb->prefix . 'rtr_prospects',
                        [
                            'visitor_id'              => $visitor->id,
                            'campaign_id'             => $visitor->campaign_id,
                            'client_id'               => $visitor->client_id,
                            'email'                   => $visitor->email,
                            'company'                 => $visitor->company ?? '',
                            'lead_score'              => $visitor->lead_score ?? 0,
                            'current_room'            => $initial_room,
                            'room_entered_at'         => current_time('mysql'),
                            'email_sequence_position' => 0,
                            'is_hot_list'             => 0,
                            'created_at'              => current_time('mysql'),
                            'updated_at'              => current_time('mysql'),
                        ],
                        ['%d', '%d', '%d', '%s', '%s', '%d', '%s', '%s', '%d', '%d', '%s', '%s']
                    );

                    $created++;

                    $this->log_job('prospect_created', sprintf(
                        'Created prospect for visitor %d (campaign: %d, room: %s, score: %d)',
                        $visitor->id,
                        $visitor->campaign_id,
                        $initial_room,
                        $visitor->lead_score ?? 0
                    ));

                } else {
                    // Update existing prospect if score changed significantly
                    $score_diff = abs(($visitor->lead_score ?? 0) - ($existing->lead_score ?? 0));
                    
                    if ($score_diff >= 5) {
                        $update_fields = [
                            'lead_score' => $visitor->lead_score ?? 0,
                        ];

                        // Calculate new room but don't apply yet - that's done by assign_rooms_internal()
                        $thresholds = $this->get_room_thresholds($visitor->campaign_id);
                        $new_room = $this->calculate_room_assignment(
                            $visitor->lead_score ?? 0,
                            $thresholds
                        );

                        $needs_update = ($new_room !== $existing->current_room);

                        if ($needs_update) {
                            $update_fields['updated_at'] = current_time('mysql');
                            
                            $wpdb->update(
                                $wpdb->prefix . 'rtr_prospects',
                                $update_fields,
                                ['id' => $existing->id],
                                array_fill(0, count($update_fields), '%s'),
                                ['%d']
                            );
                            
                            $updated++;
                            
                            $this->log_job(
                                'prospect_updated',
                                sprintf(
                                    'Updated prospect %d (visitor: %d, new score: %d)',
                                    $existing->id,
                                    $visitor->id,
                                    $visitor->lead_score
                                )
                            );
                        }
                    } else {
                        $skipped++;
                    }
                }

            } catch (\Exception $e) {
                $this->job_stats['errors']++;
                $this->log_job('prospect_creation_error', sprintf(
                    'Prospect creation failed for visitor %d: %s',
                    $visitor->id,
                    $e->getMessage()
                ), 'error');
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'total'   => count($visitors),
        ];
    }

    /**
     * Internal room assignment logic
     * PHASE 5.7: Immediate transitions, no delays
     * FIXED: Added return type
     *
     * @return array{transitions: int, delayed: int, total: int} Results.
     */
    private function assign_rooms_internal(): array {
        global $wpdb;

        $transitions = 0;
        $delayed = 0;

        // Get all active prospects
        $prospects = $wpdb->get_results("
            SELECT p.*, v.lead_score
            FROM {$wpdb->prefix}rtr_prospects p
            INNER JOIN {$wpdb->prefix}cpd_visitors v ON p.visitor_id = v.id
            WHERE p.archived_at IS NULL
            AND p.sales_handoff_at IS NULL
        ");

        $this->log_job('room_assignment_start', sprintf(
            'Starting room assignment for %d active prospects',
            count($prospects)
        ));

        foreach ($prospects as $prospect) {
            try {
                // Get thresholds for this campaign
                $thresholds = $this->get_room_thresholds($prospect->campaign_id);

                // Calculate what room they should be in
                $calculated_room = $this->calculate_room_assignment(
                    $prospect->lead_score ?? 0,
                    $thresholds
                );

                // Check if room needs to change
                $should_change = false;
                if ($prospect->current_room !== $calculated_room) {
                    // Phase 5.7: NO delays - allow all transitions immediately
                    $should_change = true;
                }

                // Apply room change if approved
                if ($should_change) {
                    $wpdb->update(
                        $wpdb->prefix . 'rtr_prospects',
                        [
                            'current_room'     => $calculated_room,
                            'room_entered_at'  => current_time('mysql'), // Reset entry time
                            'updated_at'       => current_time('mysql'),
                        ],
                        [
                            'id' => $prospect->id,
                        ],
                        ['%s', '%s', '%s'],
                        ['%d']
                    );

                    $this->log_room_transition(
                        $prospect->visitor_id,
                        $prospect->campaign_id,
                        $prospect->current_room,
                        $calculated_room,
                        'Automatic room assignment based on score'
                    );

                    $transitions++;
                }

            } catch (\Exception $e) {
                $this->job_stats['errors']++;
                
                // ENHANCEMENT: Better error context
                $this->log_job('room_assignment_prospect_error', sprintf(
                    'Room assignment failed for prospect %d: %s',
                    $prospect->id,
                    $e->getMessage()
                ), 'error');
            }
        }

        return [
            'transitions' => $transitions,
            'delayed'     => $delayed,
            'total'       => count($prospects),
        ];
    }

    /**
     * Check if room change is downward movement (score dropped)
     * FIXED: Uses class constant and added return type
     *
     * @param string $from_room Current room.
     * @param string $to_room   Target room.
     * @return bool True if downward movement.
     */
    private function is_downward_movement(string $from_room, string $to_room): bool {
        $from_level = self::ROOM_HIERARCHY[$from_room] ?? 0;
        $to_level = self::ROOM_HIERARCHY[$to_room] ?? 0;
        
        return $to_level < $from_level;
    }

    /**
     * Get room thresholds for a campaign
     * PHASE 2.3: Enhanced with validation, caching with TTL
     * FIXED: Added cache expiration and return type
     *
     * @param int $campaign_id Campaign ID.
     * @return array{problem_max: int, solution_max: int, offer_min: int} Thresholds.
     */
    private function get_room_thresholds(int $campaign_id): array {
        global $wpdb;

        // Get client_id from campaign
        $client_id = $wpdb->get_var($wpdb->prepare(
            "SELECT client_id FROM {$wpdb->prefix}dr_campaign_settings WHERE id = %d",
            $campaign_id
        ));

        // FIXED: Check cache with TTL
        if (isset($this->thresholds_cache[$client_id])) {
            $cached_at = $this->cache_timestamps['threshold_' . $client_id] ?? 0;
            if (time() - $cached_at < self::CACHE_TTL) {
                return $this->thresholds_cache[$client_id];
            }
        }

        // Try to get client-specific thresholds
        $thresholds = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}rtr_room_thresholds WHERE client_id = %d LIMIT 1",
            $client_id
        ));

        // Fall back to global defaults (client_id IS NULL)
        if (!$thresholds) {
            $thresholds = $wpdb->get_row(
                "SELECT * FROM {$wpdb->prefix}rtr_room_thresholds WHERE client_id IS NULL LIMIT 1"
            );
        }

        // Final fallback to hardcoded defaults
        if (!$thresholds) {
            $result = [
                'problem_max'  => 40,
                'solution_max' => 60,
                'offer_min'    => 61,
            ];
            
            // Cache and return hardcoded defaults
            $this->thresholds_cache[$client_id] = $result;
            $this->cache_timestamps['threshold_' . $client_id] = time();
            return $result;
        }

        // Extract threshold values
        $result = [
            'problem_max'  => (int) ($thresholds->problem_max ?? 40),
            'solution_max' => (int) ($thresholds->solution_max ?? 60),
            'offer_min'    => (int) ($thresholds->offer_min ?? 61),
        ];

        // PHASE 2.3: Validate threshold logic with comprehensive checks
        $validation = $this->validate_thresholds($result);
        
        if (!$validation['valid']) {
            // Log warning for invalid thresholds
            $this->log_job('threshold_validation_error', sprintf(
                'Invalid room thresholds for client %d: %s. Using hardcoded defaults.',
                $client_id,
                implode(', ', $validation['errors'])
            ), 'warning');

            // Fall back to hardcoded defaults
            $result = [
                'problem_max'  => 40,
                'solution_max' => 60,
                'offer_min'    => 61,
            ];
        }

        // Cache the result with timestamp
        $this->thresholds_cache[$client_id] = $result;
        $this->cache_timestamps['threshold_' . $client_id] = time();

        return $result;
    }

    /**
     * Validate room thresholds
     * FIXED: New comprehensive validation method
     *
     * @param array{problem_max: int, solution_max: int, offer_min: int} $thresholds Thresholds to validate.
     * @return array{valid: bool, errors: array<string>} Validation result.
     */
    private function validate_thresholds(array $thresholds): array {
        $errors = [];
        
        // Check for positive values
        foreach (['problem_max', 'solution_max', 'offer_min'] as $key) {
            if (!isset($thresholds[$key]) || $thresholds[$key] <= 0) {
                $errors[] = "{$key} must be positive";
            }
        }
        
        // Check proper ordering
        if ($thresholds['problem_max'] >= $thresholds['solution_max']) {
            $errors[] = "problem_max ({$thresholds['problem_max']}) must be less than solution_max ({$thresholds['solution_max']})";
        }
        
        if ($thresholds['solution_max'] >= $thresholds['offer_min']) {
            $errors[] = "solution_max ({$thresholds['solution_max']}) must be less than offer_min ({$thresholds['offer_min']})";
        }
        
        // Check reasonable values
        if ($thresholds['offer_min'] > 100) {
            $errors[] = "offer_min should not exceed 100 (got {$thresholds['offer_min']})";
        }
        
        // Check minimum gaps
        if (($thresholds['solution_max'] - $thresholds['problem_max']) < 5) {
            $errors[] = "Gap between problem and solution should be at least 5 points";
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Calculate room assignment based on score
     * PHASE 2.3: Verified correct logic
     * FIXED: Improved edge case handling and return type
     *
     * @param int   $lead_score Lead score (0-100+).
     * @param array{problem_max: int, solution_max: int, offer_min: int} $thresholds Room thresholds.
     * @return string Room assignment: 'none', 'problem', 'solution', or 'offer'.
     */
    private function calculate_room_assignment(int $lead_score, array $thresholds): string {
        // Handle negative or invalid scores
        if ($lead_score < 0) {
            error_log(self::LOG_PREFIX . " Invalid negative score: {$lead_score}");
            return 'none';
        }
        
        // FIXED: Improved logic to handle edge cases
        // Offer room: score >= offer_min (default: 61+)
        if ($lead_score >= $thresholds['offer_min']) {
            return 'offer';
        }

        // Solution room: score > problem_max and < offer_min (default: 41-60)
        if ($lead_score > $thresholds['problem_max']) {
            return 'solution';
        }

        // Problem room: score between 1 and problem_max (default: 1-40)
        if ($lead_score >= 1) {
            return 'problem';
        }

        // None room: score is 0
        return 'none';
    }

    /**
     * Log room transition
     * FIXED: Added return types
     *
     * @param int    $visitor_id  Visitor ID.
     * @param int    $campaign_id Campaign ID.
     * @param string $from_room   From room.
     * @param string $to_room     To room.
     * @param string $reason      Reason for transition.
     * @return void
     */
    private function log_room_transition(int $visitor_id, int $campaign_id, string $from_room, string $to_room, string $reason): void {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'rtr_room_progression',
            [
                'visitor_id'  => $visitor_id,
                'campaign_id' => $campaign_id,
                'from_room'   => $from_room,
                'to_room'     => $to_room,
                'reason'      => $reason,
                'created_at'  => current_time('mysql'),
            ],
            ['%d', '%d', '%s', '%s', '%s', '%s']
        );
    }

    /**
     * Log job action
     * ENHANCED: Better formatting and context
     * FIXED: Conditional error_log based on WP_DEBUG
     *
     * @param string $action_type Action type.
     * @param string $description Description.
     * @param string $level       Log level (info, warning, error).
     * @return void
     */
    private function log_job(string $action_type, string $description, string $level = 'info'): void {
        global $wpdb;

        // Log to action logs table
        $wpdb->insert(
            $wpdb->prefix . 'cpd_action_logs',
            [
                'user_id'     => 0, // System job
                'action_type' => 'nightly_job_' . $action_type,
                'description' => $description,
                'timestamp'   => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%s']
        );

        // FIXED: Only log to error_log for warnings/errors or if WP_DEBUG is true
        if ($level !== 'info' || (defined('WP_DEBUG') && WP_DEBUG)) {
            $prefix = self::LOG_PREFIX_JOB . ' ' . strtoupper($level) . ']';
            error_log("{$prefix} {$action_type}: {$description}");
        }
    }

    /**
     * Check API key authentication
     * FIXED: Added return type
     *
     * @param WP_REST_Request $request Request object.
     * @return bool|WP_Error True if authenticated, WP_Error otherwise.
     */
    public function check_api_key($request): bool|WP_Error {
        // Check for API key in header
        $api_key = $request->get_header('X-API-Key');

        if (empty($api_key)) {
            return new WP_Error(
                'missing_api_key',
                'API key is required',
                ['status' => 401]
            );
        }

        // Get stored API key from options
        $stored_key = get_option('cpd_api_key');

        if (empty($stored_key)) {
            // If no API key is configured, fall back to checking if user is admin
            return current_user_can('manage_options');
        }

        // Validate API key
        if ($api_key !== $stored_key) {
            return new WP_Error(
                'invalid_api_key',
                'Invalid API key',
                ['status' => 403]
            );
        }

        return true;
    }
}