<?php
/**
 * Reading Room Database Manager
 * 
 * Handles database migrations for sales handoff fields
 * 
 * @package DirectReach
 * @subpackage ReadingTheRoom
 * @since 1.0.0
 */

namespace DirectReach\ReadingTheRoom;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Reading_Room_Database {
    
    /**
     * Current database version
     */
    const DB_VERSION = '1.0.0';
    
    /**
     * Database version option key
     */
    const VERSION_OPTION = 'dr_rtr_db_version';
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->maybe_upgrade();
    }
    
    /**
     * Check if upgrade needed
     */
    private function maybe_upgrade() {
        $current_version = get_option(self::VERSION_OPTION, '0.0.0');
        
        if (version_compare($current_version, self::DB_VERSION, '<')) {
            $this->upgrade_database($current_version);
        }
    }
    
    /**
     * Run database upgrade
     */
    private function upgrade_database($from_version) {
        global $wpdb;
        
        // Add sales handoff fields to prospects table
        $this->add_sales_handoff_fields();
        
        // Update version
        update_option(self::VERSION_OPTION, self::DB_VERSION);
        
        error_log('RTR Dashboard: Database upgraded from ' . $from_version . ' to ' . self::DB_VERSION);
    }
    
    /**
     * Add sales handoff fields to wp_rtr_prospects
     */
    private function add_sales_handoff_fields() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'rtr_prospects';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") !== $table_name) {
            error_log('RTR Dashboard: wp_rtr_prospects table does not exist yet. Will add fields when table is created.');
            return;
        }
        
        // Check if columns already exist
        $columns = $wpdb->get_col("DESCRIBE {$table_name}");
        
        // Add sales_handoff_at if not exists
        if (!in_array('sales_handoff_at', $columns)) {
            $wpdb->query("
                ALTER TABLE {$table_name} 
                ADD COLUMN sales_handoff_at DATETIME NULL AFTER archived_at
            ");
            error_log('RTR Dashboard: Added sales_handoff_at column');
        }
        
        // Add handoff_notes if not exists
        if (!in_array('handoff_notes', $columns)) {
            $wpdb->query("
                ALTER TABLE {$table_name} 
                ADD COLUMN handoff_notes TEXT NULL AFTER sales_handoff_at
            ");
            error_log('RTR Dashboard: Added handoff_notes column');
        }
        
        // Add index on sales_handoff_at if not exists
        $indexes = $wpdb->get_results("SHOW INDEX FROM {$table_name}");
        $has_handoff_index = false;
        
        foreach ($indexes as $index) {
            if ($index->Column_name === 'sales_handoff_at') {
                $has_handoff_index = true;
                break;
            }
        }
        
        if (!$has_handoff_index) {
            $wpdb->query("
                ALTER TABLE {$table_name} 
                ADD INDEX idx_sales_handoff (sales_handoff_at)
            ");
            error_log('RTR Dashboard: Added index on sales_handoff_at');
        }
    }
    
    /**
     * Get table name with prefix
     */
    public function get_table_name($table) {
        global $wpdb;
        return $wpdb->prefix . 'rtr_' . $table;
    }
}