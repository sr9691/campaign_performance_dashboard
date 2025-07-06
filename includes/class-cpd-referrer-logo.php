<?php
/**
 * Handles referrer logo mapping and display logic
 *
 * @package CPD_Dashboard
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CPD_Referrer_Logo {

    /**
     * Get the appropriate logo URL for a given referrer URL
     *
     * @param string $referrer_url The full referrer URL from visitor data
     * @return string The logo URL to display
     */
    public static function get_logo_for_referrer( $referrer_url ) {
        // Get the referrer logo mappings from options
        $logo_mappings = get_option( 'cpd_referrer_logo_mappings', self::get_default_mappings() );
        $show_direct_logo = get_option( 'cpd_show_direct_logo', 1 );
        
        // If referrer is empty or null, handle "DIRECT" case
        if ( empty( $referrer_url ) || trim( $referrer_url ) === '' ) {
            if ( $show_direct_logo ) {
                return self::generate_direct_logo();
            } else {
                return self::get_default_logo();
            }
        }
        
        // Extract domain from referrer URL
        $domain = self::extract_domain_from_url( $referrer_url );
        
        // Check if we have a mapping for this domain
        if ( !empty( $domain ) && isset( $logo_mappings[$domain] ) ) {
            return $logo_mappings[$domain];
        }
        
        // Check for partial matches (e.g., www.google.com matches google.com)
        foreach ( $logo_mappings as $mapped_domain => $logo_url ) {
            if ( strpos( $domain, $mapped_domain ) !== false || strpos( $mapped_domain, $domain ) !== false ) {
                return $logo_url;
            }
        }
        
        // Default fallback
        return self::get_default_logo();
    }
    
    /**
     * Get referrer URL for tooltip display from visitor object
     *
     * @param object $visitor Visitor object
     * @return string The referrer URL for tooltip display
     */
    public static function get_referrer_url_for_visitor( $visitor ) {
        $referrer_url = '';
        
        // Try to get referrer from visitor object
        if ( isset( $visitor->most_recent_referrer ) ) {
            $referrer_url = $visitor->most_recent_referrer;
        } elseif ( isset( $visitor->referrer ) ) {
            $referrer_url = $visitor->referrer;
        } elseif ( isset( $visitor->referrer_url ) ) {
            $referrer_url = $visitor->referrer_url;
        }
        
        // Return appropriate tooltip text
        if ( empty( $referrer_url ) || trim( $referrer_url ) === '' ) {
            return 'Direct Traffic - No referrer';
        }
        
        return 'Referrer: ' . $referrer_url;
    }
    
    /**
     * Get alt text for referrer logo based on referrer URL
     *
     * @param string $referrer_url The full referrer URL from visitor data
     * @return string The alt text to display
     */
    public static function get_alt_text_for_referrer( $referrer_url ) {
        // If referrer is empty or null, return "DIRECT" text
        if ( empty( $referrer_url ) || trim( $referrer_url ) === '' ) {
            return 'Direct Traffic';
        }
        
        // Extract domain from referrer URL for cleaner display
        $domain = self::extract_domain_from_url( $referrer_url );
        
        if ( !empty( $domain ) ) {
            return 'Referrer: ' . $domain;
        }
        
        // Fallback to full URL if domain extraction fails
        return 'Referrer: ' . $referrer_url;
    }
    
    /**
     * Extract domain from a full URL
     *
     * @param string $url The full URL
     * @return string The domain part
     */
    private static function extract_domain_from_url( $url ) {
        // Add protocol if missing
        if ( !preg_match( '/^https?:\/\//', $url ) ) {
            $url = 'http://' . $url;
        }
        
        $parsed = parse_url( $url );
        if ( !$parsed || !isset( $parsed['host'] ) ) {
            return '';
        }
        
        $host = strtolower( $parsed['host'] );
        
        // Remove www. prefix
        if ( strpos( $host, 'www.' ) === 0 ) {
            $host = substr( $host, 4 );
        }
        
        return $host;
    }
    
    /**
     * Get default logo mappings
     *
     * @return array Default domain to logo URL mappings
     */
    private static function get_default_mappings() {
        return array(
            'google.com' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/c/c1/Google_%22G%22_logo.svg/240px-Google_%22G%22_logo.svg.png',
            'linkedin.com' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/c/ca/LinkedIn_logo_initials.png/240px-LinkedIn_logo_initials.png',
            'bing.com' => 'https://upload.wikimedia.org/wikipedia/commons/f/f3/Bing_fluent_logo.jpg',
        );
    }
    
    /**
     * Get the default MEMO seal logo
     *
     * @return string Default logo URL
     */
    private static function get_default_logo() {
        return CPD_DASHBOARD_PLUGIN_URL . 'assets/images/MEMO_Seal.png';
    }
    
    /**
     * Generate a data URI for "DIRECT" text logo
     *
     * @return string Data URI for DIRECT logo
     */
    private static function generate_direct_logo() {
        // Create a simple SVG with "DIRECT" text
        $svg = '<svg width="50" height="50" xmlns="http://www.w3.org/2000/svg">
                    <rect width="50" height="50" fill="#2c435d" rx="25"/>
                    <text x="25" y="32" font-family="Arial, sans-serif" font-size="8" font-weight="bold" fill="white" text-anchor="middle">DIRECT</text>
                </svg>';
        
        return 'data:image/svg+xml;base64,' . base64_encode( $svg );
    }
    
    /**
     * Get logo URL with visitor object (convenience method)
     *
     * @param object $visitor Visitor object
     * @return string Logo URL
     */
    public static function get_logo_for_visitor( $visitor ) {
        $referrer_url = '';
        
        // Try to get referrer from visitor object
        if ( isset( $visitor->most_recent_referrer ) ) {
            $referrer_url = $visitor->most_recent_referrer;
        } elseif ( isset( $visitor->referrer ) ) {
            $referrer_url = $visitor->referrer;
        } elseif ( isset( $visitor->referrer_url ) ) {
            $referrer_url = $visitor->referrer_url;
        }
        
        return self::get_logo_for_referrer( $referrer_url );
    }
    
    /**
     * Get alt text for visitor logo (convenience method)
     *
     * @param object $visitor Visitor object
     * @return string Alt text for the logo
     */
    public static function get_alt_text_for_visitor( $visitor ) {
        $referrer_url = '';
        
        // Try to get referrer from visitor object
        if ( isset( $visitor->most_recent_referrer ) ) {
            $referrer_url = $visitor->most_recent_referrer;
        } elseif ( isset( $visitor->referrer ) ) {
            $referrer_url = $visitor->referrer;
        } elseif ( isset( $visitor->referrer_url ) ) {
            $referrer_url = $visitor->referrer_url;
        }
        
        return self::get_alt_text_for_referrer( $referrer_url );
    }
    
    /**
     * Initialize default mappings if none exist
     *
     * @return void
     */
    public static function init_default_mappings() {
        $existing_mappings = get_option( 'cpd_referrer_logo_mappings', false );
        
        if ( $existing_mappings === false ) {
            // No mappings exist, create defaults
            $default_mappings = self::get_default_mappings();
            update_option( 'cpd_referrer_logo_mappings', $default_mappings );
            
            // Also set the direct logo option to enabled by default
            update_option( 'cpd_show_direct_logo', 1 );
        }
    }
}