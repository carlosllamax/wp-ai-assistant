<?php
/**
 * AI Provider Interface
 * 
 * @package WP_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

interface WPAIA_Provider_Interface {
    
    /**
     * Send chat completion request
     * 
     * @param array $messages Array of message objects with 'role' and 'content'
     * @param string $model Model identifier
     * @return array|WP_Error Response array or error
     */
    public function chat(array $messages, string $model);
    
    /**
     * Test the API connection
     * 
     * @return array|WP_Error Response array or error
     */
    public function test_connection();
    
    /**
     * Get provider name
     * 
     * @return string
     */
    public function get_name(): string;
    
    /**
     * Get available models
     * 
     * @return array
     */
    public function get_models(): array;
}
