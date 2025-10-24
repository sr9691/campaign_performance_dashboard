<?php
/**
 * Visitor Campaign Manager
 * 
 * Manages visitor-campaign attribution records
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

class Visitor_Campaign_Manager {
    
    /**
     * Create or update visitor-campaign attribution
     * 
     * @param int $visitor_id Visitor ID
     * @param int $campaign_id Campaign ID (from wp_dr_campaign_settings)
     * @param string $campaign_id_str Campaign ID string (for storage)
     * @param array $matched_urls Array of matched page URLs
     * @param string $account_id Account/client ID
     * @return bool|int Insert ID on create, true on update, false on error
     */
    public function create_or_update_attribution($visitor_id, $campaign_id, $campaign_id_str, $matched_urls, $account_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cpd_visitor_campaigns';
        
        // Check if attribution already exists
        $existing = $this->attribution_exists($visitor_id, $campaign_id_str);
        
        if ($existing) {
            // UPDATE existing attribution
            return $this->update_attribution($visitor_id, $campaign_id_str, $matched_urls, $existing);
        } else {
            // CREATE new attribution
            return $this->create_attribution($visitor_id, $campaign_id_str, $matched_urls, $account_id);
        }
    }
    
    /**
     * Check if attribution exists
     * 
     * @param int $visitor_id Visitor ID
     * @param string $campaign_id Campaign ID string
     * @return object|null Existing record or null
     */
    public function attribution_exists($visitor_id, $campaign_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cpd_visitor_campaigns';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} 
             WHERE visitor_id = %d 
             AND campaign_id = %s",
            $visitor_id,
            $campaign_id
        ));
    }
    
    /**
     * Create new attribution record
     * 
     * @param int $visitor_id Visitor ID
     * @param string $campaign_id Campaign ID string
     * @param array $matched_urls Array of matched page URLs
     * @param string $account_id Account/client ID
     * @return bool|int Insert ID or false on error
     */
    private function create_attribution($visitor_id, $campaign_id, $matched_urls, $account_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cpd_visitor_campaigns';
        $current_time = current_time('mysql');
        
        // Deduplicate matched URLs
        $matched_urls = array_unique($matched_urls);
        
        // Entry page is first matched URL
        $entry_page = !empty($matched_urls) ? $matched_urls[0] : null;
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'visitor_id' => $visitor_id,
                'campaign_id' => $campaign_id,
                'account_id' => $account_id,
                'first_visit_at' => $current_time,
                'last_visit_at' => $current_time,
                'entry_page' => $entry_page,
                'page_urls' => json_encode($matched_urls),
                'total_page_views' => count($matched_urls),
                'unique_pages_count' => count($matched_urls),
                'is_prospect' => 0,
                'current_room' => 'none',
                'created_at' => $current_time,
                'updated_at' => $current_time
            ),
            array(
                '%d', // visitor_id
                '%s', // campaign_id
                '%s', // account_id
                '%s', // first_visit_at
                '%s', // last_visit_at
                '%s', // entry_page
                '%s', // page_urls
                '%d', // total_page_views
                '%d', // unique_pages_count
                '%d', // is_prospect
                '%s', // current_room
                '%s', // created_at
                '%s'  // updated_at
            )
        );
        
        if ($result === false) {
            error_log(sprintf(
                'Visitor-Campaign Manager: Failed to create attribution for visitor %d, campaign %s: %s',
                $visitor_id,
                $campaign_id,
                $wpdb->last_error
            ));
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Update existing attribution record
     * 
     * Rules:
     * - NEVER update first_visit_at or entry_page
     * - Update last_visit_at to current time
     * - Replace page_urls with current matched pages (deduplicated)
     * - Recalculate total_page_views and unique_pages_count
     * 
     * @param int $visitor_id Visitor ID
     * @param string $campaign_id Campaign ID string
     * @param array $matched_urls Array of matched page URLs
     * @param object $existing Existing attribution record
     * @return bool True on success, false on error
     */
    private function update_attribution($visitor_id, $campaign_id, $matched_urls, $existing) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cpd_visitor_campaigns';
        
        // Deduplicate matched URLs
        $matched_urls = array_unique($matched_urls);
        
        $result = $wpdb->update(
            $table_name,
            array(
                'last_visit_at' => current_time('mysql'),
                'page_urls' => json_encode($matched_urls),
                'total_page_views' => count($matched_urls),
                'unique_pages_count' => count($matched_urls),
                'updated_at' => current_time('mysql')
            ),
            array(
                'visitor_id' => $visitor_id,
                'campaign_id' => $campaign_id
            ),
            array(
                '%s', // last_visit_at
                '%s', // page_urls
                '%d', // total_page_views
                '%d', // unique_pages_count
                '%s'  // updated_at
            ),
            array(
                '%d', // visitor_id
                '%s'  // campaign_id
            )
        );
        
        if ($result === false) {
            error_log(sprintf(
                'Visitor-Campaign Manager: Failed to update attribution for visitor %d, campaign %s: %s',
                $visitor_id,
                $campaign_id,
                $wpdb->last_error
            ));
            return false;
        }
        
        return true;
    }
    
    /**
     * Get attribution statistics
     * 
     * @param int $visitor_id Visitor ID
     * @return array Statistics array
     */
    public function get_visitor_attribution_stats($visitor_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cpd_visitor_campaigns';
        
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total_attributions,
                SUM(total_page_views) as total_page_views,
                SUM(unique_pages_count) as total_unique_pages,
                MAX(last_visit_at) as most_recent_visit
             FROM {$table_name}
             WHERE visitor_id = %d",
            $visitor_id
        ), ARRAY_A);
        
        return $stats;
    }
    
    /**
     * Get all attributions for a visitor
     * 
     * @param int $visitor_id Visitor ID
     * @return array Array of attribution records
     */
    public function get_visitor_attributions($visitor_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cpd_visitor_campaigns';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name} 
             WHERE visitor_id = %d
             ORDER BY last_visit_at DESC",
            $visitor_id
        ), ARRAY_A);
    }
    
    /**
     * Get all attributions for a campaign
     * 
     * @param string $campaign_id Campaign ID string
     * @return array Array of attribution records
     */
    public function get_campaign_attributions($campaign_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cpd_visitor_campaigns';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name} 
             WHERE campaign_id = %s
             ORDER BY last_visit_at DESC",
            $campaign_id
        ), ARRAY_A);
    }
    
    /**
     * Log action to wp_cpd_action_logs
     * 
     * @param string $action_type Action type
     * @param string $description Description
     */
    private function log_action($action_type, $description) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'cpd_action_logs',
            array(
                'user_id' => get_current_user_id() ?: 0,
                'action_type' => $action_type,
                'description' => $description,
                'timestamp' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s')
        );
    }
}
