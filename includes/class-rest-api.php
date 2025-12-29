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
        add_action('wp_ajax_wpaia_save_lead', array($this, 'ajax_save_lead'));
        add_action('wp_ajax_nopriv_wpaia_save_lead', array($this, 'ajax_save_lead'));
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
                    'validate_callback' => function($value) {
                        if (empty($value)) {
                            return new WP_Error('empty_message', __('Message cannot be empty', 'wp-ai-assistant'));
                        }
                        if (mb_strlen($value) > 1000) {
                            return new WP_Error('message_too_long', __('Message is too long (max 1000 characters)', 'wp-ai-assistant'));
                        }
                        return true;
                    },
                ),
                'conversation_id' => array(
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => function($value) {
                        if (!empty($value) && !preg_match('/^[a-f0-9\-]{36}$/i', $value)) {
                            return new WP_Error('invalid_conversation_id', __('Invalid conversation ID format', 'wp-ai-assistant'));
                        }
                        return true;
                    },
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
        
        $message = $request->get_param('message');
        $conversation_id = $request->get_param('conversation_id') ?: WPAIA_Conversation::get_id();
        
        // Rate limiting (check both IP and conversation)
        if (!$this->check_rate_limit($conversation_id)) {
            return new WP_Error('rate_limited', __('Too many requests. Please wait a moment.', 'wp-ai-assistant'), array('status' => 429));
        }
        
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
        
        // Save to database (permanent storage)
        if (class_exists('WPAIA_Database') && WP_AI_Assistant::get_option('save_conversations', true)) {
            WPAIA_Database::save_message($conversation_id, 'user', $message);
            WPAIA_Database::save_message($conversation_id, 'assistant', $response['content'], $response['tokens'] ?? 0);
        }
        
        /**
         * Fires after a chat message exchange is completed
         * 
         * @param array $message_data Message exchange information including:
         *   - conversation_id: string
         *   - user_message: string
         *   - assistant_response: string
         *   - tokens_used: int
         */
        do_action('wpaia_message_sent', array(
            'conversation_id' => $conversation_id,
            'user_message' => $message,
            'assistant_response' => $response['content'],
            'tokens_used' => $response['tokens'] ?? 0,
        ));
        
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
            case 'anthropic':
                return new WPAIA_Provider_Anthropic($api_key);
            default:
                return new WP_Error('invalid_provider', __('Invalid AI provider', 'wp-ai-assistant'));
        }
    }
    
    /**
     * Check rate limit
     */
    private function check_rate_limit(string $conversation_id = ''): bool {
        $rate_limit = WP_AI_Assistant::get_option('rate_limit', 20);
        $ip = $this->get_client_ip();
        
        // Rate limit by IP
        $ip_key = 'wpaia_rate_' . md5($ip);
        $ip_count = get_transient($ip_key);
        
        if ($ip_count === false) {
            set_transient($ip_key, 1, MINUTE_IN_SECONDS);
        } elseif ($ip_count >= $rate_limit) {
            return false;
        } else {
            set_transient($ip_key, $ip_count + 1, MINUTE_IN_SECONDS);
        }
        
        // Additional rate limit by conversation_id (prevent context abuse)
        if (!empty($conversation_id)) {
            $conv_key = 'wpaia_conv_rate_' . md5($conversation_id);
            $conv_count = get_transient($conv_key);
            $conv_limit = $rate_limit * 2; // Allow more per conversation but still limit
            
            if ($conv_count === false) {
                set_transient($conv_key, 1, HOUR_IN_SECONDS);
            } elseif ($conv_count >= $conv_limit) {
                return false;
            } else {
                set_transient($conv_key, $conv_count + 1, HOUR_IN_SECONDS);
            }
        }
        
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
    
    /**
     * AJAX handler for saving lead information
     */
    public function ajax_save_lead() {
        check_ajax_referer('wpaia_chat_nonce', 'nonce');
        
        $session_id = sanitize_text_field($_POST['session_id'] ?? '');
        $email = sanitize_email($_POST['email'] ?? '');
        $phone = sanitize_text_field($_POST['phone'] ?? '');
        $name = sanitize_text_field($_POST['name'] ?? '');
        $page_url = esc_url_raw($_POST['page_url'] ?? '');
        $gdpr_consent = !empty($_POST['gdpr_consent']);
        
        if (empty($session_id)) {
            wp_send_json_error(array('message' => __('Session ID required', 'wp-ai-assistant')));
        }
        
        if (empty($email) && empty($phone)) {
            wp_send_json_error(array('message' => __('Email or phone required', 'wp-ai-assistant')));
        }
        
        // Validate email format if provided
        if (!empty($email) && !is_email($email)) {
            wp_send_json_error(array('message' => __('Invalid email format', 'wp-ai-assistant')));
        }
        
        // Validate phone format if provided (basic validation)
        if (!empty($phone) && !preg_match('/^[\d\s\+\-\(\)]{6,20}$/', $phone)) {
            wp_send_json_error(array('message' => __('Invalid phone format', 'wp-ai-assistant')));
        }
        
        if (!class_exists('WPAIA_Database')) {
            wp_send_json_error(array('message' => __('Database not available', 'wp-ai-assistant')));
        }
        
        $lead_data = array(
            'session_id' => $session_id,
            'email' => $email,
            'phone' => $phone,
            'name' => $name,
            'page_url' => $page_url,
            'gdpr_consent' => $gdpr_consent,
        );
        
        $lead_id = WPAIA_Database::save_lead($lead_data);
        
        if ($lead_id) {
            // Fire action hook for CRM/webhook integrations
            $lead_data['lead_id'] = $lead_id;
            $lead_data['ip_address'] = WPAIA_Database::get_client_ip();
            $lead_data['created_at'] = current_time('mysql');
            
            /**
             * Fires when a new lead is captured
             * 
             * @param array $lead_data Lead information including:
             *   - lead_id: int
             *   - session_id: string
             *   - email: string
             *   - phone: string
             *   - name: string
             *   - page_url: string
             *   - gdpr_consent: bool
             *   - ip_address: string
             *   - created_at: string
             */
            do_action('wpaia_lead_captured', $lead_data);
            
            wp_send_json_success(array(
                'lead_id' => $lead_id,
                'message' => __('Thank you! Your information has been saved.', 'wp-ai-assistant')
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to save information', 'wp-ai-assistant')));
        }
    }
}
