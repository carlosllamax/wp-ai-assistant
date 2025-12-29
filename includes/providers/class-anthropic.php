<?php
/**
 * Anthropic Claude AI Provider
 * 
 * @package WP_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPAIA_Provider_Anthropic implements WPAIA_Provider_Interface {
    
    /**
     * API endpoint
     */
    private const API_URL = 'https://api.anthropic.com/v1/messages';
    
    /**
     * API version
     */
    private const API_VERSION = '2023-06-01';
    
    /**
     * API key
     */
    private $api_key;
    
    /**
     * Constructor
     */
    public function __construct(string $api_key = '') {
        $this->api_key = $api_key ?: WP_AI_Assistant::get_option('anthropic_api_key');
    }
    
    /**
     * Get provider name
     */
    public function get_name(): string {
        return 'Anthropic';
    }
    
    /**
     * Get available models
     */
    public function get_models(): array {
        return array(
            'claude-3-5-sonnet-20241022' => 'Claude 3.5 Sonnet (Latest)',
            'claude-3-5-haiku-20241022' => 'Claude 3.5 Haiku (Fast)',
            'claude-3-opus-20240229' => 'Claude 3 Opus (Most Capable)',
            'claude-3-sonnet-20240229' => 'Claude 3 Sonnet',
            'claude-3-haiku-20240307' => 'Claude 3 Haiku (Fastest)',
        );
    }
    
    /**
     * Send chat completion request
     * 
     * Note: Anthropic API uses a different format than OpenAI
     * - System messages are passed separately
     * - User/assistant messages alternate
     */
    public function chat(array $messages, string $model = 'claude-3-5-sonnet-20241022') {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', __('Anthropic API key is not configured', 'wp-ai-assistant'));
        }
        
        // Extract system message if present
        $system = '';
        $formatted_messages = array();
        
        foreach ($messages as $msg) {
            if ($msg['role'] === 'system') {
                $system .= ($system ? "\n\n" : '') . $msg['content'];
            } else {
                // Anthropic requires alternating user/assistant
                $formatted_messages[] = array(
                    'role' => $msg['role'],
                    'content' => $msg['content'],
                );
            }
        }
        
        // Ensure messages start with user and alternate properly
        $formatted_messages = $this->normalize_messages($formatted_messages);
        
        $body = array(
            'model' => $model,
            'max_tokens' => 1024,
            'messages' => $formatted_messages,
        );
        
        // Add system message if present
        if (!empty($system)) {
            $body['system'] = $system;
        }
        
        $response = wp_remote_post(self::API_URL, array(
            'timeout' => 60, // Claude can take longer
            'headers' => array(
                'x-api-key' => $this->api_key,
                'anthropic-version' => self::API_VERSION,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode($body),
        ));
        
        if (is_wp_error($response)) {
            WPAIA_Logger::api_error('Anthropic', $response->get_error_message(), array('model' => $model));
            return $response;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($status_code !== 200) {
            $error_message = $body['error']['message'] ?? __('Unknown error', 'wp-ai-assistant');
            WPAIA_Logger::api_error('Anthropic', $error_message, array(
                'status_code' => $status_code,
                'model' => $model,
                'error_type' => $body['error']['type'] ?? 'unknown',
            ));
            return new WP_Error('api_error', $error_message, array('status' => $status_code));
        }
        
        if (!isset($body['content'][0]['text'])) {
            WPAIA_Logger::api_error('Anthropic', 'Invalid response structure', array('response' => $body));
            return new WP_Error('invalid_response', __('Invalid response from API', 'wp-ai-assistant'));
        }
        
        // Calculate token usage for compatibility
        $input_tokens = $body['usage']['input_tokens'] ?? 0;
        $output_tokens = $body['usage']['output_tokens'] ?? 0;
        
        // Log successful request
        WPAIA_Logger::debug('Anthropic API request successful', array(
            'model' => $body['model'] ?? $model,
            'input_tokens' => $input_tokens,
            'output_tokens' => $output_tokens,
            'stop_reason' => $body['stop_reason'] ?? 'unknown',
        ));
        
        return array(
            'content' => $body['content'][0]['text'],
            'usage' => array(
                'prompt_tokens' => $input_tokens,
                'completion_tokens' => $output_tokens,
                'total_tokens' => $input_tokens + $output_tokens,
            ),
            'model' => $body['model'] ?? $model,
            'tokens' => $input_tokens + $output_tokens,
        );
    }
    
    /**
     * Normalize messages to ensure alternating user/assistant format
     * 
     * Anthropic requires:
     * - First message must be from user
     * - Messages must alternate between user and assistant
     */
    private function normalize_messages(array $messages): array {
        if (empty($messages)) {
            return array();
        }
        
        $normalized = array();
        $last_role = null;
        
        foreach ($messages as $msg) {
            // Skip if same role as last (Anthropic doesn't allow consecutive same roles)
            if ($msg['role'] === $last_role) {
                // Combine with previous message
                $last_index = count($normalized) - 1;
                if ($last_index >= 0) {
                    $normalized[$last_index]['content'] .= "\n\n" . $msg['content'];
                }
                continue;
            }
            
            // First message must be user
            if (empty($normalized) && $msg['role'] !== 'user') {
                // Insert a placeholder user message
                $normalized[] = array(
                    'role' => 'user',
                    'content' => 'Continue the conversation.',
                );
            }
            
            $normalized[] = array(
                'role' => $msg['role'],
                'content' => $msg['content'],
            );
            $last_role = $msg['role'];
        }
        
        return $normalized;
    }
    
    /**
     * Test the API connection
     */
    public function test_connection() {
        $messages = array(
            array(
                'role' => 'user',
                'content' => 'Say "Connection successful!" in exactly those words.',
            ),
        );
        
        return $this->chat($messages, 'claude-3-5-haiku-20241022');
    }
}
