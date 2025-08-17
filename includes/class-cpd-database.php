<?php
/**
 * Enhanced CPD_Database class with AI Intelligence support
 * Phase 1: Database & Settings Implementation
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

        // Table for Client information (Enhanced with AI Intelligence fields).
        $table_name_clients = $this->wpdb->prefix . 'cpd_clients';
        $sql_clients = "CREATE TABLE $table_name_clients (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            account_id varchar(255) NOT NULL,
            client_name varchar(255) NOT NULL,
            logo_url varchar(255) DEFAULT '' NOT NULL,
            webpage_url varchar(255) DEFAULT '' NOT NULL,
            crm_feed_email text NOT NULL,
            ai_intelligence_enabled tinyint(1) DEFAULT 0 NOT NULL,
            client_context_info text NULL,
            ai_settings_updated_at timestamp NULL,
            ai_settings_updated_by bigint(20) NULL,
            PRIMARY KEY (id),
            UNIQUE KEY account_id (account_id),
            INDEX idx_ai_enabled (ai_intelligence_enabled),
            INDEX idx_ai_settings_updated (ai_settings_updated_at)
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
        $table_name_visitors = $this->wpdb->prefix . 'cpd_visitors';
        $sql_visitors = "CREATE TABLE $table_name_visitors (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            visitor_id varchar(255) NULL DEFAULT NULL,
            account_id varchar(255) NOT NULL,
            linkedin_url varchar(255) DEFAULT '' NOT NULL,
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
            UNIQUE KEY unique_linkedin_account (linkedin_url, account_id)
        ) $this->charset_collate;";
        dbDelta( $sql_visitors );

        // Table for Visitor Intelligence Data
        $table_name_intelligence = $this->wpdb->prefix . 'cpd_visitor_intelligence';
        $sql_intelligence = "CREATE TABLE $table_name_intelligence (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            visitor_id mediumint(9) NOT NULL,
            client_id mediumint(9) NOT NULL,
            user_id bigint(20) NOT NULL,
            request_data longtext NOT NULL,
            response_data longtext NULL,
            client_context text NULL,
            status enum('pending', 'processing', 'completed', 'failed') DEFAULT 'pending' NOT NULL,
            api_request_id varchar(255) NULL,
            error_message text NULL,
            processing_time int(11) NULL,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP NOT NULL,
            updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY (id),
            INDEX idx_visitor_client (visitor_id, client_id),
            INDEX idx_status (status),
            INDEX idx_created_at (created_at),
            INDEX idx_api_request (api_request_id)
        ) $this->charset_collate;";
        dbDelta( $sql_intelligence );
        
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

        // NEW: Create Hot List Settings table
        if (!class_exists('CPD_Hot_List_Database')) {
            require_once CPD_DASHBOARD_PLUGIN_DIR . 'includes/class-cpd-hot-list-database.php';
        }
        
        $hot_list_db = new CPD_Hot_List_Database();
        $hot_list_db->create_table();

        // Set plugin version for migration tracking
        update_option( 'cpd_database_version', '1.2.0' ); // Update version for Hot List
        
        // Set plugin version for migration tracking
        update_option( 'cpd_database_version', '1.1.0' );
        
        error_log( 'CPD Database: Tables created successfully with AI Intelligence support' );
    }

    /**
     * Handle database migrations for existing installations
     */
    public function migrate_database() {
        $current_version = get_option( 'cpd_database_version', '1.0.0' );
        
        // Migration for AI Intelligence features (1.0.0 -> 1.1.0)
        if ( version_compare( $current_version, '1.1.0', '<' ) ) {
            $this->migrate_to_1_1_0();
            update_option( 'cpd_database_version', '1.1.0' );
        }

        // Migration for Hot List features (1.1.0 -> 1.2.0)
        if ( version_compare( $current_version, '1.2.0', '<' ) ) {
            $this->migrate_to_1_2_0();
            update_option( 'cpd_database_version', '1.2.0' );
        }
    }
    
    /**
     * Migration to add AI Intelligence features to existing installations
     */
    private function migrate_to_1_1_0() {
        $table_name_clients = $this->wpdb->prefix . 'cpd_clients';
        
        // Check if AI intelligence columns already exist
        $ai_enabled_column_exists = $this->column_exists( $table_name_clients, 'ai_intelligence_enabled' );
        
        if ( ! $ai_enabled_column_exists ) {
            // Add AI Intelligence columns to clients table
            $sql_add_columns = "
                ALTER TABLE $table_name_clients 
                ADD COLUMN ai_intelligence_enabled tinyint(1) DEFAULT 0 NOT NULL,
                ADD COLUMN client_context_info text NULL,
                ADD COLUMN ai_settings_updated_at timestamp NULL,
                ADD COLUMN ai_settings_updated_by bigint(20) NULL,
                ADD INDEX idx_ai_enabled (ai_intelligence_enabled),
                ADD INDEX idx_ai_settings_updated (ai_settings_updated_at)
            ";
            
            $this->wpdb->query( $sql_add_columns );
            
            if ( $this->wpdb->last_error ) {
                error_log( 'CPD Database Migration Error: ' . $this->wpdb->last_error );
            } else {
                error_log( 'CPD Database: Added AI intelligence columns to clients table' );
            }
        }

        // Create the visitor intelligence table if it doesn't exist
        $table_name_intelligence = $this->wpdb->prefix . 'cpd_visitor_intelligence';
        $table_name_visitors = $this->wpdb->prefix . 'cpd_visitors';
        $table_name_clients = $this->wpdb->prefix . 'cpd_clients';
        
        if ( $this->wpdb->get_var( "SHOW TABLES LIKE '$table_name_intelligence'" ) != $table_name_intelligence ) {
            $sql_intelligence = "CREATE TABLE $table_name_intelligence (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                visitor_id mediumint(9) NOT NULL,
                client_id mediumint(9) NOT NULL,
                user_id bigint(20) NOT NULL,
                request_data longtext NOT NULL,
                response_data longtext NULL,
                client_context text NULL,
                status enum('pending', 'processing', 'completed', 'failed') DEFAULT 'pending' NOT NULL,
                api_request_id varchar(255) NULL,
                error_message text NULL,
                processing_time int(11) NULL,
                created_at timestamp DEFAULT CURRENT_TIMESTAMP NOT NULL,
                updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
                PRIMARY KEY (id),
                INDEX idx_visitor_client (visitor_id, client_id),
                INDEX idx_status (status),
                INDEX idx_created_at (created_at),
                INDEX idx_api_request (api_request_id)
            ) $this->charset_collate;";
            
            require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
            dbDelta( $sql_intelligence );
            
            if ( $this->wpdb->last_error ) {
                error_log( 'CPD Database Migration Error creating intelligence table: ' . $this->wpdb->last_error );
            } else {
                error_log( 'CPD Database: Created visitor intelligence table' );
            }
        }
    }

    /**
     * Migration to add Hot List features
     */
    private function migrate_to_1_2_0() {
        if (!class_exists('CPD_Hot_List_Database')) {
            require_once CPD_DASHBOARD_PLUGIN_DIR . 'includes/class-cpd-hot-list-database.php';
        }
        
        $hot_list_db = new CPD_Hot_List_Database();
        
        if (!$hot_list_db->table_exists()) {
            $hot_list_db->create_table();
            // error_log( 'CPD Database: Created Hot List Settings table during migration' );
        }
    }


    /**
     * Check if a column exists in a table
     */
    private function column_exists( $table_name, $column_name ) {
        $column = $this->wpdb->get_results( 
            $this->wpdb->prepare(
                "SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
                DB_NAME, $table_name, $column_name
            )
        );
        
        return ! empty( $column );
    }

    /**
     * Get AI-enabled clients
     */
    public function get_ai_enabled_clients() {
        $table_name_clients = $this->wpdb->prefix . 'cpd_clients';
        
        $results = $this->wpdb->get_results(
            "SELECT * FROM $table_name_clients WHERE ai_intelligence_enabled = 1 ORDER BY client_name ASC"
        );

        return $results ? $results : array();
    }

    /**
     * Check if a client has AI intelligence enabled
     */
    public function is_client_ai_enabled( $account_id ) {
        $table_name_clients = $this->wpdb->prefix . 'cpd_clients';
        
        $result = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT ai_intelligence_enabled FROM $table_name_clients WHERE account_id = %s",
                $account_id
            )
        );

        return $result == 1;
    }

    /**
     * Get client context information
     */
    public function get_client_context( $account_id ) {
        $table_name_clients = $this->wpdb->prefix . 'cpd_clients';
        
        $result = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT client_context_info FROM $table_name_clients WHERE account_id = %s",
                $account_id
            )
        );

        return $result ? $result : '';
    }

    /**
     * Update client AI settings
     */
    public function update_client_ai_settings( $client_id, $ai_enabled, $context_info, $user_id ) {
        $table_name_clients = $this->wpdb->prefix . 'cpd_clients';
        
        $result = $this->wpdb->update(
            $table_name_clients,
            array(
                'ai_intelligence_enabled' => $ai_enabled ? 1 : 0,
                'client_context_info' => $context_info,
                'ai_settings_updated_at' => current_time( 'mysql' ),
                'ai_settings_updated_by' => $user_id,
            ),
            array( 'id' => $client_id ),
            array( '%d', '%s', '%s', '%d' ),
            array( '%d' )
        );

        if ( $this->wpdb->last_error ) {
            error_log( 'CPD Database Error updating AI settings: ' . $this->wpdb->last_error );
            return false;
        }

        return $result !== false;
    }
}