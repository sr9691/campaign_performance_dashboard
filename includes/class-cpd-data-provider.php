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
    public function get_visitor_data( $account_id, $hot_list_only = false ) {
        $account_id = $this->normalize_account_id( $account_id ); // Normalize input
        $visitor_table = $this->wpdb->prefix . 'cpd_visitors';
        $intelligence_table = $this->wpdb->prefix . 'cpd_visitor_intelligence';
        $clients_table = $this->wpdb->prefix . 'cpd_clients';
        
        // Get visitors with intelligence status
        $sql_select_order = "
            SELECT v.*, 
                   COALESCE(i.status, 'none') as intelligence_status,
                   COALESCE(c.ai_intelligence_enabled, 0) as ai_intelligence_enabled,
                   c.client_name
            FROM %i AS v
            LEFT JOIN %i AS c ON v.account_id = c.account_id
            LEFT JOIN (
                SELECT visitor_id, status
                FROM %i 
                WHERE id IN (
                    SELECT MAX(id) 
                    FROM %i 
                    GROUP BY visitor_id
                )
            ) AS i ON v.id = i.visitor_id
            WHERE v.is_archived = %d AND v.is_crm_added = %d";
        
        $prepare_args = [
            $visitor_table, 
            $clients_table, 
            $intelligence_table, 
            $intelligence_table, 
            0, // not archived
            0  // not CRM-added
        ];

        // Only add account_id filter if it's explicitly provided (not null)
        if ( $account_id !== null ) { // Check for normalized null
            $sql_select_order .= " AND v.account_id = %s";
            $prepare_args[] = $account_id;
        }
        
        $sql_select_order .= " ORDER BY v.last_seen_at DESC";

        // Prepare the query
        $sql_query = $this->wpdb->prepare( $sql_select_order, ...$prepare_args );
        
        $results = $this->wpdb->get_results( $sql_query );

        if ( empty( $results ) ) {
            error_log('CPD_Data_Provider: get_visitor_data returned no results for account_id: ' . ($account_id ?? 'ALL'));
        }
        if ( $this->wpdb->last_error ) {
            error_log('CPD_Data_Provider: Database error for visitor data: ' . $this->wpdb->last_error);
        }

        // Ensure CPD_Referrer_Logo class is loaded
        if ( ! class_exists( 'CPD_Referrer_Logo' ) ) {
            require_once CPD_DASHBOARD_PLUGIN_DIR . 'includes/class-cpd-referrer-logo.php';
        }

        // Iterate through each visitor and add the referrer_logo_url and alt_text
        foreach ( $results as $visitor ) {
            $visitor->referrer_logo_url = CPD_Referrer_Logo::get_logo_for_visitor( $visitor );
            $visitor->referrer_alt_text = CPD_Referrer_Logo::get_alt_text_for_visitor( $visitor );
            $visitor->referrer_tooltip = CPD_Referrer_Logo::get_referrer_url_for_visitor( $visitor );
            
            $visitor->ai_intelligence_enabled = (bool) $visitor->ai_intelligence_enabled;
            
            if ( !$visitor->ai_intelligence_enabled ) {
                // If AI is not enabled for this client, set status to 'disabled'
                $visitor->intelligence_status = 'disabled';
            } else {
                // If AI is enabled, use the status from the query (defaults to 'none' if no intelligence record exists)
                if ( empty($visitor->intelligence_status) || $visitor->intelligence_status === null ) {
                    $visitor->intelligence_status = 'none';
                }
                // Otherwise, keep the existing status (pending, completed, failed)
            }
            // error_log("Visitor {$visitor->id}: AI enabled = " . ($visitor->ai_intelligence_enabled ? 'true' : 'false') . ", Status = {$visitor->intelligence_status}");
        }

        if ( $hot_list_only && $account_id !== null ) {
            $results = $this->filter_hot_list_visitors( $results, $account_id );
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
        // error_log('CPD_Data_Provider::get_campaign_date_range - Account ID: ' . ($account_id ?? 'NULL') . ' | Query: ' . $query);

        $result = $this->wpdb->get_row( $query );

        if ( $this->wpdb->last_error ) {
            error_log('CPD_Data_Provider: Database error for campaign date range: ' . $this->wpdb->last_error);
        }

        // Set default dates if the result is empty or dates are null/blank
        if ( !$result || empty($result->min_date) || empty($result->max_date) ) {
            // Create a new result object if none exists
            if ( !$result ) {
                $result = new stdClass();
            }
            
            // Set default start date to start of current month if min_date is empty/null
            if ( empty($result->min_date) ) {
                $result->min_date = date('Y-m-01'); // First day of current month
            }
            
            // Set default end date to 3 years from start of current month if max_date is empty/null
            if ( empty($result->max_date) ) {
                $start_of_month = date('Y-m-01'); // First day of current month
                $result->max_date = date('Y-m-01', strtotime($start_of_month . ' +1 month')); // 1 month from start of month
            }
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
    
    
    
    /**
     * Check if a client has AI intelligence enabled
     *
     * @param string $account_id The account ID.
     * @return bool True if AI intelligence is enabled, false otherwise.
     */
    public function is_client_ai_enabled( $account_id ) {
        $clients_table = $this->wpdb->prefix . 'cpd_clients';
        
        $result = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT ai_intelligence_enabled FROM $clients_table WHERE account_id = %s",
                $account_id
            )
        );

        return $result == 1;
    }

    /**
     * Get client context information for AI intelligence
     *
     * @param string $account_id The account ID.
     * @return string The client context information.
     */
    public function get_client_context( $account_id ) {
        $clients_table = $this->wpdb->prefix . 'cpd_clients';
        
        $result = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT client_context_info FROM $clients_table WHERE account_id = %s",
                $account_id
            )
        );

        return $result ? $result : '';
    }

    /**
     * Get visitor data with intelligence information for a specific visitor
     *
     * @param int $visitor_id The visitor ID.
     * @return object|null The visitor object with intelligence data or null if not found.
     */
    public function get_visitor_with_intelligence( $visitor_id ) {
        $visitor_table = $this->wpdb->prefix . 'cpd_visitors';
        $intelligence_table = $this->wpdb->prefix . 'cpd_visitor_intelligence';
        $clients_table = $this->wpdb->prefix . 'cpd_clients';
        
        $sql = "
            SELECT v.*, 
                   c.ai_intelligence_enabled,
                   c.client_context_info,
                   i.status as intelligence_status,
                   i.response_data as intelligence_data,
                   i.created_at as intelligence_created_at,
                   i.updated_at as intelligence_updated_at
            FROM %i AS v
            LEFT JOIN %i AS c ON v.account_id = c.account_id
            LEFT JOIN (
                SELECT visitor_id, status, response_data, created_at, updated_at
                FROM %i 
                WHERE id IN (
                    SELECT MAX(id) 
                    FROM %i 
                    WHERE visitor_id = %d
                    GROUP BY visitor_id
                )
            ) AS i ON v.id = i.visitor_id
            WHERE v.id = %d";
        
        $query = $this->wpdb->prepare( 
            $sql, 
            $visitor_table, 
            $clients_table, 
            $intelligence_table, 
            $intelligence_table, 
            $visitor_id,
            $visitor_id 
        );
        
        $result = $this->wpdb->get_row( $query );

        if ( $this->wpdb->last_error ) {
            error_log('CPD_Data_Provider: Database error for visitor with intelligence: ' . $this->wpdb->last_error);
        }

        // Decode intelligence data if present
        if ( $result && $result->intelligence_data ) {
            $result->intelligence_data = json_decode( $result->intelligence_data, true );
        }

        return $result;
    }

    /**
     * Get intelligence statistics for admin dashboard
     *
     * @return array Statistics about intelligence usage.
     */
    public function get_intelligence_statistics() {
        $intelligence_table = $this->wpdb->prefix . 'cpd_visitor_intelligence';
        $clients_table = $this->wpdb->prefix . 'cpd_clients';
        
        $stats = array();
        
        // Total intelligence requests
        $stats['total_requests'] = $this->wpdb->get_var(
            "SELECT COUNT(*) FROM $intelligence_table"
        );
        
        // Requests by status
        $status_counts = $this->wpdb->get_results(
            "SELECT status, COUNT(*) as count FROM $intelligence_table GROUP BY status"
        );
        
        $stats['by_status'] = array();
        foreach ( $status_counts as $status ) {
            $stats['by_status'][$status->status] = $status->count;
        }
        
        // Today's requests
        $today = date( 'Y-m-d' );
        $stats['today_requests'] = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM $intelligence_table WHERE DATE(created_at) = %s",
                $today
            )
        );
        
        // AI-enabled clients count
        $stats['ai_enabled_clients'] = $this->wpdb->get_var(
            "SELECT COUNT(*) FROM $clients_table WHERE ai_intelligence_enabled = 1"
        );
        
        // Success rate (completed vs total)
        $completed = isset( $stats['by_status']['completed'] ) ? $stats['by_status']['completed'] : 0;
        $stats['success_rate'] = $stats['total_requests'] > 0 ? 
            round( ( $completed / $stats['total_requests'] ) * 100, 2 ) : 0;

        return $stats;
    }

    /**
     * NEW: Filter visitors based on hot list criteria
     *
     * @param array $visitors Array of visitor objects.
     * @param string $account_id The account ID.
     * @return array Filtered array of hot visitors.
     */
    private function filter_hot_list_visitors( $visitors, $account_id ) {
        // Load hot list database class
        if (!class_exists('CPD_Hot_List_Database')) {
            require_once CPD_DASHBOARD_PLUGIN_DIR . 'includes/class-cpd-hot-list-database.php';
        }
        
        $hot_list_db = new CPD_Hot_List_Database();
        $settings = $hot_list_db->get_settings( $account_id );
        
        if ( !$settings ) {
            return array(); // No settings = no hot visitors
        }
        
        $hot_visitors = array();
        
        foreach ( $visitors as $visitor ) {
            if ( $this->visitor_matches_hot_criteria( $visitor, $settings ) ) {
                $visitor->is_hot_lead = true;
                $hot_visitors[] = $visitor;
            }
        }
        
        return $hot_visitors;
    }

    /**
     * NEW: Check if a visitor matches hot list criteria
     *
     * @param object $visitor The visitor object.
     * @param object $settings The hot list settings.
     * @return bool True if visitor matches criteria.
     */
    private function visitor_matches_hot_criteria( $visitor, $settings ) {
        $matches = 0;
        $active_filters = 0;
        
        // Check revenue filter
        if ( !empty($settings->revenue_filters) && !in_array('any', $settings->revenue_filters) ) {
            $active_filters++;
            if ( $this->visitor_matches_revenue( $visitor, $settings->revenue_filters ) ) {
                $matches++;
            }
        }
        
        // Check company size filter
        if ( !empty($settings->company_size_filters) && !in_array('any', $settings->company_size_filters) ) {
            $active_filters++;
            if ( $this->visitor_matches_company_size( $visitor, $settings->company_size_filters ) ) {
                $matches++;
            }
        }
        
        // Check industry filter
        if ( !empty($settings->industry_filters) && !in_array('any', $settings->industry_filters) ) {
            $active_filters++;
            if ( in_array( $visitor->industry, $settings->industry_filters ) ) {
                $matches++;
            }
        }
        
        // Check state filter
        if ( !empty($settings->state_filters) && !in_array('any', $settings->state_filters) ) {
            $active_filters++;
            if ( in_array( $visitor->state, $settings->state_filters ) ) {
                $matches++;
            }
        }
        
        // Apply required matches logic
        $required_matches = max( 1, min( $settings->required_matches, $active_filters ) );
        
        return $matches >= $required_matches;
    }

    /**
     * NEW: Check if visitor matches revenue criteria
     */
    private function visitor_matches_revenue( $visitor, $revenue_filters ) {
        // Parse revenue from string like "$1,000,000" to numeric value
        $revenue_str = str_replace( array('$', ','), '', $visitor->estimated_revenue );
        $revenue = is_numeric($revenue_str) ? intval($revenue_str) : 0;
        
        foreach ( $revenue_filters as $range ) {
            switch ( $range ) {
                case 'below-500k':
                    if ( $revenue < 500000 ) return true;
                    break;
                case '500k-1m':
                    if ( $revenue >= 500000 && $revenue < 1000000 ) return true;
                    break;
                case '1m-5m':
                    if ( $revenue >= 1000000 && $revenue < 5000000 ) return true;
                    break;
                case '5m-10m':
                    if ( $revenue >= 5000000 && $revenue < 10000000 ) return true;
                    break;
                case '10m-20m':
                    if ( $revenue >= 10000000 && $revenue < 20000000 ) return true;
                    break;
                case '20m-50m':
                    if ( $revenue >= 20000000 && $revenue < 50000000 ) return true;
                    break;
                case 'above-50m':
                    if ( $revenue >= 50000000 ) return true;
                    break;
            }
        }
        
        return false;
    }

    /**
     * NEW: Check if visitor matches company size criteria
     */
    private function visitor_matches_company_size( $visitor, $size_filters ) {
        // Parse company size from string to numeric value
        $size_str = str_replace( array(',', '+'), '', $visitor->estimated_employee_count );
        $size = is_numeric($size_str) ? intval($size_str) : 0;
        
        foreach ( $size_filters as $range ) {
            switch ( $range ) {
                case '1-10':
                    if ( $size >= 1 && $size <= 10 ) return true;
                    break;
                case '11-20':
                    if ( $size >= 11 && $size <= 20 ) return true;
                    break;
                case '21-50':
                    if ( $size >= 21 && $size <= 50 ) return true;
                    break;
                case '51-200':
                    if ( $size >= 51 && $size <= 200 ) return true;
                    break;
                case '200-500':
                    if ( $size >= 200 && $size <= 500 ) return true;
                    break;
                case '500-1000':
                    if ( $size >= 500 && $size <= 1000 ) return true;
                    break;
                case '1000-5000':
                    if ( $size >= 1000 && $size <= 5000 ) return true;
                    break;
                case 'above-5000':
                    if ( $size > 5000 ) return true;
                    break;
            }
        }
        
        return false;
    }

    /**
     * NEW: Simple wrapper methods for convenience
     */
    public function get_hot_visitor_data( $account_id ) {
        return $this->get_visitor_data( $account_id, true );
    }

    public function get_hot_visitor_count( $account_id, $start_date = null, $end_date = null ) {
        $hot_visitors = $this->get_hot_visitor_data( $account_id );
        
        // Apply date filtering if provided
        if ( $start_date && $end_date ) {
            $hot_visitors = array_filter( $hot_visitors, function( $visitor ) use ( $start_date, $end_date ) {
                return $visitor->last_seen_at >= $start_date && $visitor->last_seen_at <= $end_date;
            });
        }
        
        return count( $hot_visitors );
    }

    public function has_hot_list_configured( $account_id ) {
        if (!class_exists('CPD_Hot_List_Database')) {
            require_once CPD_DASHBOARD_PLUGIN_DIR . 'includes/class-cpd-hot-list-database.php';
        }
        
        $hot_list_db = new CPD_Hot_List_Database();
        $settings = $hot_list_db->get_settings( $account_id );
        
        return !empty( $settings );
    }

    public function get_hot_list_criteria_summary( $account_id ) {
        if (!class_exists('CPD_Hot_List_Database')) {
            require_once CPD_DASHBOARD_PLUGIN_DIR . 'includes/class-cpd-hot-list-database.php';
        }
        
        $hot_list_db = new CPD_Hot_List_Database();
        $settings = $hot_list_db->get_settings( $account_id );
        
        if ( !$settings ) {
            return null;
        }
        
        $summary = array();
        
        if ( !empty($settings->revenue_filters) && !in_array('any', $settings->revenue_filters) ) {
            $summary[] = 'Revenue: ' . count($settings->revenue_filters) . ' selected';
        }
        
        if ( !empty($settings->company_size_filters) && !in_array('any', $settings->company_size_filters) ) {
            $summary[] = 'Size: ' . count($settings->company_size_filters) . ' selected';
        }
        
        if ( !empty($settings->industry_filters) && !in_array('any', $settings->industry_filters) ) {
            $summary[] = 'Industry: ' . count($settings->industry_filters) . ' selected';
        }
        
        if ( !empty($settings->state_filters) && !in_array('any', $settings->state_filters) ) {
            $summary[] = 'States: ' . count($settings->state_filters) . ' selected';
        }
        
        return array(
            'criteria' => $summary,
            'required_matches' => $settings->required_matches,
            'active_filters' => count( $summary )
        );
    }

}    
