<?php
/**
 * Room Thresholds Database Operations
 * 
 * Handles database operations for room score thresholds
 * Global defaults and client-specific overrides
 * 
 * @package DirectReach
 * @subpackage RTR_Scoring_System
 * @since 2.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class RTR_Room_Thresholds_Database {
    
    /**
     * Room thresholds table name
     * @var string
     */
    private $table;
    
    /**
     * WordPress database object
     * @var wpdb
     */
    private $wpdb;
    
    /**
     * Default thresholds
     * @var array
     */
    private $defaults = [
        'problem_max' => 40,
        'solution_max' => 60,
        'offer_min' => 61
    ];
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'rtr_room_thresholds';
    }
    
    // ===========================================
    // GLOBAL THRESHOLDS METHODS
    // ===========================================
    
    /**
     * Get global threshold defaults
     * 
     * @return array|false Thresholds array or false
     */
    public function get_global_thresholds() {
        $thresholds = $this->wpdb->get_row(
            "SELECT problem_max, solution_max, offer_min 
             FROM {$this->table}
             WHERE client_id IS NULL",
            ARRAY_A
        );
        
        if ($thresholds === null) {
            // Return hardcoded defaults if not in DB
            return $this->defaults;
        }
        
        return [
            'problem_max' => (int)$thresholds['problem_max'],
            'solution_max' => (int)$thresholds['solution_max'],
            'offer_min' => (int)$thresholds['offer_min']
        ];
    }
    
    /**
     * Update global thresholds
     * 
     * @param array $thresholds Threshold values
     * @return bool Success
     */
    public function update_global_thresholds($thresholds) {
        // Validate thresholds
        if (!$this->validate_thresholds($thresholds)) {
            return false;
        }
        
        // Use INSERT ... ON DUPLICATE KEY UPDATE
        $result = $this->wpdb->query(
            $this->wpdb->prepare(
                "INSERT INTO {$this->table} 
                 (client_id, problem_max, solution_max, offer_min) 
                 VALUES (NULL, %d, %d, %d)
                 ON DUPLICATE KEY UPDATE 
                 problem_max = VALUES(problem_max),
                 solution_max = VALUES(solution_max),
                 offer_min = VALUES(offer_min),
                 updated_at = CURRENT_TIMESTAMP",
                $thresholds['problem_max'],
                $thresholds['solution_max'],
                $thresholds['offer_min']
            )
        );
        
        return $result !== false;
    }
    
    // ===========================================
    // CLIENT THRESHOLDS METHODS
    // ===========================================
    
    /**
     * Get client-specific thresholds
     * Falls back to global if client has no override
     * 
     * @param int $client_id Client ID
     * @return array Thresholds array
     */
    public function get_client_thresholds($client_id) {
        $thresholds = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT problem_max, solution_max, offer_min 
                 FROM {$this->table}
                 WHERE client_id = %d",
                $client_id
            ),
            ARRAY_A
        );
        
        if ($thresholds === null) {
            // No client override, return global
            return $this->get_global_thresholds();
        }
        
        return [
            'problem_max' => (int)$thresholds['problem_max'],
            'solution_max' => (int)$thresholds['solution_max'],
            'offer_min' => (int)$thresholds['offer_min']
        ];
    }
    
    /**
     * Get effective thresholds with metadata
     * Returns thresholds and indicates if custom or global
     * 
     * @param int $client_id Client ID
     * @return array Thresholds with metadata
     */
    public function get_effective_thresholds($client_id) {
        $client_thresholds = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT problem_max, solution_max, offer_min 
                 FROM {$this->table}
                 WHERE client_id = %d",
                $client_id
            ),
            ARRAY_A
        );
        
        $is_custom = ($client_thresholds !== null);
        
        if (!$is_custom) {
            // Use global
            $thresholds = $this->get_global_thresholds();
        } else {
            $thresholds = [
                'problem_max' => (int)$client_thresholds['problem_max'],
                'solution_max' => (int)$client_thresholds['solution_max'],
                'offer_min' => (int)$client_thresholds['offer_min']
            ];
        }
        
        return [
            'thresholds' => $thresholds,
            'is_custom' => $is_custom,
            'source' => $is_custom ? 'client' : 'global'
        ];
    }
    
    /**
     * Save client-specific thresholds
     * 
     * @param int $client_id Client ID
     * @param array $thresholds Threshold values
     * @return bool Success
     */
    public function save_client_thresholds($client_id, $thresholds) {
        // Validate thresholds
        if (!$this->validate_thresholds($thresholds)) {
            return false;
        }
        
        // Upsert
        $result = $this->wpdb->query(
            $this->wpdb->prepare(
                "INSERT INTO {$this->table} 
                 (client_id, problem_max, solution_max, offer_min) 
                 VALUES (%d, %d, %d, %d)
                 ON DUPLICATE KEY UPDATE 
                 problem_max = VALUES(problem_max),
                 solution_max = VALUES(solution_max),
                 offer_min = VALUES(offer_min),
                 updated_at = CURRENT_TIMESTAMP",
                $client_id,
                $thresholds['problem_max'],
                $thresholds['solution_max'],
                $thresholds['offer_min']
            )
        );
        
        return $result !== false;
    }
    
    /**
     * Delete client thresholds (reset to global)
     * 
     * @param int $client_id Client ID
     * @return bool Success
     */
    public function delete_client_thresholds($client_id) {
        $result = $this->wpdb->delete(
            $this->table,
            ['client_id' => $client_id],
            ['%d']
        );
        
        return $result !== false;
    }
    
    /**
     * Check if client has custom thresholds
     * 
     * @param int $client_id Client ID
     * @return bool
     */
    public function has_client_override($client_id) {
        $count = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) 
                 FROM {$this->table}
                 WHERE client_id = %d",
                $client_id
            )
        );
        
        return $count > 0;
    }
    
    // ===========================================
    // ROOM ASSIGNMENT METHODS
    // ===========================================
    
    /**
     * Determine room based on score and thresholds
     * 
     * @param int $score Lead score
     * @param int $client_id Client ID (optional)
     * @return string Room type: 'none', 'problem', 'solution', 'offer'
     */
    public function get_room_for_score($score, $client_id = null) {
        if ($score < 0) {
            return 'none';
        }
        
        // Get appropriate thresholds
        if ($client_id !== null) {
            $thresholds = $this->get_client_thresholds($client_id);
        } else {
            $thresholds = $this->get_global_thresholds();
        }
        
        // Determine room
        if ($score <= $thresholds['problem_max']) {
            return $score > 0 ? 'problem' : 'none';
        } elseif ($score <= $thresholds['solution_max']) {
            return 'solution';
        } else {
            return 'offer';
        }
    }
    
    /**
     * Check if score meets minimum for a room
     * 
     * @param int $score Lead score
     * @param string $room_type Target room
     * @param int $client_id Client ID (optional)
     * @return bool
     */
    public function meets_room_threshold($score, $room_type, $client_id = null) {
        if ($client_id !== null) {
            $thresholds = $this->get_client_thresholds($client_id);
        } else {
            $thresholds = $this->get_global_thresholds();
        }
        
        switch ($room_type) {
            case 'problem':
                return $score > 0 && $score <= $thresholds['problem_max'];
            
            case 'solution':
                return $score > $thresholds['problem_max'] && 
                       $score <= $thresholds['solution_max'];
            
            case 'offer':
                return $score >= $thresholds['offer_min'];
            
            default:
                return false;
        }
    }
    
    /**
     * Get score range for a room
     * 
     * @param string $room_type Room type
     * @param int $client_id Client ID (optional)
     * @return array ['min' => X, 'max' => Y]
     */
    public function get_room_score_range($room_type, $client_id = null) {
        if ($client_id !== null) {
            $thresholds = $this->get_client_thresholds($client_id);
        } else {
            $thresholds = $this->get_global_thresholds();
        }
        
        switch ($room_type) {
            case 'problem':
                return [
                    'min' => 1,
                    'max' => $thresholds['problem_max']
                ];
            
            case 'solution':
                return [
                    'min' => $thresholds['problem_max'] + 1,
                    'max' => $thresholds['solution_max']
                ];
            
            case 'offer':
                return [
                    'min' => $thresholds['offer_min'],
                    'max' => 999 // Effectively unlimited
                ];
            
            default:
                return ['min' => 0, 'max' => 0];
        }
    }
    
    // ===========================================
    // VALIDATION METHODS
    // ===========================================
    
    /**
     * Validate threshold values
     * Ensures proper ordering: problem_max < solution_max < offer_min
     * 
     * @param array $thresholds Threshold values
     * @return bool
     */
    private function validate_thresholds($thresholds) {
        // Check required keys
        $required = ['problem_max', 'solution_max', 'offer_min'];
        foreach ($required as $key) {
            if (!isset($thresholds[$key])) {
                return false;
            }
        }
        
        // Ensure all are positive integers
        if ($thresholds['problem_max'] < 1 || 
            $thresholds['solution_max'] < 1 || 
            $thresholds['offer_min'] < 1) {
            return false;
        }
        
        // Ensure proper ordering
        if ($thresholds['problem_max'] >= $thresholds['solution_max']) {
            return false;
        }
        
        if ($thresholds['solution_max'] >= $thresholds['offer_min']) {
            return false;
        }
        
        return true;
    }
    
    // ===========================================
    // UTILITY METHODS
    // ===========================================
    
    /**
     * Get statistics about threshold usage
     * 
     * @return array Statistics
     */
    public function get_statistics() {
        // Count clients with custom thresholds
        $clients_with_custom = $this->wpdb->get_var(
            "SELECT COUNT(*) 
             FROM {$this->table}
             WHERE client_id IS NOT NULL"
        );
        
        // Get global thresholds
        $global = $this->get_global_thresholds();
        
        return [
            'clients_with_custom_thresholds' => (int)$clients_with_custom,
            'global_thresholds' => $global
        ];
    }
    
    /**
     * Copy thresholds from one client to another
     * 
     * @param int $source_client_id Source client ID
     * @param int $target_client_id Target client ID
     * @return bool Success
     */
    public function copy_client_thresholds($source_client_id, $target_client_id) {
        $source_thresholds = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT problem_max, solution_max, offer_min 
                 FROM {$this->table}
                 WHERE client_id = %d",
                $source_client_id
            ),
            ARRAY_A
        );
        
        if ($source_thresholds === null) {
            return false;
        }
        
        return $this->save_client_thresholds($target_client_id, [
            'problem_max' => (int)$source_thresholds['problem_max'],
            'solution_max' => (int)$source_thresholds['solution_max'],
            'offer_min' => (int)$source_thresholds['offer_min']
        ]);
    }
    
    /**
     * Get last updated timestamp for client thresholds
     * 
     * @param int $client_id Client ID
     * @return string|null Datetime string or null
     */
    public function get_last_updated($client_id) {
        return $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT updated_at 
                 FROM {$this->table}
                 WHERE client_id = %d",
                $client_id
            )
        );
    }
    
    /**
     * Validate threshold transition
     * Check if moving from old to new thresholds is safe
     * 
     * @param array $old_thresholds Old thresholds
     * @param array $new_thresholds New thresholds
     * @return array ['safe' => bool, 'warnings' => array]
     */
    public function validate_threshold_change($old_thresholds, $new_thresholds) {
        $warnings = [];
        
        // Check for major shifts
        $problem_change = abs($new_thresholds['problem_max'] - $old_thresholds['problem_max']);
        $solution_change = abs($new_thresholds['solution_max'] - $old_thresholds['solution_max']);
        
        if ($problem_change > 10) {
            $warnings[] = "Problem room threshold changed by {$problem_change} points";
        }
        
        if ($solution_change > 10) {
            $warnings[] = "Solution room threshold changed by {$solution_change} points";
        }
        
        // Check for room collapse
        if (($new_thresholds['solution_max'] - $new_thresholds['problem_max']) < 10) {
            $warnings[] = "Solution room range is very narrow (< 10 points)";
        }
        
        return [
            'safe' => empty($warnings),
            'warnings' => $warnings
        ];
    }
}