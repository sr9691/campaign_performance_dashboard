<?php
/**
 * OPTIMIZED HOT LIST CLASS - class-cpd-hot-list.php
 * 
 * Includes performance improvements and duplicate call prevention
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CPD_Hot_List {

    private $wpdb;
    private $hot_list_db;
    private $visitors_table;
    
    // Add caching to prevent duplicate queries
    private static $cache = array();
    private static $cache_ttl = 300; // 5 minutes
    
    // Add singleton pattern to prevent duplicate instantiation
    private static $instance = null;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->visitors_table = $wpdb->prefix . 'cpd_visitors';
        
        // Load hot list database class
        if (!class_exists('CPD_Hot_List_Database')) {
            require_once CPD_DASHBOARD_PLUGIN_DIR . 'includes/class-cpd-hot-list-database.php';
        }
        $this->hot_list_db = new CPD_Hot_List_Database();
    }
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Generate cache key for query
     */
    private function get_cache_key($client_id, $date_from, $date_to, $type = 'leads') {
        return 'cpd_hot_list_' . $type . '_' . md5($client_id . '_' . $date_from . '_' . $date_to);
    }

    /**
     * Get cached data if available
     */
    private function get_cached_data($cache_key) {
        if (!isset(self::$cache[$cache_key])) {
            return false;
        }
        
        $cached = self::$cache[$cache_key];
        if (time() - $cached['timestamp'] > self::$cache_ttl) {
            unset(self::$cache[$cache_key]);
            return false;
        }
        
        return $cached['data'];
    }

    /**
     * Set cached data
     */
    private function set_cached_data($cache_key, $data) {
        self::$cache[$cache_key] = array(
            'data' => $data,
            'timestamp' => time()
        );
    }

    /**
     * Get hot leads based on client's hot list settings - OPTIMIZED
     */
    public function get_hot_leads($client_id, $date_from = null, $date_to = null) {
        // Check cache first
        $cache_key = $this->get_cache_key($client_id, $date_from, $date_to, 'leads');
        $cached_data = $this->get_cached_data($cache_key);
        
        if ($cached_data !== false) {
            error_log("CPD Hot List: Using cached data for client {$client_id}");
            return $cached_data;
        }
        
        // error_log("CPD Hot List: Generating fresh data for client {$client_id}");
        
        // Get hot list settings for client
        $hot_list_settings = $this->hot_list_db->get_settings($client_id);
        
        if (!$hot_list_settings) {
            // Cache empty result
            $this->set_cached_data($cache_key, array());
            return array();
        }
        
        // Start building the query
        $where_conditions = array();
        $where_params = array();
        
        // Base conditions
        $where_conditions[] = "account_id = %s";
        $where_params[] = $client_id;
        
        $where_conditions[] = "is_archived = %d";
        $where_params[] = 0;

        $where_conditions[] = "is_crm_added = %d";
        $where_params[] = 0;        
        
        // Date filtering
        if ($date_from && $date_to) {
            $where_conditions[] = "last_seen_at BETWEEN %s AND %s";
            $where_params[] = $date_from;
            $where_params[] = $date_to;
        }
        
        // Build filter conditions
        $filter_conditions = array();
        $active_filter_count = 0;
        
        // Revenue filter
        if (!empty($hot_list_settings->revenue_filters)) {
            if (!in_array('any', $hot_list_settings->revenue_filters)) {
                // "Any" not selected, apply revenue filter
                $revenue_conditions = $this->build_revenue_conditions($hot_list_settings->revenue_filters);
                if (!empty($revenue_conditions)) {
                    $filter_conditions[] = "(" . implode(' OR ', $revenue_conditions) . ")";
                }
            }
            // Always count this as an active filter category (whether "Any" or specific values)
            $active_filter_count++;
        }
        
        // Company size filter
        if (!empty($hot_list_settings->company_size_filters)) {
            if (!in_array('any', $hot_list_settings->company_size_filters)) {
                // "Any" not selected, apply company size filter
                $size_conditions = $this->build_size_conditions($hot_list_settings->company_size_filters);
                if (!empty($size_conditions)) {
                    $filter_conditions[] = "(" . implode(' OR ', $size_conditions) . ")";
                }
            }
            // Always count this as an active filter category
            $active_filter_count++;
        }
        
        // Industry filter
        if (!empty($hot_list_settings->industry_filters)) {
            if (!in_array('Any', $hot_list_settings->industry_filters)) {
                // "Any" not selected, apply industry filter
                $industry_placeholders = array_fill(0, count($hot_list_settings->industry_filters), '%s');
                $filter_conditions[] = "industry IN (" . implode(',', $industry_placeholders) . ")";
                $where_params = array_merge($where_params, $hot_list_settings->industry_filters);
            }
            // Always count this as an active filter category
            $active_filter_count++;
        }
        
        // State filter
        if (!empty($hot_list_settings->state_filters)) {
            if (!in_array('any', $hot_list_settings->state_filters)) {
                // "Any" not selected, apply state filter
                $state_placeholders = array_fill(0, count($hot_list_settings->state_filters), '%s');
                $filter_conditions[] = "state IN (" . implode(',', $state_placeholders) . ")";
                $where_params = array_merge($where_params, $hot_list_settings->state_filters);
            }
            // Always count this as an active filter category
            $active_filter_count++;
        }        
        
        // Apply required matches logic
        if ($active_filter_count > 0) {
            $required_matches = max(1, min($hot_list_settings->required_matches, count($filter_conditions)));
            
            if (count($filter_conditions) == 0) {
                // All filters are set to "Any" - no additional WHERE conditions needed
                // This means show all visitors (subject to base conditions like not archived)
            } else if ($required_matches == count($filter_conditions)) {
                // All non-"Any" filters must match (AND logic)
                $where_conditions[] = implode(' AND ', $filter_conditions);
            } else {
                // At least N non-"Any" filters must match - create a scoring system
                $case_statements = array();
                foreach ($filter_conditions as $condition) {
                    $case_statements[] = "CASE WHEN ($condition) THEN 1 ELSE 0 END";
                }
                $score_calculation = implode(' + ', $case_statements);
                $where_conditions[] = "($score_calculation) >= $required_matches";
            }
        }
        
        // Build final query
        if (empty($where_conditions)) {
            $this->set_cached_data($cache_key, array());
            return array();
        }
        
        $sql = "SELECT * FROM {$this->visitors_table} WHERE " . implode(' AND ', $where_conditions) . " ORDER BY last_seen_at DESC";
        
        if (!empty($where_params)) {
            $sql = $this->wpdb->prepare($sql, $where_params);
        }
        
        // error_log("CPD Hot List: Executing query: $sql");
        $results = $this->wpdb->get_results($sql);
        
        // Cache the results
        $this->set_cached_data($cache_key, $results);
        
        return $results;
    }

    /**
     * Get hot leads count for dashboard - OPTIMIZED
     */
    public function get_hot_leads_count($client_id, $date_from = null, $date_to = null) {
        // Check cache first
        $cache_key = $this->get_cache_key($client_id, $date_from, $date_to, 'count');
        $cached_data = $this->get_cached_data($cache_key);
        
        if ($cached_data !== false) {
            return $cached_data;
        }
        
        $hot_leads = $this->get_hot_leads($client_id, $date_from, $date_to);
        $count = count($hot_leads);
        
        // Cache the count
        $this->set_cached_data($cache_key, $count);
        
        return $count;
    }

    /**
     * Check if client has hot list configured - CACHED
     */
    public function has_hot_list_configured($client_id) {
        $cache_key = 'cpd_hot_list_configured_' . $client_id;
        $cached_data = $this->get_cached_data($cache_key);
        
        if ($cached_data !== false) {
            return $cached_data;
        }
        
        $settings = $this->hot_list_db->get_settings($client_id);
        $has_config = !empty($settings);
        
        $this->set_cached_data($cache_key, $has_config);
        
        return $has_config;
    }

    /**
     * Get hot list criteria summary for display - CACHED
     */
    public function get_criteria_summary($client_id) {
        $cache_key = 'cpd_hot_list_summary_' . $client_id;
        $cached_data = $this->get_cached_data($cache_key);
        
        if ($cached_data !== false) {
            return $cached_data;
        }
        
        $settings = $this->hot_list_db->get_settings($client_id);
        
        if (!$settings) {
            $this->set_cached_data($cache_key, null);
            return null;
        }
        
        $summary = array();
        
        if (!empty($settings->revenue_filters) && !in_array('any', $settings->revenue_filters)) {
            $summary[] = 'Revenue: ' . count($settings->revenue_filters) . ' selected';
        }
        
        if (!empty($settings->company_size_filters) && !in_array('any', $settings->company_size_filters)) {
            $summary[] = 'Size: ' . count($settings->company_size_filters) . ' selected';
        }
        
        if (!empty($settings->industry_filters) && !in_array('any', $settings->industry_filters)) {
            $summary[] = 'Industry: ' . count($settings->industry_filters) . ' selected';
        }
        
        if (!empty($settings->state_filters) && !in_array('any', $settings->state_filters)) {
            $summary[] = 'States: ' . count($settings->state_filters) . ' selected';
        }
        
        $result = array(
            'criteria' => $summary,
            'required_matches' => $settings->required_matches,
            'active_filters' => count($summary)
        );
        
        $this->set_cached_data($cache_key, $result);
        
        return $result;
    }

    private function build_revenue_conditions($revenue_filters) {
        // Cache revenue conditions
        $cache_key = 'cpd_revenue_conditions_' . md5(serialize($revenue_filters));
        $cached_data = $this->get_cached_data($cache_key);
        
        if ($cached_data !== false) {
            return $cached_data;
        }
        
        $conditions = array();
        
        foreach ($revenue_filters as $revenue_range) {
            switch ($revenue_range) {
                case 'below-500k':
                    // Look for patterns like "Below $500K", "Under $500K", "$0 - $500K"
                    $conditions[] = "(estimated_revenue IS NOT NULL AND estimated_revenue != '' AND (
                        estimated_revenue LIKE '%Below%500K%' OR 
                        estimated_revenue LIKE '%Under%500K%' OR 
                        estimated_revenue LIKE '%Less than%500K%' OR
                        estimated_revenue LIKE '$0%500K%' OR
                        estimated_revenue = 'Below $500K'
                    ))";
                    break;
                case '500k-1m':
                    // Look for patterns like "$500K - $1M", "500K to 1M"
                    $conditions[] = "(estimated_revenue IS NOT NULL AND estimated_revenue != '' AND (
                        estimated_revenue LIKE '%500K%1M%' OR 
                        estimated_revenue LIKE '%$500K%$1M%' OR
                        estimated_revenue = '$500K - $1M'
                    ))";
                    break;
                case '1m-5m':
                    // Look for patterns like "$1M - $5M"
                    $conditions[] = "(estimated_revenue IS NOT NULL AND estimated_revenue != '' AND (
                        estimated_revenue LIKE '%1M%5M%' OR 
                        estimated_revenue LIKE '%$1M%$5M%' OR
                        estimated_revenue = '$1M - $5M'
                    ))";
                    break;
                case '5m-10m':
                    // Look for patterns like "$5M - $10M"
                    $conditions[] = "(estimated_revenue IS NOT NULL AND estimated_revenue != '' AND (
                        estimated_revenue LIKE '%5M%10M%' OR 
                        estimated_revenue LIKE '%$5M%$10M%' OR
                        estimated_revenue = '$5M - $10M'
                    ))";
                    break;
                case '10m-20m':
                    // Look for patterns like "$10M - $20M"
                    $conditions[] = "(estimated_revenue IS NOT NULL AND estimated_revenue != '' AND (
                        estimated_revenue LIKE '%10M%20M%' OR 
                        estimated_revenue LIKE '%$10M%$20M%' OR
                        estimated_revenue = '$10M - $20M'
                    ))";
                    break;
                case '20m-50m':
                    // Look for patterns like "$20M - $50M"
                    $conditions[] = "(estimated_revenue IS NOT NULL AND estimated_revenue != '' AND (
                        estimated_revenue LIKE '%20M%50M%' OR 
                        estimated_revenue LIKE '%$20M%$50M%' OR
                        estimated_revenue = '$20M - $50M'
                    ))";
                    break;
                case 'above-50m':
                    // Look for patterns like "Above $50M", "Over $50M", "$50M+"
                    $conditions[] = "(estimated_revenue IS NOT NULL AND estimated_revenue != '' AND (
                        estimated_revenue LIKE '%Above%50M%' OR 
                        estimated_revenue LIKE '%Over%50M%' OR
                        estimated_revenue LIKE '%50M+%' OR
                        estimated_revenue = 'Above $50M' OR
                        estimated_revenue = 'Over $50M'
                    ))";
                    break;
            }
        }
        
        $this->set_cached_data($cache_key, $conditions);
        return $conditions;
    }
    /**
     * Build company size filter conditions - OPTIMIZED
     */
    private function build_size_conditions($size_filters) {
        // Cache size conditions
        $cache_key = 'cpd_size_conditions_' . md5(serialize($size_filters));
        $cached_data = $this->get_cached_data($cache_key);
        
        if ($cached_data !== false) {
            return $cached_data;
        }
        
        $conditions = array();
        
        foreach ($size_filters as $size_range) {
            switch ($size_range) {
                case '1-10':
                    $conditions[] = "(estimated_employee_count IS NOT NULL AND estimated_employee_count != '' AND CAST(REPLACE(REPLACE(estimated_employee_count, ',', ''), '+', '') AS UNSIGNED) >= 1 AND CAST(REPLACE(REPLACE(estimated_employee_count, ',', ''), '+', '') AS UNSIGNED) <= 10)";
                    break;
                case '11-20':
                    $conditions[] = "(estimated_employee_count IS NOT NULL AND estimated_employee_count != '' AND CAST(REPLACE(REPLACE(estimated_employee_count, ',', ''), '+', '') AS UNSIGNED) >= 11 AND CAST(REPLACE(REPLACE(estimated_employee_count, ',', ''), '+', '') AS UNSIGNED) <= 20)";
                    break;
                case '21-50':
                    $conditions[] = "(estimated_employee_count IS NOT NULL AND estimated_employee_count != '' AND CAST(REPLACE(REPLACE(estimated_employee_count, ',', ''), '+', '') AS UNSIGNED) >= 21 AND CAST(REPLACE(REPLACE(estimated_employee_count, ',', ''), '+', '') AS UNSIGNED) <= 50)";
                    break;
                case '51-200':
                    $conditions[] = "(estimated_employee_count IS NOT NULL AND estimated_employee_count != '' AND CAST(REPLACE(REPLACE(estimated_employee_count, ',', ''), '+', '') AS UNSIGNED) >= 51 AND CAST(REPLACE(REPLACE(estimated_employee_count, ',', ''), '+', '') AS UNSIGNED) <= 200)";
                    break;
                case '200-500':
                    $conditions[] = "(estimated_employee_count IS NOT NULL AND estimated_employee_count != '' AND CAST(REPLACE(REPLACE(estimated_employee_count, ',', ''), '+', '') AS UNSIGNED) >= 200 AND CAST(REPLACE(REPLACE(estimated_employee_count, ',', ''), '+', '') AS UNSIGNED) <= 500)";
                    break;
                case '500-1000':
                    $conditions[] = "(estimated_employee_count IS NOT NULL AND estimated_employee_count != '' AND CAST(REPLACE(REPLACE(estimated_employee_count, ',', ''), '+', '') AS UNSIGNED) >= 500 AND CAST(REPLACE(REPLACE(estimated_employee_count, ',', ''), '+', '') AS UNSIGNED) <= 1000)";
                    break;
                case '1000-5000':
                    $conditions[] = "(estimated_employee_count IS NOT NULL AND estimated_employee_count != '' AND CAST(REPLACE(REPLACE(estimated_employee_count, ',', ''), '+', '') AS UNSIGNED) >= 1000 AND CAST(REPLACE(REPLACE(estimated_employee_count, ',', ''), '+', '') AS UNSIGNED) <= 5000)";
                    break;
                case 'above-5000':
                    $conditions[] = "(estimated_employee_count IS NOT NULL AND estimated_employee_count != '' AND CAST(REPLACE(REPLACE(estimated_employee_count, ',', ''), '+', '') AS UNSIGNED) > 5000)";
                    break;
            }
        }
        
        $this->set_cached_data($cache_key, $conditions);
        return $conditions;
    }

    /**
     * Clear cache for a specific client
     */
    public function clear_client_cache($client_id) {
        $keys_to_remove = array();
        
        foreach (self::$cache as $key => $data) {
            if (strpos($key, $client_id) !== false) {
                $keys_to_remove[] = $key;
            }
        }
        
        foreach ($keys_to_remove as $key) {
            unset(self::$cache[$key]);
        }
        
        error_log("CPD Hot List: Cleared cache for client {$client_id}");
    }

    /**
     * Clear all cache
     */
    public static function clear_all_cache() {
        self::$cache = array();
        error_log("CPD Hot List: Cleared all cache");
    }

    /**
     * OPTIMIZED AJAX HANDLER - with call deduplication
     */
    public function ajax_get_hot_list_data() {
        // Check if this is a duplicate call within a short time window
        $request_signature = md5(serialize($_POST));
        $cache_key = 'ajax_request_' . $request_signature;
        
        if ($this->get_cached_data($cache_key) !== false) {
            error_log('CPD Hot List AJAX: Duplicate request detected, ignoring');
            wp_send_json_error('Duplicate request detected');
            return;
        }
        
        // Mark this request as processed
        $this->set_cached_data($cache_key, true);
        
        error_log('=== CPD Hot List AJAX Handler Called ===');
        
        try {
            // Check nonce and permissions
            if ( ! current_user_can( 'read' ) || ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'cpd_get_dashboard_data_nonce' ) ) {
                error_log('CPD Hot List AJAX: Security check failed');
                wp_send_json_error( 'Security check failed.' );
                return;
            }

            $client_id = isset( $_POST['client_id'] ) ? sanitize_text_field( $_POST['client_id'] ) : null;
            
            // Convert empty string or 'null' to actual null
            if ( empty( $client_id ) || $client_id === 'null' || $client_id === 'all' ) {
                $client_id = null;
            }
            
            // error_log('CPD Hot List AJAX: Client ID: ' . ($client_id ?? 'NULL'));

            // Check if hot list table exists first
            if ( ! class_exists( 'CPD_Hot_List_Database' ) ) {
                require_once CPD_DASHBOARD_PLUGIN_DIR . 'includes/class-cpd-hot-list-database.php';
            }
            
            $hot_list_db = new CPD_Hot_List_Database();
            if ( ! $hot_list_db->table_exists() ) {
                error_log('CPD Hot List AJAX: Hot list table does not exist');
                wp_send_json_success( array(
                    'has_settings' => false,
                    'hot_visitors' => array(),
                    'criteria_summary' => null,
                    'debug_message' => 'Hot list table not found'
                ) );
                return;
            }

            // Use singleton instance
            $hot_list = self::get_instance();

            // Check if client has hot list configured
            $has_settings = $hot_list->has_hot_list_configured( $client_id );
            // error_log('CPD Hot List AJAX: Has settings: ' . ($has_settings ? 'true' : 'false'));
            
            if ( ! $has_settings ) {
                wp_send_json_success( array(
                    'has_settings' => false,
                    'hot_visitors' => array(),
                    'criteria_summary' => null
                ) );
                return;
            }

            // Get hot leads
            // error_log('CPD Hot List AJAX: Getting hot leads...');
            $hot_visitors = $hot_list->get_hot_leads( $client_id );
            // error_log('CPD Hot List AJAX: Found ' . count($hot_visitors) . ' hot visitors');
            
            // Add required fields for display
            if ( ! empty( $hot_visitors ) ) {
                foreach ( $hot_visitors as $visitor ) {
                    // Add referrer logo information
                    if ( class_exists( 'CPD_Referrer_Logo' ) ) {
                        $visitor->referrer_logo_url = CPD_Referrer_Logo::get_logo_for_visitor( $visitor );
                        $visitor->referrer_alt_text = CPD_Referrer_Logo::get_alt_text_for_visitor( $visitor );
                        $visitor->referrer_tooltip = CPD_Referrer_Logo::get_referrer_url_for_visitor( $visitor );
                    }
                    
                    // Add AI intelligence data processing (same as ajax_get_dashboard_data)
                    $visitor_client_ai_enabled = false;
                    if ( $visitor->account_id ) {
                        $visitor_client_ai_enabled = $this->data_provider->is_client_ai_enabled( $visitor->account_id );
                    }
                    
                    $visitor->ai_intelligence_enabled = $visitor_client_ai_enabled;
                    
                    // Get intelligence status from database
                    if ( $visitor_client_ai_enabled ) {
                        global $wpdb;
                        $intelligence_table = $wpdb->prefix . 'cpd_visitor_intelligence';
                        
                        // Check if intelligence table exists
                        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$intelligence_table'") == $intelligence_table;
                        
                        if ( $table_exists ) {
                            $intelligence_status = $wpdb->get_var( 
                                $wpdb->prepare( 
                                    "SELECT status FROM $intelligence_table WHERE visitor_id = %d ORDER BY created_at DESC LIMIT 1", 
                                    $visitor->id 
                                )
                            );
                            $visitor->intelligence_status = $intelligence_status ?: 'none';
                        } else {
                            $visitor->intelligence_status = 'none';
                        }
                    } else {
                        $visitor->intelligence_status = 'disabled';
                    }
                    
                    error_log("Hot List Visitor {$visitor->id}: AI enabled = " . 
                            ($visitor->ai_intelligence_enabled ? 'true' : 'false') . 
                            ", Status = {$visitor->intelligence_status}");
                }
            }

            // Get criteria summary
            $criteria_summary = $hot_list->get_criteria_summary( $client_id );
            // error_log('CPD Hot List AJAX: Criteria summary: ' . print_r($criteria_summary, true));

            wp_send_json_success( array(
                'has_settings' => true,
                'hot_visitors' => $hot_visitors,
                'criteria_summary' => $criteria_summary,
                'cached' => false // Indicate this was a fresh request
            ) );

        } catch ( Exception $e ) {
            error_log( 'CPD Hot List AJAX Error: ' . $e->getMessage() );
            error_log( 'CPD Hot List AJAX Stack Trace: ' . $e->getTraceAsString() );
            wp_send_json_error( 'Failed to load hot list data: ' . $e->getMessage() );
        } catch ( Error $e ) {
            error_log( 'CPD Hot List AJAX Fatal Error: ' . $e->getMessage() );
            error_log( 'CPD Hot List AJAX Stack Trace: ' . $e->getTraceAsString() );
            wp_send_json_error( 'Fatal error in hot list handler: ' . $e->getMessage() );
        }
    }
}

// Helper function to clear hot list cache when settings are updated
function cpd_clear_hot_list_cache_on_settings_update($client_id) {
    $hot_list = CPD_Hot_List::get_instance();
    $hot_list->clear_client_cache($client_id);
}

?>