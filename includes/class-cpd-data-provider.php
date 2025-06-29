<?php
/**
 * Provides data from the database tables for both admin and public dashboards.
 *
 * @package CPD_Dashboard
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CPD_Data_Provider {

    private $wpdb;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    /**
     * Get all client accounts from the database.
     *
     * @return array An array of client objects.
     */
    public function get_all_client_accounts() {
        $table_name = $this->wpdb->prefix . 'cpd_clients';
        return $this->wpdb->get_results( "SELECT * FROM $table_name ORDER BY client_name ASC" );
    }

    /**
     * Get client details by Account ID.
     *
     * @param string $account_id The account ID.
     * @return object|null The client object or null if not found.
     */
    public function get_client_by_account_id( $account_id ) {
        $table_name = $this->wpdb->prefix . 'cpd_clients';
        return $this->wpdb->get_row( $this->wpdb->prepare( "SELECT * FROM $table_name WHERE account_id = %s", $account_id ) );
    }

    /**
     * Get the account ID for a given WordPress user.
     *
     * @param int $user_id The WordPress user ID.
     * @return string|null The account ID or null if not linked.
     */
    public function get_account_id_by_user_id( $user_id ) {
        // Corrected query to use a JOIN to get the account_id from the clients table.
        $user_client_table = $this->wpdb->prefix . 'cpd_client_users';
        $client_table = $this->wpdb->prefix . 'cpd_clients';
        
        return $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT c.account_id 
                 FROM $user_client_table AS uc
                 INNER JOIN $client_table AS c ON uc.client_id = c.id
                 WHERE uc.user_id = %d",
                $user_id
            )
        );
    }

    /**
     * Get campaign data (Ad Groups) for a specific account and date range.
     *
     * @param string $account_id The account ID.
     * @param string $start_date The start date of the range.
     * @param string $end_date The end date of the range.
     * @return array An array of campaign data rows.
     */
    public function get_campaign_data( $account_id, $start_date, $end_date ) {
        $table_name = $this->wpdb->prefix . 'cpd_campaign_data';
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM $table_name WHERE account_id = %s AND last_updated BETWEEN %s AND %s ORDER BY ad_group_name ASC",
                $account_id,
                $start_date,
                $end_date
            )
        );
    }

    /**
     * Get visitor data for a specific account.
     *
     * @param string $account_id The account ID.
     * @return array An array of visitor data rows.
     */
    public function get_visitor_data( $account_id ) {
        $table_name = $this->wpdb->prefix . 'cpd_visitors';
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM $table_name WHERE account_id = %s AND is_archived = %d ORDER BY visit_time DESC",
                $account_id,
                0 // Only get unarchived visitors
            )
        );
    }

    /**
     * Get summary metrics for a specific account and date range.
     *
     * @param string $account_id The account ID.
     * @param string $start_date The start date of the range.
     * @param string $end_date The end date of the range.
     * @return array An associative array of aggregated metrics.
     */
    public function get_summary_metrics( $account_id, $start_date, $end_date ) {
        $campaign_table = $this->wpdb->prefix . 'cpd_campaign_data';
        $visitor_table = $this->wpdb->prefix . 'cpd_visitors';

        // Sum up metrics from the campaign data table.
        $metrics = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT SUM(impressions) as total_impressions, SUM(reach) as total_reach, SUM(clicks) as total_clicks
                 FROM $campaign_table
                 WHERE account_id = %s AND last_updated BETWEEN %s AND %s",
                $account_id,
                $start_date,
                $end_date
            )
        );

        // Count new contacts and CRM additions from the visitors table.
        $contact_counts = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT COUNT(*) as new_contacts, SUM(is_crm_added) as crm_additions
                 FROM $visitor_table
                 WHERE account_id = %s AND visit_time BETWEEN %s AND %s",
                $account_id,
                $start_date,
                $end_date
            )
        );
        
        // Add a check to ensure $metrics and $contact_counts are not null.
        $total_clicks = isset($metrics->total_clicks) ? $metrics->total_clicks : 0;
        $total_impressions = isset($metrics->total_impressions) ? $metrics->total_impressions : 0;
        $total_reach = isset($metrics->total_reach) ? $metrics->total_reach : 0;
        $new_contacts = isset($contact_counts->new_contacts) ? $contact_counts->new_contacts : 0;
        $crm_additions = isset($contact_counts->crm_additions) ? $contact_counts->crm_additions : 0;

        // Calculate CTR (Click-Through Rate).
        $ctr = ($total_impressions > 0) ? ($total_clicks / $total_impressions) * 100 : 0;

        return array(
            'impressions' => number_format( $total_impressions ),
            'reach'       => number_format( $total_reach ),
            'ctr'         => number_format( $ctr, 2 ) . '%',
            'new_contacts'  => number_format( $new_contacts ),
            'crm_additions' => number_format( $crm_additions ),
        );
    }
}