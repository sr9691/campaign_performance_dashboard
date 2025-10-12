<?php
/**
 * Email Tracking Manager
 *
 * Manages email tracking records in the database.
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
     * Table name
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
     * @param array $data Tracking data
     * @return int|WP_Error Tracking ID or error
     */
    public function create_tracking_record( $data ) {
        // Validate required fields
        $required = array( 'prospect_id', 'email_number', 'room_type' );
        foreach ( $required as $field ) {
            if ( ! isset( $data[ $field ] ) ) {
                return new WP_Error(
                    'missing_field',
                    sprintf( 'Missing required field: %s', $field )
                );
            }
        }

        // Prepare data for insertion
        $insert_data = array(
            'prospect_id' => (int) $data['prospect_id'],
            'email_number' => (int) $data['email_number'],
            'room_type' => sanitize_text_field( $data['room_type'] ),
            'subject' => isset( $data['subject'] ) ? sanitize_text_field( $data['subject'] ) : null,
            'body_html' => isset( $data['body_html'] ) ? wp_kses_post( $data['body_html'] ) : null,
            'body_text' => isset( $data['body_text'] ) ? sanitize_textarea_field( $data['body_text'] ) : null,
            'generated_by_ai' => isset( $data['generated_by_ai'] ) ? (int) $data['generated_by_ai'] : 0,
            'template_used' => isset( $data['template_used'] ) ? (int) $data['template_used'] : null,
            'ai_prompt_tokens' => isset( $data['ai_prompt_tokens'] ) ? (int) $data['ai_prompt_tokens'] : null,
            'ai_completion_tokens' => isset( $data['ai_completion_tokens'] ) ? (int) $data['ai_completion_tokens'] : null,
            'url_included' => isset( $data['url_included'] ) ? esc_url_raw( $data['url_included'] ) : null,
            'tracking_token' => isset( $data['tracking_token'] ) ? sanitize_text_field( $data['tracking_token'] ) : null,
            'status' => isset( $data['status'] ) ? sanitize_text_field( $data['status'] ) : 'pending',
            'sent_at' => current_time( 'mysql' ),
        );

        // Format for wpdb
        $format = array(
            '%d', // prospect_id
            '%d', // email_number
            '%s', // room_type
            '%s', // subject
            '%s', // body_html
            '%s', // body_text
            '%d', // generated_by_ai
            '%d', // template_used
            '%d', // ai_prompt_tokens
            '%d', // ai_completion_tokens
            '%s', // url_included
            '%s', // tracking_token
            '%s', // status
            '%s', // sent_at
        );

        // Insert record
        $result = $this->wpdb->insert(
            $this->table_name,
            $insert_data,
            $format
        );

        if ( false === $result ) {
            error_log( '[DirectReach] Failed to create tracking record: ' . $this->wpdb->last_error );
            return new WP_Error(
                'database_error',
                'Failed to create tracking record: ' . $this->wpdb->last_error
            );
        }

        $tracking_id = $this->wpdb->insert_id;

        error_log( sprintf(
            '[DirectReach] Tracking record created: id=%d, prospect=%d, ai=%d',
            $tracking_id,
            $insert_data['prospect_id'],
            $insert_data['generated_by_ai']
        ));

        return $tracking_id;
    }

    /**
     * Update tracking status
     *
     * @param int    $tracking_id Tracking ID
     * @param string $status New status
     * @param array  $additional_data Additional data to update
     * @return bool|WP_Error Success or error
     */
    public function update_tracking_status( $tracking_id, $status, $additional_data = array() ) {
        // Validate status
        $valid_statuses = array( 'pending', 'copied', 'sent', 'opened', 'clicked' );
        if ( ! in_array( $status, $valid_statuses, true ) ) {
            return new WP_Error(
                'invalid_status',
                sprintf( 'Invalid status: %s', $status )
            );
        }

        // Build update data
        $update_data = array_merge(
            array( 'status' => $status ),
            $additional_data
        );

        // Update record
        $result = $this->wpdb->update(
            $this->table_name,
            $update_data,
            array( 'id' => (int) $tracking_id ),
            null, // Let wpdb determine format
            array( '%d' )
        );

        if ( false === $result ) {
            error_log( '[DirectReach] Failed to update tracking status: ' . $this->wpdb->last_error );
            return new WP_Error(
                'database_error',
                'Failed to update tracking status: ' . $this->wpdb->last_error
            );
        }

        return true;
    }

    /**
     * Record email open via tracking token
     *
     * @param string $token Tracking token
     * @return bool Success
     */
    public function record_open( $token ) {
        if ( empty( $token ) ) {
            return false;
        }

        $token = sanitize_text_field( $token );

        // Check if already opened
        $opened_at = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT opened_at FROM {$this->table_name} WHERE tracking_token = %s",
                $token
            )
        );

        // Only record first open
        if ( ! empty( $opened_at ) ) {
            return true;
        }

        // Update record
        $result = $this->wpdb->update(
            $this->table_name,
            array(
                'opened_at' => current_time( 'mysql' ),
                'status' => 'opened',
            ),
            array( 'tracking_token' => $token ),
            array( '%s', '%s' ),
            array( '%s' )
        );

        if ( false !== $result && $result > 0 ) {
            error_log( sprintf( '[DirectReach] Email opened: token=%s', $token ) );
        }

        return false !== $result;
    }

    /**
     * Get tracking record by ID
     *
     * @param int $tracking_id Tracking ID
     * @return array|null Tracking record or null
     */
    public function get_tracking_record( $tracking_id ) {
        $record = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE id = %d",
                (int) $tracking_id
            ),
            ARRAY_A
        );

        return $record ?: null;
    }

    /**
     * Get tracking records for prospect
     *
     * @param int   $prospect_id Prospect ID
     * @param array $args Query arguments
     * @return array Tracking records
     */
    public function get_prospect_tracking( $prospect_id, $args = array() ) {
        $defaults = array(
            'limit' => 50,
            'offset' => 0,
            'order_by' => 'sent_at',
            'order' => 'DESC',
        );

        $args = wp_parse_args( $args, $defaults );

        $query = $this->wpdb->prepare(
            "SELECT * FROM {$this->table_name}
            WHERE prospect_id = %d
            ORDER BY {$args['order_by']} {$args['order']}
            LIMIT %d OFFSET %d",
            (int) $prospect_id,
            (int) $args['limit'],
            (int) $args['offset']
        );

        $records = $this->wpdb->get_results( $query, ARRAY_A );

        return $records ?: array();
    }

    /**
     * Get tracking statistics
     *
     * @param array $filters Optional filters (campaign_id, room_type, date_from, date_to)
     * @return array Statistics
     */
    public function get_tracking_stats( $filters = array() ) {
        $where = array( '1=1' );
        $prepare_args = array();

        // Build WHERE clause
        if ( ! empty( $filters['campaign_id'] ) ) {
            $where[] = 'p.campaign_id = %d';
            $prepare_args[] = (int) $filters['campaign_id'];
        }

        if ( ! empty( $filters['room_type'] ) ) {
            $where[] = 't.room_type = %s';
            $prepare_args[] = sanitize_text_field( $filters['room_type'] );
        }

        if ( ! empty( $filters['date_from'] ) ) {
            $where[] = 't.sent_at >= %s';
            $prepare_args[] = sanitize_text_field( $filters['date_from'] );
        }

        if ( ! empty( $filters['date_to'] ) ) {
            $where[] = 't.sent_at <= %s';
            $prepare_args[] = sanitize_text_field( $filters['date_to'] );
        }

        $where_clause = implode( ' AND ', $where );

        // Get statistics
        $query = "
            SELECT 
                COUNT(*) as total_emails,
                SUM(CASE WHEN t.generated_by_ai = 1 THEN 1 ELSE 0 END) as ai_generated,
                SUM(CASE WHEN t.status = 'copied' OR t.status = 'sent' THEN 1 ELSE 0 END) as sent_count,
                SUM(CASE WHEN t.opened_at IS NOT NULL THEN 1 ELSE 0 END) as opened_count,
                SUM(CASE WHEN t.clicked_at IS NOT NULL THEN 1 ELSE 0 END) as clicked_count,
                SUM(COALESCE(t.ai_prompt_tokens, 0)) as total_prompt_tokens,
                SUM(COALESCE(t.ai_completion_tokens, 0)) as total_completion_tokens
            FROM {$this->table_name} t
            LEFT JOIN {$this->wpdb->prefix}rtr_prospects p ON t.prospect_id = p.id
            WHERE {$where_clause}
        ";

        if ( ! empty( $prepare_args ) ) {
            $query = $this->wpdb->prepare( $query, $prepare_args );
        }

        $stats = $this->wpdb->get_row( $query, ARRAY_A );

        if ( ! $stats ) {
            return array();
        }

        // Calculate rates
        $total = (int) $stats['total_emails'];
        $sent = (int) $stats['sent_count'];

        $stats['open_rate'] = $sent > 0 ? round( ( (int) $stats['opened_count'] / $sent ) * 100, 2 ) : 0;
        $stats['click_rate'] = $sent > 0 ? round( ( (int) $stats['clicked_count'] / $sent ) * 100, 2 ) : 0;
        $stats['ai_percentage'] = $total > 0 ? round( ( (int) $stats['ai_generated'] / $total ) * 100, 2 ) : 0;

        return $stats;
    }

    /**
     * Delete tracking records for prospect
     *
     * @param int $prospect_id Prospect ID
     * @return bool Success
     */
    public function delete_prospect_tracking( $prospect_id ) {
        $result = $this->wpdb->delete(
            $this->table_name,
            array( 'prospect_id' => (int) $prospect_id ),
            array( '%d' )
        );

        return false !== $result;
    }

    /**
     * Clean up old tracking records
     *
     * @param int $days_old Delete records older than this many days
     * @return int Number of records deleted
     */
    public function cleanup_old_records( $days_old = 180 ) {
        $cutoff_date = date( 'Y-m-d H:i:s', strtotime( "-{$days_old} days" ) );

        $deleted = $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$this->table_name} WHERE sent_at < %s",
                $cutoff_date
            )
        );

        if ( $deleted > 0 ) {
            error_log( sprintf(
                '[DirectReach] Cleaned up %d old tracking records (older than %d days)',
                $deleted,
                $days_old
            ));
        }

        return $deleted;
    }
}