<?php
/**
 * Email Generation REST Controller
 *
 * Handles REST API endpoints for AI-powered email generation and tracking.
 *
 * @package DirectReach
 * @subpackage RTR/API
 * @since 2.5.0
 */

namespace DirectReach\CampaignBuilder\API;

use WP_REST_Server;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Email_Generation_Controller extends WP_REST_Controller {

    /**
     * Namespace
     *
     * @var string
     */
    protected $namespace = 'directreach/v2';

    /**
     * Rest base
     *
     * @var string
     */
    protected $rest_base = 'emails';

    /**
     * AI Email Generator instance
     *
     * @var \CPD_AI_Email_Generator
     */
    private $generator;

    /**
     * Email Tracking Manager instance
     *
     * @var \CPD_Email_Tracking_Manager
     */
    private $tracking;

    /**
     * Rate Limiter instance
     *
     * @var \CPD_AI_Rate_Limiter
     */
    private $rate_limiter;

    /**
     * Constructor
     */
    public function __construct() {
        $this->generator = new \CPD_AI_Email_Generator();
        $this->tracking = new \CPD_Email_Tracking_Manager();
        $this->rate_limiter = new \CPD_AI_Rate_Limiter();
    }

    /**
     * Register routes
     */
    public function register_routes() {
        // Generate email
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/generate', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array( $this, 'generate_email' ),
            'permission_callback' => array( $this, 'generate_permissions_check' ),
            'args' => array(
                'prospect_id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'validate_callback' => function( $param ) {
                        return is_numeric( $param ) && $param > 0;
                    },
                ),
                'room_type' => array(
                    'required' => true,
                    'type' => 'string',
                    'enum' => array( 'problem', 'solution', 'offer' ),
                ),
                'email_number' => array(
                    'required' => true,
                    'type' => 'integer',
                    'validate_callback' => function( $param ) {
                        return is_numeric( $param ) && $param > 0;
                    },
                ),
            ),
        ));

        // Track copy (mark as sent)
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/track-copy', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array( $this, 'track_copy' ),
            'permission_callback' => array( $this, 'track_permissions_check' ),
            'args' => array(
                'email_tracking_id' => array(
                    'required' => true,
                    'type' => 'integer',
                ),
                'prospect_id' => array(
                    'required' => true,
                    'type' => 'integer',
                ),
                'url_included' => array(
                    'required' => false,
                    'type' => 'string',
                    'format' => 'uri',
                ),
            ),
        ));

        // Track open (tracking pixel endpoint - future)
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/track-open/(?P<token>[a-zA-Z0-9]+)', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array( $this, 'track_open' ),
            'permission_callback' => '__return_true', // Public endpoint
        ));


        register_rest_route($this->namespace, '/emails/test-prompt', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'test_prompt'],
            'permission_callback' => [$this, 'check_admin_permissions'],
            'args' => [
                'prompt_template' => [
                    'type' => 'object',
                    'required' => true,
                    'description' => '7-component prompt structure',
                ],
                'campaign_id' => [
                    'type' => 'integer',
                    'required' => true,
                    'description' => 'Campaign ID for content links',
                ],
                'room_type' => [
                    'type' => 'string',
                    'required' => false,
                    'default' => 'problem',
                    'enum' => ['problem', 'solution', 'offer'],
                ],
            ],
        ]);

        // Get email tracking details by ID
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/tracking/(?P<tracking_id>[\d]+)', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array( $this, 'get_tracking_details' ),
            'permission_callback' => array( $this, 'get_tracking_permissions_check' ),
            'args' => array(
                'tracking_id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'validate_callback' => function( $param ) {
                        return is_numeric( $param ) && $param > 0;
                    },
                ),
            ),
        ));
        
        // Get email tracking by prospect and email number
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/tracking/prospect/(?P<prospect_id>[\d]+)/email/(?P<email_number>[\d]+)', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array( $this, 'get_tracking_by_prospect' ),
            'permission_callback' => array( $this, 'get_tracking_permissions_check' ),
            'args' => array(
                'prospect_id' => array(
                    'required' => true,
                    'type' => 'integer',
                ),
                'email_number' => array(
                    'required' => true,
                    'type' => 'integer',
                ),
            ),
        ));

    }

    /**
     * Generate email endpoint
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function generate_email( $request ) {
        $start_time = microtime( true );

        $prospect_id = (int) $request->get_param( 'prospect_id' );
        $room_type = sanitize_text_field( $request->get_param( 'room_type' ) );
        $email_number = (int) $request->get_param( 'email_number' );

        // Validate prospect exists and get campaign_id
        $prospect = $this->get_prospect( $prospect_id );
        if ( is_wp_error( $prospect ) ) {
            return $prospect;
        }

        $campaign_id = (int) $prospect['campaign_id'];

        // Log generation attempt
        error_log( sprintf(
            '[DirectReach] Email generation requested: prospect=%d, campaign=%d, room=%s, email_num=%d',
            $prospect_id,
            $campaign_id,
            $room_type,
            $email_number
        ));

        // Generate email via AI
        $result = $this->generator->generate_email(
            $prospect_id,
            $campaign_id,
            $room_type,
            $email_number
        );

        if ( is_wp_error( $result ) ) {
            error_log( '[DirectReach] Email generation failed: ' . $result->get_error_message() );
            
            return new WP_Error(
                $result->get_error_code(),
                $result->get_error_message(),
                array( 'status' => 500 )
            );
        }

        // Generate tracking token
        $tracking_token = $this->generate_tracking_token();

        // Create email tracking record
        $tracking_id = $this->tracking->create_tracking_record( array(
            'prospect_id' => $prospect_id,
            'email_number' => $email_number,
            'room_type' => $room_type,
            'subject' => $result['subject'],
            'body_html' => $result['body_html'],
            'body_text' => $result['body_text'],
            'generated_by_ai' => ! isset( $result['fallback'] ) || ! $result['fallback'],
            'template_used' => $result['template_used']['id'] ?? null,
            'ai_prompt_tokens' => $result['tokens_used']['prompt'] ?? 0,
            'ai_completion_tokens' => $result['tokens_used']['completion'] ?? 0,
            'url_included' => $result['selected_url']['url'] ?? null,
            'tracking_token' => $tracking_token,
            'status' => 'pending',
        ));

        if ( is_wp_error( $tracking_id ) ) {
            error_log( '[DirectReach] Failed to create tracking record: ' . $tracking_id->get_error_message() );
        }

        // Record usage stats if AI was used
        if ( ! isset( $result['fallback'] ) || ! $result['fallback'] ) {
            $this->rate_limiter->record_generation(
                $result['tokens_used']['total'] ?? 0,
                $result['tokens_used']['cost'] ?? 0
            );
        }

        $total_time = ( microtime( true ) - $start_time ) * 1000;

        // Build response
        $response = array(
            'success' => true,
            'data' => array(
                'email_tracking_id' => $tracking_id,
                'subject' => $result['subject'],
                'body_html' => $result['body_html'],
                'body_text' => $result['body_text'],
                'selected_url' => $result['selected_url'],
                'template_used' => $result['template_used'],
                'tracking_token' => $tracking_token,
                'tokens_used' => $result['tokens_used'],
            ),
            'meta' => array(
                'generation_time_ms' => round( $total_time, 2 ),
                'api_calls' => 1,
                'fallback_used' => isset( $result['fallback'] ) && $result['fallback'],
            ),
        );

        error_log( sprintf(
            '[DirectReach] Email generated successfully: tracking_id=%d, time=%dms, ai=%s',
            $tracking_id,
            round( $total_time ),
            isset( $result['fallback'] ) && $result['fallback'] ? 'no' : 'yes'
        ));

        return rest_ensure_response( $response );
    }

    /**
     * Track copy endpoint
     *
     * Marks email as copied/sent and updates prospect's sent URLs.
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function track_copy( $request ) {
        $email_tracking_id = (int) $request->get_param( 'email_tracking_id' );
        $prospect_id = (int) $request->get_param( 'prospect_id' );
        $url_included = $request->get_param( 'url_included' );

        // Update email tracking record
        $update_result = $this->tracking->update_tracking_status(
            $email_tracking_id,
            'copied',
            array( 'copied_at' => current_time( 'mysql' ) )
        );

        if ( is_wp_error( $update_result ) ) {
            return $update_result;
        }

        // Update prospect's sent URLs
        if ( ! empty( $url_included ) ) {
            $url_update = $this->update_prospect_sent_urls( $prospect_id, $url_included );
            
            if ( is_wp_error( $url_update ) ) {
                error_log( '[DirectReach] Failed to update sent URLs: ' . $url_update->get_error_message() );
                // Don't fail the request, just log
            }
        }

        // Update prospect's email data (timestamp + increment sequence position)
        $this->update_prospect_email_data( $prospect_id );

        error_log( sprintf(
            '[DirectReach] Email copied: tracking_id=%d, prospect=%d, url=%s',
            $email_tracking_id,
            $prospect_id,
            $url_included ?? 'none'
        ));

        return rest_ensure_response( array(
            'success' => true,
            'message' => 'Email marked as copied',
            'data' => array(
                'email_tracking_id' => $email_tracking_id,
                'status' => 'copied',
                'copied_at' => current_time( 'mysql' ),
            ),
        ));
    }   

    /**
     * Track open endpoint (tracking pixel)
     *
     * Future implementation - returns 1x1 transparent GIF.
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response
     */
    public function track_open( $request ) {
        $token = sanitize_text_field( $request->get_param( 'token' ) );

        // Update tracking record
        $this->tracking->record_open( $token );

        // Return 1x1 transparent GIF
        header( 'Content-Type: image/gif' );
        header( 'Cache-Control: no-cache, no-store, must-revalidate' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );
        
        // 1x1 transparent GIF in base64
        echo base64_decode( 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7' );
        exit;
    }

    /**
     * Get prospect data
     *
     * @param int $prospect_id Prospect ID
     * @return array|WP_Error Prospect data or error
     */
    private function get_prospect( $prospect_id ) {
        global $wpdb;

        $table = $wpdb->prefix . 'rtr_prospects';
        $prospect = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $prospect_id ),
            ARRAY_A
        );

        if ( ! $prospect ) {
            return new WP_Error(
                'prospect_not_found',
                'Prospect not found',
                array( 'status' => 404 )
            );
        }

        return $prospect;
    }

    /**
     * Update prospect's sent URLs
     *
     * @param int    $prospect_id Prospect ID
     * @param string $url URL to add
     * @return bool|WP_Error Success or error
     */
    private function update_prospect_sent_urls( $prospect_id, $url ) {
        global $wpdb;

        $table = $wpdb->prefix . 'rtr_prospects';

        // Get current sent URLs
        $current_urls_json = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT urls_sent FROM {$table} WHERE id = %d",
                $prospect_id
            )
        );

        // Parse existing URLs
        $sent_urls = array();
        if ( ! empty( $current_urls_json ) ) {
            $sent_urls = json_decode( $current_urls_json, true );
            if ( ! is_array( $sent_urls ) ) {
                $sent_urls = array();
            }
        }

        // Add new URL if not already present
        if ( ! in_array( $url, $sent_urls, true ) ) {
            $sent_urls[] = $url;
        }

        // Update database
        $result = $wpdb->update(
            $table,
            array( 'urls_sent' => wp_json_encode( $sent_urls ) ),
            array( 'id' => $prospect_id ),
            array( '%s' ),
            array( '%d' )
        );

        if ( false === $result ) {
            return new WP_Error(
                'database_error',
                'Failed to update sent URLs: ' . $wpdb->last_error
            );
        }

        return true;
    }

    /**
     * Update prospect's email data (timestamp + increment sequence position)
     *
     * @param int $prospect_id Prospect ID
     * @return bool|WP_Error Success or error
     */
    private function update_prospect_email_data( $prospect_id ) {
        global $wpdb;

        $table = $wpdb->prefix . 'rtr_prospects';

        // Get current sequence position
        $current_position = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT email_sequence_position FROM {$table} WHERE id = %d",
                $prospect_id
            )
        );

        if ( $current_position === null ) {
            error_log( '[DirectReach] Prospect not found for email data update: ' . $prospect_id );
            return new WP_Error(
                'prospect_not_found',
                'Prospect not found',
                array( 'status' => 404 )
            );
        }

        // Increment sequence position and update timestamp
        $new_position = $current_position + 1;
        
        $result = $wpdb->update(
            $table,
            array( 
                'last_email_sent' => current_time( 'mysql' ),
                'email_sequence_position' => $new_position
            ),
            array( 'id' => $prospect_id ),
            array( '%s', '%d' ),
            array( '%d' )
        );

        if ( false === $result ) {
            error_log( '[DirectReach] Failed to update prospect email data: ' . $wpdb->last_error );
            return new WP_Error(
                'database_error',
                'Failed to update prospect email data: ' . $wpdb->last_error
            );
        }

        // Log successful update
        error_log( sprintf(
            '[DirectReach] Updated prospect %d: position %d → %d',
            $prospect_id,
            $current_position,
            $new_position
        ));

        return true;
    }

    /**
     * Generate tracking token
     *
     * @return string Unique tracking token
     */
    private function generate_tracking_token() {
        return bin2hex( random_bytes( 16 ) );
    }

    /**
     * Permission check for generation
     *
     * @param WP_REST_Request $request Request object
     * @return bool|WP_Error
     */
    public function generate_permissions_check( $request ) {
        if ( ! current_user_can( 'edit_posts' ) ) {
            return new WP_Error(
                'rest_forbidden',
                'You do not have permission to generate emails',
                array( 'status' => 403 )
            );
        }

        return true;
    }

    /**
     * Permission check for tracking
     *
     * @param WP_REST_Request $request Request object
     * @return bool|WP_Error
     */
    public function track_permissions_check( $request ) {
        if ( ! current_user_can( 'edit_posts' ) ) {
            return new WP_Error(
                'rest_forbidden',
                'You do not have permission to track emails',
                array( 'status' => 403 )
            );
        }

        return true;
    }


    /**
     * Test prompt with mock data
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function test_prompt($request) {
        try {
            $params = $request->get_json_params();
            
            $prompt_template = $params['prompt_template'] ?? null;
            $campaign_id = isset($params['campaign_id']) ? intval($params['campaign_id']) : 0;
            $room_type = $params['room_type'] ?? 'problem';
            
            if (empty($prompt_template)) {
                return new \WP_Error(
                    'missing_prompt',
                    'Prompt template is required',
                    ['status' => 400]
                );
            }
            
            if (empty($campaign_id)) {
                return new \WP_Error(
                    'missing_campaign',
                    'Campaign ID is required',
                    ['status' => 400]
                );
            }
            
            // Verify campaign exists
            if (!$this->campaign_exists($campaign_id)) {
                return new \WP_Error(
                    'invalid_campaign',
                    'Campaign not found',
                    ['status' => 404]
                );
            }
            
            // Check rate limit
            $rate_limit_check = $this->rate_limiter->check_limit();
            if (is_wp_error($rate_limit_check)) {
                $this->log_action(
                    'ai_test_prompt',
                    'Rate limit exceeded for test prompt'
                );
                
                return $rate_limit_check;
            }
            
            // Generate test email
            $generation_start = microtime(true);
            $result = $this->generator->generate_email_for_test(
                $prompt_template,
                $campaign_id,
                $room_type
            );
            $generation_time = (microtime(true) - $generation_start) * 1000;
            
            if (is_wp_error($result)) {
                $this->log_action(
                    'ai_test_prompt',
                    sprintf(
                        'Test prompt failed - Campaign: %d, Room: %s, Error: %s',
                        $campaign_id,
                        $room_type,
                        $result->get_error_message()
                    )
                );
                
                return $result;
            }
            
            // Increment rate limiter
            $this->rate_limiter->increment();
            
            // Log successful test
            $this->log_action(
                'ai_test_prompt',
                sprintf(
                    'Test prompt executed - Campaign: %d, Room: %s, Tokens: %d, Cost: $%.4f',
                    $campaign_id,
                    $room_type,
                    $result['usage']['total_tokens'],
                    $result['usage']['cost']
                )
            );
            
            return new \WP_REST_Response([
                'success' => true,
                'data' => [
                    'subject' => $result['subject'],
                    'body_html' => $result['body_html'],
                    'body_text' => $result['body_text'],
                    'selected_url' => $result['selected_url'],
                    'mock_prospect' => $result['mock_prospect'],
                    'usage' => $result['usage'],
                ],
                'meta' => [
                    'generation_time_ms' => round($generation_time, 2),
                    'campaign_id' => $campaign_id,
                    'room_type' => $room_type,
                    'test_mode' => true,
                ],
            ], 200);
            
        } catch (\Exception $e) {
            error_log('Email Generation - Test Prompt Error: ' . $e->getMessage());
            
            $this->log_action(
                'ai_test_prompt',
                'Test prompt error: ' . $e->getMessage()
            );
            
            return new \WP_Error(
                'test_prompt_failed',
                'Failed to test prompt: ' . $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Check if campaign exists
     * 
     * @param int $campaign_id
     * @return bool
     */
    private function campaign_exists($campaign_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'dr_campaign_settings';
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE id = %d",
            $campaign_id
        ));
        
        return $exists > 0;
    }

    /**
     * Check if user has admin permissions
     * 
     * @return bool
     */
    public function check_admin_permissions() {
        return current_user_can('manage_options');
    }

    /**
     * Log action to wp_cpd_action_logs
     * 
     * @param string $action_type
     * @param string $description
     */
    private function log_action($action_type, $description) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cpd_action_logs';
        
        $wpdb->insert(
            $table_name,
            [
                'user_id' => get_current_user_id(),
                'action_type' => sanitize_text_field($action_type),
                'description' => sanitize_text_field($description),
                'timestamp' => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%s']
        );
        
        if ($wpdb->last_error) {
            error_log('Action Log Error: ' . $wpdb->last_error);
        }
    }

    /**
     * Get email tracking details by tracking ID
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function get_tracking_details( $request ) {
        global $wpdb;
        
        $tracking_id = (int) $request->get_param( 'tracking_id' );
        $table = $wpdb->prefix . 'rtr_email_tracking';
        
        $tracking = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE id = %d",
                $tracking_id
            ),
            ARRAY_A
        );
        
        if ( ! $tracking ) {
            return new WP_Error(
                'not_found',
                'Email tracking record not found',
                array( 'status' => 404 )
            );
        }
        
        // Get prospect details
        $prospect = $this->get_prospect( (int) $tracking['prospect_id'] );
        if ( is_wp_error( $prospect ) ) {
            error_log( '[DirectReach] Failed to load prospect for tracking: ' . $prospect->get_error_message() );
            // Don't fail - just continue without prospect data
            $prospect = null;
        }
        
        // Get template details if used
        $template_info = null;
        if ( ! empty( $tracking['template_used'] ) ) {
            $template_info = $this->get_template_info( (int) $tracking['template_used'] );
        }
        
        // Format response
        $response_data = array(
            'id' => (int) $tracking['id'],
            'prospect_id' => (int) $tracking['prospect_id'],
            'email_number' => (int) $tracking['email_number'],
            'room_type' => $tracking['room_type'],
            'subject' => $tracking['subject'],
            'body_html' => $tracking['body_html'],
            'body_text' => $tracking['body_text'],
            'generated_by_ai' => (bool) $tracking['generated_by_ai'],
            'template_used' => $template_info,
            'ai_prompt_tokens' => (int) $tracking['ai_prompt_tokens'],
            'ai_completion_tokens' => (int) $tracking['ai_completion_tokens'],
            'url_included' => $tracking['url_included'],
            'copied_at' => $tracking['copied_at'],
            'sent_at' => $tracking['sent_at'],
            'opened_at' => $tracking['opened_at'],
            'clicked_at' => $tracking['clicked_at'],
            'status' => $tracking['status'],
            'tracking_token' => $tracking['tracking_token'],
        );
        
        // Add prospect context if available
        if ( $prospect ) {
            $response_data['prospect'] = array(
                'company_name' => $prospect['company_name'],
                'contact_name' => $prospect['contact_name'],
                'current_room' => $prospect['current_room'],
                'lead_score' => (int) $prospect['lead_score'],
            );
        }
        
        // Calculate token cost if AI generated
        if ( $tracking['generated_by_ai'] ) {
            $total_tokens = (int) $tracking['ai_prompt_tokens'] + (int) $tracking['ai_completion_tokens'];
            $cost = $this->calculate_token_cost(
                (int) $tracking['ai_prompt_tokens'],
                (int) $tracking['ai_completion_tokens']
            );
            
            $response_data['usage'] = array(
                'prompt_tokens' => (int) $tracking['ai_prompt_tokens'],
                'completion_tokens' => (int) $tracking['ai_completion_tokens'],
                'total_tokens' => $total_tokens,
                'cost' => $cost,
            );
        }
        
        return rest_ensure_response( array(
            'success' => true,
            'data' => $response_data,
        ));
    }

    /**
     * Get email tracking by prospect and email number
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function get_tracking_by_prospect( $request ) {
        global $wpdb;
        
        $prospect_id = (int) $request->get_param( 'prospect_id' );
        $email_number = (int) $request->get_param( 'email_number' );
        $table = $wpdb->prefix . 'rtr_email_tracking';
        
        // Get most recent tracking record for this prospect/email combination
        $tracking = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} 
                WHERE prospect_id = %d 
                AND email_number = %d 
                ORDER BY id DESC 
                LIMIT 1",
                $prospect_id,
                $email_number
            ),
            ARRAY_A
        );
        
        if ( ! $tracking ) {
            return new WP_Error(
                'not_found',
                'No email tracking found for this prospect and email number',
                array( 'status' => 404 )
            );
        }
        
        // Reuse the get_tracking_details logic by creating a mock request
        $mock_request = new WP_REST_Request( 'GET', $this->namespace . '/' . $this->rest_base . '/tracking/' . $tracking['id'] );
        $mock_request->set_param( 'tracking_id', $tracking['id'] );
        
        return $this->get_tracking_details( $mock_request );
    }

    /**
     * Get template info by ID
     *
     * @param int $template_id Template ID
     * @return array|null Template info or null
     */
    private function get_template_info( $template_id ) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'rtr_email_templates';
        $template = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, template_name, is_global FROM {$table} WHERE id = %d",
                $template_id
            ),
            ARRAY_A
        );
        
        if ( ! $template ) {
            return null;
        }
        
        return array(
            'id' => (int) $template['id'],
            'name' => $template['template_name'],
            'is_global' => (bool) $template['is_global'],
        );
    }

    /**
     * Calculate token cost
     *
     * Gemini 1.5 Pro pricing (as of Oct 2024):
     * - Input: $0.00125 / 1K tokens
     * - Output: $0.005 / 1K tokens
     *
     * @param int $prompt_tokens Prompt tokens
     * @param int $completion_tokens Completion tokens
     * @return float Cost in USD
     */
    private function calculate_token_cost( $prompt_tokens, $completion_tokens ) {
        $input_cost = ( $prompt_tokens / 1000 ) * 0.00125;
        $output_cost = ( $completion_tokens / 1000 ) * 0.005;
        
        return round( $input_cost + $output_cost, 6 );
    }

    /**
     * Permission check for tracking endpoints
     *
     * @param WP_REST_Request $request Request object
     * @return bool|WP_Error
     */
    public function get_tracking_permissions_check( $request ) {
        if ( ! current_user_can( 'edit_posts' ) ) {
            return new WP_Error(
                'rest_forbidden',
                'You do not have permission to view email tracking',
                array( 'status' => 403 )
            );
        }
        
        return true;
    }

}