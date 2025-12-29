<?php
/**
 * Conversation Manager
 * Handles chat history and session management
 * 
 * @package WP_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPAIA_Conversation {
    
    /**
     * Session key
     */
    private const SESSION_KEY = 'wpaia_conversation';
    
    /**
     * Max history messages
     */
    private const MAX_HISTORY = 20;
    
    /**
     * Get conversation ID
     */
    public static function get_id(): string {
        if (!isset($_COOKIE['wpaia_conv_id'])) {
            return wp_generate_uuid4();
        }
        return sanitize_text_field($_COOKIE['wpaia_conv_id']);
    }
    
    /**
     * Get conversation history from transient
     */
    public static function get_history(string $conversation_id): array {
        $history = get_transient(self::SESSION_KEY . '_' . $conversation_id);
        return is_array($history) ? $history : array();
    }
    
    /**
     * Add message to history
     */
    public static function add_message(string $conversation_id, string $role, string $content): void {
        $history = self::get_history($conversation_id);
        
        $history[] = array(
            'role' => $role,
            'content' => $content,
            'timestamp' => time(),
        );
        
        // Keep only last N messages
        if (count($history) > self::MAX_HISTORY) {
            $history = array_slice($history, -self::MAX_HISTORY);
        }
        
        // Store for 1 hour
        set_transient(self::SESSION_KEY . '_' . $conversation_id, $history, HOUR_IN_SECONDS);
    }
    
    /**
     * Clear conversation history
     */
    public static function clear(string $conversation_id): void {
        delete_transient(self::SESSION_KEY . '_' . $conversation_id);
    }
    
    /**
     * Format history for API
     */
    public static function format_for_api(array $history): array {
        $messages = array();
        
        foreach ($history as $msg) {
            $messages[] = array(
                'role' => $msg['role'],
                'content' => $msg['content'],
            );
        }
        
        return $messages;
    }
    
    /**
     * Check if user is verified (for order lookup)
     */
    public static function is_verified(string $conversation_id): bool {
        $verified = get_transient('wpaia_verified_' . $conversation_id);
        return !empty($verified);
    }
    
    /**
     * Set user as verified
     */
    public static function set_verified(string $conversation_id, int $order_id, string $email): void {
        set_transient('wpaia_verified_' . $conversation_id, array(
            'order_id' => $order_id,
            'email' => $email,
        ), HOUR_IN_SECONDS);
    }
    
    /**
     * Get verified order info
     */
    public static function get_verified_order(string $conversation_id): ?array {
        $verified = get_transient('wpaia_verified_' . $conversation_id);
        return is_array($verified) ? $verified : null;
    }
}
