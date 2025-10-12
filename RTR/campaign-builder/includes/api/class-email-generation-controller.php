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
    protected $namespace = 'directreach/rtr/v1';

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

        // Update prospect's last email sent timestamp
        $this->update_prospect_email_timestamp( $prospect_id );

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
     * Update prospect's last email sent timestamp
     *
     * @param int $prospect_id Prospect ID
     * @return bool Success
     */
    private function update_prospect_email_timestamp( $prospect_id ) {
        global $wpdb;

        $table = $wpdb->prefix . 'rtr_prospects';

        $result = $wpdb->update(
            $table,
            array( 'last_email_sent' => current_time( 'mysql' ) ),
            array( 'id' => $prospect_id ),
            array( '%s' ),
            array( '%d' )
        );

        return false !== $result;
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
}