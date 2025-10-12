<?php
/**
 * AI Email Generator
 *
 * Integrates with Google Gemini API to generate personalized emails
 * based on prompt templates and visitor context.
 *
 * @package DirectReach
 * @subpackage RTR
 * @since 2.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CPD_AI_Email_Generator {

    /**
     * Settings manager instance
     *
     * @var CPD_AI_Settings_Manager
     */
    private $settings;

    /**
     * Rate limiter instance
     *
     * @var CPD_AI_Rate_Limiter
     */
    private $rate_limiter;

    /**
     * Template resolver instance
     *
     * @var Template_Resolver
     */
    private $resolver;

    /**
     * Last generation metadata
     *
     * @var array
     */
    private $last_generation_meta = array();

    /**
     * Constructor
     */
    public function __construct() {
        $this->settings = new CPD_AI_Settings_Manager();
        $this->rate_limiter = new CPD_AI_Rate_Limiter();
        $this->resolver = new Template_Resolver();
    }

    /**
     * Generate email for prospect
     *
     * @param int    $prospect_id Prospect ID
     * @param int    $campaign_id Campaign ID
     * @param string $room_type Room type
     * @param int    $email_number Email sequence number
     * @return array|WP_Error Generation result or error
     */
    public function generate_email( $prospect_id, $campaign_id, $room_type, $email_number ) {
        // Check if AI is enabled
        if ( ! $this->settings->is_enabled() ) {
            error_log( '[DirectReach] AI generation disabled, using fallback' );
            return $this->fallback_to_template( $prospect_id, $campaign_id, $room_type );
        }

        // Check rate limit
        $rate_limit_check = $this->rate_limiter->check_limit();
        if ( is_wp_error( $rate_limit_check ) ) {
            error_log( '[DirectReach] Rate limit exceeded: ' . $rate_limit_check->get_error_message() );
            return $rate_limit_check;
        }

        // Load prospect data
        $prospect = $this->load_prospect_data( $prospect_id );
        if ( is_wp_error( $prospect ) ) {
            return $prospect;
        }

        // Load available templates
        $templates = $this->resolver->get_available_templates( $campaign_id, $room_type );
        if ( empty( $templates ) ) {
            return new WP_Error(
                'no_templates',
                'No templates available for this room',
                array( 'status' => 400 )
            );
        }

        // Select best template based on visitor behavior
        $selected_template = $this->select_template( $templates, $prospect );

        // Load content links
        $content_links = $this->load_content_links( $campaign_id, $room_type );

        // Build generation payload
        $payload = $selected_template->build_generation_payload(
            $prospect,
            $content_links
        );

        // Generate email via Gemini API
        $generation_start = microtime( true );
        $result = $this->call_gemini_api( $payload );
        $generation_time = ( microtime( true ) - $generation_start ) * 1000;

        if ( is_wp_error( $result ) ) {
            error_log( '[DirectReach] AI generation failed: ' . $result->get_error_message() );
            
            // Fallback to template
            return $this->fallback_to_template( $prospect_id, $campaign_id, $room_type );
        }

        // Increment rate limiter
        $this->rate_limiter->increment();

        // Store generation metadata
        $this->last_generation_meta = array(
            'generation_time_ms' => round( $generation_time, 2 ),
            'template_id' => $selected_template->get_id(),
            'template_name' => $selected_template->get_name(),
            'is_global' => $selected_template->is_global(),
            'prompt_tokens' => $result['usage']['prompt_tokens'] ?? 0,
            'completion_tokens' => $result['usage']['completion_tokens'] ?? 0,
            'total_tokens' => $result['usage']['total_tokens'] ?? 0,
            'cost' => $this->calculate_cost( $result['usage'] ?? array() ),
        );

        // Format response
        return array(
            'success' => true,
            'subject' => $result['subject'],
            'body_html' => $result['body_html'],
            'body_text' => $result['body_text'],
            'selected_url' => $result['selected_url'],
            'template_used' => array(
                'id' => $selected_template->get_id(),
                'name' => $selected_template->get_name(),
                'is_global' => $selected_template->is_global(),
            ),
            'tokens_used' => array(
                'prompt' => $this->last_generation_meta['prompt_tokens'],
                'completion' => $this->last_generation_meta['completion_tokens'],
                'total' => $this->last_generation_meta['total_tokens'],
                'cost' => $this->last_generation_meta['cost'],
            ),
            'generation_time_ms' => $this->last_generation_meta['generation_time_ms'],
        );
    }

    /**
     * Call Gemini API with generation payload
     *
     * @param array $payload Generation payload
     * @return array|WP_Error API response or error
     */
    private function call_gemini_api( $payload ) {
        $api_key = $this->settings->get_api_key();
        if ( empty( $api_key ) ) {
            return new WP_Error( 'no_api_key', 'Gemini API key not configured' );
        }

        $model = $this->settings->get_model();
        $endpoint = sprintf(
            'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
            $model,
            $api_key
        );

        // Build comprehensive prompt
        $prompt = $this->build_complete_prompt( $payload );

        // Prepare request body
        $body = array(
            'contents' => array(
                array(
                    'parts' => array(
                        array( 'text' => $prompt )
                    )
                )
            ),
            'generationConfig' => array(
                'temperature' => $this->settings->get_temperature(),
                'maxOutputTokens' => $this->settings->get_max_tokens(),
                'topP' => 0.8,
                'topK' => 40,
            ),
        );

        // Make API request
        $response = wp_remote_post( $endpoint, array(
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode( $body ),
        ));

        if ( is_wp_error( $response ) ) {
            return new WP_Error(
                'api_request_failed',
                'Failed to connect to Gemini API: ' . $response->get_error_message()
            );
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );

        if ( $status_code !== 200 ) {
            error_log( sprintf(
                '[DirectReach] Gemini API error (status %d): %s',
                $status_code,
                $response_body
            ));

            return new WP_Error(
                'api_error',
                sprintf( 'Gemini API returned status %d', $status_code ),
                array( 'status' => $status_code, 'body' => $response_body )
            );
        }

        $data = json_decode( $response_body, true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return new WP_Error(
                'invalid_response',
                'Invalid JSON response from Gemini API'
            );
        }

        // Parse response
        return $this->parse_gemini_response( $data, $payload );
    }

    /**
     * Build complete prompt for Gemini
     *
     * @param array $payload Generation payload
     * @return string Complete prompt
     */
    private function build_complete_prompt( $payload ) {
        $sections = array();

        // Add template prompt
        $sections[] = "=== EMAIL GENERATION INSTRUCTIONS ===\n" . $payload['prompt_template'];

        // Add visitor context
        $sections[] = "=== VISITOR INFORMATION ===\n" . $this->format_visitor_context( $payload['visitor_info'] );

        // Add available URLs
        if ( ! empty( $payload['available_urls'] ) ) {
            $sections[] = "=== AVAILABLE CONTENT LINKS ===\n" . $this->format_urls_context( $payload['available_urls'] );
        }

        // Add output format instructions
        $sections[] = $this->get_output_format_instructions();

        return implode( "\n\n", $sections );
    }

    /**
     * Format visitor context for prompt
     *
     * @param array $visitor_info Visitor information
     * @return string Formatted context
     */
    private function format_visitor_context( $visitor_info ) {
        $lines = array();

        if ( ! empty( $visitor_info['company_name'] ) ) {
            $lines[] = "Company: {$visitor_info['company_name']}";
        }

        if ( ! empty( $visitor_info['contact_name'] ) ) {
            $lines[] = "Contact: {$visitor_info['contact_name']}";
        }

        if ( ! empty( $visitor_info['job_title'] ) ) {
            $lines[] = "Title: {$visitor_info['job_title']}";
        }

        $lines[] = "Current Stage: {$visitor_info['current_room']}";
        $lines[] = "Lead Score: {$visitor_info['lead_score']}";
        $lines[] = "Days in Current Stage: {$visitor_info['days_in_room']}";
        $lines[] = "Email Sequence Position: {$visitor_info['email_sequence_position']}";

        if ( ! empty( $visitor_info['recent_pages'] ) ) {
            $lines[] = "\nRecent Pages Visited:";
            foreach ( array_slice( $visitor_info['recent_pages'], 0, 5 ) as $page ) {
                $lines[] = "- {$page['url']} (" . ($page['intent'] ?? 'unknown intent') . ")";
            }
        }

        return implode( "\n", $lines );
    }

    /**
     * Format URLs context for prompt
     *
     * @param array $urls Available URLs
     * @return string Formatted URLs
     */
    private function format_urls_context( $urls ) {
        $lines = array();
        $lines[] = "Select ONE of the following content links to include in the email:";
        $lines[] = "";

        foreach ( $urls as $index => $url ) {
            $lines[] = sprintf( "[%d] %s", $index + 1, $url['title'] );
            $lines[] = "    URL: {$url['url']}";
            
            if ( ! empty( $url['summary'] ) ) {
                $lines[] = "    About: {$url['summary']}";
            }
            
            $lines[] = "";
        }

        return implode( "\n", $lines );
    }

    /**
     * Get output format instructions
     *
     * @return string Format instructions
     */
    private function get_output_format_instructions() {
        return <<<INSTRUCTIONS
=== OUTPUT FORMAT ===

You must respond with ONLY a valid JSON object. Do not include any text before or after the JSON.
DO NOT wrap the JSON in markdown code blocks or backticks.

Required JSON structure:
{
    "subject": "email subject line here",
    "body_html": "<p>HTML formatted email body here</p>",
    "body_text": "Plain text version of email here",
    "selected_url_index": 1,
    "reasoning": "Brief explanation of why you selected this URL"
}

CRITICAL REQUIREMENTS:
- selected_url_index must be a number matching one of the available content links (1-based)
- body_html must use proper HTML tags (<p>, <strong>, <em>, etc.)
- body_text must be plain text only, no HTML
- subject should be compelling and personalized
- DO NOT include any text outside the JSON structure
- DO NOT use markdown code fences (```)
INSTRUCTIONS;
    }

    /**
     * Parse Gemini API response
     *
     * @param array $data API response data
     * @param array $payload Original payload for URL lookup
     * @return array|WP_Error Parsed response or error
     */
    private function parse_gemini_response( $data, $payload ) {
        // Extract text from response
        if ( empty( $data['candidates'][0]['content']['parts'][0]['text'] ) ) {
            return new WP_Error( 'empty_response', 'Empty response from Gemini API' );
        }

        $response_text = $data['candidates'][0]['content']['parts'][0]['text'];

        // Remove markdown code fences if present
        $response_text = preg_replace( '/^```json\s*/m', '', $response_text );
        $response_text = preg_replace( '/^```\s*/m', '', $response_text );
        $response_text = trim( $response_text );

        // Parse JSON
        $parsed = json_decode( $response_text, true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            error_log( '[DirectReach] Failed to parse Gemini JSON response: ' . $response_text );
            return new WP_Error(
                'invalid_json_response',
                'Could not parse JSON from Gemini response: ' . json_last_error_msg()
            );
        }

        // Validate required fields
        $required = array( 'subject', 'body_html', 'body_text', 'selected_url_index' );
        foreach ( $required as $field ) {
            if ( empty( $parsed[ $field ] ) ) {
                return new WP_Error(
                    'missing_field',
                    sprintf( 'Missing required field in response: %s', $field )
                );
            }
        }

        // Lookup selected URL
        $url_index = (int) $parsed['selected_url_index'] - 1; // Convert to 0-based
        if ( ! isset( $payload['available_urls'][ $url_index ] ) ) {
            error_log( sprintf(
                '[DirectReach] Invalid URL index %d, defaulting to first URL',
                $url_index
            ));
            $url_index = 0;
        }

        $selected_url = $payload['available_urls'][ $url_index ] ?? null;

        // Get token usage
        $usage = array(
            'prompt_tokens' => $data['usageMetadata']['promptTokenCount'] ?? 0,
            'completion_tokens' => $data['usageMetadata']['candidatesTokenCount'] ?? 0,
            'total_tokens' => $data['usageMetadata']['totalTokenCount'] ?? 0,
        );

        return array(
            'subject' => sanitize_text_field( $parsed['subject'] ),
            'body_html' => wp_kses_post( $parsed['body_html'] ),
            'body_text' => sanitize_textarea_field( $parsed['body_text'] ),
            'selected_url' => $selected_url,
            'reasoning' => isset( $parsed['reasoning'] ) ? sanitize_text_field( $parsed['reasoning'] ) : '',
            'usage' => $usage,
        );
    }

    /**
     * Select best template based on visitor behavior
     *
     * For now, returns first template. Future enhancement: intelligent selection.
     *
     * @param array $templates Available templates
     * @param array $prospect Prospect data
     * @return CPD_Prompt_Template Selected template
     */
    private function select_template( $templates, $prospect ) {
        // Simple selection: use first template
        // TODO: Implement intelligent selection based on:
        // - Lead score
        // - Recent page visits
        // - Email sequence position
        // - Days in room
        
        return $templates[0];
    }

    /**
     * Fallback to template-based email
     *
     * @param int    $prospect_id Prospect ID
     * @param int    $campaign_id Campaign ID
     * @param string $room_type Room type
     * @return array Template-based email
     */
    private function fallback_to_template( $prospect_id, $campaign_id, $room_type ) {
        // Load prospect
        $prospect = $this->load_prospect_data( $prospect_id );
        if ( is_wp_error( $prospect ) ) {
            return $prospect;
        }

        // Load templates
        $templates = $this->resolver->get_available_templates( $campaign_id, $room_type );
        if ( empty( $templates ) ) {
            return new WP_Error( 'no_templates', 'No templates available' );
        }

        $template = $templates[0];

        // Load content links
        $content_links = $this->load_content_links( $campaign_id, $room_type );
        
        // Get sent URLs
        $sent_urls = array();
        if ( ! empty( $prospect['urls_sent'] ) ) {
            $sent_urls = json_decode( $prospect['urls_sent'], true ) ?: array();
        }

        // Select first unsent URL
        $selected_url = null;
        foreach ( $content_links as $link ) {
            if ( ! in_array( $link['link_url'], $sent_urls, true ) ) {
                $selected_url = array(
                    'id' => $link['id'],
                    'title' => $link['link_title'],
                    'url' => $link['link_url'],
                    'description' => $link['link_description'] ?? '',
                );
                break;
            }
        }

        // Build simple email from template
        $subject = "Follow up: {$prospect['company_name']}";
        $body_html = "<p>Hi {$prospect['contact_name']},</p>";
        $body_html .= "<p>I noticed you've been exploring our {$room_type} solutions.</p>";
        
        if ( $selected_url ) {
            $body_html .= "<p>I thought you might find this resource helpful: <a href=\"{$selected_url['url']}\">{$selected_url['title']}</a></p>";
        }
        
        $body_html .= "<p>Let me know if you have any questions.</p>";
        
        $body_text = wp_strip_all_tags( $body_html );

        return array(
            'success' => true,
            'fallback' => true,
            'subject' => $subject,
            'body_html' => $body_html,
            'body_text' => $body_text,
            'selected_url' => $selected_url,
            'template_used' => array(
                'id' => $template->get_id(),
                'name' => $template->get_name(),
                'is_global' => $template->is_global(),
            ),
            'tokens_used' => array(
                'prompt' => 0,
                'completion' => 0,
                'total' => 0,
                'cost' => 0,
            ),
        );
    }

    /**
     * Load prospect data
     *
     * @param int $prospect_id Prospect ID
     * @return array|WP_Error Prospect data or error
     */
    private function load_prospect_data( $prospect_id ) {
        global $wpdb;

        $table = $wpdb->prefix . 'rtr_prospects';
        $prospect = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $prospect_id ),
            ARRAY_A
        );

        if ( ! $prospect ) {
            return new WP_Error( 'prospect_not_found', 'Prospect not found' );
        }

        return $prospect;
    }

    /**
     * Load content links
     *
     * @param int    $campaign_id Campaign ID
     * @param string $room_type Room type
     * @return array Content links
     */
    private function load_content_links( $campaign_id, $room_type ) {
        global $wpdb;

        $table = $wpdb->prefix . 'rtr_room_content_links';
        $links = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} 
                WHERE campaign_id = %d 
                AND room_type = %s 
                AND is_active = 1
                ORDER BY link_order ASC",
                $campaign_id,
                $room_type
            ),
            ARRAY_A
        );

        return $links ?: array();
    }

    /**
     * Calculate cost based on token usage
     *
     * Gemini 1.5 Pro pricing (as of Oct 2024):
     * - Input: $0.00125 / 1K tokens
     * - Output: $0.005 / 1K tokens
     *
     * @param array $usage Token usage
     * @return float Cost in USD
     */
    private function calculate_cost( $usage ) {
        $prompt_tokens = $usage['prompt_tokens'] ?? 0;
        $completion_tokens = $usage['completion_tokens'] ?? 0;

        $input_cost = ( $prompt_tokens / 1000 ) * 0.00125;
        $output_cost = ( $completion_tokens / 1000 ) * 0.005;

        return round( $input_cost + $output_cost, 6 );
    }

    /**
     * Get last generation metadata
     *
     * @return array Metadata
     */
    public function get_last_generation_meta() {
        return $this->last_generation_meta;
    }
}