<?php
/**
 * Chat Widget Frontend
 * 
 * @package WP_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPAIA_Chat_Widget {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_footer', array($this, 'render_widget'));
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_assets() {
        wp_enqueue_style(
            'wpaia-chat-widget',
            WPAIA_PLUGIN_URL . 'assets/css/chat-widget.css',
            array(),
            WPAIA_VERSION
        );
        
        wp_enqueue_script(
            'wpaia-chat-widget',
            WPAIA_PLUGIN_URL . 'assets/js/chat-widget.js',
            array(),
            WPAIA_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('wpaia-chat-widget', 'wpaiaChat', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('wp-ai-assistant/v1/'),
            'nonce' => wp_create_nonce('wpaia_chat_nonce'),
            'restNonce' => wp_create_nonce('wp_rest'),
            'conversationId' => WPAIA_Conversation::get_id(),
            'welcomeMessage' => WP_AI_Assistant::get_option('welcome_message', __('Hello! How can I help you today?', 'wp-ai-assistant')),
            'position' => WP_AI_Assistant::get_option('widget_position', 'bottom-right'),
            'primaryColor' => WP_AI_Assistant::get_option('primary_color', '#0073aa'),
            'enableOrderLookup' => WP_AI_Assistant::get_option('enable_order_lookup') && class_exists('WooCommerce'),
            'gdpr' => array(
                'enabled' => WP_AI_Assistant::get_option('gdpr_consent_enabled', false),
                'text' => WP_AI_Assistant::get_option('gdpr_consent_text', '') ?: __('I agree to the storage of my data for support purposes.', 'wp-ai-assistant'),
                'linkText' => WP_AI_Assistant::get_option('gdpr_link_text', '') ?: __('Privacy Policy', 'wp-ai-assistant'),
                'linkUrl' => WP_AI_Assistant::get_option('gdpr_link_url', '') ?: get_privacy_policy_url(),
            ),
            'leadCapture' => array(
                'enabled' => WP_AI_Assistant::get_option('lead_capture_enabled', false),
                'mode' => WP_AI_Assistant::get_option('lead_capture_mode', 'after'),
                'afterMessages' => (int) WP_AI_Assistant::get_option('lead_capture_after_messages', 3),
                'fields' => WP_AI_Assistant::get_option('lead_capture_fields', array('email')),
                'title' => WP_AI_Assistant::get_option('lead_capture_title', '') ?: __('Stay in touch!', 'wp-ai-assistant'),
                'description' => WP_AI_Assistant::get_option('lead_capture_description', '') ?: __('Leave your contact info and we\'ll get back to you.', 'wp-ai-assistant'),
            ),
            'strings' => array(
                'placeholder' => __('Type your message...', 'wp-ai-assistant'),
                'send' => __('Send', 'wp-ai-assistant'),
                'thinking' => __('Thinking...', 'wp-ai-assistant'),
                'error' => __('Sorry, something went wrong. Please try again.', 'wp-ai-assistant'),
                'orderLookup' => __('Check Order Status', 'wp-ai-assistant'),
                'orderNumber' => __('Order Number', 'wp-ai-assistant'),
                'email' => __('Email', 'wp-ai-assistant'),
                'phone' => __('Phone', 'wp-ai-assistant'),
                'name' => __('Name', 'wp-ai-assistant'),
                'verify' => __('Verify', 'wp-ai-assistant'),
                'verifying' => __('Verifying...', 'wp-ai-assistant'),
                'verified' => __('Order verified! You can now ask about your order.', 'wp-ai-assistant'),
                'verifyFailed' => __('Could not verify order. Please check your details.', 'wp-ai-assistant'),
                'minimize' => __('Minimize', 'wp-ai-assistant'),
                'close' => __('Close', 'wp-ai-assistant'),
                'submit' => __('Submit', 'wp-ai-assistant'),
                'skip' => __('Skip', 'wp-ai-assistant'),
                'thanks' => __('Thank you! How can I help you?', 'wp-ai-assistant'),
                'emailPlaceholder' => __('your@email.com', 'wp-ai-assistant'),
                'phonePlaceholder' => __('Your phone number', 'wp-ai-assistant'),
                'namePlaceholder' => __('Your name', 'wp-ai-assistant'),
            ),
        ));
    }
    
    /**
     * Render widget HTML
     */
    public function render_widget() {
        $position = WP_AI_Assistant::get_option('widget_position', 'bottom-right');
        $primary_color = WP_AI_Assistant::get_option('primary_color', '#0073aa');
        $chat_icon = WP_AI_Assistant::get_option('chat_icon', 'chat');
        $custom_icon_url = WP_AI_Assistant::get_option('custom_icon_url', '');
        $header_avatar_url = WP_AI_Assistant::get_option('header_avatar_url', '');
        $site_name = get_bloginfo('name');
        ?>
        <div id="wpaia-chat-widget" class="wpaia-widget wpaia-position-<?php echo esc_attr($position); ?>" style="--wpaia-primary: <?php echo esc_attr($primary_color); ?>">
            
            <!-- Chat Toggle Button -->
            <button class="wpaia-toggle-btn" aria-label="<?php esc_attr_e('Open chat', 'wp-ai-assistant'); ?>">
                <?php echo $this->get_toggle_icon($chat_icon, $custom_icon_url); ?>
                <svg class="wpaia-icon-close" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
            
            <!-- Chat Window -->
            <div class="wpaia-chat-window" role="dialog" aria-labelledby="wpaia-title" aria-describedby="wpaia-subtitle">
                <!-- Header -->
                <div class="wpaia-header">
                    <div class="wpaia-header-info">
                        <div class="wpaia-avatar" aria-hidden="true">
                            <?php if (!empty($header_avatar_url)): ?>
                                <img src="<?php echo esc_url($header_avatar_url); ?>" alt="" />
                            <?php else: ?>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="3"></circle>
                                    <path d="M12 1v2m0 18v2M4.22 4.22l1.42 1.42m12.72 12.72l1.42 1.42M1 12h2m18 0h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"></path>
                                </svg>
                            <?php endif; ?>
                        </div>
                        <div class="wpaia-header-text">
                            <span class="wpaia-title" id="wpaia-title"><?php echo esc_html($site_name); ?></span>
                            <span class="wpaia-subtitle" id="wpaia-subtitle"><?php _e('AI Assistant', 'wp-ai-assistant'); ?></span>
                        </div>
                    </div>
                    <button class="wpaia-minimize-btn" aria-label="<?php esc_attr_e('Close chat', 'wp-ai-assistant'); ?>">
                        <!-- Line icon for desktop minimize -->
                        <svg class="wpaia-icon-minimize" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                        <!-- X icon for mobile close -->
                        <svg class="wpaia-icon-close" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                </div>
                
                <!-- Messages -->
                <div class="wpaia-messages" role="log" aria-live="polite" aria-label="<?php esc_attr_e('Chat messages', 'wp-ai-assistant'); ?>">
                    <!-- Messages will be inserted here -->
                </div>
                
                <!-- Order Verification Form (hidden by default) -->
                <div class="wpaia-order-form" style="display: none;" role="form" aria-labelledby="wpaia-order-title">
                    <div class="wpaia-order-form-inner">
                        <h4 id="wpaia-order-title"><?php _e('Verify Your Order', 'wp-ai-assistant'); ?></h4>
                        <input type="text" class="wpaia-order-input" placeholder="<?php esc_attr_e('Order # (e.g., 12345)', 'wp-ai-assistant'); ?>" id="wpaia-order-id" aria-label="<?php esc_attr_e('Order number', 'wp-ai-assistant'); ?>">
                        <input type="email" class="wpaia-order-input" placeholder="<?php esc_attr_e('Email address', 'wp-ai-assistant'); ?>" id="wpaia-order-email" aria-label="<?php esc_attr_e('Email address', 'wp-ai-assistant'); ?>">
                        <div class="wpaia-order-buttons">
                            <button type="button" class="wpaia-btn wpaia-btn-cancel"><?php _e('Cancel', 'wp-ai-assistant'); ?></button>
                            <button type="button" class="wpaia-btn wpaia-btn-verify"><?php _e('Verify', 'wp-ai-assistant'); ?></button>
                        </div>
                    </div>
                </div>
                
                <!-- Lead Capture Form (hidden by default) -->
                <?php 
                $lead_capture_enabled = WP_AI_Assistant::get_option('lead_capture_enabled', false);
                $lead_capture_fields = WP_AI_Assistant::get_option('lead_capture_fields', array('email'));
                $lead_capture_title = WP_AI_Assistant::get_option('lead_capture_title', '') ?: __('Stay in touch!', 'wp-ai-assistant');
                $lead_capture_description = WP_AI_Assistant::get_option('lead_capture_description', '') ?: __('Leave your contact info and we\'ll get back to you.', 'wp-ai-assistant');
                ?>
                <div class="wpaia-lead-form" style="display: none;" role="form" aria-labelledby="wpaia-lead-title">
                    <div class="wpaia-lead-form-inner">
                        <h4 id="wpaia-lead-title"><?php echo esc_html($lead_capture_title); ?></h4>
                        <p class="wpaia-lead-description" id="wpaia-lead-desc"><?php echo esc_html($lead_capture_description); ?></p>
                        <?php if (in_array('name', $lead_capture_fields)): ?>
                        <input type="text" class="wpaia-lead-input" placeholder="<?php esc_attr_e('Your name', 'wp-ai-assistant'); ?>" id="wpaia-lead-name" aria-label="<?php esc_attr_e('Your name', 'wp-ai-assistant'); ?>" autocomplete="name">
                        <?php endif; ?>
                        <?php if (in_array('email', $lead_capture_fields)): ?>
                        <input type="email" class="wpaia-lead-input" placeholder="<?php esc_attr_e('your@email.com', 'wp-ai-assistant'); ?>" id="wpaia-lead-email" aria-label="<?php esc_attr_e('Email address', 'wp-ai-assistant'); ?>" autocomplete="email">
                        <?php endif; ?>
                        <?php if (in_array('phone', $lead_capture_fields)): ?>
                        <input type="tel" class="wpaia-lead-input" placeholder="<?php esc_attr_e('Your phone number', 'wp-ai-assistant'); ?>" id="wpaia-lead-phone" aria-label="<?php esc_attr_e('Phone number', 'wp-ai-assistant'); ?>" autocomplete="tel">
                        <?php endif; ?>
                        
                        <!-- GDPR Consent Checkbox -->
                        <?php 
                        $gdpr_enabled = WP_AI_Assistant::get_option('gdpr_consent_enabled', false);
                        $gdpr_text = WP_AI_Assistant::get_option('gdpr_consent_text', '') ?: __('I agree to the storage of my data for support purposes.', 'wp-ai-assistant');
                        $gdpr_link_text = WP_AI_Assistant::get_option('gdpr_link_text', '') ?: __('Privacy Policy', 'wp-ai-assistant');
                        $gdpr_link_url = WP_AI_Assistant::get_option('gdpr_link_url', '') ?: get_privacy_policy_url();
                        if ($gdpr_enabled): ?>
                        <div class="wpaia-gdpr-consent">
                            <label>
                                <input type="checkbox" id="wpaia-gdpr-checkbox" aria-required="true">
                                <span class="wpaia-gdpr-text">
                                    <?php echo esc_html($gdpr_text); ?>
                                    <?php if (!empty($gdpr_link_url)): ?>
                                    <a href="<?php echo esc_url($gdpr_link_url); ?>" target="_blank" rel="noopener"><?php echo esc_html($gdpr_link_text); ?></a>
                                    <?php endif; ?>
                                </span>
                            </label>
                        </div>
                        <?php endif; ?>
                        
                        <div class="wpaia-lead-buttons">
                            <button type="button" class="wpaia-btn wpaia-btn-skip"><?php _e('Skip', 'wp-ai-assistant'); ?></button>
                            <button type="button" class="wpaia-btn wpaia-btn-submit-lead"><?php _e('Submit', 'wp-ai-assistant'); ?></button>
                        </div>
                    </div>
                </div>
                
                <!-- Input -->
                <div class="wpaia-input-area">
                    <?php if (WP_AI_Assistant::get_option('enable_order_lookup') && class_exists('WooCommerce')): ?>
                    <button class="wpaia-order-btn" aria-label="<?php esc_attr_e('Check order status', 'wp-ai-assistant'); ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="1" y="3" width="22" height="18" rx="2" ry="2"></rect>
                            <line x1="1" y1="9" x2="23" y2="9"></line>
                        </svg>
                    </button>
                    <?php endif; ?>
                    <input type="text" class="wpaia-input" placeholder="<?php esc_attr_e('Type your message...', 'wp-ai-assistant'); ?>" maxlength="1000" aria-label="<?php esc_attr_e('Chat message', 'wp-ai-assistant'); ?>" />
                    <button class="wpaia-send-btn" aria-label="<?php esc_attr_e('Send message', 'wp-ai-assistant'); ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="22" y1="2" x2="11" y2="13"></line>
                            <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                        </svg>
                    </button>
                </div>
                
                <!-- Powered by (only hidden with valid license) -->
                <?php 
                $can_hide_branding = WP_AI_Assistant::get_option('hide_branding') && WP_AI_Assistant::has_valid_license();
                if (!$can_hide_branding): 
                ?>
                <div class="wpaia-powered">
                    <span><?php _e('Powered by', 'wp-ai-assistant'); ?> <a href="https://carlosllamax.com" target="_blank" rel="noopener">carlosllamax.com</a></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Get toggle button icon based on settings
     */
    private function get_toggle_icon($icon_type, $custom_url = '') {
        if ($icon_type === 'custom' && !empty($custom_url)) {
            return '<img class="wpaia-icon-chat wpaia-icon-custom" src="' . esc_url($custom_url) . '" alt="" />';
        }
        
        $icons = array(
            'chat' => '<svg class="wpaia-icon-chat" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
            </svg>',
            'message' => '<svg class="wpaia-icon-chat" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                <polyline points="22,6 12,13 2,6"></polyline>
            </svg>',
            'help' => '<svg class="wpaia-icon-chat" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path>
                <line x1="12" y1="17" x2="12.01" y2="17"></line>
            </svg>',
            'bot' => '<svg class="wpaia-icon-chat" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="11" width="18" height="10" rx="2"></rect>
                <circle cx="12" cy="5" r="2"></circle>
                <path d="M12 7v4"></path>
                <line x1="8" y1="16" x2="8" y2="16"></line>
                <line x1="16" y1="16" x2="16" y2="16"></line>
            </svg>',
            'headset' => '<svg class="wpaia-icon-chat" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M3 18v-6a9 9 0 0 1 18 0v6"></path>
                <path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"></path>
            </svg>',
        );
        
        return $icons[$icon_type] ?? $icons['chat'];
    }
}
