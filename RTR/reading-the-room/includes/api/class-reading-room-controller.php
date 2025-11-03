<?php
/**
 * Reading Room REST API Controller
 *
 * Handles all REST endpoints for prospects, analytics, and campaign data.
 *
 * @package DirectReach
 * @subpackage ReadingTheRoom
 * @since 2.0.0
 */

declare(strict_types=1);

namespace DirectReach\ReadingTheRoom\API;

use DirectReach\ReadingTheRoom\Reading_Room_Database;
use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

final class Reading_Room_Controller extends WP_REST_Controller
{
    /** @var Reading_Room_Database */
    private $db;

    /** @var string */
    protected $namespace = 'directreach/v1/reading-room';

    /**
     * Constructor.
     *
     * @param Reading_Room_Database $db
     */
    public function __construct(Reading_Room_Database $db)
    {
        $this->db = $db;
    }

    /**
     * Register REST API routes.
     */
    public function register_routes(): void
    {
        // Prospects endpoints
        register_rest_route($this->namespace, '/prospects', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_prospects'],
                'permission_callback' => [$this, 'check_permission'],
                'args'                => [
                    'client_id'   => ['type' => 'integer', 'required' => false],
                    'campaign_id' => ['type' => 'integer', 'required' => false],
                    'room'        => ['type' => 'string', 'required' => false],
                ],
            ],
        ]);

        register_rest_route($this->namespace, '/prospects/(?P<id>\d+)', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_prospect'],
                'permission_callback' => [$this, 'check_permission'],
            ],
        ]);

        register_rest_route($this->namespace, '/prospects/(?P<id>\d+)/archive', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'archive_prospect'],
                'permission_callback' => [$this, 'check_permission'],
            ],
        ]);

        register_rest_route($this->namespace, '/prospects/(?P<id>\d+)/handoff', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'handoff_prospect'],
                'permission_callback' => [$this, 'check_permission'],
            ],
        ]);

        // Analytics endpoints
        register_rest_route($this->namespace, '/analytics/room-counts', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_room_counts'],
                'permission_callback' => [$this, 'check_permission'],
                'args'                => [
                    'client_id'   => ['type' => 'integer', 'required' => false],
                    'campaign_id' => ['type' => 'integer', 'required' => false],
                ],
            ],
        ]);

        register_rest_route($this->namespace, '/analytics/campaign-stats', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_campaign_stats'],
                'permission_callback' => [$this, 'check_permission'],
                'args'                => [
                    'room'      => ['type' => 'string', 'required' => true],
                    'client_id' => ['type' => 'integer', 'required' => false],
                    'days'      => ['type' => 'integer', 'default' => 30],
                ],
            ],
        ]);

        // Campaigns endpoint
        register_rest_route($this->namespace, '/campaigns', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_campaigns'],
                'permission_callback' => [$this, 'check_permission'],
            ],
        ]);
    }



    /**
     * Get all prospects with optional filters.
     *
     */
    public function get_prospects(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $filters = [];
            error_log('Getting prospects with filters: ' . print_r($request->get_params(), true));
            if ($request->has_param('client_id') && !empty($request->get_param('client_id'))) {
                $filters['client_id'] = (int) $request->get_param('client_id');
            }
            error_log('Client ID filter: ' . ($filters['client_id'] ?? 'none'));
            if ($request->has_param('campaign_id') && !empty($request->get_param('campaign_id'))) {
                $filters['campaign_id'] = (int) $request->get_param('campaign_id');
            }
            error_log('Campaign ID filter: ' . ($filters['campaign_id'] ?? 'none'));

            if ($request->has_param('days') && !empty($request->get_param('days'))) {
                $filters['days'] = (int) $request->get_param('days');
            }
            error_log('Days filter: ' . ($filters['days'] ?? 'none'));
            
            $prospects = $this->db->get_prospects($filters);



            // Ensure each prospect has a room assignment and email states
            foreach ($prospects as &$prospect) {
                if (empty($prospect['room'])) {
                    $prospect['room'] = $this->determine_prospect_room($prospect);
                }
                
                // Add email states
                $prospect['email_states'] = $this->get_email_states(
                    (int) $prospect['id'],
                    $prospect['room']
                );
            }

            // Filter by room if parameter is provided
            $requested_room = $request->get_param('room');
            if (!empty($requested_room)) {
                $prospects = array_values(array_filter($prospects, function($prospect) use ($requested_room) {
                    return ($prospect['room'] ?? '') === $requested_room;
                }));
            }

            return new WP_REST_Response([
                'success' => true,
                'data'    => $prospects,
                'count'   => count($prospects),
            ], 200);

        } catch (\Exception $e) {
            error_log('[DirectReach][API] get_prospects error: ' . $e->getMessage());
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Failed to retrieve prospects',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get a single prospect by ID.
     */
    public function get_prospect(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        
        $prospect = $this->db->get_prospect($id);
        
        if (!$prospect) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Prospect not found',
            ], 404);
        }

        // FIX: Ensure room assignment
        if (empty($prospect['room'])) {
            $prospect['room'] = $this->determine_prospect_room($prospect);
        }

        // Add email states
        $prospect['email_states'] = $this->get_email_states(
            (int) $prospect['id'],
            $prospect['room']
        );

        return new WP_REST_Response([
            'success' => true,
            'data'    => $prospect,
        ], 200);
    }

    /**
     * Archive a prospect.
     */
    public function archive_prospect(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        
        try {
            // Update prospect status to archived
            $prospect = $this->db->get_prospect($id);
            if (!$prospect) {
                return new WP_REST_Response([
                    'success' => false,
                    'message' => 'Prospect not found',
                ], 404);
            }

            // Parse attributes
            $attributes = !empty($prospect['attributes']) 
                ? json_decode($prospect['attributes'], true) 
                : [];
            
            $attributes['status'] = 'archived';
            $attributes['archived_at'] = current_time('mysql');

            $result = $this->db->save_prospect([
                'id'         => $id,
                'attributes' => $attributes,
            ]);

            if (!$result) {
                throw new \Exception('Failed to update prospect');
            }

            return new WP_REST_Response([
                'success' => true,
                'message' => 'Prospect archived successfully',
            ], 200);

        } catch (\Exception $e) {
            error_log('[DirectReach][API] archive_prospect error: ' . $e->getMessage());
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Failed to archive prospect',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Hand off prospect to sales.
     */
    public function handoff_prospect(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        
        try {
            $prospect = $this->db->get_prospect($id);
            if (!$prospect) {
                return new WP_REST_Response([
                    'success' => false,
                    'message' => 'Prospect not found',
                ], 404);
            }

            // Parse attributes
            $attributes = !empty($prospect['attributes']) 
                ? json_decode($prospect['attributes'], true) 
                : [];
            
            // Update room to sales
            $attributes['room'] = 'sales';
            $attributes['handed_off_at'] = current_time('mysql');

            $result = $this->db->save_prospect([
                'id'         => $id,
                'attributes' => $attributes,
            ]);

            if (!$result) {
                throw new \Exception('Failed to update prospect');
            }

            // Log the handoff event
            $this->db->log_event([
                'prospect_id' => $id,
                'event_key'   => 'sales_handoff',
                'event_value' => json_encode(['status' => 'handed_off']),
            ]);

            return new WP_REST_Response([
                'success' => true,
                'message' => 'Prospect handed off to sales',
            ], 200);

        } catch (\Exception $e) {
            error_log('[DirectReach][API] handoff_prospect error: ' . $e->getMessage());
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Failed to hand off prospect',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get room counts with filters.
     *
     * FIX: Proper counting logic based on prospect attributes
     */
    public function get_room_counts(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $filters = [];
            
            if ($request->has_param('client_id') && !empty($request->get_param('client_id'))) {
                $filters['client_id'] = (int) $request->get_param('client_id');
            }
            
            if ($request->has_param('campaign_id') && !empty($request->get_param('campaign_id'))) {
                $filters['campaign_id'] = (int) $request->get_param('campaign_id');
            }

            $prospects = $this->db->get_prospects($filters);

            // FIX: Count prospects by room
            $counts = [
                'problem'  => 0,
                'solution' => 0,
                'offer'    => 0,
                'sales'    => 0,
            ];

            foreach ($prospects as $prospect) {
                // Skip archived prospects
                if (!empty($prospect['attributes'])) {
                    $attrs = json_decode($prospect['attributes'], true);
                    if (isset($attrs['status']) && $attrs['status'] === 'archived') {
                        continue;
                    }
                }

                // Determine room
                $room = $this->determine_prospect_room($prospect);
                if (isset($counts[$room])) {
                    $counts[$room]++;
                }
            }

            return new WP_REST_Response([
                'success' => true,
                'data'    => $counts,
                'total'   => array_sum($counts),
            ], 200);

        } catch (\Exception $e) {
            error_log('[DirectReach][API] get_room_counts error: ' . $e->getMessage());
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Failed to retrieve room counts',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get campaign statistics for a room.
     *
     */
    public function get_campaign_stats(WP_REST_Request $request): WP_REST_Response
    {
        $room      = sanitize_text_field($request->get_param('room'));
        $days      = (int) $request->get_param('days') ?: 30;
        $client_id = $request->has_param('client_id') 
            ? (int) $request->get_param('client_id') 
            : null;

        if (!in_array($room, ['problem', 'solution', 'offer', 'sales'], true)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Invalid room specified',
            ], 400);
        }

        try {
            $date_from = date('Y-m-d H:i:s', strtotime("-{$days} days"));
            
            $filters = ['date_from' => $date_from];
            if ($client_id) {
                $filters['client_id'] = $client_id;
            }

            $prospects = $this->db->get_prospects($filters);

            $room_prospects = array_filter($prospects, function($p) use ($room) {
                return $this->determine_prospect_room($p) === $room;
            });

            $total = count($room_prospects);
            $new_count = 0;
            $avg_score = 0;

            if ($total > 0) {
                $today = date('Y-m-d');
                $score_sum = 0;

                foreach ($room_prospects as $p) {
                    $created = substr($p['created_at'] ?? '', 0, 10);
                    if ($created === $today) {
                        $new_count++;
                    }
                    $score_sum += (int) ($p['lead_score'] ?? 0);
                }

                $avg_score = round($score_sum / $total, 1);
            }

            $email_stats = $this->get_email_stats_for_room($room, $filters);

            $stats = [
                'room'            => $room,
                'total_prospects' => $total,
                'new_prospects'   => $new_count,
                'avg_score'       => $avg_score,
                'sent_emails'     => $email_stats['sent'] ?? 0,
                'opened_emails'   => $email_stats['opened'] ?? 0,
                'clicked_links'   => $email_stats['clicked'] ?? 0,
            ];

            switch ($room) {
                case 'problem':
                    $stats['progress_rate'] = $total > 0 ? round(($new_count / $total) * 100) : 0;
                    break;
                case 'solution':
                    $stats['high_scores'] = $avg_score > 70 ? round($total * 0.3) : round($total * 0.15);
                    $stats['open_rate'] = $stats['sent_emails'] > 0 
                        ? round(($stats['opened_emails'] / $stats['sent_emails']) * 100) 
                        : 0;
                    break;
                case 'offer':
                    $this_week_count = $this->count_prospects_this_week($room_prospects);
                    $stats['this_week'] = $this_week_count;
                    $stats['click_rate'] = $stats['sent_emails'] > 0 
                        ? round(($stats['clicked_links'] / $stats['sent_emails']) * 100) 
                        : 0;
                    break;
                case 'sales':
                    $this_week_sales = $this->count_prospects_this_week($room_prospects);
                    $stats['this_week'] = $this_week_sales;
                    $stats['avg_days'] = $this->calculate_avg_days_to_close($room_prospects);
                    break;
            }

            return new WP_REST_Response([
                'success' => true,
                'data'    => $stats,
            ], 200);

        } catch (\Exception $e) {
            error_log('[DirectReach][API] get_campaign_stats error: ' . $e->getMessage());
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Failed to retrieve campaign stats',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get campaigns list.
     */
    public function get_campaigns(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $campaigns = $this->db->get_campaigns(['status' => 'active']);

            return new WP_REST_Response([
                'success' => true,
                'data'    => $campaigns,
            ], 200);

        } catch (\Exception $e) {
            error_log('[DirectReach][API] get_campaigns error: ' . $e->getMessage());
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Failed to retrieve campaigns',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Helper: Determine which room a prospect belongs to.
     *
     * FIX: Consistent room determination logic matching JS side
     */
    private function determine_prospect_room(array $prospect): string
    {
        // Check explicit room in attributes
        if (!empty($prospect['attributes'])) {
            $attrs = json_decode($prospect['attributes'], true);
            if (isset($attrs['room']) && in_array($attrs['room'], ['problem', 'solution', 'offer', 'sales'])) {
                return $attrs['room'];
            }
        }

        // Fallback: Use lead score
        $score = (int) ($prospect['lead_score'] ?? 0);
        
        if ($score >= 80) {
            return 'offer';
        } elseif ($score >= 50) {
            return 'solution';
        } else {
            return 'problem';
        }
    }

    /**
     * Helper: Get email statistics for a room.
     */
    private function get_email_stats_for_room(string $room, array $filters): array
    {
        // Get prospects in this room
        $prospects = $this->db->get_prospects($filters);
        $room_prospect_ids = [];
        
        foreach ($prospects as $p) {
            if ($this->determine_prospect_room($p) === $room) {
                $room_prospect_ids[] = (int) $p['id'];
            }
        }

        if (empty($room_prospect_ids)) {
            return ['sent' => 0, 'opened' => 0, 'clicked' => 0];
        }

        // Query analytics for email events
        global $wpdb;
        $tables = $this->db->tables();
        $analytics_table = $tables['analytics'] ?? '';
        
        if (empty($analytics_table)) {
            return ['sent' => 0, 'opened' => 0, 'clicked' => 0];
        }

        $ids_str = implode(',', $room_prospect_ids);
        
        $sent = (int) $wpdb->get_var("
            SELECT COUNT(DISTINCT prospect_id) 
            FROM {$analytics_table} 
            WHERE prospect_id IN ({$ids_str}) 
            AND event_key = 'email_sent'
        ");

        $opened = (int) $wpdb->get_var("
            SELECT COUNT(DISTINCT prospect_id) 
            FROM {$analytics_table} 
            WHERE prospect_id IN ({$ids_str}) 
            AND event_key = 'email_opened'
        ");

        $clicked = (int) $wpdb->get_var("
            SELECT COUNT(DISTINCT prospect_id) 
            FROM {$analytics_table} 
            WHERE prospect_id IN ({$ids_str}) 
            AND event_key = 'email_clicked'
        ");

        return [
            'sent'    => $sent,
            'opened'  => $opened,
            'clicked' => $clicked,
        ];
    }

/**
     * Count prospects created this week.
     */
    private function count_prospects_this_week(array $prospects): int
    {
        $week_start = date('Y-m-d', strtotime('monday this week'));
        $count = 0;
        
        foreach ($prospects as $p) {
            $created = substr($p['created_at'] ?? '', 0, 10);
            if ($created >= $week_start) {
                $count++;
            }
        }
        
        return $count;
    }

    /**
     * Calculate average days to close for sales.
     */
    private function calculate_avg_days_to_close(array $prospects): float
    {
        if (empty($prospects)) {
            return 0;
        }
        
        $total_days = 0;
        $count = 0;
        
        foreach ($prospects as $p) {
            if (!empty($p['created_at']) && !empty($p['updated_at'])) {
                $created = strtotime($p['created_at']);
                $updated = strtotime($p['updated_at']);
                $days = round(($updated - $created) / 86400, 1);
                
                if ($days > 0) {
                    $total_days += $days;
                    $count++;
                }
            }
        }
        
        return $count > 0 ? round($total_days / $count, 1) : 0;
    }

    /**
     * Get email states for a prospect from tracking table.
     *
     * @param int    $prospect_id Prospect ID
     * @param string $room_type   Current room type
     * @return array Email states for 5 emails
     */
    private function get_email_states(int $prospect_id, string $room_type): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'rtr_email_tracking';
        
        $email_states = [];
        
        // Query tracking status for each of 5 emails
        for ($i = 1; $i <= 5; $i++) {
            $tracking = $wpdb->get_row($wpdb->prepare(
                "SELECT 
                    id,
                    status,
                    copied_at,
                    opened_at,
                    created_at
                FROM {$table}
                WHERE prospect_id = %d 
                AND room_type = %s
                AND email_number = %d
                ORDER BY created_at DESC
                LIMIT 1",
                $prospect_id,
                $room_type,
                $i
            ));
            
            if ($tracking) {
                // Determine state priority: opened > sent > ready > generating > failed > pending
                $state = 'pending';
                $timestamp = null;
                
                if (!empty($tracking->opened_at)) {
                    $state = 'opened';
                    $timestamp = $tracking->opened_at;
                } elseif (!empty($tracking->copied_at)) {
                    $state = 'sent';
                    $timestamp = $tracking->copied_at;
                } elseif ($tracking->status === 'completed' || $tracking->status === 'generated') {
                    $state = 'ready';
                    $timestamp = $tracking->created_at;
                } elseif ($tracking->status === 'generating' || $tracking->status === 'pending_generation') {
                    $state = 'generating';
                    $timestamp = $tracking->created_at;
                } elseif ($tracking->status === 'failed' || $tracking->status === 'error') {
                    $state = 'failed';
                    $timestamp = $tracking->created_at;
                }
                
                $email_states["email_$i"] = [
                    'state' => $state,
                    'timestamp' => $timestamp
                ];
            } else {
                // No tracking record = pending state
                $email_states["email_$i"] = [
                    'state' => 'pending',
                    'timestamp' => null
                ];
            }
        }
        
        return $email_states;
    }

    /**
     * Permission check.
     */
    public function check_permission(): bool
    {
        return current_user_can('edit_posts');
    }
}