<?php
/**
 * RTR Score Calculator (FIXED)
 * 
 * Calculates visitor lead scores based on JSON-configured rules across three room types:
 * - Problem Room: Visitor qualification (company fit, role match, basic engagement)
 * - Solution Room: Engagement signals (emails, page visits, ad interaction)
 * - Offer Room: Intent signals (demo requests, pricing, contact forms)
 * 
 * FIXES in this version:
 * 1. key_page_visit - Now checks against rtr_room_content_links table per campaign
 * 2. ad_engagement - Now checks actual UTM parameters (campaign_source), not referrer string
 * 3. email_open/email_click - Now queries rtr_email_tracking table for real data
 * 
 * @package DirectReach_Reports
 * @subpackage RTR
 * @version 2.1.0
 */

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
     * Table names
     * @var array
     */
    private $tables = array();
    
    /**
     * Cached column existence checks
     * @var array
     */
    private static $column_cache = array();
    
    /**
     * Cached rules by client
     * @var array
     */
    private $rules_cache = array();
    
    /**
     * Cached content links by campaign
     * @var array
     */
    private $content_links_cache = array();
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        
        // Initialize table names
        $this->tables = array(
            'visitors'          => $wpdb->prefix . 'cpd_visitors',
            'prospects'         => $wpdb->prefix . 'rtr_prospects',
            'global_rules'      => $wpdb->prefix . 'rtr_global_scoring_rules',
            'client_rules'      => $wpdb->prefix . 'rtr_client_scoring_rules',
            'content_links'     => $wpdb->prefix . 'rtr_room_content_links',
            'thresholds'        => $wpdb->prefix . 'rtr_room_thresholds',
            'email_tracking'    => $wpdb->prefix . 'rtr_email_tracking',
            'visitor_campaigns' => $wpdb->prefix . 'cpd_visitor_campaigns',
            'action_logs'       => $wpdb->prefix . 'rtr_action_logs',
        );
    }

    /**
     * Calculate visitor score based on rules
     * 
     * @param int $visitor_id Visitor ID
     * @param int $client_id Client ID
     * @param bool $return_breakdown Whether to return detailed breakdown
     * @return array|false Array with total_score and component_scores, or false on error
     */
    public function calculate_visitor_score($visitor_id, $client_id, $return_breakdown = false) {
        // Get visitor data
        $visitor = $this->get_visitor_data($visitor_id);
        if (!$visitor) {
            error_log("RTR Score Calculator: Visitor {$visitor_id} not found");
            return false;
        }
        
        // Get campaign ID for this visitor (needed for content links lookup)
        $campaign_id = $this->get_visitor_campaign_id($visitor_id);
        
        // Load scoring rules
        $rules = $this->load_scoring_rules($client_id);
        if (!$rules) {
            error_log("RTR Score Calculator: No scoring rules found for client {$client_id}");
            return false;
        }
        
        // Get email tracking stats for this visitor
        $email_stats = $this->get_visitor_email_stats($visitor_id);
        
        // Calculate score for each room type
        $breakdown = array(
            'problem' => 0,
            'solution' => 0,
            'offer' => 0
        );

        $details = array();
        $disqualified = false;

        // Calculate problem score first - check for disqualification
        if ($return_breakdown) {
            $problem_result = $this->calculate_problem_score($visitor, $rules['problem'] ?? array(), $campaign_id, true);
            $breakdown['problem'] = $problem_result['score'];
            $details['problem'] = $problem_result['details'];
            $disqualified = !empty($problem_result['disqualified']);
        } else {
            $problem_result = $this->calculate_problem_score($visitor, $rules['problem'] ?? array(), $campaign_id, true);
            $breakdown['problem'] = $problem_result['score'];
            $disqualified = !empty($problem_result['disqualified']);
        }

        // If disqualified, skip remaining scoring and mark visitor
        if ($disqualified) {
            $total_score = $breakdown['problem']; // Will be negative
            $current_room = 'disqualified';
            
            // Update visitor with disqualified status
            $this->update_visitor_score($visitor_id, $total_score, $current_room, true);

            $result = array(
                'total_score' => $total_score,
                'breakdown' => $breakdown,
                'current_room' => $current_room,
                'disqualified' => true,
            );

            if ($return_breakdown && !empty($details)) {
                $result['details'] = $details;
            }

            return $result;
        }

        // Continue with solution and offer scoring for non-disqualified visitors
        if ($return_breakdown) {
            $solution_result = $this->calculate_solution_score($visitor, $rules['solution'] ?? array(), $campaign_id, $email_stats, true);
            $offer_result = $this->calculate_offer_score($visitor, $rules['offer'] ?? array(), $campaign_id, true);
            
            $breakdown['solution'] = $solution_result['score'];
            $breakdown['offer'] = $offer_result['score'];
            
            $details['solution'] = $solution_result['details'];
            $details['offer'] = $offer_result['details'];
        } else {
            $breakdown['solution'] = $this->calculate_solution_score($visitor, $rules['solution'] ?? array(), $campaign_id, $email_stats);
            $breakdown['offer'] = $this->calculate_offer_score($visitor, $rules['offer'] ?? array(), $campaign_id);
        }

        // Calculate total score
        $total_score = array_sum($breakdown);

        // Cap at 100
        $total_score = min($total_score, 100);

        $current_room = $this->determine_room($total_score, $client_id);
        
        // Update score - never archive, just store the threshold status
        $this->update_visitor_score($visitor_id, $total_score, $current_room, false);

        $result = array(
            'total_score' => $total_score,
            'breakdown' => $breakdown,
            'current_room' => $current_room,
            'disqualified' => false,
        );

        // Add details if requested
        if ($return_breakdown && !empty($details)) {
            $result['details'] = $details;
        }

        return $result;
    }
    
    /**
     * Get campaign ID for a visitor
     * 
     * @param int $visitor_id Visitor ID
     * @return int|null Campaign ID or null
     */
    private function get_visitor_campaign_id($visitor_id) {
        $campaign_id = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT campaign_id FROM {$this->tables['visitor_campaigns']} 
             WHERE visitor_id = %d 
             ORDER BY id DESC 
             LIMIT 1",
            $visitor_id
        ));
        
        return $campaign_id ? (int) $campaign_id : null;
    }
    
    /**
     * Get email tracking statistics for a visitor
     * 
     * FIX #3: Query actual email tracking data instead of hardcoded zeros
     * 
     * @param int $visitor_id Visitor ID
     * @return array Email stats (opens, clicks, etc.)
     */
    private function get_visitor_email_stats($visitor_id) {
        // Check if table exists
        $table_exists = $this->wpdb->get_var(
            "SHOW TABLES LIKE '{$this->tables['email_tracking']}'"
        );
        
        if (!$table_exists) {
            return array(
                'total_emails' => 0,
                'opened_count' => 0,
                'clicked_count' => 0,
            );
        }
        
        // Query email tracking for this visitor
        $stats = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT 
                COUNT(*) as total_emails,
                SUM(CASE WHEN status = 'opened' THEN 1 ELSE 0 END) as opened_count,
                SUM(CASE WHEN clicked_at IS NOT NULL THEN 1 ELSE 0 END) as clicked_count
             FROM {$this->tables['email_tracking']}
             WHERE visitor_id = %d",
            $visitor_id
        ), ARRAY_A);
        
        if (!$stats) {
            return array(
                'total_emails' => 0,
                'opened_count' => 0,
                'clicked_count' => 0,
            );
        }
        
        return array(
            'total_emails'  => (int) ($stats['total_emails'] ?? 0),
            'opened_count'  => (int) ($stats['opened_count'] ?? 0),
            'clicked_count' => (int) ($stats['clicked_count'] ?? 0),
        );
    }
    
    /**
     * Get content links for a campaign and room type
     * 
     * @param int $campaign_id Campaign ID
     * @param string $room_type Room type (problem, solution, offer)
     * @return array Array of link URLs
     */
    private function get_content_links($campaign_id, $room_type = null) {
        if (!$campaign_id) {
            return array();
        }
        
        $cache_key = $campaign_id . '_' . ($room_type ?? 'all');
        
        if (isset($this->content_links_cache[$cache_key])) {
            return $this->content_links_cache[$cache_key];
        }
        
        $where_room = '';
        if ($room_type) {
            $where_room = $this->wpdb->prepare(' AND room_type = %s', $room_type);
        }
        
        $links = $this->wpdb->get_col($this->wpdb->prepare(
            "SELECT link_url FROM {$this->tables['content_links']}
             WHERE campaign_id = %d
             AND is_active = 1
             {$where_room}",
            $campaign_id
        ));
        
        // Normalize URLs for comparison
        $normalized = array();
        foreach ($links as $url) {
            $normalized[] = $this->normalize_url($url);
        }
        
        $this->content_links_cache[$cache_key] = $normalized;
        
        return $normalized;
    }
    
    /**
     * Normalize URL for comparison
     * 
     * @param string $url URL to normalize
     * @return string Normalized URL (lowercase, no trailing slash, no query string)
     */
    private function normalize_url($url) {
        $url = strtolower(trim($url));
        
        // Parse URL
        $parsed = parse_url($url);
        if (!$parsed || empty($parsed['host'])) {
            return $url;
        }
        
        // Reconstruct without query string
        $normalized = ($parsed['scheme'] ?? 'https') . '://';
        $normalized .= $parsed['host'];
        $normalized .= rtrim($parsed['path'] ?? '/', '/');
        
        return $normalized;
    }
    
    /**
     * Load scoring rules for a client
     * 
     * @param int $client_id Client ID
     * @return array|false Array of rules by room type
     */
    private function load_scoring_rules($client_id) {
        // Check cache
        if (isset($this->rules_cache[$client_id])) {
            return $this->rules_cache[$client_id];
        }
        
        $rules = array();
        
        // Load global rules
        $global_rules = $this->wpdb->get_results(
            "SELECT room_type, rules_config FROM {$this->tables['global_rules']}",
            ARRAY_A
        );
        
        if ($global_rules) {
            foreach ($global_rules as $rule) {
                $rules[$rule['room_type']] = json_decode($rule['rules_config'], true);
            }
        }
        
        // Override with client-specific rules if they exist
        if ($client_id > 0) {
            $client_rules = $this->wpdb->get_results(
                $this->wpdb->prepare(
                    "SELECT room_type, rules_config FROM {$this->tables['client_rules']} WHERE client_id = %d",
                    $client_id
                ),
                ARRAY_A
            );
            
            if ($client_rules) {
                foreach ($client_rules as $rule) {
                    $rules[$rule['room_type']] = json_decode($rule['rules_config'], true);
                }
            }
        }
        
        // Cache results
        $this->rules_cache[$client_id] = $rules;
        
        return $rules;
    }
    
    /**
     * Get visitor data
     * 
     * @param int $visitor_id Visitor ID
     * @return object|false Visitor object or false
     */
    private function get_visitor_data($visitor_id) {
        $visitor = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->tables['visitors']} WHERE id = %d",
                $visitor_id
            )
        );
        
        return $visitor;
    }
    
    /**
     * Calculate problem room score (qualification)
     * 
     * @param object $visitor Visitor data
     * @param array $rules Problem room rules
     * @param int|null $campaign_id Campaign ID for content links
     * @param bool $return_details Whether to return details
     * @return int|array Score or array with score and details
     */
    private function calculate_problem_score($visitor, $rules, $campaign_id = null, $return_details = false) {
        $score = 0;
        $details = array(
            'revenue' => 0,
            'company_size' => 0,
            'industry_alignment' => 0,
            'industry_exclusion' => 0,
            'target_states' => 0,
            'visited_target_pages' => 0,
            'multiple_visits' => 0,
            'role_match' => 0,
            'disqualified' => false,
            'disqualified_reason' => ''
        );
        
        // INDUSTRY EXCLUSION CHECK - Must be first, skips all other scoring if matched
        if (!empty($rules['industry_alignment']['enabled']) && !empty($rules['industry_alignment']['excluded_values'])) {
            if ($this->check_industry_match($visitor->industry ?? '', $rules['industry_alignment']['excluded_values'])) {
                $exclusion_points = intval($rules['industry_alignment']['exclusion_points'] ?? -200);
                $score = $exclusion_points;
                
                if ($return_details) {
                    $details['industry_exclusion'] = $exclusion_points;
                    $details['disqualified'] = true;
                    $details['disqualified_reason'] = 'industry_excluded';
                    return array(
                        'score' => $score,
                        'details' => $details,
                        'disqualified' => true
                    );
                }
                
                return $score;
            }
        }
        
        // Revenue match
        if (!empty($rules['revenue']['enabled']) && !empty($rules['revenue']['values'])) {
            if ($this->check_revenue_match($visitor->estimated_revenue ?? '', $rules['revenue']['values'])) {
                $points = intval($rules['revenue']['points'] ?? 0);
                $score += $points;
                if ($return_details) {
                    $details['revenue'] = $points;
                }
            }
        }
        
        // Company size match
        if (!empty($rules['company_size']['enabled']) && !empty($rules['company_size']['values'])) {
            if ($this->check_company_size_match($visitor->estimated_employee_count ?? '', $rules['company_size']['values'])) {
                $points = intval($rules['company_size']['points'] ?? 0);
                $score += $points;
                if ($return_details) {
                    $details['company_size'] = $points;
                }
            }
        }
        
        // Industry alignment (positive match)
        if (!empty($rules['industry_alignment']['enabled']) && !empty($rules['industry_alignment']['values'])) {
            if ($this->check_industry_match($visitor->industry ?? '', $rules['industry_alignment']['values'])) {
                $points = intval($rules['industry_alignment']['points'] ?? 0);
                $score += $points;
                if ($return_details) {
                    $details['industry_alignment'] = $points;
                }
            }
        }
        
        // Target states
        if (!empty($rules['target_states']['enabled']) && !empty($rules['target_states']['values'])) {
            if ($this->check_state_match($visitor->state ?? '', $rules['target_states']['values'])) {
                $points = intval($rules['target_states']['points'] ?? 0);
                $score += $points;
                if ($return_details) {
                    $details['target_states'] = $points;
                }
            }
        }
        
        // Visited target pages - use content_links table
        if (!empty($rules['visited_target_pages']['enabled']) && $campaign_id) {
            $points = $this->calculate_content_link_score(
                $visitor->recent_page_urls ?? '',
                $campaign_id,
                'problem',
                $rules['visited_target_pages']
            );
            $score += $points;
            if ($return_details) {
                $details['visited_target_pages'] = $points;
            }
        }
        
        // Multiple visits
        if (!empty($rules['multiple_visits']['enabled'])) {
            $min_visits = intval($rules['multiple_visits']['minimum_visits'] ?? 2);
            if (intval($visitor->all_time_page_views ?? 0) >= $min_visits) {
                $points = intval($rules['multiple_visits']['points'] ?? 0);
                $score += $points;
                if ($return_details) {
                    $details['multiple_visits'] = $points;
                }
            }
        }
        
        // Role match
        if (!empty($rules['role_match']['enabled'])) {
            if ($this->check_role_match($visitor->job_title ?? '', $rules['role_match'])) {
                $points = intval($rules['role_match']['points'] ?? 0);
                $score += $points;
                if ($return_details) {
                    $details['role_match'] = $points;
                }
            }
        }
        
        if ($return_details) {
            return array(
                'score' => $score,
                'details' => $details,
                'disqualified' => false
            );
        }
        
        return $score;
    }
    
    /**
     * Calculate solution room score (engagement)
     * 
     * @param object $visitor Visitor data
     * @param array $rules Solution room rules
     * @param int|null $campaign_id Campaign ID for content links
     * @param array $email_stats Email tracking statistics
     * @param bool $return_details Whether to return details
     * @return int|array Score or array with score and details
     */
    private function calculate_solution_score($visitor, $rules, $campaign_id = null, $email_stats = array(), $return_details = false) {
        $score = 0;
        $details = array(
            'email_open' => 0,
            'email_click' => 0,
            'email_multiple_click' => 0,
            'page_visit' => 0,
            'key_page_visit' => 0,
            'ad_engagement' => 0
        );
        
        // Default email stats if not provided
        if (empty($email_stats)) {
            $email_stats = array(
                'opened_count' => 0,
                'clicked_count' => 0,
            );
        }
        
        // FIX #3: Email opens - use actual tracking data
        if (!empty($rules['email_open']['enabled'])) {
            $email_opens = (int) ($email_stats['opened_count'] ?? 0);
            $points_per_open = intval($rules['email_open']['points'] ?? 2);
            $max_points = intval($rules['email_open']['max_points'] ?? 10);
            $points = min($email_opens * $points_per_open, $max_points);
            $score += $points;
            if ($return_details) {
                $details['email_open'] = $points;
            }
        }
        
        // FIX #3: Email clicks - use actual tracking data
        if (!empty($rules['email_click']['enabled'])) {
            $email_clicks = (int) ($email_stats['clicked_count'] ?? 0);
            $points_per_click = intval($rules['email_click']['points'] ?? 5);
            $max_points = intval($rules['email_click']['max_points'] ?? 15);
            $points = min($email_clicks * $points_per_click, $max_points);
            $score += $points;
            if ($return_details) {
                $details['email_click'] = $points;
            }
        }
        
        // Email multiple clicks
        if (!empty($rules['email_multiple_click']['enabled'])) {
            $email_clicks = (int) ($email_stats['clicked_count'] ?? 0);
            $min_clicks = intval($rules['email_multiple_click']['minimum_clicks'] ?? 2);
            if ($email_clicks >= $min_clicks) {
                $points = intval($rules['email_multiple_click']['points'] ?? 0);
                $score += $points;
                if ($return_details) {
                    $details['email_multiple_click'] = $points;
                }
            }
        }
        
        // Page visits (general - based on page count)
        if (!empty($rules['page_visit']['enabled'])) {
            $page_count = intval($visitor->recent_page_count ?? 0);
            $points_per_visit = intval($rules['page_visit']['points_per_visit'] ?? 3);
            $max_points = intval($rules['page_visit']['max_points'] ?? 15);
            $points = min($page_count * $points_per_visit, $max_points);
            $score += $points;
            if ($return_details) {
                $details['page_visit'] = $points;
            }
        }
        
        // FIX #1: Key page visits - use content_links table for "solution" room
        if (!empty($rules['key_page_visit']['enabled']) && $campaign_id) {
            if ($this->check_content_link_visits($visitor->recent_page_urls ?? '', $campaign_id, 'solution')) {
                $points = intval($rules['key_page_visit']['points'] ?? 0);
                $score += $points;
                if ($return_details) {
                    $details['key_page_visit'] = $points;
                }
            }
        }
        
        // FIX #2: Ad engagement - check actual UTM parameters, not referrer string
        if (!empty($rules['ad_engagement']['enabled']) && !empty($rules['ad_engagement']['utm_sources'])) {
            if ($this->check_utm_source_match($visitor, $rules['ad_engagement']['utm_sources'])) {
                $points = intval($rules['ad_engagement']['points'] ?? 0);
                $score += $points;
                if ($return_details) {
                    $details['ad_engagement'] = $points;
                }
            }
        }
        
        if ($return_details) {
            return array(
                'score' => $score,
                'details' => $details
            );
        }
        
        return $score;
    }
    
    /**
     * Calculate offer room score (intent signals)
     * 
     * @param object $visitor Visitor data
     * @param array $rules Offer room rules
     * @param int|null $campaign_id Campaign ID for content links
     * @param bool $return_details Whether to return details
     * @return int|array Score or array with score and details
     */
    private function calculate_offer_score($visitor, $rules, $campaign_id = null, $return_details = false) {
        $score = 0;
        $details = array(
            'demo_request' => 0,
            'contact_form' => 0,
            'pricing_page' => 0,
            'pricing_question' => 0,
            'partner_referral' => 0,
            'webinar_attendance' => 0
        );
        
        // Demo request
        if (!empty($rules['demo_request']['enabled'])) {
            if ($this->check_demo_request($visitor, $rules['demo_request'])) {
                $points = intval($rules['demo_request']['points'] ?? 0);
                $score += $points;
                if ($return_details) {
                    $details['demo_request'] = $points;
                }
            }
        }
        
        // Contact form
        if (!empty($rules['contact_form']['enabled'])) {
            if ($this->check_contact_form_submission($visitor, $rules['contact_form'])) {
                $points = intval($rules['contact_form']['points'] ?? 0);
                $score += $points;
                if ($return_details) {
                    $details['contact_form'] = $points;
                }
            }
        }
        
        // Pricing page - use content_links for "offer" room
        if (!empty($rules['pricing_page']['enabled']) && $campaign_id) {
            if ($this->check_content_link_visits($visitor->recent_page_urls ?? '', $campaign_id, 'offer')) {
                $points = intval($rules['pricing_page']['points'] ?? 0);
                $score += $points;
                if ($return_details) {
                    $details['pricing_page'] = $points;
                }
            }
        }
        
        // Pricing question
        if (!empty($rules['pricing_question']['enabled'])) {
            if ($this->check_pricing_question($visitor, $rules['pricing_question'])) {
                $points = intval($rules['pricing_question']['points'] ?? 0);
                $score += $points;
                if ($return_details) {
                    $details['pricing_question'] = $points;
                }
            }
        }
        
        // Partner referral - check actual UTM source
        if (!empty($rules['partner_referral']['enabled']) && !empty($rules['partner_referral']['utm_sources'])) {
            if ($this->check_utm_source_match($visitor, $rules['partner_referral']['utm_sources'])) {
                $points = intval($rules['partner_referral']['points'] ?? 0);
                $score += $points;
                if ($return_details) {
                    $details['partner_referral'] = $points;
                }
            }
        }
        
        // Webinar attendance
        if (!empty($rules['webinar_attendance']['enabled'])) {
            // Implement webinar detection logic
            if ($return_details) {
                $details['webinar_attendance'] = 0;
            }
        }
        
        if ($return_details) {
            return array(
                'score' => $score,
                'details' => $details
            );
        }
        
        return $score;
    }
    
    /**
     * Calculate score based on content link visits
     * 
     * FIX #1: Use rtr_room_content_links table instead of hardcoded patterns
     * 
     * @param string $recent_urls JSON array of recent URLs
     * @param int $campaign_id Campaign ID
     * @param string $room_type Room type
     * @param array $rule_config Rule configuration
     * @return int Score
     */
    private function calculate_content_link_score($recent_urls, $campaign_id, $room_type, $rule_config) {
        $visitor_urls = $this->parse_recent_page_urls($recent_urls);
        if (empty($visitor_urls)) {
            return 0;
        }
        
        // Get content links for this campaign and room
        $content_links = $this->get_content_links($campaign_id, $room_type);
        if (empty($content_links)) {
            return 0;
        }
        
        // Count how many content links were visited
        $matches = 0;
        foreach ($visitor_urls as $visitor_url) {
            $normalized_visitor_url = $this->normalize_url($visitor_url);
            foreach ($content_links as $content_url) {
                if ($normalized_visitor_url === $content_url) {
                    $matches++;
                    break; // Only count each content link once
                }
            }
        }
        
        if ($matches === 0) {
            return 0;
        }
        
        // Calculate points based on matches
        $points_per_page = intval($rule_config['points'] ?? 10);
        $max_points = intval($rule_config['max_points'] ?? 30);
        
        return min($matches * $points_per_page, $max_points);
    }
    
    /**
     * Check if visitor visited any content links for a room
     * 
     * FIX #1: Use rtr_room_content_links table instead of hardcoded patterns
     * 
     * @param string $recent_urls JSON array of recent URLs
     * @param int $campaign_id Campaign ID
     * @param string $room_type Room type
     * @return bool True if any content link was visited
     */
    private function check_content_link_visits($recent_urls, $campaign_id, $room_type) {
        $visitor_urls = $this->parse_recent_page_urls($recent_urls);
        if (empty($visitor_urls)) {
            return false;
        }
        
        // Get content links for this campaign and room
        $content_links = $this->get_content_links($campaign_id, $room_type);
        if (empty($content_links)) {
            return false;
        }
        
        // Check if any visitor URL matches a content link
        foreach ($visitor_urls as $visitor_url) {
            $normalized_visitor_url = $this->normalize_url($visitor_url);
            foreach ($content_links as $content_url) {
                if ($normalized_visitor_url === $content_url) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Check if visitor has matching UTM source
     * 
     * FIX #2: Check actual UTM parameters, not referrer string matching
     * 
     * @param object $visitor Visitor data
     * @param array $utm_sources Target UTM sources
     * @return bool True if UTM source matches
     */
    private function check_utm_source_match($visitor, $utm_sources) {
        // Get the visitor's campaign_source (utm_source)
        $visitor_utm_source = strtolower(trim($visitor->campaign_source ?? ''));
        
        if (empty($visitor_utm_source)) {
            return false;
        }
        
        // Check against configured UTM sources
        foreach ($utm_sources as $source) {
            $source = strtolower(trim($source));
            if ($visitor_utm_source === $source) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Parse recent_page_urls JSON into array
     * 
     * @param string|array $recent_urls JSON string or array
     * @return array Array of URLs
     */
    private function parse_recent_page_urls($recent_urls) {
        if (empty($recent_urls)) {
            return array();
        }
        
        if (is_array($recent_urls)) {
            return array_filter($recent_urls, 'is_string');
        }
        
        if (!is_string($recent_urls)) {
            return array();
        }
        
        $decoded = json_decode($recent_urls, true);
        if (is_array($decoded)) {
            return array_filter($decoded, 'is_string');
        }
        
        // Maybe it's a single URL
        if (filter_var($recent_urls, FILTER_VALIDATE_URL)) {
            return array($recent_urls);
        }
        
        return array();
    }
    
    // =========================================================================
    // EXISTING HELPER METHODS (unchanged)
    // =========================================================================
    
    /**
     * Check if visitor revenue matches target values using numeric range comparison
     * 
     * Handles formats like:
     * - "$10M - $20M", "$5M - $10M", "$50M - $100M"
     * - "Over $100M", "Under $1M"
     * - "$1M - $5M", "$100K - $500K"
     * 
     * @param string $visitor_revenue Visitor's revenue string
     * @param array $target_values Array of target revenue ranges
     * @return bool True if visitor revenue overlaps with any target range
     */
    private function check_revenue_match($visitor_revenue, $target_values) {
        if (empty($visitor_revenue)) {
            return false;
        }
        
        $visitor_revenue = trim($visitor_revenue);
        
        // First try exact match (backwards compatibility)
        if (in_array($visitor_revenue, $target_values)) {
            return true;
        }
        
        // Parse visitor revenue into numeric range
        $visitor_range = $this->parse_revenue_range($visitor_revenue);
        if ($visitor_range === null) {
            // Fallback to string matching if parsing fails
            foreach ($target_values as $target) {
                if (stripos($visitor_revenue, $target) !== false || stripos($target, $visitor_revenue) !== false) {
                    return true;
                }
            }
            return false;
        }
        
        // Check if visitor range overlaps with any target range
        foreach ($target_values as $target) {
            $target_range = $this->parse_revenue_range($target);
            if ($target_range === null) {
                continue;
            }
            
            // Check for overlap: visitor_min <= target_max AND visitor_max >= target_min
            if ($visitor_range['min'] <= $target_range['max'] && $visitor_range['max'] >= $target_range['min']) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Parse a revenue string into a numeric range (in dollars)
     * 
     * @param string $revenue_str Revenue string to parse
     * @return array|null Array with 'min' and 'max' keys, or null if parsing fails
     */
    private function parse_revenue_range($revenue_str) {
        if (empty($revenue_str)) {
            return null;
        }
        
        $revenue_str = trim($revenue_str);
        
        // Handle "Over $X" format
        if (preg_match('/^over\s*\$?([\d.]+)\s*(k|m|b)?/i', $revenue_str, $matches)) {
            $value = $this->parse_revenue_value($matches[1], $matches[2] ?? '');
            return array('min' => $value, 'max' => PHP_FLOAT_MAX);
        }
        
        // Handle "Under $X" format
        if (preg_match('/^under\s*\$?([\d.]+)\s*(k|m|b)?/i', $revenue_str, $matches)) {
            $value = $this->parse_revenue_value($matches[1], $matches[2] ?? '');
            return array('min' => 0, 'max' => $value);
        }
        
        // Handle range format: "$10M - $20M" or "$10M-$20M" or "10M - 20M"
        if (preg_match('/\$?([\d.]+)\s*(k|m|b)?\s*[-–—to]+\s*\$?([\d.]+)\s*(k|m|b)?/i', $revenue_str, $matches)) {
            $min = $this->parse_revenue_value($matches[1], $matches[2] ?? '');
            $max = $this->parse_revenue_value($matches[3], $matches[4] ?? '');
            return array('min' => $min, 'max' => $max);
        }
        
        // Handle single value: "$10M" or "10M"
        if (preg_match('/\$?([\d.]+)\s*(k|m|b)?/i', $revenue_str, $matches)) {
            $value = $this->parse_revenue_value($matches[1], $matches[2] ?? '');
            // For single values, create a small range around the value
            return array('min' => $value * 0.9, 'max' => $value * 1.1);
        }
        
        return null;
    }
    
    /**
     * Convert a revenue number with suffix to actual dollar value
     * 
     * @param string $number The numeric part
     * @param string $suffix The suffix (K, M, B)
     * @return float The value in dollars
     */
    private function parse_revenue_value($number, $suffix) {
        $value = floatval($number);
        
        switch (strtoupper($suffix)) {
            case 'K':
                return $value * 1000;
            case 'M':
                return $value * 1000000;
            case 'B':
                return $value * 1000000000;
            default:
                // If no suffix, assume it's already in dollars
                // But if value is small (< 1000), it might be in millions
                if ($value < 1000) {
                    return $value * 1000000; // Assume millions
                }
                return $value;
        }
    }
    
    /**
     * Check if company size matches target values using numeric range comparison
     * 
     * Handles formats like:
     * - "21-50", "11-50", "51-200", "201-500"
     * - "1001-5000", "5000+", "10000+"
     * - "1-10", "Under 10"
     * 
     * @param string $company_size Visitor's company size string
     * @param array $target_values Array of target employee count ranges
     * @return bool True if visitor company size overlaps with any target range
     */
    private function check_company_size_match($company_size, $target_values) {
        if (empty($company_size)) {
            return false;
        }
        
        $company_size = trim($company_size);
        
        // First try exact match (backwards compatibility)
        if (in_array($company_size, $target_values)) {
            return true;
        }
        
        // Parse visitor company size into numeric range
        $visitor_range = $this->parse_employee_range($company_size);
        if ($visitor_range === null) {
            // Fallback to string matching if parsing fails
            foreach ($target_values as $target) {
                if (stripos($company_size, $target) !== false || stripos($target, $company_size) !== false) {
                    return true;
                }
            }
            return false;
        }
        
        // Check if visitor range overlaps with any target range
        foreach ($target_values as $target) {
            $target_range = $this->parse_employee_range($target);
            if ($target_range === null) {
                continue;
            }
            
            // Check for overlap: visitor_min <= target_max AND visitor_max >= target_min
            if ($visitor_range['min'] <= $target_range['max'] && $visitor_range['max'] >= $target_range['min']) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Parse an employee count string into a numeric range
     * 
     * @param string $size_str Employee count string to parse
     * @return array|null Array with 'min' and 'max' keys, or null if parsing fails
     */
    private function parse_employee_range($size_str) {
        if (empty($size_str)) {
            return null;
        }
        
        $size_str = trim($size_str);
        
        // Remove commas from numbers (e.g., "1,000" -> "1000")
        $size_str = str_replace(',', '', $size_str);
        
        // Handle "X+" format (e.g., "5000+", "10000+")
        if (preg_match('/^(\d+)\s*\+/', $size_str, $matches)) {
            return array('min' => (int) $matches[1], 'max' => PHP_INT_MAX);
        }
        
        // Handle "Over X" or "More than X" format
        if (preg_match('/^(?:over|more\s*than)\s*(\d+)/i', $size_str, $matches)) {
            return array('min' => (int) $matches[1], 'max' => PHP_INT_MAX);
        }
        
        // Handle "Under X" or "Less than X" format
        if (preg_match('/^(?:under|less\s*than)\s*(\d+)/i', $size_str, $matches)) {
            return array('min' => 0, 'max' => (int) $matches[1]);
        }
        
        // Handle range format: "11-50", "51-200", "1001-5000"
        if (preg_match('/^(\d+)\s*[-–—to]+\s*(\d+)/', $size_str, $matches)) {
            return array('min' => (int) $matches[1], 'max' => (int) $matches[2]);
        }
        
        // Handle single number
        if (preg_match('/^(\d+)$/', $size_str, $matches)) {
            $value = (int) $matches[1];
            return array('min' => $value, 'max' => $value);
        }
        
        return null;
    }
    
    /**
     * Check if industry matches target values
     */
    private function check_industry_match($industry, $target_values) {
        if (empty($industry)) {
            return false;
        }
        
        $industry = strtolower(trim($industry));
        
        foreach ($target_values as $target) {
            $target = strtolower(trim($target));
            if ($industry === $target) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if state matches target values
     */
    private function check_state_match($state, $target_values) {
        if (empty($state)) {
            return false;
        }
        
        $state = strtoupper(trim($state));
        $target_values = array_map('strtoupper', array_map('trim', $target_values));
        
        return in_array($state, $target_values);
    }
    
    /**
     * Check if job title matches target roles
     */
    private function check_role_match($job_title, $rule_config) {
        if (empty($job_title)) {
            return false;
        }
        
        $job_title = strtolower(trim($job_title));
        $target_roles = $rule_config['target_roles'] ?? array();
        $match_type = $rule_config['match_type'] ?? 'contains';
        
        foreach ($target_roles as $category => $roles) {
            if (!is_array($roles)) {
                continue;
            }
            
            foreach ($roles as $role) {
                $role = strtolower(trim($role));
                
                if ($match_type === 'exact') {
                    if ($job_title === $role) {
                        return true;
                    }
                } else {
                    if (stripos($job_title, $role) !== false) {
                        return true;
                    }
                }
            }
        }
        
        return false;
    }
    
    /**
     * Check for demo request
     */
    private function check_demo_request($visitor, $rule_config) {
        $detection_method = $rule_config['detection_method'] ?? 'url_pattern';
        
        if ($detection_method === 'utm_parameter') {
            $utm_content = $rule_config['utm_content'] ?? '';
            $visitor_content = strtolower($visitor->campaign_content ?? '');
            
            if (!empty($utm_content) && stripos($visitor_content, strtolower($utm_content)) !== false) {
                return true;
            }
        }
        
        // Check URLs for demo pages
        $urls = json_decode($visitor->recent_page_urls ?? '[]', true);
        $patterns = $rule_config['patterns'] ?? array('/demo', '/request');
        
        if (is_array($urls)) {
            foreach ($urls as $url) {
                foreach ($patterns as $pattern) {
                    if (stripos($url, $pattern) !== false) {
                        return true;
                    }
                }
            }
        }
        
        return false;
    }
    
    /**
     * Check for contact form submission
     */
    private function check_contact_form_submission($visitor, $rule_config) {
        $detection_method = $rule_config['detection_method'] ?? 'utm_parameter';
        
        if ($detection_method === 'utm_parameter') {
            $utm_content = $rule_config['utm_content'] ?? '';
            $visitor_content = strtolower($visitor->campaign_content ?? '');
            
            if (!empty($utm_content) && stripos($visitor_content, strtolower($utm_content)) !== false) {
                return true;
            }
        }
        
        // Check URLs for thank-you pages
        $urls = json_decode($visitor->recent_page_urls ?? '[]', true);
        if (is_array($urls)) {
            foreach ($urls as $url) {
                if (stripos($url, 'thank') !== false || stripos($url, 'confirmation') !== false) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Check for pricing question
     */
    private function check_pricing_question($visitor, $rule_config) {
        $detection_method = $rule_config['detection_method'] ?? 'utm_parameter';
        
        if ($detection_method === 'utm_parameter') {
            $utm_content = $rule_config['utm_content'] ?? '';
            $visitor_content = strtolower($visitor->campaign_content ?? '');
            
            if (!empty($utm_content) && stripos($visitor_content, strtolower($utm_content)) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Determine which room a visitor should be in based on score
     */
    private function determine_room($score, $client_id) {
        $thresholds = $this->get_room_thresholds($client_id);
        
        if ($score === 0) {
            return 'none';
        } elseif ($score <= $thresholds['problem_max']) {
            return 'problem';
        } elseif ($score <= $thresholds['solution_max']) {
            return 'solution';
        } else {
            return 'offer';
        }
    }
    
    /**
     * Get room thresholds for a client
     */
    private function get_room_thresholds($client_id) {
        $thresholds = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT problem_max, solution_max, offer_min FROM {$this->tables['thresholds']} WHERE client_id = %d",
                $client_id
            ),
            ARRAY_A
        );
        
        if (!$thresholds) {
            $thresholds = $this->wpdb->get_row(
                "SELECT problem_max, solution_max, offer_min FROM {$this->tables['thresholds']} WHERE client_id IS NULL LIMIT 1",
                ARRAY_A
            );
        }
        
        if (!$thresholds) {
            $thresholds = array(
                'problem_max' => 40,
                'solution_max' => 60,
                'offer_min' => 61
            );
        }
        
        return $thresholds;
    }
    
    /**
     * Update visitor score in database
     * 
     * @param int $visitor_id Visitor ID
     * @param int $score Lead score
     * @param string $current_room Current room assignment
     * @param bool $disqualified Whether visitor is disqualified
     * @return bool Success
     */
    private function update_visitor_score($visitor_id, $score, $current_room, $disqualified = false) {
        $result = $this->wpdb->update(
            $this->tables['visitors'],
            array(
                'lead_score' => $score,
                'current_room' => $current_room,
                'is_disqualified' => $disqualified ? 1 : 0,
                'score_calculated_at' => current_time('mysql')
            ),
            array('id' => $visitor_id),
            array('%d', '%s', '%d', '%s'),
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Batch calculate scores for multiple visitors
     */
    public function batch_calculate_scores($visitor_ids, $client_id, $cache_result = true) {
        $results = array();
        
        foreach ($visitor_ids as $visitor_id) {
            $results[$visitor_id] = $this->calculate_visitor_score($visitor_id, $client_id, $cache_result);
        }
        
        return $results;
    }
}