<?php
/**
 * Base REST Controller
 *
 * @package DirectReach_Campaign_Builder
 * @since 2.0.0
 */

namespace DirectReach\CampaignBuilder\API;

if (!defined('ABSPATH')) {
    exit;
}

abstract class REST_Controller {
    
    /**
     * REST API namespace
     */
    protected $namespace = 'directreach/v2';
    
    /**
     * Register routes
     */
    abstract public function register_routes();
    
    /**
     * Standard WordPress REST API permission callback
     * This is the method WordPress REST API looks for by default
     */
    public function check_permissions() {
        return current_user_can('manage_options');
    }
}