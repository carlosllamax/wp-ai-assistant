<?php
/**
 * License Manager
 * 
 * Handles premium license verification
 * 
 * @package WP_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPAIA_License {
    
    /**
     * License API endpoint
     */
    private $api_url = 'https://carlosllamax.com/api/licenses/verify';
    
    /**
     * Cache key for license status
     */
    private $cache_key = 'wpaia_license_status';
    
    /**
     * Cache expiration (24 hours)
     */
    private $cache_expiration = 86400;
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp_ajax_wpaia_activate_license', array($this, 'ajax_activate_license'));
        add_action('wp_ajax_wpaia_deactivate_license', array($this, 'ajax_deactivate_license'));
        add_action('wpaia_daily_license_check', array($this, 'scheduled_license_check'));
        
        // Schedule daily check
        if (!wp_next_scheduled('wpaia_daily_license_check')) {
            wp_schedule_event(time(), 'daily', 'wpaia_daily_license_check');
        }
    }
    
    /**
     * Check if license is valid (with cache)
     */
    public function is_valid() {
        // Check cache first
        $cached = get_transient($this->cache_key);
        if ($cached !== false) {
            return $cached === 'valid';
        }
        
        // Get stored license
        $license_key = get_option('wpaia_license_key', '');
        if (empty($license_key)) {
            return false;
        }
        
        // Verify with API
        $is_valid = $this->verify_license($license_key);
        
        // Cache result
        set_transient($this->cache_key, $is_valid ? 'valid' : 'invalid', $this->cache_expiration);
        
        return $is_valid;
    }
    
    /**
     * Verify license with API
     */
    public function verify_license($license_key) {
        $response = wp_remote_post($this->api_url, array(
            'timeout' => 15,
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode(array(
                'license_key' => $license_key,
                'site_url' => home_url(),
                'action' => 'verify',
            )),
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return false;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        return !empty($body['valid']);
    }
    
    /**
     * Activate license via AJAX
     */
    public function ajax_activate_license() {
        check_ajax_referer('wpaia_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'wp-ai-assistant')));
        }
        
        $license_key = sanitize_text_field($_POST['license_key'] ?? '');
        
        if (empty($license_key)) {
            wp_send_json_error(array('message' => __('Please enter a license key', 'wp-ai-assistant')));
        }
        
        // Verify with API (activation)
        $response = wp_remote_post($this->api_url, array(
            'timeout' => 15,
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode(array(
                'license_key' => $license_key,
                'site_url' => home_url(),
                'action' => 'activate',
            )),
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => __('Could not connect to license server', 'wp-ai-assistant')));
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!empty($body['valid'])) {
            // Save license
            update_option('wpaia_license_key', $license_key);
            update_option('wpaia_license_email', $body['email'] ?? '');
            
            // Clear cache
            delete_transient($this->cache_key);
            set_transient($this->cache_key, 'valid', $this->cache_expiration);
            
            wp_send_json_success(array(
                'message' => __('License activated successfully!', 'wp-ai-assistant'),
                'email' => $body['email'] ?? '',
            ));
        } else {
            wp_send_json_error(array(
                'message' => $body['message'] ?? __('Invalid license key', 'wp-ai-assistant'),
            ));
        }
    }
    
    /**
     * Deactivate license via AJAX
     */
    public function ajax_deactivate_license() {
        check_ajax_referer('wpaia_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'wp-ai-assistant')));
        }
        
        $license_key = get_option('wpaia_license_key', '');
        
        if (!empty($license_key)) {
            // Notify API of deactivation
            wp_remote_post($this->api_url, array(
                'timeout' => 15,
                'headers' => array(
                    'Content-Type' => 'application/json',
                ),
                'body' => json_encode(array(
                    'license_key' => $license_key,
                    'site_url' => home_url(),
                    'action' => 'deactivate',
                )),
            ));
        }
        
        // Remove local license data
        delete_option('wpaia_license_key');
        delete_option('wpaia_license_email');
        delete_transient($this->cache_key);
        
        wp_send_json_success(array(
            'message' => __('License deactivated', 'wp-ai-assistant'),
        ));
    }
    
    /**
     * Scheduled license verification
     */
    public function scheduled_license_check() {
        $license_key = get_option('wpaia_license_key', '');
        if (!empty($license_key)) {
            $is_valid = $this->verify_license($license_key);
            set_transient($this->cache_key, $is_valid ? 'valid' : 'invalid', $this->cache_expiration);
        }
    }
    
    /**
     * Get license info
     */
    public function get_info() {
        return array(
            'key' => get_option('wpaia_license_key', ''),
            'email' => get_option('wpaia_license_email', ''),
            'is_valid' => $this->is_valid(),
        );
    }
}
