<?php
/**
 * Reading Room REST API Controller
 * 
 * Handles prospect management and room operations
 * 
 * @package DirectReach
 * @subpackage ReadingTheRoom
 * @since 1.0.0
 */

namespace DirectReach\ReadingTheRoom\API;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Reading_Room_Controller extends \WP_REST_Controller {
    
    /**
     * Namespace
     */
    protected $namespace = 'directreach/rtr/v1';
    
    /**
     * Register routes
     */
    public function register_routes() {
        // Get prospects
        register_rest_route($this->namespace, '/prospects', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_prospects'),
            'permission_callback' => array($this, 'check_permission'),
            'args' => array(
                'room' => array('required' => false, 'type' => 'string'),
                'campaign_id' => array('required' => false, 'type' => 'integer'),
                'limit' => array('required' => false, 'type' => 'integer', 'default' => 50),
                'offset' => array('required' => false, 'type' => 'integer', 'default' => 0)
            )
        ));
        
        // Get single prospect
        register_rest_route($this->namespace, '/prospects/(?P<id>[\d]+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_prospect'),
            'permission_callback' => array($this, 'check_permission')
        ));
        
        // Archive prospect
        register_rest_route($this->namespace, '/prospects/(?P<id>[\d]+)/archive', array(
            'methods' => 'POST',
            'callback' => array($this, 'archive_prospect'),
            'permission_callback' => array($this, 'check_permission'),
            'args' => array(
                'reason' => array('required' => false, 'type' => 'string')
            )
        ));
        
        // Handoff to sales
        register_rest_route($this->namespace, '/prospects/(?P<id>[\d]+)/handoff-sales', array(
            'methods' => 'POST',
            'callback' => array($this, 'handoff_to_sales'),
            'permission_callback' => array($this, 'check_permission'),
            'args' => array(
                'notes' => array('required' => false, 'type' => 'string')
            )
        ));
        
        // Get room counts
        register_rest_route($this->namespace, '/analytics/room-counts', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_room_counts'),
            'permission_callback' => array($this, 'check_permission'),
            'args' => array(
                'campaign_id' => array('required' => false, 'type' => 'integer')
            )
        ));
        
        // Get campaign stats
        register_rest_route($this->namespace, '/analytics/campaign-stats', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_campaign_stats'),
            'permission_callback' => array($this, 'check_permission'),
            'args' => array(
                'campaign_id' => array('required' => false, 'type' => 'integer'),
                'room' => array('required' => false, 'type' => 'string'),
                'days' => array('required' => false, 'type' => 'integer', 'default' => 30)
            )
        ));

        // Get campaigns
        register_rest_route($this->namespace, '/campaigns', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_campaigns'),
            'permission_callback' => array($this, 'check_permission'),
            'args' => array(
                'client_id' => array('required' => false, 'type' => 'integer')
            )
        ));        
    }
    
    /**
     * Check permissions
     */
    public function check_permission() {
        // Must be logged in and have manage_options capability
        return current_user_can('manage_options');
    }
    
    /**
     * Get prospects list
     */
    public function get_prospects($request) {
        global $wpdb;
        
        $room = $request->get_param('room');
        $campaign_id = $request->get_param('campaign_id');
        $limit = $request->get_param('limit');
        $offset = $request->get_param('offset');
        
        $table = $wpdb->prefix . 'rtr_prospects';
        
        $where = array('1=1');
        $where[] = 'archived_at IS NULL';
        $where[] = 'sales_handoff_at IS NULL';
        
        if ($room) {
            $where[] = $wpdb->prepare('current_room = %s', $room);
        }
        
        if ($campaign_id) {
            $where[] = $wpdb->prepare('campaign_id = %d', $campaign_id);
        }
        
        $where_sql = implode(' AND ', $where);
        
        $prospects = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} 
             WHERE {$where_sql}
             ORDER BY lead_score DESC, updated_at DESC
             LIMIT %d OFFSET %d",
            $limit,
            $offset
        ));
        
        // Parse JSON fields
        foreach ($prospects as &$prospect) {
            $prospect->urls_sent = json_decode($prospect->urls_sent, true) ?: array();
            $prospect->engagement_data = json_decode($prospect->engagement_data, true) ?: array();
        }
        
        return rest_ensure_response(array(
            'success' => true,
            'data' => $prospects,
            'meta' => array(
                'total' => $this->count_prospects($where_sql),
                'limit' => $limit,
                'offset' => $offset
            )
        ));
    }
    
    /**
     * Get single prospect
     */
    public function get_prospect($request) {
        global $wpdb;
        
        $id = $request->get_param('id');
        $table = $wpdb->prefix . 'rtr_prospects';
        
        $prospect = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $id
        ));
        
        if (!$prospect) {
            return new \WP_Error('not_found', 'Prospect not found', array('status' => 404));
        }
        
        // Parse JSON fields
        $prospect->urls_sent = json_decode($prospect->urls_sent, true) ?: array();
        $prospect->engagement_data = json_decode($prospect->engagement_data, true) ?: array();
        
        return rest_ensure_response(array(
            'success' => true,
            'data' => $prospect
        ));
    }
    
    /**
     * Archive prospect
     */
    public function archive_prospect($request) {
        global $wpdb;
        
        $id = $request->get_param('id');
        $reason = $request->get_param('reason');
        $table = $wpdb->prefix . 'rtr_prospects';
        
        $result = $wpdb->update(
            $table,
            array('archived_at' => current_time('mysql')),
            array('id' => $id),
            array('%s'),
            array('%d')
        );
        
        if ($result === false) {
            return new \WP_Error('update_failed', 'Failed to archive prospect', array('status' => 500));
        }
        
        // Log action
        $this->log_action('prospect_archived', sprintf('Archived prospect %d: %s', $id, $reason));
        
        return rest_ensure_response(array(
            'success' => true,
            'message' => 'Prospect archived successfully'
        ));
    }

    /**
     * Get campaigns list
     */
    public function get_campaigns($request) {
        global $wpdb;
        
        $client_id = $request->get_param('client_id');
        $campaigns_table = $wpdb->prefix . 'dr_campaign_settings';
        
        $where = '1=1';
        if ($client_id) {
            $where .= $wpdb->prepare(' AND client_id = %d', $client_id);
        }
        
        $campaigns = $wpdb->get_results(
            "SELECT id, campaign_id, campaign_name, utm_campaign, client_id, start_date, end_date
            FROM {$campaigns_table}
            WHERE {$where}
            ORDER BY campaign_name ASC"
        );
        
        return rest_ensure_response(array(
            'success' => true,
            'data' => $campaigns
        ));
    }    
    
    /**
     * Hand off prospect to sales
     */
    public function handoff_to_sales($request) {
        global $wpdb;
        
        $id = $request->get_param('id');
        $notes = $request->get_param('notes');
        $table = $wpdb->prefix . 'rtr_prospects';
        
        // Get prospect details for logging
        $prospect = $wpdb->get_row($wpdb->prepare(
            "SELECT campaign_id, current_room FROM {$table} WHERE id = %d",
            $id
        ));
        
        if (!$prospect) {
            return new \WP_Error('not_found', 'Prospect not found', array('status' => 404));
        }
        
        // Update prospect
        $result = $wpdb->update(
            $table,
            array(
                'sales_handoff_at' => current_time('mysql'),
                'handoff_notes' => $notes
            ),
            array('id' => $id),
            array('%s', '%s'),
            array('%d')
        );
        
        if ($result === false) {
            return new \WP_Error('update_failed', 'Failed to hand off prospect', array('status' => 500));
        }
        
        // Log room progression
        $this->log_room_progression($id, $prospect->campaign_id, $prospect->current_room, 'sales', 'Manual handoff');
        
        // Log action
        $this->log_action('prospect_handoff', sprintf('Handed off prospect %d to sales: %s', $id, $notes));
        
        return rest_ensure_response(array(
            'success' => true,
            'message' => 'Prospect handed off to sales successfully'
        ));
    }
    
    /**
     * Get room counts
     */
    public function get_room_counts($request) {
        global $wpdb;
        
        $campaign_id = $request->get_param('campaign_id');
        $table = $wpdb->prefix . 'rtr_prospects';
        
        $where = array('archived_at IS NULL');
        
        if ($campaign_id) {
            $where[] = $wpdb->prepare('campaign_id = %d', $campaign_id);
        }
        
        $where_sql = implode(' AND ', $where);
        
        $counts = $wpdb->get_results(
            "SELECT 
                current_room,
                COUNT(*) as count,
                AVG(lead_score) as avg_score
             FROM {$table}
             WHERE {$where_sql} AND sales_handoff_at IS NULL
             GROUP BY current_room"
        );
        
        // Get sales handoff count
        $sales_count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table}
             WHERE {$where_sql} AND sales_handoff_at IS NOT NULL"
        );
        
        $result = array(
            'problem' => 0,
            'solution' => 0,
            'offer' => 0,
            'sales' => (int) $sales_count
        );
        
        foreach ($counts as $row) {
            $result[$row->current_room] = (int) $row->count;
        }
        
        return rest_ensure_response(array(
            'success' => true,
            'data' => $result
        ));
    }
    
    /**
     * Get campaign statistics
     */
    public function get_campaign_stats($request) {
        global $wpdb;
        
        $campaign_id = $request->get_param('campaign_id');
        $room = $request->get_param('room');
        $days = $request->get_param('days');
        
        $table = $wpdb->prefix . 'rtr_prospects';
        $date_cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $where = array('1=1');
        
        if ($campaign_id) {
            $where[] = $wpdb->prepare('campaign_id = %d', $campaign_id);
        }
        
        if ($room) {
            $where[] = $wpdb->prepare('current_room = %s', $room);
        }
        
        $where_sql = implode(' AND ', $where);
        
        $stats = $wpdb->get_row(
            "SELECT 
                COUNT(*) as total_prospects,
                AVG(lead_score) as avg_score,
                AVG(days_in_room) as avg_days,
                SUM(CASE WHEN created_at >= '{$date_cutoff}' THEN 1 ELSE 0 END) as new_prospects
             FROM {$table}
             WHERE {$where_sql} AND archived_at IS NULL"
        );
        
        return rest_ensure_response(array(
            'success' => true,
            'data' => $stats
        ));
    }
    
    /**
     * Helper: Count prospects
     */
    private function count_prospects($where_sql) {
        global $wpdb;
        $table = $wpdb->prefix . 'rtr_prospects';
        
        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}"
        );
    }
    
    /**
     * Helper: Log room progression
     */
    private function log_room_progression($visitor_id, $campaign_id, $from_room, $to_room, $reason) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'rtr_room_progression',
            array(
                'visitor_id' => $visitor_id,
                'campaign_id' => $campaign_id,
                'from_room' => $from_room,
                'to_room' => $to_room,
                'reason' => $reason,
                'timestamp' => current_time('mysql')
            ),
            array('%d', '%d', '%s', '%s', '%s', '%s')
        );
    }
    
    /**
     * Helper: Log action
     */
    private function log_action($action_type, $description) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'cpd_action_logs',
            array(
                'user_id' => get_current_user_id(),
                'action_type' => $action_type,
                'description' => $description,
                'timestamp' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s')
        );
    }
}