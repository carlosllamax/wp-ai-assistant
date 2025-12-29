<?php
/**
 * Plugin Updater
 * 
 * Handles self-hosted updates from GitHub releases
 * 
 * @package WP_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPAIA_Updater {
    
    /**
     * Plugin slug
     */
    private $slug;
    
    /**
     * Plugin data
     */
    private $plugin_data;
    
    /**
     * GitHub username
     */
    private $github_user = 'carlosllamax';
    
    /**
     * GitHub repo name
     */
    private $github_repo = 'wp-ai-assistant';
    
    /**
     * Update server URL (Vercel endpoint)
     */
    private $update_url = 'https://carlosllamax.com/api/plugins/wp-ai-assistant';
    
    /**
     * Cache key
     */
    private $cache_key = 'wpaia_update_check';
    
    /**
     * Cache expiration (12 hours)
     */
    private $cache_expiration = 43200;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->slug = WPAIA_PLUGIN_BASENAME;
        
        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_update'));
        add_filter('plugins_api', array($this, 'plugin_info'), 20, 3);
        add_action('upgrader_process_complete', array($this, 'clear_cache'), 10, 2);
        add_filter('plugin_row_meta', array($this, 'add_check_update_link'), 10, 2);
        add_action('wp_ajax_wpaia_force_update_check', array($this, 'force_update_check'));
    }
    
    /**
     * Get plugin data
     */
    private function get_plugin_data() {
        if (!$this->plugin_data) {
            if (!function_exists('get_plugin_data')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            $this->plugin_data = get_plugin_data(WPAIA_PLUGIN_DIR . 'wp-ai-assistant.php');
        }
        return $this->plugin_data;
    }
    
    /**
     * Get remote version info
     */
    private function get_remote_info() {
        // Check cache first
        $cached = get_transient($this->cache_key);
        if ($cached !== false) {
            return $cached;
        }
        
        // Fetch from update server
        $response = wp_remote_get($this->update_url, array(
            'timeout' => 10,
            'headers' => array(
                'Accept' => 'application/json',
            ),
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body);
        
        if (empty($data) || !isset($data->version)) {
            return false;
        }
        
        // Cache the result
        set_transient($this->cache_key, $data, $this->cache_expiration);
        
        return $data;
    }
    
    /**
     * Check for updates
     */
    public function check_for_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }
        
        $remote = $this->get_remote_info();
        
        if (!$remote) {
            return $transient;
        }
        
        $plugin_data = $this->get_plugin_data();
        $current_version = $plugin_data['Version'];
        
        // Compare versions
        if (version_compare($current_version, $remote->version, '<')) {
            $transient->response[$this->slug] = (object) array(
                'slug'        => dirname($this->slug),
                'plugin'      => $this->slug,
                'new_version' => $remote->version,
                'url'         => $remote->homepage ?? "https://github.com/{$this->github_user}/{$this->github_repo}",
                'package'     => $remote->download_url ?? $this->get_github_release_url($remote->version),
                'icons'       => array(
                    '1x' => $remote->icon ?? '',
                    '2x' => $remote->icon_2x ?? '',
                ),
                'banners'     => array(
                    'low'  => $remote->banner ?? '',
                    'high' => $remote->banner_2x ?? '',
                ),
                'tested'      => $remote->tested ?? '',
                'requires'    => $remote->requires ?? '5.8',
                'requires_php'=> $remote->requires_php ?? '7.4',
            );
        } else {
            // No update available - remove from response if exists
            unset($transient->response[$this->slug]);
            $transient->no_update[$this->slug] = (object) array(
                'slug'        => dirname($this->slug),
                'plugin'      => $this->slug,
                'new_version' => $current_version,
                'url'         => "https://github.com/{$this->github_user}/{$this->github_repo}",
            );
        }
        
        return $transient;
    }
    
    /**
     * Get GitHub release download URL
     */
    private function get_github_release_url($version) {
        return "https://github.com/{$this->github_user}/{$this->github_repo}/releases/download/v{$version}/wp-ai-assistant.zip";
    }
    
    /**
     * Plugin information popup
     */
    public function plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information') {
            return $result;
        }
        
        if (!isset($args->slug) || $args->slug !== dirname($this->slug)) {
            return $result;
        }
        
        $remote = $this->get_remote_info();
        
        if (!$remote) {
            return $result;
        }
        
        $plugin_data = $this->get_plugin_data();
        
        $info = new stdClass();
        $info->name = $plugin_data['Name'];
        $info->slug = dirname($this->slug);
        $info->version = $remote->version;
        $info->author = $plugin_data['Author'];
        $info->author_profile = $plugin_data['AuthorURI'];
        $info->homepage = $remote->homepage ?? $plugin_data['PluginURI'];
        $info->requires = $remote->requires ?? '5.8';
        $info->tested = $remote->tested ?? '';
        $info->requires_php = $remote->requires_php ?? '7.4';
        $info->downloaded = $remote->downloaded ?? 0;
        $info->last_updated = $remote->last_updated ?? '';
        $info->download_link = $remote->download_url ?? $this->get_github_release_url($remote->version);
        
        // Sections
        $info->sections = array(
            'description' => $remote->description ?? $plugin_data['Description'],
            'installation' => $remote->installation ?? $this->get_default_installation(),
            'changelog' => $remote->changelog ?? '',
        );
        
        // Banners
        if (!empty($remote->banner)) {
            $info->banners = array(
                'low' => $remote->banner,
                'high' => $remote->banner_2x ?? $remote->banner,
            );
        }
        
        return $info;
    }
    
    /**
     * Default installation instructions
     */
    private function get_default_installation() {
        return '<ol>
            <li>Download the plugin ZIP file</li>
            <li>Go to WordPress Admin → Plugins → Add New → Upload Plugin</li>
            <li>Upload the ZIP file and click Install Now</li>
            <li>Activate the plugin</li>
            <li>Go to WP AI Assistant settings and enter your API key</li>
        </ol>';
    }
    
    /**
     * Clear cache after update
     */
    public function clear_cache($upgrader, $options) {
        if ($options['action'] === 'update' && $options['type'] === 'plugin') {
            delete_transient($this->cache_key);
        }
    }
    
    /**
     * Add "Check for updates" link
     */
    public function add_check_update_link($links, $file) {
        if ($file === $this->slug) {
            $links[] = sprintf(
                '<a href="#" class="wpaia-check-update" data-nonce="%s">%s</a>',
                wp_create_nonce('wpaia_force_update'),
                __('Check for updates', 'wp-ai-assistant')
            );
        }
        return $links;
    }
    
    /**
     * Force update check via AJAX
     */
    public function force_update_check() {
        check_ajax_referer('wpaia_force_update', 'nonce');
        
        if (!current_user_can('update_plugins')) {
            wp_send_json_error('Permission denied');
        }
        
        // Clear cache
        delete_transient($this->cache_key);
        delete_site_transient('update_plugins');
        
        // Force check
        wp_update_plugins();
        
        wp_send_json_success(array(
            'message' => __('Update check complete. Refreshing page...', 'wp-ai-assistant'),
        ));
    }
}
