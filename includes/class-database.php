<?php
/**
 * Database Handler for Conversations and Leads
 *
 * @package WP_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPAIA_Database {

    /**
     * Database version
     */
    const DB_VERSION = '1.0.0';

    /**
     * Create database tables on activation
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Leads table
        $leads_table = $wpdb->prefix . 'wpaia_leads';
        $sql_leads = "CREATE TABLE $leads_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            session_id varchar(64) NOT NULL,
            email varchar(255) DEFAULT NULL,
            phone varchar(50) DEFAULT NULL,
            name varchar(255) DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            page_url text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY session_id (session_id),
            KEY email (email),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Conversations table
        $conversations_table = $wpdb->prefix . 'wpaia_conversations';
        $sql_conversations = "CREATE TABLE $conversations_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            lead_id bigint(20) unsigned DEFAULT NULL,
            session_id varchar(64) NOT NULL,
            role enum('user','assistant','system') NOT NULL,
            message text NOT NULL,
            tokens_used int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY lead_id (lead_id),
            KEY session_id (session_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_leads);
        dbDelta($sql_conversations);
        
        update_option('wpaia_db_version', self::DB_VERSION);
    }

    /**
     * Check and update tables if needed
     */
    public static function check_tables() {
        $installed_version = get_option('wpaia_db_version', '0');
        
        if (version_compare($installed_version, self::DB_VERSION, '<')) {
            self::create_tables();
        }
    }

    /**
     * Get leads table name
     */
    public static function get_leads_table() {
        global $wpdb;
        return $wpdb->prefix . 'wpaia_leads';
    }

    /**
     * Get conversations table name
     */
    public static function get_conversations_table() {
        global $wpdb;
        return $wpdb->prefix . 'wpaia_conversations';
    }

    /**
     * Save or update a lead
     */
    public static function save_lead($data) {
        global $wpdb;
        
        $table = self::get_leads_table();
        $session_id = sanitize_text_field($data['session_id'] ?? '');
        
        if (empty($session_id)) {
            return false;
        }
        
        // Check if lead exists
        $existing = $wpdb->get_row(
            $wpdb->prepare("SELECT id FROM $table WHERE session_id = %s", $session_id)
        );
        
        $lead_data = array(
            'session_id' => $session_id,
            'email' => sanitize_email($data['email'] ?? ''),
            'phone' => sanitize_text_field($data['phone'] ?? ''),
            'name' => sanitize_text_field($data['name'] ?? ''),
            'ip_address' => self::get_client_ip(),
            'user_agent' => sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? ''),
            'page_url' => esc_url_raw($data['page_url'] ?? ''),
        );
        
        if ($existing) {
            // Update existing lead
            $wpdb->update($table, $lead_data, array('id' => $existing->id));
            return $existing->id;
        } else {
            // Insert new lead
            $wpdb->insert($table, $lead_data);
            return $wpdb->insert_id;
        }
    }

    /**
     * Save a conversation message
     */
    public static function save_message($session_id, $role, $message, $tokens = 0) {
        global $wpdb;
        
        $table = self::get_conversations_table();
        $leads_table = self::get_leads_table();
        
        // Get lead_id if exists
        $lead_id = $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM $leads_table WHERE session_id = %s", $session_id)
        );
        
        $wpdb->insert($table, array(
            'lead_id' => $lead_id,
            'session_id' => sanitize_text_field($session_id),
            'role' => sanitize_text_field($role),
            'message' => wp_kses_post($message),
            'tokens_used' => absint($tokens),
        ));
        
        return $wpdb->insert_id;
    }

    /**
     * Get conversation by session
     */
    public static function get_conversation($session_id) {
        global $wpdb;
        
        $table = self::get_conversations_table();
        
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE session_id = %s ORDER BY created_at ASC",
                $session_id
            )
        );
    }

    /**
     * Get all leads with pagination
     */
    public static function get_leads($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'per_page' => 20,
            'page' => 1,
            'orderby' => 'created_at',
            'order' => 'DESC',
            'search' => '',
        );
        
        $args = wp_parse_args($args, $defaults);
        $table = self::get_leads_table();
        $conv_table = self::get_conversations_table();
        
        $offset = ($args['page'] - 1) * $args['per_page'];
        
        $where = "1=1";
        $prepare_args = array();
        
        if (!empty($args['search'])) {
            $where .= " AND (email LIKE %s OR phone LIKE %s OR name LIKE %s)";
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $prepare_args[] = $search;
            $prepare_args[] = $search;
            $prepare_args[] = $search;
        }
        
        $orderby = in_array($args['orderby'], array('id', 'email', 'created_at')) ? $args['orderby'] : 'created_at';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        
        // Get leads with message count
        $sql = "SELECT l.*, 
                (SELECT COUNT(*) FROM $conv_table WHERE session_id = l.session_id) as message_count,
                (SELECT MAX(created_at) FROM $conv_table WHERE session_id = l.session_id) as last_message
                FROM $table l 
                WHERE $where 
                ORDER BY $orderby $order 
                LIMIT %d OFFSET %d";
        
        $prepare_args[] = $args['per_page'];
        $prepare_args[] = $offset;
        
        if (!empty($prepare_args)) {
            $results = $wpdb->get_results($wpdb->prepare($sql, $prepare_args));
        } else {
            $results = $wpdb->get_results($sql);
        }
        
        // Get total count
        $count_sql = "SELECT COUNT(*) FROM $table WHERE $where";
        if (!empty($args['search'])) {
            $total = $wpdb->get_var($wpdb->prepare($count_sql, $search, $search, $search));
        } else {
            $total = $wpdb->get_var($count_sql);
        }
        
        return array(
            'leads' => $results,
            'total' => (int) $total,
            'pages' => ceil($total / $args['per_page']),
        );
    }

    /**
     * Get all conversations (for admin view)
     */
    public static function get_all_conversations($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'per_page' => 20,
            'page' => 1,
            'orderby' => 'last_message',
            'order' => 'DESC',
            'search' => '',
        );
        
        $args = wp_parse_args($args, $defaults);
        $table = self::get_conversations_table();
        $leads_table = self::get_leads_table();
        
        $offset = ($args['page'] - 1) * $args['per_page'];
        
        // Get unique sessions with their lead info
        $sql = "SELECT 
                c.session_id,
                l.id as lead_id,
                l.email,
                l.phone,
                l.name,
                COUNT(c.id) as message_count,
                MIN(c.created_at) as started_at,
                MAX(c.created_at) as last_message,
                SUM(c.tokens_used) as total_tokens
                FROM $table c
                LEFT JOIN $leads_table l ON c.session_id = l.session_id
                GROUP BY c.session_id
                ORDER BY last_message DESC
                LIMIT %d OFFSET %d";
        
        $results = $wpdb->get_results($wpdb->prepare($sql, $args['per_page'], $offset));
        
        // Get total unique sessions
        $total = $wpdb->get_var("SELECT COUNT(DISTINCT session_id) FROM $table");
        
        return array(
            'conversations' => $results,
            'total' => (int) $total,
            'pages' => ceil($total / $args['per_page']),
        );
    }

    /**
     * Delete conversation and associated lead
     */
    public static function delete_conversation($session_id) {
        global $wpdb;
        
        $conv_table = self::get_conversations_table();
        $leads_table = self::get_leads_table();
        
        $wpdb->delete($conv_table, array('session_id' => $session_id));
        $wpdb->delete($leads_table, array('session_id' => $session_id));
        
        return true;
    }

    /**
     * Get client IP address
     */
    private static function get_client_ip() {
        $ip_keys = array(
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        );
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // Handle comma-separated IPs
                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return '0.0.0.0';
    }

    /**
     * Get stats for dashboard
     */
    public static function get_stats() {
        global $wpdb;
        
        $conv_table = self::get_conversations_table();
        $leads_table = self::get_leads_table();
        
        $today = date('Y-m-d 00:00:00');
        $week_ago = date('Y-m-d 00:00:00', strtotime('-7 days'));
        
        return array(
            'total_conversations' => (int) $wpdb->get_var("SELECT COUNT(DISTINCT session_id) FROM $conv_table"),
            'total_leads' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $leads_table WHERE email != '' OR phone != ''"),
            'conversations_today' => (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT session_id) FROM $conv_table WHERE created_at >= %s", $today
            )),
            'conversations_week' => (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT session_id) FROM $conv_table WHERE created_at >= %s", $week_ago
            )),
            'total_messages' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $conv_table"),
        );
    }
}
