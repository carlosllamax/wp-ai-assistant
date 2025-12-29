<?php
/**
 * WooCommerce Integration
 * 
 * @package WP_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPAIA_WooCommerce {
    
    /**
     * Get order status label
     */
    public static function get_order_status_label(string $status): string {
        $statuses = wc_get_order_statuses();
        $key = 'wc-' . $status;
        return $statuses[$key] ?? $status;
    }
    
    /**
     * Get products summary for context
     */
    public static function get_products_summary(int $limit = 50): array {
        $products = wc_get_products(array(
            'status' => 'publish',
            'limit' => $limit,
            'orderby' => 'popularity',
        ));
        
        $summary = array();
        
        foreach ($products as $product) {
            $summary[] = array(
                'id' => $product->get_id(),
                'name' => $product->get_name(),
                'price' => $product->get_price(),
                'currency' => get_woocommerce_currency_symbol(),
                'url' => $product->get_permalink(),
                'description' => wp_trim_words($product->get_short_description(), 30),
                'in_stock' => $product->is_in_stock(),
            );
        }
        
        return $summary;
    }
    
    /**
     * Get order details for verified user
     */
    public static function get_order_details(int $order_id, string $email): ?array {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return null;
        }
        
        // Verify email
        if (strtolower($order->get_billing_email()) !== strtolower($email)) {
            return null;
        }
        
        $items = array();
        foreach ($order->get_items() as $item) {
            $items[] = array(
                'name' => $item->get_name(),
                'quantity' => $item->get_quantity(),
                'total' => $item->get_total(),
            );
        }
        
        return array(
            'id' => $order_id,
            'status' => self::get_order_status_label($order->get_status()),
            'date' => $order->get_date_created()->date_i18n(get_option('date_format')),
            'total' => $order->get_formatted_order_total(),
            'items' => $items,
            'shipping_method' => $order->get_shipping_method(),
            'billing_address' => $order->get_formatted_billing_address(),
            'shipping_address' => $order->get_formatted_shipping_address(),
        );
    }
    
    /**
     * Search orders by email
     */
    public static function search_orders_by_email(string $email, int $limit = 5): array {
        $orders = wc_get_orders(array(
            'billing_email' => $email,
            'limit' => $limit,
            'orderby' => 'date',
            'order' => 'DESC',
        ));
        
        $results = array();
        
        foreach ($orders as $order) {
            $results[] = array(
                'id' => $order->get_id(),
                'status' => self::get_order_status_label($order->get_status()),
                'date' => $order->get_date_created()->date_i18n(get_option('date_format')),
                'total' => $order->get_formatted_order_total(),
            );
        }
        
        return $results;
    }
}
