<?php
/**
 * WP AI Assistant Uninstall
 * 
 * Cleanup all plugin data when uninstalled
 *
 * @package WP_AI_Assistant
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Clean up all plugin data
 */
function wpaia_uninstall_cleanup() {
    global $wpdb;
    
    // Delete options
    delete_option('wpaia_settings');
    delete_option('wpaia_license_key');
    delete_option('wpaia_license_email');
    delete_option('wpaia_db_version');
    
    // Delete transients
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wpaia_%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_wpaia_%'");
    
    // Delete site transients (for multisite)
    $wpdb->query("DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE '_site_transient_wpaia_%'");
    $wpdb->query("DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE '_site_transient_timeout_wpaia_%'");
    
    // Drop custom tables
    $tables = array(
        $wpdb->prefix . 'wpaia_leads',
        $wpdb->prefix . 'wpaia_conversations',
        $wpdb->prefix . 'wpaia_logs',
    );
    
    foreach ($tables as $table) {
        $wpdb->query("DROP TABLE IF EXISTS $table");
    }
    
    // Clear any scheduled hooks
    wp_clear_scheduled_hook('wpaia_daily_license_check');
    wp_clear_scheduled_hook('wpaia_cleanup_logs');
    
    // Clear rewrite rules
    flush_rewrite_rules();
}

// Run cleanup
wpaia_uninstall_cleanup();
