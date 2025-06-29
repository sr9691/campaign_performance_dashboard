<?php
/**
 * Handles sending daily CRM feed emails to clients.
 *
 * @package CPD_Dashboard
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CPD_Email_Handler {

    /**
     * Sends a daily CRM feed email for visitors marked for CRM addition.
     * This function is called by the WordPress cron job.
     */
    public static function send_daily_crm_feed() {
        global $wpdb;
        $visitor_table = $wpdb->prefix . 'cpd_visitors';
        $client_table = $wpdb->prefix . 'cpd_clients';
        $log_table = $wpdb->prefix . 'cpd_action_logs';

        // Get all unique account IDs that have visitors flagged for CRM addition but not yet archived.
        $account_ids_to_email = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT account_id FROM $visitor_table WHERE is_crm_added = %d AND is_archived = %d",
                1,
                0
            )
        );

        if ( empty( $account_ids_to_email ) ) {
            // No emails to send today.
            return;
        }

        foreach ( $account_ids_to_email as $account_id ) {
            // Get the client's CRM email address from the database.
            $client_info = $wpdb->get_row(
                $wpdb->prepare( "SELECT client_name, crm_feed_email FROM $client_table WHERE account_id = %s", $account_id )
            );

            if ( ! $client_info || empty( $client_info->crm_feed_email ) ) {
                // Log if a client has no email configured.
                $log_data = array(
                    'user_id'     => 0, // Cron job, no user.
                    'action_type' => 'EMAIL_FAILED',
                    'description' => 'CRM email feed failed: No email configured for Account ID: ' . $account_id,
                );
                $wpdb->insert( $log_table, $log_data );
                continue; // Skip to the next client.
            }
            
            // Get visitor data for this client flagged for CRM.
            $visitors_data = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM $visitor_table WHERE account_id = %s AND is_crm_added = %d AND is_archived = %d",
                    $account_id,
                    1,
                    0
                ),
                ARRAY_A
            );
            
            if ( empty( $visitors_data ) ) {
                continue; // Should not happen, but a good safeguard.
            }

            // --- Generate CSV content from the visitor data ---
            $csv_output = fopen( 'php://temp', 'r+' );
            
            // Get CSV headers from the first row's keys.
            fputcsv( $csv_output, array_keys( $visitors_data[0] ) );
            
            // Add data rows.
            foreach ( $visitors_data as $row ) {
                fputcsv( $csv_output, $row );
            }

            // Rewind the stream to read its content.
            rewind( $csv_output );
            $csv_content = stream_get_contents( $csv_output );
            fclose( $csv_output );

            // --- Prepare and send the email ---
            $to = explode( ',', $client_info->crm_feed_email ); // Supports comma-separated emails.
            $subject = 'Daily CRM Feed for ' . $client_info->client_name . ' - ' . date( 'Y-m-d' );
            $message = 'Attached is the daily visitor data you marked for CRM integration.';
            $attachments = array(
                array(
                    'name'    => 'crm_feed_' . $account_id . '_' . date( 'Y-m-d' ) . '.csv',
                    'content' => $csv_content,
                    'type'    => 'text/csv',
                ),
            );

            // Use wp_mail with the attachment filter.
            // This is a common way to send attachments without saving a physical file.
            add_filter( 'wp_mail_attachments', function( $attachments_list ) use ( $attachments ) {
                return array_merge( $attachments_list, $attachments );
            } );

            $email_sent = wp_mail( $to, $subject, $message );

            // --- Log the outcome and update records ---
            if ( $email_sent ) {
                // Update visitor records to be archived so they are not sent again.
                $visitors_to_archive_ids = wp_list_pluck( $visitors_data, 'id' );
                $ids_string = implode( ',', array_map( 'intval', $visitors_to_archive_ids ) );

                $wpdb->query(
                    "UPDATE $visitor_table SET is_archived = 1 WHERE id IN ($ids_string)"
                );

                // Log success.
                $log_description = 'Daily CRM feed sent to ' . $client_info->client_name . ' (' . implode(', ', $to) . '). ' . count($visitors_data) . ' records archived.';
                $log_data = array(
                    'user_id'     => 0,
                    'action_type' => 'EMAIL_SUCCESS',
                    'description' => $log_description,
                );
                $wpdb->insert( $log_table, $log_data );

            } else {
                // Log failure.
                $log_description = 'Failed to send daily CRM feed to ' . $client_info->client_name . ' (' . implode(', ', $to) . ').';
                $log_data = array(
                    'user_id'     => 0,
                    'action_type' => 'EMAIL_FAILED',
                    'description' => $log_description,
                );
                $wpdb->insert( $log_table, $log_data );
            }
        }
    }
}