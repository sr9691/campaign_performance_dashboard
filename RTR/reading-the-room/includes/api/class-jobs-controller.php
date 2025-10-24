<?php
/**
 * Jobs Controller for Nightly Processing
 * 
 * Handles endpoints for Make.com webhook processing
 * 
 * @package DirectReach
 * @subpackage ReadingTheRoom
 * @since 1.0.0
 */

namespace DirectReach\ReadingTheRoom\API;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Jobs_Controller extends \WP_REST_Controller {
    
    /**
     * Namespace
     */
    protected $namespace = 'directreach/rtr/v1';

    /**
     * Constructor
     */
    public function __construct() {
        // Load score calculator if not already loaded
        if (!class_exists('DirectReach\CampaignBuilder\Score_Calculator')) {
            $score_calc_path = CPD_DASHBOARD_PLUGIN_DIR . 'RTR/scoring-system/includes/class-score-calculator.php';
            if (file_exists($score_calc_path)) {
                require_once $score_calc_path;
            }
        }
    }

    /**
     * Register routes
     */
    public function register_routes() {
        // Single orchestrator endpoint
        register_rest_route($this->namespace, '/jobs/run-nightly', array(
            'methods' => 'POST',
            'callback' => array($this, 'run_nightly_job'),
            'permission_callback' => array($this, 'check_permission'),
            'args' => array(
                'mode' => array(
                    'required' => false,
                    'default' => 'incremental',
                    'enum' => array('incremental', 'full'),
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                'force_full' => array(
                    'required' => false,
                    'default' => false,
                    'type' => 'boolean'
                )
            )
        ));
    }

    /**
     * Check permissions - uses existing cpd_api_key
     */
    public function check_permission() {
        $stored_key = get_option('cpd_api_key', '');
        $provided_key = isset($_SERVER['HTTP_X_API_KEY']) ? $_SERVER['HTTP_X_API_KEY'] : '';
        
        if (empty($stored_key)) {
            $this->log_action('API_KEY_MISSING', 'Jobs API key not configured');
            return new \WP_Error('unauthorized', 'API key not configured', array('status' => 401));
        }
        
        if (!$provided_key || $provided_key !== $stored_key) {
            $this->log_action('API_AUTH_FAILED', 'Invalid API key for jobs endpoint. IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'N/A'));
            return new \WP_Error('unauthorized', 'Invalid API key', array('status' => 401));
        }
        
        return true;
    }

    /**
     * Main nightly job orchestrator
     * 
     * Executes in sequence:
     * 1. Calculate scores for all/changed visitors
     * 2. Assign rooms based on scores
     * 3. Create/update prospect records
     */
    public function run_nightly_job($request) {
        global $wpdb;
        
        $start_time = microtime(true);
        $mode = $request->get_param('mode');
        $force_full = $request->get_param('force_full');
        
        // Override mode if forced
        if ($force_full) {
            $mode = 'full';
        }
        
        $this->log_job_start('nightly_job', $mode);
        
        try {
            // Initialize stats
            $stats = array(
                'mode' => $mode,
                'visitors_processed' => 0,
                'scores_calculated' => 0,
                'prospects_created' => 0,
                'prospects_updated' => 0,
                'room_transitions' => 0,
                'errors' => array()
            );
            
            // Get visitors to process
            $visitors = $this->get_visitors_to_process($mode);
            $stats['visitors_processed'] = count($visitors);
            
            if (empty($visitors)) {
                $duration = round(microtime(true) - $start_time, 2);
                
                $this->log_job_complete('nightly_job', array_merge($stats, array(
                    'duration_seconds' => $duration
                )));
                
                return rest_ensure_response(array(
                    'success' => true,
                    'message' => 'No visitors to process',
                    'stats' => array_merge($stats, array('duration_seconds' => $duration))
                ));
            }
            
            // STEP 1: Calculate scores for all visitors
            $score_calculator_available = class_exists('DirectReach\CampaignBuilder\Score_Calculator');
            
            foreach ($visitors as $visitor) {
                try {
                    if ($score_calculator_available) {
                        // Use actual score calculator
                        $score_calculator = new \DirectReach\CampaignBuilder\Score_Calculator();
                        $score_result = $score_calculator->calculate_visitor_score(
                            $visitor->id,
                            $visitor->account_id
                        );
                        
                        if ($score_result['success']) {
                            $stats['scores_calculated']++;
                            $visitor->lead_score = $score_result['total_score'];
                            $visitor->current_room_from_score = $score_result['current_room'];
                        }
                    } else {
                        // Fallback: Use existing lead_score
                        $visitor->lead_score = $visitor->lead_score ?? 0;
                        $stats['scores_calculated']++;
                    }
                    
                } catch (\Exception $e) {
                    $stats['errors'][] = "Score calculation failed for visitor {$visitor->id}: {$e->getMessage()}";
                    error_log("RTR Jobs: Score calc error for visitor {$visitor->id}: {$e->getMessage()}");
                }
            }
            
            // STEP 2 & 3: For each visitor, assign room and create/update prospect
            foreach ($visitors as $visitor) {
                if (!isset($visitor->lead_score)) {
                    continue; // Skip if score calculation failed
                }
                
                try {
                    // Get client thresholds
                    $client_id = $this->get_client_id_for_account($visitor->account_id);
                    $thresholds = $this->get_room_thresholds($client_id);
                    
                    // Determine room based on score
                    $assigned_room = $this->assign_room($visitor->lead_score, $thresholds);
                    
                    // Only create prospect if they qualify (not 'none')
                    if ($assigned_room !== 'none') {
                        // Check if prospect exists
                        $existing_prospect = $this->get_existing_prospect($visitor->id);
                        
                        if ($existing_prospect) {
                            // Update existing prospect
                            $update_result = $this->update_prospect(
                                $existing_prospect,
                                $visitor,
                                $assigned_room
                            );
                            
                            if ($update_result['updated']) {
                                $stats['prospects_updated']++;
                            }
                            
                            if ($update_result['room_changed']) {
                                $stats['room_transitions']++;
                            }
                            
                        } else {
                            // Create new prospect (default campaign_id = 1 for now)
                            $created = $this->create_prospect($visitor, $assigned_room, 1);
                            
                            if ($created) {
                                $stats['prospects_created']++;
                            }
                        }
                    }
                    
                } catch (\Exception $e) {
                    $stats['errors'][] = "Prospect processing failed for visitor {$visitor->id}: {$e->getMessage()}";
                    error_log("RTR Jobs: Prospect error for visitor {$visitor->id}: {$e->getMessage()}");
                }
            }
            
            // Update last run tracking
            $duration = round(microtime(true) - $start_time, 2);
            $this->update_last_run($mode, 'success', array_merge($stats, array(
                'duration_seconds' => $duration
            )));
            
            $this->log_job_complete('nightly_job', array_merge($stats, array(
                'duration_seconds' => $duration
            )));
            
            // Determine overall status
            $status = 'success';
            if (!empty($stats['errors'])) {
                $status = 'partial_success';
            }
            
            return rest_ensure_response(array(
                'success' => true,
                'status' => $status,
                'message' => sprintf('Nightly job completed in %.2fs', $duration),
                'stats' => array_merge($stats, array('duration_seconds' => $duration))
            ));
            
        } catch (\Exception $e) {
            $duration = round(microtime(true) - $start_time, 2);
            
            $this->log_job_error('nightly_job', $e->getMessage());
            $this->update_last_run($mode, 'failed', array(
                'error' => $e->getMessage(),
                'duration_seconds' => $duration
            ));
            
            return new \WP_Error('job_failed', $e->getMessage(), array('status' => 500));
        }
    }

    /**
     * Get visitors to process based on mode
     */
    private function get_visitors_to_process($mode) {
        global $wpdb;
        
        $visitors_table = $wpdb->prefix . 'cpd_visitors';
        
        if ($mode === 'full') {
            // Get all active visitors
            return $wpdb->get_results(
                "SELECT * FROM {$visitors_table}
                WHERE is_archived = 0
                AND is_crm_added = 0
                ORDER BY last_seen_at DESC"
            );
        } else {
            // Incremental: Get visitors updated since last run
            $last_run = get_option('dr_jobs_last_run_timestamp');
            
            if (empty($last_run)) {
                // First run, process last 7 days
                $last_run = date('Y-m-d H:i:s', strtotime('-7 days'));
            }
            
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$visitors_table}
                WHERE is_archived = 0
                AND is_crm_added = 0
                AND last_seen_at >= %s
                ORDER BY last_seen_at DESC",
                $last_run
            ));
        }
    }

    /**
     * Assign room based on lead score and thresholds
     */
    private function assign_room($lead_score, $thresholds) {
        if ($lead_score >= $thresholds['offer_min']) {
            return 'offer';
        }
        
        if ($lead_score >= ($thresholds['problem_max'] + 1)) {
            return 'solution';
        }
        
        if ($lead_score >= 0) {
            return 'problem';
        }
        
        return 'none';
    }

    /**
     * Get room thresholds for client
     */
    private function get_room_thresholds($client_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'rtr_room_thresholds';
        
        // Try client-specific first
        if ($client_id) {
            $client_thresholds = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table} WHERE client_id = %d",
                $client_id
            ), ARRAY_A);
            
            if ($client_thresholds) {
                return $client_thresholds;
            }
        }
        
        // Fall back to global defaults
        $global_thresholds = $wpdb->get_row(
            "SELECT * FROM {$table} WHERE client_id IS NULL",
            ARRAY_A
        );
        
        if ($global_thresholds) {
            return $global_thresholds;
        }
        
        // Hard-coded fallback
        return array(
            'problem_max' => 40,
            'solution_max' => 60,
            'offer_min' => 61
        );
    }

    /**
     * Get client_id from account_id
     */
    private function get_client_id_for_account($account_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'cpd_clients';
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE account_id = %s",
            $account_id
        ));
    }

    /**
     * Get existing prospect record for visitor
     */
    private function get_existing_prospect($visitor_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'rtr_prospects';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table}
            WHERE visitor_id = %d
            AND archived_at IS NULL
            LIMIT 1",
            $visitor_id
        ));
    }

    /**
     * Create new prospect record
     */
    private function create_prospect($visitor, $assigned_room, $campaign_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'rtr_prospects';
        
        // Build engagement data from visitor
        $engagement_data = array(
            'recent_pages' => json_decode($visitor->recent_page_urls, true) ?: array(),
            'page_view_count' => $visitor->recent_page_count,
            'all_time_page_views' => $visitor->all_time_page_views,
            'last_seen_at' => $visitor->last_seen_at,
            'most_recent_referrer' => $visitor->most_recent_referrer
        );
        
        $result = $wpdb->insert(
            $table,
            array(
                'campaign_id' => $campaign_id,
                'visitor_id' => $visitor->id,
                'current_room' => $assigned_room,
                'company_name' => $visitor->company_name,
                'contact_name' => trim($visitor->first_name . ' ' . $visitor->last_name),
                'contact_email' => $visitor->email,
                'lead_score' => $visitor->lead_score,
                'days_in_room' => 0,
                'email_sequence_position' => 0,
                'urls_sent' => json_encode(array()),
                'engagement_data' => json_encode($engagement_data),
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ),
            array('%d', '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s')
        );
        
        if ($result === false) {
            error_log("RTR Jobs: Failed to create prospect for visitor {$visitor->id}: {$wpdb->last_error}");
            return false;
        }
        
        // Log room progression (entering room for first time)
        $this->log_room_progression(
            $visitor->id,
            $campaign_id,
            'none',
            $assigned_room,
            'Initial prospect creation',
            $visitor->lead_score
        );
        
        return true;
    }

    /**
     * Update existing prospect record
     */
    private function update_prospect($existing_prospect, $visitor, $new_room) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'rtr_prospects';
        
        $updated = false;
        $room_changed = false;
        
        // Check if score changed
        if ($visitor->lead_score != $existing_prospect->lead_score) {
            $updated = true;
        }
        
        // Check if room changed
        if ($new_room !== $existing_prospect->current_room) {
            $room_changed = true;
            $updated = true;
            
            // Log room transition
            $this->log_room_progression(
                $visitor->id,
                $existing_prospect->campaign_id,
                $existing_prospect->current_room,
                $new_room,
                'Automatic score-based transition',
                $visitor->lead_score
            );
        }
        
        if (!$updated) {
            return array('updated' => false, 'room_changed' => false);
        }
        
        // Update engagement data
        $engagement_data = array(
            'recent_pages' => json_decode($visitor->recent_page_urls, true) ?: array(),
            'page_view_count' => $visitor->recent_page_count,
            'all_time_page_views' => $visitor->all_time_page_views,
            'last_seen_at' => $visitor->last_seen_at,
            'most_recent_referrer' => $visitor->most_recent_referrer
        );
        
        // Update prospect
        $result = $wpdb->update(
            $table,
            array(
                'lead_score' => $visitor->lead_score,
                'current_room' => $new_room,
                'engagement_data' => json_encode($engagement_data),
                'updated_at' => current_time('mysql')
            ),
            array('id' => $existing_prospect->id),
            array('%d', '%s', '%s', '%s'),
            array('%d')
        );
        
        if ($result === false) {
            error_log("RTR Jobs: Failed to update prospect {$existing_prospect->id}: {$wpdb->last_error}");
        }
        
        return array(
            'updated' => $result !== false,
            'room_changed' => $room_changed
        );
    }

    /**
     * Log room progression/transition
     */
    private function log_room_progression($visitor_id, $campaign_id, $from_room, $to_room, $reason, $score = null) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'rtr_room_progression';
        
        // Check if campaign_id column is VARCHAR or INT
        $campaign_id_type = $wpdb->get_var($wpdb->prepare(
            "SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = %s 
            AND TABLE_NAME = %s 
            AND COLUMN_NAME = 'campaign_id'",
            DB_NAME,
            $table
        ));
        
        $campaign_format = (strtolower($campaign_id_type) === 'varchar') ? '%s' : '%d';
        
        $wpdb->insert(
            $table,
            array(
                'visitor_id' => $visitor_id,
                'campaign_id' => $campaign_id,
                'from_room' => $from_room,
                'to_room' => $to_room,
                'score_at_transition' => $score,
                'reason' => $reason,
                'transitioned_at' => current_time('mysql')
            ),
            array('%d', $campaign_format, '%s', '%s', '%d', '%s', '%s')
        );
    }

    /**
     * Update last run tracking
     */
    private function update_last_run($mode, $status, $stats) {
        update_option('dr_jobs_last_run_timestamp', current_time('mysql'));
        update_option('dr_jobs_last_run_type', $mode);
        update_option('dr_jobs_last_run_status', $status);
        update_option('dr_jobs_last_run_stats', json_encode($stats));
    }

    /**
     * Log job start
     */
    private function log_job_start($job_name, $mode = null) {
        $description = 'RTR Nightly Job Started';
        if ($mode) {
            $description .= " (mode: {$mode})";
        }
        
        $this->log_action('RTR_JOB_START', $description);
    }

    /**
     * Log job completion
     */
    private function log_job_complete($job_name, $stats) {
        $description = sprintf(
            'RTR Nightly Job Complete. Visitors: %d, Scores: %d, Created: %d, Updated: %d, Transitions: %d, Duration: %.2fs',
            $stats['visitors_processed'] ?? 0,
            $stats['scores_calculated'] ?? 0,
            $stats['prospects_created'] ?? 0,
            $stats['prospects_updated'] ?? 0,
            $stats['room_transitions'] ?? 0,
            $stats['duration_seconds'] ?? 0
        );
        
        if (!empty($stats['errors'])) {
            $description .= sprintf(' | Errors: %d', count($stats['errors']));
        }
        
        $this->log_action('RTR_JOB_COMPLETE', $description);
    }

    /**
     * Log job error
     */
    private function log_job_error($job_name, $error_message) {
        $this->log_action('RTR_JOB_ERROR', "RTR Nightly Job Failed: {$error_message}");
    }

    /**
     * Log action to wp_cpd_action_logs
     */
    private function log_action($action_type, $description) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'cpd_action_logs';
        
        $wpdb->insert(
            $table,
            array(
                'user_id' => 0, // System/API
                'action_type' => $action_type,
                'description' => $description,
                'timestamp' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s')
        );
    }
}