<?php
/**
 * Workflow Manager Class
 * Handles 3-step workflow state management for Campaign Builder
 *
 * @package DirectReach_Campaign_Builder
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class DR_Workflow_Manager {
    
    /**
     * Workflow state table name
     * @var string
     */
    private $table_name;
    
    /**
     * Valid workflow steps
     * @var array
     */
    private $valid_steps = ['client', 'campaign', 'templates'];
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'dr_workflows';
    }
    
    /**
     * Get workflow state for current user
     *
     * @param int $user_id WordPress user ID
     * @return array|null Workflow state or null if not found
     */
    public function get_workflow_state($user_id) {
        global $wpdb;
        
        $state = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE user_id = %d ORDER BY updated_at DESC LIMIT 1",
                $user_id
            ),
            ARRAY_A
        );
        
        if (!$state) {
            return $this->get_default_state();
        }
        
        // Parse JSON data
        $state['data'] = json_decode($state['data'], true);
        
        return $state;
    }
    
    /**
     * Save workflow state
     *
     * @param int $user_id WordPress user ID
     * @param array $state_data Workflow state data
     * @return bool Success status
     */
    public function save_workflow_state($user_id, $state_data) {
        global $wpdb;
        
        // Validate step
        if (!in_array($state_data['currentStep'], $this->valid_steps)) {
            return false;
        }
        
        // Prepare data for storage
        $workflow_data = [
            'user_id' => $user_id,
            'client_id' => isset($state_data['clientId']) ? $state_data['clientId'] : null,
            'campaign_id' => isset($state_data['campaignId']) ? $state_data['campaignId'] : null,
            'current_step' => $state_data['currentStep'],
            'data' => wp_json_encode($state_data)
        ];
        
        // Check if workflow exists
        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$this->table_name} WHERE user_id = %d",
                $user_id
            )
        );
        
        if ($existing) {
            // Update existing workflow
            $result = $wpdb->update(
                $this->table_name,
                $workflow_data,
                ['user_id' => $user_id],
                ['%d', '%d', '%d', '%s', '%s'],
                ['%d']
            );
        } else {
            // Insert new workflow
            $result = $wpdb->insert(
                $this->table_name,
                $workflow_data,
                ['%d', '%d', '%d', '%s', '%s']
            );
        }
        
        return $result !== false;
    }
    
    /**
     * Validate step completion
     *
     * @param string $step Step name
     * @param array $state_data Current state data
     * @return array Validation result with 'valid' and 'errors' keys
     */
    public function validate_step($step, $state_data) {
        $errors = [];
        
        switch ($step) {
            case 'client':
                if (empty($state_data['clientId'])) {
                    $errors[] = 'Please select a client';
                }
                break;
                
            case 'campaign':
                if (empty($state_data['campaignId'])) {
                    $errors[] = 'Please create or select a campaign';
                }
                if (empty($state_data['campaignName'])) {
                    $errors[] = 'Campaign name is required';
                }
                if (empty($state_data['utmCampaign'])) {
                    $errors[] = 'UTM campaign parameter is required';
                }
                break;
                
            case 'templates':
                // Validate that at least one template exists per room
                $required_rooms = ['problem', 'solution', 'offer'];
                foreach ($required_rooms as $room) {
                    if (empty($state_data['templates'][$room])) {
                        $errors[] = "At least one {$room} room template is required";
                    }
                }
                break;
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Get next step in workflow
     *
     * @param string $current_step Current step name
     * @return string|null Next step name or null if at end
     */
    public function get_next_step($current_step) {
        $index = array_search($current_step, $this->valid_steps);
        
        if ($index === false || $index >= count($this->valid_steps) - 1) {
            return null;
        }
        
        return $this->valid_steps[$index + 1];
    }
    
    /**
     * Get previous step in workflow
     *
     * @param string $current_step Current step name
     * @return string|null Previous step name or null if at beginning
     */
    public function get_previous_step($current_step) {
        $index = array_search($current_step, $this->valid_steps);
        
        if ($index === false || $index <= 0) {
            return null;
        }
        
        return $this->valid_steps[$index - 1];
    }
    
    /**
     * Check if step is complete
     *
     * @param string $step Step name
     * @param array $state_data Current state data
     * @return bool True if step is complete
     */
    public function is_step_complete($step, $state_data) {
        $validation = $this->validate_step($step, $state_data);
        return $validation['valid'];
    }
    
    /**
     * Clear workflow state for user
     *
     * @param int $user_id WordPress user ID
     * @return bool Success status
     */
    public function clear_workflow_state($user_id) {
        global $wpdb;
        
        $result = $wpdb->delete(
            $this->table_name,
            ['user_id' => $user_id],
            ['%d']
        );
        
        return $result !== false;
    }
    
    /**
     * Get default workflow state
     *
     * @return array Default state structure
     */
    private function get_default_state() {
        return [
            'user_id' => get_current_user_id(),
            'client_id' => null,
            'campaign_id' => null,
            'current_step' => 'client',
            'data' => [
                'currentStep' => 'client',
                'clientId' => null,
                'clientName' => '',
                'campaignId' => null,
                'campaignName' => '',
                'utmCampaign' => '',
                'templates' => [
                    'problem' => [],
                    'solution' => [],
                    'offer' => []
                ],
                'settings' => [
                    'roomThresholds' => [],
                    'scoringRules' => []
                ],
                'completedSteps' => []
            ]
        ];
    }
    
    /**
     * Calculate workflow progress percentage
     *
     * @param array $state_data Current state data
     * @return int Progress percentage (0-100)
     */
    public function get_progress_percentage($state_data) {
        $total_steps = count($this->valid_steps);
        $completed = 0;
        
        foreach ($this->valid_steps as $step) {
            if ($this->is_step_complete($step, $state_data)) {
                $completed++;
            }
        }
        
        return round(($completed / $total_steps) * 100);
    }
}