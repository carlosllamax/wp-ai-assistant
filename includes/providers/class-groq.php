<?php
/**
 * Groq AI Provider
 * 
 * @package WP_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPAIA_Provider_Groq implements WPAIA_Provider_Interface {
    
    /**
     * API endpoint
     */
    private const API_URL = 'https://api.groq.com/openai/v1/chat/completions';
    
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
        return 'Groq';
    }
    
    /**
     * Get available models
     */
    public function get_models(): array {
        return array(
            'llama-3.3-70b-versatile' => 'Llama 3.3 70B Versatile',
            'llama-3.1-70b-versatile' => 'Llama 3.1 70B Versatile',
            'llama-3.1-8b-instant' => 'Llama 3.1 8B Instant',
            'mixtral-8x7b-32768' => 'Mixtral 8x7B',
            'gemma2-9b-it' => 'Gemma 2 9B',
        );
    }
    
    /**
     * Send chat completion request
     */
    public function chat(array $messages, string $model = 'llama-3.3-70b-versatile') {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', __('API key is not configured', 'wp-ai-assistant'));
        }
        
        $body = array(
            'model' => $model,
            'messages' => $messages,
            'temperature' => 0.7,
            'max_tokens' => 1024,
            'top_p' => 1,
            'stream' => false,
        );
        
        $response = wp_remote_post(self::API_URL, array(
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode($body),
        ));
        
        if (is_wp_error($response)) {
            WPAIA_Logger::api_error('Groq', $response->get_error_message(), array('model' => $model));
            return $response;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($status_code !== 200) {
            $error_message = $body['error']['message'] ?? __('Unknown error', 'wp-ai-assistant');
            WPAIA_Logger::api_error('Groq', $error_message, array(
                'status_code' => $status_code,
                'model' => $model,
                'response' => $body,
            ));
            return new WP_Error('api_error', $error_message, array('status' => $status_code));
        }
        
        if (!isset($body['choices'][0]['message']['content'])) {
            WPAIA_Logger::api_error('Groq', 'Invalid response structure', array('response' => $body));
            return new WP_Error('invalid_response', __('Invalid response from API', 'wp-ai-assistant'));
        }
        
        // Log successful request for debugging
        WPAIA_Logger::debug('Groq API request successful', array(
            'model' => $body['model'] ?? $model,
            'tokens' => $body['usage'] ?? array(),
        ));
        
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
        
        return $this->chat($messages, 'llama-3.1-8b-instant');
    }
}
