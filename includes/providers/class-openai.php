<?php
/**
 * OpenAI Provider
 * 
 * @package WP_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPAIA_Provider_OpenAI implements WPAIA_Provider_Interface {
    
    /**
     * API endpoint
     */
    private const API_URL = 'https://api.openai.com/v1/chat/completions';
    
    /**
     * API key
     */
    private $api_key;
    
    /**
     * Constructor
     */
    public function __construct(string $api_key = '') {
        $this->api_key = $api_key ?: WP_AI_Assistant::get_option('api_key');
    }
    
    /**
     * Get provider name
     */
    public function get_name(): string {
        return 'OpenAI';
    }
    
    /**
     * Get available models
     */
    public function get_models(): array {
        return array(
            'gpt-4o' => 'GPT-4o',
            'gpt-4o-mini' => 'GPT-4o Mini',
            'gpt-4-turbo' => 'GPT-4 Turbo',
            'gpt-3.5-turbo' => 'GPT-3.5 Turbo',
        );
    }
    
    /**
     * Send chat completion request
     */
    public function chat(array $messages, string $model = 'gpt-4o-mini') {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', __('API key is not configured', 'wp-ai-assistant'));
        }
        
        $body = array(
            'model' => $model,
            'messages' => $messages,
            'temperature' => 0.7,
            'max_tokens' => 1024,
        );
        
        $response = wp_remote_post(self::API_URL, array(
            'timeout' => 60,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode($body),
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($status_code !== 200) {
            $error_message = $body['error']['message'] ?? __('Unknown error', 'wp-ai-assistant');
            return new WP_Error('api_error', $error_message, array('status' => $status_code));
        }
        
        if (!isset($body['choices'][0]['message']['content'])) {
            return new WP_Error('invalid_response', __('Invalid response from API', 'wp-ai-assistant'));
        }
        
        return array(
            'content' => $body['choices'][0]['message']['content'],
            'usage' => $body['usage'] ?? array(),
            'model' => $body['model'] ?? $model,
        );
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
        
        return $this->chat($messages, 'gpt-4o-mini');
    }
}
