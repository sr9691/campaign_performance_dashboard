<?php
/**
 * DirectReach v2 Access Control
 * Manages tier-based access to v1 and v2 features
 *
 * @package CPD_Dashboard
 * @subpackage V2
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CPD_Access_Control {
    
    /**
     * Get subscription tier for current user's client
     *
     * @param int $user_id WordPress user ID
     * @return string|null 'basic', 'premium', or null if no client linked
     */
    public static function get_user_tier( $user_id ) {
        // Admins have implicit premium access but we return their client tier if they have one
        if ( self::is_admin_user( $user_id ) ) {
            // Check if admin has a linked client for context
            $client_tier = self::get_linked_client_tier( $user_id );
            return $client_tier ?: 'premium'; // Default admins to premium if no client
        }
        
        return self::get_linked_client_tier( $user_id );
    }
    
    /**
     * Check if user has access to v1 features
     *
     * @param int $user_id WordPress user ID
     * @return bool
     */
    public static function has_v1_access( $user_id ) {
        // Admins always have v1 access
        if ( self::is_admin_user( $user_id ) ) {
            return true;
        }
        
        $tier = self::get_user_tier( $user_id );
        
        // Basic tier clients have v1 access
        // Premium tier clients do NOT have v1 access (v2 only)
        return $tier === 'basic';
    }
    
    /**
     * Check if user has access to v2 features
     *
     * @param int $user_id WordPress user ID
     * @return bool
     */
    public static function has_v2_access( $user_id ) {
        // Admins always have v2 access
        if ( self::is_admin_user( $user_id ) ) {
            return true;
        }
        
        $tier = self::get_user_tier( $user_id );
        
        // Only premium tier has v2 access
        if ( $tier !== 'premium' ) {
            return false;
        }
        
        // Check if RTR is explicitly enabled for this client
        return self::is_rtr_enabled_for_user( $user_id );
    }
    
    /**
     * Check if user is an administrator
     *
     * @param int $user_id WordPress user ID
     * @return bool
     */
    public static function is_admin_user( $user_id ) {
        $user = get_userdata( $user_id );
        
        if ( ! $user ) {
            return false;
        }
        
        return in_array( 'administrator', $user->roles, true );
    }
    
    /**
     * Get subscription tier for user's linked client
     *
     * @param int $user_id WordPress user ID
     * @return string|null 'basic', 'premium', or null
     */
    private static function get_linked_client_tier( $user_id ) {
        global $wpdb;
        
        $user_client_table = $wpdb->prefix . 'cpd_client_users';
        $clients_table = $wpdb->prefix . 'cpd_clients';
        
        $tier = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT c.subscription_tier 
                 FROM {$user_client_table} AS uc
                 INNER JOIN {$clients_table} AS c ON uc.client_id = c.id
                 WHERE uc.user_id = %d
                 LIMIT 1",
                $user_id
            )
        );
        
        return $tier ?: null;
    }
    
    /**
     * Check if RTR is enabled for user's client
     *
     * @param int $user_id WordPress user ID
     * @return bool
     */
    private static function is_rtr_enabled_for_user( $user_id ) {
        global $wpdb;
        
        $user_client_table = $wpdb->prefix . 'cpd_client_users';
        $clients_table = $wpdb->prefix . 'cpd_clients';
        
        $rtr_enabled = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT c.rtr_enabled 
                 FROM {$user_client_table} AS uc
                 INNER JOIN {$clients_table} AS c ON uc.client_id = c.id
                 WHERE uc.user_id = %d
                 LIMIT 1",
                $user_id
            )
        );
        
        return (bool) $rtr_enabled;
    }
    
    /**
     * Check if subscription is expired
     *
     * @param int $user_id WordPress user ID
     * @return bool True if expired
     */
    public static function is_subscription_expired( $user_id ) {
        global $wpdb;
        
        $user_client_table = $wpdb->prefix . 'cpd_client_users';
        $clients_table = $wpdb->prefix . 'cpd_clients';
        
        $expires_at = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT c.subscription_expires_at 
                 FROM {$user_client_table} AS uc
                 INNER JOIN {$clients_table} AS c ON uc.client_id = c.id
                 WHERE uc.user_id = %d
                 LIMIT 1",
                $user_id
            )
        );
        
        if ( ! $expires_at ) {
            return false; // No expiration set
        }
        
        return strtotime( $expires_at ) < time();
    }
    
    /**
     * Get account ID for user's linked client
     *
     * @param int $user_id WordPress user ID
     * @return string|null Account ID or null
     */
    public static function get_user_account_id( $user_id ) {
        global $wpdb;
        
        $user_client_table = $wpdb->prefix . 'cpd_client_users';
        $clients_table = $wpdb->prefix . 'cpd_clients';
        
        return $wpdb->get_var(
            $wpdb->prepare(
                "SELECT c.account_id 
                 FROM {$user_client_table} AS uc
                 INNER JOIN {$clients_table} AS c ON uc.client_id = c.id
                 WHERE uc.user_id = %d
                 LIMIT 1",
                $user_id
            )
        );
    }
}