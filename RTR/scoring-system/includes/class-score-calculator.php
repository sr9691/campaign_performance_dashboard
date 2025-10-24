<?php
/**
 * Score Calculator
 * 
 * Calculates lead scores based on firmographics, engagement, and purchase signals
 * Assigns prospects to Problem, Solution, or Offer rooms
 * Caches scores in wp_cpd_visitors table
 * 
 * @package DirectReach
 * @subpackage RTR_Scoring_System
 * @since 2.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class RTR_Score_Calculator {
    
    /**
     * WordPress database object
     * @var wpdb
     */
    private $wpdb;
    
    /**
     * Scoring rules database handler
     * @var RTR_Scoring_Rules_Database
     */
    private $rules_db;
    
    /**
     * Room thresholds database handler
     * @var RTR_Room_Thresholds_Database
     */
    private $thresholds_db;
    
    /**
     * Visitors table name
     * @var string
     */
    private $visitors_table;
    
    /**
     * Visitor activity table name
     * @var string
     */
    private $activity_table;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->visitors_table = $wpdb->prefix . 'cpd_visitors';
        $this->activity_table = $wpdb->prefix . 'cpd_visitor_activity';
        
        // Get database handlers
        $system = directreach_scoring_system();
        $this->rules_db = $system->get_scoring_rules_db();
        $this->thresholds_db = $system->get_room_thresholds_db();
    }
    
    // ===========================================
    // MAIN CALCULATION METHOD
    // ===========================================
    
    /**
     * Calculate score for a visitor
     * Main entry point for score calculation
     * 
     * @param int $visitor_id Visitor ID
     * @param int $client_id Client ID
     * @param bool $cache Whether to cache results (default true)
     * @return array|false Score data or false on error
     */
    public function calculate_score($visitor_id, $client_id, $cache = true) {
        // Load visitor data
        $visitor = $this->get_visitor_data($visitor_id);
        if (!$visitor) {
            return false;
        }
        
        // Load scoring rules for client
        $problem_rules = $this->rules_db->get_client_rules_for_room($client_id, 'problem');
        $solution_rules = $this->rules_db->get_client_rules_for_room($client_id, 'solution');
        $offer_rules = $this->rules_db->get_client_rules_for_room($client_id, 'offer');
        
        if ($problem_rules === false || $solution_rules === false || $offer_rules === false) {
            error_log("RTR Score Calculator: Failed to load rules for client {$client_id}");
            return false;
        }
        
        // Calculate scores for each room
        $problem_score = $this->calculate_problem_score($visitor, $problem_rules);
        $solution_score = $this->calculate_solution_score($visitor, $solution_rules);
        $offer_score = $this->calculate_offer_score($visitor, $offer_rules);
        
        // Check minimum threshold for Problem Room
        if (isset($problem_rules['minimum_threshold']['enabled']) && 
            $problem_rules['minimum_threshold']['enabled']) {
            $min_threshold = $problem_rules['minimum_threshold']['required_score'];
            if ($problem_score < $min_threshold) {
                // Visitor doesn't qualify - set all scores to 0
                $total_score = 0;
                $current_room = 'none';
                $breakdown = [
                    'problem' => 0,
                    'solution' => 0,
                    'offer' => 0,
                    'failed_minimum_threshold' => true,
                    'minimum_required' => $min_threshold,
                    'problem_score_achieved' => $problem_score
                ];
            } else {
                // Calculate total and assign room
                $total_score = $problem_score + $solution_score + $offer_score;
                $current_room = $this->assign_room($total_score, $client_id);
                $breakdown = [
                    'problem' => $problem_score,
                    'solution' => $solution_score,
                    'offer' => $offer_score
                ];
            }
        } else {
            // No minimum threshold check
            $total_score = $problem_score + $solution_score + $offer_score;
            $current_room = $this->assign_room($total_score, $client_id);
            $breakdown = [
                'problem' => $problem_score,
                'solution' => $solution_score,
                'offer' => $offer_score
            ];
        }
        
        $score_data = [
            'total_score' => $total_score,
            'current_room' => $current_room,
            'breakdown' => $breakdown,
            'calculated_at' => current_time('mysql')
        ];
        
        // Cache score if requested
        if ($cache) {
            $this->cache_score($visitor_id, $score_data);
        }
        
        return $score_data;
    }
    
    // ===========================================
    // PROBLEM ROOM SCORING (FIRMOGRAPHICS)
    // ===========================================
    
    /**
     * Calculate Problem Room score
     * Based on firmographics: revenue, company size, industry, location, visits, role
     * 
     * @param object $visitor Visitor data
     * @param array $rules Problem Room rules
     * @return int Score
     */
    private function calculate_problem_score($visitor, $rules) {
        $score = 0;
        
        // Revenue
        if (isset($rules['revenue']['enabled']) && $rules['revenue']['enabled']) {
            if ($this->matches_value($visitor->revenue, $rules['revenue']['values'])) {
                $score += (int)$rules['revenue']['points'];
            }
        }
        
        // Company Size
        if (isset($rules['company_size']['enabled']) && $rules['company_size']['enabled']) {
            if ($this->matches_value($visitor->company_size, $rules['company_size']['values'])) {
                $score += (int)$rules['company_size']['points'];
            }
        }
        
        // Industry Alignment
        if (isset($rules['industry_alignment']['enabled']) && $rules['industry_alignment']['enabled']) {
            if ($this->matches_industry($visitor->industry, $rules['industry_alignment']['values'])) {
                $score += (int)$rules['industry_alignment']['points'];
            }
        }
        
        // Target States
        if (isset($rules['target_states']['enabled']) && $rules['target_states']['enabled']) {
            if ($this->matches_value($visitor->state, $rules['target_states']['values'])) {
                $score += (int)$rules['target_states']['points'];
            }
        }
        
        // Multiple Visits
        if (isset($rules['multiple_visits']['enabled']) && $rules['multiple_visits']['enabled']) {
            $visit_count = $this->get_visitor_visit_count($visitor->id);
            $minimum_visits = (int)$rules['multiple_visits']['minimum_visits'];
            if ($visit_count >= $minimum_visits) {
                $score += (int)$rules['multiple_visits']['points'];
            }
        }
        
        // Role/Job Title Match
        if (isset($rules['role_match']['enabled']) && $rules['role_match']['enabled']) {
            if ($this->matches_role($visitor->job_title, $rules['role_match'])) {
                $score += (int)$rules['role_match']['points'];
            }
        }
        
        // Visited Target Pages (future implementation)
        // This requires wp_rtr_campaign_target_pages table (deferred to Iteration 7)
        
        return $score;
    }
    
    // ===========================================
    // SOLUTION ROOM SCORING (ENGAGEMENT)
    // ===========================================
    
    /**
     * Calculate Solution Room score
     * Based on engagement: email opens, clicks, page visits
     * 
     * @param object $visitor Visitor data
     * @param array $rules Solution Room rules
     * @return int Score
     */
    private function calculate_solution_score($visitor, $rules) {
        $score = 0;
        
        // Get engagement data
        $engagement = $this->get_visitor_engagement($visitor->id);
        
        // Email Opens
        if (isset($rules['email_open']['enabled']) && $rules['email_open']['enabled']) {
            $score += $engagement['email_opens'] * (int)$rules['email_open']['points'];
        }
        
        // Email Clicks
        if (isset($rules['email_click']['enabled']) && $rules['email_click']['enabled']) {
            $score += $engagement['email_clicks'] * (int)$rules['email_click']['points'];
        }
        
        // Email Multiple Clicks
        if (isset($rules['email_multiple_click']['enabled']) && $rules['email_multiple_click']['enabled']) {
            $minimum_clicks = (int)$rules['email_multiple_click']['minimum_clicks'];
            if ($engagement['email_clicks'] >= $minimum_clicks) {
                $score += (int)$rules['email_multiple_click']['points'];
            }
        }
        
        // Page Visits
        if (isset($rules['page_visit']['enabled']) && $rules['page_visit']['enabled']) {
            $points_per_visit = (int)$rules['page_visit']['points_per_visit'];
            $max_points = (int)$rules['page_visit']['max_points'];
            $visit_points = $engagement['page_visits'] * $points_per_visit;
            $score += min($visit_points, $max_points);
        }
        
        // Key Page Visits
        if (isset($rules['key_page_visit']['enabled']) && $rules['key_page_visit']['enabled']) {
            if ($this->visited_key_pages($visitor->id, $rules['key_page_visit']['key_pages'])) {
                $score += (int)$rules['key_page_visit']['points'];
            }
        }
        
        // Ad Engagement (UTM tracking)
        if (isset($rules['ad_engagement']['enabled']) && $rules['ad_engagement']['enabled']) {
            if ($this->has_ad_engagement($visitor->id, $rules['ad_engagement']['utm_sources'])) {
                $score += (int)$rules['ad_engagement']['points'];
            }
        }
        
        return $score;
    }
    
    // ===========================================
    // OFFER ROOM SCORING (PURCHASE SIGNALS)
    // ===========================================
    
    /**
     * Calculate Offer Room score
     * Based on purchase signals: demo requests, pricing visits, contact forms
     * 
     * @param object $visitor Visitor data
     * @param array $rules Offer Room rules
     * @return int Score
     */
    private function calculate_offer_score($visitor, $rules) {
        $score = 0;
        
        // Demo Request
        if (isset($rules['demo_request']['enabled']) && $rules['demo_request']['enabled']) {
            if ($this->detect_action($visitor->id, $rules['demo_request'])) {
                $score += (int)$rules['demo_request']['points'];
            }
        }
        
        // Contact Form
        if (isset($rules['contact_form']['enabled']) && $rules['contact_form']['enabled']) {
            if ($this->detect_action($visitor->id, $rules['contact_form'])) {
                $score += (int)$rules['contact_form']['points'];
            }
        }
        
        // Pricing Page Visit
        if (isset($rules['pricing_page']['enabled']) && $rules['pricing_page']['enabled']) {
            if ($this->visited_pages($visitor->id, $rules['pricing_page']['page_urls'])) {
                $score += (int)$rules['pricing_page']['points'];
            }
        }
        
        // Pricing Question
        if (isset($rules['pricing_question']['enabled']) && $rules['pricing_question']['enabled']) {
            if ($this->detect_action($visitor->id, $rules['pricing_question'])) {
                $score += (int)$rules['pricing_question']['points'];
            }
        }
        
        // Partner Referral
        if (isset($rules['partner_referral']['enabled']) && $rules['partner_referral']['enabled']) {
            if ($this->detect_action($visitor->id, $rules['partner_referral'])) {
                $score += (int)$rules['partner_referral']['points'];
            }
        }
        
        // Webinar Attendance (future)
        if (isset($rules['webinar_attendance']['enabled']) && $rules['webinar_attendance']['enabled']) {
            if ($this->detect_action($visitor->id, $rules['webinar_attendance'])) {
                $score += (int)$rules['webinar_attendance']['points'];
            }
        }
        
        return $score;
    }
    
    // ===========================================
    // ROOM ASSIGNMENT
    // ===========================================
    
    /**
     * Assign room based on total score and thresholds
     * 
     * @param int $total_score Total lead score
     * @param int $client_id Client ID
     * @return string Room type: 'none', 'problem', 'solution', 'offer'
     */
    private function assign_room($total_score, $client_id) {
        return $this->thresholds_db->get_room_for_score($total_score, $client_id);
    }
    
    // ===========================================
    // SCORE CACHING
    // ===========================================
    
    /**
     * Cache score in visitors table
     * Updates lead_score, current_room, score_calculated_at
     * 
     * @param int $visitor_id Visitor ID
     * @param array $score_data Score data to cache
     * @return bool Success
     */
    private function cache_score($visitor_id, $score_data) {
        $result = $this->wpdb->update(
            $this->visitors_table,
            [
                'lead_score' => $score_data['total_score'],
                'current_room' => $score_data['current_room'],
                'score_calculated_at' => $score_data['calculated_at']
            ],
            ['id' => $visitor_id],
            ['%d', '%s', '%s'],
            ['%d']
        );
        
        return $result !== false;
    }
    
    // ===========================================
    // DATA RETRIEVAL METHODS
    // ===========================================
    
    /**
     * Get visitor data from database
     * 
     * @param int $visitor_id Visitor ID
     * @return object|false Visitor data or false
     */
    private function get_visitor_data($visitor_id) {
        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->visitors_table} WHERE id = %d",
                $visitor_id
            )
        );
    }
    
    /**
     * Get visitor engagement data
     * Aggregates email opens, clicks, page visits
     * 
     * @param int $visitor_id Visitor ID
     * @return array Engagement metrics
     */
    private function get_visitor_engagement($visitor_id) {
        // Query activity table for engagement metrics
        $activity = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT 
                    COUNT(CASE WHEN activity_type = 'email_open' THEN 1 END) as email_opens,
                    COUNT(CASE WHEN activity_type = 'email_click' THEN 1 END) as email_clicks,
                    COUNT(CASE WHEN activity_type = 'page_visit' THEN 1 END) as page_visits
                 FROM {$this->activity_table}
                 WHERE visitor_id = %d",
                $visitor_id
            ),
            ARRAY_A
        );
        
        if (!$activity) {
            return [
                'email_opens' => 0,
                'email_clicks' => 0,
                'page_visits' => 0
            ];
        }
        
        return [
            'email_opens' => (int)$activity['email_opens'],
            'email_clicks' => (int)$activity['email_clicks'],
            'page_visits' => (int)$activity['page_visits']
        ];
    }
    
    /**
     * Get visitor page visit count
     * 
     * @param int $visitor_id Visitor ID
     * @return int Visit count
     */
    private function get_visitor_visit_count($visitor_id) {
        $count = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) 
                 FROM {$this->activity_table}
                 WHERE visitor_id = %d AND activity_type = 'page_visit'",
                $visitor_id
            )
        );
        
        return $count ? (int)$count : 0;
    }
    
    // ===========================================
    // MATCHING METHODS
    // ===========================================
    
    /**
     * Check if value matches any in the target values array
     * 
     * @param mixed $value Value to check
     * @param array $target_values Array of target values
     * @return bool Match found
     */
    private function matches_value($value, $target_values) {
        if (empty($value) || empty($target_values)) {
            return false;
        }
        
        return in_array($value, $target_values);
    }
    
    /**
     * Check if industry matches any target industry
     * Handles "Category|Sub-Category" format
     * 
     * @param string $industry Visitor's industry
     * @param array $target_industries Target industry strings
     * @return bool Match found
     */
    private function matches_industry($industry, $target_industries) {
        if (empty($industry) || empty($target_industries)) {
            return false;
        }
        
        // Exact match
        if (in_array($industry, $target_industries)) {
            return true;
        }
        
        // Check if industry matches category or sub-category
        foreach ($target_industries as $target) {
            if (strpos($target, '|') !== false) {
                // Target is "Category|Sub-Category"
                list($category, $subcategory) = explode('|', $target, 2);
                
                // Check if visitor industry matches either part
                if (strpos($industry, $category) !== false || 
                    strpos($industry, $subcategory) !== false) {
                    return true;
                }
            } else {
                // Target is just category, match broadly
                if (strpos($industry, $target) !== false) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Check if job title matches target roles
     * 
     * @param string $job_title Visitor's job title
     * @param array $role_config Role matching configuration
     * @return bool Match found
     */
    private function matches_role($job_title, $role_config) {
        if (empty($job_title) || empty($role_config['target_roles'])) {
            return false;
        }
        
        $job_title_lower = strtolower($job_title);
        $match_type = isset($role_config['match_type']) ? $role_config['match_type'] : 'contains';
        
        // Check all role categories
        foreach ($role_config['target_roles'] as $category => $keywords) {
            foreach ($keywords as $keyword) {
                $keyword_lower = strtolower($keyword);
                
                if ($match_type === 'contains') {
                    if (strpos($job_title_lower, $keyword_lower) !== false) {
                        return true;
                    }
                } else {
                    // Exact match
                    if ($job_title_lower === $keyword_lower) {
                        return true;
                    }
                }
            }
        }
        
        return false;
    }
    
    /**
     * Check if visitor visited any key pages
     * 
     * @param int $visitor_id Visitor ID
     * @param array $key_pages Array of key page URLs
     * @return bool Visited at least one key page
     */
    private function visited_key_pages($visitor_id, $key_pages) {
        if (empty($key_pages)) {
            return false;
        }
        
        foreach ($key_pages as $page) {
            $count = $this->wpdb->get_var(
                $this->wpdb->prepare(
                    "SELECT COUNT(*) 
                     FROM {$this->activity_table}
                     WHERE visitor_id = %d 
                     AND activity_type = 'page_visit'
                     AND page_url LIKE %s",
                    $visitor_id,
                    '%' . $this->wpdb->esc_like($page) . '%'
                )
            );
            
            if ($count > 0) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if visitor visited specific pages
     * 
     * @param int $visitor_id Visitor ID
     * @param array $page_urls Array of page URLs
     * @return bool Visited at least one page
     */
    private function visited_pages($visitor_id, $page_urls) {
        return $this->visited_key_pages($visitor_id, $page_urls);
    }
    
    /**
     * Check if visitor has ad engagement from specified sources
     * 
     * @param int $visitor_id Visitor ID
     * @param array $utm_sources Array of UTM source values
     * @return bool Has ad engagement
     */
    private function has_ad_engagement($visitor_id, $utm_sources) {
        if (empty($utm_sources)) {
            return false;
        }
        
        foreach ($utm_sources as $source) {
            $count = $this->wpdb->get_var(
                $this->wpdb->prepare(
                    "SELECT COUNT(*) 
                     FROM {$this->activity_table}
                     WHERE visitor_id = %d 
                     AND utm_source = %s",
                    $visitor_id,
                    $source
                )
            );
            
            if ($count > 0) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Detect action based on rule configuration
     * Supports URL patterns and UTM parameters
     * 
     * @param int $visitor_id Visitor ID
     * @param array $rule_config Rule configuration
     * @return bool Action detected
     */
    private function detect_action($visitor_id, $rule_config) {
        $detection_method = isset($rule_config['detection_method']) 
            ? $rule_config['detection_method'] 
            : 'url_pattern';
        
        if ($detection_method === 'url_pattern') {
            // Check URL patterns
            $patterns = isset($rule_config['patterns']) ? $rule_config['patterns'] : [];
            if (empty($patterns)) {
                return false;
            }
            
            foreach ($patterns as $pattern) {
                $count = $this->wpdb->get_var(
                    $this->wpdb->prepare(
                        "SELECT COUNT(*) 
                         FROM {$this->activity_table}
                         WHERE visitor_id = %d 
                         AND page_url LIKE %s",
                        $visitor_id,
                        '%' . $this->wpdb->esc_like($pattern) . '%'
                    )
                );
                
                if ($count > 0) {
                    return true;
                }
            }
            
        } elseif ($detection_method === 'utm_parameter') {
            // Check UTM content
            if (isset($rule_config['utm_content'])) {
                $count = $this->wpdb->get_var(
                    $this->wpdb->prepare(
                        "SELECT COUNT(*) 
                         FROM {$this->activity_table}
                         WHERE visitor_id = %d 
                         AND utm_content = %s",
                        $visitor_id,
                        $rule_config['utm_content']
                    )
                );
                
                if ($count > 0) {
                    return true;
                }
            }
            
        } elseif ($detection_method === 'utm_source') {
            // Check UTM source
            $utm_sources = isset($rule_config['utm_sources']) ? $rule_config['utm_sources'] : [];
            return $this->has_ad_engagement($visitor_id, $utm_sources);
        }
        
        return false;
    }
    
    // ===========================================
    // BULK OPERATIONS
    // ===========================================
    
    /**
     * Recalculate scores for all visitors of a client
     * 
     * @param int $client_id Client ID
     * @param int $limit Batch size (default 100)
     * @return array Results
     */
    public function recalculate_all($client_id, $limit = 100) {
        // Get all visitors for client
        $visitors = $this->wpdb->get_col(
            $this->wpdb->prepare(
                "SELECT id FROM {$this->visitors_table} WHERE client_id = %d LIMIT %d",
                $client_id,
                $limit
            )
        );
        
        if (empty($visitors)) {
            return [
                'success' => true,
                'processed' => 0,
                'message' => 'No visitors found'
            ];
        }
        
        $results = [
            'success' => true,
            'total' => count($visitors),
            'processed' => 0,
            'failed' => 0,
            'details' => []
        ];
        
        foreach ($visitors as $visitor_id) {
            $score_data = $this->calculate_score($visitor_id, $client_id);
            
            if ($score_data !== false) {
                $results['processed']++;
            } else {
                $results['failed']++;
            }
        }
        
        return $results;
    }
    
    /**
     * Get cached score for visitor
     * 
     * @param int $visitor_id Visitor ID
     * @return array|false Cached score data or false
     */
    public function get_cached_score($visitor_id) {
        $visitor = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT lead_score, current_room, score_calculated_at 
                 FROM {$this->visitors_table} 
                 WHERE id = %d",
                $visitor_id
            ),
            ARRAY_A
        );
        
        if (!$visitor) {
            return false;
        }
        
        return [
            'total_score' => (int)$visitor['lead_score'],
            'current_room' => $visitor['current_room'],
            'calculated_at' => $visitor['score_calculated_at']
        ];
    }
}

/**
 * Initialize Score Calculator
 * 
 * @return RTR_Score_Calculator
 */
function rtr_score_calculator() {
    static $instance = null;
    
    if ($instance === null) {
        $instance = new RTR_Score_Calculator();
    }
    
    return $instance;
}

// Initialize on plugins_loaded
add_action('plugins_loaded', 'rtr_score_calculator', 20);