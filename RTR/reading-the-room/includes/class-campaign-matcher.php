<?php
/**
 * Campaign Matcher
 * 
 * Matches visitor page URLs to campaign content links
 * 
 * @package DirectReach
 * @subpackage ReadingTheRoom
 * @since 1.0.0
 */

namespace DirectReach\ReadingTheRoom;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Campaign_Matcher {
    
    /**
     * Content links cache
     * Indexed by campaign_id for fast lookup
     */
    private $content_links_cache = array();
    
    /**
     * Load and cache all content links for a client
     * 
     * @param int $client_id Client ID
     * @return array Array of content links indexed by campaign_id
     */
    public function load_content_links_for_client($client_id) {
        global $wpdb;
        
        // Get all active campaigns for client
        $campaigns = $wpdb->get_results($wpdb->prepare(
            "SELECT id, campaign_id 
             FROM {$wpdb->prefix}dr_campaign_settings 
             WHERE client_id = %d 
             AND (end_date IS NULL OR end_date >= CURDATE())",
            $client_id
        ), ARRAY_A);
        
        if (empty($campaigns)) {
            return array();
        }
        
        $campaign_ids = array_column($campaigns, 'id');
        
        // Build IN clause for prepared statement
        $placeholders = implode(',', array_fill(0, count($campaign_ids), '%d'));
        
        // Load all content links for these campaigns
        $content_links = $wpdb->get_results($wpdb->prepare(
            "SELECT campaign_id, link_url, link_title, room_type
             FROM {$wpdb->prefix}rtr_room_content_links 
             WHERE campaign_id IN ($placeholders) 
             AND is_active = 1
             ORDER BY campaign_id, room_type, link_order",
            ...$campaign_ids
        ), ARRAY_A);
        
        // Index by campaign_id for fast lookup
        $this->content_links_cache = array();
        foreach ($content_links as $link) {
            $campaign_id = $link['campaign_id'];
            if (!isset($this->content_links_cache[$campaign_id])) {
                $this->content_links_cache[$campaign_id] = array();
            }
            $this->content_links_cache[$campaign_id][] = $link;
        }
        
        error_log(sprintf(
            'Campaign Matcher: Loaded %d content links for %d campaigns (client %d)',
            count($content_links),
            count($campaign_ids),
            $client_id
        ));
        
        return $this->content_links_cache;
    }
    
    /**
     * Match a visitor to campaigns based on their page URLs
     * 
     * @param object $visitor Visitor record from wp_cpd_visitors
     * @return array Array of matches: [campaign_id => [matched_urls]]
     */
    public function match_visitor_to_campaigns($visitor) {
        // Parse recent page URLs
        $page_urls = json_decode($visitor->recent_page_urls, true);
        
        if (empty($page_urls) || !is_array($page_urls)) {
            return array();
        }
        
        $matches = array();
        
        // Loop through each campaign's content links
        foreach ($this->content_links_cache as $campaign_id => $content_links) {
            $matched_urls = array();
            
            // Check each visitor page against each content link
            foreach ($page_urls as $visitor_url) {
                $normalized_visitor_url = $this->normalize_url($visitor_url);
                
                foreach ($content_links as $content_link) {
                    $normalized_content_url = $this->normalize_url($content_link['link_url']);
                    
                    if ($this->url_matches($normalized_visitor_url, $normalized_content_url)) {
                        // Store the original visitor URL (not content link URL)
                        if (!in_array($visitor_url, $matched_urls)) {
                            $matched_urls[] = $visitor_url;
                        }
                    }
                }
            }
            
            // If we found matches for this campaign, add to results
            if (!empty($matched_urls)) {
                $matches[$campaign_id] = $matched_urls;
            }
        }
        
        return $matches;
    }
    
    /**
     * Normalize URL for comparison
     * 
     * Rules:
     * - Strip domain
     * - Strip query string
     * - Strip trailing slash
     * - Lowercase
     * 
     * @param string $url URL to normalize
     * @return string Normalized URL path
     */
    public function normalize_url($url) {
        // Remove protocol and domain
        $parsed = parse_url($url);
        $path = isset($parsed['path']) ? $parsed['path'] : '';
        
        // Remove trailing slash
        $path = rtrim($path, '/');
        
        // Lowercase for case-insensitive matching
        $path = strtolower($path);
        
        return $path;
    }
    
    /**
     * Check if visitor URL matches content link URL
     * 
     * Uses partial match: content link "/blog/post-1" matches visitor "/blog/post-1?utm_source=google"
     * 
     * @param string $visitor_url Normalized visitor URL
     * @param string $content_url Normalized content link URL
     * @return bool True if match
     */
    public function url_matches($visitor_url, $content_url) {
        // Empty URLs never match
        if (empty($visitor_url) || empty($content_url)) {
            return false;
        }
        
        // Use stripos for case-insensitive partial match
        // Content link must appear at the START of visitor URL
        return stripos($visitor_url, $content_url) === 0;
    }
    
    /**
     * Get statistics about cached content links
     * 
     * @return array Stats array
     */
    public function get_cache_stats() {
        $total_links = 0;
        $campaigns_count = count($this->content_links_cache);
        
        foreach ($this->content_links_cache as $links) {
            $total_links += count($links);
        }
        
        return array(
            'campaigns' => $campaigns_count,
            'total_links' => $total_links,
            'avg_links_per_campaign' => $campaigns_count > 0 ? round($total_links / $campaigns_count, 2) : 0
        );
    }
}
