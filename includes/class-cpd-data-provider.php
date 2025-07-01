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
        return $this->wpdb->get_row( $this->wpdb->prepare( "SELECT * FROM %i WHERE account_id = %s", $table_name, $account_id ) );
    }

    /**
     * Get the account ID for a given WordPress user.
     *
     * @param int $user_id The WordPress user ID.
     * @return string|null The account ID or null if not linked.
     */
    public function get_account_id_by_user_id( $user_id ) {
        $user_client_table = $this->wpdb->prefix . 'cpd_client_users';
        $client_table = $this->wpdb->prefix . 'cpd_clients';
        
        return $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT c.account_id 
                 FROM %i AS uc
                 INNER JOIN %i AS c ON uc.client_id = c.id
                 WHERE uc.user_id = %d",
                $user_client_table,
                $client_table,
                $user_id
            )
        );
    }

    /**
     * Get campaign data aggregated by Ad Group for a specific account and date range.
     * This data is suitable for the Ad Group Table and Pie Chart.
     *
     * @param string $account_id The account ID.
     * @param string $start_date The start date of the range.
     * @param string $end_date The end date of the range.
     * @return array An array of campaign data rows aggregated by ad group.
     */
    public function get_campaign_data_by_ad_group( $account_id, $start_date, $end_date ) {
        $table_name = 'dashdev_cpd_campaign_data'; // Using fixed table name as per your setup

        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT
                    ad_group_name,
                    SUM(impressions) AS impressions,
                    SUM(daily_reach) AS reach,
                    SUM(clicks) AS clicks,
                    (SUM(clicks) / NULLIF(SUM(impressions), 0)) * 100 AS ctr,
                    MAX(date) AS last_updated
                 FROM %i
                 WHERE account_id = %s AND date BETWEEN %s AND %s
                 GROUP BY ad_group_name
                 ORDER BY ad_group_name ASC",
                $table_name, // Using %i for table name
                $account_id,
                $start_date,
                $end_date
            )
        );
    }

    /**
     * Get campaign data aggregated by Date for a specific account and date range.
     * This data is suitable for the Impressions Line Chart.
     *
     * @param string $account_id The account ID.
     * @param string $start_date The start date of the range.
     * @param string $end_date The end date of the range.
     * @return array An array of campaign data rows aggregated by date.
     */
    public function get_campaign_data_by_date( $account_id, $start_date, $end_date ) {
        $table_name = 'dashdev_cpd_campaign_data'; // Using fixed table name as per your setup

        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT
                    date,
                    SUM(impressions) AS impressions
                 FROM %i
                 WHERE account_id = %s AND date BETWEEN %s AND %s
                 GROUP BY date
                 ORDER BY date ASC",
                $table_name, // Using %i for table name
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
        
        // Prepare the SQL query to fetch visitor data for the given account_id.
        // UPDATED: Changed ORDER BY from 'visit_time' to 'last_seen_at'
        $sql_query = $this->wpdb->prepare(
            "SELECT * FROM %i WHERE account_id = %s AND is_archived = %d ORDER BY last_seen_at DESC",
            $table_name, // Using %i for table name
            $account_id,
            0 // Only get unarchived visitors
        );

        error_log('CPD_Data_Provider: Fetching visitor data with query: ' . $sql_query);
        
        $results = $this->wpdb->get_results( $sql_query );

        if ( empty( $results ) ) {
            error_log('CPD_Data_Provider: get_visitor_data returned no results for account_id: ' . $account_id);
        }

        if ( $this->wpdb->last_error ) {
            error_log('CPD_Data_Provider: Database error: ' . $this->wpdb->last_error);
        }

        return $results;
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
        $campaign_table = 'dashdev_cpd_campaign_data'; // Using fixed table name
        $visitor_table = $this->wpdb->prefix . 'cpd_visitors';

        // Sum up metrics from the campaign data table.
        $metrics = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT SUM(impressions) as total_impressions, SUM(daily_reach) as total_reach, SUM(clicks) as total_clicks
                 FROM %i WHERE account_id = %s AND date BETWEEN %s AND %s",
                $campaign_table, // Using %i for table name
                $account_id,
                $start_date,
                $end_date
            )
        );

        // Count new contacts and CRM additions from the visitors table.
        $contact_counts = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT SUM(new_profile) as new_contacts, SUM(is_crm_added) as crm_additions
                 FROM %i WHERE account_id = %s AND last_seen_at BETWEEN %s AND %s",
                $visitor_table, // Using %i for table name
                $account_id,
                $start_date,
                $end_date
            )
        );
        
        $total_clicks = isset($metrics->total_clicks) ? $metrics->total_clicks : 0;
        $total_impressions = isset($metrics->total_impressions) ? $metrics->total_impressions : 0;
        $total_reach = isset($metrics->total_reach) ? $metrics->total_reach : 0;
        $new_contacts = isset($contact_counts->new_contacts) ? $contact_counts->new_contacts : 0;
        $crm_additions = isset($contact_counts->crm_additions) ? $contact_counts->crm_additions : 0;

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
