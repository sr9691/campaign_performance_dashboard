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
     * FIXED: Added skip_default_fallback option for nightly job processing
     *
     * @param array<string,mixed> $context
     * @return array<string,mixed>|null Campaign data array or null
     */
    public function match(array $context): ?array
    {
        if (empty($context)) {
            error_log(self::LOG_PREFIX . ' match called with empty context');
            return null;
        }

        // FIXED: Check for skip_default_fallback flag
        $skip_default_fallback = !empty($context['skip_default_fallback']);

        try {
            // Fetch visitor context from database if only visitor_id provided
            if (isset($context['visitor_id']) && is_int($context['visitor_id'])) {
                $visitor_id = $context['visitor_id'];
                $other_fields = array_diff_key($context, ['visitor_id' => true, 'skip_default_fallback' => true]);
                
                if (empty($other_fields)) {
                    $db_context = $this->get_visitor_context($visitor_id);
                    if ($db_context === null) {
                        error_log(self::LOG_PREFIX . ' Cannot match: visitor_id=' . $visitor_id . ' not found');
                        return $skip_default_fallback ? null : $this->get_default_campaign();
                    }
                    $context = array_merge($db_context, ['skip_default_fallback' => $skip_default_fallback]);
                } else {
                    $db_context = $this->get_visitor_context($visitor_id);
                    if ($db_context !== null) {
                        $context = array_merge($db_context, $context);
                    }
                }
            }

            // Step 1: Check for utm_campaign in recent_page_urls
            $utm_match = $this->match_by_utm_campaign($context);
            if ($utm_match !== null) {
                $utm_match['score'] = self::SCORE_UTM_EXACT_MATCH;
                $utm_match['match_method'] = 'utm_campaign';
                $this->record_match_event($context, $utm_match);
                error_log(self::LOG_PREFIX . ' Matched campaign via UTM: ' . ($utm_match['campaign_name'] ?? $utm_match['name'] ?? 'unknown'));
                return $utm_match;
            }

            // Step 2: Check recent_page_urls against content_links
            $content_link_match = $this->match_by_content_link($context);
            if ($content_link_match !== null) {
                $content_link_match['score'] = self::SCORE_CONTENT_LINK_MATCH;
                $content_link_match['match_method'] = 'content_link';
                $this->record_match_event($context, $content_link_match);
                error_log(self::LOG_PREFIX . ' Matched campaign via content link: ' . ($content_link_match['campaign_name'] ?? $content_link_match['name'] ?? 'unknown'));
                return $content_link_match;
            }

            // Step 3: Return default campaign (ONLY if not skipping)
            // FIXED: Respect skip_default_fallback flag
            if ($skip_default_fallback) {
                error_log(self::LOG_PREFIX . ' No explicit match found and skip_default_fallback=true, returning null');
                return null;
            }

            $default_campaign = $this->get_default_campaign();
            if ($default_campaign) {
                $default_campaign['score'] = 0;
                $default_campaign['match_method'] = 'default_fallback';
                $this->record_match_event($context, $default_campaign);
                error_log(self::LOG_PREFIX . ' No match found, using default campaign');
            }
            
            return $default_campaign;
        } catch (\Throwable $e) {
            error_log(self::LOG_PREFIX . ' match error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Step 1: Match by UTM Campaign
     * 
     * Extracts utm_campaign from visitor's recent_page_urls and matches against
     * campaign's configured utm_campaign field.
     *
     * @param array<string,mixed> $context Visitor context
     * @return array<string,mixed>|null Campaign array if matched, null otherwise
     */
    private function match_by_utm_campaign(array $context): ?array
    {
        try {
            // Parse recent_page_urls to extract utm_campaign parameters
            $visitor_urls = $this->parse_recent_page_urls($context['recent_page_urls'] ?? null);
            
            if (empty($visitor_urls)) {
                return null;
            }

            // Extract utm_campaign from each URL
            $utm_campaigns = [];
            foreach ($visitor_urls as $url) {
                $utm = $this->extract_utm_from_url($url);
                if ($utm !== null) {
                    $utm_campaigns[] = strtolower(trim($utm));
                }
            }

            if (empty($utm_campaigns)) {
                return null;
            }

            // Remove duplicates
            $utm_campaigns = array_unique($utm_campaigns);

            // Query campaigns table for matching utm_campaign
            global $wpdb;
            $table = $wpdb->prefix . 'dr_campaign_settings';
            
            // Build query to check against all found utm_campaigns
            $placeholders = implode(',', array_fill(0, count($utm_campaigns), '%s'));
            
            $query = $wpdb->prepare(
                "SELECT * FROM {$table} 
                 WHERE LOWER(TRIM(utm_campaign)) IN ({$placeholders})
                 AND (
                     (start_date IS NULL OR start_date <= CURDATE())
                     AND (end_date IS NULL OR end_date >= CURDATE())
                 )
                 LIMIT 1",
                ...$utm_campaigns
            );

            $campaign = $wpdb->get_row($query, ARRAY_A);

            if ($campaign) {
                error_log(sprintf(
                    self::LOG_PREFIX . ' UTM campaign match: campaign_id=%s, utm_campaign=%s',
                    $campaign['id'],
                    $campaign['utm_campaign'] ?? 'N/A'
                ));
                return $campaign;
            }

            return null;
        } catch (\Throwable $e) {
            error_log(self::LOG_PREFIX . ' match_by_utm_campaign error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Step 2: Match by Content Links
     * 
     * Checks if visitor's recent_page_urls match any URLs in rtr_room_content_links table.
     * Returns the first matching campaign found.
     *
     * @param array<string,mixed> $context Visitor context
     * @return array<string,mixed>|null Campaign array if matched, null otherwise
     */
    private function match_by_content_link(array $context): ?array
    {
        try {
            $visitor_urls = $this->parse_recent_page_urls($context['recent_page_urls'] ?? null);
            
            if (empty($visitor_urls)) {
                return null;
            }

            // Normalize visitor URLs for comparison
            $normalized_visitor_urls = [];
            foreach ($visitor_urls as $url) {
                $normalized = $this->normalize_url($url);
                if ($normalized !== null) {
                    $normalized_visitor_urls[] = $normalized;
                }
            }

            if (empty($normalized_visitor_urls)) {
                return null;
            }

            global $wpdb;
            $links_table = $wpdb->prefix . 'rtr_room_content_links';
            $campaigns_table = $wpdb->prefix . 'dr_campaign_settings';
            
            // Get all active content links
            $query = "SELECT campaign_id, link_url 
                      FROM {$links_table} 
                      WHERE is_active = 1 
                      AND link_url IS NOT NULL 
                      AND link_url != ''";
            
            $content_links = $wpdb->get_results($query, ARRAY_A);

            if (empty($content_links)) {
                return null;
            }

            // Check each visitor URL against content links
            foreach ($normalized_visitor_urls as $visitor_url) {
                foreach ($content_links as $link) {
                    $content_url = $this->normalize_url($link['link_url']);
                    
                    if ($content_url !== null && $visitor_url === $content_url) {
                        // Found a match! Fetch the campaign
                        $campaign = $wpdb->get_row(
                            $wpdb->prepare(
                                "SELECT * FROM {$campaigns_table} 
                                 WHERE id = %d 
                                 AND (
                                     (start_date IS NULL OR start_date <= CURDATE())
                                     AND (end_date IS NULL OR end_date >= CURDATE())
                                 )
                                 LIMIT 1",
                                $link['campaign_id']
                            ),
                            ARRAY_A
                        );

                        if ($campaign) {
                            error_log(sprintf(
                                self::LOG_PREFIX . ' Content link match: campaign_id=%s, url=%s',
                                $campaign['id'],
                                $visitor_url
                            ));
                            return $campaign;
                        }
                    }
                }
            }

            return null;
        } catch (\Throwable $e) {
            error_log(self::LOG_PREFIX . ' match_by_content_link error: ' . $e->getMessage());
            return null;
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
     * Step 3: Get default campaign
     * 
     * Returns the campaign named "Default" or the first active campaign found.
     *
     * @return array<string,mixed>|null Default campaign or null if none found
     */
    private function get_default_campaign(): ?array
    {
        try {
            global $wpdb;
            $table = $wpdb->prefix . 'dr_campaign_settings';
            
            // Try to find a campaign specifically named "Default"
            $campaign = $wpdb->get_row(
                "SELECT * FROM {$table} 
                 WHERE LOWER(TRIM(campaign_name)) = 'default'
                 AND (
                     (start_date IS NULL OR start_date <= CURDATE())
                     AND (end_date IS NULL OR end_date >= CURDATE())
                 )
                 LIMIT 1",
                ARRAY_A
            );

            if ($campaign) {
                error_log(self::LOG_PREFIX . ' Using "Default" campaign: ' . $campaign['id']);
                return $campaign;
            }

            // Fallback: get the first active campaign ordered by ID
            $campaign = $wpdb->get_row(
                "SELECT * FROM {$table} 
                 WHERE (
                     (start_date IS NULL OR start_date <= CURDATE())
                     AND (end_date IS NULL OR end_date >= CURDATE())
                 )
                 ORDER BY id ASC
                 LIMIT 1",
                ARRAY_A
            );

            if ($campaign) {
                error_log(self::LOG_PREFIX . ' Using first available campaign as default: ' . $campaign['id']);
                return $campaign;
            }

            error_log(self::LOG_PREFIX . ' No default campaign found');
            return null;
        } catch (\Throwable $e) {
            error_log(self::LOG_PREFIX . ' get_default_campaign error: ' . $e->getMessage());
            return null;
        }
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