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
     * Find email for a specific person.
     *
     * @param string $member_id Member ID from search results
     * @param string $first_name First name
     * @param string $last_name Last name  
     * @param string $company_domain Company domain
     * @return array Email data or empty array on failure
     */
    public function find_email(string $member_id, string $first_name, string $last_name, string $company_domain): array
    {
        if (empty($this->make_webhook_url)) {
            error_log('A-Leads API Error: Make.com webhook URL not configured');
            return [];
        }

        $payload = [
            'member_id' => $member_id,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'company_domain' => $company_domain
        ];

        $context = "member_id: {$member_id}, name: {$first_name} {$last_name}, domain: {$company_domain}";
        $response_data = $this->make_proxy_request('find_email', $payload, $context);

        if (empty($response_data) || !isset($response_data['email'])) {
            error_log('A-Leads Find Email: No email found for ' . $context);
            return [];
        }

        return [
            'email' => $response_data['email'] ?? '',
            'email_status' => $response_data['email_status'] ?? '',
            'confidence' => $response_data['confidence'] ?? ''
        ];
    }

    /**
     * Verify email address.
     *
     * @param string $email Email to verify
     * @return array Verification data or empty array on failure
     */
    public function verify_email(string $email): array
    {
        if (empty($this->make_webhook_url)) {
            error_log('A-Leads API Error: Make.com webhook URL not configured');
            return [];
        }

        if (!is_email($email)) {
            error_log('A-Leads API Error: Invalid email format: ' . $email);
            return [];
        }

        $payload = ['email' => $email];

        $response_data = $this->make_proxy_request('verify_email', $payload, $email);

        return $response_data ?? [];
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

        // ===== DEBUG LOGGING =====
        error_log('========================================');
        error_log('A-LEADS API REQUEST (via Make.com Proxy)');
        error_log('========================================');
        error_log('Timestamp: ' . current_time('mysql'));
        error_log('Endpoint: ' . $endpoint);
        error_log('Original A-Leads URL: ' . ($this->endpoints[$endpoint] ?? 'unknown'));
        error_log('Make.com Webhook URL: ' . $url);
        error_log('Context: ' . $context);
        error_log('----------------------------------------');
        error_log('REQUEST BODY:');
        error_log($args['body']);
        error_log('========================================');

        // Make request
        $start_time = microtime(true);
        $response = wp_remote_post($url, $args);
        $end_time = microtime(true);
        $duration = round(($end_time - $start_time) * 1000, 2);

        // ===== RESPONSE DEBUG LOGGING =====
        error_log('========================================');
        error_log('A-LEADS API RESPONSE (via Make.com)');
        error_log('========================================');
        error_log('Request Duration: ' . $duration . ' ms');
        error_log('----------------------------------------');

        if (is_wp_error($response)) {
            error_log('WP_Error Occurred: YES');
            error_log('Error Code: ' . $response->get_error_code());
            error_log('Error Message: ' . $response->get_error_message());
            error_log('========================================');
            return null;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        error_log('HTTP Status Code: ' . $status_code);
        error_log('RESPONSE BODY LENGTH: ' . strlen($body) . ' bytes');
        
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

        error_log('JSON Parse: Success');
        
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
        
        error_log('========================================');

        return $data;
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