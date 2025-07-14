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
     * Helper function to normalize account_id input.
     * Converts string 'null' or 'all' to actual PHP null for consistent filtering.
     *
     * @param string|null $account_id The raw account ID from input.
     * @return string|null Normalized account ID.
     */
    private function normalize_account_id( $account_id ) {
        if ( is_string( $account_id ) && ( strtolower( $account_id ) === 'null' || strtolower( $account_id ) === 'all' ) ) {
            return null;
        }
        return $account_id;
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
        // error_log('CPD_Data_Provider::get_client_by_account_id - Account ID Passed: ' . $account_id);
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
        // error_log('CPD_Data_Provider::get_campaign_data_by_ad_group - Account ID Passed: ' . $account_id);
        $account_id = $this->normalize_account_id( $account_id ); // Normalize input
        $table_name = $this->wpdb->prefix . 'cpd_campaign_data'; // Using fixed table name as per your setup

        $sql_select_group_order = "
            SELECT
                ad_group_name,
                SUM(impressions) AS impressions,
                SUM(daily_reach) AS reach,
                SUM(clicks) AS clicks,
                (SUM(clicks) / NULLIF(SUM(impressions), 0)) * 100 AS ctr,
                MAX(date) AS last_updated
            FROM %i
            WHERE date BETWEEN %s AND %s";

        $prepare_args = [$table_name, $start_date, $end_date];

        if ( $account_id !== null ) { // Check for normalized null
            $sql_select_group_order .= " AND account_id = %s";
            $prepare_args[] = $account_id;
        }

        $sql_select_group_order .= " GROUP BY ad_group_name ORDER BY ad_group_name ASC";
        
        $query = $this->wpdb->prepare( $sql_select_group_order, ...$prepare_args );
        
        // error_log('CPD_Data_Provider::get_campaign_data_by_ad_group - Account ID: ' . ($account_id ?? 'NULL') . ' | Start Date: ' . $start_date . ' | End Date: ' . $end_date);
        // error_log('SQL Query for ad group: ' . $query); // Log the actual prepared query

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
        // error_log('CPD_Data_Provider::get_campaign_data_by_date - Account ID Passed: ' . $account_id . ' | Start Date: ' . $start_date . ' | End Date: ' . $end_date);
        $account_id = $this->normalize_account_id( $account_id ); // Normalize input
        $table_name = $this->wpdb->prefix . 'cpd_campaign_data'; // Using fixed table name as per your setup

        $sql_select_group_order = "
            SELECT
                date,
                SUM(impressions) AS impressions
            FROM %i
            WHERE date BETWEEN %s AND %s";

        $prepare_args = [$table_name, $start_date, $end_date];

        if ( $account_id !== null ) { // Check for normalized null
            $sql_select_group_order .= " AND account_id = %s";
            $prepare_args[] = $account_id;
        }

        $sql_select_group_order .= " GROUP BY date ORDER BY date ASC";
        
        $query = $this->wpdb->prepare( $sql_select_group_order, ...$prepare_args );
        
        // error_log('CPD_Data_Provider::get_campaign_data_by_date - Account ID: ' . ($account_id ?? 'NULL') . ' | Start Date: ' . $start_date . ' | End Date: ' . $end_date);
        // error_log('SQL Query for campaign by date: ' . $query); // Log the actual prepared query

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
/**
     * Get visitor data for a specific account, showing only those not archived and not yet sent to CRM.
     *
     * @param string $account_id The account ID.
     * @return array An array of visitor data rows.
     */
    public function get_visitor_data( $account_id ) {
        // error_log('CPD_Data_Provider::get_visitor_data - Account ID Passed: ' . $account_id);
        $account_id = $this->normalize_account_id( $account_id ); // Normalize input
        $table_name = $this->wpdb->prefix . 'cpd_visitors';
        
        $sql_select_order = "SELECT * FROM %i WHERE is_archived = %d AND is_crm_added = %d";
        $prepare_args = [$table_name, 0, 0]; // 0 for not archived, 0 for not CRM-added

        // Only add account_id filter if it's explicitly provided (not null)
        if ( $account_id !== null ) { // Check for normalized null
            $sql_select_order .= " AND account_id = %s";
            $prepare_args[] = $account_id;
        }
        
        $sql_select_order .= " ORDER BY last_seen_at DESC";

        // Prepare the query
        $sql_query = $this->wpdb->prepare( $sql_select_order, ...$prepare_args );
        
        $results = $this->wpdb->get_results( $sql_query );
        // error_log('CPD_Data_Provider::get_visitor_data - Account ID Passed: ' . $results);

        if ( empty( $results ) ) {
            error_log('CPD_Data_Provider: get_visitor_data returned no results for account_id: ' . ($account_id ?? 'ALL'));
        }
        if ( $this->wpdb->last_error ) {
            error_log('CPD_Data_Provider: Database error for visitor data: ' . $this->wpdb->last_error);
        }

        // Ensure CPD_Referrer_Logo class is loaded (it should already be via class-cpd-public.php or class-cpd-admin.php)
        if ( ! class_exists( 'CPD_Referrer_Logo' ) ) {
            // Adjust path if necessary based on your plugin structure
            require_once CPD_DASHBOARD_PLUGIN_DIR . 'includes/class-cpd-referrer-logo.php';
        }

        // Iterate through each visitor and add the referrer_logo_url and alt_text
        foreach ( $results as $visitor ) {
            $visitor->referrer_logo_url = CPD_Referrer_Logo::get_logo_for_visitor( $visitor );
            $visitor->referrer_alt_text = CPD_Referrer_Logo::get_alt_text_for_visitor( $visitor );
            $visitor->referrer_tooltip = CPD_Referrer_Logo::get_referrer_url_for_visitor( $visitor );
        }

        return $results;
    }
    
    /**
     * NEW: Get eligible visitor data for CRM emails (is_crm_added = 1 AND crm_sent IS NULL).
     * This is used for the CRM Email Management section in admin settings.
     *
     * @param string|null $account_id Optional. Filter by account ID. Null for all eligible visitors across all accounts.
     * @return array An array of eligible visitor data rows.
     */
    public function get_eligible_crm_visitors( $account_id = null ) {
        $account_id = $this->normalize_account_id( $account_id ); // Normalize input
        $table_name = $this->wpdb->prefix . 'cpd_visitors';
        
        $sql_select_order = "SELECT id, first_name, last_name, `job_title` as title, `email` as work_email, `estimated_revenue`, company_name, linkedin_url, city, state, zipcode, last_seen_at, recent_page_count, account_id FROM %i WHERE is_crm_added = %d AND crm_sent IS NULL";
        $prepare_args = [$table_name, 1]; // is_crm_added = 1

        if ( $account_id !== null ) { // Check for normalized null
            $sql_select_order .= " AND account_id = %s";
            $prepare_args[] = $account_id;
        }
        
        $sql_select_order .= " ORDER BY last_seen_at DESC";

        // Prepare the query
        $sql_query = $this->wpdb->prepare( $sql_select_order, ...$prepare_args );

        // error_log('CPD_Data_Provider::get_eligible_crm_visitors - Account ID Passed: ' . ($account_id ?? 'NULL (All Clients)'));
        // error_log('CPD_Data_Provider::get_eligible_crm_visitors - Final SQL Query: ' . $sql_query); // Log the actual prepared query

        $results = $this->wpdb->get_results( $sql_query );

        if ( $this->wpdb->last_error ) {
            error_log('CPD_Data_Provider: Database error for eligible CRM visitors: ' . $this->wpdb->last_error);
        }

        return $results;
    }

    /**
     * Retrieves the earliest and latest campaign dates for a given account ID.
     * If account_id is null, it fetches for all accounts.
     *
     * @param string|null $account_id The account ID or null for all accounts.
     * @return object|null An object with 'min_date' and 'max_date' properties, or null if no data.
     */
    public function get_campaign_date_range( $account_id = null ) {
        $account_id = $this->normalize_account_id( $account_id );
        $table_name = $this->wpdb->prefix . 'cpd_campaign_data'; // Your campaign data table

        $sql = "SELECT MIN(campaign_start_date) AS min_date, MAX(campaign_end_date) AS max_date FROM %i";
        $prepare_args = [$table_name];

        if ( $account_id !== null ) {
            $sql .= " WHERE account_id = %s";
            $prepare_args[] = $account_id;
        }

        $query = $this->wpdb->prepare( $sql, ...$prepare_args );
        error_log('CPD_Data_Provider::get_campaign_date_range - Account ID: ' . ($account_id ?? 'NULL') . ' | Query: ' . $query);

        $result = $this->wpdb->get_row( $query );

        if ( $this->wpdb->last_error ) {
            error_log('CPD_Data_Provider: Database error for campaign date range: ' . $this->wpdb->last_error);
        }

        return $result;
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
        $account_id = $this->normalize_account_id( $account_id ); // Normalize input
        $campaign_table = $this->wpdb->prefix . 'cpd_campaign_data';
        $visitor_table = $this->wpdb->prefix . 'cpd_visitors';

        // Campaign metrics query construction
        $campaign_metrics_sql = "SELECT SUM(impressions) as total_impressions, SUM(daily_reach) as total_reach, SUM(clicks) as total_clicks FROM %i WHERE date BETWEEN %s AND %s";
        $campaign_metrics_prepare_args = [$campaign_table, $start_date, $end_date];
        if ( $account_id !== null ) { // Check for normalized null
            $campaign_metrics_sql .= " AND account_id = %s";
            $campaign_metrics_prepare_args[] = $account_id;
        }
        $metrics = $this->wpdb->get_row( $this->wpdb->prepare( $campaign_metrics_sql, ...$campaign_metrics_prepare_args ) );
        
        // error_log('CPD_Data_Provider::get_summary_metrics - Account ID: ' . ($account_id ?? 'NULL'));
        // error_log('SQL Query for summary campaign metrics: ' . $this->wpdb->prepare( $campaign_metrics_sql, ...$campaign_metrics_prepare_args ) );

        // Visitor metrics query construction - separate queries for different date requirements
        // Query for new_contacts (with date range)
        $new_contacts_sql = "SELECT SUM(new_profile) as new_contacts FROM %i WHERE last_seen_at BETWEEN %s AND %s";
        $new_contacts_prepare_args = [$visitor_table, $start_date, $end_date];
        if ( $account_id !== null ) { // Check for normalized null
            $new_contacts_sql .= " AND account_id = %s";
            $new_contacts_prepare_args[] = $account_id;
        }
        
        // Query for crm_additions (without date range - all dates)
        $crm_additions_sql = "SELECT SUM(CASE WHEN is_crm_added = 1 THEN 1 ELSE 0 END) as crm_additions_count FROM %i WHERE 1=1";
        $crm_additions_prepare_args = [$visitor_table];
        if ( $account_id !== null ) { // Check for normalized null
            $crm_additions_sql .= " AND account_id = %s";
            $crm_additions_prepare_args[] = $account_id;
        }
        
        // Execute both queries
        $new_contacts_result = $this->wpdb->get_row( $this->wpdb->prepare( $new_contacts_sql, ...$new_contacts_prepare_args ) );
        $crm_additions_result = $this->wpdb->get_row( $this->wpdb->prepare( $crm_additions_sql, ...$crm_additions_prepare_args ) );
        
        // error_log('SQL Query for new contacts: ' . $this->wpdb->prepare( $new_contacts_sql, ...$new_contacts_prepare_args ) );
        // error_log('SQL Query for crm additions: ' . $this->wpdb->prepare( $crm_additions_sql, ...$crm_additions_prepare_args ) );
        
        $total_clicks = isset($metrics->total_clicks) ? $metrics->total_clicks : 0;
        $total_impressions = isset($metrics->total_impressions) ? $metrics->total_impressions : 0;
        $total_reach = isset($metrics->total_reach) ? $metrics->total_reach : 0;
        $new_contacts = isset($new_contacts_result->new_contacts) ? $new_contacts_result->new_contacts : 0;
        $crm_additions = isset($crm_additions_result->crm_additions_count) ? $crm_additions_result->crm_additions_count : 0;

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