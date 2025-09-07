<?php
/**
 * Plugin Name: WooCommerce Abandoned Cart Tracker
 * Plugin URI: https://github.com/razvanmt/abandoned-cart
 * Description: Comprehensive WooCommerce plugin to track abandoned carts, analyze user behavior, and provide detailed statistics in the WordPress admin.
 * Version: 1.0.4
 * Author: Razvan Turc
 * Author URI: https://github.com/razvanmt
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: abandoned-cart-tracker
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * WC requires at least: 3.0
 * WC tested up to: 8.5
 * Requires Plugins: woocommerce
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Check if WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p>' . __('WooCommerce Abandoned Cart Tracker requires WooCommerce to be installed and active.', 'abandoned-cart-tracker') . '</p></div>';
    });
    return;
}

// Define plugin constants
define('ACT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ACT_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('ACT_VERSION', '1.0.4');

// Declare HPOS compatibility
add_action('before_woocommerce_init', function() {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

// Include the updater class
require_once ACT_PLUGIN_PATH . 'includes/plugin-updater.php';

// Main plugin class
class WC_Abandoned_Cart_Tracker {
    
    private static $instance = null;
    private $table_name;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'wc_abandoned_carts';
        
        add_action('init', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function init() {
        // Initialize updater (replace with your GitHub details)
        if (is_admin()) {
            new ACT_Plugin_Updater(
                __FILE__,
                'razvanmt',  // Replace with your GitHub username
                'abandoned-cart', // Replace with your repository name
                ''                       // Optional: GitHub access token for private repos
            );
        }
        
        // Hook into WooCommerce events
        add_action('woocommerce_add_to_cart', array($this, 'track_add_to_cart'), 10, 6);
        
        // Order status hooks (HPOS compatible)
        add_action('woocommerce_order_status_completed', array($this, 'mark_cart_as_converted'));
        add_action('woocommerce_order_status_processing', array($this, 'mark_cart_as_converted'));
        add_action('woocommerce_payment_complete', array($this, 'mark_cart_as_converted'));
        add_action('woocommerce_thankyou', array($this, 'mark_cart_as_converted'));
        
        // Additional HPOS-compatible hooks
        add_action('woocommerce_checkout_order_processed', array($this, 'mark_cart_as_converted'), 10, 1);
        add_action('woocommerce_store_api_checkout_order_processed', array($this, 'mark_cart_as_converted'), 10, 1);
        
        // Admin hooks
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_notices', array($this, 'activation_notice'));
        
        // AJAX handlers
        add_action('wp_ajax_act_get_stats', array($this, 'ajax_get_stats'));
        add_action('wp_ajax_act_export_data', array($this, 'ajax_export_data'));
        
        // Cleanup old records (runs daily)
        add_action('act_cleanup_old_records', array($this, 'cleanup_old_records'));
        if (!wp_next_scheduled('act_cleanup_old_records')) {
            wp_schedule_event(time(), 'daily', 'act_cleanup_old_records');
        }
    }
    
    public function activate() {
        $this->create_database_table();
        flush_rewrite_rules();
        
        // Set activation notice flag
        set_transient('act_activation_notice', true, 30);
    }
    
    public function deactivate() {
        wp_clear_scheduled_hook('act_cleanup_old_records');
    }
    
    private function create_database_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            session_id varchar(255) NOT NULL,
            user_id bigint(20) unsigned NULL,
            user_email varchar(100) NULL,
            product_id bigint(20) unsigned NOT NULL,
            product_name varchar(255) NOT NULL,
            quantity int(11) NOT NULL DEFAULT 1,
            price decimal(10,2) NOT NULL DEFAULT 0.00,
            cart_total decimal(10,2) NOT NULL DEFAULT 0.00,
            status enum('abandoned','converted','pending') NOT NULL DEFAULT 'pending',
            user_agent text NULL,
            ip_address varchar(45) NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            converted_at datetime NULL,
            order_id bigint(20) unsigned NULL,
            PRIMARY KEY (id),
            KEY session_id (session_id),
            KEY user_id (user_id),
            KEY user_email (user_email),
            KEY product_id (product_id),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    public function track_add_to_cart($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data) {
        global $wpdb;
        
        // Get session ID
        $session_id = $this->get_session_id();
        
        // Get user information
        $user_id = get_current_user_id();
        $user_email = '';
        
        if ($user_id) {
            $user = get_user_by('id', $user_id);
            $user_email = $user->user_email;
        } else {
            // Try to get email from billing if available in session
            $customer = WC()->customer;
            if ($customer) {
                $user_email = $customer->get_billing_email();
            }
        }
        
        // Get product information
        $product = wc_get_product($product_id);
        $product_name = $product ? $product->get_name() : 'Unknown Product';
        $price = $product ? $product->get_price() : 0;
        
        // Calculate cart total
        $cart_total = WC()->cart ? WC()->cart->get_total('') : 0;
        
        // Get user agent and IP
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '';
        $ip_address = $this->get_user_ip();
        
        // Check if this session/product combination already exists
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$this->table_name} 
             WHERE session_id = %s AND product_id = %d AND status = 'pending'",
            $session_id, $product_id
        ));
        
        if ($existing) {
            // Update existing record
            $wpdb->update(
                $this->table_name,
                array(
                    'quantity' => $quantity,
                    'price' => $price,
                    'cart_total' => $cart_total,
                    'updated_at' => current_time('mysql')
                ),
                array('id' => $existing->id),
                array('%d', '%f', '%f', '%s'),
                array('%d')
            );
        } else {
            // Insert new record
            $wpdb->insert(
                $this->table_name,
                array(
                    'session_id' => $session_id,
                    'user_id' => $user_id ?: null,
                    'user_email' => $user_email,
                    'product_id' => $product_id,
                    'product_name' => $product_name,
                    'quantity' => $quantity,
                    'price' => $price,
                    'cart_total' => $cart_total,
                    'status' => 'pending',
                    'user_agent' => $user_agent,
                    'ip_address' => $ip_address,
                    'created_at' => current_time('mysql')
                ),
                array('%s', '%d', '%s', '%d', '%s', '%d', '%f', '%f', '%s', '%s', '%s', '%s')
            );
        }
        
        // Mark old pending carts as abandoned after 30 minutes
        $this->mark_old_carts_abandoned();
    }
    
    public function mark_cart_as_converted($order_id) {
        global $wpdb;
        
        // Get order using WooCommerce method (HPOS compatible)
        $order = wc_get_order($order_id);
        if (!$order) return;
        
        $session_id = $this->get_session_id();
        $user_id = $order->get_user_id();
        $user_email = $order->get_billing_email();
        
        // Get order items to match against cart products
        $order_items = $order->get_items();
        $order_product_ids = array();
        
        foreach ($order_items as $item) {
            $product_id = $item->get_product_id();
            if ($product_id) {
                $order_product_ids[] = $product_id;
            }
        }
        
        // Update records based on session, user ID, email, or product IDs
        $where_conditions = array();
        $where_values = array();
        
        if (!empty($session_id)) {
            $where_conditions[] = "session_id = %s";
            $where_values[] = $session_id;
        }
        
        if ($user_id) {
            $where_conditions[] = "user_id = %d";
            $where_values[] = $user_id;
        }
        
        if (!empty($user_email)) {
            $where_conditions[] = "user_email = %s";
            $where_values[] = $user_email;
        }
        
        // Also match by product IDs in the order (more accurate for HPOS)
        if (!empty($order_product_ids)) {
            $product_placeholders = implode(',', array_fill(0, count($order_product_ids), '%d'));
            $where_conditions[] = "product_id IN ($product_placeholders)";
            $where_values = array_merge($where_values, $order_product_ids);
        }
        
        if (!empty($where_conditions)) {
            $where_clause = "(" . implode(" OR ", $where_conditions) . ") AND status IN ('pending', 'abandoned')";
            
            $wpdb->query($wpdb->prepare(
                "UPDATE {$this->table_name} 
                 SET status = 'converted', converted_at = %s, order_id = %d, updated_at = %s 
                 WHERE $where_clause",
                array_merge([current_time('mysql'), $order_id, current_time('mysql')], $where_values)
            ));
        }
    }
    
    private function mark_old_carts_abandoned() {
        global $wpdb;
        
        // Mark carts as abandoned if they're older than 30 minutes and still pending
        $wpdb->query($wpdb->prepare(
            "UPDATE {$this->table_name} 
             SET status = 'abandoned', updated_at = %s 
             WHERE status = 'pending' AND created_at < %s",
            current_time('mysql'),
            date('Y-m-d H:i:s', strtotime('-30 minutes'))
        ));
    }
    
    public function cleanup_old_records() {
        global $wpdb;
        
        // Delete records older than 90 days
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->table_name} WHERE created_at < %s",
            date('Y-m-d H:i:s', strtotime('-90 days'))
        ));
    }
    
    private function get_session_id() {
        if (WC()->session) {
            return WC()->session->get_customer_id();
        }
        
        // Fallback to WordPress session
        if (!session_id()) {
            session_start();
        }
        return session_id();
    }
    
    private function get_user_ip() {
        $ip_keys = array('HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = sanitize_text_field($_SERVER[$key]);
                // Handle comma-separated IPs
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : '';
    }
    
    // Admin functions
    public function activation_notice() {
        if (get_transient('act_activation_notice')) {
            delete_transient('act_activation_notice');
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p><strong>' . __('WooCommerce Abandoned Cart Tracker activated!', 'abandoned-cart-tracker') . '</strong> ';
            echo __('The plugin is now tracking cart events. Visit the', 'abandoned-cart-tracker') . ' ';
            echo '<a href="' . admin_url('admin.php?page=abandoned-cart-tracker') . '">' . __('Abandoned Carts dashboard', 'abandoned-cart-tracker') . '</a> ';
            echo __('to view your analytics.', 'abandoned-cart-tracker') . '</p>';
            echo '</div>';
        }
    }
    
    public function add_admin_menu() {
        add_menu_page(
            __('Abandoned Carts', 'abandoned-cart-tracker'),
            __('Abandoned Carts', 'abandoned-cart-tracker'),
            'manage_woocommerce',
            'abandoned-cart-tracker',
            array($this, 'admin_page'),
            'dashicons-cart',
            56
        );
    }
    
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'toplevel_page_abandoned-cart-tracker') {
            return;
        }
        
        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '3.9.1');
        wp_enqueue_script('act-admin', ACT_PLUGIN_URL . 'assets/admin.js', array('jquery', 'chart-js'), ACT_VERSION);
        wp_enqueue_style('act-admin', ACT_PLUGIN_URL . 'assets/admin.css', array(), ACT_VERSION);
        
        wp_localize_script('act-admin', 'actAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('act_admin_nonce'),
            'currency_symbol' => get_woocommerce_currency_symbol(),
            'currency_position' => get_option('woocommerce_currency_pos', 'left')
        ));
    }
    
    public function admin_page() {
        include ACT_PLUGIN_PATH . 'includes/admin-page.php';
    }
    
    // AJAX handlers
    public function ajax_get_stats() {
        check_ajax_referer('act_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        $period = sanitize_text_field($_POST['period'] ?? '30');
        $stats = $this->get_statistics($period);
        
        wp_send_json_success($stats);
    }
    
    public function ajax_export_data() {
        check_ajax_referer('act_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        $this->export_csv_data();
    }
    
    // Statistics functions
    public function get_statistics($period = 30) {
        global $wpdb;
        
        $date_from = date('Y-m-d H:i:s', strtotime("-{$period} days"));
        
        // Basic stats
        $total_carts = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE created_at >= %s",
            $date_from
        ));
        
        $abandoned_carts = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'abandoned' AND created_at >= %s",
            $date_from
        ));
        
        $converted_carts = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'converted' AND created_at >= %s",
            $date_from
        ));
        
        $pending_carts = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'pending' AND created_at >= %s",
            $date_from
        ));
        
        // Revenue stats
        $lost_revenue = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(cart_total) FROM {$this->table_name} WHERE status = 'abandoned' AND created_at >= %s",
            $date_from
        ));
        
        $recovered_revenue = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(cart_total) FROM {$this->table_name} WHERE status = 'converted' AND created_at >= %s",
            $date_from
        ));
        
        // Daily breakdown
        $daily_stats = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                DATE(created_at) as date,
                COUNT(*) as total,
                SUM(CASE WHEN status = 'abandoned' THEN 1 ELSE 0 END) as abandoned,
                SUM(CASE WHEN status = 'converted' THEN 1 ELSE 0 END) as converted,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
             FROM {$this->table_name} 
             WHERE created_at >= %s 
             GROUP BY DATE(created_at) 
             ORDER BY date DESC",
            $date_from
        ));
        
        // Top abandoned products
        $top_abandoned_products = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                product_name,
                COUNT(*) as count,
                SUM(cart_total) as lost_revenue
             FROM {$this->table_name} 
             WHERE status = 'abandoned' AND created_at >= %s 
             GROUP BY product_id, product_name 
             ORDER BY count DESC 
             LIMIT 10",
            $date_from
        ));
        
        return array(
            'summary' => array(
                'total_carts' => (int) $total_carts,
                'abandoned_carts' => (int) $abandoned_carts,
                'converted_carts' => (int) $converted_carts,
                'pending_carts' => (int) $pending_carts,
                'abandonment_rate' => $total_carts > 0 ? round(($abandoned_carts / $total_carts) * 100, 2) : 0,
                'conversion_rate' => $total_carts > 0 ? round(($converted_carts / $total_carts) * 100, 2) : 0,
                'lost_revenue' => (float) $lost_revenue ?: 0,
                'recovered_revenue' => (float) $recovered_revenue ?: 0
            ),
            'daily_stats' => $daily_stats,
            'top_abandoned_products' => $top_abandoned_products
        );
    }
    
    private function export_csv_data() {
        global $wpdb;
        
        $filename = 'abandoned-carts-' . date('Y-m-d') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // CSV headers - must match the SELECT column order exactly
        fputcsv($output, array(
            'ID', 'Session ID', 'User ID', 'User Email', 'Product ID', 'Product Name', 
            'Quantity', 'Price', 'Cart Total', 'Status', 'User Agent', 'IP Address', 
            'Created At', 'Updated At', 'Converted At', 'Order ID'
        ));
        
        // Get data with explicit column order to match headers
        $results = $wpdb->get_results(
            "SELECT id, session_id, user_id, user_email, product_id, product_name, 
                    quantity, price, cart_total, status, user_agent, ip_address, 
                    created_at, updated_at, converted_at, order_id 
             FROM {$this->table_name} 
             ORDER BY created_at DESC",
            ARRAY_A
        );
        
        foreach ($results as $row) {
            fputcsv($output, $row);
        }
        
        fclose($output);
        exit;
    }
}

// Initialize the plugin
WC_Abandoned_Cart_Tracker::getInstance();
