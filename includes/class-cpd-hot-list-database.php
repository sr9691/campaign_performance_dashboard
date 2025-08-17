<?php
/**
 * Hot List Database Operations
 * 
 * Create this file: includes/class-cpd-hot-list-database.php
 *
 * @package CPD_Dashboard
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CPD_Hot_List_Database {

    private $wpdb;
    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'cpd_hot_list_settings';
    }

    /**
     * Create hot list settings table
     */
    public function create_table() {
        $charset_collate = $this->wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$this->table_name} (
            id INT AUTO_INCREMENT PRIMARY KEY,
            client_id VARCHAR(255) NOT NULL,
            revenue_filters TEXT DEFAULT NULL,
            company_size_filters TEXT DEFAULT NULL,
            industry_filters TEXT DEFAULT NULL,
            state_filters TEXT DEFAULT NULL,
            required_matches INT DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_by INT DEFAULT NULL,
            updated_by INT DEFAULT NULL,
            UNIQUE KEY unique_client_settings (client_id),
            KEY idx_client_id (client_id),
            KEY idx_updated_at (updated_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Log the table creation
        if ($this->wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'") == $this->table_name) {
            error_log("CPD: Hot List Settings table created successfully");
            return true;
        } else {
            error_log("CPD: Failed to create Hot List Settings table: " . $this->wpdb->last_error);
            return false;
        }
    }

    /**
     * Check if hot list table exists
     */
    public function table_exists() {
        return $this->wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'") == $this->table_name;
    }

    /**
     * Get hot list settings for a client
     */
    public function get_settings($client_id) {
        $settings = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE client_id = %s",
                $client_id
            )
        );
        
        if ($settings) {
            // Decode JSON fields
            $settings->revenue_filters = json_decode($settings->revenue_filters, true) ?: array();
            $settings->company_size_filters = json_decode($settings->company_size_filters, true) ?: array();
            $settings->industry_filters = json_decode($settings->industry_filters, true) ?: array();
            $settings->state_filters = json_decode($settings->state_filters, true) ?: array();
        }
        
        return $settings;
    }

    /**
     * Save hot list settings for a client
     */
    public function save_settings($client_id, $settings, $user_id = null) {
        $data = array(
            'client_id' => $client_id,
            'revenue_filters' => json_encode($settings['revenue'] ?: array()),
            'company_size_filters' => json_encode($settings['company_size'] ?: array()),
            'industry_filters' => json_encode($settings['industry'] ?: array()),
            'state_filters' => json_encode($settings['state'] ?: array()),
            'required_matches' => intval($settings['required_matches']) ?: 1,
            'updated_by' => $user_id
        );
        
        // Check if settings exist
        $existing = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT id FROM {$this->table_name} WHERE client_id = %s",
                $client_id
            )
        );
        
        if ($existing) {
            // Update existing settings
            $result = $this->wpdb->update(
                $this->table_name,
                $data,
                array('client_id' => $client_id),
                array('%s', '%s', '%s', '%s', '%s', '%d', '%d'),
                array('%s')
            );
        } else {
            // Insert new settings
            $data['created_by'] = $user_id;
            $result = $this->wpdb->insert(
                $this->table_name,
                $data,
                array('%s', '%s', '%s', '%s', '%s', '%d', '%d')
            );
        }
        
        return $result !== false;
    }

    /**
     * Delete hot list settings for a client
     */
    public function delete_settings($client_id) {
        return $this->wpdb->delete(
            $this->table_name,
            array('client_id' => $client_id),
            array('%s')
        ) !== false;
    }

    /**
     * Get all clients with hot list settings configured
     */
    public function get_clients_with_settings() {
        return $this->wpdb->get_results(
            "SELECT DISTINCT client_id FROM {$this->table_name} ORDER BY updated_at DESC"
        );
    }

    /**
     * Get hot list statistics for admin dashboard
     */
    public function get_statistics() {
        $stats = array();
        
        // Total clients with hot list configured
        $stats['configured_clients'] = $this->wpdb->get_var(
            "SELECT COUNT(DISTINCT client_id) FROM {$this->table_name}"
        );
        
        // Most recent configuration update
        $stats['last_updated'] = $this->wpdb->get_var(
            "SELECT MAX(updated_at) FROM {$this->table_name}"
        );
        
        // Average required matches
        $stats['avg_required_matches'] = $this->wpdb->get_var(
            "SELECT AVG(required_matches) FROM {$this->table_name}"
        );
        
        return $stats;
    }
}