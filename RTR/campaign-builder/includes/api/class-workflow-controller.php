<?php
/**
 * Workflow REST API Controller
 *
 * @package DirectReach_Campaign_Builder
 * @since 2.0.0
 */

namespace DirectReach\CampaignBuilder\API;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

class Workflow_Controller extends REST_Controller {
    
    /**
     * Register routes
     */
    public function register_routes() {
        // GET/PUT /workflow - Get or save workflow state
        register_rest_route($this->namespace, '/workflow', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'get_workflow'],
                'permission_callback' => [$this, 'check_permissions'],
            ],
            [
                'methods'             => 'PUT',
                'callback'            => [$this, 'save_workflow'],
                'permission_callback' => [$this, 'check_permissions'],
            ],
        ]);

        // POST /workflow/complete - Mark workflow as complete
        register_rest_route($this->namespace, '/workflow/complete', [
            'methods'             => 'POST',
            'callback'            => [$this, 'complete_workflow'],
            'permission_callback' => [$this, 'check_permissions'],
        ]);        
    }
    
    /**
     * Get workflow state
     */
    public function get_workflow($request) {
        $user_id = get_current_user_id();
        $state = get_user_meta($user_id, 'dr_cb_workflow_state', true);
        
        if (empty($state)) {
            $state = [
                'currentStep' => 'client',
                'clientId' => null,
                'clientName' => null,
                'campaignId' => null,
                'campaignName' => null,
            ];
        }
        
        return new WP_REST_Response([
            'success' => true,
            'data'    => $state,
        ], 200);
    }
    
    /**
     * Save workflow state
     */
    public function save_workflow($request) {
        $user_id = get_current_user_id();
        $state = $request->get_json_params();
        
        update_user_meta($user_id, 'dr_cb_workflow_state', $state);
        
        return new WP_REST_Response([
            'success' => true,
            'message' => 'Workflow state saved',
        ], 200);
    }

    /**
     * Mark workflow as complete
     */
    public function complete_workflow($request) {
        $campaign_id = $request->get_param('campaign_id');
        
        // Could add completion tracking here if needed
        // For now, just return success
        
        return new WP_REST_Response([
            'success' => true,
            'message' => 'Workflow completed successfully',
        ], 200);
    }

}