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
     * Max history messages (fallback)
     */
    private const MAX_HISTORY = 20;
    
    /**
     * Max tokens for context (approximate)
     * Most models support 4k-8k context, we use ~3k for safety
     */
    private const MAX_CONTEXT_TOKENS = 3000;
    
    /**
     * Average chars per token (rough estimate)
     */
    private const CHARS_PER_TOKEN = 4;
    
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
            'tokens' => self::estimate_tokens($content),
        );
        
        // Trim history to fit within token limit
        $history = self::trim_to_token_limit($history);
        
        // Store for 1 hour
        set_transient(self::SESSION_KEY . '_' . $conversation_id, $history, HOUR_IN_SECONDS);
    }
    
    /**
     * Estimate tokens for a string
     */
    public static function estimate_tokens(string $text): int {
        return (int) ceil(mb_strlen($text) / self::CHARS_PER_TOKEN);
    }
    
    /**
     * Trim history to fit within token limit
     */
    private static function trim_to_token_limit(array $history): array {
        $total_tokens = 0;
        
        // Calculate total tokens
        foreach ($history as $msg) {
            $total_tokens += $msg['tokens'] ?? self::estimate_tokens($msg['content']);
        }
        
        // Remove oldest messages until within limit
        while ($total_tokens > self::MAX_CONTEXT_TOKENS && count($history) > 2) {
            $removed = array_shift($history);
            $total_tokens -= $removed['tokens'] ?? self::estimate_tokens($removed['content']);
        }
        
        // Also enforce max message count as secondary limit
        if (count($history) > self::MAX_HISTORY) {
            $history = array_slice($history, -self::MAX_HISTORY);
        }
        
        return $history;
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
    
    /**
     * Get current token usage for a conversation
     */
    public static function get_token_usage(string $conversation_id): int {
        $history = self::get_history($conversation_id);
        $total = 0;
        
        foreach ($history as $msg) {
            $total += $msg['tokens'] ?? self::estimate_tokens($msg['content']);
        }
        
        return $total;
    }
}
