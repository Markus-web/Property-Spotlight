<?php
/**
 * Property Spotlight Uninstall Handler
 *
 * Cleans up plugin data when the plugin is deleted.
 *
 * @package Property_Spotlight
 */

// Exit if not called by WordPress uninstall
defined('WP_UNINSTALL_PLUGIN') || exit;

// Delete plugin options
delete_option('property_spotlight_settings');

// Clean up transients
global $wpdb;

$property_spotlight_like_transient = $wpdb->esc_like('_transient_property_spotlight_') . '%';
$property_spotlight_like_timeout = $wpdb->esc_like('_transient_timeout_property_spotlight_') . '%';

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
        $property_spotlight_like_transient
    )
);

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
        $property_spotlight_like_timeout
    )
);
