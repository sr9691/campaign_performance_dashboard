<?php
/**
 * Email Tracking Manager
 *
 * Manages email tracking records for the RTR dashboard.
 * Handles creation, updates, and queries for email tracking data.
 *
 * @package DirectReach
 * @subpackage RTR
 * @since 2.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CPD_Email_Tracking_Manager {

    /**
     * WordPress database instance
     *
     * @var wpdb
     */
    private $wpdb;

    /**
     * Tracking table name
     *
     * @var string
     */
    private $table_name;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'rtr_email_tracking';
    }

    /**
     * Create tracking record
     *
     * UPDATED: Now accepts an array of data instead of positional parameters.
     * This matches the new calling pattern from Email_Generation_Controller.
     *
     * @param array $data {
     *     Tracking data array
     *
     *     @type int    $prospect_id           Prospect ID (required)
     *     @type string $room_type             Room type: problem, solution, offer (required)
     *     @type int    $email_number          Email sequence number (required)
     *     @type string $subject               Email subject (required)
     *     @type string $body_html             Email body HTML (required)
     *     @type string $body_text             Email body plain text (required)
     *     @type string $tracking_token        Unique tracking token (required)
     *     @type string $status                Status: generated, copied, sent, opened (required)
     *     @type int    $generated_by_ai       1 if AI generated, 0 otherwise (default: 0)
     *     @type string $url_included          URL included in email (optional)
     *     @type int    $template_used         Template ID used (optional)
     *     @type int    $ai_prompt_tokens      Number of prompt tokens used (optional)
     *     @type int    $ai_completion_tokens  Number of completion tokens used (optional)
     * }
     * @return int|false Insert ID on success, false on failure
     */
    public function create_tracking_record( $data ) {
        // Validate required fields
        $required_fields = array(
            'prospect_id',
            'visitor_id',
            'room_type',
            'email_number',
            'subject',
            'body_html',
            'body_text',
            'tracking_token',
            'status',
        );

        foreach ( $required_fields as $field ) {
            if ( ! isset( $data[ $field ] ) || $data[ $field ] === '' ) {
                error_log( sprintf(
                    '[DirectReach] Tracking creation failed: Missing required field "%s"',
                    $field
                ) );
                return false;
            }
        }

        // Validate room_type
        if ( ! in_array( $data['room_type'], array( 'problem', 'solution', 'offer' ), true ) ) {
            error_log( sprintf(
                '[DirectReach] Tracking creation failed: Invalid room_type "%s"',
                $data['room_type']
            ) );
            return false;
        }

        // Prepare insert data with defaults
        $insert_data = array(
            'prospect_id'           => absint( $data['prospect_id'] ),
            'visitor_id'            => absint( $data['visitor_id'] ),
            'room_type'             => sanitize_text_field( $data['room_type'] ),
            'email_number'          => absint( $data['email_number'] ),
            'subject'               => sanitize_text_field( $data['subject'] ),
            'body_html'             => wp_kses_post( $data['body_html'] ),
            'body_text'             => sanitize_textarea_field( $data['body_text'] ),
            'tracking_token'        => sanitize_text_field( $data['tracking_token'] ),
            'status'                => sanitize_text_field( $data['status'] ),
            'generated_by_ai'       => isset( $data['generated_by_ai'] ) ? absint( $data['generated_by_ai'] ) : 0,
            'url_included'          => isset( $data['url_included'] ) ? esc_url_raw( $data['url_included'] ) : null,
            'template_used'         => isset( $data['template_used'] ) ? absint( $data['template_used'] ) : null,
            'ai_prompt_tokens'      => isset( $data['ai_prompt_tokens'] ) ? absint( $data['ai_prompt_tokens'] ) : 0,
            'ai_completion_tokens'  => isset( $data['ai_completion_tokens'] ) ? absint( $data['ai_completion_tokens'] ) : 0,
            'created_at'            => current_time( 'mysql' ),
        );

        // Define data types for wpdb->insert
        $data_types = array(
            '%d', // prospect_id
            '%d', // visitor_id
            '%s', // room_type
            '%d', // email_number
            '%s', // subject
            '%s', // body_html
            '%s', // body_text
            '%s', // tracking_token
            '%s', // status
            '%d', // generated_by_ai
            '%s', // url_included
            '%d', // template_used
            '%d', // ai_prompt_tokens
            '%d', // ai_completion_tokens
            '%s', // created_at
        );

        // Insert the record
        $result = $this->wpdb->insert(
            $this->table_name,
            $insert_data,
            $data_types
        );

        if ( $result === false ) {
            error_log( sprintf(
                '[DirectReach] Tracking insert failed. DB Error: %s. Data: %s',
                $this->wpdb->last_error,
                wp_json_encode( $insert_data )
            ) );
            return false;
        }

        $insert_id = $this->wpdb->insert_id;

        error_log( sprintf(
            '[DirectReach] Created tracking record ID %d for prospect %d, email %d',
            $insert_id,
            $data['prospect_id'],
            $data['email_number']
        ) );

        return $insert_id;
    }

    /**
     * Update tracking record status
     *
     * @param int    $tracking_id Tracking record ID
     * @param string $status New status
     * @param array  $additional_data Optional additional fields to update
     * @return bool True on success, false on failure
     */
    public function update_status( $tracking_id, $status, $additional_data = array() ) {
        $update_data = array_merge(
            array( 'status' => sanitize_text_field( $status ) ),
            $additional_data
        );

        $result = $this->wpdb->update(
            $this->table_name,
            $update_data,
            array( 'id' => absint( $tracking_id ) ),
            array_fill( 0, count( $update_data ), '%s' ),
            array( '%d' )
        );

        if ( $result === false ) {
            error_log( sprintf(
                '[DirectReach] Failed to update tracking record %d. DB Error: %s',
                $tracking_id,
                $this->wpdb->last_error
            ) );
            return false;
        }

        return true;
    }

    /**
     * Mark email as copied
     *
     * @param int    $tracking_id Tracking record ID
     * @param string $url_included URL that was included in email (optional)
     * @return bool True on success, false on failure
     */
    public function mark_as_copied( $tracking_id, $url_included = null ) {
        $update_data = array(
            'status' => 'copied',
            'copied_at' => current_time( 'mysql' ),
        );

        if ( $url_included ) {
            $update_data['url_included'] = esc_url_raw( $url_included );
        }

        return $this->update_status( $tracking_id, 'copied', $update_data );
    }

    /**
     * Mark email as opened
     *
     * @param string $tracking_token Tracking token from pixel
     * @return bool True on success, false on failure
     */
    public function mark_as_opened( $tracking_token, $recipient_ip = null ) {
        // Find the tracking record
        $tracking = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT id, status, opened_at FROM {$this->table_name} WHERE tracking_token = %s LIMIT 1",
                $tracking_token
            )
        );

        if ( ! $tracking ) {
            error_log( sprintf(
                '[DirectReach] Tracking token not found: %s',
                $tracking_token
            ) );
            return false;
        }

        // Update opened_at every time (tracks most recent open)
        $update_data = array( 'opened_at' => current_time( 'mysql' ) );
        
        // Store recipient IP if provided
        if ( ! empty( $recipient_ip ) ) {
            $update_data['recipient_ip'] = $recipient_ip;
        }
        
        // Only change status if not already opened
        if ( $tracking->status !== 'opened' ) {
            $update_data['status'] = 'opened';
        }

        $result = $this->wpdb->update(
            $this->table_name,
            $update_data,
            array( 'id' => $tracking->id ),
            array_fill( 0, count( $update_data ), '%s' ),
            array( '%d' )
        );

        if ( $result === false ) {
            error_log( sprintf(
                '[DirectReach] Failed to update tracking %d. DB Error: %s',
                $tracking->id,
                $this->wpdb->last_error
            ) );
            return false;
        }

        error_log( sprintf(
            '[DirectReach] Updated tracking record %d (opened: %s, recipient_ip: %s)',
            $tracking->id,
            $tracking->status === 'opened' ? 'already' : 'now',
            $recipient_ip ?? 'not provided'
        ) );

        return true;
    }

    /**
     * Get tracking record by ID
     *
     * @param int $tracking_id Tracking record ID
     * @return object|null Tracking record or null if not found
     */
    public function get_by_id( $tracking_id ) {
        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE id = %d",
                $tracking_id
            )
        );
    }

    /**
     * Get tracking record by prospect and email number
     *
     * @param int $prospect_id Prospect ID
     * @param int $email_number Email sequence number
     * @return object|null Most recent tracking record or null
     */
    public function get_by_prospect_email( $prospect_id, $email_number ) {
        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table_name} 
                WHERE prospect_id = %d 
                AND email_number = %d 
                ORDER BY created_at DESC 
                LIMIT 1",
                $prospect_id,
                $email_number
            )
        );
    }

    /**
     * Get all tracking records for a prospect
     *
     * @param int $prospect_id Prospect ID
     * @return array Array of tracking records
     */
    public function get_by_prospect( $prospect_id ) {
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table_name} 
                WHERE prospect_id = %d 
                ORDER BY email_number ASC, created_at DESC",
                $prospect_id
            )
        );
    }

    /**
     * Get tracking statistics
     *
     * @param array $filters Optional filters (prospect_id, campaign_id, date_range)
     * @return array Statistics array
     */
    public function get_stats( $filters = array() ) {
        $where_clauses = array( '1=1' );
        $where_values = array();

        if ( ! empty( $filters['prospect_id'] ) ) {
            $where_clauses[] = 'prospect_id = %d';
            $where_values[] = absint( $filters['prospect_id'] );
        }

        if ( ! empty( $filters['date_from'] ) ) {
            $where_clauses[] = 'created_at >= %s';
            $where_values[] = sanitize_text_field( $filters['date_from'] );
        }

        if ( ! empty( $filters['date_to'] ) ) {
            $where_clauses[] = 'created_at <= %s';
            $where_values[] = sanitize_text_field( $filters['date_to'] );
        }

        $where_sql = implode( ' AND ', $where_clauses );

        if ( ! empty( $where_values ) ) {
            $where_sql = $this->wpdb->prepare( $where_sql, $where_values );
        }

        $stats = $this->wpdb->get_row(
            "SELECT 
                COUNT(*) as total_emails,
                SUM(CASE WHEN generated_by_ai = 1 THEN 1 ELSE 0 END) as ai_generated,
                SUM(CASE WHEN status = 'copied' OR status = 'opened' THEN 1 ELSE 0 END) as copied_count,
                SUM(CASE WHEN status = 'opened' THEN 1 ELSE 0 END) as opened_count,
                SUM(ai_prompt_tokens) as total_prompt_tokens,
                SUM(ai_completion_tokens) as total_completion_tokens
            FROM {$this->table_name}
            WHERE {$where_sql}",
            ARRAY_A
        );

        // Calculate costs (Gemini 1.5 Pro pricing)
        $input_cost = ( $stats['total_prompt_tokens'] / 1000 ) * 0.00125;
        $output_cost = ( $stats['total_completion_tokens'] / 1000 ) * 0.005;
        $stats['total_cost'] = round( $input_cost + $output_cost, 6 );

        // Calculate rates
        if ( $stats['copied_count'] > 0 ) {
            $stats['open_rate'] = round( ( $stats['opened_count'] / $stats['copied_count'] ) * 100, 2 );
        } else {
            $stats['open_rate'] = 0;
        }

        return $stats;
    }

    /**
     * Delete old tracking records
     *
     * @param int $days_old Delete records older than this many days
     * @return int Number of records deleted
     */
    public function cleanup_old_records( $days_old = 90 ) {
        $date_threshold = date( 'Y-m-d H:i:s', strtotime( "-{$days_old} days" ) );

        $result = $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$this->table_name} WHERE created_at < %s",
                $date_threshold
            )
        );

        if ( $result === false ) {
            error_log( sprintf(
                '[DirectReach] Failed to cleanup old tracking records. DB Error: %s',
                $this->wpdb->last_error
            ) );
            return 0;
        }

        error_log( sprintf(
            '[DirectReach] Cleaned up %d tracking records older than %d days',
            $result,
            $days_old
        ) );

        return $result;
    }
}