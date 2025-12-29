<?php
/**
 * Conversations Admin Page
 *
 * @package WP_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPAIA_Conversations_Admin {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_submenu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_ajax_wpaia_get_conversation', array($this, 'ajax_get_conversation'));
        add_action('wp_ajax_wpaia_delete_conversation', array($this, 'ajax_delete_conversation'));
        add_action('wp_ajax_wpaia_export_conversations', array($this, 'ajax_export_conversations'));
    }

    /**
     * Add submenu page
     */
    public function add_submenu() {
        add_submenu_page(
            'wp-ai-assistant',
            __('Conversations', 'wp-ai-assistant'),
            __('Conversations', 'wp-ai-assistant'),
            'manage_options',
            'wpaia-conversations',
            array($this, 'render_page')
        );
    }

    /**
     * Enqueue assets
     */
    public function enqueue_assets($hook) {
        if ($hook !== 'ai-assistant_page_wpaia-conversations') {
            return;
        }

        wp_enqueue_style(
            'wpaia-conversations',
            WPAIA_PLUGIN_URL . 'assets/css/conversations.css',
            array(),
            WPAIA_VERSION
        );

        wp_enqueue_script(
            'wpaia-conversations',
            WPAIA_PLUGIN_URL . 'assets/js/conversations.js',
            array('jquery'),
            WPAIA_VERSION,
            true
        );

        wp_localize_script('wpaia-conversations', 'wpaiaConv', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wpaia_conversations'),
            'strings' => array(
                'confirmDelete' => __('Are you sure you want to delete this conversation?', 'wp-ai-assistant'),
                'loading' => __('Loading...', 'wp-ai-assistant'),
                'noMessages' => __('No messages in this conversation.', 'wp-ai-assistant'),
            )
        ));
    }

    /**
     * Render admin page
     */
    public function render_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $page = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        
        $data = WPAIA_Database::get_all_conversations(array(
            'page' => $page,
            'per_page' => 20,
            'search' => $search,
        ));
        
        $stats = WPAIA_Database::get_stats();
        ?>
        <div class="wrap wpaia-conversations-wrap">
            <h1><?php _e('Conversations', 'wp-ai-assistant'); ?></h1>
            
            <!-- Stats Cards -->
            <div class="wpaia-stats-grid">
                <div class="wpaia-stat-card">
                    <span class="wpaia-stat-number"><?php echo number_format($stats['total_conversations']); ?></span>
                    <span class="wpaia-stat-label"><?php _e('Total Conversations', 'wp-ai-assistant'); ?></span>
                </div>
                <div class="wpaia-stat-card">
                    <span class="wpaia-stat-number"><?php echo number_format($stats['total_leads']); ?></span>
                    <span class="wpaia-stat-label"><?php _e('Leads Captured', 'wp-ai-assistant'); ?></span>
                </div>
                <div class="wpaia-stat-card">
                    <span class="wpaia-stat-number"><?php echo number_format($stats['conversations_today']); ?></span>
                    <span class="wpaia-stat-label"><?php _e('Today', 'wp-ai-assistant'); ?></span>
                </div>
                <div class="wpaia-stat-card">
                    <span class="wpaia-stat-number"><?php echo number_format($stats['conversations_week']); ?></span>
                    <span class="wpaia-stat-label"><?php _e('This Week', 'wp-ai-assistant'); ?></span>
                </div>
            </div>

            <!-- Search and Actions -->
            <div class="wpaia-table-actions">
                <form method="get" class="wpaia-search-form">
                    <input type="hidden" name="page" value="wpaia-conversations">
                    <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search by email or phone...', 'wp-ai-assistant'); ?>">
                    <button type="submit" class="button"><?php _e('Search', 'wp-ai-assistant'); ?></button>
                </form>
                <div class="wpaia-actions">
                    <button type="button" class="button wpaia-export-btn">
                        <span class="dashicons dashicons-download"></span>
                        <?php _e('Export CSV', 'wp-ai-assistant'); ?>
                    </button>
                </div>
            </div>

            <!-- Conversations Table -->
            <table class="wp-list-table widefat fixed striped wpaia-conversations-table">
                <thead>
                    <tr>
                        <th class="column-id"><?php _e('ID', 'wp-ai-assistant'); ?></th>
                        <th class="column-contact"><?php _e('Contact', 'wp-ai-assistant'); ?></th>
                        <th class="column-messages"><?php _e('Messages', 'wp-ai-assistant'); ?></th>
                        <th class="column-started"><?php _e('Started', 'wp-ai-assistant'); ?></th>
                        <th class="column-last"><?php _e('Last Message', 'wp-ai-assistant'); ?></th>
                        <th class="column-actions"><?php _e('Actions', 'wp-ai-assistant'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($data['conversations'])): ?>
                        <tr>
                            <td colspan="6" class="wpaia-no-data">
                                <?php _e('No conversations yet. They will appear here once visitors start chatting.', 'wp-ai-assistant'); ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($data['conversations'] as $conv): ?>
                            <tr data-session="<?php echo esc_attr($conv->session_id); ?>">
                                <td class="column-id">
                                    <strong>#<?php echo esc_html($conv->lead_id ?: substr($conv->session_id, 0, 8)); ?></strong>
                                </td>
                                <td class="column-contact">
                                    <?php if ($conv->email || $conv->phone): ?>
                                        <?php if ($conv->name): ?>
                                            <strong><?php echo esc_html($conv->name); ?></strong><br>
                                        <?php endif; ?>
                                        <?php if ($conv->email): ?>
                                            <a href="mailto:<?php echo esc_attr($conv->email); ?>"><?php echo esc_html($conv->email); ?></a><br>
                                        <?php endif; ?>
                                        <?php if ($conv->phone): ?>
                                            <a href="tel:<?php echo esc_attr($conv->phone); ?>"><?php echo esc_html($conv->phone); ?></a>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="wpaia-anonymous"><?php _e('Anonymous', 'wp-ai-assistant'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="column-messages">
                                    <span class="wpaia-badge"><?php echo absint($conv->message_count); ?></span>
                                </td>
                                <td class="column-started">
                                    <?php echo esc_html(human_time_diff(strtotime($conv->started_at))); ?> ago
                                </td>
                                <td class="column-last">
                                    <?php echo esc_html(human_time_diff(strtotime($conv->last_message))); ?> ago
                                </td>
                                <td class="column-actions">
                                    <button type="button" class="button button-small wpaia-view-btn" data-session="<?php echo esc_attr($conv->session_id); ?>">
                                        <span class="dashicons dashicons-visibility"></span>
                                        <?php _e('View', 'wp-ai-assistant'); ?>
                                    </button>
                                    <button type="button" class="button button-small wpaia-delete-btn" data-session="<?php echo esc_attr($conv->session_id); ?>">
                                        <span class="dashicons dashicons-trash"></span>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php if ($data['pages'] > 1): ?>
                <div class="wpaia-pagination">
                    <?php
                    $pagination_args = array(
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'current' => $page,
                        'total' => $data['pages'],
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;',
                    );
                    echo paginate_links($pagination_args);
                    ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Conversation Modal -->
        <div id="wpaia-conversation-modal" class="wpaia-modal" style="display:none;">
            <div class="wpaia-modal-content">
                <div class="wpaia-modal-header">
                    <h2><?php _e('Conversation Details', 'wp-ai-assistant'); ?></h2>
                    <button type="button" class="wpaia-modal-close">&times;</button>
                </div>
                <div class="wpaia-modal-body">
                    <div class="wpaia-contact-info"></div>
                    <div class="wpaia-messages-container"></div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX: Get conversation messages
     */
    public function ajax_get_conversation() {
        check_ajax_referer('wpaia_conversations', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        $session_id = sanitize_text_field($_POST['session_id'] ?? '');
        
        if (empty($session_id)) {
            wp_send_json_error('Invalid session');
        }
        
        $messages = WPAIA_Database::get_conversation($session_id);
        
        // Get lead info
        global $wpdb;
        $leads_table = WPAIA_Database::get_leads_table();
        $lead = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $leads_table WHERE session_id = %s",
            $session_id
        ));
        
        wp_send_json_success(array(
            'lead' => $lead,
            'messages' => $messages,
        ));
    }

    /**
     * AJAX: Delete conversation
     */
    public function ajax_delete_conversation() {
        check_ajax_referer('wpaia_conversations', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        $session_id = sanitize_text_field($_POST['session_id'] ?? '');
        
        if (empty($session_id)) {
            wp_send_json_error('Invalid session');
        }
        
        WPAIA_Database::delete_conversation($session_id);
        
        wp_send_json_success();
    }

    /**
     * AJAX: Export conversations
     */
    public function ajax_export_conversations() {
        check_ajax_referer('wpaia_conversations', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        global $wpdb;
        $conv_table = WPAIA_Database::get_conversations_table();
        $leads_table = WPAIA_Database::get_leads_table();
        
        $data = $wpdb->get_results("
            SELECT 
                l.id as lead_id,
                l.email,
                l.phone,
                l.name,
                l.created_at as lead_created,
                c.role,
                c.message,
                c.created_at as message_time
            FROM $conv_table c
            LEFT JOIN $leads_table l ON c.session_id = l.session_id
            ORDER BY c.session_id, c.created_at ASC
        ");
        
        wp_send_json_success(array('data' => $data));
    }
}
