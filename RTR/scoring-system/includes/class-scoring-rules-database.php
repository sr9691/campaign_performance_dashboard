<?php
/**
 * Scoring Rules Database Operations
 * 
 * Handles all database operations for scoring rules (global and client-specific)
 * 
 * @package DirectReach
 * @subpackage RTR_Scoring_System
 * @since 2.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class RTR_Scoring_Rules_Database {
    
    /**
     * Global scoring rules table name
     * @var string
     */
    private $global_table;
    
    /**
     * Client scoring rules table name
     * @var string
     */
    private $client_table;
    
    /**
     * WordPress database object
     * @var wpdb
     */
    private $wpdb;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->global_table = $wpdb->prefix . 'rtr_global_scoring_rules';
        $this->client_table = $wpdb->prefix . 'rtr_client_scoring_rules';
    }
    
    // ===========================================
    // GLOBAL SCORING RULES METHODS
    // ===========================================
    
    /**
     * Get global rules for all rooms
     * 
     * @return array|false Array of rules by room type or false on error
     */
    public function get_all_global_rules() {
        $results = $this->wpdb->get_results(
            "SELECT room_type, rules_config 
             FROM {$this->global_table}
             ORDER BY 
                FIELD(room_type, 'problem', 'solution', 'offer')",
            ARRAY_A
        );
        
        if ($results === false) {
            return false;
        }
        
        // Format as associative array
        $rules = [];
        foreach ($results as $row) {
            $rules[$row['room_type']] = json_decode($row['rules_config'], true);
        }
        
        return $rules;
    }
    
    /**
     * Get global rules for specific room
     * 
     * @param string $room_type 'problem', 'solution', or 'offer'
     * @return array|false Decoded rules array or false
     */
    public function get_global_rules_for_room($room_type) {
        if (!$this->validate_room_type($room_type)) {
            return false;
        }
        
        $rules_json = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT rules_config 
                 FROM {$this->global_table} 
                 WHERE room_type = %s",
                $room_type
            )
        );
        
        if ($rules_json === null) {
            return false;
        }
        
        return json_decode($rules_json, true);
    }
    
    /**
     * Update global rules for specific room
     * 
     * @param string $room_type Room type
     * @param array $rules_config Rules configuration array
     * @return bool Success
     */
    public function update_global_rules($room_type, $rules_config) {
        if (!$this->validate_room_type($room_type)) {
            return false;
        }
        
        // Validate rules structure
        if (!$this->validate_rules_structure($room_type, $rules_config)) {
            return false;
        }
        
        $rules_json = json_encode($rules_config);
        
        // Use INSERT ... ON DUPLICATE KEY UPDATE for upsert
        $result = $this->wpdb->query(
            $this->wpdb->prepare(
                "INSERT INTO {$this->global_table} 
                 (room_type, rules_config) 
                 VALUES (%s, %s)
                 ON DUPLICATE KEY UPDATE 
                 rules_config = VALUES(rules_config),
                 updated_at = CURRENT_TIMESTAMP",
                $room_type,
                $rules_json
            )
        );
        
        return $result !== false;
    }
    
    // ===========================================
    // CLIENT SCORING RULES METHODS
    // ===========================================
    
    /**
     * Get client rules for all rooms
     * If client has no custom rules, returns false
     * 
     * @param int $client_id Client ID
     * @return array|false Array of rules by room type or false
     */
    public function get_client_rules($client_id) {
        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT room_type, rules_config 
                 FROM {$this->client_table}
                 WHERE client_id = %d
                 ORDER BY 
                    FIELD(room_type, 'problem', 'solution', 'offer')",
                $client_id
            ),
            ARRAY_A
        );
        
        if (empty($results)) {
            return false;
        }
        
        // Format as associative array
        $rules = [];
        foreach ($results as $row) {
            $rules[$row['room_type']] = json_decode($row['rules_config'], true);
        }
        
        return $rules;
    }
    
    /**
     * Get client rules for specific room
     * Falls back to global if client has no override
     * 
     * @param int $client_id Client ID
     * @param string $room_type Room type
     * @return array|false Rules array or false
     */
    public function get_client_rules_for_room($client_id, $room_type) {
        if (!$this->validate_room_type($room_type)) {
            return false;
        }
        
        $rules_json = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT rules_config 
                 FROM {$this->client_table}
                 WHERE client_id = %d AND room_type = %s",
                $client_id,
                $room_type
            )
        );
        
        if ($rules_json === null) {
            // No client override, return global
            return $this->get_global_rules_for_room($room_type);
        }
        
        return json_decode($rules_json, true);
    }
    
    /**
     * Get effective rules for client (with global indicators)
     * Returns rules for all rooms with metadata about source
     * 
     * @param int $client_id Client ID
     * @return array Rules with metadata
     */
    public function get_effective_rules($client_id) {
        $client_rules = $this->get_client_rules($client_id);
        $global_rules = $this->get_all_global_rules();
        
        $effective = [];
        
        foreach (['problem', 'solution', 'offer'] as $room) {
            if (isset($client_rules[$room])) {
                $effective[$room] = [
                    'rules' => $client_rules[$room],
                    'is_custom' => true,
                    'source' => 'client'
                ];
            } else {
                $effective[$room] = [
                    'rules' => $global_rules[$room] ?? [],
                    'is_custom' => false,
                    'source' => 'global'
                ];
            }
        }
        
        return $effective;
    }
    
    /**
     * Save client rules for specific room
     * 
     * @param int $client_id Client ID
     * @param string $room_type Room type
     * @param array $rules_config Rules configuration
     * @return bool Success
     */
    public function save_client_rules($client_id, $room_type, $rules_config) {
        if (!$this->validate_room_type($room_type)) {
            return false;
        }
        
        // Validate rules structure
        if (!$this->validate_rules_structure($room_type, $rules_config)) {
            return false;
        }
        
        $rules_json = json_encode($rules_config);
        
        // Upsert
        $result = $this->wpdb->query(
            $this->wpdb->prepare(
                "INSERT INTO {$this->client_table} 
                 (client_id, room_type, rules_config) 
                 VALUES (%d, %s, %s)
                 ON DUPLICATE KEY UPDATE 
                 rules_config = VALUES(rules_config),
                 updated_at = CURRENT_TIMESTAMP",
                $client_id,
                $room_type,
                $rules_json
            )
        );
        
        return $result !== false;
    }
    
    /**
     * Delete all client rules (reset all to global)
     * This is the version called by the controller
     * 
     * @param int $client_id Client ID
     * @return bool Success
     */
    public function delete_client_rules($client_id) {
        $result = $this->wpdb->delete(
            $this->client_table,
            ['client_id' => $client_id],
            ['%d']
        );
        
        return $result !== false;
    }
    
    /**
     * Delete client rules for specific room (reset to global)
     * 
     * @param int $client_id Client ID
     * @param string $room_type Room type
     * @return bool Success
     */
    public function delete_client_rules_for_room($client_id, $room_type) {
        if (!$this->validate_room_type($room_type)) {
            return false;
        }
        
        $result = $this->wpdb->delete(
            $this->client_table,
            [
                'client_id' => $client_id,
                'room_type' => $room_type
            ],
            ['%d', '%s']
        );
        
        return $result !== false;
    }    
    
    // ===========================================
    // VALIDATION METHODS
    // ===========================================
    
    /**
     * Validate room type
     * 
     * @param string $room_type
     * @return bool
     */
    private function validate_room_type($room_type) {
        return in_array($room_type, ['problem', 'solution', 'offer']);
    }
    
    /**
     * Validate rules structure based on room type
     * 
     * @param string $room_type
     * @param array $rules_config
     * @return bool
     */
    private function validate_rules_structure($room_type, $rules_config) {
        if (!is_array($rules_config)) {
            return false;
        }
        
        // Define required keys for each room
        $required_keys = [
            'problem' => [
                'revenue', 'company_size', 'industry_alignment', 
                'target_states', 'visited_target_pages', 'multiple_visits',
                'role_match', 'minimum_threshold'
            ],
            'solution' => [
                'email_open', 'email_click', 'email_multiple_click',
                'page_visit', 'key_page_visit', 'ad_engagement'
            ],
            'offer' => [
                'demo_request', 'contact_form', 'pricing_page',
                'pricing_question', 'partner_referral', 'webinar_attendance'
            ]
        ];
        
        if (!isset($required_keys[$room_type])) {
            return false;
        }
        
        // Check all required keys exist
        foreach ($required_keys[$room_type] as $key) {
            if (!isset($rules_config[$key])) {
                return false;
            }
            
            // Each rule must have 'enabled' field
            if (!isset($rules_config[$key]['enabled'])) {
                return false;
            }
        }
        
        // Additional validation for industry_alignment exclusion
        if ($room_type === 'problem' && isset($rules_config['industry_alignment'])) {
            $industry_rule = $rules_config['industry_alignment'];
            
            // If both values and excluded_values exist, ensure no overlap
            if (!empty($industry_rule['values']) && !empty($industry_rule['excluded_values'])) {
                $overlap = array_intersect($industry_rule['values'], $industry_rule['excluded_values']);
                if (!empty($overlap)) {
                    error_log('RTR Scoring Rules: Industry cannot be in both match and exclude lists: ' . implode(', ', $overlap));
                    return false;
                }
            }
            
            // Validate exclusion_points is negative or zero
            if (isset($industry_rule['exclusion_points']) && $industry_rule['exclusion_points'] > 0) {
                error_log('RTR Scoring Rules: exclusion_points must be zero or negative');
                return false;
            }
        }
        
        return true;
    }
    
    // ===========================================
    // UTILITY METHODS
    // ===========================================
    
    /**
     * Get statistics about rule usage
     * 
     * @return array Statistics
     */
    public function get_statistics() {
        // Count clients with custom rules
        $clients_with_custom = $this->wpdb->get_var(
            "SELECT COUNT(DISTINCT client_id) FROM {$this->client_table}"
        );
        
        // Count total custom rules
        $total_custom_rules = $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->client_table}"
        );
        
        // Count by room
        $by_room = $this->wpdb->get_results(
            "SELECT room_type, COUNT(*) as count 
             FROM {$this->client_table}
             GROUP BY room_type",
            ARRAY_A
        );
        
        $room_counts = [];
        foreach ($by_room as $row) {
            $room_counts[$row['room_type']] = (int)$row['count'];
        }
        
        return [
            'clients_with_custom_rules' => (int)$clients_with_custom,
            'total_custom_rules' => (int)$total_custom_rules,
            'custom_rules_by_room' => $room_counts
        ];
    }
    
    /**
     * Copy rules from one client to another
     * Useful for template/cloning scenarios
     * 
     * @param int $source_client_id Source client ID
     * @param int $target_client_id Target client ID
     * @return bool Success
     */
    public function copy_client_rules($source_client_id, $target_client_id) {
        $source_rules = $this->get_client_rules($source_client_id);
        
        if ($source_rules === false) {
            return false;
        }
        
        $success = true;
        foreach ($source_rules as $room_type => $rules) {
            if (!$this->save_client_rules($target_client_id, $room_type, $rules)) {
                $success = false;
            }
        }
        
        return $success;
    }

    /**
     * Alias for get_global_rules_for_room for controller compatibility
     * 
     * @param string $room_type Room type
     * @return array|false Rules array or false
     */
    public function get_global_rules($room_type) {
        return $this->get_global_rules_for_room($room_type);
    }    
    
    /**
     * Get last updated timestamp for client rules
     * 
     * @param int $client_id Client ID
     * @return string|null Datetime string or null
     */
    public function get_last_updated($client_id) {
        return $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT MAX(updated_at) 
                 FROM {$this->client_table}
                 WHERE client_id = %d",
                $client_id
            )
        );
    }
}