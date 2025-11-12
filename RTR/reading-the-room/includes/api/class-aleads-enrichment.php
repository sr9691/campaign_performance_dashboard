<?php
/**
 * A-Leads Enrichment API Integration
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

    /** @var string */
    private $api_url = 'https://api.a-leads.co/gateway/v1/search/advanced-search';

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->api_key = get_option('rtr_aleads_api_key', '');
    }

    /**
     * Search for contacts at a company.
     *
     * @param string $company_name Company name to search
     * @return array Array of contacts (empty array on failure)
     */
    public function search_contacts(string $company_name): array
    {
        if (empty($this->api_key)) {
            error_log('A-Leads API Error: API key not configured');
            return [];
        }

        if (empty($company_name)) {
            error_log('A-Leads API Error: Company name is required');
            return [];
        }

        // Build API request payload using correct A-Leads structure
        $payload = [
            'advanced_filters' => [
                'organizations' => [$company_name]
            ],
            'current_page' => 1,
            'search_type' => 'new'
        ];

        // Debug logging
        error_log('A-Leads API Request - Company: ' . $company_name);
        error_log('A-Leads API Request - Payload: ' . wp_json_encode($payload));

        // Make API request
        $response = wp_remote_post($this->api_url, [
            'headers' => [
                'accept' => 'application/json',
                'content-type' => 'application/json',
                'x-api-key' => $this->api_key
            ],
            'body' => wp_json_encode($payload),
            'timeout' => 45,
            'sslverify' => true
        ]);

        // Check for WordPress errors (timeout, connection failed, etc)
        if (is_wp_error($response)) {
            error_log('A-Leads API Error: ' . $response->get_error_message());
            return;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if (empty($body)) {
            error_log('A-Leads API Error: Empty response body');
            return [];
        }
        
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('A-Leads API Error: Invalid JSON response - ' . json_last_error_msg());
            return [];
        }

        // Handle API errors
        if ($status_code !== 200) {
            $error_message = $data['message'] ?? 'Unknown error from enrichment service';
            error_log("A-Leads API returned status {$status_code}: {$error_message}");
            return [];
        }

        // Parse and filter results
        return $this->parse_contacts($data);
    }

    /**
     * Parse API response and extract contacts with valid emails.
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
            // Skip if no valid business email
            if (empty($person['email']) || !$this->is_valid_business_email($person['email'])) {
                continue;
            }

            $contact = [
                'name' => $this->build_full_name($person),
                'email' => sanitize_email($person['email']),
                'job_title' => $person['job_title'] ?? '',
                'linkedin' => $person['linkedin_url'] ?? '',
                'seniority' => $person['seniority'] ?? '',
                'company_name' => $person['company_name'] ?? '',
                'department' => $person['department'] ?? ''
            ];

            // Only include if we have at least name and email
            if (!empty($contact['name']) && !empty($contact['email'])) {
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
        $parts = [];

        if (!empty($person['first_name'])) {
            $parts[] = $person['first_name'];
        }

        if (!empty($person['middle_name'])) {
            $parts[] = $person['middle_name'];
        }

        if (!empty($person['last_name'])) {
            $parts[] = $person['last_name'];
        }

        return implode(' ', $parts);
    }

    /**
     * Validate if email is a business email.
     *
     * @param string $email Email to validate
     * @return bool True if valid business email
     */
    private function is_valid_business_email(string $email): bool
    {
        if (!is_email($email)) {
            return false;
        }

        // List of free email providers to exclude
        $free_domains = [
            'gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com',
            'aol.com', 'icloud.com', 'mail.com', 'protonmail.com',
            'zoho.com', 'yandex.com', 'gmx.com', 'inbox.com'
        ];

        $domain = strtolower(substr(strrchr($email, "@"), 1));

        return !in_array($domain, $free_domains, true);
    }

    /**
     * Check if API key is configured.
     *
     * @return bool True if API key exists
     */
    public function is_configured(): bool
    {
        return !empty($this->api_key);
    }
}