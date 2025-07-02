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

        $where_clauses = ["date BETWEEN %s AND %s"];
        $prepare_args = [$start_date, $end_date];

        if ( $account_id !== null ) { // Only add account_id filter if it's explicitly provided
            $where_clauses[] = "account_id = %s";
            $prepare_args[] = $account_id;
        }

        $sql = "SELECT
                    ad_group_name,
                    SUM(impressions) AS impressions,
                    SUM(daily_reach) AS reach,
                    SUM(clicks) AS clicks,
                    (SUM(clicks) / NULLIF(SUM(impressions), 0)) * 100 AS ctr,
                    MAX(date) AS last_updated
                 FROM %i";
        
        if ( ! empty( $where_clauses ) ) {
            $sql .= " WHERE " . implode( " AND ", $where_clauses );
        }

        $sql .= " GROUP BY ad_group_name
                 ORDER BY ad_group_name ASC";

        $query = $this->wpdb->prepare( $sql, $table_name, ...$prepare_args );
        
        $results = $this->wpdb->get_results( $query );

        if ( $this->wpdb->last_error ) {
            error_log('CPD_Data_Provider: Database error for ad group data: ' . $this->wpdb->last_error);
        }

        return $results;
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

        $where_clauses = ["date BETWEEN %s AND %s"];
        $prepare_args = [$start_date, $end_date];

        if ( $account_id !== null ) { // Only add account_id filter if it's explicitly provided
            $where_clauses[] = "account_id = %s";
            $prepare_args[] = $account_id;
        }

        $sql = "SELECT
                    date,
                    SUM(impressions) AS impressions
                 FROM %i";
        
        if ( ! empty( $where_clauses ) ) {
            $sql .= " WHERE " . implode( " AND ", $where_clauses );
        }

        $sql .= " GROUP BY date
                 ORDER BY date ASC";

        $query = $this->wpdb->prepare( $sql, $table_name, ...$prepare_args );
        
        $results = $this->wpdb->get_results( $query );

        if ( $this->wpdb->last_error ) {
            error_log('CPD_Data_Provider: Database error for campaign data by date: ' . $this->wpdb->last_error);
        }

        return $results;
    }

    /**
     * Get visitor data for a specific account, showing only those not archived and not yet sent to CRM.
     *
     * @param string $account_id The account ID.
     * @return array An array of visitor data rows.
     */
    public function get_visitor_data( $account_id ) {
        $table_name = $this->wpdb->prefix . 'cpd_visitors';
        
        // Filter: Only show visitors that are NOT archived (is_archived = 0)
        // AND NOT flagged for CRM (is_crm_added = 0).
        $where_clauses = ["is_archived = %d", "is_crm_added = %d"];
        $prepare_args = [0, 0]; // 0 for not archived, 0 for not CRM-added

        if ( $account_id !== null ) { // Only add account_id filter if it's explicitly provided
            $where_clauses[] = "account_id = %s";
            $prepare_args[] = $account_id;
        }
        
        $sql_query = $this->wpdb->prepare(
            "SELECT * FROM %i WHERE " . implode(" AND ", $where_clauses) . " ORDER BY last_seen_at DESC",
            $table_name,
            ...$prepare_args
        );

        $results = $this->wpdb->get_results( $sql_query );

        if ( empty( $results ) ) {
            error_log('CPD_Data_Provider: get_visitor_data returned no results for account_id: ' . ($account_id ?? 'ALL'));
        }
        if ( $this->wpdb->last_error ) {
            error_log('CPD_Data_Provider: Database error for visitor data: ' . $this->wpdb->last_error);
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
        $campaign_table = 'dashdev_cpd_campaign_data';
        $visitor_table = $this->wpdb->prefix . 'cpd_visitors';

        // Build WHERE clause for campaign data
        $campaign_where_clauses = ["date BETWEEN %s AND %s"];
        $campaign_prepare_args = [$start_date, $end_date];
        if ( $account_id !== null ) {
            $campaign_where_clauses[] = "account_id = %s";
            $campaign_prepare_args[] = $account_id;
        }
        $campaign_where_sql = implode(" AND ", $campaign_where_clauses);

        // Sum up metrics from the campaign data table.
        $metrics = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT SUM(impressions) as total_impressions, SUM(daily_reach) as total_reach, SUM(clicks) as total_clicks
                 FROM %i WHERE " . $campaign_where_sql,
                $campaign_table,
                ...$campaign_prepare_args
            )
        );

        // Build WHERE clause for visitor data (using last_seen_at for date range for CRM additions)
        $visitor_where_clauses = ["last_seen_at BETWEEN %s AND %s"];
        $visitor_prepare_args = [$start_date, $end_date];
        if ( $account_id !== null ) {
            $visitor_where_clauses[] = "account_id = %s";
            $visitor_prepare_args[] = $account_id;
        }
        $visitor_where_sql = implode(" AND ", $visitor_where_clauses);

        // Count new contacts and CRM additions from the visitors table.
        // SUM(is_crm_added) correctly sums 1s for true and 0s for false.

        $crm_additions_sql = $this->wpdb->prepare(
            "SELECT SUM(new_profile) as new_contacts, SUM(CASE WHEN is_crm_added = 1 THEN 1 ELSE 0 END) as crm_additions_count
             FROM %i WHERE " . $visitor_where_sql,
            $visitor_table,
            ...$visitor_prepare_args
        );
        error_log('CPD_Data_Provider: CRM Additions SQL Query: ' . $crm_additions_sql); // Log the CRM SQL query

        $contact_counts = $this->wpdb->get_row( $crm_additions_sql );
        
        $total_clicks = isset($metrics->total_clicks) ? $metrics->total_clicks : 0;
        $total_impressions = isset($metrics->total_impressions) ? $metrics->total_impressions : 0;
        $total_reach = isset($metrics->total_reach) ? $metrics->total_reach : 0;
        $new_contacts = isset($contact_counts->new_contacts) ? $contact_counts->new_contacts : 0;
        $crm_additions = isset($contact_counts->crm_additions_count) ? $contact_counts->crm_additions_count : 0;
        error_log('CPD_Data_Provider: Summary Metrics - Impressions: ' . $metrics->total_impressions . ', Reach: ' . $metrics->total_reach . ', Clicks: ' . $metrics->total_clicks . ', New Contacts: ' . $contact_counts->new_contacts . ', CRM Additions: ' . $contact_counts->crm_additions_count);

        $ctr = ($total_impressions > 0) ? ($total_clicks / $total_impressions) * 100 : 0;

        $final_summary = array(
                'impressions' => number_format( $total_impressions ),
                'reach'       => number_format( $total_reach ),
                'ctr'         => number_format( $ctr, 2 ) . '%',
                'new_contacts'  => number_format( $new_contacts ),
                'crm_additions' => number_format( $crm_additions ),
            );

        return $final_summary;
    }
}