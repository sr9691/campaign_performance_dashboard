<?php
/**
 * Handles all database operations for the Campaign Performance Dashboard plugin.
 *
 * @package CPD_Dashboard
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CPD_Database {

    private $wpdb;
    private $charset_collate;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->charset_collate = $this->wpdb->get_charset_collate();
    }

    /**
     * Create custom database tables on plugin activation.
     */
    public function create_tables() {
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        // Table for Client information.
        $table_name_clients = $this->wpdb->prefix . 'cpd_clients';
        $sql_clients = "CREATE TABLE $table_name_clients (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            account_id varchar(255) NOT NULL,
            client_name varchar(255) NOT NULL,
            logo_url varchar(255) DEFAULT '' NOT NULL,
            webpage_url varchar(255) DEFAULT '' NOT NULL,
            crm_feed_email text NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY account_id (account_id)
        ) $this->charset_collate;";
        dbDelta( $sql_clients );

        // Table for linking WordPress Users to Clients.
        $table_name_users = $this->wpdb->prefix . 'cpd_client_users';
        $sql_users = "CREATE TABLE $table_name_users (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            client_id mediumint(9) NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY user_client (user_id, client_id)
        ) $this->charset_collate;";
        dbDelta( $sql_users );

        // Table for Campaign Performance Data (GroundTruth).
        // NOTE: This table has been renamed to 'dashdev_cpd_campaign_data' as per user's request.
        // It's assumed 'dashdev_cpd_' is the full prefix for this specific table, not using $wpdb->prefix here.
        $table_name_campaign_data = 'dashdev_cpd_campaign_data';
        $sql_campaign_data = "CREATE TABLE $table_name_campaign_data (
            id BIGINT(20) NOT NULL AUTO_INCREMENT,
            account_id VARCHAR(255) NOT NULL,
            date DATE DEFAULT NULL,
            organization_name VARCHAR(255) DEFAULT NULL,
            account_name VARCHAR(255) DEFAULT NULL,
            campaign_id VARCHAR(255) DEFAULT NULL,
            campaign_name VARCHAR(255) DEFAULT NULL,
            campaign_start_date DATE DEFAULT NULL,
            campaign_end_date DATE DEFAULT NULL,
            campaign_budget DECIMAL(10,2) DEFAULT NULL,
            ad_group_id VARCHAR(255) DEFAULT NULL,
            ad_group_name VARCHAR(255) DEFAULT NULL,
            creative_id VARCHAR(255) DEFAULT NULL,
            creative_name VARCHAR(255) DEFAULT NULL,
            creative_size VARCHAR(50) DEFAULT NULL,
            creative_url VARCHAR(2048) DEFAULT NULL,
            advertiser_bid_type VARCHAR(50) DEFAULT NULL,
            budget_type VARCHAR(50) DEFAULT NULL,
            cpm DECIMAL(10,2) DEFAULT NULL,
            cpv DECIMAL(10,2) DEFAULT NULL,
            market VARCHAR(50) DEFAULT NULL,
            contact_number VARCHAR(50) DEFAULT NULL,
            external_ad_group_id VARCHAR(255) DEFAULT NULL,
            total_impressions_contracted INT(11) DEFAULT NULL,
            impressions INT(11) DEFAULT NULL,
            clicks INT(11) DEFAULT NULL,
            ctr DECIMAL(5,2) DEFAULT NULL,
            visits INT(11) DEFAULT NULL,
            total_spent DECIMAL(10,2) DEFAULT NULL,
            secondary_actions INT(11) DEFAULT NULL,
            secondary_action_rate DECIMAL(5,2) DEFAULT NULL,
            website VARCHAR(255) DEFAULT NULL,
            direction VARCHAR(255) DEFAULT NULL,
            click_to_call INT(11) DEFAULT NULL,
            cta_more_info INT(11) DEFAULT NULL,
            coupon INT(11) DEFAULT NULL,
            daily_reach INT(11) DEFAULT NULL,
            video_start INT(11) DEFAULT NULL,
            first_quartile INT(11) DEFAULT NULL,
            midpoint INT(11) DEFAULT NULL,
            third_quartile INT(11) DEFAULT NULL,
            video_complete INT(11) DEFAULT NULL,
            PRIMARY KEY (id)
        ) $this->charset_collate;";
        dbDelta( $sql_campaign_data );

        // Table for Visitor Data (RB2B).
        // CRITICAL CHANGE: UNIQUE KEY is now `unique_linkedin_account` on `linkedin_url` + `account_id`.
        // `visitor_id` is a VARCHAR for external ID, and is nullable as per previous steps.
        $table_name_visitors = $this->wpdb->prefix . 'cpd_visitors';
        $sql_visitors = "CREATE TABLE $table_name_visitors (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            visitor_id varchar(255) NULL DEFAULT NULL, -- Changed to NULLABLE to match current DB and API handling
            account_id varchar(255) NOT NULL,
            linkedin_url varchar(255) DEFAULT '' NOT NULL, -- Confirmed NOT NULL as per your latest change
            company_name varchar(255) DEFAULT '' NOT NULL,
            all_time_page_views int(11) DEFAULT 0 NOT NULL,
            first_name varchar(255) DEFAULT '' NOT NULL,
            last_name varchar(255) DEFAULT '' NOT NULL,
            job_title varchar(255) DEFAULT '' NOT NULL,
            most_recent_referrer text NOT NULL,
            recent_page_count int(11) DEFAULT 0 NOT NULL,
            recent_page_urls varchar(10000) DEFAULT '' NOT NULL,
            tags text NOT NULL,
            estimated_employee_count varchar(50) DEFAULT '' NOT NULL,
            estimated_revenue varchar(100) DEFAULT '' NOT NULL,
            city varchar(50) DEFAULT '' NOT NULL,
            zipcode varchar(20) DEFAULT '' NOT NULL,
            last_seen_at datetime NOT NULL,
            first_seen_at datetime NOT NULL,
            new_profile tinyint(1) DEFAULT 0 NOT NULL,
            email varchar(255) DEFAULT '' NOT NULL,
            website varchar(255) DEFAULT '' NOT NULL,
            industry varchar(100) DEFAULT '' NOT NULL,
            state varchar(100) DEFAULT '' NOT NULL,
            filter_matches text NOT NULL,
            profile_type varchar(10) DEFAULT '' NOT NULL,
            status varchar(10) DEFAULT 'active' NOT NULL,
            is_crm_added tinyint(1) DEFAULT 0 NOT NULL,
            crm_sent datetime DEFAULT NULL,
            is_archived tinyint(1) DEFAULT 0 NOT NULL,
            PRIMARY KEY (id),
            -- Corrected UNIQUE KEY to match the live DB and API logic: (linkedin_url, account_id)
            UNIQUE KEY unique_linkedin_account (linkedin_url, account_id)
        ) $this->charset_collate;";
        dbDelta( $sql_visitors );
        
        // Table for Logging actions.
        $table_name_logs = $this->wpdb->prefix . 'cpd_action_logs';
        $sql_logs = "CREATE TABLE $table_name_logs (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            action_type varchar(50) NOT NULL,
            description text NOT NULL,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY (id)
        ) $this->charset_collate;";
        dbDelta( $sql_logs );
    }
}