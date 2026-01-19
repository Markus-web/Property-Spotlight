<?php
/**
 * @package Property_Spotlight
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Plugin Name: Property Spotlight
 * Plugin URI: https://markusmedia.fi
 * Description: Feature specific property listings on your website with manual selection controls. Works with Linear.fi API.
 * Version: 1.2.1
 * Author: Markus Media
 * Author URI: https://markusmedia.fi
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: property-spotlight
 * Domain Path: /languages
 * Requires at least: 6.4
 * Requires PHP: 8.2
 */

define('PROPERTY_SPOTLIGHT_VERSION', '1.2.1');
define('PROPERTY_SPOTLIGHT_PLUGIN_PATH', rtrim(plugin_dir_path(__FILE__), '/'));
define('PROPERTY_SPOTLIGHT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PROPERTY_SPOTLIGHT_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Check if API credentials are configured (either from this plugin or official Linear plugin)
 */
function property_spotlight_check_api_configured() {
    // Check own settings first
    $spotlight_options = get_option('property_spotlight_settings', []);
    $has_own = !empty($spotlight_options['data_url']) && 
               (!empty($spotlight_options['api_key']) || !empty($spotlight_options['client_secret']));
    if ($has_own) {
        return true;
    }
    
    // Fall back to official Linear plugin settings
    $linear_options = get_option('linear_settings', []);
    if (!empty($linear_options['client_secret']) && !empty($linear_options['data_url'])) {
        return true;
    }
    
    return false;
}

/**
 * Display admin notice if API is not configured
 */
function property_spotlight_admin_notice() {
    // Only show on non-plugin pages
    $screen = get_current_screen();
    if ($screen && $screen->id === 'toplevel_page_property-spotlight') {
        return;
    }
    
    if (!property_spotlight_check_api_configured()) {
        ?>
        <div class="notice notice-warning">
            <p>
                <strong><?php esc_html_e('Property Spotlight:', 'property-spotlight'); ?></strong>
                <?php esc_html_e('API credentials need to be configured.', 'property-spotlight'); ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=property-spotlight')); ?>">
                    <?php esc_html_e('Configure now', 'property-spotlight'); ?>
                </a>
            </p>
        </div>
        <?php
    }
}
add_action('admin_notices', 'property_spotlight_admin_notice');

/**
 * Add settings link on plugins page
 *
 * @param array $links Existing plugin action links
 * @return array Modified links with settings added
 */
function property_spotlight_plugin_action_links(array $links): array {
    $settings_link = sprintf(
        '<a href="%s">%s</a>',
        esc_url(admin_url('admin.php?page=property-spotlight')),
        esc_html__('Settings', 'property-spotlight')
    );
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'property_spotlight_plugin_action_links');

/**
 * Activation hook
 */
function property_spotlight_activate() {
    // Create default options if they don't exist
    if (!get_option('property_spotlight_settings')) {
        add_option('property_spotlight_settings', [
            'featured_ids' => [],
            'cache_ttl' => 1800,
            'data_url' => '',
            'api_key' => '',
            'auto_expire_days' => 0,
            'auto_remove_sold' => false,
            'enable_analytics' => false,
        ], '', false); // Disable autoload for performance
    } else {
        // Ensure new fields exist in existing options
        $options = get_option('property_spotlight_settings');
        if (!isset($options['data_url'])) {
            $options['data_url'] = '';
        }
        // Migrate from client_secret to api_key if needed
        if (isset($options['client_secret']) && !isset($options['api_key'])) {
            $options['api_key'] = $options['client_secret'];
            unset($options['client_secret']);
        }
        if (!isset($options['api_key'])) {
            $options['api_key'] = '';
        }
        // Add new v1.1.0 settings
        if (!isset($options['auto_expire_days'])) {
            $options['auto_expire_days'] = 0;
        }
        if (!isset($options['auto_remove_sold'])) {
            $options['auto_remove_sold'] = false;
        }
        if (!isset($options['enable_analytics'])) {
            $options['enable_analytics'] = false;
        }
        
        // Migrate featured_ids from simple array to metadata structure
        $options['featured_ids'] = property_spotlight_migrate_featured_ids($options['featured_ids'] ?? []);
        
        update_option('property_spotlight_settings', $options);
    }
    
    // Schedule cron job for cleanup
    if (!wp_next_scheduled('property_spotlight_daily_cleanup')) {
        wp_schedule_event(time(), 'daily', 'property_spotlight_daily_cleanup');
    }
    
    // Flush rewrite rules for any custom endpoints
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'property_spotlight_activate');

/**
 * Migrate featured_ids from simple array to metadata structure
 *
 * @param array $featured_ids Current featured IDs (may be old or new format)
 * @return array Migrated featured IDs with metadata
 */
function property_spotlight_migrate_featured_ids(array $featured_ids): array {
    if (empty($featured_ids)) {
        return [];
    }
    
    // Check if already migrated (first item is an array with 'id' key)
    if (isset($featured_ids[0]) && is_array($featured_ids[0]) && isset($featured_ids[0]['id'])) {
        return $featured_ids;
    }
    
    // Migrate from simple array ['id1', 'id2'] to metadata structure
    $migrated = [];
    foreach ($featured_ids as $id) {
        if (is_string($id) || is_numeric($id)) {
            $migrated[] = [
                'id' => (string) $id,
                'added' => time(),
                'expires' => null,
                'start' => null,
                'end' => null,
            ];
        }
    }
    
    return $migrated;
}

/**
 * Deactivation hook
 */
function property_spotlight_deactivate() {
    // Clean up transients
    delete_transient('property_spotlight_listings_cache');
    
    // Remove scheduled cron job
    $timestamp = wp_next_scheduled('property_spotlight_daily_cleanup');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'property_spotlight_daily_cleanup');
    }
    
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'property_spotlight_deactivate');

/**
 * Daily cleanup cron handler
 * Removes expired listings from featured list
 */
function property_spotlight_daily_cleanup() {
    $options = get_option('property_spotlight_settings', []);
    $featured_ids = $options['featured_ids'] ?? [];
    $auto_expire_days = (int) ($options['auto_expire_days'] ?? 0);
    
    if (empty($featured_ids)) {
        return;
    }
    
    $now = time();
    $updated = false;
    $cleaned = [];
    
    foreach ($featured_ids as $item) {
        // Skip invalid entries
        if (!is_array($item) || empty($item['id'])) {
            continue;
        }
        
        // Check explicit expiration date
        if (!empty($item['expires']) && $item['expires'] < $now) {
            $updated = true;
            continue;
        }
        
        // Check schedule end date
        if (!empty($item['end']) && $item['end'] < $now) {
            $updated = true;
            continue;
        }
        
        // Check auto-expire based on days since added
        if ($auto_expire_days > 0 && !empty($item['added'])) {
            $days_since_added = ($now - $item['added']) / 86400;
            if ($days_since_added > $auto_expire_days) {
                $updated = true;
                continue;
            }
        }
        
        $cleaned[] = $item;
    }
    
    if ($updated) {
        $options['featured_ids'] = $cleaned;
        update_option('property_spotlight_settings', $options);
    }
}
add_action('property_spotlight_daily_cleanup', 'property_spotlight_daily_cleanup');

/**
 * Load plugin after all plugins are loaded
 */
add_action('plugins_loaded', function() {
    // Load dependencies
    require_once PROPERTY_SPOTLIGHT_PLUGIN_PATH . '/includes/class-property-spotlight.php';
    require_once PROPERTY_SPOTLIGHT_PLUGIN_PATH . '/includes/class-property-spotlight-api.php';
    require_once PROPERTY_SPOTLIGHT_PLUGIN_PATH . '/includes/class-property-spotlight-admin.php';
    require_once PROPERTY_SPOTLIGHT_PLUGIN_PATH . '/includes/class-property-spotlight-shortcode.php';
    require_once PROPERTY_SPOTLIGHT_PLUGIN_PATH . '/includes/class-property-spotlight-block.php';
    
    // Initialize the plugin
    Property_Spotlight::get_instance();
}, 20);
