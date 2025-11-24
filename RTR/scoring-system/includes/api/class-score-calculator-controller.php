<?php
/**
 * RTR Score Calculator
 * 
 * Calculates visitor lead scores based on JSON-configured rules across three room types:
 * - Problem Room: Visitor qualification (company fit, role match, basic engagement)
 * - Solution Room: Engagement signals (emails, page visits, ad interaction)
 * - Offer Room: Intent signals (demo requests, pricing, contact forms)
 * 
 * @package DirectReach_Reports
 * @subpackage RTR
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
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        
        // Initialize table names
        $this->tables = array(
            'visitors' => $wpdb->prefix . 'cpd_visitors',
            'global_rules' => $wpdb->prefix . 'rtr_global_scoring_rules',
            'client_rules' => $wpdb->prefix . 'rtr_client_scoring_rules',
            'content_links' => $wpdb->prefix . 'rtr_room_content_links',
            'thresholds' => $wpdb->prefix . 'rtr_room_thresholds'
        );
    }
    
    /**
     * Calculate visitor score based on rules
     * 
     * IMPORTANT: ALL visitors get scored, including those without names/emails.
     * Anonymous visitors can still accumulate points based on:
     * - Company firmographics (revenue, size, industry)
     * - Geographic location
     * - Page visits and engagement
     * - Ad interaction
     * 
     * @param int $visitor_id Visitor ID
     * @param int $client_id Client ID
     * @param bool $return_breakdown Whether to include detailed score breakdown
     * @return array|false Array with total_score and component_scores, or false on error
     */
    public function calculate_visitor_score($visitor_id, $client_id, $return_breakdown = false) {
        // Get visitor data
        $visitor = $this->get_visitor_data($visitor_id);
        if (!$visitor) {
            error_log("RTR Score Calculator: Visitor {$visitor_id} not found");
            return false;
        }
        
        // Load scoring rules
        $rules = $this->load_scoring_rules($client_id);
        if (!$rules) {
            error_log("RTR Score Calculator: No scoring rules found for client {$client_id}");
            return false;
        }
        
        // Calculate score for each room type
        $breakdown = array(
            'problem' => 0,
            'solution' => 0,
            'offer' => 0
        );

        $details = array();

        // Calculate with detailed breakdown if requested
        if ($return_breakdown) {
            $problem_result = $this->calculate_problem_score($visitor, $rules['problem'] ?? array(), true);
            $solution_result = $this->calculate_solution_score($visitor, $rules['solution'] ?? array(), true);
            $offer_result = $this->calculate_offer_score($visitor, $rules['offer'] ?? array(), true);
            
            $breakdown['problem'] = $problem_result['score'];
            $breakdown['solution'] = $solution_result['score'];
            $breakdown['offer'] = $offer_result['score'];
            
            $details['problem'] = $problem_result['details'];
            $details['solution'] = $solution_result['details'];
            $details['offer'] = $offer_result['details'];
        } else {
            $breakdown['problem'] = $this->calculate_room_score($visitor, 'problem', $rules['problem'] ?? array());
            $breakdown['solution'] = $this->calculate_room_score($visitor, 'solution', $rules['solution'] ?? array());
            $breakdown['offer'] = $this->calculate_room_score($visitor, 'offer', $rules['offer'] ?? array());
        }

        // Calculate total score
        $total_score = array_sum($breakdown);

        // Cap at 100
        $total_score = min($total_score, 100);

        // Determine current room based on score and thresholds
        $current_room = $this->determine_room($total_score, $client_id);

        // Update database (always cache regardless of breakdown request)
        $this->update_visitor_score($visitor_id, $total_score, $current_room);

        $result = array(
            'total_score' => $total_score,
            'breakdown' => $breakdown,
            'current_room' => $current_room
        );

        // Add details if requested
        if ($return_breakdown && !empty($details)) {
            $result['details'] = $details;
        }

        return $result;
    }
    
    /**
     * Load scoring rules for a client
     * 
     * Implements proper merge logic:
     * - Starts with global rules as baseline
     * - Overlays client-specific rules on top
     * - Client rules override only the specific rules they customize, not entire room types
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
        
        // Step 1: Load global rules as baseline
        $global_rules = $this->wpdb->get_results(
            "SELECT room_type, rules_config FROM {$this->tables['global_rules']}",
            ARRAY_A
        );
        
        if ($global_rules) {
            foreach ($global_rules as $rule) {
                $decoded = json_decode($rule['rules_config'], true);
                if (is_array($decoded)) {
                    $rules[$rule['room_type']] = $decoded;
                }
            }
        }
        
        // Step 2: Merge client-specific rule overrides (only if client_id provided)
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
                    $room_type = $rule['room_type'];
                    $client_config = json_decode($rule['rules_config'], true);
                    
                    if (!is_array($client_config)) {
                        continue;
                    }
                    
                    // Merge client rules into global rules at the individual rule level
                    if (!isset($rules[$room_type])) {
                        // No global rules for this room type, use client rules entirely
                        $rules[$room_type] = $client_config;
                    } else {
                        // Merge: client rules override global rules for specific criteria
                        foreach ($client_config as $rule_key => $rule_value) {
                            if (is_array($rule_value) && isset($rule_value['enabled'])) {
                                // This is a scoring rule - merge it
                                $rules[$room_type][$rule_key] = $rule_value;
                            }
                        }
                    }
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
     * Calculate score for a specific room type
     * 
     * @param object $visitor Visitor data
     * @param string $room_type Room type (problem, solution, offer)
     * @param array $rules Rules configuration
     * @return int Score for this room
     */
    private function calculate_room_score($visitor, $room_type, $rules) {
        if (empty($rules)) {
            return 0;
        }
        
        $score = 0;
        
        switch ($room_type) {
            case 'problem':
                $score = $this->calculate_problem_score($visitor, $rules);
                break;
                
            case 'solution':
                $score = $this->calculate_solution_score($visitor, $rules);
                break;
                
            case 'offer':
                $score = $this->calculate_offer_score($visitor, $rules);
                break;
        }
        
        return $score;
    }
    
    /**
     * Calculate problem room score (qualification)
     * 
     * @param object $visitor Visitor data
     * @param array $rules Problem room rules
     * @return int Score
     */
    private function calculate_problem_score($visitor, $rules, $return_details = false) {
        $score = 0;
        $details = array(
            'revenue' => 0,
            'company_size' => 0,
            'industry_alignment' => 0,
            'target_states' => 0,
            'visited_target_pages' => 0,
            'multiple_visits' => 0,
            'role_match' => 0
        );
        
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
        
        // Industry alignment
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
        
        // Visited target pages
        if (!empty($rules['visited_target_pages']['enabled'])) {
            $points = $this->calculate_target_page_score(
                $visitor->recent_page_urls ?? '',
                $visitor->client_id ?? 0,
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
                'details' => $details
            );
        }
        
        return $score;
    }
    
    /**
     * Calculate solution room score (engagement)
     * 
     * @param object $visitor Visitor data
     * @param array $rules Solution room rules
     * @param bool $return_details Whether to return detailed breakdown
     * @return int|array Score or array with score and details
     */
    private function calculate_solution_score($visitor, $rules, $return_details = false) {
        $score = 0;
        $details = array(
            'email_open' => 0,
            'email_click' => 0,
            'email_multiple_click' => 0,
            'page_visit' => 0,
            'key_page_visit' => 0,
            'ad_engagement' => 0
        );
        
        // Get email tracking stats if available
        $email_stats = $this->get_visitor_email_stats($visitor->id ?? 0);
        
        // Email opens
        if (!empty($rules['email_open']['enabled'])) {
            $email_opens = $email_stats['opens'] ?? 0;
            $points = $email_opens * intval($rules['email_open']['points'] ?? 0);
            $score += $points;
            if ($return_details) {
                $details['email_open'] = $points;
            }
        }
        
        // Email clicks
        if (!empty($rules['email_click']['enabled'])) {
            $email_clicks = $email_stats['clicks'] ?? 0;
            $points = $email_clicks * intval($rules['email_click']['points'] ?? 0);
            $score += $points;
            if ($return_details) {
                $details['email_click'] = $points;
            }
        }
        
        // Email multiple clicks
        if (!empty($rules['email_multiple_click']['enabled'])) {
            $email_clicks = $email_stats['clicks'] ?? 0;
            $min_clicks = intval($rules['email_multiple_click']['minimum_clicks'] ?? 2);
            if ($email_clicks >= $min_clicks) {
                $points = intval($rules['email_multiple_click']['points'] ?? 0);
                $score += $points;
                if ($return_details) {
                    $details['email_multiple_click'] = $points;
                }
            }
        }
        
        // Page visits
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
        
        // Key page visits
        if (!empty($rules['key_page_visit']['enabled']) && !empty($rules['key_page_visit']['key_pages'])) {
            if ($this->check_key_page_visits($visitor->recent_page_urls ?? '', $rules['key_page_visit']['key_pages'])) {
                $points = intval($rules['key_page_visit']['points'] ?? 0);
                $score += $points;
                if ($return_details) {
                    $details['key_page_visit'] = $points;
                }
            }
        }
        
        // Ad engagement
        if (!empty($rules['ad_engagement']['enabled']) && !empty($rules['ad_engagement']['utm_sources'])) {
            if ($this->check_ad_engagement($visitor->most_recent_referrer ?? '', $rules['ad_engagement']['utm_sources'])) {
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
     * @return int Score
     */
    private function calculate_offer_score($visitor, $rules, $return_details = false) {
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
        
        // Pricing page
        if (!empty($rules['pricing_page']['enabled']) && !empty($rules['pricing_page']['page_urls'])) {
            if ($this->check_pricing_page_visit($visitor->recent_page_urls ?? '', $rules['pricing_page']['page_urls'])) {
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
        
        // Partner referral
        if (!empty($rules['partner_referral']['enabled']) && !empty($rules['partner_referral']['utm_sources'])) {
            if ($this->check_partner_referral($visitor->most_recent_referrer ?? '', $rules['partner_referral']['utm_sources'])) {
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
     * Check if visitor revenue matches target values
     * 
     * @param string $visitor_revenue Visitor's estimated revenue
     * @param array $target_values Target revenue ranges
     * @return bool True if matches
     */
    private function check_revenue_match($visitor_revenue, $target_values) {
        if (empty($visitor_revenue)) {
            return false;
        }
        
        // Normalize the visitor revenue
        $visitor_revenue = trim($visitor_revenue);
        
        // Check for exact match first
        if (in_array($visitor_revenue, $target_values)) {
            return true;
        }
        
        // Check for "Above $50M" matching "$100M+"
        if (stripos($visitor_revenue, 'above') !== false || stripos($visitor_revenue, '50m') !== false) {
            foreach ($target_values as $value) {
                if (stripos($value, '100m+') !== false || stripos($value, '50m') !== false) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Check if visitor company size matches target values
     * 
     * @param string $visitor_size Visitor's estimated employee count
     * @param array $target_values Target size ranges
     * @return bool True if matches
     */
    private function check_company_size_match($visitor_size, $target_values) {
        if (empty($visitor_size)) {
            return false;
        }
        
        $visitor_size = trim($visitor_size);
        
        // Check for exact match
        if (in_array($visitor_size, $target_values)) {
            return true;
        }
        
        // Check for "5001+" matching "1000+"
        if (stripos($visitor_size, '5001+') !== false || stripos($visitor_size, '5000+') !== false) {
            foreach ($target_values as $value) {
                if (stripos($value, '1000+') !== false) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Check if visitor industry matches target industries
     * 
     * @param string $visitor_industry Visitor's industry
     * @param array $target_industries Target industries (may include pipe-separated values)
     * @return bool True if matches
     */
    private function check_industry_match($visitor_industry, $target_industries) {
        if (empty($visitor_industry)) {
            return false;
        }
        
        $visitor_industry = strtolower(trim($visitor_industry));
        
        foreach ($target_industries as $target) {
            // Split pipe-separated values
            $industries = explode('|', $target);
            foreach ($industries as $industry) {
                $industry = strtolower(trim($industry));
                if (stripos($visitor_industry, $industry) !== false) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Check if visitor state matches target states
     * 
     * @param string $visitor_state Visitor's state
     * @param array $target_states Target state codes
     * @return bool True if matches
     */
    private function check_state_match($visitor_state, $target_states) {
        if (empty($visitor_state) || empty($target_states)) {
            return false;
        }
        
        return in_array(strtoupper(trim($visitor_state)), array_map('strtoupper', $target_states));
    }
    
    /**
     * Check if visitor job title matches target roles
     * 
     * @param string $visitor_title Visitor's job title
     * @param array $rule_config Role match configuration
     * @return bool True if matches
     */
    private function check_role_match($visitor_title, $rule_config) {
        if (empty($visitor_title) || empty($rule_config['target_roles'])) {
            return false;
        }
        
        $visitor_title = strtolower($visitor_title);
        $match_type = $rule_config['match_type'] ?? 'contains';
        
        // Check all role categories
        foreach ($rule_config['target_roles'] as $category => $keywords) {
            foreach ($keywords as $keyword) {
                $keyword = strtolower($keyword);
                
                if ($match_type === 'contains') {
                    if (stripos($visitor_title, $keyword) !== false) {
                        return true;
                    }
                } else {
                    // Exact match
                    if ($visitor_title === $keyword) {
                        return true;
                    }
                }
            }
        }
        
        return false;
    }
    
    /**
     * Calculate score for target page visits
     * 
     * @param string $recent_urls JSON array of recent URLs
     * @param int $client_id Client ID
     * @param string $room_type Room type
     * @param array $rule_config Page visit rule configuration
     * @return int Score
     */
    private function calculate_target_page_score($recent_urls, $client_id, $room_type, $rule_config) {
        $urls = json_decode($recent_urls, true);
        if (!is_array($urls) || empty($urls)) {
            return 0;
        }
        
        // This would ideally check against rtr_room_content_links
        // For now, award points per URL visited, capped at max_points
        $points_per_page = intval($rule_config['points'] ?? 10);
        $max_points = intval($rule_config['max_points'] ?? 30);
        
        $score = min(count($urls) * $points_per_page, $max_points);
        
        return $score;
    }
    
    /**
     * Check if visitor visited key pages
     * 
     * @param string $recent_urls JSON array of recent URLs
     * @param array $key_pages Array of key page patterns
     * @return bool True if any key page was visited
     */
    private function check_key_page_visits($recent_urls, $key_pages) {
        $urls = json_decode($recent_urls, true);
        if (!is_array($urls) || empty($urls)) {
            return false;
        }
        
        foreach ($urls as $url) {
            foreach ($key_pages as $pattern) {
                if ($this->url_matches_pattern($url, $pattern)) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Check if visitor engaged with ads
     * 
     * @param string $referrer Most recent referrer
     * @param array $utm_sources Target UTM sources
     * @return bool True if ad engagement detected
     */
    private function check_ad_engagement($referrer, $utm_sources) {
        if (empty($referrer)) {
            return false;
        }
        
        $referrer = strtolower($referrer);
        
        foreach ($utm_sources as $source) {
            $source = strtolower($source);
            if (stripos($referrer, $source) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check for contact form submission
     * 
     * @param object $visitor Visitor data
     * @param array $rule_config Contact form rule configuration
     * @return bool True if form submission detected
     */
    private function check_contact_form_submission($visitor, $rule_config) {
        $detection_method = $rule_config['detection_method'] ?? 'utm_parameter';
        
        if ($detection_method === 'utm_parameter') {
            $utm_content = $rule_config['utm_content'] ?? '';
            $referrer = strtolower($visitor->most_recent_referrer ?? '');
            
            if (!empty($utm_content) && stripos($referrer, strtolower($utm_content)) !== false) {
                return true;
            }
        }
        
        // Could also check URLs for thank-you pages
        $urls = json_decode($visitor->recent_page_urls ?? '[]', true);
        if (is_array($urls)) {
            foreach ($urls as $url) {
                if (stripos($url, 'thank') !== false || stripos($url, 'contact') !== false) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Check for demo request
     * 
     * @param object $visitor Visitor data
     * @param array $rule_config Demo request rule configuration
     * @return bool True if demo request detected
     */
    private function check_demo_request($visitor, $rule_config) {
        $detection_method = $rule_config['detection_method'] ?? 'utm_parameter';
        
        if ($detection_method === 'utm_parameter') {
            $utm_content = $rule_config['utm_content'] ?? '';
            $referrer = strtolower($visitor->most_recent_referrer ?? '');
            
            if (!empty($utm_content) && stripos($referrer, strtolower($utm_content)) !== false) {
                return true;
            }
        }
        
        // Check URLs for demo pages
        $urls = json_decode($visitor->recent_page_urls ?? '[]', true);
        if (is_array($urls)) {
            foreach ($urls as $url) {
                if (stripos($url, 'demo') !== false || stripos($url, 'request') !== false) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Check for pricing page visit
     * 
     * @param string $recent_urls JSON array of recent URLs
     * @param array $page_urls Array of pricing page patterns
     * @return bool True if pricing page was visited
     */
    private function check_pricing_page_visit($recent_urls, $page_urls) {
        $urls = json_decode($recent_urls, true);
        if (!is_array($urls) || empty($urls)) {
            return false;
        }
        
        foreach ($urls as $url) {
            foreach ($page_urls as $pattern) {
                if ($this->url_matches_pattern($url, $pattern)) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Check for pricing question
     * 
     * @param object $visitor Visitor data
     * @param array $rule_config Pricing question rule configuration
     * @return bool True if pricing question detected
     */
    private function check_pricing_question($visitor, $rule_config) {
        $detection_method = $rule_config['detection_method'] ?? 'utm_parameter';
        
        if ($detection_method === 'utm_parameter') {
            $utm_content = $rule_config['utm_content'] ?? '';
            $referrer = strtolower($visitor->most_recent_referrer ?? '');
            
            if (!empty($utm_content) && stripos($referrer, strtolower($utm_content)) !== false) {
                return true;
            }
        }
        
        // Check URLs for pricing-related pages
        $urls = json_decode($visitor->recent_page_urls ?? '[]', true);
        if (is_array($urls)) {
            foreach ($urls as $url) {
                if (stripos($url, 'pricing') !== false || stripos($url, 'quote') !== false) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Check for partner referral
     * 
     * @param string $referrer Most recent referrer
     * @param array $utm_sources Partner UTM sources
     * @return bool True if partner referral detected
     */
    private function check_partner_referral($referrer, $utm_sources) {
        if (empty($referrer)) {
            return false;
        }
        
        $referrer = strtolower($referrer);
        
        foreach ($utm_sources as $source) {
            if (stripos($referrer, strtolower($source)) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if URL matches a pattern
     * 
     * @param string $url URL to check
     * @param string $pattern Pattern (may contain regex or simple string)
     * @return bool True if matches
     */
    private function url_matches_pattern($url, $pattern) {
        $url = strtolower($url);
        $pattern = strtolower(str_replace('\\/', '/', $pattern)); // Handle escaped slashes from JSON
        
        // Simple contains check
        if (stripos($url, $pattern) !== false) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Determine which room a visitor should be in based on score
     * 
     * @param int $score Total score
     * @param int $client_id Client ID
     * @return string Room name (none, problem, solution, offer)
     */
    private function determine_room($score, $client_id) {
        // Get thresholds for this client
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
     * 
     * @param int $client_id Client ID
     * @return array Thresholds
     */
    private function get_room_thresholds($client_id) {
        // Try to get client-specific thresholds
        $thresholds = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT problem_max, solution_max, offer_min FROM {$this->tables['thresholds']} WHERE client_id = %d",
                $client_id
            ),
            ARRAY_A
        );
        
        // Fall back to global thresholds if no client-specific ones
        if (!$thresholds) {
            $thresholds = $this->wpdb->get_row(
                "SELECT problem_max, solution_max, offer_min FROM {$this->tables['thresholds']} WHERE client_id IS NULL LIMIT 1",
                ARRAY_A
            );
        }
        
        // Default thresholds if none found
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
     * @param int $score Total score
     * @param string $current_room Current room assignment
     * @return bool Success
     */
    private function update_visitor_score($visitor_id, $score, $current_room) {
        $result = $this->wpdb->update(
            $this->tables['visitors'],
            array(
                'lead_score' => $score,
                'current_room' => $current_room,
                'score_calculated_at' => current_time('mysql')
            ),
            array('id' => $visitor_id),
            array('%d', '%s', '%s'),
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Batch calculate scores for multiple visitors
     * 
     * @param array $visitor_ids Array of visitor IDs
     * @param int $client_id Client ID
     * @param bool $cache_result Whether to save scores to database
     * @return array Results keyed by visitor ID
     */
    public function batch_calculate_scores($visitor_ids, $client_id, $cache_result = true) {
        $results = array();
        
        foreach ($visitor_ids as $visitor_id) {
            $results[$visitor_id] = $this->calculate_visitor_score($visitor_id, $client_id, $cache_result);
        }
        
        return $results;
    }
    
    /**
     * Get email tracking statistics for a visitor
     * 
     * Queries rtr_email_tracking table for email opens and clicks.
     * Returns counts of unique emails opened/clicked.
     * 
     * @param int $visitor_id Visitor ID
     * @return array Email stats array with 'opens' and 'clicks' counts
     */
    private function get_visitor_email_stats($visitor_id) {
        if ($visitor_id <= 0) {
            return array('opens' => 0, 'clicks' => 0);
        }
        
        $tracking_table = $this->wpdb->prefix . 'rtr_email_tracking';
        
        // Check if table exists
        $table_exists = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $tracking_table
            )
        );
        
        if (!$table_exists) {
            return array('opens' => 0, 'clicks' => 0);
        }
        
        // Count emails opened (status = 'opened' or opened_at is not null)
        $opens = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(DISTINCT id) 
                FROM {$tracking_table} 
                WHERE visitor_id = %d 
                AND (status = 'opened' OR opened_at IS NOT NULL)",
                $visitor_id
            )
        );
        
        // For clicks, we'd need a separate tracking mechanism
        // For now, approximate clicks as emails that were copied (potential sends)
        $clicks = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(DISTINCT id) 
                FROM {$tracking_table} 
                WHERE visitor_id = %d 
                AND status IN ('copied', 'opened')",
                $visitor_id
            )
        );
        
        return array(
            'opens' => intval($opens),
            'clicks' => intval($clicks)
        );
    }
}