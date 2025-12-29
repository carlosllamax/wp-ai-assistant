<?php
/**
 * Logger Class
 * 
 * Handles error and debug logging for the plugin
 *
 * @package WP_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPAIA_Logger {

    /**
     * Log levels
     */
    const LEVEL_DEBUG = 'debug';
    const LEVEL_INFO = 'info';
    const LEVEL_WARNING = 'warning';
    const LEVEL_ERROR = 'error';
    
    /**
     * Log table name
     */
    private static $table_name = null;
    
    /**
     * Is logging enabled
     */
    private static $enabled = null;
    
    /**
     * Get log table name
     */
    public static function get_table_name() {
        if (self::$table_name === null) {
            global $wpdb;
            self::$table_name = $wpdb->prefix . 'wpaia_logs';
        }
        return self::$table_name;
    }
    
    /**
     * Check if logging is enabled
     */
    public static function is_enabled() {
        if (self::$enabled === null) {
            self::$enabled = WP_AI_Assistant::get_option('enable_logging', false) || WP_DEBUG;
        }
        return self::$enabled;
    }
    
    /**
     * Create logs table
     */
    public static function create_table() {
        global $wpdb;
        
        $table = self::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            level varchar(20) NOT NULL DEFAULT 'info',
            message text NOT NULL,
            context longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY level (level),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Log a message
     */
    public static function log($level, $message, $context = array()) {
        if (!self::is_enabled()) {
            return;
        }
        
        // Always log errors to PHP error log
        if ($level === self::LEVEL_ERROR) {
            error_log('[WP AI Assistant] ' . $message . ' | Context: ' . json_encode($context));
        }
        
        // Log to database
        global $wpdb;
        $table = self::get_table_name();
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return; // Table doesn't exist yet
        }
        
        $wpdb->insert($table, array(
            'level' => sanitize_text_field($level),
            'message' => sanitize_text_field($message),
            'context' => wp_json_encode($context),
        ));
        
        // Cleanup old logs (keep last 1000)
        self::cleanup();
    }
    
    /**
     * Log debug message
     */
    public static function debug($message, $context = array()) {
        self::log(self::LEVEL_DEBUG, $message, $context);
    }
    
    /**
     * Log info message
     */
    public static function info($message, $context = array()) {
        self::log(self::LEVEL_INFO, $message, $context);
    }
    
    /**
     * Log warning message
     */
    public static function warning($message, $context = array()) {
        self::log(self::LEVEL_WARNING, $message, $context);
    }
    
    /**
     * Log error message
     */
    public static function error($message, $context = array()) {
        self::log(self::LEVEL_ERROR, $message, $context);
    }
    
    /**
     * Log API error
     */
    public static function api_error($provider, $error_message, $request_data = array()) {
        self::error("API Error ({$provider}): {$error_message}", array(
            'provider' => $provider,
            'request' => $request_data,
            'timestamp' => current_time('mysql'),
        ));
    }
    
    /**
     * Get logs
     */
    public static function get_logs($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'level' => '',
            'per_page' => 50,
            'page' => 1,
            'orderby' => 'created_at',
            'order' => 'DESC',
        );
        
        $args = wp_parse_args($args, $defaults);
        $table = self::get_table_name();
        $offset = ($args['page'] - 1) * $args['per_page'];
        
        $where = "1=1";
        $prepare_args = array();
        
        if (!empty($args['level'])) {
            $where .= " AND level = %s";
            $prepare_args[] = $args['level'];
        }
        
        $sql = "SELECT * FROM $table WHERE $where ORDER BY {$args['orderby']} {$args['order']} LIMIT %d OFFSET %d";
        $prepare_args[] = $args['per_page'];
        $prepare_args[] = $offset;
        
        return $wpdb->get_results($wpdb->prepare($sql, $prepare_args));
    }
    
    /**
     * Cleanup old logs
     */
    public static function cleanup($keep = 1000) {
        global $wpdb;
        $table = self::get_table_name();
        
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
        
        if ($count > $keep) {
            $delete_count = $count - $keep;
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM $table ORDER BY created_at ASC LIMIT %d",
                    $delete_count
                )
            );
        }
    }
    
    /**
     * Clear all logs
     */
    public static function clear_all() {
        global $wpdb;
        $table = self::get_table_name();
        $wpdb->query("TRUNCATE TABLE $table");
    }
    
    /**
     * Drop table
     */
    public static function drop_table() {
        global $wpdb;
        $table = self::get_table_name();
        $wpdb->query("DROP TABLE IF EXISTS $table");
    }
}
