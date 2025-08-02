<?php
/**
 * CPD Intelligence Handler - Updated with Phase 3 API Integration
 * Full intelligence handler class for AI Intelligence feature
 *
 * @package CPD_Dashboard
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CPD_Intelligence {

    private $wpdb;
    private $database;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        
        if ( ! class_exists( 'CPD_Database' ) ) {
            require_once CPD_DASHBOARD_PLUGIN_DIR . 'includes/class-cpd-database.php';
        }
        $this->database = new CPD_Database();
    }

    /**
     * Request visitor intelligence for a specific visitor
     * Updated: Full implementation with Make.com API integration
     */
    public function request_visitor_intelligence( $visitor_id, $user_id ) {
        try {
            error_log("CPD_Intelligence: Starting request for visitor_id: $visitor_id, user_id: $user_id");
            
            // Get visitor data
            $visitor = $this->get_visitor_data( $visitor_id );
            if ( ! $visitor ) {
                error_log("CPD_Intelligence: Visitor $visitor_id not found");
                throw new Exception( 'Visitor not found' );
            }
            error_log("CPD_Intelligence: Visitor found - account_id: " . $visitor->account_id);

            // Get client data and check AI enablement
            $client = $this->get_client_data( $visitor->account_id );
            if ( ! $client ) {
                error_log("CPD_Intelligence: Client not found for account_id: " . $visitor->account_id);
                throw new Exception( 'Client not found' );
            }
            error_log("CPD_Intelligence: Client found - AI enabled: " . ($client->ai_intelligence_enabled ? 'YES' : 'NO'));

            // Check if AI intelligence is enabled for this client
            if ( ! $client->ai_intelligence_enabled ) {
                error_log("CPD_Intelligence: AI Intelligence not enabled for client");
                throw new Exception( 'AI Intelligence is not enabled for this client' );
            }

            // Check if Make.com webhook is configured
            $webhook_url = get_option( 'cpd_intelligence_webhook_url' );
            $api_key = get_option( 'cpd_makecom_api_key' );
            
            if ( empty( $webhook_url ) || empty( $api_key ) ) {
                error_log("CPD_Intelligence: Webhook not configured - URL: " . ($webhook_url ? 'SET' : 'NOT SET') . ", API Key: " . ($api_key ? 'SET' : 'NOT SET'));
                throw new Exception( 'Intelligence webhook not configured. Please configure Make.com settings.' );
            }

            // Check rate limiting
            if ( ! $this->check_rate_limit( $visitor_id, $client->id ) ) {
                error_log("CPD_Intelligence: Rate limit exceeded");
                throw new Exception( 'Rate limit exceeded for this visitor' );
            }

            // Check if intelligence already exists
            $existing_intelligence = $this->get_existing_intelligence( $visitor_id, $client->id );
            if ( $existing_intelligence ) {
                error_log("CPD_Intelligence: Existing intelligence found - status: " . $existing_intelligence->status);
                return array(
                    'success' => true,
                    'status' => $existing_intelligence->status,
                    'intelligence_id' => $existing_intelligence->id,
                    'message' => 'Intelligence request already exists'
                );
            }

            // Create new intelligence record
            $intelligence_id = $this->create_intelligence_record( $visitor_id, $client->id, $user_id, $visitor, $client );
            
            if ( ! $intelligence_id ) {
                error_log("CPD_Intelligence: Failed to create intelligence record");
                throw new Exception( 'Failed to create intelligence record' );
            }
            
            error_log("CPD_Intelligence: Intelligence record created with ID: $intelligence_id");

            // NEW: Make actual API call to Make.com
            $api_result = $this->make_intelligence_api_call( $visitor, $client, $intelligence_id, $api_key, $webhook_url );
            
            if ( $api_result['success'] ) {
                error_log("CPD_Intelligence: API call successful for intelligence ID: $intelligence_id");
                return array(
                    'success' => true,
                    'status' => 'completed', // Changed from 'pending' to 'completed'
                    'intelligence_id' => $intelligence_id,
                    'message' => 'Intelligence generated successfully',
                    'intelligence_data' => $api_result['intelligence_data'] ?? null
                );
            } else {
                error_log("CPD_Intelligence: API call failed: " . $api_result['error']);
                // Update intelligence record with error
                $this->update_intelligence_status( $intelligence_id, 'failed', null, $api_result['error'] );
                throw new Exception( 'API request failed: ' . $api_result['error'] );
            }

        } catch ( Exception $e ) {
            error_log( 'CPD Intelligence Error: ' . $e->getMessage() );
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }

    /**
     * NEW: Make actual API call to Make.com webhook
     */
    private function make_intelligence_api_call( $visitor, $client, $intelligence_id, $api_key, $webhook_url ) {
        try {
            error_log("CPD_Intelligence: Preparing API call for intelligence ID: $intelligence_id");
            
            // Prepare client context
            $client_context = $this->prepare_client_context( $client );
            
            // Prepare visitor data payload (matching admin test format)
            $visitor_payload = $this->prepare_visitor_payload( $visitor );
            
            // Create the full API payload
            $api_payload = array(
                'api_key' => $api_key,
                'request_type' => 'single_visitor_intelligence',
                'intelligence_id' => $intelligence_id,
                'client_context' => $client_context,
                'visitor_data' => $visitor_payload,
                'request_metadata' => array(
                    'request_timestamp' => current_time( 'mysql' ),
                    'request_id' => uniqid( 'cpd_intel_' ),
                    'source' => 'Campaign Performance Dashboard - Manual Request'
                )
            );
            
            error_log("CPD_Intelligence: Sending API request to: $webhook_url");
            
            // Get timeout setting
            $timeout = get_option( 'cpd_intelligence_timeout', 30 );
            
            // Make the API request
            $response = wp_remote_post( $webhook_url, array(
                'timeout' => $timeout,
                'headers' => array(
                    'Content-Type' => 'application/json',
                ),
                'body' => wp_json_encode( $api_payload ),
                'sslverify' => true
            ) );
            
            if ( is_wp_error( $response ) ) {
                $error_message = $response->get_error_message();
                error_log("CPD_Intelligence: API request failed - " . $error_message);
                return array(
                    'success' => false,
                    'error' => 'Connection failed: ' . $error_message
                );
            }
            
            $response_code = wp_remote_retrieve_response_code( $response );
            $response_body = wp_remote_retrieve_body( $response );
            
            error_log("CPD_Intelligence: API response code: $response_code");
            
            if ( $response_code >= 200 && $response_code < 300 ) {
                // Parse the response data
                $intelligence_data = json_decode( $response_body, true );
                
                if ( $intelligence_data && json_last_error() === JSON_ERROR_NONE ) {
                    // Update the intelligence record with the completed data
                    $this->update_intelligence_status( $intelligence_id, 'completed', $intelligence_data );
                    
                    // Log successful API call
                    $this->log_intelligence_action( 
                        get_current_user_id(), 
                        'intelligence_api_success', 
                        "Intelligence API call successful and completed for visitor ID: {$visitor->id}, Intelligence ID: $intelligence_id"
                    );
                    
                    return array(
                        'success' => true,
                        'response_code' => $response_code,
                        'intelligence_data' => $intelligence_data
                    );
                } else {
                    // Invalid JSON response - mark as failed
                    $error_msg = 'Invalid JSON response from Make.com';
                    $this->update_intelligence_status( $intelligence_id, 'failed', null, $error_msg );
                    
                    error_log("CPD_Intelligence: Invalid JSON response: $response_body");
                    
                    return array(
                        'success' => false,
                        'error' => $error_msg
                    );
                }
            } else {
                error_log("CPD_Intelligence: API request failed with HTTP $response_code: $response_body");
                
                // Update intelligence record with failure
                $error_message = "API request failed (HTTP $response_code)";
                $this->update_intelligence_status( $intelligence_id, 'failed', null, $error_message );
                
                // Log failed API call
                $this->log_intelligence_action( 
                    get_current_user_id(), 
                    'intelligence_api_failed', 
                    "Intelligence API call failed for visitor ID: {$visitor->id}, Intelligence ID: $intelligence_id. HTTP $response_code: $response_body"
                );
                
                return array(
                    'success' => false,
                    'error' => "API request failed (HTTP $response_code): $response_body"
                );
            }
            
        } catch ( Exception $e ) {
            error_log("CPD_Intelligence: Exception in API call: " . $e->getMessage());
            return array(
                'success' => false,
                'error' => 'API call exception: ' . $e->getMessage()
            );
        }
    }

    /**
     * NEW: Prepare client context for API call
     */
    private function prepare_client_context( $client ) {
        return array(
            'client_id' => $client->id,
            'client_name' => $client->client_name,
            'about_client' => $client->client_context_info ?: '',
            'industry_focus' => $this->extract_industry_from_context( $client->client_context_info ),
            'target_audience' => $this->extract_audience_from_context( $client->client_context_info ),
            'ai_enabled' => (bool) $client->ai_intelligence_enabled,
            'webpage_url' => $client->webpage_url ?: ''
        );
    }

    /**
     * NEW: Prepare visitor data payload for API call
     */
    private function prepare_visitor_payload( $visitor ) {
        // Parse recent page URLs if they're stored as JSON string
        $recent_page_urls = array();
        if ( ! empty( $visitor->recent_page_urls ) ) {
            $decoded_urls = json_decode( $visitor->recent_page_urls, true );
            if ( is_array( $decoded_urls ) ) {
                $recent_page_urls = $decoded_urls;
            } else {
                // If not JSON, treat as comma-separated string
                $recent_page_urls = array_map( 'trim', explode( ',', $visitor->recent_page_urls ) );
            }
        }
        
        return array(
            'visitor_id' => $visitor->id,
            'client_id' => $visitor->account_id,
            'personal_info' => array(
                'first_name' => $visitor->first_name ?: '',
                'last_name' => $visitor->last_name ?: '',
                'full_name' => trim( ($visitor->first_name ?: '') . ' ' . ($visitor->last_name ?: '') ),
                'job_title' => $visitor->job_title ?: '',
                'linkedin_url' => $visitor->linkedin_url ?: ''
            ),
            'company_info' => array(
                'company_name' => $visitor->company_name ?: '',
                'website' => $visitor->website ?: '',
                'industry' => $visitor->industry ?: '',
                'estimated_employee_count' => $visitor->estimated_employee_count ?: '',
                'estimated_revenue' => $visitor->estimated_revenue ?: '',
                'location' => array(
                    'city' => $visitor->city ?: '',
                    'state' => $visitor->state ?: '',
                    'zipcode' => $visitor->zipcode ?: ''
                )
            ),
            'engagement_data' => array(
                'first_seen_at' => $visitor->first_seen_at ?: '',
                'last_seen_at' => $visitor->last_seen_at ?: '',
                'all_time_page_views' => (int) ($visitor->all_time_page_views ?: 0),
                'recent_page_count' => (int) ($visitor->recent_page_count ?: 0),
                'recent_page_urls' => $recent_page_urls,
                'most_recent_referrer' => $visitor->most_recent_referrer ?: ''
            )
        );
    }

    /**
     * NEW: Extract industry information from client context
     */
    private function extract_industry_from_context( $context ) {
        if ( empty( $context ) ) {
            return '';
        }
        
        // Simple keyword extraction for industry
        $industry_keywords = array(
            'technology', 'tech', 'software', 'saas', 'healthcare', 'medical', 
            'finance', 'financial', 'retail', 'ecommerce', 'manufacturing', 
            'construction', 'education', 'consulting', 'marketing', 'real estate'
        );
        
        $context_lower = strtolower( $context );
        foreach ( $industry_keywords as $keyword ) {
            if ( strpos( $context_lower, $keyword ) !== false ) {
                return ucfirst( $keyword );
            }
        }
        
        return '';
    }

    /**
     * NEW: Extract target audience from client context
     */
    private function extract_audience_from_context( $context ) {
        if ( empty( $context ) ) {
            return '';
        }
        
        // Simple keyword extraction for audience
        $audience_keywords = array(
            'b2b', 'b2c', 'enterprise', 'small business', 'startups', 
            'consumers', 'professionals', 'executives'
        );
        
        $context_lower = strtolower( $context );
        foreach ( $audience_keywords as $keyword ) {
            if ( strpos( $context_lower, $keyword ) !== false ) {
                return ucwords( $keyword );
            }
        }
        
        return '';
    }
    
    /**
     * Get visitor data by ID
     */
    private function get_visitor_data( $visitor_id ) {
        $table_name = $this->wpdb->prefix . 'cpd_visitors';
        
        $visitor = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM $table_name WHERE id = %d AND is_archived = 0",
                $visitor_id
            )
        );

        return $visitor;
    }

    /**
     * Get client data by account ID
     */
    private function get_client_data( $account_id ) {
        $table_name = $this->wpdb->prefix . 'cpd_clients';
        
        $client = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM $table_name WHERE account_id = %s",
                $account_id
            )
        );

        return $client;
    }

    /**
     * Check rate limiting for visitor intelligence requests
     */
    private function check_rate_limit( $visitor_id, $client_id ) {
        $rate_limit = get_option( 'cpd_intelligence_rate_limit', 5 ); // Default 5 per day
        $table_name = $this->wpdb->prefix . 'cpd_visitor_intelligence';
        
        $today_start = date( 'Y-m-d 00:00:00' );
        $today_end = date( 'Y-m-d 23:59:59' );
        
        $count = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name 
                WHERE visitor_id = %d AND client_id = %d 
                AND created_at BETWEEN %s AND %s",
                $visitor_id, $client_id, $today_start, $today_end
            )
        );

        return $count < $rate_limit;
    }

    /**
     * Get existing intelligence record for visitor and client
     */
    private function get_existing_intelligence( $visitor_id, $client_id ) {
        $table_name = $this->wpdb->prefix . 'cpd_visitor_intelligence';
        
        $intelligence = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM $table_name 
                WHERE visitor_id = %d AND client_id = %d 
                ORDER BY created_at DESC LIMIT 1",
                $visitor_id, $client_id
            )
        );

        return $intelligence;
    }

    /**
     * Create new intelligence record
     */
    private function create_intelligence_record( $visitor_id, $client_id, $user_id, $visitor, $client ) {
        $table_name = $this->wpdb->prefix . 'cpd_visitor_intelligence';
        
        // Prepare request data
        $request_data = array(
            'visitor' => array(
                'id' => $visitor->id,
                'linkedin_url' => $visitor->linkedin_url,
                'first_name' => $visitor->first_name,
                'last_name' => $visitor->last_name,
                'job_title' => $visitor->job_title,
                'company_name' => $visitor->company_name,
                'industry' => $visitor->industry,
                'website' => $visitor->website,
                'city' => $visitor->city,
                'state' => $visitor->state,
                'estimated_employee_count' => $visitor->estimated_employee_count,
                'estimated_revenue' => $visitor->estimated_revenue,
                'recent_page_urls' => $visitor->recent_page_urls,
                'all_time_page_views' => $visitor->all_time_page_views,
                'recent_page_count' => $visitor->recent_page_count
            ),
            'client' => array(
                'account_id' => $client->account_id,
                'client_name' => $client->client_name,
                'webpage_url' => $client->webpage_url
            )
        );
        
        $result = $this->wpdb->insert(
            $table_name,
            array(
                'visitor_id' => $visitor_id,
                'client_id' => $client_id,
                'user_id' => $user_id,
                'request_data' => wp_json_encode( $request_data ),
                'client_context' => $client->client_context_info,
                'status' => 'pending',
                'created_at' => current_time( 'mysql' ),
                'updated_at' => current_time( 'mysql' )
            ),
            array( '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
        );

        if ( $result === false ) {
            error_log( 'CPD Intelligence: Failed to create intelligence record - ' . $this->wpdb->last_error );
            return false;
        }

        $intelligence_id = $this->wpdb->insert_id;
        
        // Log the action
        $this->log_intelligence_action( $user_id, 'intelligence_requested', 
            "Intelligence requested for visitor ID: $visitor_id, Intelligence ID: $intelligence_id" );

        return $intelligence_id;
    }

    /**
     * Get intelligence status for a visitor
     */
    public function get_visitor_intelligence_status( $visitor_id ) {
        $table_name = $this->wpdb->prefix . 'cpd_visitor_intelligence';
        
        $intelligence = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT status, created_at, updated_at FROM $table_name 
                WHERE visitor_id = %d 
                ORDER BY created_at DESC LIMIT 1",
                $visitor_id
            )
        );

        if ( ! $intelligence ) {
            return array(
                'status' => 'not_requested',
                'created_at' => null,
                'updated_at' => null
            );
        }

        return array(
            'status' => $intelligence->status,
            'created_at' => $intelligence->created_at,
            'updated_at' => $intelligence->updated_at
        );
    }

    /**
     * Update intelligence status
     */
    public function update_intelligence_status( $intelligence_id, $status, $response_data = null, $error_message = null ) {
        $table_name = $this->wpdb->prefix . 'cpd_visitor_intelligence';
        
        $update_data = array(
            'status' => $status,
            'updated_at' => current_time( 'mysql' )
        );
        
        $format = array( '%s', '%s' );

        if ( $response_data !== null ) {
            $update_data['response_data'] = wp_json_encode( $response_data );
            $format[] = '%s';
        }

        if ( $error_message !== null ) {
            $update_data['error_message'] = $error_message;
            $format[] = '%s';
        }

        $result = $this->wpdb->update(
            $table_name,
            $update_data,
            array( 'id' => $intelligence_id ),
            $format,
            array( '%d' )
        );

        return $result !== false;
    }

    /**
     * Get intelligence data for a specific intelligence ID
     */
    public function get_intelligence_data( $intelligence_id ) {
        $table_name = $this->wpdb->prefix . 'cpd_visitor_intelligence';
        
        $intelligence = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM $table_name WHERE id = %d",
                $intelligence_id
            )
        );

        if ( $intelligence ) {
            // Decode JSON fields
            $intelligence->request_data = json_decode( $intelligence->request_data, true );
            if ( $intelligence->response_data ) {
                $intelligence->response_data = json_decode( $intelligence->response_data, true );
            }
        }

        return $intelligence;
    }

    /**
     * Get intelligence statistics for admin dashboard
     */
    public function get_intelligence_statistics() {
        $table_name = $this->wpdb->prefix . 'cpd_visitor_intelligence';
        
        $stats = array();
        
        // Total intelligence requests
        $stats['total_requests'] = $this->wpdb->get_var(
            "SELECT COUNT(*) FROM $table_name"
        );
        
        // Requests by status
        $status_counts = $this->wpdb->get_results(
            "SELECT status, COUNT(*) as count FROM $table_name GROUP BY status"
        );
        
        foreach ( $status_counts as $status ) {
            $stats['by_status'][$status->status] = $status->count;
        }
        
        // Today's requests
        $today = date( 'Y-m-d' );
        $stats['today_requests'] = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE DATE(created_at) = %s",
                $today
            )
        );
        
        // Success rate (completed vs total)
        $completed = isset( $stats['by_status']['completed'] ) ? $stats['by_status']['completed'] : 0;
        $stats['success_rate'] = $stats['total_requests'] > 0 ? 
            round( ( $completed / $stats['total_requests'] ) * 100, 2 ) : 0;

        return $stats;
    }

    /**
     * Get recent intelligence requests for admin monitoring
     */
    public function get_recent_intelligence_requests( $limit = 20 ) {
        $table_name_intelligence = $this->wpdb->prefix . 'cpd_visitor_intelligence';
        $table_name_visitors = $this->wpdb->prefix . 'cpd_visitors';
        $table_name_clients = $this->wpdb->prefix . 'cpd_clients';
        $table_name_users = $this->wpdb->users;
        
        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT 
                    i.id,
                    i.status,
                    i.created_at,
                    i.updated_at,
                    v.first_name,
                    v.last_name,
                    v.company_name,
                    c.client_name,
                    u.display_name as user_name
                FROM $table_name_intelligence i
                LEFT JOIN $table_name_visitors v ON i.visitor_id = v.id
                LEFT JOIN $table_name_clients c ON i.client_id = c.id
                LEFT JOIN $table_name_users u ON i.user_id = u.ID
                ORDER BY i.created_at DESC
                LIMIT %d",
                $limit
            )
        );

        return $results ? $results : array();
    }

    /**
     * Check if intelligence feature is properly configured
     */
    public function is_intelligence_configured() {
        $webhook_url = get_option( 'cpd_intelligence_webhook_url' );
        $api_key = get_option( 'cpd_makecom_api_key' );
        
        return ! empty( $webhook_url ) && ! empty( $api_key );
    }

    /**
     * Validate intelligence settings
     */
    public function validate_intelligence_settings( $settings ) {
        $errors = array();
        
        // Validate webhook URL
        if ( empty( $settings['webhook_url'] ) ) {
            $errors[] = 'Webhook URL is required';
        } elseif ( ! filter_var( $settings['webhook_url'], FILTER_VALIDATE_URL ) ) {
            $errors[] = 'Invalid webhook URL format';
        }
        
        // Validate rate limit
        if ( ! isset( $settings['rate_limit'] ) || ! is_numeric( $settings['rate_limit'] ) ) {
            $errors[] = 'Rate limit must be a number';
        } elseif ( $settings['rate_limit'] < 1 || $settings['rate_limit'] > 10 ) {
            $errors[] = 'Rate limit must be between 1 and 10';
        }
        
        // Validate timeout
        if ( ! isset( $settings['timeout'] ) || ! is_numeric( $settings['timeout'] ) ) {
            $errors[] = 'Timeout must be a number';
        } elseif ( $settings['timeout'] < 10 || $settings['timeout'] > 120 ) {
            $errors[] = 'Timeout must be between 10 and 120 seconds';
        }

        return $errors;
    }

    /**
     * Log intelligence-related actions
     */
    private function log_intelligence_action( $user_id, $action_type, $description ) {
        $table_name = $this->wpdb->prefix . 'cpd_action_logs';
        
        $this->wpdb->insert(
            $table_name,
            array(
                'user_id' => $user_id,
                'action_type' => $action_type,
                'description' => $description,
                'timestamp' => current_time( 'mysql' )
            ),
            array( '%d', '%s', '%s', '%s' )
        );
    }

    /**
     * Clean up old intelligence records (for maintenance)
     */
    public function cleanup_old_intelligence_records( $days_old = 90 ) {
        $table_name = $this->wpdb->prefix . 'cpd_visitor_intelligence';
        
        $cutoff_date = date( 'Y-m-d H:i:s', strtotime( "-$days_old days" ) );
        
        $deleted = $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM $table_name WHERE created_at < %s AND status IN ('completed', 'failed')",
                $cutoff_date
            )
        );

        if ( $deleted !== false ) {
            error_log( "CPD Intelligence: Cleaned up $deleted old intelligence records" );
        }

        return $deleted;
    }

    /**
     * Get intelligence requests for a specific client (for client-specific filtering)
     */
    public function get_client_intelligence_requests( $account_id, $limit = 50 ) {
        $table_name_intelligence = $this->wpdb->prefix . 'cpd_visitor_intelligence';
        $table_name_visitors = $this->wpdb->prefix . 'cpd_visitors';
        $table_name_clients = $this->wpdb->prefix . 'cpd_clients';
        
        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT 
                    i.*,
                    v.first_name,
                    v.last_name,
                    v.company_name,
                    c.client_name
                FROM $table_name_intelligence i
                LEFT JOIN $table_name_visitors v ON i.visitor_id = v.id
                LEFT JOIN $table_name_clients c ON i.client_id = c.id
                WHERE c.account_id = %s
                ORDER BY i.created_at DESC
                LIMIT %d",
                $account_id, $limit
            )
        );

        return $results ? $results : array();
    }
}