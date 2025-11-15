<?php
/**
 * A-Leads Enrichment API Integration (via Make.com Proxy)
 *
 * @package DirectReach
 * @subpackage ReadingTheRoom
 * @since 2.0.0
 */

declare(strict_types=1);

namespace DirectReach\ReadingTheRoom\API;

if (!defined('ABSPATH')) {
    exit;
}

final class ALeads_Enrichment
{
    /** @var string */
    private $api_key;

    /** @var string Make.com proxy webhook URL */
    private $make_webhook_url;

    /** @var array A-Leads endpoint mappings */
    private $endpoints = [
        'advanced_search' => 'https://api.a-leads.co/gateway/v1/search/advanced-search',
        'find_email' => 'https://api.a-leads.co/gateway/v1/enrich/find-email',
        'verify_email' => 'https://api.a-leads.co/gateway/v1/enrich/verify-email'
    ];

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->api_key = get_option('rtr_aleads_api_key', '');
        
        // Get Make.com webhook URL from options
        $this->make_webhook_url = get_option('rtr_aleads_make_webhook', 'https://hook.us1.make.com/aeqmdssxzk72vk653vnjktqzpqmp5s6v');
        
        if (empty($this->make_webhook_url)) {
            error_log('A-Leads Warning: Make.com webhook URL not configured. Set via update_option("rtr_aleads_make_webhook", "YOUR_URL")');
        }
    }

    /**
     * Search for contacts at a company.
     *
     * @param string $company_name Company name to search
     * @return array Array of contacts (empty array on failure)
     */
    public function search_contacts(string $company_name): array
    {
        if (empty($this->make_webhook_url)) {
            error_log('A-Leads API Error: Make.com webhook URL not configured');
            return [];
        }

        if (empty($company_name)) {
            error_log('A-Leads API Error: Company name is required');
            return [];
        }

        // Build API request payload
        $payload = [
            'advanced_filters' => [
                'organizations' => [$company_name]
            ],
            'current_page' => 0,
            'search_type' => 'new'
        ];

        // Make request via Make.com proxy
        $response_data = $this->make_proxy_request('advanced_search', $payload, $company_name);

        if (empty($response_data)) {
            return [];
        }

        // Parse and filter results
        return $this->parse_contacts($response_data);
    }

    /**
     * Find email address for a prospect
     * 
     * Handles two workflows:
     * 1. Enrichment Manager: Receives member_id directly from contact search
     * 2. Prospect Info Modal: Searches A-Leads first, matches by LinkedIn/name/title
     * 
     * POST /wp-json/directreach/v1/reading-room/prospects/{id}/find-email
     */
    public function find_email($request) {
        global $wpdb;
        
        $visitor_id = (int) $request['id'];
        $body = $request->get_json_params();
        
        try {
            // WORKFLOW 1: Enrichment Manager (has member_id from search)
            if (!empty($body['member_id'])) {
                $enrichment = new \DirectReach\ReadingTheRoom\API\ALeads_Enrichment();
                $result = $enrichment->find_email_by_member_id(
                    $body['member_id'],
                    $body['first_name'],
                    $body['last_name'],
                    $body['company_domain']
                );
                
                if (!empty($result['email'])) {
                    // Update visitor
                    $wpdb->update(
                        "{$wpdb->prefix}cpd_visitors",
                        ['email' => $result['email']],
                        ['id' => $visitor_id],
                        ['%s'],
                        ['%d']
                    );
                    
                    // Update prospect
                    $wpdb->update(
                        "{$wpdb->prefix}rtr_prospects",
                        ['contact_email' => $result['email']],
                        ['visitor_id' => $visitor_id],
                        ['%s'],
                        ['%d']
                    );
                    
                    return new \WP_REST_Response([
                        'success' => true,
                        'data' => [
                            'email' => $result['email'],
                            'confidence' => $result['confidence'] ?? null,
                            'email_status' => $result['email_status'] ?? null,
                            'source' => 'aleads'
                        ]
                    ], 200);
                }
                
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => 'Email not found'
                ], 404);
            }
            
            // WORKFLOW 2: Prospect Info Modal (no member_id - need to search first)
            
            // Get visitor data
            $visitor = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}cpd_visitors WHERE id = %d",
                $visitor_id
            ), ARRAY_A);
            
            if (!$visitor) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => 'Visitor not found'
                ], 404);
            }
            
            // Check if email already exists
            if (!empty($visitor['email'])) {
                return new \WP_REST_Response([
                    'success' => true,
                    'data' => [
                        'email' => $visitor['email'],
                        'source' => 'existing',
                        'message' => 'Email already exists for this prospect'
                    ]
                ], 200);
            }
            
            // Validate we have minimum required data
            if (empty($visitor['company_name'])) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => 'Company name required for email search'
                ], 400);
            }
            
            // Step 1: Search A-Leads to find contacts at company
            $enrichment = new \DirectReach\ReadingTheRoom\API\ALeads_Enrichment();
            $contacts = $enrichment->search_contacts($visitor['company_name']);
            
            if (empty($contacts)) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => 'No contacts found at ' . $visitor['company_name']
                ], 404);
            }
            
            // Step 2: Find best matching contact using LinkedIn/name/title
            $first_name = $visitor['first_name'] ?? '';
            $last_name = $visitor['last_name'] ?? '';
            $job_title = $visitor['job_title'] ?? '';
            $linkedin_url = $visitor['linkedin_url'] ?? '';
            
            $matched_contact = null;
            $best_score = 0;
            
            foreach ($contacts as $contact) {
                $score = 0;
                
                // LinkedIn URL match (most reliable) - worth 100 points
                if (!empty($linkedin_url) && !empty($contact['linkedin'])) {
                    $visitor_linkedin = strtolower(trim($linkedin_url));
                    $contact_linkedin = strtolower(trim($contact['linkedin']));
                    
                    // Normalize URLs - remove protocol and www
                    $visitor_linkedin = preg_replace('#^https?://(www\.)?linkedin\.com/in/#', '', $visitor_linkedin);
                    $visitor_linkedin = rtrim($visitor_linkedin, '/');
                    $contact_linkedin = preg_replace('#^https?://(www\.)?linkedin\.com/in/#', '', $contact_linkedin);
                    $contact_linkedin = rtrim($contact_linkedin, '/');
                    
                    if ($visitor_linkedin === $contact_linkedin) {
                        $score += 100;
                    }
                }
                
                // Name match - worth up to 50 points
                $first_match = false;
                $last_match = false;
                
                if (!empty($first_name) && !empty($contact['first_name'])) {
                    $first_match = stripos($contact['first_name'], $first_name) !== false || 
                                stripos($first_name, $contact['first_name']) !== false;
                }
                
                if (!empty($last_name) && !empty($contact['last_name'])) {
                    $last_match = stripos($contact['last_name'], $last_name) !== false || 
                                stripos($last_name, $contact['last_name']) !== false;
                }
                
                if ($first_match && $last_match) {
                    $score += 50;
                } elseif ($first_match || $last_match) {
                    $score += 25;
                }
                
                // Job title fuzzy match - worth up to 40 points
                if (!empty($job_title) && !empty($contact['job_title'])) {
                    $title_score = $this->fuzzy_match_job_title($job_title, $contact['job_title']);
                    $score += $title_score;
                }
                
                // Keep track of best match
                if ($score > $best_score) {
                    $best_score = $score;
                    $matched_contact = $contact;
                }
            }
            
            // Require minimum score of 50 (at least name match)
            if (!$matched_contact || $best_score < 50) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => 'No reliable contact match found in A-Leads (score: ' . $best_score . ')'
                ], 404);
            }
            
            // Validate member_id exists
            if (empty($matched_contact['member_id'])) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => 'Matched contact missing member_id'
                ], 500);
            }
            
            // Step 3: Call find_email with the matched contact's member_id
            $result = $enrichment->find_email_by_member_id(
                $matched_contact['member_id'],
                $matched_contact['first_name'] ?? $first_name,
                $matched_contact['last_name'] ?? $last_name,
                $matched_contact['domain'] ?? ''
            );
            
            if (!empty($result['email'])) {
                // Update visitor
                $wpdb->update(
                    "{$wpdb->prefix}cpd_visitors",
                    ['email' => $result['email']],
                    ['id' => $visitor_id],
                    ['%s'],
                    ['%d']
                );
                
                // Update prospect
                $wpdb->update(
                    "{$wpdb->prefix}rtr_prospects",
                    ['contact_email' => $result['email']],
                    ['visitor_id' => $visitor_id],
                    ['%s'],
                    ['%d']
                );
                
                // Log the action
                $wpdb->insert(
                    "{$wpdb->prefix}cpd_action_logs",
                    [
                        'client_id' => $visitor['client_id'] ?? null,
                        'visitor_id' => $visitor_id,
                        'action_type' => 'email_found',
                        'action_data' => wp_json_encode([
                            'email' => $result['email'],
                            'source' => 'aleads',
                            'match_score' => $best_score,
                            'confidence' => $result['confidence'] ?? null
                        ]),
                        'created_at' => current_time('mysql')
                    ],
                    ['%d', '%d', '%s', '%s', '%s']
                );
                
                return new \WP_REST_Response([
                    'success' => true,
                    'data' => [
                        'email' => $result['email'],
                        'confidence' => $result['confidence'] ?? null,
                        'email_status' => $result['email_status'] ?? null,
                        'match_score' => $best_score,
                        'source' => 'aleads'
                    ]
                ], 200);
            }
            
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Email not found for matched contact'
            ], 404);
            
        } catch (\Exception $e) {
            error_log('Find email error: ' . $e->getMessage());
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Error finding email: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Fuzzy match job titles
     * Returns score from 0-40 based on similarity
     */
    private function fuzzy_match_job_title($title1, $title2) {
        $title1 = strtolower(trim($title1));
        $title2 = strtolower(trim($title2));
        
        // Exact match
        if ($title1 === $title2) {
            return 40;
        }
        
        // One contains the other
        if (strpos($title1, $title2) !== false || strpos($title2, $title1) !== false) {
            return 35;
        }
        
        // Normalize: remove common filler words and punctuation
        $fillers = ['senior', 'junior', 'lead', 'head', 'chief', 'assistant', 'associate', 'vice', 'president', 'vp', 'svp', 'evp', 'the', 'of', 'and', '&', '-', ',', '.', 'ii', 'iii', 'iv'];
        
        // Split into words
        $t1_clean = str_replace($fillers, ' ', $title1);
        $t2_clean = str_replace($fillers, ' ', $title2);
        
        $t1_words = array_filter(array_map('trim', explode(' ', $t1_clean)));
        $t2_words = array_filter(array_map('trim', explode(' ', $t2_clean)));
        
        if (empty($t1_words) || empty($t2_words)) {
            return 0;
        }
        
        // Count matching significant words
        $matches = count(array_intersect($t1_words, $t2_words));
        $total = max(count($t1_words), count($t2_words));
        
        // Calculate overlap percentage
        $overlap = $matches / $total;
        
        // Score based on overlap
        // 70%+ overlap = 30 points (e.g., "Marketing Director" vs "Director Marketing")
        // 50-69% overlap = 20 points (e.g., "VP Marketing" vs "Marketing Manager")
        // 30-49% overlap = 10 points (e.g., "Sales Manager" vs "Account Manager")
        if ($overlap >= 0.7) {
            return 30;
        } elseif ($overlap >= 0.5) {
            return 20;
        } elseif ($overlap >= 0.3) {
            return 10;
        }
        
        return 0;
    }

    /**
     * Verify email address for a prospect
     */
    public function verify_email( $email, $visitor_id = null ) {
        try {
            error_log('[RTR] Starting email verification for: ' . $email . ' (visitor_id: ' . $visitor_id . ')');
            
            // Validate email parameter
            if (!is_string($email) || empty($email)) {
                error_log('[RTR] Invalid email parameter: ' . print_r($email, true));
                return [
                    'success' => false,
                    'message' => 'Invalid email provided'
                ];
            }
            
            $company_domain = '';
            $prospect = null;
            
            // Get prospect by visitor_id (primary lookup method)
            if ($visitor_id) {
                $prospect = $this->get_prospect_by_visitor_id($visitor_id);
                error_log('[RTR] Prospect lookup by visitor_id: ' . print_r($prospect, true));
                
                if (is_array($prospect) && !empty($prospect)) {
                    // Get company domain from prospect
                    $company_domain = $prospect['company_domain'] ?? '';
                    error_log('[RTR] Company domain from prospect: ' . $company_domain);
                }
            }
            
            // If no domain found, try visitor table
            if (empty($company_domain) && $visitor_id) {
                $visitor = $this->get_visitor_data($visitor_id);
                error_log('[RTR] Visitor data: ' . print_r($visitor, true));
                
                if (is_array($visitor) && !empty($visitor)) {
                    if (!empty($visitor['website'])) {
                        $company_domain = $this->extract_domain($visitor['website']);
                        error_log('[RTR] Domain from visitor website: ' . $company_domain);
                    } elseif (!empty($visitor['company_domain'])) {
                        $company_domain = $visitor['company_domain'];
                        error_log('[RTR] Domain from visitor company_domain: ' . $company_domain);
                    }
                }
            }
            
            // Last resort: extract domain from email
            if (empty($company_domain)) {
                $email_parts = explode('@', $email);
                if (count($email_parts) === 2) {
                    $company_domain = $email_parts[1];
                    error_log('[RTR] Domain extracted from email: ' . $company_domain);
                }
            }
                
            if (empty($company_domain)) {
                error_log('[RTR] No company domain found');
                return [
                    'success' => false,
                    'message' => 'Company domain not found'
                ];
            }

            error_log('[RTR] Final company domain: ' . $company_domain);

            // Call ALeads API via Make.com proxy
            $api_response = $this->make_proxy_request('verify_email', [
                'email' => $email
            ], "Email verification for: {$email}");

            if (!$api_response) {
                error_log('[RTR] API request failed - no result returned');
                return [
                    'success' => false,
                    'message' => 'Email verification API request failed'
                ];
            }

            error_log('[RTR] ALeads API raw response: ' . print_r($api_response, true));

            // Parse nested response structure from A-Leads
            // Response structure: message.data.response.{is_valid, quality, result, catch_all_status, esp}
            $response_data = $api_response['message']['data']['response'] ?? 
                            $api_response['data']['response'] ?? 
                            $api_response['response'] ?? 
                            $api_response;
            
            error_log('[RTR] Parsed response data: ' . print_r($response_data, true));
            
            // Check if verification was successful using is_valid field
            $is_valid = $response_data['is_valid'] ?? false;
            $quality = $response_data['quality'] ?? 'unknown';
            $catch_all = $response_data['catch_all_status'] ?? false;
            
            // Determine verification status
            $is_verified = $is_valid === true;
            
            error_log('[RTR] Email verification - is_valid: ' . ($is_valid ? 'true' : 'false') . ', quality: ' . $quality);

            // Build standardized result
            $result_data = [
                'email' => $email,
                'is_valid' => $is_valid,
                'verified' => $is_verified,
                'quality' => $quality,
                'catch_all_status' => $catch_all,
                'esp' => $response_data['esp'] ?? null,
                'status' => $is_valid ? 'valid' : 'invalid'
            ];

            // Update prospect with verification result
            if ($prospect && isset($prospect['id'])) {
                $this->update_prospect_verification($prospect['id'], [
                    'email_verified' => $is_verified ? 1 : 0,
                    'email_verification_date' => current_time('mysql'),
                    'email_verification_result' => wp_json_encode($result_data)
                ]);
                error_log('[RTR] Updated prospect ' . $prospect['id'] . ' with verification result');
            }

            return [
                'success' => true,
                'verified' => $is_verified,
                'data' => $result_data
            ];

        } catch (\Exception $e) {
            error_log('[RTR] Email verification error: ' . $e->getMessage());
            error_log('[RTR] Stack trace: ' . $e->getTraceAsString());
            return [
                'success' => false,
                'message' => 'Verification failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Make proxy request via Make.com webhook.
     *
     * @param string $endpoint Endpoint identifier (advanced_search, find_email, verify_email)
     * @param array $payload Request payload
     * @param string $context Context for logging
     * @return array|null Response data or null on failure
     */
    private function make_proxy_request(string $endpoint, array $payload, string $context): ?array
    {
        // Map internal endpoint names to hyphenated query parameters
        $endpoint_map = [
            'advanced_search' => 'advanced-search',
            'find_email' => 'find-email',
            'verify_email' => 'verify-email'
        ];
        
        $query_param = $endpoint_map[$endpoint] ?? $endpoint;
        
        // Build webhook URL with endpoint query parameter
        $url = add_query_arg('ep', $query_param, $this->make_webhook_url);

        // Build request arguments
        $args = [
            'headers' => [
                'Accept' => '*/*',
                'Content-Type' => 'application/json'
            ],
            'body' => wp_json_encode($payload),
            'timeout' => 45,
            'sslverify' => true,
            'httpversion' => '1.1',
            'blocking' => true
        ];

        // Make request
        $start_time = microtime(true);
        $response = wp_remote_post($url, $args);
        $end_time = microtime(true);
        $duration = round(($end_time - $start_time) * 1000, 2);

        if (is_wp_error($response)) {
            error_log('WP_Error Occurred: YES');
            error_log('Error Code: ' . $response->get_error_code());
            error_log('Error Message: ' . $response->get_error_message());
            error_log('========================================');
            return null;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if (empty($body)) {
            error_log('ERROR: Empty response body');
            error_log('========================================');
            return null;
        }
        
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('JSON PARSE ERROR: ' . json_last_error_msg());
            error_log('Raw response body:');
            error_log(substr($body, 0, 1000));
            error_log('========================================');
            return null;
        }

        if ($status_code !== 200) {
            $error_message = $data['message'] ?? 'Unknown error';
            error_log("API Error - Status {$status_code}: {$error_message}");
            error_log('========================================');
            return null;
        }

        // Log success details based on endpoint
        if ($endpoint === 'advanced_search') {
            $contact_count = isset($data['data']) && is_array($data['data']) ? count($data['data']) : 0;
            error_log('Contacts Found: ' . $contact_count);
        } elseif ($endpoint === 'find_email') {
            error_log('Email Found: ' . ($data['email'] ?? 'none'));
        } elseif ($endpoint === 'verify_email') {
            error_log('Verification Status: ' . ($data['status'] ?? 'unknown'));
        }

        return $data;
    }

    /**
     * Find email by member_id (helper method for API call)
     * 
     * @param string $member_id A-Leads member ID (document_id)
     * @param string $first_name First name
     * @param string $last_name Last name
     * @param string $domain Company domain/website
     * @return array Result with email and confidence
     */
    public function find_email_by_member_id($member_id, $first_name, $last_name, $domain) {
        try {
            error_log('[RTR] Finding email for member_id: ' . $member_id);
            
            // Build payload matching A-Leads API structure
            $payload = [
                'data' => [
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'website' => $domain
                ]
            ];
            
            // Add document_id if we have member_id from advanced search
            if (!empty($member_id)) {
                $payload['data']['document_id'] = $member_id;
                error_log('[RTR] Including document_id for cached lookup');
            }
            
            error_log('[RTR] Find email payload: ' . print_r($payload, true));
            
            $api_response = $this->make_proxy_request(
                'find_email',
                $payload,
                "Find email for: {$first_name} {$last_name}"
            );
            
            if (!$api_response) {
                error_log('[RTR] Find email API request failed');
                return [];
            }
            
            error_log('[RTR] Find email raw response: ' . print_r($api_response, true));
            
            // Parse nested response structure from A-Leads
            // Response structure: message.data.response.{result, is_valid, quality, etc}
            $response_data = $api_response['message']['data']['response'] ?? 
                            $api_response['data']['response'] ?? 
                            $api_response['response'] ?? 
                            $api_response;
            
            error_log('[RTR] Parsed response data: ' . print_r($response_data, true));
            
            // Extract email from 'result' field
            $email = $response_data['result'] ?? null;
            
            if (empty($email)) {
                error_log('[RTR] No email found in response');
                return [];
            }
            
            // Build standardized result format
            $result = [
                'email' => $email,
                'is_valid' => $response_data['is_valid'] ?? null,
                'quality' => $response_data['quality'] ?? null,
                'confidence' => $response_data['quality'] ?? null, // Use quality as confidence
                'email_status' => $response_data['is_valid'] ? 'valid' : 'unknown',
                'catch_all' => $response_data['catch_all_status'] ?? false,
                'esp' => $response_data['esp'] ?? null
            ];
            
            error_log('[RTR] Parsed email result: ' . print_r($result, true));
            
            return $result;
            
        } catch (\Exception $e) {
            error_log('[RTR] Find email error: ' . $e->getMessage());
            error_log('[RTR] Stack trace: ' . $e->getTraceAsString());
            return [];
        }
    }

    private function get_prospect_by_visitor_id($visitor_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'rtr_prospects';
        
        $result = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE visitor_id = %d",
                $visitor_id
            ),
            ARRAY_A
        );
        
        if ($wpdb->last_error) {
            error_log('[RTR] Database error in get_prospect_by_visitor_id: ' . $wpdb->last_error);
            return null;
        }
        
        error_log('[RTR] get_prospect_by_visitor_id result: ' . print_r($result, true));
        
        return $result;
    }

    /**
     * Parse API response and extract contacts (WITHOUT emails - those come from find-email endpoint).
     *
     * @param array $data API response data
     * @return array Filtered array of contacts
     */
    private function parse_contacts(array $data): array
    {
        $contacts = [];

        if (!isset($data['data']) || !is_array($data['data'])) {
            return $contacts;
        }

        foreach ($data['data'] as $person) {
            // Only include if email_found is true (we'll fetch the actual email later)
            if (empty($person['email_found']) || $person['email_found'] !== true) {
                continue;
            }

            $contact = [
                'member_id' => $person['member_id'] ?? '',
                'name' => $this->build_full_name($person),
                'first_name' => $person['member_name_first'] ?? '',
                'last_name' => $person['member_name_last'] ?? '',
                'job_title' => $person['job_title'] ?? '',
                'linkedin' => $person['member_linkedin_url'] ?? '',
                'seniority' => $person['seniority'] ?? '',
                'company_name' => $person['company_name'] ?? '',
                'department' => $person['department'] ?? '',
                'domain' => $person['domain'] ?? '',
                'email_found' => true,
                'email' => null, // Email needs to be fetched via find-email endpoint
                'phone_available' => $person['phone_number_available'] ?? false
            ];

            // Only include if we have at least name
            if (!empty($contact['name'])) {
                $contacts[] = $contact;
            }
        }

        return $contacts;
    }

    /**
     * Build full name from person data.
     *
     * @param array $person Person data
     * @return string Full name
     */
    private function build_full_name(array $person): string
    {
        // A-Leads uses different field names
        $first = $person['member_name_first'] ?? $person['first_name'] ?? '';
        $last = $person['member_name_last'] ?? $person['last_name'] ?? '';
        $full = $person['member_full_name'] ?? '';

        // If full name is provided, use it
        if (!empty($full)) {
            return $full;
        }

        // Otherwise build from parts
        $parts = array_filter([$first, $last]);
        return implode(' ', $parts);
    }

    /**
     * Check if API key is configured.
     *
     * @return bool True if API key exists
     */
    public function is_configured(): bool
    {
        return !empty($this->api_key) && !empty($this->make_webhook_url);
    }

    /**
     * Get configuration status.
     *
     * @return array Configuration details
     */
    public function get_config_status(): array
    {
        return [
            'api_key_set' => !empty($this->api_key),
            'webhook_url_set' => !empty($this->make_webhook_url),
            'webhook_url' => $this->make_webhook_url ? substr($this->make_webhook_url, 0, 50) . '...' : 'Not set',
            'fully_configured' => $this->is_configured()
        ];
    }
}