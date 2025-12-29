<?php
/**
 * REST API Endpoints
 * 
 * @package WP_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPAIA_REST_API {
    
    /**
     * Namespace
     */
    private const NAMESPACE = 'wp-ai-assistant/v1';
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
        add_action('wp_ajax_wpaia_test_connection', array($this, 'ajax_test_connection'));
        add_action('wp_ajax_wpaia_chat', array($this, 'ajax_chat'));
        add_action('wp_ajax_nopriv_wpaia_chat', array($this, 'ajax_chat'));
    }
    
    /**
     * Register REST routes
     */
    public function register_routes() {
        register_rest_route(self::NAMESPACE, '/chat', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_chat'),
            'permission_callback' => '__return_true',
            'args' => array(
                'message' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'conversation_id' => array(
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ));
        
        register_rest_route(self::NAMESPACE, '/verify-order', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_verify_order'),
            'permission_callback' => '__return_true',
            'args' => array(
                'order_id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ),
                'email' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_email',
                ),
                'conversation_id' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ));
    }
    
    /**
     * Handle chat request
     */
    public function handle_chat(WP_REST_Request $request) {
        // Check if enabled
        if (!WP_AI_Assistant::get_instance()->is_enabled()) {
            return new WP_Error('disabled', __('AI Assistant is not enabled', 'wp-ai-assistant'), array('status' => 503));
        }
        
        // Rate limiting
        if (!$this->check_rate_limit()) {
            return new WP_Error('rate_limited', __('Too many requests. Please wait a moment.', 'wp-ai-assistant'), array('status' => 429));
        }
        
        $message = $request->get_param('message');
        $conversation_id = $request->get_param('conversation_id') ?: WPAIA_Conversation::get_id();
        
        // Get provider
        $provider = $this->get_provider();
        if (is_wp_error($provider)) {
            return $provider;
        }
        
        // Build messages
        $messages = $this->build_messages($message, $conversation_id);
        
        // Get model
        $model = WP_AI_Assistant::get_option('model', 'llama-3.3-70b-versatile');
        
        // Call AI
        $response = $provider->chat($messages, $model);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        // Save to history
        WPAIA_Conversation::add_message($conversation_id, 'user', $message);
        WPAIA_Conversation::add_message($conversation_id, 'assistant', $response['content']);
        
        return rest_ensure_response(array(
            'success' => true,
            'message' => $response['content'],
            'conversation_id' => $conversation_id,
        ));
    }
    
    /**
     * Handle order verification
     */
    public function handle_verify_order(WP_REST_Request $request) {
        if (!class_exists('WooCommerce')) {
            return new WP_Error('woo_not_installed', __('WooCommerce is not installed', 'wp-ai-assistant'), array('status' => 400));
        }
        
        $order_id = $request->get_param('order_id');
        $email = $request->get_param('email');
        $conversation_id = $request->get_param('conversation_id');
        
        // Try to get order context
        $order_context = WPAIA_Context_Builder::get_order_context($order_id, $email);
        
        if (is_null($order_context)) {
            return new WP_Error('verification_failed', __('Could not verify order. Please check your order number and email.', 'wp-ai-assistant'), array('status' => 401));
        }
        
        // Set as verified
        WPAIA_Conversation::set_verified($conversation_id, $order_id, $email);
        
        return rest_ensure_response(array(
            'success' => true,
            'message' => __('Order verified successfully!', 'wp-ai-assistant'),
            'order_info' => $order_context,
        ));
    }
    
    /**
     * AJAX handler for chat (fallback)
     */
    public function ajax_chat() {
        check_ajax_referer('wpaia_chat_nonce', 'nonce');
        
        $message = sanitize_text_field($_POST['message'] ?? '');
        $conversation_id = sanitize_text_field($_POST['conversation_id'] ?? '');
        
        if (empty($message)) {
            wp_send_json_error(array('message' => __('Message is required', 'wp-ai-assistant')));
        }
        
        // Create fake REST request
        $request = new WP_REST_Request('POST');
        $request->set_param('message', $message);
        $request->set_param('conversation_id', $conversation_id);
        
        $response = $this->handle_chat($request);
        
        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()));
        }
        
        wp_send_json_success($response->get_data());
    }
    
    /**
     * AJAX handler for testing connection
     */
    public function ajax_test_connection() {
        check_ajax_referer('wpaia_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'wp-ai-assistant')));
        }
        
        $provider_name = sanitize_text_field($_POST['provider'] ?? 'groq');
        $api_key = sanitize_text_field($_POST['api_key'] ?? '');
        
        $provider = $this->get_provider($provider_name, $api_key);
        
        if (is_wp_error($provider)) {
            wp_send_json_error(array('message' => $provider->get_error_message()));
        }
        
        $result = $provider->test_connection();
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success(array('message' => __('Connection successful!', 'wp-ai-assistant')));
    }
    
    /**
     * Build messages array for API
     */
    private function build_messages(string $user_message, string $conversation_id): array {
        $messages = array();
        
        // System prompt with context
        $system_prompt = WPAIA_Context_Builder::get_system_prompt();
        $context = WPAIA_Context_Builder::build();
        
        // Add order context if verified
        if (WP_AI_Assistant::get_option('enable_order_lookup')) {
            $verified = WPAIA_Conversation::get_verified_order($conversation_id);
            if ($verified) {
                $order_context = WPAIA_Context_Builder::get_order_context($verified['order_id'], $verified['email']);
                if ($order_context) {
                    $context .= "\n\n" . $order_context;
                }
            }
        }
        
        $messages[] = array(
            'role' => 'system',
            'content' => $system_prompt . "\n\n## Context:\n" . $context,
        );
        
        // Add conversation history
        $history = WPAIA_Conversation::get_history($conversation_id);
        $formatted_history = WPAIA_Conversation::format_for_api($history);
        $messages = array_merge($messages, $formatted_history);
        
        // Add current user message
        $messages[] = array(
            'role' => 'user',
            'content' => $user_message,
        );
        
        return $messages;
    }
    
    /**
     * Get AI provider instance
     */
    private function get_provider(string $provider_name = '', string $api_key = '') {
        if (empty($provider_name)) {
            $provider_name = WP_AI_Assistant::get_option('provider', 'groq');
        }
        if (empty($api_key)) {
            $api_key = WP_AI_Assistant::get_option('api_key');
        }
        
        switch ($provider_name) {
            case 'groq':
                return new WPAIA_Provider_Groq($api_key);
            case 'openai':
                return new WPAIA_Provider_OpenAI($api_key);
            default:
                return new WP_Error('invalid_provider', __('Invalid AI provider', 'wp-ai-assistant'));
        }
    }
    
    /**
     * Check rate limit
     */
    private function check_rate_limit(): bool {
        $rate_limit = WP_AI_Assistant::get_option('rate_limit', 20);
        $ip = $this->get_client_ip();
        $key = 'wpaia_rate_' . md5($ip);
        
        $count = get_transient($key);
        
        if ($count === false) {
            set_transient($key, 1, MINUTE_IN_SECONDS);
            return true;
        }
        
        if ($count >= $rate_limit) {
            return false;
        }
        
        set_transient($key, $count + 1, MINUTE_IN_SECONDS);
        return true;
    }
    
    /**
     * Get client IP
     */
    private function get_client_ip(): string {
        $ip_keys = array('HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = explode(',', $_SERVER[$key])[0];
                return trim($ip);
            }
        }
        
        return '127.0.0.1';
    }
}
