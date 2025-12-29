<?php
/**
 * Admin Settings Page
 * 
 * @package WP_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPAIA_Admin {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('AI Assistant', 'wp-ai-assistant'),
            __('AI Assistant', 'wp-ai-assistant'),
            'manage_options',
            'wp-ai-assistant',
            array($this, 'render_settings_page'),
            'dashicons-format-chat',
            30
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('wpaia_settings_group', 'wpaia_settings', array(
            'sanitize_callback' => array($this, 'sanitize_settings')
        ));
        
        // General Section
        add_settings_section(
            'wpaia_general_section',
            __('General Settings', 'wp-ai-assistant'),
            array($this, 'render_general_section'),
            'wp-ai-assistant'
        );
        
        // API Section
        add_settings_section(
            'wpaia_api_section',
            __('API Configuration', 'wp-ai-assistant'),
            array($this, 'render_api_section'),
            'wp-ai-assistant'
        );
        
        // Context Section
        add_settings_section(
            'wpaia_context_section',
            __('Context Sources', 'wp-ai-assistant'),
            array($this, 'render_context_section'),
            'wp-ai-assistant'
        );
        
        // Appearance Section
        add_settings_section(
            'wpaia_appearance_section',
            __('Appearance', 'wp-ai-assistant'),
            array($this, 'render_appearance_section'),
            'wp-ai-assistant'
        );
    }
    
    /**
     * Sanitize settings
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
        $sanitized['enabled'] = !empty($input['enabled']);
        $sanitized['provider'] = sanitize_text_field($input['provider'] ?? 'groq');
        $sanitized['api_key'] = sanitize_text_field($input['api_key'] ?? '');
        $sanitized['model'] = sanitize_text_field($input['model'] ?? 'llama-3.1-70b-versatile');
        $sanitized['system_prompt'] = wp_kses_post($input['system_prompt'] ?? '');
        $sanitized['welcome_message'] = sanitize_text_field($input['welcome_message'] ?? '');
        $sanitized['widget_position'] = sanitize_text_field($input['widget_position'] ?? 'bottom-right');
        $sanitized['primary_color'] = sanitize_hex_color($input['primary_color'] ?? '#0073aa');
        $sanitized['chat_icon'] = sanitize_text_field($input['chat_icon'] ?? 'chat');
        $sanitized['custom_icon_url'] = esc_url_raw($input['custom_icon_url'] ?? '');
        $sanitized['header_avatar_url'] = esc_url_raw($input['header_avatar_url'] ?? '');
        $sanitized['include_pages'] = !empty($input['include_pages']);
        $sanitized['include_products'] = !empty($input['include_products']);
        $sanitized['include_faqs'] = !empty($input['include_faqs']);
        $sanitized['enable_order_lookup'] = !empty($input['enable_order_lookup']);
        $sanitized['rate_limit'] = absint($input['rate_limit'] ?? 20);
        $sanitized['custom_context'] = wp_kses_post($input['custom_context'] ?? '');
        $sanitized['hide_branding'] = !empty($input['hide_branding']);
        
        // Lead capture settings
        $sanitized['save_conversations'] = !empty($input['save_conversations']);
        $sanitized['lead_capture_enabled'] = !empty($input['lead_capture_enabled']);
        $sanitized['lead_capture_mode'] = in_array($input['lead_capture_mode'] ?? 'after', array('before', 'after', 'end')) 
            ? $input['lead_capture_mode'] 
            : 'after';
        $sanitized['lead_capture_after_messages'] = absint($input['lead_capture_after_messages'] ?? 3);
        $sanitized['lead_capture_fields'] = array();
        if (!empty($input['lead_capture_fields']) && is_array($input['lead_capture_fields'])) {
            $valid_fields = array('email', 'phone', 'name');
            foreach ($input['lead_capture_fields'] as $field) {
                if (in_array($field, $valid_fields)) {
                    $sanitized['lead_capture_fields'][] = $field;
                }
            }
        }
        $sanitized['lead_capture_title'] = sanitize_text_field($input['lead_capture_title'] ?? '');
        $sanitized['lead_capture_description'] = sanitize_text_field($input['lead_capture_description'] ?? '');
        
        // GDPR settings
        $sanitized['gdpr_consent_enabled'] = !empty($input['gdpr_consent_enabled']);
        $sanitized['gdpr_consent_text'] = sanitize_text_field($input['gdpr_consent_text'] ?? '');
        $sanitized['gdpr_link_text'] = sanitize_text_field($input['gdpr_link_text'] ?? '');
        $sanitized['gdpr_link_url'] = esc_url_raw($input['gdpr_link_url'] ?? '');
        
        return $sanitized;
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Load minimal script on plugins page for "Check for updates" link
        if ($hook === 'plugins.php') {
            wp_enqueue_script(
                'wpaia-plugins-page',
                WPAIA_PLUGIN_URL . 'assets/js/plugins-page.js',
                array('jquery'),
                WPAIA_VERSION,
                true
            );
            return;
        }

        if ($hook !== 'toplevel_page_wp-ai-assistant') {
            return;
        }
        
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        
        // Enqueue media uploader for icon/avatar uploads
        wp_enqueue_media();
        
        wp_enqueue_style(
            'wpaia-admin',
            WPAIA_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            WPAIA_VERSION
        );
        
        wp_enqueue_script(
            'wpaia-admin',
            WPAIA_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'wp-color-picker', 'media-upload'),
            WPAIA_VERSION,
            true
        );
        
        wp_localize_script('wpaia-admin', 'wpaiaAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wpaia_admin_nonce'),
            'strings' => array(
                'testing' => __('Testing connection...', 'wp-ai-assistant'),
                'success' => __('Connection successful!', 'wp-ai-assistant'),
                'error' => __('Connection failed: ', 'wp-ai-assistant'),
                'selectIcon' => __('Select Chat Icon', 'wp-ai-assistant'),
                'selectAvatar' => __('Select Avatar', 'wp-ai-assistant'),
                'useImage' => __('Use this image', 'wp-ai-assistant'),
            )
        ));
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $options = get_option('wpaia_settings', array());
        ?>
        <div class="wrap wpaia-admin-wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="wpaia-admin-container">
                <div class="wpaia-admin-main">
                    <form method="post" action="options.php">
                        <?php
                        settings_fields('wpaia_settings_group');
                        ?>
                        
                        <!-- Enable/Disable Toggle -->
                        <div class="wpaia-card wpaia-enable-card">
                            <label class="wpaia-toggle">
                                <input type="checkbox" name="wpaia_settings[enabled]" value="1" <?php checked(!empty($options['enabled'])); ?>>
                                <span class="wpaia-toggle-slider"></span>
                                <span class="wpaia-toggle-label"><?php _e('Enable AI Assistant', 'wp-ai-assistant'); ?></span>
                            </label>
                        </div>
                        
                        <!-- API Configuration -->
                        <div class="wpaia-card">
                            <h2><?php _e('API Configuration', 'wp-ai-assistant'); ?></h2>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="wpaia_provider"><?php _e('AI Provider', 'wp-ai-assistant'); ?></label>
                                    </th>
                                    <td>
                                        <select name="wpaia_settings[provider]" id="wpaia_provider">
                                            <option value="groq" <?php selected($options['provider'] ?? '', 'groq'); ?>>Groq (Free, Fast)</option>
                                            <option value="openai" <?php selected($options['provider'] ?? '', 'openai'); ?>>OpenAI (GPT-4)</option>
                                            <option value="anthropic" <?php selected($options['provider'] ?? '', 'anthropic'); ?>>Anthropic (Claude)</option>
                                        </select>
                                        <p class="description"><?php _e('Select your AI provider. Groq is free and fast!', 'wp-ai-assistant'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="wpaia_api_key"><?php _e('API Key', 'wp-ai-assistant'); ?></label>
                                    </th>
                                    <td>
                                        <input type="password" name="wpaia_settings[api_key]" id="wpaia_api_key" 
                                               value="<?php echo esc_attr($options['api_key'] ?? ''); ?>" 
                                               class="regular-text">
                                        <button type="button" class="button wpaia-toggle-visibility" data-target="wpaia_api_key">
                                            <span class="dashicons dashicons-visibility"></span>
                                        </button>
                                        <button type="button" class="button wpaia-test-connection">
                                            <?php _e('Test Connection', 'wp-ai-assistant'); ?>
                                        </button>
                                        <span class="wpaia-test-result"></span>
                                        <p class="description">
                                            <?php _e('Get your API key from:', 'wp-ai-assistant'); ?>
                                            <a href="https://console.groq.com/keys" target="_blank">Groq</a> |
                                            <a href="https://platform.openai.com/api-keys" target="_blank">OpenAI</a> |
                                            <a href="https://console.anthropic.com/" target="_blank">Anthropic</a>
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="wpaia_model"><?php _e('Model', 'wp-ai-assistant'); ?></label>
                                    </th>
                                    <td>
                                        <select name="wpaia_settings[model]" id="wpaia_model">
                                            <optgroup label="Groq">
                                                <option value="llama-3.3-70b-versatile" <?php selected($options['model'] ?? '', 'llama-3.3-70b-versatile'); ?>>Llama 3.3 70B (Recommended)</option>
                                                <option value="llama-3.1-70b-versatile" <?php selected($options['model'] ?? '', 'llama-3.1-70b-versatile'); ?>>Llama 3.1 70B</option>
                                                <option value="llama-3.1-8b-instant" <?php selected($options['model'] ?? '', 'llama-3.1-8b-instant'); ?>>Llama 3.1 8B (Fast)</option>
                                                <option value="mixtral-8x7b-32768" <?php selected($options['model'] ?? '', 'mixtral-8x7b-32768'); ?>>Mixtral 8x7B</option>
                                            </optgroup>
                                            <optgroup label="OpenAI">
                                                <option value="gpt-4o" <?php selected($options['model'] ?? '', 'gpt-4o'); ?>>GPT-4o</option>
                                                <option value="gpt-4o-mini" <?php selected($options['model'] ?? '', 'gpt-4o-mini'); ?>>GPT-4o Mini</option>
                                                <option value="gpt-4-turbo" <?php selected($options['model'] ?? '', 'gpt-4-turbo'); ?>>GPT-4 Turbo</option>
                                            </optgroup>
                                            <optgroup label="Anthropic">
                                                <option value="claude-3-5-sonnet-20241022" <?php selected($options['model'] ?? '', 'claude-3-5-sonnet-20241022'); ?>>Claude 3.5 Sonnet</option>
                                                <option value="claude-3-haiku-20240307" <?php selected($options['model'] ?? '', 'claude-3-haiku-20240307'); ?>>Claude 3 Haiku (Fast)</option>
                                            </optgroup>
                                        </select>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        
                        <!-- Context Sources -->
                        <div class="wpaia-card">
                            <h2><?php _e('Context Sources', 'wp-ai-assistant'); ?></h2>
                            <p class="description"><?php _e('Select what information the AI assistant can access to answer questions.', 'wp-ai-assistant'); ?></p>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php _e('Include in Context', 'wp-ai-assistant'); ?></th>
                                    <td>
                                        <fieldset>
                                            <label>
                                                <input type="checkbox" name="wpaia_settings[include_pages]" value="1" 
                                                       <?php checked(!empty($options['include_pages'])); ?>>
                                                <?php _e('Pages (About, Services, Contact, etc.)', 'wp-ai-assistant'); ?>
                                            </label><br>
                                            
                                            <label>
                                                <input type="checkbox" name="wpaia_settings[include_products]" value="1" 
                                                       <?php checked(!empty($options['include_products'])); ?>
                                                       <?php disabled(!class_exists('WooCommerce')); ?>>
                                                <?php _e('WooCommerce Products', 'wp-ai-assistant'); ?>
                                                <?php if (!class_exists('WooCommerce')): ?>
                                                    <span class="description">(<?php _e('WooCommerce not installed', 'wp-ai-assistant'); ?>)</span>
                                                <?php endif; ?>
                                            </label><br>
                                            
                                            <label>
                                                <input type="checkbox" name="wpaia_settings[include_faqs]" value="1" 
                                                       <?php checked(!empty($options['include_faqs'])); ?>>
                                                <?php _e('FAQ Pages', 'wp-ai-assistant'); ?>
                                            </label><br>
                                            
                                            <label>
                                                <input type="checkbox" name="wpaia_settings[enable_order_lookup]" value="1" 
                                                       <?php checked(!empty($options['enable_order_lookup'])); ?>
                                                       <?php disabled(!class_exists('WooCommerce')); ?>>
                                                <?php _e('Order Lookup (requires verification)', 'wp-ai-assistant'); ?>
                                                <?php if (!class_exists('WooCommerce')): ?>
                                                    <span class="description">(<?php _e('WooCommerce not installed', 'wp-ai-assistant'); ?>)</span>
                                                <?php endif; ?>
                                            </label>
                                        </fieldset>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="wpaia_custom_context"><?php _e('Custom Knowledge Base', 'wp-ai-assistant'); ?></label>
                                    </th>
                                    <td>
                                        <textarea name="wpaia_settings[custom_context]" id="wpaia_custom_context" 
                                                  rows="8" class="large-text"><?php echo esc_textarea($options['custom_context'] ?? ''); ?></textarea>
                                        <p class="description"><?php _e('Add custom information the AI should know (business hours, policies, special instructions, etc.)', 'wp-ai-assistant'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="wpaia_system_prompt"><?php _e('System Prompt', 'wp-ai-assistant'); ?></label>
                                    </th>
                                    <td>
                                        <textarea name="wpaia_settings[system_prompt]" id="wpaia_system_prompt" 
                                                  rows="6" class="large-text" 
                                                  placeholder="<?php esc_attr_e('You are a helpful assistant for {site_name}. Be friendly, concise, and helpful.', 'wp-ai-assistant'); ?>"><?php echo esc_textarea($options['system_prompt'] ?? ''); ?></textarea>
                                        <p class="description"><?php _e('Customize how the AI behaves. Use {site_name} as placeholder. Leave empty for default.', 'wp-ai-assistant'); ?></p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        
                        <!-- Lead Capture & Conversations -->
                        <div class="wpaia-card">
                            <h2><?php _e('Lead Capture & Conversations', 'wp-ai-assistant'); ?></h2>
                            <p class="description"><?php _e('Save conversations and capture visitor contact information.', 'wp-ai-assistant'); ?></p>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php _e('Save Conversations', 'wp-ai-assistant'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="wpaia_settings[save_conversations]" value="1" 
                                                   <?php checked(!empty($options['save_conversations'])); ?>>
                                            <?php _e('Save all conversations to database', 'wp-ai-assistant'); ?>
                                        </label>
                                        <p class="description"><?php _e('Store conversation history for review in the admin panel.', 'wp-ai-assistant'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php _e('Lead Capture', 'wp-ai-assistant'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="wpaia_settings[lead_capture_enabled]" value="1" 
                                                   id="wpaia_lead_capture_enabled"
                                                   <?php checked(!empty($options['lead_capture_enabled'])); ?>>
                                            <?php _e('Request visitor contact information', 'wp-ai-assistant'); ?>
                                        </label>
                                        <p class="description"><?php _e('Ask visitors for their contact details during chat.', 'wp-ai-assistant'); ?></p>
                                    </td>
                                </tr>
                                <tr class="wpaia-lead-capture-option" style="<?php echo empty($options['lead_capture_enabled']) ? 'display:none;' : ''; ?>">
                                    <th scope="row">
                                        <label for="wpaia_lead_capture_mode"><?php _e('When to Ask', 'wp-ai-assistant'); ?></label>
                                    </th>
                                    <td>
                                        <select name="wpaia_settings[lead_capture_mode]" id="wpaia_lead_capture_mode">
                                            <option value="before" <?php selected($options['lead_capture_mode'] ?? 'after', 'before'); ?>><?php _e('Before chat starts', 'wp-ai-assistant'); ?></option>
                                            <option value="after" <?php selected($options['lead_capture_mode'] ?? 'after', 'after'); ?>><?php _e('After X messages', 'wp-ai-assistant'); ?></option>
                                            <option value="end" <?php selected($options['lead_capture_mode'] ?? 'after', 'end'); ?>><?php _e('When closing chat', 'wp-ai-assistant'); ?></option>
                                        </select>
                                    </td>
                                </tr>
                                <tr class="wpaia-lead-capture-option wpaia-after-messages-option" style="<?php echo (empty($options['lead_capture_enabled']) || ($options['lead_capture_mode'] ?? 'after') !== 'after') ? 'display:none;' : ''; ?>">
                                    <th scope="row">
                                        <label for="wpaia_lead_capture_after_messages"><?php _e('After Messages', 'wp-ai-assistant'); ?></label>
                                    </th>
                                    <td>
                                        <input type="number" name="wpaia_settings[lead_capture_after_messages]" id="wpaia_lead_capture_after_messages" 
                                               value="<?php echo esc_attr($options['lead_capture_after_messages'] ?? 3); ?>" 
                                               min="1" max="20" class="small-text">
                                        <span class="description"><?php _e('messages exchanged', 'wp-ai-assistant'); ?></span>
                                    </td>
                                </tr>
                                <tr class="wpaia-lead-capture-option" style="<?php echo empty($options['lead_capture_enabled']) ? 'display:none;' : ''; ?>">
                                    <th scope="row"><?php _e('Fields to Capture', 'wp-ai-assistant'); ?></th>
                                    <td>
                                        <fieldset>
                                            <?php $capture_fields = $options['lead_capture_fields'] ?? array('email'); ?>
                                            <label>
                                                <input type="checkbox" name="wpaia_settings[lead_capture_fields][]" value="email" 
                                                       <?php checked(in_array('email', $capture_fields)); ?>>
                                                <?php _e('Email address', 'wp-ai-assistant'); ?>
                                            </label><br>
                                            <label>
                                                <input type="checkbox" name="wpaia_settings[lead_capture_fields][]" value="phone" 
                                                       <?php checked(in_array('phone', $capture_fields)); ?>>
                                                <?php _e('Phone number', 'wp-ai-assistant'); ?>
                                            </label><br>
                                            <label>
                                                <input type="checkbox" name="wpaia_settings[lead_capture_fields][]" value="name" 
                                                       <?php checked(in_array('name', $capture_fields)); ?>>
                                                <?php _e('Name', 'wp-ai-assistant'); ?>
                                            </label>
                                        </fieldset>
                                    </td>
                                </tr>
                                <tr class="wpaia-lead-capture-option" style="<?php echo empty($options['lead_capture_enabled']) ? 'display:none;' : ''; ?>">
                                    <th scope="row">
                                        <label for="wpaia_lead_capture_title"><?php _e('Form Title', 'wp-ai-assistant'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" name="wpaia_settings[lead_capture_title]" id="wpaia_lead_capture_title" 
                                               value="<?php echo esc_attr($options['lead_capture_title'] ?? ''); ?>" 
                                               class="regular-text"
                                               placeholder="<?php esc_attr_e('Stay in touch!', 'wp-ai-assistant'); ?>">
                                    </td>
                                </tr>
                                <tr class="wpaia-lead-capture-option" style="<?php echo empty($options['lead_capture_enabled']) ? 'display:none;' : ''; ?>">
                                    <th scope="row">
                                        <label for="wpaia_lead_capture_description"><?php _e('Form Description', 'wp-ai-assistant'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" name="wpaia_settings[lead_capture_description]" id="wpaia_lead_capture_description" 
                                               value="<?php echo esc_attr($options['lead_capture_description'] ?? ''); ?>" 
                                               class="regular-text"
                                               placeholder="<?php esc_attr_e('Leave your contact info and we\'ll get back to you.', 'wp-ai-assistant'); ?>">
                                    </td>
                                </tr>
                            </table>
                        </div>
                        
                        <!-- GDPR Compliance -->
                        <div class="wpaia-card">
                            <h2><?php _e('GDPR Compliance', 'wp-ai-assistant'); ?></h2>
                            <p class="description"><?php _e('Settings for GDPR/privacy compliance when collecting user data.', 'wp-ai-assistant'); ?></p>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="wpaia_gdpr_consent_enabled"><?php _e('Require Consent', 'wp-ai-assistant'); ?></label>
                                    </th>
                                    <td>
                                        <label class="wpaia-switch">
                                            <input type="checkbox" name="wpaia_settings[gdpr_consent_enabled]" value="1" 
                                                   id="wpaia_gdpr_consent_enabled"
                                                   <?php checked(!empty($options['gdpr_consent_enabled'])); ?>>
                                            <span class="wpaia-slider"></span>
                                        </label>
                                        <p class="description"><?php _e('Require users to accept privacy consent before submitting their contact information.', 'wp-ai-assistant'); ?></p>
                                    </td>
                                </tr>
                                <tr class="wpaia-gdpr-option" style="<?php echo empty($options['gdpr_consent_enabled']) ? 'display:none;' : ''; ?>">
                                    <th scope="row">
                                        <label for="wpaia_gdpr_consent_text"><?php _e('Consent Text', 'wp-ai-assistant'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" name="wpaia_settings[gdpr_consent_text]" id="wpaia_gdpr_consent_text" 
                                               value="<?php echo esc_attr($options['gdpr_consent_text'] ?? ''); ?>" 
                                               class="regular-text"
                                               placeholder="<?php esc_attr_e('I agree to the storage of my data for support purposes.', 'wp-ai-assistant'); ?>">
                                    </td>
                                </tr>
                                <tr class="wpaia-gdpr-option" style="<?php echo empty($options['gdpr_consent_enabled']) ? 'display:none;' : ''; ?>">
                                    <th scope="row">
                                        <label for="wpaia_gdpr_link_text"><?php _e('Privacy Link Text', 'wp-ai-assistant'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" name="wpaia_settings[gdpr_link_text]" id="wpaia_gdpr_link_text" 
                                               value="<?php echo esc_attr($options['gdpr_link_text'] ?? ''); ?>" 
                                               class="regular-text"
                                               placeholder="<?php esc_attr_e('Privacy Policy', 'wp-ai-assistant'); ?>">
                                    </td>
                                </tr>
                                <tr class="wpaia-gdpr-option" style="<?php echo empty($options['gdpr_consent_enabled']) ? 'display:none;' : ''; ?>">
                                    <th scope="row">
                                        <label for="wpaia_gdpr_link_url"><?php _e('Privacy Link URL', 'wp-ai-assistant'); ?></label>
                                    </th>
                                    <td>
                                        <input type="url" name="wpaia_settings[gdpr_link_url]" id="wpaia_gdpr_link_url" 
                                               value="<?php echo esc_url($options['gdpr_link_url'] ?? ''); ?>" 
                                               class="regular-text"
                                               placeholder="<?php echo esc_url(get_privacy_policy_url() ?: site_url('/privacy-policy/')); ?>">
                                        <p class="description"><?php _e('Leave empty to use your site\'s default Privacy Policy page.', 'wp-ai-assistant'); ?></p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        
                        <!-- Appearance -->
                        <div class="wpaia-card">
                            <h2><?php _e('Appearance', 'wp-ai-assistant'); ?></h2>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="wpaia_welcome_message"><?php _e('Welcome Message', 'wp-ai-assistant'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" name="wpaia_settings[welcome_message]" id="wpaia_welcome_message" 
                                               value="<?php echo esc_attr($options['welcome_message'] ?? ''); ?>" 
                                               class="regular-text"
                                               placeholder="<?php esc_attr_e('Hello! How can I help you today?', 'wp-ai-assistant'); ?>">
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="wpaia_widget_position"><?php _e('Widget Position', 'wp-ai-assistant'); ?></label>
                                    </th>
                                    <td>
                                        <select name="wpaia_settings[widget_position]" id="wpaia_widget_position">
                                            <option value="bottom-right" <?php selected($options['widget_position'] ?? '', 'bottom-right'); ?>><?php _e('Bottom Right', 'wp-ai-assistant'); ?></option>
                                            <option value="bottom-left" <?php selected($options['widget_position'] ?? '', 'bottom-left'); ?>><?php _e('Bottom Left', 'wp-ai-assistant'); ?></option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="wpaia_primary_color"><?php _e('Primary Color', 'wp-ai-assistant'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" name="wpaia_settings[primary_color]" id="wpaia_primary_color" 
                                               value="<?php echo esc_attr($options['primary_color'] ?? '#0073aa'); ?>" 
                                               class="wpaia-color-picker">
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="wpaia_chat_icon"><?php _e('Chat Button Icon', 'wp-ai-assistant'); ?></label>
                                    </th>
                                    <td>
                                        <select name="wpaia_settings[chat_icon]" id="wpaia_chat_icon">
                                            <option value="chat" <?php selected($options['chat_icon'] ?? '', 'chat'); ?>><?php _e('💬 Chat Bubble', 'wp-ai-assistant'); ?></option>
                                            <option value="message" <?php selected($options['chat_icon'] ?? '', 'message'); ?>><?php _e('✉️ Message', 'wp-ai-assistant'); ?></option>
                                            <option value="help" <?php selected($options['chat_icon'] ?? '', 'help'); ?>><?php _e('❓ Help Circle', 'wp-ai-assistant'); ?></option>
                                            <option value="bot" <?php selected($options['chat_icon'] ?? '', 'bot'); ?>><?php _e('🤖 Robot', 'wp-ai-assistant'); ?></option>
                                            <option value="headset" <?php selected($options['chat_icon'] ?? '', 'headset'); ?>><?php _e('🎧 Support Headset', 'wp-ai-assistant'); ?></option>
                                            <option value="custom" <?php selected($options['chat_icon'] ?? '', 'custom'); ?>><?php _e('🖼️ Custom Image', 'wp-ai-assistant'); ?></option>
                                        </select>
                                    </td>
                                </tr>
                                <tr class="wpaia-custom-icon-row" style="<?php echo ($options['chat_icon'] ?? '') !== 'custom' ? 'display:none;' : ''; ?>">
                                    <th scope="row">
                                        <label for="wpaia_custom_icon"><?php _e('Custom Icon Image', 'wp-ai-assistant'); ?></label>
                                    </th>
                                    <td>
                                        <input type="hidden" name="wpaia_settings[custom_icon_url]" id="wpaia_custom_icon_url" 
                                               value="<?php echo esc_url($options['custom_icon_url'] ?? ''); ?>">
                                        <div class="wpaia-custom-icon-preview">
                                            <?php if (!empty($options['custom_icon_url'])): ?>
                                                <img src="<?php echo esc_url($options['custom_icon_url']); ?>" alt="Custom icon" style="max-width: 60px; max-height: 60px;">
                                            <?php endif; ?>
                                        </div>
                                        <button type="button" class="button wpaia-upload-icon"><?php _e('Upload Image', 'wp-ai-assistant'); ?></button>
                                        <button type="button" class="button wpaia-remove-icon" <?php echo empty($options['custom_icon_url']) ? 'style="display:none;"' : ''; ?>><?php _e('Remove', 'wp-ai-assistant'); ?></button>
                                        <p class="description"><?php _e('Recommended: 120x120px PNG or SVG with transparent background.', 'wp-ai-assistant'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="wpaia_header_avatar"><?php _e('Header Avatar', 'wp-ai-assistant'); ?></label>
                                    </th>
                                    <td>
                                        <input type="hidden" name="wpaia_settings[header_avatar_url]" id="wpaia_header_avatar_url" 
                                               value="<?php echo esc_url($options['header_avatar_url'] ?? ''); ?>">
                                        <div class="wpaia-avatar-preview">
                                            <?php if (!empty($options['header_avatar_url'])): ?>
                                                <img src="<?php echo esc_url($options['header_avatar_url']); ?>" alt="Avatar" style="max-width: 50px; max-height: 50px; border-radius: 50%;">
                                            <?php else: ?>
                                                <span class="description"><?php _e('Using default AI icon', 'wp-ai-assistant'); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <button type="button" class="button wpaia-upload-avatar"><?php _e('Upload Avatar', 'wp-ai-assistant'); ?></button>
                                        <button type="button" class="button wpaia-remove-avatar" <?php echo empty($options['header_avatar_url']) ? 'style="display:none;"' : ''; ?>><?php _e('Remove', 'wp-ai-assistant'); ?></button>
                                        <p class="description"><?php _e('Avatar shown in chat header. Recommended: 100x100px.', 'wp-ai-assistant'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="wpaia_rate_limit"><?php _e('Rate Limit', 'wp-ai-assistant'); ?></label>
                                    </th>
                                    <td>
                                        <input type="number" name="wpaia_settings[rate_limit]" id="wpaia_rate_limit" 
                                               value="<?php echo esc_attr($options['rate_limit'] ?? 20); ?>" 
                                               min="1" max="100" class="small-text">
                                        <span class="description"><?php _e('requests per minute per user', 'wp-ai-assistant'); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="wpaia_hide_branding"><?php _e('Hide Branding', 'wp-ai-assistant'); ?></label>
                                    </th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="wpaia_settings[hide_branding]" id="wpaia_hide_branding" value="1" 
                                                   <?php checked(!empty($options['hide_branding'])); ?>>
                                            <?php _e('Hide "Powered by" footer', 'wp-ai-assistant'); ?>
                                        </label>
                                        <p class="description"><?php _e('Premium feature - Remove branding from the chat widget.', 'wp-ai-assistant'); ?></p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        
                        <?php submit_button(__('Save Settings', 'wp-ai-assistant')); ?>
                    </form>
                </div>
                
                <!-- Sidebar -->
                <div class="wpaia-admin-sidebar">
                    <!-- License Card -->
                    <div class="wpaia-card wpaia-license-card">
                        <h3><?php _e('Premium License', 'wp-ai-assistant'); ?></h3>
                        <?php 
                        $license_key = get_option('wpaia_license_key', '');
                        $license_email = get_option('wpaia_license_email', '');
                        $is_valid = WP_AI_Assistant::has_valid_license();
                        
                        if ($is_valid): ?>
                            <div class="wpaia-license-status wpaia-license-active">
                                <span class="dashicons dashicons-yes-alt"></span>
                                <?php _e('License Active', 'wp-ai-assistant'); ?>
                            </div>
                            <p class="wpaia-license-email"><?php echo esc_html($license_email); ?></p>
                            <input type="text" value="<?php echo esc_attr(substr($license_key, 0, 8)); ?>********" readonly class="regular-text">
                            <button type="button" class="button wpaia-deactivate-license">
                                <?php _e('Deactivate', 'wp-ai-assistant'); ?>
                            </button>
                        <?php else: ?>
                            <div class="wpaia-license-status wpaia-license-inactive">
                                <span class="dashicons dashicons-lock"></span>
                                <?php _e('No License', 'wp-ai-assistant'); ?>
                            </div>
                            <p><?php _e('Unlock premium features:', 'wp-ai-assistant'); ?></p>
                            <ul class="wpaia-premium-features">
                                <li>Remove branding footer</li>
                                <li>Priority support</li>
                                <li>Future premium features</li>
                            </ul>
                            <input type="text" id="wpaia_license_key_input" placeholder="Enter license key" class="regular-text">
                            <div class="wpaia-license-buttons">
                                <button type="button" class="button button-primary wpaia-activate-license">
                                    <?php _e('Activate', 'wp-ai-assistant'); ?>
                                </button>
                                <a href="https://carlosllamax.com/plugins/wp-ai-assistant#pricing" target="_blank" class="button">
                                    Buy License - 99 EUR
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="wpaia-card">
                        <h3><?php _e('Quick Start', 'wp-ai-assistant'); ?></h3>
                        <ol>
                            <li><?php _e('Get a free API key from Groq', 'wp-ai-assistant'); ?></li>
                            <li><?php _e('Paste your API key above', 'wp-ai-assistant'); ?></li>
                            <li><?php _e('Enable the assistant', 'wp-ai-assistant'); ?></li>
                            <li><?php _e('Customize the appearance', 'wp-ai-assistant'); ?></li>
                        </ol>
                        <a href="https://console.groq.com/keys" target="_blank" class="button button-primary">
                            <?php _e('Get Free Groq API Key', 'wp-ai-assistant'); ?>
                        </a>
                    </div>
                    
                    <div class="wpaia-card">
                        <h3><?php _e('Preview', 'wp-ai-assistant'); ?></h3>
                        <div class="wpaia-preview-widget">
                            <div class="wpaia-preview-bubble" style="background-color: <?php echo esc_attr($options['primary_color'] ?? '#0073aa'); ?>">
                                <span class="dashicons dashicons-format-chat"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render section descriptions
     */
    public function render_general_section() {
        echo '<p>' . __('Configure the general settings for your AI assistant.', 'wp-ai-assistant') . '</p>';
    }
    
    public function render_api_section() {
        echo '<p>' . __('Enter your AI provider API key.', 'wp-ai-assistant') . '</p>';
    }
    
    public function render_context_section() {
        echo '<p>' . __('Choose what information the AI can access.', 'wp-ai-assistant') . '</p>';
    }
    
    public function render_appearance_section() {
        echo '<p>' . __('Customize the look and feel of the chat widget.', 'wp-ai-assistant') . '</p>';
    }
}
