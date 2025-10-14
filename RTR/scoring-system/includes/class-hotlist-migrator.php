<?php
/**
 * Hot List to Problem Room Migrator
 * 
 * Handles one-time migration of Hot List settings to Problem Room scoring rules
 * when a client upgrades to Premium tier
 * 
 * @package DirectReach
 * @subpackage RTR_Scoring_System
 * @since 2.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class RTR_Hotlist_Migrator {
    
    /**
     * WordPress database object
     * @var wpdb
     */
    private $wpdb;
    
    /**
     * Scoring rules database handler
     * @var RTR_Scoring_Rules_Database
     */
    private $scoring_rules_db;
    
    /**
     * Migration flag option prefix
     * @var string
     */
    private $flag_prefix = 'cpd_hotlist_migrated_';
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        
        // Get scoring rules database handler
        $system = directreach_scoring_system();
        $this->scoring_rules_db = $system->get_scoring_rules_db();
        
        // Hook into client tier changes
        add_action('cpd_client_tier_changed', [$this, 'maybe_migrate_on_upgrade'], 10, 3);
    }
    
    // ===========================================
    // MIGRATION TRIGGER METHODS
    // ===========================================
    
    /**
     * Check if migration should run when client tier changes
     * Hook: cpd_client_tier_changed
     * 
     * @param int $client_id Client ID
     * @param string $old_tier Previous tier
     * @param string $new_tier New tier
     */
    public function maybe_migrate_on_upgrade($client_id, $old_tier, $new_tier) {
        // Only migrate when upgrading to Premium
        if ($new_tier !== 'premium') {
            return;
        }
        
        // Check if already migrated
        if ($this->is_migrated($client_id)) {
            return;
        }
        
        // Perform migration
        $this->migrate_client($client_id);
    }
    
    /**
     * Manually trigger migration for a client
     * For admin use or batch operations
     * 
     * @param int $client_id Client ID
     * @return array Migration result
     */
    public function migrate_client($client_id) {
        // Verify client exists and is Premium
        if (!$this->validate_client($client_id)) {
            return [
                'success' => false,
                'error' => 'Client not found or not Premium tier'
            ];
        }
        
        // Check if already migrated
        if ($this->is_migrated($client_id)) {
            return [
                'success' => false,
                'error' => 'Hot List already migrated for this client',
                'already_migrated' => true
            ];
        }
        
        // Load Hot List settings
        $hotlist_settings = $this->get_hotlist_settings($client_id);
        
        // Build Problem Room rules
        if ($hotlist_settings === false || empty($hotlist_settings)) {
            // No Hot List settings found - use global defaults
            $problem_rules = $this->get_default_problem_rules();
            $source = 'global_defaults';
        } else {
            // Map Hot List settings to Problem Room rules
            $problem_rules = $this->map_hotlist_to_rules($hotlist_settings);
            $source = 'hotlist_migration';
        }
        
        // Save Problem Room rules for client
        $saved = $this->scoring_rules_db->save_client_rules(
            $client_id, 
            'problem', 
            $problem_rules
        );
        
        if (!$saved) {
            return [
                'success' => false,
                'error' => 'Failed to save migrated rules'
            ];
        }
        
        // Set migration flag
        $this->set_migration_flag($client_id);
        
        // Log the migration
        $this->log_migration($client_id, $source, $problem_rules);
        
        return [
            'success' => true,
            'client_id' => $client_id,
            'source' => $source,
            'migrated_at' => current_time('mysql'),
            'rules_count' => count($problem_rules)
        ];
    }
    
    // ===========================================
    // HOT LIST READING METHODS
    // ===========================================
    
    /**
     * Get Hot List settings for a client
     * 
     * @param int $client_id Client ID
     * @return array|false Hot List settings or false
     */
    private function get_hotlist_settings($client_id) {
        // Hot List settings are stored in wp_cpd_clients table
        $settings = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT 
                    revenue_filters,
                    company_size_filters,
                    industry_filters,
                    state_filters,
                    required_matches
                 FROM {$this->wpdb->prefix}cpd_clients
                 WHERE id = %d",
                $client_id
            ),
            ARRAY_A
        );
        
        if ($settings === null) {
            return false;
        }
        
        // Check if any Hot List settings exist
        $has_settings = false;
        foreach ($settings as $value) {
            if (!empty($value)) {
                $has_settings = true;
                break;
            }
        }
        
        return $has_settings ? $settings : false;
    }
    
    // ===========================================
    // MAPPING METHODS
    // ===========================================
    
    /**
     * Map Hot List settings to Problem Room rules
     * 
     * @param array $hotlist_settings Hot List settings from database
     * @return array Problem Room rules configuration
     */
    private function map_hotlist_to_rules($hotlist_settings) {
        // Start with default Problem Room structure
        $rules = $this->get_default_problem_rules();
        
        // Map Revenue Filters
        if (!empty($hotlist_settings['revenue_filters'])) {
            $revenue_values = $this->parse_filter_values($hotlist_settings['revenue_filters']);
            if (!empty($revenue_values)) {
                $rules['revenue']['enabled'] = true;
                $rules['revenue']['values'] = $revenue_values;
            }
        }
        
        // Map Company Size Filters
        if (!empty($hotlist_settings['company_size_filters'])) {
            $size_values = $this->parse_filter_values($hotlist_settings['company_size_filters']);
            if (!empty($size_values)) {
                $rules['company_size']['enabled'] = true;
                $rules['company_size']['values'] = $size_values;
            }
        }
        
        // Map Industry Filters
        if (!empty($hotlist_settings['industry_filters'])) {
            $industry_values = $this->parse_filter_values($hotlist_settings['industry_filters']);
            if (!empty($industry_values)) {
                $rules['industry_alignment']['enabled'] = true;
                $rules['industry_alignment']['values'] = $industry_values;
            }
        }
        
        // Map State Filters
        if (!empty($hotlist_settings['state_filters'])) {
            $state_values = $this->parse_filter_values($hotlist_settings['state_filters']);
            if (!empty($state_values)) {
                $rules['target_states']['enabled'] = true;
                $rules['target_states']['values'] = $state_values;
            }
        }
        
        // Map Required Matches to Minimum Threshold
        if (!empty($hotlist_settings['required_matches'])) {
            $required = (int)$hotlist_settings['required_matches'];
            if ($required > 0) {
                // Convert required matches to approximate score
                // Each match = ~10 points average
                $min_score = $required * 10;
                $rules['minimum_threshold']['enabled'] = true;
                $rules['minimum_threshold']['required_score'] = min($min_score, 40); // Cap at problem_max
            }
        }
        
        return $rules;
    }
    
    /**
     * Parse filter values from Hot List format
     * Hot List stores as JSON or serialized arrays
     * 
     * @param mixed $filter_value Raw filter value from database
     * @return array Parsed values
     */
    private function parse_filter_values($filter_value) {
        if (empty($filter_value)) {
            return [];
        }
        
        // Try JSON decode first
        $values = json_decode($filter_value, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($values)) {
            return array_values($values);
        }
        
        // Try unserialize
        $values = @unserialize($filter_value);
        if (is_array($values)) {
            return array_values($values);
        }
        
        // Try comma-separated string
        if (is_string($filter_value)) {
            $values = explode(',', $filter_value);
            return array_map('trim', array_filter($values));
        }
        
        return [];
    }
    
    /**
     * Get default Problem Room rules structure
     * Used when client has no Hot List settings
     * 
     * @return array Default Problem Room rules
     */
    private function get_default_problem_rules() {
        return [
            'revenue' => [
                'enabled' => false,
                'points' => 10,
                'values' => []
            ],
            'company_size' => [
                'enabled' => false,
                'points' => 10,
                'values' => []
            ],
            'industry_alignment' => [
                'enabled' => false,
                'points' => 15,
                'values' => []
            ],
            'target_states' => [
                'enabled' => false,
                'points' => 5,
                'values' => []
            ],
            'visited_target_pages' => [
                'enabled' => false,
                'points' => 10,
                'max_points' => 30
            ],
            'multiple_visits' => [
                'enabled' => true,
                'points' => 5,
                'minimum_visits' => 2
            ],
            'role_match' => [
                'enabled' => false,
                'points' => 5,
                'target_roles' => [
                    'decision_makers' => ['CEO', 'President', 'Director', 'VP', 'Chief'],
                    'technical' => ['Engineer', 'Developer', 'CTO', 'Architect'],
                    'marketing' => ['Marketing', 'CMO', 'Brand', 'Content'],
                    'sales' => ['Sales', 'Business Development', 'Account']
                ],
                'match_type' => 'contains'
            ],
            'minimum_threshold' => [
                'enabled' => true,
                'required_score' => 20
            ]
        ];
    }
    
    // ===========================================
    // MIGRATION FLAG METHODS
    // ===========================================
    
    /**
     * Check if client has been migrated
     * 
     * @param int $client_id Client ID
     * @return bool
     */
    public function is_migrated($client_id) {
        return (bool)get_option($this->flag_prefix . $client_id, false);
    }
    
    /**
     * Set migration flag for client
     * 
     * @param int $client_id Client ID
     * @return bool Success
     */
    private function set_migration_flag($client_id) {
        return update_option(
            $this->flag_prefix . $client_id,
            [
                'migrated' => true,
                'migrated_at' => current_time('mysql'),
                'version' => RTR_SCORING_VERSION
            ],
            false // Don't autoload
        );
    }
    
    /**
     * Clear migration flag (for testing or re-migration)
     * Admin only
     * 
     * @param int $client_id Client ID
     * @return bool Success
     */
    public function clear_migration_flag($client_id) {
        if (!current_user_can('manage_options')) {
            return false;
        }
        
        return delete_option($this->flag_prefix . $client_id);
    }
    
    /**
     * Get migration info for client
     * 
     * @param int $client_id Client ID
     * @return array|false Migration info or false
     */
    public function get_migration_info($client_id) {
        return get_option($this->flag_prefix . $client_id, false);
    }
    
    // ===========================================
    // VALIDATION METHODS
    // ===========================================
    
    /**
     * Validate client exists and is Premium
     * 
     * @param int $client_id Client ID
     * @return bool
     */
    private function validate_client($client_id) {
        $client = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT id, subscription_tier 
                 FROM {$this->wpdb->prefix}cpd_clients
                 WHERE id = %d",
                $client_id
            )
        );
        
        if ($client === null) {
            return false;
        }
        
        return ($client->subscription_tier === 'premium');
    }
    
    // ===========================================
    // BATCH OPERATIONS
    // ===========================================
    
    /**
     * Migrate all Premium clients that haven't been migrated
     * For admin use only
     * 
     * @return array Results
     */
    public function batch_migrate_all() {
        if (!current_user_can('manage_options')) {
            return [
                'success' => false,
                'error' => 'Insufficient permissions'
            ];
        }
        
        // Get all Premium clients
        $clients = $this->wpdb->get_col(
            "SELECT id 
             FROM {$this->wpdb->prefix}cpd_clients
             WHERE subscription_tier = 'premium'"
        );
        
        if (empty($clients)) {
            return [
                'success' => true,
                'migrated' => 0,
                'message' => 'No Premium clients found'
            ];
        }
        
        $results = [
            'success' => true,
            'total' => count($clients),
            'migrated' => 0,
            'skipped' => 0,
            'failed' => 0,
            'details' => []
        ];
        
        foreach ($clients as $client_id) {
            if ($this->is_migrated($client_id)) {
                $results['skipped']++;
                continue;
            }
            
            $result = $this->migrate_client($client_id);
            
            if ($result['success']) {
                $results['migrated']++;
            } else {
                $results['failed']++;
            }
            
            $results['details'][$client_id] = $result;
        }
        
        return $results;
    }
    
    /**
     * Get migration status for all Premium clients
     * 
     * @return array Status report
     */
    public function get_migration_status() {
        // Count total Premium clients
        $total_premium = $this->wpdb->get_var(
            "SELECT COUNT(*) 
             FROM {$this->wpdb->prefix}cpd_clients
             WHERE subscription_tier = 'premium'"
        );
        
        // Count migrated clients
        $migrated = 0;
        $premium_clients = $this->wpdb->get_col(
            "SELECT id 
             FROM {$this->wpdb->prefix}cpd_clients
             WHERE subscription_tier = 'premium'"
        );
        
        foreach ($premium_clients as $client_id) {
            if ($this->is_migrated($client_id)) {
                $migrated++;
            }
        }
        
        return [
            'total_premium_clients' => (int)$total_premium,
            'migrated_clients' => $migrated,
            'pending_migration' => (int)$total_premium - $migrated,
            'completion_rate' => $total_premium > 0 
                ? round(($migrated / $total_premium) * 100, 2) 
                : 0
        ];
    }
    
    // ===========================================
    // LOGGING METHODS
    // ===========================================
    
    /**
     * Log migration event
     * 
     * @param int $client_id Client ID
     * @param string $source Migration source
     * @param array $rules Migrated rules
     */
    private function log_migration($client_id, $source, $rules) {
        // Count enabled rules
        $enabled_count = 0;
        foreach ($rules as $rule) {
            if (isset($rule['enabled']) && $rule['enabled']) {
                $enabled_count++;
            }
        }
        
        error_log(sprintf(
            'DirectReach Hot List Migration: Client %d | Source: %s | Enabled Rules: %d',
            $client_id,
            $source,
            $enabled_count
        ));
        
        // Store detailed log in database (optional)
        $this->store_migration_log($client_id, $source, $rules);
    }
    
    /**
     * Store migration log in database
     * Optional: Can be used for audit trail
     * 
     * @param int $client_id Client ID
     * @param string $source Migration source
     * @param array $rules Migrated rules
     */
    private function store_migration_log($client_id, $source, $rules) {
        // Store in wp_options for audit trail
        $log_key = 'cpd_hotlist_migration_log_' . $client_id;
        
        $log_data = [
            'client_id' => $client_id,
            'source' => $source,
            'migrated_at' => current_time('mysql'),
            'rules_snapshot' => $rules,
            'version' => RTR_SCORING_VERSION
        ];
        
        update_option($log_key, $log_data, false);
    }
    
    /**
     * Get migration log for client
     * 
     * @param int $client_id Client ID
     * @return array|false Migration log or false
     */
    public function get_migration_log($client_id) {
        $log_key = 'cpd_hotlist_migration_log_' . $client_id;
        return get_option($log_key, false);
    }
}

/**
 * Initialize Hot List Migrator
 * 
 * @return RTR_Hotlist_Migrator
 */
function rtr_hotlist_migrator() {
    static $instance = null;
    
    if ($instance === null) {
        $instance = new RTR_Hotlist_Migrator();
    }
    
    return $instance;
}

// Initialize on plugins_loaded
add_action('plugins_loaded', 'rtr_hotlist_migrator', 20);