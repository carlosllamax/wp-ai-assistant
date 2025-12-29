<?php
/**
 * Plugin Name: WP AI Assistant
 * Plugin URI: https://github.com/carlosllamax/wp-ai-assistant
 * Description: AI-powered chat assistant for WordPress. Supports Groq, OpenAI, Anthropic. BYOK (Bring Your Own Key).
 * Version: 1.1.0
 * Author: Carlos Llamas
 * Author URI: https://carlosllamax.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-ai-assistant
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('WPAIA_VERSION', '1.1.0');
define('WPAIA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WPAIA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WPAIA_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main Plugin Class
 */
final class WP_AI_Assistant {
    
    /**
     * Single instance
     */
    private static $instance = null;
    
    /**
     * Get instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    /**
     * Load required files
     */
    private function load_dependencies() {
        // Core classes
        require_once WPAIA_PLUGIN_DIR . 'includes/class-admin.php';
        require_once WPAIA_PLUGIN_DIR . 'includes/class-rest-api.php';
        require_once WPAIA_PLUGIN_DIR . 'includes/class-chat-widget.php';
        require_once WPAIA_PLUGIN_DIR . 'includes/class-context-builder.php';
        require_once WPAIA_PLUGIN_DIR . 'includes/class-conversation.php';
        require_once WPAIA_PLUGIN_DIR . 'includes/class-updater.php';
        require_once WPAIA_PLUGIN_DIR . 'includes/class-license.php';
        
        // AI Providers
        require_once WPAIA_PLUGIN_DIR . 'includes/providers/interface-provider.php';
        require_once WPAIA_PLUGIN_DIR . 'includes/providers/class-groq.php';
        require_once WPAIA_PLUGIN_DIR . 'includes/providers/class-openai.php';
        
        // Integrations (conditional)
        if (class_exists('WooCommerce')) {
            require_once WPAIA_PLUGIN_DIR . 'integrations/class-woocommerce.php';
        }
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Load translations
        add_action('init', array($this, 'load_textdomain'));
        
        // Initialize components
        add_action('plugins_loaded', array($this, 'init_components'));
        
        // Activation/Deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'wp-ai-assistant',
            false,
            dirname(WPAIA_PLUGIN_BASENAME) . '/languages'
        );
    }
    
    /**
     * Initialize plugin components
     */
    public function init_components() {
        // Admin settings
        if (is_admin()) {
            new WPAIA_Admin();
            new WPAIA_Updater();
            new WPAIA_License();
        }
        
        // REST API
        new WPAIA_REST_API();
        
        // Chat Widget (frontend)
        if (!is_admin() && $this->is_enabled()) {
            new WPAIA_Chat_Widget();
        }
    }
    
    /**
     * Check if plugin is enabled and configured
     */
    public function is_enabled() {
        $options = get_option('wpaia_settings', array());
        return !empty($options['enabled']) && !empty($options['api_key']);
    }
    
    /**
     * Get plugin option
     */
    public static function get_option($key, $default = '') {
        $options = get_option('wpaia_settings', array());
        $value = isset($options[$key]) ? $options[$key] : $default;
        
        // hide_branding requires valid premium license
        if ($key === 'hide_branding' && $value) {
            return self::has_valid_license();
        }
        
        return $value;
    }
    
    /**
     * Check if site has valid premium license
     */
    public static function has_valid_license() {
        static $license_valid = null;
        
        if ($license_valid === null) {
            $license_key = get_option('wpaia_license_key', '');
            if (empty($license_key)) {
                $license_valid = false;
            } else {
                $cached = get_transient('wpaia_license_status');
                $license_valid = ($cached === 'valid');
            }
        }
        
        return $license_valid;
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Set default options
        $defaults = array(
            'enabled' => false,
            'provider' => 'groq',
            'api_key' => '',
            'model' => 'llama-3.1-70b-versatile',
            'system_prompt' => '',
            'welcome_message' => __('Hello! How can I help you today?', 'wp-ai-assistant'),
            'widget_position' => 'bottom-right',
            'primary_color' => '#0073aa',
            'include_pages' => true,
            'include_products' => true,
            'include_faqs' => true,
            'enable_order_lookup' => true,
            'rate_limit' => 20, // requests per minute
        );
        
        if (!get_option('wpaia_settings')) {
            add_option('wpaia_settings', $defaults);
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        flush_rewrite_rules();
    }
}

/**
 * Initialize plugin
 */
function wpaia_init() {
    return WP_AI_Assistant::get_instance();
}

// Start the plugin
wpaia_init();





