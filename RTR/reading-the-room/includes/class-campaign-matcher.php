<?php
/**
 * Campaign Matcher
 *
 * Determines the most relevant campaign for a visitor or prospect based on
 * filters, metadata, and analytics context.
 *
 * @package DirectReach
 * @subpackage ReadingTheRoom
 * @since 1.2.0
 */

declare(strict_types=1);

namespace DirectReach\ReadingTheRoom;

// FIXED: Added proper namespace imports
use DirectReach\ReadingTheRoom\Reading_Room_Database;

if (!defined('ABSPATH')) {
    exit;
}

final class Campaign_Matcher
{
    // FIXED: Added class constants for magic values
    private const LOG_PREFIX = '[DirectReach][Matcher]';
    private const SCORE_UTM_EXACT_MATCH = 100;
    private const SCORE_CONTENT_LINK_MATCH = 100;
    
    // FIXED: Added proper type declaration
    private ?Reading_Room_Database $db = null;

    /** @var array<int,array<string,int>> */
    private array $weights_cache = [];

    /**
     * Constructor with dependency injection
     * FIXED: Removed reliance on global $dr_rtr_db
     * 
     * @param Reading_Room_Database|null $db Database instance (optional, will create if not provided)
     */
    public function __construct(?Reading_Room_Database $db = null)
    {
        $this->db = $db;
    }

    /**
     * Get database instance with lazy initialization
     * FIXED: Removed global variable usage
     * 
     * @return Reading_Room_Database
     * @throws \RuntimeException If database cannot be initialized
     */
    private function db(): Reading_Room_Database
    {
        if ($this->db instanceof Reading_Room_Database) {
            return $this->db;
        }

        // Create new instance if not provided
        global $wpdb;
        $this->db = new Reading_Room_Database($wpdb);
        return $this->db;
    }

    /* ---------------------------------------------------------------------
     * Context Retrieval - Phase 1.5
     * -------------------------------------------------------------------*/

    /**
     * Get visitor context from cpd_visitors table by visitor ID.
     * FIXED: Changed from public to private (internal method)
     * 
     * @param int $visitor_id Primary key (id column) from cpd_visitors table
     * @return array<string,mixed>|null Context array or null if not found
     */
    private function get_visitor_context(int $visitor_id): ?array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'cpd_visitors';

        $query = $wpdb->prepare(
            "SELECT 
                id,
                email,
                company_name,
                first_name,
                last_name,
                job_title,
                recent_page_urls,
                campaign_source,
                campaign_medium,
                campaign_name,
                campaign_term,
                campaign_content,
                city,
                state,
                country,
                industry
            FROM {$table}
            WHERE id = %d
            LIMIT 1",
            $visitor_id
        );

        $visitor = $wpdb->get_row($query, ARRAY_A);

        if ($wpdb->last_error) {
            error_log(self::LOG_PREFIX . ' get_visitor_context query error: ' . $wpdb->last_error);
            return null;
        }

        if (!$visitor) {
            error_log(self::LOG_PREFIX . ' Visitor not found: id=' . $visitor_id);
            return null;
        }

        // Build context array
        // FIXED: Removed duplicate campaign_name field, keeping only utm_campaign
        $context = [
            'visitor_id' => $visitor_id,
            'email' => !empty($visitor['email']) ? trim($visitor['email']) : null,
            'company' => !empty($visitor['company_name']) ? trim($visitor['company_name']) : null,
            'first_name' => !empty($visitor['first_name']) ? trim($visitor['first_name']) : null,
            'last_name' => !empty($visitor['last_name']) ? trim($visitor['last_name']) : null,
            'job_title' => !empty($visitor['job_title']) ? trim($visitor['job_title']) : null,
            'utm_source' => !empty($visitor['campaign_source']) ? trim($visitor['campaign_source']) : null,
            'utm_medium' => !empty($visitor['campaign_medium']) ? trim($visitor['campaign_medium']) : null,
            'utm_campaign' => !empty($visitor['campaign_name']) ? trim($visitor['campaign_name']) : null,
            'campaign_term' => !empty($visitor['campaign_term']) ? trim($visitor['campaign_term']) : null,
            'campaign_content' => !empty($visitor['campaign_content']) ? trim($visitor['campaign_content']) : null,
            'recent_page_urls' => !empty($visitor['recent_page_urls']) ? $visitor['recent_page_urls'] : null,
            'industry' => !empty($visitor['industry']) ? trim($visitor['industry']) : null,
        ];

        // Build location string
        $location_parts = array_filter([
            !empty($visitor['city']) ? trim($visitor['city']) : null,
            !empty($visitor['state']) ? trim($visitor['state']) : null,
            !empty($visitor['country']) ? trim($visitor['country']) : null,
        ]);
        $context['location'] = !empty($location_parts) ? implode(', ', $location_parts) : null;

        return $context;
    }

    /* ---------------------------------------------------------------------
     * Matching Logic
     * -------------------------------------------------------------------*/

    /**
     * Match a campaign for a visitor or prospect.
     * ENHANCED: Added input validation
     *
     * @param array<string,mixed> $context
     *        Full context array:
     *        [
     *          'email'      => string|null,
     *          'company'    => string|null,
     *          'utm_source' => string|null,
     *          'utm_medium' => string|null,
     *          'utm_campaign' => string|null,
     *          'recent_page_urls' => string|array|null,
     *          'interests'  => string[]|null,
     *          'location'   => string|null,
     *        ]
     *        OR minimal context for database lookup:
     *        [
     *          'visitor_id' => int
     *        ]
     *        If only visitor_id is provided, full context will be fetched from cpd_visitors table.
     *        If visitor_id is provided WITH other fields, provided fields take precedence (override).
     *
     * @return array<string,mixed>|null
     */
    public function match(array $context): ?array
    {
        // FIXED: Added input validation
        if (empty($context)) {
            error_log(self::LOG_PREFIX . ' match called with empty context');
            return null;
        }

        // Validate that we have at least some usable data
        if (!isset($context['visitor_id']) && 
            empty($context['email']) && 
            empty($context['utm_campaign']) &&
            empty($context['recent_page_urls'])) {
            error_log(self::LOG_PREFIX . ' match called with insufficient context data');
            return null;
        }

        try {
            // Phase 1.5: Check if we need to fetch context from database
            if (isset($context['visitor_id']) && is_int($context['visitor_id'])) {
                $visitor_id = $context['visitor_id'];
                
                // Count how many other fields are provided
                $other_fields = array_diff_key($context, ['visitor_id' => true]);
                
                if (empty($other_fields)) {
                    // Only visitor_id provided, fetch full context
                    $db_context = $this->get_visitor_context($visitor_id);
                    if ($db_context === null) {
                        error_log(self::LOG_PREFIX . ' Cannot match: visitor_id=' . $visitor_id . ' not found');
                        return null;
                    }
                    $context = $db_context;
                } else {
                    // Visitor_id + other fields: fetch from DB, then override with provided fields
                    $db_context = $this->get_visitor_context($visitor_id);
                    if ($db_context !== null) {
                        $context = array_merge($db_context, $context);
                    }
                    // If DB fetch failed but we have other fields, continue with provided context
                }
            }

            // Phase 1.2: UTM Priority Matching - Check first for instant 100% match
            $utm_match = $this->match_by_utm($context);
            if ($utm_match !== null) {
                $utm_match['score'] = self::SCORE_UTM_EXACT_MATCH;
                $utm_match['match_method'] = 'utm_priority';
                $this->record_match_event($context, $utm_match);
                return $utm_match;
            }

            // Phase 1.3: Content Link Matching
            $content_matches = $this->match_by_content_links($context);
            
            // Fallback to existing scoring logic
            $campaigns = $this->db()->get_campaigns(['status' => 'active']);
            if (empty($campaigns)) {
                return null;
            }

            $best_score    = 0;
            $best_campaign = null;
            $best_method   = 'fallback_scoring';

            foreach ($campaigns as $campaign) {
                $campaign_id = (int) $campaign['id'];
                
                $content_score = $content_matches[$campaign_id] ?? 0;
                $fallback_score = $this->calculate_match_score($campaign, $context);
                
                if ($content_score >= $fallback_score) {
                    $score = $content_score;
                    $method = 'content_link';
                } else {
                    $score = $fallback_score;
                    $method = 'fallback_scoring';
                }

                if ($score > $best_score) {
                    $best_score    = $score;
                    $best_campaign = $campaign;
                    $best_method   = $method;
                }
            }

            if ($best_campaign) {
                $best_campaign['score'] = $best_score;
                $best_campaign['match_method'] = $best_method;
                $this->record_match_event($context, $best_campaign);
            }

            return $best_campaign;
        } catch (\Throwable $e) {
            error_log(self::LOG_PREFIX . ' match error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * UTM Priority Matching - Phase 1.2
     * 
     * Returns campaign immediately if utm_campaign matches, with score=100.
     * This is the highest priority matching method.
     *
     * @param array<string,mixed> $context
     * @return array<string,mixed>|null Campaign array if matched, null otherwise
     */
    private function match_by_utm(array $context): ?array
    {
        try {
            $utm_campaign = $this->extract_utm_campaign_from_context($context);
            
            if (empty($utm_campaign)) {
                return null;
            }

            global $wpdb;
            $table = $wpdb->prefix . 'dr_campaign_settings';
            
            $query = $wpdb->prepare(
                "SELECT * FROM {$table} 
                 WHERE LOWER(utm_campaign) = LOWER(%s) 
                 AND (
                     (start_date IS NULL OR start_date <= CURDATE())
                     AND (end_date IS NULL OR end_date >= CURDATE())
                 )
                 LIMIT 1",
                $utm_campaign
            );

            $campaign = $wpdb->get_row($query, ARRAY_A);

            if ($campaign) {
                error_log(sprintf(
                    self::LOG_PREFIX . ' UTM match found: campaign_id=%s, utm_campaign=%s',
                    $campaign['id'],
                    $utm_campaign
                ));
                return $campaign;
            }

            return null;
        } catch (\Throwable $e) {
            error_log(self::LOG_PREFIX . ' match_by_utm error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Content Link Matching - Phase 1.3
     * 
     * Scores campaigns based on visitor's recent_page_urls matching campaign content links.
     * Exact URL matches (ignoring query string) score 100 points each.
     * Multiple matches for the same campaign are summed.
     *
     * @param array<string,mixed> $context
     * @return array<int,int> Array mapping campaign_id to total score
     */
    private function match_by_content_links(array $context): array
    {
        try {
            $visitor_urls = $this->parse_recent_page_urls($context['recent_page_urls'] ?? null);
            
            if (empty($visitor_urls)) {
                return [];
            }

            $normalized_visitor_urls = [];
            foreach ($visitor_urls as $url) {
                $normalized = $this->normalize_url($url);
                if ($normalized !== null) {
                    $normalized_visitor_urls[] = $normalized;
                }
            }

            if (empty($normalized_visitor_urls)) {
                return [];
            }

            global $wpdb;
            $links_table = $wpdb->prefix . 'rtr_room_content_links';
            
            $query = "SELECT campaign_id, link_url 
                      FROM {$links_table} 
                      WHERE is_active = 1 
                      AND link_url IS NOT NULL 
                      AND link_url != ''";
            
            $content_links = $wpdb->get_results($query, ARRAY_A);

            if (empty($content_links)) {
                return [];
            }

            $campaign_scores = [];

            foreach ($content_links as $link) {
                $campaign_id = (int) $link['campaign_id'];
                $normalized_link = $this->normalize_url($link['link_url']);
                
                if ($normalized_link === null) {
                    continue;
                }

                if (in_array($normalized_link, $normalized_visitor_urls, true)) {
                    if (!isset($campaign_scores[$campaign_id])) {
                        $campaign_scores[$campaign_id] = 0;
                    }
                    $campaign_scores[$campaign_id] += self::SCORE_CONTENT_LINK_MATCH;
                }
            }

            return $campaign_scores;
        } catch (\Throwable $e) {
            error_log(self::LOG_PREFIX . ' match_by_content_links error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Normalize URL by removing query string and trailing slash.
     * Returns lowercase URL path for comparison.
     *
     * @param string $url
     * @return string|null
     */
    private function normalize_url(string $url): ?string
    {
        try {
            $url = trim($url);
            if (empty($url)) {
                return null;
            }

            $parsed = parse_url($url);
            if ($parsed === false || empty($parsed['host'])) {
                return null;
            }

            $scheme = isset($parsed['scheme']) ? strtolower($parsed['scheme']) : 'https';
            $host = strtolower($parsed['host']);
            $path = isset($parsed['path']) ? $parsed['path'] : '/';
            
            $path = rtrim($path, '/');
            if (empty($path)) {
                $path = '/';
            }

            return $scheme . '://' . $host . $path;
        } catch (\Throwable $e) {
            error_log(self::LOG_PREFIX . ' normalize_url error: ' . $url . ' - ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Extract utm_campaign from context, checking multiple sources.
     * Prioritizes direct utm_campaign field, then campaign_name, then extracts from recent_page_urls.
     *
     * @param array<string,mixed> $context
     * @return string|null
     */
    private function extract_utm_campaign_from_context(array $context): ?string
    {
        if (!empty($context['utm_campaign']) && is_string($context['utm_campaign'])) {
            return trim($context['utm_campaign']);
        }

        if (!empty($context['campaign_name']) && is_string($context['campaign_name'])) {
            return trim($context['campaign_name']);
        }

        $visitor_urls = $this->parse_recent_page_urls($context['recent_page_urls'] ?? null);
        foreach ($visitor_urls as $url) {
            $utm = $this->extract_utm_from_url($url);
            if ($utm !== null) {
                return $utm;
            }
        }

        return null;
    }

    /**
     * Parse recent_page_urls into array of URL strings.
     * Handles JSON string, JSON array, or single URL string.
     *
     * @param mixed $recent_page_urls
     * @return string[]
     */
    private function parse_recent_page_urls($recent_page_urls): array
    {
        if (empty($recent_page_urls)) {
            return [];
        }

        if (is_array($recent_page_urls)) {
            return array_filter($recent_page_urls, 'is_string');
        }

        if (!is_string($recent_page_urls)) {
            return [];
        }

        $trimmed = trim($recent_page_urls);
        
        if ($trimmed[0] === '[') {
            $decoded = json_decode($trimmed, true);
            if (is_array($decoded)) {
                return array_filter($decoded, 'is_string');
            }
            return [];
        }

        if (filter_var($trimmed, FILTER_VALIDATE_URL)) {
            return [$trimmed];
        }

        $decoded = json_decode($trimmed, true);
        if (is_array($decoded)) {
            return array_filter($decoded, 'is_string');
        }

        if (!empty($recent_page_urls)) {
            return [$recent_page_urls];
        }

        return [];
    }

    /**
     * Extract utm_campaign parameter from a URL querystring.
     *
     * @param string $url
     * @return string|null
     */
    private function extract_utm_from_url(string $url): ?string
    {
        try {
            $parsed = parse_url($url);
            
            if (empty($parsed['query'])) {
                return null;
            }

            parse_str($parsed['query'], $params);
            
            if (!empty($params['utm_campaign']) && is_string($params['utm_campaign'])) {
                return trim($params['utm_campaign']);
            }

            return null;
        } catch (\Throwable $e) {
            error_log(self::LOG_PREFIX . ' extract_utm_from_url error: ' . $url . ' - ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get scoring weights from database - Phase 1.4
     * 
     * Queries rtr_global_scoring_rules (problem room type).
     * FIXED: Removed rtr_client_scoring_rules query as table doesn't exist
     * 
     * Extracts weights from rules_config JSON and maps them to matcher weights:
     * - industry_alignment.points -> keyword_match
     * - target_states.points -> location_match
     * - defaults used for utm_match and interest_match
     *
     * @param int $campaign_id
     * @return array<string,int> ['utm_match' => int, 'keyword_match' => int, 'interest_match' => int, 'location_match' => int]
     */
    private function get_scoring_weights(int $campaign_id): array
    {
        if (isset($this->weights_cache[$campaign_id])) {
            return $this->weights_cache[$campaign_id];
        }

        $defaults = [
            'utm_match' => 10,
            'keyword_match' => 5,
            'interest_match' => 3,
            'location_match' => 2,
        ];

        try {
            global $wpdb;
            
            // FIXED: Removed client_id lookup and client-specific rules
            // Only use global rules since rtr_client_scoring_rules doesn't exist
            $global_rules_table = $wpdb->prefix . 'rtr_global_scoring_rules';
            $rules = $wpdb->get_row(
                "SELECT rules_config FROM {$global_rules_table} 
                 WHERE room_type = 'problem' 
                 LIMIT 1",
                ARRAY_A
            );
            
            if (!$rules || empty($rules['rules_config'])) {
                $this->weights_cache[$campaign_id] = $defaults;
                return $defaults;
            }
            
            $config = json_decode($rules['rules_config'], true);
            if (!is_array($config)) {
                error_log(self::LOG_PREFIX . ' Invalid rules_config JSON for campaign ' . $campaign_id);
                $this->weights_cache[$campaign_id] = $defaults;
                return $defaults;
            }
            
            $weights = $defaults;
            
            if (isset($config['industry_alignment']['enabled']) && 
                $config['industry_alignment']['enabled'] && 
                isset($config['industry_alignment']['points'])) {
                $points = (int) $config['industry_alignment']['points'];
                if ($points > 0) {
                    $weights['keyword_match'] = $points;
                }
            }
            
            if (isset($config['target_states']['enabled']) && 
                $config['target_states']['enabled'] && 
                isset($config['target_states']['points'])) {
                $points = (int) $config['target_states']['points'];
                if ($points > 0) {
                    $weights['location_match'] = $points;
                }
            }
            
            $this->weights_cache[$campaign_id] = $weights;
            return $weights;
            
        } catch (\Throwable $e) {
            error_log(self::LOG_PREFIX . ' get_scoring_weights error: ' . $e->getMessage());
            $this->weights_cache[$campaign_id] = $defaults;
            return $defaults;
        }
    }

    /**
     * Compute a simple match score between campaign metadata and visitor context.
     * 
     * Phase 1.4: Uses database-driven weights from get_scoring_weights()
     *
     * @param array<string,mixed> $campaign
     * @param array<string,mixed> $context
     * @return int
     */
    private function calculate_match_score(array $campaign, array $context): int
    {
        $campaign_id = (int) ($campaign['id'] ?? 0);
        if ($campaign_id === 0) {
            return 0;
        }
        
        $weights = $this->get_scoring_weights($campaign_id);
        $score = 0;

        $metadata = [];
        if (!empty($campaign['metadata'])) {
            $decoded = json_decode((string) $campaign['metadata'], true);
            if (is_array($decoded)) {
                $metadata = $decoded;
            }
        }

        if (!empty($context['utm_campaign']) && stripos($campaign['name'], $context['utm_campaign']) !== false) {
            $score += $weights['utm_match'];
        }

        if (!empty($metadata['keywords']) && is_array($metadata['keywords'])) {
            foreach ($metadata['keywords'] as $kw) {
                if (!empty($context['utm_source']) && stripos($context['utm_source'], $kw) !== false) {
                    $score += $weights['keyword_match'];
                }
                if (!empty($context['company']) && stripos($context['company'], $kw) !== false) {
                    $score += $weights['keyword_match'];
                }
            }
        }

        if (!empty($context['interests']) && !empty($metadata['topics'])) {
            $common = array_intersect(array_map('strtolower', $context['interests']), array_map('strtolower', (array) $metadata['topics']));
            $score += count($common) * $weights['interest_match'];
        }

        if (!empty($context['location']) && !empty($metadata['locations'])) {
            foreach ((array) $metadata['locations'] as $loc) {
                if (stripos($context['location'], $loc) !== false) {
                    $score += $weights['location_match'];
                }
            }
        }

        return $score;
    }

    /**
     * Optionally log the match event.
     * 
     * @param array<string,mixed> $context
     * @param array<string,mixed> $campaign
     * @return void
     */
    private function record_match_event(array $context, array $campaign): void
    {
        // Skip logging if campaign name is not available to avoid warnings
        if (!isset($campaign['campaign_name']) && !isset($campaign['name'])) {
            return;
        }

        try {
            $this->db()->log_event([
                'prospect_id' => $context['prospect_id'] ?? null,
                'event_key'   => 'campaign_match',
                'event_value' => [
                    'campaign_id'   => $campaign['id'],
                    'campaign_name' => $campaign['campaign_name'] ?? $campaign['name'],
                    'match_score'   => $campaign['score'] ?? 0,
                    'match_method'  => $campaign['match_method'] ?? 'fallback_scoring',
                    'context'       => $context,
                ],
            ]);
        } catch (\Throwable $e) {
            error_log(self::LOG_PREFIX . ' record_match_event failed: ' . $e->getMessage());
        }
    }

    /**
     * Public helper: return just campaign ID.
     * 
     * @param array<string,mixed> $context
     * @return int|null
     */
    public function get_campaign_id_for_context(array $context): ?int
    {
        $match = $this->match($context);
        return $match['id'] ?? null;
    }
}

/**
 * Back-compat alias.
 */
if (!class_exists('\Campaign_Matcher', false)) {
    class_alias(__NAMESPACE__ . '\\Campaign_Matcher', 'Campaign_Matcher');
}