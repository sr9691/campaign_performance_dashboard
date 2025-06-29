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
        $table_name_campaign_data = $this->wpdb->prefix . 'cpd_campaign_data';
        $sql_campaign_data = "CREATE TABLE $table_name_campaign_data (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            account_id varchar(255) NOT NULL,
            campaign_start date NOT NULL,
            campaign_end date NOT NULL,
            ad_group_name varchar(255) NOT NULL,
            impressions int(11) DEFAULT 0 NOT NULL,
            reach int(11) DEFAULT 0 NOT NULL,
            clicks int(11) DEFAULT 0 NOT NULL,
            ctr decimal(5,2) DEFAULT 0.00 NOT NULL,
            last_updated datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY campaign_ad_group (account_id, ad_group_name)
        ) $this->charset_collate;";
        dbDelta( $sql_campaign_data );

        // Table for Visitor Data (RB2B).
        $table_name_visitors = $this->wpdb->prefix . 'cpd_visitors';
        $sql_visitors = "CREATE TABLE $table_name_visitors (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            visitor_id varchar(255) NOT NULL,
            account_id varchar(255) NOT NULL,
            name varchar(255) DEFAULT '' NOT NULL,
            job_title varchar(255) DEFAULT '' NOT NULL,
            company_name varchar(255) DEFAULT '' NOT NULL,
            location varchar(255) DEFAULT '' NOT NULL,
            email varchar(255) DEFAULT '' NOT NULL,
            referrer_url varchar(255) DEFAULT '' NOT NULL,
            is_crm_added boolean DEFAULT 0 NOT NULL,
            is_archived boolean DEFAULT 0 NOT NULL,
            visit_time datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY visitor_account (visitor_id, account_id)
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