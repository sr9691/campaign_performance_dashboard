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
        update_option( 'cpd_database_version', '1.2.0' );
        
        error_log( 'CPD Database: Tables created successfully with AI Intelligence support' );
    }

    /**
     * Handle database migrations for existing installations
     * Supports migrations from v1.0.0 through v2.0.0
     */
    public function migrate_database() {
        $current_version = get_option('cpd_database_version', '1.0.0');
        
        // Migration for AI Intelligence features (1.0.0 -> 1.1.0)
        if (version_compare($current_version, '1.1.0', '<')) {
            $this->migrate_to_1_1_0();
            update_option('cpd_database_version', '1.1.0');
            $current_version = '1.1.0';
        }

        // Migration for Hot List features (1.1.0 -> 1.2.0)
        if (version_compare($current_version, '1.2.0', '<')) {
            $this->migrate_to_1_2_0();
            update_option('cpd_database_version', '1.2.0');
            $current_version = '1.2.0';
        }
        
        // Migration to v2.0.0 (Premium features)
        if (version_compare($current_version, '2.0.0', '<')) {
            error_log('CPD: Starting migration to v2.0.0');
            
            if ($this->migrate_to_2_0_0()) {
                update_option('cpd_database_version', '2.0.0');
                error_log('CPD: Successfully migrated to v2.0.0');
            } else {
                error_log('CPD: Migration to v2.0.0 FAILED');
                return false;
            }
        }
        
        return true;
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
        }
    }

    /**
     * V2: Add premium fields to wp_cpd_clients and create all v2 tables
     */
    private function migrate_to_2_0_0() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cpd_clients';
        
        try {
            // Check if columns already exist
            $columns = $wpdb->get_results("SHOW COLUMNS FROM {$table_name}");
            $existing_columns = array_column($columns, 'Field');
            
            // Add subscription_tier column
            if (!in_array('subscription_tier', $existing_columns)) {
                $wpdb->query(
                    "ALTER TABLE {$table_name} 
                    ADD COLUMN subscription_tier ENUM('basic', 'premium') DEFAULT 'basic' 
                    AFTER ai_settings_updated_by"
                );
                error_log('CPD: Added subscription_tier column');
            }
            
            // Add rtr_enabled column
            if (!in_array('rtr_enabled', $existing_columns)) {
                $wpdb->query(
                    "ALTER TABLE {$table_name} 
                    ADD COLUMN rtr_enabled TINYINT(1) DEFAULT 0 
                    AFTER subscription_tier"
                );
                error_log('CPD: Added rtr_enabled column');
            }
            
            // Add rtr_activated_at column
            if (!in_array('rtr_activated_at', $existing_columns)) {
                $wpdb->query(
                    "ALTER TABLE {$table_name} 
                    ADD COLUMN rtr_activated_at DATETIME NULL 
                    AFTER rtr_enabled"
                );
                error_log('CPD: Added rtr_activated_at column');
            }
            
            // Add subscription_expires_at column
            if (!in_array('subscription_expires_at', $existing_columns)) {
                $wpdb->query(
                    "ALTER TABLE {$table_name} 
                    ADD COLUMN subscription_expires_at DATETIME NULL 
                    AFTER rtr_activated_at"
                );
                error_log('CPD: Added subscription_expires_at column');
            }
            
            // Add indexes
            $this->add_premium_indexes($table_name);
            
            // Create all v2 tables
            $this->create_all_v2_tables();
            
            return true;
            
        } catch (Exception $e) {
            error_log('CPD Migration Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * V2: Add indexes for premium fields
     */
    private function add_premium_indexes($table_name) {
        global $wpdb;
        
        // Check if indexes exist
        $indexes = $wpdb->get_results("SHOW INDEX FROM {$table_name}");
        $existing_indexes = array_column($indexes, 'Key_name');
        
        // Add subscription_tier index
        if (!in_array('idx_subscription_tier', $existing_indexes)) {
            $wpdb->query(
                "CREATE INDEX idx_subscription_tier 
                ON {$table_name}(subscription_tier, rtr_enabled)"
            );
            error_log('CPD: Added idx_subscription_tier index');
        }
        
        // Add subscription_expires index
        if (!in_array('idx_subscription_expires', $existing_indexes)) {
            $wpdb->query(
                "CREATE INDEX idx_subscription_expires 
                ON {$table_name}(subscription_expires_at)"
            );
            error_log('CPD: Added idx_subscription_expires index');
        }
    }
    
    /**
     * V2: Create wp_cpd_visitor_campaigns table
     */
    public function create_visitor_campaigns_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cpd_visitor_campaigns';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            visitor_id mediumint(9) NOT NULL,
            campaign_id VARCHAR(255) NOT NULL,
            account_id VARCHAR(255) NOT NULL,
            
            -- Engagement Tracking
            first_visit_at DATETIME NOT NULL,
            last_visit_at DATETIME NOT NULL,
            total_page_views INT DEFAULT 0,
            unique_pages_count INT DEFAULT 0,
            page_urls TEXT,
            entry_page VARCHAR(2048),
            most_recent_page VARCHAR(2048),
            
            -- UTM Tracking
            utm_source VARCHAR(255),
            utm_medium VARCHAR(255),
            utm_campaign VARCHAR(255),
            utm_content VARCHAR(255),
            utm_term VARCHAR(255),
            
            -- RTR Prospect Fields
            is_prospect TINYINT(1) DEFAULT 0,
            current_room ENUM('none', 'problem', 'solution', 'offer') DEFAULT 'none',
            room_entered_at DATETIME NULL,
            days_in_room INT DEFAULT 0,
            lead_score INT DEFAULT 0,
            email_sequence_position INT DEFAULT 0,
            last_email_sent DATETIME NULL,
            next_email_due DATE NULL,
            
            -- Timestamps
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            -- Constraints
            UNIQUE KEY unique_visitor_campaign (visitor_id, campaign_id),
            INDEX idx_visitor (visitor_id),
            INDEX idx_campaign (campaign_id),
            INDEX idx_account (account_id),
            INDEX idx_prospect (is_prospect, current_room),
            INDEX idx_email_due (next_email_due),
            INDEX idx_last_visit (last_visit_at),
            INDEX idx_lead_score (lead_score),
            
            FOREIGN KEY (visitor_id) 
                REFERENCES {$wpdb->prefix}cpd_visitors(id) 
                ON DELETE CASCADE
        ) {$charset_collate};";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name) {
            error_log('CPD: Successfully created wp_cpd_visitor_campaigns table');
            return true;
        } else {
            error_log('CPD: FAILED to create wp_cpd_visitor_campaigns table');
            return false;
        }
    }
    
    /**
     * V2: Create wp_rtr_email_tracking table
     */
    public function create_email_tracking_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'rtr_email_tracking';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            visitor_id mediumint(9) NOT NULL,
            campaign_id VARCHAR(255) NOT NULL,
            email_number INT NOT NULL,
            room_type ENUM('problem', 'solution', 'offer') NOT NULL,
            subject VARCHAR(500),
            sent_at DATETIME,
            status ENUM('pending', 'sent', 'noted') DEFAULT 'pending',
            notes TEXT,
            
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            INDEX idx_visitor_campaign (visitor_id, campaign_id),
            INDEX idx_status (status),
            
            FOREIGN KEY (visitor_id) 
                REFERENCES {$wpdb->prefix}cpd_visitors(id) 
                ON DELETE CASCADE
        ) {$charset_collate};";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        error_log('CPD: Created wp_rtr_email_tracking table');
        return true;
    }
    
    /**
     * V2: Create wp_rtr_room_progression table
     */
    public function create_room_progression_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'rtr_room_progression';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            visitor_id mediumint(9) NOT NULL,
            campaign_id VARCHAR(255) NOT NULL,
            from_room ENUM('none', 'problem', 'solution', 'offer'),
            to_room ENUM('problem', 'solution', 'offer') NOT NULL,
            score_at_transition INT,
            reason VARCHAR(255),
            transitioned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            
            INDEX idx_visitor_campaign (visitor_id, campaign_id),
            INDEX idx_transition_date (transitioned_at),
            
            FOREIGN KEY (visitor_id) 
                REFERENCES {$wpdb->prefix}cpd_visitors(id) 
                ON DELETE CASCADE
        ) {$charset_collate};";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        error_log('CPD: Created wp_rtr_room_progression table');
        return true;
    }
    
    /**
     * V2: Create wp_dr_campaign_settings table
     */
    public function create_campaign_settings_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'dr_campaign_settings';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            campaign_id VARCHAR(255) NOT NULL,
            client_id mediumint(9) NOT NULL,
            utm_campaign VARCHAR(255) NOT NULL,
            campaign_name VARCHAR(255) NOT NULL,
            campaign_description TEXT NULL,
            start_date DATE NULL,
            end_date DATE NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            UNIQUE KEY unique_campaign (campaign_id),
            UNIQUE KEY unique_utm_per_client (client_id, utm_campaign),
            INDEX idx_utm (utm_campaign),
            INDEX idx_client (client_id),
            
            FOREIGN KEY (client_id) 
                REFERENCES {$wpdb->prefix}cpd_clients(id) 
                ON DELETE CASCADE
        ) {$charset_collate};";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        error_log('CPD: Created wp_dr_campaign_settings table');
        return true;
    }
    
    /**
     * V2.0: Create wp_rtr_email_templates table
     * Prompt-based template system for AI email generation
     */
    public function create_email_templates_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'rtr_email_templates';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            campaign_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            room_type ENUM('problem', 'solution', 'offer') NOT NULL,
            template_name VARCHAR(255) NOT NULL,
            prompt_template LONGTEXT NOT NULL COMMENT 'JSON: 7-component prompt structure for AI generation',
            template_order INT DEFAULT 0,
            is_global TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            INDEX idx_campaign (campaign_id),
            INDEX idx_room_type (room_type),
            INDEX idx_campaign_room (campaign_id, room_type),
            INDEX idx_template_order (template_order),
            INDEX idx_is_global (is_global),
            INDEX idx_global_room (is_global, room_type),
            
            FOREIGN KEY (campaign_id) 
                REFERENCES {$wpdb->prefix}dr_campaign_settings(id) 
                ON DELETE CASCADE
        ) {$charset_collate};";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name) {
            error_log('CPD: Successfully created wp_rtr_email_templates table (prompt-based AI system)');
            return true;
        } else {
            error_log('CPD: FAILED to create wp_rtr_email_templates table');
            return false;
        }
    }
    
    /**
     * V2: Create all v2 tables
     */
    public function create_all_v2_tables() {
        $success = true;
        
        $success = $success && $this->create_campaign_settings_table();
        $success = $success && $this->create_visitor_campaigns_table();
        $success = $success && $this->create_email_tracking_table();
        $success = $success && $this->create_room_progression_table();
        $success = $success && $this->create_email_templates_table();
        
        if ($success) {
            error_log('CPD: All v2 tables created successfully');
        } else {
            error_log('CPD: Some v2 tables failed to create');
        }
        
        return $success;
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

    /**
     * V2: Get current database version
     */
    public function get_current_version() {
        return get_option('cpd_database_version', '1.2.0');
    }
    
    /**
     * V2: Rollback migration (for testing)
     */
    public function rollback_to_1_0_0() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cpd_clients';
        
        error_log('CPD: Starting rollback from v2.0.0 to v1.2.0');
        
        try {
            // Drop indexes
            $wpdb->query("DROP INDEX IF EXISTS idx_subscription_tier ON {$table_name}");
            $wpdb->query("DROP INDEX IF EXISTS idx_subscription_expires ON {$table_name}");
            
            // Drop columns
            $wpdb->query("ALTER TABLE {$table_name} DROP COLUMN IF EXISTS subscription_expires_at");
            $wpdb->query("ALTER TABLE {$table_name} DROP COLUMN IF EXISTS rtr_activated_at");
            $wpdb->query("ALTER TABLE {$table_name} DROP COLUMN IF EXISTS rtr_enabled");
            $wpdb->query("ALTER TABLE {$table_name} DROP COLUMN IF EXISTS subscription_tier");
            
            // Reset version
            update_option('cpd_database_version', '1.2.0');
            
            error_log('CPD: Rollback completed successfully');
            return true;
            
        } catch (Exception $e) {
            error_log('CPD Rollback Error: ' . $e->getMessage());
            return false;
        }
    }
}