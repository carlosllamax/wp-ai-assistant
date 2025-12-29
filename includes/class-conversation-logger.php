<?php
/**
 * Conversation Logger
 * 
 * Handles saving conversations to database
 *
 * @package WP_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPAIA_Conversation_Logger {

    /**
     * Constructor
     */
    public function __construct() {
        // Hook into chat responses to save messages
        add_filter('wpaia_after_chat_response', array($this, 'log_conversation'), 10, 4);
        
        // AJAX endpoint for saving lead info
        add_action('wp_ajax_wpaia_save_lead', array($this, 'ajax_save_lead'));
        add_action('wp_ajax_nopriv_wpaia_save_lead', array($this, 'ajax_save_lead'));
    }

    /**
     * Log conversation to database
     */
    public function log_conversation($response, $user_message, $session_id, $tokens = 0) {
        // Check if logging is enabled
        if (!WP_AI_Assistant::get_option('save_conversations', true)) {
            return $response;
        }
        
        // Save user message
        WPAIA_Database::save_message($session_id, 'user', $user_message);
        
        // Save assistant response
        WPAIA_Database::save_message($session_id, 'assistant', $response, $tokens);
        
        return $response;
    }

    /**
     * AJAX handler for saving lead info
     */
    public function ajax_save_lead() {
        check_ajax_referer('wpaia_chat_nonce', 'nonce');
        
        $session_id = sanitize_text_field($_POST['session_id'] ?? '');
        $email = sanitize_email($_POST['email'] ?? '');
        $phone = sanitize_text_field($_POST['phone'] ?? '');
        $name = sanitize_text_field($_POST['name'] ?? '');
        $page_url = esc_url_raw($_POST['page_url'] ?? '');
        
        if (empty($session_id)) {
            wp_send_json_error(array('message' => 'Session ID required'));
        }
        
        if (empty($email) && empty($phone)) {
            wp_send_json_error(array('message' => 'Email or phone required'));
        }
        
        $lead_id = WPAIA_Database::save_lead(array(
            'session_id' => $session_id,
            'email' => $email,
            'phone' => $phone,
            'name' => $name,
            'page_url' => $page_url,
        ));
        
        if ($lead_id) {
            wp_send_json_success(array('lead_id' => $lead_id));
        } else {
            wp_send_json_error(array('message' => 'Failed to save lead'));
        }
    }
}
