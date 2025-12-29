<?php
/**
 * Context Builder
 * Builds context from WordPress data for AI
 * 
 * @package WP_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPAIA_Context_Builder {
    
    /**
     * Build complete context for AI
     */
    public static function build(): string {
        $context_parts = array();
        
        // Site info
        $context_parts[] = self::get_site_info();
        
        // Custom context
        $custom = WP_AI_Assistant::get_option('custom_context');
        if (!empty($custom)) {
            $context_parts[] = "## Additional Information\n" . $custom;
        }
        
        // Pages
        if (WP_AI_Assistant::get_option('include_pages')) {
            $pages_context = self::get_pages_context();
            if (!empty($pages_context)) {
                $context_parts[] = $pages_context;
            }
        }
        
        // WooCommerce Products
        if (WP_AI_Assistant::get_option('include_products') && class_exists('WooCommerce')) {
            $products_context = self::get_products_context();
            if (!empty($products_context)) {
                $context_parts[] = $products_context;
            }
        }
        
        // FAQs
        if (WP_AI_Assistant::get_option('include_faqs')) {
            $faqs_context = self::get_faqs_context();
            if (!empty($faqs_context)) {
                $context_parts[] = $faqs_context;
            }
        }
        
        return implode("\n\n", array_filter($context_parts));
    }
    
    /**
     * Get site basic info
     */
    private static function get_site_info(): string {
        $site_name = get_bloginfo('name');
        $site_desc = get_bloginfo('description');
        $site_url = home_url();
        
        $info = "## Website Information\n";
        $info .= "- Site Name: {$site_name}\n";
        $info .= "- Description: {$site_desc}\n";
        $info .= "- URL: {$site_url}\n";
        
        // Get current language if WPML
        if (defined('ICL_LANGUAGE_CODE')) {
            $info .= "- Current Language: " . ICL_LANGUAGE_CODE . "\n";
        }
        
        return $info;
    }
    
    /**
     * Get pages context
     */
    private static function get_pages_context(): string {
        $pages = get_posts(array(
            'post_type' => 'page',
            'post_status' => 'publish',
            'posts_per_page' => 20,
            'orderby' => 'menu_order',
            'order' => 'ASC',
        ));
        
        if (empty($pages)) {
            return '';
        }
        
        $context = "## Website Pages\n";
        
        foreach ($pages as $page) {
            $title = $page->post_title;
            $url = get_permalink($page->ID);
            $excerpt = wp_trim_words(strip_tags($page->post_content), 100);
            
            $context .= "\n### {$title}\n";
            $context .= "URL: {$url}\n";
            $context .= "Content: {$excerpt}\n";
        }
        
        return $context;
    }
    
    /**
     * Get WooCommerce products context
     */
    private static function get_products_context(): string {
        if (!class_exists('WooCommerce')) {
            return '';
        }
        
        $products = wc_get_products(array(
            'status' => 'publish',
            'limit' => 50,
            'orderby' => 'popularity',
        ));
        
        if (empty($products)) {
            return '';
        }
        
        $context = "## Products/Services\n";
        
        foreach ($products as $product) {
            $name = $product->get_name();
            $price = $product->get_price();
            $currency = get_woocommerce_currency_symbol();
            $description = wp_trim_words($product->get_short_description() ?: $product->get_description(), 50);
            $url = $product->get_permalink();
            
            $context .= "\n### {$name}\n";
            $context .= "- Price: {$currency}{$price}\n";
            $context .= "- URL: {$url}\n";
            if (!empty($description)) {
                $context .= "- Description: {$description}\n";
            }
        }
        
        return $context;
    }
    
    /**
     * Get FAQs context
     */
    private static function get_faqs_context(): string {
        // Try to get FAQ page by slug
        $faq_page = get_page_by_path('frequently-asked-questions');
        if (!$faq_page) {
            $faq_page = get_page_by_path('faq');
        }
        if (!$faq_page) {
            $faq_page = get_page_by_path('preguntas-frecuentes');
        }
        
        if (!$faq_page) {
            return '';
        }
        
        $context = "## Frequently Asked Questions\n";
        $context .= strip_tags($faq_page->post_content);
        
        return $context;
    }
    
    /**
     * Get order context for verified user
     */
    public static function get_order_context(int $order_id, string $email): ?string {
        if (!class_exists('WooCommerce')) {
            return null;
        }
        
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return null;
        }
        
        // Verify email matches
        if (strtolower($order->get_billing_email()) !== strtolower($email)) {
            return null;
        }
        
        $status = wc_get_order_status_name($order->get_status());
        $date = $order->get_date_created()->date_i18n(get_option('date_format'));
        $total = $order->get_formatted_order_total();
        
        $context = "## Order Information (Verified)\n";
        $context .= "- Order Number: #{$order_id}\n";
        $context .= "- Status: {$status}\n";
        $context .= "- Date: {$date}\n";
        $context .= "- Total: {$total}\n";
        $context .= "\n### Items:\n";
        
        foreach ($order->get_items() as $item) {
            $context .= "- {$item->get_name()} x {$item->get_quantity()}\n";
        }
        
        // Get shipping info if available
        $shipping_method = $order->get_shipping_method();
        if (!empty($shipping_method)) {
            $context .= "\n### Shipping:\n";
            $context .= "- Method: {$shipping_method}\n";
        }
        
        // Get order notes (public only)
        $notes = wc_get_order_notes(array(
            'order_id' => $order_id,
            'type' => 'customer',
        ));
        
        if (!empty($notes)) {
            $context .= "\n### Order Notes:\n";
            foreach (array_slice($notes, 0, 5) as $note) {
                $context .= "- {$note->content}\n";
            }
        }
        
        return $context;
    }
    
    /**
     * Get system prompt
     */
    public static function get_system_prompt(): string {
        $custom_prompt = WP_AI_Assistant::get_option('system_prompt');
        $site_name = get_bloginfo('name');
        $site_url = home_url();
        
        if (!empty($custom_prompt)) {
            return str_replace('{site_name}', $site_name, $custom_prompt);
        }
        
        // Default system prompt
        $prompt = "You are a helpful customer service assistant for {$site_name}. ";
        $prompt .= "Be friendly, concise, and helpful. ";
        $prompt .= "Answer questions based on the provided context. ";
        $prompt .= "If you don't know something, say so politely and suggest contacting support. ";
        $prompt .= "When users ask about their order, ask them to provide their order number and email for verification. ";
        $prompt .= "Keep responses brief and to the point. Use bullet points when listing multiple items. ";
        
        // Important: Include links instruction
        $prompt .= "\n\nIMPORTANT - LINKS: When mentioning pages, services, or products, ALWAYS include the relevant link using markdown format [text](url). ";
        $prompt .= "The context includes URLs for each page and product - use them! ";
        $prompt .= "Example: 'You can check our [luggage storage service]({$site_url}/services/storage/)' or 'Visit our [contact page]({$site_url}/contact/)'. ";
        $prompt .= "This helps users navigate directly to the information they need.";
        
        // Add language instruction if WPML
        if (defined('ICL_LANGUAGE_CODE')) {
            $lang = ICL_LANGUAGE_CODE;
            $prompt .= "\n\nRespond in the same language as the user's message (current site language: {$lang}).";
        }
        
        return $prompt;
    }
}
