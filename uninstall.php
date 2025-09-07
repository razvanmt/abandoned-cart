<?php
/**
 * Uninstall script for WooCommerce Abandoned Cart Tracker
 * 
 * This file is executed when the plugin is deleted via the WordPress admin.
 * It cleans up all plugin data from the database.
 */

// Prevent direct access
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Only run if user has proper permissions
if (!current_user_can('delete_plugins')) {
    exit;
}

global $wpdb;

// Define table name
$table_name = $wpdb->prefix . 'wc_abandoned_carts';

// Drop the plugin table
$wpdb->query("DROP TABLE IF EXISTS {$table_name}");

// Remove scheduled events
wp_clear_scheduled_hook('act_cleanup_old_records');

// Remove any plugin options (if we had any stored in wp_options)
delete_option('act_plugin_version');
delete_option('act_db_version');

// Clear any cached data
wp_cache_flush();
